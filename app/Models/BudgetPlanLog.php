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
        'submitted'        => 'Diajukan ke Finance',
        'finance_verified' => 'Diverifikasi Finance (Active)',
        'finance_revised'  => 'Direvisi oleh Finance',
        'finance_rejected' => 'Ditolak Finance',
        'closing_submitted'=> 'Pengajuan Closing oleh Manager',
        'closing_approved' => 'Closing Diverifikasi (Closed)',
        'closing_rejected' => 'Closing Ditolak (kembali Active)',
        'auto_closed'      => 'Otomatis Ditutup',
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