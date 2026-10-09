<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Trouble Ticket — master pendukung: kategori masalah & kebijakan SLA.
|
| Target SLA SENGAJA tidak diisi: nilainya kontraktual dan belum diberikan
| perusahaan. Tiket tanpa target ditampilkan "Target belum diatur", tidak
| pernah ditandai terlambat. Target yang berlaku disalin (snapshot) ke tiket
| saat dibuat, supaya mengubah kebijakan tidak mengubah tiket lama.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trouble_ticket_categories')) {
            Schema::create('trouble_ticket_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                // Ajukan Closed wajib menyertakan lampiran bukti.
                $table->boolean('requires_evidence')->default(false);
                $table->string('status', 20)->default('ACTIVE');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('trouble_ticket_sla_policies')) {
            Schema::create('trouble_ticket_sla_policies', function (Blueprint $table) {
                $table->id();
                $table->string('priority', 10);
                // NULL = berlaku untuk semua kategori pada prioritas ini.
                $table->foreignId('category_id')->nullable()
                    ->constrained('trouble_ticket_categories')->restrictOnDelete();
                // Target dalam menit; NULL = tidak ada target untuk ukuran itu.
                $table->unsignedInteger('response_minutes')->nullable();
                $table->unsignedInteger('travel_minutes')->nullable();
                $table->unsignedInteger('resolution_minutes')->nullable();
                $table->date('effective_from')->nullable();
                $table->string('status', 20)->default('ACTIVE');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['priority', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trouble_ticket_sla_policies');
        Schema::dropIfExists('trouble_ticket_categories');
    }
};
