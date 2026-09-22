<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| M-01 — daftarkan menu 'cash-management' di section Finance.
|
| Route-nya dijaga middleware role (superadmin, finance, treasury, approver),
| bukan middleware menu, jadi menu item ini murni untuk memunculkan tautannya
| di sidebar. Role yang boleh melihat tautannya tetap dicentang manual lewat
| halaman Role Management — superadmin otomatis lolos.
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('menu_items')->where('key', 'cash-management')->exists()) {
            return;
        }

        DB::table('menu_items')->insert([
            'key'        => 'cash-management',
            'label'      => 'Cash Management',
            'icon'       => 'fas fa-wallet',
            'route'      => '/cash-management',
            'section'    => 'finance',
            // Tepat setelah Daily Transaction (24), karena isinya justru
            // menyatukan Treasury dan Cash Receipt.
            'sort_order' => 25,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $menuId = DB::table('menu_items')->where('key', 'cash-management')->value('id');

        if ($menuId) {
            DB::table('role_menu_items')->where('menu_item_id', $menuId)->delete();
            DB::table('menu_items')->where('id', $menuId)->delete();
        }
    }
};
