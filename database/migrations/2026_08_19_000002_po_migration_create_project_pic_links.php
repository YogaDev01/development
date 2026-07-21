<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_pic_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('master_pic_id')->constrained('master_pics')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'master_pic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_pic_links');
    }
};