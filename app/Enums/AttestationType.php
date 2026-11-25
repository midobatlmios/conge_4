<?php

namespace App\Enums;

enum AttestationType: string
{
    case Work = 'travail';
    case Salary = 'salaire';

    public function label(): string
    {
        return match ($this) {
            self::Work => 'Attestation de travail',
            self::Salary => 'Attestation de salaire',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $type) => ['value' => $type->value, 'label' => $type->label()], self::cases());
    }
}
