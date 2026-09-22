<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NOB (Not On Budget) menjadi data, bukan tulisan.
 *
 * Sebelum ini penanda "pengajuan melebihi anggaran" disimpan sebagai awalan
 * teks pada kolom deskripsi — '[NOB] beli genset' — dan pada catatan tiap
 * baris rincian: '[NOB][1.500.000] ...'. Konsekuensinya:
 *
 *   - status keuangan penting dibaca ulang dengan str_starts_with / regex di
 *     tujuh tempat berbeda; satu tempat yang lupa berarti angka NOB tidak
 *     muncul di laporan itu saja, tanpa ada yang menyadarinya;
 *   - pengguna bisa mengetik "[NOB]" sendiri di deskripsi dan sistem akan
 *     mempercayainya;
 *   - mengedit deskripsi bisa menghapus penandanya tanpa sengaja;
 *   - besar pelampauannya hanya ada sebagai teks berformat ribuan, sehingga
 *     tidak bisa dijumlahkan.
 *
 * Kolomnya diisi ulang dari penanda lama, lalu penanda itu dibersihkan dari
 * teksnya — data lama tetap utuh artinya, hanya pindah tempat.
 */
return new class extends Migration
{
    public function up(): void
    {
        // hasColumn(): migrasi ini pernah gagal separuh jalan saat nama tabel
        // rincian salah, jadi dibuat aman untuk dijalankan ulang.
        Schema::table('budget_request', function (Blueprint $table) {
            if (! Schema::hasColumn('budget_request', 'is_nob')) {
                $table->boolean('is_nob')->default(false)->after('amount');
            }
            if (! Schema::hasColumn('budget_request', 'nob_amount')) {
                $table->unsignedBigInteger('nob_amount')->default(0)->after('is_nob');
            }
        });

        Schema::table('request_detail', function (Blueprint $table) {
            if (! Schema::hasColumn('request_detail', 'is_nob')) {
                $table->boolean('is_nob')->default(false)->after('total');
            }
            if (! Schema::hasColumn('request_detail', 'nob_amount')) {
                $table->unsignedBigInteger('nob_amount')->default(0)->after('is_nob');
            }
        });

        // --- Pindahkan penanda lama ke kolom -------------------------------

        DB::table('budget_request')
            ->where('description', 'like', '[NOB]%')
            ->update([
                'is_nob'      => true,
                'description' => DB::raw("TRIM(SUBSTRING(description, 7))"),
            ]);

        /*
        | Rincian: '[NOB][1.500.000] catatan' -> is_nob + nob_amount + catatan.
        | Titik ribuan dibuang agar bisa disimpan sebagai angka. Baris tanpa
        | penanda tidak disentuh.
        */
        foreach (DB::table('request_detail')->where('notes', 'like', '[NOB][%')->get(['id', 'notes']) as $row) {
            if (! preg_match('/^\[NOB\]\[([\d.,]+)\]\s*(.*)$/is', (string) $row->notes, $cocok)) {
                continue;
            }

            DB::table('request_detail')->where('id', $row->id)->update([
                'is_nob'     => true,
                'nob_amount' => (int) preg_replace('/[^\d]/', '', $cocok[1]),
                'notes'      => trim($cocok[2]) !== '' ? trim($cocok[2]) : null,
            ]);
        }
    }

    public function down(): void
    {
        // Kembalikan penanda ke teks supaya rollback tidak kehilangan informasi.
        DB::table('budget_request')
            ->where('is_nob', true)
            ->update(['description' => DB::raw("CONCAT('[NOB] ', description)")]);

        Schema::table('budget_request', function (Blueprint $table) {
            $table->dropColumn(['is_nob', 'nob_amount']);
        });

        Schema::table('request_detail', function (Blueprint $table) {
            $table->dropColumn(['is_nob', 'nob_amount']);
        });
    }
};
