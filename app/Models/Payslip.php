<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One employee's payslip for a month. 'inputs' holds what Paie may adjust
 * (overtime hours) and what the figures come from; 'figures' every computed
 * line (PayslipCalculator) plus the employee details printed on it.
 *
 * @property int $payroll_run_id
 * @property int $user_id
 * @property array<string, mixed> $inputs
 * @property array<string, mixed> $figures
 */
class Payslip extends Model
{
    protected $fillable = ['payroll_run_id', 'user_id', 'inputs', 'figures'];

    /** @var array<string, string> */
    protected $casts = ['inputs' => 'encrypted:array', 'figures' => 'encrypted:array'];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fileName(): string
    {
        return 'bulletin-'.$this->run->month->format('Y-m').'-'.str($this->figures['employee']['name'] ?? 'employe')->slug().'.pdf';
    }
}
