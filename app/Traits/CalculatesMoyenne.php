<?php

namespace App\Traits;

trait CalculatesMoyenne
{
    public function recalculateMoyenne(): void
    {
        $grades = $this->grades()
            ->with('competence.subject')
            ->whereNotNull('score')
            ->get();

        if ($grades->isEmpty()) {
            return;
        }

        $subjectTotals = $grades->groupBy('competence.subject_id')
            ->map(function ($gradeGroup) {
                $maxScore = $gradeGroup->first()->competence->subject->max_score;
                $earned   = $gradeGroup->sum('score');

                return ['earned' => $earned, 'max' => $maxScore];
            });

        $totalEarned = $subjectTotals->sum('earned');
        $totalMax    = $subjectTotals->sum('max');

        $this->update([
            'total_score' => $totalEarned,
            'moyenne'     => $totalMax > 0 ? round(($totalEarned / $totalMax) * 20, 2) : 0,
        ]);
    }
}
