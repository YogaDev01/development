<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Alur Settlement Cash Advance ditambah 2 langkah approval baru antara
    | REALIZED (submit settlement) dan CLOSED: Head check lalu Finance verify,
    | baru CEO close. Ditulis sebagai status baru (bukan reuse CHECKED/VERIFIED
    | yang sudah punya arti lain di tahap pra-approval) supaya tidak ambigu.
    |
    | Label tampilan tetap "Settle -> Checked -> Verified -> Closed" (lihat
    | BudgetRequest::statusLabelFor()) — REALIZED sendiri sudah lama dilabeli
    | "Settle", jadi hanya perlu 2 nilai baru di sini. Tidak ada data existing
    | yang perlu dipetakan ulang karena kedua status ini baru.
    |--------------------------------------------------------------------------
    */

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return; // SQLite: kolom status TEXT, tidak menegakkan ENUM.
        }

        DB::statement("
            ALTER TABLE budget_request
            MODIFY COLUMN status ENUM(
                'DRAFT',
                'SUBMITTED',
                'CHECKED',
                'VERIFIED',
                'APPROVED',
                'QUEUED',
                'PAID',
                'REALIZED',
                'SETTLE_CHECKED',
                'SETTLE_VERIFIED',
                'CLOSED',
                'REJECTED',
                'CANCELLED'
            ) DEFAULT 'DRAFT'
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Turunkan CR yang sudah di tahap settlement baru balik ke REALIZED
        // supaya tidak ada baris dengan status yang bakal hilang dari ENUM.
        DB::statement("UPDATE budget_request SET status = 'REALIZED' WHERE status IN ('SETTLE_CHECKED', 'SETTLE_VERIFIED')");

        DB::statement("
            ALTER TABLE budget_request
            MODIFY COLUMN status ENUM(
                'DRAFT',
                'SUBMITTED',
                'CHECKED',
                'VERIFIED',
                'APPROVED',
                'QUEUED',
                'PAID',
                'REALIZED',
                'CLOSED',
                'REJECTED',
                'CANCELLED'
            ) DEFAULT 'DRAFT'
        ");
    }
};
