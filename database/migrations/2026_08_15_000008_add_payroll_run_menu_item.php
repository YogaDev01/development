<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Daftarkan menu 'payroll-run'. Section 'payroll' (terpisah dari master).
    | Route memakai middleware menu:payroll-run.
    |--------------------------------------------------------------------------
    */

    public function up(): void
    {
        $exists = DB::table('menu_items')->where('key', 'payroll-run')->exists();

        if (!$exists) {
            DB::table('menu_items')->insert([
                'key'        => 'payroll-run',
                'label'      => 'Payroll Run',
                'icon'       => 'fas fa-file-invoice-dollar',
                'route'      => '/payroll-run',
                'section'    => 'payroll',
                'sort_order' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $menuId = DB::table('menu_items')->where('key', 'payroll-run')->value('id');
        if ($menuId) {
            DB::table('role_menu_items')->where('menu_item_id', $menuId)->delete();
            DB::table('menu_items')->where('id', $menuId)->delete();
        }
    }
};