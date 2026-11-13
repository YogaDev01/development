<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Accounting Fase 4 (2026-11-14) — klasifikasi laporan keuangan per akun COA.
 * Aditif: dua kolom nullable. NULL = memakai bawaan menurut tipe akun
 * (lihat CoaAccount::effectiveSection / effectiveCashActivity), jadi akun yang
 * sudah ada tetap valid tanpa diisi ulang.
 *
 * statement_section  : penempatan di Laba Rugi / Neraca / Perubahan Ekuitas.
 * cash_flow_activity : OPERATING | INVESTING | FINANCING untuk Arus Kas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coa_accounts') && ! Schema::hasColumn('coa_accounts', 'statement_section')) {
            Schema::table('coa_accounts', function (Blueprint $table) {
                $table->string('statement_section', 30)->nullable()->after('category');
                $table->string('cash_flow_activity', 12)->nullable()->after('statement_section');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('coa_accounts', 'statement_section')) {
            Schema::table('coa_accounts', function (Blueprint $table) {
                $table->dropColumn(['statement_section', 'cash_flow_activity']);
            });
        }
    }
};
