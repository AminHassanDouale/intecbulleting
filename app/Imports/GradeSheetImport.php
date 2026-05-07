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
 * XLSX Grade Sheet Import — class-specific subjects/competences only.
 *
 * Matches the GradeSheetExport layout:
 *   Row 1  → Title  (skipped)
 *   Row 2  → Period label (skipped)
 *   Row 3  → Subject names (used for composite key)
 *   Row 4  → Competence labels (e.g. "CB1 / Lecture /20")
 *   Row 5+ → Student data
 *
 * Subjects loaded match niveau + classroom.code (or NULL classroom_code).
 *
 * Manual columns (Total, Moyenne /10, Moy. classe /10, DISCIPLINE, OBSERVATIONS)
 * are detected by their header text and SKIPPED from grade processing.
 * DISCIPLINE → discipline_status, OBSERVATIONS → teacher_comment.
 *
 * Composite key = "subjectname::competencecode" (lowercased) so subjects that
 * share competence codes (CB1, CB2, CB3) don't collide.
 */
class GradeSheetImport implements ToArray, WithStartRow
{
    public int   $imported    = 0;
    public int   $skipped     = 0;
    public int   $updated     = 0;
    public int   $gradesTotal = 0;
    public array $errors      = [];

    private Collection $subjects;

    /** "subjectname::competencecode" (lowercased) → competence model */
    private array $competenceMap = [];

    /** column index (0-based) → "subjectname::competencecode" */
    private array $columnMap = [];

    /** column index (0-based) for special manual columns */
    private ?int $disciplineCol = null;
    private ?int $observationsCol = null;

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
        return 1;
    }

    // ── Subject / competence loading (CLASS-SPECIFIC) ─────────────────────────

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
            'classroom_code'    => $classroom->code,
            'subjects_count'    => $this->subjects->count(),
            'competences_count' => count($this->competenceMap),
            'teacher_id'        => $this->teacherId,
        ]);
    }

    private function makeKey(string $subjectName, string $competenceCode): string
    {
        return strtolower(trim($subjectName)) . '::' . strtolower(trim($competenceCode));
    }

    // ── Main entry point ──────────────────────────────────────────────────────

    public function array(array $allRows): void
    {
        $codeRowIndex    = null;
        $subjectRowIndex = null;

        foreach ($allRows as $i => $row) {
            $firstCell = strtolower(trim((string) ($row[0] ?? '')));
            if ($firstCell === 'matricule') {
                $codeRowIndex    = $i;
                $subjectRowIndex = $i - 1;
                break;
            }
        }

        if ($codeRowIndex === null || $subjectRowIndex === null || $subjectRowIndex < 0) {
            $this->errors[] = 'Impossible de trouver la ligne des en-têtes (Matricule introuvable à la ligne 4).';
            Log::error('GradeSheetImport: header row not found');
            return;
        }

        $subjectRow = $allRows[$subjectRowIndex] ?? [];
        $codeRow    = $allRows[$codeRowIndex]    ?? [];

        $this->buildColumnMap($subjectRow, $codeRow);

        Log::info('GradeSheetImport: Column map built', [
            'columns'         => count($this->columnMap),
            'discipline_col'  => $this->disciplineCol,
            'observations_col'=> $this->observationsCol,
        ]);

        if (empty($this->columnMap)) {
            $knownSubjects = implode(', ', array_unique(array_map(
                fn($k) => explode('::', $k)[0],
                array_keys($this->competenceMap)
            )));
            $this->errors[] = 'Aucune colonne de notes reconnue. '
                . ($knownSubjects ? "Matières attendues : {$knownSubjects}." : 'Aucune matière pour ce niveau/classe.');
            return;
        }

        // Find first data row
        $dataStartIndex = null;
        for ($i = $codeRowIndex + 1; $i < count($allRows); $i++) {
            $firstCell = trim((string) ($allRows[$i][0] ?? ''));
            if ($firstCell === '' || $firstCell === '---') continue;
            if (in_array(strtolower($firstCell), ['matricule', 'n/a'], true)) continue;
            $dataStartIndex = $i;
            break;
        }

        if ($dataStartIndex === null) {
            $this->errors[] = 'Aucune ligne de données étudiants trouvée.';
            return;
        }

        for ($i = $dataStartIndex; $i < count($allRows); $i++) {
            $row       = $allRows[$i];
            $firstCell = trim((string) ($row[0] ?? ''));

            if ($firstCell === '' || $firstCell === '---') continue;

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
     * Build the mapping from column index → competence key.
     * Also detects DISCIPLINE and OBSERVATIONS manual columns.
     *
     * Manual summary columns (Total, Moyenne, Moy. classe) are recognized by
     * their header text and SKIPPED — they don't map to any competence.
     */
    private function buildColumnMap(array $subjectRow, array $codeRow): void
    {
        // Labels in the SUBJECT row that are not actual subjects
        $nonSubjectLabels = [
            'matricule', 'nom complet', 'nom', 'prénom', 'prenom',
            'date naissance', 'date de naissance',
            'totaux / moyennes', 'totaux/moyennes', 'totaux',
            'dim. pers.', 'dim pers', 'dimension personnelle',
            'observations', 'commentaire',
            '',
        ];

        // Labels in the CODE row that mark MANUAL columns (not competences)
        $manualColumnPatterns = [
            'total sur',           // "Total sur 290"
            'total',
            'moyenne sur 10',
            'moyenne sur',
            'moyenne de la classe',
            'moyenne classe',
            'moy. classe',
            'moy classe',
            'discipline',
            'observations',
            'commentaire',
        ];

        // Propagate subject name rightward across merged (null/blank) cells
        $currentSubject = null;
        $subjectForCol  = [];

        foreach ($subjectRow as $val) {
            $trimmed = trim((string) $val);
            if ($trimmed !== '' && !in_array(strtolower($trimmed), $nonSubjectLabels, true)) {
                // Strip trailing " /20" max-score hint from subject header
                $cleaned = preg_replace('#\s*/\s*\d+\s*$#u', '', $trimmed);
                $currentSubject = trim($cleaned);
            } elseif ($trimmed !== '' && in_array(strtolower($trimmed), $nonSubjectLabels, true)) {
                // Hit a non-subject label (e.g. TOTAUX / MOYENNES, DIM. PERS., OBSERVATIONS)
                // → reset so propagation stops
                $currentSubject = null;
            }
            $subjectForCol[] = $currentSubject;
        }

        $this->columnMap       = [];
        $this->disciplineCol   = null;
        $this->observationsCol = null;

        // Skip identity columns (0=Matricule, 1=Nom Complet, 2=Date Naissance)
        for ($col = 3; $col < count($codeRow); $col++) {
            $rawLabel    = trim((string) ($codeRow[$col] ?? ''));
            $lowerLabel  = strtolower($rawLabel);
            $subject     = $subjectForCol[$col] ?? null;
            $subjectRowVal = strtolower(trim((string) ($subjectRow[$col] ?? '')));

            // ── Detect MANUAL summary columns by header text ──────────────────
            $isManualColumn = false;
            foreach ($manualColumnPatterns as $pattern) {
                if (str_contains($lowerLabel, $pattern)) {
                    $isManualColumn = true;
                    break;
                }
            }

            if ($isManualColumn) {
                // Identify DISCIPLINE specifically
                if (str_contains($lowerLabel, 'discipline')) {
                    $this->disciplineCol = $col;
                }
                continue; // skip from competence map
            }

            // ── Detect OBSERVATIONS column from subject row (it's labeled in row 3) ──
            if (str_contains($subjectRowVal, 'observation') || str_contains($subjectRowVal, 'commentaire')) {
                if ($this->observationsCol === null) {
                    $this->observationsCol = $col;
                }
                continue;
            }

            // ── Skip if no subject context or empty label ─────────────────────
            if ($rawLabel === '' || $subject === null) continue;

            // ── Extract competence code: "CB1 / Lecture /20" → "CB1" ──────────
            $code = $rawLabel;
            if (str_contains($rawLabel, '/')) {
                $code = trim(explode('/', $rawLabel)[0]);
            }
            // Special case: DICTEE has no "CB" prefix
            $code = trim($code);

            $key = $this->makeKey($subject, $code);

            if (isset($this->competenceMap[$key])) {
                $this->columnMap[$col] = $key;
            } else {
                Log::warning('GradeSheetImport: unknown competence key', [
                    'col'         => $col,
                    'subject_raw' => $subject,
                    'code'        => $code,
                    'key'         => $key,
                    'known_keys'  => array_keys($this->competenceMap),
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

                if ($trimmed === '' || $rawValue === null) continue;

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

            // ── Read DISCIPLINE column (manual entry) ─────────────────────────
            if ($this->disciplineCol !== null) {
                $discipline = trim((string) ($row[$this->disciplineCol] ?? ''));
                if ($discipline !== '') {
                    $bulletin->update(['discipline_status' => $discipline]);
                }
            }

            // ── Read OBSERVATIONS column (manual entry) ───────────────────────
            if ($this->observationsCol !== null) {
                $obs = trim((string) ($row[$this->observationsCol] ?? ''));
                if ($obs !== '' && strtolower($obs) !== 'observations' && strtolower($obs) !== 'commentaire') {
                    $bulletin->update(['teacher_comment' => $obs]);
                }
            } else {
                // Fallback: last column might be observations
                $lastColIndex = count($row) - 1;
                $comment      = trim((string) ($row[$lastColIndex] ?? ''));
                if ($comment !== '' && strtolower($comment) !== 'commentaire' && strtolower($comment) !== 'observations') {
                    $bulletin->update(['teacher_comment' => $comment]);
                }
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
