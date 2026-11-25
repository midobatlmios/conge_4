<?php

namespace App\Http\Controllers;

use App\Enums\LeaveStatus;
use App\Models\Demande;
use App\Models\Department;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Team calendar: who is off when, one month at a time. Admins see everyone
 * (optionally one department); employees see their own department, with
 * names and dates only. Pending requests are shown too, refused and cancelled
 * ones never; a leave whose cancellation is pending is still booked.
 */
class CalendarController extends Controller
{
    public function index(Request $request): Response
    {
        $viewer = $request->user();
        $seesAll = $viewer->canViewHr();

        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month'))
            ? Carbon::createFromFormat('Y-m-d', $request->query('month').'-01')->startOfDay()
            : now()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        $departmentId = $seesAll ? ($request->integer('department') ?: null) : $viewer->department_id;

        $people = User::active()
            ->with('department:id,name')
            ->when($seesAll && $departmentId, fn (Builder $q) => $q->where('department_id', $departmentId))
            ->when(! $seesAll, fn (Builder $q) => $viewer->department_id
                ? $q->where('department_id', $viewer->department_id)
                : $q->whereKey($viewer->id))
            ->orderBy('name')
            ->get(['id', 'name', 'job_title', 'department_id']);

        $leaves = Demande::query()
            ->whereIn('user_id', $people->modelKeys())
            ->whereNotIn('etat', LeaveStatus::inactive())
            ->whereDate('date_debut', '<=', $monthEnd)
            ->whereDate('date_fin', '>=', $month)
            ->orderBy('date_debut')
            ->get()
            ->groupBy('user_id');

        return Inertia::render('Calendar', [
            'month' => $month->format('Y-m'),
            'departmentId' => $departmentId,
            'departments' => $seesAll ? Department::orderBy('name')->get(['id', 'name']) : [],
            'departmentName' => $seesAll ? null : $viewer->department?->name,
            'people' => $people->map(fn (User $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'job_title' => $person->job_title,
                'department' => $person->department?->name,
                'leaves' => ($leaves[$person->id] ?? collect())->map(fn (Demande $leave) => [
                    'id' => $leave->id,
                    'start' => Carbon::parse($leave->date_debut)->toDateString(),
                    'end' => Carbon::parse($leave->date_fin)->toDateString(),
                    'startHalf' => $leave->start_half, // starts in the afternoon
                    'endHalf' => $leave->end_half, // ends at noon
                    'pending' => in_array($leave->etat, LeaveStatus::awaiting(), true),
                    // The kind of leave is private: only admins and the employee see it.
                    'type' => $seesAll || $person->is($viewer) ? $leave->type_conge->value : null,
                ])->values(),
            ])->values(),
        ]);
    }
}
