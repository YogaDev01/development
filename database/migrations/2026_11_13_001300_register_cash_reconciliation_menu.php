<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menu 'cash-reconciliation' (Cash & Bank Fase 3, 2026-11-13). Aditif, aman
 * diulang, TIDAK memberi akses ke role mana pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('menu_items') && ! DB::table('menu_items')->where('key', 'cash-reconciliation')->exists()) {
            DB::table('menu_items')->insert([
                'key' => 'cash-reconciliation', 'label' => 'Reconciliation', 'icon' => 'fas fa-balance-scale-left',
                'route' => '/cash-reconciliation', 'section' => 'finance', 'sort_order' => 31,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }
        $id = DB::table('menu_items')->where('key', 'cash-reconciliation')->value('id');
        if ($id) {
            DB::table('role_menu_items')->where('menu_item_id', $id)->delete();
            DB::table('menu_items')->where('id', $id)->delete();
        }
    }
};
