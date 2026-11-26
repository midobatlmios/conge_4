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
    $this->actingAs($paie)->put("/admin/payslips/{$payslip->id}/overtime", ['overtime_hours' => ['25' => 2, '50' => 0, '100' => 0]])->assertSessionHasNoErrors();
    expect($payslip->fresh()->figures['overtime'])->toEqual(130.89)->and($payslip->fresh()->inputs['overtime_adjusted'])->toBeTrue();

    $this->actingAs($paie)->put("/admin/payroll-runs/{$run->id}/submit")->assertSessionHas('success');
    $this->actingAs($paie)->put("/admin/payroll-runs/{$run->id}/lock")->assertForbidden();
    $this->actingAs($rh)->put("/admin/payroll-runs/{$run->id}/lock")->assertSessionHas('success');

    expect($run->fresh()->status)->toBe('locked');
    // A locked month never changes.
    $this->actingAs($paie)->put("/admin/payroll-runs/{$run->id}/recalculate")->assertForbidden();
    $this->actingAs($paie)->put("/admin/payslips/{$payslip->id}/overtime", ['overtime_hours' => ['25' => 0, '50' => 0, '100' => 0]])->assertForbidden();
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
