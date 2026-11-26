<?php

namespace App\Models;

use App\Enums\ReviewRating;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An objective for one year: set by the manager in a review, rated in the
 * next year's review.
 *
 * @property int $user_id
 * @property int $year
 * @property string $title
 * @property string|null $target
 * @property int $weight percent
 * @property ReviewRating|null $rating
 */
class Objective extends Model
{
    protected $fillable = ['user_id', 'year', 'set_in_review_id', 'title', 'target', 'weight', 'rating', 'rating_comment'];

    /** @var array<string, string> */
    protected $casts = ['rating' => ReviewRating::class, 'weight' => 'integer', 'year' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, mixed> */
    public function present(): array
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'title' => $this->title,
            'target' => $this->target,
            'weight' => $this->weight,
            'rating' => $this->rating?->value,
            'rating_label' => $this->rating?->label(),
            'rating_comment' => $this->rating_comment,
        ];
    }
}
