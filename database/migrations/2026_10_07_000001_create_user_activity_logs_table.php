<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| USER ACTIVITY LOG — audit trail aktivitas bisnis user lintas module.
|
| Additive murni (tabel baru). Mengikuti pola ai_audit_logs: immutable,
| hanya created_at (tanpa updated_at), actor disnapshot agar log tetap
| terbaca walau user di-rename/hapus. Index dirancang untuk volume besar
| (lihat kolom yang sering difilter: user_id, created_at, module, action,
| subject). Jangan pernah menyimpan password/token/credential di sini.
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_activity_logs')) {
            return;
        }

        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->id();

            // Actor — selalu dari authenticated user/session, nullable supaya
            // log tidak ikut hilang jika user dihapus (nullOnDelete).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 255)->nullable();   // snapshot nama saat aksi
            $table->string('actor_role', 100)->nullable();   // snapshot role saat aksi

            // Aktivitas
            $table->string('module', 40);                    // identifier module, mis. cost_request
            $table->string('action', 30);                    // mis. create, approve, login
            $table->string('description', 500);              // human-readable (Bahasa Indonesia)
            $table->string('notes', 500)->nullable();        // opsional

            // Referensi objek (polymorphic ringan, tanpa relasi morph formal)
            $table->string('subject_type', 150)->nullable(); // FQCN model
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('reference', 100)->nullable();    // nomor dokumen, mis. CR-00231

            // Konteks audit — hanya IP (login). Tanpa user_agent/URL/body.
            $table->string('ip_address', 45)->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Index — kolom yang sering difilter/urutkan.
            $table->index(['user_id', 'created_at']);
            $table->index('module');
            $table->index('action');
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_logs');
    }
};
