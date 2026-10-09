<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Trouble Ticket — diselaraskan dengan skema referensi TT telekomunikasi
| (database `slameet`.`trouble_ticket`, keputusan 9 Okt 2026).
|
| 200001–200003 sudah berjalan di dev lokal, jadi tidak diedit; perubahan
| ditulis di sini. Saat ditulis, semua tabel TT masih KOSONG (0 baris), jadi
| rename/drop di bawah tidak menyentuh data. Kategori yang terlanjur ada
| disalin ke Problem sebelum tabel kategori dihapus (jaga-jaga).
|
|   tt_number            → ticket_number (varchar 20)
|   subject              → issue
|   priority             → severity (FK ke trouble_ticket_severities.code)
|   category_id          → problem_id (FK ke trouble_ticket_problems)
|   first_responded_at   → acknowledged_at, first_responded_by → acknowledged_by
|   closure_requested_at → resolved_at
|   + external_ref, pic_id, device, flag_alarm, rca, sub_rca, action, approval,
|     visit_site, target_online, reported_at
|
| Problem & Severity diisi LANGSUNG di basis data (tidak ada UI, tidak ada data
| awal). Severity disimpan sebagai kode (varchar) seperti referensi.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trouble_ticket_problems')) {
            Schema::create('trouble_ticket_problems', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                // Ajukan Closed wajib menyertakan lampiran bukti.
                $table->boolean('requires_evidence')->default(false);
                $table->string('status', 20)->default('ACTIVE');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('trouble_ticket_severities')) {
            Schema::create('trouble_ticket_severities', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->unique();   // disimpan di trouble_tickets.severity
                $table->string('name', 50);
                // 1 = paling parah. Menentukan urutan & warna di layar.
                $table->unsignedTinyInteger('level')->default(1);
                $table->string('status', 20)->default('ACTIVE');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('trouble_ticket_categories')) {
            foreach (DB::table('trouble_ticket_categories')->get() as $c) {
                DB::table('trouble_ticket_problems')->insertOrIgnore([
                    'name' => $c->name, 'requires_evidence' => $c->requires_evidence,
                    'status' => $c->status, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // ---- trouble_tickets
        if (Schema::hasColumn('trouble_tickets', 'category_id')) {
            Schema::table('trouble_tickets', function (Blueprint $table) {
                $table->dropConstrainedForeignId('category_id');
            });
        }

        if (Schema::hasColumn('trouble_tickets', 'tt_number')) {
            Schema::table('trouble_tickets', function (Blueprint $table) {
                $table->renameColumn('tt_number', 'ticket_number');
                $table->renameColumn('subject', 'issue');
                $table->renameColumn('priority', 'severity');
                $table->renameColumn('first_responded_at', 'acknowledged_at');
                $table->renameColumn('first_responded_by', 'acknowledged_by');
                $table->renameColumn('closure_requested_at', 'resolved_at');
            });

            Schema::table('trouble_tickets', function (Blueprint $table) {
                $table->string('ticket_number', 20)->change();
                $table->string('severity', 20)->change();
                $table->renameIndex('trouble_tickets_tt_number_unique', 'trouble_tickets_ticket_number_unique');
                $table->renameIndex('trouble_tickets_priority_index', 'trouble_tickets_severity_index');
            });
        }

        if (! Schema::hasColumn('trouble_tickets', 'external_ref')) {
            Schema::table('trouble_tickets', function (Blueprint $table) {
                // Nomor tiket dari customer/NOC.
                $table->string('external_ref', 40)->nullable()->unique()->after('ticket_number');
                $table->foreignId('problem_id')->nullable()->after('customer_id')
                    ->constrained('trouble_ticket_problems')->restrictOnDelete();
                // Baris master_pics teknisi yang ditugaskan (PIC internal site);
                // disinkronkan dengan assigned_to setiap kali ditugaskan.
                $table->foreignId('pic_id')->nullable()->after('assigned_to')
                    ->constrained('master_pics')->restrictOnDelete();
                $table->string('device', 40)->nullable()->after('issue');
                $table->string('flag_alarm', 40)->nullable()->after('severity');
                $table->dateTime('reported_at')->nullable()->after('reporter_name');
                $table->date('visit_site')->nullable()->after('visit_decision_reason');
                $table->date('target_online')->nullable()->after('visit_site');
                $table->string('rca', 40)->nullable()->after('resolved_at');
                $table->string('sub_rca', 150)->nullable()->after('rca');
                $table->string('action', 150)->nullable()->after('sub_rca');
                // Status pengajuan Closed terakhir: PENDING | APPROVED | REJECTED.
                $table->string('approval', 20)->nullable()->after('action');

                $table->foreign('severity')->references('code')->on('trouble_ticket_severities')
                    ->restrictOnDelete()->cascadeOnUpdate();
                $table->index(['status', 'reported_at']);
                $table->index('reported_at');
            });
        }

        // ---- closure reviews: RCA/action per pengajuan; ringkasan jadi opsional.
        if (! Schema::hasColumn('trouble_ticket_closure_reviews', 'rca')) {
            Schema::table('trouble_ticket_closure_reviews', function (Blueprint $table) {
                $table->string('rca', 40)->nullable()->after('requested_at');
                $table->string('sub_rca', 150)->nullable()->after('rca');
                $table->string('action', 150)->nullable()->after('sub_rca');
                $table->text('resolution_summary')->nullable()->change();
            });
        }

        // ---- SLA: per severity (+ opsional per problem).
        if (Schema::hasColumn('trouble_ticket_sla_policies', 'category_id')) {
            Schema::table('trouble_ticket_sla_policies', function (Blueprint $table) {
                $table->dropIndex(['priority', 'status']);
                $table->dropConstrainedForeignId('category_id');
                $table->renameColumn('priority', 'severity');
            });
            Schema::table('trouble_ticket_sla_policies', function (Blueprint $table) {
                $table->string('severity', 20)->change();
                $table->foreignId('problem_id')->nullable()->after('severity')
                    ->constrained('trouble_ticket_problems')->restrictOnDelete();
                $table->index(['severity', 'status']);
            });
        }

        Schema::dropIfExists('trouble_ticket_categories');
    }

    public function down(): void
    {
        if (! Schema::hasTable('trouble_ticket_categories')) {
            Schema::create('trouble_ticket_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->boolean('requires_evidence')->default(false);
                $table->string('status', 20)->default('ACTIVE');
                $table->timestamps();
            });
        }

        Schema::table('trouble_ticket_sla_policies', function (Blueprint $table) {
            $table->dropIndex(['severity', 'status']);
            $table->dropConstrainedForeignId('problem_id');
            $table->renameColumn('severity', 'priority');
        });
        Schema::table('trouble_ticket_sla_policies', function (Blueprint $table) {
            $table->string('priority', 10)->change();
            $table->foreignId('category_id')->nullable()->after('priority')
                ->constrained('trouble_ticket_categories')->restrictOnDelete();
            $table->index(['priority', 'status']);
        });

        Schema::table('trouble_ticket_closure_reviews', function (Blueprint $table) {
            $table->dropColumn(['rca', 'sub_rca', 'action']);
        });

        Schema::table('trouble_tickets', function (Blueprint $table) {
            $table->dropForeign(['severity']);
            $table->dropIndex(['status', 'reported_at']);
            $table->dropIndex(['reported_at']);
            $table->dropUnique(['external_ref']);
            $table->dropConstrainedForeignId('problem_id');
            $table->dropConstrainedForeignId('pic_id');
            $table->dropColumn([
                'external_ref', 'device', 'flag_alarm', 'reported_at', 'visit_site',
                'target_online', 'rca', 'sub_rca', 'action', 'approval',
            ]);
        });
        Schema::table('trouble_tickets', function (Blueprint $table) {
            $table->renameIndex('trouble_tickets_ticket_number_unique', 'trouble_tickets_tt_number_unique');
            $table->renameIndex('trouble_tickets_severity_index', 'trouble_tickets_priority_index');
            $table->renameColumn('ticket_number', 'tt_number');
            $table->renameColumn('issue', 'subject');
            $table->renameColumn('severity', 'priority');
            $table->renameColumn('acknowledged_at', 'first_responded_at');
            $table->renameColumn('acknowledged_by', 'first_responded_by');
            $table->renameColumn('resolved_at', 'closure_requested_at');
        });
        Schema::table('trouble_tickets', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('customer_id')
                ->constrained('trouble_ticket_categories')->restrictOnDelete();
        });

        Schema::dropIfExists('trouble_ticket_severities');
        Schema::dropIfExists('trouble_ticket_problems');
    }
};
