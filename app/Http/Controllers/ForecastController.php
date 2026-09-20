<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Models\Forecast;
use App\Models\SourceFund;

class ForecastController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $forecasts = Forecast::with(['details.sourceFund:id,name', 'details.project:id,code,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->get();

        $summary = [
            'total'  => $forecasts->count(),
            'draft'  => $forecasts->where('status', 'DRAFT')->count(),
            'active' => $forecasts->where('status', 'ACTIVE')->count(),
            'closed' => $forecasts->where('status', 'CLOSED')->count(),
        ];

        return view('forecast.index', compact('forecasts', 'summary'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $sourceFunds = SourceFund::where('status', 'ACTIVE')->orderBy('name')->get();
        $projects    = $this->pilihanProject();

        return view('forecast.create', compact('sourceFunds', 'projects'));
    }

    /**
     * Pilihan PO untuk header Forecast. Diurutkan per customer lalu kode,
     * karena itulah cara orang mencarinya — bukan per tanggal dibuat.
     */
    private function pilihanProject(): \Illuminate\Support\Collection
    {
        return \App\Models\Project::with('customer:id,name')
            ->get(['id', 'code', 'name', 'customer_id'])
            ->sortBy(fn ($p) => strtolower(($p->customer->name ?? '') . '-' . $p->code))
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $validated = $this->validateForecast($request);

        DB::transaction(function () use ($validated) {
            $forecast = Forecast::create([
                'notes'      => isset($validated['notes']) ? strtoupper(trim($validated['notes'])) : null,
                'date_start' => $validated['date_start'],
                'date_end'   => $validated['date_end'],
                'status'     => 'DRAFT',
                'created_by' => Auth::id(),
            ]);

            $this->syncDetails($forecast, $validated['details']);
        });

        return redirect('/forecast')->with('success', 'Forecast berhasil dibuat.');
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        // Rekening kini di detail, bukan header.
        // PO kini di detail, bukan header — sama seperti rekening.
        $forecast = Forecast::with(['details.sourceFund', 'details.project.customer', 'creator'])->findOrFail($id);

        return view('forecast.show', compact('forecast'));
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT — hanya DRAFT
    |--------------------------------------------------------------------------
    */

    public function edit($id)
    {
        $forecast = Forecast::with(['details.sourceFund'])->findOrFail($id);

        if ($forecast->isLocked()) {
            return redirect('/forecast/' . $id)->with('error', 'Forecast yang sudah ACTIVE/CLOSED tidak dapat diedit.');
        }

        $sourceFunds = SourceFund::where('status', 'ACTIVE')->orderBy('name')->get();
        $projects    = $this->pilihanProject();

        return view('forecast.edit', compact('forecast', 'sourceFunds', 'projects'));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE — hanya DRAFT
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        $forecast = Forecast::findOrFail($id);

        if ($forecast->isLocked()) {
            return back()->with('error', 'Forecast yang sudah ACTIVE/CLOSED tidak dapat diedit.');
        }

        $validated = $this->validateForecast($request);

        DB::transaction(function () use ($forecast, $validated) {
            $forecast->update([
                'notes'      => isset($validated['notes']) ? strtoupper(trim($validated['notes'])) : null,
                'date_start' => $validated['date_start'],
                'date_end'   => $validated['date_end'],
            ]);

            $this->syncDetails($forecast, $validated['details']);
        });

        return redirect('/forecast')->with('success', 'Forecast berhasil diupdate.');
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY — hanya DRAFT
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $forecast = Forecast::findOrFail($id);

        if ($forecast->isLocked()) {
            return back()->with('error', 'Forecast yang sudah ACTIVE/CLOSED tidak dapat dihapus.');
        }

        $forecast->delete();

        return redirect('/forecast')->with('success', 'Forecast berhasil dihapus.');
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVATE — DRAFT -> ACTIVE
    |--------------------------------------------------------------------------
    */

    public function activate($id)
    {
        $forecast = Forecast::with('details')->findOrFail($id);

        if ($forecast->status !== 'DRAFT') {
            return back()->with('error', 'Hanya forecast DRAFT yang dapat diaktifkan.');
        }
        if ($forecast->details->isEmpty()) {
            return back()->with('error', 'Forecast wajib punya minimal 1 detail sebelum diaktifkan.');
        }

        $forecast->update(['status' => 'ACTIVE', 'activated_at' => now()]);

        return back()->with('success', 'Forecast diaktifkan. Header & detail terkunci.');
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE ACTUAL — hanya saat ACTIVE (bukan bagian header/detail yang dikunci)
    |--------------------------------------------------------------------------
    */

    public function updateActual(Request $request, $id)
    {
        $forecast = Forecast::findOrFail($id);

        if ($forecast->status !== 'ACTIVE') {
            return back()->with('error', 'Actual Amount hanya bisa diupdate selama forecast ACTIVE.');
        }

        // Actual diinput per detail item, bukan agregat header — header
        // actual_amount otomatis ke-sync lewat ForecastDetail::booted().
        $validated = $request->validate([
            'details'                 => 'required|array',
            'details.*'               => 'nullable',
        ]);

        DB::transaction(function () use ($forecast, $validated) {
            foreach ($validated['details'] as $detailId => $rawAmount) {
                $detail = $forecast->details()->whereKey($detailId)->first();
                if (!$detail) {
                    continue;
                }
                $detail->update(['actual_amount' => (int) preg_replace('/\D/', '', (string) $rawAmount)]);
            }
        });

        return back()->with('success', 'Actual Amount diupdate.');
    }

    /*
    |--------------------------------------------------------------------------
    | CLOSE — manual, ACTIVE -> CLOSED (final, tidak reversible)
    |--------------------------------------------------------------------------
    */

    public function close($id)
    {
        $forecast = Forecast::findOrFail($id);

        if ($forecast->status !== 'ACTIVE') {
            return back()->with('error', 'Hanya forecast ACTIVE yang dapat di-Close.');
        }

        $forecast->update(['status' => 'CLOSED', 'closed_at' => now()]);

        return back()->with('success', 'Forecast di-Close.');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function validateForecast(Request $request): array
    {
        /*
         | Struktur Forecast: HEADER hanya rentang waktu + catatan. PO/Project,
         | rekening, dan arah (IN/OUT) semuanya melekat di DETAIL — satu forecast
         | (mis. "Overhead Sep 2026 — Week 4") bisa memuat rencana untuk beberapa
         | PO sekaligus, atau tanpa PO sama sekali.
         |
         | project_id di baris sengaja NULLABLE: rencana overhead memang tidak
         | punya PO, dan mewajibkannya akan memaksa pengguna memilih PO yang
         | tidak ada hubungannya.
         */
        return $request->validate([
            'notes'                    => 'nullable|string|max:255',
            'date_start'               => 'required|date',
            'date_end'                 => 'required|date|after_or_equal:date_start',
            'details'                  => 'required|array|min:1',
            'details.*.description'    => 'required|string|max:255',
            'details.*.project_id'     => 'nullable|exists:projects,id',
            'details.*.type'           => 'required|in:IN,OUT',
            'details.*.source_fund_id' => 'nullable|exists:source_funds,id',
            'details.*.amount'         => 'required',
        ]);
    }

    /** Delete-all-then-recreate dibungkus DB transaction (pola child-row edit existing). */
    private function syncDetails(Forecast $forecast, array $details): void
    {
        $forecast->details()->delete();

        foreach ($details as $d) {
            $amount = (int) preg_replace('/\D/', '', (string) $d['amount']);

            // Baris bernilai nol tidak menambah informasi apa pun ke rencana.
            if ($amount <= 0) {
                continue;
            }

            $forecast->details()->create([
                'description'    => $d['description'],
                'project_id'     => $d['project_id'] ?? null,
                'type'           => $d['type'],
                'source_fund_id' => $d['source_fund_id'] ?? null,
                'amount'         => $amount,
            ]);
        }

        $forecast->refresh()->syncForecastAmount();
    }
}
