<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |----------------------------------------------------------------------
        | 1. ROLES — rename maker→manager, manager→head, tambah head
        |----------------------------------------------------------------------
        */

        // Rename manager → head dulu (agar tidak konflik)
        DB::table('roles')->where('name', 'manager')->update(['name' => 'head']);

        // Rename maker → manager
        DB::table('roles')->where('name', 'maker')->update(['name' => 'manager']);

        // Pastikan head sudah ada (jika belum ada dari rename di atas)
        if (!DB::table('roles')->where('name', 'head')->exists()) {
            DB::table('roles')->insert([
                'name'       => 'head',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        /*
        |----------------------------------------------------------------------
        | 2. BUDGET PLANS — update status ENUM
        |----------------------------------------------------------------------
        */

        // Update data existing yang tidak valid
        DB::statement("
            UPDATE budget_plans
            SET status = 'DRAFT'
            WHERE status NOT IN ('DRAFT','SUBMITTED','ACTIVE','REJECTED','CLOSED','PENDING_EXTEND')
        ");

        // Mapping status lama → baru
        DB::statement("UPDATE budget_plans SET status = 'SUBMITTED' WHERE status IN ('PENDING_MANAGER','PENDING_FINANCE','PENDING_APPROVER')");
        DB::statement("UPDATE budget_plans SET status = 'ACTIVE'    WHERE status = 'ACTIVE'");

        DB::statement("
            ALTER TABLE budget_plans
            MODIFY COLUMN status ENUM(
                'DRAFT',
                'SUBMITTED',
                'ACTIVE',
                'PENDING_EXTEND',
                'CLOSED',
                'REJECTED'
            ) DEFAULT 'DRAFT'
        ");

        /*
        |----------------------------------------------------------------------
        | 3. BUDGET REQUESTS — update status ENUM
        |----------------------------------------------------------------------
        */

        // ALTER dulu agar semua nilai baru valid
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
                'PENDING_MANAGER',
                'PENDING_FINANCE',
                'PENDING_PAYMENT',
                'PENDING_APPROVER'
            ) DEFAULT 'DRAFT'
        ");

        // Mapping status lama → baru
        DB::statement("UPDATE budget_request SET status = 'SUBMITTED' WHERE status IN ('PENDING_MANAGER','PENDING_HEAD')");
        DB::statement("UPDATE budget_request SET status = 'APPROVED'  WHERE status = 'PENDING_FINANCE'");
        DB::statement("UPDATE budget_request SET status = 'VERIFIED'  WHERE status IN ('APPROVED','PENDING_APPROVER')");
        DB::statement("UPDATE budget_request SET status = 'QUEUED'    WHERE status = 'PENDING_PAYMENT'");

        // Hapus nilai lama dari ENUM
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

        /*
        |----------------------------------------------------------------------
        | 4. REQUEST STATUS LOGS — pastikan VARCHAR (tidak perlu alter jika sudah)
        |----------------------------------------------------------------------
        */
        DB::statement("
            ALTER TABLE request_status_logs
            MODIFY COLUMN status_from VARCHAR(50) NULL DEFAULT NULL,
            MODIFY COLUMN status_to   VARCHAR(50) NULL DEFAULT NULL,
            MODIFY COLUMN action_type VARCHAR(50) NULL DEFAULT NULL
        ");
    }

    public function down(): void
    {
        // Rollback roles
        DB::table('roles')->where('name', 'manager')->update(['name' => 'maker']);
        DB::table('roles')->where('name', 'head')->update(['name' => 'manager']);
    }
};