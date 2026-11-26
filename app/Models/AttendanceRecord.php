<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One employee's arrival and departure on one day (times as HH:MM). The
 * departure is empty until they clock out.
 *
 * @property int $user_id
 * @property \Illuminate\Support\Carbon $date
 * @property string $clock_in
 * @property string|null $clock_out
 * @property string $source 'app' or 'correction'
 */
class AttendanceRecord extends Model
{
    protected $fillable = ['user_id', 'date', 'clock_in', 'clock_out', 'source'];

    /** @var array<string, string> */
    protected $casts = ['date' => 'date:Y-m-d'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
