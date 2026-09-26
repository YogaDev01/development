<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Cabang Expense Claim — settlement yang MELEBIHI nilai pengajuan.
    |
    | Sebelumnya kelebihan itu hanya melahirkan baris Second Payment sementara
    | CR-nya langsung boleh di-Close. Sekarang kelebihannya harus disetujui dua
    | lapis dulu: Settle Approve (Finance Manager) lalu 2nd Approve (Approver),
    | baru masuk batch Treasury — CR-nya CLOSED otomatis begitu batch itu
    | ditandai PAID.
    |
    | Dua status baru, tidak ada data lama yang perlu dipetakan ulang.
    |--------------------------------------------------------------------------
    */

    private const STATUSES_BARU = [
        'DRAFT', 'SUBMITTED', 'CHECKED', 'VERIFIED', 'APPROVED', 'QUEUED',
        'PAID', 'REALIZED', 'SETTLE_CHECKED', 'SETTLE_VERIFIED',
        'SETTLE_APPROVED', 'SECOND_APPROVED',
        'CLOSED', 'REJECTED', 'CANCELLED',
    ];

    private const STATUSES_LAMA = [
        'DRAFT', 'SUBMITTED', 'CHECKED', 'VERIFIED', 'APPROVED', 'QUEUED',
        'PAID', 'REALIZED', 'SETTLE_CHECKED', 'SETTLE_VERIFIED',
        'CLOSED', 'REJECTED', 'CANCELLED',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return; // SQLite: kolom status TEXT, tidak menegakkan ENUM.
        }

        $this->ubahEnum(self::STATUSES_BARU);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // CR yang sedang berada di dua tahap baru dikembalikan ke
        // SETTLE_VERIFIED supaya tidak ada baris dengan status yang hilang
        // dari ENUM — tahap sebelum cabang ini dimulai.
        DB::statement("
            UPDATE budget_request
            SET status = 'SETTLE_VERIFIED'
            WHERE status IN ('SETTLE_APPROVED', 'SECOND_APPROVED')
        ");

        $this->ubahEnum(self::STATUSES_LAMA);
    }

    private function ubahEnum(array $statuses): void
    {
        $daftar = collect($statuses)->map(fn ($s) => "'" . $s . "'")->implode(', ');

        DB::statement("
            ALTER TABLE budget_request
            MODIFY COLUMN status ENUM($daftar) DEFAULT 'DRAFT'
        ");
    }
};
