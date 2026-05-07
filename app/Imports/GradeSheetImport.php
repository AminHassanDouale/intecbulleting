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
 * GradeSheetImport — section-aware, ignores manual summary columns.
 *
 * Subjects loaded match the SAME logic as GradeSheetExport:
 *   1) section_code = classroom.code, OR
 *   2) classroom_code = level (CPA → CP) when section_code is NULL, OR
 *   3) global subjects (no section, no level) within the niveau.
 *
 * Manual columns detected by header text and routed (NOT treated as competences):
 *   - "Total sur …"            → bulletin->total_manuel
 *   - "Moyenne sur 10"          → bulletin->moyenne_10
 *   - "Moyenne de la classe …"  → bulletin->moyenne_classe
 *   - "DISCIPLINE"              → bulletin->discipline_status
 *   - "OBSERVATIONS"            → bulletin->teacher_comment
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
    private ?int $totalCol         = null;
    private ?int $moy10Col         = null;
    private ?int $moyClasseCol     = null;
    private ?int $disciplineCol    = null;
    private ?int $observationsCol  = null;

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

    // ── Subject loading (section-aware, mirrors export) ──────────────────────

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
            'subjects_count'    => $this->subjects->count(),
            'competences_count' => count($this->competenceMap),
            'teacher_id'        => $this->teacherId,
        ]);
    }

    private function makeKey(string $subjectName, string $competenceCode): string
    {
        return strtolower(trim($subjectName)) . '::' . strtolower(trim($competenceCode));
    }

    // ── Main entry ───────────────────────────────────────────────────────────

    public function array(array $allRows): void
    {
        $codeRowIndex    = null;
        $subjectRowIndex = null;

        // Find the header row whose first cell is "Matricule"
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

        if (empty($this->columnMap)) {
            $known = implode(', ', array_unique(array_map(
                fn($k) => explode('::', $k)[0],
                array_keys($this->competenceMap)
            )));
            $this->errors[] = 'Aucune colonne de notes reconnue. ' . ($known ? "Matières attendues : {$known}." : '');
            return;
        }

        // Locate first data row
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
        // Labels in the SUBJECT row (row 3) that are NOT subject names
        $nonSubjectLabels = [
            'matricule', 'nom complet', 'nom', 'prénom', 'prenom',
            'date naissance', 'date de naissance',
            'totaux / moyennes', 'totaux/moyennes', 'totaux',
            'dim. pers.', 'dim pers', 'dimension personnelle',
            'observations', 'commentaire',
            '',
        ];

        // Propagate subject names rightward across merged blank cells.
        // Reset to null when a non-subject label is encountered.
        $currentSubject = null;
        $subjectForCol  = [];

        foreach ($subjectRow as $val) {
            $trimmed = trim((string) $val);
            $lower   = strtolower($trimmed);

            if ($trimmed !== '' && !in_array($lower, $nonSubjectLabels, true)) {
                // Strip trailing "/20" max-score hint from subject header
                $cleaned        = preg_replace('#\s*/\s*\d+\s*$#u', '', $trimmed);
                $currentSubject = trim($cleaned);
            } elseif ($trimmed !== '' && in_array($lower, $nonSubjectLabels, true)) {
                $currentSubject = null;
            }
            $subjectForCol[] = $currentSubject;
        }

        // Reset all routed-column trackers
        $this->columnMap        = [];
        $this->totalCol         = null;
        $this->moy10Col         = null;
        $this->moyClasseCol     = null;
        $this->disciplineCol    = null;
        $this->observationsCol  = null;

        // Iterate over the columns, skipping identity (0, 1, 2)
        $maxCol = max(count($codeRow), count($subjectRow));
        for ($col = 3; $col < $maxCol; $col++) {
            $rawLabel      = trim((string) ($codeRow[$col] ?? ''));
            $lowerLabel    = strtolower($rawLabel);
            $subjectRowVal = strtolower(trim((string) ($subjectRow[$col] ?? '')));
            $subjectFor    = $subjectForCol[$col] ?? null;

            // ── Manual summary column detection ────────────────────────────
            // Order matters: more specific patterns first.
            if (str_contains($lowerLabel, 'moyenne de la classe')
                || str_contains($lowerLabel, 'moyenne classe')
                || str_contains($lowerLabel, 'moy. classe')
                || str_contains($lowerLabel, 'moy classe')) {
                $this->moyClasseCol = $col;
                continue;
            }
            if (str_contains($lowerLabel, 'moyenne sur 10')
                || str_contains($lowerLabel, 'moyenne sur')
                || (str_contains($lowerLabel, 'moyenne') && !str_contains($lowerLabel, 'classe'))) {
                $this->moy10Col = $col;
                continue;
            }
            if (str_contains($lowerLabel, 'total sur')
                || $lowerLabel === 'total') {
                $this->totalCol = $col;
                continue;
            }
            if (str_contains($lowerLabel, 'discipline')
                || str_contains($subjectRowVal, 'dim. pers.')
                || str_contains($subjectRowVal, 'dim pers')) {
                $this->disciplineCol = $col;
                continue;
            }
            if (str_contains($subjectRowVal, 'observation')
                || str_contains($subjectRowVal, 'commentaire')
                || str_contains($lowerLabel, 'observations')
                || str_contains($lowerLabel, 'commentaire')) {
                $this->observationsCol = $col;
                continue;
            }

            // ── Competence mapping ─────────────────────────────────────────
            if ($rawLabel === '' || $subjectFor === null) continue;

            // Extract competence code: "CB1 / Lecture /20" → "CB1"
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
            'columns'         => count($this->columnMap),
            'total_col'       => $this->totalCol,
            'moy10_col'       => $this->moy10Col,
            'moy_classe_col'  => $this->moyClasseCol,
            'discipline_col'  => $this->disciplineCol,
            'observations_col'=> $this->observationsCol,
        ]);
    }

    // ── Row processing ───────────────────────────────────────────────────────

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
                    $this->saveNumericScore($bulletin, $competence, $rawValue);
                }
                $gradesProcessed++;
            }

            // ── Manual summary fields → bulletin columns ────────────────────
            $updates = [];

            if ($this->totalCol !== null) {
                $val = $this->parseNumeric($row[$this->totalCol] ?? null);
                $updates['total_manuel'] = $val;
            }
            if ($this->moy10Col !== null) {
                $val = $this->parseNumeric($row[$this->moy10Col] ?? null);
                $updates['moyenne_10'] = $val;
            }
            if ($this->moyClasseCol !== null) {
                $val = $this->parseNumeric($row[$this->moyClasseCol] ?? null);
                $updates['moyenne_classe'] = $val;
            }
            if ($this->disciplineCol !== null) {
                $val = trim((string) ($row[$this->disciplineCol] ?? ''));
                $updates['discipline_status'] = $val !== '' ? $val : null;
            }
            if ($this->observationsCol !== null) {
                $val   = trim((string) ($row[$this->observationsCol] ?? ''));
                $lower = strtolower($val);
                $updates['teacher_comment'] = ($val !== '' && !in_array($lower, ['observations', 'commentaire'], true))
                    ? $val
                    : null;
            }

            if (! empty($updates)) {
                $bulletin->update($updates);
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

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function parseNumeric(mixed $raw): ?float
    {
        if ($raw === null) return null;
        $clean = str_replace([' ', ','], ['', '.'], trim((string) $raw));
        if ($clean === '') return null;
        return is_numeric($clean) ? (float) $clean : null;
    }

    // ── Grade persistence ────────────────────────────────────────────────────

    private function saveCompetenceStatus($bulletin, $competence, mixed $raw): void
    {
        $value = strtoupper(trim((string) $raw));
        if (! in_array($value, ['A', 'EVA', 'NA'], true)) {
            throw new \InvalidArgumentException("Statut invalide « {$value} » pour {$competence->code} (attendu : A, EVA ou NA)");
        }

        BulletinGrade::updateOrCreate(
            ['bulletin_id' => $bulletin->id, 'competence_id' => $competence->id, 'period' => $this->period],
            ['competence_status' => CompetenceStatusEnum::from($value), 'score' => null]
        );
    }

    private function saveNumericScore($bulletin, $competence, mixed $raw): void
    {
        $clean = str_replace([' ', ','], ['', '.'], trim((string) $raw));
        if (! is_numeric($clean)) {
            throw new \InvalidArgumentException("Note non numérique « {$raw} » pour {$competence->code}");
        }

        $score    = (float) $clean;
        $maxScore = (float) ($competence->max_score ?? $competence->subject->max_score ?? 20);

        if ($score < 0 || $score > $maxScore) {
            throw new \InvalidArgumentException("Note {$score} hors plage 0–{$maxScore} pour {$competence->code}");
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
