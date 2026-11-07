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
    | STORE — tambah role baru (D3, disetujui pemilik 2026-11-07)
    |--------------------------------------------------------------------------
    | Kewenangan di TESYS masih banyak yang ditulis sebagai nama role di kode
    | (gerbang route role:, AccessRoles, STAGE_ROLES, kondisi Blade, ...).
    | Role baru yang namanya kebetulan sama dengan salah satu nama itu akan
    | langsung mewarisi kewenangannya — misalnya role "guest" baru otomatis
    | melihat nilai PO. Karena itu nama yang dipakai kode ditolak, dan role
    | baru dibuat TANPA menu apa pun (default deny). Nama tidak bisa diubah
    | setelah dibuat (mengganti nama = mengganti kewenangan).
    */

    /** Nama role yang dibaca langsung oleh kode di luar gerbang route. */
    private const RESERVED_NAMES = [
        'superadmin', 'approver', 'head', 'manager', 'supervisor', 'finance',
        'finance_manager', 'cost_control', 'treasury', 'accounting', 'collection',
        'admin', 'hr', 'guest', 'teknisi', 'staff',
    ];

    public function store(Request $request)
    {
        $request->merge(['name' => strtolower(trim((string) $request->input('name')))]);

        $request->validate([
            'name' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{2,39}$/', 'unique:roles,name'],
        ], [
            'name.regex'  => 'Nama role hanya huruf kecil, angka, dan garis bawah; 3–40 karakter, diawali huruf.',
            'name.unique' => 'Role dengan nama ini sudah ada.',
        ]);

        if (in_array($request->name, $this->reservedNames(), true)) {
            return back()->withInput()->withErrors([
                'name' => 'Nama ini dipakai aturan bawaan aplikasi dan tidak dapat dipakai untuk role baru.',
            ]);
        }

        $role = Role::create(['name' => $request->name, 'status' => 'ACTIVE']);

        UserActivityLog::record('user_access', 'create', 'Menambah role ' . $role->name . ' (tanpa akses menu)', $role, null, null, [
            ['label' => 'Role', 'from' => '-', 'to' => $role->name],
        ]);

        return redirect()->route('role.index')
            ->with('success', 'Role ' . $role->name . ' dibuat tanpa akses menu. Atur menunya lewat tombol Atur Menu Access.');
    }

    /** RESERVED_NAMES ∪ setiap nama role yang dipakai gerbang role: di route. */
    private function reservedNames(): array
    {
        $dariRoute = [];

        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $m) {
                if (is_string($m) && str_starts_with($m, 'role:')) {
                    foreach (explode(',', substr($m, 5)) as $nama) {
                        $dariRoute[] = strtolower(trim($nama));
                    }
                }
            }
        }

        return array_values(array_unique(array_merge(self::RESERVED_NAMES, $dariRoute)));
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
        $changes  = [];

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

            // Selisih yang sama juga masuk jejak audit aplikasi (konsol Keamanan),
            // bukan hanya laravel.log yang tidak terbaca dari aplikasi.
            if ($ditambah->isNotEmpty()) {
                $changes[] = ['label' => 'Menu ditambah', 'from' => '-', 'to' => $ditambah->map(fn ($id) => $namaMenu[$id] ?? $id)->implode(', ')];
            }
            if ($dicabut->isNotEmpty()) {
                $changes[] = ['label' => 'Menu dicabut', 'from' => $dicabut->map(fn ($id) => $namaMenu[$id] ?? $id)->implode(', '), 'to' => '-'];
            }
        }

        UserActivityLog::record('user_access', 'update', 'Mengubah akses menu role ' . $role->name, $role, null, null, $changes);

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
            // Fase 3 — kewenangan aksi per kelompok, BACA-SAJA selama mode uji
            // (gerbang role: lama masih yang berlaku; tidak ada jalur simpan).
            'permissions'  => \App\Support\Authorizer::groupStatusForRole((int) $role->id),
            // Fase 4 — batas data per modul, BACA-SAJA (mesin belum dipasang ke daftar).
            'data_scopes'  => \App\Support\DataScope::statusForRole((int) $role->id),
        ]);
    }
}