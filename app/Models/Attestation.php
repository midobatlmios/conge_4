<?php

namespace App\Models;

use App\Enums\AttestationStatus;
use App\Enums\AttestationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An attestation (de travail, de salaire). Requested by the employee or
 * created by HR; once issued it has a number and a frozen snapshot of what the
 * PDF prints, so downloading it again later gives the same document.
 *
 * @property int $user_id
 * @property AttestationType $type
 * @property AttestationStatus $status
 * @property string|null $number
 * @property \Illuminate\Support\Carbon|null $issued_at
 * @property array<string, mixed>|null $snapshot
 */
class Attestation extends Model
{
    protected $fillable = ['user_id', 'type', 'purpose', 'status', 'requested_by', 'number', 'issued_at', 'handled_by', 'refusal_reason', 'snapshot'];

    // The snapshot holds the CIN and the salary: only the PDF route reads it.
    protected $hidden = ['snapshot'];

    /** @var array<string, string> */
    protected $casts = [
        'type' => AttestationType::class,
        'status' => AttestationStatus::class,
        'issued_at' => 'datetime',
        'snapshot' => 'encrypted:array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * As the pages show it (no snapshot).
     *
     * @return array<string, mixed>
     */
    public function present(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->user?->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'purpose' => $this->purpose,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'number' => $this->number,
            'requested_at' => $this->created_at->toDateString(),
            'requested_by_employee' => $this->requested_by === $this->user_id,
            'issued_at' => $this->issued_at?->toDateString(),
            'handled_by' => $this->handler?->name,
            'refusal_reason' => $this->refusal_reason,
        ];
    }
}
