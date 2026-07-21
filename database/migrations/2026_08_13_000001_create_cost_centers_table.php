<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();          // e.g. 'OH-001', 'PR-001'
            $table->string('name');                    // e.g. 'OVERHEAD KANTOR PUSAT'
            $table->enum('type', ['OVERHEAD', 'PAYROLL']);
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('input_by')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_centers');
    }
};