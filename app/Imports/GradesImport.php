<?php

namespace App\Imports;

use App\Actions\Grade\SaveGradeAction;
use App\Models\Bulletin;
use App\Models\Student;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class GradesImport implements ToArray, WithHeadingRow
{
    public function __construct(
        private int    $classroomId,
        private string $period,
        private int    $academicYearId
    ) {}

    public function array(array $rows): void
    {
        foreach ($rows as $row) {
            $student = Student::where('matricule', $row['matricule'])->first();
            if (! $student) {
                continue;
            }

            $bulletin = Bulletin::firstOrCreate([
                'student_id'       => $student->id,
                'classroom_id'     => $this->classroomId,
                'academic_year_id' => $this->academicYearId,
                'period'           => $this->period,
            ]);

            // Les colonnes commençant par "cb" sont des compétences
            $grades = collect($row)
                ->filter(fn($v, $k) => str_starts_with((string) $k, 'cb'))
                ->toArray();

            app(SaveGradeAction::class)->execute($bulletin, $grades);
        }
    }
}
