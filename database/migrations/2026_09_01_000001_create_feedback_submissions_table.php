<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_submissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->enum('category', ['SUGGESTION', 'CRITICISM', 'CONCERN', 'IDEA', 'OTHER']);

            // Dipakai untuk semua kategori KECUALI Criticism.
            $table->text('message')->nullable();

            // Khusus kategori Criticism — wajib dua-duanya diisi (kritik + saran perbaikan).
            $table->text('criticism_text')->nullable();
            $table->text('suggestion_text')->nullable();

            $table->boolean('willing_to_discuss')->default(false);
            $table->string('whatsapp_number')->nullable();

            $table->enum('status', ['SUBMITTED', 'REVIEWED', 'RESPONDED', 'CLOSED'])->default('SUBMITTED');

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->text('response_text')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();

            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_submissions');
    }
};
