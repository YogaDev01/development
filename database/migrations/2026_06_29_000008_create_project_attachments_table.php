<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_attachments', function (Blueprint $table) {

            $table->id();

            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            $table->string('title')->nullable();        // Judul lampiran
            $table->string('file_path');                // Path di storage
            $table->string('file_name');                // Nama file asli
            $table->string('file_type')->nullable();    // MIME type
            $table->unsignedBigInteger('input_by')->nullable();

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_attachments');
    }
};