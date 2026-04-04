<?php

namespace App\Actions\Grade;

use App\Models\Competence;

class ValidateGradeAction
{
    public function validate(int $competenceId, mixed $value): bool|string
    {
        $competence = Competence::find($competenceId);
        if (! $competence) {
            return 'Compétence introuvable.';
        }

        if ($competence->isPrescolaire()) {
            if (! in_array($value, ['A', 'EVA', 'NA'])) {
                return 'La valeur doit être A, EVA ou NA.';
            }
        } else {
            if (! is_numeric($value)) {
                return 'La note doit être un nombre.';
            }
            if ($value < 0 || $value > $competence->max_score) {
                return "La note doit être entre 0 et {$competence->max_score}.";
            }
        }

        return true;
    }
}
