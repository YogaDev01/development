<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah value 'RECEIVED' ke enum status (antara SENT dan PARTIALLY_PAID) — via
        // raw statement karena Laravel tidak punya cara native ubah enum tanpa doctrine/dbal.
        DB::statement("ALTER TABLE invoices MODIFY status ENUM(
            'DRAFT', 'SUBMITTED', 'VERIFIED', 'APPROVED', 'SENT', 'RECEIVED',
            'PARTIALLY_PAID', 'PAID', 'CANCELLED'
        ) NOT NULL DEFAULT 'DRAFT'");

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('received_by')->nullable()->after('sent_at')->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable()->after('received_by');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('received_by');
            $table->dropColumn('received_at');
        });

        DB::statement("ALTER TABLE invoices MODIFY status ENUM(
            'DRAFT', 'SUBMITTED', 'VERIFIED', 'APPROVED', 'SENT',
            'PARTIALLY_PAID', 'PAID', 'CANCELLED'
        ) NOT NULL DEFAULT 'DRAFT'");
    }
};
