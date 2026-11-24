<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Demande;
use App\Models\Holiday;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Leave-day arithmetic: working days, the yearly allowance and what each
 * request takes from it. Rules come from config/conge.php; public holidays
 * from the holidays table (managed in the admin UI).
 */
class LeaveCalculator
{
    /** Annual leave allowance per employee per calendar year. */
    public function maxDaysPerYear(): int
    {
        return (int) config('conge.max_days_per_year');
    }

    /** Maximum working days for a fixed-length leave type (mariage, naissance, décès), or null. */
    public function typeLimit(LeaveType $type): ?int
    {
        return config('conge.type_limits')[$type->value] ?? null;
    }

    /** Whether a leave type uses up any of the yearly allowance. */
    public function countsTowardsAllowance(LeaveType $type): bool
    {
        return in_array($type->value, $this->allowanceTypeValues(), true);
    }

    /**
     * Working days between two dates (both included), skipping non-working
     * weekdays and public holidays.
     */
    public function workingDays(CarbonInterface|string $start, CarbonInterface|string $end): int
    {
        $day = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();
        $workingDays = config('conge.working_days');
        $holidays = Holiday::keys();
        $count = 0;

        for (; $day->lte($end); $day->addDay()) {
            if (in_array($day->isoWeekday(), $workingDays, true)
                && ! in_array($day->format('m-d'), $holidays, true)
                && ! in_array($day->format('Y-m-d'), $holidays, true)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Days a leave takes from the allowance, limited to one calendar year when given.
     * Fully counted types use their working days in that year; fixed deductions
     * (mariage, décès) count once, in the year the leave starts.
     */
    public function allowanceDays(LeaveType $type, string $start, string $end, ?int $year = null): int
    {
        $start = Carbon::parse($start);
        $end = Carbon::parse($end);

        if (in_array($type->value, config('conge.counted_types'), true)) {
            if ($year) {
                $start = $start->max(Carbon::create($year, 1, 1));
                $end = $end->min(Carbon::create($year, 12, 31));
            }

            return $start->lte($end) ? $this->workingDays($start, $end) : 0;
        }

        $deduction = config('conge.fixed_deductions')[$type->value] ?? null;

        if ($deduction === null || ($year && $start->year !== $year)) {
            return 0;
        }

        return min($deduction, $this->workingDays($start, $end));
    }

    /** Allowance days a user's accepted requests use in a year. */
    public function usedDays(int $userId, int $year, ?int $excludeDemandeId = null): int
    {
        return (int) Demande::query()
            ->where('user_id', $userId)
            ->where('etat', LeaveStatus::Accepted)
            ->whereIn('type_conge', $this->allowanceTypeValues())
            ->when($excludeDemandeId !== null, fn ($q) => $q->whereKeyNot($excludeDemandeId))
            ->whereDate('date_debut', '<=', Carbon::create($year, 12, 31))
            ->whereDate('date_fin', '>=', Carbon::create($year, 1, 1))
            ->get()
            ->sum(fn (Demande $demande) => $this->allowanceDays(
                $demande->type_conge,
                $demande->date_debut,
                $demande->date_fin,
                $year
            ));
    }

    /** Allowance days a user has left in a year. */
    public function remainingDays(int $userId, int $year): int
    {
        return $this->maxDaysPerYear() - $this->usedDays($userId, $year);
    }

    /**
     * First calendar year in which accepting this leave would exceed the
     * user's allowance, or null if it fits.
     */
    public function yearOverAllowance(
        int $userId,
        LeaveType $type,
        string $start,
        string $end,
        ?int $excludeDemandeId = null
    ): ?int {
        for ($year = Carbon::parse($start)->year; $year <= Carbon::parse($end)->year; $year++) {
            $requested = $this->allowanceDays($type, $start, $end, $year);
            $used = $this->usedDays($userId, $year, $excludeDemandeId);

            if ($used + $requested > $this->maxDaysPerYear()) {
                return $year;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function allowanceTypeValues(): array
    {
        return [...config('conge.counted_types'), ...array_keys(config('conge.fixed_deductions'))];
    }
}
