<?php

namespace App\Http\Controllers;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\Role;
use App\Http\Requests\Demandes\StoreDemandeRequest;
use App\Http\Requests\Demandes\UpdateDemandeRequest;
use App\Models\Demande;
use App\Models\DemandeEvent;
use App\Models\User;
use App\Notifications\DemandeStatusChanged;
use App\Notifications\LeaveRequestAwaitingDecision;
use App\Notifications\SickLeaveDeclared;
use App\Services\LeaveCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
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

        $demandes = Demande::with(['user', 'documents'])
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
            'cnssMinDays' => config('conge.cnss_min_days'),
            // Employees an admin can file a request for.
            'users' => $user->can('createForOthers', Demande::class)
                ? User::active()->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }

    public function store(StoreDemandeRequest $request): RedirectResponse
    {
        $demande = Demande::create([
            ...$request->safe()->only(['date_debut', 'date_fin', 'type_conge', 'comment']),
            ...$request->halfDays(),
            'date_demande' => now()->toDateString(),
            'etat' => LeaveStatus::Pending,
            'user_id' => $request->ownerId(),
        ]);

        // A certificate can be attached right away (or later, before HR accepts).
        if ($request->hasFile('document')) {
            DemandeDocumentController::attach($demande, $request->file('document'), $request->user()->id);
        }

        // Two-step approval: the employee's manager first, HR directly when there is none.
        $manager = $demande->user->approvingManager();

        if ($demande->type_conge->isDeclared()) {
            // Sick leave: straight to HR; the manager is only told someone is off.
            $this->notifyHr($demande, $request->user());
            if ($manager && ! $manager->is($request->user())) {
                $this->send($manager, new SickLeaveDeclared($demande));
            }
        } elseif ($manager && ! $manager->is($request->user())) {
            // During a delegation, the delegate is told (unless it is their own request).
            $recipient = $manager->approvalRecipient($demande->user);
            if (! $recipient->is($request->user())) {
                $this->send($recipient, new LeaveRequestAwaitingDecision($demande, forManager: true));
            }
        } elseif (! $manager) {
            $this->notifyHr($demande, $request->user());
        }

        return back()->with('success', 'Demande ajoutée avec succès');
    }

    /** HR records (or clears) the day the CNSS dossier of a sick leave was filed. */
    public function cnss(Request $request, Demande $demande): RedirectResponse
    {
        Gate::authorize('decide', Demande::class);
        abort_unless($demande->type_conge === LeaveType::Sick, 422, 'Seul un congé maladie a un dossier CNSS.');

        $validated = $request->validate(
            ['cnss_filed_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.Carbon::parse($demande->date_debut)->toDateString(), 'before_or_equal:today']],
            ['cnss_filed_on.after_or_equal' => "Le dossier ne peut pas être déposé avant le début de l'arrêt."],
            ['cnss_filed_on' => 'date de dépôt'],
        );

        $demande->update(['cnss_filed_on' => $validated['cnss_filed_on'] ?? null]);

        return back()->with('success', $demande->cnss_filed_on ? 'Dépôt du dossier CNSS enregistré.' : 'Dépôt du dossier CNSS annulé.');
    }

    /** The manager approves: HR makes the final decision. */
    public function managerApprove(Request $request, Demande $demande): RedirectResponse
    {
        Gate::authorize('managerDecide', $demande);

        $demande->update(['etat' => LeaveStatus::ManagerApproved]);
        $this->notifyHr($demande, $request->user());

        return back()->with('success', 'Demande validée : elle est transmise aux RH.');
    }

    /** The manager refuses: the refusal is final and the employee is told. */
    public function managerRefuse(Request $request, Demande $demande): RedirectResponse
    {
        Gate::authorize('managerDecide', $demande);

        $reason = $request->validate(['manager_comment' => ['nullable', 'string', 'max:1000']])['manager_comment'] ?? null;

        $demande->update([
            'etat' => LeaveStatus::Refused,
            'manager_comment' => $reason,
            'valide_par' => $request->user()->id,
        ]);
        $this->send($demande->user, new DemandeStatusChanged($demande, byManager: true));

        return back()->with('success', 'Demande refusée.');
    }

    public function update(UpdateDemandeRequest $request, Demande $demande): RedirectResponse
    {
        $status = $request->newStatus();
        $type = $request->leaveType();

        if ($status === LeaveStatus::Accepted
            && in_array($type->value, config('conge.document_types'), true)
            && ! $demande->documents()->exists()) {
            return back()->withErrors([
                'etat' => "Impossible d'accepter ce congé : un justificatif ({$type->label()}) doit d'abord être joint à la demande.",
            ]);
        }

        if ($status === LeaveStatus::Accepted
            && $this->calculator->startsBeforeEligibility($demande->user, $type, $request->validated('date_debut'))) {
            return back()->withErrors([
                'etat' => "Impossible d'accepter ce congé annuel : l'employé n'y a droit qu'après "
                    .config('conge.eligibility_months').' mois de service, soit à partir du '
                    .$this->calculator->eligibleFrom($demande->user)->format('d/m/Y').'.',
            ]);
        }

        if ($status === LeaveStatus::Accepted && $this->calculator->countsTowardsAllowance($type)) {
            $year = $this->calculator->yearOverAllowance(
                $demande->user_id,
                $type,
                $request->validated('date_debut'),
                $request->validated('date_fin'),
                $demande->id,
                ...array_values($request->halfDays()),
            );

            if ($year !== null) {
                return back()->withErrors([
                    'etat' => "Impossible d'accepter cette demande : l'employé n'aura pas encore acquis assez de jours "
                        ."de congé à cette date en {$year} (1,5 jour par mois travaillé, plus l'ancienneté).",
                ]);
            }
        }

        $oldStatus = $demande->etat;
        $decidedByAdmin = $request->user()->can('decide', Demande::class) && $status->isDecided();

        // date_demande is the submission date: set once on creation, never edited.
        $demande->update([
            ...$request->safe()->only(['date_debut', 'date_fin', 'type_conge']),
            ...$request->halfDays(),
            'comment' => $request->validated('comment'),
            'etat' => $status,
            'valide_par' => $decidedByAdmin ? $request->user()->id : null,
        ]);

        if ($decidedByAdmin && $oldStatus !== $status) {
            $this->notifyEmployee($demande);
        }

        return back()->with('success', 'Demande mise à jour avec succès');
    }

    /** The employee asks to cancel an accepted leave; its days stay booked until an admin decides. */
    public function requestCancellation(Demande $demande): RedirectResponse
    {
        Gate::authorize('requestCancellation', $demande);

        $demande->update(['etat' => LeaveStatus::CancellationRequested]);

        return back()->with('success', "Demande d'annulation envoyée.");
    }

    /** An admin cancels an accepted leave (approving the employee's request, or not): the days are returned. */
    public function cancel(Request $request, Demande $demande): RedirectResponse
    {
        Gate::authorize('cancel', $demande);

        $demande->update(['etat' => LeaveStatus::Cancelled, 'valide_par' => $request->user()->id]);
        $this->notifyEmployee($demande);

        return back()->with('success', 'Congé annulé.');
    }

    /** An admin turns down the cancellation request: the leave stays accepted. */
    public function keep(Request $request, Demande $demande): RedirectResponse
    {
        Gate::authorize('keep', $demande);

        $demande->update(['etat' => LeaveStatus::Accepted, 'valide_par' => $request->user()->id]);
        $this->notifyEmployee($demande, cancellationKept: true);

        return back()->with('success', 'Congé maintenu.');
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

    private function notifyEmployee(Demande $demande, bool $cancellationKept = false): void
    {
        $this->send($demande->user, new DemandeStatusChanged($demande, $cancellationKept));
    }

    /** Every active Admin and RH account but the one acting is told a request waits for HR's decision. */
    private function notifyHr(Demande $demande, User $actor): void
    {
        User::active()
            ->whereIn('role', [Role::Admin, Role::Hr])
            ->whereKeyNot($actor->id)
            ->get()
            ->each(fn (User $admin) => $this->send($admin, new LeaveRequestAwaitingDecision($demande, forManager: false)));
    }

    private function send(User $to, Notification $notification): void
    {
        try {
            $to->notify($notification);
        } catch (TransportExceptionInterface $e) {
            // The change is saved and the in-app notification stored; a mail
            // outage must not turn it into an error. Logged for the admin.
            report($e);
        }
    }

    public function destroy(Demande $demande): RedirectResponse
    {
        Gate::authorize('delete', $demande);

        $demande->delete();

        return back()->with('success', 'Demande supprimée avec succès');
    }
}
