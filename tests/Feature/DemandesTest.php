<?php

use App\Models\Demande;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createDemandeForTest(User $user, array $attributes = []): Demande
{
    return Demande::create(array_merge([
        'user_id' => $user->id,
        'date_demande' => '2026-01-01',
        'date_debut' => '2026-03-01',
        'date_fin' => '2026-03-02',
        'type_conge' => 'sans_solde',
        'etat' => 'en attente',
    ], $attributes));
}

test('employees only see their own leave requests', function () {
    $employee = User::factory()->create();
    $otherEmployee = User::factory()->create();
    createDemandeForTest($employee);
    createDemandeForTest($otherEmployee);

    $this->actingAs($employee)
        ->get('/demandes')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Demandes')
            ->has('demandes', 1)
            ->where('demandes.0.user_id', $employee->id));
});

test('employees cannot edit or delete another employees request', function () {
    $employee = User::factory()->create();
    $otherEmployee = User::factory()->create();
    $demande = createDemandeForTest($otherEmployee);

    $this->actingAs($employee)
        ->putJson("/demandes/{$demande->id}", [
            'date_demande' => '2026-01-01',
            'date_debut' => '2026-03-01',
            'date_fin' => '2026-03-02',
            'type_conge' => 'sans_solde',
            'etat' => 'acceptée',
        ])
        ->assertForbidden();

    $this->delete("/demandes/{$demande->id}")->assertForbidden();
    expect($demande->fresh()->etat)->toBe('en attente');
});

test('new requests ignore client-supplied owner status days and year', function () {
    $employee = User::factory()->create();
    $otherEmployee = User::factory()->create();

    $this->actingAs($employee)
        ->postJson('/demandes', [
            'date_demande' => '2026-01-01',
            'date_debut' => '2027-03-01',
            'date_fin' => '2027-03-04',
            'type_conge' => 'mariage',
            'etat' => 'acceptée',
            'nbr_jours' => 999,
            'annee' => 2020,
            'user_id' => $otherEmployee->id,
        ])
        ->assertCreated();

    $demande = Demande::sole();
    expect($demande->user_id)->toBe($employee->id)
        ->and($demande->etat)->toBe('en attente')
        ->and($demande->nbr_jours)->toBe(4)
        ->and($demande->annee)->toBe(2027);
});

test('requests cannot have an end date before their start date', function () {
    $employee = User::factory()->create();

    $this->actingAs($employee)
        ->postJson('/demandes', [
            'date_demande' => '2026-01-01',
            'date_debut' => '2026-03-03',
            'date_fin' => '2026-03-01',
            'type_conge' => 'sans_solde',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('date_fin');
});

test('leave-type day limits are calculated from the submitted dates', function () {
    $employee = User::factory()->create();

    $this->actingAs($employee)
        ->postJson('/demandes', [
            'date_demande' => '2026-01-01',
            'date_debut' => '2026-03-01',
            'date_fin' => '2026-03-05',
            'type_conge' => 'mariage',
            'nbr_jours' => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('date_fin');
});

test('admins can approve requests only when the yearly limit is respected', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = User::factory()->create();
    createDemandeForTest($employee, [
        'date_debut' => '2026-01-01',
        'date_fin' => '2026-01-18',
        'etat' => 'acceptée',
    ]);
    $pending = createDemandeForTest($employee, [
        'date_debut' => '2026-12-30',
        'date_fin' => '2026-12-30',
    ]);

    $this->actingAs($admin)
        ->putJson("/demandes/{$pending->id}", [
            'date_demande' => '2026-01-01',
            'date_debut' => '2026-12-30',
            'date_fin' => '2026-12-30',
            'type_conge' => 'sans_solde',
            'etat' => 'acceptée',
        ])
        ->assertUnprocessable();

    expect($pending->fresh()->etat)->toBe('en attente');
});

test('approved days are counted in the calendar years they overlap', function () {
    $employee = User::factory()->create();
    createDemandeForTest($employee, [
        'date_debut' => '2025-12-31',
        'date_fin' => '2026-01-02',
        'etat' => 'acceptée',
    ]);

    expect(Demande::getTotalDaysForUser($employee->id, 2025))->toBe(1)
        ->and(Demande::getTotalDaysForUser($employee->id, 2026))->toBe(2);
});
