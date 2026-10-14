<?php

namespace App\Models;

use App\Support\DocumentSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class BudgetRequest extends Model
{
    /*
    | Penghapusan catatan keuangan ditandai, bukan dilenyapkan - lihat migrasi
    | 2026_09_18_000002. Baris yang ditandai otomatis keluar dari semua query
    | (termasuk penjumlahan pembayaran), tapi tetap ada untuk ditelusuri.
    */
    use SoftDeletes;

    protected $table = 'budget_request';

    protected $fillable = [
        'deleted_by',
        'delete_reason',
        'code',
        'description',
        'amount',
        'notes',
        'is_nob',
        'nob_amount',
        'applicant_id',
        'beneficiary_id',
        'type_id',
        'cluster_id',
        'budget_plan_id',
        'status',
        'submitted_at',
        'approved_at',
        'paid_at',
        'realized_at',
        'closed_at',
        'email',
    ];

    protected $casts = [
        'is_nob'       => 'boolean',
        'nob_amount'   => 'integer',
        'amount'       => 'integer',
        'submitted_at' => 'datetime',
        'approved_at'  => 'datetime',
        'paid_at'      => 'datetime',
        'realized_at'  => 'datetime',
        'closed_at'    => 'datetime',
    ];

    public const STATUSES = [
        'DRAFT',
        'SUBMITTED',
        'CHECKED',
        'VERIFIED',
        'APPROVED',
        'QUEUED',
        'PAID',
        'REALIZED',
        'SETTLE_CHECKED',
        'SETTLE_VERIFIED',
        // Dua tahap baru — sudah terdaftar supaya label, warna, dan stepper
        // konsisten. Transisinya belum dipasang (getAllowedNextStatuses):
        // aturan perpindahannya menyusul.
        'SETTLE_APPROVED',
        'SECOND_APPROVED',
        'CLOSED',
        'REJECTED',
        'CANCELLED',
        // Reject settlement (Head/Finance/Finance Manager/Approver) berhenti
        // di sini, bukan langsung ke PAID — lihat getAllowedNextStatuses()
        // dan Concerns\HandlesCrSettlement.
        'SETTLE_REJECTED',
    ];

    /*
    |--------------------------------------------------------------------------
    | LIMIT CASH ADVANCE PER PIC
    |--------------------------------------------------------------------------
    | Angkanya BUKAN konstanta: diambil dari Master PIC (master_pics) milik
    | beneficiary yang dipilih — kolom `token` jadi batas jumlah pengajuan dan
    | kolom `nominal` jadi batas total rupiah. Kolom yang kosong berarti aspek
    | itu tidak dibatasi.
    |
    | Jumlah pengajuan dihitung mulai CR mencapai tahap PAID (uangnya benar-
    | benar keluar) sampai tahap SETTLE_APPROVED selesai di-approve Approver.
    | Begitu CR pindah ke SECOND_APPROVED, slotnya LEPAS — seluruh approval
    | settlement sudah tuntas, jadi PIC boleh mengajukan Cash Advance baru
    | tanpa menunggu pembayaran Expense Claim dan penutupan CR-nya.
    |
    | Nominal tetap dihitung sejak CR dibuat sampai CLOSED/CANCELLED.
    */

    /** Status yang TIDAK lagi memakai limit CA milik PIC. */
    public const CA_QUOTA_RELEASED_STATUSES = ['CLOSED', 'CANCELLED'];

    /**
     * Status yang dihitung sebagai "pengajuan berjalan": sudah cair dan
     * approval settlement-nya belum tuntas. SECOND_APPROVED dan seterusnya
     * sengaja TIDAK ikut — di situlah slot limit dikembalikan.
     */
    public const CA_COUNTED_STATUSES = [
        'PAID', 'REALIZED', 'SETTLE_CHECKED', 'SETTLE_VERIFIED', 'SETTLE_APPROVED',
        // Settlement ditolak TETAP berjalan (dananya belum balik/ditutup),
        // jadi tetap ikut terhitung — sama seperti PAID.
        'SETTLE_REJECTED',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function type()
    {
        return $this->belongsTo(RequestType::class, 'type_id');
    }

    public function budgetPlan()
    {
        return $this->belongsTo(BudgetPlan::class, 'budget_plan_id');
    }

    public function applicant()
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class, 'beneficiary_id');
    }

    public function cluster()
    {
        return $this->belongsTo(Cluster::class, 'cluster_id');
    }

    public function attachments()
    {
        return $this->hasMany(RequestAttachment::class, 'budget_request_id')->orderBy('id');
    }

    /**
     * Lampiran pengajuan. Tabel request_attachments memang KHUSUS lampiran
     * pengajuan (realisasi & bukti bayar punya tabelnya sendiri), jadi ini
     * identik dengan attachments() — tanpa filter kolom 'type'.
     */
    public function requestAttachments()
    {
        return $this->hasMany(RequestAttachment::class, 'budget_request_id')->orderBy('id');
    }

    public function details()
    {
        return $this->hasMany(RequestDetail::class, 'budget_request_id')->orderBy('id');
    }

    /** Detail yang masih ikut proses (bukan TAKEOUT) — dasar nilai pengajuan. */
    public function includedDetails()
    {
        return $this->hasMany(RequestDetail::class, 'budget_request_id')
            ->where('status', '!=', 'TAKEOUT')
            ->orderBy('id');
    }

    public function statusLogs()
    {
        return $this->hasMany(RequestStatusLog::class, 'budget_request_id')
            ->orderBy('created_at', 'desc');
    }

    public function dailyTransactionItems()
    {
        return $this->hasMany(DailyTransactionItem::class, 'budget_request_id');
    }

    public function realizations()
    {
        return $this->hasMany(BudgetRequestRealization::class, 'budget_request_id')->orderBy('id');
    }

    public function realizationAttachments()
    {
        return $this->hasMany(BudgetRequestRealizationAttachment::class, 'budget_request_id')->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS CHECKS
    |--------------------------------------------------------------------------
    */

    public function isDraft(): bool     { return $this->status === 'DRAFT'; }
    public function isSubmitted(): bool { return $this->status === 'SUBMITTED'; }
    public function isChecked(): bool   { return $this->status === 'CHECKED'; }
    public function isVerified(): bool  { return $this->status === 'VERIFIED'; }
    public function isApproved(): bool  { return $this->status === 'APPROVED'; }
    public function isQueued(): bool    { return $this->status === 'QUEUED'; }
    public function isPaid(): bool      { return $this->status === 'PAID'; }
    public function isRealized(): bool  { return $this->status === 'REALIZED'; }
    public function isClosed(): bool    { return $this->status === 'CLOSED'; }
    public function isRejected(): bool  { return $this->status === 'REJECTED'; }
    public function isCancelled(): bool { return $this->status === 'CANCELLED'; }
    public function isEditable(): bool  { return $this->status === 'DRAFT'; }

    /** Finance bisa kembalikan REALIZED → PAID */
    public function isRealizationRevisable(): bool
    {
        return $this->status === 'REALIZED';
    }

    /**
     * Cash Advance — satu-satunya tipe request yang menjalani alur lengkap
     * Process (QUEUED) > Paid > Settle (REALIZED) > Closed. Tipe lain langsung
     * Process (QUEUED) > (Treasury tandai Paid) > Closed, tanpa tahap realisasi.
     * Deteksi berbasis nama RequestType (case-insensitive), pola sama seperti
     * BudgetRequestController::typeIsCashAdvance().
     */
    public function isCashAdvance(): bool
    {
        $name = strtolower(trim($this->type?->name ?? ''));

        return in_array($name, self::CASH_ADVANCE_TYPE_NAMES, true);
    }

    /** Nama RequestType (lowercase) yang dianggap Cash Advance. */
    public const CASH_ADVANCE_TYPE_NAMES = ['cash advance', 'cash-advance', 'cashadvance'];

    /** @var array<int>|null cache per-request: id RequestType Cash Advance */
    private static ?array $cashAdvanceTypeIdsCache = null;

    /**
     * ID RequestType Cash Advance (tabel kecil, di-cache per request). Dipakai
     * scope di bawah supaya pemisahan Cash Advance vs Cost Payment cukup
     * whereIn/whereNotIn pada type_id (memanfaatkan indeks), bukan join/subquery.
     */
    public static function cashAdvanceTypeIds(): array
    {
        return self::$cashAdvanceTypeIdsCache ??= RequestType::query()
            ->whereRaw('LOWER(TRIM(name)) IN (?, ?, ?)', self::CASH_ADVANCE_TYPE_NAMES)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** Hanya Cash Advance (alur penuh sampai Settlement). */
    public function scopeCashAdvance($query)
    {
        return $query->whereIn('type_id', self::cashAdvanceTypeIds());
    }

    /**
     * CR yang uangnya sudah keluar — dasar kolom "Used" Budget Plan.
     * Selain daftar DISBURSED, ikut juga CR yang sedang di leg expense claim
     * dan kembali ke QUEUED (masuk batch Treasury untuk kekurangannya):
     * pencairan awalnya sudah lama keluar, jadi tidak boleh hilang dari Used.
     */
    public function scopeDisbursed($query)
    {
        return $query->where(fn ($w) => $w
            ->whereIn('status', \App\Support\BudgetConsumption::DISBURSED)
            ->orWhere(fn ($q) => $q
                ->where('status', 'QUEUED')
                ->whereHas('statusLogs', fn ($l) => $l->where('status_to', 'SECOND_APPROVED'))));
    }

    /** Padanan scopeDisbursed() untuk CR yang sudah dimuat (collection). */
    public function isDisbursed(): bool
    {
        if (in_array($this->status, \App\Support\BudgetConsumption::DISBURSED, true)) {
            return true;
        }

        return $this->status === 'QUEUED' && $this->isExpenseClaimLeg();
    }

    /** Semua selain Cash Advance (Process langsung Closed, tanpa Settlement). */
    public function scopeCostPayment($query)
    {
        $ids = self::cashAdvanceTypeIds();

        return $query->where(fn ($w) => $w->whereNull('type_id')->orWhereNotIn('type_id', $ids));
    }

    /**
     * Log status_to='REJECTED' paling baru — sumber reason/rejected by/at/from
     * untuk panel Reject Handling (Manager pilih Revise/Cancel). statusLogs()
     * sudah diurutkan created_at desc, jadi cukup first(). Pakai koleksi yang
     * sudah di-eager-load kalau ada (hindari N+1), fallback ke query baru.
     */
    public function latestRejectionLog(): ?RequestStatusLog
    {
        if ($this->relationLoaded('statusLogs')) {
            return $this->statusLogs->firstWhere('status_to', 'REJECTED');
        }

        return $this->statusLogs()->where('status_to', 'REJECTED')->first();
    }

    /**
     * Log transisi TERAKHIR yang membawa CR ini ke status-nya SEKARANG —
     * sumber timestamp "aging" Todo List (dihitung sejak CR masuk ke status
     * saat ini, bukan sejak pertama kali dibuat). Fallback ke created_at
     * kalau belum ada log sama sekali (mis. masih DRAFT).
     */
    public function latestStatusChangeLog(): ?RequestStatusLog
    {
        if ($this->relationLoaded('statusLogs')) {
            return $this->statusLogs->firstWhere('status_to', $this->status);
        }

        return $this->statusLogs()->where('status_to', $this->status)->first();
    }

    /** Sejak kapan CR ini berada di status-nya sekarang — dasar hitungan aging. */
    public function getAgingSinceAttribute(): \Illuminate\Support\Carbon
    {
        // Dibaca berkali-kali per CR (sortBy di tiap antrean Todo/Dashboard,
        // aging_label, dst). Tanpa cache, CR yang statusLogs-nya tidak
        // di-eager-load memicu satu query SETIAP kali dibaca.
        return $this->agingSinceCache ??= ($this->latestStatusChangeLog()?->created_at ?? $this->created_at ?? now());
    }

    /** Cache in-memory per instance untuk aging_since. */
    private ?\Illuminate\Support\Carbon $agingSinceCache = null;

    /** Cache in-memory per instance — lihat catatan di getTotalRealizationAttribute(). */
    private ?int $totalRealizationCache = null;

    /**
     * Dipanggil berulang per CR (mis. loop "Close Realisasi" di Todo List, atau
     * refund/refund_formatted yang sama-sama memanggil ini di showJson()) —
     * pakai koleksi realizations yang sudah di-eager-load kalau ada (nol query
     * tambahan), fallback ke query baru (di-cache per instance) kalau belum.
     */
    public function getTotalRealizationAttribute(): int
    {
        if ($this->relationLoaded('realizations')) {
            return (int) $this->realizations->sum('total');
        }

        if ($this->totalRealizationCache !== null) {
            return $this->totalRealizationCache;
        }

        return $this->totalRealizationCache = (int) $this->realizations()->sum('total');
    }

    public function getTotalRealizationFormattedAttribute(): string
    {
        return number_format($this->total_realization, 0, ',', '.');
    }

    public function secondPayments()
    {
        return $this->hasMany(BudgetRequestSecondPayment::class, 'budget_request_id')->orderBy('id');
    }

    /** Second Payment yang masih PENDING (kalau ada) — CR baru boleh Close
     *  setelah baris ini PAID (lihat BudgetRequestController::ceoCloseSettlement()). */
    public function pendingSecondPayment(): ?BudgetRequestSecondPayment
    {
        if ($this->relationLoaded('secondPayments')) {
            return $this->secondPayments->firstWhere('status', 'PENDING');
        }

        return $this->secondPayments()->where('status', 'PENDING')->first();
    }

    /*
    |--------------------------------------------------------------------------
    | REFUND — SATU-SATUNYA SUMBER KEBENARAN (P-01)
    |--------------------------------------------------------------------------
    | Refund hanya nyata setelah Cost Request melewati settlement. Sebelum itu
    | (REALIZED = baris realisasi baru diinput, belum disetujui Cost Control /
    | Finance) selisih amount - realisasi masih bisa berubah, jadi TIDAK boleh
    | dipakai sebagai angka refund di detail, summary, atau reporting.
    |
    | Aplikasi ini tidak punya status literal "SETTLE"; tahap settlement-nya
    | adalah SETTLE_CHECKED -> SETTLE_VERIFIED -> CLOSED. Ketiganya yang
    | dianggap "sudah settle" oleh rule P-01.
    */

    /** Status yang dianggap sudah melewati settlement. */
    public const SETTLED_STATUSES = [
        'SETTLE_CHECKED', 'SETTLE_VERIFIED', 'SETTLE_APPROVED', 'SECOND_APPROVED', 'CLOSED',
    ];

    public function isSettled(): bool
    {
        return in_array($this->status, self::SETTLED_STATUSES, true);
    }

    /**
     * Refund = Pengajuan - Settlement. Nyata begitu settlement DIISI (ada
     * realisasi), bukan menunggu settlement disetujui — simetris dengan
     * expenseClaimAmount() yang juga sudah muncul sejak realisasi diinput.
     * Negatif tidak mungkin: kelebihan realisasi ditangani lewat Second
     * Payment, bukan refund negatif.
     */
    public function refundAmount(): int
    {
        /*
        | Dua penjagaan, bukan lagi isSettled():
        |   - Tipe selain Cash Advance TIDAK punya tahap settlement: dananya
        |     cair sesuai pengajuan lalu langsung Closed, tidak ada sisa yang
        |     dikembalikan. Tanpa isCashAdvance() CR non-CA yang Closed terbaca
        |     refund = SELURUH nominalnya (total_realization-nya memang 0).
        |   - Cash Advance yang BELUM pernah diajukan settlement-nya (realize
        |     form belum pernah disubmit): belum ada dasar refund, jadi 0.
        |     INI BUKAN hal yang sama dengan "settlement diisi 0" — Settle
        |     boleh 0 (dana sama sekali tidak dipakai / dikembalikan utuh),
        |     dan dalam kasus itu refund justru harus senilai penuh amount.
        |     Pembedanya pakai hasSettlementSubmitted() (baris realisasi ada
        |     atau tidak), bukan total_realization <= 0 — sum 0 tidak bisa
        |     dibedakan dari "belum ada baris" sama sekali.
        | Sebelumnya dijaga !isSettled() sehingga refund baru muncul SETELAH
        | settlement disetujui — tidak simetris dengan expenseClaimAmount().
        */
        if (!$this->isCashAdvance() || !$this->hasSettlementSubmitted()) {
            return 0;
        }

        return max(0, (int) $this->amount - $this->total_realization);
    }

    /**
     * Settlement sudah pernah diajukan (minimal sekali submit realize form),
     * terlepas dari totalnya. Dipakai refundAmount() untuk membedakan
     * "belum pernah settle" (realizations masih kosong) dari "sudah settle
     * dengan Settle = 0" (total_realization kebetulan 0 juga, tapi baris
     * realisasi tetap tercatat — lihat syncRealizations()).
     */
    public function hasSettlementSubmitted(): bool
    {
        if ($this->relationLoaded('realizations')) {
            return $this->realizations->isNotEmpty();
        }

        return $this->realizations()->exists();
    }

    /**
     * Expense Claim = Settlement - Request. Kebalikan refund: settlement
     * MELEBIHI nilai pengajuan, jadi ada kekurangan bayar yang ditagihkan
     * balik ke perusahaan (diselesaikan lewat Second Payment).
     *
     * Sengaja TIDAK memakai penjagaan isSettled() seperti refundAmount():
     * angkanya sudah nyata sejak realisasi diinput (status Settle), dan justru
     * di tahap itulah kelebihannya perlu terlihat. Tetap tidak pernah negatif
     * — kekurangan realisasi adalah urusan Refund, bukan kolom ini. CR non
     * Cash Advance ikut nol dengan sendirinya karena realisasinya 0.
     */
    public function expenseClaimAmount(): int
    {
        return max(0, $this->total_realization - (int) $this->amount);
    }

    /**
     * CR sedang berada di kaki pembayaran Expense Claim — sudah pernah
     | di-Second Approve, jadi QUEUED di sini BUKAN pencairan pengajuan awal
     * melainkan pembayaran kekurangan settlement. Dipakai Treasury untuk
     * memutuskan: batch dibayar -> CR langsung CLOSED, bukan PAID.
     */
    public function isExpenseClaimLeg(): bool
    {
        return $this->statusLogs()->where('status_to', 'SECOND_APPROVED')->exists();
    }

    /** Settlement melebihi pengajuan, jadi wajib lewat Settle Approved & Second Approved. */
    public function hasExpenseClaim(): bool
    {
        return $this->expenseClaimAmount() > 0;
    }

    public function getExpenseClaimAttribute(): int
    {
        return $this->expenseClaimAmount();
    }

    public function getExpenseClaimFormattedAttribute(): string
    {
        return number_format($this->expenseClaimAmount(), 0, ',', '.');
    }

    public function getRefundAttribute(): int
    {
        return $this->refundAmount();
    }

    public function getRefundFormattedAttribute(): string
    {
        return number_format($this->refundAmount(), 0, ',', '.');
    }

    /**
     * Refund yang DIUSULKAN — dipakai HANYA di layar approval settlement
     * (Todo List), tempat user justru perlu melihat selisih sebelum settle
     * disetujui. Jangan dipakai untuk summary/report.
     */
    public function proposedRefundAmount(): int
    {
        return (int) ($this->amount ?? 0) - $this->total_realization;
    }

    /**
     * Nilai biaya yang benar-benar terpakai, mengikuti aturan settlement yang
     * sama dengan refundAmount():
     *   - Cash Advance + settlement sudah pernah diajukan -> nilai settlement
     *     (biaya aktual, termasuk 0 kalau dana sepenuhnya dikembalikan/tidak
     *     dipakai), bukan menunggu settlement disetujui;
     *   - selainnya (tipe lain, atau CA yang settlement-nya belum pernah
     *     diajukan sama sekali) -> nilai pengajuan penuh.
     * Dipakai kolom "realisasi/used" per CR di modal Budget Plan & Variable
     * Cost PO (Project::variableCostTotal()).
     */
    public function realizedCostValue(): int
    {
        // Deteksi CA memakai isCashAdvance() (nama tipe), sama dengan
        // refundAmount() — relasi `type` (dan `realizations` untuk
        // hasSettlementSubmitted()) perlu di-eager-load oleh pemanggil yang
        // mengagregasi banyak baris (lihat Project::preloadFinancials()).
        return $this->isCashAdvance() && $this->hasSettlementSubmitted()
            ? (int) $this->total_realization
            : (int) $this->amount;
    }

    /** Selisih = Settlement (total realisasi) - Original Approved Amount. Dasar
     *  nilai Second Payment saat Settlement > nilai pengajuan awal. */
    public function getSettlementOverageAttribute(): int
    {
        return $this->total_realization - (int) $this->amount;
    }

    public function getAllowedNextStatuses(): array
    {
        return match($this->status) {
            'DRAFT'     => ['SUBMITTED'],
            'SUBMITTED' => ['CHECKED', 'REJECTED'],    // Head: approve or reject only
            'CHECKED'   => ['VERIFIED', 'REJECTED'],   // Finance: approve or reject only
            'VERIFIED'  => ['APPROVED', 'REJECTED'],   // CEO/Approver: individual approval step
            'APPROVED'  => ['QUEUED'],                 // via Daily Transaction batch (label: "Process")
            // Treasury markPaid: Cash Advance -> PAID (lanjut realisasi),
            // tipe lain -> langsung CLOSED (tidak ada tahap realisasi).
            'QUEUED'    => $this->isCashAdvance() ? ['PAID'] : ['CLOSED'],
            'PAID'      => ['REALIZED'],
            // Settlement (Cash Advance only): Head check -> Finance verify -> CEO
            // close. Reject di tiap tahap berhenti di SETTLE_REJECTED untuk
            // direvisi (bukan langsung ke PAID — lihat SETTLE_REJECTED di bawah).
            'REALIZED'         => ['SETTLE_CHECKED', 'SETTLE_REJECTED'],
            'SETTLE_CHECKED'   => ['SETTLE_VERIFIED', 'SETTLE_REJECTED'],
            /*
            | Cabang Expense Claim: settlement melebihi pengajuan, jadi
            | kekurangannya harus disetujui dua lapis dulu.
            |   SETTLE_VERIFIED  -> menunggu approve Finance Manager
            |   SETTLE_APPROVED  -> menunggu approve Approver
            |   SECOND_APPROVED  -> menunggu Treasury membuat batch
            |   QUEUED (Process) -> menunggu dibayar, lalu CLOSED otomatis
            | Tanpa klaim, alurnya tetap SETTLE_VERIFIED -> CLOSED.
            */
            'SETTLE_VERIFIED'  => $this->expenseClaimAmount() > 0 ? ['SETTLE_APPROVED', 'SETTLE_REJECTED'] : ['CLOSED'],
            'SETTLE_APPROVED'  => ['SECOND_APPROVED', 'SETTLE_REJECTED'],
            'SECOND_APPROVED'  => ['QUEUED'],
            'REJECTED'  => ['DRAFT', 'CANCELLED'],     // Manager decision: Revise or Cancel
            // Settlement ditolak: revisi lalu submit ulang, sama seperti PAID.
            'SETTLE_REJECTED'  => ['REALIZED'],
            default     => [],
        };
    }

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, $this->getAllowedNextStatuses());
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Generate code: CR-{MM}{YY}{kodePO}{urutan4digit}
     *
     * Slot cluster 4 digit yang dulu berada di antara kode PO dan nomor urut
     * dihapus, jadi kodenya 4 digit lebih pendek:
     *   lama : CR-0926 0085 0018 0039   (bulan-tahun, PO, cluster, urut)
     *   baru : CR-0926 0085 0039        (bulan-tahun, PO, urut)
     *
     * Kode lama DIBIARKAN apa adanya - tidak ada backfill. Keduanya tidak
     * mungkin bentrok karena panjangnya berbeda (18 vs 14 karakter).
     */
    public static function generateCode(string $poCode): string
    {
        $month  = now()->format('m');
        $year   = now()->format('y');
        $po     = str_pad($poCode, 4, '0', STR_PAD_LEFT);
        $prefix = 'CR-' . $month . $year . $po;

        /*
        | Nomor diambil dari penghitung terkunci, bukan COUNT(*) + 1 dengan
        | putaran coba-lagi. Putaran itu menutupi gejala (bentrok) tanpa
        | menghilangkan sebabnya: dua permintaan tetap membaca hitungan yang
        | sama, dan di bawah beban keduanya bisa berputar mengejar nomor yang
        | sama berulang kali. Penghitung disemai dari data yang ada.
        |
        | Prefix baru lebih pendek, sehingga LIKE-nya ikut mencakup kode format
        | lama pada PO & bulan yang sama (CR-09260085xxxx0039). Itu memang
        | dikehendaki: penghitung jadi bersambung dari nomor terakhir yang
        | sudah terbit, bukan mengulang dari 0001 di bulan berjalan.
        */
        $no = DocumentSequence::next(
            'cr:' . $prefix,
            fn () => static::whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->where('code', 'like', $prefix . '%')
                ->count(),
        );

        return $prefix . str_pad((string) $no, 4, '0', STR_PAD_LEFT);
    }

    public function getAmountFormattedAttribute(): string
    {
        return number_format($this->amount ?? 0, 0, ',', '.');
    }

    /** Umur CR sejak diajukan, dalam hari bulat (dibulatkan ke bawah). Dipakai Todo List. */
    /**
     * Umur CR SEJAK MASUK KE STATUS SAAT INI (bukan sejak pertama diajukan) —
     * dalam hari bulat (dibulatkan ke bawah). Dipakai Todo List.
     */
    public function getAgingDaysAttribute(): int
    {
        return (int) floor($this->aging_since->diffInDays(now()));
    }

    /**
     * Label umur CR yang ramah dibaca — otomatis turun ke satuan jam/menit
     * kalau belum sampai 1 hari, supaya CR yang baru saja masuk tidak selalu
     * tampil "0 hari". Dihitung sejak transisi TERAKHIR ke status saat ini
     * (aging_since), bukan sejak created_at. Dipakai Todo List.
     */
    public function getAgingLabelAttribute(): string
    {
        $since = $this->aging_since;

        $days = $this->aging_days;
        if ($days >= 1) return $days . ' hari';

        $hours = (int) floor($since->diffInHours(now()));
        if ($hours >= 1) return $hours . ' jam';

        $minutes = (int) floor($since->diffInMinutes(now()));
        return $minutes . ' menit';
    }

    /**
     * PM Internal — diambil dari relasi Budget Plan > Project > manager (users).
     * '-' jika Budget Plan bersumber dari Cost Center (tidak punya Project) atau
     * PO belum diisi Manager-nya.
     */
    public function getPmInternalNameAttribute(): string
    {
        return $this->budgetPlan?->project?->pm_internal_name ?: '-';
    }

    /** Kode Purchase Order (Project::code) — null jika tidak bersumber dari Project. */
    public function getPoCodeAttribute(): ?string
    {
        return $this->budgetPlan?->project?->code;
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'DRAFT'     => 'secondary',
            'SUBMITTED' => 'warning',
            'CHECKED'   => 'info',
            'VERIFIED'  => 'primary',
            'APPROVED'  => 'success',
            'QUEUED'    => 'warning',
            'PAID'      => 'success',
            'REALIZED'  => 'primary',
            'SETTLE_APPROVED' => 'success',
            'SECOND_APPROVED' => 'success',
            'CLOSED'    => 'dark',
            'REJECTED'  => 'danger',
            'CANCELLED' => 'dark',
            default     => 'secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabelFor($this->status);
    }

    /**
     * Label tampilan untuk sebuah kode status (dipakai juga untuk menampilkan
     * transisi status_from/status_to di riwayat log). "Process" = QUEUED,
     * "Settle" = REALIZED — istilah internal tetap dipakai di DB & filter.
     */
    public static function statusLabelFor(?string $status): string
    {
        return match($status) {
            'DRAFT'     => 'Draft',
            'SUBMITTED' => 'Submitted',
            'CHECKED'   => 'Checked',
            'VERIFIED'  => 'Verified',
            'APPROVED'  => 'Approved',
            'QUEUED'    => 'Process',
            'PAID'      => 'Paid',
            'REALIZED'  => 'Settle',
            'SETTLE_CHECKED'  => 'Settle Checked',
            'SETTLE_VERIFIED' => 'Settle Verified',
            'SETTLE_APPROVED' => 'Settle Approved',
            'SECOND_APPROVED' => 'Second Approved',
            'CLOSED'    => 'Closed',
            'REJECTED'  => 'Rejected',
            'CANCELLED' => 'Cancelled',
            'SETTLE_REJECTED' => 'Settle Rejected',
            default     => $status ?? '-',
        };
    }

    /** Role yang lihat SEMUA overhead tanpa access group */
    public const OVERHEAD_PRIVILEGED_ROLES = ['superadmin', 'approver', 'finance_manager', 'guest'];

    /** Role yang boleh melihat CR PAYROLL (rahasia) */
    public const PAYROLL_ROLES = ['superadmin', 'approver', 'hr', 'finance_manager', 'guest'];

    public function isAccessibleBy(User $user): bool
    {
        $role = strtolower($user->role->name ?? '');

        // Superadmin & approver (CEO) lihat semua — guest juga (view-only, akses
        // sama luasnya dengan approver, tapi tidak boleh create/update/delete).
        if (in_array($role, ['superadmin', 'approver', 'finance_manager', 'guest'])) return true;

        $plan = $this->budgetPlan;

        /*
        | CR bersumber dari COST CENTER:
        |   - PAYROLL  -> role-gated (rahasia)
        |   - OVERHEAD -> privileged lihat semua; lainnya per access group cost center
        */
        if ($plan && $plan->cost_center_id !== null) {
            $type = strtoupper($plan->costCenter->type ?? 'OVERHEAD');

            if ($type === 'PAYROLL') {
                return in_array($role, self::PAYROLL_ROLES);
            }

            // OVERHEAD
            if (in_array($role, self::OVERHEAD_PRIVILEGED_ROLES)) return true;

            if (!$user->accessGroup) return false;

            return $user->accessGroup->costCenterIds()
                ->contains((int) $plan->cost_center_id);
        }

        // CR bersumber dari PROYEK: manager/head/finance per access group project
        if (!$user->accessGroup) return false;

        $projectIds = $user->accessGroup->details->pluck('project_id')->filter();
        $projectId  = $plan?->project_id
                   ?? $this->cluster?->project_id;

        return $projectId !== null && $projectIds->contains($projectId);
    }

    public function recalculateAmount(): void
    {
        // Hanya detail yang masih INCLUDED yang dihitung — item yang di-takeout
        // salah satu approval tidak ikut nilai pengajuan yang diproses Treasury.
        $this->amount = $this->details()->where('status', '!=', 'TAKEOUT')->sum('total');
        $this->save();
    }

    /*
    |--------------------------------------------------------------------------
    | TAKEOUT SUB-ITEM — tahap approval yang boleh mengeluarkan item pengajuan
    |--------------------------------------------------------------------------
    */

    /** Status CR yang masih dalam proses approval (item bisa di-takeout/restore). */
    public const APPROVAL_STAGES = ['SUBMITTED', 'CHECKED', 'VERIFIED'];

    /** Role (selain superadmin) yang berwenang di tiap tahap approval. */
    public const STAGE_ROLES = [
        'SUBMITTED' => ['head'],
        'CHECKED'   => ['finance', 'finance_manager', 'cost_control'],
        'VERIFIED'  => ['approver'],
    ];

    public function isInApprovalStage(): bool
    {
        return in_array($this->status, self::APPROVAL_STAGES, true);
    }

    /** Apakah $user berwenang men-takeout/restore item pada tahap CR saat ini. */
    public function canModerateItems(?User $user): bool
    {
        if (!$user || !$this->isInApprovalStage()) return false;

        $role = strtolower($user->role->name ?? '');
        if ($role === 'superadmin') return true;

        return in_array($role, self::STAGE_ROLES[$this->status] ?? [], true);
    }

    public function addLog(
        string $statusFrom,
        string $statusTo,
        string $actionType,
        ?string $notes = null,
        ?string $snapshot = null
    ): void {
        $user = Auth::user();

        $this->statusLogs()->create([
            'status_from'      => $statusFrom,
            'status_to'        => $statusTo,
            'action_type'      => $actionType,
            'notes'            => $notes,
            'acted_by'         => $user?->id,
            'actor_name'       => $user?->name ?? 'System',
            'actor_role'       => $user?->role?->name,
            'request_snapshot' => $snapshot,
        ]);

        /*
        | Audit trail terpusat (UserActivityLog).
        |
        | addLog() adalah titik tunggal yang dilewati hampir semua transisi
        | status Cost Request, jadi mencatat di sini sekaligus menutup seluruh
        | jalur CR tanpa menyentuh tiap method controller. Gagal-aman: record()
        | sudah dibungkus try/catch, tidak akan menggagalkan transaksi CR.
        */
        $label = RequestStatusLog::ACTION_TYPES[$actionType] ?? $actionType;
        UserActivityLog::record(
            'cost_request',
            self::activityActionKey($actionType),
            trim($label) . ' — Cost Request #' . ($this->code ?? $this->id),
            $this,
            $notes
        );
    }

    /**
     * Pemetaan action_type CR (RequestStatusLog) → action key generik
     * UserActivityLog, supaya filter/warna di Activity Log konsisten.
     */
    protected static function activityActionKey(string $actionType): string
    {
        return match ($actionType) {
            'created'                                               => 'create',
            'submitted', 'realization_submitted'                    => 'submit',
            'head_approved', 'settlement_checked'                   => 'check',
            'finance_approved', 'settlement_verified'               => 'verify',
            'ceo_approved', 'cr_approved', 'settlement_approved',
            'second_payment_approved', 'second_payment_ceo_approved' => 'approve',
            'head_rejected', 'finance_rejected', 'ceo_rejected',
            'cr_rejected', 'realization_rejected', 'settlement_rejected',
            'second_payment_batch_rejected'                         => 'reject',
            'head_revised', 'finance_revised', 'revised'            => 'revise',
            'cancelled'                                             => 'cancel',
            'paid', 'second_payment_paid'                           => 'pay',
            'realization_approved', 'settlement_closed', 'closed'   => 'close',
            'item_takeout', 'all_items_takeout', 'second_payment_takeout' => 'takeout',
            'item_restore'                                          => 'restore',
            'batched', 'second_payment_batched', 'realized'         => 'process',
            default                                                 => 'update',
        };
    }
}