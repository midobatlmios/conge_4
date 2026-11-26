<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An employee asking to fix a day's clocking (forgot to clock, wrong time).
 * Their manager (or the delegate) or HR approves it; it then replaces the
 * day's record.
 *
 * @property int $user_id
 * @property \Illuminate\Support\Carbon $date
 * @property string $clock_in
 * @property string|null $clock_out
 * @property string $reason
 * @property string $status 'pending', 'approved' or 'refused'
 * @property string|null $refusal_reason
 */
class AttendanceCorrection extends Model
{
    public const STATUS_LABELS = ['pending' => 'En attente', 'approved' => 'Acceptée', 'refused' => 'Refusée'];

    protected $fillable = ['user_id', 'date', 'clock_in', 'clock_out', 'reason', 'status', 'decided_by', 'decided_at', 'refusal_reason'];

    /** @var array<string, string> */
    protected $casts = ['date' => 'date:Y-m-d', 'decided_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    /** Who may decide: HR, or the employee's manager / today's delegate; never the employee. */
    public function canBeDecidedBy(User $user): bool
    {
        if ($this->user_id === $user->id || $this->status !== 'pending') {
            return false;
        }

        $managerId = $this->user?->manager_id;

        return $user->isHr()
            || ($managerId !== null && ($managerId === $user->id || in_array($managerId, $user->delegatingManagerIds(), true)));
    }

    /** @return array<string, mixed> */
    public function present(?User $viewer = null): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->relationLoaded('user') ? $this->user?->name : null,
            'date' => $this->date->toDateString(),
            'clock_in' => $this->clock_in,
            'clock_out' => $this->clock_out,
            'reason' => $this->reason,
            'status' => $this->status,
            'status_label' => self::STATUS_LABELS[$this->status] ?? $this->status,
            'refusal_reason' => $this->refusal_reason,
            'decided_by' => $this->relationLoaded('decider') ? $this->decider?->name : null,
            'can_decide' => $viewer ? $this->canBeDecidedBy($viewer) : false,
        ];
    }
}
