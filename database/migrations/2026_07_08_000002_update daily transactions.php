<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
        Schema::table('daily_transactions', function (Blueprint $table) {
            $table->foreignId('paid_by')
                ->nullable()
                ->after('approved_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('paid_at')->nullable()->after('paid_by');
            $table->text('payment_notes')->nullable()->after('paid_at');
        });

        /*
        |----------------------------------------------------------------------
        | 3. Buat tabel daily_transaction_attachments
        |----------------------------------------------------------------------
        */
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