<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trouble Ticket — Pengajuan Biaya (pra-Cost Request). Murni additive.
 *
 * Pengajuan biaya terlepas dari Plan Visit: satu tiket boleh punya banyak
 * pengajuan. Revisi = baris item BARU (revision + 1); baris lama tidak diubah
 * atau dihapus. Cost Request hanya lahir saat pengajuan di-approve, dan
 * budget_request_id (UNIQUE, tanpa FK) mencegah satu CR terhubung ke dua
 * pengajuan.
 *
 * Tabel/kolom lama (trouble_ticket_plan_costs, trouble_tickets.plan_budget_*,
 * plan_cost_total, budget_request_id) TIDAK disentuh: data lama tetap terbaca.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['trouble_tickets', 'budget_plans', 'request_type', 'users'] as $tabel) {
            if (! Schema::hasTable($tabel)) {
                throw new \RuntimeException("Tabel {$tabel} tidak ada; jalankan migrasi sebelumnya dulu.");
            }
        }

        if (! Schema::hasTable('trouble_ticket_requests')) {
            Schema::create('trouble_ticket_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trouble_ticket_id')->constrained('trouble_tickets')->restrictOnDelete();
                $table->foreignId('budget_plan_id')->constrained('budget_plans')->restrictOnDelete();
                $table->unsignedBigInteger('cluster_id')->nullable();
                $table->foreignId('request_type_id')->constrained('request_type')->restrictOnDelete();
                $table->unsignedBigInteger('beneficiary_id')->nullable();
                $table->string('description', 500);
                $table->unsignedBigInteger('total')->default(0);
                // DRAFT | SUBMITTED | APPROVED | REJECTED | CANCELLED
                $table->string('status', 12)->default('DRAFT');
                $table->unsignedInteger('revision')->default(1);
                $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_notes')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancel_reason')->nullable();
                // CR hasil approve. UNIQUE tanpa FK: satu CR tidak bisa terhubung ke dua pengajuan.
                $table->unsignedBigInteger('budget_request_id')->nullable()->unique();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['trouble_ticket_id', 'status']);
            });

            Schema::table('trouble_ticket_requests', function (Blueprint $table) {
                $table->foreign('cluster_id')->references('id')->on('clusters')->nullOnDelete();
                $table->foreign('beneficiary_id')->references('id')->on('beneficiaries')->nullOnDelete();
                $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('cancelled_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('trouble_ticket_request_items')) {
            Schema::create('trouble_ticket_request_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trouble_ticket_request_id')->constrained('trouble_ticket_requests')->restrictOnDelete();
                $table->unsignedInteger('revision');
                $table->unsignedBigInteger('budget_detail_id')->nullable();
                $table->unsignedBigInteger('account_id')->nullable();
                $table->string('account_name')->nullable();
                $table->string('sub_item_name')->nullable();
                $table->string('description');
                $table->unsignedBigInteger('amount');
                $table->unsignedInteger('qty');
                $table->unsignedBigInteger('total');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['trouble_ticket_request_id', 'revision'], 'tt_req_items_req_rev_idx');
                $table->foreign('budget_detail_id')->references('id')->on('budget_plan_details')->nullOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('trouble_ticket_request_attachments')) {
            Schema::create('trouble_ticket_request_attachments', function (Blueprint $table) {
                $table->id();
                // Nama FK eksplisit: nama otomatis (69 karakter) melewati batas 64 karakter MySQL.
                $table->foreignId('trouble_ticket_request_id')
                    ->constrained('trouble_ticket_requests', 'id', 'tt_req_att_request_fk')
                    ->restrictOnDelete();
                $table->unsignedBigInteger('item_id')->nullable();
                // Berkas di disk "secure"; unduhan lewat controller Trouble Ticket (cek canView).
                $table->string('path');
                $table->string('original_name');
                $table->string('mime')->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();

                $table->foreign('item_id')->references('id')->on('trouble_ticket_request_items')->nullOnDelete();
                $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        // Pemulihan: bila run sebelumnya gagal tepat di FK lampiran, tabelnya sudah ada tanpa FK itu.
        if (Schema::hasTable('trouble_ticket_request_attachments')) {
            $adaFk = DB::table('information_schema.TABLE_CONSTRAINTS')
                ->where('CONSTRAINT_SCHEMA', DB::raw('DATABASE()'))
                ->where('TABLE_NAME', 'trouble_ticket_request_attachments')
                ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
                ->where('CONSTRAINT_NAME', 'tt_req_att_request_fk')
                ->exists();

            if (! $adaFk) {
                Schema::table('trouble_ticket_request_attachments', function (Blueprint $table) {
                    $table->foreign('trouble_ticket_request_id', 'tt_req_att_request_fk')
                        ->references('id')->on('trouble_ticket_requests')->restrictOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        // Hanya dihapus bila kosong: pengajuan biaya adalah catatan keuangan dan tidak boleh ikut hilang.
        foreach (['trouble_ticket_request_attachments', 'trouble_ticket_request_items', 'trouble_ticket_requests'] as $tabel) {
            if (Schema::hasTable($tabel) && DB::table($tabel)->exists()) {
                throw new \RuntimeException(
                    "Rollback migrasi dibatalkan: tabel {$tabel} masih berisi data pengajuan biaya. "
                    . 'Arsipkan/kosongkan oleh manusia lebih dulu.'
                );
            }
        }

        Schema::dropIfExists('trouble_ticket_request_attachments');
        Schema::dropIfExists('trouble_ticket_request_items');
        Schema::dropIfExists('trouble_ticket_requests');
    }
};
