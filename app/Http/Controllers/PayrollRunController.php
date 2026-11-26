<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PersonalDataAccess;
use App\Models\User;
use App\Services\PayslipCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Monthly payroll. Paie (or RH / Admin) prepares the month: one payslip per
 * employee with a salary, overtime pre-filled from attendance and adjustable;
 * then sends it for validation. RH or Admin — not the person who prepared it —
 * validates and locks it: payslips become final and employees see them.
 */
class PayrollRunController extends Controller
{
    public function __construct(private readonly PayslipCalculator $calculator) {}

    public function index(Request $request): Response
    {
        $month = $this->month($request);
        $run = PayrollRun::with(['preparer:id,name', 'locker:id,name'])->whereDate('month', $month)->first();
        $payslips = $run ? $run->payslips()->with('user:id,name')->get()->sortBy('user.name')->values() : collect();

        return Inertia::render('Admin/Payslips', [
            'month' => $month->format('Y-m'),
            'run' => $run ? [
                'id' => $run->id,
                'status' => $run->status,
                'status_label' => PayrollRun::STATUS_LABELS[$run->status],
                'prepared_by' => $run->preparer?->name,
                'prepared_by_id' => $run->prepared_by,
                'prepared_at' => $run->prepared_at?->toIso8601String(),
                'locked_by' => $run->locker?->name,
                'locked_at' => $run->locked_at?->toIso8601String(),
            ] : null,
            'payslips' => $payslips->map(fn (Payslip $p) => [
                'id' => $p->id,
                'user_id' => $p->user_id,
                'name' => $p->user?->name ?? $p->figures['employee']['name'],
                'overtime_hours' => $p->inputs['overtime_hours'],
                'overtime_from_attendance' => $p->inputs['overtime_from_attendance'],
                'overtime_adjusted' => $p->inputs['overtime_adjusted'],
                ...collect($p->figures)->only(['base', 'seniority', 'overtime', 'gross', 'cnss', 'amo', 'ir', 'net', 'employer_total', 'cost', 'employed_days', 'month_days'])->all(),
            ]),
            'missing' => $run && ! $run->isLocked() ? $this->withoutSalary($month) : [],
            'runs' => PayrollRun::orderByDesc('month')->limit(12)->get()->map(fn (PayrollRun $r) => [
                'month' => $r->month->format('Y-m'), 'status_label' => PayrollRun::STATUS_LABELS[$r->status],
            ]),
        ]);
    }

    /** Creates the month's payroll (draft) with a payslip per employee who has a salary. */
    public function prepare(Request $request): RedirectResponse
    {
        $month = $this->month($request);
        abort_if($month->gt(today()->startOfMonth()), 422, 'La paie d’un mois futur ne peut pas être préparée.');

        if (PayrollRun::whereDate('month', $month)->exists()) {
            return back()->with('error', 'La paie de ce mois existe déjà.');
        }

        $run = PayrollRun::create(['month' => $month->toDateString(), 'status' => 'draft', 'prepared_by' => $request->user()->id, 'prepared_at' => now()]);
        $count = $this->generate($run);

        return back()->with('success', "Paie préparée : {$count} bulletin(s).");
    }

    /** Reads the employees' files and attendance again; overtime adjusted by hand is kept. */
    public function recalculate(Request $request, PayrollRun $run): RedirectResponse
    {
        abort_unless($run->status === 'draft', 403);
        $this->generate($run);

        return back()->with('success', 'Bulletins recalculés.');
    }

    public function updateOvertime(Request $request, Payslip $payslip): RedirectResponse
    {
        abort_unless($payslip->run->status === 'draft', 403);
        $validated = $request->validate([
            'overtime_hours.25' => ['required', 'numeric', 'min:0', 'max:300'],
            'overtime_hours.50' => ['required', 'numeric', 'min:0', 'max:300'],
            'overtime_hours.100' => ['required', 'numeric', 'min:0', 'max:300'],
        ], [], ['overtime_hours.25' => 'heures à +25 %', 'overtime_hours.50' => 'heures à +50 %', 'overtime_hours.100' => 'heures à +100 %']);

        $inputs = $payslip->inputs;
        $inputs['overtime_hours'] = array_map(fn ($hours) => round((float) $hours, 2), $validated['overtime_hours']);
        $inputs['overtime_adjusted'] = $inputs['overtime_hours'] != $inputs['overtime_from_attendance'];
        $payslip->update(['inputs' => $inputs, 'figures' => [...$payslip->figures, ...$this->calculator->compute($inputs, $payslip->run->month)]]);

        return back()->with('success', 'Heures supplémentaires mises à jour.');
    }

    /** Paie sends the month for validation. */
    public function submit(Request $request, PayrollRun $run): RedirectResponse
    {
        abort_unless($run->status === 'draft', 403);
        $run->update(['status' => 'ready', 'prepared_by' => $request->user()->id, 'prepared_at' => now()]);

        return back()->with('success', 'Paie envoyée pour validation à RH.');
    }

    /** Back to preparation (before it is locked). */
    public function reopen(PayrollRun $run): RedirectResponse
    {
        abort_unless($run->status === 'ready', 403);
        $run->update(['status' => 'draft']);

        return back()->with('success', 'Paie rouverte pour modification.');
    }

    /** RH or Admin validates: payslips become final and visible to employees. */
    public function lock(Request $request, PayrollRun $run): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isHr() && $run->status === 'ready', 403);

        if ($run->prepared_by === $user->id) {
            return back()->with('error', 'La paie doit être validée par une autre personne que celle qui l’a préparée.');
        }

        $run->update(['status' => 'locked', 'locked_by' => $user->id, 'locked_at' => now()]);

        return back()->with('success', 'Paie validée : les employés peuvent consulter leur bulletin.');
    }

    /** A payslip as PDF: HR, Paie and Admin; the employee only once the month is locked. */
    public function pdf(Request $request, Payslip $payslip)
    {
        $user = $request->user();
        abort_unless($user->canViewHr() || ($payslip->user_id === $user->id && $payslip->run->isLocked()), 403);

        if ($payslip->user_id !== $user->id) {
            PersonalDataAccess::record($user, $payslip->user, 'payslip_pdf', $payslip->run->month->format('m/Y'));
        }

        return self::renderPdf($payslip)->download($payslip->fileName());
    }

    /** The employee's own locked payslips (Paramètres → Mes bulletins). */
    public function own(Request $request): Response
    {
        return Inertia::render('settings/payslips', [
            'payslips' => Payslip::with('run')
                ->where('user_id', $request->user()->id)
                ->whereHas('run', fn ($query) => $query->where('status', 'locked'))
                ->get()
                ->sortByDesc(fn (Payslip $p) => $p->run->month)
                ->values()
                ->map(fn (Payslip $p) => ['id' => $p->id, 'month' => $p->run->month->format('Y-m'), 'gross' => $p->figures['gross'], 'net' => $p->figures['net']]),
        ]);
    }

    public static function renderPdf(Payslip $payslip): \Barryvdh\DomPDF\PDF
    {
        $company = \App\Models\CompanySetting::current();

        return Pdf::loadView('pdf.payslip', ['p' => $payslip->figures, 'month' => $payslip->run->month, 'company' => $company, 'logo' => $company->logoDataUri()])
            ->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true);
    }

    /**
     * (Re)builds the payslips of a draft month from the employees' files.
     *
     * @return int How many payslips the month has.
     */
    private function generate(PayrollRun $run): int
    {
        $month = $run->month;

        return DB::transaction(function () use ($run, $month) {
            $existing = $run->payslips()->get()->keyBy('user_id');
            $kept = [];

            foreach ($this->employees($month) as $user) {
                $inputs = $this->calculator->inputs($user, $month);
                if ($inputs === null) {
                    continue;
                }

                // Hours Paie adjusted by hand stay as they are.
                $previous = $existing[$user->id] ?? null;
                if ($previous && ($previous->inputs['overtime_adjusted'] ?? false)) {
                    $inputs['overtime_hours'] = $previous->inputs['overtime_hours'];
                    $inputs['overtime_adjusted'] = true;
                }

                $figures = [...$this->calculator->compute($inputs, $month), 'employee' => $this->calculator->employee($user)];
                Payslip::updateOrCreate(['payroll_run_id' => $run->id, 'user_id' => $user->id], ['inputs' => $inputs, 'figures' => $figures]);
                $kept[] = $user->id;
            }

            $run->payslips()->whereNotIn('user_id', $kept)->delete();

            return count($kept);
        });
    }

    /** Everyone employed at some point of the month. */
    private function employees(Carbon $month)
    {
        $end = $month->copy()->endOfMonth();

        return User::with(['contracts', 'careerChanges', 'profile', 'department:id,name'])
            ->where(fn ($q) => $q->whereNull('deactivated_at')->orWhere('deactivated_at', '>', $month))
            ->where(fn ($q) => $q->whereNull('hire_date')->orWhereDate('hire_date', '<=', $end))
            ->orderBy('name')
            ->get();
    }

    /** @return list<array{id: int, name: string}> Employees of the month left out for lack of a salary. */
    private function withoutSalary(Carbon $month): array
    {
        return $this->employees($month)
            ->filter(fn (User $user) => $this->calculator->inputs($user, $month) === null && $user->salaryOn($month->copy()->endOfMonth()->startOfDay()) === null)
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
            ->values()
            ->all();
    }

    private function month(Request $request): Carbon
    {
        $value = (string) $request->input('month');

        return preg_match('/^\d{4}-\d{2}$/', $value)
            ? Carbon::createFromFormat('Y-m-d', "{$value}-01")->startOfDay()
            : today()->startOfMonth();
    }
}
