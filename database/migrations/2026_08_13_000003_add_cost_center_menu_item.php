<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Daftarkan menu 'cost-center' di section Master.
    |
    | Akses route memakai middleware menu:cost-center, jadi menu item ini WAJIB
    | ada. Superadmin otomatis bypass; role lain perlu dicentang manual lewat
    | halaman Role Management.
    |--------------------------------------------------------------------------
    */

    public function up(): void
    {
        $exists = DB::table('menu_items')->where('key', 'cost-center')->exists();

        if (!$exists) {
            DB::table('menu_items')->insert([
                'key'        => 'cost-center',
                'label'      => 'Cost Center',
                'icon'       => 'fas fa-sitemap',
                'route'      => '/cost-center',
                'section'    => 'master',
                'sort_order' => 60,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $menuId = DB::table('menu_items')->where('key', 'cost-center')->value('id');

        if ($menuId) {
            DB::table('role_menu_items')->where('menu_item_id', $menuId)->delete();
            DB::table('menu_items')->where('id', $menuId)->delete();
        }
    }
};