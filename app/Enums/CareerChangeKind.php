<?php

namespace App\Enums;

/** Why an employee's position or pay changed (career history). */
enum CareerChangeKind: string
{
    case Promotion = 'promotion';
    case Raise = 'augmentation';
    case Transfer = 'mutation';
    case Reclassification = 'reclassement';
    case Other = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Promotion => 'Promotion',
            self::Raise => 'Augmentation de salaire',
            self::Transfer => 'Mutation',
            self::Reclassification => 'Reclassement',
            self::Other => 'Autre changement',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $kind) => ['value' => $kind->value, 'label' => $kind->label()], self::cases());
    }
}
