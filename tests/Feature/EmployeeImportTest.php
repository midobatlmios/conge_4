<?php

use App\Enums\ContractType;
use App\Enums\Gender;
use App\Enums\Role;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Notifications\EmployeeInvitation;
use App\Services\EmployeeImport;
use Database\Factories\EmployeeProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('uploads'));

/**
 * An .xlsx with the given headings and rows (every cell written as text, as
 * HR would type it in the template).
 *
 * @param  list<string>  $headings
 * @param  list<list<string>>  $rows
 */
function excelFile(array $headings, array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray($headings, null, 'A1');
    foreach ($rows as $r => $row) {
        foreach ($row as $c => $value) {
            $sheet->setCellValueExplicit([$c + 1, $r + 2], $value, DataType::TYPE_STRING);
        }
    }
    $path = tempnam(sys_get_temp_dir(), 'test').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'employes.xlsx', null, null, true);
}

/** Uploads a file as $actor and returns the token of the stored file. */
function uploadImport(User $actor, UploadedFile $file): string
{
    $response = test()->actingAs($actor)->post('/admin/import', ['file' => $file]);
    $response->assertRedirect();
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

    return $query['fichier'];
}

function hrUser(): User
{
    return User::factory()->create(['role' => Role::Hr]);
}

test('the template has every column, an example and instructions', function () {
    $response = $this->actingAs(hrUser())->get('/admin/import/modele')->assertOk();

    $workbook = IOFactory::load($response->getFile()->getPathname());
    expect($workbook->getSheetNames())->toBe(['Employés', 'Exemple', "Mode d'emploi"])
        ->and($workbook->getSheet(0)->getCell('A1')->getValue())->toBe('Nom complet *')
        ->and($workbook->getSheet(0)->getHighestDataColumn())->toBe('AB');
});

test('a clean file is checked, then imported with record, personal data, contract and managers', function () {
    $rh = hrUser();
    $rib = EmployeeProfileFactory::rib();
    $file = excelFile(
        ['Nom complet', 'E-mail professionnel', "Date d'embauche", 'Matricule', 'Département', 'Poste', 'Responsable', 'Genre', 'CIN', 'RIB', 'Type de contrat', 'Catégorie', 'Salaire brut mensuel'],
        [
            ['Mehdi Lahlou', 'mehdi@atlas.ma', '01/02/2015', 'EMP-001', 'Commercial', 'Directeur commercial', '', 'Homme', 'AB111111', '', 'CDI', 'Cadre', '31000'],
            ['Sara Bennani', 'sara@atlas.ma', '2021-09-01', 'EMP-002', 'commercial', 'Commerciale', 'EMP-001', 'femme', 'ab 222222', $rib, 'CDD', 'Employé', '12 500,50'],
        ],
    );

    $token = uploadImport($rh, $file);
    // The CDD has no end date: the check refuses the file and nothing is saved.
    $this->actingAs($rh)->get("/admin/import?fichier={$token}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('check.errors', 1)
            ->where('check.rows.1.errors.0', 'Fin du contrat obligatoire pour un CDD.')
            ->where('check.new_departments', ['Commercial']));
    $this->actingAs($rh)->post("/admin/import/{$token}/confirm")->assertSessionHasErrors('file');
    expect(User::where('email', 'sara@atlas.ma')->exists())->toBeFalse();

    // Fixed file.
    $file = excelFile(
        ['Nom complet', 'E-mail professionnel', "Date d'embauche", 'Matricule', 'Département', 'Poste', 'Responsable', 'Genre', 'CIN', 'RIB', 'Type de contrat', 'Catégorie', 'Salaire brut mensuel', 'Fin du contrat'],
        [
            ['Mehdi Lahlou', 'mehdi@atlas.ma', '01/02/2015', 'EMP-001', 'Commercial', 'Directeur commercial', '', 'Homme', 'AB111111', '', 'CDI', 'Cadre', '31000', ''],
            ['Sara Bennani', 'sara@atlas.ma', '2021-09-01', 'EMP-002', 'commercial', 'Commerciale', 'EMP-001', 'femme', 'ab 222222', $rib, 'CDD', 'Employé', '12 500,50', '31/08/2027'],
        ],
    );
    $token = uploadImport($rh, $file);
    $this->actingAs($rh)->post("/admin/import/{$token}/confirm")->assertRedirect('/admin/import');

    $sara = User::where('email', 'sara@atlas.ma')->sole();
    $mehdi = User::where('email', 'mehdi@atlas.ma')->sole();
    expect(Department::count())->toBe(1)
        ->and($sara->department->name)->toBe('Commercial')
        ->and($sara->manager->is($mehdi))->toBeTrue()
        ->and($sara->hire_date->toDateString())->toBe('2021-09-01')
        ->and($sara->role)->toBe(Role::User)
        ->and($sara->profile->cin)->toBe('AB222222')
        ->and($sara->profile->gender)->toBe(Gender::Female)
        ->and($sara->profile->rib)->toBe($rib)
        ->and($sara->contracts()->sole()->type)->toBe(ContractType::FixedTerm)
        ->and($sara->contracts()->sole()->gross_salary)->toBe('12500.50')
        ->and($sara->privacy_acknowledged_at)->toBeNull();
    Storage::disk('uploads')->assertMissing("imports/{$token}");
});

test('an existing employee is updated: filled cells overwrite, empty ones change nothing', function () {
    $rh = hrUser();
    $employee = User::factory()->create(['email' => 'imane@atlas.ma', 'job_title' => 'Comptable', 'matricule' => 'EMP-009']);
    EmployeeProfile::factory()->for($employee)->create(['city' => 'Rabat', 'cin' => 'AB999999']);

    $token = uploadImport($rh, excelFile(['Nom complet', 'E-mail professionnel', "Date d'embauche", 'Poste', 'Ville'], [['Imane Chraibi', 'IMANE@atlas.ma', '', '', 'Casablanca']]));
    $this->actingAs($rh)->post("/admin/import/{$token}/confirm")->assertSessionHasNoErrors();

    $employee->refresh();
    expect(User::count())->toBe(2)
        ->and($employee->name)->toBe('Imane Chraibi')
        ->and($employee->job_title)->toBe('Comptable')
        ->and($employee->profile->city)->toBe('Casablanca')
        ->and($employee->profile->cin)->toBe('AB999999');
});

test('every problem is reported per row, with a downloadable report', function () {
    $rh = hrUser();
    $admin = User::factory()->create(['role' => Role::Admin, 'email' => 'boss@atlas.ma']);
    EmployeeProfile::factory()->for(User::factory()->create())->create(['cin' => 'BE123456']);

    $token = uploadImport($rh, excelFile(
        ['Nom complet', 'E-mail professionnel', "Date d'embauche", 'Responsable', 'CIN', 'RIB', 'N° CNSS'],
        [
            ['', 'pas-un-email', '31/02/2024', '', '', '', ''],
            ['Ali', 'ali@atlas.ma', '01/01/2024', 'inconnu@atlas.ma', 'BE123456', '123', '12'],
            ['Ali bis', 'ali@atlas.ma', '01/01/2024', 'ali@atlas.ma', '', '', ''],
            ['Boss', 'boss@atlas.ma', '01/01/2010', '', '', '', ''],
        ],
    ));

    $this->actingAs($rh)->get("/admin/import?fichier={$token}")->assertInertia(fn (Assert $page) => $page
        ->where('check.errors', 4)
        ->where('check.rows.0.errors', fn ($e) => collect($e)->contains('Nom complet manquant.') && collect($e)->contains("Date d'embauche invalide (JJ/MM/AAAA)."))
        ->where('check.rows.1.errors', fn ($e) => collect($e)->contains(fn ($m) => str_contains($m, 'introuvable')) && collect($e)->contains(fn ($m) => str_contains($m, 'CIN déjà enregistrée')) && collect($e)->contains(fn ($m) => str_starts_with($m, 'RIB')) && collect($e)->contains(fn ($m) => str_starts_with($m, 'N° CNSS')))
        ->where('check.rows.2.errors', fn ($e) => collect($e)->contains('E-mail déjà présent ligne 3.') && collect($e)->contains('Un employé ne peut pas être son propre responsable.'))
        ->where('check.rows.3.errors.0', fn ($m) => str_contains($m, 'à modifier à la main')));

    $report = $this->actingAs($rh)->get("/admin/import/{$token}/erreurs")->assertOk();
    expect(IOFactory::load($report->getFile()->getPathname())->getSheet(0)->getHighestDataRow())->toBe(5);
    expect($admin->fresh()->name)->not->toBe('Boss');
});

test('a file without the required columns is refused with a clear message', function () {
    $rh = hrUser();
    $token = uploadImport($rh, excelFile(['Prénom', 'Nom'], [['Sara', 'Bennani']]));

    $this->actingAs($rh)->get("/admin/import?fichier={$token}")
        ->assertInertia(fn (Assert $page) => $page->where('check.file_error', fn ($m) => str_contains($m, 'E-mail professionnel')));
});

test('new employees get an invitation that lets them choose a password for 7 days', function () {
    Notification::fake();
    $rh = hrUser();
    $token = uploadImport($rh, excelFile(['Nom complet', 'E-mail professionnel', "Date d'embauche"], [['Nora Benali', 'nora@atlas.ma', '01/09/2026']]));
    $this->actingAs($rh)->post("/admin/import/{$token}/confirm");
    $nora = User::where('email', 'nora@atlas.ma')->sole();

    $this->actingAs($rh)->post('/admin/users/invite', ['ids' => [$nora->id]])->assertSessionHasNoErrors();

    expect($nora->fresh()->invited_at)->not->toBeNull();
    $token = null;
    Notification::assertSentTo($nora, EmployeeInvitation::class, function (EmployeeInvitation $notification) use (&$token) {
        $token = (fn () => $this->token)->call($notification);

        return true;
    });

    // Six days later the link still works (a reset link would have expired after 60 minutes).
    $this->travel(6)->days();
    auth()->logout();
    $this->post('/reset-password', ['token' => $token, 'email' => 'nora@atlas.ma', 'password' => 'nouveau-mot-de-passe', 'password_confirmation' => 'nouveau-mot-de-passe', 'invitation' => true])
        ->assertRedirect('/login')
        ->assertSessionHasNoErrors();
    $this->post('/login', ['email' => 'nora@atlas.ma', 'password' => 'nouveau-mot-de-passe']);
    $this->assertAuthenticatedAs($nora->fresh());
});

test('an invitation token cannot be used as a normal reset link, and Paie cannot import', function () {
    $employee = User::factory()->create();
    $token = Password::broker('invitations')->createToken($employee);

    $this->post('/reset-password', ['token' => $token, 'email' => $employee->email, 'password' => 'nouveau-mot-de-passe', 'password_confirmation' => 'nouveau-mot-de-passe'])
        ->assertSessionHasErrors('email');

    $payroll = User::factory()->create(['role' => Role::Payroll]);
    $this->actingAs($payroll)->get('/admin/import')->assertForbidden();
    $this->actingAs($payroll)->post('/admin/import', ['file' => excelFile(['Nom complet'], [['x']])])->assertForbidden();
    $this->actingAs($payroll)->post('/admin/users/invite', ['ids' => [$employee->id]])->assertForbidden();
});

test('the maximum number of rows is enforced', function () {
    expect(EmployeeImport::MAX_ROWS)->toBe(500);
});
