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
        'created'          => 'Created',
        'submitted'        => 'Submitted to Finance',
        'finance_verified' => 'Verified by Finance (Active)',
        'finance_revised'  => 'Revised by Finance',
        'finance_rejected' => 'Rejected by Finance',
        'closing_submitted'=> 'Closing Submitted by Manager',
        'closing_approved' => 'Closing Verified (Closed)',
        'closing_rejected' => 'Closing Rejected (back to Active)',
        'auto_closed'      => 'Auto Closed',
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