<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostRegister extends Model
{
    protected $fillable = [
        'expense_no',
        'project_id',
        'type',
        'amount',
        'period',
        'notes',
        'batch_code',
        'input_by',
        // MN-09 — alur Non-CR menuju Treasury.
        'status',
        'beneficiary_id',
        'source_fund_id',
        'submitted_at',
        'paid_at',
        'verified_by',
        'verified_at',
        'approved_by',
        'approved_at',
        // Expense Fase 2 — Cash Advance + settlement.
        'is_cash_advance',
        'settled_amount',
        'refund_amount',
        'shortfall_amount',
        'settle_submitted_by',
        'settle_submitted_at',
        'settle_approved_by',
        'settle_approved_at',
        // Expense Fase 3 — pembebanan multi-periode.
        'period_start',
        'period_end',
        'period_method',
    ];

    protected $casts = [
        'amount'       => 'integer',
        'period'       => 'date',
        'submitted_at' => 'datetime',
        'paid_at'      => 'datetime',
        'verified_at'  => 'datetime',
        'approved_at'  => 'datetime',
        'is_cash_advance'     => 'boolean',
        'settled_amount'      => 'integer',
        'refund_amount'       => 'integer',
        'shortfall_amount'    => 'integer',
        'settle_submitted_at' => 'datetime',
        'settle_approved_at'  => 'datetime',
        'period_start'        => 'date',
        'period_end'          => 'date',
    ];

    /**
     * Tipe header milik data Cost Register LAMA (satu PO per header). Expense baru
     * (sejak 2026-11-10) menyimpan type = NULL; kategori biaya diambil dari akun
     * per baris rincian (cost_register_details.cost_category).
     */
    public const TYPES = ['FIXED_COST', 'OVERHEAD'];

    /**
     * Status Expense yang dihitung sebagai biaya PO (Variable/Fixed/Overhead):
     * HANYA setelah dananya benar-benar keluar lewat Treasury (keputusan
     * 2026-11-10, revisi hasil audit alur). Aturan sebelumnya menghitung semua
     * status kecuali DRAFT/REJECTED/CANCELLED, sehingga pengajuan yang baru
     * "Submitted" — belum disetujui CEO — sudah mengubah angka biaya PO dan
     * angkanya bergoyang mengikuti antrean approval.
     *
     * Sejalan dengan kolom Variable Cost PO yang hanya menghitung Cost Request
     * yang sudah cair (Project::variableCostTotal() -> BudgetRequest::scopeDisbursed()).
     *
     *   Non-CA : CLOSED           (QUEUED -> CLOSED saat batch Treasury dibayar).
     *   CA     : PAID dan sesudahnya, termasuk SETTLE_REJECTED (uang sudah keluar,
     *            realisasinya yang masih diperbaiki).
     *
     * Hanya berlaku untuk Expense baru; data Cost Register lama tetap dihitung
     * seperti sebelumnya (semua status, lewat `type` header).
     */
    public const PO_COST_COUNTED_STATUSES = ['PAID', 'SETTLE_SUBMITTED', 'SETTLE_REJECTED', 'SETTLE_APPROVED', 'CLOSED'];

    /** Metode pembebanan multi-periode. */
    public const PERIOD_METHODS = ['equal' => 'Bagi rata', 'manual' => 'Bagi manual'];

    /** Batas jumlah bulan pembebanan satu Expense. */
    public const MAX_PERIODS = 60;

    /**
     * Expense (dulu Cost Register). Alur = alur Cost Payment (Non-Cash Advance) di Cost Request,
     * tanpa tahap Checked:
     *
     *     DRAFT -> VERIFIED -> APPROVED -> QUEUED -> CLOSED
     *
     *   DRAFT     : baru diinput, belum diajukan.
     *   VERIFIED  : diajukan (Draft langsung Verified); label tampilan "Submitted".
     *               Menunggu persetujuan Approver (CEO).
     *   APPROVED  : disetujui; siap dimasukkan ke batch Treasury.
     *   QUEUED    : sudah masuk batch Treasury yang aktif (label "Process").
     *   CLOSED    : batch-nya sudah dibayar.
     *   CANCELLED : dibatalkan sebelum masuk batch.
     *
     * Expense Cash Advance (is_cash_advance, Fase 2) setelah QUEUED:
     *
     *     QUEUED -> PAID -> SETTLE_SUBMITTED -> CLOSED
     *                                       \-> SETTLE_APPROVED -> CLOSED (Second Payment dibayar)
     *
     *   PAID             : batch Treasury dibayar; menunggu Finance Manager input realisasi.
     *   SETTLE_SUBMITTED : realisasi diajukan; menunggu CEO.
     *   SETTLE_REJECTED  : realisasi ditolak CEO; Finance Manager memperbaiki & ajukan ulang.
     *   SETTLE_APPROVED  : disetujui CEO dengan kekurangan bayar; menunggu Second
     *                      Payment dibayar lewat Treasury.
     *
     * Perpindahan status milik CostRegisterController (DRAFT/VERIFIED/APPROVED,
     * SETTLE_*) dan DailyTransactionController (QUEUED/PAID/CLOSED).
     */
    public const STATUSES = ['DRAFT', 'VERIFIED', 'REJECTED', 'APPROVED', 'QUEUED', 'PAID', 'SETTLE_SUBMITTED', 'SETTLE_REJECTED', 'SETTLE_APPROVED', 'CLOSED', 'CANCELLED'];

    /**
     * REJECTED (Fase 3): CEO menolak pengajuan (VERIFIED -> REJECTED, catatan wajib).
     * Finance Manager lalu memilih: Revisi (REJECTED -> DRAFT, bisa diedit & diajukan
     * ulang) atau Batalkan (REJECTED -> CANCELLED) — pola Cost Request.
     */

    /** Status saat Finance Manager boleh mengisi / memperbaiki realisasi Cash Advance. */
    public const SETTLE_INPUT_STATUSES = ['PAID', 'SETTLE_REJECTED'];

    /** Status yang boleh dipilih Treasury saat menyusun batch. */
    public const TREASURY_READY_STATUS = 'APPROVED';

    /**
     * Peran per tahap (dipakai route, controller, dan Blade — satu sumber).
     * Expense hanya melibatkan Finance Manager (input/ajukan) dan CEO/approver
     * (setujui); role finance dicabut sejak 2026-11-10.
     */
    public const SUBMIT_ROLES  = ['superadmin', 'finance_manager'];
    public const APPROVE_ROLES = ['superadmin', 'approver'];
    /** Input realisasi Cash Advance (Finance Manager); approve/tolak settlement = APPROVE_ROLES. */
    public const SETTLE_ROLES  = ['superadmin', 'finance_manager'];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function attachments()
    {
        return $this->hasMany(CostRegisterAttachment::class)->orderBy('id');
    }

    public function inputBy()
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function sourceFund()
    {
        return $this->belongsTo(SourceFund::class);
    }

    public function details()
    {
        return $this->hasMany(CostRegisterDetail::class)->orderBy('id');
    }

    public function logs()
    {
        return $this->hasMany(CostRegisterLog::class)->orderByDesc('id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function settlementDetails()
    {
        return $this->hasMany(CostRegisterSettlementDetail::class)->orderBy('id');
    }

    public function settlementAttachments()
    {
        return $this->hasMany(CostRegisterSettlementAttachment::class)->orderBy('id');
    }

    public function secondPayments()
    {
        return $this->hasMany(CostRegisterSecondPayment::class)->orderBy('id');
    }

    /** Pembebanan per bulan (Fase 3). */
    public function periods()
    {
        return $this->hasMany(CostRegisterPeriod::class)->orderBy('period');
    }

    public function settleSubmittedBy()
    {
        return $this->belongsTo(User::class, 'settle_submitted_by');
    }

    public function settleApprovedBy()
    {
        return $this->belongsTo(User::class, 'settle_approved_by');
    }

    /** Baris batch Treasury yang memuat Cost Register ini. */
    public function dailyTransactionItems()
    {
        return $this->hasMany(DailyTransactionItem::class, 'cost_register_id');
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS
    |--------------------------------------------------------------------------
    */

    public function isDraft(): bool    { return $this->status === 'DRAFT'; }
    public function isVerified(): bool { return $this->status === 'VERIFIED'; }
    public function isApproved(): bool { return $this->status === 'APPROVED'; }
    public function isClosed(): bool   { return $this->status === 'CLOSED'; }
    public function isPaid(): bool     { return $this->status === 'PAID'; }
    public function isSettleSubmitted(): bool { return $this->status === 'SETTLE_SUBMITTED'; }
    public function isRejected(): bool { return $this->status === 'REJECTED'; }

    /** Expense Cash Advance yang realisasinya boleh diisi / diperbaiki Finance Manager. */
    public function canInputSettlement(): bool
    {
        return $this->isCashAdvance() && in_array($this->status, self::SETTLE_INPUT_STATUSES, true);
    }
    public function isCashAdvance(): bool { return (bool) $this->is_cash_advance; }

    /**
     * Rincian yang dihitung sebagai biaya: rincian settlement bila Expense Cash
     * Advance sudah disetujui settlement-nya, selain itu rincian pengajuan.
     */
    public function usesSettlementCost(): bool
    {
        return $this->isCashAdvance() && $this->settle_approved_at !== null;
    }

    /** Hanya DRAFT yang boleh dihapus (Verified/Approved dibatalkan, bukan dihapus). */
    public function isEditable(): bool
    {
        return $this->status === 'DRAFT';
    }

    /**
     * Boleh membuka form edit: Finance Manager (SUBMIT_ROLES) saat DRAFT, atau
     * Superadmin di status APA PUN (keputusan 2026-11-12, termasuk nominal —
     * batch Treasury membaca nominal Expense langsung, jadi perubahan nominal
     * setelah masuk batch/dibayar ikut mengubah angka batch). Expense lama
     * (legacy, tanpa rincian) tetap tidak bisa diedit lewat form.
     */
    public function isEditableBy($user = null): bool
    {
        $user ??= \Illuminate\Support\Facades\Auth::user();
        if (! $user || $this->isLegacy()) {
            return false;
        }

        return ($this->isEditable() && $user->hasRole(self::SUBMIT_ROLES))
            || $user->hasRole(['superadmin']);
    }

    /** Boleh dibatalkan selama belum masuk antrean pembayaran (termasuk setelah ditolak CEO). */
    public function isCancellable(): bool
    {
        return in_array($this->status, ['DRAFT', 'VERIFIED', 'REJECTED', 'APPROVED'], true);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'DRAFT'    => 'secondary',
            'VERIFIED' => 'warning',
            'APPROVED' => 'primary',
            'QUEUED'   => 'info',
            'PAID'     => 'info',
            'SETTLE_SUBMITTED' => 'warning',
            'SETTLE_REJECTED'  => 'danger',
            'SETTLE_APPROVED'  => 'primary',
            'REJECTED'         => 'danger',
            'CLOSED'   => 'success',
            'CANCELLED' => 'danger',
            default    => 'secondary',
        };
    }

    /**
     * Label tampilan. VERIFIED tampil sebagai "Submitted" (Draft langsung Verified,
     * tahap Checked dilewati) dan QUEUED sebagai "Process", seperti Cost Request.
     */
    public static function statusLabelFor(?string $status): string
    {
        return match ($status) {
            'DRAFT'     => 'Draft',
            'VERIFIED'  => 'Submitted',
            'APPROVED'  => 'Approved',
            'QUEUED'    => 'Process',
            'PAID'      => 'Paid',
            'SETTLE_SUBMITTED' => 'Settle Submitted',
            'SETTLE_REJECTED'  => 'Settle Rejected',
            'SETTLE_APPROVED'  => 'Settle Approved',
            'REJECTED'  => 'Rejected',
            'CLOSED'    => 'Closed',
            'CANCELLED' => 'Cancelled',
            default     => $status ?? '-',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabelFor($this->status);
    }

    /** Catat transisi status / catatan ke cost_register_logs (aktor dari user login). */
    public function addLog(?string $from, ?string $to, string $action, ?string $notes = null): void
    {
        $user = \Illuminate\Support\Facades\Auth::user();

        $this->logs()->create([
            'action'      => $action,
            'status_from' => $from,
            'status_to'   => $to,
            'notes'       => $notes,
            'user_id'     => $user?->id,
            'user_name'   => $user?->name ?? 'System',
            'role'        => $user?->role?->name,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS TAMPILAN
    |--------------------------------------------------------------------------
    */

    /** Expense baru (tanpa type header) vs Cost Register lama (satu PO per header). */
    public function isLegacy(): bool
    {
        return $this->type !== null;
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'FIXED_COST' => 'Fixed Cost',
            'OVERHEAD'   => 'Overhead',
            null         => $this->is_cash_advance ? 'Expense CA' : 'Expense',
            default      => $this->type,
        };
    }

    /** Kode tampilan: nomor Expense, atau CREG-xxxxx untuk data lama. */
    public function getDisplayCodeAttribute(): string
    {
        return $this->expense_no
            ?: 'CREG-' . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Ringkasan tujuan biaya: kode PO (data lama / satu PO), "N PO",
     * kode Cost Center, atau gabungan. Memakai relasi details.project &
     * details.costCenter bila sudah di-eager-load.
     */
    public function getTargetLabelAttribute(): string
    {
        if ($this->isLegacy()) {
            return $this->project->code ?? '-';
        }

        // PO langsung + PO terpilih (allocation 'selected'; relasi details.projects bila di-load).
        $po = $this->details->pluck('project.code')
            ->merge($this->details->filter(fn ($d) => $d->allocation === 'selected' && $d->relationLoaded('projects'))
                ->flatMap(fn ($d) => $d->projects->pluck('code')))
            ->filter()->unique()->values();
        $cc = $this->details->pluck('costCenter.code')->filter()->unique()->values();
        $tahunan = $this->details->where('allocation', 'annual')->pluck('allocation_year')->filter()->unique()->sort()->values();

        $bagian = [];
        foreach ($tahunan as $th) {
            $bagian[] = 'Semua PO ' . $th;
        }
        if ($po->count() === 1) {
            $bagian[] = $po->first();
        } elseif ($po->count() > 1) {
            $bagian[] = $po->count() . ' PO';
        }
        if ($cc->count() === 1) {
            $bagian[] = $cc->first();
        } elseif ($cc->count() > 1) {
            $bagian[] = $cc->count() . ' Cost Center';
        }

        return $bagian ? implode(' + ', $bagian) : '-';
    }

    /**
     * Nomor Expense berikutnya: EXP-YYMM-0001, dari penghitung terkunci
     * (DocumentSequence), disemai dari nomor yang sudah terbit bulan ini.
     */
    public static function nextExpenseNo(): string
    {
        $prefix = 'EXP-' . now()->format('ym') . '-';

        /*
        | Semaian = nomor TERAKHIR YANG SUDAH TERBIT (kontrak
        | DocumentSequence::next(), yang mengembalikan semaian + 1), bukan
        | COUNT(*). COUNT(*) hanya kebetulan benar bila nomor yang ada
        | membentuk deret rapat 1..N; begitu ada baris yang hilang (Expense
        | DRAFT boleh dihapus, dan data hasil restore bisa punya nomor tanpa
        | baris penghitung), COUNT(*) memberi nomor yang sudah dipakai dan
        | penyimpanan gagal karena expense_no UNIK. Cermin
        | BudgetRequest::generateCode().
        |
        | Balapan: semaian dipakai sekali saja, saat baris penghitung belum ada;
        | sesudah itu nomor datang dari baris penghitung yang dikunci
        | (lockForUpdate), jadi dua penyimpanan bersamaan tidak bisa kembar.
        */
        $no = \App\Support\DocumentSequence::next(
            'expense:' . $prefix,
            fn () => (int) static::where('expense_no', 'like', $prefix . '%')
                ->whereRaw("RIGHT(expense_no, 4) REGEXP '^[0-9]{4}\$'")
                ->max(\Illuminate\Support\Facades\DB::raw('CAST(RIGHT(expense_no, 4) AS UNSIGNED)')),
        );

        return $prefix . str_pad((string) $no, 4, '0', STR_PAD_LEFT);
    }

    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            'FIXED_COST' => 'warning',
            'OVERHEAD'   => 'info',
            default      => 'secondary',
        };
    }

    /**
     * Label periode: "Nov 2026" (satu bulan / data lama), "Jan 2026 – Mei 2026 (bagi rata)".
     */
    public function getPeriodLabelAttribute(): string
    {
        if ($this->period_start && $this->period_end && ! $this->period_start->equalTo($this->period_end)) {
            return $this->period_start->format('M Y') . ' – ' . $this->period_end->format('M Y')
                . ' (' . strtolower(self::PERIOD_METHODS[$this->period_method] ?? '-') . ')';
        }

        $awal = $this->period_start ?? $this->period;

        return $awal ? $awal->format('M Y') : '-';
    }

    /**
     * Bagi $total ke $n periode sama rata; sisa pembagian (rupiah) ditambahkan satu
     * per satu ke periode awal. Mengembalikan null bila $total < $n (ada periode 0).
     *
     * @return array<int,int>|null
     */
    public static function splitEqual(int $total, int $n): ?array
    {
        if ($n < 1 || $total < $n) {
            return null;
        }
        $dasar = intdiv($total, $n);
        $sisa  = $total - $dasar * $n;

        return array_map(fn ($i) => $dasar + ($i < $sisa ? 1 : 0), range(0, $n - 1));
    }

    /**
     * Skala ulang pembebanan per periode ke total baru, proporsional bobot lama
     * (metode sisa terbesar: jumlah tepat = $total). Dipakai saat settlement Cash
     * Advance disetujui. Periode yang hasilnya 0 dinaikkan ke 1 dengan mengambil dari
     * periode terbesar, selama $total >= jumlah periode; null bila tidak mungkin.
     *
     * @param  array<int,int>  $bobot
     * @return array<int,int>|null
     */
    public static function rescale(array $bobot, int $total): ?array
    {
        $n = count($bobot);
        $jumlah = array_sum($bobot);
        if ($n < 1 || $total < $n || $jumlah <= 0) {
            return null;
        }

        $hasil = []; $pecahan = [];
        foreach (array_values($bobot) as $i => $b) {
            $mentah = $total * ($b / $jumlah);
            $hasil[$i] = (int) floor($mentah);
            $pecahan[$i] = $mentah - $hasil[$i];
        }
        $kurang = $total - array_sum($hasil);
        arsort($pecahan);
        foreach (array_keys($pecahan) as $i) {
            if ($kurang <= 0) break;
            $hasil[$i]++; $kurang--;
        }
        foreach ($hasil as $i => $v) {
            if ($v < 1) {
                $terbesar = array_keys($hasil, max($hasil))[0];
                $hasil[$terbesar]--;
                $hasil[$i] = 1;
            }
        }

        return $hasil;
    }

    public function getAmountFormattedAttribute(): string
    {
        return number_format($this->amount, 0, ',', '.');
    }
}
