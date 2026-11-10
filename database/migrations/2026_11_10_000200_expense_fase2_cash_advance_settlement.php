<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expense (Fase 2) — Cash Advance + Settlement + Second Payment
 * (rencana: docs/plans/2026-11-10-expense-module.md).
 *
 * Alur Expense Cash Advance:
 *   APPROVED -> QUEUED -> PAID (batch Treasury dibayar)
 *   -> SETTLE_SUBMITTED (Finance Manager input realisasi)
 *   -> CEO approve:  realisasi <= nominal  -> CLOSED (kelebihan dicatat refund_amount)
 *                    realisasi >  nominal  -> SETTLE_APPROVED + Second Payment PENDING
 *                                             -> Treasury bayar -> CLOSED
 *   -> CEO tolak: kembali PAID.
 *
 * Perubahan skema (TANPA mengubah isi data lama; semua baris lama is_cash_advance = 0):
 *   cost_registers          + is_cash_advance, settled_amount, refund_amount, shortfall_amount,
 *                             settle_submitted_by/at, settle_approved_by/at;
 *                           ~ status ENUM + PAID, SETTLE_SUBMITTED, SETTLE_APPROVED.
 *   cost_register_settlement_details      rincian realisasi (struktur = cost_register_details).
 *   cost_register_settlement_attachments  bukti realisasi (disk secure).
 *   cost_register_second_payments         kekurangan bayar, dibayar lewat Treasury.
 *   daily_transaction_items + cost_register_second_payment_id (FK RESTRICT). Baris ini
 *                           SEKALIGUS mengisi cost_register_id (Expense induk) — pola
 *                           yang sama dengan second_payment_id milik Cost Request.
 * Uang = integer rupiah (BIGINT UNSIGNED).
 *
 * down(): menolak berjalan bila sudah ada Expense berstatus baru atau data settlement.
 */
return new class extends Migration
{
    private const LAMA = ['DRAFT', 'VERIFIED', 'APPROVED', 'QUEUED', 'CLOSED', 'CANCELLED'];
    private const BARU = ['DRAFT', 'VERIFIED', 'APPROVED', 'QUEUED', 'PAID', 'SETTLE_SUBMITTED', 'SETTLE_APPROVED', 'CLOSED', 'CANCELLED'];

    private function enum(array $nilai): string
    {
        return "ENUM('" . implode("','", $nilai) . "')";
    }

    public function up(): void
    {
        if (! Schema::hasTable('cost_registers')) {
            return;
        }

        Schema::table('cost_registers', function (Blueprint $table) {
            if (! Schema::hasColumn('cost_registers', 'is_cash_advance')) {
                $table->boolean('is_cash_advance')->default(false)->after('type');
                $table->unsignedBigInteger('settled_amount')->nullable()->after('amount');
                $table->unsignedBigInteger('refund_amount')->nullable()->after('settled_amount');
                $table->unsignedBigInteger('shortfall_amount')->nullable()->after('refund_amount');
                $table->foreignId('settle_submitted_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
                $table->timestamp('settle_submitted_at')->nullable()->after('settle_submitted_by');
                $table->foreignId('settle_approved_by')->nullable()->after('settle_submitted_at')->constrained('users')->nullOnDelete();
                $table->timestamp('settle_approved_at')->nullable()->after('settle_approved_by');
            }
        });

        DB::statement('ALTER TABLE cost_registers MODIFY status ' . $this->enum(self::BARU) . " NOT NULL DEFAULT 'DRAFT'");

        if (! Schema::hasTable('cost_register_settlement_details')) {
            Schema::create('cost_register_settlement_details', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cost_register_id')->constrained('cost_registers')->restrictOnDelete();
                $table->foreignId('account_id')->nullable()->constrained('accounts')->restrictOnDelete();
                $table->foreignId('project_id')->nullable()->constrained('projects')->restrictOnDelete();
                $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->restrictOnDelete();
                $table->string('cost_category', 20)->nullable();
                $table->text('description');
                $table->unsignedInteger('qty')->default(1);
                $table->unsignedBigInteger('unit_price')->default(0);
                $table->unsignedBigInteger('total')->default(0);
                $table->timestamps();

                $table->index('cost_register_id');
            });
        }

        if (! Schema::hasTable('cost_register_settlement_attachments')) {
            Schema::create('cost_register_settlement_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cost_register_id')->constrained('cost_registers')->restrictOnDelete();
                $table->string('title');
                $table->string('file_path');
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('cost_register_id');
            });
        }

        if (! Schema::hasTable('cost_register_second_payments')) {
            Schema::create('cost_register_second_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cost_register_id')->constrained('cost_registers')->restrictOnDelete();
                $table->unsignedBigInteger('amount');
                $table->enum('status', ['PENDING', 'PAID'])->default('PENDING');
                $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('paid_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['cost_register_id', 'status']);
            });
        }

        if (! Schema::hasColumn('daily_transaction_items', 'cost_register_second_payment_id')) {
            Schema::table('daily_transaction_items', function (Blueprint $table) {
                $table->foreignId('cost_register_second_payment_id')->nullable()->after('second_payment_id')
                    ->constrained('cost_register_second_payments')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('cost_registers')) {
            return;
        }

        $statusBaru = DB::table('cost_registers')->whereIn('status', ['PAID', 'SETTLE_SUBMITTED', 'SETTLE_APPROVED'])->count();
        $settlement = Schema::hasTable('cost_register_settlement_details')
            ? DB::table('cost_register_settlement_details')->count() : 0;
        $sp = Schema::hasTable('cost_register_second_payments')
            ? DB::table('cost_register_second_payments')->count() : 0;

        if ($statusBaru > 0 || $settlement > 0 || $sp > 0) {
            throw new \RuntimeException(
                "Rollback Expense Fase 2 dibatalkan: {$statusBaru} Expense berstatus PAID/SETTLE_*, "
                . "{$settlement} rincian settlement, {$sp} Second Payment. Data akan hilang."
            );
        }

        if (Schema::hasColumn('daily_transaction_items', 'cost_register_second_payment_id')) {
            Schema::table('daily_transaction_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('cost_register_second_payment_id');
            });
        }

        Schema::dropIfExists('cost_register_second_payments');
        Schema::dropIfExists('cost_register_settlement_attachments');
        Schema::dropIfExists('cost_register_settlement_details');

        DB::statement('ALTER TABLE cost_registers MODIFY status ' . $this->enum(self::LAMA) . " NOT NULL DEFAULT 'DRAFT'");

        Schema::table('cost_registers', function (Blueprint $table) {
            if (Schema::hasColumn('cost_registers', 'is_cash_advance')) {
                $table->dropConstrainedForeignId('settle_submitted_by');
                $table->dropConstrainedForeignId('settle_approved_by');
                $table->dropColumn([
                    'is_cash_advance', 'settled_amount', 'refund_amount', 'shortfall_amount',
                    'settle_submitted_at', 'settle_approved_at',
                ]);
            }
        });
    }
};
