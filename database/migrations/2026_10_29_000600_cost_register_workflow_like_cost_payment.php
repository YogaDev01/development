<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Cost Register mengikuti alur Cost Payment (Non-Cash Advance) di Cost Request:
 *
 *     DRAFT -> VERIFIED -> APPROVED -> QUEUED -> CLOSED   (+ CANCELLED)
 *
 * Tahap Checked dilewati: Draft langsung VERIFIED (ditampilkan sebagai "Submitted"
 * di UI). Approval oleh Approver (CEO) menggantikan "disepakati di luar sistem".
 *
 * MIGRASI DATA (status adalah ENUM MySQL):
 *   SUBMITTED -> APPROVED   siap masuk batch Treasury (perilaku Treasury tidak berubah;
 *                           nominalnya memang sudah disepakati di luar sistem)
 *   PAID      -> CLOSED     biaya yang sudah dibayar / catatan historis
 *   DRAFT, QUEUED, CANCELLED tetap.
 * Jumlah baris yang dipetakan ditulis ke log. down() membalik pemetaan
 * (VERIFIED, yang tidak ada di alur lama, dikembalikan ke DRAFT).
 */
return new class extends Migration
{
    private const LAMA = ['DRAFT', 'SUBMITTED', 'QUEUED', 'PAID', 'CANCELLED'];
    private const BARU = ['DRAFT', 'VERIFIED', 'APPROVED', 'QUEUED', 'CLOSED', 'CANCELLED'];

    private function enum(array $nilai): string
    {
        return "ENUM('" . implode("','", $nilai) . "')";
    }

    public function up(): void
    {
        if (! Schema::hasTable('cost_registers')) {
            return;
        }

        Schema::table('cost_registers', function (Blueprint $table) {
            if (! Schema::hasColumn('cost_registers', 'verified_by')) {
                $table->foreignId('verified_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
                $table->timestamp('verified_at')->nullable()->after('verified_by');
                $table->foreignId('approved_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
        });

        // 1) ENUM sementara memuat nilai lama + baru, supaya UPDATE pemetaan sah.
        DB::statement('ALTER TABLE cost_registers MODIFY status ' . $this->enum(array_unique(array_merge(self::LAMA, self::BARU))) . " NOT NULL DEFAULT 'DRAFT'");

        // 2) Pemetaan data.
        $disetujui = DB::table('cost_registers')->where('status', 'SUBMITTED')->update([
            'status'      => 'APPROVED',
            'approved_at' => DB::raw('COALESCE(submitted_at, created_at)'),
        ]);
        $ditutup = DB::table('cost_registers')->where('status', 'PAID')->update(['status' => 'CLOSED']);
        Log::info('Cost Register workflow: SUBMITTED->APPROVED ' . $disetujui . ' baris; PAID->CLOSED ' . $ditutup . ' baris.');

        // 3) ENUM final.
        DB::statement('ALTER TABLE cost_registers MODIFY status ' . $this->enum(self::BARU) . " NOT NULL DEFAULT 'DRAFT'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('cost_registers')) {
            return;
        }

        DB::statement('ALTER TABLE cost_registers MODIFY status ' . $this->enum(array_unique(array_merge(self::LAMA, self::BARU))) . " NOT NULL DEFAULT 'DRAFT'");

        DB::table('cost_registers')->where('status', 'VERIFIED')->update(['status' => 'DRAFT']); // tahap ini tidak ada di alur lama
        DB::table('cost_registers')->where('status', 'APPROVED')->update(['status' => 'SUBMITTED']);
        DB::table('cost_registers')->where('status', 'CLOSED')->update(['status' => 'PAID']);

        DB::statement('ALTER TABLE cost_registers MODIFY status ' . $this->enum(self::LAMA) . " NOT NULL DEFAULT 'DRAFT'");

        Schema::table('cost_registers', function (Blueprint $table) {
            if (Schema::hasColumn('cost_registers', 'verified_by')) {
                $table->dropForeign(['verified_by']);
                $table->dropForeign(['approved_by']);
                $table->dropColumn(['verified_by', 'verified_at', 'approved_by', 'approved_at']);
            }
        });
    }
};
