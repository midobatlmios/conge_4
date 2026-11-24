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
 */
class Demande extends Model
{
    use HasFactory;

    protected $fillable = [
        'date_demande',
        'date_debut',
        'date_fin',
        'type_conge',
        'etat',
        'user_id',
        'valide_par',
        'comment',
    ];

    protected $casts = [
        'type_conge' => LeaveType::class,
        'etat' => LeaveStatus::class,
    ];

    // Always computed from the dates, so it is included in JSON responses.
    protected $appends = ['nbr_jours'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Audit trail, oldest first. */
    public function events(): HasMany
    {
        return $this->hasMany(DemandeEvent::class)->oldest('created_at')->oldest('id');
    }

    public function getNbrJoursAttribute(): int
    {
        if ($this->date_debut && $this->date_fin) {
            return app(LeaveCalculator::class)->workingDays($this->date_debut, $this->date_fin);
        }

        return 0;
    }

    /** Requests of a user that are not refused and share at least one day with the period. */
    public function scopeOverlapping(Builder $query, int $userId, string $start, string $end, ?int $excludeDemandeId = null): Builder
    {
        return $query->where('user_id', $userId)
            ->where('etat', '!=', LeaveStatus::Refused)
            ->whereDate('date_debut', '<=', $end)
            ->whereDate('date_fin', '>=', $start)
            ->when($excludeDemandeId !== null, fn (Builder $q) => $q->whereKeyNot($excludeDemandeId));
    }

    protected static function booted(): void
    {
        // Keep the stored year and day count in sync with the dates.
        static::saving(function (Demande $demande) {
            if ($demande->date_debut && $demande->date_fin
                && ($demande->isDirty(['date_debut', 'date_fin']) || ! $demande->exists)) {
                $demande->annee = Carbon::parse($demande->date_debut)->year;
                $demande->nbr_jours = $demande->getNbrJoursAttribute();
            }
        });

        // Audit trail: every change, whichever screen or code path made it.
        static::created(fn (Demande $demande) => DemandeEvent::recordCreated($demande));
        static::updated(fn (Demande $demande) => DemandeEvent::recordUpdated($demande));
        static::deleted(fn (Demande $demande) => DemandeEvent::recordDeleted($demande));
    }
}
