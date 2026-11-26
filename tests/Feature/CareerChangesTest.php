<?php

use App\Enums\CareerChangeKind;
use App\Enums\Role;
use App\Enums\StaffCategory;
use App\Models\CareerChange;
use App\Models\Department;
use App\Models\EmploymentContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->travelTo('2026-06-15'));

function careerEmployee(array $attributes = []): User
{
    $employee = User::factory()->create(['role' => Role::User, 'hire_date' => '2022-01-01', 'job_title' => 'Comptable', ...$attributes]);
    EmploymentContract::create([
        'user_id' => $employee->id, 'type' => 'cdi', 'category' => 'employe', 'job_title' => 'Comptable',
        'gross_salary' => '8000', 'start_date' => '2022-01-01',
    ]);

    return $employee;
}

function recordChange(User $actor, User $employee, array $data)
{
    return test()->actingAs($actor)->post("/admin/users/{$employee->id}/career-changes", [
        'kind' => 'promotion', 'effective_date' => '2026-06-01', ...$data,
    ]);
}

test('a promotion updates the record and the current contract, keeping the previous values', function () {
    $rh = User::factory()->create(['role' => Role::Hr]);
    $finance = Department::create(['name' => 'Finance']);
    $employee = careerEmployee();

    recordChange($rh, $employee, [
        'job_title' => 'Chef comptable', 'change_department' => true, 'department_id' => $finance->id,
        'category' => 'cadre', 'gross_salary' => '11000', 'notes' => 'Avenant du 28/05',
    ])->assertSessionHasNoErrors();

    $employee->refresh();
    $contract = $employee->currentContract();
    $change = CareerChange::sole();

    expect($employee->job_title)->toBe('Chef comptable')
        ->and($employee->department_id)->toBe($finance->id)
        ->and($contract->category)->toBe(StaffCategory::Executive)
        ->and((float) $contract->gross_salary)->toBe(11000.0)
        ->and($change->kind)->toBe(CareerChangeKind::Promotion)
        ->and($change->previous_job_title)->toBe('Comptable')
        ->and($change->previous_department)->toBeNull()
        ->and($change->department)->toBe('Finance')
        ->and($change->previous_category)->toBe(StaffCategory::Employee)
        ->and((float) $change->previous_gross_salary)->toBe(8000.0)
        ->and($change->contract_id)->toBe($contract->id);

    // The salary is encrypted at rest, like on the contract.
    expect(DB::table('career_changes')->value('gross_salary'))->not->toContain('11000');
});

test('the salary on a given day follows the raises', function () {
    $rh = User::factory()->create(['role' => Role::Hr]);
    $employee = careerEmployee();

    recordChange($rh, $employee, ['kind' => 'augmentation', 'effective_date' => '2025-01-01', 'gross_salary' => '9000']);
    recordChange($rh, $employee, ['kind' => 'augmentation', 'effective_date' => '2026-03-01', 'gross_salary' => '9500']);

    $employee = User::with(['contracts', 'careerChanges'])->find($employee->id);
    expect($employee->salaryOn(now()->parse('2024-12-31')))->toBe(8000.0)
        ->and($employee->salaryOn(now()->parse('2025-01-01')))->toBe(9000.0)
        ->and($employee->salaryOn(now()->parse('2026-02-28')))->toBe(9000.0)
        ->and($employee->salaryOn(now()->parse('2026-06-15')))->toBe(9500.0)
        ->and($employee->salaryOn(now()->parse('2021-06-01')))->toBeNull();
});

test('a change must change something, follow the hire and earlier changes, and not be in the future', function () {
    $rh = User::factory()->create(['role' => Role::Hr]);
    $employee = careerEmployee();

    recordChange($rh, $employee, ['job_title' => 'Comptable', 'gross_salary' => '8000'])->assertSessionHasErrors('kind');
    recordChange($rh, $employee, ['effective_date' => '2026-07-01', 'job_title' => 'Chef'])->assertSessionHasErrors('effective_date');
    recordChange($rh, $employee, ['effective_date' => '2021-12-31', 'job_title' => 'Chef'])->assertSessionHasErrors('effective_date');

    recordChange($rh, $employee, ['effective_date' => '2026-05-01', 'job_title' => 'Chef'])->assertSessionHasNoErrors();
    recordChange($rh, $employee, ['effective_date' => '2026-04-01', 'gross_salary' => '9000'])->assertSessionHasErrors('effective_date');
});

test('category and salary need a contract in force on the date', function () {
    $rh = User::factory()->create(['role' => Role::Hr]);
    $employee = User::factory()->create(['role' => Role::User, 'hire_date' => '2022-01-01']);

    recordChange($rh, $employee, ['gross_salary' => '9000'])->assertSessionHasErrors('gross_salary');
    recordChange($rh, $employee, ['job_title' => 'Assistant'])->assertSessionHasNoErrors();
    expect($employee->fresh()->job_title)->toBe('Assistant');
});

test('only the latest change can be removed, and it puts the previous situation back', function () {
    $rh = User::factory()->create(['role' => Role::Hr]);
    $employee = careerEmployee();
    recordChange($rh, $employee, ['effective_date' => '2025-01-01', 'gross_salary' => '9000']);
    recordChange($rh, $employee, ['effective_date' => '2026-06-01', 'job_title' => 'Chef comptable', 'gross_salary' => '10000']);
    [$latest, $first] = $employee->careerChanges()->get()->all();

    $this->actingAs($rh)->delete("/admin/career-changes/{$first->id}")->assertSessionHas('error');
    expect(CareerChange::count())->toBe(2);

    $this->actingAs($rh)->delete("/admin/career-changes/{$latest->id}")->assertSessionHas('success');
    $employee->refresh();
    expect(CareerChange::count())->toBe(1)
        ->and($employee->job_title)->toBe('Comptable')
        ->and((float) $employee->currentContract()->gross_salary)->toBe(9000.0);
});

test('Paie, managers and employees cannot record changes; nobody records their own', function () {
    $rh = User::factory()->create(['role' => Role::Hr, 'hire_date' => '2022-01-01']);
    $employee = careerEmployee();

    foreach ([Role::Payroll, Role::User] as $role) {
        recordChange(User::factory()->create(['role' => $role]), $employee, ['job_title' => 'Chef'])->assertForbidden();
    }
    recordChange($rh, $rh, ['job_title' => 'DRH'])->assertForbidden();
    expect(CareerChange::count())->toBe(0);
});

test('the employee page and the employee own page show the history; the access is logged', function () {
    $rh = User::factory()->create(['role' => Role::Hr]);
    $employee = careerEmployee();
    recordChange($rh, $employee, ['kind' => 'augmentation', 'gross_salary' => '8800']);

    $this->actingAs($rh)->get("/admin/users/{$employee->id}")->assertInertia(fn (Assert $page) => $page
        ->has('careerChanges', 1)
        ->where('careerChanges.0.gross_salary', ['from' => 8000, 'to' => 8800])
        ->where('careerChanges.0.author', $rh->name)
        ->has('careerKinds', count(CareerChangeKind::cases())));

    $this->actingAs($employee)->get('/settings/personal-data')->assertInertia(fn (Assert $page) => $page
        ->where('careerChanges.0.kind', 'augmentation'));

    $this->assertDatabaseHas('personal_data_accesses', ['subject_id' => $employee->id, 'action' => 'career_change']);
});

test('the attestation prints the current job title from the record', function () {
    $rh = User::factory()->create(['role' => Role::Hr]);
    $employee = careerEmployee();
    recordChange($rh, $employee, ['job_title' => 'Chef comptable']);

    $snapshot = (new ReflectionMethod(App\Services\AttestationIssuer::class, 'snapshot'))->invoke(
        app(App\Services\AttestationIssuer::class),
        App\Models\Attestation::forceCreate(['user_id' => $employee->id, 'type' => 'travail', 'status' => 'delivree', 'number' => 'ATT-T', 'issued_at' => now()]),
        null,
    );

    expect($snapshot['employee']['job_title'])->toBe('Chef comptable');
});
