<?php

use App\Enums\Role;
use App\Models\Attestation;
use App\Models\Demande;
use App\Models\DemandeEvent;
use App\Models\EmployeeProfile;
use App\Models\PersonalDataAccess;
use App\Models\User;
use App\Services\DataRetention;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('uploads'));

function withRoleDp(Role $role, array $attributes = []): User
{
    return User::factory()->create(['role' => $role, ...$attributes]);
}

// ---------------------------------------------------------------- notice

test('until they accept the notice, employees are sent to it; afterwards they use the app', function () {
    $employee = User::factory()->privacyNotAcknowledged()->create();

    $this->actingAs($employee)->get('/dashboard')->assertRedirect('/protection-des-donnees');
    $this->actingAs($employee)->get('/protection-des-donnees')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Privacy')->where('acknowledgedAt', null));

    $this->actingAs($employee)->post('/protection-des-donnees/accept')->assertRedirect('/dashboard');

    expect($employee->fresh()->privacy_acknowledged_at)->not->toBeNull();
    $this->actingAs($employee)->get('/dashboard')->assertOk();
});

test('signing out still works before accepting the notice', function () {
    $this->actingAs(User::factory()->privacyNotAcknowledged()->create())->post('/logout')->assertRedirect('/');
    $this->assertGuest();
});

// ---------------------------------------------------------------- access log

test('HR viewing a file, changing data or opening documents is logged; one\'s own views are not', function () {
    $rh = withRoleDp(Role::Hr);
    $employee = withRoleDp(Role::User);

    $this->actingAs($rh)->get("/admin/users/{$employee->id}");
    $this->actingAs($rh)->put("/admin/users/{$employee->id}/personal-data", ['city' => 'Rabat', 'cin' => 'AB123456']);
    $this->actingAs($rh)->post("/admin/users/{$employee->id}/documents", ['category' => 'cin', 'file' => UploadedFile::fake()->create('cin.pdf', 50, 'application/pdf')]);
    $document = $employee->documents()->sole();
    $this->actingAs($rh)->get("/employee-documents/{$document->id}");
    $this->actingAs($employee)->get("/employee-documents/{$document->id}"); // own: not logged

    $log = PersonalDataAccess::where('subject_id', $employee->id)->orderBy('id')->get();
    expect($log->pluck('action')->all())->toBe(['view_file', 'update_profile', 'add_document', 'open_document'])
        ->and($log->every(fn ($entry) => $entry->actor_id === $rh->id))->toBeTrue()
        // Field names, never the values.
        ->and($log[1]->detail)->toContain('CIN')
        ->and($log[1]->detail)->not->toContain('AB123456');
});

test('opening someone\'s medical certificate is logged', function () {
    $employee = withRoleDp(Role::User);
    $leave = Demande::create(['user_id' => $employee->id, 'type_conge' => 'maladie', 'date_debut' => '2026-01-05', 'date_fin' => '2026-01-07', 'date_demande' => '2026-01-05', 'etat' => 'acceptée']);
    Storage::disk('uploads')->put('documents/x.pdf', '%PDF');
    $certificate = $leave->documents()->create(['original_name' => 'certificat.pdf', 'path' => 'documents/x.pdf', 'mime_type' => 'application/pdf', 'size' => 4]);

    $this->actingAs(withRoleDp(Role::Payroll))->get("/documents/{$certificate->id}")->assertOk();

    expect(PersonalDataAccess::where('action', 'open_certificate')->where('subject_id', $employee->id)->exists())->toBeTrue();
});

test('only the Administrateur reads the access log and the register', function () {
    PersonalDataAccess::record(withRoleDp(Role::Hr), withRoleDp(Role::User), 'view_file');

    $this->actingAs(withRoleDp(Role::Admin))->get('/admin/privacy/log')
        ->assertInertia(fn (Assert $page) => $page->component('Admin/PrivacyLog')->has('entries.data', 1));
    $this->actingAs(withRoleDp(Role::Admin))->get('/admin/privacy')->assertOk();

    foreach ([Role::Hr, Role::Payroll, Role::User] as $role) {
        $this->actingAs(withRoleDp($role))->get('/admin/privacy/log')->assertForbidden();
        $this->actingAs(withRoleDp($role))->get('/admin/privacy')->assertForbidden();
    }
});

// ---------------------------------------------------------------- export

test('an employee downloads everything held about them; HR can export it too (logged)', function () {
    $employee = withRoleDp(Role::User, ['name' => 'Sara Bennani']);
    EmployeeProfile::factory()->for($employee)->create(['cin' => 'AB654321']);
    $employee->contracts()->create(['type' => 'cdi', 'start_date' => '2024-01-01', 'gross_salary' => '12000']);
    Storage::disk('uploads')->put('employee-documents/1/a.pdf', '%PDF-contrat');
    $employee->documents()->create(['category' => 'contrat', 'original_name' => 'contrat.pdf', 'path' => 'employee-documents/1/a.pdf', 'mime_type' => 'application/pdf', 'size' => 12]);

    $response = $this->actingAs($employee)->get('/protection-des-donnees/export')->assertOk();
    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());
    $data = json_decode($zip->getFromName('donnees.json'), true);

    expect($data['compte']['nom'])->toBe('Sara Bennani')
        ->and($data['donnees_personnelles']['cin'])->toBe('AB654321')
        ->and($data['contrats'][0]['salaire_brut_mensuel'])->toEqual(12000)
        ->and($zip->getFromName('resume.html'))->toContain('Sara Bennani')
        ->and($zip->locateName('documents/dossier/1-contrat.pdf'))->not->toBeFalse();
    expect(PersonalDataAccess::where('action', 'export')->exists())->toBeFalse(); // own export

    $this->actingAs(withRoleDp(Role::Hr))->get("/admin/users/{$employee->id}/data-export")->assertOk();
    $this->actingAs(withRoleDp(Role::Payroll))->get("/admin/users/{$employee->id}/data-export")->assertForbidden();
    expect(PersonalDataAccess::where('action', 'export')->where('subject_id', $employee->id)->count())->toBe(1);
});

// ---------------------------------------------------------------- retention

test('medical certificates are deleted 2 years after the leave', function () {
    $employee = withRoleDp(Role::User);
    $old = Demande::create(['user_id' => $employee->id, 'type_conge' => 'maladie', 'date_debut' => now()->subYears(3)->toDateString(), 'date_fin' => now()->subYears(3)->toDateString(), 'date_demande' => now()->subYears(3)->toDateString(), 'etat' => 'acceptée']);
    $recent = Demande::create(['user_id' => $employee->id, 'type_conge' => 'maladie', 'date_debut' => now()->subMonth()->toDateString(), 'date_fin' => now()->subMonth()->toDateString(), 'date_demande' => now()->subMonth()->toDateString(), 'etat' => 'acceptée']);
    foreach ([$old, $recent] as $leave) {
        Storage::disk('uploads')->put("documents/{$leave->id}.pdf", '%PDF');
        $leave->documents()->create(['original_name' => 'c.pdf', 'path' => "documents/{$leave->id}.pdf", 'mime_type' => 'application/pdf', 'size' => 4]);
    }

    $done = app(DataRetention::class)->apply(null);

    expect($done['certificates'])->toBe(1)
        ->and($old->documents()->count())->toBe(0)
        ->and($recent->documents()->count())->toBe(1);
    Storage::disk('uploads')->assertMissing("documents/{$old->id}.pdf");
});

test('5 years after departure personal data goes; 10 years after, the person is anonymized but the leave history stays', function () {
    $leftLongAgo = withRoleDp(Role::User, ['name' => 'Hamza Ancien', 'deactivated_at' => now()->subYears(11)]);
    $leftRecently = withRoleDp(Role::User, ['deactivated_at' => now()->subYears(6)]);
    $stillHere = withRoleDp(Role::User);
    foreach ([$leftLongAgo, $leftRecently, $stillHere] as $person) {
        EmployeeProfile::factory()->for($person)->create();
        Attestation::create(['user_id' => $person->id, 'type' => 'travail', 'status' => 'demandee', 'requested_by' => $person->id]);
    }
    $leave = Demande::create(['user_id' => $leftLongAgo->id, 'type_conge' => 'annuel', 'date_debut' => now()->subYears(12)->toDateString(), 'date_fin' => now()->subYears(12)->toDateString(), 'date_demande' => now()->subYears(12)->toDateString(), 'etat' => 'acceptée', 'comment' => 'Voyage à Fès avec ma famille']);

    $this->actingAs(withRoleDp(Role::Admin))->post('/admin/privacy/retention')->assertSessionHasNoErrors();

    $leftLongAgo->refresh();
    expect($leftLongAgo->anonymized_at)->not->toBeNull()
        ->and($leftLongAgo->name)->toBe("Ancien employé n° {$leftLongAgo->id}")
        ->and($leftLongAgo->email)->toEndWith('@anonymise.invalid')
        ->and($leftLongAgo->profile)->toBeNull()
        ->and($leave->fresh())->not->toBeNull()
        ->and($leave->fresh()->comment)->toBeNull()
        ->and(DemandeEvent::where('employee_id', $leftLongAgo->id)->whereNotNull('changes')->exists())->toBeFalse();

    expect($leftRecently->fresh()->profile)->toBeNull()
        ->and($leftRecently->fresh()->anonymized_at)->toBeNull()
        ->and($leftRecently->attestations()->count())->toBe(0)
        ->and($stillHere->fresh()->profile)->not->toBeNull()
        ->and($stillHere->attestations()->count())->toBe(1);

    expect(PersonalDataAccess::where('action', 'retention')->count())->toBe(3);
});

test('only the Administrateur applies retention; the command lists without deleting unless asked', function () {
    withRoleDp(Role::User, ['deactivated_at' => now()->subYears(6)])->profile()->create(['city' => 'Rabat']);

    $this->actingAs(withRoleDp(Role::Hr))->post('/admin/privacy/retention')->assertForbidden();

    $this->artisan('privacy:retention')->assertSuccessful();
    expect(EmployeeProfile::count())->toBe(1);

    $this->artisan('privacy:retention', ['--apply' => true])->assertSuccessful();
    expect(EmployeeProfile::count())->toBe(0);
});
