<?php

namespace App\Http\Controllers;

use App\Models\CashFlow;
use App\Models\SourceFund;
use App\Services\CashFlowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * M-01 — Cash Management / Cash Flow.
 *
 * Dua peran halaman ini:
 *
 *  1. MEMANTAU seluruh pergerakan kas perusahaan — disusun oleh CashFlowService
 *     dari modul-modul yang sudah ada (Cash Receipt, Treasury) plus entri
 *     manual. Tidak ada transaksi yang digandakan; baca catatan di service.
 *
 *  2. MENCATAT pergerakan kas yang tidak punya dokumen di modul lain (setoran
 *     modal, biaya bank, koreksi kas) lewat dokumen Cash Flow header+detail.
 */
class CashManagementController extends Controller
{
    public function __construct(private CashFlowService $cashFlow)
    {
    }

    /*
    |--------------------------------------------------------------------------
    | MONITORING
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        // Default: bulan berjalan. Rentang selalu terisi supaya ringkasan tidak
        // diam-diam menjumlah seluruh sejarah kas dan terbaca seperti angka
        // periode.
        $from = $request->query('from') ?: now()->startOfMonth()->format('Y-m-d');
        $to   = $request->query('to') ?: now()->endOfMonth()->format('Y-m-d');

        $filters = [
            'from'           => $from,
            'to'             => $to,
            // Tiga filter ini checklist (boleh lebih dari satu). Nilai tunggal
            // dari tautan lama tetap dibungkus jadi array.
            'direction'      => $this->filterList($request->query('direction')),
            'source_fund_id' => $this->filterList($request->query('source_fund_id')),
            'module'         => $this->filterList($request->query('module')),
            'q'              => $request->query('q'),
        ];

        /*
        | Satu dataset untuk semua panel — daftar transaksi, kartu ringkasan,
        | dan rekap periode semuanya diturunkan dari koleksi yang sama. Ini
        | pelajaran langsung dari bug P-02 di Collection Summary, tempat tabel
        | dan ringkasan berangkat dari query yang berbeda.
        */
        $ledger = $this->cashFlow->ledger($filters);

        $summary  = $this->cashFlow->summarize($ledger);
        $monthly  = $this->cashFlow->monthlySeries($ledger);

        // Dokumen manual (untuk tab "Entri Manual") — termasuk DRAFT, yang
        // sengaja tidak ikut ke buku besar sampai di-post.
        $documents = CashFlow::with(['sourceFund:id,name', 'createdBy:id,username', 'details'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return view('cash-management.index', [
            'ledger'      => $ledger,
            'summary'     => $summary,
            'monthly'     => $monthly,
            'documents'   => $documents,
            'sourceFunds' => SourceFund::orderBy('name')->get(['id', 'name']),
            'filters'     => $filters,
            'modules'     => [
                CashFlowService::MODULE_CASH_RECEIPT => 'Cash Receipt',
                CashFlowService::MODULE_TREASURY     => 'Treasury',
                CashFlowService::MODULE_MANUAL       => 'Entri Manual',
            ],
        ]);
    }

    /** Normalisasi nilai filter checklist menjadi array string tanpa nilai kosong. */
    private function filterList($value): array
    {
        return array_values(array_filter(
            array_map('strval', (array) ($value ?? [])),
            fn ($v) => $v !== '',
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | DOKUMEN MANUAL — CREATE / STORE
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        return view('cash-management.create', [
            'sourceFunds' => SourceFund::orderBy('name')->get(['id', 'name']),
            'categories'  => CashFlow::CATEGORIES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateDocument($request);

        $cashFlow = DB::transaction(function () use ($request, $data) {
            $cashFlow = CashFlow::create([
                'code'             => CashFlow::generateCode(\Carbon\Carbon::parse($data['date'])),
                'date'             => $data['date'],
                'description'      => $data['description'],
                'source_fund_id'   => $data['source_fund_id'] ?? null,
                'source_module'    => $request->input('source_module'),
                'source_id'        => $request->input('source_id'),
                'reference_number' => $request->input('reference_number'),
                'notes'            => $request->input('notes'),
                'status'           => 'DRAFT',
                'created_by'       => Auth::id(),
            ]);

            $this->syncDetails($cashFlow, $request);
            $cashFlow->recalculateTotals();

            return $cashFlow;
        });

        return redirect('/cash-management/' . $cashFlow->id)
            ->with('success', 'Cash Flow ' . $cashFlow->code . ' berhasil dibuat sebagai DRAFT.');
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW / EDIT / UPDATE
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $cashFlow = CashFlow::with([
            'details', 'sourceFund', 'createdBy', 'postedBy', 'cancelledBy',
        ])->findOrFail($id);

        return view('cash-management.show', compact('cashFlow'));
    }

    public function edit($id)
    {
        $cashFlow = CashFlow::with('details')->findOrFail($id);

        // Dokumen yang sudah masuk buku kas tidak boleh diubah diam-diam —
        // koreksinya lewat dokumen penyesuaian baru, supaya jejaknya kelihatan.
        if (!$cashFlow->isEditable()) {
            return redirect('/cash-management/' . $cashFlow->id)
                ->with('error', 'Cash Flow berstatus ' . $cashFlow->status . ' tidak bisa diubah. Buat dokumen koreksi kalau perlu penyesuaian.');
        }

        return view('cash-management.edit', [
            'cashFlow'    => $cashFlow,
            'sourceFunds' => SourceFund::orderBy('name')->get(['id', 'name']),
            'categories'  => CashFlow::CATEGORIES,
        ]);
    }

    public function update(Request $request, $id)
    {
        $cashFlow = CashFlow::findOrFail($id);

        if (!$cashFlow->isEditable()) {
            return back()->with('error', 'Cash Flow berstatus ' . $cashFlow->status . ' tidak bisa diubah.');
        }

        $data = $this->validateDocument($request);

        DB::transaction(function () use ($request, $data, $cashFlow) {
            $cashFlow->update([
                'date'             => $data['date'],
                'description'      => $data['description'],
                'source_fund_id'   => $data['source_fund_id'] ?? null,
                'source_module'    => $request->input('source_module'),
                'source_id'        => $request->input('source_id'),
                'reference_number' => $request->input('reference_number'),
                'notes'            => $request->input('notes'),
            ]);

            $cashFlow->details()->delete();
            $this->syncDetails($cashFlow, $request);
            $cashFlow->recalculateTotals();
        });

        return redirect('/cash-management/' . $cashFlow->id)
            ->with('success', 'Cash Flow ' . $cashFlow->code . ' berhasil diperbarui.');
    }

    /*
    |--------------------------------------------------------------------------
    | WORKFLOW
    |--------------------------------------------------------------------------
    | DRAFT -> POSTED -> (CANCELLED)
    |
    | Hanya dokumen POSTED yang masuk buku besar kas. Membatalkan dokumen tidak
    | menghapusnya: baris tetap ada untuk ditelusuri, hanya berhenti dihitung.
    */

    public function post($id)
    {
        $cashFlow = CashFlow::with('details')->findOrFail($id);

        if (!$cashFlow->isDraft()) {
            return back()->with('error', 'Hanya Cash Flow berstatus DRAFT yang bisa di-post.');
        }

        if ($cashFlow->details->isEmpty()) {
            return back()->with('error', 'Cash Flow tanpa baris rincian tidak bisa di-post.');
        }

        $cashFlow->update([
            'status'    => 'POSTED',
            'posted_by' => Auth::id(),
            'posted_at' => now(),
        ]);

        return back()->with('success', 'Cash Flow ' . $cashFlow->code . ' masuk buku kas.');
    }

    public function cancel(Request $request, $id)
    {
        $request->validate(['cancel_reason' => 'required|string|max:255']);

        $cashFlow = CashFlow::findOrFail($id);

        if ($cashFlow->isCancelled()) {
            return back()->with('error', 'Cash Flow ini sudah dibatalkan.');
        }

        $cashFlow->update([
            'status'        => 'CANCELLED',
            'cancelled_by'  => Auth::id(),
            'cancelled_at'  => now(),
            'cancel_reason' => $request->cancel_reason,
        ]);

        return back()->with('success', 'Cash Flow ' . $cashFlow->code . ' dibatalkan.');
    }

    public function destroy($id)
    {
        $cashFlow = CashFlow::findOrFail($id);

        if (!$cashFlow->isDraft()) {
            return back()->with('error', 'Hanya DRAFT yang bisa dihapus. Dokumen yang sudah masuk buku kas dibatalkan, bukan dihapus.');
        }

        $cashFlow->delete(); // soft delete

        return redirect('/cash-management')
            ->with('success', 'Cash Flow ' . $cashFlow->code . ' dihapus.');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function validateDocument(Request $request): array
    {
        return $request->validate([
            'date'                     => 'required|date',
            'description'              => 'required|string|max:255',
            'source_fund_id'           => 'nullable|exists:source_funds,id',
            'reference_number'         => 'nullable|string|max:255',
            'source_module'            => 'nullable|string|max:40',
            'source_id'                => 'nullable|integer',
            'notes'                    => 'nullable|string|max:2000',
            'details'                  => 'required|array|min:1',
            'details.*.direction'      => 'required|in:IN,OUT',
            'details.*.category'       => 'required|string|max:60',
            'details.*.description'    => 'required|string|max:255',
            'details.*.amount'         => 'required',
            'details.*.reference_number' => 'nullable|string|max:255',
        ]);
    }

    private function syncDetails(CashFlow $cashFlow, Request $request): void
    {
        foreach ((array) $request->input('details', []) as $row) {
            $amount = $this->normalizeMoney($row['amount'] ?? 0);

            // Baris bernilai nol tidak menambah informasi apa pun ke buku kas.
            if ($amount <= 0) {
                continue;
            }

            $cashFlow->details()->create([
                'direction'        => $row['direction'],
                'category'         => $row['category'],
                'description'      => $row['description'],
                'amount'           => $amount,
                'source_module'    => $row['source_module'] ?? null,
                'source_id'        => $row['source_id'] ?? null,
                'reference_number' => $row['reference_number'] ?? null,
            ]);
        }
    }

    /** Input rupiah datang berformat ribuan ("1.500.000") dari form. */
    private function normalizeMoney($value): int
    {
        if (blank($value)) {
            return 0;
        }

        return (int) preg_replace('/[^\d]/', '', (string) $value);
    }
}
