<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Review settlement oleh atasan teknisi SEBELUM Cost Request menjadi REALIZED.
 * Tidak menambah status Cost Request: workflow Finance tidak berubah (K1).
 * Additive; NULL = belum pernah dikirim.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trouble_ticket_requests') && ! Schema::hasColumn('trouble_ticket_requests', 'settlement_review_status')) {
            Schema::table('trouble_ticket_requests', function (Blueprint $table) {
                // PENDING | APPROVED | REJECTED
                $table->string('settlement_review_status', 12)->nullable()->after('budget_request_id');
                $table->unsignedBigInteger('settlement_reviewed_by')->nullable()->after('settlement_review_status');
                $table->timestamp('settlement_reviewed_at')->nullable()->after('settlement_reviewed_by');
                $table->text('settlement_review_notes')->nullable()->after('settlement_reviewed_at');

                $table->foreign('settlement_reviewed_by', 'tt_req_settle_reviewer_fk')->references('id')->on('users')->nullOnDelete();
                $table->index('settlement_review_status', 'tt_req_settle_review_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('trouble_ticket_requests', 'settlement_review_status')) {
            Schema::table('trouble_ticket_requests', function (Blueprint $table) {
                $table->dropForeign('tt_req_settle_reviewer_fk');
                $table->dropIndex('tt_req_settle_review_idx');
                $table->dropColumn(['settlement_review_status', 'settlement_reviewed_by', 'settlement_reviewed_at', 'settlement_review_notes']);
            });
        }
    }
};
