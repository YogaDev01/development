<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Preventive Maintenance site: satu catatan per kunjungan, berisi serial
| number perangkat SEBELUM dan SESUDAH maintenance.
|
| `sn_before` adalah salinan SN material saat catatan dibuat (bukan relasi),
| supaya riwayat tetap utuh walau SN material sudah diganti oleh `sn_after`.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_maintenances')) {
            Schema::create('site_maintenances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('site_id')->constrained('sites')->restrictOnDelete();
                $table->date('performed_at');
                $table->string('performed_by', 150)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['site_id', 'performed_at']);
            });
        }

        if (! Schema::hasTable('site_maintenance_items')) {
            Schema::create('site_maintenance_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('maintenance_id')->constrained('site_maintenances')->cascadeOnDelete();
                // Material boleh dilepas dari site di kemudian hari; catatan tetap
                // menyimpan nama perangkatnya.
                $table->foreignId('material_id')->nullable()->constrained('master_material')->nullOnDelete();
                $table->string('device_name', 150);
                $table->string('sn_before', 150)->nullable();
                $table->string('sn_after', 150)->nullable();
                $table->timestamps();

                $table->index('maintenance_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_maintenance_items');
        Schema::dropIfExists('site_maintenances');
    }
};
