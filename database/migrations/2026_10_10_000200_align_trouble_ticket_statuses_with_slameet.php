<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Trouble Ticket — alur status disamakan dengan SLAmeet (5 tahap)
|--------------------------------------------------------------------------
|
| Keputusan 10 Okt 2026. Alur lama (9 status) diringkas menjadi:
|
|   OPEN        tiket dibuat; teknisi belum mengajukan plan
|   APPROVED    plan sudah diajukan teknisi, MENUNGGU approval leader team
|   ON_PLAN     plan disetujui leader team
|   IN_PROGRESS troubleshooting berjalan
|   CLOSED      ditutup teknisi (tanpa approval)
|
| Status AWAITING_PLAN, PLANNED, TRAVELING, ON_SITE, PENDING_CLOSURE dan
| CANCELLED dihapus. Pemetaan baris lama ada di PETA di bawah.
|
| Kolom lama TIDAK dibuang (cancelled_at/cancelled_by/cancel_reason, tabel
| trouble_ticket_visits & trouble_ticket_closure_reviews): riwayat tiket
| tidak boleh hilang, hanya tidak ditulis lagi. Yang ditambah hanya dua
| kolom jejak approval plan.
|
| down(): mengembalikan kolom baru dan memulihkan status lama HANYA untuk
| baris yang masih bisa dipastikan (APPROVED → PLANNED, ON_PLAN → PLANNED).
| TRAVELING/ON_SITE/PENDING_CLOSURE/CANCELLED tidak bisa dipulihkan karena
| informasinya sudah lebur — dicatat di log saat up() berjalan.
*/
return new class extends Migration
{
    /** status lama => status baru */
    private const PETA = [
        'AWAITING_PLAN'   => 'OPEN',
        'PLANNED'         => 'APPROVED',
        'TRAVELING'       => 'ON_PLAN',
        'ON_SITE'         => 'ON_PLAN',
        'PENDING_CLOSURE' => 'IN_PROGRESS',
        'CANCELLED'       => 'CLOSED',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('trouble_tickets')) {
            return;
        }

        Schema::table('trouble_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('trouble_tickets', 'plan_approved_at')) {
                $table->timestamp('plan_approved_at')->nullable()->after('visit_decision_reason');
            }
            if (! Schema::hasColumn('trouble_tickets', 'plan_approved_by')) {
                $table->foreignId('plan_approved_by')->nullable()->after('plan_approved_at')
                    ->constrained('users')->restrictOnDelete();
            }
        });

        // Jejak apa yang diubah — status lama tidak bisa direkonstruksi dari
        // kolom lain, jadi hitungannya dicatat sebelum UPDATE dijalankan.
        $sebelum = DB::table('trouble_tickets')
            ->selectRaw('status, COUNT(*) AS jml')
            ->whereIn('status', array_keys(self::PETA))
            ->groupBy('status')
            ->pluck('jml', 'status')
            ->all();

        foreach ($sebelum as $lama => $jml) {
            Log::info("[migrasi] trouble_tickets: {$jml} baris '{$lama}' → '" . self::PETA[$lama] . "'");
        }

        foreach (self::PETA as $lama => $baru) {
            DB::table('trouble_tickets')->where('status', $lama)->update(['status' => $baru]);
        }

        // Tiket yang dulu CANCELLED kini CLOSED: beri closed_at agar tidak
        // tampak "terbuka selamanya" di laporan.
        DB::table('trouble_tickets')
            ->where('status', 'CLOSED')
            ->whereNull('closed_at')
            ->whereNotNull('cancelled_at')
            ->update([
                'closed_at' => DB::raw('cancelled_at'),
                'closed_by' => DB::raw('cancelled_by'),
            ]);

        // Pengajuan Closed yang masih PENDING tidak punya gerbang lagi:
        // kolom approval dipakai ulang untuk approval PLAN.
        DB::table('trouble_tickets')->whereNotNull('approval')->update(['approval' => null]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('trouble_tickets')) {
            return;
        }

        DB::table('trouble_tickets')->whereIn('status', ['APPROVED', 'ON_PLAN'])->update(['status' => 'PLANNED']);

        Schema::table('trouble_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('trouble_tickets', 'plan_approved_by')) {
                $table->dropConstrainedForeignId('plan_approved_by');
            }
            if (Schema::hasColumn('trouble_tickets', 'plan_approved_at')) {
                $table->dropColumn('plan_approved_at');
            }
        });
    }
};
