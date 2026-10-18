<?php

namespace Tests\Feature;

use App\Models\CashReceipt;
use App\Models\CashReceiptHeader;
use App\Models\Invoice;
use App\Models\ProjectPoNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Cash Receipt Header/Detail (M-03): alokasi, pembatalan alokasi, dan pembatalan
 * bukti terima tetap menyinkronkan status invoice dan No. PO (penagihan per BAST),
 * dan semuanya mengunci No. PO lebih dulu, baru invoice, baru detail.
 */
class CashReceiptSyncTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    private User $user;

    /** @return array{0: ProjectPoNumber, 1: Invoice, 2: CashReceiptHeader} */
    private function siapkan(): array
    {
        $this->user = $this->userWithRole('superadmin');
        $po = $this->makePoNumber($this->makeProject(), ['nominal' => 100_000_000, 'status' => 'BAST_SUBMITTED']);
        $bast = $this->makeBast($po, 100_000_000);
        $inv = $this->makeInvoice($po, [
            'billed_amount' => 100_000_000, 'captured_total' => 111_000_000, 'status' => 'SENT',
        ], $bast);
        $header = CashReceiptHeader::create([
            'code'          => CashReceiptHeader::generateCode(now()),
            'received_date' => '2026-10-20',
            'customer_id'   => $po->project->customer_id,
            'total_amount'  => 111_000_000,
            'status'        => 'DRAFT',
            'created_by'    => $this->user->id,
        ]);

        return [$po, $inv, $header];
    }

    private function alokasi(CashReceiptHeader $header, Invoice $inv)
    {
        return $this->actingAs($this->user)->post(route('cash-receipt.allocate', $header->id), [
            'invoice_id' => $inv->id,
            'amount'     => '111000000',
        ]);
    }

    /** Posisi pertama pernyataan yang cocok dengan semua potongan, atau null. */
    private function posisi(array $log, string ...$potongan): ?int
    {
        foreach ($log as $i => $sql) {
            foreach ($potongan as $p) {
                if (! str_contains($sql, $p)) {
                    continue 2;
                }
            }

            return $i;
        }

        return null;
    }

    /** Jalankan $aksi sambil merekam SQL; kembalikan SQL berhuruf kecil berurutan. */
    private function rekam(\Closure $aksi): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $aksi();
        $log = array_map(fn ($q) => strtolower($q['query']), DB::getQueryLog());
        DB::disableQueryLog();

        return $log;
    }

    private function assertNoPoDikunciLebihDulu(array $log, string $tulis): void
    {
        $po      = $this->posisi($log, 'from `project_po_numbers`', 'for update');
        $invoice = $this->posisi($log, 'from `invoices`', 'for update');
        $detail  = $this->posisi($log, $tulis);

        $this->assertNotNull($po, 'No. PO tidak dikunci');
        $this->assertNotNull($invoice, 'invoice tidak dikunci');
        $this->assertNotNull($detail, "tidak ada pernyataan {$tulis}");
        $this->assertLessThan($invoice, $po, 'No. PO harus dikunci sebelum invoice');
        $this->assertLessThan($detail, $invoice, 'invoice harus dikunci sebelum detail ditulis');
    }

    public function test_alokasi_menyinkronkan_invoice_dan_no_po_dengan_urutan_kunci_benar(): void
    {
        [$po, $inv, $header] = $this->siapkan();

        $log = $this->rekam(fn () => $this->alokasi($header, $inv)->assertSessionHas('success'));

        $this->assertSame('PAID', $inv->fresh()->status);
        $this->assertSame('PAID', $po->fresh()->status);
        $this->assertSame('ALLOCATED', $header->fresh()->status);
        $this->assertNoPoDikunciLebihDulu($log, 'insert into `cash_receipts`');
    }

    public function test_pembatalan_alokasi_menurunkan_status_dan_berjalan_dalam_transaksi_berurutan_kunci(): void
    {
        [$po, $inv, $header] = $this->siapkan();
        $this->alokasi($header, $inv);
        $detail = CashReceipt::where('invoice_id', $inv->id)->firstOrFail();

        $log = $this->rekam(fn () => $this->actingAs($this->user)
            ->delete(route('cash-receipt.remove-allocation', [$header->id, $detail->id]))
            ->assertSessionHas('success'));

        $this->assertSoftDeleted('cash_receipts', ['id' => $detail->id]);
        $this->assertSame('SENT', $inv->fresh()->status);
        $this->assertSame('INVOICED', $po->fresh()->status, 'satu-satunya BAST masih ditagih, belum lunas');
        $this->assertSame('DRAFT', $header->fresh()->status);
        // Dulu update + delete berjalan tanpa transaksi dan tanpa kunci: kini No. PO -> invoice -> detail.
        $this->assertNoPoDikunciLebihDulu($log, 'update `cash_receipts`');
    }

    public function test_pembatalan_bukti_terima_membatalkan_alokasi_dan_menyinkronkan_status(): void
    {
        [$po, $inv, $header] = $this->siapkan();
        $this->alokasi($header, $inv);
        $this->assertSame('PAID', $po->fresh()->status);

        $log = $this->rekam(fn () => $this->actingAs($this->user)
            ->post(route('cash-receipt.cancel', $header->id), ['cancel_reason' => 'salah catat'])
            ->assertSessionHas('success'));

        $this->assertSame('CANCELLED', $header->fresh()->status);
        $this->assertSame(0, CashReceipt::where('invoice_id', $inv->id)->count());
        $this->assertSame('SENT', $inv->fresh()->status);
        $this->assertSame('INVOICED', $po->fresh()->status);
        $this->assertNoPoDikunciLebihDulu($log, 'update `cash_receipts`');
    }

    public function test_penerimaan_baru_menutup_invoice_dan_menaikkan_no_po_ke_paid(): void
    {
        [$po, $inv] = $this->siapkan();

        $log = $this->rekam(fn () => $this->actingAs($this->user)
            ->post(route('cash-receipt.store'), [
                'received_date' => '2026-10-21',
                'customer_id'   => $po->project->customer_id,
                'invoice_ids'   => [$inv->id],
                'amounts'       => [$inv->id => '111000000'],
            ])->assertSessionHas('success'));

        $this->assertSame('PAID', $inv->fresh()->status);
        $this->assertSame('PAID', $po->fresh()->status);
        $this->assertNoPoDikunciLebihDulu($log, 'insert into `cash_receipts`');
    }
}
