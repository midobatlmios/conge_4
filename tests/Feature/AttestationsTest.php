<?php

use App\Enums\AttestationStatus;
use App\Enums\Role;
use App\Models\Attestation;
use App\Models\CompanySetting;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Notifications\AttestationHandled;
use App\Notifications\AttestationRequested;
use App\Support\FrenchNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function attUser(Role $role = Role::User, array $attributes = []): User
{
    return User::factory()->create(['role' => $role, 'hire_date' => '2021-03-01', ...$attributes]);
}

function setUpCompany(): void
{
    CompanySetting::create([
        'name' => 'Omnitrade', 'legal_form' => 'SARL', 'address' => '10 bd Zerktouni', 'city' => 'Casablanca',
        'ice' => '001234567000089', 'signatory_name' => 'Rachid Alaoui', 'signatory_title' => 'DRH',
    ]);
}

/** An employee with everything an attestation prints: CIN and a CDI with a salary. */
function readyEmployee(): User
{
    $employee = attUser(attributes: ['name' => 'Sara Bennani', 'job_title' => 'Comptable']);
    EmployeeProfile::factory()->for($employee)->create(['cin' => 'AB123456', 'gender' => 'femme']);
    $employee->contracts()->create(['type' => 'cdi', 'start_date' => '2021-03-01', 'gross_salary' => '12500']);

    return $employee;
}

test('an employee asks for an attestation; HR is notified', function () {
    Notification::fake();
    $rh = attUser(Role::Hr);
    $paie = attUser(Role::Payroll);
    $employee = attUser();

    $this->actingAs($employee)->post('/attestations', ['type' => 'travail', 'purpose' => 'Visa'])->assertSessionHasNoErrors();

    $attestation = $employee->attestations()->sole();
    expect($attestation->status)->toBe(AttestationStatus::Requested)
        ->and($attestation->purpose)->toBe('Visa');
    Notification::assertSentTo($rh, AttestationRequested::class);
    Notification::assertNotSentTo($paie, AttestationRequested::class);

    // Only one request of each type at a time.
    $this->actingAs($employee)->post('/attestations', ['type' => 'travail'])->assertSessionHasErrors('type');
    $this->actingAs($employee)->post('/attestations', ['type' => 'salaire'])->assertSessionHasNoErrors();
});

test('RH issues a requested attestation: numbered, frozen, employee notified, PDF downloadable', function () {
    Notification::fake();
    setUpCompany();
    $rh = attUser(Role::Hr);
    $employee = readyEmployee();
    $this->actingAs($employee)->post('/attestations', ['type' => 'salaire']);
    $attestation = $employee->attestations()->sole();

    $this->actingAs($rh)->put("/admin/attestations/{$attestation->id}/issue", ['net_salary' => '9800.50'])->assertSessionHasNoErrors();

    $attestation->refresh();
    expect($attestation->status)->toBe(AttestationStatus::Issued)
        ->and($attestation->number)->toBe('ATT-'.now()->year.'-0001')
        ->and($attestation->snapshot['employee']['cin'])->toBe('AB123456')
        ->and($attestation->snapshot['employee']['civility'])->toBe('Madame')
        ->and($attestation->snapshot['salary']['gross'])->toEqual(12500)
        ->and($attestation->snapshot['salary']['gross_words'])->toBe('douze mille cinq cents dirhams')
        ->and($attestation->snapshot['salary']['net'])->toEqual(9800.5);
    Notification::assertSentTo($employee, AttestationHandled::class);

    $response = $this->actingAs($employee)->get("/attestations/{$attestation->id}/pdf")->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');

    // Later changes do not alter an issued attestation.
    $employee->profile->update(['cin' => 'ZZ999999']);
    expect($attestation->fresh()->snapshot['employee']['cin'])->toBe('AB123456');
});

test('numbers follow each other within the year', function () {
    setUpCompany();
    $rh = attUser(Role::Hr);
    $employee = readyEmployee();

    $this->actingAs($rh)->post("/admin/users/{$employee->id}/attestations", ['type' => 'travail'])->assertSessionHasNoErrors();
    $this->actingAs($rh)->post("/admin/users/{$employee->id}/attestations", ['type' => 'travail'])->assertSessionHasNoErrors();

    expect($employee->attestations()->orderBy('number')->pluck('number')->all())
        ->toBe(['ATT-'.now()->year.'-0001', 'ATT-'.now()->year.'-0002']);
});

test('nothing is issued while company details, CIN or salary are missing', function () {
    $rh = attUser(Role::Hr);
    $employee = attUser();

    $this->actingAs($rh)->post("/admin/users/{$employee->id}/attestations", ['type' => 'travail'])
        ->assertSessionHasErrors('attestation');

    setUpCompany();
    EmployeeProfile::factory()->for($employee)->create(['cin' => 'AB654321']);
    $employee->contracts()->create(['type' => 'cdi', 'start_date' => '2021-03-01']);

    // Work certificate OK, salary certificate needs the contract's salary.
    $this->actingAs($rh)->post("/admin/users/{$employee->id}/attestations", ['type' => 'salaire'])->assertSessionHasErrors('attestation');
    $this->actingAs($rh)->post("/admin/users/{$employee->id}/attestations", ['type' => 'travail'])->assertSessionHasNoErrors();

    expect(Attestation::count())->toBe(1);
});

test('the HR list shows what blocks a request', function () {
    $employee = attUser();
    $this->actingAs($employee)->post('/attestations', ['type' => 'salaire']);

    $this->actingAs(attUser(Role::Payroll))
        ->get('/admin/attestations')
        ->assertInertia(fn (Assert $page) => $page
            ->has('attestations', 1)
            ->where('attestations.0.missing', fn ($missing) => collect($missing)->contains("CIN de l'employé")
                && collect($missing)->contains('salaire brut du contrat en cours'))
            ->missing('attestations.0.snapshot'));
});

test('RH refuses with a reason; the employee is told', function () {
    Notification::fake();
    $employee = attUser();
    $this->actingAs($employee)->post('/attestations', ['type' => 'travail']);
    $attestation = $employee->attestations()->sole();

    $this->actingAs(attUser(Role::Hr))->put("/admin/attestations/{$attestation->id}/refuse", [])->assertSessionHasErrors('reason');
    $this->actingAs(attUser(Role::Hr))->put("/admin/attestations/{$attestation->id}/refuse", ['reason' => 'Période d\'essai'])->assertSessionHasNoErrors();

    expect($attestation->fresh()->status)->toBe(AttestationStatus::Refused);
    Notification::assertSentTo($employee, AttestationHandled::class);
});

test('Paie and employees cannot issue; nobody issues their own; others cannot download', function () {
    setUpCompany();
    $rh = attUser(Role::Hr);
    EmployeeProfile::factory()->for($rh)->create(['cin' => 'AB000001']);
    $employee = readyEmployee();
    $this->actingAs($employee)->post('/attestations', ['type' => 'travail']);
    $attestation = $employee->attestations()->sole();

    $this->actingAs(attUser(Role::Payroll))->put("/admin/attestations/{$attestation->id}/issue")->assertForbidden();
    $this->actingAs($employee)->put("/admin/attestations/{$attestation->id}/issue")->assertForbidden();
    $this->actingAs($rh)->post("/admin/users/{$rh->id}/attestations", ['type' => 'travail'])->assertForbidden();

    $this->actingAs($rh)->put("/admin/attestations/{$attestation->id}/issue")->assertSessionHasNoErrors();
    $this->actingAs(attUser())->get("/attestations/{$attestation->id}/pdf")->assertForbidden();
    $this->actingAs(attUser(Role::Payroll))->get("/attestations/{$attestation->id}/pdf")->assertOk();

    // Already handled.
    $this->actingAs($rh)->put("/admin/attestations/{$attestation->id}/refuse", ['reason' => 'x'])->assertStatus(422);
});

test('a former employee gets "a été employé(e) du … au …"', function () {
    setUpCompany();
    $employee = readyEmployee();
    $employee->contracts()->sole()->update(['end_date' => '2025-06-30']);
    $employee->forceFill(['deactivated_at' => '2025-07-01'])->save();

    $this->actingAs(attUser(Role::Hr))->post("/admin/users/{$employee->id}/attestations", ['type' => 'travail'])->assertSessionHasNoErrors();

    $snapshot = $employee->attestations()->sole()->snapshot;
    expect($snapshot['employee']['start'])->toBe('2021-03-01')
        ->and($snapshot['employee']['left'])->toBe('2025-06-30');
});

test('the employee sees their attestations in settings', function () {
    $employee = attUser();
    $this->actingAs($employee)->post('/attestations', ['type' => 'travail']);

    $this->actingAs($employee)
        ->get('/settings/attestations')
        ->assertInertia(fn (Assert $page) => $page->component('settings/attestations')->has('attestations', 1)->has('types', 2));
});

test('HR edits the company details and logo; Paie cannot', function () {
    Storage::fake(CompanySetting::DISK);
    $rh = attUser(Role::Hr);
    $payload = ['name' => 'Omnitrade', 'address' => '10 bd Zerktouni', 'city' => 'Casablanca', 'signatory_name' => 'R. Alaoui', 'ice' => '123'];

    $this->actingAs($rh)->put('/admin/company', $payload)->assertSessionHasErrors('ice');
    $this->actingAs($rh)->put('/admin/company', [...$payload, 'ice' => '001234567000089'])->assertSessionHasNoErrors();
    $this->actingAs($rh)->post('/admin/company/logo', ['logo' => UploadedFile::fake()->image('logo.png')])->assertSessionHasNoErrors();
    $this->actingAs($rh)->post('/admin/company/logo', ['logo' => UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml')])->assertSessionHasErrors('logo');

    expect(CompanySetting::current()->missingRequired())->toBe([])
        ->and(CompanySetting::current()->logoDataUri())->toStartWith('data:image/png;base64,');

    $this->actingAs(attUser(Role::Payroll))->put('/admin/company', $payload)->assertForbidden();
    $this->actingAs(attUser(Role::Payroll))->get('/admin/company')->assertForbidden();
});

test('the contract salary is stored encrypted', function () {
    $rh = attUser(Role::Hr);
    $employee = attUser();

    $this->actingAs($rh)->post("/admin/users/{$employee->id}/contracts", ['type' => 'cdi', 'start_date' => '2024-01-01', 'gross_salary' => '15000'])
        ->assertSessionHasNoErrors();

    expect($employee->contracts()->sole()->gross_salary)->toBe('15000')
        ->and(DB::table('employment_contracts')->value('gross_salary'))->not->toContain('15000');
});

test('amounts are written in French words', function (float $amount, string $words) {
    expect(FrenchNumber::dirhams($amount))->toBe($words);
})->with([
    [1, 'un dirham'],
    [21, 'vingt et un dirhams'],
    [71, 'soixante et onze dirhams'],
    [80, 'quatre-vingts dirhams'],
    [99, 'quatre-vingt-dix-neuf dirhams'],
    [200, 'deux cents dirhams'],
    [1000, 'mille dirhams'],
    [3280, 'trois mille deux cent quatre-vingts dirhams'],
    [12500, 'douze mille cinq cents dirhams'],
    [200000, 'deux cent mille dirhams'],
    [1250000.5, 'un million deux cent cinquante mille dirhams et cinquante centimes'],
]);
