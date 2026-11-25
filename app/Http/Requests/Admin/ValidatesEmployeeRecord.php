<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Employee record fields shared by the create and edit user forms. The hire
 * date is required (monthly accrual will be based on it); the rest is optional.
 */
trait ValidatesEmployeeRecord
{
    /** @return array<string, mixed> */
    protected function employeeRecordRules(?User $target = null): array
    {
        return [
            'matricule' => ['nullable', 'string', 'max:50', Rule::unique('users', 'matricule')->ignore($target?->id)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'job_title' => ['nullable', 'string', 'max:100'],
            'hire_date' => ['required', 'date_format:Y-m-d'],
            'manager_id' => [
                'nullable',
                'integer',
                // A newly picked manager must be active; an unchanged one who has since
                // left is kept so HR can still edit the rest of the record.
                $target && (int) $this->input('manager_id') === $target->manager_id
                    ? Rule::exists('users', 'id')
                    : Rule::exists('users', 'id')->whereNull('deactivated_at'),
                Rule::notIn(array_filter([$target?->id])),
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'department_id' => 'département',
            'job_title' => 'poste',
            'hire_date' => "date d'embauche",
            'manager_id' => 'responsable',
        ];
    }

    /** @return array<string, string> */
    protected function employeeRecordMessages(): array
    {
        return [
            'manager_id.not_in' => 'Un employé ne peut pas être son propre responsable.',
            'manager_id.exists' => 'Le responsable doit être un employé actif.',
        ];
    }

    /** A manager cannot report (directly or not) to the employee they manage. */
    protected function checkManagerChain(Validator $validator, ?User $target): void
    {
        $managerId = $this->input('manager_id');

        if (! $target || ! $managerId || $validator->errors()->has('manager_id')) {
            return;
        }

        $seen = [];
        $current = User::find($managerId);

        while ($current && ! in_array($current->id, $seen, true)) {
            if ($current->manager_id === $target->id) {
                $validator->errors()->add('manager_id', "{$target->name} est déjà le responsable (direct ou indirect) de cette personne.");

                return;
            }
            $seen[] = $current->id;
            $current = $current->manager_id ? User::find($current->manager_id) : null;
        }
    }

    /**
     * Validated employee record (empty fields arrive as null).
     *
     * @return array<string, mixed>
     */
    public function employeeRecord(): array
    {
        return $this->safe()->only(['matricule', 'department_id', 'job_title', 'hire_date', 'manager_id']);
    }
}
