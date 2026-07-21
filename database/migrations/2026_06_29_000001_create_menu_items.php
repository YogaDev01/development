<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();          // unique identifier, e.g. 'customer', 'budget-plan'
            $table->string('label');                  // label ditampilkan di sidebar
            $table->string('icon')->nullable();       // font awesome class, e.g. 'fas fa-building'
            $table->string('route')->nullable();      // URL path, e.g. '/customer'
            $table->string('section')->nullable();    // section header: 'master', 'finance', 'project'
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};