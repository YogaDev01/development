<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menu Cash & Bank Fase 2 (2026-11-13): internal transfer, adjustment, viewer
 * transaksi. Aditif, aman diulang, TIDAK memberi akses ke role mana pun.
 */
return new class extends Migration
{
    private const MENUS = [
        'cash-transactions' => ['Cash Transactions', 'fas fa-list-alt', '/cash-transactions', 28],
        'cash-transfers'    => ['Internal Transfer', 'fas fa-random', '/cash-transfers', 29],
        'cash-adjustments'  => ['Cash Adjustment', 'fas fa-sliders-h', '/cash-adjustments', 30],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }

        foreach (self::MENUS as $key => [$label, $icon, $route, $sort]) {
            if (DB::table('menu_items')->where('key', $key)->exists()) {
                continue;
            }
            DB::table('menu_items')->insert([
                'key' => $key, 'label' => $label, 'icon' => $icon, 'route' => $route,
                'section' => 'finance', 'sort_order' => $sort,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }
        foreach (array_keys(self::MENUS) as $key) {
            $id = DB::table('menu_items')->where('key', $key)->value('id');
            if ($id) {
                DB::table('role_menu_items')->where('menu_item_id', $id)->delete();
                DB::table('menu_items')->where('id', $id)->delete();
            }
        }
    }
};
