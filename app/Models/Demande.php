<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Demande extends Model
{
    use HasFactory;

    protected $fillable = [
        'date_demande',
        'date_debut',
        'date_fin',
        'nbr_jours',
        'annee',
        'type_conge',
        'etat',
        'user_id',
        'valide_par',
        'comment'
    ];
    
    protected $appends = ['nbr_jours']; // Make Laravel include it in JSON/API responses

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getNbrJoursAttribute()
    {
        if ($this->date_debut && $this->date_fin) {
            $debut = Carbon::parse($this->date_debut);
            $fin = Carbon::parse($this->date_fin);
            return (int) $debut->diffInDays($fin) + 1;
        }
        return 0;
    }

    public static function getTotalDaysForUser($userId, $year = null, $excludeDemandeId = null): int
    {
        $query = self::where('user_id', $userId)
            ->where('etat', 'acceptée');

        if ($excludeDemandeId !== null) {
            $query->whereKeyNot($excludeDemandeId);
        }

        if ($year) {
            $yearStart = Carbon::create((int) $year, 1, 1)->startOfDay();
            $yearEnd = Carbon::create((int) $year, 12, 31)->endOfDay();
            $query->whereDate('date_debut', '<=', $yearEnd)
                ->whereDate('date_fin', '>=', $yearStart);
        }

        return (int) $query->get()->sum(function (self $demande) use ($year) {
            $start = Carbon::parse($demande->date_debut);
            $end = Carbon::parse($demande->date_fin);

            if ($year) {
                $start = $start->max(Carbon::create((int) $year, 1, 1)->startOfDay());
                $end = $end->min(Carbon::create((int) $year, 12, 31)->endOfDay());
            }

            return max(0, (int) $start->diffInDays($end) + 1);
        });
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($demande) {
            if ($demande->date_debut && $demande->date_fin) {
                $demande->annee = Carbon::parse($demande->date_debut)->year;
                $demande->nbr_jours = $demande->getNbrJoursAttribute();
            }
        });

        static::updating(function ($demande) {
            if ($demande->isDirty(['date_debut', 'date_fin'])) {
                if ($demande->date_debut) {
                    $demande->annee = Carbon::parse($demande->date_debut)->year;
                }
                $demande->nbr_jours = $demande->getNbrJoursAttribute();
            }
        });
    }
}