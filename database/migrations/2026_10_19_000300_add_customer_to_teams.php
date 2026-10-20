<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Team — customer yang dilayani
|--------------------------------------------------------------------------
|
| Keputusan 19 Okt 2026. Tiap team Operation bekerja untuk satu customer
| (project). Form Buat Trouble Ticket tidak lagi menanyakan customer: ia
| diambil dari team ACTIVE tempat pembuatnya terdaftar, dan daftar site
| mengikuti customer itu.
|
| Aditif dan nullable: team yang belum diisi customer-nya berperilaku persis
| seperti sebelumnya (pembuat tiket memilih customer sendiri).
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teams') || Schema::hasColumn('teams', 'customer_id')) {
            return;
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->after('department_id');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('teams') || ! Schema::hasColumn('teams', 'customer_id')) {
            return;
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};
