<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Accounting Fase 5 (2026-11-14): jurnal manual, akrual, jurnal pembalik.
 * Aditif: satu kolom nullable di journals + menu 'accounting-audit'
 * (Journal Audit Trail). TIDAK memberi akses ke role mana pun.
 *
 * auto_reverse_date: khusus jurnal ACCRUAL — saat diposting dibuatkan Draft
 * pembalik bertanggal itu (tetap harus di-submit & diposting seperti biasa).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('journals') && ! Schema::hasColumn('journals', 'auto_reverse_date')) {
            Schema::table('journals', function (Blueprint $table) {
                $table->date('auto_reverse_date')->nullable()->after('journal_date');
            });
        }

        if (Schema::hasTable('menu_items') && ! DB::table('menu_items')->where('key', 'accounting-audit')->exists()) {
            DB::table('menu_items')->insert([
                'key' => 'accounting-audit', 'label' => 'Journal Audit Trail', 'icon' => 'fas fa-history',
                'route' => '/accounting/audit', 'section' => 'finance', 'sort_order' => 49,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('journals', 'auto_reverse_date')) {
            Schema::table('journals', function (Blueprint $table) {
                $table->dropColumn('auto_reverse_date');
            });
        }
        if (Schema::hasTable('menu_items')) {
            $id = DB::table('menu_items')->where('key', 'accounting-audit')->value('id');
            if ($id) {
                DB::table('role_menu_items')->where('menu_item_id', $id)->delete();
                DB::table('menu_items')->where('id', $id)->delete();
            }
        }
    }
};
