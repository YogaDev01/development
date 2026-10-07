<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Role;
use App\Models\MenuItem;
use App\Models\UserActivityLog;
use App\Support\Navigation;

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

        $summary = [
            'total'    => $roles->count(),
            'active'   => $roles->where('status', 'ACTIVE')->count(),
            'inactive' => $roles->where('status', 'INACTIVE')->count(),
        ];

        return view('role.index', compact('roles', 'totalMenus', 'summary'));
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

        $sebelum = $role->menuItems()->pluck('menu_items.id')->map(fn ($id) => (int) $id)->sort()->values();
        $sesudah = collect($request->input('menu_item_ids', []))
            ->map(fn ($id) => (int) $id)->unique()->sort()->values();

        /*
        | sync() DI DALAM transaksi.
        |
        | sync() mengeluarkan DELETE lalu INSERT. Kalau gagal di antara keduanya
        | — deadlock, koneksi terputus, timeout — role kehilangan SELURUH
        | menunya, dan karena CheckMenu serta sidebar membaca tabel yang sama,
        | setiap pengguna role itu langsung terkunci dari seluruh aplikasi
        | sampai superadmin menyadarinya dan memperbaiki manual. Untuk satu
        | operasi yang bisa mengunci puluhan orang, "biasanya berhasil" bukan
        | jaminan yang cukup.
        */
        DB::transaction(function () use ($role, $sesudah) {
            $role->menuItems()->sync($sesudah->all());
        });

        /*
        | Jejak audit perubahan hak akses.
        |
        | Sebelumnya tidak ada catatan apa pun tentang siapa mengubah kewenangan
        | role mana dan kapan. Untuk sistem yang menyetujui pembayaran, itu
        | celah audit: pertanyaan "sejak kapan role ini boleh membuka Treasury,
        | dan siapa yang memberikannya" tidak bisa dijawab.
        |
        | Dicatat hanya SELISIHNYA, bukan seluruh daftar — yang ingin diketahui
        | saat menelusuri adalah apa yang berubah.
        */
        $ditambah = $sesudah->diff($sebelum);
        $dicabut  = $sebelum->diff($sesudah);

        if ($ditambah->isNotEmpty() || $dicabut->isNotEmpty()) {
            $namaMenu = MenuItem::whereIn('id', $ditambah->merge($dicabut))
                ->pluck('key', 'id');

            Log::info('Menu access role diubah', [
                'role_id'    => $role->id,
                'role'       => $role->name,
                'ditambah'   => $ditambah->map(fn ($id) => $namaMenu[$id] ?? $id)->values()->all(),
                'dicabut'    => $dicabut->map(fn ($id) => $namaMenu[$id] ?? $id)->values()->all(),
                'oleh_id'    => Auth::id(),
                'oleh'       => Auth::user()?->username,
            ]);
        }

        UserActivityLog::record('user_access', 'update', 'Mengubah akses menu role ' . $role->name, $role);

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

        $selectedIds = $role->menuItems->pluck('id')->toArray();

        /*
        | Susunan checklist dibaca dari config/navigation.php + config/apps.php
        | (sumber yang sama dengan sidebar), bukan dari kolom menu_items.section
        | yang ditulis tangan: menu dikelompokkan per aplikasi Tesys -> seksi,
        | dan Master Data tampil SEKALI karena berlaku di semua aplikasi.
        */
        return response()->json([
            'role'         => [
                'id'         => $role->id,
                'name'       => $role->name,
                'status'     => $role->status,
                'is_super'   => strtolower($role->name) === 'superadmin',
            ],
            'selected_ids' => $selectedIds,
            'tree'         => Navigation::accessTree(),
        ]);
    }
}