<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesAssetRecords;
use App\Models\Asset;
use App\Models\AssetLocation;
use App\Models\AssetLog;
use App\Models\AssetOpname;
use App\Models\AssetOpnameItem;
use App\Support\AssetNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Stock Opname aset per lokasi.
 *
 * Membuat opname = memotret seluruh aset (belum DISPOSED) yang tercatat di
 * lokasi tersebut ke asset_opname_items. Petugas mengisi hasil per aset
 * (Sesuai / Tidak Ditemukan / Selisih Data) dan kondisi. Menyelesaikan opname
 * (ASSET_MANAGE) mengunci hasilnya dan memperbarui KONDISI aset yang diisi.
 * Aset "Tidak Ditemukan" TIDAK otomatis di-disposal — tindak lanjutnya lewat
 * pengajuan Disposal agar tetap melalui persetujuan.
 */
class AssetOpnameController extends Controller
{
    use HandlesAssetRecords;

    public function index()
    {
        $opnames = AssetOpname::with('location:id,name', 'creator:id,name')
            ->withCount([
                'items',
                'items as matched_count'     => fn ($q) => $q->where('result', 'MATCHED'),
                'items as missing_count'     => fn ($q) => $q->where('result', 'MISSING'),
                'items as discrepancy_count' => fn ($q) => $q->where('result', 'DISCREPANCY'),
            ])
            ->orderByDesc('id')->limit(500)->get();

        return view('asset.opname.index', [
            'opnames'   => $opnames,
            'locations' => AssetLocation::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'asset_location_id' => 'required|exists:asset_locations,id',
            'opname_date'       => 'required|date|before_or_equal:today',
            'notes'             => 'nullable|string|max:2000',
        ]);

        if (AssetOpname::where('asset_location_id', $data['asset_location_id'])->where('status', 'COUNTING')->exists()) {
            return back()->withInput()->with('error', 'Lokasi ini masih punya opname yang belum selesai.');
        }

        $opname = DB::transaction(function () use ($data) {
            $opname = AssetOpname::create(array_merge($data, [
                'number'     => AssetNumber::document('asset_opnames', 'SO-AST'),
                'status'     => 'COUNTING',
                'created_by' => Auth::id(),
            ]));

            $now = now();
            $rows = Asset::live()->where('asset_location_id', $data['asset_location_id'])->pluck('id')
                ->map(fn ($id) => ['asset_opname_id' => $opname->id, 'asset_id' => $id, 'created_at' => $now, 'updated_at' => $now]);
            foreach ($rows->chunk(500) as $chunk) {
                AssetOpnameItem::insert($chunk->all());
            }

            return $opname;
        });

        return redirect()->route('asset.opname.show', $opname)->with('success', "Opname {$opname->number} dibuat.");
    }

    public function show(AssetOpname $opname)
    {
        $opname->load('location', 'creator:id,name', 'items.asset:id,code,name,serial_number,condition,status');

        return view('asset.opname.show', [
            'opname'    => $opname,
            'canManage' => $this->canManageAssets(),
        ]);
    }

    /** Simpan hasil hitung (boleh berkali-kali selama COUNTING). */
    public function update(Request $request, AssetOpname $opname)
    {
        $data = $request->validate([
            'items'             => 'required|array',
            'items.*.result'    => 'nullable|in:' . implode(',', array_keys(AssetOpnameItem::RESULTS)),
            'items.*.condition' => 'nullable|in:' . implode(',', array_keys(Asset::CONDITIONS)),
            'items.*.notes'     => 'nullable|string|max:500',
        ]);

        $ok = DB::transaction(function () use ($opname, $data) {
            $locked = AssetOpname::whereKey($opname->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'COUNTING') {
                return false;
            }
            $items = $locked->items()->get()->keyBy('id');
            foreach ($data['items'] as $id => $isi) {
                $item = $items->get((int) $id);
                if ($item) {
                    $item->update([
                        'result'    => $isi['result'] ?? null,
                        'condition' => $isi['condition'] ?? null,
                        'notes'     => $isi['notes'] ?? null,
                    ]);
                }
            }

            return true;
        });

        return $ok ? back()->with('success', 'Hasil opname disimpan.') : back()->with('error', 'Opname sudah selesai.');
    }

    public function complete(AssetOpname $opname)
    {
        $this->authorizeManage();

        $hasil = DB::transaction(function () use ($opname) {
            $locked = AssetOpname::whereKey($opname->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'COUNTING') {
                return 'Opname sudah selesai.';
            }
            $items = $locked->items()->get();
            if ($items->whereNull('result')->isNotEmpty()) {
                return 'Masih ada aset yang belum diisi hasilnya.';
            }

            foreach ($items as $item) {
                $asset = $this->lockAsset($item->asset_id);
                if ($item->condition && $item->condition !== $asset->condition && ! $asset->isDisposed()) {
                    $asset->update(['condition' => $item->condition, 'updated_by' => Auth::id()]);
                }
                AssetLog::record($asset, 'opname', "Opname {$locked->number}: " . AssetOpnameItem::RESULTS[$item->result] . '.');
            }

            $locked->update(['status' => 'COMPLETED', 'completed_by' => Auth::id(), 'completed_at' => now()]);

            return true;
        });

        return $hasil === true
            ? back()->with('success', 'Opname diselesaikan.')
            : back()->with('error', $hasil);
    }
}
