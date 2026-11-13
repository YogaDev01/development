<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Header jurnal. Fase 1 hanya struktur; alur generate/submit/post dibangun di Fase 2.
 * Nominal = sen (lihat App\Support\AccountingMoney). Jurnal POSTED tidak boleh
 * diubah atau dihapus — koreksi lewat jurnal pembalik (reverses_journal_id).
 */
class Journal extends Model
{
    use SoftDeletes;

    public const DRAFT = 'DRAFT';
    public const SUBMITTED = 'SUBMITTED';
    public const POSTED = 'POSTED';
    public const REVERSED = 'REVERSED';

    public const STATUSES = [self::DRAFT, self::SUBMITTED, self::POSTED, self::REVERSED];

    public const TYPES = [
        'SOURCE', 'GENERAL', 'ACCRUAL', 'ADJUSTMENT', 'RECLASSIFICATION', 'DEPRECIATION', 'REVERSAL', 'OPENING',
    ];

    protected $fillable = [
        'journal_number', 'journal_date', 'auto_reverse_date', 'accounting_period_id', 'journal_type', 'status',
        'reference_number', 'description', 'total_debit', 'total_credit', 'reverses_journal_id',
        'needs_review', 'review_reason', 'return_reason', 'created_by', 'last_edited_by', 'submitted_by', 'submitted_at',
        'posted_by', 'posted_at', 'deleted_by', 'delete_reason',
    ];

    protected $casts = [
        'journal_date' => 'date',
        'auto_reverse_date' => 'date',
        'total_debit'  => 'integer',
        'total_credit' => 'integer',
        'needs_review' => 'boolean',
        'submitted_at' => 'datetime',
        'posted_at'    => 'datetime',
    ];

    public function lines()
    {
        return $this->hasMany(JournalLine::class)->orderBy('line_no');
    }

    public function sources()
    {
        return $this->hasMany(JournalSource::class);
    }

    public function period()
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Jenis yang boleh dibuat manual (SOURCE hanya dari Treasury/Cash Receipt, REVERSAL lewat aksi pembalik). */
    public const MANUAL_TYPES = [
        'GENERAL'          => 'General Journal',
        'ACCRUAL'          => 'Accrual Journal',
        'ADJUSTMENT'       => 'Adjustment Journal',
        'RECLASSIFICATION' => 'Reclassification Journal',
        'DEPRECIATION'     => 'Depreciation Journal',
        'OPENING'          => 'Jurnal Saldo Awal',
    ];

    /** Jurnal yang dibalikkan oleh jurnal ini (bila jenis REVERSAL). */
    public function reversesJournal()
    {
        return $this->belongsTo(self::class, 'reverses_journal_id');
    }

    /** Jurnal pembalik atas jurnal ini. */
    public function reversals()
    {
        return $this->hasMany(self::class, 'reverses_journal_id');
    }

    /** JV/{tahun}/{urut} — urut mengikuti pembuatan (bukan tanggal jurnal). */
    public static function nextNumber(): string
    {
        $year = now()->year;

        return sprintf('JV/%d/%05d', $year, \App\Support\DocumentSequence::next('journal:' . $year, fn () => 0));
    }

    public function isBalanced(): bool
    {
        return $this->total_debit === $this->total_credit && $this->total_debit > 0;
    }

    /** Hanya Draft yang boleh diedit/dihapus. */
    public function isEditable(): bool
    {
        return $this->status === self::DRAFT;
    }
}
