<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sub-RCA — turunan sebuah RCA. Pilihan di form dibatasi RCA yang dipilih
 * (endpoint trouble-ticket.sub-rcas).
 */
class TroubleTicketSubRca extends Model
{
    protected $table = 'trouble_ticket_sub_rcas';

    protected $fillable = ['rca_id', 'name', 'status'];

    public function rca(): BelongsTo
    {
        return $this->belongsTo(TroubleTicketRca::class, 'rca_id');
    }

    /** Sub-RCA aktif milik sebuah RCA, urut nama. */
    public static function activeFor(int $rcaId)
    {
        return static::where('status', 'ACTIVE')->where('rca_id', $rcaId)->orderBy('name')->get();
    }
}
