<?php

namespace App\Http\Controllers;

use App\Models\ApprovalDelegation;
use App\Models\User;
use App\Notifications\DelegationGiven;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Delegation of a manager's approvals for a period: set by the manager
 * ("Mon équipe") or by HR (employee page), ended early by either.
 */
class DelegationController extends Controller
{
    /** The signed-in manager delegates their own approvals. */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);

        return $this->create($request, $request->user());
    }

    /** HR sets a delegation for a manager (sudden absence…). Behind role:admin,rh. */
    public function storeFor(Request $request, User $user): RedirectResponse
    {
        if (! $user->isManager()) {
            throw ValidationException::withMessages(['delegate_id' => "{$user->name} n'a pas d'équipe : rien à déléguer."]);
        }

        return $this->create($request, $user);
    }

    /** Ends a delegation now: deleted if not started, otherwise stopped yesterday. */
    public function destroy(Request $request, ApprovalDelegation $delegation): RedirectResponse
    {
        abort_unless($delegation->manager_id === $request->user()->id || $request->user()->isHr(), 403);

        if ($delegation->starts_on->gte(today())) {
            $delegation->delete();
        } else {
            $delegation->update(['ends_on' => today()->subDay()]);
        }

        return back()->with('success', 'Délégation terminée.');
    }

    private function create(Request $request, User $manager): RedirectResponse
    {
        $validated = $request->validate([
            'delegate_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->whereNull('deactivated_at'),
                Rule::notIn([$manager->id]),
            ],
            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ], [
            'delegate_id.not_in' => 'Choisissez une autre personne que le responsable.',
            'delegate_id.exists' => 'La personne choisie doit être un employé actif.',
            'starts_on.after_or_equal' => 'La délégation ne peut pas commencer dans le passé.',
        ], ['delegate_id' => 'remplaçant', 'starts_on' => 'date de début', 'ends_on' => 'date de fin']);

        $overlap = $manager->delegationsGiven()
            ->whereDate('starts_on', '<=', $validated['ends_on'])
            ->whereDate('ends_on', '>=', $validated['starts_on'])
            ->with('delegate:id,name')
            ->first();
        if ($overlap) {
            throw ValidationException::withMessages(['starts_on' => "Une délégation à {$overlap->delegate->name} couvre déjà une partie de cette période ({$overlap->starts_on->format('d/m/Y')} – {$overlap->ends_on->format('d/m/Y')})."]);
        }

        $delegation = $manager->delegationsGiven()->create([...$validated, 'created_by' => $request->user()->id]);

        try {
            $delegation->delegate->notify(new DelegationGiven($delegation));
        } catch (TransportExceptionInterface $e) {
            report($e);
        }

        return back()->with('success', "Délégation à {$delegation->delegate->name} enregistrée.");
    }
}
