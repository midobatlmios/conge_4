<?php

use App\Enums\Role;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake(EmployeeDocument::DISK));

function folderUser(Role $role = Role::User): User
{
    return User::factory()->create(['role' => $role, 'hire_date' => '2020-01-01']);
}

function uploadTo(User $actor, User $employee, array $overrides = [])
{
    return test()->actingAs($actor)->post("/admin/users/{$employee->id}/documents", [
        'category' => 'contrat',
        'file' => UploadedFile::fake()->create('contrat.pdf', 200, 'application/pdf'),
        ...$overrides,
    ]);
}

test('RH adds a document to an employee folder, stored privately under a random name', function () {
    $rh = folderUser(Role::Hr);
    $employee = folderUser();

    uploadTo($rh, $employee, ['title' => 'CDI 2024', 'expires_on' => '2030-01-01'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $document = $employee->documents()->sole();
    expect($document->title)->toBe('CDI 2024')
        ->and($document->original_name)->toBe('contrat.pdf')
        ->and($document->uploaded_by)->toBe($rh->id)
        ->and($document->path)->not->toContain('contrat')
        ->and($document->expires_on->toDateString())->toBe('2030-01-01');
    Storage::disk(EmployeeDocument::DISK)->assertExists($document->path);
});

test('only PDF and images up to the size limit are accepted', function () {
    $rh = folderUser(Role::Hr);
    $employee = folderUser();

    uploadTo($rh, $employee, ['file' => UploadedFile::fake()->create('macro.exe', 10)])->assertSessionHasErrors('file');
    uploadTo($rh, $employee, ['file' => UploadedFile::fake()->create('big.pdf', 20000, 'application/pdf')])->assertSessionHasErrors('file');
    uploadTo($rh, $employee, ['category' => 'inconnu'])->assertSessionHasErrors('category');

    expect($employee->documents()->count())->toBe(0);
});

test('Paie, employees and managers cannot add or remove documents; nobody manages their own folder', function () {
    $rh = folderUser(Role::Hr);
    $manager = folderUser();
    $employee = folderUser();
    $employee->update(['manager_id' => $manager->id]);
    uploadTo($rh, $employee);
    $document = $employee->documents()->sole();

    foreach ([folderUser(Role::Payroll), $manager, $employee] as $user) {
        uploadTo($user, $employee)->assertForbidden();
        $this->actingAs($user)->delete("/admin/employee-documents/{$document->id}")->assertForbidden();
    }

    uploadTo($rh, $rh)->assertForbidden();

    expect($employee->documents()->count())->toBe(1);
});

test('deleting a document removes its file', function () {
    $rh = folderUser(Role::Hr);
    $employee = folderUser();
    uploadTo($rh, $employee);
    $document = $employee->documents()->sole();

    $this->actingAs($rh)->delete("/admin/employee-documents/{$document->id}")->assertRedirect();

    expect(EmployeeDocument::count())->toBe(0);
    Storage::disk(EmployeeDocument::DISK)->assertMissing($document->path);
});

test('HR (incl. Paie) and the employee open a document; colleagues and managers cannot', function () {
    $manager = folderUser();
    $employee = folderUser();
    $employee->update(['manager_id' => $manager->id]);
    uploadTo(folderUser(Role::Hr), $employee);
    $document = $employee->documents()->sole();

    foreach ([folderUser(Role::Payroll), folderUser(Role::Admin), $employee] as $user) {
        $this->actingAs($user)->get("/employee-documents/{$document->id}")->assertOk();
    }

    foreach ([$manager, folderUser()] as $user) {
        $this->actingAs($user)->get("/employee-documents/{$document->id}")->assertForbidden();
    }
});

test('the employee sees their own folder, read-only, in settings', function () {
    $employee = folderUser();
    uploadTo(folderUser(Role::Hr), $employee, ['title' => 'Mon contrat']);
    uploadTo(folderUser(Role::Hr), folderUser(), ['title' => 'Pas le mien']);

    $this->actingAs($employee)
        ->get('/settings/documents')
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/documents')
            ->has('documents', 1)
            ->where('documents.0.title', 'Mon contrat')
            ->missing('documents.0.path'));
});

test('documents expiring within 30 days are flagged on the employee page and the overview', function () {
    $this->travelTo('2026-06-01');
    $rh = folderUser(Role::Hr);
    $employee = folderUser();
    uploadTo($rh, $employee, ['category' => 'cin', 'expires_on' => '2026-05-20']);
    uploadTo($rh, $employee, ['category' => 'carte_sejour', 'expires_on' => '2026-06-20']);
    uploadTo($rh, $employee, ['category' => 'aptitude_medicale', 'expires_on' => '2026-12-31']);

    $this->actingAs($rh)
        ->get("/admin/users/{$employee->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->has('documents', 3)
            ->where('documents', fn ($documents) => collect($documents)->pluck('expiry', 'category')->sortKeys()->all() === [
                'aptitude_medicale' => null,
                'carte_sejour' => 'soon',
                'cin' => 'expired',
            ]));

    $this->actingAs(folderUser(Role::Payroll))
        ->get('/admin/overview')
        ->assertInertia(fn (Assert $page) => $page
            ->has('expiringDocuments', 2)
            ->where('expiringDocuments.0.label', 'Copie CIN')
            ->where('expiringDocuments.0.expiry', 'expired')
            ->where('expiringDocuments.1.expiry', 'soon'));
});

test('documents of employees who left are not flagged on the overview', function () {
    $employee = folderUser();
    uploadTo(folderUser(Role::Hr), $employee, ['expires_on' => now()->subDay()->toDateString()]);
    $employee->forceFill(['deactivated_at' => now()])->save();

    $this->actingAs(folderUser(Role::Hr))
        ->get('/admin/overview')
        ->assertInertia(fn (Assert $page) => $page->has('expiringDocuments', 0));
});
