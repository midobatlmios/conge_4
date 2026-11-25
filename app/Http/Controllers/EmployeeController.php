<?php

namespace App\Http\Controllers;

use App\Enums\AttestationType;
use App\Enums\DocumentCategory;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Http\Requests\Admin\UpdateEmployeeProfileRequest;
use App\Models\Demande;
use App\Models\Department;
use App\Models\EmployeeProfile;
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
            'sickDays' => $this->sickDays($user),
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

    public function updateProfile(UpdateEmployeeProfileRequest $request, User $user): RedirectResponse
    {
        $profile = $user->profile ?? new EmployeeProfile;
        $profile->user_id = $user->id;
        $profile->fill($request->validated());

        if ($profile->isDirty()) {
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
        ]);
    }
}
