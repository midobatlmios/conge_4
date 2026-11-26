<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\MaritalStatus;
use App\Enums\StaffCategory;
use App\Models\Demande;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Moroccan payslip for one employee and month, with the rates of
 * config/payroll.php:
 *
 *   base salary (prorated to the working days employed that month)
 * − unpaid leave and unjustified absences (one day = monthly salary ÷ 26)
 * + seniority bonus (rate by completed years since the hire date, on that base)
 * + overtime (hourly rate = monthly salary ÷ 191 h, × 1.25 / 1.5 / 2; none
 *   pre-filled for cadres)
 * + taxable bonuses entered by Paie
 * = gross
 * − CNSS 4.48 % (gross capped at 6 000) − AMO 2.26 %
 * − IR: (gross − frais professionnels − CNSS − AMO) through the monthly
 *   barème, minus the family deduction (spouse if married + children, max 6)
 * + allowances that are not taxable (transport, panier… within legal caps)
 * − advances and loan instalments
 * = net to pay
 *
 * Employer contributions are computed too (payroll cost).
 */
class PayslipCalculator
{
    public function __construct(
        private readonly LeaveCalculator $leaves,
        private readonly AttendanceCalculator $attendance,
    ) {}

    /**
     * What the payslip is computed from, read from the employee's file and
     * attendance. Null when there is no salary to pay that month.
     *
     * @return array<string, mixed>|null
     */
    public function inputs(User $user, CarbonInterface $month): ?array
    {
        $start = Carbon::parse($month)->startOfMonth();
        $end = $start->copy()->endOfMonth()->startOfDay();
        $first = $user->hire_date ? $start->copy()->max($user->hire_date) : $start->copy();
        $last = $user->deactivated_at ? $end->copy()->min($user->deactivated_at->copy()->startOfDay()->subDay()) : $end->copy();

        if ($first->gt($last)) {
            return null;
        }

        $salary = $user->salaryOn($last);
        if ($salary === null || $salary <= 0) {
            return null;
        }

        $monthDays = $this->leaves->workingDays($start, $end);
        $employedDays = $this->leaves->workingDays($first, $last);
        // Cadres are not paid overtime from clocking (Paie may still enter hours).
        $executive = $user->currentContract()?->category === StaffCategory::Executive;
        $overtime = $executive ? ['25' => 0.0, '50' => 0.0, '100' => 0.0] : $this->overtimeHours($user, $start, $last);
        $unpaid = $this->unpaidDays($user, $first, $last);
        $absent = $this->absentDays($user, $first, $last);
        $profile = $user->profile;
        $dependents = ($profile?->marital_status === MaritalStatus::Married ? 1 : 0) + (int) ($profile?->children_count ?? 0);

        return [
            'base_salary' => $salary,
            'month_days' => $monthDays,
            'employed_days' => min($employedDays, $monthDays),
            'hire_date' => $user->hire_date?->toDateString(),
            'dependents' => min($dependents, (int) config('payroll.family_deduction_max_persons')),
            'overtime_hours' => $overtime,
            'overtime_from_attendance' => $overtime,
            'unpaid_days' => $unpaid,
            'unpaid_from_leave' => $unpaid,
            'absent_days' => $absent,
            'absent_from_attendance' => $absent,
            // Which of the values above Paie changed by hand (kept on "Recalculer").
            'adjusted' => ['overtime' => false, 'unpaid' => false, 'absent' => false],
            'bonuses' => [],
            'advances' => [],
        ];
    }

    /**
     * Every line of the payslip.
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function compute(array $inputs, CarbonInterface $month): array
    {
        $c = config('payroll');
        $end = Carbon::parse($month)->endOfMonth()->startOfDay();
        $round = fn (float $value) => round($value, 2);

        $ratio = $inputs['month_days'] > 0 ? $inputs['employed_days'] / $inputs['month_days'] : 0;
        $prorated = $round($inputs['base_salary'] * $ratio);

        // Unpaid leave and unjustified absences: one day = monthly salary ÷ 26.
        $dayValue = $inputs['base_salary'] / $c['days_per_month'];
        $unpaidDays = (float) ($inputs['unpaid_days'] ?? 0);
        $absentDays = (float) ($inputs['absent_days'] ?? 0);
        $absenceDeduction = $round(min($prorated, ($unpaidDays + $absentDays) * $dayValue));
        $base = $round($prorated - $absenceDeduction);

        // Paie's lines: taxable ones join the gross; others are paid on top of the net.
        $bonusLines = array_values(array_map(fn (array $line) => ['label' => $line['label'], 'amount' => $round((float) $line['amount']), 'taxable' => (bool) $line['taxable']], $inputs['bonuses'] ?? []));
        $taxableBonuses = $round(array_sum(array_column(array_filter($bonusLines, fn ($l) => $l['taxable']), 'amount')));
        $exemptAllowances = $round(array_sum(array_column(array_filter($bonusLines, fn ($l) => ! $l['taxable']), 'amount')));
        $advanceLines = array_values(array_map(fn (array $line) => ['label' => $line['label'], 'amount' => $round((float) $line['amount'])], $inputs['advances'] ?? []));
        $advances = $round(array_sum(array_column($advanceLines, 'amount')));

        $years = $inputs['hire_date'] ? (int) Carbon::parse($inputs['hire_date'])->diffInYears($end) : 0;
        $seniorityRate = 0.0;
        foreach ($c['seniority'] as $minYears => $rate) {
            if ($years >= $minYears) {
                $seniorityRate = $rate;
                break;
            }
        }
        $seniority = $round($base * $seniorityRate);

        $hourly = $inputs['base_salary'] / $c['monthly_hours'];
        $overtimeLines = [];
        foreach ($c['overtime_rates'] as $key => $multiplier) {
            $hours = (float) ($inputs['overtime_hours'][$key] ?? 0);
            if ($hours > 0) {
                $overtimeLines[] = ['label' => "Heures supplémentaires +{$key} %", 'hours' => $hours, 'rate' => $round($hourly * $multiplier), 'amount' => $round($hours * $hourly * $multiplier)];
            }
        }
        $overtime = $round(array_sum(array_column($overtimeLines, 'amount')));

        $gross = $round($base + $seniority + $overtime + $taxableBonuses);
        $cnss = $round(min($gross, $c['cnss_ceiling']) * $c['cnss_employee_rate']);
        $amo = $round($gross * $c['amo_employee_rate']);

        $pe = $c['professional_expenses'];
        $expensesRate = $gross <= $pe['threshold_monthly'] ? $pe['low_rate'] : $pe['high_rate'];
        $expenses = $round(min($gross * $expensesRate, $pe['cap_monthly']));
        $taxable = max(0, $round($gross - $expenses - $cnss - $amo));

        $irGross = 0.0;
        $irRate = 0.0;
        foreach ($c['ir_brackets'] as [$upTo, $rate, $deduction]) {
            if ($taxable <= $upTo) {
                $irGross = max(0, $taxable * $rate - $deduction);
                $irRate = $rate;
                break;
            }
        }
        $familyDeduction = $round($inputs['dependents'] * $c['family_deduction_per_person']);
        $ir = $round(max(0, $irGross - $familyDeduction));

        // Net pay: after contributions and tax, plus exempt allowances, minus advances.
        $netBeforeAdvances = $round($gross - $cnss - $amo - $ir + $exemptAllowances);
        $net = $round($netBeforeAdvances - $advances);
        // CNSS declared days: 26 for a full month, minus unpaid and absent days.
        $cnssDays = max(0, (int) round($c['days_per_month'] * $ratio - $unpaidDays - $absentDays));

        $employer = [];
        foreach ($c['employer'] as $key => $line) {
            $employer[$key] = ['label' => $line['label'], 'rate' => $line['rate'], 'amount' => $round(($line['capped'] ? min($gross, $c['cnss_ceiling']) : $gross) * $line['rate'])];
        }
        $employerTotal = $round(array_sum(array_column($employer, 'amount')));

        return [
            'base_salary' => $inputs['base_salary'],
            'employed_days' => $inputs['employed_days'],
            'month_days' => $inputs['month_days'],
            'prorated' => $prorated,
            'day_value' => $round($dayValue),
            'unpaid_days' => $unpaidDays,
            'absent_days' => $absentDays,
            'absence_deduction' => $absenceDeduction,
            'base' => $base,
            'bonus_lines' => $bonusLines,
            'taxable_bonuses' => $taxableBonuses,
            'exempt_allowances' => $exemptAllowances,
            'advance_lines' => $advanceLines,
            'advances' => $advances,
            'net_before_advances' => $netBeforeAdvances,
            'cnss_days' => $cnssDays,
            'seniority_years' => $years,
            'seniority_rate' => $seniorityRate,
            'seniority' => $seniority,
            'hourly_rate' => $round($hourly),
            'overtime_lines' => $overtimeLines,
            'overtime' => $overtime,
            'gross' => $gross,
            'cnss' => $cnss,
            'cnss_base' => $round(min($gross, $c['cnss_ceiling'])),
            'amo' => $amo,
            'professional_expenses_rate' => $expensesRate,
            'professional_expenses' => $expenses,
            'taxable' => $taxable,
            'ir_rate' => $irRate,
            'dependents' => $inputs['dependents'],
            'family_deduction' => $familyDeduction,
            'ir' => $ir,
            'deductions' => $round($cnss + $amo + $ir),
            'net' => $net,
            'employer' => $employer,
            'employer_total' => $employerTotal,
            'cost' => $round($gross + $employerTotal + $exemptAllowances),
        ];
    }

    /** @return array<string, mixed> The employee details printed on the payslip, frozen. */
    public function employee(User $user): array
    {
        $user->loadMissing(['department:id,name', 'profile']);

        return [
            'name' => $user->name,
            'matricule' => $user->matricule,
            'job_title' => $user->job_title,
            'department' => $user->department?->name,
            'category' => $user->currentContract()?->category?->label(),
            'hire_date' => $user->hire_date?->toDateString(),
            'cin' => $user->profile?->cin,
            'cnss_number' => $user->profile?->cnss_number,
        ];
    }

    /** Working days of accepted unpaid leave (sans solde) between two days, half days included. */
    private function unpaidDays(User $user, Carbon $from, Carbon $to): float
    {
        return (float) Demande::where('user_id', $user->id)
            ->where('type_conge', LeaveType::Unpaid)
            ->whereIn('etat', LeaveStatus::booked())
            ->whereDate('date_debut', '<=', $to)
            ->whereDate('date_fin', '>=', $from)
            ->get()
            ->sum(fn (Demande $leave) => $this->leaves->leaveDays($leave->date_debut, $leave->date_fin, (bool) $leave->start_half, (bool) $leave->end_half, $from, $to));
    }

    /** Unjustified absences from attendance between two days (0 when attendance is not tracked). */
    private function absentDays(User $user, Carbon $from, Carbon $to): float
    {
        $to = $to->copy()->min(today()->subDay());
        if (! $this->attendance->isTracking($to) || $from->gt($to)) {
            return 0.0;
        }

        return (float) $this->attendance->totals($this->attendance->days(collect([$user]), $from, $to)[$user->id] ?? [])['absent_days'];
    }

    /** @return array{25: float, 50: float, 100: float} Overtime hours clocked in the month (0 when attendance is not tracked). */
    private function overtimeHours(User $user, Carbon $from, Carbon $to): array
    {
        $to = $to->copy()->min(today());
        if (! $this->attendance->isTracking($to) || $from->gt($to)) {
            return ['25' => 0.0, '50' => 0.0, '100' => 0.0];
        }

        $totals = $this->attendance->totals($this->attendance->days(collect([$user]), $from, $to)[$user->id] ?? []);

        return [
            '25' => round($totals['overtime_25'] / 60, 2),
            '50' => round($totals['overtime_50'] / 60, 2),
            '100' => round($totals['overtime_100'] / 60, 2),
        ];
    }
}
