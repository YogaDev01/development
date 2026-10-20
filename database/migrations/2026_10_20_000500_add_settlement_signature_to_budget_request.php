<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Cost Request — tanda tangan penerima dana pada settlement
|--------------------------------------------------------------------------
|
| Keputusan 20 Okt 2026. Settlement yang diajukan dari halaman Trouble Ticket
| menyertakan tanda tangan PENERIMA DANA di lapangan: coretan di layar
| (disimpan sebagai PNG di disk `secure`, bukan `public`), nama penanda tangan
| yang diketik, dan waktu penandatanganan dari jam server.
|
|   settlement_signature_path : path berkas PNG di disk secure
|   settlement_signer_name    : nama penanda tangan (diketik pengaju)
|   settlement_signed_at      : waktu tanda tangan dikirim
|
| Aditif & nullable: CR lama dan settlement yang diajukan dari modul Cost
| Request (tanpa tiket) berperilaku persis seperti sebelumnya.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('budget_request')) {
            return;
        }

        Schema::table('budget_request', function (Blueprint $table) {
            if (! Schema::hasColumn('budget_request', 'settlement_signature_path')) {
                $table->string('settlement_signature_path')->nullable()->after('realized_at');
            }

            if (! Schema::hasColumn('budget_request', 'settlement_signer_name')) {
                $table->string('settlement_signer_name')->nullable()->after('settlement_signature_path');
            }

            if (! Schema::hasColumn('budget_request', 'settlement_signed_at')) {
                $table->timestamp('settlement_signed_at')->nullable()->after('settlement_signer_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('budget_request')) {
            return;
        }

        Schema::table('budget_request', function (Blueprint $table) {
            foreach (['settlement_signature_path', 'settlement_signer_name', 'settlement_signed_at'] as $kolom) {
                if (Schema::hasColumn('budget_request', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });
    }
};
