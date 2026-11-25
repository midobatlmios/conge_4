<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry of a leave request's audit trail. Written only by Demande's model
 * events (see Demande::booted), never edited.
 *
 * @property int $demande_id
 * @property string $action
 * @property array<string, mixed>|null $changes
 */
class DemandeEvent extends Model
{
    public const UPDATED_AT = null;

    /** Fields whose changes are recorded. */
    public const TRACKED = ['user_id', 'type_conge', 'date_debut', 'date_fin', 'start_half', 'end_half', 'etat', 'comment', 'manager_comment', 'cnss_filed_on'];

    protected $fillable = ['demande_id', 'employee_id', 'user_id', 'action', 'changes'];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function recordCreated(Demande $demande): void
    {
        static::record($demande, 'created', static::snapshot($demande));
    }

    public static function recordUpdated(Demande $demande): void
    {
        $changes = [];

        foreach (array_intersect(array_keys($demande->getChanges()), self::TRACKED) as $field) {
            $changes[$field] = ['old' => $demande->getRawOriginal($field), 'new' => $demande->getAttributes()[$field]];
        }

        if ($changes === []) {
            return;
        }

        $action = 'updated';

        if (isset($changes['etat'])) {
            $previous = LeaveStatus::tryFrom((string) $changes['etat']['old']);
            $new = LeaveStatus::from($changes['etat']['new']);
            $actor = auth()->user();
            $byManager = $actor && ! $actor->isHr() && $demande->user?->manager_id === $actor->id;

            $action = match ($new) {
                LeaveStatus::Accepted => $previous === LeaveStatus::CancellationRequested ? 'cancellation_refused' : 'accepted',
                LeaveStatus::Refused => $byManager ? 'manager_refused' : 'refused',
                LeaveStatus::Pending => 'reopened',
                LeaveStatus::ManagerApproved => 'manager_approved',
                LeaveStatus::CancellationRequested => 'cancellation_requested',
                LeaveStatus::Cancelled => 'cancelled',
            };

            // HR decided while the employee's manager had not approved yet.
            if ($new->isDecided() && $previous === LeaveStatus::Pending && ! $byManager
                && ($manager = $demande->user?->approvingManager()) && $manager->id !== $actor?->id) {
                $changes['skipped_manager'] = true;
            }
        }

        static::record($demande, $action, $changes);
    }

    /** A supporting document was added or removed ($action: document_added / document_removed). */
    public static function recordDocument(Demande $demande, string $action, string $fileName): void
    {
        static::record($demande, $action, ['document' => $fileName]);
    }

    public static function recordDeleted(Demande $demande): void
    {
        static::record($demande, 'deleted', static::snapshot($demande));
    }

    private static function record(Demande $demande, string $action, ?array $changes): void
    {
        static::create([
            'demande_id' => $demande->id,
            'employee_id' => $demande->user_id,
            'user_id' => auth()->id(),
            'action' => $action,
            'changes' => $changes,
        ]);
    }

    /** @return array<string, mixed> */
    private static function snapshot(Demande $demande): array
    {
        $attributes = $demande->getAttributes();

        return array_intersect_key($attributes, array_flip(self::TRACKED));
    }
}
