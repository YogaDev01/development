<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_pics', function (Blueprint $table) {

            $table->id();

            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            $table->string('pic')->nullable();          // Nama PIC
            $table->string('position')->nullable();     // Jabatan
            $table->string('contact')->nullable();      // No. HP / Email
            $table->text('remark')->nullable();         // Keterangan

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_pics');
    }
};