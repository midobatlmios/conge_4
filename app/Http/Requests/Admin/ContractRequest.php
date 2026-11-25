<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContractType;
use App\Enums\StaffCategory;
use App\Models\EmploymentContract;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * HR adding or editing an employment contract (role:admin,rh routes). Nobody
 * manages their own contracts. An employee's contracts never overlap.
 */
class ContractRequest extends FormRequest
{
    /** The contract being edited, if any. */
    public function contract(): ?EmploymentContract
    {
        return $this->route('contract');
    }

    /** The employee the contract belongs to. */
    public function employee(): User
    {
        return $this->contract()?->user ?? $this->route('user');
    }

    public function authorize(): bool
    {
        return ! $this->employee()->is($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $type = ContractType::tryFrom((string) $this->input('type'));

        return [
            'type' => ['required', Rule::enum(ContractType::class)],
            'category' => ['nullable', Rule::enum(StaffCategory::class)],
            'job_title' => ['nullable', 'string', 'max:100'],
            'gross_salary' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            // A CDI only has an end date once it ends (resignation, dismissal…).
            'end_date' => [$type?->requiresEndDate() ? 'required' : 'nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'trial_end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date', ...($this->filled('end_date') ? ['before_or_equal:end_date'] : [])],
            'trial_renewed' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['start_date', 'end_date'])) {
                    return;
                }

                $start = $this->input('start_date');
                $end = $this->input('end_date');

                $clash = $this->employee()->contracts()
                    ->when($this->contract(), fn ($query, $contract) => $query->whereKeyNot($contract->id))
                    ->when($end, fn ($query) => $query->whereDate('start_date', '<=', $end))
                    ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $start))
                    ->first();

                if ($clash) {
                    $validator->errors()->add('start_date', $clash->end_date
                        ? "Ce contrat chevauche le {$clash->type->label()} du {$clash->start_date->format('d/m/Y')} au {$clash->end_date->format('d/m/Y')}."
                        : "Ce contrat chevauche le {$clash->type->label()} en cours depuis le {$clash->start_date->format('d/m/Y')} : indiquez d'abord sa date de fin.");
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'type' => 'type de contrat',
            'category' => 'catégorie',
            'job_title' => 'poste',
            'gross_salary' => 'salaire brut mensuel',
            'start_date' => 'date de début',
            'end_date' => 'date de fin',
            'trial_end' => "fin de période d'essai",
            'notes' => 'remarques',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'end_date.required' => 'Un CDD, un contrat ANAPEC ou un stage a une date de fin.',
            'trial_end.before_or_equal' => "La période d'essai doit finir avant la fin du contrat.",
        ];
    }

    /** @return array<string, mixed> */
    public function contractData(): array
    {
        return [
            ...$this->safe()->except('trial_renewed'),
            'trial_renewed' => $this->boolean('trial_renewed'),
        ];
    }
}
