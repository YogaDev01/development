<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Riwayat perubahan Team (struktur organisasi).
|
| Pola sama dengan project_logs / budget_plan_logs: satu baris per kejadian,
| ditulis DI DALAM transaksi perubahan Team (bukan gagal-aman seperti
| UserActivityLog), sehingga perubahan struktur tidak pernah tersimpan tanpa
| jejak. Tidak ada jalur update/delete untuk tabel ini.
|
| user_name / role = snapshot teks pelaku saat aksi (tetap terbaca walau user
| kemudian dihapus / ganti role).
|--------------------------------------------------------------------------
*/

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('team_logs')) {
            return;
        }

        Schema::create('team_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->restrictOnDelete();
            $table->string('action', 40);
            $table->json('changes')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('role', 100)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['team_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_logs');
    }
};
