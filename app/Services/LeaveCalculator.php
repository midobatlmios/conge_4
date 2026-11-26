<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Demande;
use App\Models\Holiday;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Leave-day arithmetic: working days, the yearly allowance and what each
 * request takes from it. Rules come from config/conge.php; public holidays
 * from the holidays table (managed in the admin UI).
 */
class LeaveCalculator
{
    /** The site whose local holidays count (null: national holidays only). */
    private ?int $siteId = null;

    /** Already set to a site (forSite / forUser). */
    private bool $scoped = false;

    /** @var array<int, int|null> Site of each user, loaded once. */
    private static ?array $userSites = null;

    /** The same calculator, counting the holidays of a site. */
    public function forSite(?int $siteId): static
    {
        $copy = clone $this;
        $copy->siteId = $siteId;
        $copy->scoped = true;

        return $copy;
    }

    /** The same calculator, counting the holidays of a user's site. */
    public function forUser(User|int|null $user): static
    {
        if ($user instanceof User) {
            return $this->forSite($user->site_id);
        }
        if ($user === null) {
            return $this->forSite(null);
        }
        self::$userSites ??= User::pluck('site_id', 'id')->all();

        return $this->forSite(self::$userSites[$user] ?? null);
    }

    /** Forget the cached user → site map (after a site change, and in tests). */
    public static function flushUserSites(): void
    {
        self::$userSites = null;
    }

    /** Annual leave earned over a full calendar year, before any seniority bonus. */
    public function maxDaysPerYear(): int
    {
        return (int) config('conge.max_days_per_year');
    }

    /**
     * Days a user earns over the whole of a year: the base allowance plus the
     * seniority bonus for every full block of service years on 1 January, capped.
     * Without a hire date (older accounts) there is no bonus.
     */
    public function annualEntitlement(User $user, int $year): float
    {
        $base = (float) $this->maxDaysPerYear();

        if (! $user->hire_date) {
            return $base;
        }

        $years = max(0, (int) floor($user->hire_date->diffInYears(Carbon::create($year, 1, 1), false)));
        $bonus = intdiv($years, (int) config('conge.seniority_years')) * (float) config('conge.seniority_bonus_days');

        return min($base + $bonus, (float) config('conge.max_days_with_seniority'));
    }

    /**
     * Days earned in a year by a given date: one twelfth of the year's entitlement
     * at the end of each full month worked (from 1 January, or the hire date if
     * later), rounded down to the half day.
     */
    public function earnedDays(User $user, int $year, CarbonInterface|string $asOf): float
    {
        $yearStart = Carbon::create($year, 1, 1);
        $from = $user->hire_date && $user->hire_date->gt($yearStart)
            ? $user->hire_date->copy()->startOfDay()
            : $yearStart;
        // The day after $asOf, so a month ending on $asOf counts as completed.
        $until = Carbon::parse($asOf)->startOfDay()->min(Carbon::create($year, 12, 31))->addDay();

        if ($until->lte($from)) {
            return 0.0;
        }

        $months = (int) floor($from->diffInMonths($until));
        $entitlement = $this->annualEntitlement($user, $year);

        return min($entitlement, floor($months * $entitlement / 12 * 2) / 2);
    }

    /** Date from which annual leave may be taken (6 months after hiring), or null if unknown. */
    public function eligibleFrom(User $user): ?Carbon
    {
        return $user->hire_date?->copy()->addMonthsNoOverflow((int) config('conge.eligibility_months'));
    }

    /** Whether this leave starts before the user may take annual leave. */
    public function startsBeforeEligibility(User $user, LeaveType $type, string $start): bool
    {
        $eligibleFrom = $this->eligibleFrom($user);

        return $eligibleFrom !== null
            && in_array($type->value, config('conge.counted_types'), true)
            && Carbon::parse($start)->lt($eligibleFrom);
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
        $count = 0;

        for (; $day->lte($end); $day->addDay()) {
            if ($this->isWorkingDay($day)) {
                $count++;
            }
        }

        return $count;
    }

    /** A working weekday that is not a public holiday. */
    public function isWorkingDay(CarbonInterface|string $day): bool
    {
        $day = Carbon::parse($day);
        $holidays = Holiday::keys($this->siteId);

        return in_array($day->isoWeekday(), config('conge.working_days'), true)
            && ! in_array($day->format('m-d'), $holidays, true)
            && ! in_array($day->format('Y-m-d'), $holidays, true);
    }

    /** Whether a leave type can start in the afternoon or end at noon. */
    public function allowsHalfDays(LeaveType $type): bool
    {
        return in_array($type->value, config('conge.half_day_types'), true);
    }

    /**
     * Days of leave between two dates, counting half a day for a leave that starts
     * in the afternoon or ends at noon. Optionally only the part between $from and
     * $to: a half day only counts when its day is inside that part.
     */
    public function leaveDays(
        CarbonInterface|string $start,
        CarbonInterface|string $end,
        bool $startHalf = false,
        bool $endHalf = false,
        CarbonInterface|string|null $from = null,
        CarbonInterface|string|null $to = null,
    ): float {
        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();
        $from = $from ? Carbon::parse($from)->startOfDay()->max($start) : $start->copy();
        $to = $to ? Carbon::parse($to)->startOfDay()->min($end) : $end->copy();

        if ($from->gt($to)) {
            return 0.0;
        }

        $days = (float) $this->workingDays($from, $to);

        if ($startHalf && $from->isSameDay($start) && $this->isWorkingDay($start)) {
            $days -= 0.5;
        }
        if ($endHalf && $to->isSameDay($end) && $this->isWorkingDay($end)) {
            $days -= 0.5;
        }

        return max(0.0, $days);
    }

    /**
     * Days a leave takes from the allowance, limited to one calendar year and up
     * to a date when given. Fully counted types use their (half) days; fixed
     * deductions (mariage, décès) count once, in the year the leave starts.
     */
    public function allowanceDays(
        LeaveType $type,
        CarbonInterface|string $start,
        CarbonInterface|string $end,
        ?int $year = null,
        bool $startHalf = false,
        bool $endHalf = false,
        CarbonInterface|string|null $until = null,
    ): float {
        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();
        $to = $year ? Carbon::create($year, 12, 31) : $end->copy();
        if ($until) {
            $to = $to->min(Carbon::parse($until)->startOfDay());
        }

        if (in_array($type->value, config('conge.counted_types'), true)) {
            return $this->leaveDays($start, $end, $startHalf, $endHalf, $year ? Carbon::create($year, 1, 1) : null, $to);
        }

        $deduction = config('conge.fixed_deductions')[$type->value] ?? null;

        if ($deduction === null || ($year && $start->year !== $year) || $start->gt($to)) {
            return 0.0;
        }

        return (float) min($deduction, $this->workingDays($start, $end->min($to)));
    }

    /** Allowance days a user's accepted requests use in a year up to a date (included). */
    public function usedDaysUntil(int $userId, int $year, CarbonInterface|string $date): float
    {
        // Count the holidays of this employee's site.
        if (! $this->scoped) {
            return $this->forUser($userId)->usedDaysUntil(...func_get_args());
        }

        $date = Carbon::parse($date)->startOfDay();

        return (float) Demande::query()
            ->where('user_id', $userId)
            ->whereIn('etat', LeaveStatus::booked())
            ->whereIn('type_conge', $this->allowanceTypeValues())
            ->whereDate('date_debut', '<=', $date->copy()->min(Carbon::create($year, 12, 31)))
            ->whereDate('date_fin', '>=', Carbon::create($year, 1, 1))
            ->get()
            ->sum(fn (Demande $demande) => $this->allowanceDays(
                $demande->type_conge,
                $demande->date_debut,
                $demande->date_fin,
                $year,
                $demande->start_half,
                $demande->end_half,
                $date,
            ));
    }

    /** Allowance days a user's accepted requests use in a year. */
    public function usedDays(int $userId, int $year, ?int $excludeDemandeId = null): float
    {
        // Count the holidays of this employee's site.
        if (! $this->scoped) {
            return $this->forUser($userId)->usedDays(...func_get_args());
        }

        return (float) $this->acceptedInYear($userId, $year, $excludeDemandeId)->sum('days');
    }

    /** Last day (end of 31 March by default) on which days carried into a year can be used. */
    public function carryOverExpiry(int $year): Carbon
    {
        return Carbon::parse($year.'-'.config('conge.carry_over_expiry'));
    }

    /**
     * Unused days of the previous year carried into this one. Only that year's own
     * days carry over (days it had itself carried in never roll again), and only
     * from the year the account was created in the app: earlier leave was not
     * recorded here.
     */
    public function carriedOver(User $user, int $year): float
    {
        // Count the holidays of this employee's site.
        if (! $this->scoped) {
            return $this->forUser($user)->carriedOver(...func_get_args());
        }

        $previous = $year - 1;

        if ($previous < ($user->created_at?->year ?? now()->year)) {
            return 0.0;
        }

        $earned = $this->earnedDays($user, $previous, Carbon::create($previous, 12, 31));
        $usedOwnDays = $this->usedDays($user->id, $previous) - $this->carryUsed($user, $previous);

        return max(0.0, $earned - $usedOwnDays);
    }

    /**
     * Days a user can use in a year by a given date: earned so far, plus the days
     * carried in (all of them until they expire, then only those actually used).
     */
    public function availableDays(User $user, int $year, CarbonInterface|string $asOf): float
    {
        // Count the holidays of this employee's site.
        if (! $this->scoped) {
            return $this->forUser($user)->availableDays(...func_get_args());
        }

        $carried = Carbon::parse($asOf)->lte($this->carryOverExpiry($year))
            ? $this->carriedOver($user, $year)
            : $this->carryUsed($user, $year);

        return $this->earnedDays($user, $year, $asOf) + $carried;
    }

    /**
     * Days available in a year so far (all of a past year, none of a future one)
     * minus the days its accepted requests use, planned ones included.
     */
    public function remainingDays(int $userId, int $year): float
    {
        // Count the holidays of this employee's site.
        if (! $this->scoped) {
            return $this->forUser($userId)->remainingDays(...func_get_args());
        }

        return $this->availableDays(User::findOrFail($userId), $year, $this->asOf($year)) - $this->usedDays($userId, $year);
    }

    /**
     * The signed-in user's balance for the current year, as shown in the app.
     *
     * @return array<string, mixed>
     */
    public function balance(User $user): array
    {
        // Count the holidays of this employee's site.
        if (! $this->scoped) {
            return $this->forUser($user)->balance(...func_get_args());
        }

        $year = now()->year;
        $expiry = $this->carryOverExpiry($year);
        $carried = $this->carriedOver($user, $year);
        $carryUsed = $this->carryUsed($user, $year);
        $used = $this->usedDays($user->id, $year);

        return [
            'annualDays' => $this->annualEntitlement($user, $year),
            'earnedDays' => $this->earnedDays($user, $year, now()),
            'usedDays' => $used,
            'remainingDays' => $this->availableDays($user, $year, now()) - $used,
            // Days brought from last year, and how many are still unused before they expire.
            'carriedDays' => $carried,
            'carryUsedDays' => $carryUsed,
            'carryLeftDays' => now()->lte($expiry) ? $carried - $carryUsed : 0.0,
            'carryExpires' => $expiry->toDateString(),
            'hireDate' => $user->hire_date?->toDateString(),
            'eligibleFrom' => $this->eligibleFrom($user)?->toDateString(),
        ];
    }

    /**
     * First calendar year in which accepting this leave would use days not yet
     * available, or null if it fits. Requests are taken in date order: each one
     * may only use what has been earned by the day it starts, after the earlier
     * ones, plus carried-over days for the part of the leave before they expire.
     */
    public function yearOverAllowance(
        int $userId,
        LeaveType $type,
        string $start,
        string $end,
        ?int $excludeDemandeId = null,
        bool $startHalf = false,
        bool $endHalf = false,
    ): ?int {
        // Count the holidays of this employee's site.
        if (! $this->scoped) {
            return $this->forUser($userId)->yearOverAllowance(...func_get_args());
        }

        $user = User::findOrFail($userId);

        for ($year = Carbon::parse($start)->year; $year <= Carbon::parse($end)->year; $year++) {
            $requested = $this->allowanceDays($type, $start, $end, $year, $startHalf, $endHalf);

            if ($requested == 0) {
                continue;
            }

            $newStart = Carbon::parse($start)->max(Carbon::create($year, 1, 1));
            $carried = $this->carriedOver($user, $year);
            $requests = $this->acceptedInYear($userId, $year, $excludeDemandeId)
                ->push([
                    'start' => $newStart,
                    'days' => $requested,
                    'beforeExpiry' => $this->daysBeforeExpiry($type, $start, $end, $year, $startHalf, $endHalf),
                ])
                ->sortBy(fn (array $request) => $request['start']->timestamp);

            $cumulative = 0;
            $cumulativeBeforeExpiry = 0;
            foreach ($requests as $request) {
                $cumulative += $request['days'];
                $cumulativeBeforeExpiry += $request['beforeExpiry'];
                // Carried days only cover leave days up to their expiry date.
                $available = $this->earnedDays($user, $year, $request['start']) + min($carried, $cumulativeBeforeExpiry);

                // Requests before this one were already checked when accepted.
                if ($request['start']->gte($newStart) && $cumulative > $available) {
                    return $year;
                }
            }
        }

        return null;
    }

    /**
     * A user's accepted requests that use allowance days in a year, with their
     * start in that year and the days they use there.
     *
     * @return Collection<int, array{start: Carbon, days: float, beforeExpiry: float}>
     */
    private function acceptedInYear(int $userId, int $year, ?int $excludeDemandeId = null): Collection
    {
        return Demande::query()
            ->where('user_id', $userId)
            // A leave whose cancellation is pending keeps its days until an admin approves.
            ->whereIn('etat', LeaveStatus::booked())
            ->whereIn('type_conge', $this->allowanceTypeValues())
            ->when($excludeDemandeId !== null, fn ($q) => $q->whereKeyNot($excludeDemandeId))
            ->whereDate('date_debut', '<=', Carbon::create($year, 12, 31))
            ->whereDate('date_fin', '>=', Carbon::create($year, 1, 1))
            ->get()
            ->map(fn (Demande $demande) => [
                'start' => Carbon::parse($demande->date_debut)->max(Carbon::create($year, 1, 1)),
                'days' => $this->allowanceDays(
                    $demande->type_conge, $demande->date_debut, $demande->date_fin, $year, $demande->start_half, $demande->end_half
                ),
                'beforeExpiry' => $this->daysBeforeExpiry(
                    $demande->type_conge, $demande->date_debut, $demande->date_fin, $year, $demande->start_half, $demande->end_half
                ),
            ])
            ->values()
            ->toBase();
    }

    /** Allowance days a leave uses in a year up to the carry-over expiry date. */
    private function daysBeforeExpiry(
        LeaveType $type,
        CarbonInterface|string $start,
        CarbonInterface|string $end,
        int $year,
        bool $startHalf = false,
        bool $endHalf = false,
    ): float {
        return $this->allowanceDays($type, $start, $end, $year, $startHalf, $endHalf, $this->carryOverExpiry($year));
    }

    /** Carried-over days actually used in a year: leave days up to the expiry date, at most what was carried. */
    public function carryUsed(User $user, int $year): float
    {
        // Count the holidays of this employee's site.
        if (! $this->scoped) {
            return $this->forUser($user)->carryUsed(...func_get_args());
        }

        $carried = $this->carriedOver($user, $year);

        return $carried > 0
            ? min($carried, (float) $this->acceptedInYear($user->id, $year)->sum('beforeExpiry'))
            : 0.0;
    }

    /** The date a year's balance is read at: today, or the end/start of a past/future year. */
    private function asOf(int $year): Carbon
    {
        return match (true) {
            $year < now()->year => Carbon::create($year, 12, 31),
            $year > now()->year => Carbon::create($year, 1, 1)->subDay(),
            default => now(),
        };
    }

    /** @return list<string> */
    private function allowanceTypeValues(): array
    {
        return [...config('conge.counted_types'), ...array_keys(config('conge.fixed_deductions'))];
    }
}
