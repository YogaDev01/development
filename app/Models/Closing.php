<?php

namespace App\Models;

use App\Support\DocumentSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * M-04 — Closing (header). Rekap transaksi finance dalam satu periode.
 */
class Closing extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'period',
        'start_date',
        'end_date',
        'status',
        'total_transaction',
        'total_value',
        'notes',
        'created_by',
        'closed_by', 'closed_at',
        'reopened_by', 'reopened_at', 'reopen_reason',
    ];

    protected $casts = [
        'start_date'        => 'date',
        'end_date'          => 'date',
        'total_transaction' => 'integer',
        'total_value'       => 'integer',
        'closed_at'         => 'datetime',
        'reopened_at'       => 'datetime',
    ];

    public const STATUSES = ['DRAFT', 'CLOSED', 'REOPENED'];

    public function details()
    {
        return $this->hasMany(ClosingDetail::class)->orderBy('source_module')->orderBy('transaction_date');
    }

    public function createdBy()  { return $this->belongsTo(User::class, 'created_by'); }
    public function closedBy()   { return $this->belongsTo(User::class, 'closed_by'); }
    public function reopenedBy() { return $this->belongsTo(User::class, 'reopened_by'); }

    /** CLS/2026/09 — satu closing per periode, jadi kodenya sudah unik sendiri. */
    public static function generateCode(string $period): string
    {
        return 'CLS/' . str_replace('-', '/', $period);
    }

    public function isClosed(): bool { return $this->status === 'CLOSED'; }
    public function isDraft(): bool  { return $this->status === 'DRAFT' || $this->status === 'REOPENED'; }

    /** Total header selalu turunan dari detail. */
    public function recalculateTotals(): void
    {
        $this->update([
            'total_transaction' => $this->details()->count(),
            'total_value'       => (int) $this->details()->sum('amount'),
        ]);
    }
}
