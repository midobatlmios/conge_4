<?php

use App\Enums\Role;
use App\Models\Demande;
use App\Models\Holiday;
use App\Models\Site;
use App\Models\User;
use App\Services\LeaveCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a local holiday counts only for the employees of its site', function () {
    $tanger = Site::create(['name' => 'Entrepôt Tanger', 'city' => 'Tanger']);
    $there = User::factory()->create(['site_id' => $tanger->id]);
    $headOffice = User::factory()->create();
    // Wednesday 10 June 2026: a local holiday in Tanger only.
    Holiday::create(['name' => 'Fête locale', 'date' => '2026-06-10', 'recurring' => false, 'site_id' => $tanger->id]);

    $calculator = app(LeaveCalculator::class);
    expect($calculator->forUser($there)->workingDays('2026-06-08', '2026-06-12'))->toBe(4)
        ->and($calculator->forUser($headOffice)->workingDays('2026-06-08', '2026-06-12'))->toBe(5)
        ->and($calculator->workingDays('2026-06-08', '2026-06-12'))->toBe(5);

    // A leave request counts the days of its employee's site.
    $leave = Demande::create(['user_id' => $there->id, 'type_conge' => 'annuel', 'date_debut' => '2026-06-08', 'date_fin' => '2026-06-12', 'date_demande' => '2026-05-01', 'etat' => 'acceptée']);
    expect($leave->nbr_jours)->toEqual(4);
});

test('RH manages sites; a site with employees cannot be deleted', function () {
    $rh = User::factory()->create(['role' => Role::Hr]);

    $this->actingAs($rh)->post('/admin/sites', ['name' => 'Siège', 'city' => 'Casablanca'])->assertSessionHasNoErrors();
    $site = Site::sole();
    $this->actingAs($rh)->post('/admin/sites', ['name' => 'Siège'])->assertSessionHasErrors('name');

    User::factory()->create(['site_id' => $site->id]);
    $this->actingAs($rh)->delete("/admin/sites/{$site->id}")->assertSessionHasErrors('site');
    expect(Site::count())->toBe(1);

    $this->actingAs(User::factory()->create(['role' => Role::User]))->get('/admin/sites')->assertForbidden();
});

test('the employee form saves the site, and HR pages filter by site', function () {
    $rh = User::factory()->create(['role' => Role::Hr]);
    $site = Site::create(['name' => 'Siège']);
    $employee = User::factory()->create(['role' => Role::User, 'hire_date' => '2020-01-01']);

    $this->actingAs($rh)->put("/admin/users/{$employee->id}", [
        'name' => $employee->name, 'email' => $employee->email, 'role' => 'user', 'hire_date' => '2020-01-01', 'site_id' => $site->id,
    ])->assertSessionHasNoErrors();
    expect($employee->fresh()->site_id)->toBe($site->id);

    $this->actingAs($rh)->get("/admin/indicators?site={$site->id}")->assertInertia(fn (Assert $page) => $page->where('siteId', $site->id)->has('siteOptions', 1));
});
