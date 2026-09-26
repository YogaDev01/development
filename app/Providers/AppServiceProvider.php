<?php

namespace App\Providers;

use App\Support\AssetVersion;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
        | @aset('css/tesys-ui.css') -> /css/tesys-ui.css?v=<versi>
        |
        | Menggantikan pola lama di layout:
        |     asset('css/x.css') . '?v=' . filemtime(public_path('css/x.css'))
        | yang diulang 25 kali, jadi 25 stat() filesystem per permintaan - dan
        | layar putih di SELURUH halaman kalau satu berkas saja luput saat
        | deploy (filemtime pada berkas yang tidak ada melempar exception).
        |
        | Lihat App\Support\AssetVersion untuk asal versinya.
        */
        Blade::directive('aset', function (string $ekspresi) {
            return "<?php echo e(\\App\\Support\\AssetVersion::url({$ekspresi})); ?>";
        });

        /*
        | @rupiah($nilai) -> 1.000.000.000
        |
        | Formatnya BENAR dan konsisten sejak awal - number_format($x, 0, ',', '.')
        | dipakai 238 kali di seluruh Blade dan semuanya menghasilkan bentuk
        | yang sama. Yang jadi masalah bukan hasilnya, melainkan bahwa aturan
        | tampilannya tersebar di 238 tempat: menambah prefiks "Rp", atau
        | menyingkat nominal besar jadi "1,2 M" di kartu KPI, berarti menyunting
        | 238 lokasi dan melewatkan beberapa di antaranya.
        |
        | Direktif ini memberi satu tempat untuk mengubahnya. Penggantian
        | pemakaian lama dilakukan BERTAHAP per modul, bukan diborong: konversi
        | massal 238 baris adalah perubahan tanpa manfaat fungsional yang akan
        | mengubur diff perubahan lain di riwayat git.
        |
        | Nilai null/'' menjadi 0, bukan string kosong - kolom nominal yang
        | kosong lebih membingungkan daripada nol.
        */
        Blade::directive('rupiah', function (string $ekspresi) {
            return "<?php echo e(number_format((float) ({$ekspresi} ?: 0), 0, ',', '.')); ?>";
        });
    }
}
