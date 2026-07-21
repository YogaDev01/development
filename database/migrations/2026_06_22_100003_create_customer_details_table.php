<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_details', function (Blueprint $table) {

            $table->id();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            // Person in charge
            $table->string('pic')
                ->nullable();

            $table->string('position')
                ->nullable();

            // Nomor telp / email / kontak lain
            $table->string('contact')
                ->nullable();

            $table->text('remark')
                ->nullable();

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_details');
    }
};