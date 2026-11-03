<?php

namespace Tests\Feature;

use App\Models\CashReceiptHeader;
use App\Models\Invoice;
use App\Models\ProjectPoNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Total invoice (captured_total) selalu mengikuti PPh terbaru, termasuk data lama yang basi,
 * dan form pembayaran di halaman invoice memakai batas sisa bruto + kolom PPh.
 * Ditulis untuk dijalankan setelah gerbang CLAUDE.md 4.3 terpenuhi; BELUM dijalankan.
 */
class CashReceiptPphTotalHealTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    private User $user;
    private ProjectPoNumber $po;

    /** DPP 250.000 + PPN 27.500; PPh $iw; captured_total diberikan apa adanya (bisa basi). */
    private function invoice(int $iw, int $captured): Invoice
    {
        $this->user ??= $this->userWithRole('superadmin');
        $this->po ??= $this->makePoNumber($this->makeProject(), ['nominal' => 500_000_000]);

        return $this->makeInvoice($this->po, [
            'subtotal' => 250_000, 'billed_amount' => 250_000, 'tax_amount' => 27_500,
            'withholding_tax' => $iw, 'captured_total' => $captured, 'status' => 'SENT',
        ]);
    }

    public function test_total_basi_dihitung_ulang_walau_pph_sama_dengan_yang_diinput(): void
    {
        $inv = $this->invoice(5_000, 277_500); // PPh sudah ada, Total belum net

        $this->actingAs($this->user)->post(route('cash-receipt.store'), [
            'received_date' => '2026-10-20',
            'customer_id'   => $this->po->project->customer_id,
            'invoice_ids'   => [$inv->id],
            'amounts'       => [$inv->id => '272500'],
            'withholding'   => [$inv->id => '5000'],
        ])->assertSessionHasNoErrors();

        $inv->refresh();
        $this->assertSame(272_500, (int) $inv->captured_total);
        $this->assertSame('PAID', $inv->status);
    }

    public function test_form_invoice_dengan_pph_menghitung_ulang_total_dan_melunasi(): void
    {
        $inv = $this->invoice(0, 277_500);

        $this->actingAs($this->user)->post(route('invoice-cash-receipt.cash-receipt.store', $inv->id), [
            'received_date'   => '2026-10-20',
            'amount'          => '272500',
            'withholding_tax' => '5000',
        ])->assertSessionHas('success');

        $inv->refresh();
        $this->assertSame(5_000, (int) $inv->withholding_tax);
        $this->assertSame(272_500, (int) $inv->captured_total);
        $this->assertSame('PAID', $inv->status);
    }

    public function test_form_invoice_menolak_melebihi_sisa_bruto(): void
    {
        $inv = $this->invoice(0, 272_500); // Total basi lebih rendah dari bruto 277.500

        // Di atas sisa bruto: ditolak.
        $this->actingAs($this->user)->post(route('invoice-cash-receipt.cash-receipt.store', $inv->id), [
            'received_date' => '2026-10-20',
            'amount'        => '277501',
        ])->assertSessionHas('error');
        $this->assertSame(0, $inv->cashReceipts()->count());

        // Pelunasan bruto penuh: dulu ditolak (batas = captured_total basi), kini diterima.
        $this->actingAs($this->user)->post(route('invoice-cash-receipt.cash-receipt.store', $inv->id), [
            'received_date' => '2026-10-20',
            'amount'        => '277500',
        ])->assertSessionHas('success');
        $this->assertSame(1, $inv->cashReceipts()->count());
    }

    public function test_command_repair_dry_run_tidak_mengubah_dan_apply_memperbaiki(): void
    {
        $inv = $this->invoice(5_000, 277_500);

        $this->artisan('invoice:repair-captured-total')->assertExitCode(0);
        $this->assertSame(277_500, (int) $inv->fresh()->captured_total);

        $this->artisan('invoice:repair-captured-total', ['--apply' => true])->assertExitCode(0);
        $this->assertSame(272_500, (int) $inv->fresh()->captured_total);

        // Idempoten.
        $this->artisan('invoice:repair-captured-total', ['--apply' => true])->assertExitCode(0);
        $this->assertSame(272_500, (int) $inv->fresh()->captured_total);
    }

    public function test_invoice_id_ganda_ditolak_validasi(): void
    {
        $inv = $this->invoice(0, 277_500);

        $this->actingAs($this->user)->post(route('cash-receipt.store'), [
            'received_date' => '2026-10-20',
            'customer_id'   => $this->po->project->customer_id,
            'invoice_ids'   => [$inv->id, $inv->id],
            'amounts'       => [$inv->id => '277500'],
        ])->assertSessionHasErrors('invoice_ids.1');

        $this->assertSame(0, $inv->cashReceipts()->count());
    }

    public function test_header_batal_tidak_bisa_dihapus(): void
    {
        $this->user = $this->userWithRole('superadmin');
        $po = $this->makePoNumber($this->makeProject(), ['nominal' => 1_000]);
        $header = CashReceiptHeader::create([
            'code' => CashReceiptHeader::generateCode(now()), 'received_date' => '2026-10-20',
            'customer_id' => $po->project->customer_id, 'total_amount' => 0, 'status' => 'CANCELLED',
            'created_by' => $this->user->id, 'proof_file' => 'cash_receipt_proofs/x.pdf',
        ]);

        $this->actingAs($this->user)->delete(route('cash-receipt.destroy', $header->id))
            ->assertSessionHas('error');

        $this->assertNotNull(CashReceiptHeader::find($header->id));
    }
}
