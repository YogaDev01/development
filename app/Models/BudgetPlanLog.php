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
    ];

    protected $casts = [
        'created_at' => 'datetime',
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
            'finance_revised'                           => 'info',
            default                                     => 'secondary',
        };
    }
}