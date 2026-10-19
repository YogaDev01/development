<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\ProjectPoBast;
use App\Models\ProjectPoNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * bast:create-from-invoice — BAST dari invoice untuk No. PO yang tidak punya baris BAST.
 */
class CreateBastFromInvoiceTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    private function jalankan(array $opts = [])
    {
        return $this->artisan('bast:create-from-invoice', $opts);
    }

    /** No. PO PAID tanpa BAST, nominal 100 juta. */
    private function poTanpaBast(int $nominal = 100_000_000): ProjectPoNumber
    {
        return $this->makePoNumber($this->makeProject(), ['nominal' => $nominal, 'status' => 'PAID']);
    }

    private function inv(ProjectPoNumber $po, int $billed, array $extra = []): Invoice
    {
        return $this->makeInvoice($po, array_merge([
            'billed_amount' => $billed, 'captured_total' => $billed, 'tax_amount' => 0,
            'invoice_date' => '2026-05-10', 'status' => 'PAID',
        ], $extra));
    }

    private function snapshotInvoice(): array
    {
        return Invoice::withTrashed()->orderBy('id')
            ->get(['id', 'billed_amount', 'captured_total', 'tax_amount', 'withholding_tax', 'status'])->toArray();
    }

    private function bastId(Invoice $inv): ?int
    {
        return Invoice::withTrashed()->find($inv->id)->project_po_bast_id;
    }

    public function test_dry_run_tidak_menulis_apa_pun(): void
    {
        $po = $this->poTanpaBast();
        $this->inv($po, 40_000_000);

        $this->jalankan()->assertExitCode(0);

        $this->assertSame(0, ProjectPoBast::withTrashed()->count());
        $this->assertSame(0, DB::table('invoice_bast_links_log')->count());
    }

    public function test_apply_tanpa_po_ditolak(): void
    {
        $po = $this->poTanpaBast();
        $this->inv($po, 40_000_000);

        $this->jalankan(['--apply' => true])->assertExitCode(1);

        $this->assertSame(0, ProjectPoBast::withTrashed()->count());
    }

    public function test_apply_membuat_satu_bast_per_invoice_aktif_tanpa_mengubah_invoice_dan_status_po(): void
    {
        $po = $this->poTanpaBast();
        $a = $this->inv($po, 40_000_000, ['invoice_date' => '2026-05-10']);
        $b = $this->inv($po, 20_000_000, ['invoice_date' => '2026-06-10']);
        $batal = $this->inv($po, 5_000_000, ['status' => 'CANCELLED']);
        $sebelum = $this->snapshotInvoice();

        $this->jalankan(['--apply' => true, '--po' => (string) $po->id])->assertExitCode(0);

        $basts = ProjectPoBast::where('project_po_number_id', $po->id)->orderBy('id')->get();
        $this->assertCount(2, $basts);
        $this->assertSame('TANPA-BAST/' . $a->code, $basts[0]->no_bast);
        $this->assertSame(40_000_000, (int) $basts[0]->nilai_bast);
        $this->assertSame('2026-05-10', $basts[0]->tanggal_bast->toDateString());
        $this->assertSame(ProjectPoBast::ORIGIN_FROM_INVOICE, $basts[0]->origin);
        $this->assertSame(20_000_000, (int) $basts[1]->nilai_bast);

        $this->assertSame($basts[0]->id, $this->bastId($a));
        $this->assertSame($basts[1]->id, $this->bastId($b));
        $this->assertNull($this->bastId($batal), 'invoice CANCELLED dibiarkan tidak tertaut');

        $po->refresh();
        $this->assertSame(60_000_000, (int) $po->nilai_bast);
        $this->assertSame('TANPA-BAST/' . $b->code, $po->no_bast);
        $this->assertSame('PAID', $po->status, 'status No. PO tidak berubah');
        $this->assertSame($sebelum, $this->snapshotInvoice());
        $this->assertSame(2, DB::table('invoice_bast_links_log')->where('rule', 'FROM_INVOICE')->count());
    }

    public function test_no_po_yang_sudah_punya_bast_tidak_disentuh(): void
    {
        $po = $this->poTanpaBast();
        $this->makeBast($po, 40_000_000);
        $this->inv($po, 40_000_000);

        $this->jalankan(['--apply' => true, '--po' => (string) $po->id])->assertExitCode(0);

        $this->assertSame(1, ProjectPoBast::withTrashed()->where('project_po_number_id', $po->id)->count());
    }

    public function test_total_melebihi_nominal_dilewati(): void
    {
        $po = $this->poTanpaBast(50_000_000);
        $a = $this->inv($po, 40_000_000);
        $this->inv($po, 20_000_000);

        $this->jalankan(['--apply' => true, '--po' => (string) $po->id])->assertExitCode(0);

        $this->assertSame(0, ProjectPoBast::withTrashed()->count());
        $this->assertNull($this->bastId($a));
    }

    public function test_rollback_memulihkan_keadaan_semula(): void
    {
        $po = $this->poTanpaBast();
        $po->update(['nilai_bast' => 0, 'no_bast' => null, 'tanggal_bast' => null]);
        $a = $this->inv($po, 40_000_000);
        $sebelum = $this->snapshotInvoice();

        $this->jalankan(['--apply' => true, '--po' => (string) $po->id])->assertExitCode(0);
        $runId = DB::table('invoice_bast_links_log')->value('run_id');
        $this->assertNotNull($this->bastId($a));

        $this->jalankan(['--rollback' => $runId])->assertExitCode(0);
        $this->assertNotNull($this->bastId($a), 'dry run rollback tidak menulis');

        $this->jalankan(['--rollback' => $runId, '--apply' => true])->assertExitCode(0);

        $this->assertNull($this->bastId($a));
        $this->assertSame(0, ProjectPoBast::where('project_po_number_id', $po->id)->count());
        $po->refresh();
        $this->assertSame(0, (int) $po->nilai_bast);
        $this->assertNull($po->no_bast);
        $this->assertSame('PAID', $po->status);
        $this->assertSame($sebelum, $this->snapshotInvoice());
        $this->assertSame(0, DB::table('invoice_bast_links_log')->whereNull('rolled_back_at')->count());
        $this->assertGreaterThan(0, DB::table('invoice_bast_links_log')->count(), 'jejak tidak dihapus');
    }
}
