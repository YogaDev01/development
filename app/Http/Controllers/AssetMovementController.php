<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CapsLargeLists;
use App\Http\Controllers\Concerns\HandlesAssetRecords;
use App\Models\Asset;
use App\Models\AssetLog;
use App\Models\AssetMovement;
use App\Models\Employee;
use App\Support\AssetNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Perpindahan aset: Serah Terima, Pengembalian, Transfer, Check-out/in, Disposal.
 *
 * Alur & syarat status aset (diperiksa ulang di dalam transaksi, aset dikunci):
 *   HANDOVER : AVAILABLE|IN_USE -> ASSIGNED                     (langsung)
 *   RETURN   : ASSIGNED -> AVAILABLE                            (langsung)
 *   TRANSFER : diajukan PENDING; APPROVED memindah lokasi/pemegang
 *   CHECKOUT : AVAILABLE|IN_USE -> CHECKED_OUT; check-in kembali ke status awal
 *   DISPOSAL : diajukan PENDING; APPROVED -> DISPOSED (final)
 * Approve/reject Transfer & Disposal: AccessRoles::ASSET_MANAGE.
 */
class AssetMovementController extends Controller
{
    use CapsLargeLists;
    use HandlesAssetRecords;

    /** Tab halaman => jenis movement yang ditampilkan. */
    public const TABS = [
        'handover' => ['HANDOVER', 'RETURN'],
        'transfer' => ['TRANSFER'],
        'checkout' => ['CHECKOUT'],
        'disposal' => ['DISPOSAL'],
    ];

    private const KIND_OF = [
        'handover' => 'HANDOVER', 'return' => 'RETURN', 'transfer' => 'TRANSFER',
        'checkout' => 'CHECKOUT', 'disposal' => 'DISPOSAL',
    ];

    /** Status aset yang menghalangi pengajuan transfer/disposal. */
    private const BUSY = ['DISPOSED', 'CHECKED_OUT', 'MAINTENANCE'];

    public function index(Request $request)
    {
        $tab = array_key_exists($request->get('tab'), self::TABS) ? $request->get('tab') : 'handover';

        $query = AssetMovement::with('asset:id,code,name', 'fromLocation:id,name', 'toLocation:id,name',
            'fromEmployee:id,name', 'toEmployee:id,name', 'requester:id,name', 'approver:id,name')
            ->whereIn('kind', self::TABS[$tab])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), function ($q) use ($request) {
                $kata = '%' . $request->q . '%';
                $q->where(fn ($x) => $x->where('number', 'like', $kata)
                    ->orWhereHas('asset', fn ($a) => $a->where('code', 'like', $kata)->orWhere('name', 'like', $kata)));
            });
        [$rows, $capped, $total] = $this->cappedRows($query, 'id');

        return view('asset.movements', array_merge($this->lookups(), [
            'tab'       => $tab,
            'rows'      => $rows,
            'capped'    => $capped,
            'total'     => $total,
            'assets'    => $this->selectableAssets(),
            'canManage' => $this->canManageAssets(),
            'filters'   => $request->only(['q', 'status']),
        ]));
    }

    public function store(Request $request, string $kind)
    {
        $kind = self::KIND_OF[$kind] ?? abort(404);

        $data = $request->validate([
            'asset_id'             => 'required|exists:assets,id',
            'movement_date'        => 'required|date|before_or_equal:today',
            'to_employee_id'       => (in_array($kind, ['HANDOVER', 'CHECKOUT'], true) ? 'required' : 'nullable') . '|exists:employees,id',
            'to_location_id'       => ($kind === 'TRANSFER' || $kind === 'RETURN' ? 'required' : 'nullable') . '|exists:asset_locations,id',
            'expected_return_date' => ($kind === 'CHECKOUT' ? 'required' : 'nullable') . '|date|after_or_equal:movement_date',
            'condition'            => 'nullable|in:' . implode(',', array_keys(Asset::CONDITIONS)),
            'reason'               => (in_array($kind, ['TRANSFER', 'DISPOSAL'], true) ? 'required' : 'nullable') . '|string|max:500',
            'notes'                => 'nullable|string|max:2000',
            'disposal_method'      => ($kind === 'DISPOSAL' ? 'required' : 'nullable') . '|in:' . implode(',', array_keys(AssetMovement::DISPOSAL_METHODS)),
            'disposal_value'       => ($kind === 'DISPOSAL' && $request->input('disposal_method') === 'SOLD' ? 'required' : 'nullable') . '|integer|min:0|max:999999999999',
        ]);

        if (in_array($kind, ['HANDOVER', 'CHECKOUT'], true)
            && ! Employee::active()->whereKey($data['to_employee_id'])->exists()) {
            return back()->withInput()->with('error', 'Karyawan penerima tidak aktif.');
        }

        $hasil = DB::transaction(function () use ($kind, $data) {
            $asset = $this->lockAsset((int) $data['asset_id']);

            $galat = $this->precondition($kind, $asset);
            if ($galat) {
                return $galat;
            }

            $movement = AssetMovement::create([
                'number'               => AssetNumber::document('asset_movements', AssetMovement::PREFIX[$kind]),
                'kind'                 => $kind,
                'asset_id'             => $asset->id,
                'status'               => match ($kind) {
                    'TRANSFER', 'DISPOSAL' => 'PENDING',
                    'CHECKOUT'             => 'CHECKED_OUT',
                    default                => 'COMPLETED',
                },
                'asset_status_before'  => $asset->status,
                'from_location_id'     => $asset->asset_location_id,
                'to_location_id'       => $data['to_location_id'] ?? null,
                'from_employee_id'     => $asset->employee_id,
                'to_employee_id'       => $data['to_employee_id'] ?? null,
                'movement_date'        => $data['movement_date'],
                'expected_return_date' => $data['expected_return_date'] ?? null,
                'condition_before'     => $asset->condition,
                'condition_after'      => $data['condition'] ?? null,
                'reason'               => $data['reason'] ?? null,
                'notes'                => $data['notes'] ?? null,
                'disposal_method'      => $data['disposal_method'] ?? null,
                'disposal_value'       => $kind === 'DISPOSAL' ? (int) ($data['disposal_value'] ?? 0) : null,
                'requested_by'         => Auth::id(),
            ]);

            match ($kind) {
                'HANDOVER' => $asset->fill([
                    'status'            => 'ASSIGNED',
                    'employee_id'       => $movement->to_employee_id,
                    'asset_location_id' => $movement->to_location_id ?? $asset->asset_location_id,
                    'condition'         => $movement->condition_after ?? $asset->condition,
                ]),
                'RETURN' => $asset->fill([
                    'status'            => 'AVAILABLE',
                    'employee_id'       => null,
                    'asset_location_id' => $movement->to_location_id,
                    'condition'         => $movement->condition_after ?? $asset->condition,
                ]),
                'CHECKOUT' => $asset->fill(['status' => 'CHECKED_OUT']),
                default    => null,
            };
            $asset->updated_by = Auth::id();
            $asset->save();

            AssetLog::record($asset, strtolower($kind), AssetMovement::KINDS[$kind] . ' ' . $movement->number
                . ($movement->status === 'PENDING' ? ' diajukan.' : ' dicatat.'));

            return $movement;
        });

        if (is_string($hasil)) {
            return back()->withInput()->with('error', $hasil);
        }

        $tab = collect(self::TABS)->search(fn ($kinds) => in_array($kind, $kinds, true));

        return redirect()->route('asset.movements.index', ['tab' => $tab])
            ->with('success', AssetMovement::KINDS[$kind] . " {$hasil->number} berhasil disimpan.");
    }

    public function approve(AssetMovement $movement)
    {
        $this->authorizeManage();

        $hasil = DB::transaction(function () use ($movement) {
            $m = AssetMovement::whereKey($movement->id)->lockForUpdate()->firstOrFail();
            if ($m->status !== 'PENDING' || ! in_array($m->kind, ['TRANSFER', 'DISPOSAL'], true)) {
                return 'Status sudah berubah, muat ulang.';
            }
            $asset = $this->lockAsset($m->asset_id);
            if (in_array($asset->status, self::BUSY, true)) {
                return "Aset sedang {$asset->status}, pengajuan tidak bisa disetujui.";
            }

            if ($m->kind === 'TRANSFER') {
                $asset->asset_location_id = $m->to_location_id;
                if ($m->to_employee_id) {
                    $asset->employee_id = $m->to_employee_id;
                    $asset->status      = 'ASSIGNED';
                }
            } else {
                $asset->status      = 'DISPOSED';
                $asset->disposed_at = $m->movement_date;
                $asset->employee_id = null;
            }
            $asset->updated_by = Auth::id();
            $asset->save();

            $m->update(['status' => 'APPROVED', 'approved_by' => Auth::id(), 'approved_at' => now()]);
            AssetLog::record($asset, strtolower($m->kind) . '_approve', AssetMovement::KINDS[$m->kind] . " {$m->number} disetujui.");

            return $m;
        });

        return is_string($hasil)
            ? back()->with('error', $hasil)
            : back()->with('success', "{$hasil->number} disetujui.");
    }

    public function reject(Request $request, AssetMovement $movement)
    {
        $this->authorizeManage();
        $data = $request->validate(['reject_reason' => 'required|string|max:500']);

        $ok = DB::transaction(function () use ($movement, $data) {
            $m = AssetMovement::whereKey($movement->id)->lockForUpdate()->firstOrFail();
            if ($m->status !== 'PENDING') {
                return false;
            }
            $m->update([
                'status'        => 'REJECTED',
                'approved_by'   => Auth::id(),
                'approved_at'   => now(),
                'reject_reason' => $data['reject_reason'],
            ]);
            AssetLog::record($m->asset, strtolower($m->kind) . '_reject', "{$m->number} ditolak: {$data['reject_reason']}");

            return true;
        });

        return $ok ? back()->with('success', "{$movement->number} ditolak.") : back()->with('error', 'Status sudah berubah, muat ulang.');
    }

    public function checkin(Request $request, AssetMovement $movement)
    {
        $data = $request->validate([
            'returned_date' => 'required|date|before_or_equal:today|after_or_equal:' . $movement->movement_date->toDateString(),
            'condition'     => 'required|in:' . implode(',', array_keys(Asset::CONDITIONS)),
            'notes'         => 'nullable|string|max:2000',
        ]);

        $ok = DB::transaction(function () use ($movement, $data) {
            $m = AssetMovement::whereKey($movement->id)->lockForUpdate()->firstOrFail();
            if ($m->kind !== 'CHECKOUT' || $m->status !== 'CHECKED_OUT') {
                return false;
            }
            $asset = $this->lockAsset($m->asset_id);

            $m->update([
                'status'          => 'RETURNED',
                'returned_date'   => $data['returned_date'],
                'condition_after' => $data['condition'],
                'notes'           => trim(($m->notes ? $m->notes . "\n" : '') . ($data['notes'] ?? '')) ?: null,
            ]);

            if ($asset->status === 'CHECKED_OUT') {
                $asset->status = $m->asset_status_before ?: 'AVAILABLE';
            }
            $asset->condition  = $data['condition'];
            $asset->updated_by = Auth::id();
            $asset->save();
            AssetLog::record($asset, 'checkin', "Check-in {$m->number}, kondisi " . Asset::CONDITIONS[$data['condition']] . '.');

            return true;
        });

        return $ok ? back()->with('success', 'Aset berhasil di-check-in.') : back()->with('error', 'Status sudah berubah, muat ulang.');
    }

    /** Dokumen serah terima (cetak) untuk Serah Terima, Pengembalian, Transfer & Check-out. */
    public function document(AssetMovement $movement)
    {
        abort_if($movement->kind === 'DISPOSAL', 404);
        $movement->load('asset.category', 'fromLocation', 'toLocation', 'fromEmployee', 'toEmployee', 'requester', 'approver');

        return view('asset.bast', ['m' => $movement]);
    }

    /** Pesan galat bila aset tidak boleh menjalani transaksi $kind, atau null. */
    private function precondition(string $kind, Asset $asset): ?string
    {
        $label = AssetMovement::KINDS[$kind];

        $ok = match ($kind) {
            'HANDOVER', 'CHECKOUT' => in_array($asset->status, ['AVAILABLE', 'IN_USE'], true),
            'RETURN'               => $asset->status === 'ASSIGNED',
            default                => ! in_array($asset->status, self::BUSY, true),
        };
        if (! $ok) {
            return "{$label} tidak bisa dilakukan: status aset {$asset->code} saat ini {$asset->status}.";
        }

        if (in_array($kind, ['TRANSFER', 'DISPOSAL'], true)
            && AssetMovement::where('asset_id', $asset->id)->whereIn('kind', ['TRANSFER', 'DISPOSAL'])->where('status', 'PENDING')->exists()) {
            return "Aset {$asset->code} masih punya pengajuan Transfer/Disposal yang menunggu persetujuan.";
        }

        return null;
    }
}
