<?php

namespace App\Http\Controllers;

use App\Enums\LeaveStatus;
use App\Models\Demande;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A manager's view of their team's leave requests: the ones waiting for their
 * approval first, then the recent ones; plus the teams delegated to them today
 * and the delegations they gave. Anyone can open it; only people with
 * employees reporting to them (or a delegation) see requests.
 */
class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $manager = $request->user();
        $delegators = $manager->delegatingManagerIds();
        $reportIds = User::where('manager_id', $manager->id)->orWhereIn('manager_id', $delegators)->pluck('id');

        // manager_id is needed by the managerDecide policy check below.
        $requests = Demande::with('user:id,name,job_title,manager_id', 'user.manager:id,name')
            ->withCount('documents')
            ->whereIn('user_id', $reportIds)
            ->orderByRaw('etat = ? desc', [LeaveStatus::Pending->value])
            ->orderByDesc('date_debut')
            ->limit(150)
            ->get();

        $nextLeave = $manager->demandes()
            ->whereIn('etat', LeaveStatus::booked())
            ->whereDate('date_fin', '>=', today())
            ->orderBy('date_debut')
            ->first();

        return Inertia::render('Team', [
            'requests' => $requests->map(fn (Demande $demande) => [
                'id' => $demande->id,
                'name' => $demande->user?->name,
                'job_title' => $demande->user?->job_title,
                'type' => $demande->type_conge->value,
                'start' => Carbon::parse($demande->date_debut)->toDateString(),
                'end' => Carbon::parse($demande->date_fin)->toDateString(),
                'start_half' => $demande->start_half,
                'end_half' => $demande->end_half,
                'days' => $demande->nbr_jours,
                'status' => $demande->etat->value,
                'comment' => $demande->comment,
                'manager_comment' => $demande->manager_comment,
                'can_decide' => $manager->can('managerDecide', $demande),
                // Managers only see that a certificate exists, not the file itself.
                'documents' => $demande->documents_count,
                'needs_document' => $demande->needsDocument(),
                // Set when the request comes from a team delegated to this user.
                'delegated_by' => $demande->user?->manager_id !== $manager->id ? $demande->user?->manager?->name : null,
            ]),
            'teamSize' => $manager->reports()->whereNull('deactivated_at')->count(),
            // Delegations this manager gave, not over yet, and who they can choose.
            'delegations' => $manager->delegationsGiven()->notOver()->with('delegate:id,name')->get()->map->present(),
            'delegateOptions' => User::active()->whereKeyNot($manager->id)->orderBy('name')->get(['id', 'name']),
            'nextLeave' => $nextLeave ? [
                'start' => Carbon::parse($nextLeave->date_debut)->toDateString(),
                'end' => Carbon::parse($nextLeave->date_fin)->toDateString(),
            ] : null,
            'delegatedTeams' => User::whereKey($delegators)->orderBy('name')->pluck('name'),
        ]);
    }
}
