<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_monitoring_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete(); // PO
            $table->foreignId('cluster_id')->nullable()->constrained('clusters')->nullOnDelete();
            $table->foreignId('project_detail_id')->nullable()->constrained('project_details')->nullOnDelete(); // milestone
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('type', ['MESSAGE', 'SYSTEM'])->default('MESSAGE');
            $table->text('message')->nullable();

            $table->timestamps();

            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_monitoring_logs');
    }
};
