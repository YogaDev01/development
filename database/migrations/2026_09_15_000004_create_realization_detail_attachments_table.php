<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lampiran per rincian Settlement/Realisasi (nota/bukti KHUSUS satu item) —
 * pola sama seperti request_detail_attachments untuk Cost Request. Lampiran
 * global tetap di tabel budget_request_realization_attachments (sudah ada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realization_detail_attachments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('realization_id');
            $table->foreign('realization_id')
                ->references('id')->on('budget_request_realizations')
                ->onDelete('cascade');

            $table->string('original_name');
            $table->string('stored_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->bigInteger('size')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();

            $table->timestamps();

            $table->index('realization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realization_detail_attachments');
    }
};
