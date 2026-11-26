<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manager's approvals handed to a delegate from starts_on to ends_on
 * (included). During the period the delegate gets the notifications and
 * reminders and can approve or refuse; the manager can still decide too.
 *
 * @property int $manager_id
 * @property int $delegate_id
 * @property \Illuminate\Support\Carbon $starts_on
 * @property \Illuminate\Support\Carbon $ends_on
 */
class ApprovalDelegation extends Model
{
    protected $fillable = ['manager_id', 'delegate_id', 'starts_on', 'ends_on', 'created_by'];

    /** @var array<string, string> */
    protected $casts = [
        'starts_on' => 'date:Y-m-d',
        'ends_on' => 'date:Y-m-d',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    /** @param Builder<ApprovalDelegation> $query  In force today. */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereDate('starts_on', '<=', today())->whereDate('ends_on', '>=', today());
    }

    /** @param Builder<ApprovalDelegation> $query  In force today or later. */
    public function scopeNotOver(Builder $query): void
    {
        $query->whereDate('ends_on', '>=', today());
    }

    /**
     * As the pages show it.
     *
     * @return array<string, mixed>
     */
    public function present(): array
    {
        return [
            'id' => $this->id,
            'manager' => $this->manager?->name,
            'delegate' => $this->delegate?->name,
            'delegate_id' => $this->delegate_id,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'current' => $this->starts_on->lte(today()) && $this->ends_on->gte(today()),
        ];
    }
}
