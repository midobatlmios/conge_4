<?php

use App\Models\Demande;
use App\Models\DemandeEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Monday 2 February 2026, so March 2026 dates are in the future.
beforeEach(fn () => $this->travelTo('2026-02-02 10:00:00'));

function historyPayload(array $overrides = []): array
{
    return array_merge([
        'date_debut' => '2026-03-02',
        'date_fin' => '2026-03-03',
        'type_conge' => 'annuel',
        'comment' => null,
    ], $overrides);
}

test('creating, editing and deciding a request is recorded with who and what', function () {
    $employee = User::factory()->create(['role' => 'user', 'name' => 'Sara']);
    $admin = User::factory()->create(['role' => 'admin', 'name' => 'RH']);

    $this->actingAs($employee)->postJson('/demandes', historyPayload())->assertCreated();
    $demande = Demande::sole();

    $this->actingAs($employee)
        ->putJson("/demandes/{$demande->id}", historyPayload(['date_fin' => '2026-03-04', 'etat' => 'en attente']))
        ->assertOk();

    $this->actingAs($admin)
        ->putJson("/demandes/{$demande->id}", historyPayload(['date_fin' => '2026-03-04', 'etat' => 'acceptée', 'comment' => 'OK']))
        ->assertOk();

    $events = $demande->events()->with('user')->get();

    expect($events->pluck('action')->all())->toBe(['created', 'updated', 'accepted'])
        ->and($events->pluck('user.name')->all())->toBe(['Sara', 'Sara', 'RH'])
        ->and($events[0]->changes)->toMatchArray(['date_debut' => '2026-03-02', 'date_fin' => '2026-03-03', 'type_conge' => 'annuel'])
        ->and($events[1]->changes)->toBe(['date_fin' => ['old' => '2026-03-03', 'new' => '2026-03-04']])
        ->and($events[2]->changes)->toBe([
            'etat' => ['old' => 'en attente', 'new' => 'acceptée'],
            'comment' => ['old' => null, 'new' => 'OK'],
        ]);
});

test('saving without any real change adds no entry', function () {
    $employee = User::factory()->create(['role' => 'user']);
    $this->actingAs($employee)->postJson('/demandes', historyPayload())->assertCreated();
    $demande = Demande::sole();

    $this->actingAs($employee)
        ->putJson("/demandes/{$demande->id}", historyPayload(['etat' => 'en attente']))
        ->assertOk();

    expect($demande->events()->count())->toBe(1);
});

test('the trail of a deleted request is kept', function () {
    $employee = User::factory()->create(['role' => 'user']);
    $this->actingAs($employee)->postJson('/demandes', historyPayload())->assertCreated();
    $demande = Demande::sole();

    $this->actingAs($employee)->delete("/demandes/{$demande->id}")->assertRedirect();

    expect(Demande::count())->toBe(0)
        ->and(DemandeEvent::where('demande_id', $demande->id)->pluck('action')->all())->toBe(['created', 'deleted'])
        ->and(DemandeEvent::where('action', 'deleted')->sole()->employee_id)->toBe($employee->id);
});

test('the employee and admins can read the history, other employees cannot', function () {
    $employee = User::factory()->create(['role' => 'user', 'name' => 'Sara']);
    $this->actingAs($employee)->postJson('/demandes', historyPayload())->assertCreated();
    $demande = Demande::sole();

    $this->actingAs($employee)
        ->getJson("/demandes/{$demande->id}/history")
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.action', 'created')
        ->assertJsonPath('0.by', 'Sara');

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->getJson("/demandes/{$demande->id}/history")
        ->assertOk();

    $this->actingAs(User::factory()->create(['role' => 'user']))
        ->getJson("/demandes/{$demande->id}/history")
        ->assertForbidden();
});

test('moving a request to another employee shows both names', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sara = User::factory()->create(['role' => 'user', 'name' => 'Sara']);
    $omar = User::factory()->create(['role' => 'user', 'name' => 'Omar']);

    $demande = Demande::create([...historyPayload(), 'user_id' => $sara->id, 'date_demande' => '2026-02-02', 'etat' => 'en attente']);
    $this->actingAs($admin);
    $demande->update(['user_id' => $omar->id]);

    $this->getJson("/demandes/{$demande->id}/history")
        ->assertOk()
        ->assertJsonPath('1.action', 'updated')
        ->assertJsonPath("1.employee_names.{$sara->id}", 'Sara')
        ->assertJsonPath("1.employee_names.{$omar->id}", 'Omar');
});
