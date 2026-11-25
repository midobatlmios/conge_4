<?php

namespace App\Enums;

enum Gender: string
{
    case Male = 'homme';
    case Female = 'femme';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Homme',
            self::Female => 'Femme',
        };
    }

    /** Form of address used on attestations ("Monsieur X", "Madame Y"). */
    public function title(): string
    {
        return match ($this) {
            self::Male => 'Monsieur',
            self::Female => 'Madame',
        };
    }
}
