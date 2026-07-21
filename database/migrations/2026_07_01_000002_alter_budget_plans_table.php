<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_plans', function (Blueprint $table) {

            // Code auto-generate BD202606000100001
            $table->string('code', 20)->nullable()->unique()->after('id');

            // Type budget plan
            $table->enum('type', ['recurring', 'one_time'])
                  ->default('one_time')
                  ->after('code');

            // Catatan dari approver saat reject / extend
            $table->text('approver_notes')->nullable()->after('status');

            // Siapa yang approve & kapan
            $table->unsignedBigInteger('approved_by')->nullable()->after('approver_notes');
            $table->timestamp('approved_at')->nullable()->after('approved_by');

            $table->foreign('approved_by')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();
        });

        /*
        |----------------------------------------------------------------------
        | UPDATE STATUS COLUMN
        | Jika status existing adalah varchar/string biasa, kita ubah
        | nilainya agar sesuai dengan workflow baru.
        | Jika sudah enum, perlu modifikasi enum — tergantung DB engine.
        |----------------------------------------------------------------------
        | Jalankan SQL berikut MANUAL jika kolom status adalah enum:
        |
        | ALTER TABLE budget_plans
        |   MODIFY COLUMN status ENUM(
        |     'DRAFT','PENDING_APPROVER','ACTIVE','PENDING_EXTEND','CLOSED'
        |   ) DEFAULT 'DRAFT';
        |
        | Atau jika varchar, tidak perlu diubah — controller akan
        | memvalidasi nilai yang diizinkan.
        |----------------------------------------------------------------------
        */
    }

    public function down(): void
    {
        Schema::table('budget_plans', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'code',
                'type',
                'approver_notes',
                'approved_by',
                'approved_at',
            ]);
        });
    }
};