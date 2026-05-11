<?php

namespace App\Imports;

use App\Actions\Bulletin\CreateBulletinAction;
use App\Enums\CompetenceStatusEnum;
use App\Models\BulletinGrade;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\Subject;
use App\Exports\GradeSheetExport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * GradeSheetImport
 *
 * Round-trip safe importer paired with GradeSheetExport.
 *
 * Philosophy: NO computation. Whatever value is in a cell is stored as-is,
 * subject only to niveau-aware bounds on the THREE summary fields
 * (total_manuel, moyenne_10, moyenne_classe). Grade scores are never bounded.
 *
 * KEY CHANGE vs the previous version:
 *   - Bounds failures on summary fields no longer abort the whole row.
 *     They now skip the offending field, save everything else, and
 *     surface the problem in $this->errors. Previously, one bad summary
 *     value silently wiped all grades for that student.
 *   - Per-row debug log shows exactly which fields were detected, parsed,
 *     and written — so you can confirm CE1/CE2/CM1/CM2 imports are
 *     actually persisting.
 *   - Bounds are slightly looser (200.5 instead of 200.01) to tolerate
 *     a teacher who enters 200.0 plus a hair of rounding noise.
 */
class GradeSheetImport implements ToArray, WithStartRow
{
    public int   $imported    = 0;
    public int   $skipped     = 0;
    public int   $updated     = 0;
    public int   $gradesTotal = 0;
    public array $errors      = [];

    private Collection $subjects;
    /** "subjectname::competencecode" (lowercased) → Competence */
    private array $competenceMap = [];
    /** colIndex (0-based) → composite key */
    private array $columnMap = [];

    /** Routed manual columns (0-based indices) */
    private ?int $totalCol        = null;
    private ?int $moy10Col        = null;
    private ?int $moyClasseCol    = null;
    private ?int $disciplineCol   = null;
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

    public function startRow(): int { return 1; }

    // ── Subject loading (mirrors export / saisie) ────────────────────────────

    private function loadSubjectsAndCompetences(): void
    {
        $classroom   = Classroom::findOrFail($this->classroomId);
        $sectionCode = (string) $classroom->code;
        $levelCode   = preg_replace('/[AB]$/', '', $sectionCode) ?: $sectionCode;

        $query = Subject::whereHas('niveau', fn($q) => $q->where('code', $this->niveauCode))
            ->where(function ($q) use ($sectionCode, $levelCode) {
                $q->where('section_code', $sectionCode)
                  ->orWhere(function ($q2) use ($levelCode) {
                      $q2->whereNull('section_code')->where('classroom_code', $levelCode);
                  })
                  ->orWhere(function ($q3) {
                      $q3->whereNull('section_code')->whereNull('classroom_code');
                  });
            })
            ->with(['competences' => function ($q) use ($sectionCode) {
                $q->where(fn($q2) => $q2->whereNull('section_code')->orWhere('section_code', $sectionCode))
                  ->orderBy('order');
            }])
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
            'section_code'      => $sectionCode,
            'level_code'        => $levelCode,
            'niveau_code'       => $this->niveauCode,
            'resolved_key'      => $this->resolvedNiveauKey(),
            'bounds'            => $this->summaryBounds(),
            'subjects_count'    => $this->subjects->count(),
            'competences_count' => count($this->competenceMap),
            'teacher_id'        => $this->teacherId,
        ]);
    }

    private function makeKey(string $subjectName, string $competenceCode): string
    {
        return strtolower(trim($subjectName)) . '::' . strtolower(trim($competenceCode));
    }

    private function resolvedNiveauKey(): ?string
    {
        return GradeSheetExport::resolveNiveauKey($this->niveauCode);
    }

    /**
     * Niveau-aware bounds for the three summary fields (NOT for grades).
     *
     * NB the `+0.5` tolerance below: the DB columns are decimal(6,2) and
     * decimal(4,2), so 200.00 / 20.00 fit cleanly. The tolerance lets
     * teachers enter `200` exactly without floating-point comparison
     * weirdness rejecting it.
     */
    private function summaryBounds(): array
    {
        $key = $this->resolvedNiveauKey();

        if ($key === 'CP') {
            return ['total_max' => 140.5, 'moy_max' => 10.5, 'moy_cls_max' => 10.5];
        }
        if (in_array($key, ['CE1', 'CE2', 'CM1', 'CM2'], true)) {
            return ['total_max' => 200.5, 'moy_max' => 20.5, 'moy_cls_max' => 20.5];
        }
        return ['total_max' => 9999, 'moy_max' => 9999, 'moy_cls_max' => 9999];
    }

    // ── Main entry ───────────────────────────────────────────────────────────

    public function array(array $allRows): void
    {
        // Find the header row (the one starting with "Matricule")
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
            $this->errors[] = 'Impossible de trouver la ligne des en-têtes (Matricule introuvable).';
            return;
        }

        $this->buildColumnMap($allRows[$subjectRowIndex] ?? [], $allRows[$codeRowIndex] ?? []);

        if (empty($this->columnMap)
            && $this->totalCol === null
            && $this->moy10Col === null
            && $this->moyClasseCol === null
            && $this->disciplineCol === null
            && $this->observationsCol === null) {
            $known = implode(', ', array_unique(array_map(
                fn($k) => explode('::', $k)[0],
                array_keys($this->competenceMap)
            )));
            $this->errors[] = 'Aucune colonne reconnue. ' . ($known ? "Matières attendues : {$known}." : '');
            return;
        }

        // Find first data row after header
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
            }
        }

        Log::info('GradeSheetImport: completed', [
            'imported'    => $this->imported,
            'skipped'     => $this->skipped,
            'gradesTotal' => $this->gradesTotal,
            'errors'      => count($this->errors),
        ]);
    }

    // ── Column map builder ───────────────────────────────────────────────────

    private function buildColumnMap(array $subjectRow, array $codeRow): void
    {
        $nonSubjectLabels = [
            'matricule', 'nom complet', 'nom', 'prénom', 'prenom',
            'date naissance', 'date de naissance',
            'totaux / moyennes', 'totaux/moyennes', 'totaux',
            'dim. pers.', 'dim pers', 'dimension personnelle', 'dim. personnelle',
            'observations', 'observation', 'commentaire', 'commentaires',
            '',
        ];

        $currentSubject = null;
        $subjectForCol  = [];

        foreach ($subjectRow as $val) {
            $trimmed = trim((string) $val);
            $lower   = strtolower($trimmed);

            if ($trimmed !== '' && !in_array($lower, $nonSubjectLabels, true)) {
                $cleaned        = preg_replace('#\s*/\s*\d+\s*$#u', '', $trimmed);
                $currentSubject = trim($cleaned);
            } elseif ($trimmed !== '' && in_array($lower, $nonSubjectLabels, true)) {
                $currentSubject = null;
            }
            $subjectForCol[] = $currentSubject;
        }

        $this->columnMap       = [];
        $this->totalCol        = null;
        $this->moy10Col        = null;
        $this->moyClasseCol    = null;
        $this->disciplineCol   = null;
        $this->observationsCol = null;

        $maxCol = max(count($codeRow), count($subjectRow));
        for ($col = 3; $col < $maxCol; $col++) {
            $rawLabel      = trim((string) ($codeRow[$col] ?? ''));
            $lowerLabel    = strtolower($rawLabel);
            $subjectRowVal = strtolower(trim((string) ($subjectRow[$col] ?? '')));
            $subjectFor    = $subjectForCol[$col] ?? null;

            // 1) MOYENNE DE LA CLASSE — must be checked BEFORE generic moyenne
            if (preg_match('/moyenne\s+de\s+la\s+classe/i', $rawLabel)
                || preg_match('/moy(?:enne)?\.?\s+(?:de\s+la\s+)?classe/i', $rawLabel)
                || str_contains($lowerLabel, 'moyenne classe')
                || str_contains($lowerLabel, 'moy classe')
                || str_contains($lowerLabel, 'moy. classe')) {
                $this->moyClasseCol = $col;
                continue;
            }

            // 2) MOYENNE (without "classe")
            if (preg_match('/^moy(?:enne)?\.?\s*(?:sur\s+|\/\s*)?\d*\s*$/i', $rawLabel)
                || preg_match('/^moyenne\s+sur\s+\d+/i', $rawLabel)
                || preg_match('/^moy\.?\s*\/\s*\d+/i', $rawLabel)
                || $lowerLabel === 'moyenne'
                || $lowerLabel === 'moy.'
                || $lowerLabel === 'moy') {
                $this->moy10Col = $col;
                continue;
            }

            // 3) TOTAL
            if (preg_match('/^total\s*(?:sur\s+|\/\s*)?\d*\s*$/i', $rawLabel)
                || str_contains($lowerLabel, 'total sur')
                || str_contains($lowerLabel, 'total /')
                || $lowerLabel === 'total'
                || $lowerLabel === 'totaux') {
                $this->totalCol = $col;
                continue;
            }

            // 4) DISCIPLINE / DIM. PERS.
            if (str_contains($lowerLabel, 'discipline')
                || str_contains($subjectRowVal, 'dim. pers.')
                || str_contains($subjectRowVal, 'dim pers')
                || str_contains($subjectRowVal, 'dimension personnelle')) {
                $this->disciplineCol = $col;
                continue;
            }

            // 5) OBSERVATIONS / COMMENTAIRE
            if (str_contains($subjectRowVal, 'observation')
                || str_contains($subjectRowVal, 'commentaire')
                || str_contains($lowerLabel, 'observations')
                || str_contains($lowerLabel, 'observation')
                || str_contains($lowerLabel, 'commentaire')) {
                $this->observationsCol = $col;
                continue;
            }

            // ── Competence mapping ─────────────────────────────────────────
            if ($rawLabel === '' || $subjectFor === null) continue;

            $code = $rawLabel;
            if (str_contains($rawLabel, '/')) {
                $code = trim(explode('/', $rawLabel)[0]);
            }

            $key = $this->makeKey($subjectFor, $code);
            if (isset($this->competenceMap[$key])) {
                $this->columnMap[$col] = $key;
            } else {
                Log::warning('GradeSheetImport: unknown competence', [
                    'col'     => $col,
                    'subject' => $subjectFor,
                    'code'    => $code,
                    'key'     => $key,
                ]);
            }
        }

        Log::info('GradeSheetImport: column map built', [
            'columns'          => count($this->columnMap),
            'total_col'        => $this->totalCol,
            'moy10_col'        => $this->moy10Col,
            'moy_classe_col'   => $this->moyClasseCol,
            'discipline_col'   => $this->disciplineCol,
            'observations_col' => $this->observationsCol,
        ]);
    }

    // ── Row processing ───────────────────────────────────────────────────────

    /**
     * Process a single student row.
     *
     * NEW BEHAVIOUR: bounds failures on summary fields no longer roll back
     * the whole row. They record the error in $this->errors, skip that
     * field's write, and save everything else (grades + valid summary
     * fields + discipline + observations). This way a single typo in one
     * summary cell can't silently destroy the student's grades.
     */
    private function processRow(array $row, int $rowNumber): void
    {
        $matricule = trim((string) ($row[0] ?? ''));
        if ($matricule === '') return;

        $student = Student::where('matricule', $matricule)
            ->where('classroom_id', $this->classroomId)
            ->first();

        if (! $student) {
            $this->errors[] = "Ligne {$rowNumber}: élève introuvable (matricule: {$matricule})";
            $this->skipped++;
            return;
        }

        DB::beginTransaction();
        try {
            $bulletin        = app(CreateBulletinAction::class)->execute($student, $this->period, $this->yearId);
            $gradesProcessed = 0;

            // ── Competence grades ───────────────────────────────────────────
            foreach ($this->columnMap as $colIndex => $compositeKey) {
                $rawValue = $row[$colIndex] ?? null;
                $trimmed  = trim((string) $rawValue);
                if ($trimmed === '') continue;

                $competence = $this->competenceMap[$compositeKey] ?? null;
                if (! $competence) continue;

                if ($competence->subject->scale_type === 'competence') {
                    $this->saveCompetenceStatus($bulletin, $competence, $rawValue);
                } else {
                    $upperTrimmed = strtoupper($trimmed);
                    if (in_array($upperTrimmed, ['A', 'EVA', 'NA'], true)) {
                        $this->saveCompetenceStatus($bulletin, $competence, $upperTrimmed);
                    } else {
                        $this->saveNumericScore($bulletin, $competence, $rawValue);
                    }
                }
                $gradesProcessed++;
            }

            // ── Manual summary fields ───────────────────────────────────────
            // Bounds failures here no longer kill the row — they skip the
            // offending field and continue.
            $bounds  = $this->summaryBounds();
            $updates = [];
            $summaryDebug = [];

            // ─── Total ─────────────────────────────────────────────────────
            if ($this->totalCol !== null) {
                $rawCell = $row[$this->totalCol] ?? null;
                $val     = $this->parseNumeric($rawCell);
                if ($val !== null && $val > $bounds['total_max']) {
                    $this->errors[] = "Ligne {$rowNumber}: total {$val} > {$bounds['total_max']} ignoré";
                    $summaryDebug['total_manuel'] = "REJECTED ({$val} > {$bounds['total_max']})";
                } else {
                    $updates['total_manuel'] = $val;
                    $summaryDebug['total_manuel'] = $val ?? 'null';
                }
            }

            // ─── Moyenne ───────────────────────────────────────────────────
            if ($this->moy10Col !== null) {
                $rawCell = $row[$this->moy10Col] ?? null;
                $val     = $this->parseNumeric($rawCell);
                if ($val !== null && $val > $bounds['moy_max']) {
                    $this->errors[] = "Ligne {$rowNumber}: moyenne {$val} > {$bounds['moy_max']} ignorée";
                    $summaryDebug['moyenne_10'] = "REJECTED ({$val} > {$bounds['moy_max']})";
                } else {
                    $updates['moyenne_10'] = $val;
                    $summaryDebug['moyenne_10'] = $val ?? 'null';
                }
            }

            // ─── Moyenne de la classe ─────────────────────────────────────
            if ($this->moyClasseCol !== null) {
                $rawCell = $row[$this->moyClasseCol] ?? null;
                $val     = $this->parseNumeric($rawCell);
                if ($val !== null && $val > $bounds['moy_cls_max']) {
                    $this->errors[] = "Ligne {$rowNumber}: moyenne classe {$val} > {$bounds['moy_cls_max']} ignorée";
                    $summaryDebug['moyenne_classe'] = "REJECTED ({$val} > {$bounds['moy_cls_max']})";
                } else {
                    $updates['moyenne_classe'] = $val;
                    $summaryDebug['moyenne_classe'] = $val ?? 'null';
                }
            }

            // ─── Discipline (text) ────────────────────────────────────────
            if ($this->disciplineCol !== null) {
                $val = trim((string) ($row[$this->disciplineCol] ?? ''));
                $updates['discipline_status'] = $val !== '' ? $val : null;
                $summaryDebug['discipline_status'] = $updates['discipline_status'] ?? 'null';
            }

            // ─── Observations (text) ──────────────────────────────────────
            if ($this->observationsCol !== null) {
                $val   = trim((string) ($row[$this->observationsCol] ?? ''));
                $lower = strtolower($val);
                $updates['teacher_comment'] = ($val !== '' && !in_array($lower, ['observations', 'commentaire', 'observation'], true))
                    ? $val
                    : null;
                $summaryDebug['teacher_comment'] = $updates['teacher_comment'] !== null ? 'set' : 'null';
            }

            if (! empty($updates)) {
                $bulletin->update($updates);
            }

            DB::commit();
            $this->imported++;
            $this->gradesTotal += $gradesProcessed;

            // Per-row debug log — see exactly what was persisted for each
            // student. Look in storage/logs/laravel.log if anything seems
            // off after an import.
            Log::info('GradeSheetImport: row persisted', [
                'row'             => $rowNumber,
                'student'         => $student->full_name,
                'matricule'       => $matricule,
                'bulletin_id'     => $bulletin->id,
                'grades_processed'=> $gradesProcessed,
                'summary_fields'  => $summaryDebug,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->errors[] = "Ligne {$rowNumber}: échec pour {$student->full_name} — " . $e->getMessage();
            $this->skipped++;
            Log::error('GradeSheetImport: row failed', [
                'row'     => $rowNumber,
                'student' => $student->full_name,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Parse "12,5" / "12.5" / " 12  " / " " / null into a float or null.
     */
    private function parseNumeric(mixed $raw): ?float
    {
        if ($raw === null) return null;
        $clean = str_replace([' ', ','], ['', '.'], trim((string) $raw));
        if ($clean === '') return null;
        return is_numeric($clean) ? (float) $clean : null;
    }

    private function saveCompetenceStatus($bulletin, $competence, mixed $raw): void
    {
        $value = strtoupper(trim((string) $raw));
        if (! in_array($value, ['A', 'EVA', 'NA'], true)) {
            throw new \InvalidArgumentException(
                "Statut invalide « {$value} » pour {$competence->code} (attendu : A, EVA ou NA)"
            );
        }

        BulletinGrade::updateOrCreate(
            ['bulletin_id' => $bulletin->id, 'competence_id' => $competence->id, 'period' => $this->period],
            ['competence_status' => CompetenceStatusEnum::from($value), 'score' => null]
        );
    }

    /**
     * Saves a numeric score without any bounds check.
     * The value is stored exactly as provided in the spreadsheet.
     */
    private function saveNumericScore($bulletin, $competence, mixed $raw): void
    {
        $clean = str_replace([' ', ','], ['', '.'], trim((string) $raw));
        if (! is_numeric($clean)) {
            throw new \InvalidArgumentException(
                "Note non numérique « {$raw} » pour {$competence->code}"
            );
        }

        BulletinGrade::updateOrCreate(
            ['bulletin_id' => $bulletin->id, 'competence_id' => $competence->id, 'period' => $this->period],
            ['score' => (float) $clean, 'competence_status' => null]
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
