<?php

namespace App\Enums;

enum AcademicLevelEnum: string
{
    case PRESCOLAIRE = 'prescolaire';
    case PRIMAIRE    = 'primaire';
    case COLLEGE     = 'college';
    case LYCEE       = 'lycee';

    public function label(): string
    {
        return match($this) {
            self::PRESCOLAIRE => 'Préscolaire',
            self::PRIMAIRE    => 'Primaire',
            self::COLLEGE     => 'Collège',
            self::LYCEE       => 'Lycée',
        };
    }

    public function classes(): array
    {
        return match($this) {
            self::PRESCOLAIRE => ClassroomEnum::prescolaireClasses(),
            self::PRIMAIRE    => ClassroomEnum::primaireClasses(),
            self::COLLEGE     => ClassroomEnum::collegeClasses(),
            self::LYCEE       => ClassroomEnum::lyceeClasses(),
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
