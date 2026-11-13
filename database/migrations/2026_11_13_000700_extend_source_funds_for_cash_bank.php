<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash & Bank — Fase 1. `source_funds` dipakai sebagai master rekening kas/bank
 * (TIDAK dibuat tabel rekening kedua). Hanya kolom ADITIF & nullable, sehingga
 * baris existing dan seluruh FK Treasury/Cash Receipt/Forecast tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('source_funds')) {
            return;
        }

        Schema::table('source_funds', function (Blueprint $table) {
            if (! Schema::hasColumn('source_funds', 'code')) {
                $table->string('code', 30)->nullable()->unique()->after('id');
            }
            // BANK | PETTY_CASH | CASH_ON_HAND | OTHER_CASH_EQUIVALENT
            if (! Schema::hasColumn('source_funds', 'account_type')) {
                $table->string('account_type', 30)->default('BANK');
            }
            if (! Schema::hasColumn('source_funds', 'account_holder')) {
                $table->string('account_holder')->nullable();
            }
            if (! Schema::hasColumn('source_funds', 'branch')) {
                $table->string('branch')->nullable();
            }
            if (! Schema::hasColumn('source_funds', 'currency')) {
                $table->string('currency', 3)->default('IDR');
            }
            if (! Schema::hasColumn('source_funds', 'description')) {
                $table->text('description')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('source_funds')) {
            return;
        }

        Schema::table('source_funds', function (Blueprint $table) {
            foreach (['code', 'account_type', 'account_holder', 'branch', 'currency', 'description'] as $col) {
                if (Schema::hasColumn('source_funds', $col)) {
                    if ($col === 'code') {
                        $table->dropUnique(['code']);
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};
