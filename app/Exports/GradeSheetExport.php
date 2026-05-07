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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * XLSX Grade Sheet Export — class-specific subjects/competences only.
 *
 * Subjects are filtered to those that match the selected classroom's code
 * (or are global with classroom_code = NULL for the niveau). Each class
 * (CP / CE1 / CE2 / CM1 / CM2 / etc.) only sees its own catalogue.
 *
 * Column layout:
 *   A  = Matricule
 *   B  = Nom Complet
 *   C  = Date Naissance
 *   D… = Competence grades (one column per competence, ordered by subject order)
 *   +0 = Total sur {totalMaxScore}      ← manual entry (header shows max)
 *   +1 = Moyenne sur 10                  ← manual entry
 *   +2 = Moyenne de la classe sur 10     ← manual entry
 *   +3 = DISCIPLINE (DIM. PERS.)         ← manual entry
 *   +4 = OBSERVATIONS                    ← manual entry
 *
 * Header rows:
 *   Row 1 → "CARNET INTEC PRIMAIRE - CLASSE {label} - {year}"
 *   Row 2 → "PÉRIODE N (Trimestre N)"
 *   Row 3 → Subject names (merged per group) + "TOTAUX / MOYENNES"
 *           + "DIM. PERS." + "OBSERVATIONS"
 *   Row 4 → Identity labels + Competence labels (with max if numeric)
 *           + "Total sur {max}" + "Moyenne sur 10" + "Moy. classe/10"
 *           + "DISCIPLINE"
 *   Row 5+→ Student data (grades pre-filled if already saved; manual cols empty)
 *
 * The importer reads rows 3 + 4 only (subject + competence code).
 * Manual summary / DIM. PERS. / OBSERVATIONS columns are ignored on import.
 */
class GradeSheetExport implements FromArray, WithStyles, WithTitle, WithColumnWidths
{
    private Collection $subjects;

    /** [{subject, start_col, end_col, competence_count}] — 1-based column indices */
    private array $subjectMap = [];

    /** 1-based index of the first summary column (Total) */
    private int $summaryStartCol;

    /** Grand total of all numeric competence max scores for this class */
    private int $totalMaxScore = 0;

    public function __construct(
        private int    $classroomId,
        private string $period,
        private int    $academicYearId,
        private string $niveauCode,
        private ?int   $teacherId = null,
    ) {
        $this->loadSubjectsAndCompetences();
    }

    // ── Subject / competence loading (CLASS-SPECIFIC) ────────────────────────

    private function loadSubjectsAndCompetences(): void
    {
        $classroom = Classroom::findOrFail($this->classroomId);

        // Filter subjects by niveau + classroom code (or global for niveau)
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

        // Build column map: A=1, B=2, C=3 → competences start at col 4
        $colIndex = 4;
        foreach ($this->subjects as $subject) {
            $count = $subject->competences->count();
            if ($count === 0) continue;

            $this->subjectMap[] = [
                'subject'          => $subject,
                'start_col'        => $colIndex,
                'end_col'          => $colIndex + $count - 1,
                'competence_count' => $count,
            ];

            // Numeric subjects contribute to grand total
            if ($subject->scale_type !== 'competence') {
                // If competences have individual max_score, sum them.
                // Otherwise, the subject's max_score is the pool for all its competences.
                $hasIndividualMax = $subject->competences->whereNotNull('max_score')->isNotEmpty();
                if ($hasIndividualMax) {
                    foreach ($subject->competences as $c) {
                        $this->totalMaxScore += (int) ($c->max_score ?? 0);
                    }
                } else {
                    // Shared pool — count subject max_score once
                    $this->totalMaxScore += (int) ($subject->max_score ?? 0);
                }
            }

            $colIndex += $count;
        }

        $this->summaryStartCol = $colIndex;

        Log::info('GradeSheetExport: loaded', [
            'classroom_id'    => $this->classroomId,
            'classroom_code'  => $classroom->code,
            'subjects_count'  => $this->subjects->count(),
            'teacher_id'      => $this->teacherId,
            'summaryStartCol' => $this->summaryStartCol,
            'totalMaxScore'   => $this->totalMaxScore,
        ]);
    }

    // ── Column letter shortcuts ───────────────────────────────────────────────

    private function col(int $oneBasedIndex): string
    {
        return Coordinate::stringFromColumnIndex($oneBasedIndex);
    }

    private function totalCol(): string      { return $this->col($this->summaryStartCol);     }
    private function moy10Col(): string      { return $this->col($this->summaryStartCol + 1); }
    private function moyClassCol(): string   { return $this->col($this->summaryStartCol + 2); }
    private function disciplineCol(): string { return $this->col($this->summaryStartCol + 3); }
    private function obsCol(): string        { return $this->col($this->summaryStartCol + 4); }

    // ── Array rows ────────────────────────────────────────────────────────────

    public function array(): array
    {
        $rows      = [];
        $classroom = Classroom::find($this->classroomId);
        $year      = \App\Models\AcademicYear::find($this->academicYearId);

        $periodLabel = $this->periodLongLabel(PeriodEnum::from($this->period));
        $totalLabel  = 'Total sur ' . ($this->totalMaxScore ?: '?');

        // Total columns = summaryStartCol + 4 (the 5 summary columns)
        $totalColCount = $this->summaryStartCol + 4;

        // ── Row 1: Title ──────────────────────────────────────────────────────
        $titleRow    = array_fill(0, $totalColCount, '');
        $titleRow[0] = 'CARNET INTEC PRIMAIRE - CLASSE '
            . strtoupper($classroom->label ?? '')
            . ' - ' . ($year->label ?? '');
        $rows[] = $titleRow;

        // ── Row 2: Period label ───────────────────────────────────────────────
        $periodRow    = array_fill(0, $totalColCount, '');
        $periodRow[3] = $periodLabel;
        $rows[] = $periodRow;

        // ── Row 3: Subject group headers ──────────────────────────────────────
        $subjectRow = array_fill(0, $totalColCount, '');
        foreach ($this->subjectMap as $info) {
            // Subject header includes max score for clarity
            $subjectName = strtoupper($info['subject']->name);
            if ($info['subject']->scale_type !== 'competence' && $info['subject']->max_score) {
                $subjectName .= ' /' . (int) $info['subject']->max_score;
            }
            $subjectRow[$info['start_col'] - 1] = $subjectName;
        }
        $subjectRow[$this->summaryStartCol - 1]     = 'TOTAUX / MOYENNES';
        $subjectRow[$this->summaryStartCol + 3 - 1] = 'DIM. PERS.';
        $subjectRow[$this->summaryStartCol + 4 - 1] = 'OBSERVATIONS';
        $rows[] = $subjectRow;

        // ── Row 4: Competence labels + summary column headers ─────────────────
        $codeRow = ['Matricule', 'Nom Complet', 'Date Naissance'];
        foreach ($this->subjects as $subject) {
            foreach ($subject->competences as $competence) {
                $label = $competence->code;

                // Append the description name if present
                if ($competence->name && strtolower($competence->name) !== strtolower($competence->code)) {
                    $label = $competence->code . ' / ' . $competence->name;
                } elseif ($competence->description) {
                    // Use a short version of the description
                    $shortDesc = mb_substr($competence->description, 0, 30);
                    if (mb_strlen($competence->description) > 30) {
                        $shortDesc .= '…';
                    }
                    $label = $competence->code . ' / ' . $shortDesc;
                }

                // Append max score if competence has its own
                if ($subject->scale_type !== 'competence' && $competence->max_score) {
                    $label .= ' /' . (int) $competence->max_score;
                }

                $codeRow[] = $label;
            }
        }
        $codeRow[] = $totalLabel;                       // Total sur {max}
        $codeRow[] = 'Moyenne sur 10';
        $codeRow[] = 'Moyenne de la classe sur 10';
        $codeRow[] = 'DISCIPLINE';
        $codeRow[] = '';                                // OBSERVATIONS label is in row 3
        $rows[] = $codeRow;

        // ── Rows 5+: Student data ─────────────────────────────────────────────
        $students = Student::where('classroom_id', $this->classroomId)
            ->orderBy('full_name')
            ->get();

        if ($students->isEmpty()) {
            $emptyRow    = array_fill(0, $totalColCount, '');
            $emptyRow[0] = '---';
            $emptyRow[1] = 'Aucun élève dans cette classe';
            $rows[]      = $emptyRow;
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
                $student->birth_date
                    ? \Carbon\Carbon::parse($student->birth_date)->format('d/m/Y')
                    : '',
            ];

            // Pre-fill existing grades
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

            // ── MANUAL ENTRY COLUMNS — left empty for the user to fill ────────
            $row[] = '';                                          // Total sur {max}
            $row[] = '';                                          // Moyenne sur 10
            $row[] = '';                                          // Moyenne de la classe sur 10
            $row[] = $bulletin?->discipline_status ?? '';         // DISCIPLINE (pre-fill if exists)
            $row[] = $bulletin?->teacher_comment ?? '';           // OBSERVATIONS (pre-fill if exists)

            $rows[] = $row;
        }

        return $rows;
    }

    // ── Styles ────────────────────────────────────────────────────────────────

    public function styles(Worksheet $sheet): array
    {
        $lastCol     = $this->obsCol();
        $lastRow     = $sheet->getHighestRow();
        $titleR      = 1;
        $periodR     = 2;
        $subjectR    = 3;
        $codeR       = 4;
        $dataStart   = 5;

        $firstGradeCol  = $this->col(4);
        $totalC         = $this->totalCol();
        $moy10C         = $this->moy10Col();
        $moyClassC      = $this->moyClassCol();
        $disciplineC    = $this->disciplineCol();
        $obsC           = $this->obsCol();

        // ── Row 1: Title ──────────────────────────────────────────────────────
        $sheet->mergeCells("A{$titleR}:{$lastCol}{$titleR}");
        $sheet->getStyle("A{$titleR}:{$lastCol}{$titleR}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1e3a8a']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($titleR)->setRowHeight(28);

        // ── Row 2: Period label ───────────────────────────────────────────────
        $sheet->mergeCells("A{$periodR}:C{$periodR}");
        if ($firstGradeCol !== $disciplineC) {
            $sheet->mergeCells("{$firstGradeCol}{$periodR}:{$disciplineC}{$periodR}");
        }
        $sheet->getStyle("A{$periodR}:{$lastCol}{$periodR}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1e40af']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($periodR)->setRowHeight(24);

        // ── Row 3: Subject group headers ──────────────────────────────────────
        $sheet->mergeCells("A{$subjectR}:C{$subjectR}");

        foreach ($this->subjectMap as $info) {
            $s = $this->col($info['start_col']);
            $e = $this->col($info['end_col']);
            if ($s !== $e) {
                $sheet->mergeCells("{$s}{$subjectR}:{$e}{$subjectR}");
            }
        }

        // Merge TOTAUX / MOYENNES across its 3 sub-columns
        $sheet->mergeCells("{$totalC}{$subjectR}:{$moyClassC}{$subjectR}");

        $sheet->getStyle("A{$subjectR}:{$lastCol}{$subjectR}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1d4ed8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getStyle("{$disciplineC}{$subjectR}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1e40af']],
        ]);
        $sheet->getStyle("{$obsC}{$subjectR}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '0f766e']],
        ]);
        $sheet->getRowDimension($subjectR)->setRowHeight(30);

        // ── Row 4: Competence + summary labels ────────────────────────────────
        $sheet->getStyle("A{$codeR}:{$lastCol}{$codeR}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '1e3a8a']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'dbeafe']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getStyle("A{$codeR}:C{$codeR}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '1e3a8a']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'bfdbfe']],
        ]);
        $sheet->getStyle("{$totalC}{$codeR}:{$moyClassC}{$codeR}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'e0e7ff']],
            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '3730a3']],
        ]);
        $sheet->getStyle("{$disciplineC}{$codeR}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'e0e7ff']],
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '1e40af']],
        ]);
        $sheet->getRowDimension($codeR)->setRowHeight(44);

        // ── Rows 5+: Student data ─────────────────────────────────────────────
        if ($lastRow >= $dataStart) {
            // Identity A-C
            $sheet->getStyle("A{$dataStart}:C{$lastRow}")->applyFromArray([
                'font'      => ['size' => 10],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'f8fafc']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            // Grade cells — yellow
            foreach ($this->subjectMap as $info) {
                $s = $this->col($info['start_col']);
                $e = $this->col($info['end_col']);
                $sheet->getStyle("{$s}{$dataStart}:{$e}{$lastRow}")->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'fefce8']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'font'      => ['bold' => true, 'size' => 10],
                ]);
            }

            // Total — light purple (manual entry)
            $sheet->getStyle("{$totalC}{$dataStart}:{$totalC}{$lastRow}")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'ede9fe']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '4c1d95']],
            ]);
            // Moyenne /10 (manual)
            $sheet->getStyle("{$moy10C}{$dataStart}:{$moy10C}{$lastRow}")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'ddd6fe']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '4c1d95']],
            ]);
            // Moy. classe /10 (manual)
            $sheet->getStyle("{$moyClassC}{$dataStart}:{$moyClassC}{$lastRow}")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'ede9fe']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '6d28d9']],
            ]);
            // DISCIPLINE
            $sheet->getStyle("{$disciplineC}{$dataStart}:{$disciplineC}{$lastRow}")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'e0e7ff']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'font'      => ['bold' => true, 'size' => 10],
            ]);
            // OBSERVATIONS
            $sheet->getStyle("{$obsC}{$dataStart}:{$obsC}{$lastRow}")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'f0fdf4']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'font'      => ['italic' => true, 'size' => 9],
            ]);

            // Alternating row shading
            for ($r = $dataStart; $r <= $lastRow; $r += 2) {
                $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'f1f5f9']],
                ]);
                foreach ($this->subjectMap as $info) {
                    $s = $this->col($info['start_col']);
                    $e = $this->col($info['end_col']);
                    $sheet->getStyle("{$s}{$r}:{$e}{$r}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'fef9c3']],
                    ]);
                }
                $sheet->getStyle("{$totalC}{$r}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'e9d5ff']],
                ]);
                $sheet->getStyle("{$moy10C}{$r}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'd8b4fe']],
                ]);
                $sheet->getStyle("{$moyClassC}{$r}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'e9d5ff']],
                ]);
                $sheet->getStyle("{$disciplineC}{$r}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'c7d2fe']],
                ]);
                $sheet->getStyle("{$obsC}{$r}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'dcfce7']],
                ]);
            }
        }

        // ── Borders ───────────────────────────────────────────────────────────
        $sheet->getStyle("A{$titleR}:{$lastCol}{$lastRow}")->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A{$titleR}:{$lastCol}{$lastRow}")->getBorders()
            ->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);
        $sheet->getStyle("A{$codeR}:{$lastCol}{$codeR}")->getBorders()
            ->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);
        $sheet->getStyle("{$totalC}{$titleR}:{$totalC}{$lastRow}")->getBorders()
            ->getLeft()->setBorderStyle(Border::BORDER_MEDIUM);
        $sheet->getStyle("{$disciplineC}{$titleR}:{$disciplineC}{$lastRow}")->getBorders()
            ->getLeft()->setBorderStyle(Border::BORDER_MEDIUM);
        $sheet->getStyle("{$obsC}{$titleR}:{$obsC}{$lastRow}")->getBorders()
            ->getLeft()->setBorderStyle(Border::BORDER_MEDIUM);

        // ── Freeze panes: identity cols + all 4 header rows ───────────────────
        $sheet->freezePane('D5');

        return [];
    }

    // ── Column widths ─────────────────────────────────────────────────────────

    public function columnWidths(): array
    {
        $widths = [
            'A' => 14, // Matricule
            'B' => 26, // Nom Complet
            'C' => 14, // Date Naissance
        ];

        $colIndex = 4;
        foreach ($this->subjects as $subject) {
            foreach ($subject->competences as $competence) {
                $widths[$this->col($colIndex)] = 13;
                $colIndex++;
            }
        }

        $widths[$this->totalCol()]      = 14;
        $widths[$this->moy10Col()]      = 13;
        $widths[$this->moyClassCol()]   = 20;
        $widths[$this->disciplineCol()] = 14;
        $widths[$this->obsCol()]        = 38;

        return $widths;
    }

    // ── Sheet title & filename ────────────────────────────────────────────────

    public function title(): string
    {
        if ($this->teacherId) {
            $name  = \App\Models\User::find($this->teacherId)?->name ?? 'Ens.';
            $short = mb_substr(trim(explode(' ', $name)[0]), 0, 14);
            return substr("{$short} - {$this->period}", 0, 31);
        }

        $classroom = Classroom::find($this->classroomId);
        return substr(($classroom->label ?? 'Classe') . ' - ' . $this->period, 0, 31);
    }

    public function getFilename(): string
    {
        $classroom   = Classroom::find($this->classroomId);
        $periodLabel = PeriodEnum::from($this->period)->label();
        $label       = preg_replace('/[^A-Za-z0-9_\-]/', '_', $classroom->label ?? 'classe');
        $suffix      = $this->teacherId ? '_prof-' . $this->teacherId : '';

        return "notes_{$label}_{$periodLabel}{$suffix}_" . now()->format('Y-m-d_H-i-s') . '.xlsx';
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function periodLongLabel(PeriodEnum $period): string
    {
        return match($period->value) {
            'T1'     => 'PÉRIODE 1 (Trimestre 1)',
            'T2'     => 'PÉRIODE 2 (Trimestre 2)',
            'T3'     => 'PÉRIODE 3 (Trimestre 3)',
            'ANNUEL' => 'ANNUEL',
            default  => strtoupper($period->label()),
        };
    }
}
