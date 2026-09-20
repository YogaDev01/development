<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Tipe PO — klasifikasi pekerjaan (Rollout / Maintenance / Delivery /
| Supply). Nullable dan sengaja TIDAK di-backfill: data lama dibiarkan
| kosong supaya diisi manual, dan tampilan memperlakukan null sebagai "-".
|--------------------------------------------------------------------------
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->enum('type', ['ROLLOUT', 'MAINTENANCE', 'DELIVERY', 'SUPPLY', 'FINANCING'])
                ->nullable()
                ->after('financing');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
