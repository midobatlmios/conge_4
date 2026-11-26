<?php

use App\Enums\Role;
use App\Models\CareerChange;
use App\Models\EmploymentContract;
use App\Models\Objective;
use App\Models\Review;
use App\Models\ReviewCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->travelTo('2026-12-10'));

/** RH, a manager and their employee (with a contract), and the 2026 campaign opened by RH. */
function reviewSetup(): array
{
    $rh = User::factory()->create(['role' => Role::Hr, 'hire_date' => '2015-01-01']);
    $manager = User::factory()->create(['role' => Role::User, 'hire_date' => '2016-01-01']);
    $employee = User::factory()->create(['role' => Role::User, 'hire_date' => '2020-01-01', 'manager_id' => $manager->id, 'job_title' => 'Commercial']);
    EmploymentContract::create(['user_id' => $employee->id, 'type' => 'cdi', 'category' => 'employe', 'gross_salary' => '9000', 'start_date' => '2020-01-01']);

    test()->actingAs($rh)->post('/admin/reviews', ['year' => 2026, 'due_on' => '2027-01-31'])->assertSessionHasNoErrors();

    return [$rh, $manager, $employee, Review::where('user_id', $employee->id)->sole()];
}

function managerForm(array $overrides = []): array
{
    return [
        'overall_rating' => 'atteint', 'manager_feedback' => 'Bonne année.', 'interview_on' => '2026-12-09',
        'objectives' => [['title' => 'Chiffre d’affaires +10 %', 'target' => '1,2 MDH', 'weight' => 60], ['title' => 'Former un junior', 'weight' => 40]],
        ...$overrides,
    ];
}

test('opening a campaign creates a review per active employee, with their manager', function () {
    [, $manager, $employee, $review] = reviewSetup();

    expect(ReviewCampaign::sole()->year)->toBe(2026)
        ->and(Review::count())->toBe(3)
        ->and($review->manager_id)->toBe($manager->id)
        ->and($review->status)->toBe('self');
});

test('the full review: self-evaluation, manager, employee comment, HR closing', function () {
    [$rh, $manager, $employee, $review] = reviewSetup();

    // The manager cannot act before the self-evaluation, nor see it while it is being written.
    $this->actingAs($manager)->put("/evaluations/{$review->id}/manager", managerForm())->assertForbidden();

    $this->actingAs($employee)->put("/evaluations/{$review->id}/self", ['self_rating' => 'atteint', 'self_assessment' => 'Objectifs tenus.'])
        ->assertSessionHasNoErrors();
    expect($review->fresh()->status)->toBe('manager');

    // The employee does not see the manager's part before it is sent.
    $this->actingAs($employee)->get("/evaluations/{$review->id}")->assertInertia(fn (Assert $page) => $page->where('review.evaluation', null));

    $this->actingAs($manager)->put("/evaluations/{$review->id}/manager", managerForm())->assertSessionHasNoErrors();
    expect($review->fresh()->status)->toBe('comment')
        ->and(Objective::where('user_id', $employee->id)->where('year', 2027)->count())->toBe(2);

    $this->actingAs($employee)->put("/evaluations/{$review->id}/comment", ['employee_comment' => 'D’accord.'])->assertSessionHasNoErrors();
    $this->actingAs($employee)->put("/evaluations/{$review->id}/close")->assertForbidden();
    $this->actingAs($rh)->put("/evaluations/{$review->id}/close")->assertSessionHas('success');

    expect($review->fresh()->status)->toBe('closed');
});

test('next year’s objectives must weigh 100 % and last year’s must all be rated', function () {
    [, $manager, $employee, $review] = reviewSetup();
    $old = Objective::create(['user_id' => $employee->id, 'year' => 2026, 'title' => 'Ancien objectif', 'weight' => 100]);
    $review->update(['status' => 'manager']);

    $this->actingAs($manager)->put("/evaluations/{$review->id}/manager", managerForm())
        ->assertSessionHasErrors("ratings.{$old->id}.rating");
    $this->actingAs($manager)->put("/evaluations/{$review->id}/manager", managerForm(['ratings' => [$old->id => ['rating' => 'atteint']], 'objectives' => [['title' => 'A', 'weight' => 50]]]))
        ->assertSessionHasErrors('objectives');

    $this->actingAs($manager)->put("/evaluations/{$review->id}/manager", managerForm(['ratings' => [$old->id => ['rating' => 'depasse']]]))
        ->assertSessionHasNoErrors();
    expect($old->fresh()->rating->value)->toBe('depasse');
});

test('a recommendation is hidden from the employee; HR applies it as a career change', function () {
    [$rh, $manager, $employee, $review] = reviewSetup();
    $review->update(['status' => 'manager']);

    $this->actingAs($manager)->put("/evaluations/{$review->id}/manager", managerForm([
        'recommendation' => 'promotion', 'recommended_job_title' => 'Chef des ventes', 'recommended_salary' => 11000, 'recommendation_reason' => 'Résultats.',
    ]))->assertSessionHasNoErrors();

    $this->actingAs($employee)->get("/evaluations/{$review->id}")->assertInertia(fn (Assert $page) => $page->where('review.recommendation', null));

    // "Appliquer": the employee page opens with the career change pre-filled.
    $this->actingAs($rh)->get("/admin/users/{$employee->id}?recommendation={$review->id}")
        ->assertInertia(fn (Assert $page) => $page->where('recommendation.job_title', 'Chef des ventes')->where('recommendation.review_id', $review->id));

    $this->actingAs($rh)->post("/admin/users/{$employee->id}/career-changes", [
        'kind' => 'promotion', 'effective_date' => '2026-12-10', 'job_title' => 'Chef des ventes', 'gross_salary' => 11000, 'review_id' => $review->id,
    ])->assertSessionHasNoErrors();

    expect($review->fresh()->recommendation_status)->toBe('applied')
        ->and($review->fresh()->career_change_id)->toBe(CareerChange::sole()->id)
        ->and($employee->fresh()->job_title)->toBe('Chef des ventes');
});

test('only the employee, their manager and HR see a review; employees cannot open campaigns', function () {
    [, , $employee, $review] = reviewSetup();
    $colleague = User::factory()->create(['role' => Role::User]);

    $this->actingAs($colleague)->get("/evaluations/{$review->id}")->assertForbidden();
    $this->actingAs($colleague)->post('/admin/reviews', ['year' => 2027])->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => Role::Payroll]))->get('/admin/reviews')->assertOk();
    $this->actingAs($employee)->get('/evaluations')->assertInertia(fn (Assert $page) => $page->has('mine', 1));
});
