<?php

namespace App\Exports;

use App\Enums\PeriodEnum;
use App\Models\Classroom;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Multi-sheet export for direction/admin.
 *
 * Produces one sheet per teacher assigned to the classroom (using that
 * teacher's subjects only), plus a final sheet with every subject for the
 * level — all using the same GradeSheetExport format that mirrors the real
 * "Extraction CPB" template:
 *
 *   Row 1  → CARNET INTEC PRIMAIRE - CLASSE {label} - {year}
 *   Row 2  → PÉRIODE N (Trimestre N)
 *   Row 3  → Subject names (merged per subject group)
 *   Row 4  → Competence labels  (Matricule / Nom Complet / Date Naissance / CB1 / …)
 *   Row 5+ → Student data
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

        // One sheet per assigned teacher (their subjects only)
        foreach ($classroom->teachers as $teacher) {
            $sheets[] = new GradeSheetExport(
                $this->classroomId,
                $this->period,
                $this->academicYearId,
                $this->niveauCode,
                $teacher->id,
            );
        }

        // Final sheet: all subjects, no teacher filter
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

        return "notes_direction_{$label}_{$periodLabel}_" . now()->format('Y-m-d') . '.xlsx';
    }
}
