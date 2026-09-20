<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Budget Plan approval workflow diperpanjang jadi 4 tahap (sama seperti
    | Cost Request): Manager submit -> Head check -> Finance verify -> CEO
    | approve (ACTIVE). Tambah status CHECKED & VERIFIED di antara SUBMITTED
    | dan ACTIVE. Lihat BudgetPlan::STATUSES & BudgetPlanController.
    |--------------------------------------------------------------------------
    */

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return; // SQLite: kolom status disimpan sebagai TEXT, tidak menegakkan ENUM
        }

        DB::statement("
            ALTER TABLE budget_plans
            MODIFY COLUMN status ENUM(
                'DRAFT',
                'SUBMITTED',
                'CHECKED',
                'VERIFIED',
                'ACTIVE',
                'CLOSING',
                'CLOSED',
                'REJECTED'
            ) DEFAULT 'DRAFT'
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Baris yang sempat berstatus CHECKED/VERIFIED dikembalikan ke SUBMITTED
        // supaya tidak truncated saat ENUM dipersempit lagi.
        DB::statement("UPDATE budget_plans SET status = 'SUBMITTED' WHERE status IN ('CHECKED','VERIFIED')");

        DB::statement("
            ALTER TABLE budget_plans
            MODIFY COLUMN status ENUM(
                'DRAFT',
                'SUBMITTED',
                'ACTIVE',
                'CLOSING',
                'CLOSED',
                'REJECTED'
            ) DEFAULT 'DRAFT'
        ");
    }
};
