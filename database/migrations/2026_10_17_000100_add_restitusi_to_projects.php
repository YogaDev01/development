<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PO ("Project") dapat kolom `restitusi`: nominal rupiah manual (informasi),
     * ditampilkan di antara Contract dan PO Value. Tidak ikut perhitungan apa pun.
     */
    public function up(): void
    {
        if (Schema::hasTable('projects') && !Schema::hasColumn('projects', 'restitusi')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->unsignedBigInteger('restitusi')->nullable()->default(0)->after('contract');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('projects', 'restitusi')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('restitusi');
            });
        }
    }
};
