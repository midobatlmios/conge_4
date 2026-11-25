<?php

namespace App\Enums;

/**
 * Staff category of a contract. It sets the trial period the Labour Code
 * allows on a CDI (art. 14): cadres 3 months, employés 1.5 months, ouvriers
 * 15 days, renewable once. The form suggests it; HR can change it.
 */
enum StaffCategory: string
{
    case Executive = 'cadre';
    case Employee = 'employe';
    case Worker = 'ouvrier';

    public function label(): string
    {
        return match ($this) {
            self::Executive => 'Cadre',
            self::Employee => 'Employé',
            self::Worker => 'Ouvrier',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $category) => ['value' => $category->value, 'label' => $category->label()], self::cases());
    }
}
