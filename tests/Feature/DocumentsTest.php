<?php

use App\Models\Demande;
use App\Models\DemandeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-02-02 10:00:00');
    Storage::fake(DemandeDocument::DISK);
});

function naissanceFor(User $employee, string $etat = 'en attente'): Demande
{
    return Demande::create([
        'user_id' => $employee->id,
        'date_demande' => '2026-02-01',
        'date_debut' => '2026-03-02',
        'date_fin' => '2026-03-04',
        'type_conge' => 'naissance',
        'etat' => $etat,
    ]);
}

function acceptNaissance(User $admin, Demande $demande)
{
    return test()->actingAs($admin)->put("/demandes/{$demande->id}", [
        'date_debut' => '2026-03-02', 'date_fin' => '2026-03-04', 'type_conge' => 'naissance', 'etat' => 'acceptée',
    ]);
}

test('HR cannot accept a naissance without a certificate, and can once one is attached', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = User::factory()->create(['role' => 'user']);
    $demande = naissanceFor($employee);

    acceptNaissance($admin, $demande)->assertSessionHasErrors('etat');

    $this->actingAs($employee)
        ->post("/demandes/{$demande->id}/documents", ['document' => UploadedFile::fake()->create('acte.pdf', 200, 'application/pdf')])
        ->assertSessionHasNoErrors();

    acceptNaissance($admin, $demande)->assertSessionHasNoErrors();
});

test('a certificate can be sent with the request itself, and is stored privately', function () {
    $employee = User::factory()->create(['role' => 'user']);

    $this->actingAs($employee)
        ->post('/demandes', [
            'date_debut' => '2026-03-02', 'date_fin' => '2026-03-04', 'type_conge' => 'naissance',
            'document' => UploadedFile::fake()->image('acte.jpg'),
        ])
        ->assertSessionHasNoErrors();

    $document = Demande::first()->documents()->first();
    expect($document->original_name)->toBe('acte.jpg')
        ->and($document->path)->toStartWith('documents/')
        ->and($document->path)->not->toContain('acte'); // random name on disk
    Storage::disk(DemandeDocument::DISK)->assertExists($document->path);
});

test('only PDF and images up to the size limit are accepted', function () {
    $employee = User::factory()->create(['role' => 'user']);
    $demande = naissanceFor($employee);

    $this->actingAs($employee)
        ->post("/demandes/{$demande->id}/documents", ['document' => UploadedFile::fake()->create('virus.exe', 10)])
        ->assertSessionHasErrors('document');
    $this->actingAs($employee)
        ->post("/demandes/{$demande->id}/documents", ['document' => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')])
        ->assertSessionHasErrors('document');

    expect($demande->documents()->count())->toBe(0);
});

test('the employee and HR can open a document; the manager and colleagues cannot', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $manager = User::factory()->create(['role' => 'user']);
    $employee = User::factory()->create(['role' => 'user', 'manager_id' => $manager->id]);
    $colleague = User::factory()->create(['role' => 'user']);
    $demande = naissanceFor($employee);
    $this->actingAs($employee)->post("/demandes/{$demande->id}/documents", ['document' => UploadedFile::fake()->create('acte.pdf', 20, 'application/pdf')]);
    $document = $demande->documents()->first();

    $this->actingAs($employee)->get("/documents/{$document->id}")->assertOk();
    $this->actingAs($admin)->get("/documents/{$document->id}")->assertOk();
    $this->actingAs($manager)->get("/documents/{$document->id}")->assertForbidden();
    $this->actingAs($colleague)->get("/documents/{$document->id}")->assertForbidden();
});

test('the employee can only change documents while the request waits for a decision', function () {
    $employee = User::factory()->create(['role' => 'user']);
    $accepted = naissanceFor($employee, 'acceptée');

    $this->actingAs($employee)
        ->post("/demandes/{$accepted->id}/documents", ['document' => UploadedFile::fake()->create('acte.pdf', 20, 'application/pdf')])
        ->assertForbidden();
});

test('removing a document or the whole request deletes the file, and the history records it', function () {
    $employee = User::factory()->create(['role' => 'user']);
    $demande = naissanceFor($employee);
    $this->actingAs($employee)->post("/demandes/{$demande->id}/documents", ['document' => UploadedFile::fake()->create('a.pdf', 20, 'application/pdf')]);
    $this->actingAs($employee)->post("/demandes/{$demande->id}/documents", ['document' => UploadedFile::fake()->create('b.pdf', 20, 'application/pdf')]);
    [$first, $second] = $demande->documents()->get()->all();

    $this->actingAs($employee)->delete("/documents/{$first->id}")->assertSessionHasNoErrors();
    Storage::disk(DemandeDocument::DISK)->assertMissing($first->path);

    $this->actingAs($employee)->delete("/demandes/{$demande->id}");
    Storage::disk(DemandeDocument::DISK)->assertMissing($second->path);

    expect(\App\Models\DemandeEvent::where('demande_id', $demande->id)->pluck('action')->all())
        ->toBe(['created', 'document_added', 'document_added', 'document_removed', 'deleted']);
});

test('annual leave needs no certificate', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $employee = User::factory()->create(['role' => 'user']);
    $demande = Demande::create([
        'user_id' => $employee->id, 'date_demande' => '2026-02-01', 'date_debut' => '2026-03-02',
        'date_fin' => '2026-03-03', 'type_conge' => 'annuel', 'etat' => 'en attente',
    ]);

    $this->actingAs($admin)
        ->put("/demandes/{$demande->id}", ['date_debut' => '2026-03-02', 'date_fin' => '2026-03-03', 'type_conge' => 'annuel', 'etat' => 'acceptée'])
        ->assertSessionHasNoErrors();
});
