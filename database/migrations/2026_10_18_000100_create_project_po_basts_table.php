<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * No. PO (project_po_numbers) = header, BAST = detail: satu No. PO bisa
     * punya banyak BAST. Kolom lama project_po_numbers.no_bast / tanggal_bast /
     * nilai_bast TIDAK dihapus — laporan & invoice masih membacanya (nilai_bast
     * tetap agregat seluruh BAST milik No. PO itu).
     *
     * Backfill: data lama hanya menyimpan satu no_bast (yang terakhir) dan
     * nilai_bast agregat, jadi tiap No. PO yang no_bast-nya terisi menjadi
     * SATU baris BAST berisi nilai agregat itu, dan seluruh lampiran
     * po_number_bast miliknya ditautkan ke baris tersebut.
     */
    public function up(): void
    {
        if (!Schema::hasTable('project_po_basts')) {
            Schema::create('project_po_basts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_po_number_id')->constrained('project_po_numbers')->restrictOnDelete();
                $table->string('no_bast');
                $table->date('tanggal_bast');
                $table->unsignedBigInteger('nilai_bast')->default(0);
                $table->text('notes')->nullable();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('project_attachments') && !Schema::hasColumn('project_attachments', 'project_po_bast_id')) {
            Schema::table('project_attachments', function (Blueprint $table) {
                $table->unsignedBigInteger('project_po_bast_id')->nullable()->after('project_po_number_id');
                $table->foreign('project_po_bast_id')->references('id')->on('project_po_basts')->restrictOnDelete();
            });
        }

        $dibuat = 0;
        $lampiran = 0;
        DB::table('project_po_numbers')
            ->whereNotNull('no_bast')->where('no_bast', '!=', '')
            ->orderBy('id')
            ->each(function ($po) use (&$dibuat, &$lampiran) {
                if (DB::table('project_po_basts')->where('project_po_number_id', $po->id)->exists()) {
                    return; // idempoten: sudah pernah di-backfill
                }

                // Baris BAST dan penautan lampiran satu kesatuan: kalau gagal di tengah,
                // tidak ada BAST tanpa lampiran tertaut yang lolos cek idempoten di atas.
                DB::transaction(function () use ($po, &$dibuat, &$lampiran) {
                    $bastId = DB::table('project_po_basts')->insertGetId([
                        'project_po_number_id' => $po->id,
                        'no_bast'             => $po->no_bast,
                        'tanggal_bast'        => $this->tanggalBast($po),
                        'nilai_bast'          => (int) $po->nilai_bast,
                        'notes'               => $po->bast_notes,
                        'submitted_by'        => $po->bast_submitted_by,
                        'created_at'          => $po->bast_submitted_at ?? now(),
                        'updated_at'          => now(),
                    ]);
                    $dibuat++;

                    $lampiran += DB::table('project_attachments')
                        ->where('project_po_number_id', $po->id)
                        ->where('type', 'po_number_bast')
                        ->whereNull('project_po_bast_id')
                        ->update(['project_po_bast_id' => $bastId]);
                });
            });

        Log::info("Backfill project_po_basts: {$dibuat} baris BAST dibuat, {$lampiran} lampiran ditautkan.");
    }

    /** Selalu berupa tanggal Y-m-d, tidak pernah null. */
    private function tanggalBast(object $po): string
    {
        $sumber = $po->tanggal_bast ?? $po->bast_submitted_at ?? $po->updated_at;

        return $sumber ? Carbon::parse($sumber)->toDateString() : now()->toDateString();
    }

    public function down(): void
    {
        if (Schema::hasColumn('project_attachments', 'project_po_bast_id')) {
            Schema::table('project_attachments', function (Blueprint $table) {
                $table->dropForeign(['project_po_bast_id']);
                $table->dropColumn('project_po_bast_id');
            });
        }

        Schema::dropIfExists('project_po_basts');
    }
};
