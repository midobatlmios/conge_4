<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

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
            return $debut->diffInDays($fin) + 1; // Add 1 to include both start and end dates
        }
        return 0;
    }

    public static function getTotalDaysForUser($userId, $year = null)
    {
        // Log the parameters
        Log::info('Calculating total days for user:', [
            'user_id' => $userId,
            'year' => $year
        ]);

        $query = self::where('user_id', $userId)
            ->where('etat', 'acceptée');

        if ($year) {
            $query->where('annee', $year);
        }

        $demandes = $query->get();
        
        // Log the found demandes
        Log::info('Found demandes:', [
            'count' => $demandes->count(),
            'demandes' => $demandes->toArray()
        ]);

        $totalDays = $demandes->sum(function ($demande) {
            $days = $demande->nbr_jours;
            Log::info('Adding days from demande:', [
                'demande_id' => $demande->id,
                'nbr_jours' => $days,
                'date_debut' => $demande->date_debut,
                'date_fin' => $demande->date_fin
            ]);
            return $days;
        });

        // Log the total days
        Log::info('Total days calculated:', ['total_days' => $totalDays]);

        return $totalDays;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($demande) {
            $demande->annee = now()->year;
            // Calculate and store nbr_jours when creating
            if ($demande->date_debut && $demande->date_fin) {
                $demande->nbr_jours = $demande->getNbrJoursAttribute();
            }
        });

        static::updating(function ($demande) {
            // Recalculate nbr_jours when dates are updated
            if ($demande->isDirty(['date_debut', 'date_fin'])) {
                $demande->nbr_jours = $demande->getNbrJoursAttribute();
            }
        });
    }
}