<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Migration 2026_09_15_000006 menambah kolom `actual_due_date`, tapi cuma
| dihitung otomatis lewat Invoice::booted() saving-hook saat `received_at`
| BERUBAH (isDirty). Invoice yang sudah punya `received_at` terisi SEBELUM
| kolom `actual_due_date` ini ada (received_at tidak "dirty" lagi karena
| nilainya sudah tersimpan) jadi tetap NULL selamanya — Aging/Overdue-nya
| tidak pernah muncul walau due date-nya sudah lama lewat.
|
| Migration ini backfill satu kali: actual_due_date = received_at + panjang
| termin asli (due_date - invoice_date), sama persis dengan rumus di
| Invoice::syncActualDueDate().
|--------------------------------------------------------------------------
*/

return new class extends Migration
{
    public function up(): void
    {
        DB::table('invoices')
            ->whereNotNull('received_at')
            ->whereNotNull('invoice_date')
            ->whereNotNull('due_date')
            ->whereNull('actual_due_date')
            ->orderBy('id')
            ->chunkById(200, function ($invoices) {
                foreach ($invoices as $invoice) {
                    $receivedAt  = Carbon::parse($invoice->received_at)->startOfDay();
                    $invoiceDate = Carbon::parse($invoice->invoice_date)->startOfDay();
                    $dueDate     = Carbon::parse($invoice->due_date)->startOfDay();
                    $termDays    = $invoiceDate->diffInDays($dueDate);

                    DB::table('invoices')->where('id', $invoice->id)->update([
                        'actual_due_date' => $receivedAt->copy()->addDays($termDays)->format('Y-m-d'),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Backfill data — tidak ada rollback yang bermakna (nilai lama sudah NULL).
    }
};
