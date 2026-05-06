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
 * Matches the GradeSheetExport layout exactly:
 *
 *   Row 1  → Title  (skip)
 *   Row 2  → Period label spanning grade columns  (skip)
 *   Row 3  → Subject names (used to build composite key)
 *   Row 4  → Competence labels  "CB1 / Lecture", "CB2 / Géométrie" …
 *             Col A = Matricule, Col B = Nom Complet, Col C = Date Naissance
 *   Row 5+ → Student data
 *
 * THE CORE FIX: Multiple subjects share the same competence codes (e.g. every
 * subject has CB1, CB2, CB3). Using the code alone as the column key means
 * only the LAST subject's CB1 column would be mapped — all earlier ones are
 * silently overwritten and their grades are lost.
 *
 * Solution: build the column map using "SubjectName::CompetenceCode" as a
 * composite key. The subject name is read from row 3 and propagated rightward
 * across its merged blank cells.  The competence code is extracted from row 4
 * by taking the part before the first " / " separator.
 */
class GradeSheetImport implements ToArray, WithStartRow
{
    public int   $imported    = 0;
    public int   $skipped     = 0;
    public int   $updated     = 0;
    public int   $gradesTotal = 0;
    public array $errors      = [];

    private Collection $subjects;

    /** "subjectname::competencecode" (both lowercased) → competence model */
    private array $competenceMap = [];

    /** column index (0-based) → "subjectname::competencecode" */
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
        /*
         * Row detection strategy:
         *
         *  Row 1 → title row:   first cell contains "CARNET" or similar text
         *  Row 2 → period row:  first grade cell contains "PÉRIODE"
         *  Row 3 → subject row: e.g. "FRANÇAIS", "MATHÉMATIQUES" …
         *  Row 4 → code row:    Col A = "Matricule"  ← this is our anchor
         *  Row 5+→ data
         *
         * We locate row 4 by finding the row whose first cell is "matricule"
         * (case-insensitive). Row 3 is immediately above it.
         */
        $codeRowIndex    = null;  // row 4 index in $allRows (0-based)
        $subjectRowIndex = null;  // row 3 index

        foreach ($allRows as $i => $row) {
            $firstCell = strtolower(trim((string) ($row[0] ?? '')));
            if ($firstCell === 'matricule') {
                $codeRowIndex    = $i;
                $subjectRowIndex = $i - 1; // subject names are directly above
                break;
            }
        }

        if ($codeRowIndex === null || $subjectRowIndex === null || $subjectRowIndex < 0) {
            $this->errors[] = 'Impossible de trouver la ligne des en-têtes (Matricule introuvable à la ligne 4).';
            Log::error('GradeSheetImport: header row not found');
            return;
        }

        // ── Build column map ──────────────────────────────────────────────────
        $subjectRow = $allRows[$subjectRowIndex] ?? [];
        $codeRow    = $allRows[$codeRowIndex]    ?? [];

        $this->buildColumnMap($subjectRow, $codeRow);

        Log::info('GradeSheetImport: Column map built', [
            'columns' => count($this->columnMap),
            'map'     => $this->columnMap,
        ]);

        if (empty($this->columnMap)) {
            $knownSubjects = implode(', ', array_unique(array_map(
                fn($k) => explode('::', $k)[0],
                array_keys($this->competenceMap)
            )));
            $this->errors[] = 'Aucune colonne de notes reconnue. '
                . ($knownSubjects ? "Matières attendues : {$knownSubjects}." : 'Aucune matière pour ce niveau/classe.');
            Log::error('GradeSheetImport: empty column map', [
                'competenceMap_keys' => array_keys($this->competenceMap),
            ]);
            return;
        }

        // ── Find data start (first row after code row with a non-empty col A) ─
        $dataStartIndex = null;
        for ($i = $codeRowIndex + 1; $i < count($allRows); $i++) {
            $firstCell = trim((string) ($allRows[$i][0] ?? ''));
            if ($firstCell === '' || $firstCell === '---') {
                continue;
            }
            // Skip rows that still look like headers or scale hints
            if (in_array(strtolower($firstCell), ['matricule', 'n/a'], true)) {
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

        // ── Process each student row ──────────────────────────────────────────
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
            'grades_total' => $this->gradesTotal,
            'errors_count' => count($this->errors),
        ]);
    }

    // ── Column map builder ────────────────────────────────────────────────────

    /**
     * @param array $subjectRow  Row 3 — subject names (sparse, merged cells = null)
     * @param array $codeRow     Row 4 — competence labels "CB1 / Lecture" or just "CB1"
     */
    private function buildColumnMap(array $subjectRow, array $codeRow): void
    {
        // Identity column labels to skip in the subject row
        $skipLabels = ['matricule', 'nom complet', 'nom', 'prénom', 'prenom',
                       'date naissance', 'date de naissance', 'observations',
                       'commentaire', ''];

        // Propagate subject name rightward across merged (null/blank) cells
        $currentSubject = null;
        $subjectForCol  = [];

        foreach ($subjectRow as $val) {
            $trimmed = trim((string) $val);
            if ($trimmed !== '' && !in_array(strtolower($trimmed), $skipLabels, true)) {
                $currentSubject = $trimmed;
            }
            $subjectForCol[] = $currentSubject;
        }

        $this->columnMap = [];

        // Columns 0=Matricule, 1=Nom Complet, 2=Date Naissance → skip
        for ($col = 3; $col < count($codeRow); $col++) {
            $rawLabel = trim((string) ($codeRow[$col] ?? ''));
            $subject  = $subjectForCol[$col] ?? null;

            if ($rawLabel === '' || $subject === null) {
                continue;
            }

            // Extract competence code: "CB1 / Lecture" → "CB1"
            $code = $rawLabel;
            if (str_contains($rawLabel, '/')) {
                $code = trim(explode('/', $rawLabel)[0]);
            }

            if (strtolower($code) === 'observations' || strtolower($code) === 'commentaire') {
                continue;
            }

            $key = $this->makeKey($subject, $code);

            if (isset($this->competenceMap[$key])) {
                $this->columnMap[$col] = $key;
            } else {
                Log::warning('GradeSheetImport: unknown competence key', [
                    'key'   => $key,
                    'col'   => $col,
                    'known' => array_keys($this->competenceMap),
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
                $rawValue = $row[$colIndex] ?? null;
                $trimmed  = trim((string) $rawValue);

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

            // Teacher comment is the last column that contains text
            // In our export that is the last column (after all grade cols)
            $lastColIndex = count($row) - 1;
            $comment      = trim((string) ($row[$lastColIndex] ?? ''));
            if ($comment !== '' && strtolower($comment) !== 'commentaire' && strtolower($comment) !== 'observations') {
                $bulletin->update(['teacher_comment' => $comment]);
            }

            DB::commit();
            $this->imported++;
            $this->gradesTotal += $gradesProcessed;

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
            'imported'    => $this->imported,
            'skipped'     => $this->skipped,
            'updated'     => $this->updated,
            'gradesTotal' => $this->gradesTotal,
            'errors'      => $this->errors,
        ];
    }
}
