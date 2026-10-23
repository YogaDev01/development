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
 * Sisa tagihan bruto (Invoice + PPN) dan sinkron PPh Cash Receipt -> Invoice.
 * Ditulis untuk dijalankan setelah gerbang CLAUDE.md 4.3 terpenuhi; BELUM dijalankan.
 */
class CashReceiptTagihanPphTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    private User $user;
    private ProjectPoNumber $po;

    /** Invoice konsisten: DPP 100jt + PPN 11jt (tarif 11%), PPh di muka $iw. */
    private function invoice(int $iw = 0): Invoice
    {
        $this->user ??= $this->userWithRole('superadmin');
        $this->po ??= $this->makePoNumber($this->makeProject(), ['nominal' => 500_000_000]);

        return $this->makeInvoice($this->po, [
            'subtotal' => 100_000_000, 'billed_amount' => 100_000_000, 'tax_amount' => 11_000_000,
            'withholding_tax' => $iw, 'captured_total' => 111_000_000 - $iw, 'status' => 'SENT',
        ]);
    }

    private function simpan(Invoice $inv, string $amount, string $pph = '')
    {
        return $this->actingAs($this->user)->post(route('cash-receipt.store'), [
            'received_date' => '2026-10-20',
            'customer_id'   => $this->po->project->customer_id,
            'invoice_ids'   => [$inv->id],
            'amounts'       => [$inv->id => $amount],
            'withholding'   => [$inv->id => $pph],
            'partial_note'  => 'parsial uji',
        ]);
    }

    public function test_sisa_memakai_invoice_plus_ppn_bukan_captured_total(): void
    {
        $inv = $this->invoice(2_000_000);
        $this->assertSame(111_000_000, $inv->sisaBruto());
        $this->assertSame(109_000_000, $inv->remainingBalance());
    }

    public function test_sisa_dikurangi_penerimaan_dan_pph_sebelumnya(): void
    {
        $inv = $this->invoice();
        $cr = CashReceipt::create(['invoice_id' => $inv->id, 'received_date' => '2026-10-01', 'amount' => 50_000_000, 'withholding_tax' => 1_000_000]);
        $this->assertSame(60_000_000, $inv->sisaBruto());
        $this->assertSame(111_000_000, $inv->sisaBruto($cr->id));
    }

    public function test_hitung_diterima_data_lama_tidak_berubah(): void
    {
        $this->assertSame(5_000, Invoice::hitungDiterima(5_000, 0, 200));
        $this->assertSame(5_300, Invoice::hitungDiterima(5_000, 300, 0));
        $this->assertSame(5_300, Invoice::hitungDiterima(5_000, 300, 200));
        $this->assertSame(5_000, Invoice::hitungDiterima(5_000, 300, 300));
    }

    public function test_pph_receipt_memperbarui_pph_invoice_captured_dan_status_paid(): void
    {
        $inv = $this->invoice();
        $this->simpan($inv, '108.000.000', '3.000.000')->assertSessionHas('success');

        $inv->refresh();
        $this->assertSame(3_000_000, (int) $inv->withholding_tax);
        $this->assertSame(108_000_000, (int) $inv->captured_total);
        $this->assertSame('PAID', $inv->status);
        $this->assertSame(1, $inv->comments()->where('comment', 'like', 'PPh invoice diubah dari 0 ke 3.000.000%')->count());
    }

    public function test_pph_sama_dengan_di_muka_tidak_berubah_dan_tanpa_log(): void
    {
        $inv = $this->invoice(3_000_000);
        $this->simpan($inv, '108.000.000', '3.000.000')->assertSessionHas('success');

        $inv->refresh();
        $this->assertSame(3_000_000, (int) $inv->withholding_tax);
        $this->assertSame(0, $inv->comments()->count());
        $this->assertSame('PAID', $inv->status);
    }

    public function test_pph_aktual_berbeda_menggantikan_dan_dilog(): void
    {
        $inv = $this->invoice(2_000_000);
        $this->simpan($inv, '108.000.000', '3.000.000')->assertSessionHas('success');

        $this->assertSame(3_000_000, (int) $inv->refresh()->withholding_tax);
        $this->assertSame(1, $inv->comments()->count());
        $this->assertDatabaseHas('user_activity_logs', ['module' => 'invoice', 'action' => 'update']);
    }

    public function test_parsial_tanpa_pph_tidak_mengubah_pph_di_muka(): void
    {
        $inv = $this->invoice(2_000_000);
        $this->simpan($inv, '50.000.000')->assertSessionHas('success');

        $inv->refresh();
        $this->assertSame(2_000_000, (int) $inv->withholding_tax);
        $this->assertSame('PARTIALLY_PAID', $inv->status);
    }

    public function test_penuh_dengan_pph_nol_menolkan_pph_di_muka(): void
    {
        $inv = $this->invoice(2_000_000);
        $this->simpan($inv, '111.000.000')->assertSessionHas('success');

        $inv->refresh();
        $this->assertSame(0, (int) $inv->withholding_tax);
        $this->assertSame(111_000_000, (int) $inv->captured_total);
        $this->assertSame('PAID', $inv->status);
    }

    public function test_diterima_melebihi_sisa_bruto_ditolak(): void
    {
        $inv = $this->invoice();
        $this->simpan($inv, '111.000.001')->assertSessionHas('error');
        $this->assertSame(0, CashReceipt::count());
    }

    public function test_hapus_alokasi_membalik_pph_dan_menurunkan_status(): void
    {
        $inv = $this->invoice();
        $header = CashReceiptHeader::create([
            'code' => 'CR/UJI/1', 'received_date' => '2026-10-20', 'customer_id' => $this->po->project->customer_id,
            'total_amount' => 111_000_000, 'status' => 'DRAFT',
        ]);
        $this->actingAs($this->user)->post(route('cash-receipt.allocate', $header->id), [
            'invoice_id' => $inv->id, 'amount' => '108000000', 'withholding_tax' => '3000000',
        ])->assertSessionHas('success');
        $this->assertSame('PAID', $inv->refresh()->status);

        $cr = CashReceipt::firstOrFail();
        $this->actingAs($this->user)
            ->delete(route('cash-receipt.remove-allocation', [$header->id, $cr->id]))
            ->assertSessionHas('success');

        $inv->refresh();
        $this->assertSame(0, (int) $inv->withholding_tax);
        $this->assertSame(111_000_000, (int) $inv->captured_total);
        $this->assertSame('SENT', $inv->status);
    }

    private function headerDenganReceipt(Invoice $inv, int $amount, int $pph): array
    {
        $header = CashReceiptHeader::create([
            'code' => 'CR/UJI/' . $inv->id, 'received_date' => '2026-10-20', 'customer_id' => $this->po->project->customer_id,
            'total_amount' => $amount + $pph, 'status' => 'ALLOCATED',
        ]);
        $cr = CashReceipt::create([
            'cash_receipt_header_id' => $header->id, 'invoice_id' => $inv->id, 'received_date' => '2026-10-20',
            'amount' => $amount, 'withholding_tax' => $pph,
        ]);

        return [$header, $cr];
    }

    public function test_pembalikan_tidak_menolkan_pph_di_muka_legacy(): void
    {
        // PPh di muka 2jt (captured 109jt), receipt lama A=108jt P=1: PPh invoice (2jt) != total PPh receipt (1) -> bukan hasil sinkron.
        $inv = $this->invoice(2_000_000);
        [$header, $cr] = $this->headerDenganReceipt($inv, 108_000_000, 1);

        $this->actingAs($this->user)
            ->delete(route('cash-receipt.remove-allocation', [$header->id, $cr->id]))
            ->assertSessionHas('success');

        $inv->refresh();
        $this->assertSame(2_000_000, (int) $inv->withholding_tax);
        $this->assertSame(109_000_000, (int) $inv->captured_total);
        $this->assertSame(0, $inv->comments()->where('comment', 'like', 'PPh invoice diubah%')->count());
    }

    public function test_pembalikan_hasil_sinkron_tetap_menolkan_pph(): void
    {
        // PPh invoice 3jt == PPh receipt 3jt (rezim sinkron) -> dibalik jadi 0.
        $inv = $this->invoice(3_000_000);
        [$header, $cr] = $this->headerDenganReceipt($inv, 108_000_000, 3_000_000);

        $this->actingAs($this->user)
            ->post(route('cash-receipt.cancel', $header->id), ['cancel_reason' => 'uji'])
            ->assertSessionHas('success');

        $inv->refresh();
        $this->assertSame(0, (int) $inv->withholding_tax);
        $this->assertSame(111_000_000, (int) $inv->captured_total);
    }

    public function test_tanpa_pph_melebihi_captured_setelah_pph_di_muka_ditolak(): void
    {
        // PPh di muka 2jt, captured 109jt: 110jt tanpa PPh bukan pelunasan bruto (111jt) dan melebihi captured.
        $inv = $this->invoice(2_000_000);
        $this->simpan($inv, '110.000.000')->assertSessionHas('error');
        $this->assertSame(0, CashReceipt::count());
        $this->assertSame(2_000_000, (int) $inv->refresh()->withholding_tax);

        // Sebesar captured masih boleh; PPh di muka tetap.
        $this->simpan($inv, '109.000.000')->assertSessionHas('success');
        $inv->refresh();
        $this->assertSame(2_000_000, (int) $inv->withholding_tax);
        $this->assertSame('PAID', $inv->status);
    }

    public function test_pembalikan_pada_invoice_inkonsisten_tidak_memblokir_dan_pph_tak_berubah(): void
    {
        $inv = $this->invoice(3_000_000);
        [$header, $cr] = $this->headerDenganReceipt($inv, 108_000_000, 3_000_000);
        $inv->update(['billed_amount' => 99_000_000]); // tidak cocok dengan hasil service

        $this->actingAs($this->user)
            ->delete(route('cash-receipt.remove-allocation', [$header->id, $cr->id]))
            ->assertSessionHas('success');

        $this->assertSame(0, CashReceipt::count());
        $this->assertSame(3_000_000, (int) $inv->refresh()->withholding_tax);
        $this->assertSame(1, $inv->comments()->where('comment', 'like', 'PPh invoice tidak disinkron%')->count());
    }

    public function test_batal_header_banyak_invoice_satu_transaksi(): void
    {
        $a = $this->invoice();
        $b = $this->invoice();
        $this->actingAs($this->user)->post(route('cash-receipt.store'), [
            'received_date' => '2026-10-20', 'customer_id' => $this->po->project->customer_id,
            'invoice_ids' => [$a->id, $b->id],
            'amounts' => [$a->id => '108.000.000', $b->id => '108.000.000'],
            'withholding' => [$a->id => '3.000.000', $b->id => '3.000.000'],
        ])->assertSessionHas('success');

        $header = CashReceiptHeader::firstOrFail();
        $this->actingAs($this->user)->post(route('cash-receipt.cancel', $header->id), ['cancel_reason' => 'uji'])
            ->assertSessionHas('success');

        foreach ([$a, $b] as $inv) {
            $inv->refresh();
            $this->assertSame(0, (int) $inv->withholding_tax);
            $this->assertSame('SENT', $inv->status);
        }
        $this->assertSame('CANCELLED', $header->refresh()->status);
    }

    public function test_settle_pph_di_halaman_invoice_menyinkronkan_invoice(): void
    {
        $inv = $this->invoice();
        $inv->update(['status' => 'PARTIALLY_PAID']);
        $cr = CashReceipt::create(['invoice_id' => $inv->id, 'received_date' => '2026-10-20', 'amount' => 108_000_000]);

        $this->actingAs($this->user)
            ->post('/invoice-cash-receipt/' . $inv->id . '/cash-receipt/' . $cr->id . '/settle', ['withholding_tax' => '3.000.000'])
            ->assertSessionHas('success');

        $inv->refresh();
        $this->assertSame(3_000_000, (int) $inv->withholding_tax);
        $this->assertSame('PAID', $inv->status);
    }

    public function test_sinkron_ditolak_bila_billed_tidak_cocok_service(): void
    {
        $inv = $this->invoice();
        $inv->update(['billed_amount' => 99_000_000]);
        $this->simpan($inv, '90.000.000', '3.000.000')->assertSessionHas('error');

        $this->assertSame(0, CashReceipt::count());
        $this->assertSame(0, CashReceiptHeader::count());
        $this->assertSame(0, (int) $inv->refresh()->withholding_tax);
    }

    public function test_urutan_kunci_no_po_invoice_detail_tetap(): void
    {
        $inv = $this->invoice();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->simpan($inv, '108.000.000', '3.000.000');
        $log = array_map(fn ($q) => strtolower($q['query']), DB::getQueryLog());

        $pos = fn (string ...$p) => collect($log)->search(fn ($s) => collect($p)->every(fn ($x) => str_contains($s, $x)));
        $this->assertLessThan($pos('from `invoices`', 'for update'), $pos('from `project_po_numbers`', 'for update'));
        $this->assertLessThan($pos('insert into `cash_receipts`'), $pos('from `invoices`', 'for update'));
    }

    public function test_collection_outstanding_tidak_double_count(): void
    {
        $inv = $this->invoice();
        $this->simpan($inv, '50.000.000', '3.000.000')->assertSessionHas('success');
        $inv->refresh();

        $this->assertSame(58_000_000, app(\App\Services\InvoiceCalculationService::class)->outstanding($inv));
        $rows = app(\App\Services\ClosingService::class)->collect('2000-01-01', '2100-01-01')
            ->where('source_module', 'COLLECTION')->where('source_id', $inv->id);
        $this->assertSame(58_000_000, (int) $rows->first()['amount']);
    }

    public function test_invoice_tak_tersentuh_tidak_berubah(): void
    {
        $a = $this->invoice(2_000_000);
        $b = $this->invoice(2_000_000);
        $this->simpan($a, '108.000.000', '3.000.000');

        $b->refresh();
        $this->assertSame(2_000_000, (int) $b->withholding_tax);
        $this->assertSame(109_000_000, (int) $b->captured_total);
        $this->assertSame(0, $b->comments()->count());
    }
}
