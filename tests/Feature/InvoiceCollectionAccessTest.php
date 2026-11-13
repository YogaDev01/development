<?php

namespace Tests\Feature;

use App\Models\CashReceipt;
use App\Models\Invoice;
use App\Models\ProjectPoBast;
use App\Models\ProjectPoNumber;
use App\Models\SourceFund;
use App\Models\UserActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Keputusan pemilik 2026-11-13: role collection BOLEH edit, hapus, submit,
 * import invoice serta hapus/settle Cash Receipt. Route, controller, dan
 * tombol Blade harus sepakat (CLAUDE.md §8). Juga menjaga hapus invoice wajib
 * beralasan (IV-01) dan edit Superadmin mencatat nilai lama -> baru.
 */
class InvoiceCollectionAccessTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    private function sumber(): SourceFund
    {
        return SourceFund::firstOrCreate(['name' => 'Rekening PT Uji'], ['no_account' => '123', 'status' => 'ACTIVE']);
    }

    private function po(): ProjectPoNumber
    {
        return $this->makePoNumber($this->makeProject(), ['nominal' => 200_000_000, 'status' => 'PARTIAL']);
    }

    private function payload(ProjectPoBast $bast, array $ubah = []): array
    {
        return array_merge([
            'project_po_bast_id' => $bast->id,
            'source_fund_id'     => $this->sumber()->id,
            'faktur_number'      => '010.000-26.00000001',
            'payment_term_type'  => 'Termin',
            'termin_number'      => 1,
            'invoice_date'       => '2026-10-10',
            'due_date'           => '2026-11-10',
            'tax_rate'           => 11,
            'details'            => [['description' => 'Jasa', 'unit_price' => '100000000', 'qty' => 1]],
        ], $ubah);
    }

    private function collection()
    {
        $user = $this->userWithRole('collection');
        $this->giveMenuAccess($this->role('collection'), 'invoice-cash-receipt');

        return $user;
    }

    /* ---------------------------------------------------------- TOMBOL */

    public function test_collection_melihat_tombol_edit_submit_hapus_di_draft(): void
    {
        $invoice = $this->makeInvoice($this->po(), ['status' => 'DRAFT']);

        $this->actingAs($this->collection())
            ->get('/invoice-cash-receipt/' . $invoice->id)
            ->assertOk()
            ->assertSee(route('invoice-cash-receipt.submit', $invoice->id), false)
            ->assertSee(route('invoice-cash-receipt.destroy', $invoice->id), false)
            ->assertSee(url('/invoice-cash-receipt/' . $invoice->id . '/edit'), false);
    }

    public function test_collection_melihat_tombol_hapus_cash_receipt(): void
    {
        $invoice = $this->makeInvoice($this->po(), ['status' => 'PARTIALLY_PAID', 'captured_total' => 10_000_000]);
        $cr = CashReceipt::create(['invoice_id' => $invoice->id, 'received_date' => now()->toDateString(), 'amount' => 1_000_000]);

        $this->actingAs($this->collection())
            ->get('/invoice-cash-receipt/' . $invoice->id)
            ->assertOk()
            ->assertSee(route('invoice-cash-receipt.cash-receipt.destroy', [$invoice->id, $cr->id]), false);
    }

    /* ----------------------------------------------------------- ROUTE */

    public function test_collection_bisa_submit_draft(): void
    {
        $invoice = $this->makeInvoice($this->po(), ['status' => 'DRAFT']);

        $this->actingAs($this->collection())
            ->post(route('invoice-cash-receipt.submit', $invoice->id))
            ->assertSessionMissing('error');

        $this->assertSame('SUBMITTED', $invoice->fresh()->status);
    }

    public function test_collection_bisa_update_draft(): void
    {
        $po = $this->po();
        $bast = $this->makeBast($po, 100_000_000);
        $invoice = $this->makeInvoice($po, ['billed_amount' => 100_000_000, 'status' => 'DRAFT'], $bast);

        $this->actingAs($this->collection())
            ->put(route('invoice-cash-receipt.update', $invoice->id), $this->payload($bast, ['faktur_number' => '010.000-26.00000099']))
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');

        $this->assertSame('010.000-26.00000099', $invoice->fresh()->faktur_number);
    }

    public function test_hapus_invoice_wajib_alasan_dan_menyisakan_jejak(): void
    {
        $invoice = $this->makeInvoice($this->po(), ['status' => 'DRAFT']);
        $user = $this->collection();

        $this->actingAs($user)
            ->delete(route('invoice-cash-receipt.destroy', $invoice->id))
            ->assertSessionHasErrors('reason');
        $this->assertNotSoftDeleted('invoices', ['id' => $invoice->id]);

        $this->actingAs($user)
            ->delete(route('invoice-cash-receipt.destroy', $invoice->id), ['reason' => 'Dobel input dengan invoice lain.'])
            ->assertRedirect('/invoice-cash-receipt');

        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseHas('invoices', [
            'id'            => $invoice->id,
            'deleted_by'    => $user->id,
            'delete_reason' => 'Dobel input dengan invoice lain.',
        ]);
    }

    public function test_collection_bisa_hapus_cash_receipt_dengan_alasan(): void
    {
        $invoice = $this->makeInvoice($this->po(), ['status' => 'PARTIALLY_PAID', 'captured_total' => 10_000_000]);
        $cr = CashReceipt::create(['invoice_id' => $invoice->id, 'received_date' => now()->toDateString(), 'amount' => 1_000_000]);

        $this->actingAs($this->collection())
            ->delete(route('invoice-cash-receipt.cash-receipt.destroy', [$invoice->id, $cr->id]), ['reason' => 'Salah input nominal.'])
            ->assertRedirect();

        $this->assertSoftDeleted('cash_receipts', ['id' => $cr->id]);
    }

    public function test_collection_bisa_settle_pph_cash_receipt(): void
    {
        $invoice = $this->makeInvoice($this->po(), ['status' => 'PARTIALLY_PAID', 'captured_total' => 10_000_000]);
        $cr = CashReceipt::create(['invoice_id' => $invoice->id, 'received_date' => now()->toDateString(), 'amount' => 1_000_000]);

        $this->actingAs($this->collection())
            ->post(route('invoice-cash-receipt.cash-receipt.settle', [$invoice->id, $cr->id]), ['withholding_tax' => '100.000'])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');

        $this->assertSame(100_000, (int) $cr->fresh()->withholding_tax);
    }

    public function test_collection_bisa_akses_import_rincian_invoice(): void
    {
        $user = $this->collection();

        $this->actingAs($user)
            ->get(route('invoice-cash-receipt.import-template'))
            ->assertOk();

        // Tanpa file: ditolak validasi (bukan 403) — artinya route import terbuka untuk collection.
        $this->actingAs($user)
            ->post(route('invoice-cash-receipt.import-preview'))
            ->assertSessionHasErrors('file');
    }

    public function test_role_tanpa_akses_ditolak(): void
    {
        $invoice = $this->makeInvoice($this->po(), ['status' => 'DRAFT']);
        $accounting = $this->userWithRole('accounting');

        $this->actingAs($accounting)->post(route('invoice-cash-receipt.submit', $invoice->id))->assertForbidden();
        $this->actingAs($accounting)
            ->delete(route('invoice-cash-receipt.destroy', $invoice->id), ['reason' => 'Coba hapus tanpa hak.'])
            ->assertForbidden();

        $this->assertSame('DRAFT', $invoice->fresh()->status);
        $this->assertNotSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    public function test_collection_tidak_bisa_super_update_cash_receipt(): void
    {
        $invoice = $this->makeInvoice($this->po(), ['status' => 'PARTIALLY_PAID', 'captured_total' => 10_000_000]);
        $cr = CashReceipt::create(['invoice_id' => $invoice->id, 'received_date' => now()->toDateString(), 'amount' => 1_000_000]);

        $this->actingAs($this->collection())
            ->post(route('invoice-cash-receipt.cash-receipt.super-update', [$invoice->id, $cr->id]), [
                'received_date' => now()->toDateString(),
                'amount'        => '2.000.000',
            ])
            ->assertForbidden();
    }

    /* ------------------------------------------------ EDIT SUPERADMIN LOG */

    public function test_edit_superadmin_di_luar_draft_mencatat_rincian_lama_baru(): void
    {
        $po = $this->po();
        $bast = $this->makeBast($po, 100_000_000);
        $invoice = $this->makeInvoice($po, ['billed_amount' => 100_000_000, 'status' => 'SENT'], $bast);
        $invoice->details()->create([
            'description' => 'Jasa lama', 'unit_price' => 100_000_000, 'qty' => 1, 'deduction' => 0, 'total' => 100_000_000,
        ]);

        $this->actingAs($this->userWithRole('superadmin'))
            ->put(route('invoice-cash-receipt.update', $invoice->id), $this->payload($bast, [
                'details' => [['description' => 'Jasa baru', 'unit_price' => '100000000', 'qty' => 1]],
            ]))
            ->assertSessionMissing('error');

        $log = UserActivityLog::where('module', 'invoice')->where('action', 'update')->latest('id')->firstOrFail();
        $rincian = collect($log->changes)->firstWhere('label', 'Rincian #1');
        $this->assertNotNull($rincian);
        $this->assertStringContainsString('Jasa lama', $rincian['from']);
        $this->assertStringContainsString('Jasa baru', $rincian['to']);

        $this->assertTrue($invoice->comments()->where('comment', 'like', '[EDIT SUPERADMIN%Rincian #1%')->exists());
    }

    public function test_cancel_mencatat_alasan_di_activity_log(): void
    {
        $invoice = $this->makeInvoice($this->po(), ['status' => 'SENT']);

        $this->actingAs($this->userWithRole('finance_manager'))
            ->post(route('invoice-cash-receipt.cancel', $invoice->id), ['cancel_reason' => 'Customer membatalkan PO.']);

        $this->assertDatabaseHas('user_activity_logs', [
            'module' => 'invoice',
            'action' => 'cancel',
            'notes'  => 'Customer membatalkan PO.',
        ]);
    }
}
