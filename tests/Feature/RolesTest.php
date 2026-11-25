<?php

use App\Enums\Role;
use App\Models\Demande;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Notifications\LeaveRequestAwaitingDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function withRole(Role $role, array $attributes = []): User
{
    return User::factory()->create(['role' => $role, 'hire_date' => '2020-01-01', ...$attributes]);
}

/** @return array<string, mixed> */
function newAccount(string $role): array
{
    return [
        'name' => 'Nouveau',
        'email' => "nouveau-{$role}@example.com",
        'password' => 'password123',
        'role' => $role,
        'hire_date' => '2024-01-02',
    ];
}

test('Admin, RH and Paie read the HR pages; employees do not', function () {
    $employee = withRole(Role::User);

    foreach ([Role::Admin, Role::Hr, Role::Payroll] as $role) {
        $user = withRole($role);
        foreach (['/admin/overview', '/admin/users', "/admin/users/{$employee->id}", '/admin/payroll'] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }

    foreach (['/admin/overview', '/admin/users', '/admin/payroll'] as $url) {
        $this->actingAs($employee)->get($url)->assertForbidden();
    }
});

test('Paie is read-only: no account, holiday, department or personal data changes', function () {
    $paie = withRole(Role::Payroll);
    $employee = withRole(Role::User);

    $this->actingAs($paie)->post('/admin/users', newAccount('user'))->assertForbidden();
    $this->actingAs($paie)->put("/admin/users/{$employee->id}/deactivate")->assertForbidden();
    $this->actingAs($paie)->put("/admin/users/{$employee->id}/personal-data", ['city' => 'Rabat'])->assertForbidden();
    $this->actingAs($paie)->get('/admin/holidays')->assertForbidden();
    $this->actingAs($paie)->get('/admin/departments')->assertForbidden();

    expect($employee->fresh()->isActive())->toBeTrue()
        ->and($employee->profile)->toBeNull();
});

test('Paie sees every leave request but cannot decide on one', function () {
    $paie = withRole(Role::Payroll);
    $demande = createDemandeForTest(withRole(Role::User));

    $this->actingAs($paie)
        ->get('/demandes')
        ->assertInertia(fn (Assert $page) => $page->has('demandes', 1)->where('users', []));

    $this->actingAs($paie)->get("/demandes/{$demande->id}/history")->assertOk();
    $this->actingAs($paie)->delete("/demandes/{$demande->id}")->assertForbidden();
    expect($demande->fresh())->not->toBeNull();
});

test('RH decides on leave like an admin', function () {
    $rh = withRole(Role::Hr);

    $this->actingAs($rh)
        ->get('/demandes')
        ->assertInertia(fn (Assert $page) => $page->where('users', fn ($users) => count($users) > 0));

    $demande = createDemandeForTest(withRole(Role::User));
    $this->actingAs($rh)->delete("/demandes/{$demande->id}")->assertRedirect();
    expect(Demande::find($demande->id))->toBeNull();
});

test('RH creates plain employees only; only an Admin gives roles', function () {
    $rh = withRole(Role::Hr);

    $this->actingAs($rh)->post('/admin/users', newAccount('user'))->assertSessionHasNoErrors();

    foreach (['admin', 'rh', 'paie'] as $role) {
        $this->actingAs($rh)->post('/admin/users', newAccount($role))->assertSessionHasErrors('role');
        $this->actingAs(withRole(Role::Admin))->post('/admin/users', newAccount($role))->assertSessionHasNoErrors();
    }

    expect(User::where('email', 'nouveau-rh@example.com')->first()->role)->toBe(Role::Hr);
});

test('RH cannot promote an employee nor touch Admin, RH or Paie accounts', function () {
    $rh = withRole(Role::Hr);
    $employee = withRole(Role::User);
    $admin = withRole(Role::Admin);
    $payload = fn (User $user, string $role) => ['name' => $user->name, 'email' => $user->email, 'role' => $role, 'hire_date' => '2020-01-01'];

    $this->actingAs($rh)->put("/admin/users/{$employee->id}", $payload($employee, 'rh'))->assertSessionHasErrors('role');
    $this->actingAs($rh)->put("/admin/users/{$employee->id}", $payload($employee, 'user'))->assertSessionHasNoErrors();

    foreach ([$admin, withRole(Role::Hr), withRole(Role::Payroll)] as $staff) {
        $this->actingAs($rh)->put("/admin/users/{$staff->id}", $payload($staff, $staff->role->value))->assertForbidden();
        $this->actingAs($rh)->put("/admin/users/{$staff->id}/reset-password", ['password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertForbidden();
        $this->actingAs($rh)->put("/admin/users/{$staff->id}/deactivate")->assertForbidden();
    }

    expect($admin->fresh()->isActive())->toBeTrue();
});

test('an Admin can turn an employee into RH', function () {
    $employee = withRole(Role::User);

    $this->actingAs(withRole(Role::Admin))
        ->put("/admin/users/{$employee->id}", ['name' => $employee->name, 'email' => $employee->email, 'role' => 'rh', 'hire_date' => '2020-01-01'])
        ->assertSessionHasNoErrors();

    expect($employee->fresh()->role)->toBe(Role::Hr);
});

test('nobody edits their own personal data, but RH edits a colleague\'s', function () {
    $rh = withRole(Role::Hr);
    $otherRh = withRole(Role::Hr);

    $this->actingAs($rh)->put("/admin/users/{$rh->id}/personal-data", ['city' => 'Rabat'])->assertForbidden();
    $this->actingAs($otherRh)->put("/admin/users/{$rh->id}/personal-data", ['city' => 'Rabat'])->assertSessionHasNoErrors();

    expect($rh->profile->city)->toBe('Rabat');
});

test('Paie reads personal data on the employee page', function () {
    $employee = withRole(Role::User);
    EmployeeProfile::factory()->for($employee)->create(['cin' => 'AB444444']);

    $this->actingAs(withRole(Role::Payroll))
        ->get("/admin/users/{$employee->id}")
        ->assertInertia(fn (Assert $page) => $page->where('profile.cin', 'AB444444')->where('roles', []));
});

test('permissions are shared with every page', function () {
    $this->actingAs(withRole(Role::Payroll))
        ->get('/calendrier')
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.can', ['view_hr' => true, 'manage_hr' => false, 'manage_roles' => false]));

    $this->actingAs(withRole(Role::Hr))
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.can', ['view_hr' => true, 'manage_hr' => true, 'manage_roles' => false]));
});

test('RH is told about requests waiting for a decision; Paie is not', function () {
    Notification::fake();
    $rh = withRole(Role::Hr);
    $paie = withRole(Role::Payroll);
    $employee = withRole(Role::User);

    $this->actingAs($employee)->post('/demandes', [
        'date_debut' => now()->addWeek()->startOfWeek()->toDateString(),
        'date_fin' => now()->addWeek()->startOfWeek()->toDateString(),
        'type_conge' => 'sans_solde',
    ])->assertSessionHasNoErrors();

    Notification::assertSentTo($rh, LeaveRequestAwaitingDecision::class);
    Notification::assertNotSentTo($paie, LeaveRequestAwaitingDecision::class);
});
