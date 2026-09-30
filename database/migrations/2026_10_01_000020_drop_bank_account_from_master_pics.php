<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_pics', function (Blueprint $table) {
            $table->dropColumn('account_number');
            $table->dropConstrainedForeignId('bank_id');
        });
    }

    public function down(): void
    {
        Schema::table('master_pics', function (Blueprint $table) {
            $table->foreignId('bank_id')->nullable()->after('position')
                ->constrained('banks')->nullOnDelete();
            $table->string('account_number')->nullable()->after('bank_id');
        });
    }
};
