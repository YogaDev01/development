<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Team — beberapa customer per team
|--------------------------------------------------------------------------
|
| Keputusan 6 Nov 2026, menggantikan "satu team satu customer" (migrasi
| 2026_10_19_000300). Trouble Ticket membaca customer team dari tabel ini.
|
| Isi teams.customer_id disalin ke sini. Kolom lamanya TIDAK dihapus: ia tetap
| diisi customer pertama, jadi kode versi lama tetap jalan bila perlu kembali.
| Baris yang customer-nya sudah terhapus (kolom lama tanpa FK) tidak disalin,
| jumlahnya dicatat di log.
|
| Salinan aman diulang: bila up() terhenti setelah tabel terbentuk, menjalankan
| ulang hanya mengisi baris yang belum ada.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('team_customers')) {
            Schema::create('team_customers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                // Customer boleh dihapus selama belum punya PO/site (CustomerController):
                // tautannya ikut hilang, sehingga FK ini tidak mengubah aturan itu.
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->unique(['team_id', 'customer_id']);
            });
        }

        if (! Schema::hasColumn('teams', 'customer_id')) {
            return;
        }

        $disalin = DB::table('team_customers')->insertOrIgnoreUsing(
            ['team_id', 'customer_id'],
            DB::table('teams')
                ->join('customers', 'customers.id', '=', 'teams.customer_id')
                ->select('teams.id', 'teams.customer_id')
        );

        $yatim = DB::table('teams')
            ->leftJoin('customers', 'customers.id', '=', 'teams.customer_id')
            ->whereNotNull('teams.customer_id')
            ->whereNull('customers.id')
            ->count();

        Log::info('Migrasi team_customers: salinan dari teams.customer_id', [
            'disalin'           => $disalin,
            'customer_terhapus' => $yatim,
        ]);
    }

    /** Pilihan selain customer pertama hilang; teams.customer_id tetap utuh. */
    public function down(): void
    {
        Schema::dropIfExists('team_customers');
    }
};
