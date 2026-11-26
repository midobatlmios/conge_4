<?php

namespace App\Services;

use App\Enums\ContractType;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\Role;
use App\Enums\StaffCategory;
use App\Http\Requests\Admin\UpdateEmployeeProfileRequest;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\PersonalDataAccess;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Imports employees from an Excel file (one row per employee): account and
 * record, personal data, and their current contract. check() reads the file
 * and lists every problem per row; import() saves everything only when there
 * is none (all or nothing). An employee already in the app (same e-mail, or
 * same matricule) is updated: filled cells overwrite, empty cells change nothing.
 */
class EmployeeImport
{
    /** Columns of the template: key => [heading, required, hint]. */
    public const COLUMNS = [
        'name' => ['Nom complet', true, 'Prénom et nom'],
        'email' => ['E-mail professionnel', true, 'Identifiant de connexion, unique'],
        'hire_date' => ["Date d'embauche", true, 'JJ/MM/AAAA'],
        'matricule' => ['Matricule', false, 'Unique'],
        'department' => ['Département', false, "Créé s'il n'existe pas encore"],
        'job_title' => ['Poste', false, ''],
        'manager' => ['Responsable', false, 'E-mail ou matricule du responsable (dans l\'application ou dans ce fichier)'],
        'gender' => ['Genre', false, 'Homme / Femme'],
        'birth_date' => ['Date de naissance', false, 'JJ/MM/AAAA'],
        'birth_place' => ['Lieu de naissance', false, ''],
        'cin' => ['CIN', false, 'Ex. : AB123456'],
        'cnss_number' => ['N° CNSS', false, '9 chiffres'],
        'marital_status' => ['Situation familiale', false, 'Célibataire / Marié(e) / Divorcé(e) / Veuf(ve)'],
        'children_count' => ['Enfants à charge', false, 'Nombre'],
        'address' => ['Adresse', false, ''],
        'city' => ['Ville', false, ''],
        'personal_phone' => ['Téléphone personnel', false, ''],
        'personal_email' => ['E-mail personnel', false, ''],
        'bank_name' => ['Banque', false, ''],
        'rib' => ['RIB', false, '24 chiffres, cellule au format texte'],
        'emergency_contact_name' => ["Contact d'urgence", false, 'Nom'],
        'emergency_contact_relation' => ['Lien de parenté', false, ''],
        'emergency_contact_phone' => ["Téléphone d'urgence", false, ''],
        'contract_type' => ['Type de contrat', false, 'CDI / CDD / ANAPEC / Stage'],
        'contract_category' => ['Catégorie', false, 'Cadre / Employé / Ouvrier'],
        'contract_start' => ['Début du contrat', false, "Par défaut : date d'embauche"],
        'contract_end' => ['Fin du contrat', false, 'Obligatoire sauf pour un CDI'],
        'gross_salary' => ['Salaire brut mensuel', false, 'En DH'],
    ];

    /** Columns read as dates; and identifiers that must stay text (long numbers). */
    private const DATES = ['hire_date', 'birth_date', 'contract_start', 'contract_end'];

    private const TEXT = ['cin', 'cnss_number', 'rib', 'personal_phone', 'emergency_contact_phone', 'matricule'];

    private const PROFILE = [
        'gender', 'birth_date', 'birth_place', 'cin', 'cnss_number', 'marital_status', 'children_count', 'address', 'city',
        'personal_phone', 'personal_email', 'bank_name', 'rib', 'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone',
    ];

    private const CONTRACT = ['contract_type', 'contract_category', 'contract_start', 'contract_end', 'gross_salary'];

    public const MAX_ROWS = 500;

    // ------------------------------------------------------------------ template

    /** @return string Path of the template (.xlsx): an empty "Employés" sheet, an example and instructions. */
    public function template(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Employés');
        $keys = array_keys(self::COLUMNS);
        $last = Coordinate::stringFromColumnIndex(count($keys));

        foreach ($keys as $i => $key) {
            [$heading, $required] = self::COLUMNS[$key];
            $column = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$column}1", $heading.($required ? ' *' : ''));
            $sheet->getColumnDimension($column)->setWidth(max(14, mb_strlen($heading) + 4));

            // Long identifiers typed as numbers would lose digits in Excel.
            if (in_array($key, self::TEXT, true)) {
                $sheet->getStyle("{$column}2:{$column}".(self::MAX_ROWS + 1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            }
            if (in_array($key, self::DATES, true)) {
                $sheet->getStyle("{$column}2:{$column}".(self::MAX_ROWS + 1))->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            }

            $choices = match ($key) {
                'gender' => 'Homme,Femme',
                'marital_status' => 'Célibataire,Marié(e),Divorcé(e),Veuf(ve)',
                'contract_type' => 'CDI,CDD,ANAPEC,Stage',
                'contract_category' => 'Cadre,Employé,Ouvrier',
                default => null,
            };
            if ($choices) {
                $validation = new DataValidation;
                $validation->setType(DataValidation::TYPE_LIST)->setAllowBlank(true)->setShowDropDown(true)->setFormula1("\"{$choices}\"");
                $sheet->setDataValidation("{$column}2:{$column}".(self::MAX_ROWS + 1), $validation);
            }
        }

        $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$last}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
        $sheet->getStyle('A1:C1')->getFill()->getStartColor()->setRGB('FDE68A');
        $sheet->freezePane('B2');

        $example = $spreadsheet->createSheet()->setTitle('Exemple');
        $example->fromArray(array_map(fn (array $column) => $column[0], array_values(self::COLUMNS)), null, 'A1');
        $example->fromArray([[
            'Sara Bennani', 'sara.bennani@entreprise.ma', '01/09/2021', 'EMP-012', 'Commercial', 'Commerciale', 'mehdi.lahlou@entreprise.ma',
            'Femme', '14/05/1992', 'Rabat', 'AB123456', '123456789', 'Marié(e)', '1', '12 rue des Orangers', 'Casablanca',
            '06 12 34 56 78', 'sara@gmail.com', 'CIH Bank', '230780123456789012345623', 'Karim Bennani', 'Conjoint', '06 98 76 54 32',
            'CDI', 'Employé', '01/09/2021', '', '12500',
        ]], null, 'A2', true);
        $example->getStyle("A1:{$last}1")->getFont()->setBold(true);

        $help = $spreadsheet->createSheet()->setTitle("Mode d'emploi");
        $help->fromArray([['Colonne', 'Obligatoire', 'Contenu']], null, 'A1');
        $help->fromArray(array_map(fn (array $c) => [$c[0], $c[1] ? 'oui' : '', $c[2]], array_values(self::COLUMNS)), null, 'A2');
        $help->setCellValue('E1', 'Une ligne par employé, dans la feuille « Employés ». Un employé déjà présent (même e-mail ou même matricule) est mis à jour : les cellules remplies remplacent, les cellules vides ne changent rien. Les comptes créés reçoivent une invitation pour choisir leur mot de passe.');
        $help->getStyle('A1:C1')->getFont()->setBold(true);
        foreach (['A' => 28, 'B' => 12, 'C' => 60] as $column => $width) {
            $help->getColumnDimension($column)->setWidth($width);
        }

        $spreadsheet->setActiveSheetIndex(0);
        $path = tempnam(sys_get_temp_dir(), 'modele');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    // ------------------------------------------------------------------ reading & checking

    /**
     * Reads the first sheet and checks every row.
     *
     * @return array{rows: list<array<string, mixed>>, errors: int, creates: int, updates: int, new_departments: list<string>, file_error: ?string}
     */
    public function check(string $path, User $actor): array
    {
        try {
            $rows = $this->read($path);
        } catch (\Throwable $e) {
            return $this->fileError("Le fichier n'a pas pu être lu : utilisez le modèle Excel (.xlsx).");
        }

        if (isset($rows['missing'])) {
            return $this->fileError('Colonnes obligatoires introuvables : '.implode(', ', $rows['missing']).'. Utilisez le modèle.');
        }
        if ($rows === []) {
            return $this->fileError('Aucun employé trouvé dans la première feuille.');
        }
        if (count($rows) > self::MAX_ROWS) {
            return $this->fileError('Au plus '.self::MAX_ROWS.' employés par fichier.');
        }

        $departments = Department::pluck('name', 'id')->mapWithKeys(fn ($name, $id) => [Str::lower(trim($name)) => $id]);
        $byEmail = User::all()->keyBy(fn (User $u) => Str::lower($u->email));
        $byMatricule = User::whereNotNull('matricule')->get()->keyBy(fn (User $u) => Str::lower($u->matricule));
        $inFile = ['email' => [], 'matricule' => [], 'cin' => []];
        $newDepartments = [];
        $checked = [];

        // References a manager can use: everyone in the app and every row of the file.
        $fileRefs = [];
        foreach ($rows as $row) {
            foreach (['email', 'matricule'] as $key) {
                if (filled($row['values'][$key] ?? null)) {
                    $fileRefs[Str::lower($row['values'][$key])] = $row['line'];
                }
            }
        }

        foreach ($rows as $row) {
            $v = $row['values'];
            $errors = [];
            $warnings = [];
            $email = Str::lower(trim((string) ($v['email'] ?? '')));
            $matricule = trim((string) ($v['matricule'] ?? ''));

            // Account and record.
            if (blank($v['name'] ?? null)) {
                $errors[] = 'Nom complet manquant.';
            }
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'E-mail professionnel manquant ou invalide.';
            } elseif (isset($inFile['email'][$email])) {
                $errors[] = "E-mail déjà présent ligne {$inFile['email'][$email]}.";
            }
            $inFile['email'][$email] = $row['line'];

            $existing = $byEmail[$email] ?? ($matricule !== '' ? $byMatricule[Str::lower($matricule)] ?? null : null);
            if ($matricule !== '') {
                $owner = $byMatricule[Str::lower($matricule)] ?? null;
                if ($owner && $existing && ! $owner->is($existing)) {
                    $errors[] = "Matricule {$matricule} déjà attribué à {$owner->name}.";
                }
                if (isset($inFile['matricule'][Str::lower($matricule)])) {
                    $errors[] = "Matricule déjà présent ligne {$inFile['matricule'][Str::lower($matricule)]}.";
                }
                $inFile['matricule'][Str::lower($matricule)] = $row['line'];
            }
            if ($existing && ! $actor->canManageAccount($existing)) {
                $errors[] = "{$existing->name} a un compte {$existing->role->label()} : à modifier à la main par un administrateur.";
            }
            if ($existing?->anonymized_at) {
                $errors[] = 'Ce compte a été anonymisé.';
            }

            $hireDate = $this->date($v['hire_date'] ?? null);
            if ($hireDate === false) {
                $errors[] = "Date d'embauche invalide (JJ/MM/AAAA).";
            } elseif ($hireDate === null && ! $existing?->hire_date) {
                $errors[] = "Date d'embauche manquante.";
            }

            $departmentName = trim((string) ($v['department'] ?? ''));
            if ($departmentName !== '' && ! isset($departments[Str::lower($departmentName)])) {
                // The first spelling found is the one created.
                $newDepartments[Str::lower($departmentName)] ??= $departmentName;
            }

            $managerName = null;
            if (filled($v['manager'] ?? null)) {
                $ref = Str::lower(trim((string) $v['manager']));
                $manager = $byEmail[$ref] ?? $byMatricule[$ref] ?? null;
                if ($ref === $email || ($matricule !== '' && $ref === Str::lower($matricule)) || ($existing && $manager?->is($existing))) {
                    $errors[] = 'Un employé ne peut pas être son propre responsable.';
                } elseif ($manager && ! $manager->isActive()) {
                    $errors[] = "Le responsable {$manager->name} n'est plus actif.";
                } elseif ($manager) {
                    $managerName = $manager->name;
                } elseif (isset($fileRefs[$ref])) {
                    $managerName = 'ligne '.$fileRefs[$ref];
                } else {
                    $errors[] = "Responsable « {$v['manager']} » introuvable (e-mail ou matricule).";
                }
            }

            // Personal data, with the same rules as the employee page.
            [$profile, $profileErrors] = $this->profile($v, $existing);
            $errors = [...$errors, ...$profileErrors];
            if (isset($profile['cin'])) {
                $hash = EmployeeProfile::cinHash($profile['cin']);
                $owner = EmployeeProfile::where('cin_hash', $hash)->when($existing, fn ($q) => $q->where('user_id', '!=', $existing->id))->with('user:id,name')->first();
                if ($owner) {
                    $errors[] = "CIN déjà enregistrée pour {$owner->user->name}.";
                } elseif (isset($inFile['cin'][$hash])) {
                    $errors[] = "CIN déjà présente ligne {$inFile['cin'][$hash]}.";
                }
                $inFile['cin'][$hash] = $row['line'];
            }

            [$contract, $contractErrors] = $this->contract($v, $hireDate ?: $existing?->hire_date?->toDateString());
            $errors = [...$errors, ...$contractErrors];
            if ($contract && $existing?->contracts()->exists()) {
                $warnings[] = 'A déjà un contrat : le contrat du fichier est ignoré (à modifier sur sa fiche).';
                $contract = null;
            }

            $checked[] = [
                'line' => $row['line'],
                'name' => trim((string) ($v['name'] ?? '')),
                'email' => $email,
                'action' => $existing ? 'update' : 'create',
                'existing_id' => $existing?->id,
                'record' => array_filter([
                    'name' => trim((string) ($v['name'] ?? '')) ?: null,
                    'email' => $email ?: null,
                    'matricule' => $matricule ?: null,
                    'job_title' => trim((string) ($v['job_title'] ?? '')) ?: null,
                    'hire_date' => $hireDate ?: null,
                ], fn ($value) => $value !== null),
                'department' => $departmentName ?: null,
                'manager' => filled($v['manager'] ?? null) ? Str::lower(trim((string) $v['manager'])) : null,
                'manager_name' => $managerName,
                'profile' => $profile,
                'contract' => $contract,
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        }

        $rows = collect($checked);

        return [
            'rows' => $checked,
            'errors' => $rows->filter(fn (array $row) => $row['errors'] !== [])->count(),
            'creates' => $rows->where('action', 'create')->count(),
            'updates' => $rows->where('action', 'update')->count(),
            'new_departments' => array_values($newDepartments),
            'file_error' => null,
        ];
    }

    /**
     * Saves everything, or nothing if any row has an error.
     *
     * @return array{created: list<int>, updated: int, departments: int}
     *
     * @throws ValidationException
     */
    public function import(string $path, User $actor): array
    {
        $result = $this->check($path, $actor);

        if ($result['file_error'] || $result['errors'] > 0) {
            throw ValidationException::withMessages(['file' => 'Le fichier contient des erreurs : corrigez-les puis importez-le à nouveau.']);
        }

        return DB::transaction(function () use ($result, $actor) {
            $departments = Department::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [Str::lower(trim($name)) => $id])->all();
            foreach ($result['new_departments'] as $name) {
                $departments[Str::lower($name)] = Department::create(['name' => $name])->id;
            }

            $created = [];
            $users = [];

            foreach ($result['rows'] as $row) {
                $user = $row['existing_id'] ? User::findOrFail($row['existing_id']) : new User;
                $record = $row['record'];

                if (! $user->exists) {
                    // No usable password until the invitation is used.
                    $record += ['password' => Str::random(40), 'role' => Role::User, 'email_verified_at' => now()];
                }
                if ($row['department']) {
                    $record['department_id'] = $departments[Str::lower($row['department'])];
                }

                $user->forceFill($record)->save();
                $users[$row['line']] = $user;
                if ($row['action'] === 'create') {
                    $created[] = $user->id;
                }
            }

            // Managers once everyone exists (a manager can be a row of the same file).
            $lookup = [];
            foreach (User::all() as $known) {
                $lookup[Str::lower($known->email)] = $known;
                if ($known->matricule) {
                    $lookup[Str::lower($known->matricule)] = $known;
                }
            }
            foreach ($result['rows'] as $row) {
                if ($row['manager']) {
                    $users[$row['line']]->forceFill(['manager_id' => $lookup[$row['manager']]->id])->save();
                }
            }
            $this->refuseCycles(array_values($users));

            foreach ($result['rows'] as $row) {
                $user = $users[$row['line']];

                if ($row['profile'] !== []) {
                    $profile = $user->profile ?? new EmployeeProfile(['user_id' => $user->id]);
                    $profile->user_id = $user->id;
                    $profile->fill($row['profile']);
                    $profile->updated_by = $actor->id;
                    $profile->save();
                    PersonalDataAccess::record($actor, $user, 'update_profile', 'Import Excel : '.implode(', ', array_map(fn ($key) => self::COLUMNS[$key][0], array_keys($row['profile']))));
                }

                if ($row['contract']) {
                    $user->contracts()->create([...$row['contract'], 'created_by' => $actor->id]);
                    PersonalDataAccess::record($actor, $user, 'update_contract', "Import Excel : {$row['contract']['type']->label()}");
                }
            }

            return ['created' => $created, 'updated' => count($result['rows']) - count($created), 'departments' => count($result['new_departments'])];
        });
    }

    /**
     * The problems of a checked file as an Excel report.
     *
     * @param  array<string, mixed>  $result  check()
     */
    public function report(array $result): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Erreurs');
        $sheet->fromArray([['Ligne', 'Nom', 'E-mail', 'Erreurs', 'Remarques']], null, 'A1');
        $line = 2;
        foreach ($result['rows'] as $row) {
            if ($row['errors'] === [] && $row['warnings'] === []) {
                continue;
            }
            $sheet->fromArray([[$row['line'], $row['name'], $row['email'], implode("\n", $row['errors']), implode("\n", $row['warnings'])]], null, "A{$line}");
            $line++;
        }
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        $sheet->getStyle('D2:E'.max(2, $line - 1))->getAlignment()->setWrapText(true);
        foreach (['A' => 8, 'B' => 26, 'C' => 32, 'D' => 70, 'E' => 50] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $path = tempnam(sys_get_temp_dir(), 'erreurs');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    // ------------------------------------------------------------------ helpers

    /** @return list<array{line: int, values: array<string, mixed>}>|array{missing: list<string>} */
    private function read(string $path): array
    {
        $sheet = IOFactory::load($path)->getSheet(0);
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $byHeading = [];
        foreach (self::COLUMNS as $key => [$heading]) {
            $byHeading[$this->normalize($heading)] = $key;
            $byHeading[$this->normalize($key)] = $key;
        }

        $map = [];
        for ($c = 1; $c <= $highestColumn; $c++) {
            $heading = $this->normalize((string) $sheet->getCell([$c, 1])->getValue());
            if (isset($byHeading[$heading])) {
                $map[$c] = $byHeading[$heading];
            }
        }

        $missing = array_values(array_map(fn ($key) => self::COLUMNS[$key][0], array_diff(['name', 'email', 'hire_date'], $map)));
        if ($missing !== []) {
            return ['missing' => $missing];
        }

        $rows = [];
        for ($r = 2, $last = $sheet->getHighestDataRow(); $r <= $last; $r++) {
            $values = [];
            foreach ($map as $c => $key) {
                $cell = $sheet->getCell([$c, $r]);
                $value = $cell->getValue();
                if (in_array($key, self::DATES, true) && is_numeric($value)) {
                    $value = ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
                } elseif (is_float($value) || is_int($value)) {
                    // A long identifier typed as a number lost its digits: flagged later.
                    $value = in_array($key, self::TEXT, true) && abs($value) >= 1e15 ? '#NOMBRE' : (string) (is_float($value) && floor($value) == $value ? (int) $value : $value);
                }
                $values[$key] = is_string($value) ? trim($value) : $value;
            }

            if (collect($values)->filter(fn ($value) => filled($value))->isEmpty()) {
                continue; // empty row
            }
            $rows[] = ['line' => $r, 'values' => $values];
        }

        return $rows;
    }

    /** @return array{0: array<string, mixed>, 1: list<string>} The profile fields filled in, and their errors. */
    private function profile(array $v, ?User $existing): array
    {
        $errors = [];
        $data = [];
        foreach (self::PROFILE as $key) {
            $value = $v[$key] ?? null;
            if (blank($value)) {
                continue;
            }
            if ($value === '#NOMBRE') {
                $errors[] = self::COLUMNS[$key][0].' : saisissez-le en texte (Excel a arrondi le nombre).';

                continue;
            }

            $data[$key] = match ($key) {
                'gender' => match ($this->normalize($value)) {
                    'homme', 'h', 'm', 'masculin' => Gender::Male->value,
                    'femme', 'f', 'feminin' => Gender::Female->value,
                    default => $value,
                },
                'marital_status' => match (true) {
                    str_starts_with($this->normalize($value), 'celib') => MaritalStatus::Single->value,
                    str_starts_with($this->normalize($value), 'mari') => MaritalStatus::Married->value,
                    str_starts_with($this->normalize($value), 'divor') => MaritalStatus::Divorced->value,
                    str_starts_with($this->normalize($value), 'veu') => MaritalStatus::Widowed->value,
                    default => $value,
                },
                'birth_date' => $this->date($value) ?: $value,
                'cin' => strtoupper(preg_replace('/[\s.\-]/', '', (string) $value)),
                'cnss_number', 'rib' => preg_replace('/[\s.\-]/', '', (string) $value),
                default => is_string($value) ? $value : (string) $value,
            };
        }
        if ($data === []) {
            return [[], $errors];
        }

        $request = new UpdateEmployeeProfileRequest;
        $validator = Validator::make($data, array_intersect_key($request->rules(), $data), $request->messages(), $request->attributes());
        foreach ($validator->errors()->messages() as $key => $messages) {
            $errors[] = self::COLUMNS[$key][0].' : '.$messages[0];
            unset($data[$key]);
        }

        return [$data, $errors];
    }

    /** @return array{0: ?array<string, mixed>, 1: list<string>} The contract to create (if any column is filled), and its errors. */
    private function contract(array $v, ?string $hireDate): array
    {
        if (collect(self::CONTRACT)->every(fn ($key) => blank($v[$key] ?? null))) {
            return [null, []];
        }

        $errors = [];
        $type = match ($this->normalize((string) ($v['contract_type'] ?? ''))) {
            'cdi' => ContractType::Permanent,
            'cdd' => ContractType::FixedTerm,
            'anapec', 'contrat_anapec', 'contrat_anapec_insertion' => ContractType::Anapec,
            'stage' => ContractType::Internship,
            default => null,
        };
        if (! $type) {
            $errors[] = 'Type de contrat manquant ou inconnu (CDI, CDD, ANAPEC, Stage).';
        }

        $category = null;
        if (filled($v['contract_category'] ?? null)) {
            $category = match (true) {
                str_starts_with($this->normalize($v['contract_category']), 'cadre') => StaffCategory::Executive,
                str_starts_with($this->normalize($v['contract_category']), 'employ') => StaffCategory::Employee,
                str_starts_with($this->normalize($v['contract_category']), 'ouvrier') => StaffCategory::Worker,
                default => null,
            };
            if (! $category) {
                $errors[] = 'Catégorie inconnue (Cadre, Employé, Ouvrier).';
            }
        }

        $start = filled($v['contract_start'] ?? null) ? $this->date($v['contract_start']) : $hireDate;
        $end = filled($v['contract_end'] ?? null) ? $this->date($v['contract_end']) : null;
        if (! $start) {
            $errors[] = 'Début du contrat invalide.';
        }
        if ($end === false) {
            $errors[] = 'Fin du contrat invalide.';
        } elseif ($type?->requiresEndDate() && ! $end) {
            $errors[] = "Fin du contrat obligatoire pour un {$type->label()}.";
        } elseif ($start && $end && $end < $start) {
            $errors[] = 'La fin du contrat est avant son début.';
        }

        $salary = null;
        if (filled($v['gross_salary'] ?? null)) {
            $salary = str_replace([' ', ','], ['', '.'], (string) $v['gross_salary']);
            if (! is_numeric($salary) || $salary < 0) {
                $errors[] = 'Salaire brut invalide.';
            }
        }

        if ($errors !== []) {
            return [null, $errors];
        }

        return [[
            'type' => $type,
            'category' => $category,
            'start_date' => $start,
            'end_date' => $end ?: null,
            'gross_salary' => $salary,
        ], []];
    }

    /** A date as Y-m-d; null when empty; false when unreadable. */
    private function date(mixed $value): string|false|null
    {
        if (blank($value)) {
            return null;
        }
        $value = trim((string) $value);

        foreach (['Y-m-d', 'd/m/Y', 'j/n/Y', 'd-m-Y', 'd.m.Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
            } catch (\Throwable) {
                continue; // not this format
            }
            // Rejects overflows such as 31/02 (which would become 2 March).
            if ($date && $date->format($format) === $value && $date->year > 1900) {
                return $date->toDateString();
            }
        }

        return false;
    }

    private function normalize(string $text): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '_', Str::lower(Str::ascii(str_replace('*', '', $text)))), '_');
    }

    /** @param list<User> $users  Refuses a file whose manager links loop. */
    private function refuseCycles(array $users): void
    {
        foreach ($users as $user) {
            $seen = [$user->id];
            for ($manager = $user->manager_id ? User::find($user->manager_id) : null; $manager; $manager = $manager->manager_id ? User::find($manager->manager_id) : null) {
                if (in_array($manager->id, $seen, true)) {
                    throw ValidationException::withMessages(['file' => "Les responsables forment une boucle autour de {$user->name} : corrigez la colonne Responsable."]);
                }
                $seen[] = $manager->id;
            }
        }
    }

    /** @return array<string, mixed> */
    private function fileError(string $message): array
    {
        return ['rows' => [], 'errors' => 0, 'creates' => 0, 'updates' => 0, 'new_departments' => [], 'file_error' => $message];
    }
}
