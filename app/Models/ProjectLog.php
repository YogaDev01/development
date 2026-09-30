<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectLog extends Model
{
    protected $table = 'project_logs';

    protected $fillable = [
        'project_id',
        'status_from',
        'status_to',
        'action',
        'notes',
        'user_id',
        'user_name',
        'role',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public const ACTIONS = [
        'created'              => 'Dibuat',
        'submitted'            => 'Diajukan',
        'approved_waiting'     => 'Disetujui (Waiting PO)',
        'approved_active'      => 'Disetujui (Active)',
        'waiting_to_active'    => 'PO Diterima → Active',
        'bast_submitted'       => 'BAST Diajukan',
        'bast_completed'       => 'BAST Ditandai Lengkap',
        'edited'               => 'Data PO Disunting',
        'po_number_added'      => 'No. PO Ditambahkan',
        'billed'               => 'Invoice Dibuat (Billed)',
        'paid'                 => 'Invoice Paid',
        'closing_submitted'    => 'MoM Closing Diajukan',
        'closing_approved'     => 'Closing Diverifikasi → Closed',
        'closing_rejected'     => 'MoM Ditolak → kembali ke Paid',
        'closed'               => 'Ditutup',
        'finance_closed'       => 'Ditutup (dari Paid)',
        'revised'              => 'Dikembalikan untuk Revisi',
        'rejected'             => 'Ditolak',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function getActionLabelAttribute(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            'created'                                    => 'secondary',
            'submitted'                                  => 'info',
            'approved_waiting'                           => 'warning',
            'approved_active', 'waiting_to_active'       => 'success',
            'bast_submitted', 'bast_completed',
            'closing_submitted'                          => 'primary',
            'edited', 'po_number_added'                  => 'info',
            'billed'                                     => 'info',
            'paid', 'closing_approved', 'closed',
            'finance_closed'                             => 'success',
            'closing_rejected', 'rejected'               => 'danger',
            'revised'                                    => 'warning',
            default                                      => 'secondary',
        };
    }
}