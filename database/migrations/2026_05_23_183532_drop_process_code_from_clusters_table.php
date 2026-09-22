<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * --------------------------------------------------------------------------
     * RUN MIGRATION
     * --------------------------------------------------------------------------
     */
    public function up(): void
    {
        Schema::table('clusters', function (Blueprint $table) {

            $table->dropColumn('process_code');

        });
    }

    /**
     * --------------------------------------------------------------------------
     * ROLLBACK MIGRATION
     * --------------------------------------------------------------------------
     */
    public function down(): void
    {
        Schema::table('clusters', function (Blueprint $table) {

            $table->string('process_code')
                ->nullable()
                ->after('group_id');

        });
    }
};