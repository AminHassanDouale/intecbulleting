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
 * GradeSheetExport — section-aware, manual summary columns.
 *
 * Subjects are filtered by the classroom's `code` against:
 *   1) subjects.section_code = classroom.code      (e.g. "CPA")
 *   2) subjects.classroom_code = level code        (e.g. "CP")  — only when section_code is NULL
 *   3) global subjects (both columns NULL) within the niveau
 *
 * Layout (one period):
 *   A  Matricule
 *   B  Nom Complet
 *   C  Date Naissance
 *   D… Competence grades (one column per competence)
 *   +0 Total sur {totalMaxScore}      ← MANUAL ENTRY (header shows max)
 *   +1 Moyenne sur 10                 ← MANUAL ENTRY
 *   +2 Moyenne de la classe sur 10    ← MANUAL ENTRY
 *   +3 DISCIPLINE (DIM. PERS.)        ← MANUAL ENTRY
 *   +4 OBSERVATIONS                   ← MANUAL ENTRY
 *
 * Header rows:
 *   1: title
 *   2: PÉRIODE N (Trimestre N)
 *   3: subject group headers + TOTAUX/MOYENNES + DIM. PERS. + OBSERVATIONS
 *   4: identity labels + competence labels (with /max) + summary sub-labels
 */
class GradeSheetExport implements FromArray, WithStyles, WithTitle, WithColumnWidths
{
    private Collection $subjects;
    /** @var array<int, array{subject: Subject, start_col: int, end_col: int, competence_count: int}> */
    private array $subjectMap = [];
    /** 1-based index of the first summary column (Total) */
    private int $summaryStartCol = 4;
    /** Total of all numeric competence/subject max scores for this class */
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

    // ── Subject loading (section-aware) ──────────────────────────────────────

    private function loadSubjectsAndCompetences(): void
    {
        $classroom = Classroom::findOrFail($this->classroomId);

        // The section identifier of this classroom (e.g. "CPA", "CE1B", "CM1")
        $sectionCode = (string) $classroom->code;
        // The level code derived from the section (CPA → CP, CE1B → CE1, CM1 → CM1)
        $levelCode = preg_replace('/[AB]$/', '', $sectionCode) ?: $sectionCode;

        $query = Subject::whereHas('niveau', fn($q) => $q->where('code', $this->niveauCode))
            ->where(function ($q) use ($sectionCode, $levelCode) {
                // 1) Exact section match
                $q->where('section_code', $sectionCode)
                  // 2) Level fallback (no specific section + same level)
                  ->orWhere(function ($q2) use ($levelCode) {
                      $q2->whereNull('section_code')
                         ->where('classroom_code', $levelCode);
                  })
                  // 3) Global within niveau (no section, no level)
                  ->orWhere(function ($q3) {
                      $q3->whereNull('section_code')->whereNull('classroom_code');
                  });
            })
            ->with(['competences' => function ($q) use ($sectionCode) {
                // Load only competences that match this section or are global
                $q->where(fn($q2) => $q2->whereNull('section_code')->orWhere('section_code', $sectionCode))
                  ->orderBy('order');
            }])
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
                $hasIndividualMax = $subject->competences->whereNotNull('max_score')->isNotEmpty();
                if ($hasIndividualMax) {
                    foreach ($subject->competences as $c) {
                        $this->totalMaxScore += (int) ($c->max_score ?? 0);
                    }
                } else {
                    $this->totalMaxScore += (int) ($subject->max_score ?? 0);
                }
            }

            $colIndex += $count;
        }

        $this->summaryStartCol = $colIndex;

        Log::info('GradeSheetExport: loaded', [
            'classroom_id'   => $this->classroomId,
            'section_code'   => $sectionCode,
            'level_code'     => $levelCode,
            'subjects_count' => $this->subjects->count(),
            'teacher_id'     => $this->teacherId,
            'totalMaxScore'  => $this->totalMaxScore,
        ]);
    }

    // ── Column letter helpers ────────────────────────────────────────────────

    private function col(int $oneBasedIndex): string
    {
        return Coordinate::stringFromColumnIndex($oneBasedIndex);
    }

    private function totalCol(): string      { return $this->col($this->summaryStartCol); }
    private function moy10Col(): string      { return $this->col($this->summaryStartCol + 1); }
    private function moyClassCol(): string   { return $this->col($this->summaryStartCol + 2); }
    private function disciplineCol(): string { return $this->col($this->summaryStartCol + 3); }
    private function obsCol(): string        { return $this->col($this->summaryStartCol + 4); }

    // ── Array rows ───────────────────────────────────────────────────────────

    public function array(): array
    {
        $rows      = [];
        $classroom = Classroom::find($this->classroomId);
        $year      = \App\Models\AcademicYear::find($this->academicYearId);

        $periodLabel = $this->periodLongLabel(PeriodEnum::from($this->period));
        $totalLabel  = 'Total sur ' . ($this->totalMaxScore ?: '?');

        // Total columns = 5 summary cols starting at $summaryStartCol (1-based).
        // Last 1-based col index = $summaryStartCol + 4 → array length = same value.
        $totalColCount = $this->summaryStartCol + 4;

        // ── Row 1: Title ──────────────────────────────────────────────────────
        $titleRow    = array_fill(0, $totalColCount, '');
        $section     = $classroom?->section ? ' (' . strtoupper($classroom->section) . ')' : '';
        $titleRow[0] = 'CARNET INTEC PRIMAIRE - CLASSE '
            . strtoupper((string) ($classroom?->label ?? ''))
            . $section
            . ' - ' . ($year?->label ?? '');
        $rows[] = $titleRow;

        // ── Row 2: Period label ───────────────────────────────────────────────
        $periodRow    = array_fill(0, $totalColCount, '');
        $periodRow[3] = $periodLabel;
        $rows[] = $periodRow;

        // ── Row 3: Subject group headers ──────────────────────────────────────
        $subjectRow = array_fill(0, $totalColCount, '');
        foreach ($this->subjectMap as $info) {
            $subjectName = strtoupper((string) $info['subject']->name);
            if ($info['subject']->scale_type !== 'competence' && $info['subject']->max_score) {
                $subjectName .= ' /' . (int) $info['subject']->max_score;
            }
            $subjectRow[$info['start_col'] - 1] = $subjectName;
        }
        // Summary group cells (0-based offsets from $summaryStartCol)
        $subjectRow[$this->summaryStartCol - 1]     = 'TOTAUX / MOYENNES';
        $subjectRow[$this->summaryStartCol + 3 - 1] = 'DIM. PERS.';
        $subjectRow[$this->summaryStartCol + 4 - 1] = 'OBSERVATIONS';
        $rows[] = $subjectRow;

        // ── Row 4: Identity labels + competence labels + summary sub-labels ──
        $codeRow = ['Matricule', 'Nom Complet', 'Date Naissance'];
        foreach ($this->subjects as $subject) {
            foreach ($subject->competences as $competence) {
                $label = $competence->code;

                if (! empty($competence->name) && strtolower($competence->name) !== strtolower($competence->code)) {
                    $label = $competence->code . ' / ' . $competence->name;
                } elseif (! empty($competence->description)) {
                    $shortDesc = mb_substr($competence->description, 0, 30);
                    if (mb_strlen($competence->description) > 30) $shortDesc .= '…';
                    $label = $competence->code . ' / ' . $shortDesc;
                }

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
        $codeRow[] = 'Commentaire';                     // sub-label under OBSERVATIONS
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
                (string) ($student->matricule ?? ''),
                (string) ($student->full_name ?? ''),
                $student->birth_date
                    ? \Carbon\Carbon::parse($student->birth_date)->format('d/m/Y')
                    : '',
            ];

            // Competence grades — pre-filled if already saved
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

            // Manual entry columns (pre-filled if already saved on the bulletin)
            $row[] = $bulletin?->total_manuel   !== null ? (string) $bulletin->total_manuel   : '';
            $row[] = $bulletin?->moyenne_10     !== null ? (string) $bulletin->moyenne_10     : '';
            $row[] = $bulletin?->moyenne_classe !== null ? (string) $bulletin->moyenne_classe : '';
            $row[] = (string) ($bulletin?->discipline_status ?? '');
            $row[] = (string) ($bulletin?->teacher_comment   ?? '');

            $rows[] = $row;
        }

        return $rows;
    }

    // ── Styles ───────────────────────────────────────────────────────────────

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

        // Row 1 — Title
        $sheet->mergeCells("A{$titleR}:{$lastCol}{$titleR}");
        $sheet->getStyle("A{$titleR}:{$lastCol}{$titleR}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1e3a8a']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($titleR)->setRowHeight(28);

        // Row 2 — Period
        $sheet->mergeCells("A{$periodR}:C{$periodR}");
        // Span period banner from first grade col to OBSERVATIONS for visual cohesion
        if ($firstGradeCol !== $obsC) {
            $sheet->mergeCells("{$firstGradeCol}{$periodR}:{$obsC}{$periodR}");
        }
        $sheet->getStyle("A{$periodR}:{$lastCol}{$periodR}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1e40af']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($periodR)->setRowHeight(24);

        // Row 3 — Subject groups
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

        // Row 4 — Competence + summary sub-labels
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
        $sheet->getStyle("{$obsC}{$codeR}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'ccfbf1']],
            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '134e4a']],
        ]);
        $sheet->getRowDimension($codeR)->setRowHeight(44);

        // Rows 5+ — student data
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

            // Total — light purple
            $sheet->getStyle("{$totalC}{$dataStart}:{$totalC}{$lastRow}")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'ede9fe']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '4c1d95']],
            ]);
            // Moyenne /10
            $sheet->getStyle("{$moy10C}{$dataStart}:{$moy10C}{$lastRow}")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'ddd6fe']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '4c1d95']],
            ]);
            // Moy. classe /10
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
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true, 'horizontal' => Alignment::HORIZONTAL_LEFT],
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

            // Set generous row heights for data rows so OBSERVATIONS wrap nicely
            for ($r = $dataStart; $r <= $lastRow; $r++) {
                $sheet->getRowDimension($r)->setRowHeight(28);
            }
        }

        // Borders
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

        // Freeze identity columns + 4 header rows
        $sheet->freezePane('D5');

        return [];
    }

    // ── Column widths ────────────────────────────────────────────────────────

    public function columnWidths(): array
    {
        $widths = [
            'A' => 14,  // Matricule
            'B' => 26,  // Nom Complet
            'C' => 14,  // Date Naissance
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

    // ── Sheet title & filename ───────────────────────────────────────────────

    public function title(): string
    {
        if ($this->teacherId) {
            $name  = \App\Models\User::find($this->teacherId)?->name ?? 'Ens.';
            $short = mb_substr(trim(explode(' ', $name)[0]), 0, 14);
            return substr("{$short} - {$this->period}", 0, 31);
        }

        $classroom = Classroom::find($this->classroomId);
        return substr(((string) ($classroom?->label ?? 'Classe')) . ' - ' . $this->period, 0, 31);
    }

    public function getFilename(): string
    {
        $classroom   = Classroom::find($this->classroomId);
        $periodLabel = PeriodEnum::from($this->period)->label();
        $label       = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) ($classroom?->label ?? 'classe'));
        $suffix      = $this->teacherId ? '_prof-' . $this->teacherId : '';

        return "notes_{$label}_{$periodLabel}{$suffix}_" . now()->format('Y-m-d_H-i-s') . '.xlsx';
    }

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
