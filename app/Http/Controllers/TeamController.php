<?php

namespace App\Http\Controllers;

use App\Enums\LeaveStatus;
use App\Models\Demande;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A manager's view of their team's leave requests: the ones waiting for their
 * approval first, then the recent ones. Anyone can open it; only people with
 * employees reporting to them see requests.
 */
class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $manager = $request->user();
        $reportIds = $manager->reports()->pluck('id');

        // manager_id is needed by the managerDecide policy check below.
        $requests = Demande::with('user:id,name,job_title,manager_id')
            ->withCount('documents')
            ->whereIn('user_id', $reportIds)
            ->orderByRaw('etat = ? desc', [LeaveStatus::Pending->value])
            ->orderByDesc('date_debut')
            ->limit(100)
            ->get();

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
            ]),
            'teamSize' => $manager->reports()->whereNull('deactivated_at')->count(),
        ]);
    }
}
