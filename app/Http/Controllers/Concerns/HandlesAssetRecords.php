<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetLocation;
use App\Models\Department;
use App\Models\Employee;
use App\Support\AccessRoles;
use Illuminate\Support\Facades\Auth;

/**
 * Bantuan bersama controller modul Asset Management.
 *
 * Seluruh perubahan status aset terjadi di dalam DB::transaction dengan baris
 * aset dikunci (lockAsset), lalu status diperiksa ulang — pola yang sama
 * dengan Concerns\TransitionsStatus, supaya dua aksi bersamaan atas satu aset
 * tidak saling menimpa.
 */
trait HandlesAssetRecords
{
    protected function canManageAssets(): bool
    {
        return (bool) Auth::user()?->hasRole(AccessRoles::ASSET_MANAGE);
    }

    protected function authorizeManage(): void
    {
        abort_unless($this->canManageAssets(), 403, 'Hanya Asset Admin yang boleh melakukan aksi ini.');
    }

    /** Ambil ulang aset dengan row lock. Wajib dipanggil di dalam transaksi. */
    protected function lockAsset(int $assetId): Asset
    {
        return Asset::whereKey($assetId)->lockForUpdate()->firstOrFail();
    }

    /** Data pilihan yang dipakai banyak form. */
    protected function lookups(): array
    {
        return [
            'categories'  => AssetCategory::active()->orderBy('name')->get(),
            'locations'   => AssetLocation::active()->orderBy('name')->get(),
            'departments' => Department::where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name']),
            'employees'   => Employee::active()->orderBy('name')->get(['id', 'name', 'employee_number']),
        ];
    }

    /** Aset yang masih bisa dipilih untuk transaksi (belum DISPOSED). */
    protected function selectableAssets()
    {
        return Asset::live()->orderBy('code')->get(['id', 'code', 'name', 'status', 'asset_location_id', 'employee_id', 'condition']);
    }
}
