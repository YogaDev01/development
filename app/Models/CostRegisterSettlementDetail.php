<?php

namespace App\Models;

use App\Models\Concerns\ExpenseCostLine;
use Illuminate\Database\Eloquent\Model;

/**
 * Rincian realisasi (settlement) Expense Cash Advance. Struktur sama dengan
 * CostRegisterDetail (akun, cara pembebanan, snapshot kategori). Setelah settlement
 * disetujui CEO, rincian inilah yang dihitung sebagai biaya PO
 * (App\Support\ExpenseAllocation). Uang = integer rupiah.
 */
class CostRegisterSettlementDetail extends Model
{
    use ExpenseCostLine;

    protected $fillable = [
        'cost_register_id',
        'account_id',
        'project_id',
        'cost_center_id',
        'allocation',
        'allocation_year',
        'cost_category',
        'description',
        'qty',
        'unit_price',
        'total',
    ];

    protected $casts = [
        'qty'             => 'integer',
        'unit_price'      => 'integer',
        'total'           => 'integer',
        'allocation_year' => 'integer',
    ];

    public function costRegister()
    {
        return $this->belongsTo(CostRegister::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function costCenter()
    {
        return $this->belongsTo(CostCenter::class);
    }

    /** PO terpilih untuk allocation = 'selected'. */
    public function projects()
    {
        return $this->belongsToMany(Project::class, 'cost_register_settlement_detail_projects', 'cost_register_settlement_detail_id', 'project_id');
    }

    /**
     * Realisasi yang dihitung sebagai biaya: settlement Expense Cash Advance sudah
     * disetujui CEO (settle_approved_at terisi) dan dananya sudah keluar lewat
     * Treasury (CostRegister::PO_COST_COUNTED_STATUSES — untuk CA yang sampai tahap
     * ini berarti SETTLE_APPROVED / CLOSED).
     * Pasangan CostRegisterDetail::scopeCounted — tidak tumpang tindih.
     */
    public function scopeCounted($query)
    {
        return $query->whereNotNull('cost_register_settlement_details.allocation')
            ->whereHas('costRegister', fn ($q) => $q
                ->whereIn('status', CostRegister::PO_COST_COUNTED_STATUSES)
                ->where('is_cash_advance', true)
                ->whereNotNull('settle_approved_at'));
    }
}
