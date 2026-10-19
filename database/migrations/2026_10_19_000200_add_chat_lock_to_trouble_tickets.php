<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Trouble Ticket — penghentian diskusi (Stop Chat)
|--------------------------------------------------------------------------
|
| Keputusan 19 Okt 2026. Tiket CLOSED tidak lagi menutup chat dengan
| sendirinya: diskusi tetap terbuka sampai superadmin menghentikannya.
| Dua kolom jejak saja (aditif, nullable) — kapan dihentikan dan oleh siapa.
| Kosong = chat terbuka.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trouble_tickets')) {
            return;
        }

        Schema::table('trouble_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('trouble_tickets', 'chat_locked_at')) {
                $table->timestamp('chat_locked_at')->nullable()->after('closed_by');
            }
            if (! Schema::hasColumn('trouble_tickets', 'chat_locked_by')) {
                $table->unsignedBigInteger('chat_locked_by')->nullable()->after('chat_locked_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trouble_tickets')) {
            return;
        }

        Schema::table('trouble_tickets', function (Blueprint $table) {
            foreach (['chat_locked_by', 'chat_locked_at'] as $kolom) {
                if (Schema::hasColumn('trouble_tickets', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });
    }
};
