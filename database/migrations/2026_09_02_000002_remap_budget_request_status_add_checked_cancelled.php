<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: superset ENUM dulu — tambah CHECKED & CANCELLED, belum hapus
        // apapun. QUEUED/REALIZED sengaja belum disentuh (di-rename di migration
        // fase berikutnya, bareng kode yang memakainya).
        DB::statement("
            ALTER TABLE budget_request
            MODIFY COLUMN status ENUM(
                'DRAFT',
                'SUBMITTED',
                'APPROVED',
                'VERIFIED',
                'QUEUED',
                'PAID',
                'REALIZED',
                'CLOSED',
                'REJECTED',
                'CHECKED',
                'CANCELLED'
            ) DEFAULT 'DRAFT'
        ");

        // Step 2: remap data. APPROVED lama (pasca-Head, sebelum Finance verify)
        // jadi CHECKED. Harus jalan SEBELUM kode yang menulis APPROVED dengan
        // makna baru (pasca-CEO individual approve) di-deploy.
        DB::statement("UPDATE budget_request SET status = 'CHECKED' WHERE status = 'APPROVED'");

        // Step 3: ENUM final fase ini. APPROVED dipakai ulang dengan makna baru
        // (pasca-CEO individual approve). QUEUED/REALIZED tetap apa adanya.
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

    public function down(): void
    {
        // CATATAN: rollback ini LOSSY untuk CR manapun yang sudah mencapai status
        // APPROVED dengan makna BARU (pasca-CEO) setelah migration ini jalan —
        // baris tsb ikut mundur seolah APPROVED makna LAMA (pasca-Head) karena
        // stringnya sama. Jangan andalkan down() ini di production kalau data
        // baru sudah terbentuk di status APPROVED/CHECKED.
        DB::statement("
            ALTER TABLE budget_request
            MODIFY COLUMN status ENUM(
                'DRAFT',
                'SUBMITTED',
                'APPROVED',
                'VERIFIED',
                'QUEUED',
                'PAID',
                'REALIZED',
                'CLOSED',
                'REJECTED',
                'CHECKED',
                'CANCELLED'
            ) DEFAULT 'DRAFT'
        ");

        DB::statement("UPDATE budget_request SET status = 'APPROVED' WHERE status = 'CHECKED'");

        DB::statement("
            ALTER TABLE budget_request
            MODIFY COLUMN status ENUM(
                'DRAFT',
                'SUBMITTED',
                'APPROVED',
                'VERIFIED',
                'QUEUED',
                'PAID',
                'REALIZED',
                'CLOSED',
                'REJECTED'
            ) DEFAULT 'DRAFT'
        ");
    }
};
