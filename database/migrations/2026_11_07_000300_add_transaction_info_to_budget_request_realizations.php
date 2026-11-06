<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tanggal transaksi & vendor per baris settlement. Additive dan nullable:
 * tidak ikut perhitungan uang apa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('budget_request_realizations') && ! Schema::hasColumn('budget_request_realizations', 'transaction_date')) {
            Schema::table('budget_request_realizations', function (Blueprint $table) {
                $table->date('transaction_date')->nullable()->after('notes');
                $table->string('vendor_name', 150)->nullable()->after('transaction_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('budget_request_realizations', 'transaction_date')) {
            Schema::table('budget_request_realizations', function (Blueprint $table) {
                $table->dropColumn(['transaction_date', 'vendor_name']);
            });
        }
    }
};
