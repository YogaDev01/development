<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Daftarkan menu Cash & Bank: 'cash-overview' dan 'bank-accounts' (2026-11-13).
 * Aditif & aman diulang. TIDAK memberi akses ke role mana pun: pemberian lewat
 * modal Menu Access (akses Treasury/Cash Receipt tidak otomatis berlaku).
 */
return new class extends Migration
{
    private const MENUS = [
        'cash-overview' => ['Cash Overview', 'fas fa-university', '/cash-overview', 24],
        'bank-accounts' => ['Bank Accounts', 'fas fa-landmark', '/bank-accounts', 27],
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
