<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\CareerChangeRequest;
use App\Models\CareerChange;
use App\Models\Department;
use App\Models\PersonalDataAccess;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Career history: HR records a promotion, raise or transfer (role:admin,rh
 * routes, never their own). The change is applied at once to the employee
 * record (job title, department) and to the current contract (category,
 * salary), keeping the previous values in the history. Only the latest change
 * can be removed (an input mistake): what it changed is put back.
 */
class CareerChangeController extends Controller
{
    public function store(CareerChangeRequest $request, User $user): RedirectResponse
    {
        $changes = $request->changes();
        $contract = $request->contract();

        DB::transaction(function () use ($request, $user, $changes, $contract) {
            $departments = Department::whereIn('id', array_filter([$user->department_id, $changes['department_id'] ?? null]))->pluck('name', 'id');
            $change = new CareerChange([
                'user_id' => $user->id,
                'contract_id' => $contract?->id,
                'effective_date' => $request->input('effective_date'),
                'kind' => $request->input('kind'),
                'notes' => $request->input('notes'),
                'created_by' => $request->user()->id,
            ]);

            if (array_key_exists('job_title', $changes)) {
                $change->fill(['previous_job_title' => $user->job_title, 'job_title' => $changes['job_title']]);
                $user->job_title = $changes['job_title'];
            }
            if (array_key_exists('department_id', $changes)) {
                $change->fill([
                    'department_changed' => true,
                    'previous_department_id' => $user->department_id,
                    'previous_department' => $departments[$user->department_id] ?? null,
                    'department_id' => $changes['department_id'],
                    'department' => $departments[$changes['department_id']] ?? null,
                ]);
                $user->department_id = $changes['department_id'];
            }
            if (array_key_exists('category', $changes)) {
                $change->fill(['previous_category' => $contract->category, 'category' => $changes['category']]);
                $contract->category = $changes['category'];
            }
            if (array_key_exists('gross_salary', $changes)) {
                $change->fill(['previous_gross_salary' => $contract->gross_salary, 'gross_salary' => $changes['gross_salary']]);
                $contract->gross_salary = $changes['gross_salary'];
            }

            $change->save();
            $user->save();
            $contract?->save();

            PersonalDataAccess::record($request->user(), $user, 'career_change', "Ajout : {$change->kind->label()}");
        });

        return back()->with('success', 'Changement enregistré et appliqué au dossier.');
    }

    public function destroy(Request $request, CareerChange $change): RedirectResponse
    {
        $user = $change->user;
        abort_if($user->is($request->user()), 403);

        if (! $user->careerChanges()->first()?->is($change)) {
            return back()->with('error', 'Seul le changement le plus récent peut être supprimé.');
        }

        DB::transaction(function () use ($request, $user, $change) {
            // Put back what this change set, unless it has been edited by hand since.
            if ($change->job_title !== null && $user->job_title === $change->job_title) {
                $user->job_title = $change->previous_job_title;
            }
            if ($change->department_changed && $user->department_id === $change->department_id) {
                $user->department_id = $change->previous_department_id;
            }
            $contract = $change->contract;
            if ($contract && $change->category !== null && $contract->category === $change->category) {
                $contract->category = $change->previous_category;
            }
            if ($contract && $change->gross_salary !== null && (float) $contract->gross_salary === (float) $change->gross_salary) {
                $contract->gross_salary = $change->previous_gross_salary;
            }

            $user->save();
            $contract?->save();
            $change->delete();

            PersonalDataAccess::record($request->user(), $user, 'career_change', "Suppression : {$change->kind->label()}");
        });

        return back()->with('success', 'Changement supprimé ; la situation précédente est rétablie.');
    }
}
