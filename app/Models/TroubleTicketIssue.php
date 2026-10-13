<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Initial Problem — gejala awal yang dilaporkan (kolom "Issue" pada daftar
 * RCA perusahaan). Menggantikan isian bebas trouble_tickets.issue.
 */
class TroubleTicketIssue extends Model
{
    protected $table = 'trouble_ticket_issues';

    protected $fillable = ['name', 'status'];

    public static function active()
    {
        return static::where('status', 'ACTIVE')->orderBy('name')->get();
    }
}
