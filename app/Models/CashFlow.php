<?php

namespace App\Models;

use App\Support\DocumentSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * M-01 — Cash Flow (header).
 *
 * Mewakili pergerakan kas yang TIDAK punya dokumen sumber di modul lain
 * (setoran modal, pendapatan lain-lain, biaya bank, koreksi kas). Pergerakan
 * kas dari Cash Receipt dan Treasury TIDAK disalin ke sini — lihat catatan
 * panjang di migrasi 2026_09_20_000003 dan di CashFlowService.
 */
class CashFlow extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'date',
        'description',
        'source_fund_id',
        'total_in',
        'total_out',
        'status',
        'source_module',
        'source_id',
        'reference_number',
        'notes',
        'created_by',
        'posted_by', 'posted_at',
        'cancelled_by', 'cancelled_at', 'cancel_reason',
    ];

    protected $casts = [
        'date'         => 'date',
        'total_in'     => 'integer',
        'total_out'    => 'integer',
        'posted_at'    => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public const STATUSES = ['DRAFT', 'POSTED', 'CANCELLED'];

    /**
     * Kategori entri manual. Sengaja daftar tetap (bukan tabel master): ini
     * kategori akuntansi kas yang jarang berubah, dan menaruhnya sebagai master
     * data baru hanya menambah satu menu yang harus dirawat tanpa manfaat.
     */
    public const CATEGORIES = [
        'CAPITAL_INJECTION' => 'Setoran Modal',
        'OTHER_INCOME'      => 'Pendapatan Lain-lain',
        'LOAN_RECEIPT'      => 'Penerimaan Pinjaman',
        'BANK_CHARGE'       => 'Biaya Bank / Admin',
        'TAX_PAYMENT'       => 'Pembayaran Pajak',
        'LOAN_PAYMENT'      => 'Pembayaran Pinjaman',
        'OTHER_EXPENSE'     => 'Pengeluaran Lain-lain',
        'ADJUSTMENT'        => 'Koreksi / Penyesuaian Kas',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function details()
    {
        return $this->hasMany(CashFlowDetail::class)->orderBy('id');
    }

    public function sourceFund()
    {
        return $this->belongsTo(SourceFund::class, 'source_fund_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /*
    |--------------------------------------------------------------------------
    | KODE & KALKULASI
    |--------------------------------------------------------------------------
    */

    /** CF/2026/09/0001 — penomoran lewat DocumentSequence (tahan balapan). */
    public static function generateCode(\Carbon\Carbon $date): string
    {
        $year = $date->format('Y');
        $mm   = $date->format('m');

        $no = DocumentSequence::next(
            "cash_flow:{$year}",
            fn () => static::withTrashed()->whereYear('date', $year)->count(),
        );

        return sprintf('CF/%s/%s/%s', $year, $mm, str_pad((string) $no, 4, '0', STR_PAD_LEFT));
    }

    /** Total header SELALU turunan dari detail — tidak pernah diinput langsung. */
    public function recalculateTotals(): void
    {
        $this->update([
            'total_in'  => (int) $this->details()->where('direction', 'IN')->sum('amount'),
            'total_out' => (int) $this->details()->where('direction', 'OUT')->sum('amount'),
        ]);
    }

    public function getNetAmountAttribute(): int
    {
        return (int) $this->total_in - (int) $this->total_out;
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS
    |--------------------------------------------------------------------------
    */

    public function isDraft(): bool     { return $this->status === 'DRAFT'; }
    public function isPosted(): bool    { return $this->status === 'POSTED'; }
    public function isCancelled(): bool { return $this->status === 'CANCELLED'; }

    /** Hanya DRAFT yang boleh diubah/dihapus — POSTED sudah masuk buku kas. */
    public function isEditable(): bool
    {
        return $this->isDraft();
    }
}
