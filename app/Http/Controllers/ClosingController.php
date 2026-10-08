<?php

namespace App\Http\Controllers;

use App\Models\Closing;
use App\Models\UserActivityLog;
use App\Services\ClosingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * M-04 — Tutup Periode (month-end close).
 *
 * CATATAN PERUBAHAN: halaman ini sebelumnya MOCKUP — seluruh angkanya ditulis
 * tangan di controller dan tidak ada aksi yang benar-benar mengubah apa pun.
 * Sekarang tersambung ke data nyata lewat ClosingService, yang mengumpulkan
 * transaksi dari Treasury, Invoice, Collection, Cash Receipt, Cost Request,
 * dan Purchase Order.
 *
 * Closing TIDAK menyalin transaksi: tiap baris rekap menunjuk balik ke dokumen
 * sumbernya (source_module + source_id), dan rekapnya selalu bisa dihitung
 * ulang untuk mendeteksi transaksi yang masuk setelah periode ditutup.
 */
class ClosingController extends Controller
{
    public function __construct(private ClosingService $closingService)
    {
    }

    public function index(Request $request)
    {
        $period = $request->query('periode') ?: now()->format('Y-m');

        // Periode yang bisa dipilih: 12 bulan terakhir + periode yang sudah
        // punya dokumen closing (supaya periode lama tetap bisa dibuka).
        $periods = collect(range(0, 11))
            ->map(fn ($i) => now()->startOfMonth()->subMonths($i)->format('Y-m'))
            ->merge(Closing::pluck('period'))
            ->unique()
            ->sortDesc()
            ->mapWithKeys(fn ($p) => [
                $p => \Carbon\Carbon::createFromFormat('Y-m', $p)->translatedFormat('F Y'),
            ]);

        $closing = Closing::with(['details', 'createdBy', 'closedBy', 'reopenedBy'])
            ->where('period', $period)
            ->first();

        $start = \Carbon\Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end   = \Carbon\Carbon::createFromFormat('Y-m', $period)->endOfMonth();

        // Keadaan data SEKARANG untuk periode ini — dihitung apa pun status
        // closing-nya, karena justru perbandingan inilah isi period control.
        $live        = $this->closingService->collect($start->format('Y-m-d'), $end->format('Y-m-d'));
        $liveSummary = $this->closingService->summarize($live);

        $drift = $closing
            ? $this->closingService->drift($closing)
            : ['missing' => collect(), 'stale' => collect(), 'has_difference' => false];

        return view('closing.index', [
            'period'      => $period,
            'periods'     => $periods,
            'startDate'   => $start,
            'endDate'     => $end,
            'closing'     => $closing,
            'live'        => $live,
            'liveSummary' => $liveSummary,
            'drift'       => $drift,
            'modules'     => ClosingService::MODULES,
            'history'     => Closing::with('closedBy')->orderByDesc('period')->limit(12)->get(),
        ]);
    }

    /**
     * Susun (atau susun ulang) rekap periode. Aman diulang: detail lama
     * dihapus dan dibangun kembali dari dokumen sumbernya, jadi menjalankan
     * ini dua kali tidak pernah menggandakan baris.
     */
    public function build(Request $request)
    {
        $request->validate(['period' => 'required|date_format:Y-m']);

        $period = $request->period;
        $start  = \Carbon\Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end    = \Carbon\Carbon::createFromFormat('Y-m', $period)->endOfMonth();

        $closing = Closing::where('period', $period)->first();

        if ($closing && $closing->isClosed()) {
            return back()->with('error', 'Periode ' . $period . ' sudah ditutup. Buka kembali dulu kalau rekapnya perlu disusun ulang.');
        }

        DB::transaction(function () use (&$closing, $period, $start, $end) {
            $closing ??= Closing::create([
                'code'       => Closing::generateCode($period),
                'period'     => $period,
                'start_date' => $start,
                'end_date'   => $end,
                'status'     => 'DRAFT',
                'created_by' => Auth::id(),
            ]);

            $closing->details()->delete();

            $rows = $this->closingService->collect($start->format('Y-m-d'), $end->format('Y-m-d'));

            foreach ($rows->chunk(500) as $chunk) {
                $closing->details()->insert(
                    $chunk->map(fn ($r) => array_merge($r, [
                        'closing_id' => $closing->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]))->all()
                );
            }

            $closing->recalculateTotals();
        });

        UserActivityLog::record('closing', 'process', 'Menyusun rekap closing periode ' . $period, $closing, null, $period);

        return redirect()->route('closing.index', ['periode' => $period])
            ->with('success', 'Rekap periode ' . $period . ' disusun: ' . number_format($closing->total_transaction) . ' transaksi.');
    }

    public function close(Request $request, $id)
    {
        $closing = Closing::with('details')->findOrFail($id);

        if ($closing->isClosed()) {
            return back()->with('error', 'Periode ini sudah ditutup.');
        }

        if ($closing->details->isEmpty()) {
            return back()->with('error', 'Rekap masih kosong — susun rekap dulu sebelum menutup periode.');
        }

        // Menutup periode yang rekapnya sudah tidak cocok dengan data akan
        // menyimpan angka yang salah, jadi dihentikan di sini.
        if ($this->closingService->drift($closing)['has_difference']) {
            return back()->with('error', 'Ada transaksi yang belum masuk rekap. Susun ulang rekap dulu, lalu tutup periodenya.');
        }

        $closing->update([
            'status'    => 'CLOSED',
            'closed_by' => Auth::id(),
            'closed_at' => now(),
            'notes'     => $request->input('notes', $closing->notes),
        ]);

        UserActivityLog::record('closing', 'close', 'Menutup periode ' . $closing->period, $closing, null, $closing->period);

        return back()->with('success', 'Periode ' . $closing->period . ' ditutup.');
    }

    public function reopen(Request $request, $id)
    {
        $request->validate(['reopen_reason' => 'required|string|max:255']);

        $closing = Closing::findOrFail($id);

        if (!$closing->isClosed()) {
            return back()->with('error', 'Periode ini belum ditutup.');
        }

        $closing->update([
            'status'        => 'REOPENED',
            'reopened_by'   => Auth::id(),
            'reopened_at'   => now(),
            'reopen_reason' => $request->reopen_reason,
        ]);

        UserActivityLog::record('closing', 'process', 'Membuka kembali periode ' . $closing->period, $closing, $request->reason ?? $request->notes, $closing->period);

        return back()->with('success', 'Periode ' . $closing->period . ' dibuka kembali.');
    }
}
