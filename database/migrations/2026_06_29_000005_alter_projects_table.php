<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {

            // Code auto-generate 4 digit (0001, 0002, ...)
            $table->string('code', 4)->nullable()->unique()->after('id');

            // Nomor PO dari customer
            $table->string('po_number')->nullable()->after('code');

            // Done by — pihak yang mengerjakan PO
            $table->string('done_by')->nullable()->after('po_number');

            // Nilai kontrak PO
            $table->unsignedBigInteger('value')->nullable()->after('done_by');

            // Contact person dari customer
            $table->string('contact_person')->nullable()->after('value');

        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'code',
                'po_number',
                'done_by',
                'value',
                'contact_person',
            ]);
        });
    }
};