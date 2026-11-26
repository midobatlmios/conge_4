<?php

use App\Enums\Role;
use App\Models\CompanySetting;
use App\Models\JobOffer;
use App\Models\User;
use App\Notifications\AppNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

test('a clocking correction notifies the manager, and its decision the employee', function () {
    $this->travelTo('2026-06-10 10:00');
    CompanySetting::create(['name' => 'X', 'address' => 'x', 'city' => 'x', 'signatory_name' => 'x', 'attendance_started_on' => '2026-06-01']);
    $manager = User::factory()->create(['role' => Role::User]);
    $employee = User::factory()->create(['role' => Role::User, 'manager_id' => $manager->id, 'hire_date' => '2020-01-01']);

    $this->actingAs($employee)->post('/pointage/corrections', ['date' => '2026-06-09', 'clock_in' => '08:30', 'clock_out' => '18:00', 'reason' => 'Oubli']);
    Notification::assertSentTo($manager, AppNotice::class, fn ($n) => str_contains($n->toArray($manager)['message'], $employee->name));

    $correction = App\Models\AttendanceCorrection::sole();
    $this->actingAs($manager)->put("/pointage/corrections/{$correction->id}/approve");
    Notification::assertSentTo($employee, AppNotice::class, fn ($n) => $n->toArray($employee)['url'] === '/pointage');
});

test('a new application notifies HR and the hiring manager, with a link to the candidate', function () {
    Storage::fake('uploads');
    $rh = User::factory()->create(['role' => Role::Hr]);
    $manager = User::factory()->create(['role' => Role::User]);
    $bystander = User::factory()->create(['role' => Role::User]);
    $offer = JobOffer::create(['title' => 'Comptable', 'contract_type' => 'cdi', 'description' => 'x', 'status' => 'published', 'hiring_manager_id' => $manager->id]);

    $this->post("/carrieres/{$offer->slug}", ['name' => 'Yasmine', 'email' => 'y@example.test', 'consent' => '1', 'cv' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')]);

    Notification::assertSentTo([$rh, $manager], AppNotice::class);
    Notification::assertNotSentTo($bystander, AppNotice::class);
});

test('the notice keeps the French sentence as a key with its values, for translation', function () {
    $data = (new AppNotice('Votre bulletin de paie de :month est disponible.', ['month' => '06/2026'], '/settings/payslips'))->toArray(new User);

    expect($data)->toBe([
        'message' => 'Votre bulletin de paie de 06/2026 est disponible.',
        'key' => 'Votre bulletin de paie de :month est disponible.',
        'params' => ['month' => '06/2026'],
        'url' => '/settings/payslips',
    ]);
});
