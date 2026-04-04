<?php

namespace App\Actions\Bulletin;

use App\Models\Bulletin;

class CalculateMoyenneAction
{
    public function execute(Bulletin $bulletin): void
    {
        $bulletin->recalculateMoyenne();

        // Calcul de la moyenne de classe
        $classBulletins = Bulletin::where('classroom_id', $bulletin->classroom_id)
            ->where('period', $bulletin->period)
            ->where('academic_year_id', $bulletin->academic_year_id)
            ->whereNotNull('moyenne')
            ->get();

        if ($classBulletins->isNotEmpty()) {
            $classMoyenne = round($classBulletins->avg('moyenne'), 2);
            Bulletin::where('classroom_id', $bulletin->classroom_id)
                ->where('period', $bulletin->period)
                ->where('academic_year_id', $bulletin->academic_year_id)
                ->update(['class_moyenne' => $classMoyenne]);
        }
    }
}
