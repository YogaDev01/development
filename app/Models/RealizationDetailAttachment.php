<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RealizationDetailAttachment extends Model
{
    protected $table = 'realization_detail_attachments';

    protected $fillable = [
        'realization_id',
        'original_name',
        'stored_name',
        'path',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function realization()
    {
        return $this->belongsTo(BudgetRequestRealization::class, 'realization_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getSizeFormattedAttribute(): string
    {
        $size = $this->size ?? 0;
        if ($size < 1024) return $size . ' B';
        if ($size < 1048576) return round($size / 1024, 1) . ' KB';
        return round($size / 1048576, 1) . ' MB';
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return (string) $this->mime_type === 'application/pdf';
    }
}
