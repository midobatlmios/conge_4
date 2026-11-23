<?php

use App\Models\Demande;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Monday 2 February 2026, so March 2026 dates are in the future.
beforeEach(fn () => $this->travelTo('2026-02-02 10:00:00'));

function createDemandeForTest(User $user, array $attributes = []): Demande
{
    return Demande::create(array_merge([
        'user_id' => $user->id,
        'date_demande' => '2026-02-01',
        'date_debut' => '2026-03-02', // Monday
        'date_fin' => '2026-03-03',
        'type_conge' => 'annuel',
        'etat' => 'en attente',
    ], $attributes));
}

function leavePayload(array $overrides = []): array
{
    return array_merge([
        'date_debut' => '2026-03-02',
        'date_fin' => '2026-03-03',
        'type_conge' => 'annuel',
    ], $overrides);
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
        ->putJson("/demandes/{$demande->id}", leavePayload(['etat' => 'acceptée']))
        ->assertForbidden();

    $this->delete("/demandes/{$demande->id}")->assertForbidden();
    expect($demande->fresh()->etat)->toBe('en attente');
});

test('new requests ignore client-supplied owner status days year and request date', function () {
    $employee = User::factory()->create();
    $otherEmployee = User::factory()->create();

    $this->actingAs($employee)
        ->postJson('/demandes', [
            'date_demande' => '2020-01-01',
            'date_debut' => '2027-03-01', // Monday
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
        ->and($demande->annee)->toBe(2027)
        ->and($demande->date_demande)->toBe('2026-02-02');
});

test('admins can file a request on behalf of an employee', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = User::factory()->create();

    $this->actingAs($admin)
        ->postJson('/demandes', leavePayload(['user_id' => $employee->id]))
        ->assertCreated();

    expect(Demande::sole()->user_id)->toBe($employee->id);
});

test('requests cannot have an end date before their start date', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/demandes', leavePayload(['date_debut' => '2026-03-04', 'date_fin' => '2026-03-02']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('date_fin');
});

test('employees cannot request leave in the past', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/demandes', leavePayload(['date_debut' => '2026-01-26', 'date_fin' => '2026-01-27']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('date_debut');
});

test('leave-type day limits are counted in working days', function () {
    $employee = User::factory()->create();

    // Monday to Friday = 5 working days, over the 4-day mariage limit.
    $this->actingAs($employee)
        ->postJson('/demandes', leavePayload([
            'date_debut' => '2026-03-02',
            'date_fin' => '2026-03-06',
            'type_conge' => 'mariage',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('date_fin');

    // Friday to Wednesday spans 6 calendar days but only 4 working days.
    $this->postJson('/demandes', leavePayload([
        'date_debut' => '2026-03-06',
        'date_fin' => '2026-03-11',
        'type_conge' => 'mariage',
    ]))->assertCreated();
});

test('weekends and public holidays are not counted as leave days', function () {
    // Thu 30 Apr, Fri 1 May (Fête du Travail), Sat, Sun, Mon 4 May.
    expect(Demande::countWorkingDays('2026-04-30', '2026-05-04'))->toBe(2);
});

test('a request covering only non-working days is rejected', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/demandes', leavePayload(['date_debut' => '2026-03-07', 'date_fin' => '2026-03-08']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('date_fin');
});

test('requests cannot overlap another pending or accepted request', function () {
    $employee = User::factory()->create();
    createDemandeForTest($employee, ['date_debut' => '2026-03-02', 'date_fin' => '2026-03-04']);

    $this->actingAs($employee)
        ->postJson('/demandes', leavePayload(['date_debut' => '2026-03-04', 'date_fin' => '2026-03-06']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('date_debut');
});

test('a refused request does not block the same dates', function () {
    $employee = User::factory()->create();
    createDemandeForTest($employee, ['etat' => 'refusée']);

    $this->actingAs($employee)
        ->postJson('/demandes', leavePayload())
        ->assertCreated();
});

test('editing a request does not conflict with itself', function () {
    $employee = User::factory()->create();
    $demande = createDemandeForTest($employee);

    $this->actingAs($employee)
        ->putJson("/demandes/{$demande->id}", leavePayload(['date_fin' => '2026-03-04']))
        ->assertOk();

    expect($demande->fresh()->nbr_jours)->toBe(3);
});

test('admins can approve annual leave only when the yearly limit is respected', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = User::factory()->create();
    // 2 to 25 March 2026 = 18 working days, the whole allowance.
    createDemandeForTest($employee, [
        'date_debut' => '2026-03-02',
        'date_fin' => '2026-03-25',
        'etat' => 'acceptée',
    ]);
    $pending = createDemandeForTest($employee, [
        'date_debut' => '2026-12-30',
        'date_fin' => '2026-12-30',
    ]);

    $this->actingAs($admin)
        ->putJson("/demandes/{$pending->id}", leavePayload([
            'date_debut' => '2026-12-30',
            'date_fin' => '2026-12-30',
            'etat' => 'acceptée',
        ]))
        ->assertUnprocessable();

    expect($pending->fresh()->etat)->toBe('en attente');
});

test('mariage and deces take 2 days from the allowance, naissance takes none', function () {
    $employee = User::factory()->create();
    // Mon 2 to Thu 5 March: 4 working days of mariage, of which 2 are deducted.
    createDemandeForTest($employee, [
        'date_debut' => '2026-03-02',
        'date_fin' => '2026-03-05',
        'type_conge' => 'mariage',
        'etat' => 'acceptée',
    ]);
    createDemandeForTest($employee, [
        'date_debut' => '2026-04-06',
        'date_fin' => '2026-04-08',
        'type_conge' => 'deces',
        'etat' => 'acceptée',
    ]);
    createDemandeForTest($employee, [
        'date_debut' => '2026-05-11',
        'date_fin' => '2026-05-13',
        'type_conge' => 'naissance',
        'etat' => 'acceptée',
    ]);

    expect(Demande::getTotalDaysForUser($employee->id, 2026))->toBe(4);
});

test('a mariage cannot be accepted when it would exceed the allowance', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = User::factory()->create();
    // 2 to 24 March 2026 = 17 working days of annual leave: 1 day left.
    createDemandeForTest($employee, [
        'date_debut' => '2026-03-02',
        'date_fin' => '2026-03-24',
        'etat' => 'acceptée',
    ]);
    $mariage = createDemandeForTest($employee, [
        'date_debut' => '2026-06-01',
        'date_fin' => '2026-06-04',
        'type_conge' => 'mariage',
    ]);

    $this->actingAs($admin)
        ->putJson("/demandes/{$mariage->id}", leavePayload([
            'date_debut' => '2026-06-01',
            'date_fin' => '2026-06-04',
            'type_conge' => 'mariage',
            'etat' => 'acceptée',
        ]))
        ->assertUnprocessable();

    expect($mariage->fresh()->etat)->toBe('en attente');
});

test('unpaid leave does not use up the annual allowance', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = User::factory()->create();
    createDemandeForTest($employee, [
        'date_debut' => '2026-03-02',
        'date_fin' => '2026-03-25',
        'type_conge' => 'sans_solde',
        'etat' => 'acceptée',
    ]);
    $pending = createDemandeForTest($employee, [
        'date_debut' => '2026-12-30',
        'date_fin' => '2026-12-30',
    ]);

    expect(Demande::getTotalDaysForUser($employee->id, 2026))->toBe(0);

    $this->actingAs($admin)
        ->putJson("/demandes/{$pending->id}", leavePayload([
            'date_debut' => '2026-12-30',
            'date_fin' => '2026-12-30',
            'etat' => 'acceptée',
        ]))
        ->assertOk();
});

test('approved days are counted in the calendar years they overlap', function () {
    $employee = User::factory()->create();
    // Wed 31 Dec 2025, Thu 1 Jan 2026 (holiday), Fri 2 Jan 2026.
    createDemandeForTest($employee, [
        'date_debut' => '2025-12-31',
        'date_fin' => '2026-01-02',
        'etat' => 'acceptée',
    ]);

    expect(Demande::getTotalDaysForUser($employee->id, 2025))->toBe(1)
        ->and(Demande::getTotalDaysForUser($employee->id, 2026))->toBe(1);
});

test('the employee is notified right away when a request is accepted', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = User::factory()->create();
    $demande = createDemandeForTest($employee);

    $this->actingAs($admin)
        ->putJson("/demandes/{$demande->id}", leavePayload(['etat' => 'acceptée']))
        ->assertOk();

    expect($employee->notifications()->count())->toBe(1);
});
