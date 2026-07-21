<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\MenuItem;

class RoleController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX — list semua role dengan menu count
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        $roles = Role::with('menuItems')
            ->orderBy('name')
            ->get();

        $totalMenus = MenuItem::count();

        return view('role.index', compact('roles', 'totalMenus'));
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT — form checklist menu untuk satu role
    |--------------------------------------------------------------------------
    */
    public function edit($id)
    {
        $role = Role::with('menuItems')->findOrFail($id);

        $menuItems = MenuItem::ordered()->get()->groupBy('section');

        $selectedKeys = $role->menuItems->pluck('key')->toArray();

        return view('role.edit', compact('role', 'menuItems', 'selectedKeys'));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE — simpan menu access untuk role
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        // Superadmin tidak bisa diubah — selalu full access
        if (strtolower($role->name) === 'superadmin') {
            return redirect()->route('role.index')
                ->with('error', 'Superadmin selalu memiliki full access dan tidak dapat diubah.');
        }

        $request->validate([
            'menu_item_ids'   => 'nullable|array',
            'menu_item_ids.*' => 'exists:menu_items,id',
        ]);

        // Sync — hapus semua lalu insert yang dipilih
        $role->menuItems()->sync(
            $request->input('menu_item_ids', [])
        );

        return redirect()->route('role.index')
            ->with('success', 'Menu access untuk role ' . $role->name . ' berhasil diupdate.');
    }

    /*
    |--------------------------------------------------------------------------
    | GET EDIT DATA — AJAX untuk modal
    |--------------------------------------------------------------------------
    */
    public function getEditData($id)
    {
        $role = Role::with('menuItems')->findOrFail($id);

        $menuItems = MenuItem::ordered()->get()->groupBy('section');

        $selectedIds = $role->menuItems->pluck('id')->toArray();

        return response()->json([
            'role'         => [
                'id'         => $role->id,
                'name'       => $role->name,
                'status'     => $role->status,
                'is_super'   => strtolower($role->name) === 'superadmin',
            ],
            'selected_ids' => $selectedIds,
            'menu_items'   => $menuItems->map(fn($items) => $items->map(fn($item) => [
                'id'    => $item->id,
                'key'   => $item->key,
                'label' => $item->label,
                'icon'  => $item->icon,
            ])),
        ]);
    }
}