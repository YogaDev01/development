<?php

namespace App\Models;

use App\Models\BudgetPlan;
use Illuminate\Database\Eloquent\Model;

class BudgetPlanAttachment extends Model
{
    /*
    |--------------------------------------------------------------------------
    | TABLE
    |--------------------------------------------------------------------------
    */

    protected $table = 'budget_plan_attachments';

    /*
    |--------------------------------------------------------------------------
    | FILLABLE
    |--------------------------------------------------------------------------
    */

    protected $fillable = [

        'budget_plan_id',

        'original_name',

        'stored_name',

        'path',

        'mime_type',

        'size'

    ];

    /*
    |--------------------------------------------------------------------------
    | RELATION : BUDGET PLAN
    |--------------------------------------------------------------------------
    */

    public function budgetPlan()
    {
        return $this->belongsTo(BudgetPlan::class);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return (string) $this->mime_type === 'application/pdf';
    }
}