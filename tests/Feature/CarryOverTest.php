<?php

use App\Models\Demande;
use App\Models\User;
use App\Services\LeaveCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Monday 2 February 2026.
beforeEach(fn () => $this->travelTo('2026-02-02 10:00:00'));

function carry(): LeaveCalculator
{
    return app(LeaveCalculator::class);
}

/** An employee hired in 2023 whose account exists in the app since the given date. */
function employeeSince(string $createdAt): User
{
    $user = User::factory()->create(['hire_date' => '2023-06-01']);
    $user->forceFill(['created_at' => $createdAt])->save();

    return $user;
}

function leaveFor(User $user, string $start, string $end, string $etat = 'acceptée'): Demande
{
    return Demande::create([
        'user_id' => $user->id,
        'date_demande' => '2025-01-02',
        'date_debut' => $start,
        'date_fin' => $end,
        'type_conge' => 'annuel',
        'etat' => $etat,
    ]);
}

function acceptLeave(Demande $demande)
{
    return test()->actingAs(User::factory()->create(['role' => 'admin']))->put("/demandes/{$demande->id}", [
        'date_debut' => $demande->date_debut,
        'date_fin' => $demande->date_fin,
        'type_conge' => 'annuel',
        'etat' => 'acceptée',
    ]);
}

test('unused days of last year carry over', function () {
    $employee = employeeSince('2025-01-01');
    leaveFor($employee, '2025-06-02', '2025-06-13'); // 10 days

    expect(carry()->carriedOver($employee, 2026))->toBe(8.0);
});

test('nothing carries over from before the account existed in the app', function () {
    $employee = employeeSince('2026-01-05');

    expect(carry()->carriedOver($employee, 2026))->toBe(0.0);
});

test('carried days never roll over a second time', function () {
    $idle = employeeSince('2024-01-01'); // took nothing in 2024 nor 2025
    expect(carry()->carriedOver($idle, 2025))->toBe(18.0)
        ->and(carry()->carriedOver($idle, 2026))->toBe(18.0); // not 36

    $busy = employeeSince('2024-01-01');
    leaveFor($busy, '2025-02-03', '2025-02-28'); // 20 days: 18 carried + 2 of 2025's own
    expect(carry()->carriedOver($busy, 2026))->toBe(16.0);
});

test('carried days make January leave possible', function () {
    $employee = employeeSince('2025-01-01');
    leaveFor($employee, '2025-06-02', '2025-06-13'); // 8 days carried into 2026

    $january = leaveFor($employee, '2026-01-19', '2026-01-23', 'en attente'); // 5 days, nothing earned yet
    acceptLeave($january)->assertSessionHasNoErrors();
});

test('carried days expire on 31 March', function () {
    $employee = employeeSince('2025-01-01');
    leaveFor($employee, '2025-06-02', '2025-06-13'); // 8 days carried into 2026

    // 5 days in April: only 4.5 earned by then, the carried days have expired.
    $april = leaveFor($employee, '2026-04-06', '2026-04-10', 'en attente');
    acceptLeave($april)->assertSessionHasErrors('etat');

    // The same 5 days in March use the carried days.
    $march = leaveFor($employee, '2026-03-09', '2026-03-13', 'en attente');
    acceptLeave($march)->assertSessionHasNoErrors();
});

test('the balance shows carried days still to use before they expire', function () {
    $employee = employeeSince('2025-01-01');
    leaveFor($employee, '2025-06-02', '2025-06-13'); // 8 days carried into 2026
    leaveFor($employee, '2026-01-19', '2026-01-21'); // 3 of them used

    expect(carry()->balance($employee))->toMatchArray([
        'earnedDays' => 1.5,
        'usedDays' => 3,
        'carriedDays' => 8.0,
        'carryUsedDays' => 3.0,
        'carryLeftDays' => 5.0,
        'remainingDays' => 6.5, // 1.5 earned + 8 carried - 3 used
        'carryExpires' => '2026-03-31',
    ]);

    // In April the 5 unused carried days are gone.
    $this->travelTo('2026-04-15');
    // 4.5 earned + the 3 carried days that were used - 3 used.
    expect(carry()->remainingDays($employee->id, 2026))->toBe(4.5);
});
