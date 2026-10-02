<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Catatan perubahan per baris Settlement: alasan kenapa Description Settle /
| nilainya berbeda dari Description Request. Wajib diisi (divalidasi di
| controller) ketika nilai Settle melebihi nilai Request.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('budget_request_realizations')) {
            return;
        }

        Schema::table('budget_request_realizations', function (Blueprint $table) {
            if (! Schema::hasColumn('budget_request_realizations', 'change_note')) {
                $table->text('change_note')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('budget_request_realizations')) {
            return;
        }

        Schema::table('budget_request_realizations', function (Blueprint $table) {
            if (Schema::hasColumn('budget_request_realizations', 'change_note')) {
                $table->dropColumn('change_note');
            }
        });
    }
};
