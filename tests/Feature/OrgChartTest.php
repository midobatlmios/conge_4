<?php

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('every employee sees the org chart, built from the manager links', function () {
    $boss = User::factory()->create(['name' => 'Boss', 'job_title' => 'Directeur']);
    $lead = User::factory()->create(['name' => 'Lead', 'manager_id' => $boss->id]);
    $member = User::factory()->create(['name' => 'Member', 'manager_id' => $lead->id]);

    $this->actingAs($member)
        ->get('/organigramme')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('OrgChart')
            ->has('people', 3)
            ->where('people', fn ($people) => collect($people)->firstWhere('id', $member->id)['manager_id'] === $lead->id
                && collect($people)->firstWhere('id', $boss->id)['manager_id'] === null
                && collect($people)->firstWhere('id', $boss->id)['job_title'] === 'Directeur'));
});

test('only names, job titles and departments are shared, never personal data', function () {
    $employee = User::factory()->create();
    EmployeeProfile::factory()->for($employee)->create(['cin' => 'AB999999']);

    $this->actingAs(User::factory()->create())
        ->get('/organigramme')
        ->assertDontSee('AB999999')
        ->assertDontSee($employee->email)
        ->assertInertia(fn (Assert $page) => $page->has('people.0', fn (Assert $person) => $person
            ->hasAll(['id', 'name', 'job_title', 'department', 'manager_id'])));
});

test('people who left are not shown; their team moves to the top', function () {
    $gone = User::factory()->create(['deactivated_at' => now()]);
    $orphan = User::factory()->create(['manager_id' => $gone->id]);

    $this->actingAs($orphan)
        ->get('/organigramme')
        ->assertInertia(fn (Assert $page) => $page
            ->has('people', 1)
            ->where('people.0.id', $orphan->id)
            ->where('people.0.manager_id', null));
});

test('guests are sent to the sign-in page', function () {
    $this->get('/organigramme')->assertRedirect('/login');
});
