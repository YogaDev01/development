<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_registers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();

            // FIXED_COST = biaya tetap yang sudah diprorate manual (mis. gaji personel
            //              yang terlibat sebagian waktu di project ini).
            // OVERHEAD   = alokasi dari pool biaya overhead perusahaan ke project ini.
            $table->enum('type', ['FIXED_COST', 'OVERHEAD']);

            $table->integer('amount');
            $table->date('period')->nullable(); // bulan/periode acuan perhitungan, opsional
            $table->text('notes'); // wajib — justifikasi/dasar perhitungan prorate atau alokasi

            // Menandai baris-baris yang lahir dari satu form "Alokasi Overhead" sekaligus
            // (satu pool dibagi ke banyak project), supaya bisa ditelusuri/dihapus bersama.
            $table->string('batch_code')->nullable();

            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['project_id', 'type']);
            $table->index('batch_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_registers');
    }
};
