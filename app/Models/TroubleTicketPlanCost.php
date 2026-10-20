<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris estimasi biaya pada Create Plan sebuah Trouble Ticket.
 *
 * ESTIMASI, bukan realisasi: baris ini tidak menyentuh BudgetConsumption dan
 * tidak mengurangi sisa pagu Budget Plan. Uang = integer rupiah.
 *
 * `account_name` adalah salinan nama COA saat baris disimpan, supaya baris
 * lama tetap terbaca bila master COA-nya kemudian dihapus (account_id jadi
 * NULL lewat nullOnDelete).
 */
class TroubleTicketPlanCost extends Model
{
    protected $fillable = [
        'trouble_ticket_id',
        'budget_detail_id',
        'account_id',
        'account_name',
        'description',
        'amount',
        'qty',
        'total',
    ];

    protected $casts = [
        'amount' => 'integer',
        'qty'    => 'decimal:2',
        'total'  => 'integer',
    ];

    public function troubleTicket()
    {
        return $this->belongsTo(TroubleTicket::class);
    }

    /** Sub-item Budget Plan asal baris ini — sumber COA & pagu per item. */
    public function budgetDetail()
    {
        return $this->belongsTo(BudgetPlanDetail::class, 'budget_detail_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
