<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->string('code')->nullable()->after('name');
            $table->enum('default_method', [
                'In-House', 'BI Fast', 'Online', 'SKN', 'RTGS', 'International',
            ])->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->dropColumn(['code', 'default_method']);
        });
    }
};