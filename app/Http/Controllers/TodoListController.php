<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsCrWorklist;
use App\Http\Controllers\Concerns\ScopesBudgetPlanVisibility;
use App\Models\BudgetPlan;
use App\Models\BudgetRequest;
use App\Models\DailyTransaction;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectDetail;
use App\Models\ProjectPoNumber;
use App\Models\RequestStatusLog;
use App\Models\User;
use App\Services\TodoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class TodoListController extends Controller
{
    use BuildsCrWorklist;
    /*
     | Visibilitas Budget Plan memakai aturan yang SAMA dengan halaman
     | Budgeting (scopeVisibleTo): budget proyek dibatasi access group kecuali
     | role privileged, overhead per cost center di access group, payroll per
     | role. Tanpa ini, antrean Budgeting di Todo List menampilkan Budget Plan
     | dari PO yang tidak boleh dilihat user — angka di halaman ini pun jadi
     | berbeda dari daftar di /budget-plan.
     */
    use ScopesBudgetPlanVisibility;

    /** Role yang lihat semua PO tanpa dibatasi Access Group — dipakai section reminder Milestone (sama seperti ProjectMonitoringController::isPrivileged()). */
    private const PROJECT_PRIVILEGED_ROLES = ['superadmin', 'admin', 'approver', 'finance', 'finance_manager', 'cost_control', 'treasury', 'collection', 'accounting', 'guest'];

    /**
     * Todo List Request — worklist lintas modul per role: Cost Request,
     * Budgeting, Treasury (Daily Transaction), Invoice & Cash Receipt, dan
     * reminder Milestone (Project Monitoring, yang tidak punya alur approval
     * jadi bukan worklist approve/reject seperti modul lain). Accounting/
     * Collection belum punya modul nyata untuk di-wire — sengaja tetap kosong
     * (lihat blok "Coming Soon" di bawah).
     */
    public function index()
    {
        /** @var User $user */
        $user = Auth::user();

        try {
            $data = $this->buildWorklistData($user);
        } catch (\Throwable $e) {
            // Jangan sampai satu baris data yang cacat (mis. relasi yatim —
            // project_id/beneficiary_id menunjuk baris yang sudah terhapus)
            // membuat SELURUH halaman gagal render (pernah kejadian: response
            // HTML terpotong di tengah karena fatal error, semua script di
            // bawahnya jadi tidak pernah terkirim ke browser). Kalau ini
            // terjadi, log detailnya lalu tampilkan halaman kosong + banner
            // error yang jelas, bukan halaman putih/rusak.
            \Illuminate\Support\Facades\Log::error('TodoListController::index gagal membangun worklist', [
                'user_id' => $user->id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString(),
            ]);
            $data = $this->emptyWorklistData();
            $data['loadError'] = true;
        }

        // Assignment — penugasan bebas antar-user. Sumber data PERSISTEN
        // pertama di halaman ini (section lain live-query status dokumen), jadi
        // sengaja disusun terpisah lewat AssignmentController, bukan di
        // buildWorklistData(), supaya tidak mengubah logika worklist yang ada.
        try {
            [$assignmentsAssignedToMe, $assignmentsCreatedByMe] =
                app(AssignmentController::class)->listsFor($user);
            $data['assignmentAssignees'] = User::where('status', 'ACTIVE')
                ->where('id', '!=', $user->id)
                ->orderBy('name')
                ->get(['id', 'name']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('TodoListController::index gagal memuat assignment', [
                'user_id' => $user->id, 'error' => $e->getMessage(),
            ]);
            $assignmentsAssignedToMe = collect();
            $assignmentsCreatedByMe  = collect();
            $data['assignmentAssignees'] = collect();
        }
        $data['assignmentsAssignedToMe'] = $assignmentsAssignedToMe;
        $data['assignmentsCreatedByMe']  = $assignmentsCreatedByMe;

        return view('todo-list.index', $data);
    }

    /**
     * Antrean "Perlu Tindakan" untuk blok ringkas di Dashboard.
     *
     * Sengaja memanggil buildWorklistData() yang sama dengan halaman Todo List
     * dan bell navbar: gerbang role, scope Access Group, dan definisi tiap
     * antrean tidak diulang di sini — kalau sebuah koleksi terisi, berarti
     * gerbangnya sudah lolos di sana. Method ini hanya memetakan hasilnya
     * menjadi baris siap tampil (label, jumlah, aging tertua, tujuan).
     *
     * Antrean yang kosong tidak dikembalikan: dashboard hanya menampilkan
     * pekerjaan yang benar-benar ada, bukan daftar nol.
     */
    public function actionQueues(User $user): array
    {
        try {
            // Blok antrean Dashboard hanya butuh jumlah & aging tertua per
            // antrean — bukan rincian anggaran per CoA.
            $data = $this->buildWorklistData($user, denganCoa: false);
        } catch (\Throwable $e) {
            // Sama seperti index(): satu baris data cacat tidak
            // boleh membuat dashboard gagal render seluruhnya.
            \Illuminate\Support\Facades\Log::error('TodoListController::actionQueues gagal', [
                'user_id' => $user->id, 'error' => $e->getMessage(),
            ]);

            return ['queues' => [], 'total' => 0, 'maxAging' => '0 hari', 'completedToday' => 0, 'gagal' => true];
        }

        // MN-03: grup approval kini dua lapis (PO -> Budget Plan -> CR).
        $rata = fn ($grup) => collect($grup)
            ->flatMap(fn ($po) => collect($po['plans'])->flatMap(fn ($g) => $g['items']));

        /*
          Halaman Todo List digerbangi middleware menu:todo-list, sedangkan
          antrean di sini bisa terisi untuk role yang tidak punya akses menu
          itu. Pengecekannya disalin dari CheckMenu (bukan aturan baru) supaya
          baris tanpa akses tampil tanpa tautan, bukan mengirim ke halaman 403.
        */
        $role      = $user->role;
        $bolehTodo = $role && (strtolower($role->name) === 'superadmin' || $role->hasMenu('todo-list'));
        $todoUrl   = $bolehTodo ? route('todo-list.index') : null;
        $antrean   = [];

        /*
          Urutan mengikuti perjalanan dokumen (pengajuan → approval → pembayaran
          → settlement → penutupan), bukan besarnya angka, supaya posisi baris
          tidak berpindah-pindah setiap jumlahnya berubah.
        */
        $peta = [
            ['key' => 'head',            'label' => 'Approval Head',             'icon' => 'fas fa-user-check',       'tone' => 'attention', 'items' => isset($data['sections']['head']) ? $rata($data['sections']['head']['groups']) : collect()],
            ['key' => 'cost_control_checked',    'label' => 'Verifikasi Cost Control',   'icon' => 'fas fa-search-dollar',    'tone' => 'attention', 'items' => isset($data['sections']['cost_control_checked']) ? $rata($data['sections']['cost_control_checked']['groups']) : collect()],
            ['key' => 'ceo',             'label' => 'Approval CEO',              'icon' => 'fas fa-stamp',            'tone' => 'attention', 'items' => isset($data['sections']['ceo']) ? $rata($data['sections']['ceo']['groups']) : collect()],
            ['key' => 'bp-head',         'label' => 'Budgeting — Approval Head', 'icon' => 'fas fa-coins',            'tone' => 'attention', 'items' => $data['budgetingHeadCheck']],
            ['key' => 'bp-verify',       'label' => 'Budgeting — Verifikasi',    'icon' => 'fas fa-coins',            'tone' => 'attention', 'items' => $data['budgetingVerify']],
            ['key' => 'bp-ceo',          'label' => 'Budgeting — Approval CEO',  'icon' => 'fas fa-coins',            'tone' => 'attention', 'items' => $data['budgetingCeoApprove']],
            ['key' => 'bp-closing-due',  'label' => 'Budgeting — Ajukan Closing','icon' => 'fas fa-flag-checkered',   'tone' => 'attention', 'items' => $data['budgetingClosingDue']],
            ['key' => 'bp-rejected',     'label' => 'Budgeting Ditolak',         'icon' => 'fas fa-undo',             'tone' => 'danger',    'items' => $data['budgetingRejected']],
            ['key' => 'reject',          'label' => 'Tindak Lanjut CR Ditolak',  'icon' => 'fas fa-undo',             'tone' => 'danger',    'items' => $data['managerRejected']],
            ['key' => 'batch-create',    'label' => 'Buat Batch Pembayaran',     'icon' => 'fas fa-layer-group',      'tone' => 'progress',  'items' => $data['treasuryBatchCreate']],
            ['key' => 'batch-process',   'label' => 'Proses Batch',              'icon' => 'fas fa-money-check-alt',  'tone' => 'progress',  'items' => $data['treasuryProcess']],
            ['key' => 'batch-pay',       'label' => 'Tandai Batch Paid',         'icon' => 'fas fa-money-bill-wave',  'tone' => 'progress',  'items' => $data['treasuryReadyToPay']],
            ['key' => 'second-payment',  'label' => 'Second Approval',   'icon' => 'fas fa-hand-holding-usd', 'tone' => 'progress',  'items' => $data['secondPaymentPending']],
            ['key' => 'settlement',      'label' => 'Kirim Settlement',          'icon' => 'fas fa-receipt',          'tone' => 'progress',  'items' => $data['managerPaid']],
            ['key' => 'settle-check',    'label' => 'Settlement — Head Check',   'icon' => 'fas fa-clipboard-check',  'tone' => 'progress',  'items' => $rata($data['headSettlementCheck'])],
            ['key' => 'settle-verify',   'label' => 'Settlement — Verifikasi',   'icon' => 'fas fa-clipboard-check',  'tone' => 'progress',  'items' => $rata($data['financeSettlementVerify'])],
            ['key' => 'settle-2nd',      'label' => 'Expense Claim — Second Approve', 'icon' => 'fas fa-hand-holding-usd', 'tone' => 'attention', 'items' => $rata($data['approverSecondApprove'])],
            ['key' => 'settle-close',    'label' => 'Settlement — Penutupan',    'icon' => 'fas fa-lock',             'tone' => 'progress',  'items' => $rata($data['ceoSettlementClose'])],
            ['key' => 'po-submitted',    'label' => 'PO — Approval Finance',     'icon' => 'fas fa-file-signature',   'tone' => 'attention', 'items' => $data['poSubmitted']],
            ['key' => 'collection',      'label' => 'Menunggu Invoice Ditagih',  'icon' => 'fas fa-file-invoice',     'tone' => 'attention', 'items' => $data['collectionWaitingInvoice']],
            ['key' => 'invoice-verify',  'label' => 'Invoice — Verifikasi',      'icon' => 'fas fa-file-invoice',     'tone' => 'attention', 'items' => $data['invoiceVerify']],
            ['key' => 'invoice-approve', 'label' => 'Invoice — Approval',        'icon' => 'fas fa-file-invoice',     'tone' => 'attention', 'items' => $data['invoiceApprove']],
        ];

        foreach ($peta as $baris) {
            $items = collect($baris['items']);
            if ($items->isEmpty()) {
                continue;
            }

            // Item tertua dipilih dari timestamp mentah, bukan hasil diff, agar
            // tidak bergantung pada arah/konvensi tanda method diff Carbon —
            // sama seperti perhitungan $maxAging di buildWorklistData().
            $tertua = $items->sortBy(fn ($i) => $i->aging_since)->first();

            $antrean[] = [
                'key'   => $baris['key'],
                'label' => $baris['label'],
                'icon'  => $baris['icon'],
                'tone'  => $baris['tone'],
                'count' => $items->count(),
                'aging' => $tertua?->aging_label ?? '-',
                'url'   => $todoUrl ? $todoUrl . '#tl-' . $baris['key'] : null,
            ];
        }

        /*
          Reminder Milestone tidak punya alur approval sehingga tidak ikut
          $totalPending di buildWorklistData(). Tetap ditampilkan karena tetap
          menuntut tindakan, tapi memakai overdue_label, bukan aging_label.
        */
        $milestone = collect($data['milestoneOverdue']);
        if ($milestone->isNotEmpty()) {
            $terlama = $milestone->sortByDesc(fn ($m) => $m->overdue_days)->first();
            $antrean[] = [
                'key'   => 'milestone',
                'label' => 'Milestone Lewat Tenggat',
                'icon'  => 'fas fa-bell',
                'tone'  => 'danger',
                'count' => $milestone->count(),
                'aging' => $terlama?->overdue_label ?? '-',
                'url'   => $todoUrl ? $todoUrl . '#tl-milestone' : null,
            ];
        }

        return [
            'queues'         => $antrean,
            'total'          => $data['totalPending'],
            'maxAging'       => $data['maxAging'],
            'completedToday' => $data['completedToday'],
            'gagal'          => false,
        ];
    }

    /** Struktur data kosong untuk todo-list.index — dipakai fallback saat
     *  buildWorklistData() gagal (lihat index()), supaya view tidak
     *  kehilangan variabel yang dibutuhkannya. */
    private function emptyWorklistData(): array
    {
        return [
            'sections' => [], 'managerRejected' => collect(), 'managerPaid' => collect(),
            'headSettlementCheck' => collect(), 'financeSettlementVerify' => collect(),
            'approverSecondApprove' => collect(),
            'ceoSettlementClose' => collect(), 'secondPaymentPending' => collect(),
            'secondPaymentGroups' => collect(),
            'budgetingHeadCheck' => collect(), 'budgetingVerify' => collect(),
            'budgetingCeoApprove' => collect(), 'budgetingRejected' => collect(),
            'budgetingClosingDue' => collect(),
            'treasuryBatchCreate' => collect(), 'treasuryBatchGroups' => collect(),
            'treasuryBatchSecond' => collect(), 'batchSourceFunds' => collect(),
            'treasuryProcess' => collect(), 'treasuryReadyToPay' => collect(),
            'poSubmitted' => collect(),
            'invoiceVerify' => collect(), 'invoiceApprove' => collect(), 'milestoneOverdue' => collect(),
            'collectionWaitingInvoice' => collect(),
            'comingSoonRoles' => [], 'totalPending' => 0, 'maxAging' => '0 hari', 'completedToday' => 0,
            'hasAnyTask' => false, 'hasAnySection' => false,
            // Bentuknya WAJIB sama dengan keluaran buildSummaryTable() —
            // view _summary_task_list membaca modules/rows/moduleTotals/
            // grandTotal. Kalau beda, fallback yang mestinya menyelamatkan
            // halaman justru ikut melempar "Undefined array key".
            'summaryTable' => ['modules' => [], 'rows' => [], 'moduleTotals' => [], 'grandTotal' => 0, 'grandAvgSeconds' => null],
            'respontimeTable' => ['modules' => [], 'rows' => [], 'moduleAvg' => []],
            'allTasks' => collect(),
        ];
    }

    /**
     * Semua data yang dibutuhkan halaman Todo List (index) — satu sumber
     * logika worklist. (Bell "Todo" di navbar dihapus 23 Sep 2026.)
     *
     * @param bool $denganCoa  Sertakan rincian anggaran per Chart of Account.
     *                         Halaman Todo List butuh (modal View Budget);
     *                         actionQueues() tidak — lihat catatan
     *                         di buildStatusGroups().
     */
    private function buildWorklistData(User $user, bool $denganCoa = true): array
    {
        $sections = [];

        // Head — SUBMITTED, grouped per Budget Plan, bulk approve/reject.
        if ($user->hasRole(['superadmin', 'head'])) {
            $sections['head'] = [
                'title'        => 'Menunggu Approval Head',
                'status_label' => 'Submitted',
                'groups'       => $this->buildPoGroups('SUBMITTED', $denganCoa),
                'approve_url'  => route('budget-request.review-bulk-approve'),
                'reject_url'   => route('budget-request.review-bulk-reject'),
            ];
        }

        // Cost Control — verifikasi CHECKED, grouped per Budget Plan, bulk approve/reject.
        if ($user->hasRole(['superadmin', 'finance', 'finance_manager', 'cost_control'])) {
            $sections['cost_control_checked'] = [
                'title'        => 'Menunggu Verifikasi Cost Control',
                'status_label' => 'Checked',
                'groups'       => $this->buildPoGroups('CHECKED', $denganCoa),
                'approve_url'  => route('budget-request.review-bulk-approve'),
                'reject_url'   => route('budget-request.review-bulk-reject'),
            ];
        }

        // CEO / Approver — VERIFIED, grouped per Budget Plan, bulk approve/reject.
        if ($user->hasRole(['superadmin', 'approver', 'guest'])) {
            $sections['ceo'] = [
                'title'        => 'Menunggu Approval CEO',
                'status_label' => 'Verified',
                'groups'       => $this->buildPoGroups('VERIFIED', $denganCoa),
                'approve_url'  => route('budget-request.review-bulk-approve'),
                'reject_url'   => route('budget-request.review-bulk-reject'),
            ];
        }

        // Manager/Supervisor — Reject Handling (Revise/Cancel) + Submit Realization.
        $managerRejected = collect();
        $managerPaid     = collect();
        $budgetPlanRelations = ['budgetPlan.project.customer', 'budgetPlan.costCenter'];

        if ($user->hasRole(['superadmin', 'manager', 'supervisor'])) {
            $rejectedQuery = BudgetRequest::with(array_merge(['type', 'beneficiary', 'statusLogs'], $budgetPlanRelations))
                ->where('status', 'REJECTED');
            $this->scopeByRole($rejectedQuery);
            $managerRejected = $rejectedQuery->latest()->get()
                ->map(function ($cr) {
                    $cr->rejection_log      = $cr->latestRejectionLog();
                    $cr->budget_plan_label  = $this->budgetPlanLabel($cr->budgetPlan);
                    $cr->customer_label     = $this->customerLabel($cr->budgetPlan);
                    $cr->po_label           = $this->poLabel($cr->budgetPlan);
                    return $cr;
                })
                ->sortBy('aging_since')->values();

            // SETTLE_REJECTED ikut daftar yang sama dengan PAID: dua-duanya
            // menunggu pengaju mengisi/mengajukan ulang Settlement lewat form
            // yang sama (realize.blade.php).
            $paidQuery = BudgetRequest::with(array_merge(['type', 'beneficiary', 'statusLogs'], $budgetPlanRelations))
                ->whereIn('status', ['PAID', 'SETTLE_REJECTED']);
            $this->scopeByRole($paidQuery);
            $managerPaid = $paidQuery->latest()->get()
                ->map(function ($cr) {
                    $cr->budget_plan_label = $this->budgetPlanLabel($cr->budgetPlan);
                    $cr->customer_label    = $this->customerLabel($cr->budgetPlan);
                    $cr->po_label          = $this->poLabel($cr->budgetPlan);
                    // Alasan reject terakhir — ditampilkan sebagai badge supaya
                    // manager langsung tahu CR mana yang perlu direvisi.
                    $cr->settle_reject_log = $cr->status === 'SETTLE_REJECTED'
                        ? $cr->statusLogs->firstWhere('action_type', 'settlement_rejected')
                        : null;
                    return $cr;
                })
                ->sortBy('aging_since')->values();
        }

        // Settlement Cash Advance — 3 tahap approval berjenjang, grouped per
        // Budget Plan (pola sama seperti "Verifikasi Cost Control"), dengan kolom
        // Refund = nilai pengajuan (amount) - nilai realisasi (total_realization)
        // dihitung di sini supaya subtotal per grup & grand total tinggal dijumlah
        // di Blade tanpa query ulang. Refund negatif = Settlement melebihi
        // pengajuan (Second Payment otomatis dibuat saat tahap Finance Verify).
        // MN-03: strukturnya kini dua lapis (PO -> Budget Plan -> CR), jadi
        // penelusurannya ikut dua lapis.
        $addRefund = function ($poGroups) {
            foreach ($poGroups as $po) {
                foreach ($po['plans'] as $group) {
                foreach ($group['items'] as $cr) {
                    // Layar approval settlement: yang ditampilkan adalah refund
                    // USULAN (belum settle), sengaja tidak memakai refundAmount()
                    // yang sudah settle-gated (P-01).
                    $refund = $cr->proposedRefundAmount();
                    $cr->proposed_refund           = $refund;
                    $cr->proposed_refund_formatted = number_format($refund, 0, ',', '.');
                }
                }
            }
            return $poGroups;
        };

        // Head — Settlement Check (REALIZED -> SETTLE_CHECKED).
        $headSettlementCheck = collect();
        if ($user->hasRole(['superadmin', 'head'])) {
            $headSettlementCheck = $addRefund($this->buildPoGroups('REALIZED', $denganCoa));
        }

        // Finance/Cost Control — Settlement Verify (SETTLE_CHECKED -> SETTLE_VERIFIED).
        $financeSettlementVerify = collect();
        if ($user->hasRole(['superadmin', 'finance', 'finance_manager', 'cost_control'])) {
            $financeSettlementVerify = $addRefund($this->buildPoGroups('SETTLE_CHECKED', $denganCoa));
        }

        // Finance Manager — Settlement Close (SETTLE_VERIFIED -> CLOSED). Diblokir
        // di backend (ceoCloseSettlement()) selama masih ada Second Payment
        // PENDING — ditandai di sini supaya Blade bisa tampilkan alasannya.
        $ceoSettlementClose = collect();
        if ($user->hasRole(['superadmin', 'finance_manager'])) {
            /*
            | Satu antrean Finance Manager di status SETTLE_VERIFIED, dua aksi:
            |   tanpa Expense Claim -> Close
            |   ada Expense Claim   -> Settle Approve / Reject, lanjut ke
            |                          antrean Second Approve milik Approver
            */
            $ceoSettlementClose = $addRefund($this->buildPoGroups('SETTLE_VERIFIED', $denganCoa));
            foreach ($ceoSettlementClose as $po) {
                foreach ($po['plans'] as $group) {
                    foreach ($group['items'] as $cr) {
                        $cr->pending_second_payment = $cr->pendingSecondPayment();
                        $cr->needs_settle_approve   = $cr->hasExpenseClaim();
                        $cr->expense_claim_fmt      = number_format($cr->expenseClaimAmount(), 0, ',', '.');
                    }
                }
            }
        }

        // Approver — Second Approve Expense Claim (SETTLE_APPROVED ->
        // SECOND_APPROVED, lalu menunggu Treasury membuat batch).
        $approverSecondApprove = collect();
        if ($user->hasRole(['superadmin', 'approver'])) {
            $approverSecondApprove = $addRefund($this->buildPoGroups('SETTLE_APPROVED', $denganCoa));
            foreach ($approverSecondApprove as $po) {
                foreach ($po['plans'] as $group) {
                    foreach ($group['items'] as $cr) {
                        $cr->expense_claim_fmt = number_format($cr->expenseClaimAmount(), 0, ',', '.');
                    }
                }
            }
        }

        /*
        | Treasury/Finance — Second Payment yang belum lunas. Bagian ini
        | INFORMATIF saja: pembayarannya dilakukan lewat batch Treasury
        | (Daily Transaction), bukan dengan menandai PAID dari sini. Tombol
        | lama dihapus karena ia melunasi tanpa meninggalkan baris kas,
        | bukti transfer, maupun jejak di riwayat CR.
        |
        | batch_code diisi supaya terlihat mana yang sudah diambil Treasury
        | dan mana yang masih menunggu dimasukkan ke batch.
        */
        $secondPaymentPending = collect();
        if ($user->hasRole(['superadmin', 'treasury', 'finance', 'finance_manager'])) {
            $secondPaymentPending = BudgetRequest::with([
                    'beneficiary',
                    'statusLogs',
                    'secondPayments.dailyTransactionItems.dailyTransaction',
                ])
                ->whereHas('secondPayments', fn ($q) => $q->where('status', 'PENDING'))
                ->get()
                ->map(function ($cr) {
                    $payment = $cr->secondPayments->firstWhere('status', 'PENDING');
                    $cr->pending_payment = $payment;

                    $activeItem = $payment?->dailyTransactionItems
                        ->first(fn ($i) => $i->status === 'INCLUDED'
                            && in_array($i->dailyTransaction?->status, ['PENDING_APPROVER', 'DONE']));

                    $cr->second_payment_batch      = $activeItem?->dailyTransaction;
                    $cr->second_payment_batch_code = $activeItem?->dailyTransaction?->code;

                    return $cr;
                })
                ->sortBy('aging_since')->values();
        }

        /*
         | Grup PO + rincian Chart of Account untuk section di atas. Dibangun
         | dari koleksi yang BENAR-BENAR tampil (bukan query status terpisah),
         | supaya kolom Request/Approved pada modal "View Budget" mewakili CR
         | yang sama dengan yang terlihat di barisnya.
         */
        $secondPaymentGroups = collect();
        if ($denganCoa && $secondPaymentPending->isNotEmpty()) {
            $this->preloadCoaRows($secondPaymentPending->pluck('budgetPlan'));

            $secondPaymentGroups = $this->groupCrsByPo($secondPaymentPending)
                ->map(function ($poGroup) {
                    $poGroup['plans'] = $poGroup['plans']->map(function ($g) {
                        $bp = $g['items']->first()->budgetPlan;
                        $g['coa_groups'] = $bp
                            ? $this->buildReviewCoaBreakdown($bp, $g['items'], ['SECOND_APPROVED'])
                            : collect();
                        // Dipakai modal untuk mewarnai baris NOB — aturannya sama
                        // dengan buildStatusGroups(): plan dianggap NOB penuh saat
                        // sisa seluruh CoA-nya sudah minus.
                        $g['is_nob_full'] = $g['coa_groups']->sum('remaining') < 0;
                        return $g;
                    });
                    return $poGroup;
                });
        }

        // Budgeting — 4 tahap sama seperti Cost Request: Manager submit -> Head
        // check -> Finance verify -> CEO approve (Active). Flat list (bukan
        // grouped+bulk seperti CR) karena BudgetPlanController cuma punya
        // endpoint single-record per tahap.
        $budgetingHeadCheck = collect();
        if ($user->hasRole(['superadmin', 'head'])) {
            $budgetingHeadCheck = BudgetPlan::with(['project.customer', 'project.manager', 'costCenter'])
                ->tap(fn ($q) => $this->scopeVisibleTo($q))
                ->where('status', 'SUBMITTED')
                ->latest()
                ->get()
                ->map(fn ($bp) => $this->withAging($bp, $bp->updated_at))
                ->sortBy('aging_since')->values();
        }

        $budgetingVerify = collect();
        if ($user->hasRole(['superadmin', 'finance', 'finance_manager'])) {
            $budgetingVerify = BudgetPlan::with(['project.customer', 'project.manager', 'costCenter'])
                ->tap(fn ($q) => $this->scopeVisibleTo($q))
                ->where('status', 'CHECKED')
                ->latest()
                ->get()
                ->map(fn ($bp) => $this->withAging($bp, $bp->updated_at))
                ->sortBy('aging_since')->values();
        }

        $budgetingCeoApprove = collect();
        if ($user->hasRole(['superadmin', 'approver', 'guest'])) {
            $budgetingCeoApprove = BudgetPlan::with(['project.customer', 'project.manager', 'costCenter'])
                ->tap(fn ($q) => $this->scopeVisibleTo($q))
                ->where('status', 'VERIFIED')
                ->latest()
                ->get()
                ->map(fn ($bp) => $this->withAging($bp, $bp->updated_at))
                ->sortBy('aging_since')->values();
        }

        // Manager — Budgeting: Ajukan Closing. Budget yang plan_finish-nya sudah
        // lewat dipindahkan ke CLOSING oleh budget-plan:auto-closing, tapi berkas
        // closing-nya belum diajukan (closing_submitted_at NULL) sehingga tugasnya
        // masih di manager, bukan di Finance.
        $budgetingClosingDue = collect();
        if ($user->hasRole(['superadmin', 'manager'])) {
            $budgetingClosingDue = BudgetPlan::with(['project.customer', 'project.manager', 'costCenter'])
                ->tap(fn ($q) => $this->scopeVisibleTo($q))
                ->where('status', 'CLOSING')
                ->whereNull('closing_submitted_at')
                ->latest()
                ->get()
                ->map(fn ($bp) => $this->withAging($bp, $bp->updated_at))
                ->sortBy('aging_since')->values();
        }

        // Manager — Budgeting: Reject Handling (REJECTED -> DRAFT via Revise).
        $budgetingRejected = collect();
        if ($user->hasRole(['superadmin', 'manager'])) {
            $budgetingRejected = BudgetPlan::with(['project.customer', 'project.manager', 'costCenter'])
                ->tap(fn ($q) => $this->scopeVisibleTo($q))
                ->where('status', 'REJECTED')
                ->latest()
                ->get()
                ->map(fn ($bp) => $this->withAging($bp, $bp->updated_at))
                ->sortBy('aging_since')->values();
        }

        // Treasury — Menunggu Proses (PENDING_APPROVER, ditandai "Submitted" di
        // model -> jadi "Approved/Done" setelah diproses CEO) & Siap Dibayar
        // (DONE, tinggal ditandai Paid oleh Treasury). Flat list per batch —
        // DailyTransactionController juga cuma punya endpoint single-record.
        $treasuryProcess = collect();
        if ($user->hasRole(['superadmin', 'approver', 'guest'])) {
            $treasuryProcess = DailyTransaction::with('sourceFund')->withCount('includedItems')
                ->where('status', 'PENDING_APPROVER')
                ->where('type', 'CASH_OUT')
                ->latest()
                ->get()
                ->map(fn ($batch) => $this->withAging($batch, $batch->created_at))
                ->sortBy('aging_since')->values();
        }

        $treasuryReadyToPay = collect();
        if ($user->hasRole(['superadmin', 'treasury', 'finance', 'finance_manager'])) {
            $treasuryReadyToPay = DailyTransaction::with('sourceFund')->withCount('includedItems')
                ->where('status', 'DONE')
                ->where('type', 'CASH_OUT')
                ->latest()
                ->get()
                ->map(fn ($batch) => $this->withAging($batch, $batch->approved_at ?? $batch->created_at))
                ->sortBy('aging_since')->values();
        }

        // Treasury — Buat Batch Pembayaran: CR APPROVED (sudah lolos CEO) yang
        // belum masuk batch aktif manapun, tapi TIDAK muncul sebagai task
        // actionable di worklist manapun di atas (hanya kelihatan di Summary
        // Task sebagai angka, lihat buildSummaryTable()) — akibatnya Treasury
        // tidak punya pengingat nyata untuk mulai membuat batch. Query sama
        // persis dengan DailyTransactionController::create() supaya jumlahnya
        // konsisten dengan CR yang benar-benar muncul di form pembuatan batch.
        $treasuryBatchCreate = collect();
        if ($user->hasRole(['superadmin', 'treasury', 'finance', 'finance_manager'])) {
            $batchCreateQuery = BudgetRequest::with(['type', 'beneficiary', 'statusLogs', 'budgetPlan.project.customer', 'budgetPlan.project.manager', 'budgetPlan.costCenter'])
                ->where('status', 'APPROVED')
                ->whereDoesntHave('dailyTransactionItems', fn ($q) =>
                    $q->where('status', 'INCLUDED')
                      ->whereHas('dailyTransaction', fn ($q2) =>
                          $q2->whereIn('status', ['PENDING_APPROVER', 'DONE'])
                      )
                );
            $this->scopeByRole($batchCreateQuery);
            $treasuryBatchCreate = $batchCreateQuery->latest()->get()
                ->map(function ($cr) {
                    $cr->budget_plan_label = $this->budgetPlanLabel($cr->budgetPlan);
                    $cr->customer_label    = $this->customerLabel($cr->budgetPlan);
                    $cr->po_label          = $this->poLabel($cr->budgetPlan);
                    return $cr;
                })
                ->sortBy('aging_since')->values();
        }

        /*
         | Todo #5 — batch pembayaran bisa dibuat langsung dari Todo List,
         | tidak perlu pindah ke halaman Treasury. Rekeningnya dibatasi sama
         | persis dengan DailyTransactionController::create(): hanya rekening
         | operasional yang AKTIF. Main Account sengaja TIDAK ikut — itu khusus
         | sumber dana Top Up, dan storeCashOut() memang menolaknya.
         |
         | Daftar ini hanya perlu disiapkan kalau bagian "Buat Batch" memang
         | tampil untuk user ini.
         */
        /*
         | Daftar yang sama, dikelompokkan per PO untuk dirender (lihat
         | groupCrsByPo()). Koleksi datarnya TETAP dipakai untuk jumlah &
         | subtotal section supaya kedua angka itu tidak pernah dihitung dari
         | himpunan yang berbeda dengan yang tampil.
         */
        $treasuryBatchGroups = $this->groupCrsByPo($treasuryBatchCreate);

        /*
         | Second Payment yang sudah lolos Second Approve ikut antre di sini,
         | BUKAN hanya di section informatif "Second Payment — Menunggu
         | Pembayaran". Sesudah disetujui ia adalah kewajiban bayar yang
         | menunggu Treasury persis seperti CR APPROVED; kalau ia tidak muncul
         | di daftar "Buat Batch", Treasury harus ingat sendiri untuk pindah ke
         | halaman Daily Transaction demi menemukannya.
         |
         | Sumber kelayakannya satu: BudgetRequestSecondPayment::availableForBatch()
         | — scope yang sama dipakai DailyTransactionController saat menyusun
         | form batch dan saat memvalidasi id yang dikirim, jadi apa yang
         | tampil di sini pasti diterima store().
         */
        $treasuryBatchSecond = collect();
        if ($user->hasRole(['superadmin', 'treasury', 'finance', 'finance_manager'])) {
            $secondQuery = \App\Models\BudgetRequestSecondPayment::availableForBatch()
                ->with(['budgetRequest.beneficiary', 'budgetRequest.budgetPlan.project.customer', 'budgetRequest.budgetPlan.project.manager', 'budgetRequest.budgetPlan.costCenter'])
                ->whereHas('budgetRequest', function ($q) {
                    $this->scopeByRole($q);
                });

            $treasuryBatchSecond = $secondQuery->latest()->get()
                ->filter(fn ($sp) => $sp->budgetRequest !== null)
                ->map(function ($sp) {
                    $cr = $sp->budgetRequest;
                    $sp->cr_code     = $cr->code;
                    $sp->cr_desc     = $cr->description;
                    $sp->beneficiary_label = $cr->beneficiary?->nickname ?? '-';
                    $sp->po_label    = $this->poLabel($cr->budgetPlan);
                    return $sp;
                })
                ->values();
        }

        $batchSourceFunds = ($treasuryBatchCreate->isNotEmpty() || $treasuryBatchSecond->isNotEmpty())
            ? \App\Models\SourceFund::with('bank')
                ->where('status', 'ACTIVE')
                ->operational()
                ->orderBy('name')
                ->get()
            : collect();

        /*
         | Purchase Order — Menunggu Verifikasi Finance (SUBMITTED -> VERIFIED
         | lewat ProjectController::verify(), atau balik ke DRAFT lewat
         | financeRevise()). Langkah approve berikutnya (VERIFIED -> Early Start
         | / Waiting PO / Active) ada di tangan Approver.
         |
         | Definisi "siapa yang menangani PO SUBMITTED" sudah ada di
         | TodoService::build() (dipakai widget Todo di Dashboard): role
         | superadmin/finance/finance_manager, link ke project.index?open=<id>.
         | Role & tautannya disamakan persis dengan itu supaya Dashboard dan
         | halaman Todo List tidak pernah menampilkan daftar yang berbeda.
         |
         | Ini reminder + link-out, bukan approve/reject inline seperti section
         | Cost Request: keputusan verify/revise tidak bisa diambil tanpa melihat
         | isi PO-nya, jadi tombolnya membuka PO itu sendiri.
         */
        $poSubmitted = collect();
        if ($user->hasRole(['superadmin', 'finance', 'finance_manager'])) {
            $poSubmitted = Project::with(['customer', 'manager'])
                ->withCount('poNumbers')
                ->where('status', 'SUBMITTED')
                ->latest()
                ->get()
                ->map(fn ($po) => $this->withAging($po, $po->submitted_at ?? $po->updated_at))
                ->sortBy('aging_since')->values();
        }

        // Invoice & Cash Receipt — Menunggu Verifikasi (SUBMITTED -> VERIFIED)
        // & Menunggu Approval (VERIFIED -> APPROVED). Kedua tahap milik Finance
        // Manager; lihat catatan di $invoiceApprove.
        $invoiceVerify = collect();
        if ($user->hasRole(['superadmin', 'finance_manager'])) {
            $invoiceVerify = Invoice::with(['po.customer'])
                ->where('status', 'SUBMITTED')
                ->latest()
                ->get()
                ->map(fn ($inv) => $this->withAging($inv, $inv->submitted_at ?? $inv->updated_at))
                ->sortBy('aging_since')->values();
        }

        /*
         | Approval invoice (VERIFIED -> APPROVED) milik FINANCE MANAGER, bukan
         | Approver.
         |
         | Alur yang berlaku memang sudah begitu — lihat catatan di atas route
         | invoice-cash-receipt.verify: "collection submit -> accounting
         | verifikasi -> finance_manager approve". Yang tertinggal hanya
         | gerbang di sini, sehingga tugasnya muncul di Todo List Approver
         | sementara Finance Manager yang seharusnya mengerjakannya tidak
         | pernah melihatnya sama sekali.
         |
         | `guest` ikut lepas: di controller ini guest selalu berpasangan
         | dengan approver sebagai peran pengintip, jadi ia mengikuti ke mana
         | pun tugasnya berada.
         */
        $invoiceApprove = collect();
        if ($user->hasRole(['superadmin', 'finance_manager'])) {
            $invoiceApprove = Invoice::with(['po.customer'])
                ->where('status', 'VERIFIED')
                ->latest()
                ->get()
                ->map(fn ($inv) => $this->withAging($inv, $inv->verified_at ?? $inv->updated_at))
                ->sortBy('aging_since')->values();
        }

        // Collection — Waiting Invoice: No. PO yang BAST-nya sudah di-upload
        // (PARTIAL/BAST_SUBMITTED, sama seperti daftar pilihan No. PO di form
        // "Buat Invoice" — lihat InvoiceCashReceiptController::INVOICEABLE_PO_STATUSES)
        // tapi masih ada sisa nominal yang belum ditagihkan sama sekali. Ini
        // reminder buat Collection segera buat invoice, bukan approval — jadi
        // flat list, link-out ke form Buat Invoice, tanpa aksi approve/reject.
        $collectionWaitingInvoice = collect();
        if ($user->hasRole(['superadmin', 'collection'])) {
            $collectionWaitingInvoice = ProjectPoNumber::with(['project.customer'])
                ->whereIn('status', ['PARTIAL', 'BAST_SUBMITTED'])
                ->latest('bast_submitted_at')
                ->get()
                ->map(fn ($po) => $this->withAging($po, $po->bast_submitted_at ?? $po->updated_at))
                ->sortBy('aging_since')->values();
        }

        // Project Monitoring — TIDAK punya alur approval sama sekali (manager
        // bebas ubah status milestone kapan saja, lihat komentar di
        // ProjectMonitoringController::setMilestoneStatus()). Jadi ini bukan
        // worklist approve/reject seperti section lain, melainkan REMINDER buat
        // Manager (PM Internal) — yang bertanggung jawab update status milestone
        // — bukan Approver/CEO yang sama sekali tidak menyentuh update-nya.
        // Milestone yang masih NOT_STARTED/ON_GOING tapi sudah lewat target
        // selesai (plan_end). Scoping akses PO-nya meniru
        // ProjectMonitoringController::accessibleProjectIds().
        $milestoneOverdue = collect();
        if ($user->hasRole(['superadmin', 'manager'])) {
            $milestoneProjectIds = $user->hasRole(self::PROJECT_PRIVILEGED_ROLES)
                ? null
                : $user->accessibleProjectIds()->all();

            $milestoneOverdue = ProjectDetail::with(['project.customer', 'stage'])
                ->whereIn('status', ['NOT_STARTED', 'ON_GOING'])
                ->whereNotNull('plan_end')
                ->where('plan_end', '<', today())
                ->when(is_array($milestoneProjectIds), fn ($q) => $q->whereHas('project', fn ($p) => $p->whereIn('id', $milestoneProjectIds)))
                ->get()
                ->map(function ($detail) {
                    $overdueDays = (int) $detail->plan_end->diffInDays(today());
                    $detail->overdue_days  = $overdueDays;
                    $detail->overdue_label = $overdueDays . ' hari';
                    return $detail;
                })
                ->sortByDesc('overdue_days')
                ->values();
        }

        // Accounting — belum ada modul nyata yang di-wire. Collection sudah
        // di-wire ke section Waiting Invoice di atas.
        $comingSoonRoles = [];
        if ($user->hasRole(['superadmin', 'accounting'])) $comingSoonRoles[] = 'Accounting';

        // Semua task yang tampil di halaman ini (flat), dipakai untuk kartu
        // ringkasan (Max Aging) & export — union dari seluruh section grouped +
        // flat di atas. Milestone reminder SENGAJA tidak ikut union ini — bukan
        // "task menunggu tindakan" (approval), jadi tidak realistis dibandingkan
        // aging-nya dengan CR/Batch/Invoice yang punya tenggat approval nyata.
        // MN-03: grouping kini dua lapis (PO -> Budget Plan -> CR), jadi
        // perataan ke daftar datar ikut menembus dua lapis. Kalau tidak, kartu
        // "Max Aging" dan ekspor akan kehilangan seluruh task approval.
        $flattenPoGroups = fn ($poGroups) => $poGroups
            ->flatMap(fn ($po) => $po['plans']->flatMap(fn ($g) => $g['items']));

        $allTasks = collect($sections)->flatMap(fn ($s) => $flattenPoGroups($s['groups']))
            ->concat($managerRejected)->concat($managerPaid)
            ->concat($flattenPoGroups($headSettlementCheck))
            ->concat($flattenPoGroups($financeSettlementVerify))
            ->concat($flattenPoGroups($approverSecondApprove))
            ->concat($flattenPoGroups($ceoSettlementClose))
            ->concat($secondPaymentPending)
            ->concat($budgetingHeadCheck)->concat($budgetingVerify)
            ->concat($budgetingCeoApprove)->concat($budgetingRejected)
            ->concat($budgetingClosingDue)
            ->concat($treasuryBatchCreate)
            ->concat($treasuryProcess)->concat($treasuryReadyToPay)
            ->concat($poSubmitted)
            ->concat($invoiceVerify)->concat($invoiceApprove)
            ->concat($collectionWaitingInvoice);

        $totalPending = $allTasks->count();

        // CR yang paling lama berada di status-nya sekarang (aging_since paling
        // awal — BUKAN created_at, supaya CR yang baru saja pindah status tidak
        // ikut dianggap "lama" hanya karena diajukan lama). Diurutkan dari
        // timestamp mentah (bukan hasil diffInMinutes) supaya tidak tergantung
        // arah/konvensi tanda dari method diff Carbon; label-nya diambil dari
        // aging_label (accessor model) yang otomatis turun ke jam/menit kalau
        // belum sampai 1 hari.
        $oldestTask = $allTasks->sortBy(fn ($cr) => $cr->aging_since)->first();
        $maxAging   = $oldestTask ? $oldestTask->aging_label : '0 hari';

        // "Completed Today" — jumlah transisi status CR yang benar-benar dieksekusi
        // user ini hari ini (approve/reject/revise/cancel/close/dst), dari log audit
        // yang sudah ada (request_status_logs) — tanpa perlu tabel/counter baru.
        $completedToday = RequestStatusLog::where('acted_by', $user->id)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        $hasAnyTask = $totalPending > 0 || $milestoneOverdue->isNotEmpty();
        $hasAnySection = !empty($sections) || $managerRejected->isNotEmpty() || $managerPaid->isNotEmpty()
            || $headSettlementCheck->isNotEmpty() || $financeSettlementVerify->isNotEmpty()
            || $approverSecondApprove->isNotEmpty()
            || $ceoSettlementClose->isNotEmpty() || $secondPaymentPending->isNotEmpty()
            || !empty($comingSoonRoles)
            || $budgetingHeadCheck->isNotEmpty() || $budgetingVerify->isNotEmpty()
            || $budgetingCeoApprove->isNotEmpty() || $budgetingRejected->isNotEmpty()
            || $budgetingClosingDue->isNotEmpty()
            || $treasuryBatchCreate->isNotEmpty()
            || $treasuryProcess->isNotEmpty() || $treasuryReadyToPay->isNotEmpty()
            || $poSubmitted->isNotEmpty()
            || $invoiceVerify->isNotEmpty() || $invoiceApprove->isNotEmpty() || $milestoneOverdue->isNotEmpty()
            || $collectionWaitingInvoice->isNotEmpty();

        $summaryTable    = $this->buildSummaryTable();
        $respontimeTable = $this->buildRespontimeTable();

        return compact(
            'sections', 'managerRejected', 'managerPaid',
            'headSettlementCheck', 'financeSettlementVerify', 'approverSecondApprove',
            'ceoSettlementClose', 'secondPaymentPending', 'secondPaymentGroups',
            'budgetingHeadCheck', 'budgetingVerify', 'budgetingCeoApprove', 'budgetingRejected',
            'budgetingClosingDue',
            'treasuryBatchCreate', 'treasuryBatchGroups', 'treasuryBatchSecond', 'batchSourceFunds', 'treasuryProcess', 'treasuryReadyToPay', 'poSubmitted',
            'invoiceVerify', 'invoiceApprove', 'milestoneOverdue', 'collectionWaitingInvoice',
            'comingSoonRoles', 'totalPending', 'maxAging', 'completedToday', 'hasAnyTask', 'hasAnySection',
            'summaryTable', 'respontimeTable', 'allTasks'
        );
    }

    /**
     * Tempel aging_since/aging_days/aging_label sebagai atribut dinamis ke
     * model $row, dihitung dari timestamp $since (fallback ke now() kalau
     * null) — meniru pola BudgetRequest::getAgingLabelAttribute() tapi untuk
     * model yang tidak punya accessor aging bawaan (BudgetPlan, DailyTransaction,
     * Invoice). Dipakai supaya baris section baru bisa ikut union $allTasks
     * (butuh aging_since) & ditampilkan aging_label di Blade, konsisten
     * dengan section Cost Request.
     */
    private function withAging($row, $since)
    {
        $since = $since ?? now();
        $days  = (int) floor($since->diffInDays(now()));

        if ($days >= 1) {
            $label = $days . ' hari';
        } else {
            $hours = (int) floor($since->diffInHours(now()));
            $label = $hours >= 1 ? $hours . ' jam' : (int) floor($since->diffInMinutes(now())) . ' menit';
        }

        $row->aging_since = $since;
        $row->aging_days  = $days;
        $row->aging_label = $label;

        return $row;
    }

    /**
     * Matrix "Summary Task": baris = TAHAP alur kerja (Role + nama tahap),
     * kolom = MODUL. Satu tahap hanya punya angka pada modul tempat ia hidup;
     * sel modul lain sengaja dibiarkan kosong, bukan nol, supaya "tidak
     * berlaku" tidak terbaca sebagai "tidak ada antrean".
     *
     * Tahap dengan Role + nama yang sama pada modul berbeda (mis. "Approval
     * Head" di Budgeting dan Cost Request) DIGABUNG jadi satu baris — di situlah
     * bentuk matrix ini berguna: beban satu peran pada tahap yang sama bisa
     * dibandingkan antar modul dengan menyusuri satu baris.
     *
     * Catatan angka: Cost Request di-scope lewat scopeByRole() (mengikuti
     * hak akses access group user, sama seperti section-section di halaman
     * ini). Modul lain dihitung global karena memang tidak punya konsep
     * access-group per PO pada tahap yang dihitung di sini.
     */
    /**
     * GET /todo-list/summary-partial — matrix Summary Task sebagai HTML partial.
     * Dipakai modal lazy-load di halaman Profil. Data sama dengan modal Summary
     * Task di halaman Todo List (buildSummaryTable()).
     */
    public function summaryPartial()
    {
        return view('todo-list._summary_task_list', [
            'summaryTable' => $this->buildSummaryTable(),
        ]);
    }

    private function buildSummaryTable(): array
    {
        $crCount = function (string $status): int {
            $query = BudgetRequest::where('status', $status);
            $this->scopeByRole($query);
            return $query->count();
        };

        /*
         | Tautan angka Cost Request pada Summary Task.
         |
         | $crCount() dihitung LINTAS TIPE (Cash Advance + Cost Payment) dan
         | TANPA batas tanggal, sementara halaman Cost Request default membuka
         | tab Cash Advance dengan rentang bulan berjalan dan menyaring status
         | hanya pada tab aktif. Akibatnya angka yang diklik sering mendarat di
         | daftar kosong (datanya Cost Payment, atau di luar bulan ini).
         |
         | Supaya daftar yang muncul persis sejumlah angkanya: arahkan ke tab
         | Database (memuat kedua tipe), saring lewat ?statuses= (column filter
         | yang berlaku di tab mana pun), dan lebarkan rentang tanggalnya.
         */
        $crLink = fn (string $status): string => url('/budget-request?tab=db&statuses=' . $status . '&all=1');

        // Angka Budgeting ikut aturan visibilitas yang sama dengan antrean di
        // atas — kalau tidak, ringkasan menghitung Budget Plan yang barisnya
        // sendiri tidak pernah tampil untuk user ini.
        $bpCount = function (callable $filter): int {
            $query = BudgetPlan::query()->tap(fn ($q) => $this->scopeVisibleTo($q));
            $filter($query);
            return $query->count();
        };

        $modules = ['Purchase Order', 'Budgeting', 'Cost Request', 'Treasury', 'Invoice & Cash Receipt'];

        /*
         | Rata-rata waktu per tahap ("Avg Time"), kolom paling kanan — berapa
         | lama dokumen biasanya menunggu di tahap ini sebelum di-aksi oleh
         | role yang bersangkutan.
         |
         | Sumbernya status_from pada tabel log transisi: baris log dengan
         | status_from = X berarti dokumen itu BERPINDAH KELUAR dari status X —
         | selisihnya dengan log SEBELUMNYA pada dokumen yang sama (LAG) adalah
         | lama dokumen itu DIAM di status X, yaitu persis tahap yang sedang
         | dihitung jumlahnya pada baris Summary Task ini.
         |
         | Treasury & Invoice tidak punya tabel log transisi — dipakai pasangan
         | kolom timestamp yang sudah ada (created_at/approved_at/paid_at,
         | submitted_at/verified_at/approved_at) yang persis menandai tahap yang
         | sama dengan hitungan barisnya.
         |
         | Tahap yang tidak punya jejak waktu yang bisa dipercaya (mis. "Ajukan
         | Closing" Budgeting — tidak ada log saat PO/BP MULAI layak diajukan
         | closing, hanya log SAAT diajukan) sengaja dibiarkan null → tampil
         | "-", daripada menebak.
         */
        $statusFromAvg = function (string $table, string $fk) {
            $rows = \DB::select("
                SELECT status_from AS st, SUM(diff) AS s, COUNT(*) AS n FROM (
                    SELECT status_from,
                           TIMESTAMPDIFF(SECOND,
                               LAG(created_at) OVER (PARTITION BY {$fk} ORDER BY created_at, id),
                               created_at) AS diff
                    FROM {$table}
                ) t
                WHERE diff IS NOT NULL AND diff >= 0
                GROUP BY status_from
            ");
            $map = [];
            foreach ($rows as $r) {
                $map[$r->st] = ['sum' => (int) $r->s, 'count' => (int) $r->n];
            }
            return $map;
        };

        // Aggregate tunggal (bukan per status) untuk pasangan timestamp Treasury/Invoice.
        $pairAvg = function (string $table, string $fromCol, string $toCol, string $where = '') {
            $row = \DB::selectOne("
                SELECT SUM(TIMESTAMPDIFF(SECOND, {$fromCol}, {$toCol})) AS s, COUNT(*) AS n
                FROM {$table}
                WHERE {$toCol} IS NOT NULL AND {$fromCol} IS NOT NULL
                  AND TIMESTAMPDIFF(SECOND, {$fromCol}, {$toCol}) >= 0 {$where}
            ");
            return ($row && $row->n > 0) ? ['sum' => (int) $row->s, 'count' => (int) $row->n] : null;
        };

        $poAvg = $statusFromAvg('project_logs', 'project_id');
        $bpAvg = $statusFromAvg('budget_plan_logs', 'budget_plan_id');
        $crAvg = $statusFromAvg('request_status_logs', 'budget_request_id');

        $treasuryApproveAvg = $pairAvg('daily_transactions', 'created_at', 'approved_at', "AND type = 'CASH_OUT'");
        $treasuryPaidAvg    = $pairAvg('daily_transactions', 'approved_at', 'paid_at',    "AND type = 'CASH_OUT'");
        $invoiceVerifyAvg   = $pairAvg('invoices', 'submitted_at', 'verified_at');
        $invoiceApproveAvg  = $pairAvg('invoices', 'verified_at', 'approved_at');

        /*
         | Katalog tahap, ditulis mengikuti urutan alur kerja perusahaan —
         | dari pembuatan dokumen, approval, pembayaran, penagihan, sampai
         | settlement & closing. Urutan baris pada tabel mengikuti urutan
         | kemunculan pertama di daftar ini.
         |
         | [role, tahap, modul, jumlah, url, avg(['sum'=>detik,'count'=>n]|null)]
         */
        $stages = [
            // ---- Purchase Order
            ['Head',            'Submit (Draft)',          'Purchase Order', Project::where('status', 'DRAFT')->count(),        url('/project?status=DRAFT'),      $poAvg['DRAFT'] ?? null],
            ['Finance',         'Approval Finance',        'Purchase Order', Project::where('status', 'SUBMITTED')->count(),    url('/project?status=SUBMITTED'),  $poAvg['SUBMITTED'] ?? null],
            ['Finance',         'Aktivasi PO',             'Purchase Order', Project::where('status', 'WAITING_PO')->count(),   url('/project?status=WAITING_PO'), $poAvg['WAITING_PO'] ?? null],

            // ---- Budgeting
            ['Manager',         'Submit (Draft)',          'Budgeting',      $bpCount(fn ($q) => $q->where('status', 'DRAFT')),     url('/budget-plan'), $bpAvg['DRAFT'] ?? null],
            ['Head',            'Approval Head',           'Budgeting',      $bpCount(fn ($q) => $q->where('status', 'SUBMITTED')), url('/budget-plan'), $bpAvg['SUBMITTED'] ?? null],
            ['Cost Control',    'Verifikasi Cost Control', 'Budgeting',      $bpCount(fn ($q) => $q->where('status', 'CHECKED')),   url('/budget-plan'), $bpAvg['CHECKED'] ?? null],
            ['CEO',             'Approval CEO',            'Budgeting',      $bpCount(fn ($q) => $q->where('status', 'VERIFIED')),  url('/budget-plan'), $bpAvg['VERIFIED'] ?? null],

            // ---- Cost Request
            ['Manager',         'Submit (Draft)',          'Cost Request',   $crCount('DRAFT'),     $crLink('DRAFT'),     $crAvg['DRAFT'] ?? null],
            ['Head',            'Approval Head',           'Cost Request',   $crCount('SUBMITTED'), $crLink('SUBMITTED'), $crAvg['SUBMITTED'] ?? null],
            ['Cost Control',    'Verifikasi Cost Control', 'Cost Request',   $crCount('CHECKED'),   $crLink('CHECKED'),   $crAvg['CHECKED'] ?? null],
            ['CEO',             'Approval CEO',            'Cost Request',   $crCount('VERIFIED'),  $crLink('VERIFIED'),  $crAvg['VERIFIED'] ?? null],

            // ---- Treasury (pembayaran CR yang sudah lolos approval)
            ['Treasury',        'Buat Batch Pembayaran',   'Treasury',       $crCount('APPROVED'),  $crLink('APPROVED'),  $crAvg['APPROVED'] ?? null],
            ['CEO',             'Approval Batch',          'Treasury',       DailyTransaction::where('status', 'PENDING_APPROVER')->where('type', 'CASH_OUT')->count(), url('/daily-transaction'), $treasuryApproveAvg],
            ['Treasury',        'Tandai Paid',             'Treasury',       DailyTransaction::where('status', 'DONE')->where('type', 'CASH_OUT')->count(),             url('/daily-transaction'), $treasuryPaidAvg],

            // ---- Invoice & Cash Receipt
            ['Collection',      'Buat Invoice',            'Invoice & Cash Receipt', ProjectPoNumber::whereIn('status', ['PARTIAL', 'BAST_SUBMITTED'])->count(), url('/invoice-cash-receipt'), null],
            ['Finance Manager', 'Verifikasi Invoice',      'Invoice & Cash Receipt', Invoice::where('status', 'SUBMITTED')->count(), url('/invoice-cash-receipt?status=SUBMITTED'), $invoiceVerifyAvg],
            ['Finance Manager', 'Approval Invoice',        'Invoice & Cash Receipt', Invoice::where('status', 'VERIFIED')->count(),  url('/invoice-cash-receipt?status=VERIFIED'),  $invoiceApproveAvg],
            ['Collection',      'Catat Pembayaran',        'Invoice & Cash Receipt', Invoice::whereIn('status', Invoice::UNPAID_STATUSES)->count(), url('/invoice-cash-receipt?statuses=' . implode(',', Invoice::UNPAID_STATUSES)), null],

            // ---- Settlement (Cost Request) & Closing
            ['Manager',         'Settlement',              'Cost Request',   $crCount('PAID'),            $crLink('PAID'),            $crAvg['PAID'] ?? null],
            ['Manager',         'Settlement Ditolak',      'Cost Request',   $crCount('SETTLE_REJECTED'), $crLink('SETTLE_REJECTED'), $crAvg['SETTLE_REJECTED'] ?? null],
            ['Head',            'Settlement Check',        'Cost Request',   $crCount('REALIZED'),        $crLink('REALIZED'),        $crAvg['REALIZED'] ?? null],
            ['Cost Control',    'Settlement Verify',       'Cost Request',   $crCount('SETTLE_CHECKED'),  $crLink('SETTLE_CHECKED'),  $crAvg['SETTLE_CHECKED'] ?? null],
            ['Finance Manager', 'Settlement Close',        'Cost Request',   $crCount('SETTLE_VERIFIED'), $crLink('SETTLE_VERIFIED'), $crAvg['SETTLE_VERIFIED'] ?? null],

            ['Head',            'Submit Closing',          'Purchase Order', Project::where('status', 'PAID')->count(),     url('/project?status=PAID'),    $poAvg['PAID'] ?? null],
            ['Finance',         'Verifikasi Closing',      'Purchase Order', Project::where('status', 'CLOSING')->count(),  url('/project?status=CLOSING'), $poAvg['CLOSING'] ?? null],
            ['Manager',         'Ajukan Closing',          'Budgeting',      $bpCount(fn ($q) => $q->where('status', 'CLOSING')->whereNull('closing_submitted_at')),    url('/budget-plan'), null],
            ['Finance',         'Verifikasi Closing',      'Budgeting',      $bpCount(fn ($q) => $q->where('status', 'CLOSING')->whereNotNull('closing_submitted_at')), url('/budget-plan'), $bpAvg['CLOSING'] ?? null],
        ];

        $rows = [];
        foreach ($stages as [$role, $label, $module, $count, $url, $avg]) {
            $key = $role . '|' . $label;

            if (!isset($rows[$key])) {
                $rows[$key] = [
                    'role'     => $role,
                    'label'    => $label,
                    'cells'    => array_fill_keys($modules, null),
                    'total'    => 0,
                    'avg_sum'   => 0,
                    'avg_count' => 0,
                ];
            }

            // avg_sum/avg_count ikut disimpan PER SEL (bukan cuma di-total ke level
            // baris) supaya filter Modul di sisi klien bisa menghitung ulang rata-
            // rata tertimbang hanya dari modul yang sedang terlihat — sama seperti
            // kolom Total.
            $rows[$key]['cells'][$module] = [
                'count'     => (int) $count,
                'url'       => $url,
                'avg_sum'   => $avg['sum'] ?? 0,
                'avg_count' => $avg['count'] ?? 0,
            ];
            $rows[$key]['total'] += (int) $count;

            // Baris gabungan (role+tahap yang sama muncul di beberapa modul, mis.
            // "Head / Approval Head" pada Budgeting & Cost Request) menjumlahkan
            // avg dari SEMUA modul penyusunnya — hasilnya rata-rata tertimbang
            // (bobot = jumlah transisi), bukan rata-rata dari rata-rata.
            if ($avg) {
                $rows[$key]['avg_sum']   += $avg['sum'];
                $rows[$key]['avg_count'] += $avg['count'];
            }
        }

        $rows = array_values($rows);

        $moduleTotals = array_fill_keys($modules, 0);
        $grandAvgSum = 0; $grandAvgCount = 0;
        foreach ($rows as &$row) {
            foreach ($row['cells'] as $module => $cell) {
                if ($cell) $moduleTotals[$module] += $cell['count'];
            }
            $row['avg_seconds'] = $row['avg_count'] > 0 ? (int) round($row['avg_sum'] / $row['avg_count']) : null;
            $grandAvgSum   += $row['avg_sum'];
            $grandAvgCount += $row['avg_count'];
        }
        unset($row);

        return [
            'modules'      => $modules,
            'rows'         => $rows,
            'moduleTotals' => $moduleTotals,
            'grandTotal'   => array_sum($moduleTotals),
            'grandAvgSeconds' => $grandAvgCount > 0 ? (int) round($grandAvgSum / $grandAvgCount) : null,
        ];
    }

    /** Format durasi detik jadi ringkas (dtk/mnt/jam/hari) untuk tampilan Summary. */
    public static function formatDuration(?int $seconds): ?string
    {
        if ($seconds === null) return null;
        if ($seconds < 60) return $seconds . ' dtk';
        $m = intdiv($seconds, 60);
        if ($m < 60) return $m . ' mnt';
        $h = intdiv($m, 60); $mm = $m % 60;
        if ($h < 24) return $h . ' j' . ($mm ? ' ' . $mm . ' m' : '');
        $d = intdiv($h, 24); $hh = $h % 24;
        return $d . ' hr' . ($hh ? ' ' . $hh . ' j' : '');
    }

    /**
     * Matrix "Summary Respontime": baris = USER, kolom = MODUL, sel = rata-rata
     * waktu respon (detik) + jumlah aksi.
     *
     * Definisi response time = lama user menanggapi satu dokumen di tahapnya:
     * jarak waktu sejak dokumen MASUK ke tahap itu (timestamp aksi/transisi
     * SEBELUMNYA) sampai user mengaksikannya (timestamp aksi user). Durasi
     * dihubungkan ke PELAKU aksi tersebut — orang yang "merespon".
     *
     * Sumber per modul:
     *  - Purchase Order  : project_logs       (transisi berurut per project)
     *  - Budgeting       : budget_plan_logs   (transisi berurut per budget plan)
     *  - Cost Request    : request_status_logs(transisi berurut per CR)
     *  - Treasury        : daily_transactions (created→approved oleh approver,
     *                       approved→paid oleh treasury)
     *  - Invoice & Cash Receipt : invoices     (submitted→verified→approved→
     *                       sent→received, tiap tahap ke pelakunya)
     *
     * Dihitung MENYELURUH (bukan di-scope role): ini ringkasan kinerja lintas
     * user untuk manajemen, bukan antrean milik user tertentu.
     */
    private function buildRespontimeTable(): array
    {
        $modules = ['Purchase Order', 'Budgeting', 'Cost Request', 'Treasury', 'Invoice & Cash Receipt'];

        // $agg[uid][module] = ['sum' => detik, 'count' => n]
        $agg = [];
        $add = function (int $uid, string $module, $sumSeconds, $count) use (&$agg) {
            $sumSeconds = (int) $sumSeconds;
            $count      = (int) $count;
            if (! $uid || $count <= 0) return;
            $agg[$uid] ??= [];
            $agg[$uid][$module] ??= ['sum' => 0, 'count' => 0];
            $agg[$uid][$module]['sum']   += $sumSeconds;
            $agg[$uid][$module]['count'] += $count;
        };

        /*
         | Tabel log transisi (project_logs/budget_plan_logs/request_status_logs):
         | selisih waktu tiap baris dengan baris SEBELUMNYA pada dokumen yang
         | sama (LAG), lalu dijumlah per pelaku. Baris pertama tiap dokumen
         | (LAG null) dan selisih negatif (anomali data) diabaikan.
         */
        $logStats = function (string $table, string $fk, string $actorCol) {
            return \DB::select("
                SELECT uid, SUM(diff) AS s, COUNT(*) AS n FROM (
                    SELECT {$actorCol} AS uid,
                           TIMESTAMPDIFF(SECOND,
                               LAG(created_at) OVER (PARTITION BY {$fk} ORDER BY created_at, id),
                               created_at) AS diff
                    FROM {$table}
                ) t
                WHERE diff IS NOT NULL AND diff >= 0 AND uid IS NOT NULL
                GROUP BY uid
            ");
        };

        foreach ($logStats('project_logs', 'project_id', 'user_id') as $r) {
            $add((int) $r->uid, 'Purchase Order', $r->s, $r->n);
        }
        foreach ($logStats('budget_plan_logs', 'budget_plan_id', 'user_id') as $r) {
            $add((int) $r->uid, 'Budgeting', $r->s, $r->n);
        }
        foreach ($logStats('request_status_logs', 'budget_request_id', 'acted_by') as $r) {
            $add((int) $r->uid, 'Cost Request', $r->s, $r->n);
        }

        /*
         | Treasury: dua tahap bertimestamp pada daily_transactions.
         |   dibuat → disetujui  (pelaku approved_by)
         |   disetujui → dibayar (pelaku paid_by)
         */
        $treasury = \DB::select("
            SELECT uid, SUM(diff) AS s, COUNT(*) AS n FROM (
                SELECT approved_by AS uid, TIMESTAMPDIFF(SECOND, created_at, approved_at) AS diff
                    FROM daily_transactions WHERE approved_at IS NOT NULL AND approved_by IS NOT NULL
                UNION ALL
                SELECT paid_by AS uid, TIMESTAMPDIFF(SECOND, approved_at, paid_at) AS diff
                    FROM daily_transactions WHERE paid_at IS NOT NULL AND approved_at IS NOT NULL AND paid_by IS NOT NULL
            ) t
            WHERE diff IS NOT NULL AND diff >= 0 AND uid IS NOT NULL
            GROUP BY uid
        ");
        foreach ($treasury as $r) {
            $add((int) $r->uid, 'Treasury', $r->s, $r->n);
        }

        /*
         | Invoice & Cash Receipt: rantai timestamp pada invoices, tiap tahap
         | dihubungkan ke pelakunya.
         */
        $invoice = \DB::select("
            SELECT uid, SUM(diff) AS s, COUNT(*) AS n FROM (
                SELECT verified_by AS uid, TIMESTAMPDIFF(SECOND, submitted_at, verified_at) AS diff
                    FROM invoices WHERE verified_at IS NOT NULL AND submitted_at IS NOT NULL AND verified_by IS NOT NULL
                UNION ALL
                SELECT approved_by AS uid, TIMESTAMPDIFF(SECOND, verified_at, approved_at) AS diff
                    FROM invoices WHERE approved_at IS NOT NULL AND verified_at IS NOT NULL AND approved_by IS NOT NULL
                UNION ALL
                SELECT sent_by AS uid, TIMESTAMPDIFF(SECOND, approved_at, sent_at) AS diff
                    FROM invoices WHERE sent_at IS NOT NULL AND approved_at IS NOT NULL AND sent_by IS NOT NULL
                UNION ALL
                SELECT received_by AS uid, TIMESTAMPDIFF(SECOND, sent_at, received_at) AS diff
                    FROM invoices WHERE received_at IS NOT NULL AND sent_at IS NOT NULL AND received_by IS NOT NULL
            ) t
            WHERE diff IS NOT NULL AND diff >= 0 AND uid IS NOT NULL
            GROUP BY uid
        ");
        foreach ($invoice as $r) {
            $add((int) $r->uid, 'Invoice & Cash Receipt', $r->s, $r->n);
        }

        if (empty($agg)) {
            return ['modules' => $modules, 'rows' => [], 'moduleAvg' => array_fill_keys($modules, null)];
        }

        // Nama user (sekali query untuk semua uid yang terpakai).
        $names = User::whereIn('id', array_keys($agg))->pluck('name', 'id');

        $rows = [];
        foreach ($agg as $uid => $cells) {
            $out       = array_fill_keys($modules, null);
            $totalSum  = 0;
            $totalCnt  = 0;
            foreach ($modules as $m) {
                if (empty($cells[$m])) continue;
                $sum = (int) $cells[$m]['sum'];
                $cnt = (int) $cells[$m]['count'];
                $out[$m]   = ['avg_seconds' => (int) round($sum / max(1, $cnt)), 'count' => $cnt];
                $totalSum += $sum;
                $totalCnt += $cnt;
            }
            $rows[] = [
                'user'        => $names[$uid] ?? ('User #' . $uid),
                'cells'       => $out,
                'avg_seconds' => $totalCnt ? (int) round($totalSum / $totalCnt) : null,
                'count'       => $totalCnt,
            ];
        }

        // Paling banyak merespon di atas — daftar kinerja yang paling ramai dulu.
        usort($rows, fn ($a, $b) => $b['count'] <=> $a['count'] ?: strcmp($a['user'], $b['user']));

        // Rata-rata per modul (gabungan semua user) untuk baris footer.
        $moduleAvg = [];
        foreach ($modules as $m) {
            $s = 0; $n = 0;
            foreach ($agg as $cells) {
                if (empty($cells[$m])) continue;
                $s += (int) $cells[$m]['sum'];
                $n += (int) $cells[$m]['count'];
            }
            $moduleAvg[$m] = $n ? ['avg_seconds' => (int) round($s / $n), 'count' => $n] : null;
        }

        return [
            'modules'   => $modules,
            'rows'      => $rows,
            'moduleAvg' => $moduleAvg,
        ];
    }

    /**
     * Section Todo List => route aksi satuan [approve, reject]. Bulk memanggil
     * ulang aksi satuan itu per baris, jadi seluruh aturan (cek status, lock,
     * log, notifikasi) tetap satu sumber di controller modulnya masing-masing.
     */
    private const BULK_ROUTES = [
        'settle-check'   => ['budget-request.head-check-settlement', 'budget-request.head-reject-settlement'],
        'settle-verify'  => ['budget-request.finance-verify-settlement', 'budget-request.finance-reject-settlement'],
        'settle-2nd'     => ['budget-request.second-approve', 'budget-request.second-reject'],
        'bp-head'        => ['budget-plan.head-approve', 'budget-plan.head-reject'],
        'bp-verify'      => ['budget-plan.finance-verify', 'budget-plan.finance-reject'],
        'bp-ceo'         => ['budget-plan.ceo-approve', 'budget-plan.ceo-reject'],
        'batch-process'  => ['daily-transaction.approve', 'daily-transaction.reject-batch'],
        'invoice-verify' => ['invoice-cash-receipt.verify', null],
        'invoice-approve'=> ['invoice-cash-receipt.approve', null],
    ];

    /**
     * GET /todo-list/has-pending — dipanggil berkala oleh badge sidebar.
     * Memakai TodoService::build() yang sama dengan widget Todo di Dashboard
     * supaya badge tidak pernah menyala untuk antrean yang beda dengan yang
     * sungguhan dilihat user saat membuka halaman ini.
     */
    public function hasPending()
    {
        /** @var User $user */
        $user = Auth::user();

        return response()->json([
            'has_pending' => count(TodoService::build($user)) > 0,
        ]);
    }

    /** POST /todo-list/bulk/{section}/{mode} — multi-select approve/reject. */
    public function bulk(Request $request, string $section, string $mode)
    {
        $routeName = self::BULK_ROUTES[$section][$mode === 'reject' ? 1 : 0] ?? null;
        abort_unless($routeName && in_array($mode, ['approve', 'reject'], true), 404);

        if ($mode === 'reject') {
            $request->validate(['notes' => 'required|string|max:500']);
        }

        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName($routeName);
        abort_unless($route, 404);

        // Middleware role: milik route aslinya ikut ditegakkan di sini, karena
        // action dipanggil langsung (tidak lewat router).
        /** @var User $user */
        $user = Auth::user();
        foreach ($route->gatherMiddleware() as $mw) {
            if (is_string($mw) && str_starts_with($mw, 'role:')
                && !$user->hasRole(explode(',', substr($mw, 5)))) {
                abort(403, 'Anda tidak memiliki wewenang untuk aksi ini.');
            }
        }

        [$controllerClass, $method] = explode('@', $route->getActionName());
        $controller = app($controllerClass);

        $ok = 0;
        $errors = [];
        foreach (array_unique((array) $request->input('ids', [])) as $id) {
            session()->forget(['success', 'error']);
            try {
                app()->call([$controller, $method], ['id' => $id]);
                if ($err = session('error')) {
                    $errors[] = '#' . $id . ': ' . $err;
                } else {
                    $ok++;
                }
            } catch (\Illuminate\Validation\ValidationException $e) {
                $errors[] = '#' . $id . ': ' . collect($e->errors())->flatten()->first();
            } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
                $errors[] = '#' . $id . ': ' . ($e->getMessage() ?: 'Akses ditolak.');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('TodoList bulk gagal', [
                    'section' => $section, 'mode' => $mode, 'id' => $id, 'error' => $e->getMessage(),
                ]);
                $errors[] = '#' . $id . ': terjadi kesalahan sistem.';
            }
        }
        session()->forget(['success', 'error']);

        $redirect = redirect()->route('todo-list.index');
        $verb = $mode === 'reject' ? 'ditolak' : 'diproses';
        if ($ok) {
            $redirect->with('success', $ok . ' item berhasil ' . $verb . '.');
        }
        if ($errors) {
            $redirect->with('error', count($errors) . ' item dilewati: ' . implode(' | ', array_slice($errors, 0, 5))
                . (count($errors) > 5 ? ' ...' : ''));
        }

        return $redirect;
    }

    /**
     * GET /todo-list/export — export Excel dari seluruh task yang tampil untuk
     * user yang login saat ini (union semua section, sama seperti index()).
     */
    public function exportList(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        /*
         | Export mengikuti pencarian/penyaringan yang sedang aktif di halaman:
         | browser mengirim id CR dari baris yang masih terlihat (lihat
         | public/js/tesys-export-filtered.js). Penyaringan dilakukan SETELAH
         | union antar-section dirakit, supaya gerbang peran tiap section tetap
         | berlaku — daftar id dari browser tidak bisa dipakai untuk menarik CR
         | yang memang tidak boleh dilihat user ini.
         */
        $ids = collect((array) $request->input('ids', []))
            ->filter(fn ($v) => is_numeric($v))
            ->map(fn ($v) => (int) $v);

        $rows = collect();

        $stageOf = function (BudgetRequest $cr): string {
            return match ($cr->status) {
                'SUBMITTED'        => 'Menunggu Approval Head',
                'CHECKED'          => 'Menunggu Verifikasi Cost Control',
                'VERIFIED'         => 'Menunggu Approval CEO',
                'REJECTED'         => 'Reject Handling',
                'PAID'             => 'Submit Settlement',
                'SETTLE_REJECTED'  => 'Submit Settlement (Revisi)',
                'REALIZED'         => 'Settlement — Head Check',
                'SETTLE_CHECKED'   => 'Settlement — Finance Verify',
                'SETTLE_VERIFIED'  => 'Settlement — Finance Manager Close',
                default            => $cr->status,
            };
        };

        foreach (['SUBMITTED', 'CHECKED', 'VERIFIED'] as $status) {
            $allowed = match ($status) {
                'SUBMITTED' => $user->hasRole(['superadmin', 'head']),
                'CHECKED'   => $user->hasRole(['superadmin', 'finance', 'finance_manager', 'cost_control']),
                'VERIFIED'  => $user->hasRole(['superadmin', 'approver', 'guest']),
            };
            if (!$allowed) continue;

            $query = BudgetRequest::with(['type', 'beneficiary', 'budgetPlan.project.manager', 'budgetPlan.costCenter', 'statusLogs'])
                ->where('status', $status);
            $this->scopeByRole($query);
            $rows = $rows->concat($query->get());
        }

        if ($user->hasRole(['superadmin', 'manager', 'supervisor'])) {
            $q1 = BudgetRequest::with(['type', 'beneficiary', 'budgetPlan.project.manager', 'budgetPlan.costCenter', 'statusLogs'])->where('status', 'REJECTED');
            $this->scopeByRole($q1);
            $rows = $rows->concat($q1->get());

            $q2 = BudgetRequest::with(['type', 'beneficiary', 'budgetPlan.project.manager', 'budgetPlan.costCenter', 'statusLogs'])
                ->whereIn('status', ['PAID', 'SETTLE_REJECTED']);
            $this->scopeByRole($q2);
            $rows = $rows->concat($q2->get());
        }

        if ($user->hasRole(['superadmin', 'head'])) {
            $q3 = BudgetRequest::with(['type', 'beneficiary', 'budgetPlan.project.manager', 'budgetPlan.costCenter', 'statusLogs'])->where('status', 'REALIZED');
            $this->scopeByRole($q3);
            $rows = $rows->concat($q3->get());
        }

        if ($user->hasRole(['superadmin', 'finance', 'finance_manager', 'cost_control'])) {
            $q4 = BudgetRequest::with(['type', 'beneficiary', 'budgetPlan.project.manager', 'budgetPlan.costCenter', 'statusLogs'])->where('status', 'SETTLE_CHECKED');
            $this->scopeByRole($q4);
            $rows = $rows->concat($q4->get());
        }

        if ($user->hasRole(['superadmin', 'approver', 'guest'])) {
            $q5 = BudgetRequest::with(['type', 'beneficiary', 'budgetPlan.project.manager', 'budgetPlan.costCenter', 'statusLogs'])->where('status', 'SETTLE_VERIFIED');
            $this->scopeByRole($q5);
            $rows = $rows->concat($q5->get());
        }

        // Penyaringan dilakukan di sini — setelah seluruh gerbang peran di atas
        // dilewati — sehingga id dari browser hanya bisa MEMPERSEMPIT hasil,
        // tidak pernah memperluasnya.
        if ($ids->isNotEmpty()) {
            $rows = $rows->whereIn('id', $ids)->values();
        }

        $columns = ['Stage', 'CR Number', 'Budget Plan', 'Manager', 'Description', 'Beneficiary', 'Amount', 'Status', 'Aging'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Todo List');
        $sheet->fromArray($columns, null, 'A1');
        foreach (range('A', 'I') as $col) {
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0D1B2A');
            $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }

        $row = 2;
        foreach ($rows as $cr) {
            $sheet->fromArray([
                $stageOf($cr),
                $cr->code,
                $this->budgetPlanLabel($cr->budgetPlan),
                $cr->pm_internal_name,
                $cr->description ?? '-',
                $cr->beneficiary?->nickname ?? '-',
                (int) $cr->amount,
                $cr->status,
                $cr->aging_label,
            ], null, 'A' . $row);
            $row++;
        }

        foreach (['A'=>26,'B'=>18,'C'=>36,'D'=>20,'E'=>40,'F'=>20,'G'=>16,'H'=>12,'I'=>12] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $filename = 'todo_list_' . now()->format('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Bangun worklist CR grouped per Budget Plan untuk satu status (SUBMITTED/
     * CHECKED/VERIFIED), sama persis dengan pola yang dulu ada di
     * BudgetRequestController::index() (section "Review Cost Request").
     */
    /** @param string|string[] $status satu status, atau beberapa sekaligus. */
    private function buildStatusGroups(string|array $status, bool $denganCoa = true): \Illuminate\Support\Collection
    {
        $query = BudgetRequest::with([
            'type', 'beneficiary', 'applicant', 'details', 'statusLogs', 'realizations',
            'budgetPlan.project.customer', 'budgetPlan.project.manager', 'budgetPlan.costCenter',
        ])->whereIn('status', (array) $status);
        $this->scopeByRole($query);

        $crs = $query->get();

        // Baris CR per sub-item untuk SEMUA Budget Plan di antrean ini dimuat
        // sekaligus, bukan per plan di dalam buildReviewCoaBreakdown().
        if ($denganCoa) {
            $this->preloadCoaRows($crs->pluck('budgetPlan'));
        }

        return $crs
            ->groupBy(fn ($cr) => $cr->budget_plan_id ?? 0)
            ->map(function ($items) use ($status, $denganCoa) {
                // Aging tertua di atas — baik urutan CR di dalam grup maupun
                // urutan grup itu sendiri (lihat sortBy di akhir method ini).
                $items = $items->sortBy('aging_since')->values();
                $bp = $items->first()->budgetPlan;

                /*
                 | Rincian per Chart of Account adalah bagian TERMAHAL di sini:
                 | ia menelusuri baris anggaran satu per satu beserta
                 | realisasinya, sehingga satu pemanggilan bisa menghasilkan
                 | ribuan query. Yang membutuhkannya hanya HALAMAN Todo List
                 | (modal "View Budget"); lonceng navbar dan blok antrean di
                 | Dashboard cuma perlu tahu ADA BERAPA dan YANG MANA.
                 |
                 | Karena itu rinciannya dibuat opsional, bukan dijadikan query
                 | hitungan terpisah: definisi tiap antrean tetap satu-satunya
                 | di sini, jadi angka di lonceng tidak mungkin berbeda dari isi
                 | halamannya.
                 */
                $coaGroups = ($denganCoa && $bp)
                    ? $this->buildReviewCoaBreakdown($bp, $items, $status)
                    : collect();

                // Merah = kondisi tersendiri yang lebih berat: total keseluruhan Budget
                // Plan ini (semua CoA + semua request yang sedang direview) sudah
                // terlampaui — berlaku untuk semua CR di dalam plan ini. Kuning = NOB
                // yang lebih sempit, baru di level CoA/sub-item tertentu.
                $planNobFull = $coaGroups->sum('remaining') < 0;

                return [
                    'label'       => $this->budgetPlanLabel($bp),
                    'customer'    => $this->customerLabel($bp),
                    'po'          => $this->poLabel($bp),
                    'pm'          => strtoupper($items->first()->pm_internal_name),
                    'po_code'     => $items->first()->po_code,
                    'items'       => $items,
                    'coa_groups'  => $coaGroups,
                    'is_nob_full' => $planNobFull,
                    'cr_nob'      => $this->buildCrNobMap($coaGroups, $planNobFull),
                ];
            })
            ->sortBy(fn ($g) => $g['items']->first()->aging_since)
            ->values();
    }

    /**
     * MN-03 — grouping Todo List per PURCHASE ORDER.
     *
     * Grouping per Budget Plan (buildStatusGroups di atas) tetap dipertahankan
     * sebagai lapis KEDUA, karena rincian anggaran per CoA memang hidup di
     * level Budget Plan — kalau plan digabung paksa ke satu tumpukan PO,
     * rincian itu kehilangan acuannya dan angka sisa anggaran jadi tidak bisa
     * dibaca.
     *
     * Jadi hasilnya dua lapis:
     *
     *     PO  ->  Budget Plan  ->  Cost Request
     *
     * Pengelompokan dikerjakan di SINI (backend), bukan di Blade: subtotal
     * per PO, jumlah CR, dan urutannya ikut terhitung dari himpunan yang sama
     * dengan yang dirender, sehingga tetap konsisten saat disaring atau dicari.
     */
    /** @param string|string[] $status satu status, atau beberapa sekaligus. */
    private function buildPoGroups(string|array $status, bool $denganCoa = true): \Illuminate\Support\Collection
    {
        return $this->buildStatusGroups($status, $denganCoa)
            // PO yang sama bisa punya beberapa Budget Plan; kunci grupnya
            // memakai label PO supaya CR dari plan berbeda tapi PO sama
            // berkumpul di satu tempat.
            ->groupBy(fn ($g) => $g['po'] ?: '— Tanpa PO —')
            ->map(function ($plans, $poLabel) {
                $items = $plans->flatMap(fn ($g) => $g['items']);

                return [
                    'po'        => $poLabel,
                    'po_code'   => $plans->first()['po_code'] ?? null,
                    'customer'  => $plans->first()['customer'] ?? '-',
                    'pm'        => $plans->first()['pm'] ?? '-',
                    'plans'     => $plans->values(),
                    'qty'       => $items->count(),
                    'amount'    => (int) $items->sum('amount'),
                    // Aging tertua di dalam PO ini — dipakai untuk mengurutkan
                    // PO, supaya yang paling lama menunggu selalu di atas.
                    'oldest'    => $items->min('aging_since'),
                    'is_nob'    => $plans->contains(fn ($g) => $g['is_nob_full']),
                ];
            })
            ->sortBy('oldest')
            ->values();
    }
}
