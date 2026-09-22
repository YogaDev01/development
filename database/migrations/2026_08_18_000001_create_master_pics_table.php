<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_pics', function (Blueprint $table) {
            $table->id();

            $table->enum('type', ['customer', 'internal'])->default('internal');

            $table->string('pic');          // Nama lengkap
            $table->string('position')->nullable(); // Jabatan
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            $table->string('status')->default('ACTIVE');

            $table->foreignId('input_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['type', 'status']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_pics');
    }
};
