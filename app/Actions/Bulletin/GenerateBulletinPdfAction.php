<?php

namespace App\Actions\Bulletin;

use App\Enums\AcademicLevelEnum;
use App\Enums\BulletinStatusEnum;
use App\Models\Bulletin;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Enums\PeriodEnum;

class GenerateBulletinPdfAction
{
    public function execute(Bulletin $bulletin): string
    {
        $bulletin->load([
            'student.classroom.niveau',
            'grades.competence.subject',
            'academicYear',
            'approvals.user',
        ]);

        $niveauCode = $bulletin->student->classroom->niveau->code;
        $view       = $this->resolveTemplate($niveauCode);

        $data = ['bulletin' => $bulletin];

        // For prescolaire, pass all 3 trimester bulletins so the template can show the full year
        if ($niveauCode === AcademicLevelEnum::PRESCOLAIRE->value) {
            $base = [
                'student_id'       => $bulletin->student_id,
                'classroom_id'     => $bulletin->classroom_id,
                'academic_year_id' => $bulletin->academic_year_id,
            ];
            $gradeEager = ['grades.competence.subject'];
            $data['t1'] = Bulletin::where($base + ['period' => PeriodEnum::TRIMESTRE_1->value])->with($gradeEager)->first();
            $data['t2'] = Bulletin::where($base + ['period' => PeriodEnum::TRIMESTRE_2->value])->with($gradeEager)->first();
            $data['t3'] = Bulletin::where($base + ['period' => PeriodEnum::TRIMESTRE_3->value])->with($gradeEager)->first();
        }

        $pdf = Pdf::loadView($view, $data)
            ->setPaper('a4', 'portrait');

        $dir      = storage_path('app/bulletins');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = "bulletin_{$bulletin->student->matricule}_{$bulletin->period}.pdf";
        $path     = $dir . '/' . $filename;
        $pdf->save($path);

        $bulletin->clearMediaCollection('bulletin_pdf');
        $bulletin->addMedia($path)->toMediaCollection('bulletin_pdf');

        $bulletin->update([
            'status'       => BulletinStatusEnum::PUBLISHED,
            'published_at' => now(),
        ]);

        return $path;
    }

    private function resolveTemplate(string $niveauCode): string
    {
        return match($niveauCode) {
            AcademicLevelEnum::PRESCOLAIRE->value => 'pdf.bulletin-prescolaire',
            AcademicLevelEnum::PRIMAIRE->value    => 'pdf.bulletin-primaire',
            default                               => 'pdf.bulletin-college-lycee',
        };
    }
}
