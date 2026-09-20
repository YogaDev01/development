<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecasts', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->date('date_start');
            $table->date('date_end');
            // Dihitung otomatis dari SUM(forecast_details.amount) — TIDAK
            // bisa diinput manual, lihat Forecast::syncForecastAmount().
            $table->unsignedBigInteger('forecast_amount')->default(0);
            // Integration point: idealnya ditarik dari transaksi realisasi
            // existing (Cost Request/Invoice), tapi sumbernya belum jelas
            // dari struktur existing (forecast tidak terikat ke satu Budget
            // Plan/Cost Center tertentu) — untuk sekarang diupdate manual
            // oleh Finance selama forecast ACTIVE (lihat updateActual()).
            $table->unsignedBigInteger('actual_amount')->default(0);
            $table->foreignId('source_fund_id')->nullable()->constrained('source_funds')->nullOnDelete();
            $table->enum('status', ['DRAFT', 'ACTIVE', 'CLOSED'])->default('DRAFT');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('forecast_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forecast_id')->constrained('forecasts')->cascadeOnDelete();
            $table->string('description');
            $table->unsignedBigInteger('amount');
            $table->date('date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_details');
        Schema::dropIfExists('forecasts');
    }
};
