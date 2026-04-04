<?php

namespace App\Actions\Bulletin;

use App\Enums\BulletinStatusEnum;
use App\Enums\PeriodEnum;
use App\Models\Bulletin;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;

class GenerateAnnualBulletinPdfAction
{
    public function execute(Student $student, int $academicYearId): Bulletin
    {
        $load = ['grades.competence.subject', 'classroom.teacher', 'academicYear'];

        $t1 = Bulletin::where('student_id', $student->id)
            ->where('academic_year_id', $academicYearId)
            ->where('period', PeriodEnum::TRIMESTRE_1->value)
            ->with($load)->first();

        $t2 = Bulletin::where('student_id', $student->id)
            ->where('academic_year_id', $academicYearId)
            ->where('period', PeriodEnum::TRIMESTRE_2->value)
            ->with($load)->first();

        $t3 = Bulletin::where('student_id', $student->id)
            ->where('academic_year_id', $academicYearId)
            ->where('period', PeriodEnum::TRIMESTRE_3->value)
            ->with($load)->first();

        $base = $t1 ?? $t2 ?? $t3;

        if (! $base) {
            throw new \RuntimeException('Aucun bulletin trimestriel trouvé pour cet élève.');
        }

        $student->load('classroom.niveau');
        $niveauCode  = $student->classroom->niveau->code ?? 'primaire';
        $view        = $this->resolveTemplate($niveauCode);

        // Calculate annual moyenne: average of available trimester moyennes
        $moyennes = collect([$t1?->moyenne, $t2?->moyenne, $t3?->moyenne])->filter();
        $annualMoyenne = $moyennes->isNotEmpty()
            ? round($moyennes->average(), 2)
            : null;

        // Calculate annual total_score: sum of available trimester totals
        $annualTotal = collect([$t1?->total_score, $t2?->total_score, $t3?->total_score])
            ->filter()
            ->sum();

        $pdf = Pdf::loadView($view, [
            't1'           => $t1,
            't2'           => $t2,
            't3'           => $t3,
            'student'      => $student,
            'classroom'    => $base->classroom,
            'academicYear' => $base->academicYear,
            'annualMoyenne'=> $annualMoyenne,
            'annualTotal'  => $annualTotal,
        ])->setPaper('a4', 'portrait');

        $dir = storage_path('app/bulletins');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = "bulletin_annuel_{$student->matricule}.pdf";
        $path     = $dir . '/' . $filename;
        $pdf->save($path);

        // Create or update the annual bulletin record
        $annualBulletin = Bulletin::updateOrCreate(
            [
                'student_id'       => $student->id,
                'academic_year_id' => $academicYearId,
                'period'           => PeriodEnum::ANNUEL->value,
            ],
            [
                'classroom_id'  => $base->classroom_id,
                'status'        => BulletinStatusEnum::PUBLISHED,
                'moyenne'       => $annualMoyenne,
                'total_score'   => $annualTotal > 0 ? $annualTotal : null,
                'published_at'  => now(),
            ]
        );

        $annualBulletin->clearMediaCollection('bulletin_pdf');
        $annualBulletin->addMedia($path)->toMediaCollection('bulletin_pdf');

        return $annualBulletin;
    }

    private function resolveTemplate(string $niveauCode): string
    {
        return match(strtolower($niveauCode)) {
            'prescolaire' => 'pdf.bulletin-annuel-prescolaire',
            'primaire'    => 'pdf.bulletin-annuel-primaire',
            default       => 'pdf.bulletin-annuel-college-lycee',
        };
    }
}
