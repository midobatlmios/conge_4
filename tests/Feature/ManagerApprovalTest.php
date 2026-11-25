<?php

use App\Enums\LeaveStatus;
use App\Models\Demande;
use App\Models\User;
use App\Notifications\DemandeStatusChanged;
use App\Notifications\LeaveRequestAwaitingDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-02-02 10:00:00');
    Notification::fake();
});

/** @return array{0: User, 1: User, 2: User} admin, manager, employee reporting to the manager */
function team(): array
{
    $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin']);
    $manager = User::factory()->create(['role' => 'user', 'name' => 'Manager']);
    $employee = User::factory()->create(['role' => 'user', 'name' => 'Sara', 'manager_id' => $manager->id]);

    return [$admin, $manager, $employee];
}

function pendingFor(User $employee, string $etat = 'en attente'): Demande
{
    return Demande::create([
        'user_id' => $employee->id,
        'date_demande' => '2026-02-01',
        'date_debut' => '2026-03-02',
        'date_fin' => '2026-03-03',
        'type_conge' => 'annuel',
        'etat' => $etat,
    ]);
}

test('a new request is sent to the employee\'s manager first', function () {
    [$admin, $manager, $employee] = team();

    $this->actingAs($employee)
        ->post('/demandes', ['date_debut' => '2026-03-02', 'date_fin' => '2026-03-03', 'type_conge' => 'annuel'])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($manager, LeaveRequestAwaitingDecision::class);
    Notification::assertNotSentTo($admin, LeaveRequestAwaitingDecision::class);
});

test('without a manager, a new request goes straight to HR', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = User::factory()->create(['role' => 'user']);

    $this->actingAs($employee)
        ->post('/demandes', ['date_debut' => '2026-03-02', 'date_fin' => '2026-03-03', 'type_conge' => 'annuel']);

    Notification::assertSentTo($admin, LeaveRequestAwaitingDecision::class);
});

test('the manager approves: HR is told and decides', function () {
    [$admin, $manager, $employee] = team();
    $demande = pendingFor($employee);

    $this->actingAs($manager)->put("/demandes/{$demande->id}/manager-approve")->assertSessionHasNoErrors();

    expect($demande->fresh()->etat)->toBe(LeaveStatus::ManagerApproved);
    Notification::assertSentTo($admin, LeaveRequestAwaitingDecision::class);
    Notification::assertNotSentTo($employee, DemandeStatusChanged::class);

    $this->actingAs($admin)
        ->put("/demandes/{$demande->id}", ['date_debut' => '2026-03-02', 'date_fin' => '2026-03-03', 'type_conge' => 'annuel', 'etat' => 'acceptée'])
        ->assertSessionHasNoErrors();

    expect($demande->fresh()->etat)->toBe(LeaveStatus::Accepted)
        ->and($demande->events()->pluck('action')->all())->toBe(['created', 'manager_approved', 'accepted']);
    Notification::assertSentTo($employee, DemandeStatusChanged::class);
});

test('the manager refuses: final, with the reason, and the employee is told', function () {
    [$admin, $manager, $employee] = team();
    $demande = pendingFor($employee);

    $this->actingAs($manager)
        ->put("/demandes/{$demande->id}/manager-refuse", ['manager_comment' => 'Clôture comptable'])
        ->assertSessionHasNoErrors();

    $demande->refresh();
    expect($demande->etat)->toBe(LeaveStatus::Refused)
        ->and($demande->manager_comment)->toBe('Clôture comptable')
        ->and($demande->events()->get()->last()->action)->toBe('manager_refused');

    Notification::assertSentTo($employee, DemandeStatusChanged::class,
        fn ($n) => $n->toMail($employee)->introLines[0] === 'Votre demande de congé a été refusée par votre responsable.');
    Notification::assertNotSentTo($admin, LeaveRequestAwaitingDecision::class);
});

test('only the employee\'s own manager can approve, and only while pending', function () {
    [$admin, $manager, $employee] = team();
    $someoneElse = User::factory()->create(['role' => 'user']);
    $demande = pendingFor($employee);

    $this->actingAs($someoneElse)->put("/demandes/{$demande->id}/manager-approve")->assertForbidden();
    $this->actingAs($employee)->put("/demandes/{$demande->id}/manager-approve")->assertForbidden();

    $approved = pendingFor($employee, 'validée manager');
    $this->actingAs($manager)->put("/demandes/{$approved->id}/manager-refuse")->assertForbidden();
});

test('HR can decide before the manager, and the history says so', function () {
    [$admin, $manager, $employee] = team();
    $demande = pendingFor($employee);

    $this->actingAs($admin)
        ->put("/demandes/{$demande->id}", ['date_debut' => '2026-03-02', 'date_fin' => '2026-03-03', 'type_conge' => 'annuel', 'etat' => 'acceptée'])
        ->assertSessionHasNoErrors();

    $event = $demande->events()->get()->last();
    expect($event->action)->toBe('accepted')
        ->and($event->changes['skipped_manager'])->toBeTrue();
});

test('a request approved by the manager is not booked yet', function () {
    [, , $employee] = team();
    pendingFor($employee, 'validée manager');

    expect(app(\App\Services\LeaveCalculator::class)->usedDays($employee->id, 2026))->toEqual(0);
});

test('the team page lists the requests waiting for the manager', function () {
    [, $manager, $employee] = team();
    pendingFor($employee);

    $this->actingAs($manager)
        ->get('/equipe')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Team')
            ->where('teamSize', 1)
            ->has('requests', 1)
            ->where('requests.0.can_decide', true));

    $this->actingAs($manager)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('auth.user.is_manager', true));
});
