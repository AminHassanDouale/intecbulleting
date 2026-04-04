<?php

namespace App\Enums;

enum WorkflowStepEnum: string
{
    case ENSEIGNANT  = 'submitted';
    case PEDAGOGIE   = 'pedagogie_review';
    case FINANCE     = 'finance_review';
    case DIRECTION   = 'direction_review';

    public function label(): string
    {
        return match($this) {
            self::ENSEIGNANT => 'Enseignant',
            self::PEDAGOGIE  => 'Pédagogie',
            self::FINANCE    => 'Finance',
            self::DIRECTION  => 'Direction',
        };
    }
}
