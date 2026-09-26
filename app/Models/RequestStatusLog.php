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
        'submitted'                => 'Diajukan',
        'head_approved'            => 'Disetujui',
        'head_revised'             => 'Direvisi',
        'head_rejected'            => 'Ditolak',
        'finance_approved'         => 'Diverifikasi',
        'finance_revised'          => 'Direvisi',
        'finance_rejected'         => 'Ditolak',
        'batched'                  => 'Dimasukkan ke Batch',
        'ceo_approved'             => 'Disetujui',
        'ceo_rejected'             => 'Ditolak (Takeout)',
        'paid'                     => 'Telah Dibayar',
        'realization_submitted'    => 'Realisasi Diajukan',
        'realization_approved'     => 'Realisasi Diverifikasi → Closed',
        'realization_rejected'     => 'Realisasi Ditolak → kembali Paid',
        'realized'                 => 'Direalisasi',
        'settlement_checked'       => 'Settlement Diperiksa',
        'settlement_verified'      => 'Settlement Diverifikasi',
        'settlement_approved'      => 'Settlement Disetujui (Finance Manager)',
        'second_payment_approved'  => 'Second Approved (Approver)',
        'second_payment_batched'   => 'Expense Claim Masuk Batch',
        'settlement_closed'        => 'Settlement Ditutup',
        'settlement_rejected'      => 'Settlement Ditolak',
        'closed'                   => 'Ditutup',
        'cr_approved'              => 'Disetujui',
        'cr_rejected'              => 'Ditolak',
        'revised'                  => 'Direvisi (dari Rejected)',
        'cancelled'                => 'Dibatalkan',
        'item_takeout'             => 'Item Di-takeout',
        'item_restore'             => 'Item Dikembalikan',
        'all_items_takeout'        => 'Semua Item Di-takeout → Rejected',

        // Second Payment — kekurangan bayar hasil Settlement. Dibayar lewat
        // batch Treasury, jadi jejaknya mengikuti siklus batch.
        'second_payment_batched'      => 'Second Payment Masuk Batch',
        'second_payment_ceo_approved' => 'Second Payment Disetujui',
        'second_payment_paid'         => 'Second Payment Telah Dibayar',
        'second_payment_takeout'      => 'Second Payment Di-takeout dari Batch',
        'second_payment_batch_rejected' => 'Batch Second Payment Ditolak',
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
            'ceo_approved', 'cr_approved', 'paid', 'realized',
            'settlement_approved', 'second_payment_approved',
            'realization_approved', 'closed'                => 'success',
            'head_rejected', 'finance_rejected',
            'ceo_rejected', 'cr_rejected',
            'realization_rejected', 'cancelled',
            'all_items_takeout'                             => 'danger',
            'head_revised', 'finance_revised', 'revised',
            'realization_submitted',
            'item_takeout', 'item_restore'                  => 'warning',
            'second_payment_paid'                           => 'success',
            'second_payment_takeout',
            'second_payment_batch_rejected'                 => 'danger',
            'second_payment_ceo_approved'                   => 'primary',
            'batched', 'second_payment_batched'             => 'dark',
            default                                         => 'secondary',
        };
    }
}