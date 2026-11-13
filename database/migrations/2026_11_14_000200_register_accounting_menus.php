<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menu Accounting Fase 1 (2026-11-14): chart of accounts, account mapping,
 * accounting period. Aditif, aman diulang, TIDAK memberi akses ke role mana
 * pun — hak diberikan lewat Menu Access.
 */
return new class extends Migration
{
    private const MENUS = [
        ['accounting-coa', 'Chart of Accounts', 'fas fa-sitemap', '/accounting/coa', 40],
        ['accounting-mapping', 'Account Mapping', 'fas fa-link', '/accounting/mapping', 41],
        ['accounting-period', 'Accounting Period', 'fas fa-calendar-alt', '/accounting/period', 42],
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
