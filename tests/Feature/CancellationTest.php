<?php

use App\Enums\LeaveStatus;
use App\Models\Demande;
use App\Models\User;
use App\Notifications\DemandeStatusChanged;
use App\Services\LeaveCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// Monday 2 February 2026.
beforeEach(fn () => $this->travelTo('2026-02-02 10:00:00'));

function acceptedFor(User $user, string $start = '2026-03-02', string $end = '2026-03-03', string $etat = 'acceptée'): Demande
{
    return Demande::create([
        'user_id' => $user->id,
        'date_demande' => '2026-01-15',
        'date_debut' => $start,
        'date_fin' => $end,
        'type_conge' => 'annuel',
        'etat' => $etat,
    ]);
}

test('an employee asks to cancel an accepted leave; its days stay booked meanwhile', function () {
    $employee = User::factory()->create();
    $leave = acceptedFor($employee);

    $this->actingAs($employee)
        ->post("/demandes/{$leave->id}/cancellation")
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($leave->fresh()->etat)->toBe(LeaveStatus::CancellationRequested)
        ->and(app(LeaveCalculator::class)->usedDays($employee->id, 2026))->toEqual(2);
});

test('cancellation can only be asked for an accepted leave that has not started, by its owner', function () {
    $employee = User::factory()->create();

    $started = acceptedFor($employee, '2026-01-26', '2026-02-03');
    $this->actingAs($employee)->post("/demandes/{$started->id}/cancellation")->assertForbidden();

    $pending = acceptedFor($employee, '2026-04-06', '2026-04-07', 'en attente');
    $this->actingAs($employee)->post("/demandes/{$pending->id}/cancellation")->assertForbidden();

    $someoneElses = acceptedFor(User::factory()->create(), '2026-05-04', '2026-05-05');
    $this->actingAs($employee)->post("/demandes/{$someoneElses->id}/cancellation")->assertForbidden();
});

test('an admin approves the cancellation: the days are returned and the employee told', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = User::factory()->create();
    $leave = acceptedFor($employee, etat: 'annulation demandée');

    $this->actingAs($admin)->put("/demandes/{$leave->id}/cancel")->assertSessionHasNoErrors();

    expect($leave->fresh()->etat)->toBe(LeaveStatus::Cancelled)
        ->and(app(LeaveCalculator::class)->usedDays($employee->id, 2026))->toEqual(0);

    Notification::assertSentTo($employee, DemandeStatusChanged::class,
        fn ($notification) => $notification->toMail($employee)->subject === 'Votre congé a été annulé');
});

test('an admin keeps the leave: it stays accepted and the employee is told', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = User::factory()->create();
    $leave = acceptedFor($employee, etat: 'annulation demandée');

    $this->actingAs($admin)->put("/demandes/{$leave->id}/keep")->assertSessionHasNoErrors();

    expect($leave->fresh()->etat)->toBe(LeaveStatus::Accepted);
    Notification::assertSentTo($employee, DemandeStatusChanged::class,
        fn ($notification) => $notification->toMail($employee)->subject === "Votre demande d'annulation a été refusée");
});

test('an admin can cancel an accepted leave directly, but not a pending one', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $accepted = acceptedFor(User::factory()->create());
    $pending = acceptedFor(User::factory()->create(), etat: 'en attente');

    $this->actingAs($admin)->put("/demandes/{$accepted->id}/cancel")->assertSessionHasNoErrors();
    $this->actingAs($admin)->put("/demandes/{$pending->id}/cancel")->assertForbidden();
    $this->actingAs($admin)->put("/demandes/{$accepted->id}/keep")->assertForbidden(); // nothing to keep

    expect($accepted->fresh()->etat)->toBe(LeaveStatus::Cancelled);
});

test('employees cannot approve their own cancellation', function () {
    $employee = User::factory()->create();
    $leave = acceptedFor($employee, etat: 'annulation demandée');

    $this->actingAs($employee)->put("/demandes/{$leave->id}/cancel")->assertForbidden();
});

test('a cancelled leave frees its dates for a new request', function () {
    $employee = User::factory()->create();
    acceptedFor($employee, etat: 'annulée');

    $this->actingAs($employee)
        ->post('/demandes', ['date_debut' => '2026-03-02', 'date_fin' => '2026-03-03', 'type_conge' => 'annuel'])
        ->assertSessionHasNoErrors();
});

test('the edit form cannot set the cancellation statuses, nor change a leave being cancelled', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $accepted = acceptedFor(User::factory()->create());
    $cancelling = acceptedFor(User::factory()->create(), etat: 'annulation demandée');
    $payload = ['date_debut' => '2026-03-02', 'date_fin' => '2026-03-03', 'type_conge' => 'annuel'];

    $this->actingAs($admin)->put("/demandes/{$accepted->id}", [...$payload, 'etat' => 'annulée'])->assertSessionHasErrors('etat');
    $this->actingAs($admin)->put("/demandes/{$cancelling->id}", [...$payload, 'etat' => 'acceptée'])->assertForbidden();
});

test('the history records the cancellation steps', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = User::factory()->create();
    $leave = acceptedFor($employee);

    $this->actingAs($employee)->post("/demandes/{$leave->id}/cancellation");
    $this->actingAs($admin)->put("/demandes/{$leave->id}/keep");
    $this->actingAs($employee)->post("/demandes/{$leave->id}/cancellation");
    $this->actingAs($admin)->put("/demandes/{$leave->id}/cancel");

    expect($leave->events()->pluck('action')->all())
        ->toBe(['created', 'cancellation_requested', 'cancellation_refused', 'cancellation_requested', 'cancelled']);
});
