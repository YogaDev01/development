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
    | SETTLE_VERIFIED (Finance) -> CLOSED (CEO). Reject di tiap tahap balik ke
    | PAID (manager mengajukan ulang Settlement).
    |--------------------------------------------------------------------------
    */

    public function headCheckSettlement(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'head'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk approval tahap ini.');
        }

        $this->findVisibleCr($id);

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
        $this->findVisibleCr($id);

        // Transisi terkunci: lihat Concerns\TransitionsStatus.
        $cr = $this->transitionStatus(BudgetRequest::class, $id, 'REALIZED', ['status' => 'PAID'], function ($cr) use ($request) {
            $cr->addLog('REALIZED', 'PAID', 'settlement_rejected', $request->notes);
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

        return back()->with('success', 'Settlement ditolak. CR dikembalikan ke status PAID.');
    }

    public function financeVerifySettlement(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole(['superadmin', 'finance', 'finance_manager', 'cost_control'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk approval tahap ini.');
        }

        $cr = $this->findVisibleCr($id);
        if ($cr->status !== 'SETTLE_CHECKED') {
            return back()->with('error', 'Status tidak valid.');
        }

        DB::transaction(function () use ($cr, $request) {
            $cr->update(['status' => 'SETTLE_VERIFIED']);
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
        $this->findVisibleCr($id);

        // Transisi terkunci: lihat Concerns\TransitionsStatus.
        $cr = $this->transitionStatus(BudgetRequest::class, $id, 'SETTLE_CHECKED', ['status' => 'PAID'], function ($cr) use ($request) {
            $cr->addLog('SETTLE_CHECKED', 'PAID', 'settlement_rejected', $request->notes);
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

        return back()->with('success', 'Settlement ditolak. CR dikembalikan ke status PAID.');
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

        $this->findVisibleCr($id);

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

    /** Finance Manager — reject Expense Claim di tahap Settle Approve: CR kembali ke PAID. */
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

        return back()->with('success', 'Expense Claim ditolak. CR dikembalikan ke status PAID untuk disettle ulang.');
    }

    /** Approver — reject Expense Claim di tahap Second Approve: CR kembali ke PAID. */
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

        return back()->with('success', 'Expense Claim ditolak. CR dikembalikan ke status PAID untuk disettle ulang.');
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
     * Inti Reject tahap Close: SETTLE_VERIFIED -> PAID, sama seperti reject
     * Head/Finance pada tahap sebelumnya. Second Payment PENDING yang lahir
     * saat verify ikut dihapus — selisihnya belum tentu sama lagi setelah
     * settlement diperbaiki. Kalau baris itu sudah masuk batch Treasury
     * (apalagi sudah PAID), reject ditolak: uangnya sudah bergerak.
     */
    /**
     * @param string[] $dariStatus status yang boleh ditolak dari tahap ini.
     */
    private function applySettlementRejectClose(BudgetRequest $cr, string $notes, array $dariStatus = ['SETTLE_VERIFIED']): ?string
    {
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
            $cr->update(['status' => 'PAID']);
            $cr->addLog($dari, 'PAID', 'settlement_rejected', $notes);
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

        return back()->with('success', 'Settlement ditolak. CR dikembalikan ke status PAID.');
    }

    /** CR terpilih yang benar-benar boleh diakses user ini (bulk). */
    private function visibleCrs(array $ids)
    {
        return BudgetRequest::whereIn('id', $ids)->get()
            ->filter(fn (BudgetRequest $cr) => $this->canViewCr($cr));
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
