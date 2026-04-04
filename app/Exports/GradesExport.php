<?php

namespace App\Exports;

use App\Models\Bulletin;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GradesExport implements FromCollection, WithHeadings, WithStyles
{
    public function __construct(
        private int    $classroomId,
        private string $period,
        private int    $academicYearId
    ) {}

    public function collection()
    {
        return Bulletin::where('classroom_id', $this->classroomId)
            ->where('period', $this->period)
            ->where('academic_year_id', $this->academicYearId)
            ->with(['student', 'grades.competence.subject'])
            ->get()
            ->map(fn($b) => [
                'matricule' => $b->student->matricule,
                'nom'       => $b->student->last_name,
                'prenom'    => $b->student->first_name,
                'moyenne'   => $b->moyenne,
                'total'     => $b->total_score,
                'statut'    => $b->status->label(),
            ]);
    }

    public function headings(): array
    {
        return ['Matricule', 'Nom', 'Prénom', 'Moyenne/20', 'Total', 'Statut'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'color' => ['rgb' => '1e40af']],
            ],
        ];
    }
}
