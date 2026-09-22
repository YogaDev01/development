<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Daftarkan menu 'employee' di section Master.
    | Route memakai middleware menu:employee, jadi menu item ini wajib ada.
    |--------------------------------------------------------------------------
    */

    public function up(): void
    {
        $exists = DB::table('menu_items')->where('key', 'employee')->exists();

        if (!$exists) {
            DB::table('menu_items')->insert([
                'key'        => 'employee',
                'label'      => 'Karyawan',
                'icon'       => 'fas fa-users',
                'route'      => '/employee',
                'section'    => 'master',
                'sort_order' => 70,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $menuId = DB::table('menu_items')->where('key', 'employee')->value('id');

        if ($menuId) {
            DB::table('role_menu_items')->where('menu_item_id', $menuId)->delete();
            DB::table('menu_items')->where('id', $menuId)->delete();
        }
    }
};