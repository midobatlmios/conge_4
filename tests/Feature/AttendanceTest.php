<?php

use App\Enums\Role;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\CompanySetting;
use App\Models\Demande;
use App\Models\User;
use App\Services\AttendanceCalculator;
use App\Services\PayrollReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

// Wednesday 10 June 2026, 08:35. Schedule 08:30–12:30 / 14:00–18:00 (8 h), tolerance 10 min.
beforeEach(function () {
    $this->travelTo('2026-06-10 08:35:00');
    CompanySetting::create(['name' => 'Atlas', 'address' => 'x', 'city' => 'Casablanca', 'signatory_name' => 'X', 'attendance_started_on' => '2026-06-01']);
});

function worker(array $attributes = []): User
{
    return User::factory()->create(['role' => Role::User, 'hire_date' => '2020-01-01', ...$attributes]);
}

function clocked(User $user, string $date, string $in, ?string $out): AttendanceRecord
{
    return AttendanceRecord::create(['user_id' => $user->id, 'date' => $date, 'clock_in' => $in, 'clock_out' => $out]);
}

function dayOf(User $user, string $date): array
{
    return app(AttendanceCalculator::class)->days(collect([$user]), now()->parse($date), now()->parse($date))[$user->id][0];
}

test('an employee clocks in and out once a day, with the server time', function () {
    $user = worker();

    $this->actingAs($user)->post('/pointage/arrivee')->assertSessionHas('success');
    $this->actingAs($user)->post('/pointage/arrivee')->assertSessionHas('error');
    $this->travelTo('2026-06-10 18:05:00');
    $this->actingAs($user)->post('/pointage/depart')->assertSessionHas('success');

    $record = AttendanceRecord::sole();
    expect($record->clock_in)->toBe('08:35')->and($record->clock_out)->toBe('18:05');
});

test('nobody can clock before HR starts tracking', function () {
    CompanySetting::query()->update(['attendance_started_on' => null]);

    $this->actingAs(worker())->post('/pointage/arrivee')->assertForbidden();
});

test('worked time leaves out lunch; lateness counts after the tolerance', function () {
    $user = worker();
    clocked($user, '2026-06-02', '08:39', '18:00');   // 9 min late: tolerated
    clocked($user, '2026-06-03', '08:41', '18:00');   // 11 min late

    expect(dayOf($user, '2026-06-02'))->toMatchArray(['status' => 'present', 'worked' => 471, 'late' => 0])
        ->and(dayOf($user, '2026-06-03')['late'])->toBe(11);
});

test('overtime: +25 % by day, +50 % at night, +50/+100 % on a rest day', function () {
    $user = worker();
    clocked($user, '2026-06-02', '08:30', '22:00');   // 12 h worked: 3 h day + 1 h night beyond 8 h
    clocked($user, '2026-06-06', '15:00', '23:00');   // Saturday: 6 h day + 2 h night

    expect(dayOf($user, '2026-06-02')['overtime'])->toBe(['25' => 180, '50' => 60, '100' => 0])
        ->and(dayOf($user, '2026-06-06')['overtime'])->toBe(['25' => 0, '50' => 360, '100' => 120]);
});

test('absences: not before tracking or hire, not on leave; half a day of leave halves the day', function () {
    $user = worker();
    $newcomer = worker(['hire_date' => '2026-06-08']);
    Demande::create(['user_id' => $user->id, 'type_conge' => 'annuel', 'date_debut' => '2026-06-03', 'date_fin' => '2026-06-04',
        'end_half' => true, 'date_demande' => '2026-05-01', 'etat' => 'acceptée']);
    clocked($user, '2026-06-04', '14:00', '18:00');

    $days = collect(app(AttendanceCalculator::class)->days(collect([$user]), now()->parse('2026-05-29'), now()->parse('2026-06-09'))[$user->id])->keyBy('date');

    expect($days['2026-05-29']['status'])->toBe('not_tracked')
        ->and($days['2026-06-01']['status'])->toBe('absent')
        ->and($days['2026-06-03']['status'])->toBe('leave')
        ->and($days['2026-06-04'])->toMatchArray(['status' => 'present', 'expected' => 240, 'worked' => 240, 'late' => 0])
        ->and($days['2026-06-06']['status'])->toBe('rest')
        ->and(dayOf($newcomer, '2026-06-05')['status'])->toBe('not_tracked')
        ->and(dayOf($user, '2026-06-10')['status'])->toBe('not_yet');
});

test('a forgotten clock-out is flagged; the manager approves a correction that replaces the day', function () {
    $manager = worker();
    $user = worker(['manager_id' => $manager->id]);
    clocked($user, '2026-06-09', '08:30', null);
    expect(dayOf($user, '2026-06-09')['status'])->toBe('missing_out');

    $this->actingAs($user)->post('/pointage/corrections', ['date' => '2026-06-09', 'clock_in' => '08:30', 'clock_out' => '17:45', 'reason' => 'Oubli'])
        ->assertSessionHasNoErrors();
    $correction = AttendanceCorrection::sole();

    // Not the employee, not a colleague.
    $this->actingAs($user)->put("/pointage/corrections/{$correction->id}/approve")->assertForbidden();
    $this->actingAs(worker())->put("/pointage/corrections/{$correction->id}/approve")->assertForbidden();

    $this->actingAs($manager)->put("/pointage/corrections/{$correction->id}/approve")->assertSessionHas('success');
    expect(dayOf($user, '2026-06-09'))->toMatchArray(['status' => 'present', 'clock_out' => '17:45', 'source' => 'correction'])
        ->and($correction->fresh()->status)->toBe('approved');
});

test('corrections cannot be in the future or before tracking; HR can refuse one', function () {
    $user = worker();
    $post = fn (string $date) => $this->actingAs($user)->post('/pointage/corrections', ['date' => $date, 'clock_in' => '08:30', 'reason' => 'x']);

    $post('2026-06-11')->assertSessionHasErrors('date');
    $post('2026-05-29')->assertSessionHasErrors('date');
    $post('2026-06-01')->assertSessionHasNoErrors();

    $hr = User::factory()->create(['role' => Role::Hr]);
    $correction = AttendanceCorrection::sole();
    $this->actingAs($hr)->put("/pointage/corrections/{$correction->id}/refuse", ['refusal_reason' => 'Absent ce jour-là'])->assertSessionHas('success');
    expect($correction->fresh()->status)->toBe('refused')->and(AttendanceRecord::count())->toBe(0);
});

test('HR sees the month and today, exports it, and sets the schedule; employees cannot', function () {
    $user = worker(['name' => 'Sara']);
    clocked($user, '2026-06-02', '08:45', '18:30');
    clocked($user, '2026-06-10', '08:50', null);

    $this->actingAs(User::factory()->create(['role' => Role::Payroll]))->get('/admin/attendance?month=2026-06')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Attendance')
            ->where('rows', fn ($rows) => collect($rows)->firstWhere('name', 'Sara')['late_days'] === 2
                // 1, 3, 4, 5, 8 and 9 June without clocking.
                && collect($rows)->firstWhere('name', 'Sara')['absent_days'] === 6)
            ->where('today.present', 1)
            ->where('today.late', 1));

    $response = $this->actingAs(User::factory()->create(['role' => Role::Payroll]))->get('/admin/attendance/export?month=2026-06')->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('pointage-2026-06.xlsx');

    $hr = User::factory()->create(['role' => Role::Hr]);
    $this->actingAs($hr)->put('/admin/attendance/schedule', [
        'work_start' => '08:00', 'lunch_start' => '12:00', 'lunch_end' => '13:00', 'work_end' => '17:00',
        'late_tolerance_minutes' => 5, 'attendance_started_on' => '2026-06-01',
    ])->assertSessionHasNoErrors();
    expect(CompanySetting::current()->work_start)->toBe('08:00');

    $this->actingAs(User::factory()->create(['role' => Role::Payroll]))->put('/admin/attendance/schedule', [])->assertForbidden();
    $this->actingAs($user)->get('/admin/attendance')->assertForbidden();
});

test('the employee page and the dashboard show the clocking; the payroll export adds absences and overtime', function () {
    $user = worker();
    clocked($user, '2026-06-02', '08:30', '19:30');

    $this->actingAs($user)->get('/pointage?month=2026-06')->assertInertia(fn (Assert $page) => $page
        ->component('Attendance')
        ->where('totals.overtime_25', 90)
        ->where('today.tracking', true));
    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('attendance.clock_in', null));

    $row = app(PayrollReport::class)->rows(now()->parse('2026-06-01'))->firstWhere('id', $user->id);
    expect($row['overtime_25'])->toEqual(1.5)->and($row['absent_days'])->toBe(6);
});
