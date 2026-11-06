<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * Kwitansi digital satu baris settlement, ditandatangani penerima uang.
 * Tidak pernah dihapus; versi lama menjadi SUPERSEDED dan tetap bisa dibuka.
 */
class SettlementReceipt extends Model
{
    public const VALID = 'VALID';
    public const SUPERSEDED = 'SUPERSEDED';

    protected $fillable = [
        'receipt_number', 'budget_request_id', 'trouble_ticket_id', 'realization_id', 'request_detail_id',
        'recipient_name', 'amount', 'purpose', 'transaction_date', 'signature_path', 'pdf_path',
        'signed_at', 'captured_by', 'status',
    ];

    protected $casts = [
        'amount'           => 'integer',
        'transaction_date' => 'date',
        'signed_at'        => 'datetime',
    ];

    /** Tabelnya belum tentu ada (migrasi menyusul di host). */
    public static function available(): bool
    {
        static $ada = null;

        return $ada ??= Schema::hasTable('settlement_receipts');
    }

    public function budgetRequest(): BelongsTo
    {
        return $this->belongsTo(BudgetRequest::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(TroubleTicket::class, 'trouble_ticket_id');
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }
}
