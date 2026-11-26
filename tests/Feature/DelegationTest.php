<?php

use App\Enums\LeaveStatus;
use App\Enums\Role;
use App\Models\ApprovalDelegation;
use App\Models\Demande;
use App\Models\User;
use App\Notifications\DelegationGiven;
use App\Notifications\LeaveRequestAwaitingDecision;
use App\Notifications\ManagerPendingReminder;
use App\Services\Reminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-06-10 09:00'); // a Wednesday
    $this->manager = User::factory()->create(['name' => 'Meryem']);
    $this->employee = User::factory()->create(['name' => 'Elias', 'manager_id' => $this->manager->id]);
    $this->delegate = User::factory()->create(['name' => 'Karim']);
});

function delegate(User $manager, User $delegate, string $from = '2026-06-10', string $to = '2026-06-20'): ApprovalDelegation
{
    return $manager->delegationsGiven()->create(['delegate_id' => $delegate->id, 'starts_on' => $from, 'ends_on' => $to]);
}

function pendingLeave(User $employee): Demande
{
    return Demande::create(['user_id' => $employee->id, 'type_conge' => 'annuel', 'date_debut' => '2026-07-06', 'date_fin' => '2026-07-07', 'date_demande' => '2026-06-10', 'etat' => 'en attente']);
}

test('a manager delegates their approvals; the delegate is told', function () {
    $this->actingAs($this->manager)
        ->post('/equipe/delegations', ['delegate_id' => $this->delegate->id, 'starts_on' => '2026-06-15', 'ends_on' => '2026-06-26'])
        ->assertSessionHasNoErrors();

    expect($this->manager->delegationsGiven()->sole()->delegate->is($this->delegate))->toBeTrue();
    Notification::assertSentTo($this->delegate, DelegationGiven::class);
});

test('a delegation needs an active delegate other than the manager, future dates and no overlap', function () {
    $gone = User::factory()->create(['deactivated_at' => now()]);
    $post = fn (array $data) => $this->actingAs($this->manager)->post('/equipe/delegations', [
        'delegate_id' => $this->delegate->id, 'starts_on' => '2026-06-15', 'ends_on' => '2026-06-26', ...$data,
    ]);

    $post(['delegate_id' => $this->manager->id])->assertSessionHasErrors('delegate_id');
    $post(['delegate_id' => $gone->id])->assertSessionHasErrors('delegate_id');
    $post(['starts_on' => '2026-06-01'])->assertSessionHasErrors('starts_on');
    $post(['ends_on' => '2026-06-14'])->assertSessionHasErrors('ends_on');
    $post([])->assertSessionHasNoErrors();
    $post(['starts_on' => '2026-06-20', 'ends_on' => '2026-06-30'])->assertSessionHasErrors('starts_on');

    // Only managers delegate; employees cannot.
    $this->actingAs($this->employee)->post('/equipe/delegations', ['delegate_id' => $this->delegate->id, 'starts_on' => '2026-07-01', 'ends_on' => '2026-07-02'])->assertForbidden();
});

test('during the delegation the delegate can decide, and so can the manager; never on one\'s own request', function () {
    delegate($this->manager, $this->delegate);
    $leave = pendingLeave($this->employee);

    $this->actingAs($this->delegate)->get('/equipe')->assertInertia(fn (Assert $page) => $page
        ->where('requests.0.can_decide', true)
        ->where('requests.0.delegated_by', 'Meryem')
        ->where('delegatedTeams', ['Meryem']));
    $this->actingAs($this->delegate)->put("/demandes/{$leave->id}/manager-approve")->assertRedirect();
    expect($leave->fresh()->etat)->toBe(LeaveStatus::ManagerApproved);

    $other = pendingLeave($this->employee);
    $this->actingAs($this->manager)->put("/demandes/{$other->id}/manager-approve")->assertRedirect();
    expect($other->fresh()->etat)->toBe(LeaveStatus::ManagerApproved);

    // The delegate is in the same team: their own request stays with the manager.
    $this->delegate->update(['manager_id' => $this->manager->id]);
    $own = pendingLeave($this->delegate);
    $this->actingAs($this->delegate)->put("/demandes/{$own->id}/manager-approve")->assertForbidden();
});

test('before and after the period, the delegate has no rights', function () {
    delegate($this->manager, $this->delegate, '2026-06-15', '2026-06-20');
    $leave = pendingLeave($this->employee);

    $this->actingAs($this->delegate)->put("/demandes/{$leave->id}/manager-approve")->assertForbidden();
    $this->travelTo('2026-06-22 09:00');
    $this->actingAs($this->delegate)->put("/demandes/{$leave->id}/manager-approve")->assertForbidden();
});

test('new requests and reminders go to the delegate during the delegation', function () {
    delegate($this->manager, $this->delegate);

    $this->actingAs($this->employee)->post('/demandes', ['type_conge' => 'annuel', 'date_debut' => '2026-07-06', 'date_fin' => '2026-07-07'])->assertSessionHasNoErrors();
    Notification::assertSentTo($this->delegate, LeaveRequestAwaitingDecision::class);
    Notification::assertNotSentTo($this->manager, LeaveRequestAwaitingDecision::class);

    // Waiting 3 working days later: the reminder goes to the delegate.
    $this->travelTo('2026-06-15 08:00');
    app(Reminders::class)->run();
    Notification::assertSentTo($this->delegate, ManagerPendingReminder::class);
    Notification::assertNotSentTo($this->manager, ManagerPendingReminder::class);
});

test('the delegate sees "Mon équipe" in the menu while the delegation lasts', function () {
    delegate($this->manager, $this->delegate);

    $this->actingAs($this->delegate)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('auth.user.is_manager', true));
    $this->travelTo('2026-06-25 09:00');
    $this->actingAs($this->delegate)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('auth.user.is_manager', false));
});

test('HR sets a delegation for a manager; the manager or HR ends it early', function () {
    $rh = User::factory()->create(['role' => Role::Hr]);

    $this->actingAs($rh)->post("/admin/users/{$this->manager->id}/delegations", ['delegate_id' => $this->delegate->id, 'starts_on' => '2026-06-10', 'ends_on' => '2026-06-30'])
        ->assertSessionHasNoErrors();
    $this->actingAs($rh)->post("/admin/users/{$this->employee->id}/delegations", ['delegate_id' => $this->delegate->id, 'starts_on' => '2026-06-10', 'ends_on' => '2026-06-30'])
        ->assertSessionHasErrors('delegate_id'); // no team

    $delegation = $this->manager->delegationsGiven()->sole();
    $this->actingAs($this->employee)->delete("/delegations/{$delegation->id}")->assertForbidden();

    // Started today: ending it removes it; started earlier: it stops yesterday.
    $this->travelTo('2026-06-12 09:00');
    $this->actingAs($this->manager)->delete("/delegations/{$delegation->id}")->assertRedirect();
    expect($delegation->fresh()->ends_on->toDateString())->toBe('2026-06-11')
        ->and($this->manager->currentDelegate())->toBeNull();

    $future = delegate($this->manager, $this->delegate, '2026-07-01', '2026-07-10');
    $this->actingAs($rh)->delete("/delegations/{$future->id}")->assertRedirect();
    expect(ApprovalDelegation::find($future->id))->toBeNull();
});
