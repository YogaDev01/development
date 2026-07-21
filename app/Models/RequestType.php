<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestType extends Model
{
    protected $table = 'request_type';

    protected $fillable = ['name'];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function budgetRequests()
    {
        return $this->hasMany(BudgetRequest::class, 'type_id');
    }
}