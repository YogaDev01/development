<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestStatusLog extends Model
{
    protected $table = 'request_status_logs';

    public $timestamps = false;

    protected $fillable = [
        'budget_request_id',
        'status_from',
        'status_to',
        'action_type',
        'notes',
        'acted_by',
        'actor_name',
        'actor_role',
        'request_snapshot',
    ];

    protected $casts = [
        'created_at'       => 'datetime',
        'request_snapshot' => 'array',
    ];

    public const ACTION_TYPES = [
        'created'                  => 'Dibuat',
        'submitted'                => 'Diajukan ke Head',
        'head_approved'            => 'Disetujui Head',
        'head_revised'             => 'Direvisi oleh Head',
        'head_rejected'            => 'Ditolak Head',
        'finance_approved'         => 'Diverifikasi Finance',
        'finance_revised'          => 'Direvisi oleh Finance',
        'finance_rejected'         => 'Ditolak Finance',
        'batched'                  => 'Dimasukkan ke Batch',
        'ceo_approved'             => 'Disetujui CEO',
        'ceo_rejected'             => 'Ditolak CEO (Takeout)',
        'paid'                     => 'Telah Dibayar',
        'realization_submitted'    => 'Realisasi Diajukan',
        'realization_approved'     => 'Realisasi Diverifikasi → Closed',
        'realization_rejected'     => 'Realisasi Ditolak → kembali Paid',
        'realized'                 => 'Direalisasi',
        'closed'                   => 'Ditutup',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function budgetRequest()
    {
        return $this->belongsTo(BudgetRequest::class, 'budget_request_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'acted_by');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function getActionLabelAttribute(): string
    {
        return self::ACTION_TYPES[$this->action_type] ?? $this->action_type;
    }

    public function getActionColorAttribute(): string
    {
        return match($this->action_type) {
            'created'                                       => 'secondary',
            'submitted'                                     => 'info',
            'head_approved', 'finance_approved'             => 'primary',
            'ceo_approved', 'paid', 'realized',
            'realization_approved', 'closed'                => 'success',
            'head_rejected', 'finance_rejected',
            'ceo_rejected', 'realization_rejected'          => 'danger',
            'head_revised', 'finance_revised',
            'realization_submitted'                         => 'warning',
            'batched'                                       => 'dark',
            default                                         => 'secondary',
        };
    }
}