<?php

use App\Models\Demande;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

// Wednesday 4 March 2026.
beforeEach(fn () => $this->travelTo('2026-03-04 10:00:00'));

function overviewLeave(User $user, string $start, string $end, string $etat = 'acceptée', string $type = 'annuel'): Demande
{
    return Demande::create([
        'user_id' => $user->id,
        'date_demande' => '2026-02-01',
        'date_debut' => $start,
        'date_fin' => $end,
        'type_conge' => $type,
        'etat' => $etat,
    ]);
}

function overviewAdmin(): User
{
    return User::factory()->create(['role' => 'admin', 'name' => 'Admin']);
}

test('it shows who is off today and who leaves in the next 7 days', function () {
    $admin = overviewAdmin();
    overviewLeave(User::factory()->create(['name' => 'Sara']), '2026-03-02', '2026-03-05');
    overviewLeave(User::factory()->create(['name' => 'Omar']), '2026-03-09', '2026-03-10');
    overviewLeave(User::factory()->create(['name' => 'Later']), '2026-03-20', '2026-03-20');
    overviewLeave(User::factory()->create(['name' => 'Pending']), '2026-03-04', '2026-03-04', 'en attente');
    overviewLeave(User::factory()->deactivated()->create(['name' => 'Gone']), '2026-03-04', '2026-03-04');

    $this->actingAs($admin)
        ->get('/admin/overview')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Overview')
            ->where('counts.offToday', 1)
            ->where('offToday.0.name', 'Sara')
            ->has('upcoming', 1)
            ->where('upcoming.0.name', 'Omar'));
});

test('it counts requests waiting for a decision and incomplete records', function () {
    $admin = overviewAdmin();
    $sara = User::factory()->create(['name' => 'Sara', 'hire_date' => null]);
    overviewLeave($sara, '2026-04-06', '2026-04-07', 'en attente');
    overviewLeave($sara, '2026-05-04', '2026-05-05', 'annulation demandée');
    overviewLeave($sara, '2026-06-01', '2026-06-02', 'refusée');

    $this->actingAs($admin)
        ->get('/admin/overview')
        ->assertInertia(fn (Assert $page) => $page
            ->where('counts.pending', 1)
            ->where('counts.cancellations', 1)
            ->where('counts.missingHireDate', 1)
            ->has('waiting', 2)
            ->where('waiting.0.status', 'en attente')); // closest first
});

test('it sums the leave taken this year per department', function () {
    $admin = overviewAdmin();
    $it = Department::factory()->create(['name' => 'IT']);
    $amal = User::factory()->create(['department_id' => $it->id]);
    $badr = User::factory()->create(['department_id' => $it->id]);
    overviewLeave($amal, '2026-02-02', '2026-02-04'); // 3 days
    overviewLeave($badr, '2026-02-09', '2026-02-09'); // 1 day
    overviewLeave($badr, '2026-02-16', '2026-02-17', 'acceptée', 'sans_solde'); // 2 unpaid

    $this->actingAs($admin)
        ->get('/admin/overview')
        ->assertInertia(fn (Assert $page) => $page
            ->where('departments.0', [
                'name' => 'IT',
                'employees' => 2,
                'usedDays' => 4,
                'averageDays' => 2,
                'unpaidDays' => 2,
            ])
            ->where('departments.1.name', null)); // the admin, without a department
});

test('employees cannot see the overview', function () {
    $this->actingAs(User::factory()->create(['role' => 'user']))
        ->get('/admin/overview')
        ->assertForbidden();
});
