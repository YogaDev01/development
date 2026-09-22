<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_register_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cost_register_id')->constrained('cost_registers')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('file_path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_register_attachments');
    }
};
