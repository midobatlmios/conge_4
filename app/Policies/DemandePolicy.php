<?php

namespace App\Policies;

use App\Enums\LeaveStatus;
use App\Models\Demande;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Who may do what with leave requests. HR (Admin, RH) manages every request,
 * Paie only reads them;
 * employees manage only their own, and only while still pending, except
 * asking to cancel an accepted leave before it starts.
 */
class DemandePolicy
{
    /** See every employee's requests, not just one's own. */
    public function viewAll(User $user): bool
    {
        return $user->canViewHr();
    }

    /** See one request and its history: HR (incl. Paie), and the employee it belongs to. */
    public function view(User $user, Demande $demande): bool
    {
        return $user->canViewHr() || $demande->user_id === $user->id;
    }

    /** File a request on behalf of another employee. */
    public function createForOthers(User $user): bool
    {
        return $user->isHr();
    }

    /** Requests being cancelled or cancelled are only changed through the cancellation actions. */
    public function update(User $user, Demande $demande): bool
    {
        return in_array($demande->etat, LeaveStatus::decisions(), true)
            && ($user->isHr() || $this->ownsPendingRequest($user, $demande));
    }

    /**
     * Open a supporting document: the employee and HR only (it can hold personal
     * data). Managers only see that one exists.
     */
    public function viewDocuments(User $user, Demande $demande): bool
    {
        return $user->canViewHr() || $demande->user_id === $user->id;
    }

    /** Add or remove documents: HR any time, the employee while the request still waits for a decision. */
    public function manageDocuments(User $user, Demande $demande): bool
    {
        return $user->isHr()
            || ($demande->user_id === $user->id && in_array($demande->etat, LeaveStatus::awaiting(), true));
    }

    /** The employee's manager approves or refuses a request still waiting for them. */
    public function managerDecide(User $user, Demande $demande): bool
    {
        return $demande->etat === LeaveStatus::Pending
            // Sick leave is declared, not approved by the manager.
            && ! $demande->type_conge->isDeclared()
            && $demande->user_id !== $user->id
            && $demande->user?->manager_id !== null
            // The manager, or the person they delegated their approvals to today.
            && ($demande->user->manager_id === $user->id || in_array($demande->user->manager_id, $user->delegatingManagerIds(), true));
    }

    /** The employee asks to cancel their accepted leave, as long as it has not started. */
    public function requestCancellation(User $user, Demande $demande): bool
    {
        return $demande->user_id === $user->id
            && $demande->etat === LeaveStatus::Accepted
            && Carbon::parse($demande->date_debut)->isAfter(today());
    }

    /** Cancel an accepted leave: approving the employee's request, or on the admin's own initiative. */
    public function cancel(User $user, Demande $demande): bool
    {
        return $user->isHr() && in_array($demande->etat, LeaveStatus::booked(), true);
    }

    /** Turn down the employee's cancellation request: the leave stays accepted. */
    public function keep(User $user, Demande $demande): bool
    {
        return $user->isHr() && $demande->etat === LeaveStatus::CancellationRequested;
    }

    public function delete(User $user, Demande $demande): bool
    {
        return $user->isHr() || $this->ownsPendingRequest($user, $demande);
    }

    /** Accept or refuse a request. */
    public function decide(User $user): bool
    {
        return $user->isHr();
    }

    private function ownsPendingRequest(User $user, Demande $demande): bool
    {
        return $demande->user_id === $user->id && $demande->etat === LeaveStatus::Pending;
    }
}
