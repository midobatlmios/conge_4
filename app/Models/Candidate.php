<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * An application to a job offer, from the careers page or added by HR.
 * Moves through the stages; on hiring it is linked to the employee account.
 * The CV is on the private disk; only HR and the offer's hiring manager open it.
 *
 * @property int $job_offer_id
 * @property string $name
 * @property string $email
 * @property string $stage
 * @property string|null $cv_path
 */
class Candidate extends Model
{
    public const DISK = 'local';

    public const STAGES = [
        'new' => 'Nouvelle',
        'screening' => 'Présélection',
        'interview' => 'Entretien',
        'offer' => 'Offre',
        'hired' => 'Embauché',
        'rejected' => 'Refusé',
    ];

    protected $fillable = ['job_offer_id', 'name', 'email', 'phone', 'message', 'cv_path', 'cv_original_name', 'cv_mime_type', 'source', 'stage', 'stage_changed_at', 'rejection_reason', 'hired_user_id', 'hired_at', 'consent_at', 'created_by'];

    /** @var array<string, string> */
    protected $casts = ['stage_changed_at' => 'datetime', 'hired_at' => 'datetime', 'consent_at' => 'datetime'];

    protected static function booted(): void
    {
        static::deleted(fn (Candidate $candidate) => $candidate->cv_path && Storage::disk(self::DISK)->delete($candidate->cv_path));
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(JobOffer::class, 'job_offer_id');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(CandidateInterview::class)->orderBy('scheduled_at');
    }

    public function hiredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hired_user_id');
    }

    /** Still in the running (not hired, not rejected). */
    public function isActive(): bool
    {
        return ! in_array($this->stage, ['hired', 'rejected'], true);
    }
}
