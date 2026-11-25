<?php

use App\Models\Demande;
use App\Models\Department;
use App\Models\User;
use App\Services\PayrollReport;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

// Monday 6 April 2026: the default month is March.
beforeEach(fn () => $this->travelTo('2026-04-06 10:00:00'));

function payrollLeave(User $user, string $start, string $end, string $type, string $etat = 'acceptée'): Demande
{
    return Demande::create([
        'user_id' => $user->id,
        'date_demande' => '2026-02-01',
        'date_debut' => $start,
        'date_fin' => $end,
        'type_conge' => $type,
        'etat' => $etat,
    ]);
}

function payrollRows(string $month = '2026-03')
{
    return app(PayrollReport::class)->rows(Carbon::parse($month.'-01'))->keyBy('name');
}

test('days are counted per kind of leave, within the month only', function () {
    $sara = User::factory()->create(['name' => 'Sara', 'matricule' => 'EMP-7', 'hire_date' => '2023-06-01']);
    payrollLeave($sara, '2026-02-26', '2026-03-03', 'annuel'); // 2 + 3 March = 2 days in March
    payrollLeave($sara, '2026-03-09', '2026-03-11', 'naissance'); // 3
    payrollLeave($sara, '2026-03-16', '2026-03-20', 'sans_solde'); // 5
    payrollLeave($sara, '2026-03-23', '2026-03-23', 'recuperation'); // 1
    payrollLeave($sara, '2026-03-25', '2026-03-26', 'annuel', 'en attente'); // not counted
    payrollLeave($sara, '2026-03-30', '2026-03-31', 'annuel', 'annulée'); // not counted

    expect(payrollRows()['Sara'])->toMatchArray([
        'matricule' => 'EMP-7',
        'annual' => 2,
        'exceptional' => 3,
        'unpaid' => 5,
        'recovery' => 1,
        'total' => 11,
    ]);
});

test('the balance is what is left at the end of the month', function () {
    $sara = User::factory()->create(['name' => 'Sara', 'hire_date' => '2023-06-01']);
    payrollLeave($sara, '2026-03-02', '2026-03-03', 'annuel'); // 2 days
    payrollLeave($sara, '2026-05-04', '2026-05-05', 'annuel'); // later: not yet used at end of March

    // 31 March: 4.5 earned (3 months) - 2 used.
    expect(payrollRows()['Sara']['balance'])->toBe(2.5);
});

test('employees who left before the month or are hired after it are not listed', function () {
    User::factory()->create(['name' => 'Present']);
    User::factory()->create(['name' => 'Left in March', 'deactivated_at' => '2026-03-15']);
    User::factory()->create(['name' => 'Left in February', 'deactivated_at' => '2026-02-15']);
    User::factory()->create(['name' => 'Hired in April', 'hire_date' => '2026-04-01']);

    expect(payrollRows()->keys()->all())->toBe(['Left in March', 'Present']);
});

test('every leave type is counted in a column', function () {
    expect(PayrollReport::unmappedTypes())->toBe([]);
});

test('an admin sees the previous month by default', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/payroll')
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Payroll')->where('month', '2026-03')->has('rows', 1));
});

test('the Excel file has the headings, one row per employee and totals', function () {
    $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin']);
    $it = Department::factory()->create(['name' => 'IT']);
    $sara = User::factory()->create(['name' => 'Sara', 'department_id' => $it->id]);
    payrollLeave($sara, '2026-03-16', '2026-03-20', 'sans_solde');

    $response = $this->actingAs($admin)->get('/admin/payroll/export?month=2026-03');

    $response->assertOk()->assertDownload('conges-paie-2026-03.xlsx');

    $sheet = IOFactory::load($response->getFile()->getPathname())->getActiveSheet();
    expect($sheet->getCell('A1')->getValue())->toBe('Congés du mois de mars 2026')
        ->and($sheet->getCell('A4')->getValue())->toBe('Matricule')
        ->and($sheet->getCell('G4')->getValue())->toBe('Sans solde – à déduire (j)')
        ->and($sheet->getCell('B6')->getValue())->toBe('Sara')
        ->and($sheet->getCell('C6')->getValue())->toBe('IT')
        ->and($sheet->getCell('G6')->getValue())->toEqual(5)
        ->and($sheet->getCell('A7')->getValue())->toBe('Total')
        ->and($sheet->getCell('G7')->getValue())->toEqual(5);
});

test('employees cannot see or download the payroll export', function () {
    $employee = User::factory()->create(['role' => 'user']);

    $this->actingAs($employee)->get('/admin/payroll')->assertForbidden();
    $this->actingAs($employee)->get('/admin/payroll/export')->assertForbidden();
});
