<?php

namespace Database\Seeders;

use App\Models\DeliveryNote;
use App\Models\Invoice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| DUMMY SURAT JALAN
|--------------------------------------------------------------------------
| Halaman Surat Jalan kosong karena rantai sebelumnya (Invoice APPROVED)
| belum ada isinya: satu-satunya jalan masuk ke modul ini adalah invoice
| berstatus APPROVED yang belum pernah dipakai Surat Jalan lain.
|
| Karena itu seeder ini membangun rantainya sekaligus:
|   1. invoice dummy APPROVED untuk beberapa customer,
|   2. Surat Jalan dari sebagian invoice itu — sebagian SENT, sebagian
|      RECEIVED lengkap dengan log + evidence,
|   3. sisa invoice dibiarkan APPROVED supaya layar "Buat Surat Jalan"
|      punya pilihan untuk dicoba manual.
|
| Penomoran memakai generator asli (Invoice::generateCode dan
| DeliveryNote::generateCode) supaya penghitung document_sequences ikut maju
| dan dokumen sungguhan berikutnya tidak bertabrakan nomor.
|
| Semua baris ditandai [DUMMY] pada remark/notes agar mudah dibersihkan.
*/
class DummyDeliveryNoteSeeder extends Seeder
{
    public function run(): void
    {
        $pairs = DB::table('projects as p')
            ->join('project_po_numbers as n', 'n.project_id', '=', 'p.id')
            ->whereNotNull('p.customer_id')
            ->orderBy('n.id')
            ->limit(8)
            ->get(['p.id as project_id', 'p.customer_id', 'n.id as ppn_id']);

        if ($pairs->isEmpty()) {
            $this->command?->warn('Tidak ada project dengan customer + No. PO. Jalankan seeder project dulu.');
            return;
        }

        $actor    = $this->actor();
        $sourceId = DB::table('source_funds')->value('id');

        // Rencana per pasangan project/PO: berapa invoice, dan mau jadi apa.
        // 'R' = Surat Jalan diterima, 'S' = masih dikirim, 'A' = invoice
        // dibiarkan APPROVED (bahan uji pembuatan Surat Jalan manual).
        $plan = ['R', 'R', 'S', 'S', 'R', 'A', 'S', 'A'];

        $created = ['invoice' => 0, 'sent' => 0, 'received' => 0, 'approved' => 0];

        DB::transaction(function () use ($pairs, $plan, $actor, $sourceId, &$created) {
            foreach ($pairs as $i => $pair) {
                $mode     = $plan[$i % count($plan)];
                $jumlah   = $mode === 'A' ? 1 : (($i % 3) + 1);
                $baseDate = Carbon::now()->subDays(80 - ($i * 9));

                $invoiceIds = [];

                for ($k = 0; $k < $jumlah; $k++) {
                    $invoiceDate = (clone $baseDate)->addDays($k * 3);
                    $subtotal    = (40 + ($i * 15) + ($k * 7)) * 1_000_000;
                    $tax         = (int) round($subtotal * 0.11);

                    $invoiceIds[] = DB::table('invoices')->insertGetId([
                        'code'                 => Invoice::generateCode($pair->project_id, $invoiceDate),
                        'faktur_number'        => sprintf('010.000-26.%08d', 30_000_000 + ($i * 137) + $k),
                        'po_id'                => $pair->project_id,
                        'project_po_number_id' => $pair->ppn_id,
                        'source_fund_id'       => $sourceId,
                        'payment_term_type'    => 'Full Payment',
                        'invoice_date'         => $invoiceDate->toDateString(),
                        'due_date'             => (clone $invoiceDate)->addDays(30)->toDateString(),
                        'actual_due_date'      => (clone $invoiceDate)->addDays(30)->toDateString(),
                        'tax_rate'             => 11.00,
                        'billing_percentage'   => 100.00,
                        'subtotal'             => $subtotal,
                        'billed_amount'        => $subtotal,
                        'tax_amount'           => $tax,
                        'withholding_tax'      => 0,
                        'captured_total'       => $subtotal + $tax,
                        'total_deduction'      => 0,
                        'is_additional'        => 0,
                        'additional_amount'    => 0,
                        'remark'               => '[DUMMY] invoice bahan uji Surat Jalan',
                        'status'               => 'APPROVED',
                        'created_by'           => $actor,
                        'submitted_by'         => $actor,
                        'submitted_at'         => $invoiceDate->toDateTimeString(),
                        'verified_by'          => $actor,
                        'verified_at'          => (clone $invoiceDate)->addDay()->toDateTimeString(),
                        'approved_by'          => $actor,
                        'approved_at'          => (clone $invoiceDate)->addDays(2)->toDateTimeString(),
                        'created_at'           => $invoiceDate->toDateTimeString(),
                        'updated_at'           => (clone $invoiceDate)->addDays(2)->toDateTimeString(),
                    ]);

                    $created['invoice']++;
                }

                if ($mode === 'A') {
                    $created['approved'] += count($invoiceIds);
                    continue;
                }

                $sentAt     = (clone $baseDate)->addDays($jumlah * 3 + 2);
                $receivedAt = $mode === 'R' ? (clone $sentAt)->addDays(3 + $i % 5) : null;

                $dnId = DB::table('delivery_notes')->insertGetId([
                    'code'        => DeliveryNote::generateCode($sentAt),
                    'customer_id' => $pair->customer_id,
                    'status'      => $receivedAt ? 'RECEIVED' : 'SENT',
                    'notes'       => '[DUMMY] Dokumen invoice + faktur pajak dikirim via kurir internal.',
                    'created_by'  => $actor,
                    'sent_at'     => $sentAt->toDateTimeString(),
                    'received_by' => $receivedAt ? $actor : null,
                    'received_at' => $receivedAt?->toDateTimeString(),
                    'created_at'  => $sentAt->toDateTimeString(),
                    'updated_at'  => ($receivedAt ?? $sentAt)->toDateTimeString(),
                ]);

                $sentLogId = DB::table('delivery_note_logs')->insertGetId([
                    'delivery_note_id' => $dnId,
                    'action'           => 'SENT',
                    'actor_id'         => $actor,
                    'actor_name'       => $this->actorName($actor),
                    'notes'            => null,
                    'created_at'       => $sentAt->toDateTimeString(),
                    'updated_at'       => $sentAt->toDateTimeString(),
                ]);

                foreach ($invoiceIds as $invoiceId) {
                    DB::table('delivery_note_items')->insert([
                        'delivery_note_id' => $dnId,
                        'invoice_id'       => $invoiceId,
                        'created_at'       => $sentAt->toDateTimeString(),
                        'updated_at'       => $sentAt->toDateTimeString(),
                    ]);

                    DB::table('invoices')->where('id', $invoiceId)->update([
                        'status'      => $receivedAt ? 'RECEIVED' : 'SENT',
                        'sent_by'     => $actor,
                        'sent_at'     => $sentAt->toDateTimeString(),
                        'received_by' => $receivedAt ? $actor : null,
                        'received_at' => $receivedAt?->toDateTimeString(),
                        'updated_at'  => ($receivedAt ?? $sentAt)->toDateTimeString(),
                    ]);
                }

                if ($receivedAt) {
                    $receivedLogId = DB::table('delivery_note_logs')->insertGetId([
                        'delivery_note_id' => $dnId,
                        'action'           => 'RECEIVED',
                        'actor_id'         => $actor,
                        'actor_name'       => $this->actorName($actor),
                        'notes'            => 'Diterima dan ditandatangani PIC customer.',
                        'created_at'       => $receivedAt->toDateTimeString(),
                        'updated_at'       => $receivedAt->toDateTimeString(),
                    ]);

                    // Evidence wajib ada di alur asli (DeliveryNoteController::receive),
                    // jadi berkasnya benar-benar ditulis — bukan hanya baris tabel —
                    // supaya tombol unduh di halaman detail tidak menghasilkan 404.
                    $path = 'delivery_note_evidence/dummy-sj-' . $dnId . '.txt';
                    Storage::disk('secure')->put(
                        $path,
                        "[DUMMY] Bukti terima Surat Jalan #{$dnId}\nDitandatangani PIC customer pada " . $receivedAt->format('d/m/Y') . ".\n"
                    );

                    DB::table('delivery_note_attachments')->insert([
                        'delivery_note_id'     => $dnId,
                        'delivery_note_log_id' => $receivedLogId,
                        'type'                 => 'evidence',
                        'title'                => 'Bukti Terima (dummy).txt',
                        'file_path'            => $path,
                        'uploaded_by'          => $actor,
                        'created_at'           => $receivedAt->toDateTimeString(),
                        'updated_at'           => $receivedAt->toDateTimeString(),
                    ]);

                    $created['received']++;
                } else {
                    $created['sent']++;
                }

                unset($sentLogId);
            }
        });

        $this->command?->info(sprintf(
            'Dummy Surat Jalan: %d invoice dibuat — %d SJ Sent, %d SJ Received, %d invoice sisa berstatus APPROVED.',
            $created['invoice'],
            $created['sent'],
            $created['received'],
            $created['approved'],
        ));
    }

    /*
    | Pelaku dipilih dari user yang memang memegang modul ini; kalau role-nya
    | belum ada di database, jatuh ke user pertama supaya seeder tetap jalan.
    */
    private function actor(): int
    {
        $byRole = DB::table('users as u')
            ->join('roles as r', 'r.id', '=', 'u.role_id')
            ->whereIn('r.name', ['collection', 'finance', 'superadmin'])
            ->orderByRaw("FIELD(r.name,'collection','finance','superadmin')")
            ->value('u.id');

        return (int) ($byRole ?: DB::table('users')->orderBy('id')->value('id'));
    }

    private function actorName(int $id): ?string
    {
        return DB::table('users')->where('id', $id)->value('name');
    }
}
