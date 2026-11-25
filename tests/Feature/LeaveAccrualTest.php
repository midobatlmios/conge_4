<?php

use App\Enums\LeaveStatus;
use App\Models\Demande;
use App\Models\User;
use App\Services\LeaveCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Monday 2 February 2026.
beforeEach(fn () => $this->travelTo('2026-02-02 10:00:00'));

function accrual(): LeaveCalculator
{
    return app(LeaveCalculator::class);
}

function hiredOn(string $date): User
{
    return User::factory()->create(['hire_date' => $date]);
}

function acceptedLeave(User $user, string $start, string $end, string $type = 'annuel'): Demande
{
    return Demande::create([
        'user_id' => $user->id,
        'date_demande' => '2026-01-15',
        'date_debut' => $start,
        'date_fin' => $end,
        'type_conge' => $type,
        'etat' => 'acceptée',
    ]);
}

function acceptAs(User $admin, Demande $demande)
{
    return test()->actingAs($admin)->put("/demandes/{$demande->id}", [
        'date_debut' => $demande->date_debut,
        'date_fin' => $demande->date_fin,
        'type_conge' => $demande->type_conge->value,
        'etat' => 'acceptée',
    ]);
}

test('1.5 days are earned at the end of each full month worked', function () {
    $employee = hiredOn('2023-06-01');

    expect(accrual()->earnedDays($employee, 2026, '2026-01-30'))->toBe(0.0)
        ->and(accrual()->earnedDays($employee, 2026, '2026-01-31'))->toBe(1.5)
        ->and(accrual()->earnedDays($employee, 2026, '2026-03-10'))->toBe(3.0)
        ->and(accrual()->earnedDays($employee, 2026, '2026-12-31'))->toBe(18.0);
});

test('a new hire earns from their hire date', function () {
    $employee = hiredOn('2026-03-15');

    expect(accrual()->earnedDays($employee, 2026, '2026-04-14'))->toBe(1.5)
        ->and(accrual()->earnedDays($employee, 2026, '2026-12-31'))->toBe(13.5); // 9 full months
});

test('seniority adds 1.5 days per 5 full years, capped at 30', function () {
    expect(accrual()->annualEntitlement(hiredOn('2021-01-01'), 2026))->toBe(19.5) // 5 years on 1 January
        ->and(accrual()->annualEntitlement(hiredOn('2021-01-02'), 2026))->toBe(18.0) // not quite 5
        ->and(accrual()->annualEntitlement(hiredOn('2016-01-01'), 2026))->toBe(21.0)
        ->and(accrual()->annualEntitlement(hiredOn('1976-01-01'), 2026))->toBe(30.0); // 50 years, capped
});

test('the bonus is earned month by month too, rounded down to the half day', function () {
    $employee = hiredOn('2021-01-01'); // 19.5 days in 2026

    expect(accrual()->earnedDays($employee, 2026, '2026-01-31'))->toBe(1.5) // 1.625
        ->and(accrual()->earnedDays($employee, 2026, '2026-12-31'))->toBe(19.5);
});

test('the balance is what has been earned so far minus accepted days', function () {
    $employee = hiredOn('2023-06-01');
    acceptedLeave($employee, '2026-01-26', '2026-01-26'); // 1 day

    // 2 February: January is earned (1.5), 1 used.
    expect(accrual()->balance($employee))->toMatchArray([
        'annualDays' => 18.0,
        'earnedDays' => 1.5,
        'usedDays' => 1,
        'remainingDays' => 0.5,
    ]);
});

test('a leave can only use the days earned by the day it starts', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = hiredOn('2023-06-01');

    // Mon 2 to Fri 6 March = 5 days; only 3 earned by 2 March (January, February).
    $early = acceptedLeave($employee, '2026-03-02', '2026-03-06');
    $early->update(['etat' => LeaveStatus::Pending]);
    acceptAs($admin, $early)->assertSessionHasErrors('etat');

    // The same 5 days in July: 9 earned by 6 July.
    $later = acceptedLeave($employee, '2026-07-06', '2026-07-10');
    $later->update(['etat' => LeaveStatus::Pending]);
    acceptAs($admin, $later)->assertSessionHasNoErrors();
});

test('an earlier leave counts against a later one', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = hiredOn('2023-06-01');
    acceptedLeave($employee, '2026-07-06', '2026-07-10'); // 5 days, 9 earned by then

    // 4 more days on 13 July: 9 used of 9 earned (June complete) - fits exactly.
    $fits = acceptedLeave($employee, '2026-07-13', '2026-07-16');
    $fits->update(['etat' => LeaveStatus::Pending]);
    acceptAs($admin, $fits)->assertSessionHasNoErrors();

    // A day in March would leave the July leaves short.
    $march = acceptedLeave($employee, '2026-03-02', '2026-03-02');
    $march->update(['etat' => LeaveStatus::Pending]);
    acceptAs($admin, $march)->assertSessionHasErrors('etat');
});

test('annual leave cannot be accepted during the first 6 months of service', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = hiredOn('2025-09-15'); // eligible from 15 March 2026

    $tooSoon = acceptedLeave($employee, '2026-03-13', '2026-03-13');
    $tooSoon->update(['etat' => LeaveStatus::Pending]);
    acceptAs($admin, $tooSoon)->assertSessionHasErrors('etat');

    $onTime = acceptedLeave($employee, '2026-03-16', '2026-03-16');
    $onTime->update(['etat' => LeaveStatus::Pending]);
    acceptAs($admin, $onTime)->assertSessionHasNoErrors();
});

test('exceptional leave is not subject to the 6-month wait', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = hiredOn('2026-01-05');

    $naissance = acceptedLeave($employee, '2026-03-02', '2026-03-04', 'naissance');
    $naissance->update(['etat' => LeaveStatus::Pending]);
    // The birth certificate HR needs before accepting.
    $naissance->documents()->create(['original_name' => 'acte.pdf', 'path' => 'documents/x.pdf', 'mime_type' => 'application/pdf', 'size' => 1]);
    acceptAs($admin, $naissance)->assertSessionHasNoErrors();
});

test('accounts without a hire date earn from 1 January with no bonus', function () {
    $employee = User::factory()->create(['hire_date' => null]);

    expect(accrual()->annualEntitlement($employee, 2026))->toBe(18.0)
        ->and(accrual()->earnedDays($employee, 2026, '2026-12-31'))->toBe(18.0)
        ->and(accrual()->eligibleFrom($employee))->toBeNull();
});
