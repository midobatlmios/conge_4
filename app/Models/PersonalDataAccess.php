<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One access to someone else's personal data (loi 09-08): who viewed or
 * changed what, and when. Written by the app, read by the Administrateur only,
 * never edited. People looking at their own data are not logged.
 *
 * @property int|null $actor_id
 * @property int|null $subject_id
 * @property string $action
 * @property string|null $detail
 */
class PersonalDataAccess extends Model
{
    public const UPDATED_AT = null;

    /** What each action means, for the log page. */
    public const ACTIONS = [
        'view_file' => 'Consultation de la fiche',
        'update_profile' => 'Modification des données personnelles',
        'update_contract' => 'Modification d\'un contrat',
        'career_change' => 'Évolution de carrière (promotion, salaire, mutation)',
        'open_document' => 'Ouverture d\'un document du dossier',
        'add_document' => 'Ajout d\'un document au dossier',
        'delete_document' => 'Suppression d\'un document du dossier',
        'open_certificate' => 'Ouverture d\'un justificatif de congé',
        'payslip_pdf' => 'Téléchargement d\'un bulletin de paie',
        'attestation_pdf' => 'Téléchargement d\'une attestation',
        'export' => 'Export des données',
        'retention' => 'Application de la durée de conservation',
    ];

    protected $fillable = ['actor_id', 'subject_id', 'action', 'detail', 'ip'];

    /** @var array<string, string> */
    protected $casts = ['created_at' => 'datetime'];

    /** Records an access by $actor to $subject's data (nothing when it is their own). */
    public static function record(?User $actor, ?User $subject, string $action, ?string $detail = null): void
    {
        if ($actor && $subject && $actor->is($subject)) {
            return;
        }

        static::create([
            'actor_id' => $actor?->id,
            'subject_id' => $subject?->id,
            'action' => $action,
            'detail' => $detail === null ? null : mb_substr($detail, 0, 255),
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_id');
    }
}
