<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE cost_centers
            MODIFY COLUMN type ENUM(
                'OVERHEAD',
                'PAYROLL',
                'TAX',
                'ASSET',
                'LIABILITY',
                'CAPITAL',
                'ADJUSTMENT'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE cost_centers
            MODIFY COLUMN type ENUM(
                'OVERHEAD',
                'PAYROLL'
            ) NOT NULL
        ");
    }
};
