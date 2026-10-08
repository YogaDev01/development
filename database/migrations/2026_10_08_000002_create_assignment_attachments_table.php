<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lampiran assignment. Berkas disimpan di disk "secure" (storage/app/private)
 * dan hanya keluar lewat SecureFileController + App\Services\FileAccess —
 * sama seperti lampiran modul lain. Anak dari assignment: cascade on delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assignment_attachments')) {
            return;
        }

        Schema::create('assignment_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');            // relatif terhadap disk secure
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0); // byte
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('assignment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_attachments');
    }
};
