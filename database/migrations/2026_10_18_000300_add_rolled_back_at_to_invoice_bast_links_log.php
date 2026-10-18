<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catat kapan sebuah tautan invoice-BAST di-rollback oleh `invoice:link-bast --rollback`.
 * Migrasi terpisah (bukan mengubah 000200) karena 000200 sudah dijalankan di lingkungan
 * lokal; migrasi baru aman untuk lingkungan mana pun. Kolom nullable, murni additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoice_bast_links_log')) {
            throw new \RuntimeException(
                'Tabel invoice_bast_links_log tidak ada. Jalankan migrasi 2026_10_18_000200_add_bast_link_to_invoices dulu.'
            );
        }

        if (!Schema::hasColumn('invoice_bast_links_log', 'rolled_back_at')) {
            Schema::table('invoice_bast_links_log', function (Blueprint $table) {
                $table->timestamp('rolled_back_at')->nullable()->after('created_at');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('invoice_bast_links_log', 'rolled_back_at')) {
            return;
        }

        if (DB::table('invoice_bast_links_log')->whereNotNull('rolled_back_at')->exists()) {
            throw new \RuntimeException(
                'Rollback migrasi dibatalkan: ada catatan rollback (rolled_back_at) yang akan hilang. Arsipkan dulu oleh manusia.'
            );
        }

        Schema::table('invoice_bast_links_log', function (Blueprint $table) {
            $table->dropColumn('rolled_back_at');
        });
    }
};
