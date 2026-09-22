<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Master karyawan — BERDIRI SENDIRI (terpisah dari beneficiaries).
    |
    | Beneficiary = pihak eksternal (pembayaran proyek).
    | Employee    = internal (payroll). Rekening disimpan langsung di sini,
    |               bank menunjuk ke master banks.
    |--------------------------------------------------------------------------
    */

    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            // Identitas
            $table->string('employee_number')->nullable()->unique();  // No Induk
            $table->string('name');                                   // Nama Karyawan
            $table->string('birth_place')->nullable();                // Tempat Lahir
            $table->date('birth_date')->nullable();                   // Tanggal Lahir
            $table->string('nik')->nullable();                        // NIK (KTP)
            $table->enum('gender', ['L', 'P'])->nullable();           // Jenis Kelamin
            $table->text('address')->nullable();                      // Alamat
            $table->text('domicile')->nullable();                     // Domisili
            $table->string('phone')->nullable();                      // No Telepon
            $table->string('email')->nullable();                      // Email

            // Pajak
            $table->string('npwp')->nullable();                       // NPWP
            $table->string('ptkp_status')->nullable();                // Status PTKP (TK/0, K/1, ...)

            // Rekening gaji — bank menunjuk master banks
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->foreign('bank_id')
                ->references('id')->on('banks')
                ->nullOnDelete();
            $table->string('account_name')->nullable();               // Nama Rekening
            $table->string('account_number')->nullable();             // No. Rekening

            // Gaji default (nilai awal saat generate payroll; tetap bisa diubah per run)
            $table->unsignedBigInteger('default_salary')->default(0);

            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('input_by')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};