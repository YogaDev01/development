<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryNoteLog extends Model
{
    protected $fillable = [
        'delivery_note_id',
        'action',
        'actor_id',
        'actor_name',
        'notes',
    ];

    public function deliveryNote()
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function attachments()
    {
        return $this->hasMany(DeliveryNoteAttachment::class)->orderBy('id');
    }

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'CREATED'  => 'Dibuat',
            'SENT'     => 'Dikirim',
            'RECEIVED' => 'Diterima',
            'SUPERADMIN_EDITED' => 'Diedit (Superadmin)',
            default    => $this->action,
        };
    }

    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            'CREATED'  => 'secondary',
            'SENT'     => 'warning',
            'RECEIVED' => 'success',
            'SUPERADMIN_EDITED' => 'warning',
            default    => 'secondary',
        };
    }
}
