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
        'CLOSED',
        'REJECTED',
        'CANCELLED',
    ];

    /*
    |--------------------------------------------------------------------------
    | PAGU CASH ADVANCE PER PIC
    |--------------------------------------------------------------------------
    | Setiap PIC (master_pics, lewat beneficiaries.pic_id) hanya boleh punya
    | maksimal 10 Cash Advance berjalan dengan total nominal 20 juta. Kuota
    | dipakai sejak CR dibuat dan baru pulih saat CR di-CLOSED (CANCELLED
    | dikecualikan karena pengajuannya memang sudah mati/dibatalkan).
    */

    /** Maksimal jumlah Cash Advance berjalan per PIC. */
    public const CA_LIMIT_COUNT = 10;

    /** Maksimal total nominal Cash Advance berjalan per PIC (rupiah). */
    public const CA_LIMIT_AMOUNT = 20000000;

    /** Status yang TIDAK lagi memakai kuota CA milik PIC. */
    public const CA_QUOTA_RELEASED_STATUSES = ['CLOSED', 'CANCELLED'];

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

        return in_array($name, ['cash advance', 'cash-advance', 'cashadvance'], true);
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
        return $this->latestStatusChangeLog()?->created_at ?? $this->created_at ?? now();
    }

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
    public const SETTLED_STATUSES = ['SETTLE_CHECKED', 'SETTLE_VERIFIED', 'CLOSED'];

    public function isSettled(): bool
    {
        return in_array($this->status, self::SETTLED_STATUSES, true);
    }

    /**
     * Refund final. 0 selama CR belum settle — berapa pun realisasi yang sudah
     * terinput. Negatif tidak mungkin: kelebihan realisasi ditangani lewat
     * Second Payment, bukan refund negatif.
     */
    public function refundAmount(): int
    {
        if (!$this->isSettled()) {
            return 0;
        }

        return max(0, (int) $this->amount - $this->total_realization);
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
     * Nilai biaya yang benar-benar terpakai. Selama belum settle, nilai
     * pengajuan penuh yang dipakai (konsisten dengan refund = 0).
     */
    public function realizedCostValue(): int
    {
        return $this->isSettled() && $this->relationLoadedRealizationsNotEmpty()
            ? (int) $this->total_realization
            : (int) $this->amount;
    }

    private function relationLoadedRealizationsNotEmpty(): bool
    {
        return $this->relationLoaded('realizations')
            ? $this->realizations->isNotEmpty()
            : $this->realizations()->exists();
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
            // close. Reject di tiap tahap mengembalikan ke PAID untuk direvisi.
            'REALIZED'         => ['SETTLE_CHECKED', 'PAID'],
            'SETTLE_CHECKED'   => ['SETTLE_VERIFIED', 'PAID'],
            'SETTLE_VERIFIED'  => ['CLOSED'],
            'REJECTED'  => ['DRAFT', 'CANCELLED'],     // Manager decision: Revise or Cancel
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
            'SETTLE_CHECKED'  => 'Checked',
            'SETTLE_VERIFIED' => 'Verified',
            'CLOSED'    => 'Closed',
            'REJECTED'  => 'Rejected',
            'CANCELLED' => 'Cancelled',
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
    }
}