<?php

namespace App\Http\Requests\Demandes;

use App\Http\Controllers\DemandeDocumentController;
use App\Models\Demande;
use Illuminate\Validation\Rule;

class StoreDemandeRequest extends LeaveRequestForm
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            ...parent::rules(),
            // An optional supporting document sent with the request.
            ...DemandeDocumentController::rules(required: false),
        ];

        // Only admins may file for someone else; an employee's user_id is ignored.
        if ($this->actor()->can('createForOthers', Demande::class)) {
            // Deactivated employees (who have left) can't receive new requests.
            $rules['user_id'] = ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deactivated_at')];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [...parent::messages(), ...DemandeDocumentController::messages()];
    }

    public function ownerId(): int
    {
        if ($this->actor()->can('createForOthers', Demande::class) && $this->filled('user_id')) {
            return (int) $this->input('user_id');
        }

        return $this->actor()->id;
    }

    protected function excludedDemandeId(): ?int
    {
        return null;
    }
}
