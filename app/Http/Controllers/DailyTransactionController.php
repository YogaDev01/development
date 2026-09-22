<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use App\Models\DailyTransaction;
use App\Models\DailyTransactionItem;
use App\Models\DailyTransactionAttachment;
use App\Models\BudgetRequest;
use App\Models\User;
use App\Services\NotifyService;
use App\Services\RoleDirectory;

class DailyTransactionController extends Controller
{
    use \App\Http\Controllers\Concerns\CapsLargeLists;

    /** Role Approver — penerima notifikasi batch Treasury baru. */
    private function approverUsers()
    {
        // Satu sumber daftar peran: App\Services\RoleDirectory.
        return app(RoleDirectory::class)->approvers();
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $dateFrom = $request->filled('date_from') ? $request->date_from : now()->startOfMonth()->format('Y-m-d');
        $dateTo   = $request->filled('date_to')   ? $request->date_to   : now()->format('Y-m-d');

        $query = DailyTransaction::with([
            'createdBy',
            'approvedBy',
            'paidBy',
            'includedItems.budgetRequest',
            'sourceFund',
            'toSourceFund',
        ])
            /*
            | Perbandingan langsung pada kolom, bukan whereDate(). Kolom `date`
            | memang bertipe DATE, jadi DATE(date) tidak mengubah hasil apa pun —
            | yang diubahnya cuma satu hal: fungsi di sisi kolom membuat MySQL
            | tidak bisa memakai indeks. Alasan yang sama sudah ditulis di
            | BudgetRequestController::index().
            */
            ->where('date', '>=', $dateFrom)
            ->where('date', '<=', $dateTo);

        /*
        | Ringkasan per status dihitung lewat satu query agregat, BUKAN dari
        | koleksi baris. Versi lama menghitungnya dengan $transactions->count()
        | dkk, yang berarti seluruh batch dalam rentang tanggal harus dihidrasi
        | jadi model lebih dulu — dan begitu barisnya dibatasi (di bawah),
        | angka kartu akan ikut terpotong dan berbohong. Agregat di sisi
        | basis data tetap benar berapa pun batas barisnya.
        */
        $statusCounts = (clone $query)->toBase()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        /*
        | Batas render baris — lihat Concerns\CapsLargeLists. Diurutkan
        | created_at desc supaya urutannya sama persis dengan ->latest()
        | yang dipakai sebelumnya.
        */
        [$transactions, $truncated, $total] = $this->cappedRows($query, 'created_at');
        $maxRows = $this->maxRows;

        $summary = [
            'total'            => $total,
            'pending_approver' => (int) ($statusCounts['PENDING_APPROVER'] ?? 0),
            'done'             => (int) ($statusCounts['DONE'] ?? 0),
            'paid'             => (int) ($statusCounts['PAID'] ?? 0),
            'rejected'         => (int) ($statusCounts['REJECTED'] ?? 0),
        ];

        return view('daily-transaction.index', compact(
            'transactions', 'summary', 'dateFrom', 'dateTo', 'truncated', 'total', 'maxRows'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE — halaman terpisah untuk pembuatan batch baru
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        // CR eligible untuk dibatch: APPROVED (sudah di-approve individual oleh
        // CEO — lihat BudgetRequestController::ceoApprove()), bukan lagi VERIFIED.
        $availableCRs = BudgetRequest::where('status', 'APPROVED')
            ->whereDoesntHave('dailyTransactionItems', fn($q) =>
                // whereNull: baris Second Payment juga menunjuk ke CR ini, tapi
                // ia membayar kekurangan Settlement - bukan pengajuan awalnya.
                // Tanpa filter ini, CR yang punya Second Payment di batch aktif
                // akan dikira sudah dibatch.
                $q->where('status', 'INCLUDED')
                  ->whereNull('second_payment_id')
                  ->whereHas('dailyTransaction', fn($q2) =>
                      $q2->whereIn('status', ['PENDING_APPROVER', 'DONE'])
                  )
            )
            ->with(['type', 'cluster', 'beneficiary', 'budgetPlan.project.customer', 'budgetPlan.project.manager', 'budgetPlan.costCenter'])
            ->latest()->get();

        // Cash Out hanya boleh dari rekening operasional (bukan Main Account —
        // Main Account khusus jadi sumber dana TOP_UP).
        $operationalSourceFunds = \App\Models\SourceFund::with('bank')
            ->where('status', 'ACTIVE')
            ->operational()
            ->orderBy('name')->get();

        // TOP_UP: "Dari" = Main Account, "Ke" = rekening operasional (sama set-nya
        // dengan pilihan Cash Out di atas).
        $mainSourceFunds = \App\Models\SourceFund::with('bank')
            ->where('status', 'ACTIVE')
            ->mainAccount()
            ->orderBy('name')->get();

        /*
        | MN-09 — biaya Non-CR yang siap masuk batch. Himpunannya sengaja sempit:
        | hanya Cost Register berstatus SUBMITTED yang belum ada di batch aktif.
        | Cost Register lama (yang di-backfill jadi PAID saat migrasi) tidak
        | ikut, karena itu catatan biaya yang sudah terjadi — bukan tagihan.
        */
        $availableRegisters = \App\Models\CostRegister::where('status', \App\Models\CostRegister::TREASURY_READY_STATUS)
            ->whereDoesntHave('dailyTransactionItems', fn ($q) =>
                $q->where('status', 'INCLUDED')
                  ->whereHas('dailyTransaction', fn ($q2) =>
                      $q2->whereIn('status', ['PENDING_APPROVER', 'DONE'])
                  )
            )
            ->with(['project.customer', 'beneficiary'])
            ->latest()->get();

        /*
        | Second Payment yang siap dibayar: PENDING dan belum ada di batch aktif.
        | Dulu baris ini hanya bisa ditandai PAID dari To-Do List sehingga uang
        | keluar tanpa jejak di Treasury - sekarang ia harus lewat batch seperti
        | pengeluaran lain.
        */
        $availableSecondPayments = \App\Models\BudgetRequestSecondPayment::availableForBatch()
            ->with(['budgetRequest.beneficiary', 'budgetRequest.budgetPlan.project.customer'])
            ->latest()->get();

        return view('daily-transaction.create', compact(
            'availableCRs', 'availableRegisters', 'availableSecondPayments',
            'operationalSourceFunds', 'mainSourceFunds'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $request->validate(['type' => 'required|in:CASH_OUT,TOP_UP']);

        if ($request->type === 'TOP_UP') {
            return $this->storeTopUp($request);
        }

        return $this->storeCashOut($request);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE — CASH OUT (perilaku lama, berbasis CR)
    |--------------------------------------------------------------------------
    */

    private function storeCashOut(Request $request)
    {
        $request->validate([
            // MN-09: satu batch boleh berisi Cost Request, Cost Register, atau
            // keduanya — jadi keduanya nullable, dan yang diwajibkan adalah
            // gabungannya tidak kosong (diperiksa tepat di bawah).
            'cr_ids'                => 'nullable|array',
            'cr_ids.*'              => 'exists:budget_request,id',
            'cost_register_ids'     => 'nullable|array',
            'cost_register_ids.*'   => 'exists:cost_registers,id',
            'second_payment_ids'    => 'nullable|array',
            'second_payment_ids.*'  => 'exists:budget_request_second_payments,id',
            'description'           => 'required|string|max:255',
            'source_fund_id'        => 'required|exists:source_funds,id',
            'notes'                 => 'nullable|string|max:500',
        ]);

        $crIds        = (array) $request->input('cr_ids', []);
        $registerIds  = (array) $request->input('cost_register_ids', []);
        $secondPayIds = (array) $request->input('second_payment_ids', []);

        if (empty($crIds) && empty($registerIds) && empty($secondPayIds)) {
            return back()->with('error', 'Pilih minimal satu Cost Request, Cost Register, atau Second Payment.');
        }

        // Second Payment: hanya yang PENDING dan belum ada di batch aktif.
        if ($secondPayIds) {
            $eligibleSecondPayIds = \App\Models\BudgetRequestSecondPayment::availableForBatch()
                ->whereIn('id', $secondPayIds)->pluck('id')->all();

            $invalidSecondPay = array_diff(
                array_map('intval', $secondPayIds),
                array_map('intval', $eligibleSecondPayIds)
            );

            if ($invalidSecondPay) {
                return back()->with('error',
                    'Second Payment berikut sudah PAID atau sudah ada dalam batch aktif: #'
                    . implode(', #', $invalidSecondPay)
                );
            }
        }

        // Cost Register yang boleh masuk batch hanya yang SUBMITTED dan belum
        // ada di batch aktif — aturan yang sama dengan Cost Request di bawah.
        if ($registerIds) {
            $invalidRegisters = \App\Models\CostRegister::whereIn('id', $registerIds)
                ->where('status', '!=', \App\Models\CostRegister::TREASURY_READY_STATUS)
                ->pluck('id');

            if ($invalidRegisters->isNotEmpty()) {
                return back()->with('error',
                    'Cost Register berikut tidak berstatus SUBMITTED: #' . $invalidRegisters->implode(', #')
                );
            }

            $registerBatched = \App\Models\CostRegister::whereIn('id', $registerIds)
                ->whereHas('dailyTransactionItems', fn ($q) =>
                    $q->where('status', 'INCLUDED')
                      ->whereHas('dailyTransaction', fn ($q2) =>
                          $q2->whereIn('status', ['PENDING_APPROVER', 'DONE'])
                      )
                )->pluck('id');

            if ($registerBatched->isNotEmpty()) {
                return back()->with('error',
                    'Cost Register berikut sudah ada dalam batch aktif: #' . $registerBatched->implode(', #')
                );
            }
        }

        $invalidCRs = BudgetRequest::whereIn('id', $crIds)
            ->where('status', '!=', 'APPROVED')->pluck('code');

        if ($invalidCRs->isNotEmpty()) {
            return back()->with('error',
                'CR berikut tidak berstatus APPROVED: ' . $invalidCRs->implode(', ')
            );
        }

        $alreadyBatched = BudgetRequest::whereIn('id', $crIds)
            ->whereHas('dailyTransactionItems', fn($q) =>
                // Baris Second Payment tidak dihitung: ia membayar kekurangan
                // Settlement, bukan pengajuan awal CR ini.
                $q->where('status', 'INCLUDED')
                  ->whereNull('second_payment_id')
                  ->whereHas('dailyTransaction', fn($q2) =>
                      $q2->whereIn('status', ['PENDING_APPROVER', 'DONE'])
                  )
            )->pluck('code');

        if ($alreadyBatched->isNotEmpty()) {
            return back()->with('error',
                'CR berikut sudah ada dalam batch aktif: ' . $alreadyBatched->implode(', ')
            );
        }

        if ($request->filled('source_fund_id')) {
            $fund = \App\Models\SourceFund::find($request->source_fund_id);
            if ($fund && $fund->is_main_account) {
                return back()->with('error', 'Source Fund untuk Cash Out tidak boleh Main Account — Main Account khusus untuk Top Up.');
            }
        }

        $code = DailyTransaction::generateCode(count($crIds) + count($registerIds) + count($secondPayIds));
        $crs  = BudgetRequest::whereIn('id', $crIds)->get()->keyBy('id');

        DB::transaction(function () use ($request, $crIds, $registerIds, $secondPayIds, $code, $crs) {
            $batch = DailyTransaction::create([
                'code'            => $code,
                'date'            => now()->toDateString(),
                'description'     => $request->description ? strtoupper($request->description) : null,
                'type'            => 'CASH_OUT',
                'source_fund_id'  => $request->source_fund_id,
                'status'          => 'PENDING_APPROVER',
                'notes'           => $request->notes,
                'created_by'      => Auth::id(),
            ]);

            foreach ($crIds as $crId) {
                $batch->items()->create([
                    'budget_request_id' => $crId,
                    'status'            => 'INCLUDED',
                ]);

                // CR langsung QUEUED begitu batch DIBUAT/di-submit oleh Treasury —
                // tidak lagi menunggu approve CEO. Approve CEO cuma menyetujui
                // batch-nya (buat pembayaran), tidak lagi mengubah status CR.
                $cr = $crs[$crId];
                $cr->update(['status' => 'QUEUED']);
                $cr->addLog('APPROVED', 'QUEUED', 'batched', $request->notes);
            }

            // MN-09 — baris Cost Register. Statusnya ikut QUEUED supaya tidak
            // bisa masuk ke dua batch sekaligus.
            foreach ($registerIds as $registerId) {
                $batch->items()->create([
                    'cost_register_id' => $registerId,
                    'status'           => 'INCLUDED',
                ]);

                \App\Models\CostRegister::where('id', $registerId)->update(['status' => 'QUEUED']);
            }

            /*
            | Second Payment - statusnya TIDAK berubah di sini (tetap PENDING
            | sampai batch benar-benar dibayar). Penguncian terhadap batch ganda
            | dilakukan lewat baris INCLUDED-nya, bukan lewat status.
            |
            | budget_request_id ikut diisi supaya baris ini punya Budget Plan,
            | penerima, dan rekening tujuan yang sama dengan CR induknya.
            */
            $secondPayments = \App\Models\BudgetRequestSecondPayment::whereIn('id', $secondPayIds)->get();
            foreach ($secondPayments as $sp) {
                $batch->items()->create([
                    'budget_request_id' => $sp->budget_request_id,
                    'second_payment_id' => $sp->id,
                    'status'            => 'INCLUDED',
                ]);

                $sp->budgetRequest?->addLog(
                    $sp->budgetRequest->status,
                    $sp->budgetRequest->status,
                    'second_payment_batched',
                    'Second Payment ' . $sp->amount_formatted . ' masuk batch ' . $batch->code
                        . ($request->notes ? ' - ' . $request->notes : '')
                );
            }

            $batch->recalculateCapturedAmount();
        });

        NotifyService::send(
            $this->approverUsers(),
            'Batch Treasury menunggu approval',
            Auth::user()->name . ' membuat batch ' . $code . ' ('
                . count($crIds) . ' CR'
                . ($registerIds ? ' + ' . count($registerIds) . ' Cost Register' : '')
                . ($secondPayIds ? ' + ' . count($secondPayIds) . ' Second Payment' : '')
                . ') untuk disetujui.',
            route('daily-transaction.index'),
            'workflow'
        );

        return redirect('/daily-transaction')
            ->with('success', 'Batch ' . $code . ' berhasil dibuat dengan '
                . count($crIds) . ' CR'
                . ($registerIds ? ', ' . count($registerIds) . ' Cost Register' : '')
                . ($secondPayIds ? ', ' . count($secondPayIds) . ' Second Payment' : '') . '.');
    }

    /*
    |--------------------------------------------------------------------------
    | STORE — TOP UP (transfer antar rekening perusahaan sendiri, tanpa CR)
    |--------------------------------------------------------------------------
    */

    private function storeTopUp(Request $request)
    {
        $request->validate([
            'from_source_fund_id' => 'required|exists:source_funds,id',
            'to_source_fund_id'   => 'required|different:from_source_fund_id|exists:source_funds,id',
            'amount'              => 'required',
            'description'         => 'required|string|max:255',
            'notes'               => 'nullable|string|max:500',
        ]);

        $fromFund = \App\Models\SourceFund::find($request->from_source_fund_id);
        if (!$fromFund || !$fromFund->is_main_account) {
            return back()->with('error', 'Rekening "Dari" untuk Top Up harus Main Account.');
        }

        $toFund = \App\Models\SourceFund::find($request->to_source_fund_id);
        if (!$toFund || $toFund->is_main_account) {
            return back()->with('error', 'Rekening "Ke" untuk Top Up harus rekening operasional (bukan Main Account).');
        }

        $amount = $this->normalizeMoney($request->amount);
        if ($amount <= 0) {
            return back()->with('error', 'Jumlah Top Up harus lebih dari 0.');
        }

        $code = DailyTransaction::generateCode(0);

        $batch = DailyTransaction::create([
            'code'              => $code,
            'date'              => now()->toDateString(),
            'description'       => $request->description ? strtoupper($request->description) : null,
            'type'              => 'TOP_UP',
            'source_fund_id'    => $fromFund->id,
            'to_source_fund_id' => $toFund->id,
            'captured_amount'   => $amount,
            'status'            => 'PENDING_APPROVER',
            'notes'             => $request->notes,
            'created_by'        => Auth::id(),
        ]);

        NotifyService::send(
            $this->approverUsers(),
            'Batch Top Up menunggu approval',
            Auth::user()->name . ' membuat Top Up ' . $code . ' dari ' . $fromFund->name . ' ke ' . $toFund->name . ' untuk disetujui.',
            route('daily-transaction.index'),
            'workflow'
        );

        return redirect('/daily-transaction')
            ->with('success', 'Top Up ' . $code . ' berhasil dibuat.');
    }

    /**
     * Normalisasi input uang berformat "1.234.567" atau "1234567" menjadi integer.
     */
    private function normalizeMoney($value): int
    {
        if (blank($value)) return 0;
        return (int) preg_replace('/[^\d]/', '', (string) $value);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW JSON
    |--------------------------------------------------------------------------
    */

    public function showJson($id)
    {
        /** @var DailyTransaction $batch */
        $batch = DailyTransaction::with([
            'createdBy', 'approvedBy', 'paidBy',
            'attachments.uploader',
            'items.budgetRequest.type',
            'items.budgetRequest.cluster',
            'items.budgetRequest.beneficiary.bank',
            'items.budgetRequest.applicant',
            'items.secondPayment',
            'sourceFund.bank',
            'toSourceFund.bank',
        ])->findOrFail($id);

        return response()->json([
            'id'                        => $batch->id,
            'code'                      => $batch->code,
            'date'                      => $batch->date->format('d M Y'),
            'description'               => $batch->description,
            'source_fund'               => $batch->sourceFund->name ?? '-',
            'source_fund_bank'          => $batch->sourceFund->bank->name ?? '-',
            'source_fund_no_account'    => $batch->sourceFund->no_account ?? '-',
            'charge_formatted'          => $batch->charge_formatted,
            'captured_amount_formatted' => $batch->captured_amount_formatted,
            'grand_total_formatted'     => $batch->grand_total_formatted,
            'status'                    => $batch->status,
            'status_label'           => $batch->status_label,
            'status_color'           => $batch->status_color,
            'notes'                  => $batch->notes,
            'payment_notes'          => $batch->payment_notes,
            'created_by'             => $batch->createdBy->name ?? '-',
            'approved_by'            => $batch->approvedBy->name ?? '-',
            'approved_at'            => $batch->approved_at?->format('d M Y H:i'),
            'paid_by'                => $batch->paidBy->name ?? '-',
            'paid_at'                => $batch->paid_at?->format('d M Y H:i'),
            'total_amount_formatted' => $batch->total_amount_formatted,
            'included_count'         => $batch->includedItems()->count(),
            'rejected_count'         => $batch->rejectedItems()->count(),
            'created_at'             => $batch->created_at->format('d M Y H:i'),

            'attachments' => $batch->attachments->map(fn($a) => [
                'original_name'  => $a->original_name,
                'path'           => $a->path,
                'size_formatted' => $a->size_formatted,
                'uploaded_by'    => $a->uploader->name ?? '-',
            ]),

            'items' => $batch->items->map(fn($item) => [
                'id'             => $item->id,
                'status'         => $item->status,
                'notes'          => $item->notes,
                // MN-09 — satu baris batch bisa berasal dari Cost Request ATAU
                // Cost Register, jadi nilainya dibaca lewat helper seragam di
                // DailyTransactionItem, bukan dengan merantai properti yang
                // bisa null.
                'cr_id'          => $item->budget_request_id,
                'is_cost_register' => $item->isCostRegister(),
                'is_second_payment' => $item->isSecondPayment(),
                'cr_code'        => $item->sourceCode(),
                'cr_description' => $item->sourceDescription(),
                'cr_type'        => $item->isSecondPayment()
                                        ? 'Second Payment'
                                        : ($item->isCostRegister()
                                            ? ($item->costRegister?->type_label ?? 'Cost Register')
                                            : ($item->budgetRequest?->type?->name ?? '-')),
                'cr_cluster'     => $item->isCostRegister()
                                        ? ($item->costRegister?->project?->code ?? '-')
                                        : ($item->budgetRequest?->cluster?->name ?? '-'),
                'cr_amount'      => $item->sourceAmount(),
                'cr_amount_fmt'  => number_format($item->sourceAmount(), 0, ',', '.'),
                'cr_applicant'   => $item->isCostRegister()
                                        ? ($item->costRegister?->inputBy?->name ?? '-')
                                        : ($item->budgetRequest?->applicant?->name ?? '-'),
                'cr_status'      => $item->isCostRegister()
                                        ? ($item->costRegister?->status ?? '-')
                                        : ($item->budgetRequest?->status ?? '-'),
            ]),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | APPROVE — CEO approve → CR jadi QUEUED
    |--------------------------------------------------------------------------
    */

    public function approve(Request $request, $id)
    {
        /** @var DailyTransaction $batch */
        $batch = DailyTransaction::with(['includedItems.budgetRequest', 'includedItems.costRegister'])->findOrFail($id);

        if (!$batch->isPendingApprover()) {
            return back()->with('error', 'Batch tidak dalam status PENDING_APPROVER.');
        }
        if ($batch->isCashOut() && $batch->includedItems()->count() === 0) {
            return back()->with('error', 'Tidak ada CR dalam batch ini.');
        }

        DB::transaction(function () use ($request, $batch) {
            // CR sudah QUEUED sejak batch ini dibuat/di-submit (lihat storeCashOut/
            // addItem) — approve CEO di sini hanya menyetujui BATCH-nya untuk
            // diproses pembayaran, status CR tidak berubah lagi. Tetap ditulis
            // log audit (status_from = status_to = QUEUED) supaya event approve
            // ini tetap terlihat di riwayat CR.
            foreach ($batch->includedItems as $item) {
                // Baris Cost Register (MN-09) tidak punya riwayat status CR.
                if (!$item->budgetRequest) continue;

                // Baris Second Payment: CR induknya sedang SETTLE_VERIFIED,
                // bukan QUEUED - menulis 'QUEUED' di sini akan memalsukan
                // riwayatnya, jadi statusnya dibaca apa adanya.
                if ($item->isSecondPayment()) {
                    $crStatus = $item->budgetRequest->status;
                    $item->budgetRequest->addLog(
                        $crStatus, $crStatus, 'second_payment_ceo_approved',
                        'Second Payment disetujui untuk dibayar pada batch ' . $batch->code
                            . ($request->notes ? ' - ' . $request->notes : '')
                    );
                    continue;
                }

                $item->budgetRequest->addLog('QUEUED', 'QUEUED', 'ceo_approved', $request->notes);
            }
            $batch->update([
                'status'      => 'DONE',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);
        });

        $applicants = $batch->includedItems->pluck('budgetRequest.applicant')->filter()->unique('id');
        NotifyService::send(
            $applicants,
            'Cost Request siap dibayar',
            'Batch ' . $batch->code . ' disetujui — CR Anda antre untuk pembayaran.',
            route('daily-transaction.index'),
            'workflow'
        );

        $successMsg = $batch->isTopUp()
            ? 'Top Up disetujui dan siap ditransfer.'
            : 'Batch disetujui. ' . $batch->includedItems()->count() . ' CR siap dibayar.';

        return redirect('/daily-transaction/' . $id)
            ->with('success', $successMsg);
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT BATCH — seluruh batch ditolak → CR kembali ke APPROVED
    |--------------------------------------------------------------------------
    */

    public function rejectBatch(Request $request, $id)
    {
        $request->validate(['notes' => 'required|string|max:500']);

        /** @var DailyTransaction $batch */
        $batch = DailyTransaction::with(['includedItems.budgetRequest', 'includedItems.costRegister'])->findOrFail($id);

        if (!$batch->isPendingApprover()) {
            return back()->with('error', 'Batch tidak dalam status PENDING_APPROVER.');
        }

        DB::transaction(function () use ($request, $batch) {
            foreach ($batch->includedItems as $item) {
                // CR sudah QUEUED sejak batch dibuat — batch ditolak berarti
                // dikembalikan lagi ke APPROVED (belum pernah masuk batch).
                if ($item->isCostRegister()) {
                    // Cost Register dikembalikan ke SUBMITTED supaya bisa
                    // dimasukkan ke batch lain (MN-09).
                    $item->costRegister?->update(['status' => \App\Models\CostRegister::TREASURY_READY_STATUS]);
                    $item->update(['status' => 'REJECTED', 'notes' => $request->notes]);
                    continue;
                }

                // Second Payment: status CR TIDAK diubah - kekurangan bayarnya
                // tetap ada dan tetap PENDING, hanya gagal di batch ini lalu
                // bisa dimasukkan ke batch berikutnya.
                if ($item->isSecondPayment()) {
                    $crStatus = $item->budgetRequest?->status;
                    $item->budgetRequest?->addLog(
                        $crStatus, $crStatus, 'second_payment_batch_rejected',
                        'Batch ' . $batch->code . ' ditolak: ' . $request->notes
                    );
                    $item->update(['status' => 'REJECTED', 'notes' => $request->notes]);
                    continue;
                }

                $item->budgetRequest->addLog('QUEUED', 'APPROVED', 'ceo_rejected', $request->notes);
                $item->budgetRequest->update(['status' => 'APPROVED']);
                $item->update(['status' => 'REJECTED', 'notes' => $request->notes]);
            }
            $batch->update([
                'status'      => 'REJECTED',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);
        });

        $applicants = $batch->includedItems->pluck('budgetRequest.applicant')->filter()->unique('id');
        NotifyService::send(
            $applicants,
            'Batch pembayaran ditolak',
            'Batch ' . $batch->code . ' ditolak: ' . $request->notes . '. CR Anda dikembalikan ke status APPROVED.',
            route('daily-transaction.index'),
            'workflow'
        );

        $successMsg = $batch->isTopUp()
            ? 'Top Up ditolak.'
            : 'Batch ditolak. Semua CR dikembalikan ke APPROVED.';

        return redirect('/daily-transaction')
            ->with('success', $successMsg);
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT ITEM — CEO takeout 1 CR → CR kembali ke VERIFIED
    |--------------------------------------------------------------------------
    */

    public function rejectItem(Request $request, $batchId, $itemId)
    {
        $request->validate(['notes' => 'required|string|max:500']);

        $batch = DailyTransaction::findOrFail($batchId);
        $item  = DailyTransactionItem::where('daily_transaction_id', $batchId)->findOrFail($itemId);

        if (!$batch->isPendingApprover()) {
            return back()->with('error', 'Batch tidak dalam status PENDING_APPROVER.');
        }
        if ($item->status !== 'INCLUDED') {
            return back()->with('error', 'Item ini sudah di-takeout.');
        }

        DB::transaction(function () use ($request, $batch, $item) {
            // CR ini sudah QUEUED sejak batch dibuat — takeout langsung menolak
            // CR-nya (bukan mengembalikan ke APPROVED dulu), sesuai perilaku lama.
            $item->update(['status' => 'REJECTED', 'notes' => $request->notes]);

            if ($item->isCostRegister()) {
                // Cost Register yang di-takeout kembali ke SUBMITTED (MN-09),
                // bukan REJECTED: biayanya tetap sah, hanya batch-nya berubah.
                $item->costRegister?->update(['status' => \App\Models\CostRegister::TREASURY_READY_STATUS]);
            } elseif ($item->isSecondPayment()) {
                // Sama seperti Cost Register: yang dikeluarkan dari batch cuma
                // barisnya. CR induk tidak boleh ikut REJECTED - Settlement-nya
                // sudah diverifikasi, yang tertunda hanya pembayarannya.
                $crStatus = $item->budgetRequest?->status;
                $item->budgetRequest?->addLog(
                    $crStatus, $crStatus, 'second_payment_takeout',
                    'Second Payment dikeluarkan dari batch ' . $batch->code . ': ' . $request->notes
                );
            } else {
                $item->budgetRequest->addLog('QUEUED', 'REJECTED', 'ceo_rejected', $request->notes);
                $item->budgetRequest->update(['status' => 'REJECTED']);
            }
            $batch->refreshCode();
            $batch->recalculateCapturedAmount();
        });

        if ($item->budgetRequest->applicant) {
            NotifyService::send(
                [$item->budgetRequest->applicant],
                'Cost Request ditolak',
                'CR ' . ($item->budgetRequest->code ?? '-') . ' ditolak (takeout dari batch ' . $batch->code . '): ' . $request->notes,
                route('daily-transaction.index'),
                'workflow'
            );
        }

        return redirect('/daily-transaction/' . $batchId)
            ->with('success', 'CR ' . ($item->budgetRequest->code ?? '-') . ' berhasil ditolak (takeout dari batch).');
    }

    /*
    |--------------------------------------------------------------------------
    | ADD ITEM
    |--------------------------------------------------------------------------
    */

    public function addItem(Request $request, $batchId)
    {
        $request->validate([
            'cr_ids'               => 'nullable|array',
            'cr_ids.*'             => 'exists:budget_request,id',
            'second_payment_ids'   => 'nullable|array',
            'second_payment_ids.*' => 'exists:budget_request_second_payments,id',
        ]);

        if (!$request->filled('cr_ids') && !$request->filled('second_payment_ids')) {
            return back()->with('error', 'Pilih minimal satu Cost Request atau Second Payment.');
        }

        $batch = DailyTransaction::findOrFail($batchId);

        if (!$batch->isPendingApprover()) {
            return back()->with('error', 'Hanya batch PENDING_APPROVER yang bisa ditambah item.');
        }

        $added = 0;

        DB::transaction(function () use ($request, $batch, &$added) {
            foreach ($request->cr_ids as $crId) {
                $cr = BudgetRequest::find($crId);
                if (!$cr || $cr->status !== 'APPROVED') continue;

                $alreadyIn = DailyTransactionItem::where('budget_request_id', $crId)
                    ->where('status', 'INCLUDED')
                    ->whereHas('dailyTransaction', fn($q) =>
                        $q->whereIn('status', ['PENDING_APPROVER', 'DONE'])
                    )->exists();

                if ($alreadyIn) continue;

                $batch->items()->create([
                    'budget_request_id' => $crId,
                    'status'            => 'INCLUDED',
                ]);

                // Sama seperti CR yang masuk batch sejak awal — langsung QUEUED
                // begitu ditambahkan ke batch yang sudah di-submit.
                $cr->update(['status' => 'QUEUED']);
                $cr->addLog('APPROVED', 'QUEUED', 'batched', null);
                $added++;
            }

            // Second Payment menyusul ke batch yang sama - aturannya identik
            // dengan saat batch dibuat (lihat storeCashOut).
            $secondPayments = \App\Models\BudgetRequestSecondPayment::availableForBatch()
                ->whereIn('id', (array) $request->input('second_payment_ids', []))
                ->get();

            foreach ($secondPayments as $sp) {
                $batch->items()->create([
                    'budget_request_id' => $sp->budget_request_id,
                    'second_payment_id' => $sp->id,
                    'status'            => 'INCLUDED',
                ]);

                $crStatus = $sp->budgetRequest?->status;
                $sp->budgetRequest?->addLog(
                    $crStatus, $crStatus, 'second_payment_batched',
                    'Second Payment ' . $sp->amount_formatted . ' masuk batch ' . $batch->code
                );
                $added++;
            }

            $batch->refreshCode();
            $batch->recalculateCapturedAmount();
        });

        return back()->with('success', $added . ' baris berhasil ditambahkan ke batch.');
    }

    /*
    |--------------------------------------------------------------------------
    | MARK PAID — Finance upload bukti + tandai PAID
    | Batch: DONE → PAID
    | CR QUEUED → PAID
    | Lampiran disimpan ke daily_transaction_attachments
    |--------------------------------------------------------------------------
    */

    public function markPaid(Request $request, $id)
    {
        $request->validate([
            'charge'          => 'nullable',
            'payment_notes'   => 'nullable|string|max:500',
            'attachments'     => 'required|array|min:1',
            'attachments.*'   => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        /** @var DailyTransaction $batch */
        $batch = DailyTransaction::with(['includedItems.budgetRequest', 'includedItems.costRegister'])->findOrFail($id);

        if (!$batch->isDone()) {
            return back()->with('error', 'Hanya batch DONE yang dapat ditandai PAID.');
        }

        $charge = $this->normalizeMoney($request->charge);

        DB::transaction(function () use ($request, $batch, $charge) {

            // Simpan lampiran ke daily_transaction_attachments
            foreach ($request->file('attachments', []) as $file) {
                if (!$file) continue;
                $path = $file->store('daily_transaction_attachments', 'secure');
                $batch->attachments()->create([
                    'original_name' => $file->getClientOriginalName(),
                    'stored_name'   => basename($path),
                    'path'          => $path,
                    'mime_type'     => $file->getClientMimeType(),
                    'size'          => $file->getSize(),
                    'uploaded_by'   => Auth::id(),
                ]);
            }

            // Treasury menandai batch sudah dibayar. Efek ke tiap CR tergantung tipe:
            //   - Cash Advance : QUEUED → PAID  (lanjut ke tahap realisasi/Settle)
            //   - tipe lain     : QUEUED → CLOSED (tidak ada tahap realisasi)
            foreach ($batch->includedItems as $item) {
                // MN-09 — baris Cost Register: tidak punya tahap realisasi,
                // jadi begitu batch dibayar ia langsung PAID dan selesai.
                if ($item->isCostRegister()) {
                    $register = $item->costRegister;
                    if ($register && $register->status === 'QUEUED') {
                        $register->update(['status' => 'PAID', 'paid_at' => now()]);
                    }
                    continue;
                }

                /*
                | Second Payment - inilah satu-satunya tempat ia jadi PAID.
                | Status CR induk tidak berubah (tetap SETTLE_VERIFIED); yang
                | terjadi adalah kekurangan bayarnya lunas, sehingga CEO baru
                | boleh Close - lihat BudgetRequestController::ceoCloseSettlement().
                */
                if ($item->isSecondPayment()) {
                    $sp = $item->secondPayment;
                    if (!$sp || $sp->status !== 'PENDING') continue;

                    $sp->update([
                        'status'  => 'PAID',
                        'paid_by' => Auth::id(),
                        'paid_at' => now(),
                        'notes'   => $request->payment_notes,
                    ]);

                    $crStatus = $item->budgetRequest?->status;
                    $item->budgetRequest?->addLog(
                        $crStatus, $crStatus, 'second_payment_paid',
                        'Second Payment ' . $sp->amount_formatted . ' dibayar lewat batch '
                            . $batch->code . ($request->payment_notes ? ' - ' . $request->payment_notes : '')
                    );
                    continue;
                }

                $cr = $item->budgetRequest;
                if (!$cr || $cr->status !== 'QUEUED') continue;

                if ($cr->isCashAdvance()) {
                    $cr->update([
                        'status'  => 'PAID',
                        'paid_at' => now(),
                    ]);
                    $cr->addLog('QUEUED', 'PAID', 'paid', $request->payment_notes);
                } else {
                    $cr->update([
                        'status'    => 'CLOSED',
                        'paid_at'   => now(),
                        'closed_at' => now(),
                    ]);
                    $cr->addLog('QUEUED', 'CLOSED', 'paid', $request->payment_notes);
                }
            }

            // Batch → PAID
            $batch->update([
                'status'        => 'PAID',
                'charge'        => $charge,
                'paid_by'       => Auth::id(),
                'paid_at'       => now(),
                'payment_notes' => $request->payment_notes,
            ]);

            $batch->recalculateCapturedAmount();
        });

        return redirect('/daily-transaction/' . $id)
            ->with('success', 'Batch ' . $batch->code . ' berhasil ditandai PAID.');
    }

    /**
     * Label Budget Plan untuk grouping: "Nama Customer - Nama Budget".
     * Fallback ke Cost Center bila budget internal (bukan project), lalu ke deskripsi saja.
     */
    /**
     * Label grup "Daftar CR dalam Batch": "{kode PO} - {deskripsi}" untuk budget
     * proyek (kode PO jadi acuan grouping, sama seperti Todo List), atau
     * "{Cost Center} - {deskripsi}" untuk budget overhead/payroll.
     */
    private function budgetPlanLabel(?\App\Models\BudgetPlan $bp): string
    {
        if (!$bp) return '— Tanpa Budget Plan —';

        $prefix = $bp->isProjectBudget()
            ? ($bp->project->code ?? null)
            : ($bp->costCenter->name ?? null);

        return $prefix ? $prefix . ' - ' . $bp->description : $bp->description;
    }

    /**
     * Breakdown Budget Plan (Chart of Account -> sub-item -> list CR) untuk satu grup
     * "Daftar CR dalam Batch" — dirender penuh di server (tanpa AJAX) supaya tampilannya
     * sama persis dengan tab Budget di modal detail CR: Price, Qty, Budget, Used, Request,
     * Variance. Kolom "Request" & list CR di level ke-4 hanya menghitung CR yang ADA DI
     * BATCH INI (bukan seluruh riwayat CR); item yang sudah di-takeout tetap terdaftar
     * (untuk audit) tapi tidak dihitung ke nilai Request.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\DailyTransactionItem>  $groupItems
     */
    private function buildCoaBreakdown(\App\Models\BudgetPlan $bp, $groupItems): \Illuminate\Support\Collection
    {
        $bp->loadMissing('details.account');

        // Kumpulkan baris pengajuan (CR detail) dari batch ini per budget_detail_id.
        $requestedByDetailId = [];
        foreach ($groupItems as $item) {
            // Baris Second Payment memakai CR yang sama dengan baris aslinya.
            // Kalau ikut dihitung, tiap rincian COA muncul dua kali padahal
            // pengajuannya cuma satu - yang dibayar ulang hanya selisihnya.
            if ($item->isSecondPayment()) continue;

            $cr = $item->budgetRequest;
            if (!$cr) continue;
            foreach ($cr->details as $d) {
                if (!$d->budget_detail_id) continue;
                if ($d->status === 'TAKEOUT') continue; // item dikeluarkan approval — tidak dihitung

                // Pastikan 'amount' selalu terisi (default 0) walau baris pertama untuk
                // budget_detail_id ini statusnya REJECTED — supaya tidak "Undefined array key".
                if (!isset($requestedByDetailId[$d->budget_detail_id])) {
                    $requestedByDetailId[$d->budget_detail_id] = ['amount' => 0, 'items' => [], 'detail_ids' => []];
                }

                $requestedByDetailId[$d->budget_detail_id]['items'][] = [
                    'cr_id'             => $cr->id,
                    'cr_code'           => $cr->code,
                    'item_id'           => $item->id,
                    'date_raw'          => $cr->created_at,
                    'date_formatted'    => $cr->created_at?->format('d M Y H:i') ?? '-',
                    'description'       => $d->notes ?: ($d->description ?: '-'),
                    'beneficiary'       => $cr->beneficiary->nickname ?? '-',
                    'price_formatted'   => number_format((int) $d->amount, 0, ',', '.'),
                    'qty'               => $d->qty,
                    'amount_formatted'  => number_format((int) $d->total, 0, ',', '.'),
                    'item_status'       => $item->status,
                ];
                $requestedByDetailId[$d->budget_detail_id]['detail_ids'][] = $d->id;
                if ($item->status !== 'REJECTED') {
                    $requestedByDetailId[$d->budget_detail_id]['amount'] += (int) $d->total;
                }
            }
        }

        return $bp->details
            ->groupBy(fn ($d) => $d->account->name ?? '-')
            ->map(function ($details, $accName) use ($requestedByDetailId) {
                $subItems = $details->map(function ($d) use ($requestedByDetailId) {
                    $used      = $d->usedAmount();
                    $reqData   = $requestedByDetailId[$d->id] ?? ['amount' => 0, 'items' => [], 'detail_ids' => []];
                    // Variance = Budget - Used - Request (bukan cuma Budget - Used), supaya
                    // mencerminkan sisa budget setelah memperhitungkan pengajuan yang sedang berjalan.
                    $remaining = (int) $d->total - $used - (int) $reqData['amount'];

                    // Riwayat CR yang sudah PAID/REALIZED/CLOSED untuk sub-item ini (di luar CR
                    // yang ada di batch saat ini) — supaya list CR menampilkan juga CR-CR lama
                    // yang menyumbang ke nilai "Used", bukan cuma pengajuan baru di batch ini.
                    $historyItems = $d->costRequestDetails()
                        ->whereNotIn('id', $reqData['detail_ids'] ?: [0])
                        ->whereHas('budgetRequest', fn ($q) => $q->whereIn('status', ['PAID', 'REALIZED', 'SETTLE_CHECKED', 'SETTLE_VERIFIED', 'CLOSED']))
                        ->with(['budgetRequest.beneficiary', 'realization'])
                        ->get()
                        ->map(function ($od) {
                            $cr     = $od->budgetRequest;
                            $amount = (int) ($od->realization->total ?? $od->total);

                            return [
                                'cr_id'             => $cr->id,
                                'cr_code'           => $cr->code,
                                'item_id'           => null,
                                'date_raw'          => $cr->created_at,
                                'date_formatted'    => $cr->created_at?->format('d M Y H:i') ?? '-',
                                'description'       => $od->notes ?: ($od->description ?: '-'),
                                'beneficiary'       => $cr->beneficiary->nickname ?? '-',
                                'price_formatted'   => number_format((int) $od->amount, 0, ',', '.'),
                                'qty'               => $od->qty,
                                'amount_formatted'  => number_format($amount, 0, ',', '.'),
                                'item_status'       => 'HISTORY',
                            ];
                        });

                    return [
                        'deskripsi'           => $d->deskripsi,
                        'amount_formatted'    => number_format($d->amount, 0, ',', '.'),
                        'qty'                 => $d->qty,
                        'unit'                => $d->unit,
                        'total'               => (int) $d->total,
                        'total_formatted'     => number_format($d->total, 0, ',', '.'),
                        'used'                => $used,
                        'used_formatted'      => number_format($used, 0, ',', '.'),
                        'remaining'           => $remaining,
                        'remaining_formatted' => number_format($remaining, 0, ',', '.'),
                        'request_amount'      => (int) $reqData['amount'],
                        'request_formatted'   => $reqData['amount'] > 0 ? number_format($reqData['amount'], 0, ',', '.') : null,
                        'cr_items'            => collect($reqData['items'])->concat($historyItems)
                            ->sortByDesc(fn ($i) => $i['date_raw'])
                            ->values(),
                    ];
                })->values();

                return [
                    'account'   => $accName,
                    'items'     => $subItems,
                    'budget'    => $subItems->sum('total'),
                    'used'      => $subItems->sum('used'),
                    'request'   => $subItems->sum('request_amount'),
                    'remaining' => $subItems->sum('remaining'),
                    'has_nob'   => $subItems->contains(fn ($i) => $i['used'] > $i['total']),
                ];
            })
            ->sortBy('account')
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT DATA — summary + detail per Budget Plan (tidak dikunci status batch)
    |--------------------------------------------------------------------------
    */

    public function exportData($id)
    {
        /** @var DailyTransaction $batch */
        $batch = DailyTransaction::with([
            'items.budgetRequest.budgetPlan.project.customer',
            'items.budgetRequest.budgetPlan.project.manager',
            'items.budgetRequest.budgetPlan.costCenter',
            'items.budgetRequest.applicant',
            'items.budgetRequest.beneficiary',
            // MN-09 — sheet Cost Register butuh relasi ini; tanpa eager-load,
            // tiap baris memicu 4 query sendiri saat file dibentuk.
            'items.costRegister.project.customer',
            'items.costRegister.beneficiary.bank',
        ])->findOrFail($id);

        if ($batch->isTopUp()) {
            return back()->with('error', 'Export Data tidak berlaku untuk batch Top Up (tidak berbasis CR).');
        }

        $included = $batch->items->where('status', 'INCLUDED');

        // Dua jenis baris dipisah sejak awal (MN-09): Cost Request punya Budget
        // Plan dan rantai approval, Cost Register tidak. Memaksa keduanya masuk
        // satu sheet berarti separuh kolomnya selalu kosong untuk salah satu
        // jenis — jadi masing-masing dapat sheet sendiri.
        $items           = $included->filter(fn ($i) => $i->budgetRequest);
        $registerItems   = $included->filter(fn ($i) => $i->isCostRegister());

        $groups = $items->groupBy(fn ($i) => $this->budgetPlanLabel($i->budgetRequest->budgetPlan));

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        /* SHEET 1 — SUMMARY */
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Summary');
        $sheet1->fromArray(['Budget Plan', 'Amount'], null, 'A1');
        foreach (['A', 'B'] as $col) {
            $sheet1->getStyle($col . '1')->getFont()->setBold(true);
            $sheet1->getStyle($col . '1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0D1B2A');
            $sheet1->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }
        $row = 2;
        foreach ($groups as $budgetPlanLabel => $groupItems) {
            $sheet1->setCellValue('A' . $row, $budgetPlanLabel);
            // sourceAmount(): baris Second Payment membayar selisihnya saja,
            // bukan nilai penuh CR - memakai $cr->amount di sini akan
            // menggelembungkan Summary.
            $sheet1->setCellValue('B' . $row, (int) $groupItems->sum(fn ($i) => $i->sourceAmount()));
            $row++;
        }

        // Cost Register masuk sebagai satu baris ringkas. Tanpa baris ini,
        // Summary tidak pernah sama dengan nilai batch pada batch campuran —
        // dan selisih diam-diam di lembar ringkasan adalah hal terakhir yang
        // boleh terjadi di dokumen pembayaran.
        if ($registerItems->isNotEmpty()) {
            $sheet1->setCellValue('A' . $row, 'Cost Register (Non-CR)');
            $sheet1->setCellValue('B' . $row, (int) $registerItems->sum(fn ($i) => $i->sourceAmount()));
            $row++;
        }

        $sheet1->setCellValue('A' . $row, 'TOTAL BATCH');
        $sheet1->setCellValue('B' . $row, (int) $included->sum(fn ($i) => $i->sourceAmount()));
        $sheet1->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);

        $sheet1->getColumnDimension('A')->setWidth(45);
        $sheet1->getColumnDimension('B')->setWidth(18);

        /* SHEET 2 — DETAIL */
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Detail');
        $headers2 = ['Budget Plan', 'Kode CR', 'Deskripsi', 'Pengaju', 'Penerima', 'Amount', 'Tanggal'];
        $sheet2->fromArray($headers2, null, 'A1');
        foreach (range('A', 'G') as $col) {
            $sheet2->getStyle($col . '1')->getFont()->setBold(true);
            $sheet2->getStyle($col . '1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0D1B2A');
            $sheet2->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }
        $row = 2;
        foreach ($groups as $budgetPlanLabel => $groupItems) {
            foreach ($groupItems as $item) {
                $cr = $item->budgetRequest;
                if (!$cr) continue; // baris Cost Register tidak masuk sheet CR

                $sheet2->fromArray([
                    $budgetPlanLabel,
                    $item->sourceCode(),
                    $item->sourceDescription(),
                    $cr->applicant->name ?? '-',
                    $cr->beneficiary->complete_name ?? '-',
                    (int) $item->sourceAmount(),
                    $cr->created_at?->format('d-m-Y'),
                ], null, 'A' . $row);
                $row++;
            }
        }
        foreach (['A'=>32,'B'=>18,'C'=>40,'D'=>18,'E'=>22,'F'=>16,'G'=>14] as $col => $width) {
            $sheet2->getColumnDimension($col)->setWidth($width);
        }

        /* SHEET 3 — COST REGISTER (MN-09)
           Hanya dibuat kalau batch ini memang memuat biaya Non-CR; batch yang
           isinya murni Cost Request tidak perlu membawa sheet kosong. */
        if ($registerItems->isNotEmpty()) {
            $sheet3 = $spreadsheet->createSheet();
            $sheet3->setTitle('Cost Register');

            $headers3 = ['Kode', 'PO', 'Customer', 'Tipe', 'Keterangan', 'Penerima', 'Bank', 'No. Rekening', 'Amount', 'Periode'];
            $sheet3->fromArray($headers3, null, 'A1');

            foreach (range('A', 'J') as $col) {
                $sheet3->getStyle($col . '1')->getFont()->setBold(true);
                $sheet3->getStyle($col . '1')->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('0D1B2A');
                $sheet3->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
            }

            $row = 2;
            foreach ($registerItems as $item) {
                $reg = $item->costRegister;
                if (!$reg) continue;

                $sheet3->fromArray([
                    $item->sourceCode(),
                    $reg->project->code ?? '-',
                    $reg->project->customer->name ?? '-',
                    $reg->type_label,
                    $reg->notes ?? '-',
                    $reg->beneficiary->complete_name ?? '-',
                    $reg->beneficiary->bank->name ?? '-',
                    $reg->beneficiary->account_number ?? '-',
                    (int) $reg->amount,
                    $reg->period?->format('m-Y') ?? '-',
                ], null, 'A' . $row);
                $row++;
            }

            // Baris total: dokumen pembayaran selalu dibaca dari angka paling
            // bawah lebih dulu.
            $sheet3->setCellValue('H' . $row, 'TOTAL');
            $sheet3->setCellValue('I' . $row, (int) $registerItems->sum(fn ($i) => $i->sourceAmount()));
            $sheet3->getStyle('H' . $row . ':I' . $row)->getFont()->setBold(true);

            foreach (['A'=>16,'B'=>14,'C'=>26,'D'=>14,'E'=>40,'F'=>24,'G'=>16,'H'=>20,'I'=>16,'J'=>12] as $col => $width) {
                $sheet3->getColumnDimension($col)->setWidth($width);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'treasury_data_' . $batch->code . '_' . now()->format('Ymd_His') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        /** @var DailyTransaction $batch */
        $batch = DailyTransaction::with([
            'createdBy', 'approvedBy', 'paidBy',
            'attachments.uploader',
            'items.budgetRequest.type',
            'items.budgetRequest.cluster',
            'items.budgetRequest.beneficiary.bank',
            'items.budgetRequest.applicant',
            'items.budgetRequest.budgetPlan.project.customer',
            'items.budgetRequest.budgetPlan.project.manager',
            'items.budgetRequest.budgetPlan.costCenter',
            'items.budgetRequest.details',
            // MN-09 — baris Cost Register ikut di-eager-load; tanpa ini tiap
            // baris Non-CR memicu query sendiri saat dirender.
            'items.costRegister.beneficiary',
            'items.costRegister.project',
            'items.secondPayment',
            'sourceFund.bank',
            'toSourceFund.bank',
        ])->findOrFail($id);

        $groupedItems = $batch->items->groupBy(function ($item) {
            // Baris Cost Register tidak punya Budget Plan — dikumpulkan dalam
            // satu grup tersendiri supaya tidak tercampur ke grup "tanpa
            // budget plan" milik Cost Request (MN-09).
            if ($item->isCostRegister()) {
                return 'cost-register';
            }

            return $item->budgetRequest?->budget_plan_id ?? 0;
        })->map(function ($groupItems, $key) {
            $first = $groupItems->first();

            if ($key === 'cost-register') {
                return [
                    'label'      => 'Cost Register (Non-CR)',
                    'pm'         => '-',
                    'po_code'    => null,
                    'items'      => $groupItems,
                    'subtotal'   => $groupItems->where('status', '!=', 'REJECTED')
                                        ->sum(fn ($i) => $i->sourceAmount()),
                    // Cost Register tidak menempel ke baris budget mana pun,
                    // jadi tidak ada rincian COA untuk ditampilkan.
                    'coa_groups' => collect(),
                ];
            }

            $bp = $first->budgetRequest?->budgetPlan;

            return [
                'label'       => $this->budgetPlanLabel($bp),
                'pm'          => $first->budgetRequest?->pm_internal_name ?? '-',
                'po_code'     => $bp?->isProjectBudget() ? ($bp->project->code ?? null) : null,
                'items'       => $groupItems,
                'subtotal'    => $groupItems->where('status', '!=', 'REJECTED')
                                    ->sum(fn ($i) => $i->sourceAmount()),
                'coa_groups'  => $bp ? $this->buildCoaBreakdown($bp, $groupItems) : collect(),
            ];
        })->sortBy('label')->values();

        $availableCRs = BudgetRequest::where('status', 'APPROVED')
            ->whereDoesntHave('dailyTransactionItems', fn($q) =>
                // whereNull: baris Second Payment juga menunjuk ke CR ini, tapi
                // ia membayar kekurangan Settlement - bukan pengajuan awalnya.
                // Tanpa filter ini, CR yang punya Second Payment di batch aktif
                // akan dikira sudah dibatch.
                $q->where('status', 'INCLUDED')
                  ->whereNull('second_payment_id')
                  ->whereHas('dailyTransaction', fn($q2) =>
                      $q2->whereIn('status', ['PENDING_APPROVER', 'DONE'])
                  )
            )
            ->with(['type', 'cluster'])
            ->latest()->get();

        $availableSecondPayments = \App\Models\BudgetRequestSecondPayment::availableForBatch()
            ->with(['budgetRequest.beneficiary'])
            ->latest()->get();

        return view('daily-transaction.show', compact(
            'batch', 'availableCRs', 'availableSecondPayments', 'groupedItems'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT LIST — daftar batch (index), rentang tanggal opsional
    |--------------------------------------------------------------------------
    */

    public function exportList(Request $request)
    {
        $dateFrom = $request->filled('date_from') ? $request->date_from : now()->startOfMonth()->format('Y-m-d');
        $dateTo   = $request->filled('date_to')   ? $request->date_to   : now()->format('Y-m-d');

        /*
         | Export mengikuti hasil filter yang sedang aktif di tabel: browser
         | mengirim id baris yang lolos (lihat public/js/tesys-export-filtered.js).
         | Tanpa 'ids' — diakses langsung, atau memang tidak ada filter aktif —
         | perilakunya persis seperti sebelumnya: seluruh data.
         */
        $ids = collect((array) $request->input('ids', []))
            ->filter(fn ($v) => is_numeric($v))
            ->map(fn ($v) => (int) $v);

        $transactions = DailyTransaction::with(['sourceFund', 'toSourceFund', 'includedItems'])
            // Dengan daftar id, rentang tanggal TIDAK ikut membatasi: id itu sudah
            // berasal dari tabel yang rentangnya sama, dan menerapkan keduanya
            // hanya bisa membuang baris yang justru diminta user.
            ->when($ids->isNotEmpty(), fn ($q) => $q->whereIn('id', $ids))
            ->when($ids->isEmpty(), fn ($q) => $q
                ->whereDate('date', '>=', $dateFrom)
                ->whereDate('date', '<=', $dateTo))
            ->latest()->get();

        $columns = [
            'Kode Batch', 'Tanggal', 'Deskripsi', 'Type', 'Kategori', 'Qty CR / Tujuan Top Up',
            'Nilai Batch', 'Charge', 'Total', 'Status', 'Transaction ID / Payment Notes',
        ];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Batch');
        $sheet->fromArray($columns, null, 'A1');
        foreach (range('A', 'K') as $col) {
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0D1B2A');
            $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }

        $row = 2;
        foreach ($transactions as $trx) {
            // Kode Batch ditulis eksplisit sebagai STRING (bukan lewat fromArray()
            // yang auto-detect angka) — kodenya berupa deretan digit panjang
            // (mis. "202608310010065"), kalau dibiarkan Excel akan membacanya
            // sebagai angka dan menampilkannya dalam notasi ilmiah (2,026E+14).
            $sheet->setCellValueExplicit('A' . $row, $trx->code, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            // Tanggal batch di-PAID (bukan tanggal batch dibuat) — ditulis sebagai
            // nilai DATE asli (bisa di-sort/filter Excel sebagai tanggal), bukan
            // teks. Kosong kalau batch belum pernah dibayar (masih Submitted/Rejected).
            if ($trx->paid_at) {
                $sheet->setCellValue('B' . $row, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($trx->paid_at));
                $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
            } else {
                $sheet->setCellValue('B' . $row, '-');
            }

            // Kolom "Qty CR / Tujuan Top Up" tergantung tipe batch: Top Up -> nama
            // rekening tujuan transfer saja (tanpa nomor rekening), Cash Out ->
            // jumlah CR yang ada dalam batch (INCLUDED saja, tidak termasuk item
            // yang sudah di-takeout/REJECTED).
            $extra = $trx->isTopUp()
                ? ($trx->toSourceFund->name ?? '-')
                : $trx->includedItems->count();

            $sheet->fromArray([
                $trx->description ?? '-',
                $trx->type_label,
                $trx->sourceFund->name ?? '-',
                $extra,
                (int) $trx->captured_amount,
                (int) $trx->charge,
                $trx->grand_total,
                $trx->status_label,
                $trx->payment_notes ?? '-',
            ], null, 'C' . $row);
            $row++;
        }

        $sheet->getStyle('A2:A' . ($row - 1))->getNumberFormat()
            ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

        foreach (['A'=>18,'B'=>14,'C'=>40,'D'=>12,'E'=>20,'F'=>16,'G'=>14,'H'=>16,'I'=>14,'J'=>28,'K'=>28] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $filename = 'treasury_batch_' . $dateFrom . '_' . $dateTo . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT BATCH — file transfer bank (hanya batch APPROVED)
    |--------------------------------------------------------------------------
    */

    public function export($id)
    {
        /** @var DailyTransaction $batch */
        $batch = DailyTransaction::with([
            'items.budgetRequest.beneficiary.bank',
            'items.secondPayment',
        ])->findOrFail($id);

        // Export tersedia sebelum batch selesai diproses bank — begitu DONE/PAID,
        // file transfer dianggap sudah terkirim/selesai sehingga tidak perlu di-export lagi.
        if (in_array($batch->status, ['DONE', 'PAID'])) {
            return back()->with('error', 'Export tidak tersedia lagi setelah batch DONE/PAID.');
        }

        if ($batch->isTopUp()) {
            return back()->with('error', 'Export Batch (format transfer per-CR) tidak berlaku untuk Top Up.');
        }

        // Hanya item yang INCLUDED (yang di-takeout/REJECTED tidak ikut ditransfer)
        $items = $batch->items->where('status', 'INCLUDED');

        $columns = [
            'Transfer Method',
            'Account Number',
            'Currency',
            'Transfer Amount',
            'Beneficiary',
            'Bank',
            'Kode Bank',
            'Charge',
            'Reference',
            'Remark',
            'Email',
        ];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($columns, null, 'A1');

        $row = 2;
        foreach ($items as $item) {
            $cr = $item->budgetRequest;
            if (!$cr) continue;

            $benef = $cr->beneficiary;
            $bank  = $benef->bank ?? null;

            $sheet->fromArray([
                $bank->default_method ?? '',              // Transfer Method
                $benef->account_number ?? '',             // Account Number
                'IDR',                                    // Currency
                (int) $item->sourceAmount(),              // Transfer Amount (baris Second Payment = selisihnya saja)
                $benef->complete_name ?? '',              // Beneficiary
                $bank->name ?? '',                        // Bank
                $bank->code ?? '',                        // Kode Bank
                'OUR',                                     // Charge
                // Reference: kode CR tanpa prefix "CR-". Baris Second Payment
                // diberi akhiran -2 supaya bank tidak menolaknya sebagai
                // referensi ganda dengan transfer pertama.
                str_replace('CR-', '', $cr->code ?? '') . ($item->isSecondPayment() ? '-2' : ''),
                \Illuminate\Support\Str::limit($item->sourceDescription(), 150, ''), // Remark
                $cr->email ?? '',                         // Email notifikasi
            ], null, 'A' . $row);
            $row++;
        }

        $filename = 'batch_' . $batch->code . '_' . now()->format('Ymd_His') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}