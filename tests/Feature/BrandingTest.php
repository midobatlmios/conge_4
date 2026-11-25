<?php

use App\Enums\Role;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake(CompanySetting::DISK));

/** @return array<string, string> */
function companyPayload(array $overrides = []): array
{
    return ['name' => 'Atlas Distribution', 'address' => '45 bd Abdelmoumen', 'city' => 'Casablanca', 'signatory_name' => 'N. El Amrani', ...$overrides];
}

test('before the company is set up, pages keep the app name and the default color', function () {
    $this->get('/login')->assertInertia(fn (Assert $page) => $page
        ->where('brand.name', null)
        ->where('brand.color', CompanySetting::DEFAULT_COLOR)
        ->where('brand.logo', null));
});

test('the company name, initials and color reach every page, the sign-in page included', function () {
    $this->actingAs(User::factory()->create(['role' => Role::Hr]))
        ->put('/admin/company', companyPayload(['brand_color' => '#1D4E89']))
        ->assertSessionHasNoErrors();
    auth()->logout();

    $this->get('/login')->assertInertia(fn (Assert $page) => $page
        ->where('brand.name', 'Atlas Distribution')
        ->where('brand.initials', 'AD')
        ->where('brand.color', '#1D4E89'));

    $this->actingAs(User::factory()->create())->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('brand.name', 'Atlas Distribution'));
});

test('the color must be #RRGGBB, and only Admin or RH change it', function () {
    $this->actingAs(User::factory()->create(['role' => Role::Hr]))
        ->put('/admin/company', companyPayload(['brand_color' => 'red']))
        ->assertSessionHasErrors('brand_color');

    $this->actingAs(User::factory()->create(['role' => Role::Payroll]))
        ->put('/admin/company', companyPayload(['brand_color' => '#000000']))
        ->assertForbidden();
});

test('the logo is served publicly for the sign-in page, with a URL that changes after each upload', function () {
    $this->get('/brand/logo')->assertNotFound();

    $hr = User::factory()->create(['role' => Role::Hr]);
    $this->actingAs($hr)->put('/admin/company', companyPayload());
    $this->actingAs($hr)->post('/admin/company/logo', ['logo' => UploadedFile::fake()->image('logo.png', 120, 60)]);
    $first = CompanySetting::current()->brand()['logo'];
    auth()->logout();

    expect($first)->toContain('/brand/logo?v=');
    $this->get('/brand/logo')->assertOk();

    $this->travel(1)->minutes();
    $this->actingAs($hr)->post('/admin/company/logo', ['logo' => UploadedFile::fake()->image('logo2.png', 120, 60)]);
    expect(CompanySetting::current()->brand()['logo'])->not->toBe($first);
});

test('initials skip small words and legal forms', function (string $name, string $initials) {
    expect((new CompanySetting(['name' => $name]))->initials())->toBe($initials);
})->with([
    ['Atlas Distribution', 'AD'],
    ['Société de Transport du Nord', 'ST'],
    ['Omnitrade', 'O'],
    ["L'Atelier Marocain SARL", 'AM'],
    ['', ''],
]);

test('attestations use the brand color, older ones keep the original red', function () {
    $snapshot = fn (?string $color) => [
        'title' => 'Attestation de travail', 'number' => 'ATT-2026-0001', 'issued_on' => '2026-01-05', 'purpose' => null, 'salary' => null,
        'company' => [...array_fill_keys(CompanySetting::FIELDS, null), ...companyPayload(), 'brand_color' => $color],
        'employee' => ['civility' => null, 'name' => 'Sara', 'cin' => 'AB1', 'cnss_number' => null, 'matricule' => null, 'job_title' => null, 'contract' => null, 'category' => null, 'start' => '2020-01-01', 'left' => null],
    ];

    expect(view('pdf.attestation', ['a' => $snapshot('#1D4E89'), 'logo' => null])->render())->toContain('solid #1D4E89');

    $old = $snapshot(null);
    unset($old['company']['brand_color']);
    expect(view('pdf.attestation', ['a' => $old, 'logo' => null])->render())->toContain('solid '.CompanySetting::DEFAULT_COLOR);
});
