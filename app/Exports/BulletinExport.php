<?php

namespace App\Exports;

use App\Models\Bulletin;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export bulletin summary for an academic year
 * Shows student info, period, average, and status
 */
class BulletinExport implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private int $academicYearId,
        private ?int $classroomId = null,
        private ?string $period = null,
    ) {}

    public function collection(): Collection
    {
        $query = Bulletin::where('academic_year_id', $this->academicYearId)
            ->with(['student', 'classroom']);

        if ($this->classroomId) {
            $query->where('classroom_id', $this->classroomId);
        }

        if ($this->period) {
            $query->where('period', $this->period);
        }

        return $query
            ->orderBy('classroom_id')
            ->orderBy('period')
            ->get()
            ->map(fn($bulletin) => [
                'matricule'     => $bulletin->student->matricule,
                'nom_complet'   => $bulletin->student->full_name,
                'classe'        => $bulletin->classroom->label,
                'section'       => $bulletin->classroom->section,
                'periode'       => 'P' . $bulletin->period,
                'moyenne'       => $bulletin->moyenne ? number_format($bulletin->moyenne, 2) : '',
                'total'         => $bulletin->total_score ? number_format($bulletin->total_score, 2) : '',
                'rang'          => $bulletin->rank ?? '',
                'statut'        => $bulletin->status->label(),
                'appreciation'  => $bulletin->general_appreciation ?? '',
            ]);
    }

    public function headings(): array
    {
        return [
            'Matricule',
            'Nom Complet',
            'Classe',
            'Section',
            'Période',
            'Moyenne',
            'Total',
            'Rang',
            'Statut',
            'Appréciation',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Header styling
        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => 'solid',
                'color' => ['rgb' => '1e40af'],
            ],
            'alignment' => [
                'horizontal' => 'center',
                'vertical' => 'center',
            ],
        ]);

        // Auto-size columns
        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }

    public function title(): string
    {
        $title = 'Bulletins';

        if ($this->classroomId) {
            $classroom = \App\Models\Classroom::find($this->classroomId);
            $title .= " - {$classroom?->label}";
        }

        if ($this->period) {
            $title .= " P{$this->period}";
        }

        return substr($title, 0, 31); // Excel sheet name limit
    }
}
