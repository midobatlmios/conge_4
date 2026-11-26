<?php

namespace App\Http\Controllers;

use App\Enums\AttestationType;
use App\Enums\CareerChangeKind;
use App\Enums\DocumentCategory;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Http\Requests\Admin\UpdateEmployeeProfileRequest;
use App\Models\Demande;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\PersonalDataAccess;
use App\Models\User;
use App\Services\LeaveCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * An employee's file: record and personal data. Admin and RH edit it, Paie reads
 * it (route middleware); the employee can only read their own personal data (own()).
 * Managers and colleagues never see it.
 */
class EmployeeController extends Controller
{
    public function show(Request $request, User $user): Response
    {
        PersonalDataAccess::record($request->user(), $user, 'view_file');

        $user->load(['department:id,name', 'manager:id,name', 'profile.editor:id,name']);
        $profile = $user->profile;
        $user->unsetRelation('profile');

        return Inertia::render('Admin/Employee', [
            'employee' => $user,
            'profile' => $profile,
            'missing' => $profile?->missingEssentials() ?? array_values(EmployeeProfile::ESSENTIAL),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'managers' => User::active()->orderBy('name')->get(['id', 'name']),
            'roles' => AdminController::roleOptions($request->user()),
            'documents' => EmployeeDocumentController::folder($user),
            'contracts' => $user->contracts->map(EmploymentContractController::present(...))->all(),
            'contractAlerts' => EmploymentContractController::alerts(collect([$user])),
            'contractOptions' => EmploymentContractController::options(),
            'careerChanges' => $user->careerChanges()->with('author:id,name')->get()->map->present(),
            'careerKinds' => CareerChangeKind::options(),
            'sickDays' => $this->sickDays($user),
            // Delegation of their approvals, when they manage a team.
            'isManager' => $user->isManager(),
            'delegations' => $user->delegationsGiven()->notOver()->with('delegate:id,name')->get()->map->present(),
            'nextLeave' => $this->nextLeave($user),
            'attestations' => $user->attestations()->with('handler:id,name')->latest()->get()->map->present(),
            'attestationTypes' => AttestationType::options(),
            'documentCategories' => DocumentCategory::options(),
            'documentMaxKb' => config('conge.employee_document_max_kb'),
        ]);
    }

    /**
     * Sick leave this year (declared or validated): working days off and the
     * calendar days the CNSS counts.
     *
     * @return array{year: int, working: float, calendar: int, count: int}
     */
    private function sickDays(User $user): array
    {
        $from = now()->startOfYear();
        $to = now()->endOfYear()->startOfDay();
        $leaves = $user->demandes()
            ->where('type_conge', LeaveType::Sick)
            ->whereNotIn('etat', LeaveStatus::inactive())
            ->whereDate('date_debut', '<=', $to)
            ->whereDate('date_fin', '>=', $from)
            ->get();
        $calculator = app(LeaveCalculator::class);

        return [
            'year' => $from->year,
            'working' => $leaves->sum(fn (Demande $leave) => $calculator->leaveDays($leave->date_debut, $leave->date_fin, false, false, $from, $to)),
            'calendar' => $leaves->sum(fn (Demande $leave) => (int) Carbon::parse($leave->date_debut)->max($from)->diffInDays(Carbon::parse($leave->date_fin)->min($to)) + 1),
            'count' => $leaves->count(),
        ];
    }

    /** @return array{start: string, end: string}|null The employee's next booked leave. */
    private function nextLeave(User $user): ?array
    {
        $leave = $user->demandes()->whereIn('etat', LeaveStatus::booked())->whereDate('date_fin', '>=', today())->orderBy('date_debut')->first();

        return $leave ? ['start' => Carbon::parse($leave->date_debut)->toDateString(), 'end' => Carbon::parse($leave->date_fin)->toDateString()] : null;
    }

    public function updateProfile(UpdateEmployeeProfileRequest $request, User $user): RedirectResponse
    {
        $profile = $user->profile ?? new EmployeeProfile;
        $profile->user_id = $user->id;
        $profile->fill($request->validated());

        if ($profile->isDirty()) {
            // Which fields changed, never their values.
            PersonalDataAccess::record($request->user(), $user, 'update_profile', implode(', ', array_map(
                fn (string $field) => $request->attributes()[$field] ?? $field,
                array_keys($profile->getDirty()),
            )));
            $profile->updated_by = $request->user()->id;
            $profile->save();
        }

        return back()->with('success', 'Données personnelles enregistrées.');
    }

    /** The signed-in employee's own personal data, read-only (Paramètres). */
    public function own(Request $request): Response
    {
        return Inertia::render('settings/personal-data', [
            'profile' => $request->user()->profile?->makeHidden(['updated_by', 'user_id']),
            'contracts' => $request->user()->contracts->map(EmploymentContractController::present(...))->all(),
            'careerChanges' => $request->user()->careerChanges()->get()->map->present(),
        ]);
    }
}
