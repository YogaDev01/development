<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lampiran satu Assignment. Berkasnya di disk "secure", dilayani lewat
 * SecureFileController; keterlihatan diatur App\Services\FileAccess.
 */
class AssignmentAttachment extends Model
{
    protected $table = 'assignment_attachments';

    protected $fillable = [
        'assignment_id',
        'original_name',
        'path',
        'mime',
        'size',
        'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /** URL unduhan terproteksi (lewat SecureFileController, route /storage/{path}). */
    public function getUrlAttribute(): string
    {
        return url('storage/' . $this->path);
    }
}
