<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('request_attachments', function (Blueprint $table) {
            if (!Schema::hasColumn('request_attachments', 'uploaded_by')) {
                $table->unsignedBigInteger('uploaded_by')->nullable()->after('size');
                $table->index('uploaded_by');
            }

            if (!Schema::hasColumn('request_attachments', 'notes')) {
                $table->text('notes')->nullable()->after('uploaded_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_attachments', function (Blueprint $table) {
            if (Schema::hasColumn('request_attachments', 'notes')) {
                $table->dropColumn('notes');
            }

            if (Schema::hasColumn('request_attachments', 'uploaded_by')) {
                $table->dropIndex(['uploaded_by']);
                $table->dropColumn('uploaded_by');
            }
        });
    }
};