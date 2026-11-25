<?php

use App\Models\Demande;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->travelTo('2026-02-02 10:00:00'));

function calendarLeave(User $user, string $start, string $end, string $etat = 'acceptée', string $type = 'annuel'): Demande
{
    return Demande::create([
        'user_id' => $user->id,
        'date_demande' => '2026-01-15',
        'date_debut' => $start,
        'date_fin' => $end,
        'type_conge' => $type,
        'etat' => $etat,
    ]);
}

test('an admin sees everyone, with pending leave and the kind of leave', function () {
    $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin']);
    $sara = User::factory()->create(['name' => 'Sara']);
    calendarLeave($sara, '2026-03-02', '2026-03-03', 'en attente', 'mariage');
    calendarLeave($sara, '2026-03-10', '2026-03-10', 'refusée');

    $this->actingAs($admin)
        ->get('/calendrier?month=2026-03')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Calendar')
            ->where('month', '2026-03')
            ->has('people', 2)
            ->where('people.1.name', 'Sara')
            ->has('people.1.leaves', 1) // the refused one is not shown
            ->where('people.1.leaves.0.pending', true)
            ->where('people.1.leaves.0.type', 'mariage'));
});

test('an admin can show one department', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $it = Department::factory()->create();
    User::factory()->create(['department_id' => $it->id, 'name' => 'Omar']);
    User::factory()->create(['name' => 'Sara']);

    $this->actingAs($admin)
        ->get("/calendrier?department={$it->id}")
        ->assertInertia(fn (Assert $page) => $page->has('people', 1)->where('people.0.name', 'Omar'));
});

test('an employee sees their own department, without the kind of leave of others', function () {
    $sales = Department::factory()->create(['name' => 'Ventes']);
    $me = User::factory()->create(['department_id' => $sales->id, 'name' => 'Amal']);
    $colleague = User::factory()->create(['department_id' => $sales->id, 'name' => 'Badr']);
    User::factory()->create(['name' => 'Outsider']);
    calendarLeave($me, '2026-02-16', '2026-02-17', 'acceptée', 'annuel');
    calendarLeave($colleague, '2026-02-18', '2026-02-18', 'acceptée', 'deces');

    $this->actingAs($me)
        ->get('/calendrier')
        ->assertInertia(fn (Assert $page) => $page
            ->where('month', '2026-02')
            ->where('departmentName', 'Ventes')
            ->has('departments', 0)
            ->has('people', 2)
            ->where('people.0.leaves.0.type', 'annuel') // their own
            ->where('people.1.leaves.0.type', null)); // a colleague's stays private
});

test('an employee cannot look at another department', function () {
    $sales = Department::factory()->create();
    $other = Department::factory()->create();
    $me = User::factory()->create(['department_id' => $sales->id]);
    User::factory()->create(['department_id' => $other->id]);

    $this->actingAs($me)
        ->get("/calendrier?department={$other->id}")
        ->assertInertia(fn (Assert $page) => $page->has('people', 1)->where('people.0.id', $me->id));
});

test('an employee without a department only sees themself', function () {
    $me = User::factory()->create();
    User::factory()->create();

    $this->actingAs($me)
        ->get('/calendrier')
        ->assertInertia(fn (Assert $page) => $page->has('people', 1)->where('people.0.id', $me->id));
});

test('only leave overlapping the month is sent, and deactivated employees are hidden', function () {
    $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin']);
    $sara = User::factory()->create(['name' => 'Sara']);
    User::factory()->deactivated()->create();
    calendarLeave($sara, '2026-02-26', '2026-03-03'); // overlaps March
    calendarLeave($sara, '2026-04-06', '2026-04-07'); // April

    $this->actingAs($admin)
        ->get('/calendrier?month=2026-03')
        ->assertInertia(fn (Assert $page) => $page->has('people', 2)->has('people.1.leaves', 1));
});

test('a malformed month falls back to the current one', function () {
    $this->actingAs(User::factory()->create())
        ->get('/calendrier?month=2026-13')
        ->assertInertia(fn (Assert $page) => $page->where('month', '2026-02'));
});
