<?php

use App\Enums\LeaveStatus;
use App\Enums\Role;
use App\Models\Attestation;
use App\Models\Demande;
use App\Models\User;
use App\Notifications\HrDailySummary;
use App\Notifications\ManagerPendingReminder;
use App\Notifications\PrivacyNoticeReminder;
use App\Services\Reminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-06-10 08:00'); // a Wednesday
});

/** A request submitted on $submitted (Y-m-d), waiting at the given step. */
function waitingRequest(User $employee, string $submitted, LeaveStatus $status = LeaveStatus::Pending, string $type = 'annuel'): Demande
{
    test()->travelTo("{$submitted} 09:00");
    $demande = Demande::create([
        'user_id' => $employee->id, 'type_conge' => $type, 'date_debut' => '2026-07-06', 'date_fin' => '2026-07-07',
        'date_demande' => $submitted, 'etat' => $status,
    ]);
    test()->travelTo('2026-06-10 08:00');

    return $demande;
}

test('a manager is reminded of requests waiting 3 working days, then every 3 working days', function () {
    $manager = User::factory()->create();
    $employee = User::factory()->create(['manager_id' => $manager->id]);
    waitingRequest($employee, '2026-06-04'); // Thursday: 4 working days ago
    $recent = User::factory()->create(['manager_id' => $manager->id]);
    waitingRequest($recent, '2026-06-09');   // yesterday: not yet

    app(Reminders::class)->run();
    Notification::assertSentTo($manager, ManagerPendingReminder::class, fn ($n, $channels) => in_array('mail', $channels, true));
    Notification::assertSentTimes(ManagerPendingReminder::class, 1);

    // Same day again: nothing new.
    app(Reminders::class)->run();
    Notification::assertSentTimes(ManagerPendingReminder::class, 1);

    // Two working days later: still too early; three: reminded again.
    $this->travelTo('2026-06-12 08:00');
    app(Reminders::class)->run();
    Notification::assertSentTimes(ManagerPendingReminder::class, 1);
    $this->travelTo('2026-06-15 08:00');
    app(Reminders::class)->run();
    Notification::assertSentTimes(ManagerPendingReminder::class, 2);
});

test('HR gets one summary with requests waiting for it and other follow-ups; employees get nothing', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $rh = User::factory()->create(['role' => Role::Hr]);
    $payroll = User::factory()->create(['role' => Role::Payroll]);
    $manager = User::factory()->create();
    $employee = User::factory()->create(['name' => 'Sara', 'manager_id' => $manager->id]);
    waitingRequest($employee, '2026-06-01', LeaveStatus::ManagerApproved);
    Attestation::create(['user_id' => $employee->id, 'type' => 'travail', 'status' => 'demandee', 'requested_by' => $employee->id]);

    app(Reminders::class)->run();

    foreach ([$admin, $rh] as $hr) {
        Notification::assertSentTo($hr, HrDailySummary::class, function (HrDailySummary $summary) use ($hr) {
            $data = $summary->toArray($hr);

            return str_contains($data['comment'], 'Demandes en attente de décision : 1')
                && str_contains($data['comment'], 'Attestations demandées : 1');
        });
    }
    Notification::assertNotSentTo($payroll, HrDailySummary::class);
    Notification::assertNotSentTo($employee, HrDailySummary::class);
    // Validated by the manager: it waits for HR, not for the manager.
    Notification::assertNotSentTo($manager, ManagerPendingReminder::class);
});

test('no summary on a day with nothing to do, and no reminders on weekends', function () {
    User::factory()->create(['role' => Role::Hr]);

    app(Reminders::class)->run();
    Notification::assertNothingSent();

    $this->travelTo('2026-06-13 08:00'); // Saturday
    expect(app(Reminders::class)->run()['skipped'])->not->toBeNull();
});

test('sick leave and requests without a manager are in HR\'s summary', function () {
    $hr = User::factory()->create(['role' => Role::Hr]);
    $alone = User::factory()->create(['name' => 'Sans responsable']);
    waitingRequest($alone, '2026-06-02');
    $manager = User::factory()->create();
    $sick = User::factory()->create(['name' => 'Malade', 'manager_id' => $manager->id]);
    waitingRequest($sick, '2026-06-02', LeaveStatus::Pending, 'maladie');

    app(Reminders::class)->run();

    Notification::assertSentTo($hr, HrDailySummary::class, fn (HrDailySummary $s) => str_contains($s->toArray($hr)['comment'], 'Demandes en attente de décision : 2'));
    Notification::assertNotSentTo($manager, ManagerPendingReminder::class);
});

test('employees who have not accepted the notice after 7 days are reminded by e-mail, at most once a week', function () {
    $late = User::factory()->privacyNotAcknowledged()->create(['created_at' => now()->subDays(10)]);
    $new = User::factory()->privacyNotAcknowledged()->create(['created_at' => now()->subDays(2)]);
    User::factory()->create(['created_at' => now()->subDays(30)]); // accepted

    app(Reminders::class)->run();
    Notification::assertSentTo($late, PrivacyNoticeReminder::class);
    Notification::assertNotSentTo($new, PrivacyNoticeReminder::class);

    $this->travelTo('2026-06-12 08:00');
    app(Reminders::class)->run();
    Notification::assertSentTimes(PrivacyNoticeReminder::class, 1);
});

test('the command can list without sending, and is scheduled on weekday mornings', function () {
    $manager = User::factory()->create();
    waitingRequest(User::factory()->create(['manager_id' => $manager->id]), '2026-06-01');

    $this->artisan('reminders:send', ['--dry-run' => true])->assertSuccessful();
    Notification::assertNothingSent();

    $this->artisan('reminders:send')->assertSuccessful();
    Notification::assertSentTo($manager, ManagerPendingReminder::class);

    $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'reminders:send'));
    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 8 * * 1-5')
        ->and($event->timezone)->toBe('Africa/Casablanca');
});
