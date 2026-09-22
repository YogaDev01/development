<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_monitoring_log_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('log_id')->constrained('project_monitoring_logs')->cascadeOnDelete();

            $table->string('file_path');
            $table->string('file_name')->nullable();
            $table->string('file_type')->nullable(); // mime type, dipakai bedakan image/video
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_monitoring_log_attachments');
    }
};
