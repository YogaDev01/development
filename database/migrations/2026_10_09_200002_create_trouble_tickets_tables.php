<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Trouble Ticket — tiket + seluruh jejak auditnya.
|
| Semua timestamp workflow diisi server (now()), tidak pernah dari klien.
| Durasi disimpan dalam DETIK (integer) saat difinalkan, supaya laporan bisa
| merata-rata langsung di SQL tanpa menghitung ulang.
|
| FK memakai RESTRICT: tiket tidak pernah dihapus keras (pembatalan = status
| CANCELLED), dan jejak audit tidak boleh ikut hilang bersama induknya.
|
| Dua aturan "paling banyak satu" dijaga basis data lewat kolom generated +
| unique index (NULL boleh berulang di MySQL):
|   - satu sesi kunjungan aktif (belum tiba) per tiket;
|   - satu pengajuan closed berstatus PENDING per tiket.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trouble_tickets')) {
            Schema::create('trouble_tickets', function (Blueprint $table) {
                $table->id();
                $table->string('tt_number', 30)->unique();

                $table->foreignId('site_id')->constrained('sites')->restrictOnDelete();
                // Disalin dari site saat dibuat — untuk filter/indeks; site
                // tetap sumber kebenarannya.
                $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
                $table->foreignId('category_id')->nullable()
                    ->constrained('trouble_ticket_categories')->restrictOnDelete();

                $table->string('priority', 10);
                $table->string('subject', 200);
                $table->text('description');
                // Pelapor di luar akun aplikasi (mis. customer/NOC); pembuat tiket = created_by.
                $table->string('reporter_name', 150)->nullable();
                $table->string('status', 20)->default('OPEN');

                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
                $table->timestamp('assigned_at')->nullable();

                // Respons pertama teknisi — ditulis SEKALI, tidak ikut berubah saat reassign.
                $table->timestamp('first_responded_at')->nullable();
                $table->foreignId('first_responded_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->unsignedInteger('response_seconds')->nullable();

                // Keputusan Visit / Non-Visit.
                $table->string('visit_decision', 10)->nullable();
                $table->foreignId('visit_decided_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('visit_decided_at')->nullable();
                $table->text('visit_decision_reason')->nullable();

                $table->timestamp('progress_started_at')->nullable();
                $table->timestamp('closure_requested_at')->nullable();
                $table->text('resolution_summary')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->unsignedInteger('resolution_seconds')->nullable();

                $table->timestamp('cancelled_at')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->string('cancel_reason', 500)->nullable();

                // Snapshot target SLA (menit) saat tiket dibuat.
                $table->foreignId('sla_policy_id')->nullable()
                    ->constrained('trouble_ticket_sla_policies')->restrictOnDelete();
                $table->unsignedInteger('sla_response_minutes')->nullable();
                $table->unsignedInteger('sla_travel_minutes')->nullable();
                $table->unsignedInteger('sla_resolution_minutes')->nullable();

                $table->timestamps();

                $table->index('status');
                $table->index('priority');
                $table->index(['assigned_to', 'status']);
                $table->index('created_at');
            });
        }

        if (! Schema::hasTable('trouble_ticket_assignments')) {
            Schema::create('trouble_ticket_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trouble_ticket_id')->constrained('trouble_tickets')->restrictOnDelete();
                $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('assigned_at');
                // Akses pertama teknisi pada penugasan INI (berbeda dari
                // trouble_tickets.first_responded_at yang hanya sekali per tiket).
                $table->timestamp('responded_at')->nullable();
                $table->timestamp('unassigned_at')->nullable();
                $table->string('reason', 500)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['trouble_ticket_id', 'unassigned_at']);
            });
        }

        if (! Schema::hasTable('trouble_ticket_messages')) {
            Schema::create('trouble_ticket_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trouble_ticket_id')->constrained('trouble_tickets')->restrictOnDelete();
                // NULL hanya untuk kejadian sistem tanpa pelaku.
                $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->string('actor_name')->nullable();
                $table->string('actor_role', 60)->nullable();
                $table->string('type', 10);            // MESSAGE | EVENT
                $table->string('event', 40)->nullable(); // kode kejadian, lihat TroubleTicketMessage::EVENTS
                $table->text('body')->nullable();
                $table->json('metadata')->nullable();
                // Token sekali-pakai dari form pesan: kirim ulang (klik ganda,
                // jaringan putus lalu dicoba lagi) tidak menghasilkan pesan kembar.
                $table->char('client_token', 36)->nullable();
                // Immutable: hanya created_at.
                $table->timestamp('created_at')->nullable();

                $table->index(['trouble_ticket_id', 'id']);
                $table->unique(['trouble_ticket_id', 'client_token']);
            });
        }

        if (! Schema::hasTable('trouble_ticket_attachments')) {
            Schema::create('trouble_ticket_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trouble_ticket_id')->constrained('trouble_tickets')->restrictOnDelete();
                $table->foreignId('message_id')->nullable()->constrained('trouble_ticket_messages')->restrictOnDelete();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->string('original_name');
                $table->string('storage_path')->unique(); // relatif terhadap disk secure
                $table->string('mime_type', 100)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->char('file_hash', 64)->nullable(); // sha256
                $table->timestamp('created_at')->nullable();

                $table->index('trouble_ticket_id');
            });
        }

        if (! Schema::hasTable('trouble_ticket_visits')) {
            Schema::create('trouble_ticket_visits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trouble_ticket_id')->constrained('trouble_tickets')->restrictOnDelete();
                $table->foreignId('technician_id')->constrained('users')->restrictOnDelete();
                $table->timestamp('departed_at');
                $table->timestamp('arrived_at')->nullable();
                $table->unsignedInteger('travel_seconds')->nullable();
                // Disiapkan untuk pencatatan lokasi; belum diisi pada MVP.
                $table->decimal('departure_latitude', 10, 7)->nullable();
                $table->decimal('departure_longitude', 10, 7)->nullable();
                $table->decimal('arrival_latitude', 10, 7)->nullable();
                $table->decimal('arrival_longitude', 10, 7)->nullable();
                $table->string('notes', 500)->nullable();
                $table->timestamps();

                // Satu sesi kunjungan aktif (belum tiba) per tiket.
                $table->unsignedBigInteger('active_ticket_id')->nullable()
                    ->storedAs('IF(arrived_at IS NULL, trouble_ticket_id, NULL)');
                $table->unique('active_ticket_id');
                $table->index('trouble_ticket_id');
            });
        }

        if (! Schema::hasTable('trouble_ticket_closure_reviews')) {
            Schema::create('trouble_ticket_closure_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trouble_ticket_id')->constrained('trouble_tickets')->restrictOnDelete();
                $table->unsignedSmallInteger('attempt_no');
                $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('requested_at');
                $table->text('resolution_summary');
                $table->string('review_status', 10)->default('PENDING'); // PENDING | APPROVED | REJECTED
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_notes')->nullable();
                $table->timestamps();

                // Satu pengajuan PENDING per tiket.
                $table->unsignedBigInteger('pending_ticket_id')->nullable()
                    ->storedAs("IF(review_status = 'PENDING', trouble_ticket_id, NULL)");
                $table->unique('pending_ticket_id');
                // Nama eksplisit: nama bawaan melebihi batas 64 karakter MySQL.
                $table->unique(['trouble_ticket_id', 'attempt_no'], 'tt_closure_reviews_ticket_attempt_unique');
                $table->index('review_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trouble_ticket_closure_reviews');
        Schema::dropIfExists('trouble_ticket_visits');
        Schema::dropIfExists('trouble_ticket_attachments');
        Schema::dropIfExists('trouble_ticket_messages');
        Schema::dropIfExists('trouble_ticket_assignments');
        Schema::dropIfExists('trouble_tickets');
    }
};
