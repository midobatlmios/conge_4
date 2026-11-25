<?php

use App\Enums\ContractType;
use App\Enums\Role;
use App\Models\EmploymentContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function contractUser(Role $role = Role::User): User
{
    return User::factory()->create(['role' => $role, 'hire_date' => '2020-01-01']);
}

function addContract(User $actor, User $employee, array $data)
{
    return test()->actingAs($actor)->post("/admin/users/{$employee->id}/contracts", $data);
}

test('RH records a contract history: CDD, then CDI', function () {
    $rh = contractUser(Role::Hr);
    $employee = contractUser();

    addContract($rh, $employee, ['type' => 'cdd', 'category' => 'employe', 'start_date' => '2025-01-01', 'end_date' => '2025-12-31', 'trial_end' => '2025-01-14'])
        ->assertSessionHasNoErrors();
    addContract($rh, $employee, ['type' => 'cdi', 'category' => 'employe', 'start_date' => '2026-01-01', 'trial_renewed' => true])
        ->assertSessionHasNoErrors();

    $contracts = $employee->contracts()->get();
    expect($contracts)->toHaveCount(2)
        ->and($contracts->first()->type)->toBe(ContractType::Permanent)
        ->and($contracts->first()->trial_renewed)->toBeTrue()
        ->and($employee->fresh()->currentContract()->type)->toBe(ContractType::Permanent);
});

test('a fixed-term contract needs an end date; dates must be consistent', function () {
    $rh = contractUser(Role::Hr);
    $employee = contractUser();

    addContract($rh, $employee, ['type' => 'cdd', 'start_date' => '2026-01-01'])->assertSessionHasErrors('end_date');
    addContract($rh, $employee, ['type' => 'stage', 'start_date' => '2026-01-01', 'end_date' => '2025-12-01'])->assertSessionHasErrors('end_date');
    addContract($rh, $employee, ['type' => 'cdd', 'start_date' => '2026-01-01', 'end_date' => '2026-03-31', 'trial_end' => '2026-05-01'])->assertSessionHasErrors('trial_end');
    addContract($rh, $employee, ['type' => 'cdi', 'start_date' => '2026-01-01'])->assertSessionHasNoErrors();
});

test('contracts of one employee cannot overlap', function () {
    $rh = contractUser(Role::Hr);
    $employee = contractUser();
    addContract($rh, $employee, ['type' => 'cdi', 'start_date' => '2024-01-01']);

    // The open-ended CDI must be given an end date first.
    addContract($rh, $employee, ['type' => 'cdd', 'start_date' => '2026-01-01', 'end_date' => '2026-06-30'])->assertSessionHasErrors('start_date');

    $cdi = $employee->contracts()->sole();
    $this->actingAs($rh)
        ->put("/admin/contracts/{$cdi->id}", ['type' => 'cdi', 'start_date' => '2024-01-01', 'end_date' => '2025-12-31'])
        ->assertSessionHasNoErrors();

    addContract($rh, $employee, ['type' => 'cdd', 'start_date' => '2026-01-01', 'end_date' => '2026-06-30'])->assertSessionHasNoErrors();
    expect($employee->contracts()->count())->toBe(2);
});

test('Paie, employees and managers cannot change contracts; nobody manages their own', function () {
    $rh = contractUser(Role::Hr);
    $manager = contractUser();
    $employee = contractUser();
    $employee->update(['manager_id' => $manager->id]);
    addContract($rh, $employee, ['type' => 'cdi', 'start_date' => '2024-01-01']);
    $contract = $employee->contracts()->sole();

    foreach ([contractUser(Role::Payroll), $manager, $employee] as $user) {
        addContract($user, $employee, ['type' => 'cdi', 'start_date' => '2030-01-01'])->assertForbidden();
        $this->actingAs($user)->put("/admin/contracts/{$contract->id}", ['type' => 'cdi', 'start_date' => '2024-01-01', 'end_date' => '2024-02-01'])->assertForbidden();
        $this->actingAs($user)->delete("/admin/contracts/{$contract->id}")->assertForbidden();
    }

    addContract($rh, $rh, ['type' => 'cdi', 'start_date' => '2024-01-01'])->assertForbidden();
    expect($contract->fresh()->end_date)->toBeNull();
});

test('RH deletes a contract entered by mistake', function () {
    $rh = contractUser(Role::Hr);
    $employee = contractUser();
    addContract($rh, $employee, ['type' => 'cdi', 'start_date' => '2024-01-01']);

    $this->actingAs($rh)->delete('/admin/contracts/'.$employee->contracts()->sole()->id)->assertRedirect();

    expect(EmploymentContract::count())->toBe(0);
});

test('CDD and trial periods ending within 30 days are flagged; a CDD followed by a CDI is not', function () {
    $this->travelTo('2026-06-01');
    $rh = contractUser(Role::Hr);

    $endingCdd = contractUser();
    $endingCdd->update(['name' => 'Amina']);
    addContract($rh, $endingCdd, ['type' => 'cdd', 'start_date' => '2025-07-01', 'end_date' => '2026-06-20']);

    $inTrial = contractUser();
    $inTrial->update(['name' => 'Bilal']);
    addContract($rh, $inTrial, ['type' => 'cdi', 'category' => 'cadre', 'start_date' => '2026-04-01', 'trial_end' => '2026-06-30']);

    $renewed = contractUser();
    addContract($rh, $renewed, ['type' => 'cdd', 'start_date' => '2025-06-01', 'end_date' => '2026-05-31']);
    addContract($rh, $renewed, ['type' => 'cdi', 'start_date' => '2026-06-01']);

    $ended = contractUser();
    $ended->update(['name' => 'Chama']);
    addContract($rh, $ended, ['type' => 'stage', 'start_date' => '2026-03-01', 'end_date' => '2026-05-15']);

    $this->actingAs(contractUser(Role::Payroll))
        ->get('/admin/overview')
        ->assertInertia(fn (Assert $page) => $page
            ->has('contractAlerts', 3)
            ->where('contractAlerts.0.name', 'Chama')
            ->where('contractAlerts.0.kind', 'ended')
            ->where('contractAlerts.1.name', 'Amina')
            ->where('contractAlerts.1.kind', 'contract_end')
            ->where('contractAlerts.2.name', 'Bilal')
            ->where('contractAlerts.2.kind', 'trial_end'));

    $this->actingAs($rh)
        ->get("/admin/users/{$inTrial->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->has('contracts', 1)
            ->where('contracts.0.in_trial', true)
            ->where('contracts.0.status', 'active')
            ->has('contractAlerts', 1));
});

test('the users list shows the contract in force; the employee reads their own contracts', function () {
    $this->travelTo('2026-06-01');
    $rh = contractUser(Role::Hr);
    $employee = contractUser();
    addContract($rh, $employee, ['type' => 'cdd', 'start_date' => '2025-06-01', 'end_date' => '2026-05-31']);
    addContract($rh, $employee, ['type' => 'cdi', 'start_date' => '2026-06-01']);

    $this->actingAs($rh)
        ->get('/admin/users')
        ->assertInertia(fn (Assert $page) => $page->where(
            'users',
            fn ($users) => collect($users)->firstWhere('id', $employee->id)['contract']['type'] === 'cdi'
                && ! array_key_exists('contracts', collect($users)->firstWhere('id', $employee->id)),
        ));

    $this->actingAs($employee)
        ->get('/settings/personal-data')
        ->assertInertia(fn (Assert $page) => $page->has('contracts', 2)->where('contracts.0.type', 'cdi'));
});
