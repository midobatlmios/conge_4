<?php

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function recordAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

/** @return array<string, mixed> */
function editPayload(User $user, array $overrides = []): array
{
    return [
        'name' => $user->name,
        'email' => $user->email,
        'role' => 'user',
        'hire_date' => '2021-09-01',
        ...$overrides,
    ];
}

test('an admin creates an employee with a full record', function () {
    $department = Department::factory()->create(['name' => 'Comptabilité']);
    $manager = User::factory()->create();

    $this->actingAs(recordAdmin())
        ->post('/admin/users', [
            'name' => 'Sara',
            'email' => 'sara@example.com',
            'password' => 'password123',
            'role' => 'user',
            'matricule' => 'EMP-001',
            'department_id' => $department->id,
            'job_title' => 'Comptable',
            'hire_date' => '2022-03-14',
            'manager_id' => $manager->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $sara = User::where('email', 'sara@example.com')->first();
    expect($sara->matricule)->toBe('EMP-001')
        ->and($sara->department->name)->toBe('Comptabilité')
        ->and($sara->job_title)->toBe('Comptable')
        ->and($sara->hire_date->toDateString())->toBe('2022-03-14')
        ->and($sara->manager->is($manager))->toBeTrue();
});

test('the hire date is required, the rest is optional', function () {
    $admin = recordAdmin();

    $this->actingAs($admin)
        ->post('/admin/users', ['name' => 'A', 'email' => 'a@example.com', 'password' => 'password123', 'role' => 'user'])
        ->assertSessionHasErrors('hire_date');

    $this->actingAs($admin)
        ->post('/admin/users', ['name' => 'B', 'email' => 'b@example.com', 'password' => 'password123', 'role' => 'user', 'hire_date' => '2024-01-02'])
        ->assertSessionHasNoErrors();
});

test('matricules are unique', function () {
    User::factory()->create(['matricule' => 'EMP-001']);
    $other = User::factory()->create();

    $this->actingAs(recordAdmin())
        ->put("/admin/users/{$other->id}", editPayload($other, ['matricule' => 'EMP-001']))
        ->assertSessionHasErrors('matricule');
});

test('an employee cannot be their own manager, nor managed by someone they manage', function () {
    $boss = User::factory()->create();
    $report = User::factory()->create(['manager_id' => $boss->id]);
    $admin = recordAdmin();

    $this->actingAs($admin)
        ->put("/admin/users/{$boss->id}", editPayload($boss, ['manager_id' => $boss->id]))
        ->assertSessionHasErrors('manager_id');

    $this->actingAs($admin)
        ->put("/admin/users/{$boss->id}", editPayload($boss, ['manager_id' => $report->id]))
        ->assertSessionHasErrors('manager_id');

    expect($boss->fresh()->manager_id)->toBeNull();
});

test('a deactivated employee cannot be picked as a new manager, but an existing one is kept', function () {
    $gone = User::factory()->deactivated()->create();
    $employee = User::factory()->create();
    $managed = User::factory()->create(['manager_id' => $gone->id]);
    $admin = recordAdmin();

    $this->actingAs($admin)
        ->put("/admin/users/{$employee->id}", editPayload($employee, ['manager_id' => $gone->id]))
        ->assertSessionHasErrors('manager_id');

    $this->actingAs($admin)
        ->put("/admin/users/{$managed->id}", editPayload($managed, ['manager_id' => $gone->id, 'job_title' => 'Technicien']))
        ->assertSessionHasNoErrors();

    expect($managed->fresh()->job_title)->toBe('Technicien');
});

test('the users page lists departments and active managers', function () {
    Department::factory()->count(2)->create();
    User::factory()->deactivated()->create();

    $this->actingAs(recordAdmin())
        ->get('/admin/users')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users')
            ->has('departments', 2)
            ->has('managers', 1) // only the active admin
        );
});

test('an admin manages departments', function () {
    $admin = recordAdmin();

    $this->actingAs($admin)->post('/admin/departments', ['name' => 'RH'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post('/admin/departments', ['name' => 'RH'])->assertSessionHasErrors('name');

    $department = Department::firstWhere('name', 'RH');
    $this->actingAs($admin)->put("/admin/departments/{$department->id}", ['name' => 'Ressources humaines'])->assertSessionHasNoErrors();
    expect($department->fresh()->name)->toBe('Ressources humaines');

    $this->actingAs($admin)
        ->get('/admin/departments')
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Departments')->where('departments.0.users_count', 0));

    $this->actingAs($admin)->delete("/admin/departments/{$department->id}")->assertSessionHasNoErrors();
    expect(Department::count())->toBe(0);
});

test('a department with employees cannot be deleted', function () {
    $department = Department::factory()->create();
    User::factory()->create(['department_id' => $department->id]);

    $this->actingAs(recordAdmin())
        ->delete("/admin/departments/{$department->id}")
        ->assertSessionHasErrors('department');

    expect($department->fresh())->not->toBeNull();
});

test('employees cannot manage departments', function () {
    $employee = User::factory()->create(['role' => 'user']);

    $this->actingAs($employee)->get('/admin/departments')->assertForbidden();
    $this->actingAs($employee)->post('/admin/departments', ['name' => 'X'])->assertForbidden();
});
