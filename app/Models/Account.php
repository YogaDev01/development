<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = [
        'name',
        'category',
        'status',
    ];

    public const CATEGORIES = [
        'Variable Cost',
        'Fixed Cost',
        'Overhead',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function budgetPlanDetails()
    {
        return $this->hasMany(BudgetPlanDetail::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}