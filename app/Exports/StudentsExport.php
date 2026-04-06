<?php

namespace App\Exports;

use App\Models\Student;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export students list
 * Can filter by classroom or export all students
 */
class StudentsExport implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private ?int $classroomId = null,
        private ?int $academicYearId = null,
    ) {}

    public function collection(): Collection
    {
        $query = Student::with('classroom');

        if ($this->classroomId) {
            $query->where('classroom_id', $this->classroomId);
        }

        if ($this->academicYearId) {
            $query->where('academic_year_id', $this->academicYearId);
        }

        return $query
            ->orderBy('classroom_id')
            ->orderBy('full_name')
            ->get()
            ->map(fn($student) => [
                'matricule'      => $student->matricule,
                'nom_complet'    => $student->full_name,
                'date_naissance' => $student->birth_date?->format('d/m/Y') ?? '',
                'genre'          => $student->gender,
                'code_classe'    => $student->classroom?->code ?? '',
                'section'        => $student->classroom?->section ?? '',
                'classe_label'   => $student->classroom?->label ?? '',
            ]);
    }

    public function headings(): array
    {
        return [
            'Matricule',
            'Nom',
            'Prenom',
            'Date Naissance',
            'Genre',
            'Code Classe',
            'Section',
            'Classe',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Header styling
        $sheet->getStyle('A1:H1')->applyFromArray([
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
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }

    public function title(): string
    {
        if ($this->classroomId) {
            $classroom = \App\Models\Classroom::find($this->classroomId);
            return substr("Élèves {$classroom?->label}", 0, 31);
        }

        return 'Élèves';
    }
}
