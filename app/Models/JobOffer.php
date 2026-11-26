<?php

namespace App\Models;

use App\Enums\ContractType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A job offer. 'draft' (HR prepares) → 'published' (on the public careers
 * page, open to applications until closes_on) → 'closed'.
 *
 * @property string $title
 * @property string $slug
 * @property ContractType $contract_type
 * @property string $status
 * @property int|null $hiring_manager_id
 * @property \Illuminate\Support\Carbon|null $closes_on
 */
class JobOffer extends Model
{
    public const STATUS_LABELS = ['draft' => 'Brouillon', 'published' => 'Publiée', 'closed' => 'Clôturée'];

    protected $fillable = ['title', 'slug', 'department_id', 'location', 'contract_type', 'description', 'requirements', 'hiring_manager_id', 'status', 'published_at', 'closes_on', 'created_by'];

    /** @var array<string, string> */
    protected $casts = ['contract_type' => ContractType::class, 'published_at' => 'datetime', 'closes_on' => 'date:Y-m-d'];

    protected static function booted(): void
    {
        static::creating(function (JobOffer $offer) {
            $offer->slug ??= Str::slug($offer->title).'-'.Str::lower(Str::random(5));
        });
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function hiringManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hiring_manager_id');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    /** Published and still open to applications. */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', 'published')->where(fn ($q) => $q->whereNull('closes_on')->orWhereDate('closes_on', '>=', today()));
    }

    public function isOpen(): bool
    {
        return $this->status === 'published' && ($this->closes_on === null || $this->closes_on->gte(today()));
    }

    /** HR (Admin, RH) and the offer's hiring manager. */
    public function canBeSeenBy(User $user): bool
    {
        return $user->isHr() || $this->hiring_manager_id === $user->id;
    }
}
