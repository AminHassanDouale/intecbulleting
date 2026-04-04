<?php

namespace App\Enums;

enum ScaleTypeEnum: string
{
    case NUMERIC    = 'numeric';
    case COMPETENCE = 'competence';

    public function label(): string
    {
        return match($this) {
            self::NUMERIC    => 'Note numérique',
            self::COMPETENCE => 'Compétence (A/EVA/NA)',
        };
    }
}
