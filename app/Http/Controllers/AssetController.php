<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CapsLargeLists;
use App\Http\Controllers\Concerns\HandlesAssetRecords;
use App\Models\Asset;
use App\Models\AssetAttachment;
use App\Models\AssetCategory;
use App\Models\AssetLog;
use App\Models\AssetMaintenance;
use App\Models\AssetMaintenanceSchedule;
use App\Models\AssetMovement;
use App\Support\AssetDepreciation;
use App\Support\AssetNumber;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Asset Management — dashboard, register aset (CRUD), detail, dan laporan.
 *
 * Gerbang route: menu:asset-management. Aksi kelola (hapus aset) dibatasi
 * AccessRoles::ASSET_MANAGE di route DAN di sini. Perpindahan status aset
 * TIDAK dilakukan di sini — milik AssetMovementController & kawan-kawan.
 */
class AssetController extends Controller
{
    use CapsLargeLists;
    use HandlesAssetRecords;

    private const ATTACHMENT_RULES = 'file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx';

    public const REPORTS = [
        'register'     => 'Asset Register',
        'depreciation' => 'Penyusutan',
        'movement'     => 'Pergerakan Aset',
        'maintenance'  => 'Maintenance',
        'condition'    => 'Kondisi Aset',
    ];

    public function dashboard()
    {
        $live = Asset::live()->with('category:id,name', 'location:id,name')
            ->get(['id', 'asset_category_id', 'asset_location_id', 'status', 'condition', 'cost', 'residual_value', 'life_years', 'depreciation_method', 'acquisition_date', 'disposed_at']);

        $totalCost = (int) $live->sum('cost');
        $totalBook = (int) $live->sum(fn (Asset $a) => $a->bookValue());

        $kpi = [
            'total'       => $live->count(),
            'cost'        => $totalCost,
            'book'        => $totalBook,
            'assigned'    => $live->where('status', 'ASSIGNED')->count(),
            'maintenance' => $live->where('status', 'MAINTENANCE')->count(),
            'disposed'    => Asset::where('status', 'DISPOSED')->count(),
        ];

        $byStatus    = $live->countBy('status')->sortDesc();
        $byCondition = $live->countBy('condition');
        $byCategory  = $live->groupBy(fn ($a) => $a->category?->name ?? '-')->map->count()->sortDesc();
        $byLocation  = $live->groupBy(fn ($a) => $a->location?->name ?? '-')->map->count()->sortDesc()->take(8);

        $openMaintenance = AssetMaintenance::with('asset:id,code,name')
            ->whereIn('status', AssetMaintenance::OPEN_STATUSES)->latest('start_date')->limit(6)->get();
        $dueSchedules = AssetMaintenanceSchedule::with('asset:id,code,name')
            ->where('status', 'ACTIVE')
            ->whereDate('next_due_date', '<=', now()->addDays(AssetMaintenanceSchedule::DUE_SOON_DAYS))
            ->orderBy('next_due_date')->limit(6)->get();
        $pendingApprovals = AssetMovement::whereIn('kind', ['TRANSFER', 'DISPOSAL'])->where('status', 'PENDING')->count()
            + \App\Models\AssetPurchaseRequest::where('status', 'PENDING')->count();
        $overdueCheckouts = AssetMovement::where('kind', 'CHECKOUT')->where('status', 'CHECKED_OUT')
            ->whereDate('expected_return_date', '<', now()->toDateString())->count();

        $activities = AssetLog::with('user:id,name')
            ->join('assets', 'assets.id', '=', 'asset_logs.asset_id')
            ->orderByDesc('asset_logs.id')->limit(10)
            ->get(['asset_logs.*', 'assets.code as asset_code', 'assets.name as asset_name']);

        return view('asset.dashboard', compact(
            'kpi', 'byStatus', 'byCondition', 'byCategory', 'byLocation',
            'openMaintenance', 'dueSchedules', 'pendingApprovals', 'overdueCheckouts', 'activities'
        ));
    }

    public function index(Request $request)
    {
        $query = $this->filteredQuery($request);
        [$assets, $capped, $total] = $this->cappedRows($query, 'id');

        return view('asset.index', array_merge($this->lookups(), [
            'assets'  => $assets,
            'capped'  => $capped,
            'total'   => $total,
            'filters' => $request->only(['q', 'category', 'location', 'status', 'condition']),
            'canManage' => $this->canManageAssets(),
        ]));
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filteredQuery($request)->orderBy('code')->limit(5000)->get();

        return $this->csv('Asset_Register_' . now()->format('Ymd') . '.csv', [
            'Kode', 'Nama', 'Kategori', 'Tipe', 'Merek', 'Model', 'Serial', 'Lokasi', 'Departemen',
            'Pemegang', 'Tgl Perolehan', 'Harga Perolehan', 'Nilai Buku', 'Kondisi', 'Status',
        ], $rows->map(fn (Asset $a) => [
            $a->code, $a->name, $a->category?->name, $a->type, $a->brand, $a->model, $a->serial_number,
            $a->location?->name, $a->department?->name, $a->holderName(), $a->acquisition_date?->toDateString(),
            $a->cost, $a->bookValue(), $a->conditionLabel(), $a->status,
        ]));
    }

    public function create()
    {
        return view('asset.form', array_merge($this->lookups(), ['asset' => new Asset([
            'acquisition_date' => now()->toDateString(),
            'acquisition_type' => 'PURCHASE',
            'condition'        => 'GOOD',
            'status'           => 'AVAILABLE',
        ])]));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);

        $asset = DB::transaction(function () use ($data, $request) {
            $category = AssetCategory::findOrFail($data['asset_category_id']);
            $asset = Asset::create(array_merge($data, [
                'code'       => AssetNumber::assetCode($category->code, (int) Carbon::parse($data['acquisition_date'])->format('Y')),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]));
            $this->saveAttachments($asset, $request);
            AssetLog::record($asset, 'create', 'Aset didaftarkan dengan status ' . $asset->status . '.');

            return $asset;
        });

        return redirect()->route('asset.show', $asset)->with('success', "Aset {$asset->code} berhasil didaftarkan.");
    }

    public function show(Asset $asset)
    {
        $asset->load([
            'category', 'location', 'department', 'employee', 'creator:id,name', 'attachments',
            'logs.user:id,name', 'movements.fromLocation', 'movements.toLocation',
            'movements.fromEmployee', 'movements.toEmployee', 'maintenances', 'inspections', 'schedules',
        ]);

        return view('asset.show', [
            'asset'     => $asset,
            'schedule'  => AssetDepreciation::schedule($asset),
            'book'      => $asset->bookValue(),
            'canManage' => $this->canManageAssets(),
        ]);
    }

    public function edit(Asset $asset)
    {
        abort_if($asset->isDisposed(), 422, 'Aset yang sudah di-disposal tidak bisa diubah.');

        return view('asset.form', array_merge($this->lookups(), ['asset' => $asset]));
    }

    public function update(Request $request, Asset $asset)
    {
        $data = $this->validated($request, $asset);

        $ok = DB::transaction(function () use ($asset, $data, $request) {
            $locked = $this->lockAsset($asset->id);
            if ($locked->isDisposed()) {
                return false;
            }
            // Status, pemegang & lokasi berpindah lewat transaksi aset, bukan form edit.
            unset($data['status']);
            $locked->fill(array_merge($data, ['updated_by' => Auth::id()]));
            $berubah = array_keys($locked->getDirty());
            $locked->save();
            $this->saveAttachments($locked, $request);
            if ($berubah) {
                AssetLog::record($locked, 'update', 'Data diubah: ' . implode(', ', array_diff($berubah, ['updated_by'])) . '.');
            }

            return true;
        });

        if (! $ok) {
            return back()->with('error', 'Aset sudah di-disposal, tidak bisa diubah.');
        }

        return redirect()->route('asset.show', $asset)->with('success', 'Data aset diperbarui.');
    }

    public function destroy(Request $request, Asset $asset)
    {
        $this->authorizeManage();
        $data = $request->validate(['delete_reason' => 'required|string|max:500']);

        // Aset dengan riwayat transaksi tidak dihapus — gunakan Disposal.
        if ($asset->movements()->exists() || $asset->maintenances()->exists() || $asset->inspections()->exists()) {
            return back()->with('error', 'Aset sudah punya riwayat transaksi. Gunakan Disposal, bukan hapus.');
        }

        DB::transaction(function () use ($asset, $data) {
            $asset->update(['deleted_by' => Auth::id(), 'delete_reason' => $data['delete_reason']]);
            AssetLog::record($asset, 'delete', 'Dihapus: ' . $data['delete_reason']);
            $asset->delete();
        });

        return redirect()->route('asset.index')->with('success', "Aset {$asset->code} dihapus.");
    }

    public function attachmentStore(Request $request, Asset $asset)
    {
        $request->validate(['attachments' => 'required|array', 'attachments.*' => 'required|' . self::ATTACHMENT_RULES]);
        $this->saveAttachments($asset, $request);
        AssetLog::record($asset, 'attachment', 'Lampiran ditambahkan.');

        return back()->with('success', 'Lampiran ditambahkan.');
    }

    public function attachmentDestroy(Asset $asset, AssetAttachment $attachment)
    {
        $this->authorizeManage();
        abort_unless($attachment->asset_id === $asset->id, 404);

        Storage::disk('secure')->delete($attachment->path);
        AssetLog::record($asset, 'attachment', 'Lampiran dihapus: ' . $attachment->original_name);
        $attachment->delete();

        return back()->with('success', 'Lampiran dihapus.');
    }

    /* ------------------------------------------------------------ LAPORAN */

    public function reports(Request $request)
    {
        $type = array_key_exists($request->get('type'), self::REPORTS) ? $request->get('type') : 'register';
        [$from, $to] = $this->period($request);

        return view('asset.reports', array_merge($this->lookups(), [
            'type'    => $type,
            'from'    => $from,
            'to'      => $to,
            'filters' => $request->only(['category', 'location']),
            'report'  => $this->buildReport($type, $request, $from, $to),
        ]));
    }

    public function reportExport(Request $request): StreamedResponse
    {
        $type = array_key_exists($request->get('type'), self::REPORTS) ? $request->get('type') : 'register';
        [$from, $to] = $this->period($request);
        $report = $this->buildReport($type, $request, $from, $to);

        return $this->csv('Laporan_Aset_' . $type . '_' . now()->format('Ymd') . '.csv', $report['headers'], $report['rows']);
    }

    /**
     * @return array{headers: array, rows: \Illuminate\Support\Collection, money: array, totals: array}
     */
    private function buildReport(string $type, Request $request, Carbon $from, Carbon $to): array
    {
        $assetFilter = function ($q) use ($request) {
            $q->when($request->filled('category'), fn ($x) => $x->where('asset_category_id', $request->category))
              ->when($request->filled('location'), fn ($x) => $x->where('asset_location_id', $request->location));
        };

        switch ($type) {
            case 'depreciation':
                $assets = Asset::with('category:id,name')->tap($assetFilter)
                    ->whereDate('acquisition_date', '<=', $to)
                    ->where(fn ($q) => $q->whereNull('disposed_at')->orWhereDate('disposed_at', '>=', $from))
                    ->orderBy('code')->get();
                $rows = $assets->map(fn (Asset $a) => [
                    $a->code, $a->name, $a->category?->name,
                    AssetCategory::METHODS[$a->depreciation_method] ?? $a->depreciation_method,
                    $a->life_years, $a->cost,
                    AssetDepreciation::between($a, $from, $to),
                    AssetDepreciation::accumulated($a, $to),
                    AssetDepreciation::bookValue($a, $to),
                ]);

                return [
                    'headers' => ['Kode', 'Nama', 'Kategori', 'Metode', 'Umur (thn)', 'Harga Perolehan', 'Penyusutan Periode', 'Akumulasi', 'Nilai Buku'],
                    'rows'    => $rows,
                    'money'   => [5, 6, 7, 8],
                    'totals'  => [5 => $rows->sum(5), 6 => $rows->sum(6), 7 => $rows->sum(7), 8 => $rows->sum(8)],
                ];

            case 'movement':
                $rows = AssetMovement::with('asset:id,code,name', 'fromLocation:id,name', 'toLocation:id,name', 'fromEmployee:id,name', 'toEmployee:id,name')
                    ->whereHas('asset', $assetFilter)
                    ->whereBetween('movement_date', [$from->toDateString(), $to->toDateString()])
                    ->orderBy('movement_date')->get()
                    ->map(fn (AssetMovement $m) => [
                        $m->number, AssetMovement::KINDS[$m->kind] ?? $m->kind, $m->movement_date?->toDateString(),
                        $m->asset?->code . ' ' . $m->asset?->name,
                        trim(($m->fromLocation?->name ?? '') . ' ' . ($m->fromEmployee?->name ? '(' . $m->fromEmployee->name . ')' : '')) ?: '-',
                        trim(($m->toLocation?->name ?? '') . ' ' . ($m->toEmployee?->name ? '(' . $m->toEmployee->name . ')' : '')) ?: '-',
                        $m->status,
                    ]);

                return ['headers' => ['Nomor', 'Jenis', 'Tanggal', 'Aset', 'Dari', 'Ke', 'Status'], 'rows' => $rows, 'money' => [], 'totals' => []];

            case 'maintenance':
                $rows = AssetMaintenance::with('asset:id,code,name')
                    ->whereHas('asset', $assetFilter)
                    ->whereBetween('start_date', [$from->toDateString(), $to->toDateString()])
                    ->orderBy('start_date')->get()
                    ->map(fn (AssetMaintenance $m) => [
                        $m->number, $m->start_date?->toDateString(), $m->asset?->code . ' ' . $m->asset?->name,
                        AssetMaintenance::TYPES[$m->maintenance_type] ?? $m->maintenance_type, $m->vendor ?: '-',
                        $m->estimated_cost, $m->actual_cost, $m->status,
                    ]);

                return [
                    'headers' => ['Nomor', 'Mulai', 'Aset', 'Jenis', 'Vendor', 'Estimasi', 'Biaya Aktual', 'Status'],
                    'rows'    => $rows,
                    'money'   => [5, 6],
                    'totals'  => [5 => $rows->sum(5), 6 => $rows->sum(6)],
                ];

            case 'condition':
                $assets = Asset::live()->with('category:id,name')->tap($assetFilter)->get(['id', 'asset_category_id', 'condition']);
                $rows = $assets->groupBy(fn ($a) => $a->category?->name ?? '-')->map(function ($grup, $nama) {
                    $baris = [$nama];
                    foreach (array_keys(Asset::CONDITIONS) as $k) {
                        $baris[] = $grup->where('condition', $k)->count();
                    }
                    $baris[] = $grup->count();

                    return $baris;
                })->sortKeys()->values();

                return [
                    'headers' => array_merge(['Kategori'], array_values(Asset::CONDITIONS), ['Total']),
                    'rows'    => $rows,
                    'money'   => [],
                    'totals'  => collect(range(1, count(Asset::CONDITIONS) + 1))->mapWithKeys(fn ($i) => [$i => $rows->sum($i)])->all(),
                ];

            default: // register
                $assets = Asset::with('category:id,name', 'location:id,name', 'employee:id,name')->tap($assetFilter)
                    ->orderBy('code')->limit(5000)->get();
                $rows = $assets->map(fn (Asset $a) => [
                    $a->code, $a->name, $a->category?->name, $a->location?->name ?? '-', $a->holderName(),
                    $a->acquisition_date?->toDateString(), $a->cost, $a->bookValue($to), $a->conditionLabel(), $a->status,
                ]);

                return [
                    'headers' => ['Kode', 'Nama', 'Kategori', 'Lokasi', 'Pemegang', 'Tgl Perolehan', 'Harga Perolehan', 'Nilai Buku', 'Kondisi', 'Status'],
                    'rows'    => $rows,
                    'money'   => [6, 7],
                    'totals'  => [6 => $rows->sum(6), 7 => $rows->sum(7)],
                ];
        }
    }

    /* ------------------------------------------------------------ HELPER */

    private function filteredQuery(Request $request)
    {
        return Asset::with('category:id,name', 'location:id,name', 'employee:id,name', 'department:id,name')
            ->when($request->filled('q'), function ($q) use ($request) {
                $kata = '%' . $request->q . '%';
                $q->where(fn ($x) => $x->where('code', 'like', $kata)->orWhere('name', 'like', $kata)
                    ->orWhere('serial_number', 'like', $kata)->orWhere('brand', 'like', $kata));
            })
            ->when($request->filled('category'), fn ($q) => $q->where('asset_category_id', $request->category))
            ->when($request->filled('location'), fn ($q) => $q->where('asset_location_id', $request->location))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('condition'), fn ($q) => $q->where('condition', $request->condition));
    }

    private function validated(Request $request, ?Asset $asset): array
    {
        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'asset_category_id'   => 'required|exists:asset_categories,id',
            'type'                => 'nullable|string|max:100',
            'brand'               => 'nullable|string|max:100',
            'model'               => 'nullable|string|max:150',
            'serial_number'       => 'nullable|string|max:150',
            'asset_location_id'   => 'required|exists:asset_locations,id',
            'department_id'       => 'nullable|exists:departments,id',
            'custodian_name'      => 'nullable|string|max:255',
            'acquisition_date'    => 'required|date|before_or_equal:today',
            'acquisition_type'    => 'required|in:' . implode(',', array_keys(Asset::ACQUISITION_TYPES)),
            'cost'                => 'required|integer|min:0|max:999999999999',
            'residual_value'      => 'required|integer|min:0|lte:cost',
            'life_years'          => 'required|integer|min:1|max:50',
            'depreciation_method' => 'required|in:' . implode(',', array_keys(AssetCategory::METHODS)),
            'condition'           => 'required|in:' . implode(',', array_keys(Asset::CONDITIONS)),
            'status'              => ($asset ? 'nullable' : 'required') . '|in:' . implode(',', Asset::INITIAL_STATUSES),
            'supplier'            => 'nullable|string|max:255',
            'po_number'           => 'nullable|string|max:100',
            'warranty_end'        => 'nullable|date',
            'description'         => 'nullable|string|max:5000',
            'spec_keys'           => 'nullable|array|max:30',
            'spec_keys.*'         => 'nullable|string|max:100',
            'spec_values'         => 'nullable|array|max:30',
            'spec_values.*'       => 'nullable|string|max:255',
            'attachments'         => 'nullable|array',
            'attachments.*'       => self::ATTACHMENT_RULES,
        ]);

        $specs = [];
        foreach ($data['spec_keys'] ?? [] as $i => $k) {
            $k = trim((string) $k);
            if ($k !== '') {
                $specs[$k] = trim((string) ($data['spec_values'][$i] ?? ''));
            }
        }
        $data['specs'] = $specs ?: null;
        unset($data['spec_keys'], $data['spec_values'], $data['attachments']);

        return $data;
    }

    private function saveAttachments(Asset $asset, Request $request): void
    {
        foreach ((array) $request->file('attachments', []) as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            AssetAttachment::create([
                'asset_id'      => $asset->id,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'path'          => $file->store('asset_attachments', 'secure'),
                'mime'          => $file->getClientMimeType(),
                'size'          => $file->getSize(),
                'uploaded_by'   => Auth::id(),
            ]);
        }
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function period(Request $request): array
    {
        try {
            $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : now()->startOfYear();
            $to   = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : now()->endOfDay();
        } catch (\Throwable) {
            [$from, $to] = [now()->startOfYear(), now()->endOfDay()];
        }

        return $to->lt($from) ? [$to->copy()->startOfDay(), $from->copy()->endOfDay()] : [$from, $to];
    }

    private function csv(string $filename, array $headers, $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $r) {
                // Cegah formula injection saat dibuka di Excel.
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $r));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
