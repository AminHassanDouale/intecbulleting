<?php

namespace App\Exports;

use App\Models\Niveau;
use App\Models\Subject;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProgrammeTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new ProgrammeMatiereSheet(),
            new ProgrammeCompetenceSheet(),
            new ProgrammeNiveauxReferenceSheet(),
        ];
    }
}

// ── Sheet 1 : Matières ───────────────────────────────────────────────────────
class ProgrammeMatiereSheet implements FromArray, WithStyles, WithTitle, WithColumnWidths
{
    public function title(): string { return 'Matières'; }

    public function array(): array
    {
        $rows = [
            ['Code Niveau', 'Nom Matière', 'Code Matière', 'Classe (optionnel)', 'Barème', 'Note Max', 'Ordre'],
        ];

        foreach (Subject::with('niveau')->orderBy('niveau_id')->orderBy('order')->get() as $s) {
            $rows[] = [
                $s->niveau->code,
                $s->name,
                $s->code,
                $s->classroom_code ?? '',
                $s->scale_type,
                $s->max_score,
                $s->order,
            ];
        }

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 30, 'C' => 16, 'D' => 18, 'E' => 16, 'F' => 10, 'G' => 8];
    }

    public function styles(Worksheet $sheet): void
    {
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => 'solid', 'color' => ['rgb' => '16363a']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getStyle('A2:G500')->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'e5e7eb']]],
        ]);
        $sheet->freezePane('A2');

        $note = $sheet->getComment('E1');
        $note->getText()->createTextRun(
            "Barème :\n• numeric = notes chiffrées\n• competence = A / EVA / NA"
        );
    }
}

// ── Sheet 2 : Compétences ────────────────────────────────────────────────────
class ProgrammeCompetenceSheet implements FromArray, WithStyles, WithTitle, WithColumnWidths
{
    public function title(): string { return 'Compétences'; }

    public function array(): array
    {
        $rows = [
            ['Code Matière', 'Code Compétence', 'Description', 'Note Max (vide=A/EVA/NA)', 'Période', 'Ordre'],
        ];

        foreach (Subject::with('competences')->orderBy('order')->get() as $s) {
            foreach ($s->competences as $c) {
                $rows[] = [
                    $s->code,
                    $c->code,
                    $c->description,
                    $c->max_score ?? '',
                    $c->period ?? '',
                    $c->order,
                ];
            }
        }

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 16, 'B' => 18, 'C' => 55, 'D' => 26, 'E' => 10, 'F' => 8];
    }

    public function styles(Worksheet $sheet): void
    {
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => 'solid', 'color' => ['rgb' => '4a1d96']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getStyle('A2:F1000')->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'e5e7eb']]],
        ]);
        $sheet->freezePane('A2');

        $note = $sheet->getComment('E1');
        $note->getText()->createTextRun("Période : T1, T2, T3 ou laisser vide pour tous les trimestres");
    }
}

// ── Sheet 3 : Référence niveaux ──────────────────────────────────────────────
class ProgrammeNiveauxReferenceSheet implements FromArray, WithStyles, WithTitle, WithColumnWidths
{
    public function title(): string { return 'Référence Niveaux'; }

    public function array(): array
    {
        $rows = [['Code Niveau', 'Libellé', 'Cycle']];
        foreach (Niveau::orderBy('order')->get() as $n) {
            $rows[] = [$n->code, $n->label, $n->cycle ?? ''];
        }
        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 30, 'C' => 20];
    }

    public function styles(Worksheet $sheet): void
    {
        $sheet->getStyle('A1:C1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => 'solid', 'color' => ['rgb' => '374151']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->freezePane('A2');
    }
}
