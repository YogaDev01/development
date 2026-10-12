<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Trouble Ticket — alur 7 tahap (keputusan 12 Okt 2026)
|--------------------------------------------------------------------------
|
|   OPEN          tiket dibuat; teknisi memilih Remote atau Create Plan
|   REMOTE        diselesaikan tanpa kunjungan; SLA Resolution berjalan
|   PLAN          plan visit dibuat, menunggu tanggal plan visit
|   TRIP          tanggal plan visit tiba; perjalanan & on site
|   IN_PROGRESS   pengerjaan di site; SLA Resolution berjalan
|   PROGRESS_DONE form close dikirim, menunggu approval supervisor
|   CLOSED        supervisor menyetujui; SLA Resolution berhenti
|
| Approval PLAN dihapus: status APPROVED (menunggu approval leader) dan
| ON_PLAN keduanya menjadi PLAN. Kolom plan_approved_at/by TIDAK dibuang —
| riwayat approval plan lama tetap terbaca, hanya tidak ditulis lagi.
|
| SLA Travel kini punya jejak sendiri (travel_started_at → travel_arrived_at)
| menggantikan definisi lama assigned_at → progress_started_at.
|
| down(): mengembalikan PLAN → ON_PLAN (tahap yang paling dekat) dan membuang
| kolom baru. Tiket yang sudah lewat tahap baru (REMOTE/TRIP/PROGRESS_DONE)
| dipetakan ke padanan terdekat; dicatat di log saat down() berjalan.
*/
return new class extends Migration
{
    /** status lama => status baru */
    private const PETA = [
        'APPROVED' => 'PLAN',
        'ON_PLAN'  => 'PLAN',
    ];

    /** status baru => status lama (down) */
    private const PETA_BALIK = [
        'REMOTE'        => 'IN_PROGRESS',
        'PLAN'          => 'ON_PLAN',
        'TRIP'          => 'ON_PLAN',
        'PROGRESS_DONE' => 'IN_PROGRESS',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('trouble_tickets')) {
            return;
        }

        Schema::table('trouble_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('trouble_tickets', 'travel_started_at')) {
                $table->timestamp('travel_started_at')->nullable()->after('plan_approved_by');
            }
            if (! Schema::hasColumn('trouble_tickets', 'travel_arrived_at')) {
                $table->timestamp('travel_arrived_at')->nullable()->after('travel_started_at');
            }
            if (! Schema::hasColumn('trouble_tickets', 'travel_seconds')) {
                $table->unsignedInteger('travel_seconds')->nullable()->after('travel_arrived_at');
            }
            if (! Schema::hasColumn('trouble_tickets', 'travel_cancelled_at')) {
                $table->timestamp('travel_cancelled_at')->nullable()->after('travel_seconds');
            }
            if (! Schema::hasColumn('trouble_tickets', 'travel_cancel_reason')) {
                $table->string('travel_cancel_reason', 500)->nullable()->after('travel_cancelled_at');
            }
            if (! Schema::hasColumn('trouble_tickets', 'progress_done_at')) {
                $table->timestamp('progress_done_at')->nullable()->after('resolved_at');
            }
            if (! Schema::hasColumn('trouble_tickets', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('progress_done_at');
            }
            if (! Schema::hasColumn('trouble_tickets', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('approved_at')
                    ->constrained('users')->restrictOnDelete();
            }
        });

        $sebelum = DB::table('trouble_tickets')
            ->selectRaw('status, COUNT(*) AS jml')
            ->whereIn('status', array_keys(self::PETA))
            ->groupBy('status')
            ->pluck('jml', 'status')
            ->all();

        foreach ($sebelum as $lama => $jml) {
            Log::info("[migrasi] trouble_tickets: {$jml} baris '{$lama}' → '".self::PETA[$lama]."'");
        }

        foreach (self::PETA as $lama => $baru) {
            DB::table('trouble_tickets')->where('status', $lama)->update(['status' => $baru]);
        }

        // Approval plan tidak ada lagi; kolom approval dipakai ulang untuk
        // approval penutupan tiket (PENDING saat form close dikirim).
        DB::table('trouble_tickets')->whereNotNull('approval')->update(['approval' => null]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('trouble_tickets')) {
            return;
        }

        foreach (self::PETA_BALIK as $baru => $lama) {
            $jml = DB::table('trouble_tickets')->where('status', $baru)->count();
            if ($jml) {
                Log::info("[migrasi:down] trouble_tickets: {$jml} baris '{$baru}' → '{$lama}'");
            }
            DB::table('trouble_tickets')->where('status', $baru)->update(['status' => $lama]);
        }

        Schema::table('trouble_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('trouble_tickets', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }
            foreach (['approved_at', 'progress_done_at', 'travel_cancel_reason', 'travel_cancelled_at',
                'travel_seconds', 'travel_arrived_at', 'travel_started_at'] as $kolom) {
                if (Schema::hasColumn('trouble_tickets', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });
    }
};
