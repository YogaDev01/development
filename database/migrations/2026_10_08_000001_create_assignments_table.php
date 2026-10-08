<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Assignment — penugasan bebas antar-user yang muncul di Todo List
 * penerimanya. Bukan dokumen keuangan: tidak pakai SoftDeletes seperti
 * budget_request/invoices/cash_receipts, cukup status OPEN/DONE/CANCELLED.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assignments')) {
            return;
        }

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();

            // Pemberi & penerima tugas. RESTRICT: user yang masih punya tugas
            // tidak boleh terhapus — sejalan dengan kebiasaan proyek yang
            // menonaktifkan user (status) alih-alih menghapusnya.
            $table->foreignId('assigner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assignee_id')->constrained('users')->restrictOnDelete();

            $table->date('due_date')->nullable();
            $table->string('priority', 10)->default('MEDIUM'); // LOW | MEDIUM | HIGH
            $table->string('status', 12)->default('OPEN');     // OPEN | DONE | CANCELLED

            $table->timestamp('done_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            // Antrean penerima (Todo List) & daftar milik pembuat.
            $table->index(['assignee_id', 'status']);
            $table->index(['assigner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
