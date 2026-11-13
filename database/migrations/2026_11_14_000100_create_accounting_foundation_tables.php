<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounting Fase 1 — fondasi COA, periode, mapping, dan struktur jurnal (2026-11-14).
 *
 * ADITIF: hanya tabel baru, tidak menyentuh tabel transaksi yang sudah ada.
 *
 * SATUAN UANG: seluruh kolom nominal di tabel akuntansi adalah BIGINT dalam
 * SEN (rupiah x 100), bukan rupiah. Bank memotong/menambah dengan sen (bunga,
 * pajak bunga), dan angka laporan harus sama dengan rekening koran sampai dua
 * digit. Tetap integer (tidak ada decimal/float). Konversi rupiah <-> sen hanya
 * lewat App\Support\AccountingMoney. Sumber (Treasury/Cash Receipt) tetap
 * rupiah bulat dan dikali 100 di pembuat jurnal.
 *
 * Semua FK ke akun / jurnal = RESTRICT (CLAUDE.md §5): catatan keuangan tidak
 * ikut terhapus. Jurnal POSTED tidak pernah diubah/dihapus; koreksi lewat
 * jurnal pembalik.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coa_accounts')) {
            Schema::create('coa_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->unique();
                $table->string('name');
                // ASSET | LIABILITY | EQUITY | REVENUE | EXPENSE
                $table->string('account_type', 12);
                $table->string('category', 100)->nullable();
                $table->foreignId('parent_id')->nullable()->constrained('coa_accounts')->restrictOnDelete();
                $table->string('normal_balance', 6);          // DEBIT | CREDIT
                $table->text('description')->nullable();
                // Akun induk (header) tidak boleh dipakai di baris jurnal.
                $table->boolean('is_postable')->default(true);
                // Akun kas/bank: menautkan satu rekening Cash & Bank ke satu akun.
                $table->foreignId('source_fund_id')->nullable()->unique()->constrained('source_funds')->restrictOnDelete();
                $table->string('status', 10)->default('ACTIVE'); // ACTIVE | INACTIVE
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['account_type', 'status']);
            });
        }

        if (! Schema::hasTable('accounting_periods')) {
            Schema::create('accounting_periods', function (Blueprint $table) {
                $table->id();
                $table->string('name', 60);
                $table->date('start_date');
                $table->date('end_date');
                $table->string('status', 10)->default('OPEN'); // OPEN | CLOSED | LOCKED
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('closed_at')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['start_date', 'end_date']);
                $table->index(['start_date', 'end_date']);
            });
        }

        if (! Schema::hasTable('account_mappings')) {
            Schema::create('account_mappings', function (Blueprint $table) {
                $table->id();
                $table->string('source_module', 20);            // TREASURY | CASH_RECEIPT
                $table->string('transaction_type', 40);         // lihat App\Support\AccountingCatalog
                // Pembeda tambahan (mis. 'request_type:5'); NULL = berlaku umum.
                $table->string('match_key', 60)->nullable();
                // Sisi bank diambil dari rekening sumber transaksi (coa_accounts.source_fund_id),
                // bukan akun tetap; sisi lain = akun tertaut.
                $table->foreignId('debit_account_id')->nullable()->constrained('coa_accounts')->restrictOnDelete();
                $table->foreignId('credit_account_id')->nullable()->constrained('coa_accounts')->restrictOnDelete();
                $table->boolean('debit_is_bank')->default(false);
                $table->boolean('credit_is_bank')->default(false);
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('status', 10)->default('ACTIVE');
                $table->string('description')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['source_module', 'transaction_type', 'status'], 'am_lookup_idx');
            });
        }

        if (! Schema::hasTable('journals')) {
            Schema::create('journals', function (Blueprint $table) {
                $table->id();
                $table->string('journal_number', 30)->unique();
                // Tanggal AKUNTANSI (boleh backdate); laporan memakai ini, bukan created_at.
                $table->date('journal_date');
                $table->foreignId('accounting_period_id')->nullable()->constrained('accounting_periods')->restrictOnDelete();
                // SOURCE | GENERAL | ACCRUAL | ADJUSTMENT | RECLASSIFICATION | DEPRECIATION | REVERSAL | OPENING
                $table->string('journal_type', 20)->default('SOURCE');
                // DRAFT | SUBMITTED | POSTED | REVERSED
                $table->string('status', 12)->default('DRAFT');
                $table->string('reference_number', 60)->nullable();
                $table->string('description', 500)->nullable();
                $table->unsignedBigInteger('total_debit')->default(0);   // sen
                $table->unsignedBigInteger('total_credit')->default(0);  // sen
                $table->foreignId('reverses_journal_id')->nullable()->constrained('journals')->restrictOnDelete();
                // Sumber berubah setelah jurnal dibuat -> wajib ditinjau ulang sebelum posting.
                $table->boolean('needs_review')->default(false);
                $table->string('review_reason')->nullable();
                $table->string('return_reason')->nullable();             // alasan dikembalikan ke Draft
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('posted_at')->nullable();
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('delete_reason')->nullable();
                $table->softDeletes();
                $table->timestamps();
                $table->index(['status', 'journal_date']);
                $table->index('journal_date');
            });
        }

        if (! Schema::hasTable('journal_lines')) {
            Schema::create('journal_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('journal_id')->constrained('journals')->restrictOnDelete();
                $table->unsignedSmallInteger('line_no')->default(1);
                $table->foreignId('coa_account_id')->constrained('coa_accounts')->restrictOnDelete();
                // Akun usulan mapping; beda dengan coa_account_id = klasifikasi diubah user (tercatat).
                $table->foreignId('suggested_account_id')->nullable()->constrained('coa_accounts')->restrictOnDelete();
                $table->string('description', 255)->nullable();
                $table->unsignedBigInteger('debit')->default(0);   // sen
                $table->unsignedBigInteger('credit')->default(0);  // sen
                $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->restrictOnDelete();
                $table->foreignId('project_id')->nullable()->constrained('projects')->restrictOnDelete();
                $table->timestamps();
                $table->index(['coa_account_id', 'journal_id']);
            });
        }

        if (! Schema::hasTable('journal_sources')) {
            Schema::create('journal_sources', function (Blueprint $table) {
                $table->id();
                $table->foreignId('journal_id')->constrained('journals')->restrictOnDelete();
                $table->string('source_module', 20);
                $table->string('source_type', 40);                 // mis. daily_transactions
                $table->unsignedBigInteger('source_id');
                $table->unsignedBigInteger('source_line_id')->default(0); // item/alokasi; 0 = tanpa baris
                $table->string('source_number', 60)->nullable();
                // Kunci anti-dobel: terisi selama sumber "dipakai" jurnal Draft/Submitted/Posted;
                // dikosongkan (NULL) bila jurnal dihapus/dibalik. UNIQUE membiarkan banyak NULL
                // tetapi menolak dua jurnal aktif untuk sumber yang sama, juga saat klik ganda
                // atau request bersamaan.
                $table->string('active_key', 100)->nullable()->unique();
                $table->timestamps();
                $table->index(['source_type', 'source_id']);
            });
        }

        if (! Schema::hasTable('accounting_audit_logs')) {
            Schema::create('accounting_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type', 30);                 // journal | coa_account | account_mapping | period
                $table->unsignedBigInteger('entity_id');
                $table->string('action', 40);
                $table->string('from_status', 12)->nullable();
                $table->string('to_status', 12)->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('reason', 500)->nullable();
                $table->string('source_ref', 100)->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->nullable();      // log tidak pernah diubah
                $table->index(['entity_type', 'entity_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_audit_logs');
        Schema::dropIfExists('journal_sources');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journals');
        Schema::dropIfExists('account_mappings');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('coa_accounts');
    }
};
