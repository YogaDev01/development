<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
        | DINONAKTIFKAN — jangan pernah dijalankan lagi.
        |
        | Versi asli migrasi ini menjalankan:
        |
        |     UPDATE budget_request SET status = 'APPROVED'
        |     WHERE status NOT IN (...daftar lama...)
        |
        | Artinya: setiap Cost Request yang statusnya tidak dikenali oleh
        | daftar saat itu langsung DISETUJUI secara massal. Waktu migrasi ini
        | ditulis, daftar tersebut memang mencakup seluruh status yang ada.
        | Sekarang tidak lagi: enum-nya sudah berkembang (CHECKED, VERIFIED,
        | QUEUED, SETTLE_CHECKED, SETTLE_VERIFIED, CANCELLED). Kalau migrasi
        | ini dijalankan ulang pada basis data berisi — memulihkan cadangan,
        | menyiapkan lingkungan baru, atau me-reset lalu migrate ulang — semua
        | CR pada tahap-tahap itu akan berubah menjadi APPROVED tanpa satu pun
        | approval nyata, dan tanpa jejak di log.
        |
        | Perubahan enum yang benar sudah dilakukan migrasi setelahnya, jadi
        | bagian ini tidak punya tugas tersisa. Berkasnya dipertahankan (bukan
        | dihapus) supaya riwayat migrasi tetap utuh untuk basis data yang
        | terlanjur menjalankannya.
        */
        return;

        // @phpstan-ignore-next-line - sengaja tidak terjangkau, lihat catatan di atas.
        DB::statement("
            ALTER TABLE budget_request
            MODIFY COLUMN status ENUM(
                'DRAFT',
                'PENDING_MANAGER',
                'PENDING_FINANCE',
                'APPROVED',
                'PENDING_PAYMENT',
                'PAID',
                'REALIZED',
                'CLOSED',
                'REJECTED'
            ) DEFAULT 'DRAFT'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE budget_request
            MODIFY COLUMN status VARCHAR(50) DEFAULT 'DRAFT'
        ");
    }
};