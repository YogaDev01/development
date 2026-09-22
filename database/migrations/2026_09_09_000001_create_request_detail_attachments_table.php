<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lampiran per rincian Cost Request (nota/bukti KHUSUS satu item).
 * Lampiran global / evidence pendukung tetap di tabel request_attachments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_detail_attachments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('request_detail_id');
            $table->foreign('request_detail_id')
                ->references('id')->on('request_detail')
                ->onDelete('cascade');

            $table->string('original_name');
            $table->string('stored_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->bigInteger('size')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();

            $table->timestamps();

            $table->index('request_detail_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_detail_attachments');
    }
};
