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

/**
 * CSV Grade Sheet Import
 *
 * Reads files produced by GradeSheetExportCSV using native SplFileObject —
 * no Maatwebsite\Excel involved, so there is no XLSX mis-detection.
 *
 * Expected CSV layout:
 *   Row 1  → # meta comment   (skipped — starts with #)
 *   Row 2  → column headers   (matricule, nom, prenom, <competence codes…>, commentaire)
 *   Row 3  → # scale hint     (skipped — starts with #)
 *   Row 4+ → student data
 *
 * Usage:
 *   $importer = new GradeSheetImportCSV($classroomId, $period, $yearId, $niveauCode, $teacherId);
 *   $importer->import('/absolute/path/to/file.csv');
 *   $stats = $importer->getStats();
 */
class GradeSheetImportCSV
{
    private int   $imported = 0;
    private int   $skipped  = 0;
    private array $errors   = [];

    private Collection $subjects;
    private array      $competenceMap = []; // lowercase code → competence (with 'subject' relation)
    private array      $columnMap     = []; // column index   → lowercase competence code

    public function __construct(
        private int    $classroomId,
        private string $period,
        private int    $yearId,
        private string $niveauCode,
        private ?int   $teacherId = null,
    ) {
        $this->loadSubjectsAndCompetences();
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
                $this->competenceMap[strtolower($competence->code)] = $competence;
            }
        }
    }

    // ── Main entry point ──────────────────────────────────────────────────────

    public function import(string $filePath): void
    {
        $file = new \SplFileObject($filePath, 'r');
        $file->setFlags(
            \SplFileObject::READ_CSV      |
            \SplFileObject::READ_AHEAD    |
            \SplFileObject::SKIP_EMPTY    |
            \SplFileObject::DROP_NEW_LINE
        );
        $file->setCsvControl(',', '"', '\\');

        $headerParsed = false;
        $lineNumber   = 0;

        foreach ($file as $row) {
            $lineNumber++;

            if ($row === null || $row === [null]) {
                continue;
            }

            // Strip UTF-8 BOM from first cell on first line
            if ($lineNumber === 1 && isset($row[0])) {
                $row[0] = ltrim($row[0], "\xEF\xBB\xBF");
            }

            $firstCell = trim((string) ($row[0] ?? ''));

            // Skip entirely blank rows
            if ($firstCell === '' && count(array_filter(array_map('trim', $row))) === 0) {
                continue;
            }

            // Skip comment / meta rows (start with #)
            if (str_starts_with($firstCell, '#')) {
                continue;
            }

            // Detect and parse header row
            if (!$headerParsed && strtolower($firstCell) === 'matricule') {
                $this->parseHeaderRow($row);
                $headerParsed = true;
                continue;
            }

            // Skip rows before header is found
            if (!$headerParsed) {
                continue;
            }

            // Skip export placeholder rows
            if ($firstCell === '---') {
                continue;
            }

            try {
                $this->processStudentRow($row, $lineNumber);
            } catch (\Throwable $e) {
                $this->errors[] = "Ligne {$lineNumber}: " . $e->getMessage();
                Log::error('GradeSheetImportCSV: row error', ['line' => $lineNumber, 'error' => $e->getMessage()]);
            }
        }

        Log::info('GradeSheetImportCSV: done', [
            'imported' => $this->imported,
            'skipped'  => $this->skipped,
            'errors'   => count($this->errors),
        ]);
    }

    // ── Header parsing ────────────────────────────────────────────────────────

    private function parseHeaderRow(array $row): void
    {
        foreach ($row as $colIndex => $cell) {
            $value = strtolower(trim((string) $cell));

            // Skip identity and comment columns
            if (in_array($value, ['matricule', 'nom', 'prenom', 'commentaire', ''], true)) {
                continue;
            }

            $this->columnMap[$colIndex] = $value; // lowercase competence code
        }
    }

    // ── Student row ───────────────────────────────────────────────────────────

    private function processStudentRow(array $row, int $lineNumber): void
    {
        $matricule = trim((string) ($row[0] ?? ''));

        if ($matricule === '') {
            return;
        }

        $student = Student::where('matricule', $matricule)
            ->where('classroom_id', $this->classroomId)
            ->first();

        if (!$student) {
            $this->errors[] = "Ligne {$lineNumber}: élève introuvable (matricule: {$matricule})";
            $this->skipped++;
            return;
        }

        DB::beginTransaction();
        try {
            $bulletin        = app(CreateBulletinAction::class)->execute($student, $this->period, $this->yearId);
            $gradesProcessed = 0;

            foreach ($this->columnMap as $colIndex => $competenceCode) {
                $rawValue = $row[$colIndex] ?? null;

                if ($rawValue === null || trim((string) $rawValue) === '') {
                    continue;
                }

                $competence = $this->competenceMap[$competenceCode] ?? null;
                if (!$competence) {
                    continue;
                }

                if ($competence->subject->scale_type === 'competence') {
                    $this->saveCompetenceStatus($bulletin, $competence, $rawValue);
                } else {
                    $this->saveNumericScore($bulletin, $competence, $rawValue);
                }

                $gradesProcessed++;
            }

            // Save teacher comment (last column)
            $lastIndex = count($row) - 1;
            $comment   = trim((string) ($row[$lastIndex] ?? ''));
            if ($comment !== '' && strtolower($comment) !== 'commentaire') {
                $bulletin->update(['teacher_comment' => $comment]);
            }

            DB::commit();

            $gradesProcessed > 0 ? $this->imported++ : $this->skipped++;

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->errors[] = "Ligne {$lineNumber}: échec pour {$student->full_name} — " . $e->getMessage();
            $this->skipped++;
            Log::error('GradeSheetImportCSV: row failed', ['line' => $lineNumber, 'student' => $student->full_name, 'error' => $e->getMessage()]);
        }
    }

    // ── Grade persistence ─────────────────────────────────────────────────────

    private function saveCompetenceStatus($bulletin, $competence, mixed $raw): void
    {
        $value = strtoupper(trim((string) $raw));

        if (!in_array($value, ['A', 'EVA', 'NA'], true)) {
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

        if (!is_numeric($clean)) {
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

    // ── Stats ─────────────────────────────────────────────────────────────────

    public function getStats(): array
    {
        return ['imported' => $this->imported, 'skipped' => $this->skipped, 'errors' => $this->errors];
    }
}
