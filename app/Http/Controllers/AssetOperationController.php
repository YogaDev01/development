<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CapsLargeLists;
use App\Http\Controllers\Concerns\HandlesAssetRecords;
use App\Models\Asset;
use App\Models\AssetInspection;
use App\Models\AssetLog;
use App\Models\AssetMaintenance;
use App\Models\AssetMaintenanceSchedule;
use App\Support\AssetNumber;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Operasional aset: Maintenance, Jadwal Maintenance, dan Inspeksi.
 *
 * Maintenance: aset AVAILABLE|ASSIGNED|IN_USE -> MAINTENANCE selama pekerjaan
 * IN_PROGRESS/WAITING_PARTS; COMPLETED/CANCELLED mengembalikan status
 * sebelumnya (asset_status_before). Menyelesaikan maintenance yang berasal dari
 * jadwal memajukan next_due_date jadwal tersebut sebesar interval_days.
 * Biaya maintenance hanya dicatat di sini — tidak membuat Cost Request.
 */
class AssetOperationController extends Controller
{
    use CapsLargeLists;
    use HandlesAssetRecords;

    /* -------------------------------------------------------- MAINTENANCE */

    public function maintenance(Request $request)
    {
        $tab = in_array($request->get('tab'), ['records', 'schedule'], true) ? $request->get('tab') : 'records';

        $query = AssetMaintenance::with('asset:id,code,name')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), function ($q) use ($request) {
                $kata = '%' . $request->q . '%';
                $q->where(fn ($x) => $x->where('number', 'like', $kata)->orWhere('vendor', 'like', $kata)
                    ->orWhereHas('asset', fn ($a) => $a->where('code', 'like', $kata)->orWhere('name', 'like', $kata)));
            });
        [$rows, $capped, $total] = $this->cappedRows($query, 'id');

        $schedules = AssetMaintenanceSchedule::with('asset:id,code,name')->orderBy('next_due_date')->limit(1000)->get();

        return view('asset.maintenance', [
            'tab'       => $tab,
            'rows'      => $rows,
            'capped'    => $capped,
            'total'     => $total,
            'schedules' => $schedules,
            'assets'    => $this->selectableAssets(),
            'filters'   => $request->only(['q', 'status']),
        ]);
    }

    public function maintenanceStore(Request $request)
    {
        $data = $request->validate([
            'asset_id'                      => 'required|exists:assets,id',
            'asset_maintenance_schedule_id' => 'nullable|exists:asset_maintenance_schedules,id',
            'maintenance_type'              => 'required|in:' . implode(',', array_keys(AssetMaintenance::TYPES)),
            'problem'                       => 'required|string|max:1000',
            'vendor'                        => 'nullable|string|max:255',
            'estimated_cost'                => 'nullable|integer|min:0|max:999999999999',
            'start_date'                    => 'required|date|before_or_equal:today',
        ]);

        $hasil = DB::transaction(function () use ($data) {
            $asset = $this->lockAsset((int) $data['asset_id']);
            if (! in_array($asset->status, ['AVAILABLE', 'ASSIGNED', 'IN_USE'], true)) {
                return "Maintenance tidak bisa dibuka: status aset {$asset->code} saat ini {$asset->status}.";
            }
            if (! empty($data['asset_maintenance_schedule_id'])
                && ! AssetMaintenanceSchedule::whereKey($data['asset_maintenance_schedule_id'])->where('asset_id', $asset->id)->exists()) {
                return 'Jadwal maintenance bukan milik aset ini.';
            }

            $mr = AssetMaintenance::create(array_merge($data, [
                'number'              => AssetNumber::document('asset_maintenances', 'MR-AST'),
                'estimated_cost'      => (int) ($data['estimated_cost'] ?? 0),
                'status'              => 'IN_PROGRESS',
                'asset_status_before' => $asset->status,
                'created_by'          => Auth::id(),
            ]));
            $asset->update(['status' => 'MAINTENANCE', 'updated_by' => Auth::id()]);
            AssetLog::record($asset, 'maintenance', "Maintenance {$mr->number} dibuka: {$mr->problem}");

            return $mr;
        });

        if (is_string($hasil)) {
            return back()->withInput()->with('error', $hasil);
        }

        return redirect()->route('asset.maintenance.index')->with('success', "Maintenance {$hasil->number} dibuat.");
    }

    /** Ubah status pekerjaan: WAITING_PARTS / IN_PROGRESS / COMPLETED / CANCELLED. */
    public function maintenanceUpdate(Request $request, AssetMaintenance $maintenance)
    {
        $data = $request->validate([
            'status'       => 'required|in:IN_PROGRESS,WAITING_PARTS,COMPLETED,CANCELLED',
            'actual_cost'  => 'nullable|integer|min:0|max:999999999999',
            'end_date'     => 'required_if:status,COMPLETED|nullable|date|before_or_equal:today|after_or_equal:' . $maintenance->start_date->toDateString(),
            'condition'    => 'required_if:status,COMPLETED|nullable|in:' . implode(',', array_keys(Asset::CONDITIONS)),
            'result_notes' => 'nullable|string|max:2000',
        ]);

        $ok = DB::transaction(function () use ($maintenance, $data) {
            $mr = AssetMaintenance::whereKey($maintenance->id)->lockForUpdate()->firstOrFail();
            if (! in_array($mr->status, AssetMaintenance::OPEN_STATUSES, true)) {
                return false;
            }
            $asset = $this->lockAsset($mr->asset_id);
            $selesai = in_array($data['status'], ['COMPLETED', 'CANCELLED'], true);

            $mr->update([
                'status'       => $data['status'],
                'actual_cost'  => $data['status'] === 'COMPLETED' ? (int) ($data['actual_cost'] ?? 0) : $mr->actual_cost,
                'end_date'     => $selesai ? ($data['end_date'] ?? now()->toDateString()) : null,
                'result_notes' => $data['result_notes'] ?? $mr->result_notes,
            ]);

            if ($selesai && $asset->status === 'MAINTENANCE') {
                $asset->status = $mr->asset_status_before ?: 'AVAILABLE';
                if ($data['status'] === 'COMPLETED' && ! empty($data['condition'])) {
                    $asset->condition = $data['condition'];
                }
                $asset->updated_by = Auth::id();
                $asset->save();
            }

            if ($data['status'] === 'COMPLETED' && $mr->asset_maintenance_schedule_id) {
                $jadwal = AssetMaintenanceSchedule::whereKey($mr->asset_maintenance_schedule_id)->lockForUpdate()->first();
                if ($jadwal) {
                    $jadwal->update([
                        'last_done_date' => $mr->end_date,
                        'next_due_date'  => Carbon::parse($mr->end_date)->addDays($jadwal->interval_days),
                    ]);
                }
            }

            AssetLog::record($asset, 'maintenance', "Maintenance {$mr->number} -> {$data['status']}.");

            return true;
        });

        return $ok ? back()->with('success', 'Status maintenance diperbarui.') : back()->with('error', 'Status sudah berubah, muat ulang.');
    }

    public function scheduleStore(Request $request)
    {
        $data = $this->scheduleData($request);
        if (Asset::whereKey($data['asset_id'])->value('status') === 'DISPOSED') {
            return back()->withInput()->with('error', 'Aset sudah di-disposal.');
        }
        $data['created_by'] = Auth::id();
        $data['status']     = 'ACTIVE';
        $jadwal = AssetMaintenanceSchedule::create($data);
        AssetLog::record($jadwal->asset, 'schedule', "Jadwal maintenance \"{$jadwal->title}\" dibuat, setiap {$jadwal->interval_days} hari.");

        return redirect()->route('asset.maintenance.index', ['tab' => 'schedule'])->with('success', 'Jadwal maintenance dibuat.');
    }

    public function scheduleUpdate(Request $request, AssetMaintenanceSchedule $schedule)
    {
        $data = $this->scheduleData($request, true);
        $schedule->update($data);

        return redirect()->route('asset.maintenance.index', ['tab' => 'schedule'])->with('success', 'Jadwal maintenance diperbarui.');
    }

    private function scheduleData(Request $request, bool $update = false): array
    {
        return $request->validate(array_filter([
            'asset_id'         => $update ? null : 'required|exists:assets,id',
            'title'            => 'required|string|max:255',
            'maintenance_type' => 'required|in:' . implode(',', array_keys(AssetMaintenance::TYPES)),
            'interval_days'    => 'required|integer|min:1|max:3650',
            'next_due_date'    => 'required|date',
            'vendor'           => 'nullable|string|max:255',
            'status'           => $update ? 'required|in:ACTIVE,INACTIVE' : null,
        ]));
    }

    /* --------------------------------------------------------- INSPEKSI */

    public function inspection(Request $request)
    {
        $query = AssetInspection::with('asset:id,code,name')
            ->when($request->filled('result'), fn ($q) => $q->where('result', $request->result))
            ->when($request->filled('q'), function ($q) use ($request) {
                $kata = '%' . $request->q . '%';
                $q->where(fn ($x) => $x->where('number', 'like', $kata)->orWhere('inspector_name', 'like', $kata)
                    ->orWhereHas('asset', fn ($a) => $a->where('code', 'like', $kata)->orWhere('name', 'like', $kata)));
            });
        [$rows, $capped, $total] = $this->cappedRows($query, 'id');

        return view('asset.inspection', [
            'rows'    => $rows,
            'capped'  => $capped,
            'total'   => $total,
            'assets'  => $this->selectableAssets(),
            'filters' => $request->only(['q', 'result']),
        ]);
    }

    public function inspectionStore(Request $request)
    {
        $data = $request->validate([
            'asset_id'        => 'required|exists:assets,id',
            'inspection_date' => 'required|date|before_or_equal:today',
            'inspector_name'  => 'required|string|max:255',
            'condition'       => 'required|in:' . implode(',', array_keys(Asset::CONDITIONS)),
            'result'          => 'required|in:' . implode(',', array_keys(AssetInspection::RESULTS)),
            'findings'        => 'nullable|string|max:3000',
            'follow_up'       => 'nullable|string|max:3000',
        ]);

        $hasil = DB::transaction(function () use ($data) {
            $asset = $this->lockAsset((int) $data['asset_id']);
            if ($asset->isDisposed()) {
                return 'Aset sudah di-disposal.';
            }
            $ins = AssetInspection::create(array_merge($data, [
                'number'     => AssetNumber::document('asset_inspections', 'INS-AST'),
                'created_by' => Auth::id(),
            ]));
            $asset->update(['condition' => $data['condition'], 'updated_by' => Auth::id()]);
            AssetLog::record($asset, 'inspection', "Inspeksi {$ins->number}: " . AssetInspection::RESULTS[$ins->result] . '.');

            return $ins;
        });

        if (is_string($hasil)) {
            return back()->withInput()->with('error', $hasil);
        }

        return redirect()->route('asset.inspection.index')->with('success', "Inspeksi {$hasil->number} dicatat.");
    }
}
