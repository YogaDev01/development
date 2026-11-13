<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SLA site per bulan (persentase availability, 0–100). Satu baris per
 * site + tahun + bulan. Aditif; menu 'site-sla' didaftarkan tanpa memberi
 * akses ke role mana pun (diatur lewat modal Role).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_slas')) {
            Schema::create('site_slas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('site_id')->constrained('sites')->restrictOnDelete();
                $table->unsignedSmallInteger('year');
                $table->unsignedTinyInteger('month');
                $table->decimal('sla_percent', 5, 2);
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['site_id', 'year', 'month']);
                $table->index(['year', 'month']);
            });
        }

        if (Schema::hasTable('menu_items') && ! DB::table('menu_items')->where('key', 'site-sla')->exists()) {
            DB::table('menu_items')->insert([
                'key' => 'site-sla', 'label' => 'SLA Site', 'icon' => 'fas fa-signal', 'route' => '/site-sla',
                'section' => 'operation', 'sort_order' => 35,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('menu_items')) {
            $id = DB::table('menu_items')->where('key', 'site-sla')->value('id');
            if ($id) {
                DB::table('role_menu_items')->where('menu_item_id', $id)->delete();
                DB::table('menu_items')->where('id', $id)->delete();
            }
        }
        Schema::dropIfExists('site_slas');
    }
};
