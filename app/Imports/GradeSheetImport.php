<?php

namespace App\Imports;

use App\Actions\Bulletin\CreateBulletinAction;
use App\Enums\CompetenceStatusEnum;
use App\Models\BulletinGrade;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * XLSX Grade Sheet Import
 *
 * THE CORE FIX: Multiple subjects share the same competence codes (e.g. every
 * subject has CB1, CB2, CB3). Using the code alone as the column key means
 * only the LAST subject's CB1 column would be mapped — all earlier ones are
 * silently overwritten and their grades are lost.
 *
 * Solution: build the column map using "SubjectName::CompetenceCode" as a
 * composite key. The subject name is read from row 1 (which has the subject
 * name in the first column of each merged group, then nulls for the rest).
 * We propagate the subject name rightward across its merged blank cells.
 *
 * Export layout (4 header rows, data from row 5):
 *   Row 1  → Subject names   (merged horizontally, sparse — nulls in merged cells)
 *   Row 2  → Competence codes (CB1, CB2 … repeated per subject)
 *   Row 3  → Competence names (full names — may be blank/null)
 *   Row 4  → Max scores / scale hint (/10, /20, A/EVA/NA …)
 *   Row 5+ → Student data
 */
class GradeSheetImport implements ToArray, WithStartRow
{
    public int   $imported = 0;
    public int   $skipped  = 0;
    public int   $updated  = 0;
    public array $errors   = [];

    private Collection $subjects;

    // "subjectname::competencecode" (both lowercased) → competence model
    private array $competenceMap = [];

    // column index → "subjectname::competencecode"
    private array $columnMap = [];

    public function __construct(
        private int    $classroomId,
        private string $period,
        private int    $yearId,
        private string $niveauCode,
        private ?int   $teacherId = null,
    ) {
        $this->loadSubjectsAndCompetences();
    }

    public function startRow(): int
    {
        return 1; // Read all rows; we detect header / data rows manually
    }

    // ── Subject / competence loading ──────────────────────────────────────────

    private function loadSubjectsAndCompetences(): void
    {
        $classroom = Classroom::findOrFail($this->classroomId);

        $query = Subject::whereHas('niveau', fn($q) => $q->where('code', $this->niveauCode))
            ->where(fn($q) => $q
                ->whereNull('classroom_code')
                ->orWhere('classroom_code', $classroom->code)
            )
            ->with(['competences' => fn($q) => $q->orderBy('order')])
            ->orderBy('order');

        if ($this->teacherId) {
            $query->whereHas('teachers', fn($q) => $q->where('users.id', $this->teacherId));
        }

        $this->subjects = $query->get();

        // Build: "subjectname::competencecode" → competence (with subject relation)
        foreach ($this->subjects as $subject) {
            foreach ($subject->competences as $competence) {
                $competence->setRelation('subject', $subject);
                $key = $this->makeKey($subject->name, $competence->code);
                $this->competenceMap[$key] = $competence;
            }
        }

        Log::info('GradeSheetImport: Loaded subjects', [
            'classroom_id'      => $this->classroomId,
            'subjects_count'    => $this->subjects->count(),
            'competences_count' => count($this->competenceMap),
            'teacher_id'        => $this->teacherId,
            'keys'              => array_keys($this->competenceMap),
        ]);
    }

    private function makeKey(string $subjectName, string $competenceCode): string
    {
        return strtolower(trim($subjectName)) . '::' . strtolower(trim($competenceCode));
    }

    // ── Main entry point ──────────────────────────────────────────────────────

    public function array(array $allRows): void
    {
        // ── Step 1: Find the subject-name row (col A = "Matricule") ───────────
        // Due to vertical cell merging, PhpSpreadsheet puts "Matricule" in the
        // first row of the merged block (row 1 = subject-name row).
        $subjectNameRowIndex  = null;
        $competenceCodeRowIndex = null;

        foreach ($allRows as $i => $row) {
            $firstCell = strtolower(trim((string) ($row[0] ?? '')));
            if ($firstCell === 'matricule') {
                $subjectNameRowIndex    = $i;
                $competenceCodeRowIndex = $i + 1; // codes are on the very next row
                break;
            }
        }

        if ($competenceCodeRowIndex === null) {
            $this->errors[] = 'Impossible de trouver la ligne des en-têtes (Matricule).';
            Log::error('GradeSheetImport: header row not found');
            return;
        }

        // ── Step 2: Build column map using subject + code ─────────────────────
        $subjectRow = $allRows[$subjectNameRowIndex]  ?? [];
        $codeRow    = $allRows[$competenceCodeRowIndex] ?? [];

        $this->buildColumnMap($subjectRow, $codeRow);

        Log::info('GradeSheetImport: Column map built', [
            'columns' => count($this->columnMap),
            'map'     => $this->columnMap,
        ]);

        // ── Step 3: Find data start (first row after headers with a matricule) ─
        $dataStartIndex = null;
        for ($i = $competenceCodeRowIndex + 1; $i < count($allRows); $i++) {
            $firstCell = trim((string) ($allRows[$i][0] ?? ''));

            if ($firstCell === '' || strtolower($firstCell) === 'matricule') {
                continue;
            }

            // Skip max-score row (starts with "Période", "/", or is all slashes)
            if (str_starts_with($firstCell, 'Période') || str_starts_with($firstCell, '/')) {
                continue;
            }

            // Skip blank-looking rows (all cells are "/" or empty)
            $nonEmpty = array_filter($allRows[$i], fn($c) => trim((string) $c) !== '' && trim((string) $c) !== '/');
            if (empty($nonEmpty)) {
                continue;
            }

            $dataStartIndex = $i;
            break;
        }

        if ($dataStartIndex === null) {
            $this->errors[] = 'Aucune ligne de données étudiants trouvée.';
            Log::error('GradeSheetImport: no data rows found');
            return;
        }

        // ── Step 4: Process each student row ──────────────────────────────────
        for ($i = $dataStartIndex; $i < count($allRows); $i++) {
            $row       = $allRows[$i];
            $firstCell = trim((string) ($row[0] ?? ''));

            if ($firstCell === '' || $firstCell === '---') {
                continue;
            }

            try {
                $this->processRow($row, $i + 1);
            } catch (\Throwable $e) {
                $this->errors[] = 'Ligne ' . ($i + 1) . ': ' . $e->getMessage();
                Log::error('GradeSheetImport: row error', [
                    'row'   => $i + 1,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('GradeSheetImport: Completed', [
            'imported'     => $this->imported,
            'skipped'      => $this->skipped,
            'errors_count' => count($this->errors),
        ]);
    }

    // ── Column map builder ────────────────────────────────────────────────────

    private function buildColumnMap(array $subjectRow, array $codeRow): void
    {
        // Propagate subject name rightward across merged (null) cells
        $currentSubject = null;
        $subjectForCol  = [];

        foreach ($subjectRow as $val) {
            $trimmed = trim((string) $val);
            if ($trimmed !== '' && !in_array(strtolower($trimmed), ['matricule', 'nom complet', 'nom', 'prénom', 'prenom', 'commentaire'], true)) {
                $currentSubject = $trimmed;
            }
            $subjectForCol[] = $currentSubject;
        }

        // Build column map: col index → "subjectname::competencecode"
        $this->columnMap = [];

        for ($col = 2; $col < count($codeRow); $col++) {
            $code    = trim((string) ($codeRow[$col] ?? ''));
            $subject = $subjectForCol[$col] ?? null;

            if ($code === '' || strtolower($code) === 'commentaire' || $subject === null) {
                continue;
            }

            $key = $this->makeKey($subject, $code);

            // Only map if we actually have this competence in DB
            if (isset($this->competenceMap[$key])) {
                $this->columnMap[$col] = $key;
            } else {
                Log::warning('GradeSheetImport: unknown competence key', [
                    'key'     => $key,
                    'col'     => $col,
                    'known'   => array_keys($this->competenceMap),
                ]);
            }
        }
    }

    // ── Student row processing ────────────────────────────────────────────────

    private function processRow(array $row, int $rowNumber): void
    {
        $matricule = trim((string) ($row[0] ?? ''));
        if ($matricule === '') return;

        $student = Student::where('matricule', $matricule)
            ->where('classroom_id', $this->classroomId)
            ->first();

        if (!$student) {
            $this->errors[] = "Ligne {$rowNumber}: élève introuvable (matricule: {$matricule})";
            $this->skipped++;
            return;
        }

        DB::beginTransaction();
        try {
            $bulletin        = app(CreateBulletinAction::class)->execute($student, $this->period, $this->yearId);
            $gradesProcessed = 0;

            foreach ($this->columnMap as $colIndex => $compositeKey) {
                $rawValue   = $row[$colIndex] ?? null;
                $trimmed    = trim((string) $rawValue);

                if ($trimmed === '' || $rawValue === null) {
                    continue;
                }

                $competence = $this->competenceMap[$compositeKey] ?? null;
                if (!$competence) continue;

                $isPrescolaire = $competence->subject->scale_type === 'competence';

                if ($isPrescolaire) {
                    $this->saveCompetenceStatus($bulletin, $competence, $rawValue, $rowNumber);
                } else {
                    $this->saveNumericScore($bulletin, $competence, $rawValue, $rowNumber);
                }

                $gradesProcessed++;
            }

            // Teacher comment (last column)
            $lastColIndex = count($row) - 1;
            $comment      = trim((string) ($row[$lastColIndex] ?? ''));
            if ($comment !== '' && strtolower($comment) !== 'commentaire') {
                $bulletin->update(['teacher_comment' => $comment]);
            }

            DB::commit();
            $gradesProcessed > 0 ? $this->imported++ : $this->skipped++;

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->errors[] = "Ligne {$rowNumber}: échec pour {$student->full_name} — " . $e->getMessage();
            $this->skipped++;
            Log::error('GradeSheetImport: row failed', [
                'row'     => $rowNumber,
                'student' => $student->full_name,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    // ── Grade persistence ─────────────────────────────────────────────────────

    private function saveCompetenceStatus($bulletin, $competence, mixed $raw, int $line): void
    {
        $value = strtoupper(trim((string) $raw));

        if (!in_array($value, ['A', 'EVA', 'NA'], true)) {
            throw new \InvalidArgumentException(
                "Statut invalide « {$value} » pour {$competence->code} (attendu : A, EVA ou NA)"
            );
        }

        BulletinGrade::updateOrCreate(
            ['bulletin_id' => $bulletin->id, 'competence_id' => $competence->id, 'period' => $this->period],
            ['competence_status' => CompetenceStatusEnum::from($value), 'score' => null]
        );
    }

    private function saveNumericScore($bulletin, $competence, mixed $raw, int $line): void
    {
        $clean = str_replace([' ', ','], ['', '.'], trim((string) $raw));

        if (!is_numeric($clean)) {
            throw new \InvalidArgumentException(
                "Note non numérique « {$raw} » pour {$competence->code}"
            );
        }

        $score    = (float) $clean;
        $maxScore = (float) ($competence->max_score ?? $competence->subject->max_score ?? 20);

        if ($score < 0 || $score > $maxScore) {
            throw new \InvalidArgumentException(
                "Note {$score} hors plage 0–{$maxScore} pour {$competence->code}"
            );
        }

        BulletinGrade::updateOrCreate(
            ['bulletin_id' => $bulletin->id, 'competence_id' => $competence->id, 'period' => $this->period],
            ['score' => $score, 'competence_status' => null]
        );
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    public function getStats(): array
    {
        return [
            'imported' => $this->imported,
            'skipped'  => $this->skipped,
            'updated'  => $this->updated,
            'errors'   => $this->errors,
        ];
    }
}
