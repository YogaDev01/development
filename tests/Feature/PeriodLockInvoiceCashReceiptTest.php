<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\CashReceipt;
use App\Models\CashReceiptHeader;
use App\Models\Closing;
use App\Support\PeriodLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Kunci periode tahap 1: Invoice & Cash Receipt (rancangan Planner 2026-11-13).
 * Ditulis 2026-11-13; belum dijalankan — lihat CLAUDE.md §4.3.
 */
class PeriodLockInvoiceCashReceiptTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('secure');
        PeriodLock::flush();
        config(['closing.enforce_from_period' => null]);
        $this->giveMenuAccess($this->role('finance'), 'cash-receipt');
        $this->tutup('2026-10');
    }

    private function tutup(string $period, string $status = 'CLOSED'): Closing
    {
        $s = \Carbon\Carbon::createFromFormat('!Y-m', $period)->startOfMonth();
        PeriodLock::flush();

        return Closing::create([
            'code' => Closing::generateCode($period), 'period' => $period,
            'start_date' => $s, 'end_date' => $s->copy()->endOfMonth(), 'status' => $status,
        ]);
    }

    /** Invoice SENT bruto 10 jt dengan satu pembayaran 4 jt pada $tglTerima. */
    private function invoiceBayar(string $tglInvoice, string $tglTerima): array
    {
        $invoice = $this->makeInvoice($this->makePoNumber($this->makeProject()), [
            'subtotal' => 9_000_000, 'billed_amount' => 9_000_000, 'tax_amount' => 1_000_000,
            'captured_total' => 10_000_000, 'status' => 'SENT', 'invoice_date' => $tglInvoice,
        ]);
        $cr = CashReceipt::create(['invoice_id' => $invoice->id, 'received_date' => $tglTerima, 'amount' => 4_000_000]);

        return [$invoice->fresh(), $cr];
    }

    // ---------------------------------------------------------------- Invoice

    public function test_submit_invoice_draft_di_periode_closed_ditolak(): void
    {
        $invoice = $this->makeInvoice($this->makePoNumber($this->makeProject()), ['status' => 'DRAFT', 'invoice_date' => '2026-10-15']);

        $this->actingAs($this->userWithRole('finance'))
            ->post('/invoice-cash-receipt/' . $invoice->id . '/submit')
            ->assertSessionHas('error');

        $this->assertSame('DRAFT', $invoice->fresh()->status);
    }

    public function test_submit_invoice_draft_di_periode_terbuka_lolos(): void
    {
        $invoice = $this->makeInvoice($this->makePoNumber($this->makeProject()), ['status' => 'DRAFT', 'invoice_date' => '2026-11-15']);

        $this->actingAs($this->userWithRole('finance'))
            ->post('/invoice-cash-receipt/' . $invoice->id . '/submit')
            ->assertSessionHas('success');

        $this->assertSame('SUBMITTED', $invoice->fresh()->status);
    }

    public function test_cancel_invoice_di_periode_closed_ditolak_untuk_non_superadmin(): void
    {
        $invoice = $this->makeInvoice($this->makePoNumber($this->makeProject()), ['status' => 'SENT', 'invoice_date' => '2026-10-15']);

        $this->actingAs($this->userWithRole('finance_manager'))
            ->post('/invoice-cash-receipt/' . $invoice->id . '/cancel', ['cancel_reason' => 'salah'])
            ->assertSessionHas('error');

        $this->assertSame('SENT', $invoice->fresh()->status);
    }

    public function test_cancel_invoice_oleh_superadmin_wajib_alasan_periode_dan_tercatat(): void
    {
        $invoice = $this->makeInvoice($this->makePoNumber($this->makeProject()), ['status' => 'SENT', 'invoice_date' => '2026-10-15']);
        $admin   = $this->userWithRole('superadmin');

        $this->actingAs($admin)
            ->post('/invoice-cash-receipt/' . $invoice->id . '/cancel', ['cancel_reason' => 'salah'])
            ->assertSessionHas('error');
        $this->assertSame('SENT', $invoice->fresh()->status);

        $this->actingAs($admin)
            ->post('/invoice-cash-receipt/' . $invoice->id . '/cancel', [
                'cancel_reason' => 'salah', 'alasan_periode' => 'koreksi atas permintaan pemilik',
            ])
            ->assertSessionHas('success');

        $this->assertSame('CANCELLED', $invoice->fresh()->status);
        $this->assertDatabaseHas('user_activity_logs', ['module' => 'closing', 'action' => 'period_override']);
    }

    public function test_blocker_tanggal_invoice_menolak_periode_closed_dan_menerima_periode_terbuka(): void
    {
        $this->assertNotNull(PeriodLock::blocker('2026-10-20', 'Invoice'));
        $this->assertNull(PeriodLock::blocker('2026-11-20', 'Invoice'));
    }

    // ----------------------------------------------------- Cash Receipt (invoice)

    public function test_tambah_cash_receipt_dengan_tanggal_di_periode_closed_ditolak(): void
    {
        [$invoice] = $this->invoiceBayar('2026-09-01', '2026-11-02');

        $this->actingAs($this->userWithRole('finance_manager'))
            ->post('/invoice-cash-receipt/' . $invoice->id . '/cash-receipt', [
                'received_date' => '2026-10-10', 'amount' => '1.000.000',
            ])
            ->assertSessionHas('error');

        $this->assertSame(1, CashReceipt::where('invoice_id', $invoice->id)->count());
    }

    public function test_tambah_cash_receipt_di_periode_terbuka_lolos(): void
    {
        [$invoice] = $this->invoiceBayar('2026-09-01', '2026-11-02');

        $this->actingAs($this->userWithRole('finance_manager'))
            ->post('/invoice-cash-receipt/' . $invoice->id . '/cash-receipt', [
                'received_date' => '2026-11-10', 'amount' => '1.000.000',
            ])
            ->assertSessionHas('success');

        $this->assertSame(2, CashReceipt::where('invoice_id', $invoice->id)->count());
    }

    public function test_hapus_cash_receipt_di_periode_closed_ditolak_lalu_superadmin_menembus_dengan_alasan(): void
    {
        [$invoice, $cr] = $this->invoiceBayar('2026-09-01', '2026-10-10');
        $url = '/invoice-cash-receipt/' . $invoice->id . '/cash-receipt/' . $cr->id;

        $this->actingAs($this->userWithRole('finance_manager'))
            ->delete($url, ['reason' => 'salah input nominal'])
            ->assertSessionHas('error');
        $this->assertNull(CashReceipt::withTrashed()->find($cr->id)->deleted_at);

        $this->actingAs($this->userWithRole('superadmin'))
            ->delete($url, ['reason' => 'salah input nominal', 'alasan_periode' => 'salah input nominal'])
            ->assertSessionHas('success');
        $this->assertNotNull(CashReceipt::withTrashed()->find($cr->id)->deleted_at);
    }

    public function test_settle_pph_di_periode_closed_ditolak(): void
    {
        [$invoice, $cr] = $this->invoiceBayar('2026-09-01', '2026-10-10');

        $this->actingAs($this->userWithRole('finance_manager'))
            ->post('/invoice-cash-receipt/' . $invoice->id . '/cash-receipt/' . $cr->id . '/settle', ['withholding_tax' => '100.000'])
            ->assertSessionHas('error');

        $this->assertSame(0, (int) $cr->fresh()->withholding_tax);
    }

    public function test_super_update_menolak_tanpa_alasan_dan_lolos_dengan_alasan(): void
    {
        [$invoice, $cr] = $this->invoiceBayar('2026-09-01', '2026-10-10');
        $url   = '/invoice-cash-receipt/' . $invoice->id . '/cash-receipt/' . $cr->id . '/super-update';
        $admin = $this->userWithRole('superadmin');

        $this->actingAs($admin)->post($url, ['received_date' => '2026-10-10', 'amount' => '5.000.000'])
            ->assertSessionHas('error');
        $this->assertSame(4_000_000, (int) $cr->fresh()->amount);

        $this->actingAs($admin)->post($url, [
            'received_date' => '2026-10-10', 'amount' => '5.000.000', 'alasan_periode' => 'koreksi nominal',
        ])->assertSessionHas('success');
        $this->assertSame(5_000_000, (int) $cr->fresh()->amount);
    }

    public function test_super_update_memindah_tanggal_keluar_dari_periode_closed_tetap_butuh_alasan(): void
    {
        [$invoice, $cr] = $this->invoiceBayar('2026-09-01', '2026-10-10');

        $this->actingAs($this->userWithRole('superadmin'))
            ->post('/invoice-cash-receipt/' . $invoice->id . '/cash-receipt/' . $cr->id . '/super-update', [
                'received_date' => '2026-11-05', 'amount' => '4.000.000',
            ])
            ->assertSessionHas('error');

        $this->assertSame('2026-10-10', $cr->fresh()->received_date->toDateString());
    }

    public function test_penembusan_ditolak_bila_periode_akuntansi_sudah_closed(): void
    {
        [$invoice, $cr] = $this->invoiceBayar('2026-09-01', '2026-10-10');
        AccountingPeriod::create([
            'name' => 'Okt 2026', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'status' => 'CLOSED',
        ]);

        $this->actingAs($this->userWithRole('superadmin'))
            ->post('/invoice-cash-receipt/' . $invoice->id . '/cash-receipt/' . $cr->id . '/super-update', [
                'received_date' => '2026-10-10', 'amount' => '5.000.000', 'alasan_periode' => 'koreksi',
            ])
            ->assertSessionHas('error');

        $this->assertSame(4_000_000, (int) $cr->fresh()->amount);
    }

    public function test_periode_reopened_membuka_kembali_penulisan(): void
    {
        Closing::where('period', '2026-10')->update(['status' => 'REOPENED']);
        PeriodLock::flush();
        [$invoice] = $this->invoiceBayar('2026-09-01', '2026-11-02');

        $this->actingAs($this->userWithRole('finance_manager'))
            ->post('/invoice-cash-receipt/' . $invoice->id . '/cash-receipt', [
                'received_date' => '2026-10-10', 'amount' => '1.000.000',
            ])
            ->assertSessionHas('success');
    }

    // ------------------------------------------- Cash Receipt (multi-invoice)

    public function test_penerimaan_multi_invoice_di_periode_closed_ditolak(): void
    {
        [$invoice] = $this->invoiceBayar('2026-09-01', '2026-11-02');

        $this->actingAs($this->userWithRole('finance'))
            ->post('/cash-receipt', [
                'received_date' => '2026-10-12',
                'customer_id'   => $invoice->po->customer_id,
                'invoice_ids'   => [$invoice->id],
                'amounts'       => [$invoice->id => '1.000.000'],
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, CashReceiptHeader::count());
    }

    public function test_batalkan_penerimaan_di_periode_closed_ditolak(): void
    {
        $header = CashReceiptHeader::create([
            'code' => 'CRH-UJI-1', 'received_date' => '2026-10-12', 'customer_id' => $this->makeCustomer()->id,
            'total_amount' => 1_000_000, 'status' => 'DRAFT', 'created_by' => $this->userWithRole('finance')->id,
        ]);

        $this->actingAs($this->userWithRole('finance'))
            ->post('/cash-receipt/' . $header->id . '/cancel', ['cancel_reason' => 'salah'])
            ->assertSessionHas('error');

        $this->assertNotSame('CANCELLED', $header->fresh()->status);
    }
}
