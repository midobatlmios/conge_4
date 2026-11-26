<?php

namespace App\Models;

use App\Enums\ReviewRating;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One employee's annual review. Steps ('status'):
 *  self     the employee fills the self-evaluation
 *  manager  the manager (or the delegate, or HR when there is no manager) rates
 *           last year's objectives, writes feedback, sets next year's
 *           objectives and may recommend a promotion or raise
 *  comment  the employee reads it and may comment
 *  hr       HR closes it
 *  closed
 * The recommendation is never shown to the employee.
 *
 * @property int $user_id
 * @property int|null $manager_id
 * @property string $status
 * @property ReviewRating|null $self_rating
 * @property ReviewRating|null $overall_rating
 * @property string|null $recommendation 'promotion' or 'raise'
 * @property string|null $recommendation_status 'pending', 'applied' or 'declined'
 */
class Review extends Model
{
    public const STATUS_LABELS = [
        'self' => 'Auto-évaluation',
        'manager' => 'Évaluation du responsable',
        'comment' => 'Commentaire de l’employé',
        'hr' => 'Clôture RH',
        'closed' => 'Clôturé',
    ];

    public const RECOMMENDATIONS = ['promotion' => 'Promotion', 'raise' => 'Augmentation de salaire'];

    protected $fillable = [
        'review_campaign_id', 'user_id', 'manager_id', 'status',
        'self_rating', 'self_assessment', 'self_wishes',
        'overall_rating', 'manager_feedback', 'strengths', 'improvements', 'training_needs', 'interview_on', 'reviewed_by',
        'employee_comment',
        'recommendation', 'recommended_job_title', 'recommended_salary', 'recommendation_reason', 'recommendation_status', 'career_change_id',
        'closed_by', 'closed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'self_rating' => ReviewRating::class,
        'overall_rating' => ReviewRating::class,
        'interview_on' => 'date:Y-m-d',
        'closed_at' => 'datetime',
        'recommended_salary' => 'encrypted',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ReviewCampaign::class, 'review_campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** The manager's side: their manager, today's delegate, or HR (never the employee). */
    public function isReviewer(User $user): bool
    {
        if ($user->id === $this->user_id) {
            return false;
        }

        $managerId = $this->manager_id ?? $this->user?->manager_id;

        return $user->isHr()
            || ($managerId !== null && ($managerId === $user->id || in_array($managerId, $user->delegatingManagerIds(), true)));
    }

    public function canView(User $user): bool
    {
        return $user->id === $this->user_id || $user->canViewHr() || $this->isReviewer($user);
    }

    /** Whose turn it is, for this viewer. */
    public function canAct(User $user): bool
    {
        return match ($this->status) {
            'self', 'comment' => $user->id === $this->user_id,
            'manager' => $this->isReviewer($user),
            'hr' => $user->isHr() && $user->id !== $this->user_id,
            default => false,
        };
    }

    /** @return array<string, mixed> What this viewer may see of the review. */
    public function present(User $viewer): array
    {
        $isEmployee = $viewer->id === $this->user_id;
        // The employee reads the manager's part once it is submitted; the manager reads the self-evaluation once submitted.
        $showManager = ! $isEmployee || in_array($this->status, ['comment', 'hr', 'closed'], true);
        $showSelf = $isEmployee || $this->status !== 'self';

        return [
            'id' => $this->id,
            'year' => $this->campaign->year,
            'due_on' => $this->campaign->due_on?->toDateString(),
            'status' => $this->status,
            'status_label' => self::STATUS_LABELS[$this->status],
            'employee' => ['id' => $this->user_id, 'name' => $this->user?->name, 'job_title' => $this->user?->job_title],
            'manager' => $this->manager?->name,
            'can_act' => $this->canAct($viewer),
            'is_employee' => $isEmployee,
            'is_reviewer' => $this->isReviewer($viewer),
            'self' => $showSelf ? [
                'rating' => $this->self_rating?->value,
                'assessment' => $this->self_assessment,
                'wishes' => $this->self_wishes,
            ] : null,
            'evaluation' => $showManager ? [
                'rating' => $this->overall_rating?->value,
                'rating_label' => $this->overall_rating?->label(),
                'feedback' => $this->manager_feedback,
                'strengths' => $this->strengths,
                'improvements' => $this->improvements,
                'training_needs' => $this->training_needs,
                'interview_on' => $this->interview_on?->toDateString(),
                'reviewed_by' => $this->reviewer?->name,
            ] : null,
            'employee_comment' => $this->employee_comment,
            // Never shown to the employee.
            'recommendation' => $isEmployee ? null : [
                'kind' => $this->recommendation,
                'kind_label' => self::RECOMMENDATIONS[$this->recommendation] ?? null,
                'job_title' => $this->recommended_job_title,
                'salary' => $this->recommended_salary === null ? null : (float) $this->recommended_salary,
                'reason' => $this->recommendation_reason,
                'status' => $this->recommendation_status,
            ],
            'closed_at' => $this->closed_at?->toIso8601String(),
        ];
    }
}
