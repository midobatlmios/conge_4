<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A public holiday: not counted as a leave day.
 *
 * @property string $name
 * @property Carbon $date
 * @property bool $recurring
 */
class Holiday extends Model
{
    protected $fillable = ['name', 'date', 'recurring'];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'recurring' => 'boolean',
    ];

    /** @var list<string>|null */
    private static ?array $keys = null;

    /**
     * "MM-DD" for a holiday that repeats every year, "YYYY-MM-DD" for a single date.
     * This is the format LeaveCalculator and the frontend (lib/leave.ts) compare against.
     */
    public function key(): string
    {
        return $this->date->format($this->recurring ? 'm-d' : 'Y-m-d');
    }

    /**
     * Keys of every holiday. Cached for the request because working days are
     * counted for every request shown; cleared whenever a holiday changes.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return self::$keys ??= static::all()->map->key()->values()->all();
    }

    public static function flushKeys(): void
    {
        self::$keys = null;
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushKeys());
        static::deleted(fn () => static::flushKeys());
    }
}
