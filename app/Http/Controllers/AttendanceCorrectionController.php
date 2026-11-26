<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Notifications\AppNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Corrections of clocking waiting for a decision: the manager (or today's
 * delegate) sees their team's, HR sees everyone's. Approving one replaces the
 * day's record.
 */
class AttendanceCorrectionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->isHr() || $user->approvesRequests(), 403);

        return Inertia::render('AttendanceCorrections', [
            'pending' => self::pendingFor($user)->map(fn (AttendanceCorrection $c) => $c->present($user))->values(),
            'recent' => AttendanceCorrection::with(['user:id,name', 'decider:id,name'])
                ->whereIn('user_id', $this->visibleIds($user))
                ->where('status', '!=', 'pending')
                ->latest('decided_at')
                ->limit(15)
                ->get()
                ->map->present(),
        ]);
    }

    public function approve(Request $request, AttendanceCorrection $correction): RedirectResponse
    {
        abort_unless($correction->canBeDecidedBy($request->user()), 403);

        DB::transaction(function () use ($request, $correction) {
            AttendanceRecord::updateOrCreate(
                ['user_id' => $correction->user_id, 'date' => $correction->date->toDateString()],
                ['clock_in' => $correction->clock_in, 'clock_out' => $correction->clock_out, 'source' => 'correction'],
            );
            $correction->update(['status' => 'approved', 'decided_by' => $request->user()->id, 'decided_at' => now()]);
            $correction->user->notify(new AppNotice('Votre correction de pointage du :date a été acceptée.', ['date' => $correction->date->format('d/m/Y')], '/pointage'));
        });

        return back()->with('success', 'Correction acceptée : le pointage du jour est mis à jour.');
    }

    public function refuse(Request $request, AttendanceCorrection $correction): RedirectResponse
    {
        abort_unless($correction->canBeDecidedBy($request->user()), 403);
        $validated = $request->validate(['refusal_reason' => ['nullable', 'string', 'max:500']]);

        $correction->update(['status' => 'refused', 'decided_by' => $request->user()->id, 'decided_at' => now(), 'refusal_reason' => $validated['refusal_reason'] ?? null]);
        $correction->user->notify(new AppNotice('Votre correction de pointage du :date a été refusée.', ['date' => $correction->date->format('d/m/Y')], '/pointage'));

        return back()->with('success', 'Correction refusée.');
    }

    /** Pending corrections this user may decide, oldest day first. */
    public static function pendingFor(User $user)
    {
        return AttendanceCorrection::with('user:id,name,manager_id')
            ->pending()
            ->orderBy('date')
            ->get()
            ->filter(fn (AttendanceCorrection $correction) => $correction->canBeDecidedBy($user));
    }

    /** @return list<int> Employees whose decided corrections this user sees. */
    private function visibleIds(User $user): array
    {
        if ($user->isHr()) {
            return User::pluck('id')->all();
        }

        return User::whereIn('manager_id', [$user->id, ...$user->delegatingManagerIds()])->pluck('id')->all();
    }
}
