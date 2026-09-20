<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // SJ-0001/08/26

            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            // SENT   = dibuat, invoice terkait ikut ditandai SENT.
            // RECEIVED = customer sudah menerima fisik dokumen (evidence wajib dilampirkan).
            $table->enum('status', ['SENT', 'RECEIVED'])->default('SENT');

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();

            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();

            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_notes');
    }
};
