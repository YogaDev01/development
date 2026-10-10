<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Menyamakan isian form Site dengan SLAmeet: Homebase & Cluster dipilih
| langsung di site, dan data jaringan (gateway/outroute/hub/spotbeam/
| polarisasi) diisi per site, bukan lagi lewat dropdown Network.
|
| Additive saja. Kolom `network_id` sengaja DIPERTAHANKAN untuk site lama —
| hanya dikeluarkan dari form.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sites')) {
            return;
        }

        Schema::table('sites', function (Blueprint $table) {
            if (! Schema::hasColumn('sites', 'hb_id')) {
                $table->foreignId('hb_id')->nullable()->after('network_id')->constrained('homebase')->restrictOnDelete();
            }
            if (! Schema::hasColumn('sites', 'region_id')) {
                $table->foreignId('region_id')->nullable()->after('hb_id')->constrained('region')->restrictOnDelete();
            }
            if (! Schema::hasColumn('sites', 'gateway')) {
                $table->string('gateway', 150)->nullable()->after('region_id');
            }
            if (! Schema::hasColumn('sites', 'outroute_id')) {
                $table->string('outroute_id', 150)->nullable()->after('gateway');
            }
            if (! Schema::hasColumn('sites', 'hub')) {
                $table->string('hub', 150)->nullable()->after('outroute_id');
            }
            if (! Schema::hasColumn('sites', 'spotbeam')) {
                $table->string('spotbeam', 150)->nullable()->after('hub');
            }
            if (! Schema::hasColumn('sites', 'polarization')) {
                $table->string('polarization', 10)->nullable()->after('spotbeam');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sites')) {
            return;
        }

        Schema::table('sites', function (Blueprint $table) {
            foreach (['hb_id', 'region_id'] as $fk) {
                if (Schema::hasColumn('sites', $fk)) {
                    $table->dropConstrainedForeignId($fk);
                }
            }
            foreach (['gateway', 'outroute_id', 'hub', 'spotbeam', 'polarization'] as $col) {
                if (Schema::hasColumn('sites', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
