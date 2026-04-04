<?php

namespace App\Exports;

use App\Models\Bulletin;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\Subject;
use App\Enums\PeriodEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * XLSX Grade Sheet Export
 *
 * Layout (no reference legend):
 *   Row 1  → Subject names  (merged horizontally per subject, dark-blue bg)
 *   Row 2  → Competence codes (light-blue bg)
 *   Row 3  → Max scores / scale type (grey bg)
 *   Row 4+ → Student data rows (yellow grade cells)
 *
 * Panes are frozen at D4 so identity columns + headers stay visible while scrolling.
 */
class GradeSheetExport implements FromArray, WithStyles, WithTitle, WithColumnWidths
{
    private Collection $subjects;
    private array      $subjectMap = [];  // [{subject, start_col, end_col, competence_count}]

    public function __construct(
        private int    $classroomId,
        private string $period,
        private int    $academicYearId,
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

        // Build subject map: track which spreadsheet columns belong to each subject
        // Columns: A=Matricule(1), B=Nom(2), C=Prénom(3), then competences from col 4
        $colIndex = 4;
        foreach ($this->subjects as $subject) {
            $count = $subject->competences->count();
            if ($count > 0) {
                $this->subjectMap[] = [
                    'subject'          => $subject,
                    'start_col'        => $colIndex,
                    'end_col'          => $colIndex + $count - 1,
                    'competence_count' => $count,
                ];
                $colIndex += $count;
            }
        }

        Log::info('GradeSheetExport: loaded', [
            'classroom_id'   => $this->classroomId,
            'subjects_count' => $this->subjects->count(),
            'teacher_id'     => $this->teacherId,
        ]);
    }

    // ── Array rows ────────────────────────────────────────────────────────────

    public function array(): array
    {
        $rows = [];

        // ── Row 1: Subject names (cells will be merged in styles()) ───────────
        $classroom   = Classroom::find($this->classroomId);
        $periodLabel = PeriodEnum::from($this->period)->label();
        $teacher     = $this->teacherId
            ? \App\Models\User::find($this->teacherId)?->name ?? 'Enseignant #' . $this->teacherId
            : 'Tous enseignants';

        $subjectRow = ['Matricule', 'Nom', 'Prénom'];
        foreach ($this->subjectMap as $info) {
            $subjectRow[] = $info['subject']->name;
            for ($i = 1; $i < $info['competence_count']; $i++) {
                $subjectRow[] = '';
            }
        }
        $subjectRow[] = 'Commentaire';
        $rows[] = $subjectRow;

        // ── Row 2: Competence codes ───────────────────────────────────────────
        $codeRow = ['', '', ''];
        foreach ($this->subjects as $subject) {
            foreach ($subject->competences as $competence) {
                $codeRow[] = $competence->code;
            }
        }
        $codeRow[] = '';
        $rows[] = $codeRow;

        // ── Row 3: Competence names ───────────────────────────────────────────
        $nameRow = ['', '', ''];
        foreach ($this->subjects as $subject) {
            foreach ($subject->competences as $competence) {
                $nameRow[] = $competence->name;
            }
        }
        $nameRow[] = '';
        $rows[] = $nameRow;

        // ── Row 4: Max scores / scale hint ────────────────────────────────────
        $maxRow = ['Période: ' . $periodLabel, '', ''];
        foreach ($this->subjects as $subject) {
            $isPrescolaire = $subject->scale_type === 'competence';
            foreach ($subject->competences as $competence) {
                $maxRow[] = $isPrescolaire
                    ? 'A/EVA/NA'
                    : '/' . ($competence->max_score ?? $subject->max_score ?? 20);
            }
        }
        $maxRow[] = '';
        $rows[] = $maxRow;

        // ── Rows 5+: Student data ─────────────────────────────────────────────
        $students = Student::where('classroom_id', $this->classroomId)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        Log::info('GradeSheetExport: exporting students', [
            'count'  => $students->count(),
            'period' => $this->period,
        ]);

        if ($students->isEmpty()) {
            $row = ['---', 'Aucun élève', 'dans cette classe'];
            foreach ($this->subjects as $subject) {
                foreach ($subject->competences as $competence) {
                    $row[] = '';
                }
            }
            $row[]  = '';
            $rows[] = $row;
            return $rows;
        }

        foreach ($students as $student) {
            $bulletin = Bulletin::where('student_id', $student->id)
                ->where('period', $this->period)
                ->where('academic_year_id', $this->academicYearId)
                ->with('grades')
                ->first();

            $row = [
                $student->matricule  ?? '',
                $student->last_name  ?? '',
                $student->first_name ?? '',
            ];

            foreach ($this->subjects as $subject) {
                foreach ($subject->competences as $competence) {
                    $cell  = '';
                    $grade = $bulletin?->grades
                        ->where('competence_id', $competence->id)
                        ->where('period', $this->period)
                        ->first();

                    if ($grade) {
                        if ($grade->competence_status) {
                            $cell = $grade->competence_status->value;
                        } elseif ($grade->score !== null) {
                            $cell = number_format((float) $grade->score, 1);
                        }
                    }

                    $row[] = $cell;
                }
            }

            $row[]  = $bulletin?->teacher_comment ?? '';
            $rows[] = $row;
        }

        return $rows;
    }

    // ── Styles ────────────────────────────────────────────────────────────────

    public function styles(Worksheet $sheet): array
    {
        $lastCol = $sheet->getHighestColumn();
        $lastRow = $sheet->getHighestRow();

        $subjectRow    = 1;
        $codeRow       = 2;
        $nameRow       = 3;
        $maxRow        = 4;
        $dataStartRow  = 5;

        // ── Row 1: Subject names — dark blue ──────────────────────────────────
        $sheet->getStyle("A{$subjectRow}:{$lastCol}{$subjectRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1e3a8a']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($subjectRow)->setRowHeight(32);

        // Merge identity columns A-C vertically across all 4 header rows
        $sheet->mergeCells("A{$subjectRow}:A{$maxRow}");
        $sheet->mergeCells("B{$subjectRow}:B{$maxRow}");
        $sheet->mergeCells("C{$subjectRow}:C{$maxRow}");

        // Merge subject name cells horizontally across their competence columns
        foreach ($this->subjectMap as $info) {
            $startLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($info['start_col']);
            $endLetter   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($info['end_col']);
            if ($startLetter !== $endLetter) {
                $sheet->mergeCells("{$startLetter}{$subjectRow}:{$endLetter}{$subjectRow}");
            }
        }

        // Merge comment column vertically
        if (!empty($this->subjectMap)) {
            $commentCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(
                $this->subjectMap[count($this->subjectMap) - 1]['end_col'] + 1
            );
            $sheet->mergeCells("{$commentCol}{$subjectRow}:{$commentCol}{$maxRow}");
            $sheet->getStyle("{$commentCol}{$subjectRow}")->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1e3a8a']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
        }

        // ── Row 2: Competence codes — light blue ──────────────────────────────
        $sheet->getStyle("A{$codeRow}:{$lastCol}{$codeRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => '1e3a8a'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'dbeafe']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($codeRow)->setRowHeight(20);

        // ── Row 3: Competence names — pale blue ───────────────────────────────
        $sheet->getStyle("A{$nameRow}:{$lastCol}{$nameRow}")->applyFromArray([
            'font'      => ['italic' => true, 'color' => ['rgb' => '1e3a8a'], 'size' => 9],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'eff6ff']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($nameRow)->setRowHeight(30);

        // ── Row 4: Max scores — grey ──────────────────────────────────────────
        $sheet->getStyle("A{$maxRow}:{$lastCol}{$maxRow}")->applyFromArray([
            'font'      => ['italic' => true, 'color' => ['rgb' => '6b7280'], 'size' => 9],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'f3f4f6']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($maxRow)->setRowHeight(18);

        // ── Rows 5+: Student data ─────────────────────────────────────────────
        if ($lastRow >= $dataStartRow) {

            // Identity columns A-C — light grey background
            $sheet->getStyle("A{$dataStartRow}:C{$lastRow}")->applyFromArray([
                'font'      => ['size' => 10],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'f8fafc']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            // Grade columns — yellow input cells
            foreach ($this->subjectMap as $info) {
                $startLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($info['start_col']);
                $endLetter   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($info['end_col']);

                $sheet->getStyle("{$startLetter}{$dataStartRow}:{$endLetter}{$lastRow}")->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'fefce8']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'font'      => ['size' => 10, 'bold' => true],
                ]);
            }

            // Comment column — light green
            if (!empty($this->subjectMap)) {
                $commentCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(
                    $this->subjectMap[count($this->subjectMap) - 1]['end_col'] + 1
                );
                $sheet->getStyle("{$commentCol}{$dataStartRow}:{$commentCol}{$lastRow}")->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'f0fdf4']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'font'      => ['size' => 9, 'italic' => true],
                ]);
            }

            // Alternating row shading
            for ($r = $dataStartRow; $r <= $lastRow; $r += 2) {
                $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'f1f5f9']],
                ]);

                // Re-apply yellow on alternating grade columns (overrides the stripe)
                foreach ($this->subjectMap as $info) {
                    $startLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($info['start_col']);
                    $endLetter   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($info['end_col']);
                    $sheet->getStyle("{$startLetter}{$r}:{$endLetter}{$r}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'fef9c3']],
                    ]);
                }

                // Re-apply green on alternating comment column
                if (!empty($this->subjectMap)) {
                    $commentCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(
                        $this->subjectMap[count($this->subjectMap) - 1]['end_col'] + 1
                    );
                    $sheet->getStyle("{$commentCol}{$r}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'dcfce7']],
                    ]);
                }
            }
        }

        // ── Borders ───────────────────────────────────────────────────────────
        $sheet->getStyle("A{$subjectRow}:{$lastCol}{$lastRow}")->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sheet->getStyle("A{$subjectRow}:{$lastCol}{$lastRow}")->getBorders()
            ->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);

        // Thick border below header block
        $sheet->getStyle("A{$maxRow}:{$lastCol}{$maxRow}")->getBorders()
            ->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);

        // ── Freeze panes at D5 (identity cols + all 4 header rows locked) ─────
        $sheet->freezePane("D{$dataStartRow}");

        return [];
    }

    // ── Column widths ─────────────────────────────────────────────────────────

    public function columnWidths(): array
    {
        $widths = [
            'A' => 16, // Matricule
            'B' => 20, // Nom
            'C' => 18, // Prénom
        ];

        $colIndex = 4;
        foreach ($this->subjects as $subject) {
            foreach ($subject->competences as $competence) {
                $widths[\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex)] = 12;
                $colIndex++;
            }
        }

        // Comment column
        $widths[\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex)] = 30;

        return $widths;
    }

    // ── Sheet title & filename ────────────────────────────────────────────────

    public function title(): string
    {
        $classroom = Classroom::find($this->classroomId);
        return substr("Notes {$classroom?->label} {$this->period}", 0, 31);
    }

    public function getFilename(): string
    {
        $classroom   = Classroom::find($this->classroomId);
        $periodLabel = PeriodEnum::from($this->period)->label();
        $label       = preg_replace('/[^A-Za-z0-9_\-]/', '_', $classroom->label ?? 'classe');
        $suffix      = $this->teacherId ? '_prof-' . $this->teacherId : '';

        return "notes_{$label}_{$periodLabel}{$suffix}_" . now()->format('Y-m-d_H-i-s') . '.xlsx';
    }
}
