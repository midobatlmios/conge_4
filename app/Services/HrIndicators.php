<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Demande;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * HR indicators for one year, optionally one department: headcount, hires
 * and departures, turnover, seniority, absenteeism and leave balances.
 *
 * Definitions:
 * - Employed on a day: hired on or before it (no hire date: since always) and
 *   not deactivated yet. A departure is the day the account was deactivated.
 * - Theoretical working days: each employee's working days while employed.
 * - Absenteeism (unplanned): sick + unpaid leave days ÷ theoretical working days.
 *   Planned leave (annual, recovery, family events) is reported apart.
 * - Turnover: departures ÷ average month-end headcount.
 * Only booked leave counts (accepted, or accepted with a cancellation pending).
 * The current year stops today.
 */
class HrIndicators
{
    /** Leave that is not planned: what absenteeism counts. */
    public const UNPLANNED = [LeaveType::Sick, LeaveType::Unpaid];

    /** Monthly French labels. */
    public const MONTHS = ['Janv.', 'Févr.', 'Mars', 'Avr.', 'Mai', 'Juin', 'Juil.', 'Août', 'Sept.', 'Oct.', 'Nov.', 'Déc.'];

    /** How many employees with the most unused days are listed. */
    private const TOP_BALANCES = 10;

    public function __construct(private readonly LeaveCalculator $calculator) {}

    /** @return array<string, mixed> */
    public function compute(int $year, ?int $departmentId = null): array
    {
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->startOfDay()->min(today());

        $people = User::with('department:id,name')
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->get();

        $workingDays = $this->workingDaysOf($start, $end);
        $leaves = Demande::query()
            ->whereIn('user_id', $people->modelKeys())
            ->whereIn('etat', LeaveStatus::booked())
            ->whereDate('date_debut', '<=', $end)
            ->whereDate('date_fin', '>=', $start)
            ->get();

        $months = $this->months($year, $end, $people, $workingDays, $leaves);
        $summary = $this->summary($start, $end, $people, $months);

        return [
            'year' => $year,
            'until' => $end->toDateString(),
            'summary' => $summary,
            'months' => $months,
            'departments' => $this->byDepartment($start, $end, $people, $workingDays, $leaves),
            'balances' => $this->balances($year, $end, $people),
        ];
    }

    /**
     * One row per month up to the end of the period.
     *
     * @return list<array<string, mixed>>
     */
    private function months(int $year, Carbon $end, Collection $people, array $workingDays, Collection $leaves): array
    {
        $rows = [];

        for ($month = 1; $month <= $end->month; $month++) {
            $from = Carbon::create($year, $month, 1)->startOfDay();
            $to = $from->copy()->endOfMonth()->startOfDay()->min($end);

            $theoretical = $people->sum(fn (User $user) => $this->employedWorkingDays($user, $from, $to, $workingDays));
            $sick = $this->leaveDaysIn($leaves->where('type_conge', LeaveType::Sick), $from, $to);
            $unpaid = $this->leaveDaysIn($leaves->where('type_conge', LeaveType::Unpaid), $from, $to);
            $planned = $this->leaveDaysIn($leaves->reject(fn (Demande $leave) => in_array($leave->type_conge, self::UNPLANNED, true)), $from, $to);

            $rows[] = [
                'month' => $month,
                'label' => self::MONTHS[$month - 1],
                'headcount' => $people->filter(fn (User $user) => $this->employedOn($user, $to))->count(),
                'hires' => $people->filter(fn (User $user) => $user->hire_date?->betweenIncluded($from, $to))->count(),
                'departures' => $people->filter(fn (User $user) => $user->deactivated_at?->copy()->startOfDay()->betweenIncluded($from, $to))->count(),
                'theoretical_days' => $theoretical,
                'sick_days' => $sick,
                'unpaid_days' => $unpaid,
                'planned_days' => $planned,
                'sick_stops' => $leaves->where('type_conge', LeaveType::Sick)
                    ->filter(fn (Demande $leave) => Carbon::parse($leave->date_debut)->betweenIncluded($from, $to))->count(),
                'absenteeism' => $this->rate($sick + $unpaid, $theoretical),
            ];
        }

        return $rows;
    }

    /** @param list<array<string, mixed>> $months */
    private function summary(Carbon $start, Carbon $end, Collection $people, array $months): array
    {
        $months = collect($months);
        $averageHeadcount = $months->avg('headcount') ?? 0;
        $departures = $months->sum('departures');
        $active = $people->filter(fn (User $user) => $this->employedOn($user, $end));
        $seniorities = $active->filter(fn (User $user) => $user->hire_date)
            ->map(fn (User $user) => $user->hire_date->floatDiffInYears($end));
        $theoretical = $months->sum('theoretical_days');
        $unplanned = $months->sum('sick_days') + $months->sum('unpaid_days');

        return [
            'headcount_start' => $people->filter(fn (User $user) => $this->employedOn($user, $start))->count(),
            'headcount_end' => $active->count(),
            'average_headcount' => round($averageHeadcount, 1),
            'hires' => $months->sum('hires'),
            'departures' => $departures,
            'turnover' => $this->rate($departures, $averageHeadcount),
            'average_seniority' => $seniorities->isEmpty() ? null : round($seniorities->avg(), 1),
            'theoretical_days' => $theoretical,
            'sick_days' => $months->sum('sick_days'),
            'unpaid_days' => $months->sum('unpaid_days'),
            'planned_days' => $months->sum('planned_days'),
            'sick_stops' => $months->sum('sick_stops'),
            'absenteeism' => $this->rate($unplanned, $theoretical),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function byDepartment(Carbon $start, Carbon $end, Collection $people, array $workingDays, Collection $leaves): array
    {
        return $people->groupBy(fn (User $user) => $user->department?->name ?? '')
            ->map(function (Collection $members, string $name) use ($start, $end, $workingDays, $leaves) {
                $ids = $members->modelKeys();
                $theoretical = $members->sum(fn (User $user) => $this->employedWorkingDays($user, $start, $end, $workingDays));
                $mine = $leaves->whereIn('user_id', $ids);
                $sick = $this->leaveDaysIn($mine->where('type_conge', LeaveType::Sick), $start, $end);
                $unpaid = $this->leaveDaysIn($mine->where('type_conge', LeaveType::Unpaid), $start, $end);

                return [
                    'name' => $name ?: null,
                    'headcount' => $members->filter(fn (User $user) => $this->employedOn($user, $end))->count(),
                    'theoretical_days' => $theoretical,
                    'sick_days' => $sick,
                    'unpaid_days' => $unpaid,
                    'absenteeism' => $this->rate($sick + $unpaid, $theoretical),
                ];
            })
            // Departments by name, employees without one last.
            ->sortBy(fn (array $row) => [$row['name'] === null, $row['name']])
            ->values()
            ->all();
    }

    /**
     * Unused annual leave at the end of the period: the total HR owes, the
     * employees with the most days left, and carried-over days about to expire.
     *
     * @return array<string, mixed>
     */
    private function balances(int $year, Carbon $end, Collection $people): array
    {
        $expiry = $this->calculator->carryOverExpiry($year);
        $carryCounts = $end->isSameDay(today()) && today()->lte($expiry);

        $rows = $people->filter(fn (User $user) => $this->employedOn($user, $end))
            ->map(function (User $user) use ($year, $end, $carryCounts) {
                $carried = $this->calculator->carriedOver($user, $year);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'department' => $user->department?->name,
                    'remaining' => round($this->calculator->availableDays($user, $year, $end) - $this->calculator->usedDaysUntil($user->id, $year, $end), 1),
                    'carry_left' => $carryCounts ? round($carried - $this->calculator->carryUsed($user, $year), 1) : 0.0,
                ];
            })
            ->sortByDesc('remaining')
            ->values();

        return [
            'total_remaining' => round($rows->sum('remaining'), 1),
            'average_remaining' => $rows->isEmpty() ? 0.0 : round($rows->avg('remaining'), 1),
            'carry_expiring' => round($rows->sum('carry_left'), 1),
            'carry_expiry' => $carryCounts ? $expiry->toDateString() : null,
            'carry_people' => $rows->where('carry_left', '>', 0)->count(),
            'top' => $rows->take(self::TOP_BALANCES)->all(),
            'all' => $rows->all(),
        ];
    }

    /** @return array<string, true> Working days of the period, as Y-m-d keys. */
    private function workingDaysOf(Carbon $start, Carbon $end): array
    {
        $days = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if ($this->calculator->isWorkingDay($day)) {
                $days[$day->toDateString()] = true;
            }
        }

        return $days;
    }

    private function employedOn(User $user, Carbon $day): bool
    {
        return (! $user->hire_date || $user->hire_date->lte($day))
            && (! $user->deactivated_at || $user->deactivated_at->copy()->startOfDay()->gt($day));
    }

    /** Working days between two dates on which the user was employed. */
    private function employedWorkingDays(User $user, Carbon $from, Carbon $to, array $workingDays): int
    {
        $first = $user->hire_date ? $from->copy()->max($user->hire_date) : $from->copy();
        $last = $user->deactivated_at ? $to->copy()->min($user->deactivated_at->copy()->startOfDay()->subDay()) : $to->copy();
        $count = 0;

        for ($day = $first->copy()->startOfDay(); $day->lte($last); $day->addDay()) {
            if (isset($workingDays[$day->toDateString()])) {
                $count++;
            }
        }

        return $count;
    }

    private function leaveDaysIn(Collection $leaves, Carbon $from, Carbon $to): float
    {
        return (float) $leaves->sum(fn (Demande $leave) => $this->calculator->leaveDays(
            $leave->date_debut, $leave->date_fin, (bool) $leave->start_half, (bool) $leave->end_half, $from, $to
        ));
    }

    /** A percentage with one decimal, or null when there is nothing to divide by. */
    private function rate(float $part, float $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }
}
