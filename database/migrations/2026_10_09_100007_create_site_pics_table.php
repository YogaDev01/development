<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_pics')) {
            Schema::create('site_pics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('site_id')->constrained('sites')->restrictOnDelete();
                $table->foreignId('pic_id')->constrained('master_pics')->restrictOnDelete();
                $table->timestamps();

                $table->unique(['site_id', 'pic_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_pics');
    }
};
