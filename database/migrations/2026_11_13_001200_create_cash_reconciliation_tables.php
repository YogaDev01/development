<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash & Bank Fase 3 — rekonsiliasi bank.
 *
 * Tabel ini TIDAK mengubah ledger: hanya membandingkan ledger TESYS
 * (CashFlowService) dengan rekening koran. Jaminan penting:
 *  - satu baris ledger hanya bisa dicocokkan SEKALI per rekening
 *    (unique source_fund_id + ledger_key);
 *  - satu baris rekening koran yang sama tidak bisa diimpor dua kali
 *    (unique source_fund_id + dedupe_hash);
 *  - rekonsiliasi APPROVED terkunci; pembukaan ulang tercatat di log.
 * Nominal bigInteger (rupiah). Tanpa soft delete: hanya rekonsiliasi DRAFT yang
 * boleh kehilangan data (pencocokan dilepas, impor dibatalkan), dan keduanya
 * tercatat di cash_reconciliation_logs. APPROVED/SUBMITTED tidak bisa diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cash_reconciliations')) {
            Schema::create('cash_reconciliations', function (Blueprint $table) {
                $table->id();
                $table->string('number', 30)->unique();
                $table->foreignId('source_fund_id')->constrained('source_funds')->restrictOnDelete();
                $table->date('period_start');
                $table->date('period_end');
                $table->bigInteger('statement_ending_balance');
                $table->string('status', 20)->default('DRAFT'); // DRAFT | SUBMITTED | APPROVED
                $table->string('exception_reason', 500)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->index(['source_fund_id', 'status', 'period_end']);
            });
        }

        if (! Schema::hasTable('bank_statement_imports')) {
            Schema::create('bank_statement_imports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('reconciliation_id')->constrained('cash_reconciliations')->restrictOnDelete();
                $table->string('filename');
                $table->unsignedInteger('rows_imported')->default(0);
                $table->unsignedInteger('rows_skipped')->default(0);
                $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bank_statement_lines')) {
            Schema::create('bank_statement_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('reconciliation_id')->constrained('cash_reconciliations')->restrictOnDelete();
                $table->foreignId('import_id')->nullable()->constrained('bank_statement_imports')->nullOnDelete();
                $table->foreignId('source_fund_id')->constrained('source_funds')->restrictOnDelete();
                $table->date('txn_date');
                $table->string('description', 500)->nullable();
                $table->string('reference', 120)->nullable();
                $table->unsignedBigInteger('debit')->default(0);   // uang keluar dari rekening
                $table->unsignedBigInteger('credit')->default(0);  // uang masuk ke rekening
                $table->bigInteger('balance')->nullable();
                $table->string('dedupe_hash', 40);
                $table->boolean('investigating')->default(false);
                $table->string('note', 255)->nullable();
                $table->timestamps();
                $table->unique(['source_fund_id', 'dedupe_hash']);
                $table->index('reconciliation_id');
            });
        }

        if (! Schema::hasTable('cash_reconciliation_matches')) {
            Schema::create('cash_reconciliation_matches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('reconciliation_id')->constrained('cash_reconciliations')->restrictOnDelete();
                $table->foreignId('source_fund_id')->constrained('source_funds')->restrictOnDelete();
                $table->foreignId('bank_line_id')->constrained('bank_statement_lines')->restrictOnDelete();
                $table->string('ledger_key', 191);
                $table->string('ledger_module', 40);
                $table->unsignedBigInteger('ledger_source_id');
                $table->string('ledger_direction', 3);
                $table->unsignedBigInteger('ledger_amount');
                $table->date('ledger_date');
                $table->string('ledger_reference')->nullable();
                $table->string('method', 10)->default('MANUAL'); // AUTO | MANUAL
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                // Satu baris ledger hanya boleh dicocokkan sekali per rekening.
                $table->unique(['source_fund_id', 'ledger_key']);
                $table->index('bank_line_id');
            });
        }

        if (! Schema::hasTable('cash_reconciliation_logs')) {
            Schema::create('cash_reconciliation_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('reconciliation_id')->constrained('cash_reconciliations')->restrictOnDelete();
                $table->string('action', 30);
                $table->string('notes', 500)->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_reconciliation_logs');
        Schema::dropIfExists('cash_reconciliation_matches');
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statement_imports');
        Schema::dropIfExists('cash_reconciliations');
    }
};
