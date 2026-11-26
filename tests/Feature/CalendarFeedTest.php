<?php

use App\Models\Demande;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->travelTo('2026-06-10 09:00'));

function feedLeave(User $user, string $type, string $start, string $end, string $status = 'acceptée', array $extra = []): Demande
{
    return Demande::create(['user_id' => $user->id, 'type_conge' => $type, 'date_debut' => $start, 'date_fin' => $end, 'date_demande' => '2026-06-01', 'etat' => $status, ...$extra]);
}

function subscribe(User $user): string
{
    test()->actingAs($user)->post('/settings/calendar')->assertRedirect();

    return $user->fresh()->calendar_token;
}

test('an employee creates their links and their calendar app reads their leave without signing in', function () {
    $employee = User::factory()->create();
    feedLeave($employee, 'annuel', '2026-07-06', '2026-07-10');
    feedLeave($employee, 'recuperation', '2026-08-03', '2026-08-03', 'en attente');
    feedLeave($employee, 'annuel', '2026-09-01', '2026-09-02', 'refusée');

    $token = subscribe($employee);
    $this->actingAs($employee)->get('/settings/calendar')->assertInertia(fn (Assert $page) => $page
        ->component('settings/calendar')
        ->where('links.me', fn ($url) => str_ends_with($url, "/calendrier/abonnement/{$token}/me.ics"))
        ->where('links.team', null));
    auth()->logout();

    $response = $this->get("/calendrier/abonnement/{$token}/me.ics")->assertOk();
    $ics = $response->getContent();

    expect($response->headers->get('content-type'))->toContain('text/calendar')
        ->and($ics)->toStartWith("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n")
        ->and($ics)->toEndWith("END:VCALENDAR\r\n")
        ->and(substr_count($ics, 'BEGIN:VEVENT'))->toBe(2)
        // All-day, end exclusive: 6 to 10 July is DTEND 11 July.
        ->and($ics)->toContain("DTSTART;VALUE=DATE:20260706\r\nDTEND;VALUE=DATE:20260711")
        ->and($ics)->toContain('SUMMARY:Congé annuel')
        ->and($ics)->toContain('SUMMARY:Récupération (en attente)')
        ->and($ics)->toContain('STATUS:TENTATIVE');

    // Every line is at most 75 octets (RFC 5545).
    foreach (explode("\r\n", $ics) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75);
    }
});

test('a manager\'s team calendar shows names and "absent(e)", never the type', function () {
    $manager = User::factory()->create();
    $sara = User::factory()->create(['name' => 'Sara Bennani', 'manager_id' => $manager->id]);
    feedLeave($sara, 'maladie', '2026-06-08', '2026-06-12');
    feedLeave($sara, 'annuel', '2026-07-06', '2026-07-07', 'en attente'); // not accepted: not shown
    $gone = User::factory()->create(['manager_id' => $manager->id, 'deactivated_at' => now()]);
    feedLeave($gone, 'annuel', '2026-06-15', '2026-06-16');

    $token = subscribe($manager);
    $ics = $this->get("/calendrier/abonnement/{$token}/team.ics")->assertOk()->getContent();

    expect(substr_count($ics, 'BEGIN:VEVENT'))->toBe(1)
        ->and($ics)->toContain('SUMMARY:Sara Bennani – absent(e)')
        ->and($ics)->not->toContain('maladie')
        ->and($ics)->not->toContain('SUMMARY:Congé');
});

test('a delegate\'s team calendar includes the delegated team during the delegation', function () {
    $manager = User::factory()->create();
    $delegate = User::factory()->create();
    $member = User::factory()->create(['name' => 'Elias', 'manager_id' => $manager->id]);
    feedLeave($member, 'annuel', '2026-06-15', '2026-06-16');
    $manager->delegationsGiven()->create(['delegate_id' => $delegate->id, 'starts_on' => '2026-06-10', 'ends_on' => '2026-06-20']);

    $token = subscribe($delegate);
    expect($this->get("/calendrier/abonnement/{$token}/team.ics")->assertOk()->getContent())->toContain('SUMMARY:Elias – absent(e)');
});

test('regenerating or disabling cuts off the old links; wrong tokens and people without a team get nothing', function () {
    $employee = User::factory()->create();
    $old = subscribe($employee);
    $new = subscribe($employee);

    expect($new)->not->toBe($old);
    $this->get("/calendrier/abonnement/{$old}/me.ics")->assertNotFound();
    $this->get("/calendrier/abonnement/{$new}/me.ics")->assertOk();
    $this->get("/calendrier/abonnement/{$new}/team.ics")->assertNotFound(); // no team

    $this->actingAs($employee)->delete('/settings/calendar');
    $this->get("/calendrier/abonnement/{$new}/me.ics")->assertNotFound();

    $this->get('/calendrier/abonnement/'.str_repeat('a', 48).'/me.ics')->assertNotFound();
});

test('a deactivated employee\'s links stop working', function () {
    $employee = User::factory()->create();
    $token = subscribe($employee);
    $employee->forceFill(['deactivated_at' => now()])->save();
    auth()->logout(); // calendar apps have no session

    $this->get("/calendrier/abonnement/{$token}/me.ics")->assertNotFound();
});
