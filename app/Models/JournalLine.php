<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalLine extends Model
{
    protected $fillable = [
        'journal_id', 'line_no', 'coa_account_id', 'suggested_account_id', 'description',
        'debit', 'credit', 'cost_center_id', 'project_id',
    ];

    protected $casts = ['debit' => 'integer', 'credit' => 'integer'];

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function account()
    {
        return $this->belongsTo(CoaAccount::class, 'coa_account_id');
    }
}
