<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Nilai type/status sengaja varchar (bukan enum DB): daftar nilainya masih
| bisa bertambah dan cukup diubah di konstanta model tanpa migrasi.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('category_item')) {
            Schema::create('category_item', function (Blueprint $table) {
                $table->id();
                $table->string('type', 30);
                $table->string('category_name', 150);
                $table->string('merk', 150);
                $table->timestamps();

                $table->unique(['type', 'category_name', 'merk']);
            });
        }

        if (! Schema::hasTable('master_material')) {
            Schema::create('master_material', function (Blueprint $table) {
                $table->id();
                $table->string('product_code', 30)->unique();
                $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
                $table->foreignId('category_id')->constrained('category_item')->restrictOnDelete();
                $table->string('serial_number', 150);
                $table->string('status', 30)->default('WAREHOUSE');
                $table->timestamps();

                $table->index('serial_number');
                $table->index('status');
            });
        }

        if (! Schema::hasTable('accessories')) {
            Schema::create('accessories', function (Blueprint $table) {
                $table->id();
                $table->string('accessories_code', 30)->unique();
                $table->string('accessories_name', 150);
                $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
                $table->foreignId('category_id')->constrained('category_item')->restrictOnDelete();
                $table->string('status', 30)->default('WAREHOUSE');
                $table->timestamps();

                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accessories');
        Schema::dropIfExists('master_material');
        Schema::dropIfExists('category_item');
    }
};
