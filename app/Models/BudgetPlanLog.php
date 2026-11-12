<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetPlanLog extends Model
{
    protected $fillable = [
        'budget_plan_id',
        'status_from',
        'status_to',
        'action',
        'notes',
        'user_id',
        'user_name',
        'role',
        // Konteks organisasi pelaku saat aksi (migrasi 2026_11_06_000300).
        'actor_org_context',
    ];

    protected $casts = [
        'created_at'        => 'datetime',
        'actor_org_context' => 'array',
    ];

    public const ACTIONS = [
        'created'          => 'Dibuat',
        'submitted'        => 'Diajukan',
        'finance_verified' => 'Diverifikasi (Active)',
        'finance_revised'  => 'Dikembalikan untuk Revisi',
        'finance_rejected' => 'Ditolak',
        'closing_submitted'=> 'Closing Diajukan',
        'closing_approved' => 'Closing Diverifikasi (Closed)',
        'closing_rejected' => 'Closing Ditolak (kembali Active)',
        'auto_closed'      => 'Ditutup Otomatis',
        'coa_added'        => 'Tambah COA',
        'superadmin_edited'=> 'Diedit (Superadmin)',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function budgetPlan()
    {
        return $this->belongsTo(BudgetPlan::class);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function getActionLabelAttribute(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    public function getActionColorAttribute(): string
    {
        return match($this->action) {
            'created'                                   => 'secondary',
            'submitted', 'closing_submitted'            => 'warning',
            'finance_verified', 'closing_approved',
            'auto_closed'                               => 'success',
            'finance_rejected', 'closing_rejected'      => 'danger',
            'finance_revised', 'coa_added'              => 'info',
            'superadmin_edited'                         => 'warning',
            default                                     => 'secondary',
        };
    }
}