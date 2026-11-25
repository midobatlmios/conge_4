<?php

namespace App\Enums;

/**
 * What an account may do. Managers are not a role: anyone with active
 * employees reporting to them approves their requests first.
 *
 * - Admin: everything HR does, plus giving and removing the Admin/RH/Paie roles.
 * - RH: day-to-day HR (employees, leave decisions, personal data, holidays…);
 *   manages plain employee accounts only.
 * - Paie: read-only access for payroll / the accountant (export, overview,
 *   leave requests, employee files including RIB and CNSS).
 * - User: an employee.
 */
enum Role: string
{
    case Admin = 'admin';
    case Hr = 'rh';
    case Payroll = 'paie';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Hr => 'RH',
            self::Payroll => 'Paie (lecture seule)',
            self::User => 'Employé',
        };
    }

    /** Changes HR data: employees, leave decisions, personal data, holidays, departments. */
    public function managesHr(): bool
    {
        return $this === self::Admin || $this === self::Hr;
    }

    /** Reads HR data: every request, employee files, payroll export, overview. */
    public function viewsHr(): bool
    {
        return $this !== self::User;
    }
}
