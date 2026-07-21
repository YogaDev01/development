<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestAttachment extends Model
{
    protected $table = 'request_attachments';

    // Tipe lampiran
    public const TYPES = [
        'request'     => 'Lampiran Pengajuan',
        'receipt'     => 'Bukti Transfer',
        'realization' => 'Lampiran Realisasi',
    ];

    protected $fillable = [
        'budget_request_id',
        'type',
        'original_name',
        'stored_name',
        'path',
        'mime_type',
        'size',
        'uploaded_by',
        'notes',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function budgetRequest()
    {
        return $this->belongsTo(BudgetRequest::class, 'budget_request_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getSizeFormattedAttribute(): string
    {
        $size = $this->size ?? 0;
        if ($size < 1024) return $size . ' B';
        if ($size < 1048576) return round($size / 1024, 1) . ' KB';
        return round($size / 1048576, 1) . ' MB';
    }
}