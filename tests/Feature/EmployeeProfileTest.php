<?php

use App\Enums\Gender;
use App\Models\EmployeeProfile;
use App\Models\User;
use Database\Factories\EmployeeProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function hrAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

test('HR fills in personal data, identifiers are normalised and sensitive fields encrypted', function () {
    $admin = hrAdmin();
    $employee = User::factory()->create();
    $rib = EmployeeProfileFactory::rib();

    $this->actingAs($admin)
        ->put("/admin/users/{$employee->id}/personal-data", [
            'gender' => 'femme',
            'birth_date' => '1990-05-12',
            'cin' => 'ab 123456',
            'cnss_number' => '123 456 789',
            'marital_status' => 'marie',
            'children_count' => 2,
            'address' => '12 rue des Fleurs',
            'city' => 'Rabat',
            'rib' => chunk_split($rib, 4, ' '),
            'bank_name' => 'CIH Bank',
            'emergency_contact_name' => 'Karim',
            'emergency_contact_relation' => 'Conjoint',
            'emergency_contact_phone' => '06 12 34 56 78',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $profile = $employee->fresh()->profile;
    expect($profile->cin)->toBe('AB123456')
        ->and($profile->cnss_number)->toBe('123456789')
        ->and($profile->rib)->toBe($rib)
        ->and($profile->gender)->toBe(Gender::Female)
        ->and($profile->children_count)->toBe(2)
        ->and($profile->updated_by)->toBe($admin->id);

    // Stored encrypted: the raw column does not contain the value.
    $raw = DB::table('employee_profiles')->where('user_id', $employee->id)->first();
    expect($raw->cin)->not->toContain('AB123456')
        ->and($raw->rib)->not->toContain($rib)
        ->and($raw->address)->not->toContain('Fleurs');
});

test('a RIB with a wrong key, a bad CNSS number or CIN is refused', function () {
    $employee = User::factory()->create();
    $rib = EmployeeProfileFactory::rib();
    $wrongKey = substr($rib, 0, 22).str_pad((string) ((((int) substr($rib, 22)) + 1) % 100), 2, '0', STR_PAD_LEFT);

    $this->actingAs(hrAdmin())
        ->put("/admin/users/{$employee->id}/personal-data", [
            'rib' => $wrongKey,
            'cnss_number' => '1234',
            'cin' => '123456',
            'birth_date' => now()->subYears(10)->toDateString(),
        ])
        ->assertSessionHasErrors(['rib', 'cnss_number', 'cin', 'birth_date']);

    expect($employee->fresh()->profile)->toBeNull();
});

test('the same CIN cannot belong to two employees', function () {
    $first = EmployeeProfile::factory()->create(['cin' => 'BE987654']);
    $other = User::factory()->create();
    $admin = hrAdmin();

    $this->actingAs($admin)
        ->put("/admin/users/{$other->id}/personal-data", ['cin' => 'be987654'])
        ->assertSessionHasErrors('cin');

    // Saving the owner's own CIN again is fine.
    $this->actingAs($admin)
        ->put("/admin/users/{$first->user_id}/personal-data", ['cin' => 'BE987654'])
        ->assertSessionHasNoErrors();
});

test('HR sees the employee page with personal data and what is missing', function () {
    $employee = User::factory()->create();
    EmployeeProfile::factory()->for($employee)->create(['cin' => 'AB111111', 'rib' => null]);

    $this->actingAs(hrAdmin())
        ->get("/admin/users/{$employee->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Employee')
            ->where('employee.id', $employee->id)
            ->missing('employee.profile')
            ->where('profile.cin', 'AB111111')
            ->missing('profile.cin_hash')
            ->where('missing', ['RIB']));
});

test('an employee without data yet shows every essential field as missing', function () {
    $employee = User::factory()->create();

    $this->actingAs(hrAdmin())
        ->get("/admin/users/{$employee->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile', null)
            ->where('missing', ['CIN', 'N° CNSS', 'date de naissance', 'adresse', 'RIB']));
});

test('employees and managers cannot see or edit anyone\'s personal data', function () {
    $manager = User::factory()->create(['role' => 'user']);
    $employee = User::factory()->create(['role' => 'user', 'manager_id' => $manager->id]);
    EmployeeProfile::factory()->for($employee)->create();

    foreach ([$manager, $employee] as $user) {
        $this->actingAs($user)->get("/admin/users/{$employee->id}")->assertForbidden();
        $this->actingAs($user)->put("/admin/users/{$employee->id}/personal-data", ['city' => 'X'])->assertForbidden();
    }

    expect($employee->profile->fresh()->city)->not->toBe('X');
});

test('an employee reads their own personal data in settings', function () {
    $employee = User::factory()->create();
    EmployeeProfile::factory()->for($employee)->create(['cin' => 'AB222222']);

    $this->actingAs($employee)
        ->get('/settings/personal-data')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/personal-data')
            ->where('profile.cin', 'AB222222')
            ->missing('profile.cin_hash')
            ->missing('profile.updated_by'));

    $this->actingAs(User::factory()->create())
        ->get('/settings/personal-data')
        ->assertInertia(fn (Assert $page) => $page->where('profile', null));
});

test('personal data never travels with the users list or shared props', function () {
    $employee = User::factory()->create();
    EmployeeProfile::factory()->for($employee)->create(['cin' => 'AB333333']);

    $this->actingAs(hrAdmin())->get('/admin/users')->assertDontSee('AB333333');
    $this->actingAs($employee)->get('/dashboard')->assertDontSee('AB333333');
});
