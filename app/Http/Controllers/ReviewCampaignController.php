<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\ReviewCampaign;
use App\Models\User;
use App\Notifications\AppNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HR side of annual reviews: open a year's campaign (one review per active
 * employee), follow progress, close reviews and the campaign, and act on
 * managers' promotion / raise recommendations. Viewing: Admin, RH, Paie;
 * changes: Admin and RH (routes).
 */
class ReviewCampaignController extends Controller
{
    public function index(Request $request): Response
    {
        $campaigns = ReviewCampaign::orderByDesc('year')->get();
        $campaign = $campaigns->firstWhere('year', $request->integer('year')) ?? $campaigns->first();
        $reviews = $campaign
            ? $campaign->reviews()->with(['user:id,name,job_title,department_id', 'user.department:id,name', 'manager:id,name'])->get()->sortBy('user.name')->values()
            : collect();

        return Inertia::render('Admin/Reviews', [
            'campaigns' => $campaigns->map(fn (ReviewCampaign $c) => ['year' => $c->year, 'status' => $c->status]),
            'campaign' => $campaign ? ['id' => $campaign->id, 'year' => $campaign->year, 'status' => $campaign->status, 'due_on' => $campaign->due_on?->toDateString()] : null,
            'counts' => collect(Review::STATUS_LABELS)->map(fn ($label, $status) => ['status' => $status, 'label' => $label, 'count' => $reviews->where('status', $status)->count()])->values(),
            'reviews' => $reviews->map(fn (Review $r) => [
                'id' => $r->id,
                'user_id' => $r->user_id,
                'name' => $r->user?->name,
                'department' => $r->user?->department?->name,
                'manager' => $r->manager?->name ?? 'RH',
                'status' => $r->status,
                'status_label' => Review::STATUS_LABELS[$r->status],
                'rating_label' => $r->overall_rating?->label(),
            ]),
            'recommendations' => $reviews->filter(fn (Review $r) => $r->recommendation !== null)->map(fn (Review $r) => [
                'id' => $r->id,
                'user_id' => $r->user_id,
                'name' => $r->user?->name,
                'kind_label' => Review::RECOMMENDATIONS[$r->recommendation],
                'job_title' => $r->recommended_job_title,
                'salary' => $r->recommended_salary === null ? null : (float) $r->recommended_salary,
                'reason' => $r->recommendation_reason,
                'status' => $r->recommendation_status,
            ])->values(),
            'nextYear' => ($campaigns->max('year') ?? now()->year - 1) + 1,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:'.(now()->year + 1), 'unique:review_campaigns,year'],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
        ], ['year.unique' => 'La campagne de cette année existe déjà.'], ['year' => 'année', 'due_on' => 'date limite']);

        $count = DB::transaction(function () use ($request, $validated) {
            $campaign = ReviewCampaign::create([...$validated, 'status' => 'open', 'created_by' => $request->user()->id]);
            $people = User::active()->where(fn ($q) => $q->whereNull('hire_date')->orWhereDate('hire_date', '<=', "{$validated['year']}-12-31"))->get();
            foreach ($people as $person) {
                $review = $campaign->reviews()->create(['user_id' => $person->id, 'manager_id' => $person->manager_id, 'status' => 'self']);
                $person->notify(new AppNotice('Votre entretien annuel :year est ouvert : remplissez votre auto-évaluation.', ['year' => $campaign->year], "/evaluations/{$review->id}"));
            }

            return $people->count();
        });

        return redirect("/admin/reviews?year={$validated['year']}")->with('success', "Campagne {$validated['year']} ouverte : {$count} entretien(s) à mener.");
    }

    public function closeCampaign(ReviewCampaign $campaign): RedirectResponse
    {
        $campaign->update(['status' => 'closed']);

        return back()->with('success', "Campagne {$campaign->year} clôturée.");
    }

    public function declineRecommendation(Review $review): RedirectResponse
    {
        abort_unless($review->recommendation_status === 'pending', 403);
        $review->update(['recommendation_status' => 'declined']);

        return back()->with('success', 'Recommandation écartée.');
    }
}
