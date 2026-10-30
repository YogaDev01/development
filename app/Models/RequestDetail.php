<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestDetail extends Model
{
    protected $table = 'request_detail';

    protected $fillable = [
        'budget_request_id',
        'budget_detail_id',
        'description',
        'amount',
        'qty',
        'total',
        'notes',
        'is_nob',
        'nob_amount',
        'account_id',
        'status',
        'takeout_by',
        'takeout_by_name',
        'takeout_role',
        'takeout_stage',
        'takeout_notes',
        'takeout_at',
    ];

    protected $casts = [
        'is_nob'     => 'boolean',
        'nob_amount' => 'integer',
        'amount'     => 'integer',
        'qty'        => 'integer',
        'total'      => 'integer',
        'takeout_at' => 'datetime',
    ];

    /** Label approval yang men-takeout item, dari status CR saat takeout. */
    public const STAGE_LABELS = [
        'SUBMITTED' => 'Head',
        'CHECKED'   => 'Finance',
        'VERIFIED'  => 'CEO',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function budgetRequest()
    {
        return $this->belongsTo(BudgetRequest::class, 'budget_request_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /** Sub-item Budget Plan yang dipilih untuk baris pengajuan ini */
    public function budgetDetail()
    {
        return $this->belongsTo(BudgetPlanDetail::class, 'budget_detail_id');
    }

    /**
     * Baris settlement yang berasal dari item pengajuan ini. Sejak Settlement
     * Custom COA (25 Okt 2026) satu item boleh dipecah menjadi beberapa baris
     * (Sub COA berbeda), atau tidak punya baris sama sekali (settle 0) — jadi
     * relasinya hasMany, bukan hasOne. Nilai biaya per sub-item jangan
     * dihitung dari sini; pakai BudgetRequest::costUnits().
     */
    public function realizations()
    {
        return $this->hasMany(BudgetRequestRealization::class, 'request_detail_id')->orderBy('id');
    }

    /** Lampiran KHUSUS baris ini (nota/bukti per item) */
    public function attachments()
    {
        return $this->hasMany(RequestDetailAttachment::class, 'request_detail_id')->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function getTotalFormattedAttribute(): string
    {
        return number_format($this->total ?? 0, 0, ',', '.');
    }

    public function isTakenOut(): bool
    {
        return $this->status === 'TAKEOUT';
    }

    /** "Head" / "Finance" / "CEO" — approval yang men-takeout item ini. */
    public function getTakeoutStageLabelAttribute(): string
    {
        return self::STAGE_LABELS[$this->takeout_stage] ?? ($this->takeout_role ?? '-');
    }

    public function scopeIncluded($query)
    {
        return $query->where('status', '!=', 'TAKEOUT');
    }
}