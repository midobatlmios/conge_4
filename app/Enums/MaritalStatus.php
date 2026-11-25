<?php

namespace App\Enums;

/** Family situation, used with the number of children for IGR deductions on payroll. */
enum MaritalStatus: string
{
    case Single = 'celibataire';
    case Married = 'marie';
    case Divorced = 'divorce';
    case Widowed = 'veuf';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Célibataire',
            self::Married => 'Marié(e)',
            self::Divorced => 'Divorcé(e)',
            self::Widowed => 'Veuf / veuve',
        };
    }
}
