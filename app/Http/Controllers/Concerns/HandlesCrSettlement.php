<?php

namespace App\Http\Controllers\Concerns;

use App\Models\BudgetRequest;
use App\Models\User;
use App\Services\NotifyService;
use App\Services\RoleDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Alur SETTLEMENT Cash Advance.
 *
 * KENAPA DIPISAH
 *
 * BudgetRequestController berisi 3.528 baris dengan 100+ method dan menangani
 * SELURUH alur Cost Request: pembuatan, tiga tahap approval, takeout item,
 * realisasi, settlement, ekspor, impor Excel, dan API publik. Ukurannya bukan
 * soal selera: riwayat git terbaru menunjukkan lima commit berurutan yang
 * semuanya memperbaiki regresi pada kolom & stepper Cost Request — gejala khas
 * berkas yang terlalu besar untuk diubah tanpa menyentuh hal lain.
 *
 * Settlement dipilih sebagai pemisahan PERTAMA karena ia paling terpisah dari
 * sisanya: alurnya punya statusnya sendiri (REALIZED -> SETTLE_CHECKED ->
 * SETTLE_VERIFIED -> SETTLE_APPROVED -> SECOND_APPROVED -> CLOSED), pemainnya
 * sendiri, dan tidak dipanggil dari alur approval pengajuan.
 *
 * POLA YANG DIIKUTI
 *
 * Trait, sama seperti Concerns\BuildsCrWorklist, Concerns\TransitionsStatus dan
 * Concerns\CapsLargeLists yang sudah ada. Isinya dipindahkan APA ADANYA — tidak
 * satu pun aturan, status, atau notifikasi diubah. Satu-satunya perubahan
 * perilaku yang mungkin adalah nol.
 *
 * KETERGANTUNGAN PADA HOST
 *
 * Method di sini memanggil helper milik BudgetRequestController
 * (findVisibleCr(), transitionStatus(), headUsers(), financeUsers(),
 * approverUsers()). Itu sah: trait diratakan ke dalam kelas, jadi method di
 * sini menjadi method kelas itu. Konsekuensinya trait ini memang TIDAK
 * dimaksudkan dipakai ulang di kelas lain — tugasnya memecah satu kelas
 * raksasa, bukan menyediakan layanan umum.
 */
trait HandlesCrSettlement
{
    /*
    |--------------------------------------------------------------------------
    | SETTLEMENT — Cash Advance: REALIZED (Settle) -> SETTLE_CHECKED (Head) ->
    | SETTLE_VERIFIED (Finance) -> CLOSED (CEO). Reject di tiap tahap berhenti
    | di SETTLE_REJECTED (manager merevisi lalu mengajukan ulang Settlement).
    |--------------------------------------------------------------------------
    */

    public function headCheckSettlement(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'head'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk approval tahap ini.');
        }

        if ($msg = $this->findVisibleCr($id)->selfApprovalError($user)) {
            return back()->with('error', $msg);
        }

        // Transisi terkunci: lihat Concerns\TransitionsStatus.
        $cr = $this->transitionStatus(BudgetRequest::class, $id, 'REALIZED', ['status' => 'SETTLE_CHECKED'], function ($cr) use ($request) {
            $cr->addLog('REALIZED', 'SETTLE_CHECKED', 'settlement_checked', $request->notes);
        });

        if (! $cr) {
            return back()->with('error', 'Status sudah berubah — muat ulang halaman.');
        }

        NotifyService::send(
            $this->financeUsers(),
            'Settlement CR menunggu verifikasi',
            $user->name . ' memeriksa Settlement Cost Request "' . $cr->description . '" (' . $cr->code . ').',
            route('budget-request.index'),
            'workflow'
        );

        return back()->with('success', 'Settlement di-check. Menunggu verifikasi Finance.');
    }

    public function headRejectSettlement(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'head'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk approval tahap ini.');
        }

        $request->validate(['notes' => 'required|string|max:500']);
        if ($msg = $this->findVisibleCr($id)->selfApprovalError($user)) {
            return back()->with('error', $msg);
        }

        // Transisi terkunci: lihat Concerns\TransitionsStatus.
        $cr = $this->transitionStatus(BudgetRequest::class, $id, 'REALIZED', ['status' => 'SETTLE_REJECTED'], function ($cr) use ($request) {
            $cr->addLog('REALIZED', 'SETTLE_REJECTED', 'settlement_rejected', $request->notes);
        });

        if (! $cr) {
            return back()->with('error', 'Status sudah berubah — muat ulang halaman.');
        }

        if ($cr->applicant) {
            NotifyService::send(
                [$cr->applicant],
                'Settlement CR ditolak',
                'Settlement Cost Request "' . $cr->description . '" (' . $cr->code . ') ditolak: ' . $request->notes,
                route('budget-request.index'),
                'workflow'
            );
        }

        return back()->with('success', 'Settlement ditolak. Silakan revisi dan ajukan ulang.');
    }

    public function financeVerifySettlement(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'finance', 'finance_manager', 'cost_control'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk approval tahap ini.');
        }

        $cr = $this->findVisibleCr($id);
        if ($msg = $cr->selfApprovalError($user)) {
            return back()->with('error', $msg);
        }
        if ($cr->status !== 'SETTLE_CHECKED') {
            return back()->with('error', 'Status tidak valid.');
        }

        /*
        | Transisi terkunci (Concerns\TransitionsStatus), sama seperti aksi
        | settlement lain di trait ini. Pemeriksaan di atas berjalan tanpa
        | kunci: dua verifikasi bersamaan (dua staf Finance, bulk Todo List +
        | klik satuan, dua tab) dulu sama-sama lolos dan masing-masing membuat
        | satu baris Second Payment - kekurangan bayar yang sama bisa dibatch
        | dan dibayar dua kali. Pembuatan Second Payment sekarang ikut di
        | dalam kunci yang sama dengan perubahan status.
        */
        $cr = $this->transitionStatus(BudgetRequest::class, $id, 'SETTLE_CHECKED', ['status' => 'SETTLE_VERIFIED'], function ($cr) use ($request) {
            $cr->addLog('SETTLE_CHECKED', 'SETTLE_VERIFIED', 'settlement_verified', $request->notes);

            /*
            | Selisih = Settlement - Original Approved Amount. Kalau > 0, CR
            | tetap berhenti di SETTLE_VERIFIED — bedanya aksi Finance Manager
            | di sana bukan Close melainkan Settle Approve, karena kekurangan
            | bayarnya wajib disetujui dua lapis sebelum dibayar Treasury.
            */
            $overage = $cr->settlement_overage;
            if ($overage > 0) {
                $cr->secondPayments()->create([
                    'amount' => $overage,
                    'status' => 'PENDING',
                ]);
            }
        });

        if (! $cr) {
            return back()->with('error', 'Status sudah berubah — muat ulang halaman.');
        }

        // CR dengan Expense Claim belum boleh ditutup: masih harus lewat
        // Settle Approved (Finance Manager) lalu 2nd Approved (Approver).
        $adaClaim = $cr->fresh()->hasExpenseClaim();

        NotifyService::send(
            $this->financeManagerUsers(),
            $adaClaim ? 'Settlement CR menunggu Settle Approve' : 'Settlement CR menunggu Close',
            $user->name . ' memverifikasi Settlement Cost Request "' . $cr->description . '" (' . $cr->code . ').',
            route('budget-request.index'),
            'workflow'
        );

        return back()->with('success', $adaClaim
            ? 'Settlement diverifikasi. Ada Expense Claim — menunggu Settle Approve Finance Manager.'
            : 'Settlement diverifikasi. Menunggu Close Finance Manager.');
    }

    public function financeRejectSettlement(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'finance', 'finance_manager', 'cost_control'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk approval tahap ini.');
        }

        $request->validate(['notes' => 'required|string|max:500']);
        if ($msg = $this->findVisibleCr($id)->selfApprovalError($user)) {
            return back()->with('error', $msg);
        }

        // Transisi terkunci: lihat Concerns\TransitionsStatus.
        $cr = $this->transitionStatus(BudgetRequest::class, $id, 'SETTLE_CHECKED', ['status' => 'SETTLE_REJECTED'], function ($cr) use ($request) {
            $cr->addLog('SETTLE_CHECKED', 'SETTLE_REJECTED', 'settlement_rejected', $request->notes);
        });

        if (! $cr) {
            return back()->with('error', 'Status sudah berubah — muat ulang halaman.');
        }

        if ($cr->applicant) {
            NotifyService::send(
                [$cr->applicant],
                'Settlement CR ditolak',
                'Settlement Cost Request "' . $cr->description . '" (' . $cr->code . ') ditolak: ' . $request->notes,
                route('budget-request.index'),
                'workflow'
            );
        }

        return back()->with('success', 'Settlement ditolak. Silakan revisi dan ajukan ulang.');
    }

    /*
    |--------------------------------------------------------------------------
    | EXPENSE CLAIM — SETTLE APPROVED -> SECOND APPROVED
    |--------------------------------------------------------------------------
    | Hanya dilewati CR yang settlement-nya MELEBIHI pengajuan (Expense Claim).
    | Finance Manager menyetujui klaimnya (SETTLE_APPROVED), lalu Approver
    | menyetujui pembayaran keduanya (SECOND_APPROVED); Close tetap milik
    | Finance Manager seperti alur tanpa klaim.
    */

    /** Finance Manager — SETTLE_VERIFIED -> SETTLE_APPROVED. */
    public function settleApprove(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'finance_manager'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk approval tahap ini.');
        }

        $cr = $this->findVisibleCr($id);
        if ($msg = $cr->selfApprovalError($user)) {
            return back()->with('error', $msg);
        }

        if (! $cr->hasExpenseClaim()) {
            return back()->with('error', 'CR ini tidak punya Expense Claim — langsung Close, tanpa tahap Settle Approve.');
        }

        // Transisi terkunci: lihat Concerns\TransitionsStatus.
        $cr = $this->transitionStatus(BudgetRequest::class, $id, 'SETTLE_VERIFIED', ['status' => 'SETTLE_APPROVED'], function ($cr) use ($request) {
            $cr->addLog('SETTLE_VERIFIED', 'SETTLE_APPROVED', 'settlement_approved', $request->notes);
        });

        if (! $cr) {
            return back()->with('error', 'Status sudah berubah — muat ulang halaman.');
        }

        NotifyService::send(
            $this->approverUsers(),
            'Expense Claim menunggu Second Approve (Approver)',
            $user->name . ' menyetujui Settlement Cost Request "' . $cr->description . '" (' . $cr->code . ')'
                . ' dengan Expense Claim ' . number_format($cr->expenseClaimAmount(), 0, ',', '.') . '.',
            route('budget-request.index'),
            'workflow'
        );

        return back()->with('success', 'Expense Claim disetujui. Menunggu Second Approve Approver.');
    }

    /** Approver — SETTLE_APPROVED -> SECOND_APPROVED. */
    public function secondApprove(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'approver'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk approval tahap ini.');
        }

        if ($msg = $this->findVisibleCr($id)->selfApprovalError($user)) {
            return back()->with('error', $msg);
        }

        /*
        | Approver setuju -> CR masuk SECOND_APPROVED sambil menunggu Treasury
        | membuat batch. Begitu ikut sebuah batch ia jadi QUEUED (Process),
        | lalu CLOSED otomatis saat batch itu ditandai PAID.
        */
        $cr = $this->transitionStatus(BudgetRequest::class, $id, 'SETTLE_APPROVED', ['status' => 'SECOND_APPROVED'], function ($cr) use ($request) {
            $cr->addLog('SETTLE_APPROVED', 'SECOND_APPROVED', 'second_payment_approved', $request->notes);
        });

        if (! $cr) {
            return back()->with('error', 'Status sudah berubah — muat ulang halaman.');
        }

        NotifyService::send(
            $this->treasuryUsers(),
            'Expense Claim siap dibatch',
            $user->name . ' menyetujui Expense Claim Cost Request "' . $cr->description . '" (' . $cr->code . ')'
                . ' sebesar ' . number_format($cr->expenseClaimAmount(), 0, ',', '.') . '. Silakan masukkan ke batch pembayaran.',
            route('daily-transaction.create'),
            'workflow'
        );

        return back()->with('success', 'Expense Claim disetujui. Masuk antrean Buat Batch Baru Treasury — CR akan CLOSED setelah dibayar.');
    }

    /** Finance Manager — reject Expense Claim di tahap Settle Approve: CR berhenti di SETTLE_REJECTED. */
    public function settleReject(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'finance_manager'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk approval tahap ini.');
        }

        $request->validate(['notes' => 'required|string|max:500']);

        $cr = $this->findVisibleCr($id);

        if ($error = $this->applySettlementRejectClose($cr, $request->notes, ['SETTLE_VERIFIED'])) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Expense Claim ditolak. Silakan revisi settlement dan ajukan ulang.');
    }

    /** Approver — reject Expense Claim di tahap Second Approve: CR berhenti di SETTLE_REJECTED. */
    public function secondReject(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'approver'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk approval tahap ini.');
        }

        $request->validate(['notes' => 'required|string|max:500']);

        $cr = $this->findVisibleCr($id);

        if ($error = $this->applySettlementRejectClose($cr, $request->notes, ['SETTLE_APPROVED'])) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Expense Claim ditolak. Silakan revisi settlement dan ajukan ulang.');
    }

    /** Penerima notifikasi tahap Finance Manager. */
    private function financeManagerUsers()
    {
        return app(RoleDirectory::class)->usersWithRoles(['finance_manager']);
    }

    /** Penerima notifikasi tahap Treasury. */
    private function treasuryUsers()
    {
        return app(RoleDirectory::class)->usersWithRoles(['treasury', 'finance_manager']);
    }

    public function ceoCloseSettlement(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        // Nama method/route tetap "ceoClose..." (historis), tapi actionnya
        // sekarang dilakukan Finance Manager, BUKAN CEO/approver.
        $this->authorizeSettlementClose($user);

        $cr = $this->findVisibleCr($id);

        if ($error = $this->applySettlementClose($cr, $request->notes)) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Settlement di-Close. CR sekarang CLOSED.');
    }

    /**
     * Tahap penutupan Settlement dipegang Finance Manager (bukan CEO) — lihat
     * catatan di ceoCloseSettlement().
     */
    private function authorizeSettlementClose(User $user): void
    {
        if (!$user->hasRole(['superadmin', 'finance_manager'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk approval tahap ini.');
        }
    }

    /**
     * Inti Close satu CR. Mengembalikan pesan error kalau ditolak, atau null
     * kalau berhasil — supaya jalur satuan dan bulk memakai aturan yang sama.
     */
    private function applySettlementClose(BudgetRequest $cr, ?string $notes): ?string
    {
        if ($cr->isSelfApprovalBlockedFor(Auth::user())) {
            return 'CR ' . $cr->code . ': ' . BudgetRequest::SELF_APPROVAL_MESSAGE;
        }
        if ($cr->status !== 'SETTLE_VERIFIED') {
            return 'CR ' . $cr->code . ': status tidak valid.';
        }

        /*
        | CR dengan Expense Claim tidak ditutup dari sini: ia lewat Settle
        | Approve -> 2nd Approve -> batch Treasury, dan CLOSED-nya dipasang
        | saat batch itu ditandai PAID (DailyTransactionController::markPaid).
        */
        if ($cr->hasExpenseClaim()) {
            return 'CR ' . $cr->code . ' punya Expense Claim — tutupnya otomatis setelah Expense Claim dibayar Treasury.';
        }

        if ($cr->pendingSecondPayment()) {
            return 'CR ' . $cr->code . ' belum bisa di-Close — Second Payment masih PENDING.';
        }

        $dari = $cr->status;
        $cr->update(['status' => 'CLOSED', 'closed_at' => now()]);
        $cr->addLog($dari, 'CLOSED', 'settlement_closed', $notes);

        if ($cr->applicant) {
            NotifyService::send(
                [$cr->applicant],
                'Settlement CR di-Close',
                'Settlement Cost Request "' . $cr->description . '" (' . $cr->code . ') di-Close. CR sekarang CLOSED.',
                route('budget-request.index'),
                'workflow'
            );
        }

        return null;
    }

    /**
     * Inti Reject tahap Close: SETTLE_VERIFIED -> SETTLE_REJECTED, sama
     * seperti reject Head/Finance pada tahap sebelumnya. Second Payment
     * PENDING yang lahir saat verify ikut dihapus — selisihnya belum tentu
     * sama lagi setelah settlement diperbaiki. Kalau baris itu sudah masuk
     * batch Treasury (apalagi sudah PAID), reject ditolak: uangnya sudah
     * bergerak.
     */
    /**
     * @param string[] $dariStatus status yang boleh ditolak dari tahap ini.
     */
    private function applySettlementRejectClose(BudgetRequest $cr, string $notes, array $dariStatus = ['SETTLE_VERIFIED']): ?string
    {
        if ($cr->isSelfApprovalBlockedFor(Auth::user())) {
            return 'CR ' . $cr->code . ': ' . BudgetRequest::SELF_APPROVAL_MESSAGE;
        }
        if (! in_array($cr->status, $dariStatus, true)) {
            return 'CR ' . $cr->code . ': status tidak valid.';
        }

        $secondPayments = $cr->secondPayments()->with('dailyTransactionItems')->get();

        foreach ($secondPayments as $sp) {
            if ($sp->status === 'PAID') {
                return 'CR ' . $cr->code . ' tidak bisa ditolak — Second Payment sudah dibayar.';
            }
            if ($sp->dailyTransactionItems->where('status', 'INCLUDED')->isNotEmpty()) {
                return 'CR ' . $cr->code . ' tidak bisa ditolak — Second Payment sudah masuk batch Treasury.';
            }
        }

        DB::transaction(function () use ($cr, $secondPayments, $notes) {
            foreach ($secondPayments as $sp) {
                $sp->delete();
            }
            $dari = $cr->status;
            $cr->update(['status' => 'SETTLE_REJECTED']);
            $cr->addLog($dari, 'SETTLE_REJECTED', 'settlement_rejected', $notes);
        });

        if ($cr->applicant) {
            NotifyService::send(
                [$cr->applicant],
                'Settlement CR ditolak',
                'Settlement Cost Request "' . $cr->description . '" (' . $cr->code . ') ditolak: ' . $notes,
                route('budget-request.index'),
                'workflow'
            );
        }

        return null;
    }

    /** Reject satuan pada tahap Finance Manager Close. */
    public function managerRejectSettlement(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        $this->authorizeSettlementClose($user);
        $request->validate(['notes' => 'required|string|max:500']);

        $cr = $this->findVisibleCr($id);

        if ($error = $this->applySettlementRejectClose($cr, $request->notes)) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Settlement ditolak. Silakan revisi dan ajukan ulang.');
    }

    /** CR terpilih yang benar-benar boleh diakses user ini (bulk). */
    private function visibleCrs(array $ids)
    {
        return BudgetRequest::whereIn('id', $ids)->get()
            ->filter(fn (BudgetRequest $cr) => $this->canViewCr($cr));
    }

    /**
     * Bulk Approve tahap SETTLEMENT dari daftar CR (Fase 4). Tiap CR dijalankan
     * lewat aksi satuannya — aturan, kunci status, log, dan notifikasi tetap
     * satu sumber:
     *   REALIZED        -> headCheckSettlement          (Head)
     *   SETTLE_CHECKED  -> financeVerifySettlement      (Finance)
     *   SETTLE_VERIFIED -> settleApprove / Close        (Finance Manager)
     *   SETTLE_APPROVED -> secondApprove                (Approver)
     * `expected` (id => status yang terlihat di layar) mencegah CR yang statusnya
     * sudah berubah ikut diproses.
     */
    public function settlementBulkApprove(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $request->validate([
            'ids'        => 'required|array|min:1|max:50',
            'ids.*'      => 'integer',
            'expected'   => 'nullable|array',
            'expected.*' => 'nullable|string|max:30',
        ]);

        $ids      = array_values(array_unique(array_map('intval', (array) $request->input('ids', []))));
        $expected = (array) $request->input('expected', []);
        $visible  = $this->visibleCrs($ids)->keyBy('id');

        $ok     = 0;
        $errors = [];

        foreach ($ids as $id) {
            $cr = $visible->get($id);
            if (! $cr) {
                $errors[] = '#' . $id . ' (tidak ditemukan atau tidak ada akses).';
                continue;
            }

            $exp = $expected[$id] ?? null;
            if ($exp !== null && $exp !== $cr->status) {
                $errors[] = 'CR ' . $cr->code . ': status berubah dari ' . $exp . ' menjadi ' . $cr->status . ' — muat ulang.';
                continue;
            }

            $roles = BudgetRequest::SETTLE_BULK_ROLES[$cr->status] ?? null;
            if (! $roles) {
                $errors[] = 'CR ' . $cr->code . ': status ' . $cr->status . ' bukan tahap settlement.';
                continue;
            }
            if (! $user->hasRole(array_merge(['superadmin'], $roles))) {
                $errors[] = 'CR ' . $cr->code . ': peran Anda tidak berhak pada tahap ' . $cr->status . '.';
                continue;
            }

            $error = $this->jalankanLangkahSettlement($request, $cr);
            if ($error) { $errors[] = $error; } else { $ok++; }
        }

        return $this->settlementBulkResponse($ok, $errors, 'di-approve');
    }

    /** Satu langkah approve settlement untuk bulk; mengembalikan pesan error atau null. */
    private function jalankanLangkahSettlement(Request $request, BudgetRequest $cr): ?string
    {
        // Tanpa Expense Claim, tahap Finance Manager = Close (sama dengan bulk Close Todo List).
        if ($cr->status === 'SETTLE_VERIFIED' && ! $cr->hasExpenseClaim()) {
            return $this->applySettlementClose($cr, $request->notes);
        }

        $metode = match ($cr->status) {
            'REALIZED'        => 'headCheckSettlement',
            'SETTLE_CHECKED'  => 'financeVerifySettlement',
            'SETTLE_VERIFIED' => 'settleApprove',
            'SETTLE_APPROVED' => 'secondApprove',
        };

        // Aksi satuan mengembalikan redirect + flash; di sini hanya flash 'error'-nya yang dibaca.
        session()->forget(['success', 'error']);
        try {
            $this->{$metode}($request, $cr->id);
            $error = session('error');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            $error = $e->getMessage() ?: 'akses ditolak.';
        }
        session()->forget(['success', 'error']);

        return $error ? 'CR ' . $cr->code . ': ' . $error : null;
    }

    /** Bulk Close dari To-Do List (multi-select baris). */
    public function settlementBulkClose(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $this->authorizeSettlementClose($user);

        $ids    = (array) $request->input('ids', []);
        $ok     = 0;
        $errors = [];

        foreach ($this->visibleCrs($ids) as $cr) {
            // CR ber-Expense Claim tidak di-Close melainkan di-Settle Approve —
            // lewat method satuannya supaya aturan & notifikasinya satu sumber.
            if ($cr->hasExpenseClaim()) {
                session()->forget('error');
                try {
                    $this->settleApprove($request, $cr->id);
                    $error = session('error');
                } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
                    $error = 'CR ' . $cr->code . ': ' . ($e->getMessage() ?: 'akses ditolak.');
                }
                session()->forget(['success', 'error']);
            } else {
                $error = $this->applySettlementClose($cr, $request->notes);
            }
            if ($error) { $errors[] = $error; } else { $ok++; }
        }

        return $this->settlementBulkResponse($ok, $errors, 'di-approve');
    }

    /** Bulk Reject dari To-Do List (multi-select baris). */
    public function settlementBulkReject(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $this->authorizeSettlementClose($user);
        $request->validate(['notes' => 'required|string|max:500']);

        $ids    = (array) $request->input('ids', []);
        $ok     = 0;
        $errors = [];

        foreach ($this->visibleCrs($ids) as $cr) {
            $error = $this->applySettlementRejectClose($cr, $request->notes);
            if ($error) { $errors[] = $error; } else { $ok++; }
        }

        return $this->settlementBulkResponse($ok, $errors, 'ditolak');
    }

    /**
     * Satu baris berhasil dan satu baris gagal harus dua-duanya sampai ke user
     * — kalau hanya jumlah sukses yang ditampilkan, CR yang tertinggal
     * diam-diam dianggap beres.
     */
    private function settlementBulkResponse(int $ok, array $errors, string $verb)
    {
        $redirect = back();

        if ($ok) {
            $redirect->with('success', $ok . ' Settlement berhasil ' . $verb . '.');
        }
        if ($errors) {
            $redirect->with('error', count($errors) . ' CR dilewati: ' . implode(' ', array_slice($errors, 0, 5))
                . (count($errors) > 5 ? ' ...' : ''));
        }

        return $redirect;
    }
}
