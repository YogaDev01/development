<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversation_messages', function (Blueprint $table) {
            $table->json('cr_links')->nullable()->after('function_args');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversation_messages', function (Blueprint $table) {
            $table->dropColumn('cr_links');
        });
    }
};
