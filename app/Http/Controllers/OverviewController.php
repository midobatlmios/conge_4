<?php

namespace App\Http\Controllers;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Demande;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Services\HrTodo;
use App\Services\LeaveCalculator;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HR overview: who is off today and this week, what is waiting for a decision,
 * and leave taken this year per department. Behind the role:admin,rh,paie middleware.
 */
class OverviewController extends Controller
{
    /** How many days ahead "coming up" looks. */
    private const UPCOMING_DAYS = 7;

    public function __construct(private readonly LeaveCalculator $calculator) {}

    public function index(): Response
    {
        $today = today();
        $year = $today->year;
        $employees = User::active()->with(['department:id,name', 'contracts'])->orderBy('name')->get();
        $absences = $this->absences($employees, $today);

        return Inertia::render('Admin/Overview', [
            'today' => $today->toDateString(),
            'year' => $year,
            'counts' => [
                'employees' => $employees->count(),
                'offToday' => count($absences['offToday']),
                // Waiting for HR: approved by the manager, or not yet (HR may decide at any step).
                'pending' => Demande::whereIn('etat', LeaveStatus::awaiting())->count(),
                'cancellations' => Demande::where('etat', LeaveStatus::CancellationRequested)->count(),
                'missingHireDate' => $employees->whereNull('hire_date')->count(),
            ],
            // Fixed-term contracts and trial periods ending soon.
            'contractAlerts' => EmploymentContractController::alerts($employees),
            // Folder documents (CIN, residence card, medical aptitude…) expired or expiring soon.
            'cnssPending' => app(HrTodo::class)->cnss($employees),
            'expiringDocuments' => EmployeeDocument::needsRenewal()
                ->whereIn('user_id', $employees->modelKeys())
                ->with('user:id,name')
                ->orderBy('expires_on')
                ->get()
                ->map(fn (EmployeeDocument $document) => [
                    'id' => $document->id,
                    'user_id' => $document->user_id,
                    'name' => $document->user->name,
                    'label' => $document->title ?: $document->category->label(),
                    'expires_on' => $document->expires_on->toDateString(),
                    'expiry' => EmployeeDocumentController::expiry($document),
                ]),
            ...$absences,
            'waiting' => Demande::with('user:id,name')
                ->whereIn('etat', [...LeaveStatus::awaiting(), LeaveStatus::CancellationRequested])
                ->orderBy('date_debut')
                ->limit(8)
                ->get()
                ->map(fn (Demande $demande) => [
                    'id' => $demande->id,
                    'name' => $demande->user?->name,
                    'type' => $demande->type_conge->value,
                    'start' => Carbon::parse($demande->date_debut)->toDateString(),
                    'end' => Carbon::parse($demande->date_fin)->toDateString(),
                    'days' => $demande->nbr_jours,
                    'status' => $demande->etat->value,
                ]),
            'departments' => $this->departments($employees, $year),
        ]);
    }

    /**
     * Booked leave of active employees today and over the next days.
     *
     * @return array{offToday: list<array<string, mixed>>, upcoming: list<array<string, mixed>>}
     */
    private function absences($employees, Carbon $today): array
    {
        $horizon = $today->copy()->addDays(self::UPCOMING_DAYS);
        $leaves = Demande::query()
            ->whereIn('user_id', $employees->modelKeys())
            ->whereIn('etat', LeaveStatus::booked())
            ->whereDate('date_debut', '<=', $horizon)
            ->whereDate('date_fin', '>=', $today)
            ->orderBy('date_debut')
            ->get();

        $byId = $employees->keyBy('id');
        $entry = fn (Demande $leave) => [
            'id' => $leave->id,
            'name' => $byId[$leave->user_id]->name,
            'department' => $byId[$leave->user_id]->department?->name,
            'type' => $leave->type_conge->value,
            'start' => Carbon::parse($leave->date_debut)->toDateString(),
            'end' => Carbon::parse($leave->date_fin)->toDateString(),
        ];

        $offToday = $leaves->filter(fn (Demande $leave) => Carbon::parse($leave->date_debut)->lte($today));

        return [
            'offToday' => $offToday->map($entry)->values()->all(),
            'upcoming' => $leaves->diff($offToday)->map($entry)->values()->all(),
        ];
    }

    /**
     * Leave taken this year per department (employees without one grouped last):
     * allowance days used, unpaid days, and the average per employee.
     *
     * @return list<array<string, mixed>>
     */
    private function departments($employees, int $year): array
    {
        $unpaid = Demande::query()
            ->whereIn('user_id', $employees->modelKeys())
            ->whereIn('etat', LeaveStatus::booked())
            ->where('type_conge', LeaveType::Unpaid)
            ->whereDate('date_debut', '<=', Carbon::create($year, 12, 31))
            ->whereDate('date_fin', '>=', Carbon::create($year, 1, 1))
            ->get()
            ->groupBy('user_id')
            ->map(fn ($leaves) => $leaves->sum(fn (Demande $leave) => $this->calculator->leaveDays(
                $leave->date_debut,
                $leave->date_fin,
                $leave->start_half,
                $leave->end_half,
                Carbon::create($year, 1, 1),
                Carbon::create($year, 12, 31),
            )));

        return $employees
            ->groupBy(fn (User $employee) => $employee->department?->name ?? '')
            ->map(function ($members, string $name) use ($year, $unpaid) {
                $used = $members->sum(fn (User $employee) => $this->calculator->usedDays($employee->id, $year));

                return [
                    'name' => $name === '' ? null : $name,
                    'employees' => $members->count(),
                    'usedDays' => $used,
                    'averageDays' => round($used / $members->count(), 1),
                    'unpaidDays' => $members->sum(fn (User $employee) => $unpaid[$employee->id] ?? 0),
                ];
            })
            ->sortBy(fn (array $row) => [$row['name'] === null, $row['name']])
            ->values()
            ->all();
    }
}
