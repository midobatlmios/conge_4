<?php

namespace App\Enums;

enum AttestationStatus: string
{
    case Requested = 'demandee';
    case Issued = 'delivree';
    case Refused = 'refusee';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Demandée',
            self::Issued => 'Délivrée',
            self::Refused => 'Refusée',
        };
    }
}
