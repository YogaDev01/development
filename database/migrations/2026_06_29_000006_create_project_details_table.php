<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_details', function (Blueprint $table) {

            $table->id();

            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            // Cluster (Project master) yang terlibat dalam stage ini
            $table->foreignId('cluster_id')
                ->nullable()
                ->constrained('clusters')
                ->nullOnDelete();

            // Stage / Milestone
            $table->foreignId('stage_id')
                ->nullable()
                ->constrained('stages')
                ->nullOnDelete();

            // Plan
            $table->date('plan_start')->nullable();
            $table->date('plan_end')->nullable();

            // Actual
            $table->date('actual_start')->nullable();
            $table->date('actual_end')->nullable();

            // Keterangan tambahan per detail
            $table->text('notes')->nullable();

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_details');
    }
};