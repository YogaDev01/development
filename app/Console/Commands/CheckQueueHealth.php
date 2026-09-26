<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Apakah antrean benar-benar diproses?
 *
 * QUEUE_CONNECTION=database dan AppNotification memakai ShouldQueue, artinya
 * SELURUH notifikasi aplikasi ini bergantung pada satu proses di luar aplikasi:
 * "php artisan queue:work". Kalau proses itu mati, tidak ada yang rusak secara
 * kasat mata - halaman tetap terbuka, approval tetap jalan - hanya notifikasinya
 * tidak pernah sampai, dan job-nya menumpuk diam-diam di tabel `jobs`.
 *
 * Itu bukan kemungkinan teoretis: 21-23 Sep 2026 worker berhenti dan 1.900+ job
 * menumpuk tanpa ada yang menyadarinya, sampai AppNotification perlu logika
 * "notifikasi basi" (BATAS_BASI_DETIK) untuk menahan pesan lama agar tidak
 * banjir saat antreannya akhirnya diproses.
 *
 * Perintah ini memberi kegagalan itu SUARA. Dijalankan terjadwal (lihat
 * routes/console.php), ia mencatat peringatan begitu antrean menumpuk melewati
 * batas wajar atau ada job yang gagal - jadi yang ketahuan adalah antreannya,
 * bukan keluhan "kok saya tidak dapat notifikasi".
 *
 * Exit code 1 saat tidak sehat, supaya bisa dipasang langsung ke pemantauan
 * luar (cron yang mengirim surel saat gagal, uptime check, dsb).
 */
class CheckQueueHealth extends Command
{
    protected $signature = 'queue:health
                            {--max-pending=200 : Jumlah job menunggu yang masih dianggap wajar}';

    protected $description = 'Periksa tumpukan antrean & job gagal; beri peringatan kalau worker tampaknya tidak berjalan';

    public function handle(): int
    {
        // Driver selain database tidak menyimpan antreannya di tabel ini;
        // memeriksanya hanya akan melaporkan nol yang menyesatkan.
        if (config('queue.default') !== 'database') {
            $this->info('QUEUE_CONNECTION bukan "database" — pemeriksaan ini dilewati.');

            return self::SUCCESS;
        }

        if (! Schema::hasTable('jobs')) {
            $this->error('Tabel `jobs` tidak ada. Jalankan migrasi lebih dulu.');

            return self::FAILURE;
        }

        $maxPending = max(1, (int) $this->option('max-pending'));

        $pending = DB::table('jobs')->count();
        $failed  = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;

        /*
        | Umur job TERTUA lebih menunjukkan daripada jumlahnya. Seratus job yang
        | semuanya baru berarti worker sedang sibuk - itu sehat. Satu job
        | berumur dua jam berarti worker tidak mengambil apa pun - itu mati.
        */
        $tertuaTs = DB::table('jobs')->min('available_at');

        /*
        | Urutan argumen penting: $waktuLama->diffInMinutes(now()) - bukan
        | sebaliknya. Carbon 3 mengembalikan nilai BERTANDA (dan berupa float),
        | jadi now()->diffInMinutes($waktuLama) menghasilkan angka NEGATIF untuk
        | waktu yang sudah lewat, dan pemeriksaan "> 15" di bawah tidak akan
        | pernah aktif. Dibulatkan ke int supaya yang tercetak menit utuh.
        */
        $umurTertua = $tertuaTs
            ? (int) round(now()->setTimestamp((int) $tertuaTs)->diffInMinutes(now()))
            : 0;

        $this->line('Job menunggu   : ' . number_format($pending, 0, ',', '.'));
        $this->line('Job gagal      : ' . number_format($failed, 0, ',', '.'));
        $this->line('Job tertua     : ' . $umurTertua . ' menit');

        $masalah = [];

        if ($pending > $maxPending) {
            $masalah[] = "Antrean menumpuk: {$pending} job menunggu (batas {$maxPending}).";
        }

        // 15 menit: jauh di atas waktu pemrosesan normal (notifikasi selesai
        // dalam hitungan detik), cukup longgar untuk lonjakan sesaat.
        if ($umurTertua > 15) {
            $masalah[] = "Job tertua sudah {$umurTertua} menit belum diproses — worker mungkin tidak berjalan.";
        }

        if ($failed > 0) {
            $masalah[] = "Ada {$failed} job gagal di tabel failed_jobs.";
        }

        if (empty($masalah)) {
            $this->info('Antrean sehat.');

            return self::SUCCESS;
        }

        foreach ($masalah as $pesan) {
            $this->error($pesan);
        }

        Log::warning('Pemeriksaan kesehatan antrean gagal', [
            'pending'        => $pending,
            'failed'         => $failed,
            'umur_tertua_mnt' => $umurTertua,
            'masalah'        => $masalah,
        ]);

        return self::FAILURE;
    }
}
