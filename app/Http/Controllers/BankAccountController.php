<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\CashOpeningBalance;
use App\Models\SourceFund;
use App\Models\UserActivityLog;
use App\Services\CashBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Cash & Bank — Bank Accounts.
 *
 * Memakai tabel master `source_funds` (rekening yang sudah dipakai Treasury,
 * Cash Receipt, dan Forecast); tidak ada tabel rekening kedua. Tidak ada fitur
 * mengedit saldo langsung: saldo hanya berasal dari saldo awal (maker-checker)
 * + transaksi di ledger.
 *
 * Akses: lihat = menu 'bank-accounts'. Tulis = WRITE_ROLES (route + Blade).
 */
class BankAccountController extends Controller
{
    /** Cermin route role: pada routes/web.php dan kondisi @if di Blade. */
    public const WRITE_ROLES = ['superadmin', 'finance_manager'];

    public function __construct(private CashBalanceService $balances)
    {
    }

    public function index()
    {
        $snapshot = $this->balances->snapshot();

        return view('bank-accounts.index', [
            'accounts' => $snapshot['accounts'],
            'kpi'      => $snapshot['summary'],
            'canWrite' => $this->canWrite(),
        ]);
    }

    public function create()
    {
        return view('bank-accounts.form', $this->formData(new SourceFund(['account_type' => 'BANK', 'currency' => 'IDR', 'status' => 'ACTIVE'])));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['input_by'] = Auth::id();

        $fund = DB::transaction(function () use ($data) {
            $fund = SourceFund::create($data);
            if (blank($fund->code)) {
                $fund->update(['code' => 'ACC-' . str_pad((string) $fund->id, 4, '0', STR_PAD_LEFT)]);
            }

            return $fund;
        });

        UserActivityLog::record('cash_bank', 'create', 'Membuat rekening ' . $fund->name, $fund);

        return redirect()->route('bank-accounts.show', $fund->id)->with('success', 'Rekening dibuat. Atur saldo awal agar saldo terhitung.');
    }

    public function edit($id)
    {
        return view('bank-accounts.form', $this->formData(SourceFund::findOrFail($id)));
    }

    public function update(Request $request, $id)
    {
        $fund = SourceFund::findOrFail($id);
        $fund->update($this->validated($request, $fund));

        UserActivityLog::record('cash_bank', 'update', 'Mengubah rekening ' . $fund->name, $fund);

        return redirect()->route('bank-accounts.show', $fund->id)->with('success', 'Rekening diperbarui.');
    }

    /** Aktifkan / nonaktifkan. Rekening tidak pernah dihapus. */
    public function toggle($id)
    {
        $fund = SourceFund::findOrFail($id);
        $fund->update(['status' => strtoupper((string) $fund->status) === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE']);

        UserActivityLog::record('cash_bank', 'update', ($fund->status === 'ACTIVE' ? 'Mengaktifkan' : 'Menonaktifkan') . ' rekening ' . $fund->name, $fund);

        return back()->with('success', 'Status rekening: ' . $fund->status . '.');
    }

    public function show($id)
    {
        $fund     = SourceFund::with(['bank:id,name', 'openingBalance.creator:id,username', 'openingBalance.verifier:id,username'])->findOrFail($id);
        $snapshot = $this->balances->snapshot();
        $account  = $snapshot['accounts']->first(fn ($a) => $a['fund']->id === $fund->id);

        return view('bank-accounts.show', [
            'fund'     => $fund,
            'account'  => $account,
            'trend'    => $this->balances->monthlyTrend($account),
            'ledger'   => $account['rows']->sortByDesc(fn ($r) => $r['date']->format('Y-m-d'))->values(),
            'opening'  => $fund->openingBalance,
            'recons'   => \App\Models\CashReconciliation::where('source_fund_id', $fund->id)->orderByDesc('period_end')->limit(24)->get(),
            'canWrite' => $this->canWrite(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SALDO AWAL
    |--------------------------------------------------------------------------
    */

    /** Ajukan / perbarui saldo awal (hanya selama belum APPROVED). */
    public function storeOpening(Request $request, $id)
    {
        $fund = SourceFund::findOrFail($id);

        $request->validate([
            'amount'         => 'required|string|max:25',
            'effective_date' => 'required|date',
            'document'       => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'notes'          => 'nullable|string|max:1000',
        ]);

        $amount = $this->normalizeMoney($request->amount);
        if ($amount === null) {
            return back()->withInput()->with('error', 'Nominal saldo awal tidak valid.');
        }

        $result = DB::transaction(function () use ($request, $fund, $amount) {
            $existing = CashOpeningBalance::where('source_fund_id', $fund->id)->lockForUpdate()->first();

            if ($existing && $existing->isApproved()) {
                return 'locked';
            }

            $payload = [
                'amount'         => $amount,
                'effective_date' => $request->effective_date,
                'status'         => CashOpeningBalance::PENDING,
                'notes'          => $request->notes,
                'created_by'     => Auth::id(),
                'verified_by'    => null,
                'verified_at'    => null,
                'reject_reason'  => null,
            ];
            if ($request->hasFile('document')) {
                $payload['supporting_document'] = $request->file('document')->store('cash_opening_documents', 'secure');
            }

            $ob = $existing
                ? tap($existing)->update($payload)
                : CashOpeningBalance::create($payload + ['source_fund_id' => $fund->id]);

            UserActivityLog::record('cash_bank', 'update', 'Mengajukan saldo awal rekening ' . $fund->name, $ob);

            return 'ok';
        });

        if ($result === 'locked') {
            return back()->with('error', 'Saldo awal sudah disetujui dan terkunci. Koreksi hanya lewat Cash Adjustment.');
        }

        return back()->with('success', 'Saldo awal diajukan, menunggu verifikasi.');
    }

    /** Verifikasi (setujui) saldo awal — verifier TIDAK boleh sama dengan pembuat. */
    public function verifyOpening($id)
    {
        $result = DB::transaction(function () use ($id) {
            $ob = CashOpeningBalance::lockForUpdate()->findOrFail($id);

            if ($ob->status !== CashOpeningBalance::PENDING) {
                return 'status';
            }
            if ((int) $ob->created_by === (int) Auth::id()) {
                return 'self';
            }

            $ob->update([
                'status'      => CashOpeningBalance::APPROVED,
                'verified_by' => Auth::id(),
                'verified_at' => now(),
            ]);
            UserActivityLog::record('cash_bank', 'approve', 'Menyetujui saldo awal rekening #' . $ob->source_fund_id, $ob);

            return 'ok';
        });

        return match ($result) {
            'status' => back()->with('error', 'Saldo awal tidak berstatus menunggu verifikasi.'),
            'self'   => back()->with('error', 'Pembuat saldo awal tidak boleh memverifikasinya sendiri.'),
            default  => back()->with('success', 'Saldo awal disetujui dan terkunci.'),
        };
    }

    public function rejectOpening(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|max:255']);

        $result = DB::transaction(function () use ($request, $id) {
            $ob = CashOpeningBalance::lockForUpdate()->findOrFail($id);

            if ($ob->status !== CashOpeningBalance::PENDING) {
                return 'status';
            }
            if ((int) $ob->created_by === (int) Auth::id()) {
                return 'self';
            }

            $ob->update([
                'status'        => CashOpeningBalance::REJECTED,
                'verified_by'   => Auth::id(),
                'verified_at'   => now(),
                'reject_reason' => $request->reason,
            ]);
            UserActivityLog::record('cash_bank', 'reject', 'Menolak saldo awal rekening #' . $ob->source_fund_id, $ob, $request->reason);

            return 'ok';
        });

        return match ($result) {
            'status' => back()->with('error', 'Saldo awal tidak berstatus menunggu verifikasi.'),
            'self'   => back()->with('error', 'Pembuat saldo awal tidak boleh menolaknya sendiri.'),
            default  => back()->with('success', 'Saldo awal ditolak; dapat diajukan ulang.'),
        };
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER
    |--------------------------------------------------------------------------
    */

    private function canWrite(): bool
    {
        return (bool) Auth::user()?->hasRole(self::WRITE_ROLES);
    }

    private function formData(SourceFund $fund): array
    {
        return [
            'fund'  => $fund,
            'banks' => Bank::orderBy('name')->get(['id', 'name']),
            'types' => SourceFund::TYPES,
        ];
    }

    private function validated(Request $request, ?SourceFund $fund = null): array
    {
        $data = $request->validate([
            'code'           => ['nullable', 'string', 'max:30', Rule::unique('source_funds', 'code')->ignore($fund?->id)],
            'name'           => 'required|string|max:255',
            'account_type'   => ['required', Rule::in(array_keys(SourceFund::TYPES))],
            'bank_id'        => 'nullable|exists:banks,id',
            'no_account'     => 'nullable|string|max:50',
            'account_holder' => 'nullable|string|max:255',
            'branch'         => 'nullable|string|max:255',
            'currency'       => 'required|string|size:3',
            'description'    => 'nullable|string|max:1000',
            'status'         => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
        ]);

        $data['currency'] = strtoupper($data['currency']);
        if (blank($data['code'] ?? null)) {
            unset($data['code']);
        }

        return $data;
    }

    /** "1.234.567" / "-1.234.567" → int; null bila bukan angka. */
    private function normalizeMoney(string $value): ?int
    {
        $neg   = str_starts_with(trim($value), '-');
        $clean = preg_replace('/[^0-9]/', '', $value);

        return $clean === '' ? null : ($neg ? -1 : 1) * (int) $clean;
    }
}
