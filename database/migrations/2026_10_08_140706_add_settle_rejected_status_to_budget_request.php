<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Status baru SETTLE_REJECTED: reject settlement (Head/Finance/Finance
 * Manager/Approver) sekarang berhenti di status ini, bukan langsung kembali
 * ke PAID — supaya CR yang settlement-nya ditolak terlihat berbeda dari CR
 * yang memang belum pernah disettle sama sekali. Dari SETTLE_REJECTED,
 * pengaju merevisi lalu submit ulang (-> REALIZED), sama seperti dari PAID.
 *
 * Murni ADDITIVE: tidak ada nilai lama yang diubah/dihapus, tidak ada
 * remapping data. Lihat App\Models\BudgetRequest::STATUSES dan
 * App\Support\BudgetConsumption.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('budget_request')) {
            return;
        }

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
                'SETTLE_CHECKED',
                'SETTLE_VERIFIED',
                'SETTLE_APPROVED',
                'SECOND_APPROVED',
                'CLOSED',
                'REJECTED',
                'CANCELLED',
                'SETTLE_REJECTED'
            ) DEFAULT 'DRAFT'
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('budget_request')) {
            return;
        }

        // CATATAN: lossy kalau ada baris yang sedang SETTLE_REJECTED saat
        // rollback — baris itu tidak valid lagi untuk ENUM lama. Pindahkan
        // manual ke PAID (perilaku lama) sebelum rollback di lingkungan yang
        // datanya sudah terbentuk.
        DB::statement("UPDATE budget_request SET status = 'PAID' WHERE status = 'SETTLE_REJECTED'");

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
                'SETTLE_CHECKED',
                'SETTLE_VERIFIED',
                'SETTLE_APPROVED',
                'SECOND_APPROVED',
                'CLOSED',
                'REJECTED',
                'CANCELLED'
            ) DEFAULT 'DRAFT'
        ");
    }
};
