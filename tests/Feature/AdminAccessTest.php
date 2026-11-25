<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('regular users get a 403 on admin pages', function () {
    $this->actingAs(User::factory()->create(['role' => 'user']))
        ->get('/admin/users')
        ->assertForbidden();
});

test('admins can open the user management page', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get('/admin/users')
        ->assertOk();
});

test('the seeder creates verified dev accounts and can run twice', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(4);

    $admin = User::where('email', 'admin@example.com')->sole();
    expect($admin->role)->toBe(Role::Admin)
        ->and($admin->hasVerifiedEmail())->toBeTrue();
});

test('an admin cannot remove their own admin role', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->put("/admin/users/{$admin->id}", ['name' => $admin->name, 'email' => $admin->email, 'role' => 'user'])
        ->assertSessionHasErrors('role');

    expect($admin->fresh()->role)->toBe(Role::Admin);
});

test('an admin can demote another admin while one remains', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $other = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->put("/admin/users/{$other->id}", ['name' => $other->name, 'email' => $other->email, 'role' => 'user', 'hire_date' => '2020-01-06'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($other->fresh()->role)->toBe(Role::User);
});

test('the last admin cannot delete their account', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->from('/settings/profile')
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertSessionHasErrors('password');

    expect($admin->fresh())->not->toBeNull();
});

test('an admin can delete their account when another admin exists', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertRedirect('/');

    expect($admin->fresh())->toBeNull();
});

test('an admin can reset an employee password', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = User::factory()->create();

    $this->actingAs($admin)
        ->put("/admin/users/{$employee->id}/reset-password", [
            'password' => 'nouveau-mdp-123',
            'password_confirmation' => 'nouveau-mdp-123',
        ])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect(Hash::check('nouveau-mdp-123', $employee->fresh()->password))->toBeTrue();
});

test('saving a user from the admin page redirects back instead of returning JSON', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = User::factory()->create();

    $this->actingAs($admin)
        ->from('/admin/users')
        ->put("/admin/users/{$employee->id}", ['name' => 'Nouveau Nom', 'email' => $employee->email, 'role' => 'user', 'hire_date' => '2020-01-06'])
        ->assertRedirect('/admin/users');

    expect($employee->fresh()->name)->toBe('Nouveau Nom');
});
