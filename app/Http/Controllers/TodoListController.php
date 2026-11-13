<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsCrWorklist;
use App\Http\Controllers\Concerns\ScopesBudgetPlanVisibility;
use App\Models\BudgetPlan;
use App\Models\BudgetRequest;
use App\Models\DailyTransaction;
use App\Models\Invoice;
use App\Models\Project;
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

    /** Rentang (hari) log yang dihitung pada tabel Respon Time. */
    private const RESPONTIME_DAYS = 90;

    /** Lama cache tabel Respon Time (menit) — ringkasan lintas user, bukan data per user. */
    private const RESPONTIME_CACHE_MINUTES = 10;

    /**
     * Todo List Request — worklist lintas modul per role: Cost Request,
     * Budgeting, Treasury (Daily Transaction), Invoice & Cash Receipt,
     * Purchase Order, dan Collection. (Project Monitoring dikeluarkan dari
     * Todo List atas permintaan pemilik — alasan performa.)
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

        // Tabel ringkasan & respon time hanya dipakai halaman ini — sengaja
        // tidak ada di buildWorklistData() agar Dashboard (actionQueues) tidak
        // ikut menanggung query agregat yang berat. Masing-masing punya try
        // sendiri: kegagalan salah satunya tidak boleh menjatuhkan worklist
        // maupun tabel yang lain.
        $kosong = $this->emptyWorklistData();
        foreach ([
            'summaryTable'    => fn () => $this->buildSummaryTable(),
            'respontimeTable' => fn () => $this->cachedRespontimeTable(),
        ] as $kunci => $bangun) {
            try {
                $data[$kunci] = $bangun();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('TodoListController::index gagal membangun ' . $kunci, [
                    'user_id' => $user->id, 'error' => $e->getMessage(),
                ]);
                $data[$kunci] = $kosong[$kunci];
            }
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
            ['key' => 'bp-closing-verify', 'label' => 'Budgeting — Verifikasi Closing', 'icon' => 'fas fa-flag-checkered', 'tone' => 'attention', 'items' => $data['budgetingClosingVerify']],
            ['key' => 'bp-rejected',     'label' => 'Budgeting Ditolak',         'icon' => 'fas fa-undo',             'tone' => 'danger',    'items' => $data['budgetingRejected']],
            ['key' => 'reject',          'label' => 'Tindak Lanjut CR Ditolak',  'icon' => 'fas fa-undo',             'tone' => 'danger',    'items' => $data['managerRejected']],
            ['key' => 'batch-create',    'label' => 'Buat Batch Pembayaran',     'icon' => 'fas fa-layer-group',      'tone' => 'progress',  'items' => $data['treasuryBatchCreate']],
            ['key' => 'batch-draft',     'label' => 'Ajukan Batch Draft',        'icon' => 'fas fa-paper-plane',      'tone' => 'progress',  'items' => $data['treasuryDraftSubmit']],
            ['key' => 'batch-process',   'label' => 'Proses Batch',              'icon' => 'fas fa-money-check-alt',  'tone' => 'progress',  'items' => $data['treasuryProcess']],
            ['key' => 'batch-pay',       'label' => 'Tandai Batch Paid',         'icon' => 'fas fa-money-bill-wave',  'tone' => 'progress',  'items' => $data['treasuryReadyToPay']],
            ['key' => 'second-payment',  'label' => 'Second Approval',   'icon' => 'fas fa-hand-holding-usd', 'tone' => 'progress',  'items' => $data['secondPaymentPending']],
            ['key' => 'settlement',      'label' => 'Kirim Settlement',          'icon' => 'fas fa-receipt',          'tone' => 'progress',  'items' => $data['managerPaid']],
            ['key' => 'settle-check',    'label' => 'Settlement — Head Check',   'icon' => 'fas fa-clipboard-check',  'tone' => 'progress',  'items' => $rata($data['headSettlementCheck'])],
            ['key' => 'settle-verify',   'label' => 'Settlement — Verifikasi',   'icon' => 'fas fa-clipboard-check',  'tone' => 'progress',  'items' => $rata($data['financeSettlementVerify'])],
            ['key' => 'settle-2nd',      'label' => 'Expense Claim — Second Approve', 'icon' => 'fas fa-hand-holding-usd', 'tone' => 'attention', 'items' => $rata($data['approverSecondApprove'])],
            ['key' => 'settle-close',    'label' => 'Settlement — Penutupan',    'icon' => 'fas fa-lock',             'tone' => 'progress',  'items' => $rata($data['ceoSettlementClose'])],
            ['key' => 'po-submitted',    'label' => 'PO — Approval Finance',     'icon' => 'fas fa-file-signature',   'tone' => 'attention', 'items' => $data['poSubmitted']],
            ['key' => 'po-verified',     'label' => 'PO — Approval Approver',    'icon' => 'fas fa-file-signature',   'tone' => 'attention', 'items' => $data['poVerified']],
            ['key' => 'po-closing',      'label' => 'PO — Verifikasi Closing',   'icon' => 'fas fa-flag-checkered',   'tone' => 'attention', 'items' => $data['poClosingVerify']],
            ['key' => 'reg-approve',     'label' => 'Expense — Approval',        'icon' => 'fas fa-file-invoice-dollar', 'tone' => 'attention', 'items' => $data['costRegisterApprove']],
            ['key' => 'reg-batch',       'label' => 'Expense — Masukkan ke Batch', 'icon' => 'fas fa-layer-group', 'tone' => 'progress', 'items' => $data['costRegisterBatch']],
            ['key' => 'reg-settle',      'label' => 'Expense CA — Input Settlement', 'icon' => 'fas fa-receipt', 'tone' => 'progress', 'items' => $data['expenseSettleInput']],
            ['key' => 'reg-settle-approve', 'label' => 'Expense CA — Approval Settlement', 'icon' => 'fas fa-clipboard-check', 'tone' => 'attention', 'items' => $data['expenseSettleApprove']],
            ['key' => 'reg-rejected',    'label' => 'Expense Ditolak — Revisi / Batalkan', 'icon' => 'fas fa-undo', 'tone' => 'danger', 'items' => $data['expenseRejected']],
            ['key' => 'collection',      'label' => 'Menunggu Invoice Ditagih',  'icon' => 'fas fa-file-invoice',     'tone' => 'attention', 'items' => $data['collectionWaitingInvoice']],
            ['key' => 'tt-request',      'label' => 'Trouble Ticket — Review Pengajuan', 'icon' => 'fas fa-file-invoice-dollar', 'tone' => 'attention', 'items' => $data['ticketRequestReview']],
            ['key' => 'tt-action',       'label' => 'Trouble Ticket — Tindakan', 'icon' => 'fas fa-tools', 'tone' => 'attention', 'items' => $data['ticketActions']],
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
            'budgetingClosingDue' => collect(), 'budgetingClosingVerify' => collect(),
            'treasuryDraftSubmit' => collect(), 'costRegisterApprove' => collect(),
            'costRegisterBatch' => collect(), 'poClosingVerify' => collect(),
            'expenseSettleInput' => collect(), 'expenseSettleApprove' => collect(), 'expenseRejected' => collect(),
            'treasuryBatchCreate' => collect(), 'treasuryBatchGroups' => collect(),
            'treasuryBatchSecond' => collect(), 'batchSourceFunds' => collect(),
            'treasuryProcess' => collect(), 'treasuryReadyToPay' => collect(),
            'poSubmitted' => collect(), 'poVerified' => collect(),
            'invoiceVerify' => collect(), 'invoiceApprove' => collect(),
            'collectionWaitingInvoice' => collect(), 'ticketRequestReview' => collect(), 'ticketActions' => collect(),
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
        if (TodoService::allows($user, 'head')) {
            $sections['head'] = [
                'title'        => 'Menunggu Approval Head',
                'status_label' => 'Submitted',
                'groups'       => $this->buildPoGroups(TodoService::statuses('head'), $denganCoa),
                'approve_url'  => route('budget-request.review-bulk-approve'),
                'reject_url'   => route('budget-request.review-bulk-reject'),
            ];
        }

        // Cost Control — verifikasi CHECKED, grouped per Budget Plan, bulk approve/reject.
        if (TodoService::allows($user, 'cost_control_checked')) {
            $sections['cost_control_checked'] = [
                'title'        => 'Menunggu Verifikasi Cost Control',
                'status_label' => 'Checked',
                'groups'       => $this->buildPoGroups(TodoService::statuses('cost_control_checked'), $denganCoa),
                'approve_url'  => route('budget-request.review-bulk-approve'),
                'reject_url'   => route('budget-request.review-bulk-reject'),
            ];
        }

        // CEO / Approver — VERIFIED, grouped per Budget Plan, bulk approve/reject.
        if (TodoService::allows($user, 'ceo')) {
            $sections['ceo'] = [
                'title'        => 'Menunggu Approval CEO',
                'status_label' => 'Verified',
                'groups'       => $this->buildPoGroups(TodoService::statuses('ceo'), $denganCoa),
                'approve_url'  => route('budget-request.review-bulk-approve'),
                'reject_url'   => route('budget-request.review-bulk-reject'),
            ];
        }

        // Manager/Supervisor — Reject Handling (Revise/Cancel) + Submit Realization.
        $managerRejected = collect();
        $managerPaid     = collect();
        $budgetPlanRelations = ['budgetPlan.project.customer', 'budgetPlan.costCenter'];

        if (TodoService::allows($user, 'managerRejected')) {
            $rejectedQuery = $this->queueQuery('managerRejected')
                ->with(array_merge(['type', 'beneficiary', 'statusLogs'], $budgetPlanRelations));
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
            $paidQuery = $this->queueQuery('managerPaid')
                ->with(array_merge(['type', 'beneficiary', 'statusLogs'], $budgetPlanRelations));
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
        if (TodoService::allows($user, 'headSettlementCheck')) {
            $headSettlementCheck = $addRefund($this->buildPoGroups(TodoService::statuses('headSettlementCheck'), $denganCoa));
        }

        // Finance/Cost Control — Settlement Verify (SETTLE_CHECKED -> SETTLE_VERIFIED).
        $financeSettlementVerify = collect();
        if (TodoService::allows($user, 'financeSettlementVerify')) {
            $financeSettlementVerify = $addRefund($this->buildPoGroups(TodoService::statuses('financeSettlementVerify'), $denganCoa));
        }

        // Finance Manager — Settlement Close (SETTLE_VERIFIED -> CLOSED). Diblokir
        // di backend (ceoCloseSettlement()) selama masih ada Second Payment
        // PENDING — ditandai di sini supaya Blade bisa tampilkan alasannya.
        $ceoSettlementClose = collect();
        if (TodoService::allows($user, 'ceoSettlementClose')) {
            /*
            | Satu antrean Finance Manager di status SETTLE_VERIFIED, dua aksi:
            |   tanpa Expense Claim -> Close
            |   ada Expense Claim   -> Settle Approve / Reject, lanjut ke
            |                          antrean Second Approve milik Approver
            */
            $ceoSettlementClose = $addRefund($this->buildPoGroups(TodoService::statuses('ceoSettlementClose'), $denganCoa));
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
        if (TodoService::allows($user, 'approverSecondApprove')) {
            $approverSecondApprove = $addRefund($this->buildPoGroups(TodoService::statuses('approverSecondApprove'), $denganCoa));
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
        if (TodoService::allows($user, 'secondPaymentPending')) {
            $secondPaymentPending = $this->queueQuery('secondPaymentPending')
                ->with([
                    'beneficiary',
                    'statusLogs',
                    'secondPayments.dailyTransactionItems.dailyTransaction',
                ])
                ->get()
                ->map(function ($cr) {
                    $payment = $cr->secondPayments->firstWhere('status', 'PENDING');
                    $cr->pending_payment = $payment;

                    $activeItem = $payment?->dailyTransactionItems
                        ->first(fn ($i) => $i->status === 'INCLUDED'
                            && in_array($i->dailyTransaction?->status, \App\Models\DailyTransaction::ACTIVE_STATUSES));

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
        if (TodoService::allows($user, 'budgetingHeadCheck')) {
            $budgetingHeadCheck = $this->queueQuery('budgetingHeadCheck')
                ->with(['project.customer', 'project.manager', 'costCenter'])
                ->latest()
                ->get()
                ->map(fn ($bp) => $this->withAging($bp, $bp->updated_at))
                ->sortBy('aging_since')->values();
        }

        $budgetingVerify = collect();
        if (TodoService::allows($user, 'budgetingVerify')) {
            $budgetingVerify = $this->queueQuery('budgetingVerify')
                ->with(['project.customer', 'project.manager', 'costCenter'])
                ->latest()
                ->get()
                ->map(fn ($bp) => $this->withAging($bp, $bp->updated_at))
                ->sortBy('aging_since')->values();
        }

        $budgetingCeoApprove = collect();
        if (TodoService::allows($user, 'budgetingCeoApprove')) {
            $budgetingCeoApprove = $this->queueQuery('budgetingCeoApprove')
                ->with(['project.customer', 'project.manager', 'costCenter'])
                ->latest()
                ->get()
                ->map(fn ($bp) => $this->withAging($bp, $bp->updated_at))
                ->sortBy('aging_since')->values();
        }

        // Manager — Budgeting: Ajukan Closing. Budget berstatus CLOSING yang berkas
        // closing-nya belum diajukan (closing_submitted_at NULL) sehingga tugasnya
        // masih di manager, bukan di Finance. Auto-closing berbasis Plan Finish sudah
        // dihapus, jadi antrean ini hanya berisi sisa data lama.
        $budgetingClosingDue = collect();
        if (TodoService::allows($user, 'budgetingClosingDue')) {
            $budgetingClosingDue = $this->queueQuery('budgetingClosingDue')
                ->with(['project.customer', 'project.manager', 'costCenter'])
                ->latest()
                ->get()
                ->map(fn ($bp) => $this->withAging($bp, $bp->updated_at))
                ->sortBy('aging_since')->values();
        }

        // Manager — Budgeting: Reject Handling (REJECTED -> DRAFT via Revise).
        $budgetingRejected = collect();
        if (TodoService::allows($user, 'budgetingRejected')) {
            $budgetingRejected = $this->queueQuery('budgetingRejected')
                ->with(['project.customer', 'project.manager', 'costCenter'])
                ->latest()
                ->get()
                ->map(fn ($bp) => $this->withAging($bp, $bp->updated_at))
                ->sortBy('aging_since')->values();
        }

        // Finance — Budgeting: Verifikasi Closing (CLOSING + berkas closing sudah diajukan
        // manager -> verifyClosing()/rejectClosing()). Verify/Reject inline lewat todo-list.bulk ('bp-closing-verify').
        $budgetingClosingVerify = collect();
        if (TodoService::allows($user, 'budgetingClosingVerify')) {
            $budgetingClosingVerify = $this->queueQuery('budgetingClosingVerify')
                ->with(['project.customer', 'project.manager', 'costCenter'])
                ->latest()
                ->get()
                ->map(fn ($bp) => $this->withAging($bp, $bp->closing_submitted_at ?? $bp->updated_at))
                ->sortBy('aging_since')->values();
        }

        // Treasury — Menunggu Proses (PENDING_APPROVER, ditandai "Submitted" di
        // model -> jadi "Approved/Done" setelah diproses CEO) & Siap Dibayar
        // (DONE, tinggal ditandai Paid oleh Treasury). Flat list per batch —
        // DailyTransactionController juga cuma punya endpoint single-record.
        $treasuryProcess = collect();
        if (TodoService::allows($user, 'treasuryProcess')) {
            $treasuryProcess = $this->queueQuery('treasuryProcess')
                ->with('sourceFund')->withCount('includedItems')
                ->latest()
                ->get()
                ->map(fn ($batch) => $this->withAging($batch, $batch->created_at))
                ->sortBy('aging_since')->values();
        }

        $treasuryReadyToPay = collect();
        if (TodoService::allows($user, 'treasuryReadyToPay')) {
            $treasuryReadyToPay = $this->queueQuery('treasuryReadyToPay')
                ->with('sourceFund')->withCount('includedItems')
                ->latest()
                ->get()
                ->map(fn ($batch) => $this->withAging($batch, $batch->approved_at ?? $batch->created_at))
                ->sortBy('aging_since')->values();
        }

        // Treasury — Draft belum diajukan (DRAFT -> PENDING_APPROVER lewat submit()). Isinya
        // sudah dipesan (ACTIVE_STATUSES), jadi tidak muncul lagi di "Buat Batch".
        $treasuryDraftSubmit = collect();
        if (TodoService::allows($user, 'treasuryDraftSubmit')) {
            $treasuryDraftSubmit = $this->queueQuery('treasuryDraftSubmit')
                ->with('sourceFund')->withCount('includedItems')
                ->latest()
                ->get()
                ->map(fn ($batch) => $this->withAging($batch, $batch->created_at))
                ->sortBy('aging_since')->values();
        }

        // Expense (dulu Cost Register) — Submitted menunggu CEO, dan Approved menunggu dibatch Treasury.
        $costRegisterApprove = collect();
        if (TodoService::allows($user, 'costRegisterApprove')) {
            $costRegisterApprove = $this->queueQuery('costRegisterApprove')
                ->with(['project.customer', 'inputBy', 'beneficiary', 'details.project', 'details.costCenter', 'details.projects:projects.id,projects.code'])
                ->latest()
                ->get()
                ->map(fn ($reg) => $this->withAging($reg, $reg->verified_at ?? $reg->submitted_at ?? $reg->updated_at))
                ->sortBy('aging_since')->values();
        }

        $costRegisterBatch = collect();
        if (TodoService::allows($user, 'costRegisterBatch')) {
            $costRegisterBatch = $this->queueQuery('costRegisterBatch')
                ->with(['project.customer', 'inputBy', 'beneficiary', 'details.project', 'details.costCenter', 'details.projects:projects.id,projects.code'])
                ->latest()
                ->get()
                ->map(fn ($reg) => $this->withAging($reg, $reg->approved_at ?? $reg->updated_at))
                ->sortBy('aging_since')->values();
        }

        // Expense Cash Advance — input realisasi (FM) dan approval settlement (CEO).
        $expenseSettleInput = collect();
        if (TodoService::allows($user, 'expenseSettleInput')) {
            $expenseSettleInput = $this->queueQuery('expenseSettleInput')
                ->with(['project.customer', 'inputBy', 'beneficiary', 'details.project', 'details.costCenter', 'details.projects:projects.id,projects.code'])
                ->latest()
                ->get()
                ->map(fn ($reg) => $this->withAging($reg, $reg->paid_at ?? $reg->updated_at))
                ->sortBy('aging_since')->values();
        }

        $expenseRejected = collect();
        if (TodoService::allows($user, 'expenseRejected')) {
            $expenseRejected = $this->queueQuery('expenseRejected')
                ->with(['project.customer', 'inputBy', 'beneficiary', 'details.project', 'details.costCenter', 'details.projects:projects.id,projects.code'])
                ->latest()
                ->get()
                ->map(fn ($reg) => $this->withAging($reg, $reg->updated_at))
                ->sortBy('aging_since')->values();
        }

        $expenseSettleApprove = collect();
        if (TodoService::allows($user, 'expenseSettleApprove')) {
            $expenseSettleApprove = $this->queueQuery('expenseSettleApprove')
                ->with(['project.customer', 'inputBy', 'beneficiary', 'details.project', 'details.costCenter', 'details.projects:projects.id,projects.code'])
                ->latest()
                ->get()
                ->map(fn ($reg) => $this->withAging($reg, $reg->settle_submitted_at ?? $reg->updated_at))
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
        if (TodoService::allows($user, 'treasuryBatchCreate')) {
            $batchCreateQuery = $this->queueQuery('treasuryBatchCreate')
                ->with(['type', 'beneficiary', 'statusLogs', 'budgetPlan.project.customer', 'budgetPlan.project.manager', 'budgetPlan.costCenter']);
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
        if (TodoService::allows($user, 'treasuryBatchSecond')) {
            $secondQuery = $this->queueQuery('treasuryBatchSecond')
                ->with(['budgetRequest.beneficiary', 'budgetRequest.budgetPlan.project.customer', 'budgetRequest.budgetPlan.project.manager', 'budgetRequest.budgetPlan.costCenter']);

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
         | Definisi "siapa yang menangani PO SUBMITTED" ada di
         | TodoService::QUEUES['poSubmitted'] (satu sumber untuk halaman,
         | badge sidebar, dan pengingat).
         |
         | Verify/Revise kini bisa langsung dari Todo List (keputusan pemilik
         | 2026-11-09, menggantikan pola reminder + link-out): aksi lewat
         | todo-list.bulk (BULK_ROUTES 'po-submitted') yang memanggil
         | ProjectController::verify()/financeRevise(). Detail PO tetap bisa
         | dibuka dari menu "Aksi lain" sebelum memutuskan.
         */
        $poSubmitted = collect();
        if (TodoService::allows($user, 'poSubmitted')) {
            $poSubmitted = $this->queueQuery('poSubmitted')
                ->with(['customer', 'manager'])
                ->withCount('poNumbers')
                ->latest()
                ->get()
                ->map(fn ($po) => $this->withAging($po, $po->submitted_at ?? $po->updated_at))
                ->sortBy('aging_since')->values();
        }

        // Purchase Order — Menunggu Approval Approver (VERIFIED -> approve()). Approve/Revise
        // inline lewat todo-list.bulk ('po-verified'; approve = decision APPROVE, Early Start
        // tetap di halaman PO), seperti $poSubmitted. BP bundled ikut disetujui bersama PO, jadi tidak
        // muncul ganda di antrean Budgeting (lihat queueQuery budgetingCeoApprove).
        $poVerified = collect();
        if (TodoService::allows($user, 'poVerified')) {
            $poVerified = $this->queueQuery('poVerified')
                ->with(['customer', 'manager'])
                ->withCount('poNumbers')
                ->latest()
                ->get()
                ->map(fn ($po) => $this->withAging($po, $po->verified_at ?? $po->updated_at))
                ->sortBy('aging_since')->values();
        }

        // Purchase Order — Closing menunggu verifikasi Finance (CLOSING -> verifyClosing()/rejectClosing()).
        $poClosingVerify = collect();
        if (TodoService::allows($user, 'poClosingVerify')) {
            $poClosingVerify = $this->queueQuery('poClosingVerify')
                ->with(['customer', 'manager'])
                ->withCount('poNumbers')
                ->latest()
                ->get()
                ->map(fn ($po) => $this->withAging($po, $po->updated_at))
                ->sortBy('aging_since')->values();
        }

        // Invoice & Cash Receipt — Menunggu Verifikasi (SUBMITTED -> VERIFIED)
        // & Menunggu Approval (VERIFIED -> APPROVED). Kedua tahap milik Finance
        // Manager; lihat catatan di $invoiceApprove.
        $invoiceVerify = collect();
        if (TodoService::allows($user, 'invoiceVerify')) {
            $invoiceVerify = $this->queueQuery('invoiceVerify')
                ->with(['po.customer'])
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
        if (TodoService::allows($user, 'invoiceApprove')) {
            $invoiceApprove = $this->queueQuery('invoiceApprove')
                ->with(['po.customer'])
                ->latest()
                ->get()
                ->map(fn ($inv) => $this->withAging($inv, $inv->verified_at ?? $inv->updated_at))
                ->sortBy('aging_since')->values();
        }

        // Collection — Waiting Invoice: No. PO yang punya BAST aktif yang belum
        // tertagih (belum punya invoice non-CANCELLED), bukan sekadar status
        // No. PO. No. PO lama tanpa baris BAST tetap memakai aturan lama
        // (status PARTIAL/BAST_SUBMITTED). Ini reminder buat Collection segera
        // buat invoice, bukan approval — jadi flat list, link-out ke form Buat
        // Invoice, tanpa aksi approve/reject. Definisi queryFor di queueQuery().
        $collectionWaitingInvoice = collect();
        if (TodoService::allows($user, 'collectionWaitingInvoice')) {
            $collectionWaitingInvoice = $this->queueQuery('collectionWaitingInvoice')
                ->with(['project.customer', 'basts' => fn ($b) => $b->whereDoesntHave('activeInvoices')])
                ->get()
                ->map(function ($po) {
                    // Aging dari BAST tertua yang belum tertagih; No. PO lama
                    // tanpa baris BAST memakai tanggal submit BAST agregatnya.
                    $tertua = $po->basts->min('tanggal_bast');

                    return $this->withAging($po, $tertua ?? $po->bast_submitted_at ?? $po->updated_at);
                })
                ->sortBy('aging_since')->values();
        }

        // Trouble Ticket — pengajuan biaya menunggu review supervisor (flat list, tautan ke tab Pengajuan tiket).
        $ticketRequestReview = collect();
        if (TodoService::allows($user, 'ticketRequestReview')) {
            $ticketRequestReview = $this->queueQuery('ticketRequestReview')
                ->with(['ticket.site:id,tid,sitename', 'requestType:id,name', 'requester:id,name', 'budgetPlan:id,code'])
                ->get()
                ->map(fn ($r) => $this->withAging($r, $r->submitted_at ?? $r->updated_at))
                ->sortBy('aging_since')->values();
        }

        // Trouble Ticket — tindakan lain, satu daftar untuk semua antrean tiket.
        $ticketActions = collect();
        foreach (TodoService::TICKET_ACTION_QUEUES as $key => $label) {
            if (TodoService::allows($user, $key)) {
                $ticketActions = $ticketActions->concat($this->ticketActionRows($key, $label));
            }
        }
        $ticketActions = $ticketActions->sortBy('aging_since')->values();

        // Accounting kini pemilik tahap Verifikasi Invoice (keputusan pemilik K2),
        // jadi tidak ada lagi role "Coming Soon".
        $comingSoonRoles = [];

        // Semua task yang tampil di halaman ini (flat), dipakai untuk kartu
        // ringkasan (Max Aging) & export — union dari seluruh section grouped +
        // flat di atas.
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
            ->concat($budgetingClosingDue)->concat($budgetingClosingVerify)
            ->concat($treasuryBatchCreate)->concat($treasuryDraftSubmit)
            ->concat($treasuryProcess)->concat($treasuryReadyToPay)
            ->concat($costRegisterApprove)->concat($costRegisterBatch)
            ->concat($expenseSettleInput)->concat($expenseSettleApprove)->concat($expenseRejected)
            ->concat($poSubmitted)->concat($poVerified)->concat($poClosingVerify)
            ->concat($invoiceVerify)->concat($invoiceApprove)
            ->concat($collectionWaitingInvoice)
            ->concat($ticketRequestReview)
            ->concat($ticketActions);

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

        $completedToday = $this->completedTodayCount($user);

        $hasAnyTask = $totalPending > 0;
        $hasAnySection = !empty($sections) || $managerRejected->isNotEmpty() || $managerPaid->isNotEmpty()
            || $headSettlementCheck->isNotEmpty() || $financeSettlementVerify->isNotEmpty()
            || $approverSecondApprove->isNotEmpty()
            || $ceoSettlementClose->isNotEmpty() || $secondPaymentPending->isNotEmpty()
            || !empty($comingSoonRoles)
            || $budgetingHeadCheck->isNotEmpty() || $budgetingVerify->isNotEmpty()
            || $budgetingCeoApprove->isNotEmpty() || $budgetingRejected->isNotEmpty()
            || $budgetingClosingDue->isNotEmpty() || $budgetingClosingVerify->isNotEmpty()
            || $treasuryBatchCreate->isNotEmpty() || $treasuryDraftSubmit->isNotEmpty()
            || $treasuryProcess->isNotEmpty() || $treasuryReadyToPay->isNotEmpty()
            || $costRegisterApprove->isNotEmpty() || $costRegisterBatch->isNotEmpty()
            || $expenseSettleInput->isNotEmpty() || $expenseSettleApprove->isNotEmpty() || $expenseRejected->isNotEmpty()
            || $poSubmitted->isNotEmpty() || $poVerified->isNotEmpty() || $poClosingVerify->isNotEmpty()
            || $invoiceVerify->isNotEmpty() || $invoiceApprove->isNotEmpty()
            || $collectionWaitingInvoice->isNotEmpty()
            || $ticketRequestReview->isNotEmpty() || $ticketActions->isNotEmpty();

        return compact(
            'sections', 'managerRejected', 'managerPaid',
            'headSettlementCheck', 'financeSettlementVerify', 'approverSecondApprove',
            'ceoSettlementClose', 'secondPaymentPending', 'secondPaymentGroups',
            'budgetingHeadCheck', 'budgetingVerify', 'budgetingCeoApprove', 'budgetingRejected',
            'budgetingClosingDue', 'budgetingClosingVerify',
            'treasuryDraftSubmit', 'costRegisterApprove', 'costRegisterBatch', 'poClosingVerify',
            'expenseSettleInput', 'expenseSettleApprove', 'expenseRejected',
            'treasuryBatchCreate', 'treasuryBatchGroups', 'treasuryBatchSecond', 'batchSourceFunds', 'treasuryProcess', 'treasuryReadyToPay', 'poSubmitted', 'poVerified',
            'invoiceVerify', 'invoiceApprove', 'collectionWaitingInvoice', 'ticketRequestReview', 'ticketActions',
            'comingSoonRoles', 'totalPending', 'maxAging', 'completedToday', 'hasAnyTask', 'hasAnySection',
            'allTasks'
        );
    }

    /**
     * KPI "Tindakan Selesai Hari Ini": transisi yang benar-benar dilakukan
     * user ini hari ini, lintas modul, dari log/kolom pelaku yang SUDAH ada:
     *  - Cost Request  : request_status_logs.acted_by
     *  - Budget Plan   : budget_plan_logs.user_id
     *  - Purchase Order: project_logs.user_id
     *  - Treasury      : daily_transactions.approved_by/approved_at, paid_by/paid_at
     *  - Invoice       : invoices.verified/approved/sent/received_by + *_at
     * Daily Transaction & Invoice tidak punya tabel log tersendiri; pelakunya
     * hanya tercatat di kolom *_by pada barisnya (jejak terakhir per tahap).
     */
    private function completedTodayCount(User $user): int
    {
        // whereBetween (bukan whereDate) agar indeks kolom waktu bisa dipakai.
        $rentang = [now()->startOfDay(), now()->endOfDay()];
        $uid     = $user->id;

        $n = RequestStatusLog::where('acted_by', $uid)->whereBetween('created_at', $rentang)->count();
        $n += \DB::table('budget_plan_logs')->where('user_id', $uid)->whereBetween('created_at', $rentang)->count();
        $n += \DB::table('project_logs')->where('user_id', $uid)->whereBetween('created_at', $rentang)->count();

        foreach ([['approved_by', 'approved_at'], ['paid_by', 'paid_at']] as [$by, $at]) {
            $n += \DB::table('daily_transactions')->where($by, $uid)->whereBetween($at, $rentang)->count();
        }
        foreach (['verified', 'approved', 'sent', 'received'] as $tahap) {
            $n += \DB::table('invoices')->where($tahap . '_by', $uid)->whereBetween($tahap . '_at', $rentang)->count();
        }

        return $n;
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
        $statusFromAvg = function (string $table, string $fk, string $where = '') {
            $rows = \DB::select("
                SELECT status_from AS st, SUM(diff) AS s, COUNT(*) AS n FROM (
                    SELECT status_from,
                           TIMESTAMPDIFF(SECOND,
                               LAG(created_at) OVER (PARTITION BY {$fk} ORDER BY created_at, id),
                               created_at) AS diff
                    FROM {$table} {$where}
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
        // BP bundled (ikut approval PO) dikecualikan, konsisten dengan $bpCount.
        $bpAvg = $statusFromAvg('budget_plan_logs', 'budget_plan_id',
            'WHERE budget_plan_id IN (SELECT id FROM budget_plans WHERE via_po_approval = 0)');
        $crAvg = $statusFromAvg('request_status_logs', 'budget_request_id');

        $treasuryApproveAvg = $pairAvg('daily_transactions', 'created_at', 'approved_at', "AND type = 'CASH_OUT'");
        $treasuryPaidAvg    = $pairAvg('daily_transactions', 'approved_at', 'paid_at',    "AND type = 'CASH_OUT'");
        $invoiceVerifyAvg   = $pairAvg('invoices', 'submitted_at', 'verified_at');
        $invoiceApproveAvg  = $pairAvg('invoices', 'verified_at', 'approved_at');

        /*
         | SETTLE_VERIFIED bercabang dua (lihat HandlesCrSettlement):
         |   - TANPA Expense Claim -> langsung CLOSED ("Settlement Close").
         |   - DENGAN Expense Claim -> SETTLE_APPROVED ("Settle Approved") lalu
         |     SECOND_APPROVED ("Second Approval").
         | $crAvg['SETTLE_VERIFIED'] (status_from saja) MENCAMPUR kedua cabang
         | itu, jadi dipakai versi lebih presisi di sini: dikelompokkan juga per
         | status_to, supaya tiap baris memakai rata-rata JALUR-nya sendiri.
         */
        $crPairAvg = function () {
            $rows = \DB::select("
                SELECT status_from AS sf, status_to AS st, SUM(diff) AS s, COUNT(*) AS n FROM (
                    SELECT status_from, status_to,
                           TIMESTAMPDIFF(SECOND,
                               LAG(created_at) OVER (PARTITION BY budget_request_id ORDER BY created_at, id),
                               created_at) AS diff
                    FROM request_status_logs
                ) t
                WHERE diff IS NOT NULL AND diff >= 0
                GROUP BY status_from, status_to
            ");
            $map = [];
            foreach ($rows as $r) {
                $map[$r->sf][$r->st] = ['sum' => (int) $r->s, 'count' => (int) $r->n];
            }
            return $map;
        };
        $crPair = $crPairAvg();
        $settlementCloseAvg = $crPair['SETTLE_VERIFIED']['CLOSED']          ?? null;
        $settleApprovedAvg  = $crPair['SETTLE_VERIFIED']['SETTLE_APPROVED'] ?? null;
        $secondApprovalAvg  = $crPair['SETTLE_APPROVED']['SECOND_APPROVED'] ?? null;

        /*
         | Jumlah CR di SETTLE_VERIFIED juga dipecah per cabang yang sama (punya
         | Expense Claim atau tidak) — kalau tidak, "Settlement Close" dan
         | "Settle Approved" akan menghitung CR yang sama dua kali. Volumenya
         | kecil (satu tahap workflow), jadi collection filter di sini aman;
         | expenseClaimAmount() bukan kolom tersimpan (lihat BudgetRequest).
         */
        $crCountByClaim = function (string $status, bool $hasClaim) {
            $query = BudgetRequest::where('status', $status)->with('realizations');
            $this->scopeByRole($query);
            return $query->get()->filter(fn ($cr) => $cr->hasExpenseClaim() === $hasClaim)->count();
        };

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
            ['Approver',        'Approval Approver (PO VERIFIED)', 'Purchase Order', Project::where('status', 'VERIFIED')->count(), url('/project?status=VERIFIED'), $poAvg['VERIFIED'] ?? null],
            ['Finance',         'Aktivasi PO',             'Purchase Order', Project::where('status', 'WAITING_PO')->count(),   url('/project?status=WAITING_PO'), $poAvg['WAITING_PO'] ?? null],

            // ---- Budgeting
            ['Manager',         'Submit (Draft)',          'Budgeting',      $bpCount(fn ($q) => $q->where('status', 'DRAFT')->where('via_po_approval', false)),     url('/budget-plan'), $bpAvg['DRAFT'] ?? null],
            ['Head',            'Approval Head',           'Budgeting',      $bpCount(fn ($q) => $q->where('status', 'SUBMITTED')->where('via_po_approval', false)), url('/budget-plan'), $bpAvg['SUBMITTED'] ?? null],
            ['Cost Control',    'Verifikasi Cost Control', 'Budgeting',      $bpCount(fn ($q) => $q->where('status', 'CHECKED')->where('via_po_approval', false)),   url('/budget-plan'), $bpAvg['CHECKED'] ?? null],
            ['CEO',             'Approval CEO',            'Budgeting',      $bpCount(fn ($q) => $q->where('status', 'VERIFIED')->where('via_po_approval', false)),  url('/budget-plan'), $bpAvg['VERIFIED'] ?? null],

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
            ['Finance Manager', 'Settlement Close',        'Cost Request',   $crCountByClaim('SETTLE_VERIFIED', false), $crLink('SETTLE_VERIFIED'), $settlementCloseAvg],
            ['Finance Manager', 'Settle Approved',         'Cost Request',   $crCountByClaim('SETTLE_VERIFIED', true),  $crLink('SETTLE_VERIFIED'), $settleApprovedAvg],
            ['CEO',             'Second Approval',         'Cost Request',   $crCount('SETTLE_APPROVED'), $crLink('SETTLE_APPROVED'), $secondApprovalAvg],

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
     * user untuk manajemen, bukan antrean milik user tertentu. Karena itu
     * hasilnya sama untuk semua user dan di-cache GLOBAL (cachedRespontimeTable()),
     * bukan per user. Hanya log dalam RESPONTIME_DAYS hari terakhir yang dihitung.
     */
    private function cachedRespontimeTable(): array
    {
        return \Illuminate\Support\Facades\Cache::remember(
            'todo-list.respontime.v1',
            now()->addMinutes(self::RESPONTIME_CACHE_MINUTES),
            fn () => $this->buildRespontimeTable()
        );
    }

    private function buildRespontimeTable(): array
    {
        $sejak = now()->subDays(self::RESPONTIME_DAYS)->startOfDay()->toDateTimeString();

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
        $logStats = function (string $table, string $fk, string $actorCol) use ($sejak) {
            // LAG dihitung atas RIWAYAT PENUH dokumen yang punya log dalam
            // jendela, supaya transisi pertama di dalam jendela tetap punya
            // pendahulu (yang boleh di luar jendela). Jendela hanya memfilter
            // baris luar: transisi yang TERJADI sebelum $sejak tidak dihitung.
            return \DB::select("
                SELECT uid, SUM(diff) AS s, COUNT(*) AS n FROM (
                    SELECT {$actorCol} AS uid, created_at AS at,
                           TIMESTAMPDIFF(SECOND,
                               LAG(created_at) OVER (PARTITION BY {$fk} ORDER BY created_at, id),
                               created_at) AS diff
                    FROM {$table}
                    WHERE {$fk} IN (SELECT DISTINCT {$fk} FROM {$table} WHERE created_at >= ?)
                ) t
                WHERE at >= ? AND diff IS NOT NULL AND diff >= 0 AND uid IS NOT NULL
                GROUP BY uid
            ", [$sejak, $sejak]);
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
                    FROM daily_transactions WHERE approved_at IS NOT NULL AND approved_by IS NOT NULL AND approved_at >= ?
                UNION ALL
                SELECT paid_by AS uid, TIMESTAMPDIFF(SECOND, approved_at, paid_at) AS diff
                    FROM daily_transactions WHERE paid_at IS NOT NULL AND approved_at IS NOT NULL AND paid_by IS NOT NULL AND paid_at >= ?
            ) t
            WHERE diff IS NOT NULL AND diff >= 0 AND uid IS NOT NULL
            GROUP BY uid
        ", [$sejak, $sejak]);
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
                    FROM invoices WHERE verified_at IS NOT NULL AND submitted_at IS NOT NULL AND verified_by IS NOT NULL AND verified_at >= ?
                UNION ALL
                SELECT approved_by AS uid, TIMESTAMPDIFF(SECOND, verified_at, approved_at) AS diff
                    FROM invoices WHERE approved_at IS NOT NULL AND verified_at IS NOT NULL AND approved_by IS NOT NULL AND approved_at >= ?
                UNION ALL
                SELECT sent_by AS uid, TIMESTAMPDIFF(SECOND, approved_at, sent_at) AS diff
                    FROM invoices WHERE sent_at IS NOT NULL AND approved_at IS NOT NULL AND sent_by IS NOT NULL AND sent_at >= ?
                UNION ALL
                SELECT received_by AS uid, TIMESTAMPDIFF(SECOND, sent_at, received_at) AS diff
                    FROM invoices WHERE received_at IS NOT NULL AND sent_at IS NOT NULL AND received_by IS NOT NULL AND received_at >= ?
            ) t
            WHERE diff IS NOT NULL AND diff >= 0 AND uid IS NOT NULL
            GROUP BY uid
        ", array_fill(0, 4, $sejak));
        foreach ($invoice as $r) {
            $add((int) $r->uid, 'Invoice & Cash Receipt', $r->s, $r->n);
        }

        if (empty($agg)) {
            return ['modules' => $modules, 'rows' => [], 'moduleAvg' => array_fill_keys($modules, null)];
        }

        // Hanya user AKTIF yang ditampilkan — user nonaktif/dihapus dikeluarkan
        // dari $agg juga (bukan cuma disembunyikan), supaya rata-rata footer
        // per modul tidak ikut ditarik oleh angka mereka.
        $names = User::whereIn('id', array_keys($agg))->where('status', 'ACTIVE')->pluck('name', 'id');
        $agg   = array_intersect_key($agg, $names->all());

        if (empty($agg)) {
            return ['modules' => $modules, 'rows' => [], 'moduleAvg' => array_fill_keys($modules, null)];
        }

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
                'user'        => $names[$uid],
                'cells'       => $out,
                'avg_seconds' => $totalCnt ? (int) round($totalSum / $totalCnt) : null,
                'count'       => $totalCnt,
            ];
        }

        // Default: rata-rata TERCEPAT di atas (ascending) — lihat permintaan
        // "default sortir asc dari kolom rata-rata". Sortir lain (termasuk
        // desc & per modul) dikerjakan di klien, lihat _summary_respontime.
        usort($rows, fn ($a, $b) => $a['avg_seconds'] <=> $b['avg_seconds'] ?: strcmp($a['user'], $b['user']));

        // Rata-rata per modul (gabungan semua user AKTIF) untuk baris footer.
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
        // "Reject" invoice = Revise (SUBMITTED -> DRAFT, VERIFIED -> SUBMITTED); invoice tidak punya status REJECTED.
        'invoice-verify' => ['invoice-cash-receipt.verify', 'invoice-cash-receipt.revise'],
        'invoice-approve'=> ['invoice-cash-receipt.approve', 'invoice-cash-receipt.revise'],
        // Purchase Order — "Reject" = Revise ke DRAFT (PO tidak punya status REJECTED).
        'po-submitted'   => ['project.verify', 'project.finance-revise'],
        'po-verified'    => ['project.approve', 'project.finance-revise'],
        'po-closing'     => ['project.verify-closing', 'project.reject-closing'],
        'bp-closing-verify' => ['budget-plan.verify-closing', 'budget-plan.reject-closing'],
        // Batch Draft -> ajukan ke Approver (DRAFT -> PENDING_APPROVER). Hapus draft tetap di halaman batch.
        'batch-draft'    => ['daily-transaction.submit', null],
    ];

    /**
     * Status yang wajib dimiliki dokumen agar boleh diproses lewat section
     * tersebut. Dicek di bulk() sebelum memanggil aksi satuan, sehingga aksi
     * yang menerima lebih dari satu status (mis. project.finance-revise menerima
     * SUBMITTED & VERIFIED) tidak bisa dipakai lintas tahap lewat section lain.
     */
    private const BULK_EXPECTED_STATUS = [
        'po-submitted'      => [Project::class, 'SUBMITTED'],
        'po-verified'       => [Project::class, 'VERIFIED'],
        'po-closing'        => [Project::class, 'CLOSING'],
        'invoice-verify'    => [Invoice::class, 'SUBMITTED'],
        'invoice-approve'   => [Invoice::class, 'VERIFIED'],
    ];

    /**
     * Input tambahan yang dibutuhkan aksi satuan saat dipanggil dari bulk.
     * Approve PO dari Todo List selalu keputusan APPROVE (status akhir dipilih
     * sistem: ACTIVE / WAITING_PO) — sama dengan ProjectController::bulkProses().
     * Early Start tetap lewat halaman Purchase Order.
     */
    private const BULK_EXTRA_INPUT = [
        'po-verified' => ['approve' => ['decision' => 'APPROVE']],
    ];

    /**
     * Scope Cost Request untuk Todo List: scopeByRole() + Access Group untuk
     * role yang di kode diperlakukan istimewa tapi BUKAN bagian dari K3
     * (admin, guest). Role K3 melihat semua; head/manager/supervisor dst.
     * sudah dibatasi scopeByRole(). Dokumen non-proyek (overhead/payroll)
     * tetap mengikuti aturan overhead/payroll yang sudah ada.
     */
    private function scopeCrTodo($query): void
    {
        $this->scopeByRole($query);

        $role = strtolower(Auth::user()->effectiveRole()?->name ?? '');
        if (in_array($role, TodoService::ISTIMEWA_DI_KODE_BUKAN_K3, true)) {
            $projectIds = Auth::user()->accessibleProjectIds();
            $query->where(function ($q) use ($projectIds) {
                $q->whereHas('budgetPlan', fn ($b) => $b->whereNull('project_id'))
                  ->orWhereHas('budgetPlan.project', fn ($p) => $p->whereIn('id', $projectIds));
            });
        }
    }

    /**
     * Query dasar satu antrean (belum di-eager-load, belum di-get): status dari
     * TodoService::QUEUES + scope visibilitas. Dipakai buildWorklistData()
     * (isi halaman) DAN pendingCounts() (badge/pengingat) — satu definisi.
     * Gerbang role dicek pemanggil lewat TodoService::allows().
     *
     * Wajib dipanggil dengan Auth::user() = user yang dihitung (scope helper
     * membaca Auth); pendingCounts() memastikan itu.
     */
    public function queueQuery(string $key)
    {
        $status = TodoService::statuses($key);
        $user   = Auth::user();

        switch ($key) {
            case 'head': case 'cost_control_checked': case 'ceo':
            case 'headSettlementCheck': case 'financeSettlementVerify':
            case 'ceoSettlementClose': case 'approverSecondApprove':
                // Antrean approval: CR milik sendiri tidak bisa diaksi (kecuali
                // Superadmin), jadi tidak ikut dihitung — sama dengan buildStatusGroups().
                return tap(BudgetRequest::query()->whereIn('status', $status), fn ($q) => $this->scopeCrTodo($q))
                    ->excludeSelfApprovalFor($user);

            case 'managerRejected':
                return tap(BudgetRequest::query()->whereIn('status', $status), fn ($q) => $this->scopeCrTodo($q));

            case 'managerPaid':
                $q = tap(BudgetRequest::query()->whereIn('status', $status), fn ($q) => $this->scopeCrTodo($q));

                // Settlement CR dari pengajuan dana baru diisi pemohon di halaman tiket.
                return \App\Models\TroubleTicketRequest::supportsNumber()
                    ? $q->whereNotIn('id', \App\Models\TroubleTicketRequest::query()
                        ->whereNotNull('request_number')->whereNotNull('budget_request_id')->select('budget_request_id'))
                    : $q;

            case 'secondPaymentPending':
                return BudgetRequest::query()
                    ->whereHas('secondPayments', fn ($q) => $q->where('status', 'PENDING'));

            case 'treasuryBatchCreate':
                // Query sama persis dengan DailyTransactionController::create().
                return tap(
                    BudgetRequest::query()
                        ->whereIn('status', $status)
                        ->whereDoesntHave('dailyTransactionItems', fn ($q) =>
                            $q->where('status', 'INCLUDED')
                              ->whereHas('dailyTransaction', fn ($q2) =>
                                  $q2->whereIn('status', \App\Models\DailyTransaction::ACTIVE_STATUSES)
                              )
                        ),
                    fn ($q) => $this->scopeCrTodo($q)
                );

            case 'treasuryBatchSecond':
                return \App\Models\BudgetRequestSecondPayment::availableForBatch()
                    ->whereHas('budgetRequest', fn ($q) => $this->scopeCrTodo($q));

            case 'budgetingHeadCheck': case 'budgetingVerify': case 'budgetingCeoApprove':
            case 'budgetingClosingDue': case 'budgetingRejected': case 'budgetingClosingVerify':
                $q = BudgetPlan::query()->tap(fn ($q) => $this->scopeVisibleTo($q))->whereIn('status', $status);
                // CLOSING yang berkas closing-nya belum diajukan (sisa data lama).
                if ($key === 'budgetingClosingDue') {
                    $q->whereNull('closing_submitted_at');
                }
                // CLOSING yang sudah diajukan manager — giliran Finance (verifyClosing()).
                if ($key === 'budgetingClosingVerify') {
                    $q->whereNotNull('closing_submitted_at');
                }
                // Budget dari form PO (via_po_approval) ikut approval PO — tugasnya sudah
                // muncul sebagai tugas PO, jadi tidak diulang di antrean Budgeting.
                // Closing tetap alur biasa.
                if (! in_array($key, ['budgetingClosingDue', 'budgetingClosingVerify'], true)) {
                    $q->where('via_po_approval', false);
                }

                return $q;

            case 'treasuryProcess': case 'treasuryReadyToPay': case 'treasuryDraftSubmit':
                $q = DailyTransaction::query()->whereIn('status', $status)->where('type', 'CASH_OUT');
                // Batch dibatasi Access Group lewat CR di dalamnya untuk role non-K3 (guest).
                if ($key === 'treasuryProcess' && TodoService::terbatasAccessGroup($user)) {
                    $q->whereHas('includedItems', fn ($i) =>
                        $i->whereHas('budgetRequest', fn ($c) => $this->scopeCrTodo($c))
                    );
                }

                return $q;

            case 'poSubmitted': case 'poVerified': case 'poClosingVerify':
                return Project::query()->whereIn('status', $status);

            case 'costRegisterApprove':
            case 'expenseSettleApprove':
            case 'expenseRejected':
                return \App\Models\CostRegister::query()->whereIn('status', $status);

            case 'expenseSettleInput':
                return \App\Models\CostRegister::query()->whereIn('status', $status)->where('is_cash_advance', true);

            case 'costRegisterBatch':
                // Query sama dengan $availableRegisters di DailyTransactionController::create().
                return \App\Models\CostRegister::query()
                    ->whereIn('status', $status)
                    ->whereDoesntHave('dailyTransactionItems', fn ($q) =>
                        $q->where('status', 'INCLUDED')
                          ->whereHas('dailyTransaction', fn ($q2) =>
                              $q2->whereIn('status', DailyTransaction::ACTIVE_STATUSES)
                          )
                    );

            case 'invoiceVerify': case 'invoiceApprove':
                return Invoice::query()->whereIn('status', $status);

            case 'collectionWaitingInvoice':
                // Dua cabang, digabung OR:
                //  1) BAST aktif yang belum punya invoice non-CANCELLED. Dikecualikan bila
                //     No. PO punya invoice lama aktif yang belum tertaut BAST: BAST-nya
                //     sintetis (hasil backfill) sehingga belum jadi dasar penagihan baru
                //     sampai invoice lamanya ditautkan (ProjectPoNumber::usesBastBilling()).
                //  2) No. PO lama tanpa baris BAST: aturan lama (status PARTIAL/BAST_SUBMITTED).
                //     TIDAK terkena pengecualian di atas — invoice terminnya memang tak
                //     bertaut BAST, dan itu keadaan normal untuk alur lama.
                return ProjectPoNumber::query()->where(function ($q) {
                    $q->where(fn ($b) => $b->tanpaInvoiceLamaBelumTertaut()
                            ->whereHas('basts', fn ($x) => $x->whereDoesntHave('activeInvoices')))
                      ->orWhere(fn ($l) => $l->whereDoesntHave('basts')->whereIn('status', ['PARTIAL', 'BAST_SUBMITTED']));
                });

            case 'ticketRequestReview':
                // Pengajuan biaya Trouble Ticket menunggu review: bukan buatan user ini; leader
                // hanya untuk bawahannya (cermin TroubleTicketWorkflow::canReviewRequest()),
                // superadmin semuanya.
                $q = \App\Models\TroubleTicketRequest::query()
                    ->whereIn('status', $status)
                    ->where('requested_by', '!=', $user->id);

                if (! $user->hasRole(\App\Support\AccessRoles::TROUBLE_TICKET_FULL_SCOPE)) {
                    $bawahan = app(\App\Services\OrgHierarchy::class)->subordinateIds((int) $user->id);
                    $q->whereHas('ticket', fn ($t) => $t->whereIn('assigned_to', $bawahan->all() ?: [0]));
                }

                return $q;

            case 'ticketAssigned':
                return \App\Models\TroubleTicket::query()->whereIn('status', $status)->where('assigned_to', $user->id);

            case 'ticketCloseApprove':
                // Cermin TroubleTicketWorkflow::canApproveClose().
                $q = \App\Models\TroubleTicket::query()->whereIn('status', $status)->where('assigned_to', '!=', $user->id);

                return $user->hasRole(\App\Support\AccessRoles::TROUBLE_TICKET_SUPERVISE)
                    ? $q
                    : $q->whereIn('assigned_to', app(\App\Services\OrgHierarchy::class)->subordinateIds((int) $user->id)->all() ?: [0]);

            case 'ticketFundingRevise':
                return \App\Models\TroubleTicketRequest::query()->whereIn('status', $status)->where('requested_by', $user->id)
                    ->whereHas('ticket', fn ($t) => $t->where('assigned_to', $user->id));

            case 'ticketSettlementDue':
                // Dana sudah cair dan belum dikirim; yang sedang direview bukan tugas pemohon.
                $q = \App\Models\TroubleTicketRequest::query()
                    ->where('requested_by', $user->id)
                    ->whereHas('budgetRequest', fn ($c) => $c->whereNull('deleted_at')->whereIn('status', $status)
                        ->whereIn('type_id', BudgetRequest::cashAdvanceTypeIds() ?: [0]));

                return \App\Models\TroubleTicketRequest::supportsSettlementReview()
                    ? $q->where(fn ($w) => $w->whereNull('settlement_review_status')
                        ->orWhere('settlement_review_status', '!=', \App\Models\TroubleTicketRequest::REVIEW_PENDING))
                    : $q;

            case 'ticketSettlementReview':
                // Cermin canReviewRequest(): bukan pemohon, dan atasan teknisi tiketnya.
                if (! \App\Models\TroubleTicketRequest::supportsSettlementReview()) {
                    return \App\Models\TroubleTicketRequest::query()->whereRaw('1 = 0');
                }

                $q = \App\Models\TroubleTicketRequest::query()
                    ->where('settlement_review_status', \App\Models\TroubleTicketRequest::REVIEW_PENDING)
                    ->where('requested_by', '!=', $user->id);

                if (! $user->hasRole(\App\Support\AccessRoles::TROUBLE_TICKET_FULL_SCOPE)) {
                    $bawahan = app(\App\Services\OrgHierarchy::class)->subordinateIds((int) $user->id);
                    $q->whereHas('ticket', fn ($t) => $t->whereIn('assigned_to', $bawahan->all() ?: [0]));
                }

                return $q;

            case 'ticketCrDraft':
                return tap(
                    BudgetRequest::query()->whereIn('status', $status)
                        ->whereIn('id', \App\Models\TroubleTicketRequest::query()->whereNotNull('budget_request_id')->select('budget_request_id')),
                    fn ($q) => $this->scopeCrTodo($q)
                );
        }

        throw new \InvalidArgumentException("Antrean Todo List tidak dikenal: {$key}");
    }

    /** Baris Todo seragam untuk satu antrean tiket: nomor, keterangan, dan tautan aksinya. */
    private function ticketActionRows(string $key, string $label)
    {
        $rows = $this->queueQuery($key)->get();
        $model = $rows->first();

        if ($model instanceof \App\Models\TroubleTicket) {
            $rows->load('site:id,tid,sitename');
        } elseif ($model instanceof \App\Models\TroubleTicketRequest) {
            $rows->load(['ticket.site:id,tid,sitename']);
        }

        return $rows->map(function ($r) use ($key, $label) {
            $tiket = $r instanceof \App\Models\TroubleTicket ? $r : ($r instanceof \App\Models\TroubleTicketRequest ? $r->ticket : null);

            $r->todo_label = $label;
            $r->todo_ref   = $r instanceof BudgetRequest ? $r->code : $tiket?->ticket_number;
            $r->todo_desc  = $r instanceof BudgetRequest ? $r->description : trim(($tiket?->site?->sitename ?? '') . ' ' . ($r->display_number ?? ''));
            $r->todo_url   = $r instanceof BudgetRequest
                ? route('budget-request.index')
                : ($tiket ? route('trouble-ticket.show', $tiket) . ($r instanceof \App\Models\TroubleTicketRequest ? '?tab=pengajuan' : '') : null);
            $r->todo_key   = $key;

            return $this->withAging($r, $r->updated_at);
        });
    }

    /**
     * Hitungan ringan per antrean (tanpa CoA/summary/respontime) untuk badge
     * sidebar & pengingat — lihat TodoService::pendingCounts().
     *
     * @return array<string,int>
     */
    public function pendingCounts(User $user): array
    {
        return $this->sebagai($user, function () use ($user) {
            $out = [];
            foreach (array_keys(TodoService::QUEUES) as $key) {
                if (TodoService::allows($user, $key)) {
                    $out[$key] = $this->queueQuery($key)->count();
                }
            }

            return $out;
        });
    }

    /**
     * Ada minimal satu tugas? Berhenti di antrean pertama yang berisi (exists()),
     * bukan menghitung semuanya. Definisi identik dengan $hasAnyTask di
     * buildWorklistData(): antrean yang ikut total (semua kecuali
     * treasuryBatchSecond).
     */
    public function anyPending(User $user): bool
    {
        return $this->sebagai($user, function () use ($user) {
            foreach (TodoService::QUEUES as $key => $def) {
                if (($def['total'] ?? true) && TodoService::allows($user, $key)
                    && $this->queueQuery($key)->exists()) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Jalankan $fn dengan Auth::user() = $user (scope helper membaca Auth;
     * command terjadwal tidak punya sesi), lalu pulihkan user sebelumnya.
     *
     * PERINGATAN View As: scope dan gerbang role membaca effectiveRole() milik
     * Auth::user(). Untuk user lain (bukan pemanggil) role efektifnya = role
     * aslinya; jangan memakai ini untuk "meniru" tampilan View As seseorang.
     * Auth global bersifat per-proses: jangan dipanggil bersamaan dari konteks
     * yang melayani banyak user dalam satu proses tanpa memulihkannya (finally).
     */
    public function sebagai(User $user, callable $fn)
    {
        $sebelumnya = Auth::user();
        $ganti      = $sebelumnya?->id !== $user->id;
        if ($ganti) {
            Auth::setUser($user);
        }

        try {
            return $fn();
        } finally {
            if ($ganti) {
                $sebelumnya ? Auth::setUser($sebelumnya) : Auth::forgetUser();
            }
        }
    }

    /**
     * GET /todo-list/has-pending — dipanggil berkala oleh badge sidebar.
     * Memakai TodoService::hasPending() — definisi antrean yang SAMA dengan
     * halaman Todo List (K1), bukan lagi daftar terpisah.
     */
    public function hasPending()
    {
        /** @var User $user */
        $user = Auth::user();

        return response()->json([
            'has_pending' => TodoService::hasPending($user),
        ]);
    }

    /** Batas jumlah item per request bulk — mencegah request panjang & timeout. */
    private const BULK_MAX_IDS = 50;

    /** POST /todo-list/bulk/{section}/{mode} — multi-select approve/reject. */
    public function bulk(Request $request, string $section, string $mode)
    {
        $routeName = self::BULK_ROUTES[$section][$mode === 'reject' ? 1 : 0] ?? null;
        abort_unless($routeName && in_array($mode, ['approve', 'reject'], true), 404);

        $request->validate([
            'ids'   => 'required|array|min:1|max:' . self::BULK_MAX_IDS,
            'ids.*' => 'required|integer',
        ], [
            'ids.max' => 'Maksimum ' . self::BULK_MAX_IDS . ' item per proses.',
        ]);

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

        if ($extra = self::BULK_EXTRA_INPUT[$section][$mode] ?? null) {
            $request->merge($extra);
        }
        [$modelClass, $expectedStatus] = self::BULK_EXPECTED_STATUS[$section] ?? [null, null];

        $ok = 0;
        $errors = [];
        foreach (array_unique((array) $request->input('ids', [])) as $id) {
            session()->forget(['success', 'error', 'warning']);
            if ($modelClass) {
                $statusNow = $modelClass::whereKey($id)->value('status');
                if ($statusNow !== $expectedStatus) {
                    $errors[] = '#' . $id . ': ' . ($statusNow === null
                        ? 'dokumen tidak ditemukan.'
                        : 'status sudah ' . $statusNow . ', bukan ' . $expectedStatus . ' — muat ulang.');
                    continue;
                }
            }
            try {
                $hasil = app()->call([$controller, $method], ['id' => $id]);
                if ($err = session('error') ?: session('warning')) {
                    $errors[] = '#' . $id . ': ' . $err;
                } elseif ($hasil instanceof \Illuminate\Http\JsonResponse
                    && ($hasil->getStatusCode() >= 400 || ($hasil->getData(true)['success'] ?? true) === false)) {
                    $errors[] = '#' . $id . ': ' . ($hasil->getData(true)['message'] ?? 'aksi ditolak.');
                } elseif (! $hasil instanceof \Illuminate\Http\RedirectResponse
                    && ! $hasil instanceof \Illuminate\Http\JsonResponse) {
                    $errors[] = '#' . $id . ': respons tidak terduga, hasil tidak dapat dipastikan.';
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
        session()->forget(['success', 'error', 'warning']);

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
     * GET|POST /todo-list/export — export Excel SEMUA antrean yang tampil untuk
     * user yang login (K5): Cost Request di semua tahap, Budgeting, Treasury,
     * Purchase Order, Collection, dan Invoice. Assignment TIDAK ikut export
     * (keputusan pemilik), baik dengan maupun tanpa filter.
     *
     * Barisnya diambil dari koleksi yang SAMA dengan halaman (buildWorklistData)
     * — bukan query ulang dengan aturan lain — sehingga gerbang role dan scope
     * Access Group otomatis sama.
     *
     * Penyaringan dari browser memakai kunci "modul:id" (mis. "cr:12",
     * "bp:7") karena id antar-modul bisa sama. Kunci dari browser hanya
     * MEMPERSEMPIT hasil setelah seluruh gerbang peran dilewati; tidak pernah
     * memperluasnya. Kunci yang formatnya tidak valid diabaikan, dan bila
     * browser mengirim kunci tetapi tak satu pun valid hasilnya kosong.
     */
    public function exportList(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if ($request->has('ids')) {
            $dikirim = collect((array) $request->input('ids', []));
            $valid   = fn ($v) => is_string($v) && preg_match('/^[a-z]+:\d+$/', $v);

            // Halaman lama (belum dimuat ulang) masih mengirim id polos. Beri
            // pesan jelas, jangan menghasilkan file kosong secara diam-diam.
            if ($dikirim->contains(fn ($v) => ! $valid($v))) {
                return redirect()->route('todo-list.index')->with(
                    'error',
                    'Format penyaringan export tidak dikenali (halaman versi lama). Muat ulang halaman Todo List lalu coba export lagi.'
                );
            }
        }

        $rows = $this->exportRows($user);

        if ($request->has('ids')) {
            $kunci = collect((array) $request->input('ids', []))->flip();
            $rows  = $rows->filter(fn ($r) => $kunci->has($r['key']))->values();
        }

        $columns = ['Modul', 'Tahap', 'Nomor', 'Referensi (PO/Budget Plan)', 'Deskripsi', 'Pemohon/Pemilik', 'Nilai', 'Status', 'Aging'];

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
        foreach ($rows as $r) {
            $sheet->fromArray([
                $r['modul'], $r['tahap'], $r['nomor'], $r['ref'], $r['desc'],
                $r['pemilik'], $r['nilai'], $r['status'], $r['aging'],
            ], null, 'A' . $row);
            $row++;
        }

        foreach (['A'=>20,'B'=>38,'C'=>20,'D'=>36,'E'=>40,'F'=>22,'G'=>16,'H'=>16,'I'=>12] as $col => $width) {
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
     * Baris export seragam dari seluruh antrean user. Label tahap = judul
     * antrean di halaman. 'key' = "modul:id" untuk penyaringan dari browser
     * (atribut data-export-key pada baris Blade).
     *
     * @return \Illuminate\Support\Collection<int,array>
     */
    private function exportRows(User $user): \Illuminate\Support\Collection
    {
        $d    = $this->buildWorklistData($user, denganCoa: false);
        $rows = collect();

        $baris = fn (string $modul, string $tahap, string $key, $nomor, $ref, $desc, $pemilik, $nilai, $status, $aging) => [
            'modul' => $modul, 'tahap' => $tahap, 'key' => $key, 'nomor' => $nomor,
            'ref' => $ref ?: '-', 'desc' => $desc ?: '-', 'pemilik' => $pemilik ?: '-',
            'nilai' => $nilai === null ? null : (int) $nilai, 'status' => $status, 'aging' => $aging ?: '-',
        ];
        $ratakan = fn ($grup) => collect($grup)
            ->flatMap(fn ($po) => collect($po['plans'])->flatMap(fn ($g) => $g['items']));

        // Cost Request — satu pemetaan untuk semua tahap.
        $cr = function (string $tahap, $items, ?callable $nilai = null) use (&$rows, $baris) {
            // Relasi yang dibaca per baris dimuat sekaligus (tidak menimpa yang sudah ada).
            $items = \Illuminate\Database\Eloquent\Collection::make($items)
                ->loadMissing(['applicant', 'budgetPlan.project.manager', 'budgetPlan.costCenter']);
            foreach ($items as $c) {
                $rows->push($baris(
                    'Cost Request', $tahap, 'cr:' . $c->id, $c->code,
                    $this->budgetPlanLabel($c->budgetPlan), $c->description,
                    $c->applicant?->name ?? $c->pm_internal_name,
                    $nilai ? $nilai($c) : $c->amount, $c->status, $c->aging_label
                ));
            }
        };

        foreach (['head', 'cost_control_checked', 'ceo'] as $k) {
            if (isset($d['sections'][$k])) {
                $cr($d['sections'][$k]['title'], $ratakan($d['sections'][$k]['groups']));
            }
        }
        $cr('Reject Handling', $d['managerRejected']);
        $cr('Submit Settlement', $d['managerPaid']);
        $cr('Settlement — Head Check', $ratakan($d['headSettlementCheck']));
        $cr('Settlement — Finance Verify', $ratakan($d['financeSettlementVerify']));
        $cr('Settlement — Finance Manager Close', $ratakan($d['ceoSettlementClose']));
        $cr('Expense Claim — Second Approve', $ratakan($d['approverSecondApprove']), fn ($c) => $c->expenseClaimAmount());
        $cr('Second Approval — Menunggu Pembayaran', $d['secondPaymentPending'], fn ($c) => $c->pending_payment?->amount);
        $cr('Treasury — Buat Batch Pembayaran', $d['treasuryBatchCreate']);

        foreach ($d['treasuryBatchSecond'] as $sp) {
            $rows->push($baris(
                'Treasury', 'Treasury — Buat Batch Pembayaran (Second Payment)', 'sp:' . $sp->id, $sp->cr_code,
                $sp->po_label, $sp->cr_desc, $sp->beneficiary_label, $sp->amount, $sp->status,
                $sp->created_at ? (int) $sp->created_at->diffInDays(now()) . ' hari' : '-'
            ));
        }

        // Budgeting
        foreach ([
            ['budgetingHeadCheck', 'Budgeting — Menunggu Approval Head'],
            ['budgetingVerify', 'Budgeting — Menunggu Verifikasi Finance'],
            ['budgetingCeoApprove', 'Budgeting — Menunggu Approval CEO'],
            ['budgetingClosingDue', 'Budgeting — Ajukan Closing (CLOSING belum diajukan)'],
            ['budgetingClosingVerify', 'Budgeting — Menunggu Verifikasi Closing Finance'],
            ['budgetingRejected', 'Budgeting — Ditolak (Revise ke Draft)'],
        ] as [$k, $tahap]) {
            foreach ($d[$k] as $bp) {
                $rows->push($baris(
                    'Budgeting', $tahap, 'bp:' . $bp->id, $bp->code, $this->budgetPlanLabel($bp),
                    $bp->description, $bp->project?->manager?->name, $bp->amount, $bp->status, $bp->aging_label
                ));
            }
        }

        // Treasury — batch
        foreach ([['treasuryDraftSubmit', 'Treasury — Draft Belum Diajukan'], ['treasuryProcess', 'Treasury — Menunggu Proses'], ['treasuryReadyToPay', 'Treasury — Siap Dibayar']] as [$k, $tahap]) {
            $batches = \Illuminate\Database\Eloquent\Collection::make($d[$k])->loadMissing(['sourceFund', 'createdBy']);
            foreach ($batches as $batch) {
                $rows->push($baris(
                    'Treasury', $tahap, 'dt:' . $batch->id, $batch->code, $batch->sourceFund?->name,
                    $batch->description, $batch->createdBy?->name, $batch->total_amount, $batch->status, $batch->aging_label
                ));
            }
        }

        // Purchase Order
        foreach ($d['poSubmitted'] as $po) {
            $rows->push($baris(
                'Purchase Order', 'Purchase Order — Menunggu Approval Finance', 'po:' . $po->id, $po->code,
                $po->customer?->name, $po->name, $po->manager?->name, $po->value, $po->status, $po->aging_label
            ));
        }

        foreach ($d['poVerified'] as $po) {
            $rows->push($baris(
                'Purchase Order', 'Purchase Order — Menunggu Approval Approver', 'po:' . $po->id, $po->code,
                $po->customer?->name, $po->name, $po->manager?->name, $po->value, $po->status, $po->aging_label
            ));
        }

        foreach ($d['poClosingVerify'] as $po) {
            $rows->push($baris(
                'Purchase Order', 'Purchase Order — Menunggu Verifikasi Closing', 'po:' . $po->id, $po->code,
                $po->customer?->name, $po->name, $po->manager?->name, $po->value, $po->status, $po->aging_label
            ));
        }

        // Expense (dulu Cost Register)
        foreach ([['costRegisterApprove', 'Expense — Menunggu Approval'], ['costRegisterBatch', 'Expense — Menunggu Dibatch Treasury'],
                  ['expenseSettleInput', 'Expense CA — Menunggu Input Settlement'], ['expenseSettleApprove', 'Expense CA — Menunggu Approval Settlement'], ['expenseRejected', 'Expense — Ditolak CEO']] as [$k, $tahap]) {
            foreach ($d[$k] ?? [] as $reg) {
                $rows->push($baris(
                    'Expense', $tahap, 'reg:' . $reg->id, $reg->display_code, $reg->target_label,
                    $reg->type_label . ($reg->notes ? ' — ' . $reg->notes : ''), $reg->inputBy?->name, $reg->amount, $reg->status, $reg->aging_label
                ));
            }
        }

        // Collection — sisa nilai (= ProjectPoNumber::remainingValue()) dihitung
        // dengan SATU query agregat untuk semua No. PO, bukan satu query per baris.
        $poCollection = \Illuminate\Database\Eloquent\Collection::make($d['collectionWaitingInvoice'])
            ->loadMissing(['project.customer']);
        $tertagih = $poCollection->isEmpty() ? collect() : Invoice::query()
            ->where('status', '!=', 'CANCELLED')
            ->whereIn('project_po_number_id', $poCollection->pluck('id'))
            ->selectRaw('project_po_number_id, SUM(billed_amount) AS total')
            ->groupBy('project_po_number_id')
            ->pluck('total', 'project_po_number_id');
        foreach ($poCollection as $pn) {
            $rows->push($baris(
                'Collection', 'Collection — Waiting Invoice', 'ponum:' . $pn->id, $pn->po_number,
                $pn->project?->code, $pn->project?->name, $pn->project?->customer?->name,
                (int) $pn->nominal - (int) ($tertagih[$pn->id] ?? 0), $pn->status, $pn->aging_label
            ));
        }

        // Trouble Ticket — pengajuan biaya menunggu review
        $pengajuanTt = \Illuminate\Database\Eloquent\Collection::make($d['ticketRequestReview'])
            ->loadMissing(['ticket.site', 'requestType', 'requester', 'budgetPlan']);
        foreach ($pengajuanTt as $r) {
            $rows->push($baris(
                'Trouble Ticket', 'Trouble Ticket — Review Pengajuan', 'ttreq:' . $r->id, $r->ticket?->ticket_number,
                $r->budgetPlan?->code, $r->description, $r->requester?->name, $r->total, $r->status, $r->aging_label
            ));
        }

        foreach (\Illuminate\Support\Collection::make($d['ticketActions']) as $r) {
            $rows->push($baris(
                'Trouble Ticket', 'Trouble Ticket — ' . $r->todo_label, $r->todo_key . ':' . $r->id, $r->todo_ref,
                null, $r->todo_desc, null, $r->total ?? $r->amount ?? 0, $r->status, $r->aging_label
            ));
        }

        // Invoice
        foreach ([['invoiceVerify', 'Invoice — Menunggu Verifikasi'], ['invoiceApprove', 'Invoice — Menunggu Approval']] as [$k, $tahap]) {
            $invoices = \Illuminate\Database\Eloquent\Collection::make($d[$k])->loadMissing(['po.customer', 'submittedBy']);
            foreach ($invoices as $inv) {
                $rows->push($baris(
                    'Invoice', $tahap, 'inv:' . $inv->id, $inv->code, $inv->po?->name, $inv->po?->customer?->name,
                    $inv->submittedBy?->name ?? null, $inv->billed_amount, $inv->status, $inv->aging_label
                ));
            }
        }

        return $rows;
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
        $this->scopeCrTodo($query);
        // Semua pemanggil adalah antrean approval: CR milik sendiri tidak bisa
        // diaksi (BudgetRequest::isSelfApprovalBlockedFor), jadi tidak ditampilkan
        // sebagai tugas — identik dengan queueQuery() untuk badge/pengingat.
        $query->excludeSelfApprovalFor(Auth::user());

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
