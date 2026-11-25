<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A document in an employee's folder. Stored on the private "local" disk; only
 * EmployeeDocumentController serves it: HR (Admin, RH, Paie) and the employee
 * themself.
 *
 * @property int $user_id
 * @property DocumentCategory $category
 * @property \Illuminate\Support\Carbon|null $expires_on
 * @property string $original_name
 * @property string $path
 * @property string $mime_type
 * @property int $size
 */
class EmployeeDocument extends Model
{
    public const DISK = 'local';

    protected $fillable = ['user_id', 'uploaded_by', 'category', 'title', 'expires_on', 'original_name', 'path', 'mime_type', 'size'];

    // The storage path is internal.
    protected $hidden = ['path'];

    /** @var array<string, string> */
    protected $casts = [
        'category' => DocumentCategory::class,
        'expires_on' => 'date:Y-m-d',
    ];

    protected static function booted(): void
    {
        // The file goes with its record.
        static::deleted(fn (EmployeeDocument $document) => Storage::disk(self::DISK)->delete($document->path));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Expired, or expiring within the alert window (config conge.document_expiry_alert_days).
     *
     * @param  Builder<EmployeeDocument>  $query
     */
    public function scopeNeedsRenewal(Builder $query): void
    {
        $query->whereNotNull('expires_on')
            ->whereDate('expires_on', '<=', today()->addDays(config('conge.document_expiry_alert_days')));
    }
}
