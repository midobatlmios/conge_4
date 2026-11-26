<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\User;
use App\Services\AttendanceCalculator;
use App\Services\AttendanceReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * HR attendance: who is in today, the month per employee (worked hours,
 * lateness, absences, overtime by legal rate), the Excel export, and the
 * company schedule. Read by Admin, RH and Paie; only Admin and RH change the
 * schedule (route middleware).
 */
class AttendanceReportController extends Controller
{
    public function __construct(private readonly AttendanceCalculator $calculator) {}

    public function index(Request $request): Response
    {
        [$month, $department] = $this->filters($request);

        return Inertia::render('Admin/Attendance', [
            'month' => $month->format('Y-m'),
            'departmentId' => $department?->id,
            'departmentOptions' => Department::orderBy('name')->get(['id', 'name']),
            'schedule' => AttendanceController::scheduleProps($this->calculator),
            'rows' => $this->rows($month, $department),
            'today' => self::today($this->calculator),
        ]);
    }

    public function export(Request $request, AttendanceReport $report): BinaryFileResponse
    {
        [$month, $department] = $this->filters($request);

        return response()
            ->download($report->toXlsx($month, $department?->name, $this->rows($month, $department)), AttendanceReport::fileName($month, $department?->name))
            ->deleteFileAfterSend();
    }

    public function updateSchedule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'work_start' => ['required', 'date_format:H:i'],
            'lunch_start' => ['required', 'date_format:H:i', 'after:work_start'],
            'lunch_end' => ['required', 'date_format:H:i', 'after_or_equal:lunch_start'],
            'work_end' => ['required', 'date_format:H:i', 'after:lunch_end'],
            'late_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'attendance_started_on' => ['nullable', 'date_format:Y-m-d'],
        ], [], [
            'work_start' => 'début de journée', 'lunch_start' => 'début de pause', 'lunch_end' => 'fin de pause',
            'work_end' => 'fin de journée', 'late_tolerance_minutes' => 'tolérance', 'attendance_started_on' => 'date de début du pointage',
        ]);

        $company = CompanySetting::query()->first() ?? new CompanySetting;
        $company->forceFill($validated)->save();
        $this->calculator->refresh();

        return back()->with('success', 'Horaires enregistrés.');
    }

    /**
     * Today: who clocked in, who is late, who has not clocked yet (working
     * employees not on leave).
     *
     * @return array<string, mixed>|null Null while tracking has not started.
     */
    public static function today(AttendanceCalculator $calculator): ?array
    {
        if (! $calculator->isTracking()) {
            return null;
        }

        $people = User::active()->get();
        $days = collect($calculator->days($people, today(), today()))->map(fn (array $days) => $days[0]);
        $byId = $people->keyBy('id');
        $list = fn (string $status) => $days->filter(fn (array $day) => $day['status'] === $status)
            ->map(fn (array $day, int $id) => ['id' => $id, 'name' => $byId[$id]->name, 'clock_in' => $day['clock_in'], 'late' => $day['late']])
            ->sortBy('name')->values()->all();

        $present = $days->filter(fn (array $day) => in_array($day['status'], ['present', 'in_progress'], true));

        return [
            'present' => $present->count(),
            'late' => $days->filter(fn (array $day) => $day['late'] > 0)->count(),
            'on_leave' => $days->where('status', 'leave')->count(),
            'expected' => $days->filter(fn (array $day) => $day['expected'] > 0)->count(),
            'not_yet' => $list('not_yet'),
            'late_list' => $days->filter(fn (array $day) => $day['late'] > 0)
                ->map(fn (array $day, int $id) => ['id' => $id, 'name' => $byId[$id]->name, 'clock_in' => $day['clock_in'], 'late' => $day['late']])
                ->sortByDesc('late')->values()->all(),
        ];
    }

    /** @return list<array<string, mixed>> One row per employee of the month, by name. */
    private function rows(Carbon $month, ?Department $department): array
    {
        $to = $month->copy()->endOfMonth()->startOfDay()->min(today());
        if ($month->gt(today())) {
            return [];
        }

        $people = User::with('department:id,name')
            ->when($department, fn ($query) => $query->where('department_id', $department->id))
            ->where(fn ($query) => $query->whereNull('deactivated_at')->orWhere('deactivated_at', '>=', $month))
            ->orderBy('name')
            ->get();
        $days = $this->calculator->days($people, $month, $to);

        return $people->map(fn (User $person) => [
            'id' => $person->id,
            'name' => $person->name,
            'department' => $person->department?->name,
            ...$this->calculator->totals($days[$person->id] ?? []),
        ])->filter(fn (array $row) => $row['expected'] > 0 || $row['worked'] > 0 || $row['present_days'] > 0)->values()->all();
    }

    /** @return array{0: Carbon, 1: ?Department} */
    private function filters(Request $request): array
    {
        $value = (string) $request->query('month');
        $month = preg_match('/^\d{4}-\d{2}$/', $value)
            ? Carbon::createFromFormat('Y-m-d', "{$value}-01")->startOfDay()
            : today()->startOfMonth();

        return [$month, $request->integer('department') ? Department::find($request->integer('department')) : null];
    }
}
