<?php

namespace Database\Seeders;

use App\Models\Objective;
use App\Models\Review;
use App\Models\ReviewCampaign;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo annual reviews for Atlas Distribution: last year's campaign closed
 * (every review done, objectives set for this year), and this year's campaign
 * in progress with reviews at every step — including two recommendations
 * waiting for HR (a promotion and a raise).
 *
 * Part of `demo:reset`; it also runs alone on an existing demo database
 * (`php artisan db:seed --class=DemoReviewSeeder`): it only adds rows and does
 * nothing when a campaign already exists.
 */
class DemoReviewSeeder extends Seeder
{
    private const OBJECTIVES = [
        'Commercial' => [['Atteindre le chiffre d’affaires annuel', 'Objectif fixé par la direction commerciale'], ['Ouvrir de nouveaux comptes', '10 nouveaux clients actifs'], ['Réduire les délais de paiement clients', 'Moins de 60 jours en moyenne']],
        'Logistique' => [['Zéro accident de travail', 'Aucun arrêt pour accident'], ['Réduire les erreurs de préparation', 'Moins de 0,5 % des commandes'], ['Inventaire tournant à jour', 'Écart de stock inférieur à 1 %']],
        'Comptabilité & finance' => [['Clôturer le mois en 5 jours ouvrés', 'Chaque mois avant le 6'], ['Fiabiliser le recouvrement', 'Relances systématiques à J+15'], ['Préparer l’audit annuel', 'Dossier complet en février']],
        'Informatique' => [['Disponibilité des systèmes', '99,5 % sur l’année'], ['Migrer la messagerie', 'Fin du projet au 2ᵉ trimestre'], ['Former les utilisateurs', 'Deux sessions par semestre']],
        'Ressources humaines' => [['Digitaliser les dossiers du personnel', '100 % des dossiers numérisés'], ['Mener les entretiens annuels', 'Taux de réalisation de 95 %'], ['Plan de formation', 'Budget consommé à 90 %']],
        'Direction' => [['Piloter le plan stratégique', 'Revue trimestrielle avec le comité'], ['Améliorer le climat social', 'Enquête interne annuelle'], ['Maîtriser les charges', 'Budget respecté à ±3 %']],
    ];

    private const RATINGS = ['atteint', 'atteint', 'atteint', 'depasse', 'a_ameliorer'];

    public function run(): void
    {
        if (ReviewCampaign::exists()) {
            $this->command?->warn('Des campagnes d’entretiens existent déjà : rien n’est ajouté.');

            return;
        }

        mt_srand(2027);
        $hr = User::where('email', 'rh@'.DemoSeeder::DOMAIN)->first();
        $year = now()->year;
        $people = User::active()->with('department:id,name')->where('email', 'like', '%@'.DemoSeeder::DOMAIN)->orderBy('id')->get();

        // Last year: closed, objectives set for this year.
        $previous = ReviewCampaign::create(['year' => $year - 1, 'due_on' => "{$year}-01-31", 'status' => 'closed', 'created_by' => $hr?->id]);
        foreach ($people->filter(fn (User $u) => ! $u->hire_date || $u->hire_date->year < $year) as $person) {
            $review = $this->review($previous, $person, 'closed', $hr);
            $this->objectivesFor($person, $year, $review);
        }

        // This year: in progress, every step represented.
        $current = ReviewCampaign::create(['year' => $year, 'due_on' => ($year + 1).'-01-31', 'status' => 'open', 'created_by' => $hr?->id]);
        $steps = ['closed', 'hr', 'comment', 'manager', 'manager', 'self', 'self', 'self'];
        foreach ($people as $i => $person) {
            $status = $steps[$i % count($steps)];
            // The demo employee and their manager: one waiting for each of them.
            if ($person->email === 'employe@'.DemoSeeder::DOMAIN) {
                $status = 'self';
            }
            $review = $this->review($current, $person, $status, $hr);
            if (in_array($status, ['comment', 'hr', 'closed'], true)) {
                $this->objectivesFor($person, $year + 1, $review);
            }
        }

        $this->recommendations($current);
    }

    /** A review filled up to the given step, rating the year's objectives when the manager is done. */
    private function review(ReviewCampaign $campaign, User $person, string $status, ?User $hr): Review
    {
        $order = ['self', 'manager', 'comment', 'hr', 'closed'];
        $step = array_search($status, $order, true);
        $rating = self::RATINGS[mt_rand(0, count(self::RATINGS) - 1)];
        $reviewer = $person->manager_id ? $person->manager_id : $hr?->id;

        $data = ['user_id' => $person->id, 'manager_id' => $person->manager_id, 'status' => $status];
        if ($step >= 1) {
            $data += [
                'self_rating' => $rating === 'a_ameliorer' ? 'atteint' : $rating,
                'self_assessment' => 'Une année chargée : la plupart de mes objectifs sont tenus. Les périodes de pointe ont été difficiles mais l’équipe a suivi.',
                'self_wishes' => mt_rand(0, 1) ? 'Suivre une formation pour progresser dans mon poste.' : null,
            ];
        }
        if ($step >= 2) {
            $data += [
                'overall_rating' => $rating,
                'manager_feedback' => match ($rating) {
                    'depasse' => 'Très belle année, au-delà des attentes. Un exemple pour l’équipe.',
                    'a_ameliorer' => 'Des résultats en retrait ; nous avons convenu d’un plan d’accompagnement.',
                    default => 'Bonne année, objectifs atteints dans l’ensemble. Continuer sur cette lancée.',
                },
                'strengths' => 'Fiabilité, sens du service, bonne entente avec l’équipe.',
                'improvements' => $rating === 'a_ameliorer' ? 'Organisation et respect des délais.' : 'Prendre davantage d’initiatives.',
                'training_needs' => mt_rand(0, 1) ? 'Excel avancé' : null,
                'interview_on' => now()->setYear($campaign->year)->month(12)->day(mt_rand(1, 15))->min(today())->toDateString(),
                'reviewed_by' => $reviewer,
            ];
            Objective::where('user_id', $person->id)->where('year', $campaign->year)->get()
                ->each(fn (Objective $o) => $o->update(['rating' => self::RATINGS[mt_rand(0, count(self::RATINGS) - 1)]]));
        }
        if ($step >= 3) {
            $data['employee_comment'] = mt_rand(0, 2) === 0 ? 'Merci pour cet échange constructif.' : null;
        }
        if ($step >= 4) {
            $data += ['closed_by' => $hr?->id, 'closed_at' => now()->setYear($campaign->year)->month(12)->day(20)->min(now())];
        }

        return $campaign->reviews()->create($data);
    }

    /** 3 weighted objectives for a year, from the person's department. */
    private function objectivesFor(User $person, int $year, Review $review): void
    {
        $list = self::OBJECTIVES[$person->department?->name] ?? self::OBJECTIVES['Direction'];
        foreach ($list as $i => [$title, $target]) {
            Objective::create(['user_id' => $person->id, 'year' => $year, 'set_in_review_id' => $review->id, 'title' => $title, 'target' => $target, 'weight' => [50, 30, 20][$i]]);
        }
    }

    /**
     * Two recommendations waiting for HR on this year's reviews already past the
     * manager, for employees who have a manager (not the director, not cadres).
     */
    public function recommendations(ReviewCampaign $campaign): void
    {
        $done = $campaign->reviews()->whereIn('status', ['comment', 'hr', 'closed'])
            ->whereHas('user', fn ($q) => $q->whereNotNull('manager_id')->where('role', 'user'))
            ->with(['user.contracts', 'user.careerChanges'])->get()
            ->filter(fn (Review $r) => $r->user->currentContract()?->category?->value !== 'cadre')
            ->values();

        if ($first = $done->get(0)) {
            $first->update([
                'overall_rating' => 'depasse',
                'recommendation' => 'promotion',
                'recommended_job_title' => ($first->user->job_title ?? 'Chargé de mission').' senior',
                'recommended_salary' => (string) round(($first->user->salaryOn(today()) ?? 8000) * 1.15, -2),
                'recommendation_reason' => 'Résultats au-delà des objectifs deux années de suite ; encadre déjà de fait les nouveaux arrivants.',
                'recommendation_status' => 'pending',
            ]);
        }
        if ($second = $done->get(1)) {
            $second->update([
                'recommendation' => 'raise',
                'recommended_salary' => (string) round(($second->user->salaryOn(today()) ?? 8000) * 1.06, -2),
                'recommendation_reason' => 'Polyvalence et charge de travail accrue depuis le départ d’un collègue.',
                'recommendation_status' => 'pending',
            ]);
        }
    }
}
