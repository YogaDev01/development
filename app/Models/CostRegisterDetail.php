<?php

namespace App\Models;

use App\Models\Concerns\ExpenseCostLine;
use Illuminate\Database\Eloquent\Model;

/** Rincian per item Cost Register / Expense (header: CostRegister). Uang = integer rupiah. */
class CostRegisterDetail extends Model
{
    use ExpenseCostLine;

    protected $fillable = [
        'cost_register_id',
        'account_id',
        // Expense (2026-11-10): tujuan per rincian (lihat Concerns\ExpenseCostLine) dan
        // snapshot kategori akun (Variable Cost/Fixed Cost/Overhead).
        // Rincian Cost Register lama: semuanya NULL (ikut PO header).
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
        return $this->belongsToMany(Project::class, 'cost_register_detail_projects', 'cost_register_detail_id', 'project_id');
    }

    /**
     * Rincian pengajuan yang dihitung sebagai biaya: Expense baru (header tanpa type)
     * yang dananya sudah keluar lewat Treasury (CostRegister::PO_COST_COUNTED_STATUSES).
     * Expense Cash Advance yang settlement-nya sudah disetujui CEO TIDAK dihitung dari
     * sini — biayanya dari rincian realisasi (CostRegisterSettlementDetail::scopeCounted).
     */
    public function scopeCounted($query)
    {
        return $query->whereNotNull('cost_register_details.allocation')
            ->whereHas('costRegister', fn ($q) => $q
                ->whereNull('type')
                ->whereIn('status', CostRegister::PO_COST_COUNTED_STATUSES)
                ->where(fn ($q2) => $q2->where('is_cash_advance', false)->orWhereNull('settle_approved_at')));
    }
}
