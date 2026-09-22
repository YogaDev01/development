<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. ALTER budget_request - tambah timestamp tracking columns
        Schema::table('budget_request', function (Blueprint $table) {
            // Ubah status dari varchar menjadi enum (jika belum)
            if (!Schema::hasColumn('budget_request', 'submitted_at')) {
                $table->dropColumn('status');
                $table->enum('status', [
                    'DRAFT',
                    'PENDING_APPROVER',
                    'PENDING_FINANCE',
                    'APPROVED',
                    'REJECTED',
                    'PAID',
                    'REALIZED',
                    'CLOSED'
                ])->default('DRAFT')->after('budget_plan_id');
                
                // Timestamp tracking columns
                $table->timestamp('submitted_at')->nullable()->after('status');
                $table->timestamp('approved_at')->nullable()->after('submitted_at');
                $table->timestamp('paid_at')->nullable()->after('approved_at');
                $table->timestamp('realized_at')->nullable()->after('paid_at');
                $table->timestamp('closed_at')->nullable()->after('realized_at');
                
                $table->index('status');
            }
        });

        // 2. Create request_status_logs - Audit Trail
        if (!Schema::hasTable('request_status_logs')) {
            Schema::create('request_status_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                
                $table->unsignedBigInteger('budget_request_id');
                
                // Status transition
                $table->enum('status_from', [
                    'DRAFT',
                    'PENDING_APPROVER',
                    'PENDING_FINANCE',
                    'APPROVED',
                    'REJECTED',
                    'PAID',
                    'REALIZED',
                    'CLOSED'
                ])->nullable();
                
                $table->enum('status_to', [
                    'DRAFT',
                    'PENDING_APPROVER',
                    'PENDING_FINANCE',
                    'APPROVED',
                    'REJECTED',
                    'PAID',
                    'REALIZED',
                    'CLOSED'
                ]);
                
                // Action details
                $table->string('action_type');
                $table->text('notes')->nullable();
                
                // Actor information
                $table->unsignedBigInteger('acted_by');
                $table->string('actor_name');
                $table->string('actor_role');
                
                // Snapshot
                $table->json('request_snapshot')->nullable();
                
                $table->timestamp('created_at')->useCurrent();
                
                // Foreign key
                $table->foreign('budget_request_id')
                    ->references('id')
                    ->on('budget_request')
                    ->onDelete('cascade');
                
                // Indexes
                $table->index('budget_request_id');
                $table->index(['budget_request_id', 'status_to']);
                $table->index('acted_by');
                $table->index('action_type');
            });
        }

        // 3. ALTER request_attachments - tambah kolom type
        Schema::table('request_attachments', function (Blueprint $table) {

            if (Schema::hasColumn('request_attachments', 'type')) {

                // Lepas FK dulu
                $table->dropForeign(['budget_request_id']);

                // Baru hapus index
                $table->dropIndex('request_attachments_budget_request_id_type_index');

                // Hapus kolom
                $table->dropColumn('type');

                // Pasang kembali FK
                $table->foreign('budget_request_id')
                    ->references('id')
                    ->on('budget_request')
                    ->cascadeOnDelete();
            }

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_attachments', function (Blueprint $table) {
            if (Schema::hasColumn('request_attachments', 'type')) {
                $table->dropIndex(['budget_request_id', 'type']);
                $table->dropColumn('type');
            }
        });

        Schema::dropIfExists('request_status_logs');

        Schema::table('budget_request', function (Blueprint $table) {
            if (Schema::hasColumn('budget_request', 'submitted_at')) {
                $table->dropIndex(['status']);
                $table->dropColumn([
                    'submitted_at',
                    'approved_at',
                    'paid_at',
                    'realized_at',
                    'closed_at',
                    'status'
                ]);
                $table->string('status')->default('DRAFT');
            }
        });
    }
};