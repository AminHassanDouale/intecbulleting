<?php

namespace App\Actions\Grade;

use App\Models\Bulletin;
use App\Models\BulletinGrade;

class SaveGradeAction
{
    public function execute(Bulletin $bulletin, array $grades): void
    {
        foreach ($grades as $competenceId => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            BulletinGrade::updateOrCreate(
                [
                    'bulletin_id'   => $bulletin->id,
                    'competence_id' => $competenceId,
                    'period'        => $bulletin->period,
                ],
                is_numeric($value)
                    ? ['score' => $value, 'competence_status' => null]
                    : ['score' => null, 'competence_status' => $value]
            );
        }

        $bulletin->recalculateMoyenne();
    }
}
