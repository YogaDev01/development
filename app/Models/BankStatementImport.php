<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Riwayat impor CSV rekening koran. */
class BankStatementImport extends Model
{
    protected $fillable = ['reconciliation_id', 'filename', 'rows_imported', 'rows_skipped', 'imported_by'];

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
