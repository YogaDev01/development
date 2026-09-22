<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M-02 — rapikan relasi Team / PIC / User / Employee.
 *
 * HASIL AUDIT:
 *
 *   Team -> TeamMember -> User          SUDAH ADA dan benar.
 *   Team -> Department                  SUDAH ADA dan benar.
 *   MasterPic -> (User / Employee)      TIDAK ADA.
 *
 * MasterPic selama ini hanya menyimpan nama sebagai teks, termasuk untuk PIC
 * bertipe `internal` — yang sebetulnya adalah orang yang juga punya akun User
 * dan/atau baris Employee. Akibatnya satu orang bisa ada di tiga tempat dengan
 * ejaan nama berbeda, dan tidak ada cara memastikan PIC internal di sebuah PO
 * adalah orang yang sama dengan anggota Team-nya.
 *
 * Dua kolom penghubung di bawah memperbaikinya TANPA memaksa: keduanya
 * nullable, jadi PIC customer (yang memang orang luar dan tidak punya akun)
 * tetap sah apa adanya, dan seluruh data lama tetap valid tanpa backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_pics', function (Blueprint $table) {
            // Akun aplikasi milik PIC ini, kalau dia orang dalam.
            $table->foreignId('user_id')
                ->nullable()
                ->after('type')
                ->constrained('users')
                ->nullOnDelete();

            // Data kepegawaiannya, kalau ada.
            $table->foreignId('employee_id')
                ->nullable()
                ->after('user_id')
                ->constrained('employees')
                ->nullOnDelete();

            $table->index(['type', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('master_pics', function (Blueprint $table) {
            $table->dropIndex(['type', 'user_id']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('employee_id');
        });
    }
};
