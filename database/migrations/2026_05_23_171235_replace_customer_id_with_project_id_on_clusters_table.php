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

            /*
            |--------------------------------------------------------------------------
            | DROP FOREIGN CUSTOMER
            |--------------------------------------------------------------------------
            */

            $table->dropForeign(['customer_id']);

            /*
            |--------------------------------------------------------------------------
            | DROP COLUMN CUSTOMER
            |--------------------------------------------------------------------------
            */

            $table->dropColumn('customer_id');

            /*
            |--------------------------------------------------------------------------
            | ADD PROJECT ID
            |--------------------------------------------------------------------------
            */

            $table->foreignId('project_id')
                ->nullable()
                ->after('status')
                ->constrained('projects')
                ->nullOnDelete();

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

            /*
            |--------------------------------------------------------------------------
            | DROP FOREIGN PROJECT
            |--------------------------------------------------------------------------
            */

            $table->dropForeign(['project_id']);

            /*
            |--------------------------------------------------------------------------
            | DROP COLUMN PROJECT
            |--------------------------------------------------------------------------
            */

            $table->dropColumn('project_id');

            /*
            |--------------------------------------------------------------------------
            | ADD CUSTOMER ID
            |--------------------------------------------------------------------------
            */

            $table->foreignId('customer_id')
                ->nullable()
                ->after('status')
                ->constrained('customers')
                ->nullOnDelete();

        });
    }
};