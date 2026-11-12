<?php

namespace Tests\Feature;

use App\Models\CashReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Edit Cash Receipt & akses edit invoice oleh Superadmin di status apa pun
 * (2026-11-12). Nominal tidak boleh membuat invoice lebih bayar; status
 * pembayaran disinkron ulang lewat hook CashReceipt::saved.
 */
class CashReceiptSuperEditTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('secure');
    }

    /** Invoice bertagihan bruto 10 juta (DPP 9 jt + PPN 1 jt) dengan satu pembayaran 4 juta. */
    private function invoiceDenganPembayaran(): array
    {
        $invoice = $this->makeInvoice($this->makePoNumber($this->makeProject()), [
            'subtotal'       => 9_000_000,
            'billed_amount'  => 9_000_000,
            'tax_amount'     => 1_000_000,
            'captured_total' => 10_000_000,
            'status'         => 'SENT',
        ]);
        $cr = CashReceipt::create([
            'invoice_id'    => $invoice->id,
            'received_date' => now()->toDateString(),
            'amount'        => 4_000_000,
        ]);

        return [$invoice->fresh(), $cr];
    }

    private function url($invoice, $cr): string
    {
        return '/invoice-cash-receipt/' . $invoice->id . '/cash-receipt/' . $cr->id . '/super-update';
    }

    public function test_superadmin_mengubah_nominal_dan_status_ikut_tersinkron(): void
    {
        [$invoice, $cr] = $this->invoiceDenganPembayaran();
        $this->assertSame('PARTIALLY_PAID', $invoice->status);

        $this->actingAs($this->userWithRole('superadmin'))
            ->post($this->url($invoice, $cr), [
                'received_date' => now()->toDateString(),
                'amount'        => '10.000.000',
            ])
            ->assertSessionHas('success');

        $this->assertSame(10_000_000, (int) $cr->fresh()->amount);
        $this->assertSame('PAID', $invoice->fresh()->status);
        // Jejak di Log invoice.
        $this->assertTrue($invoice->comments()->where('comment', 'like', '[EDIT CASH RECEIPT%')->exists());
    }

    public function test_nominal_melebihi_sisa_tagihan_ditolak(): void
    {
        [$invoice, $cr] = $this->invoiceDenganPembayaran();

        $this->actingAs($this->userWithRole('superadmin'))
            ->post($this->url($invoice, $cr), [
                'received_date' => now()->toDateString(),
                'amount'        => '11.000.000',
            ])
            ->assertSessionHas('error');

        $this->assertSame(4_000_000, (int) $cr->fresh()->amount);
        $this->assertSame('PARTIALLY_PAID', $invoice->fresh()->status);
    }

    public function test_selain_superadmin_tidak_boleh_edit_cash_receipt(): void
    {
        [$invoice, $cr] = $this->invoiceDenganPembayaran();

        $this->actingAs($this->userWithRole('finance'))
            ->post($this->url($invoice, $cr), [
                'received_date' => now()->toDateString(),
                'amount'        => '5.000.000',
            ])
            ->assertForbidden();

        $this->assertSame(4_000_000, (int) $cr->fresh()->amount);
    }

    public function test_edit_invoice_non_draft_hanya_untuk_superadmin(): void
    {
        [$invoice] = $this->invoiceDenganPembayaran();

        // Finance: tetap hanya Draft -> dialihkan ke detail dengan pesan.
        $this->actingAs($this->userWithRole('finance'))
            ->get('/invoice-cash-receipt/' . $invoice->id . '/edit')
            ->assertRedirect('/invoice-cash-receipt/' . $invoice->id)
            ->assertSessionHas('error');

        // Superadmin: form edit terbuka walau invoice sudah Partially Paid.
        $this->actingAs($this->userWithRole('superadmin'))
            ->get('/invoice-cash-receipt/' . $invoice->id . '/edit')
            ->assertOk()
            ->assertSee('Edit Superadmin');
    }
}
