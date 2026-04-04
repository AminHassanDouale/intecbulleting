<?php

namespace App\Enums;

enum SectionEnum: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';
    case E = 'E';

    public function label(): string
    {
        return 'Section ' . $this->value;
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn($e) => [
            'id'   => $e->value,
            'name' => $e->label(),
        ])->toArray();
    }
}
