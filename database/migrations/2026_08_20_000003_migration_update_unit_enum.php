<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Daftar unit HARUS selalu sinkron dengan App\Models\BudgetPlanDetail::UNITS.
     * MySQL ENUM tidak otomatis update walau const PHP-nya sudah diubah —
     * makanya perlu migration terpisah tiap kali daftar unit bertambah.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE budget_plan_details
            MODIFY COLUMN unit ENUM(
                'kg', 'liter', 'meter', 'meter persegi', 'meter kubik',
                'roll', 'pcs', 'unit', 'lumpsum', 'package',
                'lot', 'ton', 'sax', 'set', 'site', 'lembar',
                'hari', 'orang', 'team', 'trip', 'minggu', 'bulan'
            ) NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE budget_plan_details
            MODIFY COLUMN unit ENUM(
                'kg', 'liter', 'meter', 'meter persegi', 'meter kubik',
                'roll', 'pcs', 'unit', 'lumpsum', 'package',
                'lot', 'ton', 'sax', 'set', 'site', 'lembar'
            ) NULL
        ");
    }
};