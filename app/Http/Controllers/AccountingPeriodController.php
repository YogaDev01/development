<?php

namespace App\Http\Controllers;

use App\Models\AccountingAuditLog;
use App\Models\AccountingPeriod;
use App\Support\AccountingCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Accounting — periode. OPEN menerima posting (termasuk backdate), CLOSED dan
 * LOCKED menolaknya. Membuka kembali periode wajib alasan dan hanya untuk
 * REOPEN_ROLES.
 */
class AccountingPeriodController extends Controller
{
    public const MANAGE_ROLES = \App\Support\AccessRoles::ACCOUNTING_MANAGE;
    public const REOPEN_ROLES = \App\Support\AccessRoles::ACCOUNTING_REOPEN;

    public function index()
    {
        return view('accounting.period.index', [
            'periods'   => AccountingPeriod::with('closedBy:id,username')->withCount('journals')->orderByDesc('start_date')->get(),
            'statuses'  => AccountingCatalog::PERIOD_STATUSES,
            'canManage' => $this->can(self::MANAGE_ROLES),
            'canReopen' => $this->can(self::REOPEN_ROLES),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:60',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'notes'      => 'nullable|string|max:500',
        ]);

        // Periode lebih awal dari periode pertama menggeser tanggal mulai pembukuan (cut-over):
        // ribuan transaksi lama mendadak layak dijurnal. Wajib konfirmasi eksplisit.
        $first = AccountingPeriod::min('start_date');
        if ($first && $data['start_date'] < \Carbon\Carbon::parse($first)->toDateString() && ! $request->boolean('confirm_cutover')) {
            return back()->withInput()->with('error', 'Periode ini lebih awal dari periode pertama (' . \Carbon\Carbon::parse($first)->format('d M Y')
                . ') dan akan menggeser tanggal mulai pembukuan. Centang konfirmasi bila memang disengaja.');
        }

        $overlap = AccountingPeriod::whereDate('start_date', '<=', $data['end_date'])
            ->whereDate('end_date', '>=', $data['start_date'])->first();
        if ($overlap) {
            return back()->withInput()->with('error', 'Rentang tanggal beririsan dengan periode ' . $overlap->name . '.');
        }

        $p = AccountingPeriod::create($data + ['status' => 'OPEN', 'created_by' => Auth::id()]);
        AccountingAuditLog::record('period', $p->id, 'create', null, $p->only(['name', 'start_date', 'end_date']), null, null, 'OPEN');

        return back()->with('success', 'Periode ' . $p->name . ' dibuat (Open).');
    }

    /** OPEN -> CLOSED, CLOSED -> LOCKED. */
    public function close(Request $request, $id)
    {
        return $this->transition($id, ['OPEN' => 'CLOSED', 'CLOSED' => 'LOCKED'], 'close', $request->input('reason'));
    }

    /** CLOSED/LOCKED -> OPEN: wajib alasan, hanya role tertentu (dijaga route). */
    public function reopen(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|min:5|max:500']);

        return $this->transition($id, ['CLOSED' => 'OPEN', 'LOCKED' => 'OPEN'], 'reopen', $request->reason);
    }

    private function transition($id, array $allowed, string $action, ?string $reason)
    {
        $result = DB::transaction(function () use ($id, $allowed, $action, $reason) {
            $p = AccountingPeriod::lockForUpdate()->findOrFail($id);
            if (! isset($allowed[$p->status])) {
                return 'Status periode (' . $p->status . ') tidak bisa diubah dengan aksi ini.';
            }
            $from = $p->status;
            $to = $allowed[$from];
            // Periode tidak boleh ditutup selagi masih ada jurnal Draft/Submitted di dalamnya:
            // jurnal itu tidak akan bisa diposting lagi tanpa membuka kembali periodenya.
            if ($action === 'close' && $from === 'OPEN') {
                $pending = \App\Models\Journal::whereIn('status', ['DRAFT', 'SUBMITTED'])
                    ->whereDate('journal_date', '>=', $p->start_date)->whereDate('journal_date', '<=', $p->end_date)->count();
                if ($pending > 0) {
                    return $pending . ' jurnal masih Draft/Submitted pada periode ini. Posting atau hapus dulu sebelum menutup periode.';
                }

                // Closing operasional (modul Closing) ditutup lebih dulu; baru periode akuntansinya.
                $belumTutup = \App\Support\PeriodLock::unclosedMonths($p->start_date, $p->end_date);
                if ($belumTutup) {
                    return 'Closing operasional bulan ' . implode(', ', $belumTutup) . ' belum ditutup. Tutup dulu di modul Closing sebelum menutup periode akuntansi.';
                }
            }
            $p->update([
                'status'    => $to,
                'closed_by' => $to === 'OPEN' ? null : Auth::id(),
                'closed_at' => $to === 'OPEN' ? null : now(),
            ]);
            AccountingAuditLog::record('period', $p->id, $action, ['status' => $from], ['status' => $to], $reason, $from, $to);

            return null;
        });

        return $result ? back()->with('error', $result) : back()->with('success', 'Status periode diperbarui.');
    }

    private function can(array $roles): bool
    {
        return (bool) Auth::user()?->hasRole($roles);
    }
}
