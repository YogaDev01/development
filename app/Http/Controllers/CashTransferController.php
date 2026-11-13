<?php

namespace App\Http\Controllers;

use App\Models\CashTransfer;
use App\Support\PeriodLock;
use App\Models\SourceFund;
use App\Models\UserActivityLog;
use App\Services\CashBalanceService;
use App\Support\DocumentSequence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Cash & Bank — Internal Transfer antar rekening perusahaan.
 *
 * Dokumen PENDING tidak memengaruhi saldo. Saat APPROVED: Cash Out di rekening
 * asal + Cash In di rekening tujuan (CashFlowService membaca keduanya, total
 * konsolidasi tidak berubah) dan biaya transfer menjadi pengeluaran terpisah.
 * Persetujuan memakai satu transaksi DB dengan kunci baris, jadi tidak bisa
 * disetujui dua kali dan dua sisi tercatat atomik (satu baris dokumen).
 *
 * Ini terpisah dari Top Up Treasury (tetap berjalan, tidak disentuh).
 */
class CashTransferController extends Controller
{
    /** Cermin route role: dan Blade. */
    public const CREATE_ROLES  = ['superadmin', 'finance_manager', 'finance'];
    public const APPROVE_ROLES = ['superadmin', 'finance_manager'];

    public function __construct(private CashBalanceService $balances)
    {
    }

    public function index()
    {
        return view('cash-transfers.index', [
            'transfers'  => CashTransfer::with(['fromFund:id,name', 'toFund:id,name', 'creator:id,username'])
                ->orderByDesc('transfer_date')->orderByDesc('id')->limit(500)->get(),
            'canCreate'  => $this->can(self::CREATE_ROLES),
        ]);
    }

    public function create()
    {
        return view('cash-transfers.create', [
            'funds' => SourceFund::where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name', 'account_type']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'transfer_date' => 'required|date',
            'from_fund_id'  => 'required|different:to_fund_id|exists:source_funds,id',
            'to_fund_id'    => 'required|exists:source_funds,id',
            'amount'        => 'required|string|max:25',
            'fee'           => 'nullable|string|max:25',
            'description'   => 'nullable|string|max:255',
            'attachment'    => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $amount = $this->money($data['amount']);
        $fee    = $this->money($data['fee'] ?? '0');
        if ($amount === null || $amount <= 0 || $fee === null || $fee < 0) {
            return back()->withInput()->with('error', 'Nominal transfer harus lebih dari 0 dan biaya tidak boleh negatif.');
        }

        $inactive = SourceFund::whereIn('id', [$data['from_fund_id'], $data['to_fund_id']])->where('status', '!=', 'ACTIVE')->exists();
        if ($inactive) {
            return back()->withInput()->with('error', 'Rekening asal dan tujuan harus aktif.');
        }

        foreach ([(int) $data['from_fund_id'], (int) $data['to_fund_id']] as $fundId) {
            if ($msg = $this->balances->dateBeforeOpening($fundId, $data['transfer_date'])
                ?? $this->balances->dateInLockedPeriod($fundId, $data['transfer_date'])) {
                return back()->withInput()->with('error', $msg);
            }
        }

        $transfer = DB::transaction(function () use ($request, $data, $amount, $fee) {
            // Kunci periode: tanggal transfer tidak boleh jatuh di periode Closing CLOSED.
            PeriodLock::assertOpen($data['transfer_date'], 'Internal Transfer', $request, null, null, true);

            $n = DocumentSequence::next('cash-transfer:' . now()->year, fn () => 0);

            return CashTransfer::create([
                'transfer_number' => sprintf('TRF/%d/%04d', now()->year, $n),
                'transfer_date'   => $data['transfer_date'],
                'from_fund_id'    => $data['from_fund_id'],
                'to_fund_id'      => $data['to_fund_id'],
                'amount'          => $amount,
                'fee'             => $fee,
                'description'     => $data['description'] ?? null,
                'attachment'      => $request->hasFile('attachment') ? $request->file('attachment')->store('cash_transfer_documents', 'secure') : null,
                'status'          => CashTransfer::PENDING,
                'created_by'      => Auth::id(),
            ]);
        });

        UserActivityLog::record('cash_bank', 'create', 'Membuat Internal Transfer ' . $transfer->transfer_number, $transfer);

        return redirect()->route('cash-transfers.show', $transfer->id)->with('success', 'Transfer dibuat, menunggu persetujuan.');
    }

    public function show($id)
    {
        return view('cash-transfers.show', [
            't'          => CashTransfer::with(['fromFund', 'toFund', 'creator:id,username', 'approver:id,username'])->findOrFail($id),
            'canApprove' => $this->can(self::APPROVE_ROLES),
            'canCreate'  => $this->can(self::CREATE_ROLES),
        ]);
    }

    public function approve(Request $request, $id)
    {
        $result = DB::transaction(function () use ($id, $request) {
            $t = CashTransfer::lockForUpdate()->findOrFail($id);
            if ($t->status !== CashTransfer::PENDING) {
                return 'status';
            }
            if ((int) $t->created_by === (int) Auth::id()) {
                return 'self';
            }

            // Kunci rekening asal supaya dua persetujuan bersamaan tidak sama-sama lolos.
            SourceFund::whereKey($t->from_fund_id)->lockForUpdate()->first();

            if ($this->balances->balanceOf($t->from_fund_id) < $t->amount + $t->fee) {
                return 'balance';
            }
            $d = $t->transfer_date->format('Y-m-d');
            if ($this->balances->dateBeforeOpening($t->from_fund_id, $d) ?? $this->balances->dateBeforeOpening($t->to_fund_id, $d)
                ?? $this->balances->dateInLockedPeriod($t->from_fund_id, $d) ?? $this->balances->dateInLockedPeriod($t->to_fund_id, $d)) {
                return 'opening';
            }

            // Kunci periode: persetujuan memasukkan transfer ke saldo kedua rekening pada tanggalnya.
            // Terakhir, setelah semua pemeriksaan lain lolos, agar log penembusan tidak tertulis untuk aksi yang gagal.
            PeriodLock::assertOpen($d, 'Internal Transfer', $request, $t, null, true);

            $t->update(['status' => CashTransfer::APPROVED, 'approved_by' => Auth::id(), 'approved_at' => now()]);
            UserActivityLog::record('cash_bank', 'approve', 'Menyetujui Internal Transfer ' . $t->transfer_number, $t);

            return 'ok';
        });

        return match ($result) {
            'status'  => back()->with('error', 'Transfer tidak berstatus menunggu persetujuan.'),
            'self'    => back()->with('error', 'Pembuat transfer tidak boleh menyetujuinya sendiri.'),
            'balance' => back()->with('error', 'Saldo rekening asal tidak mencukupi (nominal + biaya). Saldo tidak boleh negatif.'),
            'opening' => back()->with('error', 'Tanggal transfer sebelum saldo awal atau di dalam periode rekonsiliasi terkunci pada salah satu rekening.'),
            default   => back()->with('success', 'Transfer disetujui dan tercatat di kedua rekening.'),
        };
    }

    public function reject(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|max:255']);

        $result = DB::transaction(function () use ($request, $id) {
            $t = CashTransfer::lockForUpdate()->findOrFail($id);
            if ($t->status !== CashTransfer::PENDING) {
                return 'status';
            }
            $t->update(['status' => CashTransfer::REJECTED, 'approved_by' => Auth::id(), 'approved_at' => now(), 'reject_reason' => $request->reason]);
            UserActivityLog::record('cash_bank', 'reject', 'Menolak Internal Transfer ' . $t->transfer_number, $t, $request->reason);

            return 'ok';
        });

        return $result === 'ok' ? back()->with('success', 'Transfer ditolak.') : back()->with('error', 'Transfer tidak berstatus menunggu persetujuan.');
    }

    /** Pembatalan hanya untuk dokumen PENDING (dokumen APPROVED tidak dibatalkan diam-diam). */
    public function cancel($id)
    {
        $result = DB::transaction(function () use ($id) {
            $t = CashTransfer::lockForUpdate()->findOrFail($id);
            if ($t->status !== CashTransfer::PENDING) {
                return false;
            }
            $t->update(['status' => CashTransfer::CANCELLED]);
            UserActivityLog::record('cash_bank', 'cancel', 'Membatalkan Internal Transfer ' . $t->transfer_number, $t);

            return true;
        });

        return $result ? back()->with('success', 'Transfer dibatalkan.') : back()->with('error', 'Hanya transfer yang menunggu persetujuan yang bisa dibatalkan.');
    }

    private function can(array $roles): bool
    {
        return (bool) Auth::user()?->hasRole($roles);
    }

    private function money(string $value): ?int
    {
        $clean = preg_replace('/[^0-9]/', '', $value);

        return $clean === '' ? null : (int) $clean;
    }
}
