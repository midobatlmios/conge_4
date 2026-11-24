<?php

use App\Models\Holiday;
use App\Models\User;
use App\Services\LeaveCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function holidaysAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

test('the fixed Moroccan holidays are installed by the migration', function () {
    expect(Holiday::keys())->toContain('01-01', '05-01', '07-30', '11-18')
        ->and(Holiday::count())->toBe(10);
});

test('an admin sees the holidays page', function () {
    $this->actingAs(holidaysAdmin())
        ->get('/admin/holidays')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Holidays')->has('holidays', 10));
});

test('employees cannot manage holidays', function () {
    $employee = User::factory()->create(['role' => 'user']);

    $this->actingAs($employee)->get('/admin/holidays')->assertForbidden();
    $this->actingAs($employee)
        ->post('/admin/holidays', ['name' => 'X', 'date' => '2026-03-20', 'recurring' => false])
        ->assertForbidden();

    expect(Holiday::count())->toBe(10);
});

test('a holiday added by HR is no longer counted as a leave day', function () {
    $calculator = app(LeaveCalculator::class);
    // Thu 19 – Mon 23 March 2026: Thu, Fri, Mon = 3 working days.
    expect($calculator->workingDays('2026-03-19', '2026-03-23'))->toBe(3);

    $this->actingAs(holidaysAdmin())
        ->post('/admin/holidays', ['name' => 'Aïd al-Fitr', 'date' => '2026-03-20', 'recurring' => false])
        ->assertSessionHasNoErrors();

    expect($calculator->workingDays('2026-03-19', '2026-03-23'))->toBe(2)
        // A single-date holiday does not repeat the next year (Fri 19 March 2027... is 2027-03-19).
        ->and($calculator->workingDays('2027-03-19', '2027-03-19'))->toBe(1);
});

test('a repeating holiday applies every year and is stored without its year', function () {
    $this->actingAs(holidaysAdmin())
        ->post('/admin/holidays', ['name' => 'Fête locale', 'date' => '2026-06-10', 'recurring' => true])
        ->assertSessionHasNoErrors();

    $holiday = Holiday::where('name', 'Fête locale')->sole();
    $calculator = app(LeaveCalculator::class);

    expect($holiday->date->toDateString())->toBe('2000-06-10')
        ->and($calculator->workingDays('2026-06-10', '2026-06-10'))->toBe(0) // Wednesday
        ->and($calculator->workingDays('2027-06-10', '2027-06-10'))->toBe(0); // Thursday
});

test('the same day cannot be added twice', function () {
    $admin = holidaysAdmin();

    // 1 May already repeats every year.
    $this->actingAs($admin)
        ->post('/admin/holidays', ['name' => 'Doublon', 'date' => '2026-05-01', 'recurring' => false])
        ->assertSessionHasErrors('date');

    Holiday::create(['name' => 'Aïd al-Adha', 'date' => '2026-05-27', 'recurring' => false]);
    $this->actingAs($admin)
        ->post('/admin/holidays', ['name' => 'Doublon', 'date' => '2026-05-27', 'recurring' => false])
        ->assertSessionHasErrors('date');

    // Same month-day in another year is fine for single dates.
    $this->actingAs($admin)
        ->post('/admin/holidays', ['name' => 'Aïd al-Adha', 'date' => '2027-05-27', 'recurring' => false])
        ->assertSessionHasNoErrors();
});

test('an admin can edit and delete a holiday', function () {
    $admin = holidaysAdmin();
    $holiday = Holiday::create(['name' => 'Aïd', 'date' => '2026-03-19', 'recurring' => false]);

    // Editing keeps its own date without a "duplicate" error.
    $this->actingAs($admin)
        ->put("/admin/holidays/{$holiday->id}", ['name' => 'Aïd al-Fitr', 'date' => '2026-03-20', 'recurring' => false])
        ->assertSessionHasNoErrors();

    expect($holiday->fresh()->name)->toBe('Aïd al-Fitr')
        ->and(Holiday::keys())->toContain('2026-03-20')->not->toContain('2026-03-19');

    $this->actingAs($admin)->delete("/admin/holidays/{$holiday->id}")->assertRedirect();

    expect(Holiday::keys())->not->toContain('2026-03-20');
});

test('the request form receives the holidays from the database', function () {
    Holiday::create(['name' => 'Aïd al-Fitr', 'date' => '2026-03-20', 'recurring' => false]);

    $this->actingAs(User::factory()->create(['role' => 'user']))
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('leaveRules.holidays', fn ($keys) => collect($keys)->contains('2026-03-20') && collect($keys)->contains('05-01')));
});
