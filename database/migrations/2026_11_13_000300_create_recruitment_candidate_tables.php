<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recruitment & Kandidat (HRIS) — tahap 2b (2026-11-13).
 *
 * Aditif: empat tabel baru + dua menu ('recruitment', 'candidate-pool').
 * Tidak mengubah tabel lama. Menyambung ke manpower_request_details lewat
 * recruitments.detail_id; saat kandidat di-hire, baris employees baru dibuat
 * oleh RecruitmentController (status INACTIVE, lihat catatan di sana).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('candidates')) {
            Schema::create('candidates', function (Blueprint $table) {
                $table->id();
                $table->string('code', 12)->unique();                 // CND-0001
                $table->string('name', 150);
                $table->text('address')->nullable();
                $table->string('phone', 30);
                $table->string('birth_place', 100)->nullable();
                $table->date('birth_date');
                $table->string('gender', 1)->nullable();              // L | P
                $table->string('marital_status', 20)->nullable();
                $table->string('education', 20)->nullable();
                $table->string('position', 100)->nullable();
                $table->string('grade', 30)->nullable();
                $table->string('source', 30)->nullable();
                $table->unsignedBigInteger('hired_employee_id')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->foreign('hired_employee_id')->references('id')->on('employees')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('candidate_documents')) {
            Schema::create('candidate_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
                $table->string('path', 255);                          // disk 'secure'
                $table->string('original_name', 255);
                $table->string('mime', 100)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('recruitments')) {
            Schema::create('recruitments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('detail_id')->constrained('manpower_request_details')->restrictOnDelete();
                $table->foreignId('candidate_id')->constrained('candidates')->restrictOnDelete();
                $table->string('stage', 20)->default('SCREENING');
                // ON_PROCESS | APPROVED (hired) | REJECTED | CANCELED | RETURNED (posisi terisi kandidat lain)
                $table->string('status', 15)->default('ON_PROCESS')->index();
                $table->date('scheduled')->nullable();
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->date('decided_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('candidate_logs')) {
            Schema::create('candidate_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('description');
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (Schema::hasTable('menu_items')) {
            foreach ([
                ['recruitment', 'Recruitment', 'fas fa-people-arrows', '/recruitment', 90],
                ['candidate-pool', 'Kandidat', 'fas fa-address-book', '/candidates', 100],
            ] as [$key, $label, $icon, $route, $sort]) {
                if (! DB::table('menu_items')->where('key', $key)->exists()) {
                    DB::table('menu_items')->insert([
                        'key' => $key, 'label' => $label, 'icon' => $icon, 'route' => $route,
                        'section' => 'hr', 'sort_order' => $sort, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /** Tidak menghapus tabel yang sudah berisi kandidat. */
    public function down(): void
    {
        if (Schema::hasTable('candidates') && DB::table('candidates')->exists()) {
            throw new RuntimeException('Rollback dibatalkan: tabel candidates sudah berisi data.');
        }

        if (Schema::hasTable('menu_items')) {
            foreach (['recruitment', 'candidate-pool'] as $key) {
                $id = DB::table('menu_items')->where('key', $key)->value('id');
                if ($id) {
                    DB::table('role_menu_items')->where('menu_item_id', $id)->delete();
                    DB::table('menu_items')->where('id', $id)->delete();
                }
            }
        }

        foreach (['candidate_logs', 'recruitments', 'candidate_documents', 'candidates'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
