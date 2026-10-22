<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu baris biaya pada sebuah revisi pengajuan Trouble Ticket.
 *
 * Tidak pernah diubah atau dihapus: revisi menulis baris baru dengan
 * `revision` lebih besar. account_name / sub_item_name adalah salinan teks
 * saat disimpan, supaya baris lama tetap terbaca bila master-nya berubah.
 * Total = amount × qty dihitung server, bukan dipercaya dari browser.
 */
class TroubleTicketRequestItem extends Model
{
    protected $fillable = [
        'trouble_ticket_request_id', 'revision', 'budget_detail_id', 'account_id', 'account_name',
        'sub_item_name', 'description', 'amount', 'qty', 'total', 'created_by',
    ];

    protected $casts = [
        'revision' => 'integer',
        'amount'   => 'integer',
        'qty'      => 'integer',
        'total'    => 'integer',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(TroubleTicketRequest::class, 'trouble_ticket_request_id');
    }

    public function budgetDetail(): BelongsTo
    {
        return $this->belongsTo(BudgetPlanDetail::class, 'budget_detail_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TroubleTicketRequestAttachment::class, 'item_id')->orderBy('id');
    }
}
