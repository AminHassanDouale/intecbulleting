<?php

namespace App\Actions\Student;

use App\Models\Classroom;
use App\Models\Student;

class TransferStudentAction
{
    public function execute(Student $student, Classroom $newClassroom): void
    {
        $student->update([
            'classroom_id'     => $newClassroom->id,
            'academic_year_id' => $newClassroom->academic_year_id,
        ]);
    }
}
