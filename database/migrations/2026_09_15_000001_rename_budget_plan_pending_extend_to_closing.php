<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Model/kode BudgetPlan sudah lama memakai status 'CLOSING' (lihat
    | BudgetPlan::STATUSES dan BudgetPlanController::submitClosing()), tapi
    | migration terakhir yang mengubah ENUM di DB (2026_07_08_000001) masih
    | menulis 'PENDING_EXTEND'. Selaraskan ENUM ke 'CLOSING' + migrasikan data
    | lama, supaya transisi ACTIVE -> CLOSING tidak gagal di level database.
    |
    | ALTER ENUM dulu (superset, agar tidak ada data yang truncated) baru
    | UPDATE data, sesuai konvensi migration di project ini.
    |--------------------------------------------------------------------------
    */

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            // SQLite: kolom status disimpan sebagai TEXT, tidak menegakkan ENUM,
            // jadi tinggal migrasikan datanya saja.
            DB::table('budget_plans')->where('status', 'PENDING_EXTEND')->update(['status' => 'CLOSING']);
            return;
        }

        DB::statement("
            ALTER TABLE budget_plans
            MODIFY COLUMN status ENUM(
                'DRAFT',
                'SUBMITTED',
                'ACTIVE',
                'PENDING_EXTEND',
                'CLOSING',
                'CLOSED',
                'REJECTED'
            ) DEFAULT 'DRAFT'
        ");

        DB::statement("UPDATE budget_plans SET status = 'CLOSING' WHERE status = 'PENDING_EXTEND'");

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

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            DB::table('budget_plans')->where('status', 'CLOSING')->update(['status' => 'PENDING_EXTEND']);
            return;
        }

        DB::statement("
            ALTER TABLE budget_plans
            MODIFY COLUMN status ENUM(
                'DRAFT',
                'SUBMITTED',
                'ACTIVE',
                'PENDING_EXTEND',
                'CLOSING',
                'CLOSED',
                'REJECTED'
            ) DEFAULT 'DRAFT'
        ");

        DB::statement("UPDATE budget_plans SET status = 'PENDING_EXTEND' WHERE status = 'CLOSING'");

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
    }
};
