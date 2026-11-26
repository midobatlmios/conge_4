<?php

namespace App\Enums;

/** The 4-level scale of annual reviews, per objective and overall. */
enum ReviewRating: string
{
    case Insufficient = 'insuffisant';
    case ToImprove = 'a_ameliorer';
    case Achieved = 'atteint';
    case Exceeded = 'depasse';

    public function label(): string
    {
        return match ($this) {
            self::Insufficient => 'Insuffisant',
            self::ToImprove => 'À améliorer',
            self::Achieved => 'Atteint',
            self::Exceeded => 'Dépassé',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $r) => ['value' => $r->value, 'label' => $r->label()], self::cases());
    }
}
