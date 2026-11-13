<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalSource extends Model
{
    protected $fillable = [
        'journal_id', 'source_module', 'source_type', 'source_id', 'source_line_id',
        'source_number', 'source_snapshot', 'active_key',
    ];

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    /** Kunci anti-dobel; UNIQUE di DB menolak dua jurnal aktif untuk sumber yang sama. */
    public static function makeActiveKey(string $type, int $id, int $lineId = 0): string
    {
        return $type . ':' . $id . ':' . $lineId;
    }
}
