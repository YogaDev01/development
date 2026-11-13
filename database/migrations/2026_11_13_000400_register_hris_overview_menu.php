<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Daftarkan menu 'hris-overview' (Todo List, Dashboard, Summary HRIS) — 2026-11-13.
 * Aditif & aman diulang. Pemberian ke role lewat modal Menu Access.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('menu_items') && ! DB::table('menu_items')->where('key', 'hris-overview')->exists()) {
            DB::table('menu_items')->insert([
                'key' => 'hris-overview', 'label' => 'HRIS Overview', 'icon' => 'fas fa-tachometer-alt',
                'route' => '/hris/dashboard', 'section' => 'hr', 'sort_order' => 60,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }
        $id = DB::table('menu_items')->where('key', 'hris-overview')->value('id');
        if ($id) {
            DB::table('role_menu_items')->where('menu_item_id', $id)->delete();
            DB::table('menu_items')->where('id', $id)->delete();
        }
    }
};
