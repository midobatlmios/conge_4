<?php

namespace Database\Seeders;

use App\Models\Candidate;
use App\Models\CandidateInterview;
use App\Models\Department;
use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo recruitment for Atlas Distribution: three published offers with
 * candidates at every stage (CVs, interviews, evaluations), a draft, and a
 * closed offer whose hired candidate is a real demo employee (Nora Benali).
 * Candidate e-mails end in @example.test: nothing is ever sent to them.
 *
 * Part of `demo:reset`; it also runs alone on an existing demo database
 * (`php artisan db:seed --class=DemoRecruitmentSeeder`): it only adds rows and
 * does nothing when offers already exist.
 */
class DemoRecruitmentSeeder extends Seeder
{
    public function run(): void
    {
        if (JobOffer::exists()) {
            $this->command?->warn('Des offres d’emploi existent déjà : rien n’est ajouté.');

            return;
        }

        mt_srand(2028);
        $hr = $this->person('rh');
        $department = fn (string $name) => Department::where('name', $name)->value('id');

        $sales = $this->offer('Commercial terrain (H/F)', 'cdi', $department('Commercial'), 'Casablanca', $this->person('manager'), 'published', 20,
            "Au sein de l'équipe commerciale, vous développez un portefeuille de clients professionnels sur la région du Grand Casablanca : prospection, visites, négociation et suivi des commandes.",
            "Bac+2/3 en commerce, 2 ans d'expérience en vente terrain, permis B, goût du challenge.");
        $picker = $this->offer('Préparateur de commandes (H/F)', 'cdd', $department('Logistique'), 'Ain Sebaa', $this->person('karim.naciri'), 'published', 9,
            'Préparation des commandes clients dans notre entrepôt : prélèvement, contrôle, emballage et chargement. CDD de 6 mois, renouvelable.',
            'Première expérience en entrepôt appréciée, rigueur, respect des consignes de sécurité. CACES apprécié.');
        $dev = $this->offer('Développeur full-stack Laravel / React (H/F)', 'cdi', $department('Informatique'), 'Casablanca (hybride)', $this->person('zineb.harti'), 'published', 35,
            'Vous faites évoluer nos outils internes (gestion commerciale, logistique, RH) avec Laravel et React, de la conception à la mise en production.',
            '3 ans d’expérience en PHP/Laravel et JavaScript/React, bonnes pratiques (tests, revues de code), autonomie.');
        $this->offer('Contrôleur de gestion junior (H/F)', 'cdi', $department('Comptabilité & finance'), 'Casablanca', $this->person('paie'), 'draft', 0,
            'Reporting mensuel, suivi budgétaire et analyses de rentabilité, en appui du responsable financier.', 'Master en finance ou école de commerce, Excel avancé.');
        $filled = $this->offer('Développeuse / Développeur (H/F)', 'cdi', $department('Informatique'), 'Casablanca', $this->person('zineb.harti'), 'closed', 160,
            'Poste pourvu.', null);

        $this->candidates($sales, [
            ['Youssef Amrani', 'new', 0], ['Salma Bennis', 'new', 0], ['Hamid Ouali', 'screening', 0],
            ['Rim El Fassi', 'interview', 1], ['Anouar Tahiri', 'interview', 1], ['Kawtar Lazrak', 'offer', 2], ['Mourad Ziani', 'rejected', 1],
        ], $hr);
        $this->candidates($picker, [
            ['Abdelkader Saidi', 'new', 0], ['Mohamed Rahali', 'screening', 0], ['Noureddine Bakkali', 'interview', 1], ['Ilyas Moutawakil', 'rejected', 0],
        ], $hr);
        $this->candidates($dev, [
            ['Mehdi Bouzid', 'new', 0], ['Hajar Chakir', 'screening', 0], ['Othmane Kadiri', 'interview', 2], ['Lina Sabri', 'rejected', 1],
        ], $hr);

        // The closed offer: Nora Benali, now an employee, was hired through it.
        $nora = $this->person('nora.benali');
        $this->candidates($filled, [['Nora Benali', 'hired', 2], ['Karim Lamrani', 'rejected', 1]], $hr, $nora);
    }

    private function person(string $login): ?User
    {
        return User::where('email', "{$login}@".DemoSeeder::DOMAIN)->first();
    }

    private function offer(string $title, string $type, ?int $departmentId, string $location, ?User $manager, string $status, int $daysAgo, string $description, ?string $requirements): JobOffer
    {
        return JobOffer::create([
            'title' => $title, 'contract_type' => $type, 'department_id' => $departmentId, 'location' => $location,
            'hiring_manager_id' => $manager?->id, 'status' => $status, 'description' => $description, 'requirements' => $requirements,
            'published_at' => $status === 'draft' ? null : now()->subDays($daysAgo),
            'closes_on' => $status === 'published' ? today()->addDays(mt_rand(15, 40))->toDateString() : null,
            'created_by' => $this->person('rh')?->id,
        ]);
    }

    /** @param list<array{0: string, 1: string, 2: int}> $rows name, stage, interviews */
    private function candidates(JobOffer $offer, array $rows, ?User $hr, ?User $hired = null): void
    {
        $ratings = ['atteint', 'depasse', 'atteint', 'a_ameliorer'];

        foreach ($rows as [$name, $stage, $interviews]) {
            $applied = ($offer->published_at ?? now())->copy()->addDays(mt_rand(1, 6));
            $applied = $applied->gt(now()) ? now()->subHours(mt_rand(2, 30)) : $applied;
            $path = "candidates/{$offer->id}/".Str::uuid().'.pdf';
            Storage::disk(Candidate::DISK)->put($path, DemoSeeder::pdf("CV - {$name}"));

            $isHired = $stage === 'hired' && $hired;
            $candidate = $offer->candidates()->create([
                'name' => $name,
                'email' => Str::slug($name, '.').'@example.test',
                'phone' => '06 '.mt_rand(10, 99).' '.mt_rand(10, 99).' '.mt_rand(10, 99).' '.mt_rand(10, 99),
                'message' => mt_rand(0, 1) ? 'Votre offre correspond à mon parcours ; je serais ravi(e) d’échanger avec vous.' : null,
                'cv_path' => $path,
                'cv_original_name' => 'CV-'.Str::slug($name).'.pdf',
                'cv_mime_type' => 'application/pdf',
                'source' => mt_rand(0, 3) ? 'careers_page' : 'manual',
                'stage' => $stage,
                'stage_changed_at' => now()->subDays(mt_rand(0, 5)),
                'rejection_reason' => $stage === 'rejected' ? 'Profil moins adapté que les autres candidats retenus.' : null,
                'hired_user_id' => $isHired ? $hired->id : null,
                'hired_at' => $isHired ? $hired->hire_date?->copy()->subDays(20) : null,
                'consent_at' => $applied,
                'created_by' => $hr?->id,
            ]);
            $candidate->forceFill(['created_at' => $isHired ? $hired->hire_date->copy()->subDays(45) : $applied])->save();

            for ($i = 0; $i < $interviews; $i++) {
                $when = $candidate->created_at->copy()->addDays(5 + 4 * $i)->setTime(10 + $i * 4, 0);
                $past = $when->lt(now());
                CandidateInterview::create([
                    'candidate_id' => $candidate->id,
                    'scheduled_at' => $past ? $when : now()->addDays(2 + $i)->setTime(10, 0),
                    'interviewer_id' => $offer->hiring_manager_id ?? $hr?->id,
                    'mode' => $i === 0 ? 'Visio (Teams)' : 'Au siège',
                    'rating' => $past || $stage !== 'interview' ? $ratings[mt_rand(0, count($ratings) - 1)] : null,
                    'comment' => $past || $stage !== 'interview' ? 'Bonne présentation, motivation claire ; à confirmer sur les aspects techniques.' : null,
                    'created_by' => $hr?->id,
                ]);
            }
        }
    }
}
