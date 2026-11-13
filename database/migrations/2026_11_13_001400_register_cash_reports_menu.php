<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menu 'cash-reports' (Cash & Bank Fase 4, 2026-11-13). Aditif, aman diulang,
 * TIDAK memberi akses ke role mana pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('menu_items') && ! DB::table('menu_items')->where('key', 'cash-reports')->exists()) {
            DB::table('menu_items')->insert([
                'key' => 'cash-reports', 'label' => 'Cash Reports', 'icon' => 'fas fa-file-alt',
                'route' => '/cash-reports', 'section' => 'finance', 'sort_order' => 32,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }
        $id = DB::table('menu_items')->where('key', 'cash-reports')->value('id');
        if ($id) {
            DB::table('role_menu_items')->where('menu_item_id', $id)->delete();
            DB::table('menu_items')->where('id', $id)->delete();
        }
    }
};
