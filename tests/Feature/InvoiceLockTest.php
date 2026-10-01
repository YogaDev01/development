<?php

namespace Tests\Feature;

use App\Models\CashReceipt;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Transisi Invoice, pencatatan pembayaran, settle PPh, dan Surat Jalan
 * memakai kunci baris.
 *
 * Balapan disimulasikan seperti TreasuryBatchLockTest: keadaan "orang lain
 * sudah menang" ditulis tepat setelah data dibaca tanpa kunci. Batasan: dua
 * settle PPh bersamaan pada DUA receipt berbeda butuh dua koneksi yang belum
 * saling melihat commit - diverifikasi lewat review + UAT.
 */
class InvoiceLockTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private int $customerId;

    private function invoice(string $status, int $total = 10_000_000): Invoice
    {
        $this->customerId ??= $this->makeCustomer()->id;

        return $this->makeInvoice(
            $this->makePoNumber($this->makeProject(['customer_id' => $this->customerId])),
            ['status' => $status, 'captured_total' => $total],
        );
    }

    private function fm(): User
    {
        return $this->userWithRole('finance_manager');
    }

    /** Jalankan $aksi sekali, tepat setelah query pertama yang memuat $penanda. */
    private function setelahQuery(string $penanda, \Closure $aksi): void
    {
        $sudah = false;

        DB::listen(function ($query) use ($penanda, $aksi, &$sudah) {
            if ($sudah || ! str_contains($query->sql, $penanda)) {
                return;
            }
            $sudah = true;
            $aksi();
        });
    }

    /* ---------------------------------------------------------- TRANSISI */

    public function test_submit_verify_approve_normal(): void
    {
        $invoice = $this->invoice('DRAFT');
        $fm = $this->fm();

        $this->actingAs($this->userWithRole('finance'))
            ->post('/invoice-cash-receipt/' . $invoice->id . '/submit')->assertSessionMissing('error');
        $this->actingAs($fm)
            ->post('/invoice-cash-receipt/' . $invoice->id . '/verify')->assertSessionMissing('error');
        $this->actingAs($fm)
            ->post('/invoice-cash-receipt/' . $invoice->id . '/approve')->assertSessionMissing('error');

        $invoice->refresh();
        $this->assertSame('APPROVED', $invoice->status);
        $this->assertSame($fm->id, (int) $invoice->verified_by);
        $this->assertSame($fm->id, (int) $invoice->approved_by);
    }

    public function test_verify_yang_kalah_balapan_tidak_menimpa_verifikator(): void
    {
        $invoice = $this->invoice('SUBMITTED');
        $verifikatorPertama = $this->fm();

        $this->setelahQuery('from `invoices`', fn () => DB::table('invoices')
            ->where('id', $invoice->id)
            ->update(['status' => 'VERIFIED', 'verified_by' => $verifikatorPertama->id]));

        $this->actingAs($this->fm())
            ->post('/invoice-cash-receipt/' . $invoice->id . '/verify')
            ->assertSessionHas('error', 'Status sudah berubah — muat ulang halaman.');

        $this->assertSame($verifikatorPertama->id, (int) $invoice->refresh()->verified_by);
    }

    public function test_revise_tidak_memundurkan_invoice_yang_baru_saja_diapprove(): void
    {
        $invoice = $this->invoice('VERIFIED');

        $this->setelahQuery('from `invoices`', fn () =>
            DB::table('invoices')->where('id', $invoice->id)->update(['status' => 'APPROVED'])
        );

        $this->actingAs($this->fm())
            ->post('/invoice-cash-receipt/' . $invoice->id . '/revise', ['notes' => 'Perbaiki'])
            ->assertSessionHas('error', 'Status sudah berubah — muat ulang halaman.');

        $this->assertSame('APPROVED', $invoice->refresh()->status);
    }

    public function test_cancel_normal(): void
    {
        $invoice = $this->invoice('APPROVED');

        $this->actingAs($this->fm())
            ->post('/invoice-cash-receipt/' . $invoice->id . '/cancel', ['cancel_reason' => 'Salah'])
            ->assertSessionMissing('error');

        $this->assertSame('CANCELLED', $invoice->refresh()->status);
    }

    /* --------------------------------------------------------- PEMBAYARAN */

    public function test_pembayaran_tidak_tercatat_pada_invoice_yang_baru_saja_dibatalkan(): void
    {
        $invoice = $this->invoice('SENT');

        $this->setelahQuery('from `invoices`', fn () =>
            DB::table('invoices')->where('id', $invoice->id)->update(['status' => 'CANCELLED'])
        );

        $this->actingAs($this->userWithRole('finance'))
            ->post('/invoice-cash-receipt/' . $invoice->id . '/cash-receipt', [
                'received_date' => now()->toDateString(),
                'amount'        => '5.000.000',
            ])
            ->assertSessionHas('error', fn ($pesan) => str_contains($pesan, 'hanya bisa ditambahkan'));

        $this->assertSame(0, CashReceipt::where('invoice_id', $invoice->id)->count());
    }

    public function test_settle_pph_normal_dan_melebihi_batas(): void
    {
        $invoice = $this->invoice('SENT');
        $user = $this->userWithRole('finance');

        $this->actingAs($user)->post('/invoice-cash-receipt/' . $invoice->id . '/cash-receipt', [
            'received_date' => now()->toDateString(),
            'amount'        => '9.800.000',
        ]);
        $cr = CashReceipt::where('invoice_id', $invoice->id)->firstOrFail();

        $this->actingAs($user)
            ->post('/invoice-cash-receipt/' . $invoice->id . '/cash-receipt/' . $cr->id . '/settle', ['withholding_tax' => '300.000'])
            ->assertSessionHas('error', 'Nominal PPh melebihi sisa tagihan (maks 200.000).');

        $this->actingAs($user)
            ->post('/invoice-cash-receipt/' . $invoice->id . '/cash-receipt/' . $cr->id . '/settle', ['withholding_tax' => '200.000'])
            ->assertSessionMissing('error');

        $this->assertSame(200_000, (int) $cr->refresh()->withholding_tax);
    }

    /* -------------------------------------------------------- SURAT JALAN */

    private function suratJalan(User $user, array $invoiceIds)
    {
        return $this->actingAs($user)->post('/delivery-note', ['invoice_ids' => $invoiceIds]);
    }

    private function deliveryUser(): User
    {
        $user = $this->userWithRole('collection');
        $this->giveMenuAccess($user->role, 'delivery-note');

        return $user;
    }

    public function test_surat_jalan_normal_menandai_invoice_sent(): void
    {
        $invoice = $this->invoice('APPROVED');

        $this->suratJalan($this->deliveryUser(), [$invoice->id])->assertSessionMissing('error');

        $this->assertSame('SENT', $invoice->refresh()->status);
        $this->assertSame(1, DeliveryNoteItem::where('invoice_id', $invoice->id)->count());
    }

    public function test_invoice_tidak_masuk_dua_surat_jalan(): void
    {
        $invoice = $this->invoice('APPROVED');
        $user = $this->deliveryUser();

        // Tepat setelah pemeriksaan "sudah ada di Surat Jalan lain", pengguna
        // lain memasukkan invoice yang sama ke Surat Jalan miliknya.
        $this->setelahQuery('from `delivery_note_items`', function () use ($invoice, $user) {
            $lain = DeliveryNote::create([
                'code'        => 'SJ-LAIN',
                'customer_id' => $this->customerId,
                'status'      => 'SENT',
                'created_by'  => $user->id,
                'sent_at'     => now(),
            ]);
            DeliveryNoteItem::create(['delivery_note_id' => $lain->id, 'invoice_id' => $invoice->id]);
        });

        $this->suratJalan($user, [$invoice->id])
            ->assertSessionHas('error', fn ($pesan) => str_contains($pesan, 'baru saja diproses'));

        $this->assertSame(1, DeliveryNoteItem::where('invoice_id', $invoice->id)->count());
        $this->assertSame(1, DeliveryNote::count());
    }
}
