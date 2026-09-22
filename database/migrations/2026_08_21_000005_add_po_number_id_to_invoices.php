<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Invoice sekarang ditagihkan per No. PO (bukan per item Scope of Work
    | lagi). Nullable supaya invoice lama (dibuat sebelum perubahan ini,
    | cuma terhubung ke `po_id`/Project) tetap valid.
    |--------------------------------------------------------------------------
    */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'project_po_number_id')) {
                $table->foreignId('project_po_number_id')
                    ->nullable()
                    ->after('po_id')
                    ->constrained('project_po_numbers')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_po_number_id');
        });
    }
};
