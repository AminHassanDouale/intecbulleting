<?php

namespace App\Actions\Bulletin;

use App\Enums\BulletinStatusEnum;
use App\Models\Bulletin;
use App\Models\Student;

class CreateBulletinAction
{
    public function execute(Student $student, string $period, int $academicYearId): Bulletin
    {
        return Bulletin::firstOrCreate(
            [
                'student_id'       => $student->id,
                'classroom_id'     => $student->classroom_id,
                'academic_year_id' => $academicYearId,
                'period'           => $period,
            ],
            ['status' => BulletinStatusEnum::DRAFT]
        );
    }
}
