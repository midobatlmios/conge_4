<?php

namespace App\Enums;

enum ContractType: string
{
    case Permanent = 'cdi';
    case FixedTerm = 'cdd';
    case Anapec = 'anapec';
    case Internship = 'stage';

    public function label(): string
    {
        return match ($this) {
            self::Permanent => 'CDI',
            self::FixedTerm => 'CDD',
            self::Anapec => 'Contrat ANAPEC (insertion)',
            self::Internship => 'Stage',
        };
    }

    /** Every contract but a CDI has an end date. */
    public function requiresEndDate(): bool
    {
        return $this !== self::Permanent;
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $type) => ['value' => $type->value, 'label' => $type->label()], self::cases());
    }
}
