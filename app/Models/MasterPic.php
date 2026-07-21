<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterPic extends Model
{
    protected $fillable = [
        'type',
        'pic',
        'position',
        'phone',
        'email',
        'status',
        'input_by',
    ];

    public const TYPES = [
        'customer' => 'Customer',
        'internal' => 'Internal',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function inputBy()
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
