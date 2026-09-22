<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_note_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_note_id')->constrained('delivery_notes')->cascadeOnDelete();
            $table->foreignId('delivery_note_log_id')->nullable()->constrained('delivery_note_logs')->nullOnDelete();
            // 'evidence' = bukti tanda terima (dilampirkan saat status RECEIVED).
            $table->string('type')->default('evidence');
            $table->string('title')->nullable();
            $table->string('file_path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_note_attachments');
    }
};
