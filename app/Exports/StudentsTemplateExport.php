<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Single-sheet import template — no Matricule column (auto-generated).
 *
 * Row 1  — headers (blue, bold)
 * Row 2+ — empty, paste data here
 *
 * Columns: Nom Complet | Date Naissance | Genre | Code Classe | Section
 */
class StudentsTemplateExport implements FromArray, WithStyles, WithTitle, WithColumnWidths
{
    public function title(): string { return 'Import Élèves'; }

    public function array(): array
    {
        return [
            ['Nom Complet', 'Date Naissance', 'Genre', 'Code Classe', 'Section'],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 35,
            'B' => 18,
            'C' => 8,
            'D' => 14,
            'E' => 12,
        ];
    }

    public function styles(Worksheet $sheet): void
    {
        // Header row
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill'      => ['fillType' => 'solid', 'color' => ['rgb' => '1e40af']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        // Data zone borders
        $sheet->getStyle('A2:E200')->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'e5e7eb']],
            ],
        ]);

        // Freeze header
        $sheet->freezePane('A2');

        // Tooltip on A1
        $note = $sheet->getComment('A1');
        $note->getText()->createTextRun(
            "Formats acceptés :\n"
            . "• Date : 6/5/2019 ou 06/05/2019\n"
            . "• Genre : M ou F\n"
            . "• Code Classe : CPA (combiné) ou CP + section A\n"
            . "• Section : laisser vide si inclus dans Code Classe\n"
            . "• Matricule : généré automatiquement"
        );
    }
}
