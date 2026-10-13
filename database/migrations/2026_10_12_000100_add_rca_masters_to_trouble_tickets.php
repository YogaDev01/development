<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Trouble Ticket — master RCA (keputusan 12 Okt 2026, sumber: Daftar RCA
| Paramitra Iintimega / "Daftar_RCA_Lengkap.xlsx").
|
| Empat master baru, diisi TroubleTicketRcaSeeder:
|   trouble_ticket_rcas      — 5 RCA (POWER, HARDWARE, TRANSMISSION, …)
|   trouble_ticket_sub_rcas  — 36 Sub-RCA, milik satu RCA
|   trouble_ticket_issues    — 55 Initial Problem (pengganti free-text `issue`)
|   trouble_ticket_devices   — daftar perangkat (pengganti free-text `device`)
|
| trouble_tickets mendapat rca_id / sub_rca_id / issue_id / device_id. Kolom
| teks lama (`issue`, `device`, `rca`, `sub_rca`, `action`) SENGAJA tidak
| dihapus: 12 tiket lama masih memakainya, dan `rca`/`sub_rca`/`action` tetap
| dipakai alur penutupan tiket.
|
| `severity` menjadi nullable: sejak sekarang severity TIDAK diisi di form,
| melainkan dihitung dari aging tiket (TroubleTicket::SEVERITY_AGING). Target
| SLA tiket baru diambil per Problem saja — lihat
| TroubleTicketSlaPolicy::applicableForProblem(). Baris lama tidak diubah.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trouble_ticket_rcas')) {
            Schema::create('trouble_ticket_rcas', function (Blueprint $table) {
                $table->id();
                $table->string('name', 60)->unique();
                $table->string('status', 20)->default('ACTIVE');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('trouble_ticket_sub_rcas')) {
            Schema::create('trouble_ticket_sub_rcas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rca_id')->constrained('trouble_ticket_rcas')->restrictOnDelete();
                $table->string('name', 150);
                $table->string('status', 20)->default('ACTIVE');
                $table->timestamps();
                $table->unique(['rca_id', 'name']);
            });
        }

        if (! Schema::hasTable('trouble_ticket_issues')) {
            Schema::create('trouble_ticket_issues', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150)->unique();
                $table->string('status', 20)->default('ACTIVE');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('trouble_ticket_devices')) {
            Schema::create('trouble_ticket_devices', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->string('status', 20)->default('ACTIVE');
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('trouble_tickets', 'issue_id')) {
            Schema::table('trouble_tickets', function (Blueprint $table) {
                $table->foreignId('issue_id')->nullable()->after('problem_id')
                    ->constrained('trouble_ticket_issues')->restrictOnDelete();
                $table->foreignId('device_id')->nullable()->after('device')
                    ->constrained('trouble_ticket_devices')->restrictOnDelete();
                $table->foreignId('rca_id')->nullable()->after('resolved_at')
                    ->constrained('trouble_ticket_rcas')->restrictOnDelete();
                $table->foreignId('sub_rca_id')->nullable()->after('rca_id')
                    ->constrained('trouble_ticket_sub_rcas')->restrictOnDelete();
            });
        }

        // Severity tidak lagi diisi dari form; kolomnya boleh kosong.
        Schema::table('trouble_tickets', function (Blueprint $table) {
            $table->string('severity', 20)->nullable()->change();
        });

        // Issue bebas tidak lagi wajib (digantikan issue_id).
        Schema::table('trouble_tickets', function (Blueprint $table) {
            $table->string('issue', 200)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('trouble_tickets', 'issue_id')) {
            Schema::table('trouble_tickets', function (Blueprint $table) {
                $table->dropConstrainedForeignId('issue_id');
                $table->dropConstrainedForeignId('device_id');
                $table->dropConstrainedForeignId('rca_id');
                $table->dropConstrainedForeignId('sub_rca_id');
            });
        }

        Schema::dropIfExists('trouble_ticket_sub_rcas');
        Schema::dropIfExists('trouble_ticket_rcas');
        Schema::dropIfExists('trouble_ticket_issues');
        Schema::dropIfExists('trouble_ticket_devices');

        // severity/issue dikembalikan NOT NULL hanya bila tidak ada baris kosong.
        if (! DB::table('trouble_tickets')->whereNull('severity')->exists()) {
            Schema::table('trouble_tickets', function (Blueprint $table) {
                $table->string('severity', 20)->nullable(false)->change();
            });
        }
        if (! DB::table('trouble_tickets')->whereNull('issue')->exists()) {
            Schema::table('trouble_tickets', function (Blueprint $table) {
                $table->string('issue', 200)->nullable(false)->change();
            });
        }
    }
};
