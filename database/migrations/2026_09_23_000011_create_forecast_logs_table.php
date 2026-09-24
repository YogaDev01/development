<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak aktivitas Forecast — bentuknya mengikuti delivery_note_logs &
 * budget_plan_logs: satu baris per kejadian, dengan nama pelaku ikut disalin
 * supaya riwayat tetap terbaca kalau penggunanya kelak dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forecast_id')->constrained('forecasts')->cascadeOnDelete();
            $table->string('action'); // CREATED, UPDATED, ACTIVATED, ACTUAL_ADDED, ACTUAL_REMOVED, CLOSED, AUTO_CLOSED
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_logs');
    }
};
