<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Jejak audit rekonsiliasi (dibuat, submit, approve, reopen, dsb.). */
class CashReconciliationLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['reconciliation_id', 'action', 'notes', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
