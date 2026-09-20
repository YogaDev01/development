<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostRegisterAttachment extends Model
{
    protected $fillable = [
        'cost_register_id',
        'title',
        'file_path',
        'uploaded_by',
    ];

    public function costRegister()
    {
        return $this->belongsTo(CostRegister::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
