<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A public holiday: not counted as a leave day. National (site_id null) or
 * local to one site.
 *
 * @property string $name
 * @property Carbon $date
 * @property bool $recurring
 * @property int|null $site_id
 */
class Holiday extends Model
{
    protected $fillable = ['name', 'date', 'recurring', 'site_id'];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'recurring' => 'boolean',
    ];

    /** @var list<array{key: string, site_id: int|null}>|null */
    private static ?array $all = null;

    /** @var array<string, list<string>> Keys by site ('' = national only). */
    private static array $keys = [];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * "MM-DD" for a holiday that repeats every year, "YYYY-MM-DD" for a single date.
     * This is the format LeaveCalculator and the frontend (lib/leave.ts) compare against.
     */
    public function key(): string
    {
        return $this->date->format($this->recurring ? 'm-d' : 'Y-m-d');
    }

    /**
     * Keys of the holidays that apply to a site: the national ones plus the
     * site's own (no site: national only). Cached for the request because
     * working days are counted for every request shown; cleared on change.
     *
     * @return list<string>
     */
    public static function keys(?int $siteId = null): array
    {
        self::$all ??= static::all()->map(fn (Holiday $h) => ['key' => $h->key(), 'site_id' => $h->site_id])->all();

        return self::$keys[(string) $siteId] ??= array_values(array_map(
            fn (array $h) => $h['key'],
            array_filter(self::$all, fn (array $h) => $h['site_id'] === null || $h['site_id'] === $siteId),
        ));
    }

    public static function flushKeys(): void
    {
        self::$all = null;
        self::$keys = [];
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushKeys());
        static::deleted(fn () => static::flushKeys());
    }
}
