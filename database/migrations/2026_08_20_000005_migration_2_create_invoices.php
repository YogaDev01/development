<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // 0001/KPA-0001/04/26

            $table->foreignId('po_id')->constrained('projects')->cascadeOnDelete();

            $table->enum('payment_term_type', ['Down Payment', 'Termin', 'Full Payment', 'Final Payment']);
            $table->unsignedInteger('termin_number')->nullable(); // hanya untuk type Termin

            $table->date('invoice_date');
            $table->date('due_date');

            // Kalkulasi (di-capture / snapshot, bukan dihitung ulang tiap load)
            $table->decimal('tax_rate', 5, 2)->default(11); // persen, bisa diubah per invoice (mis. non-PKP = 0)
            $table->unsignedBigInteger('subtotal')->default(0);       // sum(detail.total) sebelum pajak
            $table->unsignedBigInteger('tax_amount')->default(0);     // subtotal x tax_rate
            $table->unsignedBigInteger('captured_total')->default(0); // subtotal + tax_amount
            $table->unsignedBigInteger('total_deduction')->default(0);// sum(detail.deduction)

            // Flag "Additional" — tagihan melebihi sisa nilai kontrak PO (Opsi A: soft warning)
            $table->boolean('is_additional')->default(false);
            $table->unsignedBigInteger('additional_amount')->default(0);

            $table->text('remark')->nullable();

            $table->enum('status', [
                'DRAFT', 'SUBMITTED', 'VERIFIED', 'APPROVED', 'SENT',
                'PARTIALLY_PAID', 'PAID', 'CANCELLED',
            ])->default('DRAFT');
            // Catatan: OVERDUE dihitung on-the-fly dari due_date, bukan status tersimpan.

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();

            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();

            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();

            $table->timestamps();

            $table->index(['po_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};