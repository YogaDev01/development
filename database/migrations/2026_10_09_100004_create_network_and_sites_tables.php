<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('network')) {
            Schema::create('network', function (Blueprint $table) {
                $table->id();
                $table->string('penyedia_lc', 150)->nullable();
                $table->string('hub', 150)->nullable();
                $table->string('satelit', 150)->nullable();
                $table->string('gateway', 150)->nullable();
                $table->string('spotbeam', 150)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sites')) {
            Schema::create('sites', function (Blueprint $table) {
                $table->id();
                $table->string('tid', 50)->unique();
                $table->string('sitename', 200);
                $table->string('type', 30);
                $table->string('source_power', 30);
                $table->string('program', 30);
                $table->string('status', 20)->default('ACTIVE');
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->text('address')->nullable();
                $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
                $table->foreignId('network_id')->nullable()->constrained('network')->restrictOnDelete();
                $table->foreignId('desa_id')->nullable()->constrained('desa')->restrictOnDelete();
                $table->foreignId('kecamatan_id')->nullable()->constrained('kecamatan')->restrictOnDelete();
                $table->foreignId('kota_id')->nullable()->constrained('kota')->restrictOnDelete();
                $table->foreignId('provinsi_id')->nullable()->constrained('provinsi')->restrictOnDelete();
                $table->foreignId('pulau_id')->nullable()->constrained('pulau')->restrictOnDelete();
                $table->timestamps();

                $table->index('sitename');
                $table->index('status');
            });
        }

        if (! Schema::hasTable('site_material')) {
            Schema::create('site_material', function (Blueprint $table) {
                $table->id();
                $table->foreignId('site_id')->constrained('sites')->restrictOnDelete();
                $table->foreignId('material_id')->constrained('master_material')->restrictOnDelete();
                $table->timestamps();

                $table->unique(['site_id', 'material_id']);
            });
        }

        if (! Schema::hasTable('site_accessories')) {
            Schema::create('site_accessories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('site_id')->constrained('sites')->restrictOnDelete();
                $table->foreignId('accessories_id')->constrained('accessories')->restrictOnDelete();
                $table->unsignedInteger('qty');
                $table->timestamps();

                $table->unique(['site_id', 'accessories_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_accessories');
        Schema::dropIfExists('site_material');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('network');
    }
};
