<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Asset Management (Tesys Finance, 2026-11-12).
 *
 * Murni ADITIF: hanya membuat tabel baru, tidak menyentuh tabel yang ada.
 * Nilai uang = integer rupiah (BIGINT UNSIGNED). FK dari data aset ke master
 * memakai RESTRICT (pola migrasi 2026_09_26_000002); hanya baris anak
 * (lampiran, log, item opname) yang ikut terhapus bersama induknya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('asset_categories')) {
            Schema::create('asset_categories', function (Blueprint $table) {
                $table->id();
                $table->string('code', 10)->unique();
                $table->string('name');
                $table->unsignedTinyInteger('life_years')->default(4);
                $table->string('depreciation_method', 30)->default('STRAIGHT_LINE');
                $table->decimal('residual_percent', 5, 2)->default(10);
                $table->string('account')->nullable();
                $table->text('description')->nullable();
                $table->string('status', 20)->default('ACTIVE');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('asset_locations')) {
            Schema::create('asset_locations', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->unique();
                $table->string('name');
                $table->string('type', 30)->default('OFFICE');
                $table->text('address')->nullable();
                $table->string('pic_name')->nullable();
                $table->string('status', 20)->default('ACTIVE');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('asset_purchase_requests')) {
            Schema::create('asset_purchase_requests', function (Blueprint $table) {
                $table->id();
                $table->string('number', 40)->unique();
                $table->string('item_name');
                $table->foreignId('asset_category_id')->constrained('asset_categories')->restrictOnDelete();
                $table->foreignId('department_id')->nullable()->constrained('departments')->restrictOnDelete();
                $table->unsignedInteger('qty')->default(1);
                $table->unsignedBigInteger('estimated_unit_cost')->default(0);
                $table->date('needed_date')->nullable();
                $table->text('justification')->nullable();
                $table->string('status', 20)->default('PENDING')->index();
                $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->string('reject_reason', 500)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('asset_receivings')) {
            Schema::create('asset_receivings', function (Blueprint $table) {
                $table->id();
                $table->string('number', 40)->unique();
                $table->foreignId('asset_purchase_request_id')->nullable()->constrained('asset_purchase_requests')->restrictOnDelete();
                $table->string('item_name');
                $table->foreignId('asset_category_id')->constrained('asset_categories')->restrictOnDelete();
                $table->foreignId('asset_location_id')->constrained('asset_locations')->restrictOnDelete();
                $table->string('supplier')->nullable();
                $table->string('po_number', 100)->nullable();
                $table->date('received_date');
                $table->unsignedInteger('qty')->default(1);
                $table->unsignedBigInteger('unit_cost')->default(0);
                $table->text('notes')->nullable();
                $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('assets')) {
            Schema::create('assets', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('name');
                $table->foreignId('asset_category_id')->constrained('asset_categories')->restrictOnDelete();
                $table->string('type', 100)->nullable();
                $table->string('brand', 100)->nullable();
                $table->string('model', 150)->nullable();
                $table->string('serial_number', 150)->nullable()->index();
                $table->foreignId('asset_location_id')->nullable()->constrained('asset_locations')->restrictOnDelete();
                $table->foreignId('department_id')->nullable()->constrained('departments')->restrictOnDelete();
                $table->foreignId('employee_id')->nullable()->constrained('employees')->restrictOnDelete();
                $table->string('custodian_name')->nullable();
                $table->date('acquisition_date');
                $table->string('acquisition_type', 30)->default('PURCHASE');
                $table->unsignedBigInteger('cost')->default(0);
                $table->unsignedBigInteger('residual_value')->default(0);
                $table->unsignedTinyInteger('life_years')->default(4);
                $table->string('depreciation_method', 30)->default('STRAIGHT_LINE');
                $table->string('condition', 20)->default('GOOD')->index();
                $table->string('status', 20)->default('AVAILABLE')->index();
                $table->string('supplier')->nullable();
                $table->string('po_number', 100)->nullable();
                $table->foreignId('asset_receiving_id')->nullable()->constrained('asset_receivings')->restrictOnDelete();
                $table->date('warranty_end')->nullable();
                $table->json('specs')->nullable();
                $table->text('description')->nullable();
                $table->date('disposed_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->foreignId('deleted_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->string('delete_reason', 500)->nullable();
            });
        }

        if (! Schema::hasTable('asset_attachments')) {
            Schema::create('asset_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
                $table->string('original_name');
                $table->string('path');
                $table->string('mime', 150)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('asset_logs')) {
            Schema::create('asset_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->string('action', 40);
                $table->string('description', 1000)->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }

        // Serah terima, pengembalian, transfer, check-out, dan disposal — satu
        // tabel karena semuanya "perpindahan" aset dengan pola yang sama.
        if (! Schema::hasTable('asset_movements')) {
            Schema::create('asset_movements', function (Blueprint $table) {
                $table->id();
                $table->string('number', 40)->unique();
                $table->string('kind', 20)->index();
                $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
                $table->string('status', 20)->index();
                $table->string('asset_status_before', 20)->nullable();
                $table->foreignId('from_location_id')->nullable()->constrained('asset_locations')->restrictOnDelete();
                $table->foreignId('to_location_id')->nullable()->constrained('asset_locations')->restrictOnDelete();
                $table->foreignId('from_employee_id')->nullable()->constrained('employees')->restrictOnDelete();
                $table->foreignId('to_employee_id')->nullable()->constrained('employees')->restrictOnDelete();
                $table->date('movement_date');
                $table->date('expected_return_date')->nullable();
                $table->date('returned_date')->nullable();
                $table->string('condition_before', 20)->nullable();
                $table->string('condition_after', 20)->nullable();
                $table->string('reason', 500)->nullable();
                $table->text('notes')->nullable();
                $table->string('disposal_method', 20)->nullable();
                $table->unsignedBigInteger('disposal_value')->nullable();
                $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->string('reject_reason', 500)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('asset_maintenance_schedules')) {
            Schema::create('asset_maintenance_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
                $table->string('title');
                $table->string('maintenance_type', 20)->default('PREVENTIVE');
                $table->unsignedSmallInteger('interval_days')->default(90);
                $table->date('next_due_date')->index();
                $table->date('last_done_date')->nullable();
                $table->string('vendor')->nullable();
                $table->string('status', 20)->default('ACTIVE');
                $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('asset_maintenances')) {
            Schema::create('asset_maintenances', function (Blueprint $table) {
                $table->id();
                $table->string('number', 40)->unique();
                $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
                $table->foreignId('asset_maintenance_schedule_id')->nullable()->constrained('asset_maintenance_schedules')->restrictOnDelete();
                $table->string('maintenance_type', 20);
                $table->string('problem', 1000);
                $table->string('vendor')->nullable();
                $table->unsignedBigInteger('estimated_cost')->default(0);
                $table->unsignedBigInteger('actual_cost')->default(0);
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->string('status', 20)->default('IN_PROGRESS')->index();
                $table->string('asset_status_before', 20)->nullable();
                $table->text('result_notes')->nullable();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('asset_inspections')) {
            Schema::create('asset_inspections', function (Blueprint $table) {
                $table->id();
                $table->string('number', 40)->unique();
                $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
                $table->date('inspection_date');
                $table->string('inspector_name');
                $table->string('condition', 20);
                $table->string('result', 20);
                $table->text('findings')->nullable();
                $table->text('follow_up')->nullable();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('asset_opnames')) {
            Schema::create('asset_opnames', function (Blueprint $table) {
                $table->id();
                $table->string('number', 40)->unique();
                $table->foreignId('asset_location_id')->constrained('asset_locations')->restrictOnDelete();
                $table->date('opname_date');
                $table->string('status', 20)->default('COUNTING');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('asset_opname_items')) {
            Schema::create('asset_opname_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('asset_opname_id')->constrained('asset_opnames')->cascadeOnDelete();
                $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
                $table->string('result', 20)->nullable();
                $table->string('condition', 20)->nullable();
                $table->string('notes', 500)->nullable();
                $table->timestamps();
                $table->unique(['asset_opname_id', 'asset_id']);
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'asset_opname_items', 'asset_opnames', 'asset_inspections', 'asset_maintenances',
            'asset_maintenance_schedules', 'asset_movements', 'asset_logs', 'asset_attachments',
            'assets', 'asset_receivings', 'asset_purchase_requests', 'asset_locations', 'asset_categories',
        ] as $tabel) {
            Schema::dropIfExists($tabel);
        }
    }
};
