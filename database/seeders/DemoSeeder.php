<?php

namespace Database\Seeders;

use App\Enums\AttestationStatus;
use App\Enums\AttestationType;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\Role;
use App\Models\Attestation;
use App\Models\CompanySetting;
use App\Models\Demande;
use App\Models\DemandeDocument;
use App\Models\Department;
use App\Models\EmployeeDocument;
use App\Models\EmployeeProfile;
use App\Models\Holiday;
use App\Models\User;
use App\Services\AttestationIssuer;
use App\Services\LeaveCalculator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A realistic, entirely fictional company to show the app: "Atlas
 * Distribution", 27 people in 6 departments with managers, personal data,
 * contracts, document folders, a year of leave history (annual, sick,
 * exceptional…) and attestations. Dates are relative to today, so the demo
 * always looks current.
 *
 * Run it with `php artisan demo:reset` (wipes the database first). Every
 * account uses the password "password"; e-mails end in @atlas-demo.test.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public const DOMAIN = 'atlas-demo.test';

    private LeaveCalculator $calculator;

    /** @var array<string, User> */
    private array $people = [];

    private User $hr;

    /** @var array<int, array{key: string, gender: string, category: ?string, salary: int}> Demo details per user id (not columns). */
    private array $meta = [];

    public function run(): void
    {
        fake()->seed(2026);
        mt_srand(2026);
        $this->calculator = app(LeaveCalculator::class);

        $this->company();
        $this->movableHolidays();
        $departments = $this->departments();
        $this->staff($departments);

        foreach ($this->people as $person) {
            $this->profile($person);
            $this->contracts($person);
            $this->folder($person);
            $this->leaveHistory($person);
        }

        $this->showcaseCases();
        $this->attestations();
    }

    private function company(): void
    {
        CompanySetting::create([
            'name' => 'Atlas Distribution',
            'legal_form' => 'SARL',
            'address' => '45, boulevard Abdelmoumen, 4ᵉ étage',
            'city' => 'Casablanca',
            'phone' => '05 22 00 00 00',
            'email' => 'rh@'.self::DOMAIN,
            'ice' => '002345678000091',
            'rc' => '412345',
            'if_number' => '40123456',
            'cnss_number' => '7012345',
            'signatory_name' => 'Nadia El Amrani',
            'signatory_title' => 'Directrice des ressources humaines',
            'brand_color' => '#1D4E89',
            'cndp_number' => 'D-GC-000/2026 (exemple)',
            'privacy_contact' => 'dpo@'.self::DOMAIN,
        ]);
    }

    /** Religious holidays move each year; these dates are approximate, for the demo only. */
    private function movableHolidays(): void
    {
        $year = now()->year;
        $approximate = [
            "{$year}-03-20" => 'Aïd al-Fitr',
            "{$year}-03-21" => 'Aïd al-Fitr (2ᵉ jour)',
            "{$year}-05-27" => 'Aïd al-Adha',
            "{$year}-05-28" => 'Aïd al-Adha (2ᵉ jour)',
            "{$year}-06-16" => 'Nouvel an de l\'Hégire',
            "{$year}-08-25" => 'Aïd al-Mawlid',
            "{$year}-08-26" => 'Aïd al-Mawlid (2ᵉ jour)',
        ];

        foreach ($approximate as $date => $name) {
            Holiday::firstOrCreate(['date' => $date, 'recurring' => false], ['name' => $name]);
        }
    }

    /** @return array<string, Department> */
    private function departments(): array
    {
        return collect(['Direction', 'Ressources humaines', 'Comptabilité & finance', 'Commercial', 'Logistique', 'Informatique'])
            ->mapWithKeys(fn (string $name) => [$name => Department::create(['name' => $name])])
            ->all();
    }

    /**
     * Who works here. Columns: key, name, gender, department, job, category,
     * months since hire (or a date), monthly gross salary, manager key,
     * role, login e-mail (else firstname.lastname@…).
     *
     * @param  array<string, Department>  $departments
     */
    private function staff(array $departments): void
    {
        $rows = [
            ['dg', 'Youssef Benjelloun', 'homme', 'Direction', 'Directeur général', 'cadre', 170, 52000, null, Role::Admin, 'admin'],
            ['assistante', 'Fatima Zahra Lamrani', 'femme', 'Direction', 'Assistante de direction', 'employe', 98, 9800, 'dg', Role::User, null],
            ['drh', 'Nadia El Amrani', 'femme', 'Ressources humaines', 'Directrice des ressources humaines', 'cadre', 146, 34000, 'dg', Role::Hr, 'rh'],
            ['charge_rh', 'Salma Idrissi', 'femme', 'Ressources humaines', 'Chargée RH', 'employe', 40, 10500, 'drh', Role::Hr, null],
            ['paie', 'Hicham Tazi', 'homme', 'Comptabilité & finance', 'Responsable paie et comptabilité', 'cadre', 120, 26000, 'dg', Role::Payroll, 'paie'],
            ['comptable', 'Imane Chraibi', 'femme', 'Comptabilité & finance', 'Comptable', 'employe', 62, 11200, 'paie', Role::User, null],
            ['fournisseurs', 'Omar Berrada', 'homme', 'Comptabilité & finance', 'Comptable fournisseurs', 'employe', 11, 8500, 'paie', Role::User, null],
            ['stagiaire', 'Kenza Alaoui', 'femme', 'Comptabilité & finance', 'Stagiaire comptabilité', null, 4, 3000, 'paie', Role::User, null],
            ['dir_com', 'Mehdi Lahlou', 'homme', 'Commercial', 'Directeur commercial', 'cadre', 110, 31000, 'dg', Role::User, 'manager'],
            ['sara', 'Sara Bennani', 'femme', 'Commercial', 'Commerciale grands comptes', 'employe', 52, 12500, 'dir_com', Role::User, 'employe'],
            ['amine', 'Amine Kettani', 'homme', 'Commercial', 'Commercial terrain', 'employe', 75, 9900, 'dir_com', Role::User, null],
            ['ghita', 'Ghita Squalli', 'femme', 'Commercial', 'Assistante commerciale', 'employe', 33, 7800, 'dir_com', Role::User, null],
            ['reda', 'Reda Fassi', 'homme', 'Commercial', 'Commercial sédentaire', 'employe', 9, 5200, 'dir_com', Role::User, null],
            ['leila', 'Leila Ouazzani', 'femme', 'Commercial', 'Cheffe de produit', 'cadre', 2, 19000, 'dir_com', Role::User, null],
            ['anas', 'Anas Belkadi', 'homme', 'Commercial', 'Commercial terrain', 'employe', 27, 9400, 'dir_com', Role::User, null],
            ['logistique', 'Karim Naciri', 'homme', 'Logistique', 'Responsable logistique', 'cadre', 132, 24000, 'dg', Role::User, null],
            ['cariste', 'Mustapha Ait Taleb', 'homme', 'Logistique', 'Cariste', 'ouvrier', 88, 4600, 'logistique', Role::User, null],
            ['magasinier', 'Brahim Ouhssain', 'homme', 'Logistique', 'Magasinier', 'ouvrier', 57, 4300, 'logistique', Role::User, null],
            ['chauffeur', 'Said Amrani', 'homme', 'Logistique', 'Chauffeur livreur', 'ouvrier', 45, 4500, 'logistique', Role::User, null],
            ['preparateur', 'Abdelali Ziani', 'homme', 'Logistique', 'Préparateur de commandes', 'ouvrier', 21, 3900, 'logistique', Role::User, null],
            ['rachid', 'Rachid Boukhris', 'homme', 'Logistique', 'Chef d\'équipe entrepôt', 'employe', 30, 7200, 'logistique', Role::User, null],
            ['hamza', 'Hamza El Ouardi', 'homme', 'Logistique', 'Cariste', 'ouvrier', 40, 4200, 'logistique', Role::User, null],
            ['dsi', 'Zineb Harti', 'femme', 'Informatique', 'Responsable des systèmes d\'information', 'cadre', 84, 29000, 'dg', Role::User, null],
            ['dev', 'Adil Mansouri', 'homme', 'Informatique', 'Développeur', 'cadre', 46, 17500, 'dsi', Role::User, null],
            ['support', 'Yassine Cherkaoui', 'homme', 'Informatique', 'Technicien support', 'employe', 35, 8200, 'dsi', Role::User, null],
            ['nora', 'Nora Benali', 'femme', 'Informatique', 'Développeuse', 'cadre', 4, 16000, 'dsi', Role::User, null],
            ['comptable2', 'Mounia Rami', 'femme', 'Comptabilité & finance', 'Contrôleuse de gestion', 'cadre', 23, 18500, 'paie', Role::User, null],
        ];

        foreach ($rows as $i => [$key, $name, $gender, $department, $job, $category, $months, $salary, $manager, $role, $login]) {
            $hire = now()->subMonthsNoOverflow($months)->startOfMonth();
            // Hires start on a working day.
            while (! $this->calculator->isWorkingDay($hire)) {
                $hire->addDay();
            }

            $user = new User;
            $user->forceFill([
                'name' => $name,
                'email' => ($login ?? Str::slug(Str::before($name, ' ').'.'.Str::afterLast($name, ' '), '.')).'@'.self::DOMAIN,
                'password' => self::PASSWORD,
                'role' => $role,
                'email_verified_at' => now(),
                'privacy_acknowledged_at' => $login === 'employe' ? null : now()->subMonths(2),
                'matricule' => sprintf('ATL-%03d', $i + 1),
                'department_id' => $departments[$department]->id,
                'job_title' => $job,
                'hire_date' => $hire->toDateString(),
                'manager_id' => $manager ? $this->people[$manager]->id : null,
                // In the app for two years, so last year's unused days carry over.
                'created_at' => now()->subYears(2)->max($hire),
            ])->save();

            $this->meta[$user->id] = compact('key', 'gender', 'category', 'salary');
            $this->people[$key] = $user;
        }

        $this->hr = $this->people['drh'];
    }

    private function profile(User $person): void
    {
        $demo = $this->meta[$person->id];

        // A few files are left incomplete, as in real life.
        if (in_array($demo['key'], ['stagiaire', 'nora'], true)) {
            EmployeeProfile::factory()->for($person)->create([
                'gender' => $demo['gender'],
                'rib' => null,
                'cnss_number' => null,
                'updated_by' => $this->hr->id,
            ]);

            return;
        }

        EmployeeProfile::factory()->for($person)->create([
            'gender' => $demo['gender'],
            'birth_date' => now()->subYears(mt_rand(23, 56))->subDays(mt_rand(0, 360))->toDateString(),
            'updated_by' => $this->hr->id,
        ]);
    }

    private function contracts(User $person): void
    {
        $demo = $this->meta[$person->id];
        $hire = Carbon::parse($person->hire_date);
        $base = ['category' => $demo['category'], 'job_title' => $person->job_title, 'gross_salary' => (string) $demo['salary'], 'created_by' => $this->hr->id];

        match ($demo['key']) {
            // A CDD that ends in three weeks: flagged on the overview.
            'fournisseurs' => $person->contracts()->create([...$base, 'type' => 'cdd', 'start_date' => $hire, 'end_date' => now()->addWeeks(3)->toDateString(), 'trial_end' => $hire->copy()->addDays(13)]),
            'stagiaire' => $person->contracts()->create([...$base, 'type' => 'stage', 'start_date' => $hire, 'end_date' => now()->addDays(12)->toDateString()]),
            'reda' => $person->contracts()->create([...$base, 'type' => 'anapec', 'start_date' => $hire, 'end_date' => $hire->copy()->addMonths(24)->subDay()]),
            // Hired two months ago: still in the trial period (3 months for a cadre).
            'leila', 'nora' => $person->contracts()->create([...$base, 'type' => 'cdi', 'start_date' => $hire, 'trial_end' => $hire->copy()->addMonths(3)->subDay()]),
            // CDD, then converted into a CDI.
            'rachid' => [
                $person->contracts()->create([...$base, 'type' => 'cdd', 'gross_salary' => '6500', 'start_date' => $hire, 'end_date' => $hire->copy()->addYear()->subDay(), 'trial_end' => $hire->copy()->addDays(13)]),
                $person->contracts()->create([...$base, 'type' => 'cdi', 'start_date' => $hire->copy()->addYear(), 'notes' => 'CDI à l\'issue du CDD.']),
            ],
            // Left the company last spring.
            'hamza' => $person->contracts()->create([...$base, 'type' => 'cdi', 'start_date' => $hire, 'end_date' => now()->subMonths(7)->endOfMonth()->toDateString(), 'notes' => 'Démission.']),
            default => $person->contracts()->create([...$base, 'type' => 'cdi', 'start_date' => $hire]),
        };

        if ($demo['key'] === 'hamza') {
            $person->forceFill(['deactivated_at' => now()->subMonths(7)->endOfMonth()->addDay()])->save();
        }
    }

    private function folder(User $person): void
    {
        $key = $this->meta[$person->id]['key'];
        $this->addDocument($person, 'contrat', 'Contrat de travail', null);

        $cinExpiry = match ($key) {
            'amine' => now()->addDays(18),       // to renew soon
            'magasinier' => now()->subDays(9),   // already expired
            default => now()->addYears(mt_rand(2, 9)),
        };
        $this->addDocument($person, 'cin', 'Copie CIN', $cinExpiry->toDateString());

        if (in_array($this->meta[$person->id]['category'], ['cadre'], true) && mt_rand(0, 1)) {
            $this->addDocument($person, 'diplome', 'Diplôme', null);
        }

        // Warehouse staff need a valid medical aptitude certificate.
        if (in_array($key, ['cariste', 'chauffeur', 'preparateur'], true)) {
            $expiry = $key === 'chauffeur' ? now()->addDays(25) : now()->addMonths(mt_rand(3, 11));
            $this->addDocument($person, 'aptitude_medicale', "Certificat d'aptitude médicale", $expiry->toDateString());
        }
    }

    private function addDocument(User $person, string $category, string $title, ?string $expiresOn): void
    {
        $path = "employee-documents/{$person->id}/".Str::uuid().'.pdf';
        $content = self::pdf("{$title} - {$person->name}");
        Storage::disk(EmployeeDocument::DISK)->put($path, $content);

        EmployeeDocument::create([
            'user_id' => $person->id,
            'uploaded_by' => $this->hr->id,
            'category' => $category,
            'title' => $category === 'diplome' ? 'Master / licence' : null,
            'expires_on' => $expiresOn,
            'original_name' => Str::slug($title).'.pdf',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size' => strlen($content),
        ]);
    }

    /**
     * About a year of leave per person, never overlapping and within the
     * rules: annual leave only once earned, exceptional leave with its
     * certificate, past leave decided, upcoming leave still waiting.
     */
    private function leaveHistory(User $person): void
    {
        $hire = Carbon::parse($person->hire_date);
        $cursor = now()->subMonths(12)->max($hire->copy()->addWeeks(2))->startOfDay();
        $end = $person->isActive() ? now()->addWeeks(6) : Carbon::parse($person->deactivated_at)->subDay();
        $annualByYear = [];

        while (true) {
            $cursor->addDays(mt_rand(35, 80));
            $start = $this->nextWorkingDay($cursor);

            if ($start->gte($end)) {
                break;
            }

            $type = $this->pickType($person, $start, $annualByYear);
            // Nobody declares a sick leave in advance.
            if ($type === LeaveType::Sick && $start->gt(today())) {
                $type = LeaveType::Recovery;
            }
            $days = match ($type) {
                LeaveType::Annual => mt_rand(2, 6),
                LeaveType::Sick => mt_rand(1, 6),
                LeaveType::Unpaid, LeaveType::Recovery => 1,
                LeaveType::Birth, LeaveType::Death => 3,
                LeaveType::Marriage => 4,
            };

            $leaveEnd = $type === LeaveType::Sick
                ? $start->copy()->addDays($days - 1)  // sick leave runs in calendar days
                : $this->addWorkingDays($start, $days);

            if ($type === LeaveType::Annual) {
                $annualByYear[$start->year] = ($annualByYear[$start->year] ?? 0) + $days;
            }

            $this->createLeave($person, $type, $start, $leaveEnd);
            $cursor = $leaveEnd->copy();
        }
    }

    /** @param array<int, int> $annualByYear */
    private function pickType(User $person, Carbon $start, array $annualByYear): LeaveType
    {
        $eligible = Carbon::parse($person->hire_date)->addMonths(config('conge.eligibility_months'));
        // Stay under what is earned (from the hire date, with carry-over) by then, or by today
        // for upcoming leave: the balance shown today must never go negative.
        $earned = $this->calculator->availableDays($person, $start->year, $start->copy()->min(now()));
        $canTakeAnnual = $start->gte($eligible) && ($annualByYear[$start->year] ?? 0) + 6 <= min(14, $earned);

        if ($canTakeAnnual && mt_rand(1, 100) <= 70) {
            return LeaveType::Annual;
        }

        // Everything else: mostly recovery days, some sick leave, a little unpaid leave.
        $roll = mt_rand(1, 100);

        return match (true) {
            $roll <= 30 => LeaveType::Sick,
            $roll <= 75 => LeaveType::Recovery,
            $roll <= 93 => LeaveType::Unpaid,
            default => LeaveType::Death,
        };
    }

    private function createLeave(User $person, LeaveType $type, Carbon $start, Carbon $end): Demande
    {
        $hasManager = $person->manager_id !== null;
        $past = $end->lt(today());
        $roll = mt_rand(1, 100);

        $status = match (true) {
            $past && $roll <= 8 && ! $type->isDeclared() => LeaveStatus::Refused,
            $past && $roll <= 12 => LeaveStatus::Cancelled,
            $past, $start->lte(today()) => LeaveStatus::Accepted,
            $type->isDeclared() => LeaveStatus::Pending,
            // Most upcoming leave is already decided; a few still wait.
            $roll <= 15 => LeaveStatus::Pending,
            $roll <= 25 && $hasManager => LeaveStatus::ManagerApproved,
            default => LeaveStatus::Accepted,
        };

        $demande = new Demande;
        $demande->forceFill([
            'user_id' => $person->id,
            'type_conge' => $type,
            'date_debut' => $start->toDateString(),
            'date_fin' => $end->toDateString(),
            'start_half' => false,
            'end_half' => false,
            'date_demande' => $type->isDeclared() ? $start->toDateString() : $start->copy()->subDays(mt_rand(7, 30))->min(today())->toDateString(),
            'etat' => $status,
            'valide_par' => in_array($status, [LeaveStatus::Accepted, LeaveStatus::Refused, LeaveStatus::Cancelled], true) ? $this->hr->id : null,
            'comment' => match ($type) {
                LeaveType::Recovery => 'Récupération du samedi travaillé (inventaire).',
                LeaveType::Death => 'Décès d\'un proche.',
                default => null,
            },
            'manager_comment' => null,
        ])->save();

        // Certificates for the leave types that need one, once it is accepted or still waiting.
        if ($demande->needsDocument() && $status !== LeaveStatus::Refused) {
            $this->addCertificate($demande, $type === LeaveType::Sick ? 'Certificat médical' : 'Justificatif');
        }

        // CNSS dossiers of past long sick leave were filed a few days after.
        if ($demande->needsCnssFiling() && $past && $status === LeaveStatus::Accepted) {
            $demande->forceFill(['cnss_filed_on' => $start->copy()->addDays(mt_rand(3, 15))->min(today())->toDateString()])->save();
        }

        return $demande;
    }

    private function addCertificate(Demande $demande, string $title): void
    {
        $path = "documents/{$demande->id}/".Str::uuid().'.pdf';
        $content = self::pdf("{$title} - {$demande->user->name}");
        Storage::disk(DemandeDocument::DISK)->put($path, $content);

        $demande->documents()->create([
            'uploaded_by' => $demande->user_id,
            'original_name' => Str::slug($title).'.pdf',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size' => strlen($content),
        ]);
    }

    /** A few situations that show off the app on day one. */
    private function showcaseCases(): void
    {
        // Off sick this week, CNSS dossier to file.
        $this->clearFrom($this->people['cariste'], now()->subDays(3));
        $this->createLeave($this->people['cariste'], LeaveType::Sick, now()->subDays(2)->startOfDay(), now()->addDays(4)->startOfDay())
            ->forceFill(['etat' => LeaveStatus::Accepted, 'cnss_filed_on' => null])->save();

        // Marriage next month, certificate not uploaded yet: HR cannot accept it.
        $this->clearFrom($this->people['ghita'], now()->addWeeks(3));
        $start = $this->nextWorkingDay(now()->addWeeks(5)->startOfWeek());
        $marriage = $this->createLeave($this->people['ghita'], LeaveType::Marriage, $start, $this->addWorkingDays($start, 4));
        $marriage->documents->each->delete();
        $marriage->forceFill(['etat' => LeaveStatus::Pending, 'comment' => 'Mariage le samedi précédent.'])->save();

        // An accepted leave the employee asks to cancel.
        $this->clearFrom($this->people['dev'], now()->addWeeks(2));
        $start = $this->nextWorkingDay(now()->addWeeks(3)->startOfWeek());
        $this->createLeave($this->people['dev'], LeaveType::Annual, $start, $this->addWorkingDays($start, 3))
            ->forceFill(['etat' => LeaveStatus::CancellationRequested])->save();

        // The demo employee: one leave waiting for the manager.
        $this->clearFrom($this->people['sara'], now()->addWeek());
        $start = $this->nextWorkingDay(now()->addWeeks(2)->startOfWeek());
        $this->createLeave($this->people['sara'], LeaveType::Annual, $start, $this->addWorkingDays($start, 2))
            ->forceFill(['etat' => LeaveStatus::Pending, 'comment' => 'Vacances en famille.'])->save();
    }

    /** Removes someone's leave from a date on, to place a showcase case without overlap. */
    private function clearFrom(User $person, Carbon $from): void
    {
        $person->demandes()->whereDate('date_fin', '>=', $from->toDateString())->get()->each->delete();
    }

    private function attestations(): void
    {
        $issuer = app(AttestationIssuer::class);

        foreach ([['sara', AttestationType::Work, 'Demande de visa'], ['dev', AttestationType::Salary, 'Dossier de crédit immobilier'], ['comptable', AttestationType::Work, null]] as [$key, $type, $purpose]) {
            $attestation = $this->people[$key]->attestations()->make([
                'type' => $type, 'purpose' => $purpose, 'status' => AttestationStatus::Requested, 'requested_by' => $this->people[$key]->id,
            ]);
            $issuer->issue($attestation, $this->hr);
            $attestation->forceFill(['created_at' => now()->subDays(mt_rand(5, 60))])->save();
        }

        // Waiting for HR.
        $this->people['amine']->attestations()->create(['type' => AttestationType::Salary, 'purpose' => 'Location d\'appartement', 'status' => AttestationStatus::Requested, 'requested_by' => $this->people['amine']->id]);
        $this->people['ghita']->attestations()->create(['type' => AttestationType::Work, 'purpose' => 'Dossier de mariage', 'status' => AttestationStatus::Requested, 'requested_by' => $this->people['ghita']->id]);

        // Refused.
        Attestation::create([
            'user_id' => $this->people['reda']->id, 'type' => AttestationType::Salary, 'status' => AttestationStatus::Refused,
            'requested_by' => $this->people['reda']->id, 'handled_by' => $this->hr->id,
            'refusal_reason' => 'Contrat ANAPEC : demandez une attestation de travail.',
        ]);
    }

    private function nextWorkingDay(Carbon $day): Carbon
    {
        $day = $day->copy()->startOfDay();
        while (! $this->calculator->isWorkingDay($day)) {
            $day->addDay();
        }

        return $day;
    }

    /** The day on which $days working days starting on $start end. */
    private function addWorkingDays(Carbon $start, int $days): Carbon
    {
        $day = $start->copy();
        $counted = $this->calculator->isWorkingDay($day) ? 1 : 0;

        while ($counted < $days) {
            $day->addDay();
            if ($this->calculator->isWorkingDay($day)) {
                $counted++;
            }
        }

        return $day;
    }

    /** A one-page PDF placeholder for demo documents. */
    public static function pdf(string $text): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], iconv('UTF-8', 'Windows-1252//TRANSLIT', $text) ?: 'Document');
        $stream = "BT /F1 18 Tf 72 760 Td ({$text}) Tj 0 -30 Td /F1 11 Tf (Document de demonstration - Atlas Distribution) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
