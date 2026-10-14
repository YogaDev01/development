<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Dokumen site (tab Document di drawer). Berkasnya disimpan di disk `secure`
| (storage/app/private) dan hanya bisa diunduh lewat SecureFileController —
| sama seperti lampiran modul lain.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_documents')) {
            return;
        }

        Schema::create('site_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('file_path', 255);
            $table->string('file_name', 255);
            $table->string('file_type', 100)->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_documents');
    }
};
