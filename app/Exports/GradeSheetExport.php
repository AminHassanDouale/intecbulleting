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
 * GradeSheetExport
 *
 * Round-trip safe Excel export for the saisie / carnet workflow.
 *
 * Philosophy: NO computation, NO conversion. Whatever was entered in
 * saisie (or imported from a previous Excel round-trip) appears verbatim
 * in the exported sheet. The carnet, the saisie screen, and this export
 * all read the same database columns and show the same values.
 *
 * Layout (rows are 1-indexed):
 *   Row 1 — Title banner ("CARNET INTEC PRIMAIRE - CLASSE … - {year}")
 *   Row 2 — Period label
 *   Row 3 — Subject group headers (merged across each subject's competences)
 *           + "TOTAUX / MOYENNES" (merged across 3 summary cols)
 *           + "DIM. PERS." + "OBSERVATIONS"
 *   Row 4 — Per-competence headers + summary labels:
 *             Matricule | Nom Complet | Date Naissance | <competences…>
 *             | Total sur N | Moyenne sur M | Moyenne de la classe sur M
 *             | DISCIPLINE | Commentaire
 *   Row 5+ — One row per student
 *
 * Summary scale by niveau (CP/CE1/CE2/CM1/CM2):
 *   CP            → Total sur 140 | Moyenne sur 10 | Moyenne classe sur 10
 *   CE1/CE2/CM1/CM2 → Total sur 200 | Moyenne sur 20 | Moyenne classe sur 20
 */
class GradeSheetExport implements FromArray, WithStyles, WithTitle, WithColumnWidths
{
    private Collection $subjects;

    /** @var array<int, array{subject: Subject, start_col: int, end_col: int, competence_count: int}> */
    private array $subjectMap = [];

    private int $summaryStartCol = 4;
    private int $totalMaxScore   = 0;
    private array $summaryLayout;

    public function __construct(
        private int    $classroomId,
        private string $period,
        private int    $academicYearId,
        private string $niveauCode,
        private ?int   $teacherId = null,
    ) {
        $this->loadSubjectsAndCompetences();
    }

    // ── Niveau key resolver ───────────────────────────────────────────────────
    //
    //  Returns the base level key (CP, CE1, CE2, CM1, CM2). Order matters —
    //  longer/more-specific keys are checked first so CM2 isn't read as CM1
    //  and CE2 isn't read as CE1.
    //
    //  Handles: CP, CPA, CPB, CE1, CE1A, CE1-A, CE2A, CM1, CM1A, CM2, CM2A,
    //  and prefixed variants (PRIMAIRE-CE1A, INTEC-CE1, …).

    public static function resolveNiveauKey(string $niveauCode): ?string
    {
        return self::resolveNiveauKeyFromCandidates([$niveauCode]);
    }

    /**
     * @param array<int, string|null> $candidates
     */
    public static function resolveNiveauKeyFromCandidates(array $candidates): ?string
    {
        $prefixes = ['CM2', 'CM1', 'CE2', 'CE1', 'CP'];

        foreach ($candidates as $candidate) {
            if ($candidate === null) continue;
            $upper = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string) $candidate)));
            if ($upper === '') continue;
            foreach ($prefixes as $prefix) {
                if (str_contains($upper, $prefix)) {
                    return $prefix;
                }
            }
        }
        return null;
    }

    // ── Layout configuration ──────────────────────────────────────────────────

    private function buildSummaryLayout(array $candidates): array
    {
        $key = self::resolveNiveauKeyFromCandidates($candidates);

        if ($key === 'CP') {
            return [
                'columns' => [
                    ['key' => 'total_manuel',  'label' => 'Total sur 140',               'max' => 140],
                    ['key' => 'moyenne_10',    'label' => 'Moyenne sur 10',              'max' => 10],
                    ['key' => 'moyenne_classe','label' => 'Moyenne de la classe sur 10', 'max' => 10],
                ],
                'group_label' => 'TOTAUX / MOYENNES',
                'matched_key' => 'CP',
            ];
        }

        if ($key !== null && in_array($key, ['CE1', 'CE2', 'CM1', 'CM2'], true)) {
            return [
                'columns' => [
                    ['key' => 'total_manuel',  'label' => 'Total sur 200',               'max' => 200],
                    ['key' => 'moyenne_10',    'label' => 'Moyenne sur 20',              'max' => 20],
                    ['key' => 'moyenne_classe','label' => 'Moyenne de la classe sur 20', 'max' => 20],
                ],
                'group_label' => 'TOTAUX / MOYENNES',
                'matched_key' => $key,
            ];
        }

        // Fallback for unknown niveaux. Total label patched after subject load.
        return [
            'columns' => [
                ['key' => 'total_manuel',  'label' => 'Total sur ?',                  'max' => 0],
                ['key' => 'moyenne_10',    'label' => 'Moyenne sur 20',              'max' => 20],
                ['key' => 'moyenne_classe','label' => 'Moyenne de la classe sur 20', 'max' => 20],
            ],
            'group_label' => 'TOTAUX / MOYENNES',
            'matched_key' => null,
        ];
    }

    // ── Subject / competence loading (mirrors saisie) ─────────────────────────

    private function loadSubjectsAndCompetences(): void
    {
        $classroom   = Classroom::findOrFail($this->classroomId);
        $sectionCode = (string) $classroom->code;
        $levelCode   = preg_replace('/[AB]$/', '', $sectionCode) ?: $sectionCode;

        $this->summaryLayout = $this->buildSummaryLayout([
            $this->niveauCode,
            $sectionCode,
            (string) ($classroom->label ?? ''),
            $levelCode,
        ]);

        $query = Subject::whereHas('niveau', function ($q) {
                $q->where('code', $this->niveauCode);
            })
            ->where(function ($q) use ($sectionCode, $levelCode) {
                $q->where('section_code', $sectionCode)
                  ->orWhere(function ($q2) use ($levelCode) {
                      $q2->whereNull('section_code')
                         ->where('classroom_code', $levelCode);
                  })
                  ->orWhere(function ($q3) {
                      $q3->whereNull('section_code')
                         ->whereNull('classroom_code');
                  });
            })
            ->with(['competences' => function ($q) use ($sectionCode) {
                $q->where(function ($q2) use ($sectionCode) {
                    $q2->whereNull('section_code')
                       ->orWhere('section_code', $sectionCode);
                })->orderBy('order');
            }])
            ->orderBy('order');

        if ($this->teacherId !== null) {
            $teacherId = $this->teacherId;
            $query->whereHas('teachers', function ($q) use ($teacherId) {
                $q->where('users.id', $teacherId);
            });
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
                    foreach ($subject->competences as $competence) {
                        $this->totalMaxScore += (int) ($competence->max_score ?? 0);
                    }
                } else {
                    $this->totalMaxScore += (int) ($subject->max_score ?? 0);
                }
            }

            $colIndex += $count;
        }

        $this->summaryStartCol = $colIndex;

        // Patch the unknown-niveau total label with the computed sum.
        if ($this->summaryLayout['matched_key'] === null) {
            foreach ($this->summaryLayout['columns'] as $i => $col) {
                if ($col['key'] === 'total_manuel') {
                    $this->summaryLayout['columns'][$i]['label'] =
                        'Total sur ' . ($this->totalMaxScore ?: '?');
                }
            }
        }

        Log::info('GradeSheetExport: loaded', [
            'classroom_id'    => $this->classroomId,
            'section_code'    => $sectionCode,
            'level_code'      => $levelCode,
            'classroom_label' => $classroom->label ?? null,
            'niveau'          => $this->niveauCode,
            'matched_key'     => $this->summaryLayout['matched_key'],
            'subjects_count'  => $this->subjects->count(),
            'teacher_id'      => $this->teacherId,
            'totalMaxScore'   => $this->totalMaxScore,
            'summary_labels'  => array_column($this->summaryLayout['columns'], 'label'),
        ]);
    }

    // ── Column letter helpers ─────────────────────────────────────────────────

    private function col(int $oneBasedIndex): string
    {
        return Coordinate::stringFromColumnIndex($oneBasedIndex);
    }

    private function summaryCol(int $offset): string
    {
        return $this->col($this->summaryStartCol + $offset);
    }

    private function summaryCount(): int
    {
        return count($this->summaryLayout['columns']); // always 3
    }

    private function disciplineCol(): string
    {
        return $this->summaryCol($this->summaryCount());
    }

    private function obsCol(): string
    {
        return $this->summaryCol($this->summaryCount() + 1);
    }

    /**
     * Format a numeric grade for export. Rounds to 2 decimals, strips
     * trailing zeros so 14.00 becomes "14" and 13.50 becomes "13.5".
     * This makes round-trip imports stable.
     */
    private function formatScore(float $score): string
    {
        $formatted = number_format($score, 2, '.', '');
        // Trim trailing zeros and the dot when integer.
        $formatted = rtrim(rtrim($formatted, '0'), '.');
        return $formatted === '' ? '0' : $formatted;
    }

    // ── Array rows (the actual grid) ──────────────────────────────────────────

    public function array(): array
    {
        $rows      = [];
        $classroom = Classroom::find($this->classroomId);
        $year      = \App\Models\AcademicYear::find($this->academicYearId);

        $periodLabel   = $this->periodLongLabel(PeriodEnum::from($this->period));
        $summaryCount  = $this->summaryCount();
        $totalColCount = $this->summaryStartCol + $summaryCount + 1; // discipline + obs

        // ── Row 1: Title ──────────────────────────────────────────────────────
        $titleRow    = array_fill(0, $totalColCount, '');
        $section     = ($classroom !== null && $classroom->section)
            ? ' (' . strtoupper($classroom->section) . ')'
            : '';
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
        $subjectRow[$this->summaryStartCol - 1]                     = $this->summaryLayout['group_label'];
        $subjectRow[$this->summaryStartCol + $summaryCount - 1]     = 'DIM. PERS.';
        $subjectRow[$this->summaryStartCol + $summaryCount + 1 - 1] = 'OBSERVATIONS';
        $rows[] = $subjectRow;

        // ── Row 4: Competence labels + summary sub-labels ─────────────────────
        $codeRow = ['Matricule', 'Nom Complet', 'Date Naissance'];
        foreach ($this->subjects as $subject) {
            foreach ($subject->competences as $competence) {
                $label = $competence->code;

                if (! empty($competence->name)
                    && strtolower($competence->name) !== strtolower($competence->code)
                ) {
                    $label = $competence->code . ' / ' . $competence->name;
                } elseif (! empty($competence->description)) {
                    $shortDesc = mb_substr($competence->description, 0, 30);
                    if (mb_strlen($competence->description) > 30) {
                        $shortDesc .= '…';
                    }
                    $label = $competence->code . ' / ' . $shortDesc;
                }

                if ($subject->scale_type !== 'competence' && $competence->max_score) {
                    $label .= ' /' . (int) $competence->max_score;
                }

                $codeRow[] = $label;
            }
        }
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

            // ── Grade cells ───────────────────────────────────────────────────
            // Output exactly what is stored. Prefer numeric score when both
            // are set (numeric is more specific). For competence-scale subjects
            // (scale_type='competence'), only the status (A/EVA/NA) is meaningful.
            foreach ($this->subjects as $subject) {
                $isCompetenceScale = $subject->scale_type === 'competence';

                foreach ($subject->competences as $competence) {
                    $cell  = '';
                    $grade = $bulletin?->grades
                        ->where('competence_id', $competence->id)
                        ->where('period', $this->period)
                        ->first();

                    if ($grade !== null) {
                        if ($isCompetenceScale) {
                            // Pure competence-based scale: only status matters
                            $cell = $grade->competence_status?->value ?? '';
                        } else {
                            // Numeric subject: prefer numeric score, fall back to status
                            if ($grade->score !== null) {
                                $cell = $this->formatScore((float) $grade->score);
                            } elseif ($grade->competence_status !== null) {
                                $cell = $grade->competence_status->value;
                            }
                        }
                    }
                    $row[] = $cell;
                }
            }

            // ── Summary columns (manual entry — no calculation) ──────────────
            foreach ($this->summaryLayout['columns'] as $col) {
                $value = $bulletin?->{$col['key']};
                if ($value === null || $value === '') {
                    $row[] = '';
                } elseif (is_numeric($value)) {
                    $row[] = $this->formatScore((float) $value);
                } else {
                    $row[] = (string) $value;
                }
            }

            // ── DISCIPLINE + OBSERVATIONS ────────────────────────────────────
            $row[] = (string) ($bulletin?->discipline_status ?? '');
            $row[] = (string) ($bulletin?->teacher_comment   ?? '');

            $rows[] = $row;
        }

        return $rows;
    }

    // ── Styles ────────────────────────────────────────────────────────────────

    public function styles(Worksheet $sheet): array
    {
        $lastCol   = $this->obsCol();
        $lastRow   = $sheet->getHighestRow();
        $titleR    = 1;
        $periodR   = 2;
        $subjectR  = 3;
        $codeR     = 4;
        $dataStart = 5;

        $firstGradeCol = $this->col(4);
        $summaryFirstC = $this->summaryCol(0);
        $summaryLastC  = $this->summaryCol($this->summaryCount() - 1);
        $disciplineC   = $this->disciplineCol();
        $obsC          = $this->obsCol();

        // ── Row 1 — Title
        $sheet->mergeCells("A{$titleR}:{$lastCol}{$titleR}");
        $sheet->getStyle("A{$titleR}:{$lastCol}{$titleR}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1e3a8a']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($titleR)->setRowHeight(28);

        // ── Row 2 — Period
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

        // ── Row 3 — Subject groups
        $sheet->mergeCells("A{$subjectR}:C{$subjectR}");
        foreach ($this->subjectMap as $info) {
            $s = $this->col($info['start_col']);
            $e = $this->col($info['end_col']);
            if ($s !== $e) {
                $sheet->mergeCells("{$s}{$subjectR}:{$e}{$subjectR}");
            }
        }
        if ($summaryFirstC !== $summaryLastC) {
            $sheet->mergeCells("{$summaryFirstC}{$subjectR}:{$summaryLastC}{$subjectR}");
        }
        $sheet->getStyle("A{$subjectR}:{$lastCol}{$subjectR}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1d4ed8']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
        ]);
        $sheet->getStyle("{$disciplineC}{$subjectR}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1e40af']],
        ]);
        $sheet->getStyle("{$obsC}{$subjectR}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '0f766e']],
        ]);
        $sheet->getRowDimension($subjectR)->setRowHeight(30);

        // ── Row 4 — Competence / summary sub-labels
        $sheet->getStyle("A{$codeR}:{$lastCol}{$codeR}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '1e3a8a']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'dbeafe']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
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

        // ── Rows 5+ — student data
        if ($lastRow >= $dataStart) {
            $sheet->getStyle("A{$dataStart}:C{$lastRow}")->applyFromArray([
                'font'      => ['size' => 10],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'f8fafc']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            foreach ($this->subjectMap as $info) {
                $s = $this->col($info['start_col']);
                $e = $this->col($info['end_col']);
                $sheet->getStyle("{$s}{$dataStart}:{$e}{$lastRow}")->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'fefce8']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'font'      => ['bold' => true, 'size' => 10],
                ]);
            }

            $palette = ['ede9fe', 'ddd6fe', 'ede9fe', 'ddd6fe', 'ede9fe'];
            foreach ($this->summaryLayout['columns'] as $i => $layoutCol) {
                $c = $this->summaryCol($i);
                $sheet->getStyle("{$c}{$dataStart}:{$c}{$lastRow}")->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => $palette[$i % count($palette)]]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '4c1d95']],
                ]);
            }

            $sheet->getStyle("{$disciplineC}{$dataStart}:{$disciplineC}{$lastRow}")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'e0e7ff']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'font'      => ['bold' => true, 'size' => 10],
            ]);
            $sheet->getStyle("{$obsC}{$dataStart}:{$obsC}{$lastRow}")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'f0fdf4']],
                'alignment' => [
                    'vertical'   => Alignment::VERTICAL_CENTER,
                    'wrapText'   => true,
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                ],
                'font'      => ['italic' => true, 'size' => 9],
            ]);

            // Alternating row shading
            $altPalette = ['e9d5ff', 'd8b4fe', 'e9d5ff', 'd8b4fe', 'e9d5ff'];
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
                foreach ($this->summaryLayout['columns'] as $i => $layoutCol) {
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

        // ── Borders
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

    // ── Column widths ─────────────────────────────────────────────────────────

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

        foreach ($this->summaryLayout['columns'] as $i => $col) {
            $widths[$this->summaryCol($i)] = str_contains(strtolower($col['label']), 'classe') ? 22 : 15;
        }
        $widths[$this->disciplineCol()] = 14;
        $widths[$this->obsCol()]        = 38;

        return $widths;
    }

    // ── Sheet title ───────────────────────────────────────────────────────────

    public function title(): string
    {
        if ($this->teacherId !== null) {
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
        $suffix      = $this->teacherId !== null ? '_prof-' . $this->teacherId : '';

        return "notes_{$label}_{$periodLabel}{$suffix}_" . now()->format('Y-m-d_H-i-s') . '.xlsx';
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function periodLongLabel(PeriodEnum $period): string
    {
        if ($period->value === 'T1')     { return 'PÉRIODE 1 (Trimestre 1)'; }
        if ($period->value === 'T2')     { return 'PÉRIODE 2 (Trimestre 2)'; }
        if ($period->value === 'T3')     { return 'PÉRIODE 3 (Trimestre 3)'; }
        if ($period->value === 'ANNUEL') { return 'ANNUEL'; }
        return strtoupper($period->label());
    }
}
