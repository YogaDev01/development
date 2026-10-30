<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cost Register punya header (cost_registers) + rincian per item, seperti Cost
 * Request, serta log workflow + catatan chat.
 *
 * cost_register_details : akun (Chart of Account kategori Fixed Cost / Overhead),
 *                         deskripsi, qty, harga satuan, total. Nominal header
 *                         (cost_registers.amount) = jumlah total rincian untuk
 *                         register baru; register lama tidak punya rincian dan
 *                         nominal headernya tetap apa adanya.
 * cost_register_logs    : riwayat status (status_from/status_to terisi) dan catatan
 *                         bebas gaya chat (action = 'note', status kosong).
 *                         Immutable: hanya created_at.
 *
 * Uang = integer rupiah (BIGINT UNSIGNED).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cost_register_details')) {
            Schema::create('cost_register_details', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cost_register_id')->constrained('cost_registers')->cascadeOnDelete();
                $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
                $table->text('description');
                $table->unsignedInteger('qty')->default(1);
                $table->unsignedBigInteger('unit_price')->default(0);
                $table->unsignedBigInteger('total')->default(0);
                $table->timestamps();

                $table->index('cost_register_id');
            });
        }

        if (! Schema::hasTable('cost_register_logs')) {
            Schema::create('cost_register_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cost_register_id')->constrained('cost_registers')->cascadeOnDelete();
                $table->string('action', 40);
                $table->string('status_from', 20)->nullable();
                $table->string('status_to', 20)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('user_name')->nullable();
                $table->string('role', 60)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index('cost_register_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_register_logs');
        Schema::dropIfExists('cost_register_details');
    }
};
