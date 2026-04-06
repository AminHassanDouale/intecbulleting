<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Import template for teachers.
 *
 * Columns: Nom | Email | Mot de Passe | Code Classe
 */
class TeachersTemplateExport implements FromArray, WithStyles, WithTitle, WithColumnWidths
{
    public function title(): string { return 'Import Enseignants'; }

    public function array(): array
    {
        return [
            ['Nom', 'Email', 'Mot de Passe', 'Code Classe'],
            ['M. Coulibaly Ibrahim', 'coulibaly@intec.ci', 'Intec@2026', 'CPA'],
            ['Mme. Diallo Fatou', 'diallo@intec.ci', '', 'PS'],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 30,
            'C' => 18,
            'D' => 14,
        ];
    }

    public function styles(Worksheet $sheet): void
    {
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill'      => ['fillType' => 'solid', 'color' => ['rgb' => '4f46e5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->getStyle('A2:D200')->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'e5e7eb']],
            ],
        ]);

        $sheet->freezePane('A2');

        $note = $sheet->getComment('A1');
        $note->getText()->createTextRun(
            "Colonnes :\n"
            . "• Nom : nom complet de l'enseignant\n"
            . "• Email : adresse email unique (identifiant de connexion)\n"
            . "• Mot de Passe : optionnel — défaut : Intec@2026\n"
            . "• Code Classe : optionnel — ex: CPA, PS, MS, CE1B\n"
            . "  Si Code Classe est fourni, l'enseignant sera assigné à cette classe."
        );
    }
}
