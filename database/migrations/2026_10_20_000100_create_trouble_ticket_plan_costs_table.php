<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Trouble Ticket — item estimasi biaya plan visit
|--------------------------------------------------------------------------
|
| Keputusan 20 Okt 2026. Create Plan tidak lagi hanya menetapkan tanggal
| visit: ia juga mencatat estimasi biaya pekerjaan (Budget Plan sebagai
| sumber dana + baris COA/Description/Harga/Qty/Total).
|
| Baris ini ESTIMASI, bukan realisasi: tidak menyentuh BudgetConsumption dan
| tidak mengurangi sisa pagu budget plan. Uang = integer rupiah.
|
| Aditif: tabel baru, tiket lama tanpa baris biaya berperilaku seperti dulu.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trouble_ticket_plan_costs') || ! Schema::hasTable('trouble_tickets')) {
            return;
        }

        Schema::create('trouble_ticket_plan_costs', function (Blueprint $table) {
            $table->id();

            // Anak dari tiket: ikut terhapus bila tiketnya dihapus.
            $table->foreignId('trouble_ticket_id')
                ->constrained('trouble_tickets')
                ->cascadeOnDelete();

            // COA. nullOnDelete mengikuti pola budget_plan_details supaya
            // penghapusan master tidak mematikan riwayat tiket; nama COA saat
            // disimpan ikut dicatat agar baris lama tetap terbaca.
            $table->foreignId('account_id')
                ->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->string('account_name')->nullable();

            $table->string('description');
            // Uang = integer rupiah (CLAUDE.md §5). qty boleh pecahan.
            $table->unsignedBigInteger('amount')->default(0);
            $table->decimal('qty', 10, 2)->default(1);
            $table->unsignedBigInteger('total')->default(0);

            $table->timestamps();

            $table->index('trouble_ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trouble_ticket_plan_costs');
    }
};
