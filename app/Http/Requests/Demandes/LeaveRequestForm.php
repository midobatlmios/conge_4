<?php

namespace App\Http\Requests\Demandes;

use App\Enums\LeaveType;
use App\Models\Demande;
use App\Models\User;
use App\Services\LeaveCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation shared by creating and editing a leave request: dates, type,
 * working-day limits and overlap with the employee's other requests.
 */
abstract class LeaveRequestForm extends FormRequest
{
    /** Employee the request belongs to. */
    abstract public function ownerId(): int;

    /** Request being edited, so it does not conflict with itself. */
    abstract protected function excludedDemandeId(): ?int;

    protected function actor(): User
    {
        return $this->user();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Employees can't request leave in the past; admins may record it after the fact.
            'date_debut' => $this->actor()->isAdmin()
                ? ['required', 'date']
                : ['required', 'date', 'after_or_equal:today'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'type_conge' => ['required', Rule::enum(LeaveType::class)],
            'comment' => ['required_if:type_conge,'.LeaveType::Recovery->value, 'nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'date_debut.after_or_equal' => 'La date de début ne peut pas être dans le passé.',
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->checkDays($validator);
                $this->checkOverlap($validator);
            },
        ];
    }

    public function leaveType(): LeaveType
    {
        return LeaveType::from($this->validated('type_conge'));
    }

    private function checkDays(Validator $validator): void
    {
        $calculator = app(LeaveCalculator::class);
        $days = $calculator->workingDays($this->input('date_debut'), $this->input('date_fin'));

        if ($days === 0) {
            $validator->errors()->add('date_fin', 'La période choisie ne contient aucun jour ouvré.');

            return;
        }

        $limit = $calculator->typeLimit(LeaveType::from($this->input('type_conge')));

        if ($limit !== null && $days > $limit) {
            $validator->errors()->add('date_fin', "Cette demande ne peut pas dépasser {$limit} jours ouvrés.");
        }
    }

    private function checkOverlap(Validator $validator): void
    {
        $overlap = Demande::overlapping(
            $this->ownerId(),
            $this->input('date_debut'),
            $this->input('date_fin'),
            $this->excludedDemandeId()
        )->first();

        if ($overlap) {
            $from = Carbon::parse($overlap->date_debut)->format('d/m/Y');
            $to = Carbon::parse($overlap->date_fin)->format('d/m/Y');
            $validator->errors()->add('date_debut', "Cette période chevauche une autre demande (du {$from} au {$to}).");
        }
    }
}
