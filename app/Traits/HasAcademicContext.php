<?php

namespace App\Traits;

use App\Models\AcademicYear;
use Illuminate\Support\Collection;

trait HasAcademicContext
{
    public function getCurrentAcademicYear(): AcademicYear
    {
        return AcademicYear::where('is_current', true)->firstOrFail();
    }

    public function getClassroomsForNiveau(string $niveauCode): Collection
    {
        return \App\Models\Classroom::whereHas(
            'niveau',
            fn($q) => $q->where('code', $niveauCode)
        )
        ->where('academic_year_id', $this->getCurrentAcademicYear()->id)
        ->get();
    }
}
