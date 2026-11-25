<?php

use App\Enums\LeaveStatus;
use App\Enums\Role;
use App\Models\Demande;
use App\Models\User;
use App\Notifications\LeaveRequestAwaitingDecision;
use App\Notifications\SickLeaveDeclared;
use App\Services\PayrollReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->travelTo('2026-06-10')); // a Wednesday

function sickUser(Role $role = Role::User, array $attributes = []): User
{
    return User::factory()->create(['role' => $role, 'hire_date' => '2020-01-01', ...$attributes]);
}

function declareSick(User $actor, array $overrides = [])
{
    return test()->actingAs($actor)->post('/demandes', [
        'type_conge' => 'maladie',
        'date_debut' => '2026-06-08',
        'date_fin' => '2026-06-14',
        ...$overrides,
    ]);
}

test('sick leave goes straight to HR; the manager is only informed and cannot decide', function () {
    Notification::fake();
    $manager = sickUser();
    $rh = sickUser(Role::Hr);
    $employee = sickUser(attributes: ['manager_id' => $manager->id]);

    declareSick($employee)->assertSessionHasNoErrors();

    $leave = $employee->demandes()->sole();
    Notification::assertSentTo($rh, LeaveRequestAwaitingDecision::class);
    Notification::assertSentTo($manager, SickLeaveDeclared::class);
    Notification::assertNotSentTo($manager, LeaveRequestAwaitingDecision::class);

    $this->actingAs($manager)->put("/demandes/{$leave->id}/manager-approve")->assertForbidden();
    expect($leave->fresh()->etat)->toBe(LeaveStatus::Pending);
});

test('an employee declares sick leave up to 2 days back; older ones go through HR', function () {
    $employee = sickUser();

    declareSick($employee, ['date_debut' => '2026-06-08', 'date_fin' => '2026-06-09'])->assertSessionHasNoErrors();
    declareSick($employee, ['date_debut' => '2026-06-01', 'date_fin' => '2026-06-02'])->assertSessionHasErrors('date_debut');

    // Other leave types still cannot start in the past.
    $this->actingAs($employee)
        ->post('/demandes', ['type_conge' => 'sans_solde', 'date_debut' => '2026-06-09', 'date_fin' => '2026-06-09'])
        ->assertSessionHasErrors('date_debut');

    // HR can record an older sick leave.
    $this->actingAs(sickUser(Role::Hr))
        ->post('/demandes', ['type_conge' => 'maladie', 'date_debut' => '2026-05-04', 'date_fin' => '2026-05-05', 'user_id' => $employee->id])
        ->assertSessionHasNoErrors();
});

test('HR accepts sick leave only once the medical certificate is attached; it does not use the allowance', function () {
    Storage::fake('local');
    $rh = sickUser(Role::Hr);
    $employee = sickUser();
    declareSick($employee);
    $leave = $employee->demandes()->sole();
    $accept = fn () => $this->actingAs($rh)->put("/demandes/{$leave->id}", [
        'type_conge' => 'maladie', 'date_debut' => '2026-06-08', 'date_fin' => '2026-06-14', 'etat' => 'acceptée',
    ]);

    $accept()->assertSessionHasErrors('etat');

    $this->actingAs($employee)->post("/demandes/{$leave->id}/documents", ['document' => UploadedFile::fake()->create('certificat.pdf', 100, 'application/pdf')]);
    $accept()->assertSessionHasNoErrors();

    expect($leave->fresh()->etat)->toBe(LeaveStatus::Accepted)
        ->and($leave->fresh()->nbr_jours)->toEqual(5)
        ->and($leave->fresh()->calendar_days)->toBe(7);

    $this->actingAs($employee)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('leaveRules.balance.usedDays', 0));
});

test('sick leave of 4+ calendar days waits for its CNSS dossier on the overview until HR records it', function () {
    $rh = sickUser(Role::Hr);
    $employee = sickUser(attributes: ['name' => 'Karim']);
    declareSick($employee); // 7 calendar days
    declareSick($employee, ['date_debut' => '2026-06-15', 'date_fin' => '2026-06-17']); // 3 days: no CNSS
    $long = $employee->demandes()->orderBy('date_debut')->first();

    $this->actingAs($rh)->get('/admin/overview')
        ->assertInertia(fn (Assert $page) => $page
            ->has('cnssPending', 1)
            ->where('cnssPending.0.name', 'Karim')
            ->where('cnssPending.0.calendar_days', 7)
            ->where('cnssPending.0.deadline', '2026-07-08')
            ->where('cnssPending.0.overdue', false));

    $this->actingAs($rh)->put("/demandes/{$long->id}/cnss", ['cnss_filed_on' => '2026-06-20'])->assertSessionHasErrors('cnss_filed_on'); // future
    $this->actingAs($rh)->put("/demandes/{$long->id}/cnss", ['cnss_filed_on' => '2026-06-10'])->assertSessionHasNoErrors();

    expect($long->fresh()->cnss_filed_on->toDateString())->toBe('2026-06-10');
    $this->actingAs($rh)->get('/admin/overview')->assertInertia(fn (Assert $page) => $page->has('cnssPending', 0));
});

test('only HR records the CNSS dossier, and only for sick leave', function () {
    $employee = sickUser();
    declareSick($employee);
    $leave = $employee->demandes()->sole();

    $this->actingAs($employee)->put("/demandes/{$leave->id}/cnss", ['cnss_filed_on' => '2026-06-10'])->assertForbidden();
    $this->actingAs(sickUser(Role::Payroll))->put("/demandes/{$leave->id}/cnss", ['cnss_filed_on' => '2026-06-10'])->assertForbidden();

    $annual = Demande::create(['user_id' => $employee->id, 'type_conge' => 'annuel', 'date_debut' => '2026-07-01', 'date_fin' => '2026-07-02', 'date_demande' => '2026-06-10', 'etat' => 'en attente']);
    $this->actingAs(sickUser(Role::Hr))->put("/demandes/{$annual->id}/cnss", ['cnss_filed_on' => '2026-06-10'])->assertStatus(422);
});

test('sick days appear on the employee page and in the payroll export', function () {
    $employee = sickUser();
    declareSick($employee);
    $leave = $employee->demandes()->sole();
    $leave->forceFill(['etat' => LeaveStatus::Accepted])->save();

    $this->actingAs(sickUser(Role::Hr))->get("/admin/users/{$employee->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('sickDays.count', 1)
            ->where('sickDays.working', 5)
            ->where('sickDays.calendar', 7));

    $row = collect(app(PayrollReport::class)->rows(now()->startOfMonth()))->firstWhere('id', $employee->id);
    expect($row['sick'])->toEqual(5)
        ->and($row['annual'])->toEqual(0)
        ->and($row['total'])->toEqual(5);
});
