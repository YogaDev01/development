<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingPeriod extends Model
{
    protected $fillable = [
        'name', 'start_date', 'end_date', 'status', 'closed_by', 'closed_at', 'notes', 'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'closed_at'  => 'datetime',
    ];

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function journals()
    {
        return $this->hasMany(Journal::class);
    }

    /** Periode yang memuat tanggal itu (null bila belum dibuat). */
    public static function forDate($date): ?self
    {
        $d = \Carbon\Carbon::parse($date)->toDateString();

        return static::whereDate('start_date', '<=', $d)->whereDate('end_date', '>=', $d)->first();
    }

    /**
     * Pesan galat bila tanggal itu TIDAK boleh menerima posting; null = boleh.
     * Backdate diizinkan selama periodenya masih OPEN.
     */
    public static function postingBlocker($date): ?string
    {
        $p = static::forDate($date);

        if (! $p) {
            return 'Belum ada periode akuntansi untuk tanggal ' . \Carbon\Carbon::parse($date)->format('d M Y') . '.';
        }
        if ($p->status !== 'OPEN') {
            return 'Periode ' . $p->name . ' berstatus ' . $p->status . '; posting tidak diizinkan.';
        }

        return null;
    }

    public function isOpen(): bool
    {
        return $this->status === 'OPEN';
    }
}
