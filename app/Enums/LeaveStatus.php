<?php

namespace App\Enums;

/** Status of a leave request. The values are what the database stores. */
enum LeaveStatus: string
{
    /** Waiting for the employee's manager (or for HR when there is no manager). */
    case Pending = 'en attente';
    /** The manager approved; waiting for HR's final decision. */
    case ManagerApproved = 'validée manager';
    case Accepted = 'acceptée';
    case Refused = 'refusée';
    /** The employee asked to cancel an accepted leave; its days stay booked until an admin decides. */
    case CancellationRequested = 'annulation demandée';
    /** An accepted leave that was cancelled: its days are returned. */
    case Cancelled = 'annulée';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::ManagerApproved => 'Validée par le responsable',
            self::Accepted => 'Acceptée',
            self::Refused => 'Refusée',
            self::CancellationRequested => 'Annulation demandée',
            self::Cancelled => 'Annulée',
        };
    }

    /** A final decision: the employee is told. */
    public function isDecided(): bool
    {
        return in_array($this, [self::Accepted, self::Refused], true);
    }

    /** Requests still waiting for a decision (manager's or HR's). */
    public static function awaiting(): array
    {
        return [self::Pending, self::ManagerApproved];
    }

    /** Statuses whose days are booked: they use the allowance and appear as leave. */
    public static function booked(): array
    {
        return [self::Accepted, self::CancellationRequested];
    }

    /** Statuses an admin can set from the edit form; cancellation has its own actions. */
    public static function decisions(): array
    {
        return [self::Pending, self::ManagerApproved, self::Accepted, self::Refused];
    }

    /** Requests that no longer take any days: never shown as leave, never overlapping. */
    public static function inactive(): array
    {
        return [self::Refused, self::Cancelled];
    }
}
