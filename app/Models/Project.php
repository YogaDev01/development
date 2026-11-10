<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Project extends Model
{
    protected $table = 'projects';

    /** PL = PO Loan, IL = Invoice Loan, NL = Non Loan */
    public const FINANCING_TYPES = ['PL', 'IL', 'NL'];

    /**
     * Tipe pekerjaan PO. Nullable di basis data — PO lama belum punya nilai
     * dan diisi manual, jadi tiap tampilan harus tahan terhadap null.
     *
     * Kunci di sini HARUS sama persis dengan daftar enum kolom projects.type
     * (lihat migration 2026_09_21_000002). Menambah tipe berarti menambah
     * migration ALTER baru, bukan cuma menambah baris di sini: validasi akan
     * meloloskan nilainya, lalu MySQL menolaknya saat disimpan.
     */
    public const TYPES = [
        'ROLLOUT'     => 'Rollout',
        'MAINTENANCE' => 'Maintenance',
        'DELIVERY'    => 'Delivery',
        'SUPPLY'      => 'Supply',
        'FINANCING'   => 'Financing',
    ];

    protected $fillable = [
        'code',
        'name',
        'po_number',
        'financing',
        'type',
        'value',
        'po_quantity',
        'contract',
        'restitusi',
        'overhead',
        'fixed_cost',
        'term_of_payment',
        'customer_id',
        'input_by',
        'manager_id',
        'status',
        'notes',
        'submitted_at',
        'verified_at',
        'verified_by',
        'approved_at',
        'closed_at',
        'approved_by',
        'closed_by',
        'closing_notes',
        'monitoring_po_received_status',
        'monitoring_bast_status',
        'monitoring_invoice_status',
        'monitoring_payment_status',
    ];

    protected $casts = [
        'value'            => 'integer',
        'po_quantity'      => 'integer',
        'contract'         => 'integer',
        'restitusi'        => 'integer',
        'overhead'         => 'integer',
        'fixed_cost'       => 'integer',
        'submitted_at'     => 'datetime',
        'verified_at'      => 'datetime',
        'approved_at'      => 'datetime',
        'closed_at'        => 'datetime',
    ];

    /**
     * PROGRESS_DONE dan PAID TETAP status header asli — dipakai submitBast()
     * (ACTIVE->PROGRESS_DONE, gerbang billing di InvoiceCashReceiptController),
     * dan Invoice::syncPaymentStatus() (auto-set PAID, gerbang closing di
     * submitClosing()). Cuma BILLED yang dihapus — dicek ulang, tidak ada satu
     * pun kode yang benar-benar men-set status itu, jadi murni status mati.
     * "Billing" versi granular (per No. PO) sekarang dihitung terpisah lewat
     * billingProgressStatus(), TIDAK menggantikan gerbang PROGRESS_DONE di atas.
     */
    public const STATUSES = [
        'DRAFT',
        'SUBMITTED',
        'VERIFIED',
        'EARLY_START',
        'WAITING_PO',
        'ACTIVE',
        'PROGRESS_DONE',
        'PARTIALLY_INVOICED',
        'PARTIALLY_PAID',
        'PAID',
        'CLOSING',
        'CLOSED',
    ];

    /**
     * Status berjalan yang dihitung ulang otomatis dari agregat status No. PO
     * (lihat syncStatusFromPoNumbers()). Di luar daftar ini status tidak
     * disentuh sinkronisasi.
     */
    public const OPERATIONAL_STATUSES = ['ACTIVE', 'PROGRESS_DONE', 'PARTIALLY_INVOICED', 'PARTIALLY_PAID'];

    /**
     * PO yang sudah disetujui (EARLY_START s.d. CLOSED) — hanya PO ini yang menerima
     * pembagian Fixed Cost / Overhead dari Expense (App\Support\ExpenseAllocation,
     * keputusan 2026-11-10).
     */
    public const EXPENSE_ALLOCATION_STATUSES = [
        'EARLY_START', 'WAITING_PO', 'ACTIVE', 'PROGRESS_DONE', 'PARTIALLY_INVOICED',
        'PARTIALLY_PAID', 'PAID', 'CLOSING', 'CLOSED',
    ];

    /**
     * Status Active "lanjutan": PO masih berjalan (belum PAID) tetapi sebagian
     * No. PO sudah ditagih/dibayar. Diperlakukan seperti ACTIVE pada pengecekan
     * yang dulu hanya melihat ACTIVE (budget-able, kunci edit, badge menunggu).
     */
    public const PARTIAL_STATUSES = ['PARTIALLY_INVOICED', 'PARTIALLY_PAID'];

    /**
     * Status PO yang boleh dibuatkan Budget Plan di dalamnya. Sumber tunggal,
     * dipakai BudgetPlanController saat menyusun pilihan PO — supaya "PO mana
     * yang budget-able" tidak bercabang antara model dan controller.
     *
     * EARLY_START ikut: PO yang baru diketahui scope-nya pun sudah dianggap
     * sah dan boleh menampung budget (lihat workflow Early Start).
     */
    public const BUDGETABLE_STATUSES = ['EARLY_START', 'WAITING_PO', 'ACTIVE', 'PARTIALLY_INVOICED', 'PARTIALLY_PAID'];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function inputBy()
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    /**
     * Project Manager PO — sekarang FK langsung ke users (menggantikan tebakan
     * lewat Master PIC position "Project Manager", lihat projectManagers()).
     */
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function details()
    {
        return $this->hasMany(ProjectDetail::class, 'project_id')->orderBy('id');
    }

    public function poNumbers()
    {
        return $this->hasMany(ProjectPoNumber::class, 'project_id')->orderBy('id');
    }

    public function accessGroupLinks()
    {
        return $this->hasMany(AccessGroupDetail::class, 'project_id');
    }

    public function accessGroups()
    {
        return AccessGroup::whereHas('details', fn ($q) => $q->where('project_id', $this->id));
    }

    /*
    |--------------------------------------------------------------------------
    | AGREGAT No. PO — progress kerja / billing / payment dihitung dari
    | status tiap baris project_po_numbers, tidak disimpan sebagai kolom.
    |--------------------------------------------------------------------------
    */

    private function poAggregateStatus(array $doneStatuses): string
    {
        $poNumbers = $this->relationLoaded('poNumbers') ? $this->poNumbers : $this->poNumbers()->get();

        if ($poNumbers->isEmpty()) {
            return 'NOT_STARTED';
        }

        $doneCount = $poNumbers->filter(fn ($p) => in_array($p->status, $doneStatuses, true))->count();

        if ($doneCount === 0) return 'NOT_STARTED';
        if ($doneCount === $poNumbers->count()) return 'DONE';
        return 'PARTIAL';
    }

    /** Progress kerja: berapa No. PO yang sudah BAST/INVOICED/PAID. */
    public function workProgressStatus(): string
    {
        return $this->poAggregateStatus(['BAST_SUBMITTED', 'INVOICED', 'PAID']);
    }

    /** Billing: berapa No. PO yang sudah INVOICED/PAID. */
    public function billingProgressStatus(): string
    {
        return $this->poAggregateStatus(['INVOICED', 'PAID']);
    }

    /** Payment: berapa No. PO yang sudah PAID. */
    public function paymentProgressStatus(): string
    {
        return $this->poAggregateStatus(['PAID']);
    }

    /**
     * Sinkron status header Project berdasarkan agregat status No. PO —
     * dipanggil tiap kali status satu baris project_po_numbers berubah.
     *   ACTIVE -> PROGRESS_DONE otomatis begitu SEMUA No. PO sudah BAST
     *   (PARTIAL belum dihitung "BAST" — lihat workProgressStatus()).
     *   PROGRESS_DONE -> ACTIVE lagi kalau ternyata ada No. PO yang balik
     *   PARTIAL/RELEASED (mis. submit BAST tambahan tanpa "Tandai Lengkap"),
     *   supaya status header tidak pernah "Progress Done" palsu.
     *   PROGRESS_DONE -> PAID otomatis begitu SEMUA No. PO sudah Paid
     *   (dipicu dari Invoice::syncPaymentStatus() saat invoice lunas).
     *   Sebagian No. PO sudah ditagih (belum semua) -> PARTIALLY_INVOICED;
     *   sebagian sudah Paid (belum semua) -> PARTIALLY_PAID. Keduanya berlaku
     *   dari ACTIVE maupun PROGRESS_DONE dan bisa kembali bila agregatnya turun.
     */
    public function syncStatusFromPoNumbers(): void
    {
        if (!in_array($this->status, self::OPERATIONAL_STATUSES, true)) {
            return;
        }

        $dari   = $this->status;
        $target = $this->operationalStatus();

        if ($target === $dari) {
            return;
        }

        $this->update(['status' => $target]);

        [$aksi, $catatan] = match ($target) {
            'PROGRESS_DONE'      => ['bast_submitted', 'Semua No. PO sudah BAST (otomatis).'],
            'ACTIVE'             => ['bast_partial', 'Ada No. PO yang belum BAST lengkap — status dikembalikan ke Active (otomatis).'],
            'PARTIALLY_INVOICED' => ['invoice_partial', 'Sebagian No. PO sudah ditagih (otomatis).'],
            'PARTIALLY_PAID'     => ['payment_partial', 'Sebagian No. PO sudah Paid (otomatis).'],
            default              => ['invoice_paid', 'Semua No. PO sudah Paid (otomatis).'],
        };
        $this->addLog($dari, $target, $aksi, $catatan);
    }

    /**
     * Sudah ada tagihan (invoice tidak Cancelled, nilai > 0) pada salah satu
     * No. PO PO ini. Status No. PO baru INVOICED bila tertagih penuh, jadi
     * penagihan sebagian (mis. 90% sesuai nilai BAST Partial) hanya terlihat dari sini.
     */
    private function hasInvoicedAmount(): bool
    {
        return Invoice::whereIn('project_po_number_id', $this->poNumbers()->select('id'))
            ->where('status', '!=', 'CANCELLED')
            ->where('billed_amount', '>', 0)
            ->exists();
    }

    /**
     * Status berjalan yang seharusnya, dihitung dari agregat status No. PO
     * dan penagihan yang sudah ada. Prioritas: PAID (semua Paid) >
     * PARTIALLY_PAID (ada yang Paid) > PARTIALLY_INVOICED (sudah ada tagihan
     * tapi belum semua No. PO tertagih penuh) > PROGRESS_DONE (semua BAST) > ACTIVE.
     */
    public function operationalStatus(): string
    {
        $work    = $this->workProgressStatus();
        $billing = $this->billingProgressStatus();
        $payment = $this->paymentProgressStatus();

        if ($work === 'DONE' && $payment === 'DONE') {
            return 'PAID';
        }
        if ($payment !== 'NOT_STARTED') {
            return 'PARTIALLY_PAID';
        }
        if ($billing === 'PARTIAL' || ($billing === 'NOT_STARTED' && $this->hasInvoicedAmount())) {
            return 'PARTIALLY_INVOICED';
        }

        return $work === 'DONE' ? 'PROGRESS_DONE' : 'ACTIVE';
    }

    /**
     * Value PO = SUM(project_po_numbers.nominal) — dihitung otomatis, TIDAK
     * lagi diinput manual di header (lihat ProjectPoNumber::booted(), yang
     * memanggil ini setiap kali No. PO ditambah/diubah/dihapus). Kolom
     * `value` tetap dipertahankan sebagai kolom fisik (bukan cuma accessor)
     * supaya query agregat existing yang pakai SUM(value) mentah
     * (FinanceSummaryController, CustomerController) tetap benar tanpa
     * perlu diubah satu-satu.
     */
    public function syncValueFromPoNumbers(): void
    {
        $sum = (int) $this->poNumbers()->sum('nominal');
        if ($sum !== (int) $this->value) {
            $this->update(['value' => $sum]);
        }
    }

    /**
     * "PO Adjustment Required" — PO Quantity (manual, ekspektasi jumlah
     * No. PO) tidak match dengan jumlah baris project_po_numbers yang
     * benar-benar sudah dientri. Tidak mengubah po_quantity/jumlah baris
     * secara otomatis — ini murni alert, penyesuaian tetap manual oleh
     * Finance (lihat dokumen Minor 2.2).
     */
    public function poAdjustmentRequired(): bool
    {
        if (!$this->po_quantity) return false;

        $actual = $this->relationLoaded('poNumbers') ? $this->poNumbers->count() : $this->poNumbers()->count();

        return $actual > 0 && $actual !== (int) $this->po_quantity;
    }

    /**
     * Berapa No. PO yang BAST-nya sudah tuntas tapi belum ditagihkan —
     * yaitu yang menunggu invoice dibuat. PARTIAL tidak ikut: BAST-nya
     * belum dinyatakan lengkap, jadi belum layak ditagih (lihat
     * ProjectPoNumber::STATUSES).
     */
    public function waitingInvoicePoCount(): int
    {
        $poNumbers = $this->relationLoaded('poNumbers') ? $this->poNumbers : $this->poNumbers()->get();

        return $poNumbers->where('status', 'BAST_SUBMITTED')->count();
    }

    public function isWaitingInvoice(): bool
    {
        return $this->waitingInvoicePoCount() > 0;
    }

    /**
     * PO-nya sudah nyata (ada No. PO) tapi belum punya Budget Plan final
     * sepeser pun. Dibatasi ke PO yang masih berjalan — pada PO yang sudah
     * CLOSING/CLOSED peringatan ini tidak lagi bisa ditindaklanjuti.
     */
    public function isWaitingBudget(): bool
    {
        $poNumbers = $this->relationLoaded('poNumbers') ? $this->poNumbers : $this->poNumbers()->get();

        return $poNumbers->isNotEmpty()
            && !in_array($this->status, ['CLOSING', 'CLOSED'], true)
            && $this->budgetPlanTotal() === 0;
    }

    /**
     * PIC (Internal maupun Customer) yang terhubung ke PO ini,
     * diambil dari Master PIC lewat pivot table project_pic_links.
     */
    public function pics()
    {
        return $this->belongsToMany(MasterPic::class, 'project_pic_links', 'project_id', 'master_pic_id')
            ->withTimestamps();
    }

    public function picsInternal()
    {
        return $this->pics()->where('type', 'internal');
    }

    public function picsCustomer()
    {
        return $this->pics()->where('type', 'customer');
    }

    public function attachments()
    {
        return $this->hasMany(ProjectAttachment::class, 'project_id')->orderBy('id');
    }

    public function closingAttachments()
    {
        return $this->hasMany(ProjectAttachment::class, 'project_id')
            ->where('type', 'closing')
            ->orderBy('id');
    }

    public function logs()
    {
        return $this->hasMany(ProjectLog::class, 'project_id')
            ->orderBy('created_at', 'desc');
    }

    public function budgetPlans()
    {
        return $this->hasMany(BudgetPlan::class, 'project_id');
    }

    /** Budget Plan yang dibuat dari form PO (status ikut approval PO). */
    public function bundledBudgetPlans()
    {
        return $this->budgetPlans()->where('via_po_approval', true);
    }

    public function costRegisters()
    {
        return $this->hasMany(CostRegister::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'po_id');
    }

    public function clusters()
    {
        return $this->hasMany(Cluster::class, 'project_id');
    }

    public function monitoringLogs()
    {
        return $this->hasMany(ProjectMonitoringLog::class, 'project_id')->orderBy('created_at');
    }

    /**
     * Persentase progress implementasi (Implementation Status) = jumlah bobot
     * milestone (project_details, dari semua Project/cluster di bawah PO ini)
     * yang berstatus DONE, dibagi total bobot semua milestone yang sudah diisi.
     */
    public function implementationProgress(): float
    {
        $details = $this->details;
        $totalWeight = (int) $details->sum('weight');
        if ($totalWeight <= 0) {
            return 0.0;
        }
        $doneWeight = (int) $details->where('status', 'DONE')->sum('weight');
        return round(($doneWeight / $totalWeight) * 100, 1);
    }

    /**
     * PIC internal dengan jabatan "Project Manager" — LEGACY. Kolom "PM Internal"
     * di semua modul sekarang membaca dari relasi manager() (users). Method ini
     * masih dipakai ReportController untuk memisahkan "PIC lainnya".
     */
    public function projectManagers()
    {
        return $this->picsInternal()->where('position', 'like', '%project manager%');
    }

    /**
     * Nama "PM Internal" (Manager PO) — SATU sumber kebenaran untuk semua modul
     * yang bersandar pada PO. Diambil dari relasi manager() (users).
     */
    public function getPmInternalNameAttribute(): string
    {
        return $this->manager?->username ?: '-';
    }

    /**
     * Sisa nilai kontrak yang belum ditagihkan (dipakai untuk cek flag "Additional" di Invoice).
     * $excludeInvoiceId: kecualikan invoice ini dari hitungan (dipakai saat mengedit invoice
     * itu sendiri, agar nilainya sendiri tidak ikut mengurangi sisa kontraknya sendiri).
     */
    public function remainingContractValue(?int $excludeInvoiceId = null): int
    {
        // Dibandingkan dari billed_amount (nominal setelah persentase penagihan,
        // sebelum PPN) — bukan subtotal — supaya invoice bertermin (1, 2, 3, ...)
        // pada PO yang sama terhitung sisa kontraknya dengan benar.
        $query = $this->invoices()->where('status', '!=', 'CANCELLED');
        if ($excludeInvoiceId) {
            $query->where('id', '!=', $excludeInvoiceId);
        }
        $invoiced = (int) $query->sum('billed_amount');
        return (int) ($this->value ?? 0) - $invoiced;
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS CHECKS
    |--------------------------------------------------------------------------
    */

    public function isDraft(): bool        { return $this->status === 'DRAFT'; }
    public function isSubmitted(): bool    { return $this->status === 'SUBMITTED'; }
    public function isVerified(): bool     { return $this->status === 'VERIFIED'; }
    public function isEarlyStart(): bool   { return $this->status === 'EARLY_START'; }
    public function isWaitingPo(): bool    { return $this->status === 'WAITING_PO'; }
    public function isActive(): bool       { return $this->status === 'ACTIVE'; }
    public function isProgressDone(): bool { return $this->status === 'PROGRESS_DONE'; }
    public function isBilled(): bool       { return $this->status === 'BILLED'; }
    public function isPaid(): bool         { return $this->status === 'PAID'; }
    public function isClosing(): bool      { return $this->status === 'CLOSING'; }
    public function isClosed(): bool       { return $this->status === 'CLOSED'; }

    /** Head bisa edit hanya saat DRAFT */
    /**
     * Peran yang boleh mengedit PO pada status APA PUN — bukan cuma DRAFT/
     * SUBMITTED/WAITING_PO seperti Head & Finance. Ditaruh di model supaya
     * route, controller, dan tombol di daftar PO membaca daftar yang sama;
     * sebelumnya tiap tempat menulis daftar perannya sendiri dan gampang
     * bergeser satu sama lain.
     */
    /**
     * @deprecated Dipertahankan hanya sebagai alias; sumbernya kini
     *             App\Support\AccessRoles::PO_FULL_EDIT.
     */
    public const FULL_EDIT_ROLES = \App\Support\AccessRoles::PO_FULL_EDIT;

    public function isFullyEditableBy($user = null): bool
    {
        $user ??= Auth::user();

        /*
        | Memakai hasRole(), bukan in_array() pada nama mentah.
        |
        | Versi sebelumnya membandingkan $user->role->name secara PERSIS
        | (case-sensitive) dengan daftar huruf kecil. Data peran di basis data
        | ini tidak dijamin seragam kapitalisasinya - RoleDirectory dan
        | User::hasRole() keduanya sengaja menormalkan huruf karena alasan itu.
        | Akibatnya, peran yang tersimpan sebagai "Approver" atau "Superadmin"
        | TIDAK pernah lolos pemeriksaan ini, dan tombol edit penuh hilang tanpa
        | sebab yang kelihatan.
        |
        | hasRole() juga menghormati fitur View As, sama seperti seluruh gerbang
        | peran lain di aplikasi.
        */
        return $user?->hasRole(\App\Support\AccessRoles::PO_FULL_EDIT) ?? false;
    }

    public function isEditableByHead(): bool { return in_array($this->status, ['DRAFT', 'EARLY_START']); }

    /**
     * Status hasil approve Approver bila PO BUKAN Early Start: ACTIVE kalau
     * seluruh nominal No. PO sudah pas dengan nilai kontrak, selain itu
     * WAITING_PO. Dihitung dari jumlah nominal No. PO yang nyata (bukan kolom
     * `value` yang bisa tertinggal), dan ACTIVE hanya bila kontrak > 0 supaya
     * PO tanpa nilai (0 == 0) tidak keliru dianggap lunas-kontrak.
     */
    public function approvalAutoStatus(): string
    {
        $contract = (int) ($this->contract ?? 0);
        $poSum    = (int) $this->poNumbers()->sum('nominal');

        return ($contract > 0 && $poSum === $contract) ? 'ACTIVE' : 'WAITING_PO';
    }

    /** Finance bisa edit saat DRAFT, SUBMITTED, atau WAITING_PO */
    public function isEditableByFinance(): bool
    {
        return in_array($this->status, ['DRAFT', 'SUBMITTED', 'WAITING_PO']);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function getValueFormattedAttribute(): string
    {
        return number_format($this->value ?? 0, 0, ',', '.');
    }

    public function getRestitusiFormattedAttribute(): string
    {
        return number_format($this->restitusi ?? 0, 0, ',', '.');
    }

    public function getContractFormattedAttribute(): string
    {
        return number_format($this->contract ?? 0, 0, ',', '.');
    }

    public function getOverheadFormattedAttribute(): string
    {
        return number_format($this->overhead ?? 0, 0, ',', '.');
    }

    public function getFixedCostFormattedAttribute(): string
    {
        return number_format($this->fixed_cost ?? 0, 0, ',', '.');
    }

    public function getTypeLabelAttribute(): ?string
    {
        return $this->type === null ? null : (self::TYPES[$this->type] ?? $this->type);
    }

    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            'ROLLOUT'     => 'primary',
            'MAINTENANCE' => 'info',
            'DELIVERY'    => 'warning',
            'SUPPLY'      => 'secondary',
            'FINANCING'   => 'success',
            default       => 'secondary',
        };
    }

    /**
     * Total nilai Budget Plan yang terhubung ke PO ini.
     * Hanya budget berstatus ACTIVE atau CLOSED yang dihitung
     * (budget yang masih DRAFT/SUBMITTED/REJECTED belum dianggap final).
     */
    public function budgetPlanTotal(): int
    {
        return (int) $this->budgetPlans
            ->whereIn('status', ['ACTIVE', 'CLOSING', 'CLOSED'])
            ->sum('amount');
    }

    public function grossProfit(): int
    {
        return (int) ($this->value ?? 0) - $this->budgetPlanTotal();
    }

    public function grossMargin(): float
    {
        $value = (int) ($this->value ?? 0);
        return $value > 0 ? round(($this->grossProfit() / $value) * 100, 1) : 0.0;
    }

    /*
    | Cache angka keuangan untuk halaman DAFTAR PO. Tanpa ini setiap baris
    | menembak 4-5 query (CR + realisasi + cash receipt + invoice), jadi 70 PO
    | = ±290 query per buka halaman. Diisi oleh preloadFinancials(); kalau
    | kosong (mis. halaman detail satu PO), method di bawah tetap query sendiri
    | seperti dulu. Isinya disaring dengan kondisi yang SAMA persis dengan
    | query aslinya, jadi angkanya identik.
    */
    private ?\Illuminate\Support\Collection $crCache = null;
    private ?\Illuminate\Support\Collection $receivedByInvoiceCache = null;
    /** @var array<string,int>|null biaya Expense per kategori untuk PO ini (lihat expenseCostByCategory()). */
    private ?array $expenseCostCache = null;
    /** @var array<string,int>|null total Cost Register LAMA per `type` untuk PO ini (lihat legacyCostRegisterTotal()). */
    private ?array $legacyCostCache = null;

    /**
     * Muat CR (+ realisasinya) dan total Cash Receipt per invoice untuk banyak
     * PO sekaligus — 2 query untuk seluruh daftar. Relasi budgetPlans dan
     * invoices harus sudah di-eager-load.
     */
    public static function preloadFinancials(\Illuminate\Support\Collection $projects): void
    {
        if ($projects->isEmpty()) {
            return;
        }

        $crByPlan = BudgetRequest::whereIn('budget_plan_id', $projects->flatMap(fn ($p) => $p->budgetPlans->pluck('id'))->unique()->values())
            ->whereNotIn('status', ['DRAFT', 'REJECTED'])
            ->with(['type', 'realizations'])
            ->get()
            ->groupBy('budget_plan_id');

        $received = CashReceipt::whereIn('invoice_id', $projects->flatMap(fn ($p) => $p->invoices->pluck('id'))->unique()->values())
            ->selectRaw('invoice_id, SUM(amount) as total')
            ->groupBy('invoice_id')
            ->pluck('total', 'invoice_id');

        self::preloadCostRegisterCosts($projects);

        foreach ($projects as $p) {
            $p->crCache = $p->budgetPlans->pluck('id')
                ->flatMap(fn ($id) => $crByPlan->get($id, collect()))
                ->values();
            $p->receivedByInvoiceCache = $received;
        }
    }

    /**
     * Isi cache biaya Expense (per kategori akun) dan Cost Register LAMA (per `type`)
     * untuk banyak PO sekaligus — 4 query untuk seluruh daftar, berapa pun jumlah PO.
     * Dipakai preloadFinancials() dan halaman yang hanya butuh angka Overhead /
     * Fixed Cost tanpa agregat CR & invoice (mis. Finance Summary).
     */
    public static function preloadCostRegisterCosts(\Illuminate\Support\Collection $projects): void
    {
        if ($projects->isEmpty()) {
            return;
        }

        $ids = $projects->pluck('id')->all();

        $expenseByProject = \App\Support\ExpenseAllocation::forProjects($ids);

        $legacyByProject = CostRegister::whereIn('project_id', $ids)
            ->whereNotNull('type')
            ->selectRaw('project_id, type, COALESCE(SUM(amount),0) as total')
            ->groupBy('project_id', 'type')
            ->get()
            ->groupBy('project_id')
            ->map(fn ($baris) => $baris->mapWithKeys(fn ($r) => [$r->type => (int) $r->total])->all());

        foreach ($projects as $p) {
            $p->expenseCostCache = $expenseByProject[$p->id] ?? array_fill_keys(\App\Support\ExpenseAllocation::CATEGORIES, 0);
            $p->legacyCostCache  = $legacyByProject[$p->id] ?? [];
        }
    }

    /**
     * Total nilai Cost Request yang sudah di-commit terhadap Budget Plan PO ini
     * (semua status kecuali DRAFT/REJECTED — CR yang masih draft/ditolak belum
     * mengurangi budget yang tersedia).
     */
    public function costRequestTotal(): int
    {
        if ($this->crCache !== null) {
            return (int) $this->crCache->whereNotIn('status', ['DRAFT', 'REJECTED'])->sum('amount');
        }

        return (int) BudgetRequest::whereIn('budget_plan_id', $this->budgetPlans->pluck('id'))
            ->whereNotIn('status', ['DRAFT', 'REJECTED'])
            ->sum('amount');
    }

    /**
     * Total nilai Cost Request yang sudah benar-benar terpakai (PAID/REALIZED/CLOSED),
     * dipakai untuk hitung Actual Gross Margin.
     */
    public function actualUsedTotal(): int
    {
        if ($this->crCache !== null) {
            return (int) $this->crCache->filter(fn ($c) => $c->isDisbursed())->sum('amount');
        }

        return (int) BudgetRequest::whereIn('budget_plan_id', $this->budgetPlans->pluck('id'))
            ->disbursed()
            ->sum('amount');
    }

    /**
     * Kolom "Cost" pada index PO: total Cost Request yang sudah PAID atau CLOSED
     * (realisasi nyata, bukan sekadar diajukan).
     */
    public function costRealizationTotal(): int
    {
        if ($this->crCache !== null) {
            return (int) $this->crCache->whereIn('status', ['PAID', 'CLOSED'])->sum('amount');
        }

        return (int) BudgetRequest::whereIn('budget_plan_id', $this->budgetPlans->pluck('id'))
            ->whereIn('status', ['PAID', 'CLOSED'])
            ->sum('amount');
    }

    /**
     * Variable Cost atas Cost Request yang sudah jadi biaya nyata
     * (PAID/REALIZED/CLOSED). Nilai per pengajuan mengikuti aturan settlement
     * tunggal di BudgetRequest::realizedCostValue(): Cash Advance + settlement
     * sudah diisi → nilai settlement; selainnya → nilai pengajuan.
     * Dipakai kolom "Variable Cost" index & tab Cost modal.
     */
    public function variableCostTotal(): int
    {
        $crs = $this->crCache !== null
            ? $this->crCache->filter(fn ($c) => $c->isDisbursed())
            : BudgetRequest::whereIn('budget_plan_id', $this->budgetPlans->pluck('id'))
                ->disbursed()
                ->with(['type', 'realizations'])
                ->get();

        // + Expense berkategori Variable Cost yang dialokasikan ke PO ini (2026-11-10).
        return (int) $crs->sum(fn ($c) => $c->realizedCostValue())
            + $this->expenseCostByCategory()['Variable Cost'];
    }

    /**
     * Biaya Expense (modul Expense, dulu Cost Register) yang dibebankan ke PO ini,
     * per kategori akun: ['Variable Cost' => int, 'Fixed Cost' => int, 'Overhead' => int].
     * Satu sumber: App\Support\ExpenseAllocation (PO langsung, PO terpilih, dan
     * pembagian tahunan proporsional nilai PO — dihitung ulang setiap dibaca).
     * Data Cost Register lama TIDAK termasuk di sini — tetap lewat type header.
     */
    public function expenseCostByCategory(): array
    {
        if ($this->expenseCostCache === null) {
            $this->expenseCostCache = \App\Support\ExpenseAllocation::forProjects([$this->id])[$this->id];
        }

        return $this->expenseCostCache;
    }

    /**
     * Total Cost Register LAMA (header `type` FIXED_COST / OVERHEAD) milik PO ini.
     * Perilaku data lama sengaja TIDAK diubah: semua status dihitung (lihat
     * CLAUDE.md §6 dan catatan di CostRegister::PO_COST_COUNTED_STATUSES).
     */
    public function legacyCostRegisterTotal(string $type): int
    {
        if ($this->legacyCostCache === null) {
            $this->legacyCostCache = $this->costRegisters()
                ->whereNotNull('type')
                ->selectRaw('type, COALESCE(SUM(amount),0) as total')
                ->groupBy('type')
                ->pluck('total', 'type')
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        return (int) ($this->legacyCostCache[$type] ?? 0);
    }

    /**
     * Fixed Cost & Overhead PO — SATU SUMBER untuk daftar PO, modal Cost,
     * Finance Summary dan Laporan PO (keputusan 2026-11-10, revisi hasil audit
     * alur Expense). Ketiga komponennya dijumlahkan:
     *   1. kolom manual `projects.fixed_cost` / `projects.overhead` (diisi di form PO,
     *      hasil prorate/alokasi pool di luar sistem);
     *   2. Cost Register LAMA (header `type`) milik PO ini;
     *   3. Expense baru berkategori Fixed Cost / Overhead yang dibebankan ke PO ini
     *      (App\Support\ExpenseAllocation — PO langsung, PO terpilih, pembagian tahunan).
     * Karena dijumlahkan, biaya yang sudah dicatat lewat Expense TIDAK BOLEH diisi
     * ulang di kolom manual PO (akan terhitung dua kali).
     */
    public function fixedCostTotal(): int
    {
        return (int) ($this->fixed_cost ?? 0)
            + $this->legacyCostRegisterTotal('FIXED_COST')
            + $this->expenseCostByCategory()['Fixed Cost'];
    }

    public function overheadCostTotal(): int
    {
        return (int) ($this->overhead ?? 0)
            + $this->legacyCostRegisterTotal('OVERHEAD')
            + $this->expenseCostByCategory()['Overhead'];
    }

    /**
     * Kolom "Payment" pada index PO: total Cash Receipt dari semua Invoice
     * yang terhubung ke PO ini. Invoice berstatus PAID tapi tanpa Cash Receipt
     * (mis. data lama) tetap dihitung penuh sebesar captured_total-nya, supaya
     * Payment tidak pernah tampil 0 untuk invoice yang sudah Paid.
     */
    public function paymentTotal(): int
    {
        $receivedByInvoice = $this->receivedByInvoiceCache
            ?? CashReceipt::whereHas('invoice', fn ($q) => $q->where('po_id', $this->id))
                ->selectRaw('invoice_id, SUM(amount) as total')
                ->groupBy('invoice_id')
                ->pluck('total', 'invoice_id');

        $invoices = $this->receivedByInvoiceCache !== null
            ? $this->invoices
            : $this->invoices()->select('id', 'status', 'billed_amount', 'captured_total')->get();

        $total = 0;

        foreach ($invoices as $invoice) {
            $received = (int) ($receivedByInvoice[$invoice->id] ?? 0);

            if ($invoice->status === 'PAID') {
                // DPP penuh (tanpa PPN) untuk invoice yang sudah lunas.
                $total += $invoice->billed_amount;
                continue;
            }

            // Cash Receipt tercatat termasuk PPN — konversi proporsional ke porsi DPP saja.
            $total += $invoice->captured_total > 0
                ? (int) round($received * $invoice->billed_amount / $invoice->captured_total)
                : $received;
        }

        return $total;
    }

    /**
     * Project Margin (PM): selisih Realization (Cost Request PAID/CLOSED) dengan
     * Budget, sebagai persentase dari total Budget Plan.
     */
    public function projectMargin(): float
    {
        $budget = $this->budgetPlanTotal();
        return $budget > 0 ? round((($budget - $this->costRealizationTotal()) / $budget) * 100, 1) : 0.0;
    }

    /**
     * Actual Gross Margin: nilai PO dikurangi Cost Request yang sudah terpakai,
     * sebagai persentase dari nilai PO.
     */
    public function actualGrossMargin(): float
    {
        $value = (int) ($this->value ?? 0);
        return $value > 0 ? round((($value - $this->actualUsedTotal()) / $value) * 100, 1) : 0.0;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'DRAFT'         => 'secondary',
            'SUBMITTED'     => 'warning',
            'VERIFIED'      => 'info',
            'EARLY_START'   => 'primary',
            'WAITING_PO'    => 'info',
            'ACTIVE'        => 'success',
            'PROGRESS_DONE' => 'warning',
            'PARTIALLY_INVOICED' => 'warning',
            'PARTIALLY_PAID'     => 'info',
            'PAID'          => 'success',
            'CLOSING'       => 'warning',
            'CLOSED'        => 'dark',
            default         => 'secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabelFor($this->status);
    }

    /**
     * Label tampilan untuk sebuah kode status, versi statis — dipakai juga
     * untuk menampilkan transisi status_from/status_to di riwayat log dan
     * oleh App\Support\WorkflowStatus, supaya istilahnya tidak bercabang.
     * Pola yang sama dengan BudgetRequest::statusLabelFor().
     */
    public static function statusLabelFor(?string $status): string
    {
        return match ($status) {
            'DRAFT'         => 'Draft',
            'SUBMITTED'     => 'Submitted',
            'VERIFIED'      => 'Verified',
            'EARLY_START'   => 'Early Start',
            'WAITING_PO'    => 'Waiting PO',
            'ACTIVE'        => 'Active',
            'PROGRESS_DONE' => 'Waiting Invoice',
            'PARTIALLY_INVOICED' => 'Partially Invoiced',
            'PARTIALLY_PAID'     => 'Partially Paid',
            'PAID'          => 'Paid',
            'CLOSING'       => 'Closing',
            'CLOSED'        => 'Closed',
            default         => $status ?? '-',
        };
    }

    /**
     * Label + warna badge untuk 3 indikator progres terhitung
     * (Progress Kerja / Billing / Payment) — dipakai bareng
     * workProgressStatus()/billingProgressStatus()/paymentProgressStatus().
     */
    public static function progressBadge(string $status): array
    {
        return match ($status) {
            'DONE'    => ['label' => 'Done', 'color' => 'success'],
            'PARTIAL' => ['label' => 'Partial', 'color' => 'warning'],
            default   => ['label' => 'Not Started', 'color' => 'secondary'],
        };
    }

    /**
     * Generate kode unik 4 digit zero-padded
     */
    public static function generateNextCode(): string
    {
        $last = static::whereNotNull('code')
            ->orderByRaw('CAST(code AS UNSIGNED) DESC')
            ->value('code');

        $next = $last ? ((int) $last + 1) : 1;
        return str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    public function getFinancingLabelAttribute(): string
    {
        return match ($this->financing) {
            'PL' => 'PO Loan',
            'IL' => 'Invoice Loan',
            'NL' => 'Non Loan',
            default => '-',
        };
    }

    public function getFinancingColorAttribute(): string
    {
        return match ($this->financing) {
            'PL' => 'warning',
            'IL' => 'info',
            'NL' => 'secondary',
            default => 'light',
        };
    }

    public const MONITORING_PO_RECEIVED_STATUSES = ['NOT_YET', 'ON_GOING', 'DONE'];
    public const MONITORING_BAST_STATUSES = ['NOT_YET', 'ON_GOING', 'DONE'];
    public const MONITORING_INVOICE_STATUSES = ['NOT_YET', 'ON_GOING', 'DONE'];
    public const MONITORING_PAYMENT_STATUSES = ['NOT_YET', 'ON_GOING', 'DONE'];

    public function getMonitoringStatusColorAttribute(): array
    {
        $map = fn ($v) => match ($v) {
            'NOT_YET'  => 'secondary',
            'ON_GOING' => 'warning',
            'DONE'     => 'success',
            default    => 'secondary',
        };

        return [
            'po_received' => $map($this->monitoring_po_received_status),
            'bast'        => $map($this->monitoring_bast_status),
            'invoice'     => $map($this->monitoring_invoice_status),
            'payment'     => $map($this->monitoring_payment_status),
        ];
    }

    /** Pemetaan status milestone -> status header (PO Received/BAST/Invoice/Payment). */
    private const DETAIL_TO_MONITORING_STATUS = [
        'NOT_STARTED' => 'NOT_YET',
        'ON_GOING'    => 'ON_GOING',
        'DONE'        => 'DONE',
    ];

    /**
     * Sinkronkan SATU kolom header (monitoring_po_received_status/bast/invoice/
     * payment) berdasarkan status milestone (project_details) yang namanya
     * cocok — dipanggil tiap kali sebuah milestone berubah status, DAN dipakai
     * ulang oleh reconcileMonitoringStatuses() untuk memperbaiki data yang
     * "nyangkut" (mis. milestone-nya sudah DONE tapi kolom header belum
     * ter-update, dari sebelum mekanisme auto-sync ini ada).
     *
     * Return array ['column','label','old','new'] kalau kolom benar-benar
     * berubah (dipakai pemanggil utk menulis log), atau null kalau tidak ada
     * perubahan / nama milestone tidak cocok dengan kolom manapun.
     */
    public function syncMonitoringHeaderStatus(string $stageName, string $detailStatus): ?array
    {
        $newValue = self::DETAIL_TO_MONITORING_STATUS[$detailStatus] ?? 'NOT_YET';
        $name = strtolower($stageName);

        $column = null;
        $label  = null;

        if (str_contains($name, 'po received') || str_contains($name, 'po receive')) {
            $column = 'monitoring_po_received_status';
            $label  = 'PO Received';
        } elseif (str_contains($name, 'bast')) {
            $column = 'monitoring_bast_status';
            $label  = 'BAST';
        } elseif (str_contains($name, 'invoic')) {
            $column = 'monitoring_invoice_status';
            $label  = 'Invoice';
        } elseif (str_contains($name, 'payment') || str_contains($name, 'pembayaran')) {
            $column = 'monitoring_payment_status';
            $label  = 'Payment';
        }

        if (! $column || $this->{$column} === $newValue) {
            return null;
        }

        $old = $this->{$column};
        $this->update([$column => $newValue]);

        return ['column' => $column, 'label' => $label, 'old' => $old, 'new' => $newValue];
    }

    /**
     * Rekonsiliasi status header (PO Received/BAST/Invoice/Payment) dari
     * status milestone SAAT INI — dipanggil di halaman manapun yang menampilkan
     * badge status ini (Project index, Project Monitoring index/show), supaya
     * badge tidak "nyangkut" gara-gara perubahan sebelumnya gagal ter-sync
     * (mis. data lama sebelum fitur auto-sync ini ada). Butuh relasi
     * `details.stage` sudah di-eager-load oleh pemanggil.
     */
    public function reconcileMonitoringStatuses(): void
    {
        foreach ($this->details as $detail) {
            $stageName = $detail->stage->name ?? '';
            if ($stageName === '') continue;

            $this->syncMonitoringHeaderStatus($stageName, $detail->status);
        }
    }

    /**
     * Versi baca-saja dari reconcileMonitoringStatuses().
     *
     * Mengembalikan nilai kolom monitoring seperti SEHARUSNYA menurut status
     * milestone saat ini, tanpa menulis apa pun ke basis data.
     *
     * Dipakai daftar Purchase Order. Versi yang menulis dulu dipanggil di
     * index() — artinya satu permintaan GET biasa (membuka daftar, menyortir
     * kolom, menekan Refresh) mengeluarkan UPDATE untuk setiap baris yang
     * nilainya berbeda: halaman membaca sekaligus mengubah data, tanpa jejak,
     * dan makin berat seiring bertambahnya proyek. Penulisan tetap terjadi di
     * tempat yang semestinya — saat status milestone benar-benar berubah di
     * Project Monitoring.
     *
     * @return array<string, string>  mis. ['monitoring_bast_status' => 'DONE']
     */
    public function monitoringStatusesAsComputed(): array
    {
        $hasil = [];

        foreach ($this->details as $detail) {
            $stageName = strtolower($detail->stage->name ?? '');
            if ($stageName === '') continue;

            $column = match (true) {
                str_contains($stageName, 'po received'), str_contains($stageName, 'po receive') => 'monitoring_po_received_status',
                str_contains($stageName, 'bast')                                                => 'monitoring_bast_status',
                str_contains($stageName, 'invoic')                                              => 'monitoring_invoice_status',
                str_contains($stageName, 'payment'), str_contains($stageName, 'pembayaran')     => 'monitoring_payment_status',
                default                                                                          => null,
            };

            if ($column) {
                $hasil[$column] = self::DETAIL_TO_MONITORING_STATUS[$detail->status] ?? 'NOT_YET';
            }
        }

        return $hasil;
    }

    /**
     * Apakah $user pernah menindak PO ini (ada baris project_logs atas
     * namanya)? Dasar jalur BACA-SAJA detail & lampiran (keputusan
     * 2026-11-06, K7). Tidak pernah dipakai untuk mengizinkan aksi.
     */
    public function wasActedOnBy(?User $user): bool
    {
        if (! $user || ! $this->getKey()) {
            return false;
        }

        return ProjectLog::where('project_id', $this->getKey())
            ->where('user_id', $user->id)
            ->exists();
    }

    public function addLog(
        string $from,
        string $to,
        string $action,
        ?string $notes = null,
        ?array $activityChanges = null
    ): void {
        $user = Auth::user();

        $this->logs()->create([
            'status_from' => $from,
            'status_to'   => $to,
            'action'      => $action,
            'notes'       => $notes,
            'user_id'     => $user?->id,
            'user_name'   => $user?->name ?? 'System',
            'role'        => $user?->role?->name,
        // Konteks organisasi pelaku saat aksi — dibekukan di baris log.
        ] + User::orgContextAttribute($user, 'project_logs'));

        /*
        | Audit trail terpusat (UserActivityLog). Titik tunggal semua transisi
        | status Project/PO. Gagal-aman (record() dibungkus try/catch).
        */
        $labels = [
            'created'           => 'Membuat',
            'edited'            => 'Menyunting',
            'submitted'         => 'Mengajukan',
            'revised'           => 'Merevisi',
            'waiting_to_active' => 'Mengaktifkan',
            'bast_submitted'    => 'Mengajukan BAST',
            'bast_completed'    => 'Menyelesaikan BAST',
            'bast_deleted'      => 'Menghapus BAST',
            'closing_submitted' => 'Mengajukan closing',
            'closing_approved'  => 'Menyetujui closing',
            'closing_rejected'  => 'Menolak closing',
            'finance_closed'    => 'Menutup (Finance)',
            'closed'            => 'Menutup',
        ];
        $actionKey = match ($action) {
            'created'                            => 'create',
            'submitted', 'bast_submitted',
            'closing_submitted'                  => 'submit',
            'closing_approved'                   => 'approve',
            'closing_rejected'                   => 'reject',
            'revised'                            => 'revise',
            'finance_closed', 'closed'           => 'close',
            'waiting_to_active', 'bast_completed' => 'process',
            default                              => 'update',
        };
        $verb = $labels[$action] ?? ucfirst($action);
        UserActivityLog::record(
            'purchase_order',
            $actionKey,
            $verb . ' Purchase Order #' . ($this->code ?? $this->id),
            $this,
            // Saat ada rincian perubahan terstruktur (credential), nilai lama->baru
            // tidak ditaruh di notes (yang non-sensitif), melainkan di $changes.
            $activityChanges ? null : $notes,
            null,
            $activityChanges
        );
    }
}