<?php

namespace App\Http\Controllers;

use App\Enums\LeaveStatus;
use App\Http\Requests\Demandes\StoreDemandeRequest;
use App\Http\Requests\Demandes\UpdateDemandeRequest;
use App\Models\Demande;
use App\Models\DemandeEvent;
use App\Models\User;
use App\Notifications\DemandeStatusChanged;
use App\Services\LeaveCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class DemandesController extends Controller
{
    public function __construct(private readonly LeaveCalculator $calculator) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $canViewAll = $user->can('viewAll', Demande::class);

        $demandes = Demande::with('user')
            ->latest()
            ->when(! $canViewAll, fn ($query) => $query->where('user_id', $user->id))
            ->get();

        $year = now()->year;
        $remaining = [];

        foreach ($demandes as $demande) {
            $remaining[$demande->user_id] ??= $this->calculator->remainingDays($demande->user_id, $year);
            $demande->user->remaining_days = $remaining[$demande->user_id];
        }

        return Inertia::render('Demandes', [
            'demandes' => $demandes,
            // Employees an admin can file a request for.
            'users' => $user->can('createForOthers', Demande::class)
                ? User::active()->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }

    public function store(StoreDemandeRequest $request): JsonResponse|RedirectResponse
    {
        Demande::create([
            ...$request->safe()->only(['date_debut', 'date_fin', 'type_conge', 'comment']),
            'date_demande' => now()->toDateString(),
            'etat' => LeaveStatus::Pending,
            'user_id' => $request->ownerId(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Demande ajoutée avec succès'], 201);
        }

        return redirect()->back()->with('success', 'Demande ajoutée avec succès');
    }

    public function update(UpdateDemandeRequest $request, Demande $demande): JsonResponse
    {
        $status = $request->newStatus();
        $type = $request->leaveType();

        if ($status === LeaveStatus::Accepted && $this->calculator->countsTowardsAllowance($type)) {
            $year = $this->calculator->yearOverAllowance(
                $demande->user_id,
                $type,
                $request->validated('date_debut'),
                $request->validated('date_fin'),
                $demande->id
            );

            if ($year !== null) {
                return response()->json([
                    'message' => "Impossible d'accepter cette demande. L'employé dépasserait la limite de "
                        ."{$this->calculator->maxDaysPerYear()} jours de congé annuel pour {$year}.",
                ], 422);
            }
        }

        $oldStatus = $demande->etat;
        $decidedByAdmin = $request->user()->can('decide', Demande::class) && $status->isDecided();

        // date_demande is the submission date: set once on creation, never edited.
        $demande->update([
            ...$request->safe()->only(['date_debut', 'date_fin', 'type_conge']),
            'comment' => $request->validated('comment'),
            'etat' => $status,
            'valide_par' => $decidedByAdmin ? $request->user()->id : null,
        ]);

        if ($decidedByAdmin && $oldStatus !== $status) {
            try {
                $demande->user->notify(new DemandeStatusChanged($demande));
            } catch (TransportExceptionInterface $e) {
                // The decision is saved and the in-app notification stored; a mail
                // outage must not turn it into an error. Logged for the admin.
                report($e);
            }
        }

        return response()->json([
            'message' => 'Demande mise à jour avec succès',
            'demande' => $demande->fresh(),
        ]);
    }

    /** Audit trail of one request: who did what, and when. */
    public function history(Demande $demande): JsonResponse
    {
        Gate::authorize('view', $demande);

        $events = $demande->events()->with('user:id,name')->get();

        // Names for "employee changed" entries (admin moved a request to someone else).
        $employeeIds = $events->flatMap(fn (DemandeEvent $event) => [
            $event->changes['user_id']['old'] ?? null,
            $event->changes['user_id']['new'] ?? null,
        ])->filter()->unique();
        $names = User::whereIn('id', $employeeIds)->pluck('name', 'id');

        return response()->json($events->map(fn (DemandeEvent $event) => [
            'id' => $event->id,
            'action' => $event->action,
            'by' => $event->user?->name,
            'at' => $event->created_at->toIso8601String(),
            'changes' => $event->changes,
            'employee_names' => $names->only(array_filter([
                $event->changes['user_id']['old'] ?? null,
                $event->changes['user_id']['new'] ?? null,
            ])),
        ]));
    }

    public function destroy(Demande $demande): RedirectResponse
    {
        Gate::authorize('delete', $demande);

        $demande->delete();

        return redirect()->back()->with('success', 'Demande supprimée avec succès');
    }
}
