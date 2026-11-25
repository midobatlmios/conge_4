<?php

namespace App\Models;

use App\Enums\ContractType;
use App\Enums\StaffCategory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One employment contract of an employee. An employee's contracts never
 * overlap; the current one is the latest that has started.
 *
 * @property int $user_id
 * @property ContractType $type
 * @property StaffCategory|null $category
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property \Illuminate\Support\Carbon|null $trial_end
 * @property bool $trial_renewed
 * @property string|null $gross_salary
 */
class EmploymentContract extends Model
{
    protected $fillable = ['user_id', 'type', 'category', 'job_title', 'gross_salary', 'start_date', 'end_date', 'trial_end', 'trial_renewed', 'notes', 'created_by'];

    /** @var array<string, string> */
    protected $casts = [
        'type' => ContractType::class,
        'category' => StaffCategory::class,
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'trial_end' => 'date:Y-m-d',
        'trial_renewed' => 'boolean',
        // Monthly gross salary in MAD, encrypted (attestation de salaire).
        'gross_salary' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 'upcoming' (not started), 'active' or 'ended' on the given day. */
    public function status(?CarbonInterface $on = null): string
    {
        $on ??= today();

        return match (true) {
            $this->start_date->gt($on) => 'upcoming',
            $this->end_date !== null && $this->end_date->lt($on) => 'ended',
            default => 'active',
        };
    }

    /** Still in the trial period on the given day. */
    public function inTrial(?CarbonInterface $on = null): bool
    {
        $on ??= today();

        return $this->trial_end !== null && $this->trial_end->gte($on) && $this->start_date->lte($on);
    }
}
