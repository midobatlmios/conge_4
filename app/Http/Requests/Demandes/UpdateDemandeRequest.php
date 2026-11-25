<?php

namespace App\Http\Requests\Demandes;

use App\Enums\LeaveStatus;
use App\Models\Demande;
use Illuminate\Validation\Rule;

class UpdateDemandeRequest extends LeaveRequestForm
{
    public function authorize(): bool
    {
        return $this->actor()->can('update', $this->demande());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();

        // Only admins set the status; an employee's etat is ignored.
        if ($this->actor()->can('decide', Demande::class)) {
            // Cancelling has its own actions (see DemandesController::cancel).
            $rules['etat'] = ['required', Rule::enum(LeaveStatus::class)->only(LeaveStatus::decisions())];
        }

        return $rules;
    }

    public function demande(): Demande
    {
        return $this->route('demande');
    }

    public function ownerId(): int
    {
        return $this->demande()->user_id;
    }

    protected function excludedDemandeId(): ?int
    {
        return $this->demande()->id;
    }

    /** Status after this update: the admin's choice, or unchanged for employees. */
    public function newStatus(): LeaveStatus
    {
        return $this->has('etat') && $this->actor()->can('decide', Demande::class)
            ? LeaveStatus::from($this->validated('etat'))
            : $this->demande()->etat;
    }
}
