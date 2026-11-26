<?php

namespace App\Services;

use App\Enums\AttestationStatus;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Http\Controllers\EmploymentContractController;
use App\Models\Attestation;
use App\Models\Demande;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What HR has to act on: shared by the overview page and the daily reminder.
 */
class HrTodo
{
    public function __construct(private readonly LeaveCalculator $calculator) {}

    /**
     * Sick leave of 4+ calendar days whose CNSS dossier is not filed yet, the closest deadline first.
     *
     * @param  Collection<int, User>  $employees
     * @return list<array<string, mixed>>
     */
    public function cnss(Collection $employees): array
    {
        $names = $employees->pluck('name', 'id');

        return Demande::query()
            ->where('type_conge', LeaveType::Sick)
            ->whereNotIn('etat', LeaveStatus::inactive())
            ->whereNull('cnss_filed_on')
            ->whereIn('user_id', $employees->modelKeys())
            ->orderBy('date_debut')
            ->get()
            ->filter(fn (Demande $leave) => $leave->needsCnssFiling())
            ->map(fn (Demande $leave) => [
                'id' => $leave->id,
                'user_id' => $leave->user_id,
                'name' => $names[$leave->user_id],
                'start' => Carbon::parse($leave->date_debut)->toDateString(),
                'end' => Carbon::parse($leave->date_fin)->toDateString(),
                'calendar_days' => $leave->calendar_days,
                'deadline' => $leave->cnssDeadline()->toDateString(),
                'overdue' => $leave->cnssDeadline()->lt(today()),
            ])
            ->values()
            ->all();
    }

    /**
     * Requests waiting for HR for at least $days working days: validated by the
     * manager, without a manager, or sick leave (declared straight to HR).
     *
     * @return Collection<int, Demande>
     */
    public function waitingForHr(int $days): Collection
    {
        return Demande::with('user:id,name,manager_id,deactivated_at', 'user.manager:id,deactivated_at')
            ->whereIn('etat', LeaveStatus::awaiting())
            ->orderBy('date_debut')
            ->get()
            ->filter(function (Demande $demande) use ($days) {
                $atHr = $demande->etat === LeaveStatus::ManagerApproved
                    || $demande->type_conge->isDeclared()
                    || ! $demande->user?->approvingManager();

                return $atHr && $this->waitingDays($demande) >= $days;
            })
            ->values();
    }

    /**
     * Requests waiting for each manager for at least $days working days, by manager id.
     *
     * @return Collection<int, Collection<int, Demande>>
     */
    public function waitingForManagers(int $days): Collection
    {
        return Demande::with('user:id,name,manager_id', 'user.manager:id,name,email,deactivated_at')
            ->where('etat', LeaveStatus::Pending)
            ->orderBy('date_debut')
            ->get()
            ->filter(fn (Demande $demande) => ! $demande->type_conge->isDeclared()
                && $demande->user?->approvingManager()
                && $this->waitingDays($demande) >= $days)
            ->groupBy(fn (Demande $demande) => $demande->user->manager_id);
    }

    /** @return Collection<int, Demande> Accepted leave whose employee asked to cancel. */
    public function cancellations(): Collection
    {
        return Demande::with('user:id,name')->where('etat', LeaveStatus::CancellationRequested)->orderBy('date_debut')->get();
    }

    /** @return Collection<int, Attestation> */
    public function attestations(): Collection
    {
        return Attestation::with('user:id,name')->where('status', AttestationStatus::Requested)->oldest()->get();
    }

    /**
     * @param  Collection<int, User>  $employees  active, with their contracts loaded
     * @return list<array<string, mixed>>
     */
    public function contracts(Collection $employees): array
    {
        return EmploymentContractController::alerts($employees);
    }

    /** @return Collection<int, EmployeeDocument> */
    public function documents(Collection $employees): Collection
    {
        return EmployeeDocument::needsRenewal()->whereIn('user_id', $employees->modelKeys())->with('user:id,name')->orderBy('expires_on')->get();
    }

    /**
     * Working days since the request reached its current step: submission, or the
     * manager's approval.
     */
    public function waitingDays(Demande $demande): int
    {
        $since = $demande->etat === LeaveStatus::ManagerApproved ? $demande->updated_at : $demande->created_at;

        if (! $since || $since->isToday()) {
            return 0;
        }

        return $this->calculator->workingDays($since->copy()->addDay()->startOfDay(), today());
    }
}
