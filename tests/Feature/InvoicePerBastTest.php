<?php

namespace Tests\Feature;

use App\Models\CashReceipt;
use App\Models\Invoice;
use App\Models\ProjectPoBast;
use App\Models\ProjectPoNumber;
use App\Models\SourceFund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Fase 2 — 1 Invoice = 1 BAST: pembuatan/validasi invoice, status No. PO dari
 * BAST, dan hapus BAST (soft delete, superadmin saja).
 */
class InvoicePerBastTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    private function sumber(): SourceFund
    {
        return SourceFund::firstOrCreate(['name' => 'Rekening PT Uji'], ['no_account' => '123', 'status' => 'ACTIVE']);
    }

    private function payload(ProjectPoBast $bast, int $harga = 100_000_000, array $ubah = []): array
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
            'details'            => [['description' => 'Jasa', 'unit_price' => (string) $harga, 'qty' => 1]],
        ], $ubah);
    }

    private function po(int $nominal = 200_000_000, string $status = 'PARTIAL'): ProjectPoNumber
    {
        return $this->makePoNumber($this->makeProject(), ['nominal' => $nominal, 'status' => $status]);
    }

    /* ------------------------------------------------------ BUAT INVOICE */

    public function test_buat_invoice_per_bast_menautkan_bast_dan_no_po(): void
    {
        $po = $this->po();
        $bast = $this->makeBast($po, 100_000_000);

        $this->actingAs($this->userWithRole('superadmin'))
            ->post(route('invoice-cash-receipt.store'), $this->payload($bast))
            ->assertSessionHasNoErrors();

        $inv = Invoice::firstOrFail();
        $this->assertSame($bast->id, $inv->project_po_bast_id);
        $this->assertSame($po->id, $inv->project_po_number_id);
        $this->assertSame(100_000_000, (int) $inv->billed_amount);
        $this->assertFalse((bool) $inv->is_additional);
    }

    public function test_nilai_invoice_harus_persis_sama_dengan_bast_tanpa_toleransi(): void
    {
        $po = $this->po();
        $bast = $this->makeBast($po, 100_000_000);
        $user = $this->userWithRole('superadmin');

        foreach ([50_000_000, 100_000_001, 99_999_999, 100_000_800] as $salah) {
            $this->actingAs($user)->post(route('invoice-cash-receipt.store'), $this->payload($bast, $salah))
                ->assertSessionHas('error');
        }
        $this->assertSame(0, Invoice::count());

        $this->actingAs($user)->post(route('invoice-cash-receipt.store'), $this->payload($bast, 100_000_000))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Invoice::count());
    }

    public function test_edit_draft_dengan_nilai_berbeda_dari_bast_ditolak_dan_nilai_persis_diterima(): void
    {
        $po = $this->po();
        $bast = $this->makeBast($po, 100_000_000);
        $draft = $this->makeInvoice($po, ['billed_amount' => 100_000_000, 'status' => 'DRAFT'], $bast);
        $user = $this->userWithRole('superadmin');

        $this->actingAs($user)->put(route('invoice-cash-receipt.update', $draft->id), $this->payload($bast, 100_000_500))
            ->assertSessionHas('error');
        $this->assertSame(100_000_000, (int) $draft->fresh()->billed_amount);

        $this->actingAs($user)->put(route('invoice-cash-receipt.update', $draft->id), $this->payload($bast, 100_000_000))
            ->assertSessionHasNoErrors();
        $this->assertSame(100_000_000, (int) $draft->fresh()->billed_amount);
    }

    public function test_tolak_bast_yang_sudah_ditagih(): void
    {
        $po = $this->po();
        $bast = $this->makeBast($po, 100_000_000);
        $this->makeInvoice($po, ['billed_amount' => 100_000_000], $bast);

        $this->actingAs($this->userWithRole('superadmin'))
            ->post(route('invoice-cash-receipt.store'), $this->payload($bast))
            ->assertSessionHas('error');

        $this->assertSame(1, Invoice::count());
    }

    public function test_cancel_membuat_bast_bisa_ditagih_lagi(): void
    {
        $po = $this->po();
        $bast = $this->makeBast($po, 100_000_000);
        $lama = $this->makeInvoice($po, ['billed_amount' => 100_000_000, 'status' => 'SENT'], $bast);
        $user = $this->userWithRole('superadmin');

        $this->actingAs($user)->post(route('invoice-cash-receipt.cancel', $lama->id), ['cancel_reason' => 'salah']);
        $this->assertSame('CANCELLED', $lama->fresh()->status);
        $this->assertFalse($bast->fresh()->isBilled());

        $this->actingAs($user)->post(route('invoice-cash-receipt.store'), $this->payload($bast))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Invoice::count());
    }

    public function test_revisi_cancelled_ke_draft_ditolak_bila_bast_sudah_dipakai_invoice_lain(): void
    {
        $po = $this->po();
        $bast = $this->makeBast($po, 100_000_000);
        $batal = $this->makeInvoice($po, ['billed_amount' => 100_000_000, 'status' => 'CANCELLED'], $bast);
        $this->makeInvoice($po, ['billed_amount' => 100_000_000, 'status' => 'SENT'], $bast);

        $this->actingAs($this->userWithRole('superadmin'))
            ->post(route('invoice-cash-receipt.revise', $batal->id), ['notes' => 'coba'])
            ->assertSessionHas('error');

        $this->assertSame('CANCELLED', $batal->fresh()->status);
    }

    public function test_submit_draft_ditolak_bila_bast_sudah_dipakai_invoice_lain(): void
    {
        $po = $this->po();
        $bast = $this->makeBast($po, 100_000_000);
        $draft = $this->makeInvoice($po, ['billed_amount' => 100_000_000, 'status' => 'DRAFT'], $bast);
        $this->makeInvoice($po, ['billed_amount' => 100_000_000, 'status' => 'SENT'], $bast);

        $this->actingAs($this->userWithRole('superadmin'))
            ->post(route('invoice-cash-receipt.submit', $draft->id))
            ->assertSessionHas('error');

        $this->assertSame('DRAFT', $draft->fresh()->status);
    }

    /* ------------------------------------------------------ STATUS NO. PO */

    public function test_transisi_status_no_po_dari_bast(): void
    {
        $po = $this->po(200_000_000, 'RELEASED');
        $po->syncInvoiceStatus();
        $this->assertSame('RELEASED', $po->fresh()->status, 'tanpa BAST aktif');

        $b1 = $this->makeBast($po, 100_000_000);
        $po->syncInvoiceStatus();
        $this->assertSame('PARTIAL', $po->fresh()->status, 'ada BAST, belum ditandai lengkap');

        // Tombol "Tandai Lengkap": boleh walau total BAST < nominal.
        $po->update(['status' => 'BAST_SUBMITTED']);
        $po->fresh()->syncInvoiceStatus();
        $this->assertSame('BAST_SUBMITTED', $po->fresh()->status);

        $i1 = $this->makeInvoice($po, ['billed_amount' => 100_000_000, 'status' => 'SENT'], $b1);
        $po->fresh()->syncInvoiceStatus();
        $this->assertSame('INVOICED', $po->fresh()->status, 'semua BAST aktif sudah ditagih');

        $b2 = $this->makeBast($po, 100_000_000);
        $po->fresh()->syncInvoiceStatus();
        $this->assertSame('BAST_SUBMITTED', $po->fresh()->status, 'BAST baru belum ditagih');

        $i2 = $this->makeInvoice($po, ['billed_amount' => 100_000_000, 'status' => 'SENT'], $b2);
        $i1->update(['status' => 'PAID']);
        $po->fresh()->syncInvoiceStatus();
        $this->assertSame('INVOICED', $po->fresh()->status, 'satu termin PAID tidak membuat No. PO PAID');

        $i2->update(['status' => 'PAID']);
        $po->fresh()->syncInvoiceStatus();
        $this->assertSame('PAID', $po->fresh()->status);

        $i2->update(['status' => 'CANCELLED']);
        $po->fresh()->syncInvoiceStatus();
        $this->assertSame('BAST_SUBMITTED', $po->fresh()->status, 'invoice CANCELLED tidak dihitung');
    }

    public function test_invoice_lama_tanpa_bast_tetap_memakai_perhitungan_lama(): void
    {
        $po = $this->po(100_000_000, 'BAST_SUBMITTED'); // tanpa satu pun baris BAST
        $this->makeInvoice($po, ['billed_amount' => 100_000_000, 'status' => 'SENT']);

        $po->fresh()->syncInvoiceStatus();

        $this->assertSame('INVOICED', $po->fresh()->status);
    }

    /* ------------------------------------------------------ HAPUS BAST */

    public function test_superadmin_hapus_bast_soft_delete_dan_agregat_status_dihitung_ulang(): void
    {
        Storage::fake('secure');
        $po = $this->po(200_000_000, 'BAST_SUBMITTED');
        $b1 = $this->makeBast($po, 60_000_000, '2026-10-01', 'B-1');
        $b2 = $this->makeBast($po, 40_000_000, '2026-10-02', 'B-2');
        $po->update(['nilai_bast' => 100_000_000, 'no_bast' => 'B-2']);
        $po->attachments()->create([
            'project_id' => $po->project_id, 'project_po_bast_id' => $b2->id, 'type' => 'po_number_bast',
            'title' => 'B-2', 'file_path' => 'project_attachments/b2.pdf', 'file_name' => 'b2.pdf', 'file_type' => 'application/pdf',
        ]);

        $this->actingAs($this->userWithRole('superadmin'))
            ->delete(route('project.po-bast.destroy', $b2->id), ['delete_reason' => 'salah input'])
            ->assertSessionHas('success');

        $this->assertSoftDeleted('project_po_basts', ['id' => $b2->id]);
        $baris = ProjectPoBast::withTrashed()->find($b2->id);
        $this->assertSame('salah input', $baris->delete_reason);
        $this->assertNotNull($baris->deleted_by);
        $this->assertDatabaseHas('project_attachments', ['project_po_bast_id' => $b2->id]); // lampiran tetap tertaut

        $po = $po->fresh();
        $this->assertSame(60_000_000, (int) $po->nilai_bast);
        $this->assertSame('B-1', $po->no_bast);
        $this->assertSame('BAST_SUBMITTED', $po->status);
    }

    public function test_hapus_bast_terakhir_mengembalikan_no_po_ke_released(): void
    {
        $po = $this->po(200_000_000, 'PARTIAL');
        $b = $this->makeBast($po, 60_000_000);

        $this->actingAs($this->userWithRole('superadmin'))
            ->delete(route('project.po-bast.destroy', $b->id), ['delete_reason' => 'batal']);

        $po = $po->fresh();
        $this->assertSame('RELEASED', $po->status);
        $this->assertNull($po->no_bast);
        $this->assertSame(0, (int) $po->nilai_bast);
    }

    public function test_hapus_bast_role_lain_403_dan_bast_tertagih_ditolak(): void
    {
        $po = $this->po();
        $bast = $this->makeBast($po, 100_000_000);

        $this->actingAs($this->userWithRole('finance_manager'))
            ->delete(route('project.po-bast.destroy', $bast->id), ['delete_reason' => 'x'])
            ->assertForbidden();
        $this->assertNull(ProjectPoBast::find($bast->id)->deleted_at);

        $this->makeInvoice($po, ['billed_amount' => 100_000_000, 'status' => 'SENT'], $bast);
        $this->actingAs($this->userWithRole('superadmin'))
            ->delete(route('project.po-bast.destroy', $bast->id), ['delete_reason' => 'x'])
            ->assertSessionHas('error');
        $this->assertNull(ProjectPoBast::find($bast->id)->deleted_at);
    }

    /* ------------------------------------------------------ SUBMIT BAST */

    public function test_submit_bast_yang_membuat_total_melebihi_nominal_diblokir(): void
    {
        Storage::fake('secure');
        $po = $this->po(100_000_000, 'RELEASED');
        $this->makeBast($po, 70_000_000);
        $user = $this->userWithRole('superadmin');

        $kirim = fn (int $nilai) => $this->actingAs($user)->post(route('project.submit-bast.store', $po->project_id), [
            'po_numbers' => [$po->id => [
                'no_bast'      => 'B-BARU',
                'tanggal_bast' => '2026-10-10',
                'nominal_bast' => (string) $nilai,
                'attachments'  => [UploadedFile::fake()->create('b.pdf', 10, 'application/pdf')],
            ]],
        ]);

        $kirim(40_000_000)->assertSessionHas('error'); // 70 + 40 > 100
        $this->assertSame(1, ProjectPoBast::where('project_po_number_id', $po->id)->count());

        $kirim(30_000_000); // 70 + 30 = 100, tidak melebihi
        $this->assertSame(2, ProjectPoBast::where('project_po_number_id', $po->id)->count());
    }

    /* ------------------------------------------------------ BAST SINTETIS (backfill) */

    /** No. PO lama: 1 BAST sintetis (nilai = nominal) + 1 invoice lama aktif yang belum tertaut. */
    private function poLama(string $statusPo = 'INVOICED', string $statusInvoice = 'SENT'): array
    {
        $po = $this->po(100_000_000, $statusPo);
        $bast = $this->makeBast($po, 100_000_000);
        $inv = $this->makeInvoice($po, ['billed_amount' => 100_000_000, 'captured_total' => 111_000_000, 'status' => $statusInvoice]);

        return [$po, $bast, $inv];
    }

    private function idBastTersedia(): array
    {
        $r = $this->actingAs($this->userWithRole('superadmin'))->get(route('invoice-cash-receipt.create'));

        return $r->viewData('availableBasts')->pluck('id')->all();
    }

    public function test_bast_sintetis_dari_no_po_dengan_invoice_lama_belum_tertaut_tidak_bisa_ditagih(): void
    {
        foreach (['INVOICED', 'PAID'] as $statusPo) {
            [$po, $bast] = $this->poLama($statusPo, $statusPo === 'PAID' ? 'PAID' : 'SENT');

            $this->assertNotContains($bast->id, $this->idBastTersedia(), "PO {$statusPo}");
            $this->assertTrue($po->punyaInvoiceLamaBelumTertaut());
            $this->assertFalse($po->usesBastBilling());

            $this->actingAs($this->userWithRole('superadmin'))
                ->post(route('invoice-cash-receipt.store'), $this->payload($bast))
                ->assertSessionHas('error'); // dijaga juga di server, bukan hanya dropdown
        }
    }

    public function test_status_no_po_tidak_turun_oleh_invoice_lama_dibayar_dibatalkan_atau_dihapus(): void
    {
        // Menunggu: INVOICED tetap INVOICED (tidak dihitung dari BAST sintetis yang "belum ditagih").
        [$po, , $inv] = $this->poLama('INVOICED');
        $po->fresh()->syncInvoiceStatus();
        $this->assertSame('INVOICED', $po->fresh()->status);

        // Dibayar: alur lama -> No. PO PAID.
        CashReceipt::create([
            'invoice_id' => $inv->id, 'received_date' => '2026-10-20', 'amount' => 111_000_000,
            'source_fund_id' => $this->sumber()->id,
        ]);
        $this->assertSame('PAID', $inv->fresh()->status);
        $this->assertSame('PAID', $po->fresh()->status);
        $po->fresh()->syncInvoiceStatus();
        $this->assertSame('PAID', $po->fresh()->status, 'sinkron sesudah dibayar tidak menurunkan');

        // Dibatalkan: sama dengan perilaku lama (kembali menunggu tagihan), bukan RELEASED/PARTIAL.
        [$po2, , $inv2] = $this->poLama('INVOICED');
        $inv2->update(['status' => 'CANCELLED']);
        $po2->fresh()->syncInvoiceStatus();
        $this->assertSame('BAST_SUBMITTED', $po2->fresh()->status);

        // Dihapus (soft delete): sama.
        [$po3, , $inv3] = $this->poLama('INVOICED');
        $inv3->delete();
        $po3->fresh()->syncInvoiceStatus();
        $this->assertSame('BAST_SUBMITTED', $po3->fresh()->status);
    }

    public function test_sesudah_link_bast_apply_status_tetap_dan_bast_dihitung_tertagih(): void
    {
        [$po, $bast, $inv] = $this->poLama('INVOICED');

        $this->artisan('invoice:link-bast', ['--apply' => true])->assertExitCode(0);

        $this->assertSame($bast->id, $inv->fresh()->project_po_bast_id);
        $po = $po->fresh();
        $this->assertFalse($po->punyaInvoiceLamaBelumTertaut());
        $this->assertTrue($po->usesBastBilling());
        $po->syncInvoiceStatus();
        $this->assertSame('INVOICED', $po->fresh()->status, 'perilaku sama setelah tertaut');
        $this->assertTrue($bast->fresh()->isBilled());
        $this->assertNotContains($bast->id, $this->idBastTersedia());

        $inv->fresh()->update(['status' => 'PAID']);
        $po->fresh()->syncInvoiceStatus();
        $this->assertSame('PAID', $po->fresh()->status);
    }

    public function test_no_po_dengan_bast_dan_semua_invoice_lama_cancelled_boleh_ditagih_lagi(): void
    {
        [$po, $bast, $inv] = $this->poLama('INVOICED');
        $inv->update(['status' => 'CANCELLED']);

        $this->assertFalse($po->fresh()->punyaInvoiceLamaBelumTertaut());
        $this->assertContains($bast->id, $this->idBastTersedia());

        $this->actingAs($this->userWithRole('superadmin'))
            ->post(route('invoice-cash-receipt.store'), $this->payload($bast))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Invoice::where('project_po_bast_id', $bast->id)->count());
    }

    /* ------------------------------------------------------ billing_percentage & is_additional */

    public function test_billing_percentage_tidak_bisa_membuat_nilai_berbeda_dari_bast(): void
    {
        $po = $this->po();
        $bast = $this->makeBast($po, 100_000_000);
        $user = $this->userWithRole('superadmin');

        // 100jt x 50% = 50jt != BAST 100jt -> ditolak.
        $this->actingAs($user)
            ->post(route('invoice-cash-receipt.store'), $this->payload($bast, 100_000_000, ['billing_percentage' => 50]))
            ->assertSessionHas('error');
        $this->assertSame(0, Invoice::count());

        // 200jt x 50% = 100jt == BAST -> diterima (yang dibandingkan selalu billed_amount hasil hitung).
        $this->actingAs($user)
            ->post(route('invoice-cash-receipt.store'), $this->payload($bast, 200_000_000, ['billing_percentage' => 50]))
            ->assertSessionHasNoErrors();
        $this->assertSame(100_000_000, (int) Invoice::firstOrFail()->billed_amount);
    }

    public function test_invoice_ber_bast_tidak_pernah_additional(): void
    {
        // Nominal No. PO sengaja kecil: dengan logika lama invoice kedua pasti "Additional".
        $po = $this->po(100_000_000, 'PARTIAL');
        $b1 = $this->makeBast($po, 100_000_000, '2026-10-01');
        $b2 = $this->makeBast($po, 100_000_000, '2026-10-02');
        $user = $this->userWithRole('superadmin');

        $this->actingAs($user)->post(route('invoice-cash-receipt.store'), $this->payload($b1));
        $this->actingAs($user)->post(route('invoice-cash-receipt.store'), $this->payload($b2));

        $this->assertSame(2, Invoice::count());
        foreach (Invoice::all() as $inv) {
            $this->assertFalse((bool) $inv->is_additional);
            $this->assertSame(0, (int) $inv->additional_amount);
        }
    }

    /* ------------------------------------------------------ revisi invoice lama (belum tertaut) */

    public function test_revisi_invoice_lama_cancelled_ditolak_bila_no_po_sudah_punya_bast_ditagih_invoice_lain(): void
    {
        $po = $this->po(100_000_000, 'PARTIAL');
        $bast = $this->makeBast($po, 100_000_000);
        $lama = $this->makeInvoice($po, ['billed_amount' => 100_000_000, 'status' => 'CANCELLED']); // belum tertaut BAST
        $user = $this->userWithRole('superadmin');

        // Invoice baru atas BAST (boleh: invoice lama sudah CANCELLED).
        $this->actingAs($user)->post(route('invoice-cash-receipt.store'), $this->payload($bast))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Invoice::where('project_po_bast_id', $bast->id)->count());

        $this->actingAs($user)->post(route('invoice-cash-receipt.revise', $lama->id), ['notes' => 'coba lagi'])
            ->assertSessionHas('error');

        $this->assertSame('CANCELLED', $lama->fresh()->status);
    }

    public function test_revisi_invoice_lama_cancelled_diizinkan_bila_tidak_ada_bast_ditagih_invoice_lain(): void
    {
        $po = $this->po(100_000_000, 'PARTIAL');
        $this->makeBast($po, 100_000_000);
        $lama = $this->makeInvoice($po, ['billed_amount' => 100_000_000, 'status' => 'CANCELLED']);

        $this->actingAs($this->userWithRole('superadmin'))
            ->post(route('invoice-cash-receipt.revise', $lama->id), ['notes' => 'perbaiki'])
            ->assertSessionHasNoErrors();

        $this->assertSame('DRAFT', $lama->fresh()->status);
    }
}
