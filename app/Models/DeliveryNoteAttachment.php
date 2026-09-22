<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryNoteAttachment extends Model
{
    protected $fillable = [
        'delivery_note_id',
        'delivery_note_log_id',
        'type',
        'title',
        'file_path',
        'uploaded_by',
    ];

    public function deliveryNote()
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function log()
    {
        return $this->belongsTo(DeliveryNoteLog::class, 'delivery_note_log_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
