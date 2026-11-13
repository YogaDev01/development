<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Rekonsiliasi bank satu rekening untuk satu periode. DRAFT (dapat diubah) →
 * SUBMITTED (menunggu approval) → APPROVED (terkunci). Pembukaan ulang
 * (APPROVED → DRAFT) hanya lewat CashReconciliationController::reopen dan
 * tercatat di cash_reconciliation_logs.
 */
class CashReconciliation extends Model
{
    public const DRAFT     = 'DRAFT';
    public const SUBMITTED = 'SUBMITTED';
    public const APPROVED  = 'APPROVED';

    protected $fillable = [
        'number', 'source_fund_id', 'period_start', 'period_end', 'statement_ending_balance', 'status',
        'exception_reason', 'created_by', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'period_start'             => 'date',
        'period_end'               => 'date',
        'statement_ending_balance' => 'integer',
        'submitted_at'             => 'datetime',
        'approved_at'              => 'datetime',
    ];

    public function sourceFund() { return $this->belongsTo(SourceFund::class); }
    public function creator()    { return $this->belongsTo(User::class, 'created_by'); }
    public function submitter()  { return $this->belongsTo(User::class, 'submitted_by'); }
    public function approver()   { return $this->belongsTo(User::class, 'approved_by'); }
    public function lines()      { return $this->hasMany(BankStatementLine::class, 'reconciliation_id'); }
    public function matches()    { return $this->hasMany(CashReconciliationMatch::class, 'reconciliation_id'); }
    public function imports()    { return $this->hasMany(BankStatementImport::class, 'reconciliation_id'); }

    public function logs()
    {
        return $this->hasMany(CashReconciliationLog::class, 'reconciliation_id')->orderBy('id');
    }

    public function isLocked(): bool
    {
        return $this->status === self::APPROVED;
    }

    /** Hanya DRAFT yang boleh diubah (impor, matching). */
    public function isEditable(): bool
    {
        return $this->status === self::DRAFT;
    }
}
