<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menu 'accounting-journal' (Accounting Fase 2, 2026-11-14). Aditif, aman
 * diulang, TIDAK memberi akses ke role mana pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('menu_items') && ! DB::table('menu_items')->where('key', 'accounting-journal')->exists()) {
            DB::table('menu_items')->insert([
                'key' => 'accounting-journal', 'label' => 'Journal Entries', 'icon' => 'fas fa-book-open',
                'route' => '/accounting/journal', 'section' => 'finance', 'sort_order' => 39,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }
        $id = DB::table('menu_items')->where('key', 'accounting-journal')->value('id');
        if ($id) {
            DB::table('role_menu_items')->where('menu_item_id', $id)->delete();
            DB::table('menu_items')->where('id', $id)->delete();
        }
    }
};
