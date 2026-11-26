<?php

namespace App\Http\Controllers;

use App\Enums\ReviewRating;
use App\Models\Objective;
use App\Models\Review;
use App\Models\User;
use App\Notifications\AppNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Annual reviews for employees and managers: the employee's self-evaluation
 * and comment, the manager's evaluation (objectives rated, feedback, next
 * year's objectives, recommendation), HR's closing.
 */
class ReviewController extends Controller
{
    /** "Entretiens annuels": my reviews and objectives, and my team's reviews. */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $teamIds = User::whereIn('manager_id', [$user->id, ...$user->delegatingManagerIds()])->pluck('id');

        return Inertia::render('Reviews/Index', [
            'mine' => Review::with(['campaign', 'user:id,name,job_title', 'manager:id,name', 'reviewer:id,name'])
                ->where('user_id', $user->id)->get()->sortByDesc(fn (Review $r) => $r->campaign->year)->values()
                ->map(fn (Review $r) => $this->summary($r, $user)),
            'team' => Review::with(['campaign', 'user:id,name,job_title', 'manager:id,name', 'reviewer:id,name'])
                ->whereIn('user_id', $teamIds)
                ->whereHas('campaign', fn ($q) => $q->where('status', 'open'))
                ->get()->sortBy('user.name')->values()
                ->map(fn (Review $r) => $this->summary($r, $user)),
            'objectives' => Objective::where('user_id', $user->id)->where('year', '>=', now()->year - 1)
                ->orderByDesc('year')->orderByDesc('weight')->get()->map->present(),
        ]);
    }

    public function show(Request $request, Review $review): Response
    {
        $user = $request->user();
        abort_unless($review->canView($user), 403);
        $review->load(['campaign', 'user:id,name,job_title', 'manager:id,name', 'reviewer:id,name']);
        $year = $review->campaign->year;

        return Inertia::render('Reviews/Show', [
            'review' => $review->present($user),
            // Objectives of the reviewed year (rated in this review) and the next one (set in it).
            'objectives' => Objective::where('user_id', $review->user_id)->where('year', $year)->orderByDesc('weight')->get()->map->present(),
            'nextObjectives' => Objective::where('user_id', $review->user_id)->where('year', $year + 1)->orderByDesc('weight')->get()->map->present(),
            'ratings' => ReviewRating::options(),
            'recommendations' => collect(Review::RECOMMENDATIONS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
        ]);
    }

    public function submitSelf(Request $request, Review $review): RedirectResponse
    {
        abort_unless($review->status === 'self' && $review->canAct($request->user()), 403);
        $validated = $request->validate([
            'self_rating' => ['required', Rule::enum(ReviewRating::class)],
            'self_assessment' => ['required', 'string', 'max:5000'],
            'self_wishes' => ['nullable', 'string', 'max:3000'],
        ], [], ['self_rating' => 'appréciation globale', 'self_assessment' => 'bilan de l’année', 'self_wishes' => 'souhaits']);

        $review->update([...$validated, 'status' => 'manager']);
        $reviewer = $review->manager_id ? User::find($review->manager_id) : null;
        \Illuminate\Support\Facades\Notification::send($reviewer ? collect([$reviewer->approvalRecipient($review->user)]) : AppNotice::hr($review->user),
            new AppNotice(':name a rempli son auto-évaluation : à vous d’évaluer.', ['name' => $review->user->name], "/evaluations/{$review->id}"));

        return redirect("/evaluations/{$review->id}")->with('success', 'Auto-évaluation envoyée à votre responsable.');
    }

    public function submitManager(Request $request, Review $review): RedirectResponse
    {
        $user = $request->user();
        abort_unless($review->status === 'manager' && $review->canAct($user), 403);
        $year = $review->campaign->year;
        $current = Objective::where('user_id', $review->user_id)->where('year', $year)->get();

        $validated = $request->validate([
            'overall_rating' => ['required', Rule::enum(ReviewRating::class)],
            'manager_feedback' => ['required', 'string', 'max:5000'],
            'strengths' => ['nullable', 'string', 'max:3000'],
            'improvements' => ['nullable', 'string', 'max:3000'],
            'training_needs' => ['nullable', 'string', 'max:3000'],
            'interview_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'ratings' => ['array'],
            ...$current->mapWithKeys(fn (Objective $o) => ["ratings.{$o->id}.rating" => ['required', Rule::enum(ReviewRating::class)]])->all(),
            'ratings.*.comment' => ['nullable', 'string', 'max:1000'],
            'objectives' => ['array', 'max:5'],
            'objectives.*.title' => ['required', 'string', 'max:150'],
            'objectives.*.target' => ['nullable', 'string', 'max:1000'],
            'objectives.*.weight' => ['required', 'integer', 'min:0', 'max:100'],
            'recommendation' => ['nullable', Rule::in(array_keys(Review::RECOMMENDATIONS))],
            'recommended_job_title' => ['nullable', 'string', 'max:100'],
            'recommended_salary' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'recommendation_reason' => ['required_with:recommendation', 'nullable', 'string', 'max:2000'],
        ], [
            'ratings.*.rating.required' => 'Évaluez chaque objectif de l’année.',
        ], [
            'overall_rating' => 'appréciation globale', 'manager_feedback' => 'appréciation du responsable', 'interview_on' => "date de l'entretien",
            'objectives.*.title' => "intitulé de l'objectif", 'objectives.*.weight' => 'poids', 'recommendation_reason' => 'justification',
        ]);

        $objectives = $validated['objectives'] ?? [];
        if ($objectives !== [] && array_sum(array_column($objectives, 'weight')) !== 100) {
            throw ValidationException::withMessages(['objectives' => 'Le poids des objectifs doit faire 100 % au total.']);
        }
        if (($validated['recommendation'] ?? null) === 'promotion' && blank($validated['recommended_job_title'] ?? null)) {
            throw ValidationException::withMessages(['recommended_job_title' => 'Indiquez le poste proposé pour une promotion.']);
        }
        if (($validated['recommendation'] ?? null) === 'raise' && blank($validated['recommended_salary'] ?? null)) {
            throw ValidationException::withMessages(['recommended_salary' => 'Indiquez le salaire proposé pour une augmentation.']);
        }

        DB::transaction(function () use ($review, $user, $validated, $current, $objectives, $year) {
            foreach ($current as $objective) {
                $objective->update([
                    'rating' => $validated['ratings'][$objective->id]['rating'],
                    'rating_comment' => $validated['ratings'][$objective->id]['comment'] ?? null,
                ]);
            }

            Objective::where('set_in_review_id', $review->id)->delete();
            foreach ($objectives as $objective) {
                Objective::create([...$objective, 'user_id' => $review->user_id, 'year' => $year + 1, 'set_in_review_id' => $review->id]);
            }

            $recommendation = $validated['recommendation'] ?? null;
            $review->update([
                ...collect($validated)->only(['overall_rating', 'manager_feedback', 'strengths', 'improvements', 'training_needs', 'interview_on'])->all(),
                'recommendation' => $recommendation,
                'recommended_job_title' => $recommendation ? ($validated['recommended_job_title'] ?? null) : null,
                'recommended_salary' => $recommendation && filled($validated['recommended_salary'] ?? null) ? (string) $validated['recommended_salary'] : null,
                'recommendation_reason' => $recommendation ? $validated['recommendation_reason'] : null,
                'recommendation_status' => $recommendation ? 'pending' : null,
                'reviewed_by' => $user->id,
                'status' => 'comment',
            ]);
        });

        $review->user->notify(new AppNotice('Votre responsable a rempli votre entretien annuel :year : lisez-le et signez-le.', ['year' => $review->campaign->year], "/evaluations/{$review->id}"));

        return redirect("/evaluations/{$review->id}")->with('success', 'Évaluation envoyée : l’employé peut la lire et la commenter.');
    }

    public function submitComment(Request $request, Review $review): RedirectResponse
    {
        abort_unless($review->status === 'comment' && $review->canAct($request->user()), 403);
        $validated = $request->validate(['employee_comment' => ['nullable', 'string', 'max:3000']], [], ['employee_comment' => 'commentaire']);

        $review->update([...$validated, 'status' => 'hr']);
        \Illuminate\Support\Facades\Notification::send(AppNotice::hr($review->user), new AppNotice('L’entretien annuel de :name est signé : à clôturer.', ['name' => $review->user->name], "/evaluations/{$review->id}"));

        return redirect("/evaluations/{$review->id}")->with('success', 'Entretien signé : le service RH va le clôturer.');
    }

    public function close(Request $request, Review $review): RedirectResponse
    {
        abort_unless($review->status === 'hr' && $review->canAct($request->user()), 403);
        $review->update(['status' => 'closed', 'closed_by' => $request->user()->id, 'closed_at' => now()]);

        return back()->with('success', 'Entretien clôturé.');
    }

    /** @return array<string, mixed> */
    private function summary(Review $review, User $viewer): array
    {
        return [
            'id' => $review->id,
            'year' => $review->campaign->year,
            'due_on' => $review->campaign->due_on?->toDateString(),
            'status' => $review->status,
            'status_label' => Review::STATUS_LABELS[$review->status],
            'name' => $review->user?->name,
            'job_title' => $review->user?->job_title,
            'can_act' => $review->canAct($viewer),
            // The overall rating, once the employee may see it.
            'rating_label' => $viewer->id !== $review->user_id || in_array($review->status, ['comment', 'hr', 'closed'], true) ? $review->overall_rating?->label() : null,
        ];
    }
}
