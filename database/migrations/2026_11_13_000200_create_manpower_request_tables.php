<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Manpower Request (HRIS) — tahap 2a (2026-11-13).
 *
 * Aditif: tiga tabel baru + menu 'manpower-request'. Tidak menyentuh tabel
 * lama. Recruitment & Kandidat (tahap 2b) akan menyambung ke
 * manpower_request_details lewat kolom status / filled_employee_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('manpower_requests')) {
            Schema::create('manpower_requests', function (Blueprint $table) {
                $table->id();
                $table->string('request_no', 20)->unique();
                $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
                $table->unsignedBigInteger('department_id')->nullable();
                $table->unsignedBigInteger('project_id')->nullable();
                $table->date('request_date');
                $table->date('needed_date')->nullable();
                $table->string('status', 15)->default('DRAFT')->index();
                $table->text('note')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->date('approved_at')->nullable();
                $table->text('approval_note')->nullable();
                $table->timestamps();

                $table->foreign('department_id')->references('id')->on('departments')->restrictOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('manpower_request_details')) {
            Schema::create('manpower_request_details', function (Blueprint $table) {
                $table->id();
                $table->foreignId('manpower_request_id')->constrained('manpower_requests')->cascadeOnDelete();
                $table->string('position', 100);
                $table->string('grade', 30);
                $table->string('type', 10)->default('NEW');            // NEW | REPLACE
                $table->unsignedBigInteger('replaced_employee_id')->nullable();
                $table->string('status', 15)->default('OPEN');         // OPEN | ON_PROCESS | DONE | CANCELED
                $table->unsignedBigInteger('filled_employee_id')->nullable();
                $table->timestamps();

                $table->foreign('replaced_employee_id')->references('id')->on('employees')->restrictOnDelete();
                $table->foreign('filled_employee_id')->references('id')->on('employees')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('manpower_request_logs')) {
            Schema::create('manpower_request_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('manpower_request_id')->constrained('manpower_requests')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_system')->default(false);          // true = peristiwa alur, false = diskusi
                $table->string('event', 255)->nullable();
                $table->text('text')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (Schema::hasTable('menu_items')
            && ! DB::table('menu_items')->where('key', 'manpower-request')->exists()) {
            DB::table('menu_items')->insert([
                'key'        => 'manpower-request',
                'label'      => 'Manpower Request',
                'icon'       => 'fas fa-user-plus',
                'route'      => '/manpower',
                'section'    => 'hr',
                'sort_order' => 80,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** Tidak menghapus tabel yang sudah berisi pengajuan. */
    public function down(): void
    {
        if (Schema::hasTable('manpower_requests') && DB::table('manpower_requests')->exists()) {
            throw new RuntimeException('Rollback dibatalkan: manpower_requests sudah berisi data.');
        }

        if (Schema::hasTable('menu_items')) {
            $id = DB::table('menu_items')->where('key', 'manpower-request')->value('id');
            if ($id) {
                DB::table('role_menu_items')->where('menu_item_id', $id)->delete();
                DB::table('menu_items')->where('id', $id)->delete();
            }
        }

        foreach (['manpower_request_logs', 'manpower_request_details', 'manpower_requests'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
