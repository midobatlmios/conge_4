<?php

use App\Enums\Role;
use App\Models\EmploymentContract;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use App\Services\PayslipCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->travelTo('2026-07-10'));

function salaried(int $salary = 10000, array $attributes = []): User
{
    $user = User::factory()->create(['role' => Role::User, 'hire_date' => '2020-01-01', ...$attributes]);
    EmploymentContract::create(['user_id' => $user->id, 'type' => 'cdi', 'category' => 'cadre', 'gross_salary' => (string) $salary, 'start_date' => $user->hire_date]);

    return $user;
}

function payslipFor(User $user, string $month = '2026-06-01'): array
{
    $calculator = app(PayslipCalculator::class);
    $user = User::with(['contracts', 'careerChanges', 'profile'])->find($user->id);

    return $calculator->compute($calculator->inputs($user, now()->parse($month)), now()->parse($month));
}

test('a payslip: seniority bonus, CNSS capped, AMO, frais pro, IR with family deduction', function () {
    $user = salaried(10000);
    $user->profile()->create(['marital_status' => 'marie', 'children_count' => 2]);

    $p = payslipFor($user);

    // 6 full years: +10 %. Gross 11 000. CNSS on 6 000. Frais pro 25 % (gross > 6 500).
    // Net imposable 11 000 − 2 750 − 268.80 − 248.60 = 7 732.60 → 30 % − 1 500 = 819.78, − 3 × 41.67 = 694.78.
    expect($p)->toMatchArray([
        'seniority_rate' => 0.10, 'seniority' => 1000.0, 'gross' => 11000.0,
        'cnss' => 268.8, 'amo' => 248.6, 'professional_expenses' => 2750.0, 'taxable' => 7732.6,
        'family_deduction' => 125.0, 'ir' => 694.78, 'net' => 9787.82, 'employer_total' => 1870.9,
    ]);
});

test('low salaries: no seniority before 2 years, 35 % frais pro, no IR', function () {
    $p = payslipFor(salaried(4000, ['hire_date' => '2025-03-01']));

    expect($p['seniority'])->toEqual(0)
        ->and($p['professional_expenses'])->toEqual(1400)
        ->and($p['ir'])->toEqual(0)
        ->and($p['net'])->toEqual(round(4000 - 179.2 - 90.4, 2));
});

test('a hire during the month is paid for the working days employed', function () {
    // June 2026 has 22 working days; hired on Monday 15 June: 12 of them.
    $p = payslipFor(salaried(8800, ['hire_date' => '2026-06-15']));

    expect($p['employed_days'])->toBe(12)->and($p['month_days'])->toBe(22)->and($p['base'])->toEqual(4800);
});

test('Paie prepares and adjusts overtime; RH validates; the preparer cannot validate', function () {
    $paie = User::factory()->create(['role' => Role::Payroll]);
    $rh = User::factory()->create(['role' => Role::Hr]);
    $employee = salaried(10000);
    salaried(0); // no salary: no payslip

    $this->actingAs($paie)->post('/admin/payslips/prepare', ['month' => '2026-06'])->assertSessionHas('success');
    $run = PayrollRun::sole();
    $payslip = Payslip::sole();
    expect($payslip->user_id)->toBe($employee->id);

    // 2 h at +25 %: 10 000 ÷ 191 × 1.25 × 2 = 130.89.
    $this->actingAs($paie)->put("/admin/payslips/{$payslip->id}/inputs", slipInputs(['overtime_hours' => ['25' => 2, '50' => 0, '100' => 0]]))->assertSessionHasNoErrors();
    expect($payslip->fresh()->figures['overtime'])->toEqual(130.89)->and($payslip->fresh()->inputs['adjusted']['overtime'])->toBeTrue();

    $this->actingAs($paie)->put("/admin/payroll-runs/{$run->id}/submit")->assertSessionHas('success');
    $this->actingAs($paie)->put("/admin/payroll-runs/{$run->id}/lock")->assertForbidden();
    $this->actingAs($rh)->put("/admin/payroll-runs/{$run->id}/lock")->assertSessionHas('success');

    expect($run->fresh()->status)->toBe('locked');
    // A locked month never changes.
    $this->actingAs($paie)->put("/admin/payroll-runs/{$run->id}/recalculate")->assertForbidden();
    $this->actingAs($paie)->put("/admin/payslips/{$payslip->id}/inputs", slipInputs())->assertForbidden();
});

test('an RH who prepared the month needs someone else to validate it', function () {
    $rh = User::factory()->create(['role' => Role::Hr]);
    salaried();
    $this->actingAs($rh)->post('/admin/payslips/prepare', ['month' => '2026-06']);
    $run = PayrollRun::sole();
    $this->actingAs($rh)->put("/admin/payroll-runs/{$run->id}/submit");

    $this->actingAs($rh)->put("/admin/payroll-runs/{$run->id}/lock")->assertSessionHas('error');
    expect($run->fresh()->status)->toBe('ready');
});

test('employees see their own payslips only once the month is validated', function () {
    $paie = User::factory()->create(['role' => Role::Payroll]);
    $employee = salaried();
    $colleague = salaried();
    $this->actingAs($paie)->post('/admin/payslips/prepare', ['month' => '2026-06']);
    $run = PayrollRun::sole();
    $mine = Payslip::where('user_id', $employee->id)->sole();

    $this->actingAs($employee)->get("/bulletins/{$mine->id}/pdf")->assertForbidden();
    $this->actingAs($employee)->get('/settings/payslips')->assertInertia(fn (Assert $page) => $page->has('payslips', 0));

    $run->update(['status' => 'locked']);
    $this->actingAs($employee)->get('/settings/payslips')->assertInertia(fn (Assert $page) => $page->has('payslips', 1)->where('payslips.0.month', '2026-06'));
    $response = $this->actingAs($employee)->get("/bulletins/{$mine->id}/pdf")->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');
    $this->actingAs($colleague)->get("/bulletins/{$mine->id}/pdf")->assertForbidden();

    // Paie downloading someone's payslip is logged.
    $this->actingAs($paie)->get("/bulletins/{$mine->id}/pdf")->assertOk();
    $this->assertDatabaseHas('personal_data_accesses', ['subject_id' => $employee->id, 'action' => 'payslip_pdf']);
});

test('employees cannot open the payroll page; a future month cannot be prepared', function () {
    $this->actingAs(salaried())->get('/admin/payslips')->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => Role::Payroll]))->post('/admin/payslips/prepare', ['month' => '2026-09'])->assertStatus(422);
});

/** The payslip edit form as Paie sends it. */
function slipInputs(array $overrides = []): array
{
    return ['unpaid_days' => 0, 'absent_days' => 0, 'overtime_hours' => ['25' => 0, '50' => 0, '100' => 0], 'bonuses' => [], 'advances' => [], ...$overrides];
}

test('unpaid leave is deducted at salary ÷ 26 a day, before the seniority bonus', function () {
    $user = salaried(10400);
    App\Models\Demande::create(['user_id' => $user->id, 'type_conge' => 'sans_solde', 'date_debut' => '2026-06-08', 'date_fin' => '2026-06-09',
        'date_demande' => '2026-05-01', 'etat' => 'acceptée']);

    $p = payslipFor($user);

    // 2 days × 10 400 ÷ 26 = 800. Base 9 600, seniority 10 % of it.
    expect($p)->toMatchArray(['unpaid_days' => 2.0, 'day_value' => 400.0, 'absence_deduction' => 800.0, 'base' => 9600.0, 'seniority' => 960.0, 'cnss_days' => 24]);
});

test('taxable bonuses join the gross; exempt allowances and advances only change the net', function () {
    $user = salaried(10000);
    $calculator = app(PayslipCalculator::class);
    $user = User::with(['contracts', 'careerChanges', 'profile'])->find($user->id);
    $inputs = $calculator->inputs($user, now()->parse('2026-06-01'));
    $plain = $calculator->compute($inputs, now()->parse('2026-06-01'));

    $p = $calculator->compute([...$inputs,
        'bonuses' => [['label' => 'Prime de rendement', 'amount' => 500, 'taxable' => true], ['label' => 'Indemnité de transport', 'amount' => 300, 'taxable' => false]],
        'advances' => [['label' => 'Avance', 'amount' => 1000]],
    ], now()->parse('2026-06-01'));

    expect($p['gross'])->toEqual($plain['gross'] + 500)
        ->and($p['exempt_allowances'])->toEqual(300)
        ->and($p['advances'])->toEqual(1000)
        ->and($p['net'])->toEqual(round($p['gross'] - $p['cnss'] - $p['amo'] - $p['ir'] + 300 - 1000, 2));
});

test('cadres get no overtime from clocking; other staff do', function () {
    App\Models\CompanySetting::create(['name' => 'X', 'address' => 'x', 'city' => 'x', 'signatory_name' => 'x', 'attendance_started_on' => '2026-06-01']);
    $cadre = salaried(20000);
    $employee = salaried(6000);
    $employee->contracts()->update(['category' => 'employe']);
    foreach ([$cadre, $employee] as $user) {
        App\Models\AttendanceRecord::create(['user_id' => $user->id, 'date' => '2026-06-02', 'clock_in' => '08:30', 'clock_out' => '20:00']);
    }

    expect(payslipFor($cadre)['overtime'])->toEqual(0)
        ->and(payslipFor($employee)['overtime'])->toBeGreaterThan(0);
});

test('Paie adds bonuses and advances; recalculating keeps them; advances cannot exceed the net', function () {
    $paie = User::factory()->create(['role' => Role::Payroll]);
    salaried(10000);
    $this->actingAs($paie)->post('/admin/payslips/prepare', ['month' => '2026-06']);
    $run = PayrollRun::sole();
    $payslip = Payslip::sole();

    $this->actingAs($paie)->put("/admin/payslips/{$payslip->id}/inputs", slipInputs(['advances' => [['label' => 'Avance', 'amount' => 999999]]]))
        ->assertSessionHasErrors('advances');

    $this->actingAs($paie)->put("/admin/payslips/{$payslip->id}/inputs", slipInputs([
        'absent_days' => 1,
        'bonuses' => [['label' => 'Prime de rendement', 'amount' => 500, 'taxable' => true]],
        'advances' => [['label' => 'Avance', 'amount' => 1000]],
    ]))->assertSessionHasNoErrors();
    $this->actingAs($paie)->put("/admin/payroll-runs/{$run->id}/recalculate");

    $inputs = $payslip->fresh()->inputs;
    expect($inputs['bonuses'])->toHaveCount(1)
        ->and($inputs['advances'][0]['amount'])->toEqual(1000)
        ->and($inputs['absent_days'])->toEqual(1)
        ->and($payslip->fresh()->figures['taxable_bonuses'])->toEqual(500);

    $response = $this->actingAs($paie)->get("/admin/payroll-runs/{$run->id}/journal")->assertOk();
    $workbook = PhpOffice\PhpSpreadsheet\IOFactory::load($response->getFile()->getPathname());
    expect($workbook->getSheetNames())->toBe(['Journal de paie', 'CNSS']);
});

test('the CNSS file: 260-character lines, employees with and without a CNSS number, totals in centimes', function () {
    App\Models\CompanySetting::create(['name' => 'Atlas Distribution', 'address' => 'Bd Zerktouni', 'city' => 'Casablanca', 'signatory_name' => 'X', 'cnss_number' => '1234567']);
    $paie = User::factory()->create(['role' => Role::Payroll]);
    $registered = salaried(10000);
    $registered->profile()->create(['cnss_number' => '111222333']);
    $newcomer = salaried(4000);
    $newcomer->profile()->create(['cin' => 'BK123456']);
    $this->actingAs($paie)->post('/admin/payslips/prepare', ['month' => '2026-06']);
    $run = PayrollRun::sole();

    $response = $this->actingAs($paie)->get("/admin/payroll-runs/{$run->id}/cnss")->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('BDS_1234567_202606.txt');

    $lines = array_values(array_filter(explode("\r\n", $response->getContent())));
    expect(array_map(fn ($l) => substr($l, 0, 3), $lines))->toBe(['B00', 'B01', 'B02', 'B03', 'B04', 'B05', 'B06'])
        ->and(array_unique(array_map('strlen', $lines)))->toBe([260]);

    $b02 = $lines[2];
    expect(substr($b02, 3, 7))->toBe('1234567')
        ->and(substr($b02, 10, 6))->toBe('202606')
        ->and(substr($b02, 16, 9))->toBe('111222333')
        // 26 days, real salary 11 000.00 (10 000 + 10 % seniority) and capped 6 000.00, in centimes.
        ->and(substr($b02, 111, 2))->toBe('26')
        ->and(substr($b02, 113, 13))->toBe('0000001100000')
        ->and(substr($b02, 126, 9))->toBe('000600000')
        ->and(substr($lines[4], 85, 8))->toBe('BK123456');
});
