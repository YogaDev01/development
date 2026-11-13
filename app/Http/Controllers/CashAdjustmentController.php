<?php

namespace App\Http\Controllers;

use App\Models\CashAdjustment;
use App\Support\PeriodLock;
use App\Models\SourceFund;
use App\Models\UserActivityLog;
use App\Services\CashBalanceService;
use App\Support\DocumentSequence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Cash & Bank — Cash Adjustment. Satu-satunya jalan mengoreksi saldo (tidak ada
 * edit saldo langsung). PENDING tidak memengaruhi saldo; APPROVED dibaca
 * CashFlowService. Wajib alasan, pembuat ≠ penyetuju, dan tidak boleh membuat
 * saldo rekening negatif.
 */
class CashAdjustmentController extends Controller
{
    public const CREATE_ROLES  = ['superadmin', 'finance_manager', 'finance'];
    public const APPROVE_ROLES = ['superadmin', 'finance_manager'];

    public function __construct(private CashBalanceService $balances)
    {
    }

    public function index()
    {
        return view('cash-adjustments.index', [
            'adjustments' => CashAdjustment::with(['sourceFund:id,name', 'creator:id,username'])
                ->orderByDesc('effective_date')->orderByDesc('id')->limit(500)->get(),
            'canCreate'   => $this->can(self::CREATE_ROLES),
        ]);
    }

    public function create()
    {
        return view('cash-adjustments.create', [
            'funds' => SourceFund::where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name']),
            'types' => CashAdjustment::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'source_fund_id' => 'required|exists:source_funds,id',
            'effective_date' => 'required|date',
            'type'           => ['required', Rule::in(array_keys(CashAdjustment::TYPES))],
            'direction'      => ['nullable', Rule::in(['IN', 'OUT'])],
            'amount'         => 'required|string|max:25',
            'reason'         => 'required|string|min:5|max:500',
            'attachment'     => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Arah mengikuti tipe bila tipenya tetap (biaya bank = keluar, bunga = masuk).
        $direction = CashAdjustment::TYPES[$data['type']][1] ?? ($data['direction'] ?? null);
        if (! $direction) {
            return back()->withInput()->with('error', 'Pilih arah penyesuaian (masuk / keluar).');
        }

        $amount = preg_replace('/[^0-9]/', '', $data['amount']);
        if ($amount === '' || (int) $amount <= 0) {
            return back()->withInput()->with('error', 'Nominal harus lebih dari 0.');
        }

        if (! SourceFund::whereKey($data['source_fund_id'])->where('status', 'ACTIVE')->exists()) {
            return back()->withInput()->with('error', 'Rekening harus aktif.');
        }
        if ($msg = $this->balances->dateBeforeOpening((int) $data['source_fund_id'], $data['effective_date'])
            ?? $this->balances->dateInLockedPeriod((int) $data['source_fund_id'], $data['effective_date'])) {
            return back()->withInput()->with('error', $msg);
        }

        $adj = DB::transaction(function () use ($request, $data, $direction, $amount) {
            // Kunci periode: tanggal efektif tidak boleh jatuh di periode Closing CLOSED.
            PeriodLock::assertOpen($data['effective_date'], 'Cash Adjustment', $request, null, null, true);

            $n = DocumentSequence::next('cash-adjustment:' . now()->year, fn () => 0);

            return CashAdjustment::create([
                'adjustment_number' => sprintf('ADJ/%d/%04d', now()->year, $n),
                'source_fund_id'    => $data['source_fund_id'],
                'effective_date'    => $data['effective_date'],
                'type'              => $data['type'],
                'direction'         => $direction,
                'amount'            => (int) $amount,
                'reason'            => $data['reason'],
                'attachment'        => $request->hasFile('attachment') ? $request->file('attachment')->store('cash_adjustment_documents', 'secure') : null,
                'status'            => CashAdjustment::PENDING,
                'created_by'        => Auth::id(),
            ]);
        });

        UserActivityLog::record('cash_bank', 'create', 'Membuat Cash Adjustment ' . $adj->adjustment_number, $adj, $adj->reason);

        return redirect()->route('cash-adjustments.show', $adj->id)->with('success', 'Adjustment dibuat, menunggu persetujuan.');
    }

    public function show($id)
    {
        return view('cash-adjustments.show', [
            'a'          => CashAdjustment::with(['sourceFund', 'creator:id,username', 'approver:id,username'])->findOrFail($id),
            'canApprove' => $this->can(self::APPROVE_ROLES),
            'canCreate'  => $this->can(self::CREATE_ROLES),
        ]);
    }

    public function approve(Request $request, $id)
    {
        $result = DB::transaction(function () use ($id, $request) {
            $a = CashAdjustment::lockForUpdate()->findOrFail($id);
            if ($a->status !== CashAdjustment::PENDING) {
                return 'status';
            }
            if ((int) $a->created_by === (int) Auth::id()) {
                return 'self';
            }

            SourceFund::whereKey($a->source_fund_id)->lockForUpdate()->first();

            $d = $a->effective_date->format('Y-m-d');
            if ($this->balances->dateBeforeOpening($a->source_fund_id, $d) ?? $this->balances->dateInLockedPeriod($a->source_fund_id, $d)) {
                return 'opening';
            }
            if ($a->direction === 'OUT' && $this->balances->balanceOf($a->source_fund_id) < $a->amount) {
                return 'balance';
            }

            // Kunci periode: persetujuan memasukkan adjustment ke saldo rekening pada tanggal efektifnya.
            // Terakhir, setelah semua pemeriksaan lain lolos, agar log penembusan tidak tertulis untuk aksi yang gagal.
            PeriodLock::assertOpen($d, 'Cash Adjustment', $request, $a, null, true);

            $a->update(['status' => CashAdjustment::APPROVED, 'approved_by' => Auth::id(), 'approved_at' => now()]);
            UserActivityLog::record('cash_bank', 'approve', 'Menyetujui Cash Adjustment ' . $a->adjustment_number, $a);

            return 'ok';
        });

        return match ($result) {
            'status'  => back()->with('error', 'Adjustment tidak berstatus menunggu persetujuan.'),
            'self'    => back()->with('error', 'Pembuat adjustment tidak boleh menyetujuinya sendiri.'),
            'balance' => back()->with('error', 'Saldo rekening tidak mencukupi; saldo tidak boleh negatif.'),
            'opening' => back()->with('error', 'Tanggal efektif sebelum saldo awal atau di dalam periode rekonsiliasi terkunci.'),
            default   => back()->with('success', 'Adjustment disetujui dan masuk ke saldo.'),
        };
    }

    public function reject(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|max:255']);

        $ok = DB::transaction(function () use ($request, $id) {
            $a = CashAdjustment::lockForUpdate()->findOrFail($id);
            if ($a->status !== CashAdjustment::PENDING) {
                return false;
            }
            $a->update(['status' => CashAdjustment::REJECTED, 'approved_by' => Auth::id(), 'approved_at' => now(), 'reject_reason' => $request->reason]);
            UserActivityLog::record('cash_bank', 'reject', 'Menolak Cash Adjustment ' . $a->adjustment_number, $a, $request->reason);

            return true;
        });

        return $ok ? back()->with('success', 'Adjustment ditolak.') : back()->with('error', 'Adjustment tidak berstatus menunggu persetujuan.');
    }

    public function cancel($id)
    {
        $ok = DB::transaction(function () use ($id) {
            $a = CashAdjustment::lockForUpdate()->findOrFail($id);
            if ($a->status !== CashAdjustment::PENDING) {
                return false;
            }
            $a->update(['status' => CashAdjustment::CANCELLED]);
            UserActivityLog::record('cash_bank', 'cancel', 'Membatalkan Cash Adjustment ' . $a->adjustment_number, $a);

            return true;
        });

        return $ok ? back()->with('success', 'Adjustment dibatalkan.') : back()->with('error', 'Hanya adjustment yang menunggu persetujuan yang bisa dibatalkan.');
    }

    private function can(array $roles): bool
    {
        return (bool) Auth::user()?->hasRole($roles);
    }
}
