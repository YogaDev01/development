<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Models\Forecast;
use App\Models\ForecastDetail;
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

    /**
     * Detailnya kini sebuah modal di halaman daftar, bukan halaman sendiri.
     *
     * Tautan lama /forecast/{id} tetap hidup dan diarahkan ke daftar dengan
     * modalnya terbuka: alamat yang sudah tersebar (bookmark, dan redirect
     * edit() saat forecast terkunci) tidak boleh berakhir 404 hanya karena
     * tampilannya berpindah bentuk.
     */
    public function show($id)
    {
        return redirect('/forecast?open=' . (int) $id);
    }

    /**
     * Isi modal detail. Dipanggil AJAX saat tombol View ditekan.
     *
     * Dikirim sebagai data siap-tampil (sudah terformat, sudah bertanda
     * boleh/tidak) supaya JavaScript tidak perlu mengulang aturan status yang
     * sudah dinyatakan model dan controller — itu cara paling mudah membuat
     * tombol yang tampil berbeda dari yang diterima backend.
     */
    public function detailJson($id)
    {
        $forecast = Forecast::with(['details.sourceFund', 'details.project.customer', 'creator'])->findOrFail($id);

        return response()->json([
            'id'     => $forecast->id,
            'code'   => 'FC-' . str_pad((string) $forecast->id, 4, '0', STR_PAD_LEFT),
            'label'  => $forecast->label,
            'notes'  => $forecast->notes,
            'status' => $forecast->status,
            'status_color' => $forecast->status_color,

            'period'     => $forecast->date_start->format('d M Y') . ' - ' . $forecast->date_end->format('d M Y'),
            'creator'    => $forecast->creator->name ?? '-',
            'activated_at' => $forecast->activated_at?->format('d M Y H:i'),
            'closed_at'    => $forecast->closed_at?->format('d M Y H:i'),

            'forecast_amount' => $forecast->forecast_amount_formatted,
            'actual_amount'   => $forecast->actual_amount_formatted,
            'variance'        => $forecast->variance_formatted,
            'variance_negative' => $forecast->variance < 0,
            'achievement_pct' => $forecast->achievement_pct,

            // PO dikumpulkan dari detail: satu forecast bisa menyentuh beberapa
            // PO, atau tidak sama sekali (rencana overhead).
            'projects' => $forecast->details
                ->map(fn ($d) => $d->project)
                ->filter()
                ->unique('id')
                ->map(fn ($p) => $p->code . ' · ' . ($p->customer->name ?? '-'))
                ->values(),

            /*
             | Kewenangan ditentukan di sini, bukan disimpulkan ulang di
             | peramban dari string status. isLocked() adalah aturan yang sama
             | yang dipakai edit()/update()/destroy().
             */
            'can_edit'          => ! $forecast->isLocked(),
            'can_delete'        => ! $forecast->isLocked(),
            'can_activate'      => $forecast->status === 'DRAFT' && $forecast->details->isNotEmpty(),
            'can_close'         => $forecast->status === 'ACTIVE',
            'can_update_actual' => $forecast->status === 'ACTIVE' && $forecast->details->isNotEmpty(),

            'details' => $forecast->details->map(fn ($d) => [
                'id'          => $d->id,
                'description' => $d->description,
                'project'     => $d->project?->code,
                'project_name' => $d->project?->name,
                'type'        => $d->type,
                'type_label'  => $d->type_label,
                'type_color'  => $d->type_color,
                'source_fund' => $d->sourceFund->name ?? '-',
                'amount'           => $d->amount,
                'amount_formatted' => $d->amount_formatted,
                'actual_amount'           => $d->actual_amount,
                'actual_amount_formatted' => $d->actual_amount_formatted,
                'selisih_formatted' => number_format($d->amount - $d->actual_amount, 0, ',', '.'),
                'selisih_negative'  => ($d->amount - $d->actual_amount) < 0,
            ])->values(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT — selama belum CLOSED (lihat Forecast::isLocked())
    |--------------------------------------------------------------------------
    */

    public function edit($id)
    {
        $forecast = Forecast::with(['details.sourceFund'])->findOrFail($id);

        if ($forecast->isLocked()) {
            return redirect('/forecast/' . $id)->with('error', 'Forecast yang sudah CLOSED tidak dapat diedit.');
        }

        $sourceFunds = SourceFund::where('status', 'ACTIVE')->orderBy('name')->get();
        $projects    = $this->pilihanProject();

        return view('forecast.edit', compact('forecast', 'sourceFunds', 'projects'));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE — selama belum CLOSED (lihat Forecast::isLocked())
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        $forecast = Forecast::findOrFail($id);

        if ($forecast->isLocked()) {
            return back()->with('error', 'Forecast yang sudah CLOSED tidak dapat diedit.');
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
    | DESTROY — selama belum CLOSED (lihat Forecast::isLocked())
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $forecast = Forecast::findOrFail($id);

        if ($forecast->isLocked()) {
            return back()->with('error', 'Forecast yang sudah CLOSED tidak dapat dihapus.');
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

        return back()->with('success', 'Forecast diaktifkan. Actual Amount sekarang bisa diisi per item.');
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE ACTUAL — hanya saat ACTIVE
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
            // Alasan withoutEvents + sync sekali di akhir sama persis dengan
            // syncDetails(): realisasi seluruh baris dikirim sekaligus, jadi
            // menghitung header per baris berarti menghitungnya di atas
            // realisasi yang baru terisi sebagian.
            ForecastDetail::withoutEvents(function () use ($forecast, $validated) {
                foreach ($validated['details'] as $detailId => $rawAmount) {
                    $detail = $forecast->details()->whereKey($detailId)->first();
                    if (!$detail) {
                        continue;
                    }
                    $detail->update(['actual_amount' => (int) preg_replace('/\D/', '', (string) $rawAmount)]);
                }
            });

            $forecast->refresh()->syncAmounts();
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

        /*
        | Event ForecastDetail dimatikan selama loop, lalu total header dihitung
        | sekali setelah semua baris masuk.
        |
        | Hook `saved` menghitung ulang header setiap satu baris tersimpan, jadi
        | selama loop totalnya dihitung di atas rencana yang baru terisi
        | sebagian: kalau baris OUT masuk lebih dulu dari baris IN, total
        | sementaranya negatif padahal total akhirnya belum tentu. Dulu itu
        | berujung error MySQL (kolomnya masih unsigned); sekarang kolomnya
        | sudah bertanda, tapi menghitung N kali untuk satu hasil akhir yang
        | sama tetap kerja sia-sia — dan angka antara yang sempat tersimpan
        | tidak pernah mewakili rencana yang sebenarnya.
        */
        ForecastDetail::withoutEvents(function () use ($forecast, $details) {
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
        });

        $forecast->refresh()->syncAmounts();
    }
}
