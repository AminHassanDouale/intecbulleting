<?php

namespace App\Exports;

use App\Models\Classroom;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentsTemplateExport implements FromArray, WithStyles, WithTitle, WithColumnWidths
{
    public function title(): string { return 'Import Élèves'; }

    public function array(): array
    {
        // Pull up to 3 real classrooms as concrete examples
        $classrooms = Classroom::with('niveau')->orderBy('code')->take(3)->get();

        $rows = [
            ['Nom Complet', 'Date Naissance', 'Genre', 'Code Classe', 'Section'],
        ];

        if ($classrooms->isNotEmpty()) {
            $sampleNames = [
                ['DUPONT MARIE CLAIRE',   '15/03/2018', 'F'],
                ['KOUASSI JEAN BAPTISTE', '08/11/2017', 'M'],
                ['AISHATH IBRAHIM SAEED', '22/06/2019', 'F'],
            ];
            foreach ($classrooms as $i => $c) {
                [$name, $date, $genre] = $sampleNames[$i];
                // Show BOTH accepted formats — first example uses combined "CODE-SECTION", rest use separate columns
                if ($i === 0) {
                    $rows[] = [$name, $date, $genre, $c->code, $c->section];        // e.g. PS-A | A
                } elseif ($i === 1) {
                    // Strip hyphen suffix so second row shows the short-code format
                    $base   = str_contains($c->code, '-') ? explode('-', $c->code)[0] : $c->code;
                    $rows[] = [$name, $date, $genre, $base, $c->section];           // e.g. PS | A
                } else {
                    $rows[] = [$name, $date, $genre, $c->code, $c->section];
                }
            }
        } else {
            // Fallback static examples when DB is empty
            $rows[] = ['DUPONT MARIE CLAIRE',   '15/03/2018', 'F', 'PS-A', 'A'];
            $rows[] = ['KOUASSI JEAN BAPTISTE', '08/11/2017', 'M', 'PS',   'A'];
            $rows[] = ['AISHATH IBRAHIM SAEED', '22/06/2019', 'F', 'CP-A', 'A'];
        }

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 36, 'B' => 18, 'C' => 8, 'D' => 14, 'E' => 10];
    }

    public function styles(Worksheet $sheet): void
    {
        // ── Row 1: header ──────────────────────────────────────────────────
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill'      => ['fillType' => 'solid', 'color' => ['rgb' => '16363a']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        // ── Rows 2-4: example rows (teal tint, italic) ─────────────────────
        $sheet->getStyle('A2:E4')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '2c5f5f']],
            'fill' => ['fillType' => 'solid', 'color' => ['rgb' => 'eaf4f4']],
        ]);

        // Mark example rows with a left border accent
        $sheet->getStyle('A2:A4')->applyFromArray([
            'borders' => ['left' => ['borderStyle' => 'medium', 'color' => ['rgb' => 'c8913a']]],
        ]);

        // ── All rows: thin borders ──────────────────────────────────────────
        $sheet->getStyle('A1:E200')->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'd1e8e8']]],
        ]);

        // ── Row 5 onward: white background (paste zone) ────────────────────
        $sheet->getStyle('A5:E200')->applyFromArray([
            'font' => ['italic' => false, 'color' => ['rgb' => '1e293b']],
            'fill' => ['fillType' => 'solid', 'color' => ['rgb' => 'FFFFFF']],
        ]);

        // ── Freeze header ──────────────────────────────────────────────────
        $sheet->freezePane('A2');

        // ── Legend note on D1 ──────────────────────────────────────────────
        $note = $sheet->getComment('D1');
        $note->getText()->createTextRun(
            "Code Classe — formats acceptés :\n"
            . "  PS-A   (code complet avec tiret)\n"
            . "  PS     (code seul + colonne Section)\n"
            . "  CPA    (code + section collés)\n\n"
            . "Les lignes en vert clair sont des\n"
            . "exemples — remplacez-les par vos données."
        );
        $note->setWidth(160);
        $note->setHeight(100);

        // ── Legend note on B1 ──────────────────────────────────────────────
        $noteB = $sheet->getComment('B1');
        $noteB->getText()->createTextRun(
            "Date de naissance :\n"
            . "  17/02/2022\n"
            . "  2022-02-17\n"
            . "Format JJ/MM/AAAA recommandé."
        );

        // ── Legend note on C1 ──────────────────────────────────────────────
        $noteC = $sheet->getComment('C1');
        $noteC->getText()->createTextRun("Genre : M (Masculin) ou F (Féminin)");
    }
}
