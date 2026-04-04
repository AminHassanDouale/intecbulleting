<?php

namespace App\Enums;

enum PeriodEnum: string
{
    case TRIMESTRE_1 = 'T1';
    case TRIMESTRE_2 = 'T2';
    case TRIMESTRE_3 = 'T3';
    case ANNUEL      = 'annual';

    public function label(): string
    {
        return match($this) {
            self::TRIMESTRE_1 => '1er Trimestre',
            self::TRIMESTRE_2 => '2ème Trimestre',
            self::TRIMESTRE_3 => '3ème Trimestre',
            self::ANNUEL      => 'Bilan Annuel',
        };
    }

    /** Return the active trimester based on current month. */
    public static function current(): self
    {
        $month = now()->month;
        return match(true) {
            $month >= 9 && $month <= 11 => self::TRIMESTRE_1,   // Sep–Nov
            $month == 12 || $month <= 2 => self::TRIMESTRE_2,   // Dec–Feb
            default                     => self::TRIMESTRE_3,   // Mar–Jun
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->reject(fn($e) => $e === self::ANNUEL)
            ->map(fn($e) => ['id' => $e->value, 'name' => $e->label()])
            ->toArray();
    }
}
