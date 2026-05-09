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
 * GradeSheetExport — section-aware, level-aware manual summary columns.
 *
 * Summary column layout depends on the niveau code:
 *
 *   CP, CE1            → [Total sur 140] [Moyenne sur 10] [Moyenne de la classe sur 10]
 *   CE2, CM1, CM2      → [Moyenne de la classe sur 20] [Moyenne sur 20] [Total sur 200]
 *
 * Each layout is followed by:
 *   [DISCIPLINE] [OBSERVATIONS]
 *
 * Subjects are filtered by the classroom's `code` against:
 *   1) subjects.section_code = classroom.code      (e.g. "CPA")
 *   2) subjects.classroom_code = level code        (e.g. "CP")  — only when section_code is NULL
 *   3) global subjects (both columns NULL) within the niveau
 */
class GradeSheetExport implements FromArray, WithStyles, WithTitle, WithColumnWidths
{
    private Collection $subjects;
    /** @var array<int, array{subject: Subject, start_col: int, end_col: int, competence_count: int}> */
    private array $subjectMap = [];
    /** 1-based index of the first summary column */
    private int $summaryStartCol = 4;
    /** Total of all numeric competence/subject max scores for this class */
    private int $totalMaxScore = 0;

    /** Layout config derived from niveau code */
    private array $summaryLayout;

    public function __construct(
        private int    $classroomId,
        private string $period,
        private int    $academicYearId,
        private string $niveauCode,
        private ?int   $teacherId = null,
    ) {
        $this->summaryLayout = $this->buildSummaryLayout($niveauCode);
        $this->loadSubjectsAndCompetences();
    }

    // ── Layout configuration per niveau ──────────────────────────────────────

    /**
     * Returns the ordered list of summary sub-columns for this niveau.
     *
     * Each entry: ['key' => string, 'label' => string, 'short' => string]
     *   key   = canonical identifier used by the importer & bulletin column
     *   label = full header text written to the file (row 4)
     *   short = used in tooltips/UI; not strictly required
     */
    private function buildSummaryLayout(string $niveauCode): array
    {
        $code = strtoupper(trim($niveauCode));

        // CP and CE1 → /140 + /10 layout
        if (in_array($code, ['CP', 'CE1'], true)) {
            return [
                'columns' => [
                    ['key' => 'total_manuel',   'label' => 'Total sur 140',                'max' => 140, 'numeric' => true],
                    ['key' => 'moyenne_10',     'label' => 'Moyenne sur 10',                'max' => 10,  'numeric' => true],
                    ['key' => 'moyenne_classe', 'label' => 'Moyenne de la classe sur 10',   'max' => 10,  'numeric' => true],
                ],
                'group_label' => 'TOTAUX / MOYENNES',
                'scale'       => '10',
            ];
        }

        // CE2, CM1, CM2 → /20 + /200 layout
        // (order: Moyenne classe → Moyenne /20 → Total /200)
        if (in_array($code, ['CE2', 'CM1', 'CM2'], true)) {
            return [
                'columns' => [
                    ['key' => 'moyenne_classe', 'label' => 'Moyenne de la classe sur 20',   'max' => 20,  'numeric' => true],
                    ['key' => 'moyenne_10',     'label' => 'Moyenne sur 20',                'max' => 20,  'numeric' => true],
                    ['key' => 'total_manuel',   'label' => 'Total sur 200',                 'max' => 200, 'numeric' => true],
                ],
                'group_label' => 'TOTAUX / MOYENNES',
                'scale'       => '20',
            ];
        }

        // Fallback (preserves previous behaviour)
        return [
            'columns' => [
                ['key' => 'total_manuel',   'label' => 'Total sur ' . ($this->totalMaxScore ?: '?'), 'max' => 0,  'numeric' => true],
                ['key' => 'moyenne_10',     'label' => 'Moyenne sur 10',                              'max' => 10, 'numeric' => true],
                ['key' => 'moyenne_classe', 'label' => 'Moyenne de la classe sur 10',                 'max' => 10, 'numeric' => true],
            ],
            'group_label' => 'TOTAUX / MOYENNES',
            'scale'       => '10',
        ];
    }

    // ── Subject loading (section-aware) ──────────────────────────────────────

    private function loadSubjectsAndCompetences(): void
    {
        $classroom = Classroom::findOrFail($this->classroomId);

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

        // Refresh the fallback "Total sur ?" label with computed total
        if (! in_array(strtoupper(trim($this->niveauCode)), ['CP', 'CE1', 'CE2', 'CM1', 'CM2'], true)) {
            foreach ($this->summaryLayout['columns'] as $i => $col) {
                if ($col['key'] === 'total_manuel') {
                    $this->summaryLayout['columns'][$i]['label'] = 'Total sur ' . ($this->totalMaxScore ?: '?');
                }
            }
        }

        Log::info('GradeSheetExport: loaded', [
            'classroom_id'   => $this->classroomId,
            'section_code'   => $sectionCode,
            'level_code'     => $levelCode,
            'niveau'         => $this->niveauCode,
            'subjects_count' => $this->subjects->count(),
            'teacher_id'     => $this->teacherId,
            'totalMaxScore'  => $this->totalMaxScore,
            'summary_keys'   => array_column($this->summaryLayout['columns'], 'key'),
        ]);
    }

    // ── Column letter helpers ────────────────────────────────────────────────

    private function col(int $oneBasedIndex): string
    {
        return Coordinate::stringFromColumnIndex($oneBasedIndex);
    }

    private function summaryCol(int $offset): string
    {
        return $this->col($this->summaryStartCol + $offset);
    }

    /** Number of summary "totaux/moyennes" sub-columns (excluding discipline + obs) */
    private function summaryCount(): int
    {
        return count($this->summaryLayout['columns']);
    }

    private function disciplineCol(): string { return $this->summaryCol($this->summaryCount()); }
    private function obsCol(): string        { return $this->summaryCol($this->summaryCount() + 1); }

    // ── Array rows ───────────────────────────────────────────────────────────

    public function array(): array
    {
        $rows      = [];
        $classroom = Classroom::find($this->classroomId);
        $year      = \App\Models\AcademicYear::find($this->academicYearId);

        $periodLabel = $this->periodLongLabel(PeriodEnum::from($this->period));

        $summaryCount  = $this->summaryCount();          // 3
        // last 1-based col = summaryStartCol + summaryCount + 1 (discipline + obs)
        $totalColCount = $this->summaryStartCol + $summaryCount + 1;

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
        // Group cells
        $subjectRow[$this->summaryStartCol - 1]                       = $this->summaryLayout['group_label']; // TOTAUX / MOYENNES
        $subjectRow[$this->summaryStartCol + $summaryCount - 1]       = 'DIM. PERS.';
        $subjectRow[$this->summaryStartCol + $summaryCount + 1 - 1]   = 'OBSERVATIONS';
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
        // Summary sub-labels (level-aware)
        foreach ($this->summaryLayout['columns'] as $col) {
            $codeRow[] = $col['label'];
        }
        $codeRow[] = 'DISCIPLINE';
        $codeRow[] = 'Commentaire';
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

            // Summary columns in the order defined by the layout
            foreach ($this->summaryLayout['columns'] as $col) {
                $value = $bulletin?->{$col['key']};
                $row[] = $value !== null && $value !== '' ? (string) $value : '';
            }

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
        $summaryFirstC  = $this->summaryCol(0);
        $summaryLastC   = $this->summaryCol($this->summaryCount() - 1);
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
        // Merge TOTAUX / MOYENNES across its sub-columns
        if ($summaryFirstC !== $summaryLastC) {
            $sheet->mergeCells("{$summaryFirstC}{$subjectR}:{$summaryLastC}{$subjectR}");
        }

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
        $sheet->getStyle("{$summaryFirstC}{$codeR}:{$summaryLastC}{$codeR}")->applyFromArray([
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

            // Summary sub-columns — alternating purple shades
            $palette = ['ede9fe', 'ddd6fe', 'ede9fe', 'ddd6fe', 'ede9fe'];
            foreach ($this->summaryLayout['columns'] as $i => $_col) {
                $c = $this->summaryCol($i);
                $sheet->getStyle("{$c}{$dataStart}:{$c}{$lastRow}")->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => $palette[$i % count($palette)]]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '4c1d95']],
                ]);
            }

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
                $altPalette = ['e9d5ff', 'd8b4fe', 'e9d5ff', 'd8b4fe', 'e9d5ff'];
                foreach ($this->summaryLayout['columns'] as $i => $_col) {
                    $c = $this->summaryCol($i);
                    $sheet->getStyle("{$c}{$r}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => $altPalette[$i % count($altPalette)]]],
                    ]);
                }
                $sheet->getStyle("{$disciplineC}{$r}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'c7d2fe']],
                ]);
                $sheet->getStyle("{$obsC}{$r}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'dcfce7']],
                ]);
            }

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
        $sheet->getStyle("{$summaryFirstC}{$titleR}:{$summaryFirstC}{$lastRow}")->getBorders()
            ->getLeft()->setBorderStyle(Border::BORDER_MEDIUM);
        $sheet->getStyle("{$disciplineC}{$titleR}:{$disciplineC}{$lastRow}")->getBorders()
            ->getLeft()->setBorderStyle(Border::BORDER_MEDIUM);
        $sheet->getStyle("{$obsC}{$titleR}:{$obsC}{$lastRow}")->getBorders()
            ->getLeft()->setBorderStyle(Border::BORDER_MEDIUM);

        $sheet->freezePane('D5');

        return [];
    }

    // ── Column widths ────────────────────────────────────────────────────────

    public function columnWidths(): array
    {
        $widths = [
            'A' => 14,
            'B' => 26,
            'C' => 14,
        ];

        $colIndex = 4;
        foreach ($this->subjects as $subject) {
            foreach ($subject->competences as $competence) {
                $widths[$this->col($colIndex)] = 13;
                $colIndex++;
            }
        }

        // Summary widths — generous for "Moyenne de la classe sur XX"
        foreach ($this->summaryLayout['columns'] as $i => $col) {
            $widths[$this->summaryCol($i)] = str_contains(strtolower($col['label']), 'classe') ? 20 : 14;
        }
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
