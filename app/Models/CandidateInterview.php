<?php

namespace App\Models;

use App\Enums\ReviewRating;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An interview with a candidate, and the interviewer's evaluation (same
 * 4-level scale as annual reviews) once it has taken place.
 *
 * @property \Illuminate\Support\Carbon $scheduled_at
 * @property int|null $interviewer_id
 * @property ReviewRating|null $rating
 */
class CandidateInterview extends Model
{
    protected $fillable = ['candidate_id', 'scheduled_at', 'interviewer_id', 'mode', 'rating', 'comment', 'created_by'];

    /** @var array<string, string> */
    protected $casts = ['scheduled_at' => 'datetime', 'rating' => ReviewRating::class];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }
}
