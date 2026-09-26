<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * CATATAN RENAME (26 Sep 2026)
 *
 * Nama berkas ini semula "2026_07_08_000002_update daily transactions.php" —
 * dengan SPASI. Itu berjalan hanya karena migrasi anonim tidak butuh nama
 * kelas, tapi rawan pada tooling yang memperlakukan nama berkas sebagai
 * argumen shell, pada sinkronisasi lintas sistem operasi, dan pada
 * "migrate --path".
 *
 * Konsekuensi rename: basis data yang SUDAH berjalan mencatat nama lama di
 * tabel `migrations`, sehingga berkas dengan nama baru dianggap migrasi yang
 * belum pernah dijalankan dan akan dicoba lagi. Versi asli akan mati di situ —
 * Schema::create('daily_transaction_attachments') menabrak tabel yang sudah
 * ada, dan penambahan kolom paid_by menabrak kolom yang sudah ada.
 *
 * Karena itu setiap langkah di bawah kini berpengaman. Pada basis data baru
 * hasilnya sama persis seperti sebelumnya; pada basis data yang sudah
 * bermigrasi, menjalankannya ulang tidak melakukan apa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
        |----------------------------------------------------------------------
        | 1. Tambah status PAID ke daily_transactions
        |----------------------------------------------------------------------
        */
        DB::statement("
            ALTER TABLE daily_transactions
            MODIFY COLUMN status ENUM(
                'PENDING_APPROVER',
                'APPROVED',
                'PAID',
                'REJECTED'
            ) DEFAULT 'PENDING_APPROVER'
        ");

        /*
        |----------------------------------------------------------------------
        | 2. Tambah kolom paid_by dan paid_at ke daily_transactions
        |----------------------------------------------------------------------
        */
        if (! Schema::hasColumn('daily_transactions', 'paid_by')) {
            Schema::table('daily_transactions', function (Blueprint $table) {
                $table->foreignId('paid_by')
                    ->nullable()
                    ->after('approved_at')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestamp('paid_at')->nullable()->after('paid_by');
                $table->text('payment_notes')->nullable()->after('paid_at');
            });
        }

        /*
        |----------------------------------------------------------------------
        | 3. Buat tabel daily_transaction_attachments
        |----------------------------------------------------------------------
        */
        if (! Schema::hasTable('daily_transaction_attachments')) {
            Schema::create('daily_transaction_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('daily_transaction_id')
                    ->constrained('daily_transactions')
                    ->cascadeOnDelete();
                $table->string('original_name');
                $table->string('stored_name');
                $table->string('path');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size')->nullable();
                $table->foreignId('uploaded_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_transaction_attachments');

        Schema::table('daily_transactions', function (Blueprint $table) {
            $table->dropForeign(['paid_by']);
            $table->dropColumn(['paid_by', 'paid_at', 'payment_notes']);
        });

        DB::statement("
            ALTER TABLE daily_transactions
            MODIFY COLUMN status ENUM(
                'PENDING_APPROVER',
                'APPROVED',
                'REJECTED'
            ) DEFAULT 'PENDING_APPROVER'
        ");
    }
};