<?php

namespace App\Enums;

/** Status of a leave request. The values are what the database stores. */
enum LeaveStatus: string
{
    case Pending = 'en attente';
    case Accepted = 'acceptée';
    case Refused = 'refusée';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Accepted => 'Acceptée',
            self::Refused => 'Refusée',
        };
    }

    /** Whether an admin has accepted or refused the request. */
    public function isDecided(): bool
    {
        return $this !== self::Pending;
    }
}
