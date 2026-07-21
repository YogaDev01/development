<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |----------------------------------------------------------------------
        | 1. Tambah kolom email ke budget_request
        |----------------------------------------------------------------------
        */
        Schema::table('budget_request', function (Blueprint $table) {
            if (!Schema::hasColumn('budget_request', 'email')) {
                $table->string('email')->nullable()->after('notes')
                      ->comment('Email notifikasi, bisa multiple dipisah koma');
            }
        });

        /*
        |----------------------------------------------------------------------
        | 2. Buat tabel budget_request_realizations
        |----------------------------------------------------------------------
        */
        Schema::create('budget_request_realizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_request_id')
                  ->constrained('budget_request')
                  ->cascadeOnDelete();
            $table->string('description');
            $table->foreignId('account_id')
                  ->nullable()
                  ->constrained('accounts')
                  ->nullOnDelete();
            $table->unsignedBigInteger('amount')->default(0);
            $table->unsignedInteger('qty')->default(1);
            $table->unsignedBigInteger('total')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        /*
        |----------------------------------------------------------------------
        | 3. Buat tabel budget_request_realization_attachments
        |----------------------------------------------------------------------
        */
        Schema::create('budget_request_realization_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_request_id')
                  ->constrained('budget_request')
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
        Schema::dropIfExists('budget_request_realization_attachments');
        Schema::dropIfExists('budget_request_realizations');

        Schema::table('budget_request', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};