<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Dual due date: `due_date` (existing) jadi basis cetak/buat invoice saja
| ("due_date_printed" secara konsep, kolom fisik tidak direname supaya
| tidak mengubah puluhan referensi existing) — dipertahankan sebagai
| informasi, bukan basis Overdue lagi.
|
| `actual_due_date` = tanggal jatuh tempo sesungguhnya, dihitung otomatis
| dari `received_at` (invoice diterima customer — kolom ini SUDAH ADA di
| invoices tapi belum pernah diisi controller manapun) + panjang termin
| asli (due_date - invoice_date). Lihat Invoice::syncActualDueDate().
|--------------------------------------------------------------------------
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->date('actual_due_date')->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('actual_due_date');
        });
    }
};
