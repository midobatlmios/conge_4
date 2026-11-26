<?php

namespace App\Http\Controllers;

use App\Enums\ContractType;
use App\Enums\StaffCategory;
use App\Http\Requests\Admin\ContractRequest;
use App\Models\EmploymentContract;
use App\Models\PersonalDataAccess;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Employment contracts (history per employee). Admin and RH add, edit and
 * remove them (role:admin,rh routes, not their own); Paie and the employee read.
 */
class EmploymentContractController extends Controller
{
    public function store(ContractRequest $request, User $user): RedirectResponse
    {
        $contract = $user->contracts()->create([...$request->contractData(), 'created_by' => $request->user()->id]);
        PersonalDataAccess::record($request->user(), $user, 'update_contract', "Ajout : {$contract->type->label()}");

        return back()->with('success', 'Contrat ajouté.');
    }

    public function update(ContractRequest $request, EmploymentContract $contract): RedirectResponse
    {
        $contract->update($request->contractData());
        PersonalDataAccess::record($request->user(), $contract->user, 'update_contract', "Modification : {$contract->type->label()}");

        return back()->with('success', 'Contrat modifié.');
    }

    public function destroy(Request $request, EmploymentContract $contract): RedirectResponse
    {
        abort_if($contract->user_id === $request->user()->id, 403);

        $contract->delete();
        PersonalDataAccess::record($request->user(), $contract->user, 'update_contract', "Suppression : {$contract->type->label()}");

        return back()->with('success', 'Contrat supprimé.');
    }

    /**
     * A contract as the pages show it.
     *
     * @return array<string, mixed>
     */
    public static function present(EmploymentContract $contract): array
    {
        return [
            'id' => $contract->id,
            'type' => $contract->type->value,
            'type_label' => $contract->type->label(),
            'category' => $contract->category?->value,
            'category_label' => $contract->category?->label(),
            'job_title' => $contract->job_title,
            // Seen only by HR (incl. Paie) and the employee: every page using present() is.
            'gross_salary' => $contract->gross_salary === null ? null : (float) $contract->gross_salary,
            'start_date' => $contract->start_date->toDateString(),
            'end_date' => $contract->end_date?->toDateString(),
            'trial_end' => $contract->trial_end?->toDateString(),
            'trial_renewed' => $contract->trial_renewed,
            'in_trial' => $contract->inTrial(),
            'notes' => $contract->notes,
            'status' => $contract->status(),
        ];
    }

    /**
     * What HR must act on soon, from each employee's current contract: a
     * fixed-term contract ending (or ended with nothing after it) and trial
     * periods ending within the alert window. Soonest first.
     *
     * @param  Collection<int, User>  $employees  with their contracts loaded
     * @return list<array<string, mixed>>
     */
    public static function alerts(Collection $employees): array
    {
        $today = today();
        $horizon = $today->copy()->addDays(config('conge.contract_alert_days'));
        $alerts = [];

        foreach ($employees as $employee) {
            $contract = $employee->currentContract();

            if (! $contract) {
                continue;
            }

            $base = ['user_id' => $employee->id, 'name' => $employee->name, 'contract' => $contract->type->label()];

            if ($contract->type->requiresEndDate() && $contract->end_date && $contract->end_date->lte($horizon)) {
                $alerts[] = [...$base, 'kind' => $contract->end_date->lt($today) ? 'ended' : 'contract_end', 'date' => $contract->end_date->toDateString()];
            }

            if ($contract->trial_end && $contract->trial_end->gte($today) && $contract->trial_end->lte($horizon)) {
                $alerts[] = [...$base, 'kind' => 'trial_end', 'date' => $contract->trial_end->toDateString()];
            }
        }

        usort($alerts, fn (array $a, array $b) => strcmp($a['date'], $b['date']));

        return $alerts;
    }

    /** @return array{types: list<array{value: string, label: string}>, categories: list<array{value: string, label: string}>} */
    public static function options(): array
    {
        return ['types' => ContractType::options(), 'categories' => StaffCategory::options()];
    }
}
