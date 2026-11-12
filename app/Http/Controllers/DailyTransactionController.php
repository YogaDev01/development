<?php

namespace App\Http\Controllers;

use App\Services\TroubleTicketWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use App\Models\DailyTransaction;
use App\Models\DailyTransactionItem;
use App\Models\DailyTransactionAttachment;
use App\Models\BudgetRequest;
use App\Models\User;
use App\Models\UserActivityLog;
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
        /*
        | Dikelompokkan per tipe juga (Cash Out / Top Up) untuk tab di halaman
        | daftar; total per status tetap jumlah seluruh tipe, sama seperti dulu.
        */
        $perTipe = (clone $query)->toBase()
            ->selectRaw('type, status, COUNT(*) as c')
            ->groupBy('type', 'status')
            ->get();

        $statusCounts = $perTipe->groupBy('status')->map(fn ($g) => (int) $g->sum('c'));

        $summaryByType = [];
        foreach (DailyTransaction::TYPES as $tipe) {
            $baris = $perTipe->where('type', $tipe);
            $summaryByType[$tipe] = ['total' => (int) $baris->sum('c')];
            foreach (DailyTransaction::STATUSES as $st) {
                $summaryByType[$tipe][$st] = (int) $baris->where('status', $st)->sum('c');
            }
        }

        /*
        | Batas render baris — lihat Concerns\CapsLargeLists. Diurutkan
        | created_at desc supaya urutannya sama persis dengan ->latest()
        | yang dipakai sebelumnya.
        */
        [$transactions, $truncated, $total] = $this->cappedRows($query, 'created_at');
        $maxRows = $this->maxRows;

        $summary = [
            'total'            => $total,
            'draft'            => (int) ($statusCounts['DRAFT'] ?? 0),
            'pending_approver' => (int) ($statusCounts['PENDING_APPROVER'] ?? 0),
            'done'             => (int) ($statusCounts['DONE'] ?? 0),
            'paid'             => (int) ($statusCounts['PAID'] ?? 0),
            'rejected'         => (int) ($statusCounts['REJECTED'] ?? 0),
        ];

        return view('daily-transaction.index', compact(
            'transactions', 'summary', 'summaryByType', 'dateFrom', 'dateTo', 'truncated', 'total', 'maxRows'
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
                      $q2->whereIn('status', DailyTransaction::ACTIVE_STATUSES)
                  )
            )
            ->with(['type', 'cluster', 'beneficiary', 'budgetPlan.project.customer', 'budgetPlan.project.manager', 'budgetPlan.project.poNumbers', 'budgetPlan.costCenter'])
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
        | hanya Cost Register berstatus APPROVED yang belum ada di batch aktif.
        | Cost Register lama (yang di-backfill jadi PAID, kini CLOSED, saat migrasi) tidak
        | ikut, karena itu catatan biaya yang sudah terjadi — bukan tagihan.
        */
        $availableRegisters = \App\Models\CostRegister::where('status', \App\Models\CostRegister::TREASURY_READY_STATUS)
            ->whereDoesntHave('dailyTransactionItems', fn ($q) =>
                $q->where('status', 'INCLUDED')
                  ->whereHas('dailyTransaction', fn ($q2) =>
                      $q2->whereIn('status', DailyTransaction::ACTIVE_STATUSES)
                  )
            )
            ->with(['project.customer', 'beneficiary', 'details.project', 'details.costCenter', 'details.projects:projects.id,projects.code'])
            ->latest()->get();

        /*
        | Second Payment yang siap dibayar: PENDING dan belum ada di batch aktif.
        | Dulu baris ini hanya bisa ditandai PAID dari To-Do List sehingga uang
        | keluar tanpa jejak di Treasury - sekarang ia harus lewat batch seperti
        | pengeluaran lain.
        */
        $availableSecondPayments = \App\Models\BudgetRequestSecondPayment::availableForBatch()
            // project.manager: dikelompokkan per PM Internal bersama CR biasa.
            ->with(['budgetRequest.beneficiary', 'budgetRequest.budgetPlan.project.customer', 'budgetRequest.budgetPlan.project.manager', 'budgetRequest.budgetPlan.project.poNumbers'])
            ->latest()->get();

        // Expense Fase 2 — kekurangan bayar settlement Expense Cash Advance.
        $availableExpenseSecondPayments = \App\Models\CostRegisterSecondPayment::availableForBatch()
            ->with(['costRegister.beneficiary', 'costRegister.details.project', 'costRegister.details.costCenter', 'costRegister.details.projects:projects.id,projects.code'])
            ->latest()->get();

        return view('daily-transaction.create', compact(
            'availableCRs', 'availableRegisters', 'availableSecondPayments', 'availableExpenseSecondPayments',
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
            'expense_second_payment_ids'   => 'nullable|array',
            'expense_second_payment_ids.*' => 'exists:cost_register_second_payments,id',
            'description'           => 'required|string|max:255',
            'source_fund_id'        => 'required|exists:source_funds,id',
            'notes'                 => 'nullable|string|max:500',
        ]);

        $crIds        = (array) $request->input('cr_ids', []);
        $registerIds  = (array) $request->input('cost_register_ids', []);
        $secondPayIds = (array) $request->input('second_payment_ids', []);
        $expenseSpIds = array_map('intval', (array) $request->input('expense_second_payment_ids', []));

        if (empty($crIds) && empty($registerIds) && empty($secondPayIds) && empty($expenseSpIds)) {
            return back()->with('error', 'Pilih minimal satu Cost Request, Expense, atau Second Payment.');
        }

        // Second Payment Expense: hanya PENDING, induk SETTLE_APPROVED, belum di batch aktif.
        if ($expenseSpIds) {
            $eligible = \App\Models\CostRegisterSecondPayment::availableForBatch()
                ->whereIn('id', $expenseSpIds)->pluck('id')->map(fn ($v) => (int) $v)->all();
            $invalid = array_diff($expenseSpIds, $eligible);
            if ($invalid) {
                return back()->with('error',
                    'Second Payment Expense berikut sudah PAID atau sudah ada dalam batch aktif: #' . implode(', #', $invalid));
            }
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

        // Cost Register yang boleh masuk batch hanya yang APPROVED dan belum
        // ada di batch aktif — aturan yang sama dengan Cost Request di bawah.
        if ($registerIds) {
            $invalidRegisters = \App\Models\CostRegister::whereIn('id', $registerIds)
                ->where('status', '!=', \App\Models\CostRegister::TREASURY_READY_STATUS)
                ->pluck('id');

            if ($invalidRegisters->isNotEmpty()) {
                return back()->with('error',
                    'Cost Register berikut tidak berstatus APPROVED: #' . $invalidRegisters->implode(', #')
                );
            }

            $registerBatched = \App\Models\CostRegister::whereIn('id', $registerIds)
                ->whereHas('dailyTransactionItems', fn ($q) =>
                    $q->where('status', 'INCLUDED')
                      ->whereHas('dailyTransaction', fn ($q2) =>
                          $q2->whereIn('status', DailyTransaction::ACTIVE_STATUSES)
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
                      $q2->whereIn('status', DailyTransaction::ACTIVE_STATUSES)
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

        $code = DailyTransaction::generateCode(count($crIds) + count($registerIds) + count($secondPayIds) + count($expenseSpIds));

        try {
        $batchId = null;
        DB::transaction(function () use ($request, $crIds, $registerIds, $secondPayIds, $expenseSpIds, $code, &$batchId) {
            /*
            | Kunci dulu, baru tulis. Pemeriksaan di atas berjalan tanpa kunci,
            | jadi dua Treasury yang membuat batch bersamaan dari antrean yang
            | sama bisa sama-sama lolos dan satu CR masuk dua batch (dibayar dua
            | kali). Baris yang akan diubah dikunci FOR UPDATE lalu statusnya
            | diperiksa ulang dari hasil kunci: request kedua menunggu, melihat
            | status yang sudah berubah, dan seluruh transaksinya dibatalkan.
            |
            | Urutan kunci tetap (budget_request -> second payment -> cost
            | register, masing-masing id naik) supaya dua batch tidak deadlock.
            */
            $spParentIds = \App\Models\BudgetRequestSecondPayment::whereIn('id', $secondPayIds)
                ->pluck('budget_request_id')->all();

            $locked = BudgetRequest::whereIn('id', array_unique(array_merge($crIds, $spParentIds)))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $berubah = collect($crIds)
                ->reject(fn ($id) => ($locked[$id]->status ?? null) === 'APPROVED')
                ->map(fn ($id) => $locked[$id]->code ?? '#' . $id);

            $secondPayments = \App\Models\BudgetRequestSecondPayment::whereIn('id', $secondPayIds)
                ->orderBy('id')->lockForUpdate()->get();

            foreach ($secondPayments as $sp) {
                $induk = $locked[$sp->budget_request_id] ?? null;
                if ($sp->status !== 'PENDING' || $induk?->status !== 'SECOND_APPROVED') {
                    $berubah->push('Second Payment #' . $sp->id);
                }
            }

            $registers = \App\Models\CostRegister::whereIn('id', $registerIds)
                ->orderBy('id')->lockForUpdate()->get();

            foreach ($registers as $register) {
                if ($register->status !== \App\Models\CostRegister::TREASURY_READY_STATUS) {
                    $berubah->push('Cost Register #' . $register->id);
                }
            }

            // Second Payment Expense dikunci terakhir (id naik), status diperiksa ulang.
            $expenseSps = \App\Models\CostRegisterSecondPayment::with('costRegister')
                ->whereIn('id', $expenseSpIds)->orderBy('id')->lockForUpdate()->get();
            foreach ($expenseSps as $esp) {
                if ($esp->status !== 'PENDING' || $esp->costRegister?->status !== 'SETTLE_APPROVED') {
                    $berubah->push('Second Payment Expense #' . $esp->id);
                }
            }

            /*
            | Batch Draft tidak mengubah status sumbernya, jadi pemeriksaan
            | status saja tidak lagi menahan batch ganda. Keanggotaan di batch
            | aktif (termasuk Draft) diperiksa ulang DI DALAM kunci: request
            | kedua menunggu kunci baris di atas lalu melihat item yang baru
            | saja dibuat request pertama.
            */
            $sudahAda = DailyTransactionItem::where('status', 'INCLUDED')
                ->whereHas('dailyTransaction', fn ($q) => $q->whereIn('status', DailyTransaction::ACTIVE_STATUSES))
                ->where(function ($q) use ($crIds, $secondPayIds, $registerIds, $expenseSpIds) {
                    $q->where(fn ($q2) => $q2->whereIn('budget_request_id', $crIds ?: [0])->whereNull('second_payment_id'))
                      ->orWhereIn('second_payment_id', $secondPayIds ?: [0])
                      ->orWhere(fn ($q2) => $q2->whereIn('cost_register_id', $registerIds ?: [0])->whereNull('cost_register_second_payment_id'))
                      ->orWhereIn('cost_register_second_payment_id', $expenseSpIds ?: [0]);
                })
                ->exists();
            if ($sudahAda) {
                $berubah->push('item yang sudah ada di batch aktif');
            }

            if ($berubah->isNotEmpty()) {
                throw new \DomainException(
                    'Batch dibatalkan — baris berikut baru saja diproses pengguna lain: '
                    . $berubah->implode(', ') . '. Muat ulang halaman.'
                );
            }

            $batch = DailyTransaction::create([
                'code'            => $code,
                'date'            => now()->toDateString(),
                'description'     => $request->description ? strtoupper($request->description) : null,
                'type'            => 'CASH_OUT',
                'source_fund_id'  => $request->source_fund_id,
                // Draft: masih bisa diubah; status sumber baru berubah saat Submit.
                'status'          => 'DRAFT',
                'notes'           => $request->notes,
                'created_by'      => Auth::id(),
            ]);

            // Status sumber (CR -> QUEUED, Cost Register -> QUEUED, CR induk
            // Second Payment -> QUEUED) TIDAK diubah di sini; lihat submit().
            foreach ($crIds as $crId) {
                $batch->items()->create([
                    'budget_request_id' => $crId,
                    'status'            => 'INCLUDED',
                ]);
            }

            foreach ($registerIds as $registerId) {
                $batch->items()->create([
                    'cost_register_id' => $registerId,
                    'status'           => 'INCLUDED',
                ]);
            }

            foreach ($secondPayments as $sp) {
                $batch->items()->create([
                    'budget_request_id' => $sp->budget_request_id,
                    'second_payment_id' => $sp->id,
                    'status'            => 'INCLUDED',
                ]);
            }

            // Baris Second Payment Expense SEKALIGUS mengisi cost_register_id (Expense induk).
            foreach ($expenseSps as $esp) {
                $batch->items()->create([
                    'cost_register_id'                => $esp->cost_register_id,
                    'cost_register_second_payment_id' => $esp->id,
                    'status'                          => 'INCLUDED',
                ]);
            }

            $batchId = $batch->id;
            $batch->recalculateCapturedAmount();
        });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Notifikasi ke Approver dikirim saat Submit, bukan saat Draft dibuat.
        UserActivityLog::record('treasury', 'create', 'Membuat draft batch pembayaran #' . $code, null, null, $code);

        return redirect('/daily-transaction/' . $batchId)
            ->with('success', 'Draft batch ' . $code . ' dibuat dengan '
                . count($crIds) . ' CR'
                . ($registerIds ? ', ' . count($registerIds) . ' Cost Register' : '')
                . ($secondPayIds ? ', ' . count($secondPayIds) . ' Second Payment' : '')
                . '. Periksa lalu tekan Submit untuk meminta approval CEO.');
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
            'status'            => 'DRAFT',
            'notes'             => $request->notes,
            'created_by'        => Auth::id(),
        ]);

        // Notifikasi ke Approver dikirim saat Submit (lihat submit()).
        UserActivityLog::record('treasury', 'create', 'Membuat draft Top Up #' . $code, $batch);

        return redirect('/daily-transaction/' . $batch->id)
            ->with('success', 'Draft Top Up ' . $code . ' dibuat. Periksa lalu tekan Submit untuk meminta approval CEO.');
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

    /** Catatan chat dikunci begitu batch berakhir (PAID / REJECTED), seperti CR yang CLOSED. */
    private function canPostLogNote(DailyTransaction $batch): bool
    {
        return ! in_array($batch->status, ['PAID', 'REJECTED'], true);
    }

    /**
     * Tab Log drawer (gaya chat, sama dengan Cost Request): aktivitas batch dari
     * UserActivityLog modul treasury + catatan bebas, terbaru lebih dulu.
     * Tabel yang belum dimigrasi di server tidak boleh merusak detail batch.
     */
    private function logsFor(DailyTransaction $batch)
    {
        $logs = collect();

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('user_activity_logs')) {
                $logs = UserActivityLog::where('module', 'treasury')
                    ->where(function ($q) use ($batch) {
                        $q->where(fn ($s) => $s->where('subject_type', DailyTransaction::class)->where('subject_id', $batch->id))
                          ->orWhere('reference', $batch->code);
                    })
                    ->orderBy('created_at')
                    ->limit(100)
                    ->get()
                    ->map(fn ($l) => [
                        'kind'         => 'activity',
                        'action_label' => $l->action_label,
                        'action_color' => $l->action_color,
                        'actor'        => $l->actor_name ?? 'System',
                        'user_id'      => $l->user_id,
                        'description'  => $l->description,
                        'notes'        => $l->notes,
                        'at'           => $l->created_at,
                    ]);
            }

            $notes = \App\Models\DailyTransactionLogNote::where('daily_transaction_id', $batch->id)->get()
                ->map(fn ($n) => [
                    'kind'         => 'note',
                    'action_label' => 'Catatan',
                    'action_color' => 'secondary',
                    'actor'        => $n->actor_name ?? 'System',
                    'user_id'      => $n->acted_by,
                    'description'  => null,
                    'notes'        => $n->notes,
                    'at'           => $n->created_at,
                ]);
        } catch (\Throwable $e) {
            $notes = collect();
        }

        $semua   = $logs->concat($notes)->sortBy('at')->values();
        $avatars = User::avatarUrlMap($semua->pluck('user_id'));

        return $semua->map(fn ($l) => [
            'kind'         => $l['kind'],
            'action_label' => $l['action_label'],
            'action_color' => $l['action_color'],
            'actor'        => $l['actor'],
            'user_id'      => $l['user_id'],
            'avatar'       => $avatars[$l['user_id']] ?? null,
            'description'  => $l['description'],
            'notes'        => $l['notes'],
            'date'         => $l['at']?->format('d M Y H:i'),
        ])->all();
    }

    /** Kirim catatan ke tab Log (gaya chat). Ditutup untuk batch PAID / REJECTED. */
    public function storeLogNote(Request $request, $id)
    {
        $batch = DailyTransaction::findOrFail($id);

        $data = $request->validate(['notes' => 'required|string|max:1000']);

        if (! $this->canPostLogNote($batch)) {
            return response()->json(['message' => 'Batch sudah ' . $batch->status . '; catatan baru tidak dapat ditambahkan.'], 422);
        }

        $user = Auth::user();
        $note = \App\Models\DailyTransactionLogNote::create([
            'daily_transaction_id' => $batch->id,
            'acted_by'             => $user?->id,
            'actor_name'           => $user?->name ?? 'System',
            'actor_role'           => $user?->role?->name,
            'notes'                => trim($data['notes']),
        ]);

        return response()->json([
            'ok'  => true,
            'log' => [
                'kind'         => 'note',
                'action_label' => 'Catatan',
                'action_color' => 'secondary',
                'actor'        => $note->actor_name,
                'user_id'      => $note->acted_by,
                'avatar'       => $user?->avatar_url,
                'description'  => null,
                'notes'        => $note->notes,
                'date'         => now()->format('d M Y H:i'),
            ],
        ]);
    }

    private function isImagePath(?string $path): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', (string) $path);
    }

    /**
     * Evidence untuk panggung carousel di samping drawer: bukti pembayaran
     * batch, lalu lampiran tiap baris yang masih INCLUDED (lampiran CR, lampiran
     * per item CR, lampiran Cost Register). Berkas tetap dilayani lewat
     * SecureFileController (/storage/{path}) + FileAccess.
     */
    private function evidenceFor(DailyTransaction $batch): array
    {
        $out = [];
        $add = function (?string $path, ?string $name, string $source) use (&$out) {
            if (!$path) return;
            $out[] = [
                'name'     => $name ?: basename($path),
                'url'      => url('/storage/' . $path),
                'is_image' => $this->isImagePath($path),
                'is_pdf'   => (bool) preg_match('/\.pdf$/i', $path),
                'source'   => $source,
            ];
        };

        foreach ($batch->attachments as $a) {
            $add($a->path, $a->original_name, 'Bukti Pembayaran');
        }

        foreach ($batch->items->where('status', 'INCLUDED') as $item) {
            if ($item->isCostRegister()) {
                foreach ($item->costRegister?->attachments ?? [] as $a) {
                    $add($a->file_path, $a->title, $item->sourceCode());
                }
                continue;
            }

            // Baris Second Payment memakai CR yang sama — lampirannya tidak diulang.
            if ($item->isSecondPayment()) continue;

            $cr = $item->budgetRequest;
            if (!$cr) continue;
            foreach ($cr->attachments as $a) {
                $add($a->path, $a->original_name, $cr->code);
            }
            foreach ($cr->details as $d) {
                if ($d->status === 'TAKEOUT') continue;
                foreach ($d->attachments as $a) {
                    $add($a->path, $a->original_name, $cr->code . ' · ' . ($d->description ?: 'Item'));
                }
            }
        }

        return $out;
    }

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
            'items.budgetRequest.attachments',
            'items.budgetRequest.details.attachments',
            'items.costRegister.attachments',
            'items.secondPayment',
            'sourceFund.bank',
            'toSourceFund.bank',
        ])->findOrFail($id);

        $user     = Auth::user();
        $treasury = $user->hasRole(['superadmin', 'finance', 'finance_manager', 'treasury']);

        return response()->json([
            'id'                        => $batch->id,
            'code'                      => $batch->code,
            'date'                      => $batch->date->format('d M Y'),
            'description'               => $batch->description,
            'type'                      => $batch->type,
            'type_label'                => $batch->type_label,
            'is_top_up'                 => $batch->isTopUp(),
            'source_fund'               => $batch->sourceFund->name ?? '-',
            'source_fund_bank'          => $batch->sourceFund->bank->name ?? '-',
            'source_fund_no_account'    => $batch->sourceFund->no_account ?? '-',
            'to_source_fund'            => $batch->toSourceFund->name ?? '-',
            'to_source_fund_bank'       => $batch->toSourceFund->bank->name ?? '-',
            'to_source_fund_no_account' => $batch->toSourceFund->no_account ?? '-',

            /*
            | Aksi drawer daftar — gerbang peran & status disalin apa adanya dari
            | halaman show (daily-transaction/show.blade.php). Route tetap
            | memeriksa ulang; flag ini hanya menentukan tombol yang tampil.
            */
            'can' => [
                'approve'      => $batch->isPendingApprover() && $user->hasRole(['superadmin', 'approver']),
                'draft'        => $batch->isDraft() && $treasury,
                'mark_paid'    => $batch->isDone() && $treasury,
                'export_data'  => $batch->isCashOut(),
                'export_batch' => $batch->isCashOut() && !in_array($batch->status, ['DONE', 'PAID'], true),
            ],

            // Panggung evidence di samping drawer (gambar/PDF), lihat evidenceFor().
            'evidence' => $this->evidenceFor($batch),
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
                'is_image'       => $this->isImagePath($a->path),
            ]),

            /*
            | Riwayat batch untuk tab Log di drawer daftar Treasury: catatan
            | UserActivityLog modul treasury milik batch ini (dibuat, disetujui,
            | ditolak, dibayar). Baca saja; tabel belum ada = daftar kosong.
            */
            'logs'              => $this->logsFor($batch),
            'can_post_log_note' => $this->canPostLogNote($batch),

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
                'cr_type'        => ($item->isSecondPayment() || $item->isExpenseSecondPayment())
                                        ? 'Second Payment'
                                        : ($item->isCostRegister()
                                            ? ($item->costRegister?->type_label ?? 'Expense')
                                            : ($item->budgetRequest?->type?->name ?? '-')),
                'cr_cluster'     => $item->isCostRegister()
                                        ? ($item->costRegister?->target_label ?? '-')
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

        UserActivityLog::record('treasury', 'approve', 'Menyetujui batch pembayaran #' . $batch->code, $batch, $request->notes);

        return $this->redirectToBatch($request, $id)
            ->with('success', $successMsg);
    }

    /*
    |--------------------------------------------------------------------------
    | BULK APPROVE / REJECT BATCH — daftar Treasury (29 Okt 2026)
    |--------------------------------------------------------------------------
    | Tiap batch dijalankan lewat aksi satuannya (approve / rejectBatch), jadi
    | aturan, log, notifikasi, dan catatan aktivitas tetap satu sumber. Hanya
    | batch PENDING_APPROVER; `expected` (id => status yang terlihat di layar)
    | melewati batch yang statusnya sudah berubah. Maks 50 batch per proses.
    */

    private const BULK_MAX = 50;

    public function bulkApprove(Request $request)
    {
        return $this->bulkBatch($request, false);
    }

    public function bulkReject(Request $request)
    {
        $request->validate(['notes' => 'required|string|max:500']);

        return $this->bulkBatch($request, true);
    }

    private function bulkBatch(Request $request, bool $tolak)
    {
        $request->validate([
            'ids'        => 'required|array|min:1|max:' . self::BULK_MAX,
            'ids.*'      => 'integer',
            'expected'   => 'nullable|array',
            'expected.*' => 'nullable|string|max:30',
        ]);

        $ids      = array_values(array_unique(array_map('intval', (array) $request->input('ids', []))));
        $expected = (array) $request->input('expected', []);
        $batches  = DailyTransaction::whereIn('id', $ids)->get()->keyBy('id');

        $ok     = 0;
        $errors = [];

        foreach ($ids as $id) {
            $batch = $batches->get($id);
            if (! $batch) {
                $errors[] = '#' . $id . ' (tidak ditemukan)';
                continue;
            }

            $exp = $expected[$id] ?? null;
            if ($exp !== null && $exp !== $batch->status) {
                $errors[] = $batch->code . ' (status berubah dari ' . $exp . ' menjadi ' . $batch->status . ' — muat ulang)';
                continue;
            }
            if (! $batch->isPendingApprover()) {
                $errors[] = $batch->code . ' (status ' . $batch->status . ' bukan Submitted)';
                continue;
            }

            // Aksi satuan mengembalikan redirect + flash; hanya flash 'error'-nya yang dibaca.
            session()->forget(['success', 'error']);
            try {
                $tolak ? $this->rejectBatch($request, $id) : $this->approve($request, $id);
                $error = session('error');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
                $error = $e->getMessage() ?: 'akses ditolak.';
            }
            session()->forget(['success', 'error']);

            if ($error) {
                $errors[] = $batch->code . ' (' . $error . ')';
            } else {
                $ok++;
            }
        }

        $redirect = back();
        if ($ok) {
            $redirect->with('success', $ok . ' batch berhasil ' . ($tolak ? 'ditolak.' : 'di-approve.'));
        }
        if ($errors) {
            $redirect->with('error', count($errors) . ' batch dilewati: ' . implode(', ', array_slice($errors, 0, 5))
                . (count($errors) > 5 ? ', …' : '') . '.');
        }

        return $redirect;
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
                // Second Payment Expense: tetap PENDING, Expense induk tetap SETTLE_APPROVED
                // — bisa dimasukkan ke batch berikutnya.
                if ($item->isExpenseSecondPayment()) {
                    $item->costRegister?->addLog($item->costRegister->status, $item->costRegister->status, 'unbatched',
                        'Kekurangan bayar: batch ' . $batch->code . ' ditolak: ' . $request->notes);
                    $item->update(['status' => 'REJECTED', 'notes' => $request->notes]);
                    continue;
                }

                if ($item->isCostRegister()) {
                    // Cost Register dikembalikan ke APPROVED supaya bisa
                    // dimasukkan ke batch lain (MN-09).
                    $item->costRegister?->update(['status' => \App\Models\CostRegister::TREASURY_READY_STATUS]);
                    $item->costRegister?->addLog('QUEUED', \App\Models\CostRegister::TREASURY_READY_STATUS, 'unbatched',
                        'Batch ' . $batch->code . ' ditolak: ' . $request->notes);
                    $item->update(['status' => 'REJECTED', 'notes' => $request->notes]);
                    continue;
                }

                // Second Payment: kekurangan bayarnya tetap ada dan tetap
                // PENDING, hanya gagal di batch ini - CR induk dikembalikan ke
                // SECOND_APPROVED supaya bisa dimasukkan ke batch berikutnya.
                if ($item->isSecondPayment()) {
                    $this->releaseSecondPaymentParent(
                        $item->budgetRequest, 'second_payment_batch_rejected',
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

        UserActivityLog::record('treasury', 'reject', 'Menolak batch pembayaran #' . $batch->code, $batch, $request->notes);

        return ($request->boolean('from_index') ? back() : redirect('/daily-transaction'))
            ->with('success', $successMsg);
    }

    /**
     * Second Payment gagal di sebuah batch (batch ditolak / item dikeluarkan).
     *
     * Saat dibatch, CR induknya pindah SECOND_APPROVED -> QUEUED. Dulu status
     * itu dibiarkan QUEUED, padahal availableForBatch() mensyaratkan induk
     * SECOND_APPROVED - akibatnya Second Payment tidak pernah bisa dibatch
     * ulang dan CR tertahan di QUEUED tanpa jalan keluar. Cerminan dari CR
     * biasa yang dikembalikan ke APPROVED saat batch-nya ditolak.
     */
    private function releaseSecondPaymentParent(?BudgetRequest $cr, string $actionType, string $notes): void
    {
        if (! $cr) {
            return;
        }

        if ($cr->status === 'QUEUED') {
            $cr->update(['status' => 'SECOND_APPROVED']);
            $cr->addLog('QUEUED', 'SECOND_APPROVED', $actionType, $notes);

            return;
        }

        $cr->addLog($cr->status, $cr->status, $actionType, $notes);
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT ITEM — CEO takeout 1 CR → CR kembali ke VERIFIED
    |--------------------------------------------------------------------------
    */

    /**
     * Takeout Superadmin dari batch PAID: membalik efek markPaid() pada sumbernya,
     * HANYA bila sumber belum melangkah lebih jauh (belum ada realisasi/settlement).
     * Second Payment yang sudah dibayar tidak dibalik otomatis (ditolak).
     * Catatan: transfer bank yang sudah terjadi TIDAK ikut dibatalkan.
     */
    private function superTakeoutPaid(DailyTransaction $batch, DailyTransactionItem $item, string $notes): void
    {
        $catatan = 'Dikeluarkan Superadmin dari batch PAID ' . $batch->code . ': ' . $notes;

        if ($item->isSecondPayment() || $item->isExpenseSecondPayment()) {
            throw new \DomainException('Second Payment yang sudah dibayar tidak bisa dikeluarkan otomatis — status CR/Expense induknya sudah ditutup. Lakukan koreksi manual.');
        }

        if ($item->isCostRegister()) {
            $reg = \App\Models\CostRegister::whereKey($item->cost_register_id)->lockForUpdate()->first();
            $boleh = $reg && (($reg->isCashAdvance() && $reg->status === 'PAID') || (! $reg->isCashAdvance() && $reg->status === 'CLOSED'));
            if (! $boleh) {
                throw new \DomainException('Expense ' . ($reg->display_code ?? '#' . $item->cost_register_id) . ' sudah berstatus '
                    . ($reg->status ?? '-') . ' (realisasi/settlement sudah berjalan) — tidak bisa dikeluarkan otomatis.');
            }
            $dari = $reg->status;
            $reg->update(['status' => \App\Models\CostRegister::TREASURY_READY_STATUS, 'paid_at' => null]);
            $reg->addLog($dari, \App\Models\CostRegister::TREASURY_READY_STATUS, 'unbatched', $catatan);
        } else {
            $cr = BudgetRequest::whereKey($item->budget_request_id)->lockForUpdate()->first();
            $boleh = $cr && (($cr->isCashAdvance() && $cr->status === 'PAID') || (! $cr->isCashAdvance() && $cr->status === 'CLOSED'));
            if (! $boleh) {
                throw new \DomainException('Cost Request ' . ($cr->code ?? '#' . $item->budget_request_id) . ' sudah berstatus '
                    . ($cr->status ?? '-') . ' (realisasi/settlement sudah berjalan) — tidak bisa dikeluarkan otomatis.');
            }
            $dari = $cr->status;
            $cr->update(['status' => 'APPROVED', 'paid_at' => null, 'closed_at' => null]);
            $cr->addLog($dari, 'APPROVED', 'batch_takeout', $catatan);
        }

        $item->update(['status' => 'REJECTED', 'notes' => $notes]);
    }

    /**
     * Edit header batch oleh Superadmin di status apa pun (keputusan 2026-11-12):
     * tanggal, deskripsi, rekening, charge, catatan, catatan pembayaran. Isi batch
     * tidak disentuh. Rincian perubahan (lama -> baru) dicatat di log batch.
     */
    public function superUpdate(Request $request, $id)
    {
        abort_unless(Auth::user()->hasRole(['superadmin']), 403);

        $data = $request->validate([
            'date'              => 'required|date',
            'description'       => 'nullable|string|max:255',
            'source_fund_id'    => 'nullable|exists:source_funds,id',
            'to_source_fund_id' => 'nullable|different:source_fund_id|exists:source_funds,id',
            'charge'            => 'nullable',
            'notes'             => 'nullable|string|max:1000',
            'payment_notes'     => 'nullable|string|max:500',
        ]);

        $perubahan = DB::transaction(function () use ($data, $id) {
            $batch = DailyTransaction::whereKey($id)->lockForUpdate()->firstOrFail();
            $nama  = fn ($fid) => $fid ? (\App\Models\SourceFund::whereKey($fid)->value('name') ?? ('#' . $fid)) : '-';
            $baru  = [
                'date'              => $data['date'],
                'description'       => isset($data['description']) ? strtoupper(trim($data['description'])) : null,
                'source_fund_id'    => $data['source_fund_id'] ?? null,
                'to_source_fund_id' => $data['to_source_fund_id'] ?? null,
                'charge'            => $this->normalizeMoney($data['charge'] ?? null),
                'notes'             => $data['notes'] ?? null,
                'payment_notes'     => $data['payment_notes'] ?? null,
            ];
            $label = [
                'date' => 'Tanggal', 'description' => 'Deskripsi', 'source_fund_id' => 'Rekening sumber',
                'to_source_fund_id' => 'Rekening tujuan', 'charge' => 'Charge', 'notes' => 'Catatan', 'payment_notes' => 'Catatan pembayaran',
            ];
            $ubah = [];
            foreach ($baru as $k => $v) {
                $lama = $batch->getAttribute($k);
                $lama = $lama instanceof \DateTimeInterface ? $lama->format('Y-m-d') : $lama;
                if ((string) $lama === (string) $v) continue;
                $fmt = match ($k) {
                    'source_fund_id', 'to_source_fund_id' => $nama,
                    'charge' => fn ($x) => number_format((int) $x, 0, ',', '.'),
                    default  => fn ($x) => ($x === null || $x === '') ? '-' : (string) $x,
                };
                $ubah[] = $label[$k] . ': ' . $fmt($lama) . ' -> ' . $fmt($v);
            }
            if (! $ubah) {
                return [];
            }

            $batch->update($baru);
            \App\Models\DailyTransactionLogNote::create([
                'daily_transaction_id' => $batch->id,
                'acted_by'             => Auth::id(),
                'actor_name'           => Auth::user()?->name ?? 'System',
                'actor_role'           => Auth::user()?->role?->name,
                'notes'                => mb_substr('[EDIT SUPERADMIN - status ' . $batch->status . '] ' . implode('; ', $ubah), 0, 1000),
            ]);

            return $ubah;
        });

        if (! $perubahan) {
            return back()->with('success', 'Tidak ada perubahan pada header batch.');
        }

        UserActivityLog::record('treasury', 'update', '[Superadmin] Mengedit header batch #' . DailyTransaction::whereKey($id)->value('code'),
            DailyTransaction::find($id), implode('; ', $perubahan));

        return back()->with('success', 'Header batch diperbarui (' . count($perubahan) . ' perubahan, tercatat di log).');
    }

    public function rejectItem(Request $request, $batchId, $itemId)
    {
        return $this->removeItemFromBatch($request, $batchId, $itemId, false);
    }

    /**
     * REJECT (bukan takeout): CR pembayaran pertama → REJECTED; Second Payment →
     * CR induk SETTLE_REJECTED (Second Payment PENDING-nya dihapus, selisihnya
     * dihitung ulang setelah settlement direvisi — sama seperti
     * applySettlementRejectClose). Cost Register tidak punya status ditolak
     * di sini, jadi hanya bisa di-takeout.
     */
    public function rejectCrItem(Request $request, $batchId, $itemId)
    {
        return $this->removeItemFromBatch($request, $batchId, $itemId, true);
    }

    private function removeItemFromBatch(Request $request, $batchId, $itemId, bool $reject)
    {
        $request->validate(['notes' => 'required|string|max:500']);

        $batch = DailyTransaction::findOrFail($batchId);
        $item  = DailyTransactionItem::where('daily_transaction_id', $batchId)->findOrFail($itemId);

        // Superadmin boleh Takeout (bukan Reject) juga dari batch DONE / PAID
        // (keputusan 2026-11-12). PAID membalik status sumber -> lihat superTakeoutPaid().
        $superLanjut = ! $reject && ($batch->isDone() || $batch->isPaid()) && Auth::user()->hasRole(['superadmin']);
        if (!$batch->isPendingApprover() && ! $superLanjut) {
            return back()->with('error', 'Batch tidak dalam status PENDING_APPROVER.');
        }
        if ($item->status !== 'INCLUDED') {
            return back()->with('error', 'Item ini sudah dikeluarkan dari batch.');
        }
        if ($reject && $item->isCostRegister()) {
            return back()->with('error', 'Cost Register tidak bisa di-reject, gunakan Takeout.');
        }

        try {
        DB::transaction(function () use ($request, $batch, $item, $reject) {
            if ($batch->isPaid()) {
                // Batch sudah dibayar: status sumber sudah lewat QUEUED -> dibalik khusus.
                $this->superTakeoutPaid($batch, $item, $request->notes);
            } else {
            $item->update(['status' => 'REJECTED', 'notes' => $request->notes]);

            if ($reject && $item->isSecondPayment()) {
                $cr = $item->budgetRequest;
                $item->secondPayment?->delete();
                if ($cr->status === 'QUEUED') {
                    $cr->update(['status' => 'SETTLE_REJECTED']);
                }
                $cr->addLog('QUEUED', 'SETTLE_REJECTED', 'second_payment_rejected',
                    'Second Payment ditolak dari batch ' . $batch->code . ': ' . $request->notes);
            } elseif ($item->isExpenseSecondPayment()) {
                // Second Payment Expense: hanya barisnya yang keluar; tetap PENDING.
                $item->costRegister?->addLog($item->costRegister->status, $item->costRegister->status, 'unbatched',
                    'Kekurangan bayar dikeluarkan dari batch ' . $batch->code . ': ' . $request->notes);
            } elseif ($item->isCostRegister()) {
                // Cost Register yang di-takeout kembali ke APPROVED (MN-09),
                // bukan REJECTED: biayanya tetap sah, hanya batch-nya berubah.
                $item->costRegister?->update(['status' => \App\Models\CostRegister::TREASURY_READY_STATUS]);
                $item->costRegister?->addLog('QUEUED', \App\Models\CostRegister::TREASURY_READY_STATUS, 'unbatched',
                    'Dikeluarkan dari batch ' . $batch->code . ': ' . $request->notes);
            } elseif ($item->isSecondPayment()) {
                // Sama seperti Cost Register: yang dikeluarkan dari batch cuma
                // barisnya. CR induk tidak boleh ikut REJECTED - Settlement-nya
                // sudah diverifikasi, yang tertunda hanya pembayarannya - dan
                // dikembalikan ke SECOND_APPROVED supaya bisa dibatch ulang.
                $this->releaseSecondPaymentParent(
                    $item->budgetRequest, 'second_payment_takeout',
                    'Second Payment dikeluarkan dari batch ' . $batch->code . ': ' . $request->notes
                );
            } elseif ($reject) {
                $item->budgetRequest->addLog('QUEUED', 'REJECTED', 'batch_item_rejected', $request->notes);
                $item->budgetRequest->update(['status' => 'REJECTED']);
            } else {
                // Takeout: CR kembali ke APPROVED (belum pernah masuk batch),
                // cerminan reject batch, supaya bisa dibatch ulang.
                $item->budgetRequest->addLog('QUEUED', 'APPROVED', 'batch_takeout', $request->notes);
                $item->budgetRequest->update(['status' => 'APPROVED']);
            }
            }
            // Kode batch PAID sudah dipakai sebagai referensi transfer — jangan diganti.
            if (! $batch->isPaid()) {
                $batch->refreshCode();
            }
            $batch->recalculateCapturedAmount();

            if (! $batch->isPendingApprover()) {
                // Jejak di log batch: takeout Superadmin di luar tahap approval.
                \App\Models\DailyTransactionLogNote::create([
                    'daily_transaction_id' => $batch->id,
                    'acted_by'             => Auth::id(),
                    'actor_name'           => Auth::user()?->name ?? 'System',
                    'actor_role'           => Auth::user()?->role?->name,
                    'notes'                => '[TAKEOUT SUPERADMIN - batch ' . $batch->status . '] Item #' . $item->id
                        . ' (' . ($item->costRegister?->display_code ?? $item->budgetRequest?->code ?? '-') . ') dikeluarkan. '
                        . 'Captured amount sekarang ' . number_format((int) $batch->captured_amount, 0, ',', '.') . '. Alasan: ' . $request->notes,
                ]);
            }
        });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $cr = $item->budgetRequest;
        $kode = $cr?->code ?? '-';
        $secondPay = $item->isSecondPayment();
        if ($cr?->applicant) {
            if ($reject) {
                $judul = $secondPay ? 'Second Payment ditolak' : 'Cost Request ditolak';
                $pesan = 'CR ' . $kode . ($secondPay
                    ? ' ditolak dari batch ' . $batch->code . ' (Settle Rejected, mohon revisi settlement): '
                    : ' ditolak dari batch ' . $batch->code . ': ') . $request->notes;
            } else {
                $judul = 'CR dikeluarkan dari batch';
                $pesan = 'CR ' . $kode . ' dikeluarkan (takeout) dari batch ' . $batch->code
                    . ($cr->status === 'APPROVED' ? ' dan kembali ke status APPROVED' : '') . ': ' . $request->notes;
            }
            NotifyService::send([$cr->applicant], $judul, $pesan, route('daily-transaction.index'), 'workflow');
        }

        return $this->redirectToBatch($request, $batchId)
            ->with('success', 'CR ' . $kode . ($reject ? ' berhasil ditolak dari batch.' : ' berhasil di-takeout dari batch.'));
    }

    /*
    |--------------------------------------------------------------------------
    | DRAFT -> SUBMIT / HAPUS DRAFT / KELUARKAN ITEM DRAFT (29 Okt 2026)
    |--------------------------------------------------------------------------
    | Batch buatan Treasury lahir sebagai DRAFT: isinya masih bisa ditambah/
    | dikurangi dan CEO belum bisa meng-approve. Selama Draft, Cost Request,
    | Cost Register, dan CR induk Second Payment TIDAK berubah status (tetap
    | APPROVED / SUBMITTED / SECOND_APPROVED); keanggotaannya di batch Draft
    | sudah cukup mencegahnya masuk batch lain (DailyTransaction::ACTIVE_STATUSES).
    | Baru saat Submit status sumbernya dipindah ke QUEUED, persis seperti
    | dulu terjadi saat batch dibuat.
    */

    public function submit(Request $request, $id)
    {
        $batch = DailyTransaction::findOrFail($id);

        if (! $batch->isDraft()) {
            return back()->with('error', 'Hanya batch Draft yang bisa di-submit.');
        }
        if ($batch->isCashOut() && $batch->includedItems()->count() === 0) {
            return back()->with('error', 'Tambahkan minimal satu item sebelum submit.');
        }

        $jumlah = ['cr' => 0, 'register' => 0, 'sp' => 0];

        try {
            DB::transaction(function () use ($batch, &$jumlah) {
                // Batch dikunci dulu supaya dua submit bersamaan tidak sama-sama lolos.
                $terkunci = DailyTransaction::whereKey($batch->id)->lockForUpdate()->first();
                if (! $terkunci || ! $terkunci->isDraft()) {
                    throw new \DomainException('Status batch sudah berubah — muat ulang halaman.');
                }

                $items = $terkunci->includedItems()->get();

                $crIds       = $items->filter(fn ($i) => $i->budget_request_id && ! $i->second_payment_id)->pluck('budget_request_id')->map(fn ($v) => (int) $v)->all();
                $spIds       = $items->filter(fn ($i) => $i->second_payment_id)->pluck('second_payment_id')->map(fn ($v) => (int) $v)->all();
                $registerIds = $items->filter(fn ($i) => $i->cost_register_id && ! $i->cost_register_second_payment_id)->pluck('cost_register_id')->map(fn ($v) => (int) $v)->all();
                $expenseSpIds = $items->filter(fn ($i) => $i->cost_register_second_payment_id)->pluck('cost_register_second_payment_id')->map(fn ($v) => (int) $v)->all();

                // Urutan kunci sama dengan storeCashOut (budget_request -> second payment -> cost register).
                $spParentIds = \App\Models\BudgetRequestSecondPayment::whereIn('id', $spIds)->pluck('budget_request_id')->all();
                $locked = BudgetRequest::whereIn('id', array_unique(array_merge($crIds, $spParentIds)))
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $secondPayments = \App\Models\BudgetRequestSecondPayment::whereIn('id', $spIds)
                    ->orderBy('id')->lockForUpdate()->get();
                $registers = \App\Models\CostRegister::whereIn('id', $registerIds)
                    ->orderBy('id')->lockForUpdate()->get();
                $expenseSps = \App\Models\CostRegisterSecondPayment::with('costRegister')
                    ->whereIn('id', $expenseSpIds)->orderBy('id')->lockForUpdate()->get();

                $berubah = collect($crIds)
                    ->reject(fn ($cid) => ($locked[$cid]->status ?? null) === 'APPROVED')
                    ->map(fn ($cid) => $locked[$cid]->code ?? '#' . $cid);
                foreach ($secondPayments as $sp) {
                    if ($sp->status !== 'PENDING' || ($locked[$sp->budget_request_id]->status ?? null) !== 'SECOND_APPROVED') {
                        $berubah->push('Second Payment #' . $sp->id);
                    }
                }
                foreach ($registers as $register) {
                    if ($register->status !== \App\Models\CostRegister::TREASURY_READY_STATUS) {
                        $berubah->push('Cost Register #' . $register->id);
                    }
                }
                // Second Payment Expense: status induk TIDAK diubah saat submit (tetap SETTLE_APPROVED).
                foreach ($expenseSps as $esp) {
                    if ($esp->status !== 'PENDING' || $esp->costRegister?->status !== 'SETTLE_APPROVED') {
                        $berubah->push('Second Payment Expense #' . $esp->id);
                    }
                    $jumlah['sp']++;
                }
                if ($berubah->isNotEmpty()) {
                    throw new \DomainException(
                        'Submit dibatalkan — status baris berikut sudah berubah: ' . $berubah->implode(', ')
                        . '. Keluarkan baris tersebut dari Draft lalu submit lagi.'
                    );
                }

                foreach ($crIds as $crId) {
                    $cr = $locked[$crId];
                    $cr->update(['status' => 'QUEUED']);
                    $cr->addLog('APPROVED', 'QUEUED', 'batched', $terkunci->notes);
                    $jumlah['cr']++;
                }

                foreach ($registers as $register) {
                    $register->update(['status' => 'QUEUED']);
                    $register->addLog('APPROVED', 'QUEUED', 'batched', $terkunci->notes);
                    $jumlah['register']++;
                }

                // Kaki pembayaran Expense Claim: CR induk SECOND_APPROVED -> QUEUED (lihat storeCashOut lama).
                foreach ($secondPayments as $sp) {
                    $indukCr    = $locked[$sp->budget_request_id] ?? null;
                    $statusAwal = $indukCr?->status;
                    if ($indukCr && $indukCr->status === 'SECOND_APPROVED') {
                        $indukCr->update(['status' => 'QUEUED']);
                    }
                    $indukCr?->addLog(
                        $statusAwal,
                        $indukCr->status,
                        'second_payment_batched',
                        'Second Payment ' . $sp->amount_formatted . ' masuk batch ' . $terkunci->code
                            . ($terkunci->notes ? ' - ' . $terkunci->notes : '')
                    );
                    $jumlah['sp']++;
                }

                $terkunci->update(['status' => 'PENDING_APPROVER']);
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $batch->refresh();
        NotifyService::send(
            $this->approverUsers(),
            $batch->isTopUp() ? 'Batch Top Up menunggu approval' : 'Batch Treasury menunggu approval',
            $batch->isTopUp()
                ? Auth::user()->name . ' mengajukan Top Up ' . $batch->code . ' dari ' . ($batch->sourceFund->name ?? '-')
                    . ' ke ' . ($batch->toSourceFund->name ?? '-') . ' untuk disetujui.'
                : Auth::user()->name . ' mengajukan batch ' . $batch->code . ' ('
                    . $jumlah['cr'] . ' CR'
                    . ($jumlah['register'] ? ' + ' . $jumlah['register'] . ' Cost Register' : '')
                    . ($jumlah['sp'] ? ' + ' . $jumlah['sp'] . ' Second Payment' : '')
                    . ') untuk disetujui.',
            route('daily-transaction.index'),
            'workflow'
        );

        UserActivityLog::record('treasury', 'submit', 'Mengajukan batch pembayaran #' . $batch->code, $batch);

        return $this->redirectToBatch($request, $batch->id)
            ->with('success', 'Batch ' . $batch->code . ' diajukan ke CEO untuk approval.');
    }

    /** Hapus batch Draft. Sumbernya belum pernah berubah status, jadi cukup baris batch & itemnya. */
    public function destroy($id)
    {
        $batch = DailyTransaction::findOrFail($id);

        if (! $batch->isDraft()) {
            return back()->with('error', 'Hanya batch Draft yang bisa dihapus.');
        }

        $kode = $batch->code;
        $dihapus = false;

        DB::transaction(function () use ($batch, &$dihapus) {
            $terkunci = DailyTransaction::whereKey($batch->id)->lockForUpdate()->first();
            if (! $terkunci || ! $terkunci->isDraft()) {
                return;
            }
            foreach ($terkunci->attachments as $att) {
                Storage::disk('secure')->delete($att->path);
                $att->delete();
            }
            $terkunci->items()->delete();
            $terkunci->delete();
            $dihapus = true;
        });

        if (! $dihapus) {
            return back()->with('error', 'Status batch sudah berubah — muat ulang halaman.');
        }

        UserActivityLog::record('treasury', 'delete', 'Menghapus draft batch #' . $kode, null, null, $kode);

        return redirect('/daily-transaction')->with('success', 'Draft batch ' . $kode . ' dihapus.');
    }

    /** Keluarkan satu item dari batch Draft (Treasury). Tanpa perubahan status sumber. */
    public function removeDraftItem($batchId, $itemId)
    {
        $batch = DailyTransaction::findOrFail($batchId);
        $item  = DailyTransactionItem::where('daily_transaction_id', $batchId)->findOrFail($itemId);

        if (! $batch->isDraft()) {
            return back()->with('error', 'Item hanya bisa dikeluarkan langsung dari batch Draft.');
        }

        DB::transaction(function () use ($batch, $item) {
            $item->delete();
            $batch->refreshCode();
            $batch->recalculateCapturedAmount();
        });

        UserActivityLog::record('treasury', 'update', 'Mengeluarkan item dari draft batch #' . $batch->fresh()->code, $batch);

        return $this->redirectToBatch(request(), $batchId)->with('success', 'Item dikeluarkan dari draft batch.');
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

        if (! $batch->isDraft() && ! $batch->isPendingApprover()) {
            return back()->with('error', 'Hanya batch Draft atau PENDING_APPROVER yang bisa ditambah item.');
        }

        $added = 0;
        $ditolak = false;

        DB::transaction(function () use ($request, $batch, &$added, &$ditolak) {
            /*
            | Batch dan kandidatnya dikunci FOR UPDATE (lihat catatan di
            | storeCashOut): tanpa kunci, dua Treasury yang menambah CR yang
            | sama bersamaan bisa sama-sama lolos dan CR masuk dua batch. Batch
            | ikut dikunci supaya item tidak masuk ke batch yang statusnya baru
            | saja berubah. Aturan "lewati yang tidak layak" tidak berubah.
            */
            $terkunci = DailyTransaction::whereKey($batch->id)->lockForUpdate()->first();
            if (! $terkunci || (! $terkunci->isDraft() && ! $terkunci->isPendingApprover())) {
                $ditolak = true;
                return;
            }
            // Draft: item dipesan saja; status sumber baru berubah saat Submit.
            $draft = $terkunci->isDraft();

            /*
            | Dua pemuatan MASSAL sebelum loop, bukan dua query per baris.
            |
            | Versi sebelumnya memanggil BudgetRequest::find() dan satu
            | pemeriksaan "sudah masuk batch lain?" (dengan whereHas, jadi
            | subquery) untuk SETIAP id yang dipilih. Batch berisi 100 Cost
            | Request karena itu mengeluarkan 200+ query tambahan di dalam satu
            | transaksi - menahan kunci baris lebih lama dari yang perlu, dan
            | pada batch besar berisiko menabrak max_execution_time di tengah
            | jalan.
            |
            | Jumlah query sekarang tetap dua, baik yang dipilih 5 baris maupun
            | 500. Aturannya tidak berubah sama sekali: hanya CR berstatus
            | APPROVED, dan tidak boleh sudah berada di batch PENDING_APPROVER
            | atau DONE mana pun.
            */
            $crIds = collect((array) $request->cr_ids)
                ->filter(fn ($v) => is_numeric($v))
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->values();

            $spIds       = (array) $request->input('second_payment_ids', []);
            $spParentIds = \App\Models\BudgetRequestSecondPayment::whereIn('id', $spIds)
                ->pluck('budget_request_id')->map(fn ($v) => (int) $v);

            // Satu kunci untuk CR pilihan + CR induk Second Payment (id naik,
            // urutan sama dengan storeCashOut). Status dibaca dari hasil kunci.
            $locked = BudgetRequest::whereIn('id', $crIds->merge($spParentIds)->unique())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $kandidat = $locked->only($crIds->all())->where('status', 'APPROVED');

            $sudahMasukBatch = DailyTransactionItem::whereIn('budget_request_id', $crIds)
                ->where('status', 'INCLUDED')
                ->whereHas('dailyTransaction', fn ($q) =>
                    $q->whereIn('status', DailyTransaction::ACTIVE_STATUSES)
                )
                ->pluck('budget_request_id')
                ->map(fn ($v) => (int) $v)
                ->all();

            foreach ($crIds as $crId) {
                $cr = $kandidat->get($crId);
                if (! $cr) continue;

                if (in_array($crId, $sudahMasukBatch, true)) continue;

                $batch->items()->create([
                    'budget_request_id' => $crId,
                    'status'            => 'INCLUDED',
                ]);

                // Sama seperti CR yang masuk batch sejak awal — langsung QUEUED
                // begitu ditambahkan ke batch yang sudah di-submit.
                if (! $draft) {
                    $cr->update(['status' => 'QUEUED']);
                    $cr->addLog('APPROVED', 'QUEUED', 'batched', null);
                }
                $added++;
            }

            // Second Payment menyusul ke batch yang sama - aturannya identik
            // dengan saat batch dibuat (lihat storeCashOut).
            // Syarat setara availableForBatch() (PENDING + induk SECOND_APPROVED),
            // tetapi dibaca dari baris terkunci: begitu SP masuk batch, induknya
            // jadi QUEUED sehingga request kedua otomatis melewatinya.
            $secondPayments = \App\Models\BudgetRequestSecondPayment::whereIn('id', $spIds)
                ->orderBy('id')->lockForUpdate()->get()
                ->filter(fn ($sp) => $sp->status === 'PENDING'
                    && ($locked[$sp->budget_request_id]->status ?? null) === 'SECOND_APPROVED');

            foreach ($secondPayments as $sp) {
                $batch->items()->create([
                    'budget_request_id' => $sp->budget_request_id,
                    'second_payment_id' => $sp->id,
                    'status'            => 'INCLUDED',
                ]);

                // Sama seperti saat batch dibuat: CR induk Expense Claim ikut
                // jadi QUEUED (Process) begitu masuk batch.
                if (! $draft) {
                    $indukCr  = $locked[$sp->budget_request_id] ?? null;
                    $crStatus = $indukCr?->status;
                    if ($indukCr && $indukCr->status === 'SECOND_APPROVED') {
                        $indukCr->update(['status' => 'QUEUED']);
                    }
                    // Satu baris log saja (lihat submit()): status awal -> status
                    // sekarang, dengan nominal Second Payment-nya.
                    $indukCr?->addLog(
                        $crStatus, $indukCr->status, 'second_payment_batched',
                        'Second Payment ' . $sp->amount_formatted . ' masuk batch ' . $batch->code
                    );
                }
                $added++;
            }

            $batch->refreshCode();
            $batch->recalculateCapturedAmount();
        });

        if ($ditolak) {
            return back()->with('error', 'Hanya batch Draft atau PENDING_APPROVER yang bisa ditambah item.');
        }

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

        try {
        DB::transaction(function () use ($request, &$batch, $charge) {
            /*
            | Kunci batch dulu, baru simpan apa pun. Pemeriksaan isDone() di
            | atas berjalan tanpa kunci: dua klik Mark Paid bersamaan (dua tab
            | atau dua staf Treasury) dulu sama-sama lolos, sehingga lampiran
            | bukti transfer dan log status CR tercatat dua kali, dan charge /
            | paid_by yang diisi pertama tertimpa. Request kedua sekarang
            | menunggu, melihat batch sudah PAID, dan mundur sebelum menyimpan
            | lampiran. Item dimuat ulang dari batch yang terkunci supaya
            | pemeriksaan status CR di bawah memakai data terbaru.
            */
            $batch = DailyTransaction::whereKey($batch->id)->lockForUpdate()->first();

            if (! $batch || ! $batch->isDone()) {
                throw new \DomainException('Batch ini baru saja ditandai PAID oleh pengguna lain — muat ulang halaman.');
            }

            $batch->load(['includedItems.budgetRequest', 'includedItems.costRegister']);

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
                // MN-09 — baris Cost Register: tidak punya tahap realisasi (seperti Cost
                // Payment Non-CA), jadi begitu batch dibayar ia langsung CLOSED.
                //
                // Expense Cash Advance (Fase 2): QUEUED -> PAID, lanjut input realisasi.
                // Second Payment Expense: PAID, Expense induk SETTLE_APPROVED -> CLOSED.
                if ($item->isExpenseSecondPayment()) {
                    $esp = \App\Models\CostRegisterSecondPayment::whereKey($item->cost_register_second_payment_id)->lockForUpdate()->first();
                    $register = $item->costRegister;
                    if ($esp && $esp->status === 'PENDING') {
                        $esp->update(['status' => 'PAID', 'paid_by' => Auth::id(), 'paid_at' => now(), 'notes' => $request->payment_notes]);
                        if ($register && $register->status === 'SETTLE_APPROVED'
                            && ! $register->secondPayments()->where('status', 'PENDING')->exists()) {
                            $register->update(['status' => 'CLOSED']);
                            $register->addLog('SETTLE_APPROVED', 'CLOSED', 'second_payment_paid',
                                'Kekurangan bayar ' . $esp->amount_formatted . ' dibayar. ' . $request->payment_notes);
                        }
                    }
                    continue;
                }

                if ($item->isCostRegister()) {
                    $register = $item->costRegister;
                    if ($register && $register->status === 'QUEUED') {
                        $tujuan = $register->isCashAdvance() ? 'PAID' : 'CLOSED';
                        $register->update(['status' => $tujuan, 'paid_at' => now()]);
                        $register->addLog('QUEUED', $tujuan, 'paid', $request->payment_notes);
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

                    $indukCr  = $item->budgetRequest;
                    $crStatus = $indukCr?->status;
                    $indukCr?->addLog(
                        $crStatus, $crStatus, 'second_payment_paid',
                        'Second Payment ' . $sp->amount_formatted . ' dibayar lewat batch '
                            . $batch->code . ($request->payment_notes ? ' - ' . $request->payment_notes : '')
                    );

                    /*
                    | Expense Claim lunas = urusan CR ini selesai. CR yang
                    | sampai ke sini sudah lewat Settle Approve & 2nd Approve,
                    | jadi tidak ada lagi tahap Close manual — langsung CLOSED.
                    */
                    if ($indukCr && $indukCr->status === 'QUEUED') {
                        $dari = $indukCr->status;
                        $indukCr->update(['status' => 'CLOSED', 'closed_at' => now()]);
                        $indukCr->addLog($dari, 'CLOSED', 'settlement_closed',
                            'Ditutup otomatis setelah Expense Claim dibayar pada batch ' . $batch->code);
                    }
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

                /* CR yang lahir dari plan visit Trouble Ticket: pencairannya
                   dicatat juga di timeline tiketnya (20 Okt 2026), supaya orang
                   lapangan tidak perlu membuka modul Finance. Hanya menulis
                   kejadian — status tiket tidak disentuh. */
                $crDibayar = $cr->refresh();
                $kodeBatch = $batch->code;
                $catatan   = $request->payment_notes;
                DB::afterCommit(function () use ($crDibayar, $kodeBatch, $catatan) {
                    try {
                        app(TroubleTicketWorkflow::class)
                            ->recordCostRequestPaid($crDibayar, Auth::user(), $catatan, $kodeBatch);
                    } catch (\Throwable $e) {
                        // Pembayaran sudah sah; jejak di tiket disusulkan saat tiket dibuka.
                        report($e);
                    }
                });
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
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        UserActivityLog::record('treasury', 'pay', 'Menandai batch #' . $batch->code . ' telah dibayar (PAID)', $batch, $request->payment_notes);

        return $this->redirectToBatch($request, $id)
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

        // "Used" & riwayat CR seluruh sub-item dimuat sekaligus (bukan per baris).
        \App\Models\BudgetPlanDetail::primeCostLines($bp->details);

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
                    // Sumber sama dengan "Used" (BudgetPlanDetail::costLines()):
                    // Cash Advance yang settlement-nya sudah diajukan tampil per
                    // baris settlement di Sub COA efektifnya.
                    $historyItems = $d->costLines()
                        ->reject(fn ($u) => $u['request_detail']
                            && in_array($u['request_detail']->id, $reqData['detail_ids'], true))
                        ->map(function ($u) {
                            $cr   = $u['budget_request'];
                            $od   = $u['request_detail'];
                            $show = \App\Models\BudgetPlanDetail::costLineDisplay($u);
                            $biasa = $od && ! $show['is_added'] && ! $show['is_moved'];

                            return [
                                'cr_id'             => $cr->id,
                                'cr_code'           => $cr->code,
                                'item_id'           => null,
                                'date_raw'          => $cr->created_at,
                                'date_formatted'    => $cr->created_at?->format('d M Y H:i') ?? '-',
                                'description'       => $biasa ? ($od->notes ?: ($od->description ?: '-')) : $show['notes'],
                                'beneficiary'       => $cr->beneficiary->nickname ?? '-',
                                'price_formatted'   => number_format($show['unit_price'], 0, ',', '.'),
                                'qty'               => $biasa ? $od->qty : $show['qty'],
                                'amount_formatted'  => number_format($show['amount'], 0, ',', '.'),
                                'item_status'       => 'HISTORY',
                            ];
                        })
                        ->values();

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
            $sheet1->setCellValue('A' . $row, 'Expense');
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
                    $reg->target_label,
                    $reg->project->customer->name ?? '-',
                    $reg->type_label,
                    $reg->notes ?? '-',
                    $reg->beneficiary->complete_name ?? '-',
                    $reg->beneficiary->bank->name ?? '-',
                    $reg->beneficiary->account_number ?? '-',
                    (int) $item->sourceAmount(),
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

    /** Relasi untuk "Daftar CR dalam Batch" (halaman show & tab Item drawer daftar). */
    private const ITEM_LIST_RELATIONS = [
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
    ];

    public function show($id)
    {
        /** @var DailyTransaction $batch */
        $batch = DailyTransaction::with(self::ITEM_LIST_RELATIONS)->findOrFail($id);

        $groupedItems = $this->groupBatchItems($batch);

        $availableCRs = BudgetRequest::where('status', 'APPROVED')
            ->whereDoesntHave('dailyTransactionItems', fn($q) =>
                // whereNull: baris Second Payment juga menunjuk ke CR ini, tapi
                // ia membayar kekurangan Settlement - bukan pengajuan awalnya.
                // Tanpa filter ini, CR yang punya Second Payment di batch aktif
                // akan dikira sudah dibatch.
                $q->where('status', 'INCLUDED')
                  ->whereNull('second_payment_id')
                  ->whereHas('dailyTransaction', fn($q2) =>
                      $q2->whereIn('status', DailyTransaction::ACTIVE_STATUSES)
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

    /**
     * Tab Item drawer daftar Treasury: tabel "Daftar CR dalam Batch" yang sama
     * persis dengan halaman show (grouping per Budget Plan/PO + modal View
     * Budget), dirender server dan dimuat saat tab dibuka.
     */
    public function itemsHtml($id)
    {
        /** @var DailyTransaction $batch */
        $batch = DailyTransaction::with(self::ITEM_LIST_RELATIONS)->findOrFail($id);

        return view('daily-transaction.partials.items', [
            'batch'        => $batch,
            'groupedItems' => $this->groupBatchItems($batch),
        ]);
    }

    /**
     * Aksi yang dipicu dari drawer daftar (form membawa from_index=1) kembali ke
     * daftar — URL daftar memuat ?open={id} sehingga drawer dibuka ulang.
     * Dari halaman show tetap kembali ke halaman show seperti sebelumnya.
     */
    private function redirectToBatch(Request $request, $id)
    {
        return $request->boolean('from_index')
            ? back()
            : redirect('/daily-transaction/' . $id);
    }

    /** Grouping item batch per Budget Plan/PO (Cost Register satu grup sendiri). */
    private function groupBatchItems(DailyTransaction $batch)
    {
        return $batch->items->groupBy(function ($item) {
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
                    'label'      => 'Expense',
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

        // Relasi item di-eager-load untuk sheet "Detail"; tanpa ini tiap baris
        // memicu query sendiri saat file dibentuk.
        $transactions = DailyTransaction::with([
                'sourceFund', 'toSourceFund',
                'includedItems.budgetRequest.budgetPlan.project',
                'includedItems.budgetRequest.budgetPlan.costCenter',
                'includedItems.budgetRequest.applicant',
                'includedItems.budgetRequest.beneficiary',
                'includedItems.secondPayment',
                'includedItems.costRegister.beneficiary',
            ])
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

        /* SHEET 2 — DETAIL
           Isi per batch sama dengan sheet "Detail" Export Data di halaman show
           (item INCLUDED saja), ditambah kolom Kode Batch + baris Cost Register. */
        $detail = $spreadsheet->createSheet();
        $detail->setTitle('Detail');
        $detail->fromArray(['Kode Batch', 'Budget Plan', 'Kode', 'Deskripsi', 'Pengaju', 'Penerima', 'Amount', 'Tanggal'], null, 'A1');
        foreach (range('A', 'H') as $col) {
            $detail->getStyle($col . '1')->getFont()->setBold(true);
            $detail->getStyle($col . '1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0D1B2A');
            $detail->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }

        $detailRow = 2;
        foreach ($transactions as $trx) {
            foreach ($trx->includedItems as $item) {
                $cr  = $item->budgetRequest;
                $reg = $item->costRegister;
                if (!$cr && !$reg) continue;

                $detail->setCellValueExplicit('A' . $detailRow, $trx->code, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $detail->fromArray([
                    $cr ? $this->budgetPlanLabel($cr->budgetPlan) : 'Expense',
                    $item->sourceCode(),
                    $item->sourceDescription(),
                    $cr ? ($cr->applicant->name ?? '-') : '-',
                    ($cr ? $cr->beneficiary : $reg->beneficiary)->complete_name ?? '-',
                    (int) $item->sourceAmount(),
                    $cr ? $cr->created_at?->format('d-m-Y') : ($reg->period?->format('m-Y') ?? '-'),
                ], null, 'B' . $detailRow);
                $detailRow++;
            }
        }
        if ($detailRow > 2) {
            $detail->getStyle('A2:A' . ($detailRow - 1))->getNumberFormat()
                ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        }
        foreach (['A'=>18,'B'=>32,'C'=>18,'D'=>40,'E'=>18,'F'=>22,'G'=>16,'H'=>14] as $col => $width) {
            $detail->getColumnDimension($col)->setWidth($width);
        }

        $spreadsheet->setActiveSheetIndex(0);

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
            'items.costRegister.beneficiary.bank',
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
            /*
            | Baris Cost Register (Non-CR, MN-09) tidak punya budgetRequest.
            | Dulu baris ini dilewati, sehingga batch berisi Non-CR menghasilkan
            | file transfer kosong / kurang. Penerimanya diambil dari Cost
            | Register; Reference memakai kode CREG-xxxxx (tanpa email).
            */
            if ($item->isCostRegister()) {
                $reg = $item->costRegister;
                if (!$reg) continue;

                $benef = $reg->beneficiary;
                $bank  = $benef->bank ?? null;

                $sheet->fromArray([
                    $bank->default_method ?? '',
                    $benef->account_number ?? '',
                    'IDR',
                    (int) $item->sourceAmount(),
                    $benef->complete_name ?? '',
                    $bank->name ?? '',
                    $bank->code ?? '',
                    'OUR',
                    $item->sourceCode(),
                    \Illuminate\Support\Str::limit($item->sourceDescription(), 150, ''),
                    '',
                ], null, 'A' . $row);
                $row++;
                continue;
            }

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
                // Reference: kode CR tanpa prefix tipe ("CR-", "CA-", "PA-", ...).
                // Baris Second Payment diberi akhiran -2 supaya bank tidak
                // menolaknya sebagai referensi ganda dengan transfer pertama.
                preg_replace('/^[A-Z]{2}-/', '', $cr->code ?? '') . ($item->isSecondPayment() ? '-2' : ''),
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