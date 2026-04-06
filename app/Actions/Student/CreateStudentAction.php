<?php

namespace App\Actions\Student;

use App\Models\AcademicYear;
use App\Models\Student;

class CreateStudentAction
{
    public function execute(array $data): Student
    {
        $year = AcademicYear::where('is_current', true)->firstOrFail();

        return Student::create([
            'matricule'        => $data['matricule'] ?? $this->generateMatricule(),
            'full_name'        => $data['full_name'],
            'birth_date'       => $data['birth_date'],
            'gender'           => $data['gender'],
            'classroom_id'     => $data['classroom_id'],
            'academic_year_id' => $year->id,
        ]);
    }

    private function generateMatricule(): string
    {
        $year   = date('Y');
        $last   = Student::max('id') + 1;

        return 'INTEC-' . $year . '-' . str_pad($last, 4, '0', STR_PAD_LEFT);
    }
}
