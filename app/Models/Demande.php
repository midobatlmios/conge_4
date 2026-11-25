<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Services\LeaveCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A leave request.
 *
 * @property LeaveType $type_conge
 * @property LeaveStatus $etat
 * @property string $date_debut
 * @property string $date_fin
 * @property bool $start_half Starts in the afternoon.
 * @property bool $end_half Ends at noon.
 */
class Demande extends Model
{
    use HasFactory;

    protected $fillable = [
        'date_demande',
        'date_debut',
        'date_fin',
        'start_half',
        'end_half',
        'type_conge',
        'etat',
        'user_id',
        'valide_par',
        'comment',
        'manager_comment', // the manager's reason when refusing
        'cnss_filed_on', // sick leave: day HR filed the CNSS dossier
    ];

    protected $casts = [
        'type_conge' => LeaveType::class,
        'etat' => LeaveStatus::class,
        'start_half' => 'boolean', // starts in the afternoon
        'end_half' => 'boolean', // ends at noon
        'cnss_filed_on' => 'date:Y-m-d',
    ];

    // Always computed from the dates, so it is included in JSON responses.
    protected $appends = ['nbr_jours', 'calendar_days'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Supporting documents (certificates), oldest first. */
    public function documents(): HasMany
    {
        return $this->hasMany(DemandeDocument::class)->oldest('id');
    }

    /** Whether HR needs a supporting document before accepting this kind of leave. */
    public function needsDocument(): bool
    {
        return in_array($this->type_conge->value, config('conge.document_types'), true);
    }

    /** Audit trail, oldest first. */
    public function events(): HasMany
    {
        return $this->hasMany(DemandeEvent::class)->oldest('created_at')->oldest('id');
    }

    /** Working days of leave, in half days when it starts in the afternoon or ends at noon. */
    public function getNbrJoursAttribute(): float
    {
        if ($this->date_debut && $this->date_fin) {
            return app(LeaveCalculator::class)->leaveDays(
                $this->date_debut, $this->date_fin, (bool) $this->start_half, (bool) $this->end_half
            );
        }

        return 0.0;
    }

    /** Calendar days, weekends and holidays included (how the CNSS counts sick leave). */
    public function getCalendarDaysAttribute(): int
    {
        if (! $this->date_debut || ! $this->date_fin) {
            return 0;
        }

        return (int) Carbon::parse($this->date_debut)->diffInDays(Carbon::parse($this->date_fin)) + 1;
    }

    /** A sick leave long enough for CNSS daily allowances: HR files a dossier. */
    public function needsCnssFiling(): bool
    {
        return $this->type_conge === LeaveType::Sick && $this->calendar_days >= config('conge.cnss_min_days');
    }

    /** Last day to file the CNSS dossier. */
    public function cnssDeadline(): Carbon
    {
        return Carbon::parse($this->date_debut)->addDays(config('conge.cnss_filing_days'));
    }

    /** Requests of a user that are not refused or cancelled and share at least one day with the period. */
    public function scopeOverlapping(Builder $query, int $userId, string $start, string $end, ?int $excludeDemandeId = null): Builder
    {
        return $query->where('user_id', $userId)
            ->whereNotIn('etat', LeaveStatus::inactive())
            ->whereDate('date_debut', '<=', $end)
            ->whereDate('date_fin', '>=', $start)
            ->when($excludeDemandeId !== null, fn (Builder $q) => $q->whereKeyNot($excludeDemandeId));
    }

    /**
     * The first of a user's requests that clashes with a period. Sharing a day is
     * fine when one ends at noon and the other starts in the afternoon.
     */
    public static function firstClash(
        int $userId,
        string $start,
        string $end,
        bool $startHalf,
        bool $endHalf,
        ?int $excludeDemandeId = null,
    ): ?self {
        $start = Carbon::parse($start)->toDateString();
        $end = Carbon::parse($end)->toDateString();

        return static::overlapping($userId, $start, $end, $excludeDemandeId)
            ->orderBy('date_debut')
            ->get()
            ->first(function (Demande $other) use ($start, $end, $startHalf, $endHalf) {
                $otherStart = Carbon::parse($other->date_debut)->toDateString();
                $otherEnd = Carbon::parse($other->date_fin)->toDateString();

                // The other leave's last morning, then this one's first afternoon (or the reverse).
                $meetsAfter = $otherEnd === $start && $other->end_half && $startHalf;
                $meetsBefore = $otherStart === $end && $other->start_half && $endHalf;

                return ! $meetsAfter && ! $meetsBefore;
            });
    }

    protected static function booted(): void
    {
        // Keep the stored year and day count in sync with the dates.
        static::saving(function (Demande $demande) {
            if ($demande->date_debut && $demande->date_fin
                && ($demande->isDirty(['date_debut', 'date_fin', 'start_half', 'end_half']) || ! $demande->exists)) {
                $demande->annee = Carbon::parse($demande->date_debut)->year;
                $demande->nbr_jours = $demande->getNbrJoursAttribute();
            }
        });

        // Audit trail: every change, whichever screen or code path made it.
        static::created(fn (Demande $demande) => DemandeEvent::recordCreated($demande));
        static::updated(fn (Demande $demande) => DemandeEvent::recordUpdated($demande));
        static::deleted(fn (Demande $demande) => DemandeEvent::recordDeleted($demande));

        // Delete documents one by one so their files go too (the database cascade would leave them).
        static::deleting(fn (Demande $demande) => $demande->documents->each->delete());
    }
}
