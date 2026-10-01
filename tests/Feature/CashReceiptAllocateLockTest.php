<?php

namespace Tests\Feature;

use App\Models\CashReceipt;
use App\Models\CashReceiptHeader;
use App\Models\Invoice;
use App\Models\SourceFund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * H3 — alokasi bukti terima tidak boleh melampaui uang yang diterima, dan
 * tidak boleh tercatat pada bukti terima yang sedang dibatalkan.
 *
 * allocate() dulu hanya mengunci invoice; sekarang header dikunci lebih dulu
 * (lalu invoice), dan cancel() memakai urutan yang sama.
 *
 * Batasan: celah "dua alokasi ke dua invoice berbeda" bergantung pada dua
 * koneksi yang belum saling melihat commit - tidak bisa diulang di satu
 * koneksi RefreshDatabase, jadi diverifikasi lewat review + UAT dua sesi.
 * Yang diuji deterministik di sini: pembatalan yang terjadi tepat setelah
 * header dibaca tanpa kunci, ditambah regresi alur normal.
 */
class CashReceiptAllocateLockTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private int $customerId;

    private function invoice(int $total = 10_000_000): Invoice
    {
        $this->customerId ??= $this->makeCustomer()->id;

        return $this->makeInvoice(
            $this->makePoNumber($this->makeProject(['customer_id' => $this->customerId])),
            ['captured_total' => $total, 'status' => 'SENT'],
        );
    }

    private function header(int $total): CashReceiptHeader
    {
        $this->customerId ??= $this->makeCustomer()->id;

        return CashReceiptHeader::create([
            'code'           => 'RCV-UJI-' . uniqid(),
            'received_date'  => now()->toDateString(),
            'customer_id'    => $this->customerId,
            'source_fund_id' => SourceFund::create(['name' => 'Rekening Uji', 'status' => 'ACTIVE'])->id,
            'total_amount'   => $total,
            'status'         => 'DRAFT',
        ]);
    }

    private function allocate(User $user, CashReceiptHeader $header, Invoice $invoice, string $amount)
    {
        return $this->actingAs($user)->post('/cash-receipt/' . $header->id . '/allocate', [
            'invoice_id' => $invoice->id,
            'amount'     => $amount,
        ]);
    }

    public function test_alokasi_penuh_tercatat_dan_header_allocated(): void
    {
        $invoice = $this->invoice();
        $header  = $this->header(10_000_000);

        $this->allocate($this->userWithRole('finance'), $header, $invoice, '10.000.000')
            ->assertSessionMissing('error');

        $this->assertSame(1, CashReceipt::where('cash_receipt_header_id', $header->id)->count());
        $this->assertSame('ALLOCATED', $header->refresh()->status);
        $this->assertSame('PAID', $invoice->refresh()->status);
    }

    public function test_alokasi_berurutan_melebihi_uang_header_ditolak(): void
    {
        $user    = $this->userWithRole('finance');
        $header  = $this->header(10_000_000);
        $invoiceX = $this->invoice();
        $invoiceY = $this->invoice();

        $this->allocate($user, $header, $invoiceX, '8.000.000')->assertSessionMissing('error');
        $this->allocate($user, $header, $invoiceY, '8.000.000')
            ->assertSessionHas('error', fn ($pesan) => str_contains($pesan, 'melebihi sisa uang'));

        $this->assertSame(1, CashReceipt::where('cash_receipt_header_id', $header->id)->count());
        $this->assertSame(0, CashReceipt::where('invoice_id', $invoiceY->id)->count());
    }

    public function test_alokasi_pada_header_yang_baru_saja_dibatalkan_ditolak(): void
    {
        $invoice = $this->invoice();
        $header  = $this->header(10_000_000);

        // Tepat setelah header dibaca tanpa kunci, pengguna lain
        // membatalkan bukti terima ini.
        $sudah = false;
        DB::listen(function ($query) use ($header, &$sudah) {
            if ($sudah || ! str_contains($query->sql, 'from `cash_receipt_headers`')) {
                return;
            }
            $sudah = true;
            DB::table('cash_receipt_headers')->where('id', $header->id)->update(['status' => 'CANCELLED']);
        });

        $this->allocate($this->userWithRole('finance'), $header, $invoice, '5.000.000')
            ->assertSessionHas('error', 'Penerimaan yang sudah dibatalkan tidak bisa dialokasikan.');

        $this->assertSame(0, CashReceipt::where('cash_receipt_header_id', $header->id)->count());
        $this->assertSame('SENT', $invoice->refresh()->status);
    }

    public function test_pembatalan_menganulir_alokasi_dan_status_invoice(): void
    {
        $user    = $this->userWithRole('finance');
        $invoice = $this->invoice();
        $header  = $this->header(10_000_000);

        $this->allocate($user, $header, $invoice, '10.000.000');
        $this->assertSame('PAID', $invoice->refresh()->status);

        $this->actingAs($user)
            ->post('/cash-receipt/' . $header->id . '/cancel', ['cancel_reason' => 'Salah input'])
            ->assertSessionMissing('error');

        $this->assertSame('CANCELLED', $header->refresh()->status);
        $this->assertSame(0, CashReceipt::where('cash_receipt_header_id', $header->id)->count());
        $this->assertNotSame('PAID', $invoice->refresh()->status);
    }

    public function test_pembatalan_kedua_ditolak(): void
    {
        $user   = $this->userWithRole('finance');
        $header = $this->header(10_000_000);

        $this->actingAs($user)->post('/cash-receipt/' . $header->id . '/cancel', ['cancel_reason' => 'Pertama']);
        $this->actingAs($user)
            ->post('/cash-receipt/' . $header->id . '/cancel', ['cancel_reason' => 'Kedua'])
            ->assertSessionHas('error', 'Penerimaan ini sudah dibatalkan.');

        $this->assertSame('Pertama', $header->refresh()->cancel_reason);
    }
}
