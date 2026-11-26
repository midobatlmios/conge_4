<?php

namespace App\Http\Requests\Admin;

use App\Enums\CareerChangeKind;
use App\Enums\StaffCategory;
use App\Models\EmploymentContract;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * HR recording a promotion, raise or transfer (role:admin,rh routes, never
 * their own). It takes effect at once, so it cannot be dated in the future, and
 * it follows the previous changes. Category and salary change the contract in
 * force on that date, which must be the current one.
 */
class CareerChangeRequest extends FormRequest
{
    public function employee(): User
    {
        return $this->route('user');
    }

    public function authorize(): bool
    {
        return ! $this->employee()->is($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(CareerChangeKind::class)],
            'effective_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'change_department' => ['boolean'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'category' => ['nullable', Rule::enum(StaffCategory::class)],
            'gross_salary' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'kind' => 'type de changement',
            'effective_date' => "date d'effet",
            'job_title' => 'nouveau poste',
            'department_id' => 'nouveau département',
            'category' => 'nouvelle catégorie',
            'gross_salary' => 'nouveau salaire',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['effective_date.before_or_equal' => "Le changement s'applique dès son enregistrement : sa date d'effet ne peut pas être dans le futur."];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $employee = $this->employee();
                $date = Carbon::parse($this->input('effective_date'));

                if ($employee->hire_date && $date->lt($employee->hire_date)) {
                    $validator->errors()->add('effective_date', "La date d'effet doit suivre l'embauche ({$employee->hire_date->format('d/m/Y')}).");
                }

                $latest = $employee->careerChanges()->first();
                if ($latest && $date->lt($latest->effective_date)) {
                    $validator->errors()->add('effective_date', "Un changement plus récent est déjà enregistré (le {$latest->effective_date->format('d/m/Y')}).");
                }

                $changes = $this->changes();
                if ($changes === []) {
                    $validator->errors()->add('kind', 'Indiquez au moins un changement : poste, département, catégorie ou salaire différent de la situation actuelle.');
                }

                if (array_intersect(array_keys($changes), ['category', 'gross_salary']) !== [] && ! $this->contract()) {
                    $validator->errors()->add(
                        array_key_exists('gross_salary', $changes) ? 'gross_salary' : 'category',
                        "Aucun contrat en cours le {$date->format('d/m/Y')} : la catégorie et le salaire se changent sur le contrat en cours, ajoutez-le d'abord.",
                    );
                }
            },
        ];
    }

    /**
     * The contract in force on the effective date, when it is also the current
     * one (a change never rewrites a contract that has ended since).
     */
    public function contract(): ?EmploymentContract
    {
        $employee = $this->employee();
        $date = Carbon::parse($this->input('effective_date'));
        $current = $employee->currentContract();

        return $current && $current->start_date->lte($date) && ($current->end_date === null || $current->end_date->gte($date))
            ? $current
            : null;
    }

    /**
     * New values that differ from the current situation, by field. A field left
     * empty does not change (except the department, which has its own checkbox).
     *
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        $employee = $this->employee();
        $current = $employee->currentContract();
        $changes = [];

        $title = trim((string) $this->input('job_title'));
        if ($title !== '' && $title !== $employee->job_title) {
            $changes['job_title'] = $title;
        }

        $department = $this->input('department_id') ? (int) $this->input('department_id') : null;
        if ($this->boolean('change_department') && $department !== $employee->department_id) {
            $changes['department_id'] = $department;
        }

        $category = StaffCategory::tryFrom((string) $this->input('category'));
        if ($category && $category !== $current?->category) {
            $changes['category'] = $category;
        }

        if ($this->filled('gross_salary') && (float) $this->input('gross_salary') !== ($current?->gross_salary === null ? null : (float) $current->gross_salary)) {
            $changes['gross_salary'] = (string) round((float) $this->input('gross_salary'), 2);
        }

        return $changes;
    }
}
