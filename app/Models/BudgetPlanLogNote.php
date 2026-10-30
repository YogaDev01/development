<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetPlanLogNote extends Model
{
    protected $table = 'budget_plan_log_notes';

    public $timestamps = false;

    protected $fillable = [
        'budget_plan_id',
        'acted_by',
        'actor_name',
        'actor_role',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function budgetPlan()
    {
        return $this->belongsTo(BudgetPlan::class, 'budget_plan_id');
    }
}
