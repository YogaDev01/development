<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Actual Amount semula diinput satu nilai agregat di header forecasts.
    | Diubah jadi per-baris forecast_details (lebih akurat per item), forecasts.
    | actual_amount sekarang SUM(forecast_details.actual_amount) — sama seperti
    | forecast_amount. Nilai actual_amount header lama tidak bisa dipetakan
    | balik ke baris manapun (tidak ada breakdown-nya), jadi di-reset ke 0
    | supaya angka tidak nyasar/ganda; Finance input ulang per item.
    |--------------------------------------------------------------------------
    */

    public function up(): void
    {
        Schema::table('forecast_details', function (Blueprint $table) {
            $table->unsignedBigInteger('actual_amount')->default(0)->after('amount');
        });

        DB::table('forecasts')->update(['actual_amount' => 0]);
    }

    public function down(): void
    {
        Schema::table('forecast_details', function (Blueprint $table) {
            $table->dropColumn('actual_amount');
        });
    }
};
