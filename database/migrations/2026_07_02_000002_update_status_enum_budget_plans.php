<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update data existing ke nilai yang valid dulu
        DB::statement("UPDATE budget_plans SET status = 'DRAFT' WHERE status NOT IN ('DRAFT','PENDING_FINANCE','PENDING_APPROVER','ACTIVE','PENDING_EXTEND','CLOSED')");

        DB::statement("
            ALTER TABLE budget_plans
            MODIFY COLUMN status ENUM(
                'DRAFT',
                'PENDING_FINANCE',
                'PENDING_APPROVER',
                'ACTIVE',
                'PENDING_EXTEND',
                'CLOSED'
            ) DEFAULT 'DRAFT'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE budget_plans
            MODIFY COLUMN status ENUM(
                'DRAFT',
                'PENDING_APPROVER',
                'ACTIVE',
                'PENDING_EXTEND',
                'CLOSED'
            ) DEFAULT 'DRAFT'
        ");
    }
};