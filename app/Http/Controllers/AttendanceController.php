<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\User;
use App\Notifications\AppNotice;
use App\Services\AttendanceCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The employee's own attendance: clock in and out with a button (time taken
 * from the server), see their month, and ask for a correction of a past day.
 */
class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceCalculator $calculator) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $month = $this->month($request);
        $to = $month->copy()->endOfMonth()->startOfDay()->min(today());
        $days = $month->gt(today()) ? [] : ($this->calculator->days(collect([$user]), $month, $to)[$user->id] ?? []);

        return Inertia::render('Attendance', [
            'month' => $month->format('Y-m'),
            'today' => self::todayState($user, $this->calculator),
            'days' => array_reverse($days),
            'totals' => $this->calculator->totals($days),
            'schedule' => self::scheduleProps($this->calculator),
            'corrections' => $user->attendanceCorrections()->with('decider:id,name')->latest()->limit(20)->get()->map->present(),
        ]);
    }

    public function clockIn(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->calculator->isTracking(), 403, "Le pointage n'est pas encore activé.");

        if (AttendanceRecord::where('user_id', $user->id)->whereDate('date', today())->exists()) {
            return back()->with('error', "Votre arrivée est déjà pointée aujourd'hui.");
        }

        AttendanceRecord::create(['user_id' => $user->id, 'date' => today()->toDateString(), 'clock_in' => now()->format('H:i')]);

        return back()->with('success', 'Arrivée pointée à '.now()->format('H:i').'.');
    }

    public function clockOut(Request $request): RedirectResponse
    {
        $record = AttendanceRecord::where('user_id', $request->user()->id)->whereDate('date', today())->first();

        if (! $record || $record->clock_out !== null) {
            return back()->with('error', $record ? "Votre départ est déjà pointé aujourd'hui." : "Pointez d'abord votre arrivée.");
        }

        $record->update(['clock_out' => max(now()->format('H:i'), $record->clock_in)]);

        return back()->with('success', 'Départ pointé à '.$record->clock_out.'. Bonne soirée !');
    }

    /** Ask to fix a past day (or today's arrival): forgotten clocking, wrong time. */
    public function requestCorrection(Request $request): RedirectResponse
    {
        $user = $request->user();
        $since = $this->calculator->schedule()['since'];
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', ...($since ? ['after_or_equal:'.$since->toDateString()] : [])],
            'clock_in' => ['required', 'date_format:H:i'],
            'clock_out' => ['nullable', 'date_format:H:i', 'after:clock_in'],
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'date.after_or_equal' => 'Le pointage a commencé le '.$since?->format('d/m/Y').'.',
            'clock_out.after' => "Le départ doit suivre l'arrivée.",
        ], ['date' => 'jour', 'clock_in' => 'arrivée', 'clock_out' => 'départ', 'reason' => 'motif']);

        if ($user->attendanceCorrections()->pending()->whereDate('date', $validated['date'])->exists()) {
            return back()->withErrors(['date' => 'Une correction de ce jour attend déjà une décision.']);
        }

        $user->attendanceCorrections()->create($validated);

        // Whoever decides: the manager (or today's delegate), else HR.
        $manager = $user->approvingManager();
        $deciders = $manager ? collect([$manager->approvalRecipient($user)]) : AppNotice::hr($user);
        Notification::send($deciders, new AppNotice(':name demande une correction de pointage pour le :date.', [
            'name' => $user->name, 'date' => Carbon::parse($validated['date'])->format('d/m/Y'),
        ], '/pointage/corrections'));

        return back()->with('success', 'Correction envoyée : votre responsable ou le service RH va la valider.');
    }

    /**
     * What the clock button shows today.
     *
     * @return array<string, mixed>
     */
    public static function todayState(User $user, AttendanceCalculator $calculator): array
    {
        $record = AttendanceRecord::where('user_id', $user->id)->whereDate('date', today())->first();

        return [
            'tracking' => $calculator->isTracking(),
            'working_day' => app(\App\Services\LeaveCalculator::class)->forUser($user)->isWorkingDay(today()),
            'clock_in' => $record?->clock_in,
            'clock_out' => $record?->clock_out,
        ];
    }

    /** @return array<string, mixed> */
    public static function scheduleProps(AttendanceCalculator $calculator): array
    {
        $s = $calculator->schedule();
        $time = fn (int $minutes) => sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);

        return [
            'start' => $time($s['start']),
            'lunch_start' => $time($s['lunch_start']),
            'lunch_end' => $time($s['lunch_end']),
            'end' => $time($s['end']),
            'tolerance' => $s['tolerance'],
            'since' => $s['since']?->toDateString(),
            'expected' => $s['expected'],
        ];
    }

    /** The requested month (YYYY-MM), this month by default. */
    private function month(Request $request): Carbon
    {
        $value = (string) $request->query('month');

        return preg_match('/^\d{4}-\d{2}$/', $value)
            ? Carbon::createFromFormat('Y-m-d', "{$value}-01")->startOfDay()
            : today()->startOfMonth();
    }
}
