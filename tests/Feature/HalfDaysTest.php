<?php

use App\Models\Demande;
use App\Models\User;
use App\Services\LeaveCalculator;
use App\Services\PayrollReport;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Monday 2 February 2026.
beforeEach(fn () => $this->travelTo('2026-02-02 10:00:00'));

/** @return array<string, mixed> */
function halfPayload(array $overrides = []): array
{
    return [
        'date_debut' => '2026-03-02', // Monday
        'date_fin' => '2026-03-04', // Wednesday
        'type_conge' => 'annuel',
        ...$overrides,
    ];
}

function halfLeave(User $user, array $attributes): Demande
{
    return Demande::create([
        'user_id' => $user->id,
        'date_demande' => '2026-02-01',
        'type_conge' => 'annuel',
        'etat' => 'acceptée',
        ...$attributes,
    ]);
}

test('starting in the afternoon and ending at noon each take half a day off the count', function () {
    $employee = User::factory()->create();

    $this->actingAs($employee)
        ->post('/demandes', halfPayload(['start_half' => true, 'end_half' => true]))
        ->assertSessionHasNoErrors();

    $demande = Demande::first();
    expect($demande->start_half)->toBeTrue()
        ->and($demande->end_half)->toBeTrue()
        ->and($demande->nbr_jours)->toBe(2.0); // 3 days - 0.5 - 0.5
});

test('a single morning or afternoon is half a day', function () {
    $employee = User::factory()->create();

    $this->actingAs($employee)
        ->post('/demandes', halfPayload(['date_fin' => '2026-03-02', 'end_half' => true]))
        ->assertSessionHasNoErrors();

    expect(Demande::first()->nbr_jours)->toBe(0.5);
});

test('a morning and an afternoon off on the same day do not overlap', function () {
    $employee = User::factory()->create();
    halfLeave($employee, ['date_debut' => '2026-03-02', 'date_fin' => '2026-03-02', 'end_half' => true]); // morning

    $this->actingAs($employee)
        ->post('/demandes', halfPayload(['date_fin' => '2026-03-02', 'start_half' => true])) // afternoon
        ->assertSessionHasNoErrors();

    // A second full day on the same date still clashes.
    $this->actingAs($employee)
        ->post('/demandes', halfPayload(['date_fin' => '2026-03-02']))
        ->assertSessionHasErrors('date_debut');
});

test('a leave ending at noon can be followed by one starting that afternoon', function () {
    $employee = User::factory()->create();
    halfLeave($employee, ['date_debut' => '2026-03-02', 'date_fin' => '2026-03-04', 'end_half' => true]);

    $this->actingAs($employee)
        ->post('/demandes', halfPayload(['date_debut' => '2026-03-04', 'date_fin' => '2026-03-06', 'start_half' => true]))
        ->assertSessionHasNoErrors();
});

test('morning and afternoon both on one day is refused', function () {
    $this->actingAs(User::factory()->create())
        ->post('/demandes', halfPayload(['date_fin' => '2026-03-02', 'start_half' => true, 'end_half' => true]))
        ->assertSessionHasErrors('date_fin');
});

test('a half day must fall on a working day', function () {
    // Starts on Saturday 7 March in the afternoon.
    $this->actingAs(User::factory()->create())
        ->post('/demandes', halfPayload(['date_debut' => '2026-03-07', 'date_fin' => '2026-03-10', 'start_half' => true]))
        ->assertSessionHasErrors('date_debut');
});

test('exceptional leave ignores half days', function () {
    $this->actingAs(User::factory()->create())
        ->post('/demandes', halfPayload(['type_conge' => 'naissance', 'start_half' => true]))
        ->assertSessionHasNoErrors();

    expect(Demande::first()->start_half)->toBeFalse()
        ->and(Demande::first()->nbr_jours)->toBe(3.0);
});

test('half days count in the balance, the acceptance check and the payroll export', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = User::factory()->create(['name' => 'Sara', 'hire_date' => '2023-06-01']);
    halfLeave($employee, ['date_debut' => '2026-03-02', 'date_fin' => '2026-03-03', 'end_half' => true]); // 1.5

    expect(app(LeaveCalculator::class)->usedDays($employee->id, 2026))->toBe(1.5);

    // 3 days earned by 4 March: 1.5 used, so another 1.5 fits exactly…
    $fits = halfLeave($employee, ['date_debut' => '2026-03-04', 'date_fin' => '2026-03-05', 'end_half' => true, 'etat' => 'en attente']);
    $this->actingAs($admin)
        ->put("/demandes/{$fits->id}", halfPayload(['date_debut' => '2026-03-04', 'date_fin' => '2026-03-05', 'end_half' => true, 'etat' => 'acceptée']))
        ->assertSessionHasNoErrors();

    // …but not a full extra day.
    $tooMuch = halfLeave($employee, ['date_debut' => '2026-03-06', 'date_fin' => '2026-03-06', 'etat' => 'en attente']);
    $this->actingAs($admin)
        ->put("/demandes/{$tooMuch->id}", halfPayload(['date_debut' => '2026-03-06', 'date_fin' => '2026-03-06', 'etat' => 'acceptée']))
        ->assertSessionHasErrors('etat');

    $row = app(PayrollReport::class)->rows(Carbon::parse('2026-03-01'))->firstWhere('name', 'Sara');
    expect($row['annual'])->toBe(3.0);
});
