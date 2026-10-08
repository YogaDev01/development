<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Workflow approval PO jadi dua langkah: Finance VERIFY (SUBMITTED →
     * VERIFIED) lalu Approver APPROVE (VERIFIED → Early Start / Waiting PO /
     * Active). Langkah verify perlu jejak siapa & kapan, sejajar dengan
     * approved_by/approved_at yang sudah ada. Additive & nullable.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'verified_by')) {
                $table->foreignId('verified_by')
                    ->nullable()
                    ->after('submitted_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('projects', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verified_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'verified_by')) {
                $table->dropConstrainedForeignId('verified_by');
            }
            if (Schema::hasColumn('projects', 'verified_at')) {
                $table->dropColumn('verified_at');
            }
        });
    }
};
