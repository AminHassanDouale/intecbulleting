<?php

namespace App\Enums;

enum CompetenceStatusEnum: string
{
    case ACQUIS     = 'A';
    case EN_VOIE    = 'EVA';
    case NON_ACQUIS = 'NA';

    public function label(): string
    {
        return match($this) {
            self::ACQUIS     => 'Acquis (A) — Réussit souvent',
            self::EN_VOIE    => "En voie d'acquisition (EVA)",
            self::NON_ACQUIS => 'Non acquis encore (NA)',
        };
    }

    public function shortLabel(): string
    {
        return match($this) {
            self::ACQUIS     => 'Acquis',
            self::EN_VOIE    => 'Moyen',
            self::NON_ACQUIS => 'Très faible',
        };
    }

    public function textColor(): string
    {
        return match($this) {
            self::ACQUIS     => 'text-success',
            self::EN_VOIE    => 'text-warning',
            self::NON_ACQUIS => 'text-error',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::ACQUIS     => 'badge-success',
            self::EN_VOIE    => 'badge-warning',
            self::NON_ACQUIS => 'badge-error',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn($e) => [
            'id'   => $e->value,
            'name' => $e->label(),
        ])->toArray();
    }
}
