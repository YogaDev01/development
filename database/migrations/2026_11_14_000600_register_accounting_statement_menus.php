<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menu laporan keuangan Accounting (Fase 4, 2026-11-14). Aditif, aman diulang,
 * TIDAK memberi akses ke role mana pun.
 */
return new class extends Migration
{
    private const MENUS = [
        ['accounting-income-statement', 'Income Statement', 'fas fa-chart-line', '/accounting/statements/income', 45],
        ['accounting-balance-sheet', 'Balance Sheet', 'fas fa-balance-scale-right', '/accounting/statements/balance', 46],
        ['accounting-cash-flow', 'Cash Flow Statement', 'fas fa-water', '/accounting/statements/cash', 47],
        ['accounting-equity', 'Changes in Equity', 'fas fa-layer-group', '/accounting/statements/equity', 48],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }
        foreach (self::MENUS as [$key, $label, $icon, $route, $sort]) {
            if (! DB::table('menu_items')->where('key', $key)->exists()) {
                DB::table('menu_items')->insert([
                    'key' => $key, 'label' => $label, 'icon' => $icon, 'route' => $route,
                    'section' => 'finance', 'sort_order' => $sort,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }
        foreach (self::MENUS as [$key]) {
            $id = DB::table('menu_items')->where('key', $key)->value('id');
            if ($id) {
                DB::table('role_menu_items')->where('menu_item_id', $id)->delete();
                DB::table('menu_items')->where('id', $id)->delete();
            }
        }
    }
};
