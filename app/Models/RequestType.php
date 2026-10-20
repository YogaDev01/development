<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestType extends Model
{
    protected $table = 'request_type';

    protected $fillable = ['name'];

    /* Tabelnya memang tanpa created_at/updated_at (migrasi
       2026_05_21_232223). Tanpa penegasan ini, setiap create() lewat Eloquent
       gagal "Unknown column 'updated_at'". */
    public $timestamps = false;

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