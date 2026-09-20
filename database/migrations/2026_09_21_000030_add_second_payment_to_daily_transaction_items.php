<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Second Payment masuk batch Treasury
|--------------------------------------------------------------------------
| Sebelumnya Second Payment (selisih Settlement > pengajuan awal) hanya
| ditandai PAID lewat To-Do List — uangnya keluar tanpa pernah tercatat di
| Treasury. Sekarang ia jadi JENIS SUMBER KETIGA pada baris batch, sejajar
| budget_request_id (Cost Request) dan cost_register_id (Cost Register/MN-09).
|
| Catatan penting: baris Second Payment MENGISI KEDUANYA —
| second_payment_id (nilai yang dibayar) DAN budget_request_id (CR induknya),
| supaya pengelompokan Budget Plan, data penerima, dan file transfer bank
| tetap jalan tanpa cabang baru di mana-mana. Pembedanya selalu
| second_payment_id, bukan ketiadaan budget_request_id.
|--------------------------------------------------------------------------
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_transaction_items', function (Blueprint $table) {
            $table->foreignId('second_payment_id')
                ->nullable()
                ->after('cost_register_id')
                ->constrained('budget_request_second_payments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_transaction_items', function (Blueprint $table) {
            $table->dropForeign(['second_payment_id']);
            $table->dropColumn('second_payment_id');
        });
    }
};
