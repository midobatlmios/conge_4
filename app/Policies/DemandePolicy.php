<?php

namespace App\Policies;

use App\Enums\LeaveStatus;
use App\Models\Demande;
use App\Models\User;

/**
 * Who may do what with leave requests. Admins manage every request;
 * employees manage only their own, and only while still pending.
 */
class DemandePolicy
{
    /** See every employee's requests, not just one's own. */
    public function viewAll(User $user): bool
    {
        return $user->isAdmin();
    }

    /** See one request and its history: admins, and the employee it belongs to. */
    public function view(User $user, Demande $demande): bool
    {
        return $user->isAdmin() || $demande->user_id === $user->id;
    }

    /** File a request on behalf of another employee. */
    public function createForOthers(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Demande $demande): bool
    {
        return $user->isAdmin() || $this->ownsPendingRequest($user, $demande);
    }

    public function delete(User $user, Demande $demande): bool
    {
        return $user->isAdmin() || $this->ownsPendingRequest($user, $demande);
    }

    /** Accept or refuse a request. */
    public function decide(User $user): bool
    {
        return $user->isAdmin();
    }

    private function ownsPendingRequest(User $user, Demande $demande): bool
    {
        return $demande->user_id === $user->id && $demande->etat === LeaveStatus::Pending;
    }
}
