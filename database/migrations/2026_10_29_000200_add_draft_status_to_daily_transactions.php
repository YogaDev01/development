<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Treasury — status DRAFT pada daily_transactions (29 Okt 2026)
|--------------------------------------------------------------------------
|
| Batch buatan Treasury lahir sebagai DRAFT (masih bisa diubah/dihapus, belum
| bisa di-approve CEO) lalu di-Submit menjadi PENDING_APPROVER. Aditif: nilai
| ENUM lama tetap ada, default kolom tidak berubah (batch dari Payroll Run
| tetap langsung PENDING_APPROVER). Kolom non-ENUM (mis. varchar) dibiarkan.
*/
return new class extends Migration
{
    public function up(): void
    {
        $kolom = DB::selectOne("SHOW COLUMNS FROM daily_transactions LIKE 'status'");
        $tipe  = strtolower($kolom->Type ?? '');

        if (! str_starts_with($tipe, 'enum(') || str_contains($tipe, "'draft'")) {
            return;
        }

        DB::statement("
            ALTER TABLE daily_transactions
            MODIFY COLUMN status ENUM(
                'DRAFT',
                'PENDING_APPROVER',
                'DONE',
                'PAID',
                'REJECTED'
            ) DEFAULT 'PENDING_APPROVER'
        ");
    }

    public function down(): void
    {
        $kolom = DB::selectOne("SHOW COLUMNS FROM daily_transactions LIKE 'status'");
        if (! str_contains(strtolower($kolom->Type ?? ''), "'draft'")) {
            return;
        }

        // Jangan memotong data: rollback ditolak selama masih ada batch Draft.
        if (DB::table('daily_transactions')->where('status', 'DRAFT')->exists()) {
            throw new \RuntimeException('Masih ada batch DRAFT — submit atau hapus dulu sebelum rollback.');
        }

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
};
