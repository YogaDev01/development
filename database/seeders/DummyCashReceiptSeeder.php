<?php

namespace Database\Seeders;

use App\Models\CashReceipt;
use App\Models\CashReceiptHeader;
use App\Models\Invoice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| DUMMY CASH RECEIPT (M-03)
|--------------------------------------------------------------------------
| Halaman Cash Receipt kosong sampai ada bukti terima yang tercatat. Seeder
| ini mengisinya dengan memakai invoice yang SUDAH ADA di database — invoice
| yang berada di tahap penagihan (SENT/RECEIVED/PARTIALLY_PAID) dan masih
| punya sisa tagihan.
|
| Sengaja TIDAK membuat invoice baru: kalau seeder ini ikut mencetak invoice,
| angka Collection dan Summary jadi bergeser tanpa ada yang meminta. Bahan
| invoice-nya disiapkan seeder rantai (DummyChainSeeder /
| DummyDeliveryNoteSeeder) — jalankan itu dulu kalau daftarnya kosong.
|
| Yang dibentuk sengaja bermacam supaya tiap keadaan di layar ada contohnya:
|   - ALLOCATED : uang masuk habis dialokasikan ke invoice (bisa >1 invoice)
|   - DRAFT     : uang masuk lebih besar dari alokasinya — sisanya muncul di
|                 kartu "Belum Teralokasi" dan bisa dicoba alokasi manual
|   - CANCELLED : bukti terima yang dibatalkan, untuk menguji tampilan status
|
| Alokasi ditulis lewat model CashReceipt supaya hook-nya jalan: status
| pembayaran invoice (PARTIALLY_PAID/PAID) ikut tersinkron, sama seperti
| kalau dicatat manual dari layar.
|
| Semua baris ditandai [DUMMY] pada notes agar mudah dibersihkan.
*/
class DummyCashReceiptSeeder extends Seeder
{
    public function run(): void
    {
        $actor    = $this->actor();
        $sourceId = DB::table('source_funds')->value('id');

        // Invoice yang boleh menerima pembayaran, dikelompokkan per customer —
        // satu bukti terima hanya boleh menutup invoice milik satu customer.
        $perCustomer = Invoice::with(['cashReceipts', 'po'])
            ->whereIn('status', ['SENT', 'RECEIVED', 'PARTIALLY_PAID'])
            ->orderBy('due_date')
            ->get()
            ->filter(fn ($inv) => $inv->po?->customer_id && $inv->remainingBalance() > 0)
            ->groupBy(fn ($inv) => $inv->po->customer_id);

        if ($perCustomer->isEmpty()) {
            $this->command?->warn(
                'Tidak ada invoice yang siap dibayar (SENT/RECEIVED/PARTIALLY_PAID dengan sisa tagihan). ' .
                'Jalankan DummyChainSeeder atau DummyDeliveryNoteSeeder dulu.'
            );
            return;
        }

        // 'F' penuh, 'P' sebagian (ada sisa belum teralokasi), 'X' dibatalkan.
        $plan    = ['F', 'P', 'F', 'X', 'P', 'F'];
        $dibuat  = ['ALLOCATED' => 0, 'DRAFT' => 0, 'CANCELLED' => 0, 'detail' => 0];
        $urutan  = 0;

        DB::transaction(function () use ($perCustomer, $plan, $actor, $sourceId, &$dibuat, &$urutan) {
            foreach ($perCustomer as $customerId => $invoices) {
                $mode = $plan[$urutan % count($plan)];
                $date = Carbon::now()->subDays(45 - ($urutan * 6));

                // Satu bukti terima menutup 1-2 invoice; itulah gunanya header.
                $dipakai = $invoices->take($mode === 'F' ? 2 : 1);

                $baris = [];
                foreach ($dipakai as $inv) {
                    $sisa = $inv->remainingBalance();

                    if ($sisa <= 0) {
                        continue;
                    }

                    // Mode 'P' membayar separuh sisa supaya invoice-nya jatuh ke
                    // PARTIALLY_PAID — keadaan yang paling sering ditemui nyata.
                    $nominal = $mode === 'P' ? (int) floor($sisa / 2) : $sisa;

                    if ($nominal <= 0) {
                        continue;
                    }

                    $baris[] = ['invoice' => $inv, 'amount' => $nominal];
                }

                if (!$baris) {
                    continue;
                }

                $urutan++;

                $totalAlokasi = array_sum(array_column($baris, 'amount'));

                // Pada mode 'P' uang yang diterima sengaja dibuat lebih besar
                // dari alokasinya — selisih inilah yang jadi "Belum Teralokasi".
                $totalDiterima = $mode === 'P'
                    ? $totalAlokasi + (int) round($totalAlokasi * 0.35)
                    : $totalAlokasi;

                $status = match ($mode) {
                    'X'     => 'CANCELLED',
                    'P'     => 'DRAFT',
                    default => 'ALLOCATED',
                };

                $header = CashReceiptHeader::create([
                    'code'             => CashReceiptHeader::generateCode($date),
                    'received_date'    => $date->toDateString(),
                    'customer_id'      => $customerId,
                    'source_fund_id'   => $sourceId,
                    'reference_number' => 'TRF/' . $date->format('ymd') . '/' . str_pad((string) $urutan, 3, '0', STR_PAD_LEFT),
                    'total_amount'     => $totalDiterima,
                    'status'           => $status,
                    'notes'            => '[DUMMY] Transfer masuk bahan uji modul Cash Receipt.',
                    'created_by'       => $actor,
                    'allocated_by'     => $status === 'ALLOCATED' ? $actor : null,
                    'allocated_at'     => $status === 'ALLOCATED' ? $date->copy()->addHours(3) : null,
                    'cancelled_by'     => $status === 'CANCELLED' ? $actor : null,
                    'cancelled_at'     => $status === 'CANCELLED' ? $date->copy()->addDay() : null,
                    'cancel_reason'    => $status === 'CANCELLED' ? 'Dana ternyata milik customer lain — dicatat ulang.' : null,
                ]);

                // Bukti terima yang dibatalkan tidak boleh meninggalkan alokasi:
                // uangnya dianggap tidak pernah masuk, jadi invoice-nya bersih.
                if ($status === 'CANCELLED') {
                    $dibuat['CANCELLED']++;
                    continue;
                }

                foreach ($baris as $b) {
                    CashReceipt::create([
                        'cash_receipt_header_id' => $header->id,
                        'invoice_id'             => $b['invoice']->id,
                        'received_date'          => $date->toDateString(),
                        'amount'                 => $b['amount'],
                        'withholding_tax'        => 0,
                        'source_fund_id'         => $sourceId,
                        'notes'                  => '[DUMMY] alokasi otomatis seeder.',
                        'input_by'               => $actor,
                    ]);

                    $dibuat['detail']++;
                }

                $dibuat[$status]++;
            }
        });


        $this->command?->info(sprintf(
            'Dummy Cash Receipt: %d ALLOCATED, %d DRAFT (ada sisa belum teralokasi), %d CANCELLED — total %d baris alokasi.',
            $dibuat['ALLOCATED'],
            $dibuat['DRAFT'],
            $dibuat['CANCELLED'],
            $dibuat['detail'],
        ));
    }

    /*
    | Pelaku dipilih dari role yang memang memegang modul ini; kalau tidak ada,
    | jatuh ke user pertama supaya seeder tetap jalan.
    */
    private function actor(): ?int
    {
        $byRole = DB::table('users as u')
            ->join('roles as r', 'r.id', '=', 'u.role_id')
            ->whereIn('r.name', ['collection', 'finance', 'superadmin'])
            ->orderByRaw("FIELD(r.name,'collection','finance','superadmin')")
            ->value('u.id');

        return $byRole ?: DB::table('users')->value('id');
    }
}
