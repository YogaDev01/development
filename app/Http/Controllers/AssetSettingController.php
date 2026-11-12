<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesAssetRecords;
use App\Models\AssetCategory;
use App\Models\AssetLocation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pengaturan Asset Management: Kategori (termasuk parameter penyusutan
 * default) dan Lokasi. Melihat = menu:asset-management; mengubah =
 * AccessRoles::ASSET_MANAGE. Departemen & User/Akses memakai master TESYS
 * yang sudah ada (Team/Department, User & Access) — tidak diduplikasi.
 *
 * Mengubah parameter penyusutan kategori hanya berlaku untuk aset BARU;
 * aset yang sudah terdaftar menyimpan parameternya sendiri.
 */
class AssetSettingController extends Controller
{
    use HandlesAssetRecords;

    public function index(Request $request)
    {
        return view('asset.settings', [
            'tab'        => $request->get('tab') === 'locations' ? 'locations' : 'categories',
            'categories' => AssetCategory::withCount('assets')->orderBy('name')->get(),
            'locations'  => AssetLocation::withCount('assets')->orderBy('name')->get(),
            'canManage'  => $this->canManageAssets(),
        ]);
    }

    public function categoryStore(Request $request)
    {
        $this->authorizeManage();
        AssetCategory::create($this->categoryData($request));

        return redirect()->route('asset.settings.index')->with('success', 'Kategori ditambahkan.');
    }

    public function categoryUpdate(Request $request, AssetCategory $category)
    {
        $this->authorizeManage();
        $category->update($this->categoryData($request, $category));

        return redirect()->route('asset.settings.index')->with('success', 'Kategori diperbarui.');
    }

    public function locationStore(Request $request)
    {
        $this->authorizeManage();
        AssetLocation::create($this->locationData($request));

        return redirect()->route('asset.settings.index', ['tab' => 'locations'])->with('success', 'Lokasi ditambahkan.');
    }

    public function locationUpdate(Request $request, AssetLocation $location)
    {
        $this->authorizeManage();
        $location->update($this->locationData($request, $location));

        return redirect()->route('asset.settings.index', ['tab' => 'locations'])->with('success', 'Lokasi diperbarui.');
    }

    private function categoryData(Request $request, ?AssetCategory $category = null): array
    {
        $data = $request->validate([
            // Kode dipakai di nomor aset (AST-{KODE}-...), jadi tidak bisa diubah setelah dibuat.
            'code'                => $category ? 'prohibited' : ['required', 'alpha_num', 'max:10', Rule::unique('asset_categories', 'code')],
            'name'                => 'required|string|max:255',
            'life_years'          => 'required|integer|min:1|max:50',
            'depreciation_method' => 'required|in:' . implode(',', array_keys(AssetCategory::METHODS)),
            'residual_percent'    => 'required|numeric|min:0|max:100',
            'account'             => 'nullable|string|max:255',
            'description'         => 'nullable|string|max:2000',
            'status'              => 'required|in:' . implode(',', AssetCategory::STATUSES),
        ]);
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        return $data;
    }

    private function locationData(Request $request, ?AssetLocation $location = null): array
    {
        $data = $request->validate([
            'code'     => ['required', 'string', 'max:20', Rule::unique('asset_locations', 'code')->ignore($location?->id)],
            'name'     => 'required|string|max:255',
            'type'     => 'required|in:' . implode(',', array_keys(AssetLocation::TYPES)),
            'address'  => 'nullable|string|max:2000',
            'pic_name' => 'nullable|string|max:255',
            'status'   => 'required|in:' . implode(',', AssetLocation::STATUSES),
        ]);
        $data['code'] = strtoupper($data['code']);

        return $data;
    }
}
