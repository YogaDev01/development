<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * M-04 — daftarkan menu 'closing' dan beri aksesnya.
 *
 * TEMUAN AUDIT: route Closing dijaga `middleware('menu:closing')` dan
 * config/navigation.php sudah memuat entrinya, tapi BARIS 'closing' di tabel
 * `menu_items` tidak pernah ada. Akibatnya Role::hasMenu('closing') selalu
 * false untuk semua role, dan halaman itu hanya bisa dibuka superadmin —
 * yang tidak kelihatan selama halamannya masih mockup, tapi jadi masalah
 * nyata begitu Closing tersambung ke data.
 *
 * Role penerima: finance & finance_manager (yang menyusun rekap) serta
 * approver/CEO (yang menutup periode). Superadmin otomatis lolos.
 *
 * Aman diulang.
 */
return new class extends Migration
{
    private const ROLES = ['finance', 'finance_manager', 'approver'];

    public function up(): void
    {
        $menuId = DB::table('menu_items')->where('key', 'closing')->value('id');

        if (!$menuId) {
            $menuId = DB::table('menu_items')->insertGetId([
                'key'        => 'closing',
                'label'      => 'Closing',
                'icon'       => 'fas fa-calendar-check',
                'route'      => '/closing',
                'section'    => 'finance',
                // Setelah Cash Management (25) & Cash Receipt (26): penutupan
                // periode adalah langkah terakhir perjalanan uang.
                'sort_order' => 27,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (self::ROLES as $roleName) {
            $roleId = DB::table('roles')->whereRaw('LOWER(name) = ?', [$roleName])->value('id');

            if (!$roleId) {
                continue;
            }

            $sudahAda = DB::table('role_menu_items')
                ->where('role_id', $roleId)
                ->where('menu_item_id', $menuId)
                ->exists();

            if ($sudahAda) {
                continue;
            }

            DB::table('role_menu_items')->insert([
                'role_id'      => $roleId,
                'menu_item_id' => $menuId,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        $menuId = DB::table('menu_items')->where('key', 'closing')->value('id');

        if ($menuId) {
            DB::table('role_menu_items')->where('menu_item_id', $menuId)->delete();
            DB::table('menu_items')->where('id', $menuId)->delete();
        }
    }
};
