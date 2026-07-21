<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tampilkan status existing dulu untuk debug
        // UPDATE semua status yang tidak valid ke APPROVED (karena mungkin sudah melewati review)
        DB::statement("
            UPDATE budget_request
            SET status = 'APPROVED'
            WHERE status NOT IN (
                'DRAFT','PENDING_MANAGER','PENDING_FINANCE',
                'APPROVED','PENDING_PAYMENT',
                'PAID','REALIZED','CLOSED','REJECTED'
            )
        ");

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