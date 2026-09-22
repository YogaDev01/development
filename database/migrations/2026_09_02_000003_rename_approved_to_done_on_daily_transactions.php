<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: superset ENUM dulu — tambah DONE, belum hapus APPROVED.
        DB::statement("
            ALTER TABLE daily_transactions
            MODIFY COLUMN status ENUM(
                'PENDING_APPROVER',
                'APPROVED',
                'PAID',
                'REJECTED',
                'DONE'
            ) DEFAULT 'PENDING_APPROVER'
        ");

        // Step 2: remap data — APPROVED (batch sudah di-approve CEO, siap dibayar)
        // jadi DONE, mengacu ke status bank/proses (bukan lagi label approval).
        DB::statement("UPDATE daily_transactions SET status = 'DONE' WHERE status = 'APPROVED'");

        // Step 3: ENUM final — APPROVED dihapus dari daftar nilai valid.
        DB::statement("
            ALTER TABLE daily_transactions
            MODIFY COLUMN status ENUM(
                'PENDING_APPROVER',
                'DONE',
                'PAID',
                'REJECTED'
            ) DEFAULT 'PENDING_APPROVER'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE daily_transactions
            MODIFY COLUMN status ENUM(
                'PENDING_APPROVER',
                'APPROVED',
                'PAID',
                'REJECTED',
                'DONE'
            ) DEFAULT 'PENDING_APPROVER'
        ");

        DB::statement("UPDATE daily_transactions SET status = 'APPROVED' WHERE status = 'DONE'");

        DB::statement("
            ALTER TABLE daily_transactions
            MODIFY COLUMN status ENUM(
                'PENDING_APPROVER',
                'APPROVED',
                'PAID',
                'REJECTED'
            ) DEFAULT 'PENDING_APPROVER'
        ");
    }
};
