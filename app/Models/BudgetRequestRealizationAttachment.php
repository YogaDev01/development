<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetRequestRealizationAttachment extends Model
{
    protected $table = 'budget_request_realization_attachments';

    protected $fillable = [
        'budget_request_id',
        'original_name',
        'stored_name',
        'path',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    public function budgetRequest()
    {
        return $this->belongsTo(BudgetRequest::class, 'budget_request_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getSizeFormattedAttribute(): string
    {
        $size = $this->size ?? 0;
        if ($size < 1024)    return $size . ' B';
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