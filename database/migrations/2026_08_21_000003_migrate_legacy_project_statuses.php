<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | `BILLED` dihapus dari Project::STATUSES — dicek ulang di seluruh
    | codebase, tidak ada satu tempat pun yang benar-benar men-set status
    | itu (cuma didefinisikan & dihitung di summary, tidak pernah ditulis).
    | Migration ini murni jaring pengaman kalau ada data lama/manual yang
    | kebetulan berstatus BILLED, diturunkan ke ACTIVE.
    |
    | PROGRESS_DONE dan PAID TIDAK disentuh — keduanya status header asli
    | yang aktif dipakai (submitBast(), Invoice::syncPaymentStatus(), gerbang
    | submitClosing()).
    |--------------------------------------------------------------------------
    */
    public function up(): void
    {
        DB::table('projects')
            ->where('status', 'BILLED')
            ->update(['status' => 'ACTIVE']);
    }

    public function down(): void
    {
        // Tidak reversible — status asli BILLED sudah tidak disimpan lagi.
    }
};
