<?php

namespace App\Exports;

use App\Models\Bulletin;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Enums\PeriodEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV Grade Sheet Export
 *
 * Layout (all # rows are skipped by GradeSheetImportCSV):
 *
 *   Row 1  → # meta comment
 *   Row 2  → # subject names   e.g. "# matiere", "", "", "Mathématiques", "", "Français", ...
 *   Row 3  → # competence names e.g. "# competence", "", "", "Calcul mental", "Géométrie", ...
 *   Row 4  → # scale hint      e.g. "# max", "", "", "/20", "/20", "A/EVA/NA", ...
 *   Row 5  → COLUMN HEADERS    matricule, nom, prenom, <codes…>, commentaire  ← importer detects this
 *   Row 6+ → student data
 *
 * The importer only reads row 5 (header) and row 6+ (data). All # rows are
 * skipped, so adding subject/competence info here does not break import at all.
 */
class GradeSheetExportCSV
{
    private Collection $subjects;
    private array      $competences = [];   // flat ordered list, each carries 'subject' relation
    private string     $exportDateTime;

    public function __construct(
        private int    $classroomId,
        private string $period,
        private int    $academicYearId,
        private string $niveauCode,
        private ?int   $teacherId = null,
    ) {
        $this->exportDateTime = now()->format('Y-m-d_H-i-s');
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
                $this->competences[] = $competence;
            }
        }

        Log::info('GradeSheetExportCSV: loaded', [
            'classroom_id'      => $this->classroomId,
            'subjects_count'    => $this->subjects->count(),
            'competences_count' => count($this->competences),
            'teacher_id'        => $this->teacherId,
        ]);
    }

    // ── Download ──────────────────────────────────────────────────────────────

    public function download(): StreamedResponse
    {
        $filename = $this->getFilename();

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM — makes Excel on Windows open accented characters correctly
            fwrite($out, "\xEF\xBB\xBF");

            foreach ($this->buildRows() as $row) {
                fputcsv($out, $row, ',', '"', '\\');
            }

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-cache, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }

    // ── Row builder ───────────────────────────────────────────────────────────

    private function buildRows(): array
    {
        $rows      = [];
        $classroom = Classroom::find($this->classroomId);
        $period    = PeriodEnum::from($this->period)->label();
        $teacher   = $this->teacherId
            ? User::find($this->teacherId)?->name ?? 'Enseignant #' . $this->teacherId
            : 'Tous enseignants';

        // ── Row 1: meta comment ───────────────────────────────────────────────
        $rows[] = [
            '# FEUILLE DE NOTES CSV',
            $classroom->label,
            $period,
            'Enseignant: ' . $teacher,
            'Export: ' . now()->format('d/m/Y H:i'),
        ];

        // ── Row 2: subject names (one per competence column, blank for repeats)
        // e.g.  "# matiere" | "" | "" | "Mathématiques" | "" | "" | "Français" | ...
        $subjectRow = ['# matiere', ''];
        $lastSubjectId = null;
        foreach ($this->competences as $c) {
            // Print subject name only on its first competence column, blank the rest
            $subjectRow[] = ($c->subject->id !== $lastSubjectId)
                ? $c->subject->name
                : '';
            $lastSubjectId = $c->subject->id;
        }
        $subjectRow[] = '';
        $rows[] = $subjectRow;

        // ── Row 3: competence names ────────────────────────────────────────────
        // e.g.  "# competence" | "" | "" | "Calcul mental" | "Géométrie" | ...
        $competenceRow = ['# competence', ''];
        foreach ($this->competences as $c) {
            $competenceRow[] = $c->name;
        }
        $competenceRow[] = '';
        $rows[] = $competenceRow;

        // ── Row 4: scale / max-score hint ─────────────────────────────────────
        $maxRow = ['# max', ''];
        foreach ($this->competences as $c) {
            $maxRow[] = $c->subject->scale_type === 'competence'
                ? 'A/EVA/NA'
                : '/' . ($c->max_score ?? $c->subject->max_score ?? 20);
        }
        $maxRow[] = '';
        $rows[] = $maxRow;

        // ── Row 5: column headers (this is what the importer detects) ─────────
        $header = ['matricule', 'nom_complet'];
        foreach ($this->competences as $c) {
            $header[] = $c->code;
        }
        $header[] = 'commentaire';
        $rows[] = $header;

        // ── Rows 6+: student data ─────────────────────────────────────────────
        $students = Student::where('classroom_id', $this->classroomId)
            ->orderBy('full_name')
            ->get();

        if ($students->isEmpty()) {
            $rows[] = array_merge(
                ['---', 'Aucun élève dans cette classe'],
                array_fill(0, count($this->competences) + 1, '')
            );
            return $rows;
        }

        foreach ($students as $student) {
            $bulletin = Bulletin::where('student_id', $student->id)
                ->where('period', $this->period)
                ->where('academic_year_id', $this->academicYearId)
                ->with('grades')
                ->first();

            $row = [
                $student->matricule ?? '',
                $student->full_name ?? '',
            ];

            foreach ($this->competences as $c) {
                $cell  = '';
                $grade = $bulletin?->grades
                    ->where('competence_id', $c->id)
                    ->where('period', $this->period)
                    ->first();

                if ($grade) {
                    if ($grade->competence_status) {
                        $cell = $grade->competence_status->value;                       // A / EVA / NA
                    } elseif ($grade->score !== null) {
                        $cell = number_format((float) $grade->score, 1, '.', '');       // e.g. 14.5
                    }
                }

                $row[] = $cell;
            }

            $row[]  = $bulletin?->teacher_comment ?? '';
            $rows[] = $row;
        }

        return $rows;
    }

    // ── Filename ──────────────────────────────────────────────────────────────

    public function getFilename(): string
    {
        $classroom = Classroom::find($this->classroomId);
        $period    = PeriodEnum::from($this->period)->label();
        $label     = preg_replace('/[^A-Za-z0-9_\-]/', '_', $classroom->label ?? 'classe');
        $suffix    = $this->teacherId ? '_prof-' . $this->teacherId : '';

        return "notes_{$label}_{$period}{$suffix}_{$this->exportDateTime}.csv";
    }
}