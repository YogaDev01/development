<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_transactions', function (Blueprint $table) {
            // CASH_OUT = batch berbasis Cost Request (perilaku lama, default).
            // TOP_UP   = transfer antar rekening perusahaan sendiri (Main Account -> operasional), tanpa CR.
            $table->string('type', 20)->default('CASH_OUT')->after('status');

            // Hanya dipakai saat type = TOP_UP. source_fund_id tetap dipakai sebagai
            // rekening ASAL (harus Main Account untuk TOP_UP, atau rekening operasional untuk CASH_OUT).
            $table->foreignId('to_source_fund_id')
                ->nullable()
                ->after('source_fund_id')
                ->constrained('source_funds')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('to_source_fund_id');
            $table->dropColumn('type');
        });
    }
};
