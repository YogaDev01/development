<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Forecast — forecast_amount & actual_amount jadi BERTANDA (signed).
|
| Kedua kolom dibuat unsigned di 2026_09_15_000010, dan waktu itu memang
| benar: isinya SUM polos seluruh detail, jadi tidak pernah negatif.
|
| 2026_09_20_000013 menambahkan arah IN/OUT di detail, dan rumus headernya
| ikut berubah jadi selisih (Masuk - Keluar). Tipe kolomnya tidak ikut
| berubah. Sejak itu setiap forecast yang memuat baris OUT mencoba menulis
| angka negatif ke kolom unsigned dan ditolak MySQL:
|
|     SQLSTATE[22003]: 1264 Out of range value for column 'forecast_amount'
|
| Rencana yang isinya pengeluaran memang bernilai bersih negatif — itu
| jawaban yang benar, bukan kesalahan input — jadi kolomnya yang mengikuti
| rumus, bukan rumusnya yang dipaksa positif.
|
| `forecast_details.amount` dan `.actual_amount` sengaja DIBIARKAN unsigned:
| nilai per baris memang selalu positif, arahnya diwakili kolom `type`.
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forecasts', function (Blueprint $table) {
            $table->bigInteger('forecast_amount')->default(0)->change();
            $table->bigInteger('actual_amount')->default(0)->change();
        });
    }

    public function down(): void
    {
        // Nilai negatif — justru yang membuat migrasi ini ada — tidak muat di
        // kolom unsigned. Dinolkan dulu supaya rollback tidak gagal separuh
        // jalan; angkanya selalu bisa dihitung ulang dari detail lewat
        // Forecast::syncAmounts().
        DB::table('forecasts')->where('forecast_amount', '<', 0)->update(['forecast_amount' => 0]);
        DB::table('forecasts')->where('actual_amount', '<', 0)->update(['actual_amount' => 0]);

        Schema::table('forecasts', function (Blueprint $table) {
            $table->unsignedBigInteger('forecast_amount')->default(0)->change();
            $table->unsignedBigInteger('actual_amount')->default(0)->change();
        });
    }
};
