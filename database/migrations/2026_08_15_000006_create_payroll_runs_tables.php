<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | PAYROLL RUN — satu dokumen per periode (mis. Juli 2026)
    |--------------------------------------------------------------------------
    | Workflow status:
    |   DRAFT -> SUBMITTED -> VERIFIED -> APPROVED -> PAID -> CLOSED  (+ REJECTED)
    |
    | Sumber dana: budget PAYROLL (cost center) via budget_plan_id.
    | Saat APPROVED, run melahirkan batch Daily Transaction (link daily_transaction_id).
    */

    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();               // PRR-072600001
            $table->string('period');                        // '2026-07' (YYYY-MM)
            $table->string('description')->nullable();

            // Sumber dana: budget plan payroll (cost center)
            $table->unsignedBigInteger('budget_plan_id')->nullable();
            $table->foreign('budget_plan_id')
                ->references('id')->on('budget_plans')
                ->nullOnDelete();

            $table->unsignedBigInteger('total')->default(0); // total net semua baris

            $table->enum('status', [
                'DRAFT', 'SUBMITTED', 'VERIFIED', 'APPROVED', 'PAID', 'CLOSED', 'REJECTED',
            ])->default('DRAFT');

            // Batch pembayaran yang dilahirkan saat APPROVED.
            // FK dipasang defensif: nama tabel daily transaction bisa berbeda
            // (daily_transactions vs daily_transaction) tergantung konvensi.
            $table->unsignedBigInteger('daily_transaction_id')->nullable();

            $table->text('notes')->nullable();

            // Jejak approval
            $table->unsignedBigInteger('input_by')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('reject_notes')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('period');
        });

        Schema::create('payroll_run_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_run_id');
            $table->foreign('payroll_run_id')
                ->references('id')->on('payroll_runs')
                ->cascadeOnDelete();

            // Karyawan + snapshot data (agar histori tak berubah bila master diedit)
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->foreign('employee_id')
                ->references('id')->on('employees')
                ->nullOnDelete();

            $table->string('employee_name');                 // snapshot nama
            $table->string('position')->nullable();          // snapshot jabatan

            // Rekening transfer (snapshot dari employee — payroll internal,
            // TIDAK memakai beneficiaries yang untuk proyek)
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->foreign('bank_id')
                ->references('id')->on('banks')
                ->nullOnDelete();
            $table->string('account_name')->nullable();      // snapshot nama rekening
            $table->string('account_number')->nullable();    // snapshot no rekening

            // Nominal — SEDERHANA dulu: net saja.
            // (kolom rinci gaji pokok/tunjangan/potongan bisa ditambah kemudian
            //  tanpa mengubah struktur ini)
            $table->unsignedBigInteger('net_amount')->default(0);

            // FLAG PEMBEBANAN per baris:
            //   OVERHEAD -> gaji staf kantor (charge_project_id NULL)
            //   PROJECT  -> gaji tenaga lapangan (charge_project_id terisi)
            $table->enum('charge_type', ['OVERHEAD', 'PROJECT'])->default('OVERHEAD');
            $table->unsignedBigInteger('charge_project_id')->nullable();
            $table->foreign('charge_project_id')
                ->references('id')->on('projects')
                ->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('charge_type');
        });

        // Pasang FK daily_transaction_id HANYA bila tabelnya ditemukan,
        // dengan nama yang benar (plural atau singular).
        $dtTable = null;
        if (Schema::hasTable('daily_transactions')) {
            $dtTable = 'daily_transactions';
        } elseif (Schema::hasTable('daily_transaction')) {
            $dtTable = 'daily_transaction';
        }

        if ($dtTable) {
            Schema::table('payroll_runs', function (Blueprint $table) use ($dtTable) {
                $table->foreign('daily_transaction_id')
                    ->references('id')->on($dtTable)
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_lines');
        Schema::dropIfExists('payroll_runs');
    }
};