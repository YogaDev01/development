<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Daftarkan menu 'asset-management' dan role 'asset_admin' (2026-11-12).
 *
 * Aditif & aman diulang:
 *   1. Baris menu_items 'asset-management' dibuat bila belum ada.
 *   2. Role 'asset_admin' dibuat bila belum ada, lalu diberi menu tersebut.
 * Role lain TIDAK diberi menu otomatis — pemberiannya lewat modal Menu Access.
 * Superadmin tidak dicatat: Role::hasMenu() & CheckMenu sudah meloloskannya.
 */
return new class extends Migration
{
    private const MENU = 'asset-management';

    private const ROLE = 'asset_admin';

    public function up(): void
    {
        if (! Schema::hasTable('menu_items') || ! Schema::hasTable('roles') || ! Schema::hasTable('role_menu_items')) {
            return;
        }

        $menuId = DB::table('menu_items')->where('key', self::MENU)->value('id');
        if (! $menuId) {
            $menuId = DB::table('menu_items')->insertGetId([
                'key'        => self::MENU,
                'label'      => 'Asset Management',
                'icon'       => 'fas fa-boxes',
                'route'      => '/asset',
                'section'    => 'finance',
                'sort_order' => 60,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->catat("buat menu '" . self::MENU . "'");
        }

        $roleId = DB::table('roles')->where('name', self::ROLE)->value('id');
        if (! $roleId) {
            $roleId = DB::table('roles')->insertGetId([
                'name'       => self::ROLE,
                'status'     => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->catat("buat role '" . self::ROLE . "'");
        }

        $inserted = DB::table('role_menu_items')->insertOrIgnore([
            'role_id'      => $roleId,
            'menu_item_id' => $menuId,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        if ($inserted) {
            $this->catat("beri menu '" . self::MENU . "' ke role '" . self::ROLE . "'");
        }
    }

    /**
     * Mencabut menu beserta seluruh pemberiannya. Role asset_admin hanya
     * dihapus bila tidak ada user yang memakainya.
     */
    public function down(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }

        $menuId = DB::table('menu_items')->where('key', self::MENU)->value('id');
        if ($menuId) {
            DB::table('role_menu_items')->where('menu_item_id', $menuId)->delete();
            DB::table('menu_items')->where('id', $menuId)->delete();
        }

        $roleId = DB::table('roles')->where('name', self::ROLE)->value('id');
        if ($roleId && ! DB::table('users')->where('role_id', $roleId)->exists()) {
            DB::table('role_menu_items')->where('role_id', $roleId)->delete();
            DB::table('roles')->where('id', $roleId)->delete();
        }
    }

    private function catat(string $pesan): void
    {
        Log::info('[migrasi 2026_11_12_000200 asset-management] ' . $pesan);
        echo '  ' . $pesan . PHP_EOL;
    }
};
