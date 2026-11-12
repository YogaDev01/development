<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesAssetRecords;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetLog;
use App\Models\AssetPurchaseRequest;
use App\Models\AssetReceiving;
use App\Support\AssetNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Pengadaan aset: Purchase Request -> persetujuan -> Penerimaan.
 *
 * Penerimaan mendaftarkan setiap unit sebagai aset baru (status AVAILABLE di
 * lokasi penerimaan) dengan parameter penyusutan default kategorinya. PR yang
 * seluruh unitnya sudah diterima menjadi RECEIVED.
 *
 * Modul ini TIDAK membuat Cost Request, Purchase Order proyek, atau
 * pembayaran apa pun — pembayarannya tetap lewat alur Finance yang ada.
 */
class AssetProcurementController extends Controller
{
    use HandlesAssetRecords;

    /** Batas unit per penerimaan agar satu request tidak membuat ribuan aset. */
    private const MAX_QTY = 100;

    public function index(Request $request)
    {
        $tab = in_array($request->get('tab'), ['pr', 'receiving', 'history'], true) ? $request->get('tab') : 'pr';

        return view('asset.procurement', array_merge($this->lookups(), [
            'tab'        => $tab,
            'requests'   => AssetPurchaseRequest::with('category:id,name', 'department:id,name', 'requester:id,name', 'approver:id,name', 'receivings:id,asset_purchase_request_id,qty')
                ->orderByDesc('id')->limit(1000)->get(),
            'receivings' => AssetReceiving::with('category:id,name', 'location:id,name', 'purchaseRequest:id,number', 'receiver:id,name')
                ->withCount('assets')->orderByDesc('id')->limit(1000)->get(),
            'history'    => Asset::withTrashed()->with('category:id,name')
                ->orderByDesc('acquisition_date')->orderByDesc('id')->limit(1000)
                ->get(['id', 'code', 'name', 'asset_category_id', 'acquisition_date', 'acquisition_type', 'supplier', 'po_number', 'cost', 'status', 'deleted_at']),
            'approvedPr' => AssetPurchaseRequest::where('status', 'APPROVED')->with('receivings:id,asset_purchase_request_id,qty')->orderBy('number')->get(),
            'canManage'  => $this->canManageAssets(),
        ]));
    }

    public function prStore(Request $request)
    {
        $data = $request->validate([
            'item_name'           => 'required|string|max:255',
            'asset_category_id'   => 'required|exists:asset_categories,id',
            'department_id'       => 'nullable|exists:departments,id',
            'qty'                 => 'required|integer|min:1|max:' . self::MAX_QTY,
            'estimated_unit_cost' => 'required|integer|min:0|max:999999999999',
            'needed_date'         => 'nullable|date',
            'justification'       => 'required|string|max:2000',
        ]);

        $pr = DB::transaction(fn () => AssetPurchaseRequest::create(array_merge($data, [
            'number'       => AssetNumber::document('asset_purchase_requests', 'PR-AST'),
            'status'       => 'PENDING',
            'requested_by' => Auth::id(),
        ])));

        return redirect()->route('asset.procurement.index')->with('success', "Purchase Request {$pr->number} diajukan.");
    }

    public function prApprove(AssetPurchaseRequest $purchaseRequest)
    {
        $this->authorizeManage();

        $ok = $this->decide($purchaseRequest, ['status' => 'APPROVED']);

        return $ok ? back()->with('success', "{$purchaseRequest->number} disetujui.") : back()->with('error', 'Status sudah berubah, muat ulang.');
    }

    public function prReject(Request $request, AssetPurchaseRequest $purchaseRequest)
    {
        $this->authorizeManage();
        $data = $request->validate(['reject_reason' => 'required|string|max:500']);

        $ok = $this->decide($purchaseRequest, ['status' => 'REJECTED', 'reject_reason' => $data['reject_reason']]);

        return $ok ? back()->with('success', "{$purchaseRequest->number} ditolak.") : back()->with('error', 'Status sudah berubah, muat ulang.');
    }

    public function receivingStore(Request $request)
    {
        $data = $request->validate([
            'asset_purchase_request_id' => 'nullable|exists:asset_purchase_requests,id',
            'item_name'                 => 'required|string|max:255',
            'asset_category_id'         => 'required|exists:asset_categories,id',
            'asset_location_id'         => 'required|exists:asset_locations,id',
            'brand'                     => 'nullable|string|max:100',
            'model'                     => 'nullable|string|max:150',
            'supplier'                  => 'nullable|string|max:255',
            'po_number'                 => 'nullable|string|max:100',
            'received_date'             => 'required|date|before_or_equal:today',
            'qty'                       => 'required|integer|min:1|max:' . self::MAX_QTY,
            'unit_cost'                 => 'required|integer|min:0|max:999999999999',
            'serial_numbers'            => 'nullable|string|max:5000',
            'notes'                     => 'nullable|string|max:2000',
        ]);

        $hasil = DB::transaction(function () use ($data) {
            $pr = null;
            if (! empty($data['asset_purchase_request_id'])) {
                $pr = AssetPurchaseRequest::whereKey($data['asset_purchase_request_id'])->lockForUpdate()->first();
                if (! $pr || $pr->status !== 'APPROVED') {
                    return 'Purchase Request belum disetujui atau sudah diterima penuh.';
                }
                $sisa = $pr->qty - (int) $pr->receivings()->sum('qty');
                if ($data['qty'] > $sisa) {
                    return "Jumlah melebihi sisa PR ({$sisa} unit).";
                }
            }

            $category = AssetCategory::findOrFail($data['asset_category_id']);
            $receiving = AssetReceiving::create(array_merge(
                collect($data)->except(['brand', 'model', 'serial_numbers'])->all(),
                ['number' => AssetNumber::document('asset_receivings', 'GR-AST'), 'received_by' => Auth::id()]
            ));

            $serials = collect(preg_split('/\r\n|\r|\n|,/', (string) ($data['serial_numbers'] ?? '')))
                ->map(fn ($s) => trim($s))->filter()->values();
            $tahun = (int) $receiving->received_date->format('Y');
            $residu = (int) round($receiving->unit_cost * $category->residual_percent / 100);

            for ($i = 0; $i < $receiving->qty; $i++) {
                $asset = Asset::create([
                    'code'                => AssetNumber::assetCode($category->code, $tahun),
                    'name'                => $receiving->item_name,
                    'asset_category_id'   => $category->id,
                    'brand'               => $data['brand'] ?? null,
                    'model'               => $data['model'] ?? null,
                    'serial_number'       => $serials[$i] ?? null,
                    'asset_location_id'   => $receiving->asset_location_id,
                    'department_id'       => $pr?->department_id,
                    'acquisition_date'    => $receiving->received_date,
                    'acquisition_type'    => 'PURCHASE',
                    'cost'                => $receiving->unit_cost,
                    'residual_value'      => min($residu, $receiving->unit_cost),
                    'life_years'          => $category->life_years,
                    'depreciation_method' => $category->depreciation_method,
                    'condition'           => 'EXCELLENT',
                    'status'              => 'AVAILABLE',
                    'supplier'            => $receiving->supplier,
                    'po_number'           => $receiving->po_number,
                    'asset_receiving_id'  => $receiving->id,
                    'created_by'          => Auth::id(),
                    'updated_by'          => Auth::id(),
                ]);
                AssetLog::record($asset, 'create', "Didaftarkan dari penerimaan {$receiving->number}.");
            }

            if ($pr && (int) $pr->receivings()->sum('qty') >= $pr->qty) {
                $pr->update(['status' => 'RECEIVED']);
            }

            return $receiving;
        });

        if (is_string($hasil)) {
            return back()->withInput()->with('error', $hasil);
        }

        return redirect()->route('asset.procurement.index', ['tab' => 'receiving'])
            ->with('success', "Penerimaan {$hasil->number} disimpan, {$hasil->qty} aset didaftarkan.");
    }

    private function decide(AssetPurchaseRequest $pr, array $changes): bool
    {
        return DB::transaction(function () use ($pr, $changes) {
            $locked = AssetPurchaseRequest::whereKey($pr->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'PENDING') {
                return false;
            }
            $locked->update(array_merge($changes, ['approved_by' => Auth::id(), 'approved_at' => now()]));

            return true;
        });
    }
}
