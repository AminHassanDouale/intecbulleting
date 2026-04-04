<?php

namespace App\Actions\Grade;

use App\Imports\GradesImport;
use Maatwebsite\Excel\Facades\Excel;

class ImportGradesAction
{
    public function execute(string $filePath, int $classroomId, string $period, int $academicYearId): void
    {
        Excel::import(
            new GradesImport($classroomId, $period, $academicYearId),
            $filePath
        );
    }
}
