<?php

use App\Enums\Role;
use App\Models\Demande;
use App\Models\Department;
use App\Models\User;
use App\Services\HrIndicators;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

// A past year, fully over, so nothing depends on today.
beforeEach(fn () => $this->travelTo('2026-03-15'));

function staffMember(array $attributes = []): User
{
    return User::factory()->create(['role' => Role::User, 'hire_date' => '2020-01-01', ...$attributes]);
}

function bookedLeave(User $user, string $type, string $start, string $end): Demande
{
    return Demande::create([
        'user_id' => $user->id, 'type_conge' => $type, 'date_debut' => $start, 'date_fin' => $end,
        'date_demande' => $start, 'etat' => 'acceptée',
    ]);
}

test('headcount, hires, departures and turnover over a year', function () {
    staffMember();
    staffMember();
    staffMember(['hire_date' => '2025-04-01']);                                   // hired in April
    staffMember()->forceFill(['deactivated_at' => '2025-09-16 00:00:00'])->save(); // left in September
    staffMember(['hire_date' => '2026-01-05']);                                   // after the year

    $data = app(HrIndicators::class)->compute(2025);

    expect($data['summary']['headcount_start'])->toBe(3)
        ->and($data['summary']['headcount_end'])->toBe(3)
        ->and($data['summary']['hires'])->toBe(1)
        ->and($data['summary']['departures'])->toBe(1)
        ->and($data['months'][2]['headcount'])->toBe(3)  // March
        ->and($data['months'][3]['headcount'])->toBe(4)  // April
        ->and($data['months'][8]['headcount'])->toBe(3)  // September
        ->and($data['months'])->toHaveCount(12);

    // 1 departure / average headcount (3+3+3+4+4+4+4+4+3+3+3+3)/12 = 3.4167 → 29.3 %.
    expect($data['summary']['turnover'])->toEqual(29.3);
});

test('absenteeism counts sick and unpaid days only, over theoretical working days', function () {
    $employee = staffMember();
    bookedLeave($employee, 'maladie', '2025-02-03', '2025-02-07');   // 5 working days, 1 stop
    bookedLeave($employee, 'sans_solde', '2025-02-10', '2025-02-10'); // 1 working day
    bookedLeave($employee, 'annuel', '2025-02-17', '2025-02-21');    // planned: not absenteeism
    Demande::create(['user_id' => $employee->id, 'type_conge' => 'maladie', 'date_debut' => '2025-02-24', 'date_fin' => '2025-02-25', 'date_demande' => '2025-02-24', 'etat' => 'refusée']);

    $february = app(HrIndicators::class)->compute(2025)['months'][1];

    // February 2025: 20 working days, no public holiday.
    expect($february['theoretical_days'])->toBe(20)
        ->and($february['sick_days'])->toEqual(5)
        ->and($february['unpaid_days'])->toEqual(1)
        ->and($february['planned_days'])->toEqual(5)
        ->and($february['sick_stops'])->toBe(1)
        ->and($february['absenteeism'])->toEqual(30.0);
});

test('a department filter limits every indicator to its people', function () {
    $sales = Department::create(['name' => 'Commercial']);
    $it = Department::create(['name' => 'Informatique']);
    $seller = staffMember(['department_id' => $sales->id]);
    staffMember(['department_id' => $it->id]);
    bookedLeave($seller, 'maladie', '2025-02-03', '2025-02-07');

    $all = app(HrIndicators::class)->compute(2025);
    $onlySales = app(HrIndicators::class)->compute(2025, $sales->id);

    expect($all['summary']['headcount_end'])->toBe(2)
        ->and($onlySales['summary']['headcount_end'])->toBe(1)
        ->and($onlySales['summary']['sick_days'])->toEqual(5)
        ->and(collect($all['departments'])->pluck('name')->all())->toBe(['Commercial', 'Informatique']);
});

test('the current year stops today; unused leave is listed, highest first', function () {
    $rested = staffMember(['name' => 'Repose']);
    $busy = staffMember(['name' => 'Occupe']);
    bookedLeave($busy, 'annuel', '2026-02-02', '2026-02-04');

    $data = app(HrIndicators::class)->compute(2026);

    expect($data['until'])->toBe('2026-03-15')
        ->and($data['months'])->toHaveCount(3)
        ->and($data['balances']['top'][0]['name'])->toBe('Repose')
        ->and($data['balances']['top'][0]['remaining'])->toBeGreaterThan($data['balances']['top'][1]['remaining']);
});

test('Admin, RH and Paie see the page and download the Excel report; employees do not', function () {
    staffMember();

    foreach ([Role::Admin, Role::Hr, Role::Payroll] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get('/admin/indicators?year=2025')
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Indicators')->where('year', 2025)->has('months', 12));
    }

    $response = $this->actingAs(User::factory()->create(['role' => Role::Payroll]))->get('/admin/indicators/export?year=2025')->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('bilan-rh-2025.xlsx');

    $workbook = IOFactory::load($response->getFile()->getPathname());
    expect($workbook->getSheetNames())->toBe(['Synthèse', 'Mensuel', 'Départements', 'Soldes de congés']);

    $this->actingAs(staffMember())->get('/admin/indicators')->assertForbidden();
});
