<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\AttendanceRecord;
use App\Models\CompanySetting;
use App\Models\Demande;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns clock-in / clock-out records into worked time, lateness, absences and
 * overtime, against the one company schedule (Pointage page).
 *
 * Rules (decided with HR):
 * - Working days are those of the leave rules (Mon–Fri, minus public holidays).
 *   The lunch break of the schedule is not worked time.
 * - Late: arrival more than the tolerance (10 min by default) after the start;
 *   the whole delay counts (08:41 for 08:30 = 11 min).
 * - Absent: a past working day, while employed and after tracking started,
 *   with no clocking and no leave covering it (sick leave declared counts).
 *   A half day of leave halves the expected time.
 * - Overtime, counted per day for information (Labour Code rates, no pay
 *   computed): on a working day, time worked beyond the expected time, at
 *   +25 % by day and +50 % at night (21:00–06:00); on a rest day or public
 *   holiday, all time worked, at +50 % by day and +100 % at night.
 */
class AttendanceCalculator
{
    private const NIGHT_START = 21 * 60;

    private const NIGHT_END = 6 * 60;

    private ?array $schedule = null;

    public function __construct(private readonly LeaveCalculator $leaves) {}

    /**
     * The schedule in minutes after midnight.
     *
     * @return array{start: int, lunch_start: int, lunch_end: int, end: int, tolerance: int, since: ?Carbon, expected: int}
     */
    public function schedule(): array
    {
        if ($this->schedule !== null) {
            return $this->schedule;
        }

        $company = CompanySetting::current();
        $start = self::minutes($company->work_start ?: '08:30');
        $lunchStart = self::minutes($company->lunch_start ?: '12:30');
        $lunchEnd = self::minutes($company->lunch_end ?: '14:00');
        $end = self::minutes($company->work_end ?: '18:00');

        return $this->schedule = [
            'start' => $start,
            'lunch_start' => $lunchStart,
            'lunch_end' => $lunchEnd,
            'end' => $end,
            'tolerance' => $company->late_tolerance_minutes ?? 10,
            'since' => $company->attendance_started_on ? Carbon::parse($company->attendance_started_on)->startOfDay() : null,
            'expected' => ($lunchStart - $start) + ($end - $lunchEnd),
        ];
    }

    /** Forget the cached schedule (after HR changes it, and in tests). */
    public function refresh(): void
    {
        $this->schedule = null;
    }

    /** Tracking has started (HR set a start date that has come). */
    public function isTracking(?CarbonInterface $on = null): bool
    {
        $since = $this->schedule()['since'];

        return $since !== null && $since->lte($on ?? today());
    }

    public static function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }

    /**
     * Every day of a period for some employees.
     *
     * @param  Collection<int, User>  $people
     * @return array<int, list<array<string, mixed>>> Days by user id
     */
    public function days(Collection $people, CarbonInterface $from, CarbonInterface $to): array
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();
        $ids = $people->pluck('id')->all();

        $records = AttendanceRecord::whereIn('user_id', $ids)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $rows) => $rows->keyBy(fn (AttendanceRecord $r) => $r->date->toDateString()));

        $leaves = Demande::whereIn('user_id', $ids)
            ->whereDate('date_debut', '<=', $to)
            ->whereDate('date_fin', '>=', $from)
            ->where(fn ($query) => $query->whereIn('etat', LeaveStatus::booked())
                // Declared sick leave counts until HR refuses it.
                ->orWhere(fn ($query) => $query->where('type_conge', LeaveType::Sick)->whereNotIn('etat', LeaveStatus::inactive())))
            ->get()
            ->groupBy('user_id');

        $result = [];
        foreach ($people as $person) {
            for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
                $result[$person->id][] = $this->day(
                    $person,
                    $day->copy(),
                    $records[$person->id][$day->toDateString()] ?? null,
                    $leaves[$person->id] ?? collect(),
                );
            }
        }

        return $result;
    }

    /**
     * One employee's day.
     *
     * @param  Collection<int, Demande>  $leaves  their leave around that day
     * @return array<string, mixed>
     */
    public function day(User $person, Carbon $day, ?AttendanceRecord $record, Collection $leaves): array
    {
        $s = $this->schedule();
        $today = today();
        $working = $this->leaves->isWorkingDay($day);
        $tracked = $s['since'] !== null && $day->gte($s['since'])
            && (! $person->hire_date || $person->hire_date->lte($day))
            && (! $person->deactivated_at || $person->deactivated_at->copy()->startOfDay()->gt($day));

        [$leave, $part] = $this->leaveOn($leaves, $day);
        // The part of the day expected at work: null when none.
        $window = match (true) {
            ! $working => null,
            $part === 'full' => null,
            $part === 'morning_off' => [$s['lunch_end'], $s['end']],
            $part === 'afternoon_off' => [$s['start'], $s['lunch_start']],
            default => [$s['start'], $s['end']],
        };
        $expected = $window === null ? 0 : $this->minusLunch($window[0], $window[1]);

        $row = [
            'date' => $day->toDateString(),
            'working_day' => $working,
            'tracked' => $tracked,
            'leave' => $leave?->type_conge->value,
            'leave_part' => $part,
            'clock_in' => $record?->clock_in,
            'clock_out' => $record?->clock_out,
            'source' => $record?->source,
            'expected' => $tracked ? $expected : 0,
            'worked' => 0,
            'late' => 0,
            'overtime' => ['25' => 0, '50' => 0, '100' => 0],
            'missing_out' => false,
            'status' => 'not_tracked',
        ];

        if (! $tracked) {
            return $row;
        }

        if (! $record) {
            $row['status'] = match (true) {
                ! $working => 'rest',
                $part === 'full' => 'leave',
                $day->gt($today) => 'future',
                $day->isSameDay($today) => 'not_yet',
                default => 'absent',
            };

            return $row;
        }

        $in = self::minutes($record->clock_in);
        if ($window !== null && $in - $window[0] > $s['tolerance']) {
            $row['late'] = $in - $window[0];
        }

        if ($record->clock_out === null) {
            // Still at work today; a past day without departure is an anomaly to correct.
            $row['missing_out'] = ! $day->isSameDay($today);
            $row['status'] = $row['missing_out'] ? 'missing_out' : 'in_progress';

            return $row;
        }

        $out = self::minutes($record->clock_out);
        $row['status'] = 'present';

        if (! $working) {
            $row['worked'] = max(0, $out - $in);
            $night = $this->nightMinutes($in, $out);
            $row['overtime'] = ['25' => 0, '50' => $row['worked'] - $night, '100' => $night];

            return $row;
        }

        $row['worked'] = $this->minusLunch($in, $out);
        $extra = max(0, $row['worked'] - $expected);
        if ($extra > 0) {
            // The extra time is the end of the day (or its start, for an early arrival before 06:00).
            $night = $this->nightMinutes(max($in, $out - $extra), $out);
            $row['overtime'] = ['25' => $extra - $night, '50' => $night, '100' => 0];
        }

        return $row;
    }

    /**
     * Totals of a list of days.
     *
     * @param  list<array<string, mixed>>  $days
     * @return array<string, int>
     */
    public function totals(array $days): array
    {
        $days = collect($days);

        return [
            'expected' => $days->sum('expected'),
            'worked' => $days->sum('worked'),
            'present_days' => $days->whereIn('status', ['present', 'missing_out', 'in_progress'])->count(),
            'absent_days' => $days->where('status', 'absent')->count(),
            'late_days' => $days->where('late', '>', 0)->count(),
            'late_minutes' => $days->sum('late'),
            'missing_out' => $days->where('missing_out', true)->count(),
            'overtime_25' => $days->sum('overtime.25'),
            'overtime_50' => $days->sum('overtime.50'),
            'overtime_100' => $days->sum('overtime.100'),
        ];
    }

    /**
     * The leave covering a day and which part of it: 'full', 'morning_off'
     * (works the afternoon) or 'afternoon_off' (works the morning).
     *
     * @return array{0: ?Demande, 1: ?string}
     */
    private function leaveOn(Collection $leaves, Carbon $day): array
    {
        foreach ($leaves as $leave) {
            $start = Carbon::parse($leave->date_debut)->startOfDay();
            $end = Carbon::parse($leave->date_fin)->startOfDay();
            if ($day->lt($start) || $day->gt($end)) {
                continue;
            }

            // start_half: the leave starts in the afternoon; end_half: it ends at noon.
            $part = match (true) {
                $leave->start_half && $day->isSameDay($start) && ! ($leave->end_half && $day->isSameDay($end)) => 'afternoon_off',
                $leave->end_half && $day->isSameDay($end) && ! ($leave->start_half && $day->isSameDay($start)) => 'morning_off',
                default => 'full',
            };

            return [$leave, $part];
        }

        return [null, null];
    }

    /** Minutes between two times, minus the lunch break they overlap. */
    private function minusLunch(int $from, int $to): int
    {
        $s = $this->schedule();
        $lunch = max(0, min($to, $s['lunch_end']) - max($from, $s['lunch_start']));

        return max(0, $to - $from - $lunch);
    }

    /** Minutes between two times that fall at night (before 06:00 or from 21:00). */
    private function nightMinutes(int $from, int $to): int
    {
        return max(0, min($to, self::NIGHT_END) - $from) + max(0, $to - max($from, self::NIGHT_START));
    }
}
