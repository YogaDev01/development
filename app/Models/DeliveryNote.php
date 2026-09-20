<?php

namespace App\Models;

use App\Support\DocumentSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class DeliveryNote extends Model
{
    protected $fillable = [
        'code',
        'customer_id',
        'status',
        'notes',
        'created_by',
        'sent_at',
        'received_by',
        'received_at',
    ];

    protected $casts = [
        'sent_at'     => 'datetime',
        'received_at' => 'datetime',
    ];

    public const STATUSES = ['SENT', 'RECEIVED'];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(DeliveryNoteItem::class)->orderBy('id');
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class, 'delivery_note_items')->withTimestamps();
    }

    public function logs()
    {
        return $this->hasMany(DeliveryNoteLog::class)->latest();
    }

    public function attachments()
    {
        return $this->hasMany(DeliveryNoteAttachment::class)->orderBy('id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /*
    |--------------------------------------------------------------------------
    | KODE
    |--------------------------------------------------------------------------
    | Format: SJ-0001/MM/YY — reset tiap tahun kalender.
    */
    public static function generateCode(?\DateTimeInterface $date = null): string
    {
        $date = $date ?: now();
        $year = $date->format('Y');
        $mm   = $date->format('m');
        $yy   = $date->format('y');

        // Penghitung terkunci - lihat App\Support\DocumentSequence.
        $no = DocumentSequence::next(
            "delivery-note:{$year}",
            fn () => static::whereYear('created_at', $year)->count(),
        );

        $sequence = str_pad((string) $no, 4, '0', STR_PAD_LEFT);

        return "SJ-{$sequence}/{$mm}/{$yy}";
    }

    /*
    |--------------------------------------------------------------------------
    | LOG
    |--------------------------------------------------------------------------
    */
    public function addLog(string $action, ?string $notes = null): DeliveryNoteLog
    {
        $user = auth::user();

        return $this->logs()->create([
            'action'     => $action,
            'actor_id'   => $user?->id,
            'actor_name' => $user?->name,
            'notes'      => $notes,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS TAMPILAN
    |--------------------------------------------------------------------------
    */

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'SENT'     => 'warning',
            'RECEIVED' => 'success',
            default    => 'secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'SENT'     => 'Sent',
            'RECEIVED' => 'Received',
            default    => $this->status,
        };
    }
}
