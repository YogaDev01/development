<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('region')) {
            Schema::create('region', function (Blueprint $table) {
                $table->id();
                $table->string('region_name', 150)->unique();
                $table->foreignId('pulau_id')->constrained('pulau')->restrictOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('homebase')) {
            Schema::create('homebase', function (Blueprint $table) {
                $table->id();
                $table->text('address');
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->foreignId('region_id')->constrained('region')->restrictOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('homebase_detail')) {
            Schema::create('homebase_detail', function (Blueprint $table) {
                $table->id();
                $table->foreignId('hb_id')->constrained('homebase')->restrictOnDelete();
                $table->foreignId('pic_id')->constrained('master_pics')->restrictOnDelete();
                $table->timestamps();

                $table->unique(['hb_id', 'pic_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('homebase_detail');
        Schema::dropIfExists('homebase');
        Schema::dropIfExists('region');
    }
};
