<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    protected $fillable = [
        'name',
        'code',
        'default_method',
    ];

    /** Opsi Default Method (dipakai untuk validasi & dropdown) */
    public const METHODS = [
        'In-House',
        'BI Fast',
        'Online',
        'SKN',
        'RTGS',
        'International',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATION
    |--------------------------------------------------------------------------
    */

    public function beneficiaries()
    {
        return $this->hasMany(Beneficiary::class, 'bank_id');
    }
}