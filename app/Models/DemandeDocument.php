<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A supporting document attached to a leave request. Stored on the private
 * "local" disk; only DemandeDocumentController serves it, after a policy check.
 *
 * @property int $demande_id
 * @property string $original_name
 * @property string $path
 * @property string $mime_type
 * @property int $size
 */
class DemandeDocument extends Model
{
    public const DISK = 'local';

    protected $fillable = ['demande_id', 'uploaded_by', 'original_name', 'path', 'mime_type', 'size'];

    // The storage path is internal.
    protected $hidden = ['path'];

    public function demande(): BelongsTo
    {
        return $this->belongsTo(Demande::class);
    }

    protected static function booted(): void
    {
        // The file goes with its record, whether the document or the whole request is deleted.
        static::deleted(fn (DemandeDocument $document) => Storage::disk(self::DISK)->delete($document->path));
    }
}
