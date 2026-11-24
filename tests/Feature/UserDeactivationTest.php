<?php

use App\Models\Demande;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('an admin can deactivate an employee and their leave history is kept', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = User::factory()->create(['role' => 'user']);
    Demande::create([
        'user_id' => $employee->id,
        'date_demande' => '2026-01-05',
        'date_debut' => '2026-01-12',
        'date_fin' => '2026-01-13',
        'type_conge' => 'annuel',
        'etat' => 'acceptée',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.deactivate', $employee))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($employee->fresh()->isActive())->toBeFalse()
        ->and($employee->demandes()->count())->toBe(1);
});

test('an admin can reactivate an employee', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = User::factory()->deactivated()->create();

    $this->actingAs($admin)->put(route('admin.users.reactivate', $employee))->assertRedirect();

    expect($employee->fresh()->isActive())->toBeTrue();
});

test('an employee cannot deactivate accounts', function () {
    $employee = User::factory()->create(['role' => 'user']);
    $colleague = User::factory()->create();

    $this->actingAs($employee)->put(route('admin.users.deactivate', $colleague))->assertForbidden();

    expect($colleague->fresh()->isActive())->toBeTrue();
});

test('an admin cannot deactivate their own account', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->put(route('admin.users.deactivate', $admin))
        ->assertSessionHasErrors('user');

    expect($admin->fresh()->isActive())->toBeTrue();
});

test('the last active admin cannot be deactivated', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $otherAdmin = User::factory()->create(['role' => 'admin']);

    // Another admin deactivates the second one: allowed, one active admin is left.
    $this->actingAs($admin)->put(route('admin.users.deactivate', $otherAdmin))->assertSessionHasNoErrors();

    // A deactivated admin doesn't count, so $admin is now the last one.
    expect($admin->isLastAdmin())->toBeTrue();
});

test('the last active admin cannot be demoted when the other admin is deactivated', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->deactivated()->create(['role' => 'admin']);
    $target = $admin;

    $this->actingAs($admin)
        ->put(route('admin.users.update', $target), ['name' => $target->name, 'email' => $target->email, 'role' => 'user'])
        ->assertSessionHasErrors('role');
});

test('a deactivated employee cannot log in, even with the right password', function () {
    $employee = User::factory()->deactivated()->create();

    $this->post('/login', ['email' => $employee->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Ce compte a été désactivé. Contactez votre administrateur.']);

    $this->assertGuest();
});

test('a wrong password on a deactivated account shows the normal error', function () {
    $employee = User::factory()->deactivated()->create();

    $this->post('/login', ['email' => $employee->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);
});

test('an open session is signed out as soon as the account is deactivated', function () {
    $employee = User::factory()->create(['role' => 'user']);
    $this->actingAs($employee)->get('/dashboard')->assertOk();

    $employee->forceFill(['deactivated_at' => now()])->save();

    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a deactivated employee gets no password reset link', function () {
    Notification::fake();
    $employee = User::factory()->deactivated()->create();

    $this->post('/forgot-password', ['email' => $employee->email])->assertSessionHas('status');

    Notification::assertNothingSent();
});

test('deactivated employees are not offered when an admin files a request', function () {
    $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin']);
    User::factory()->create(['name' => 'Active']);
    $gone = User::factory()->deactivated()->create(['name' => 'Gone']);

    $this->actingAs($admin)->get('/demandes')->assertInertia(
        fn (Assert $page) => $page->where('users', fn ($users) => collect($users)->pluck('name')->sort()->values()->all() === ['Active', 'Admin'])
    );

    $this->travelTo('2026-02-02 10:00:00');
    $this->actingAs($admin)->postJson('/demandes', [
        'user_id' => $gone->id,
        'date_debut' => '2026-03-02',
        'date_fin' => '2026-03-03',
        'type_conge' => 'annuel',
    ])->assertStatus(422)->assertJsonValidationErrors('user_id');
});
