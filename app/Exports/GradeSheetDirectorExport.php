<?php

namespace App\Exports;

use App\Enums\PeriodEnum;
use App\Models\Classroom;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * GradeSheetDirectorExport
 *
 * Single-sheet export for direction / admin users.
 *
 * Produces ONE sheet containing ALL subjects (no teacher filter), using
 * the exact same layout as GradeSheetExport:
 *
 *   Row 1 — Title banner
 *   Row 2 — Period label
 *   Row 3 — Subject group headers + TOTAUX/MOYENNES + DIM. PERS. + OBSERVATIONS
 *   Row 4 — Per-competence headers + summary labels
 *   Row 5+ — Student data
 *
 * NO computation, NO conversion — every value comes verbatim from the
 * bulletins / bulletin_grades tables, matching what was entered in saisie.
 *
 * Deliberately does NOT implement WithMultipleSheets — we want exactly
 * one tab in the workbook.
 */
class GradeSheetDirectorExport implements FromArray, WithStyles, WithTitle, WithColumnWidths
{
    private GradeSheetExport $inner;

    public function __construct(
        private int    $classroomId,
        private string $period,
        private int    $academicYearId,
        private string $niveauCode,
    ) {
        // No teacherId → all subjects, full picture.
        $this->inner = new GradeSheetExport(
            $this->classroomId,
            $this->period,
            $this->academicYearId,
            $this->niveauCode,
            null,
        );
    }

    // Delegate everything to the inner single-sheet export.

    public function array(): array
    {
        return $this->inner->array();
    }

    public function styles(Worksheet $sheet): array
    {
        return $this->inner->styles($sheet);
    }

    public function columnWidths(): array
    {
        return $this->inner->columnWidths();
    }

    public function title(): string
    {
        return $this->inner->title();
    }

    public function getFilename(): string
    {
        $classroom   = Classroom::find($this->classroomId);
        $periodLabel = PeriodEnum::from($this->period)->label();
        $label       = preg_replace('/[^A-Za-z0-9_\-]/', '_', $classroom?->label ?? 'classe');

        return "notes_direction_{$label}_{$periodLabel}_" . now()->format('Y-m-d_H-i-s') . '.xlsx';
    }
}
