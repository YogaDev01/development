<?php

namespace Tests\Feature;

use App\Models\CashReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Menjaga perbaikan BIZ-05 (pembayaran melebihi tagihan, nominal nol) dan
 * SEC-07 (pembayaran dihapus tanpa jejak).
 */
class CashReceiptTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function invoiceSiapBayar()
    {
        return $this->makeInvoice($this->makePoNumber($this->makeProject()), [
            'captured_total' => 10_000_000,
            'status'         => 'SENT',
        ]);
    }

    public function test_pembayaran_melebihi_sisa_tagihan_ditolak(): void
    {
        $invoice = $this->invoiceSiapBayar();

        $this->actingAs($this->userWithRole('finance'))
            ->post('/invoice-cash-receipt/' . $invoice->id . '/cash-receipt', [
                'received_date' => now()->toDateString(),
                'amount'        => '11.000.000',
            ])
            ->assertRedirect();

        $this->assertSame(0, CashReceipt::where('invoice_id', $invoice->id)->count());
    }

    public function test_nominal_nol_ditolak(): void
    {
        $invoice = $this->invoiceSiapBayar();

        // "abc" lolos aturan 'required' yang lama lalu menjadi 0 setelah
        // normalizeMoney() membuang huruf - pembayaran nol yang tercatat.
        $this->actingAs($this->userWithRole('finance'))
            ->post('/invoice-cash-receipt/' . $invoice->id . '/cash-receipt', [
                'received_date' => now()->toDateString(),
                'amount'        => 'abc',
            ]);

        $this->assertSame(0, CashReceipt::where('invoice_id', $invoice->id)->count());
    }

    public function test_pembayaran_sah_tercatat_dan_status_menyesuaikan(): void
    {
        $invoice = $this->invoiceSiapBayar();

        $this->actingAs($this->userWithRole('finance'))
            ->post('/invoice-cash-receipt/' . $invoice->id . '/cash-receipt', [
                'received_date' => now()->toDateString(),
                'amount'        => '4.000.000',
            ]);

        $this->assertSame(1, CashReceipt::where('invoice_id', $invoice->id)->count());
        $this->assertSame('PARTIALLY_PAID', $invoice->fresh()->status);

        $this->actingAs($this->userWithRole('finance'))
            ->post('/invoice-cash-receipt/' . $invoice->id . '/cash-receipt', [
                'received_date' => now()->toDateString(),
                'amount'        => '6.000.000',
            ]);

        $this->assertSame('PAID', $invoice->fresh()->status);
    }

    public function test_hapus_pembayaran_wajib_beralasan(): void
    {
        $invoice = $this->invoiceSiapBayar();
        $cr = CashReceipt::create([
            'invoice_id'    => $invoice->id,
            'received_date' => now()->toDateString(),
            'amount'        => 1_000_000,
        ]);

        $this->actingAs($this->userWithRole('finance'))
            ->delete('/invoice-cash-receipt/' . $invoice->id . '/cash-receipt/' . $cr->id)
            ->assertSessionHasErrors('reason');

        $this->assertNotSoftDeleted('cash_receipts', ['id' => $cr->id]);
    }

    public function test_hapus_pembayaran_menyisakan_jejak(): void
    {
        $invoice = $this->invoiceSiapBayar();
        $cr = CashReceipt::create([
            'invoice_id'    => $invoice->id,
            'received_date' => now()->toDateString(),
            'amount'        => 1_000_000,
        ]);

        $finance = $this->userWithRole('finance');

        $this->actingAs($finance)
            ->delete('/invoice-cash-receipt/' . $invoice->id . '/cash-receipt/' . $cr->id, [
                'reason' => 'Salah input nominal, diganti dengan CR berikutnya.',
            ])
            ->assertRedirect();

        // Baris tetap ada (bisa diaudit) tapi tidak lagi ikut perhitungan.
        $this->assertSoftDeleted('cash_receipts', ['id' => $cr->id]);
        $this->assertDatabaseHas('cash_receipts', [
            'id'            => $cr->id,
            'deleted_by'    => $finance->id,
            'delete_reason' => 'Salah input nominal, diganti dengan CR berikutnya.',
        ]);
        $this->assertSame(0, (int) $invoice->fresh()->totalReceived());
    }
}
