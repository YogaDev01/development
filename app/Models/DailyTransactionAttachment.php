<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyTransactionAttachment extends Model
{
    protected $table = 'daily_transaction_attachments';

    protected $fillable = [
        'daily_transaction_id',
        'original_name',
        'stored_name',
        'path',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    public function dailyTransaction()
    {
        return $this->belongsTo(DailyTransaction::class);
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
}