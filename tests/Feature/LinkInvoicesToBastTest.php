<?php

namespace Tests\Feature;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Command invoice:link-bast (Fase 1 penagihan per BAST): menautkan invoice lama
 * ke BAST tanpa mengubah nilai uang/status.
 */
class LinkInvoicesToBastTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    private function inv($po, int $billed, array $extra = []): Invoice
    {
        return $this->makeInvoice($po, array_merge(['billed_amount' => $billed], $extra));
    }

    private function bastId(Invoice $inv): ?int
    {
        return Invoice::withTrashed()->find($inv->id)->project_po_bast_id;
    }

    private function jalankan(array $opts = [])
    {
        return $this->artisan('invoice:link-bast', $opts);
    }

    private function logFlag(Invoice $inv): ?string
    {
        return DB::table('invoice_bast_links_log')->where('invoice_id', $inv->id)->value('flag');
    }

    public function test_r1_satu_bast_menautkan_invoice_dan_menandai_value_mismatch(): void
    {
        $po = $this->makePoNumber($this->makeProject());
        $bast = $this->makeBast($po, 100_000_000);
        $cocok = $this->inv($po, 100_000_000);

        $this->jalankan(['--apply' => true])->assertExitCode(0);

        $this->assertSame($bast->id, $this->bastId($cocok));
        $this->assertNull($this->logFlag($cocok));

        // Selisih kecil (dalam toleransi Rp 1.000) tetap tertaut, hanya diberi flag per invoice.
        $po2 = $this->makePoNumber($this->makeProject());
        $bast2 = $this->makeBast($po2, 100_000_000);
        $beda = $this->inv($po2, 100_000_500);
        $this->jalankan(['--apply' => true]);

        $this->assertSame($bast2->id, $this->bastId($beda));
        $this->assertSame('VALUE_MISMATCH', $this->logFlag($beda));
    }

    public function test_r1_lebih_dari_satu_invoice_aktif_diberi_flag_legacy_multi(): void
    {
        $po = $this->makePoNumber($this->makeProject());
        $bast = $this->makeBast($po, 100_000_000);
        // Dua invoice aktif yang jumlahnya sama dengan BAST (60 + 40): tertaut, ditandai LEGACY_MULTI.
        $a = $this->inv($po, 60_000_000);
        $b = $this->inv($po, 40_000_000);

        $this->jalankan(['--apply' => true]);

        $this->assertSame($bast->id, $this->bastId($a));
        $this->assertSame($bast->id, $this->bastId($b));
        $this->assertStringContainsString('LEGACY_MULTI', (string) $this->logFlag($a));
    }

    public function test_r2_cocok_nilai_persis_dan_terdekat_hanya_dengan_include_multi(): void
    {
        $po = $this->makePoNumber($this->makeProject());
        $b1 = $this->makeBast($po, 30_000_000, '2026-09-01');
        $b2 = $this->makeBast($po, 50_000_000, '2026-09-02');
        $i1 = $this->inv($po, 50_000_000, ['invoice_date' => '2026-09-10']); // persis -> b2
        $i2 = $this->inv($po, 28_000_000, ['invoice_date' => '2026-09-11']); // terdekat -> b1

        $this->jalankan(['--apply' => true]);
        $this->assertNull($this->bastId($i1), 'R2 tidak boleh ditulis tanpa --include-multi');

        $this->jalankan(['--apply' => true, '--include-multi' => true]);
        $this->assertSame($b2->id, $this->bastId($i1));
        $this->assertSame($b1->id, $this->bastId($i2));
        $this->assertSame('NEAREST', $this->logFlag($i2));
    }

    public function test_r2_invoice_cancelled_mengikuti_invoice_aktif_berikutnya(): void
    {
        $po = $this->makePoNumber($this->makeProject());
        $b1 = $this->makeBast($po, 30_000_000, '2026-09-01');
        $b2 = $this->makeBast($po, 50_000_000, '2026-09-02');
        $batal = $this->inv($po, 10_000_000, ['invoice_date' => '2026-09-01', 'status' => 'CANCELLED']);
        $aktif = $this->inv($po, 50_000_000, ['invoice_date' => '2026-09-05']);

        $this->jalankan(['--apply' => true, '--include-multi' => true]);

        $this->assertSame($b2->id, $this->bastId($aktif));
        $this->assertSame($b2->id, $this->bastId($batal));
        $this->assertSame('CANCELLED', $this->logFlag($batal));
    }

    public function test_r3_tanpa_bast_dan_r4_tanpa_no_po_tidak_disentuh(): void
    {
        $po = $this->makePoNumber($this->makeProject());
        $tanpaBast = $this->inv($po, 10_000_000);
        $tanpaPo = $this->inv($po, 20_000_000, ['project_po_number_id' => null]);

        $this->jalankan(['--apply' => true])->assertExitCode(0);

        $this->assertNull($this->bastId($tanpaBast));
        $this->assertNull($this->bastId($tanpaPo));
        $this->assertSame(0, DB::table('invoice_bast_links_log')->count());
    }

    public function test_dry_run_tidak_menulis_apa_pun(): void
    {
        $po = $this->makePoNumber($this->makeProject());
        $this->makeBast($po, 100_000_000);
        $inv = $this->inv($po, 100_000_000);

        $this->jalankan()->assertExitCode(0);

        $this->assertNull($this->bastId($inv));
        $this->assertSame(0, DB::table('invoice_bast_links_log')->count());
    }

    public function test_idempoten_dan_invarian_nilai_uang_tidak_berubah(): void
    {
        $po = $this->makePoNumber($this->makeProject());
        $this->makeBast($po, 100_000_000);
        $inv = $this->inv($po, 100_000_000);
        $sebelum = Invoice::withTrashed()->get(['id', 'billed_amount', 'captured_total', 'tax_amount', 'withholding_tax', 'status'])->toArray();

        $this->jalankan(['--apply' => true]);
        $this->jalankan(['--apply' => true]);

        $this->assertSame(1, DB::table('invoice_bast_links_log')->where('invoice_id', $inv->id)->count());
        $sesudah = Invoice::withTrashed()->get(['id', 'billed_amount', 'captured_total', 'tax_amount', 'withholding_tax', 'status'])->toArray();
        $this->assertSame($sebelum, $sesudah);
    }

    public function test_invoice_soft_deleted_ikut_tertaut(): void
    {
        $po = $this->makePoNumber($this->makeProject());
        $bast = $this->makeBast($po, 100_000_000);
        $inv = $this->inv($po, 100_000_000);
        $inv->delete();

        $this->jalankan(['--apply' => true]);

        $this->assertSame($bast->id, $this->bastId($inv));
    }

    public function test_rollback_default_dry_run_tidak_menulis(): void
    {
        $po = $this->makePoNumber($this->makeProject());
        $bast = $this->makeBast($po, 100_000_000);
        $inv = $this->inv($po, 100_000_000);

        $this->jalankan(['--apply' => true]);
        $runId = DB::table('invoice_bast_links_log')->value('run_id');

        $this->jalankan(['--rollback' => $runId])->assertExitCode(0);

        $this->assertSame($bast->id, $this->bastId($inv), 'dry run tidak boleh mengosongkan');
        $this->assertNull(DB::table('invoice_bast_links_log')->value('rolled_back_at'));
    }

    public function test_rollback_apply_mengosongkan_dan_mencatat_rolled_back_at(): void
    {
        $po = $this->makePoNumber($this->makeProject());
        $this->makeBast($po, 100_000_000);
        $inv = $this->inv($po, 100_000_000);
        $sebelum = Invoice::withTrashed()->get(['id', 'billed_amount', 'captured_total', 'tax_amount', 'withholding_tax', 'status'])->toArray();

        $this->jalankan(['--apply' => true]);
        $runId = DB::table('invoice_bast_links_log')->value('run_id');

        $this->jalankan(['--rollback' => $runId, '--apply' => true])->assertExitCode(0);

        $this->assertNull($this->bastId($inv));
        $this->assertNotNull(DB::table('invoice_bast_links_log')->value('rolled_back_at'));
        $this->assertSame(1, DB::table('invoice_bast_links_log')->count(), 'log tidak dihapus');
        $this->assertSame($sebelum, Invoice::withTrashed()->get(['id', 'billed_amount', 'captured_total', 'tax_amount', 'withholding_tax', 'status'])->toArray());

        // Rollback kedua tidak melakukan apa pun.
        $this->jalankan(['--rollback' => $runId, '--apply' => true])->assertExitCode(0);
        $this->assertNull($this->bastId($inv));
    }

    public function test_rollback_melewati_tautan_yang_sudah_berubah(): void
    {
        $po = $this->makePoNumber($this->makeProject());
        $this->makeBast($po, 100_000_000);
        $lain = $this->makeBast($po, 50_000_000);
        $inv = $this->inv($po, 100_000_000);
        // Dua BAST pada satu No. PO = R2, tulis dengan --include-multi.
        $this->jalankan(['--apply' => true, '--include-multi' => true]);
        $runId = DB::table('invoice_bast_links_log')->value('run_id');

        // Tautan diubah pihak lain setelah run (mis. user mengedit invoice).
        DB::table('invoices')->where('id', $inv->id)->update(['project_po_bast_id' => $lain->id]);

        $this->jalankan(['--rollback' => $runId, '--apply' => true])->assertExitCode(0);

        $this->assertSame($lain->id, $this->bastId($inv), 'tautan yang sudah berubah tidak ditimpa');
        $this->assertNull(DB::table('invoice_bast_links_log')->value('rolled_back_at'));
    }

    public function test_down_migrasi_menolak_saat_masih_ada_tautan(): void
    {
        $po = $this->makePoNumber($this->makeProject());
        $this->makeBast($po, 100_000_000);
        $this->inv($po, 100_000_000);
        $this->jalankan(['--apply' => true]);

        // Penjaga berjalan sebelum DDL apa pun, jadi tidak ada yang terhapus.
        $migrasi = require database_path('migrations/2026_10_18_000200_add_bast_link_to_invoices.php');
        try {
            $migrasi->down();
            $this->fail('down() seharusnya menolak karena masih ada tautan.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('invoice:link-bast --rollback', $e->getMessage());
        }

        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('invoices', 'project_po_bast_id'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('invoice_bast_links_log'));
    }

    /* ------------------------------------------------------ R1: total tagihan vs nilai BAST */

    public function test_r1_total_tagihan_sama_dengan_bast_tertaut_termasuk_invoice_cancelled(): void
    {
        $po = $this->makePoNumber($this->makeProject());
        $bast = $this->makeBast($po, 100_000_000);
        $aktif = $this->inv($po, 100_000_000);
        $batal = $this->inv($po, 7_000_000, ['status' => 'CANCELLED']); // tidak dihitung ke total

        $this->jalankan(['--apply' => true]);

        $this->assertSame($bast->id, $this->bastId($aktif));
        $this->assertSame($bast->id, $this->bastId($batal));
        $this->assertStringNotContainsString('TAGIHAN_TIDAK_SAMA_BAST', (string) $this->logFlag($aktif));
    }

    public function test_r1_total_dalam_toleransi_tetap_tertaut_tanpa_flag_tagihan_tidak_sama(): void
    {
        $po = $this->makePoNumber($this->makeProject());
        $bast = $this->makeBast($po, 100_000_000);
        $inv = $this->inv($po, 100_001_000); // selisih tepat Rp 1.000 = masih sama

        $this->jalankan(['--apply' => true]);

        $this->assertSame($bast->id, $this->bastId($inv));
        $this->assertStringNotContainsString('TAGIHAN_TIDAK_SAMA_BAST', (string) $this->logFlag($inv));
    }

    public function test_r1_total_kurang_atau_lebih_dari_bast_dilewati_seluruhnya_tanpa_opsi(): void
    {
        $kurang = $this->makePoNumber($this->makeProject());
        $this->makeBast($kurang, 100_000_000);
        $iKurang = $this->inv($kurang, 60_000_000);
        $iKurangBatal = $this->inv($kurang, 5_000_000, ['status' => 'CANCELLED']);

        $lebih = $this->makePoNumber($this->makeProject());
        $this->makeBast($lebih, 100_000_000);
        $iLebih = $this->inv($lebih, 150_000_000);

        $this->jalankan(['--apply' => true])
            ->expectsOutputToContain('TAGIHAN_TIDAK_SAMA_BAST: 2 No. PO, 3 invoice')
            ->assertExitCode(0);

        foreach ([$iKurang, $iKurangBatal, $iLebih] as $inv) {
            $this->assertNull($this->bastId($inv), 'harus dilewati, termasuk invoice CANCELLED');
        }
        $this->assertSame(0, DB::table('invoice_bast_links_log')->count());
    }

    public function test_r1_total_tidak_sama_tertaut_dengan_opsi_dan_tercatat_di_log(): void
    {
        $po = $this->makePoNumber($this->makeProject());
        $bast = $this->makeBast($po, 100_000_000);
        $inv = $this->inv($po, 60_000_000);
        $batal = $this->inv($po, 5_000_000, ['status' => 'CANCELLED']);

        $this->jalankan(['--apply' => true, '--sertakan-tagihan-tidak-sama' => true])->assertExitCode(0);

        $this->assertSame($bast->id, $this->bastId($inv));
        $this->assertSame($bast->id, $this->bastId($batal));
        $this->assertStringContainsString('TAGIHAN_TIDAK_SAMA_BAST', (string) $this->logFlag($inv));
    }
}
