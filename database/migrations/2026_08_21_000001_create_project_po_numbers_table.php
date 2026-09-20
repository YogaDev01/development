<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | project_po_numbers
    |--------------------------------------------------------------------------
    | Satu item scope of work (Project) bisa berisi beberapa No. PO yang
    | rilis tidak dalam satu waktu. Tabel ini merecord tiap No. PO secara
    | individual — nominal, tanggal rilis, dan status per-No. PO (RELEASED
    | -> WORK_DONE -> INVOICED -> PAID). Status header Project (progress
    | kerja/billing/payment) dihitung agregat dari tabel ini, tidak disimpan
    | manual.
    */
    public function up(): void
    {
        Schema::create('project_po_numbers', function (Blueprint $table) {

            $table->id();

            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            $table->string('po_number');
            $table->unsignedBigInteger('nominal')->default(0);
            $table->date('release_date')->nullable();

            // RELEASED -> WORK_DONE -> INVOICED -> PAID
            $table->string('status', 20)->default('RELEASED');

            $table->unsignedBigInteger('input_by')->nullable();

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_po_numbers');
    }
};
