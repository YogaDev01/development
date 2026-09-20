<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Lampiran per No. PO — pakai tabel project_attachments yang sudah ada
    | (type='po_number'), tinggal tambah kolom penunjuk baris project_po_numbers.
    | Nullable karena lampiran umum/closing yang sudah ada tidak terkait ke
    | baris No. PO manapun.
    */
    public function up(): void
    {
        Schema::table('project_attachments', function (Blueprint $table) {
            if (!Schema::hasColumn('project_attachments', 'project_po_number_id')) {
                $table->foreignId('project_po_number_id')
                    ->nullable()
                    ->after('project_id')
                    ->constrained('project_po_numbers')
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_attachments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_po_number_id');
        });
    }
};
