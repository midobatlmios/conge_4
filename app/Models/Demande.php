<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Demande extends Model
{
    use HasFactory;

    public const TYPES = ['annuel', 'mariage', 'naissance', 'deces', 'sans_solde', 'recuperation'];

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

    /** Annual leave allowance per employee per calendar year. */
    public static function maxDaysPerYear(): int
    {
        return (int) config('conge.max_days_per_year');
    }

    /** Leave types that use up some or all of the yearly allowance. */
    public static function allowanceTypes(): array
    {
        return [...config('conge.counted_types'), ...array_keys(config('conge.fixed_deductions'))];
    }

    /** Whether a leave type uses up any of the yearly allowance. */
    public static function countsTowardsAllowance(string $type): bool
    {
        return in_array($type, self::allowanceTypes(), true);
    }

    /**
     * Days a leave takes from the allowance, limited to one calendar year when given.
     * Fully counted types use their working days in that year; fixed deductions
     * (mariage, décès) count once, in the year the leave starts.
     */
    public static function allowanceDays(string $type, string $start, string $end, ?int $year = null): int
    {
        $start = Carbon::parse($start);
        $end = Carbon::parse($end);

        if (in_array($type, config('conge.counted_types'), true)) {
            if ($year) {
                $start = $start->max(Carbon::create($year, 1, 1));
                $end = $end->min(Carbon::create($year, 12, 31));
            }
            return $start->lte($end) ? self::countWorkingDays($start, $end) : 0;
        }

        $deduction = config('conge.fixed_deductions')[$type] ?? null;

        if ($deduction === null || ($year && $start->year !== $year)) {
            return 0;
        }

        return min($deduction, self::countWorkingDays($start, $end));
    }

    /**
     * Number of working days between two dates (both included), skipping
     * non-working weekdays and public holidays from config/conge.php.
     */
    public static function countWorkingDays(CarbonInterface|string $start, CarbonInterface|string $end): int
    {
        $day = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();
        $workingDays = config('conge.working_days');
        $holidays = config('conge.holidays');
        $count = 0;

        for (; $day->lte($end); $day->addDay()) {
            if (in_array($day->isoWeekday(), $workingDays, true)
                && !in_array($day->format('m-d'), $holidays, true)
                && !in_array($day->format('Y-m-d'), $holidays, true)) {
                $count++;
            }
        }

        return $count;
    }

    public function getNbrJoursAttribute(): int
    {
        if ($this->date_debut && $this->date_fin) {
            return self::countWorkingDays($this->date_debut, $this->date_fin);
        }
        return 0;
    }

    /** Requests of a user that are not refused and share at least one day with the period. */
    public static function overlapping(int $userId, string $start, string $end, ?int $excludeDemandeId = null): Builder
    {
        return self::where('user_id', $userId)
            ->where('etat', '!=', 'refusée')
            ->whereDate('date_debut', '<=', $end)
            ->whereDate('date_fin', '>=', $start)
            ->when($excludeDemandeId !== null, fn (Builder $q) => $q->whereKeyNot($excludeDemandeId));
    }

    /**
     * Days of accepted leave that use up the allowance, for a user,
     * optionally limited to one calendar year.
     */
    public static function getTotalDaysForUser($userId, $year = null, $excludeDemandeId = null): int
    {
        $query = self::where('user_id', $userId)
            ->where('etat', 'acceptée')
            ->whereIn('type_conge', self::allowanceTypes());

        if ($excludeDemandeId !== null) {
            $query->whereKeyNot($excludeDemandeId);
        }

        if ($year) {
            $query->whereDate('date_debut', '<=', Carbon::create((int) $year, 12, 31))
                ->whereDate('date_fin', '>=', Carbon::create((int) $year, 1, 1));
        }

        return (int) $query->get()->sum(fn (self $demande) => self::allowanceDays(
            $demande->type_conge,
            $demande->date_debut,
            $demande->date_fin,
            $year ? (int) $year : null
        ));
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
