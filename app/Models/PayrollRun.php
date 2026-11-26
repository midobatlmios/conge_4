<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One month of payroll. 'draft': Paie prepares and adjusts; 'ready': sent for
 * validation; 'locked': validated by RH or Admin (not the person who prepared
 * it), payslips final and visible to employees.
 *
 * @property \Illuminate\Support\Carbon $month first day of the month
 * @property string $status
 */
class PayrollRun extends Model
{
    public const STATUS_LABELS = ['draft' => 'En préparation', 'ready' => 'À valider', 'locked' => 'Validée'];

    protected $fillable = ['month', 'status', 'prepared_by', 'prepared_at', 'locked_by', 'locked_at'];

    /** @var array<string, string> */
    protected $casts = ['month' => 'date:Y-m-d', 'prepared_at' => 'datetime', 'locked_at' => 'datetime'];

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }
}
