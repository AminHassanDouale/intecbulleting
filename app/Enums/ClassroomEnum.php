<?php

namespace App\Enums;

enum ClassroomEnum: string
{
    // Préscolaire
    case PS  = 'PS';
    case MS  = 'MS';
    case GS  = 'GS';

    // Primaire
    case CP  = 'CP';
    case CE1 = 'CE1';
    case CE2 = 'CE2';
    case CM1 = 'CM1';
    case CM2 = 'CM2';

    // Collège
    case SIXIEME   = '6eme';
    case CINQUIEME = '5eme';
    case QUATRIEME = '4eme';
    case TROISIEME = '3eme';

    // Lycée
    case SECONDE   = '2nde';
    case PREMIERE  = '1ere';
    case TERMINALE = 'Tle';

    public function label(): string
    {
        return match($this) {
            self::PS        => 'Petite Section',
            self::MS        => 'Moyenne Section',
            self::GS        => 'Grande Section',
            self::CP        => 'Cours Préparatoire',
            self::CE1       => 'CE1',
            self::CE2       => 'CE2',
            self::CM1       => 'CM1',
            self::CM2       => 'CM2',
            self::SIXIEME   => '6ème',
            self::CINQUIEME => '5ème',
            self::QUATRIEME => '4ème',
            self::TROISIEME => '3ème',
            self::SECONDE   => '2nde',
            self::PREMIERE  => '1ère',
            self::TERMINALE => 'Terminale',
        };
    }

    public function level(): AcademicLevelEnum
    {
        return match($this) {
            self::PS, self::MS, self::GS
                => AcademicLevelEnum::PRESCOLAIRE,
            self::CP, self::CE1, self::CE2, self::CM1, self::CM2
                => AcademicLevelEnum::PRIMAIRE,
            self::SIXIEME, self::CINQUIEME, self::QUATRIEME, self::TROISIEME
                => AcademicLevelEnum::COLLEGE,
            self::SECONDE, self::PREMIERE, self::TERMINALE
                => AcademicLevelEnum::LYCEE,
        };
    }

    public static function prescolaireClasses(): array { return [self::PS, self::MS, self::GS]; }
    public static function primaireClasses(): array    { return [self::CP, self::CE1, self::CE2, self::CM1, self::CM2]; }
    public static function collegeClasses(): array     { return [self::SIXIEME, self::CINQUIEME, self::QUATRIEME, self::TROISIEME]; }
    public static function lyceeClasses(): array       { return [self::SECONDE, self::PREMIERE, self::TERMINALE]; }

    public static function optionsForLevel(AcademicLevelEnum $level): array
    {
        return collect(self::cases())
            ->filter(fn($c) => $c->level() === $level)
            ->map(fn($c) => ['id' => $c->value, 'name' => $c->label()])
            ->values()->toArray();
    }
}
