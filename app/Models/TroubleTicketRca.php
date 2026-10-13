<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * RCA (Root Cause Analysis) Trouble Ticket — 5 kategori akar masalah.
 * Diisi TroubleTicketRcaSeeder dari daftar RCA perusahaan; belum ada UI master.
 */
class TroubleTicketRca extends Model
{
    protected $table = 'trouble_ticket_rcas';

    protected $fillable = ['name', 'status'];

    public function subRcas(): HasMany
    {
        return $this->hasMany(TroubleTicketSubRca::class, 'rca_id');
    }

    /** RCA aktif, urut nama. */
    public static function active()
    {
        return static::where('status', 'ACTIVE')->orderBy('name')->get();
    }
}
