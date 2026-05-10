<?php

namespace App\Exports;

use App\Enums\PeriodEnum;
use App\Models\Classroom;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * GradeSheetDirectorExport
 *
 * Multi-sheet export for direction / admin users.
 *
 * Produces one sheet per teacher assigned to the classroom (filtered to
 * that teacher's subjects only), plus a final "all subjects" sheet — every
 * sheet uses the same GradeSheetExport layout so directors can review per
 * teacher OR see the full picture.
 *
 * Same shape as the single-teacher export:
 *   Row 1 — Title banner
 *   Row 2 — Period label
 *   Row 3 — Subject group headers + TOTAUX/MOYENNES + DIM. PERS. + OBSERVATIONS
 *   Row 4 — Per-competence headers + summary labels
 *   Row 5+ — Student data
 *
 * NO computation, NO conversion — every value comes verbatim from the
 * bulletins / bulletin_grades tables, matching what was entered in saisie.
 */
class GradeSheetDirectorExport implements WithMultipleSheets
{
    public function __construct(
        private int    $classroomId,
        private string $period,
        private int    $academicYearId,
        private string $niveauCode,
    ) {}

    public function sheets(): array
    {
        $classroom = Classroom::with('teachers')->findOrFail($this->classroomId);
        $sheets    = [];
        $seen      = [];

        // One sheet per assigned teacher (their subjects only).
        // Deduplicate in case the same teacher is attached twice.
        foreach ($classroom->teachers as $teacher) {
            if (isset($seen[$teacher->id])) continue;
            $seen[$teacher->id] = true;

            $sheets[] = new GradeSheetExport(
                $this->classroomId,
                $this->period,
                $this->academicYearId,
                $this->niveauCode,
                $teacher->id,
            );
        }

        // Final "Toutes matières" sheet: all subjects, no teacher filter.
        $sheets[] = new GradeSheetExport(
            $this->classroomId,
            $this->period,
            $this->academicYearId,
            $this->niveauCode,
            null,
        );

        return $sheets;
    }

    public function getFilename(): string
    {
        $classroom   = Classroom::find($this->classroomId);
        $periodLabel = PeriodEnum::from($this->period)->label();
        $label       = preg_replace('/[^A-Za-z0-9_\-]/', '_', $classroom?->label ?? 'classe');

        return "notes_direction_{$label}_{$periodLabel}_" . now()->format('Y-m-d_H-i-s') . '.xlsx';
    }
}
