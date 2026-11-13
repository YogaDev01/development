<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Daftarkan menu 'operations-overview' (Todo List, Dashboard, Summary Operations) — 2026-11-13.
 * Aditif & aman diulang. Pemberian ke role lewat modal Menu Access.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('menu_items') && ! DB::table('menu_items')->where('key', 'operations-overview')->exists()) {
            DB::table('menu_items')->insert([
                'key' => 'operations-overview', 'label' => 'Operations Overview', 'icon' => 'fas fa-tachometer-alt',
                'route' => '/operations/dashboard', 'section' => 'operation', 'sort_order' => 60,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }
        $id = DB::table('menu_items')->where('key', 'operations-overview')->value('id');
        if ($id) {
            DB::table('role_menu_items')->where('menu_item_id', $id)->delete();
            DB::table('menu_items')->where('id', $id)->delete();
        }
    }
};
