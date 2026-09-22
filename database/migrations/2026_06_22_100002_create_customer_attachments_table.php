<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_attachments', function (Blueprint $table) {

            $table->id();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            // Label / judul lampiran (mis. "NPWP", "Akta", "SIUP")
            $table->string('title')
                ->nullable();

            // Path file di storage (mis. customer_attachments/xxxx.pdf)
            $table->string('file_path');

            // Nama asli file saat diunggah
            $table->string('file_name')
                ->nullable();

            // MIME / tipe file
            $table->string('file_type')
                ->nullable();

            $table->foreignId('input_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_attachments');
    }
};