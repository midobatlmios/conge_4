<?php

namespace App\Enums;

/**
 * Kind of leave. The values are what the database stores and what
 * config/conge.php uses as keys.
 */
enum LeaveType: string
{
    case Annual = 'annuel';
    case Marriage = 'mariage';
    case Birth = 'naissance';
    case Death = 'deces';
    case Unpaid = 'sans_solde';
    case Recovery = 'recuperation';
    case Sick = 'maladie';

    public function label(): string
    {
        return match ($this) {
            self::Annual => 'Congé annuel',
            self::Marriage => 'Congé de mariage',
            self::Birth => 'Congé de naissance',
            self::Death => 'Congé de décès',
            self::Unpaid => 'Congé sans solde',
            self::Recovery => 'Récupération',
            self::Sick => 'Congé maladie',
        };
    }

    /**
     * Declared rather than requested: no manager approval (the manager is only
     * told), HR validates it once the medical certificate is in.
     */
    public function isDeclared(): bool
    {
        return $this === self::Sick;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
