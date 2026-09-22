<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ubah ENUM menjadi VARCHAR sementara
        DB::statement("
            ALTER TABLE budget_plans
            MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'DRAFT'
        ");

        // 2. Mapping status lama ke status baru
        DB::statement("
            UPDATE budget_plans
            SET status = CASE UPPER(status)

                WHEN 'DRAFT' THEN 'DRAFT'
                WHEN 'SUBMITTED' THEN 'PENDING_APPROVER'
                WHEN 'PENDING' THEN 'PENDING_APPROVER'
                WHEN 'APPROVED' THEN 'ACTIVE'
                WHEN 'ACTIVE' THEN 'ACTIVE'
                WHEN 'CLOSED' THEN 'CLOSED'
                WHEN 'PENDING_EXTEND' THEN 'PENDING_EXTEND'
                WHEN 'PENDING_FINANCE' THEN 'PENDING_FINANCE'
                WHEN 'PENDING_APPROVER' THEN 'PENDING_APPROVER'

                ELSE 'DRAFT'

            END
        ");

        // 3. Baru ubah menjadi ENUM baru
        DB::statement("
            ALTER TABLE budget_plans
            MODIFY COLUMN status ENUM(
                'DRAFT',
                'PENDING_FINANCE',
                'PENDING_APPROVER',
                'ACTIVE',
                'PENDING_EXTEND',
                'CLOSED'
            ) NOT NULL DEFAULT 'DRAFT'
        ");
    }

    public function down(): void
    {
        // Kembali ke VARCHAR
        DB::statement("
            ALTER TABLE budget_plans
            MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'DRAFT'
        ");

        // Mapping balik
        DB::statement("
            UPDATE budget_plans
            SET status = CASE status

                WHEN 'PENDING_FINANCE' THEN 'PENDING_APPROVER'
                WHEN 'PENDING_APPROVER' THEN 'PENDING_APPROVER'
                WHEN 'ACTIVE' THEN 'ACTIVE'
                WHEN 'PENDING_EXTEND' THEN 'PENDING_EXTEND'
                WHEN 'CLOSED' THEN 'CLOSED'

                ELSE 'DRAFT'

            END
        ");

        // Kembalikan ENUM lama
        DB::statement("
            ALTER TABLE budget_plans
            MODIFY COLUMN status ENUM(
                'DRAFT',
                'PENDING_APPROVER',
                'ACTIVE',
                'PENDING_EXTEND',
                'CLOSED'
            ) NOT NULL DEFAULT 'DRAFT'
        ");
    }
};