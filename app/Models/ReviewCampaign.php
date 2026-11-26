<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One year of annual reviews, opened by HR ('open'), then 'closed'.
 *
 * @property int $year
 * @property \Illuminate\Support\Carbon|null $due_on
 * @property string $status
 */
class ReviewCampaign extends Model
{
    protected $fillable = ['year', 'due_on', 'status', 'created_by'];

    /** @var array<string, string> */
    protected $casts = ['due_on' => 'date:Y-m-d'];

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
