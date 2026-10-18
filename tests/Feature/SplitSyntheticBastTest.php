<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\ProjectPoBast;
use App\Models\ProjectPoNumber;
use App\Models\SourceFund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Fase 1b — pemecahan BAST sintetis (bast:mark-backfill & invoice:split-synthetic-bast).
 */
class SplitSyntheticBastTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    private function jalankan(array $opts = [])
    {
        return $this->artisan('invoice:split-synthetic-bast', $opts);
    }

    /** No. PO lama: agregat kolom lama + 1 BAST sintetis (BACKFILL) bernilai 300 juta. */
    private function poSintetis(string $statusPo = 'INVOICED'): array
    {
        $po = $this->makePoNumber($this->makeProject(), [
            'nominal' => 300_000_000, 'status' => $statusPo,
            'nilai_bast' => 300_000_000, 'no_bast' => 'B-001', 'tanggal_bast' => '2026-09-01',
        ]);
        $parent = $this->makeBast($po, 300_000_000, '2026-09-01', 'B-001', ['origin' => 'BACKFILL']);

        return [$po, $parent];
    }

    private function inv(ProjectPoNumber $po, int $billed, array $extra = []): Invoice
    {
        return $this->makeInvoice($po, array_merge([
            'billed_amount' => $billed, 'captured_total' => $billed, 'tax_amount' => 0,
            'invoice_date' => '2026-09-10', 'status' => 'SENT',
        ], $extra));
    }

    private function snapshotInvoice(): array
    {
        return Invoice::withTrashed()->orderBy('id')
            ->get(['id', 'billed_amount', 'captured_total', 'tax_amount', 'withholding_tax', 'status'])->toArray();
    }

    private function bastAktif(ProjectPoNumber $po)
    {
        return ProjectPoBast::where('project_po_number_id', $po->id)->orderBy('id')->get();
    }

    private function bastId(Invoice $inv): ?int
    {
        return Invoice::withTrashed()->find($inv->id)->project_po_bast_id;
    }

    /* ------------------------------------------------------ PEMECAHAN */

    public function test_tertagih_kurang_dengan_dua_invoice_aktif_menjadi_dua_pecahan_dan_satu_sisa(): void
    {
        [$po, $parent] = $this->poSintetis();
        $a = $this->inv($po, 100_000_000, ['invoice_date' => '2026-09-10']);
        $b = $this->inv($po, 50_000_000, ['invoice_date' => '2026-09-11', 'status' => 'DRAFT']);
        $sebelum = $this->snapshotInvoice();
        $agregat = $po->fresh()->only(['nilai_bast', 'no_bast', 'tanggal_bast']);

        $this->jalankan(['--apply' => true])->assertExitCode(0);

        $aktif = $this->bastAktif($po);
        $this->assertCount(3, $aktif);
        [$t1, $t2, $sisa] = [$aktif[0], $aktif[1], $aktif[2]];

        $this->assertSame('B-001/T1', $t1->no_bast);
        $this->assertSame(100_000_000, (int) $t1->nilai_bast);
        $this->assertSame('B-001/T2', $t2->no_bast);
        $this->assertSame(50_000_000, (int) $t2->nilai_bast);
        $this->assertSame('B-001', $sisa->no_bast);
        $this->assertSame(150_000_000, (int) $sisa->nilai_bast);
        $this->assertSame(ProjectPoBast::ORIGIN_SPLIT_INVOICE, $t1->origin);
        $this->assertSame(ProjectPoBast::ORIGIN_SPLIT_SISA, $sisa->origin);
        $this->assertSame($parent->id, $t1->split_from_bast_id);
        $this->assertGreaterThan($t2->id, $sisa->id, 'sisa dibuat terakhir');

        $this->assertSame($t1->id, $this->bastId($a));
        $this->assertSame($t2->id, $this->bastId($b), 'invoice DRAFT ikut dipecah');
        $this->assertFalse($sisa->isBilled());

        // Induk soft-delete, nilainya tidak diubah.
        $trashed = ProjectPoBast::withTrashed()->find($parent->id);
        $this->assertNotNull($trashed->deleted_at);
        $this->assertStringContainsString('Dipecah oleh run', $trashed->delete_reason);
        $this->assertSame(300_000_000, (int) $trashed->nilai_bast);

        // Invarian: Σ BAST aktif, agregat No. PO, serta nilai & status invoice.
        $this->assertSame(300_000_000, (int) $aktif->sum('nilai_bast'));
        $this->assertSame($agregat, $po->fresh()->only(['nilai_bast', 'no_bast', 'tanggal_bast']));
        $this->assertSame($sebelum, $this->snapshotInvoice());
    }

    public function test_dry_run_tidak_menulis_dan_run_kedua_idempoten(): void
    {
        [$po, $parent] = $this->poSintetis();
        $this->inv($po, 100_000_000);

        $this->jalankan()->assertExitCode(0);
        $this->assertCount(1, $this->bastAktif($po));
        $this->assertSame(0, DB::table('bast_split_log')->count());

        $this->jalankan(['--apply' => true]);
        $jumlah = ProjectPoBast::withTrashed()->count();
        $log = DB::table('bast_split_log')->count();

        $this->jalankan(['--apply' => true]);
        $this->assertSame($jumlah, ProjectPoBast::withTrashed()->count(), 'induk sudah dipecah, tidak diulang');
        $this->assertSame($log, DB::table('bast_split_log')->count());
    }

    public function test_tertagih_lebih_tidak_menulis_apa_pun(): void
    {
        [$po] = $this->poSintetis();
        $this->inv($po, 400_000_000);

        $this->jalankan(['--apply' => true])
            ->expectsOutputToContain('TAGIHAN_LEBIH: 1 No. PO')
            ->assertExitCode(0);

        $this->assertCount(1, $this->bastAktif($po));
        $this->assertSame(0, DB::table('bast_split_log')->count());
    }

    public function test_selisih_dalam_toleransi_bukan_kandidat(): void
    {
        [$po] = $this->poSintetis();
        $inv = $this->inv($po, 299_999_500); // selisih Rp 500

        $this->jalankan(['--apply' => true])->assertExitCode(0);

        $this->assertCount(1, $this->bastAktif($po));
        $this->assertNull($this->bastId($inv));
    }

    public function test_bast_bukan_sintetis_tidak_dipecah(): void
    {
        $po = $this->makePoNumber($this->makeProject(), ['nominal' => 300_000_000, 'status' => 'INVOICED']);
        $this->makeBast($po, 300_000_000); // origin NULL = submit normal
        $this->inv($po, 100_000_000);

        $this->jalankan(['--apply' => true])
            ->expectsOutputToContain('SKIP_BUKAN_SINTETIS: 1 No. PO')
            ->assertExitCode(0);

        $this->assertCount(1, $this->bastAktif($po));
    }

    public function test_invoice_cancelled_dan_terhapus_ditautkan_ke_induk_dan_sisa_bisa_ditagih_persis(): void
    {
        Storage::fake('secure');
        [$po, $parent] = $this->poSintetis();
        $this->inv($po, 100_000_000);
        $batal = $this->inv($po, 10_000_000, ['status' => 'CANCELLED']);
        $hapus = $this->inv($po, 20_000_000);
        $hapus->delete();

        $this->jalankan(['--apply' => true]);

        $this->assertSame($parent->id, $this->bastId($batal));
        $this->assertSame($parent->id, $this->bastId($hapus));
        $this->assertSame(2, DB::table('bast_split_log')->where('action', 'LINK_HISTORY')->count());

        $sisa = $this->bastAktif($po)->last();
        $this->assertSame(200_000_000, (int) $sisa->nilai_bast);
        $this->assertFalse($sisa->isBilled());

        $sumber = SourceFund::create(['name' => 'Rekening PT Uji', 'no_account' => '1', 'status' => 'ACTIVE']);
        $payload = fn (int $harga) => [
            'project_po_bast_id' => $sisa->id, 'source_fund_id' => $sumber->id, 'faktur_number' => '010',
            'payment_term_type' => 'Termin', 'termin_number' => 2, 'invoice_date' => '2026-10-10', 'due_date' => '2026-11-10',
            'tax_rate' => 11, 'details' => [['description' => 'Jasa', 'unit_price' => (string) $harga, 'qty' => 1]],
        ];
        $user = $this->userWithRole('superadmin');

        $this->actingAs($user)->post(route('invoice-cash-receipt.store'), $payload(199_999_999))->assertSessionHas('error');
        $this->actingAs($user)->post(route('invoice-cash-receipt.store'), $payload(200_000_000))->assertSessionHasNoErrors();
        $this->assertTrue($sisa->fresh()->isBilled());
    }

    public function test_edit_draft_hasil_pecahan_tanpa_toleransi(): void
    {
        [$po] = $this->poSintetis();
        $draft = $this->inv($po, 100_000_000, ['status' => 'DRAFT']);
        $this->jalankan(['--apply' => true]);
        $pecahan = ProjectPoBast::find($this->bastId($draft));

        $sumber = SourceFund::create(['name' => 'Rekening PT Uji', 'no_account' => '1', 'status' => 'ACTIVE']);
        $payload = fn (int $harga) => [
            'project_po_bast_id' => $pecahan->id, 'source_fund_id' => $sumber->id, 'faktur_number' => '010',
            'payment_term_type' => 'Termin', 'termin_number' => 1, 'invoice_date' => '2026-10-10', 'due_date' => '2026-11-10',
            'tax_rate' => 11, 'details' => [['description' => 'Jasa', 'unit_price' => (string) $harga, 'qty' => 1]],
        ];
        $user = $this->userWithRole('superadmin');

        $this->actingAs($user)->put(route('invoice-cash-receipt.update', $draft->id), $payload(100_000_500))
            ->assertSessionHas('error');
        $this->actingAs($user)->put(route('invoice-cash-receipt.update', $draft->id), $payload(100_000_000))
            ->assertSessionHasNoErrors();
        $this->assertSame(100_000_000, (int) $draft->fresh()->billed_amount);
    }

    public function test_rencana_berubah_di_dalam_kunci_dilewati(): void
    {
        [$po, $parent] = $this->poSintetis();
        $inv = $this->inv($po, 100_000_000);

        // Setelah kunci BAST induk diambil, orang lain mengubah tanggal invoice: rencana awal tak berlaku lagi
        // (yang diubah sengaja bukan nilai uang/status, supaya kontrol invarian run tidak ikut terpicu).
        $sudah = false;
        DB::listen(function ($query) use (&$sudah, $inv) {
            if ($sudah || ! str_contains(strtolower($query->sql), 'for update')
                || ! str_contains(strtolower($query->sql), 'from `project_po_basts`')) {
                return;
            }
            $sudah = true;
            DB::table('invoices')->where('id', $inv->id)->update(['invoice_date' => '2026-09-12']);
        });

        $this->jalankan(['--apply' => true])
            ->expectsOutputToContain('BERUBAH_SEJAK_DRYRUN: 1')
            ->assertExitCode(0);

        $this->assertNull(ProjectPoBast::find($parent->id)->deleted_at);
        $this->assertCount(1, $this->bastAktif($po));
        $this->assertSame(0, DB::table('bast_split_log')->count());
    }

    /* ------------------------------------------------------ ROLLBACK */

    public function test_rollback_default_dry_run_lalu_apply_memulihkan_keadaan_persis(): void
    {
        [$po, $parent] = $this->poSintetis();
        $a = $this->inv($po, 100_000_000);
        $batal = $this->inv($po, 10_000_000, ['status' => 'CANCELLED']);
        $sebelum = $this->snapshotInvoice();

        $this->jalankan(['--apply' => true]);
        $runId = DB::table('bast_split_log')->value('run_id');
        $this->assertNotNull($this->bastId($a));

        $this->jalankan(['--rollback' => $runId])->assertExitCode(0);
        $this->assertNotNull($this->bastId($a), 'dry run rollback tidak menulis');

        $this->jalankan(['--rollback' => $runId, '--apply' => true])->assertExitCode(0);

        $this->assertNull($this->bastId($a));
        $this->assertNull($this->bastId($batal));
        $this->assertNull(ProjectPoBast::withTrashed()->find($parent->id)->deleted_at);
        $this->assertNull(ProjectPoBast::withTrashed()->find($parent->id)->delete_reason);
        $this->assertCount(1, $this->bastAktif($po));
        $this->assertSame(300_000_000, (int) $this->bastAktif($po)->sum('nilai_bast'));
        $this->assertSame($sebelum, $this->snapshotInvoice());
        $this->assertSame(0, DB::table('bast_split_log')->whereNull('rolled_back_at')->count());
        $this->assertGreaterThan(0, DB::table('bast_split_log')->count(), 'log tidak dihapus');
    }

    public function test_rollback_dilewati_bila_sisa_sudah_ditagih_invoice_baru(): void
    {
        [$po] = $this->poSintetis();
        $this->inv($po, 100_000_000);
        $this->jalankan(['--apply' => true]);
        $runId = DB::table('bast_split_log')->value('run_id');

        $sisa = $this->bastAktif($po)->last();
        $baru = $this->makeInvoice($po, ['billed_amount' => 200_000_000, 'status' => 'DRAFT'], $sisa);

        $this->jalankan(['--rollback' => $runId, '--apply' => true])
            ->expectsOutputToContain('DILEWATI')
            ->assertExitCode(0);

        $this->assertSame($sisa->id, $this->bastId($baru));
        $this->assertCount(2, $this->bastAktif($po), 'pecahan + sisa tetap');
    }

    /* ------------------------------------------------------ STATUS */

    public function test_apply_tidak_mengubah_status_dan_sync_po_mengikuti_tandai_lengkap_manual(): void
    {
        // Sudah ditandai lengkap (INVOICED): setelah dipecah ada sisa belum ditagih -> BAST_SUBMITTED.
        [$lengkap] = $this->poSintetis('INVOICED');
        $this->inv($lengkap, 100_000_000);
        // Belum ditandai lengkap (PARTIAL): tidak naik otomatis.
        [$partial] = $this->poSintetis('PARTIAL');
        $this->inv($partial, 100_000_000);

        $this->jalankan(['--apply' => true]);
        $runId = DB::table('bast_split_log')->value('run_id');

        $this->assertSame('INVOICED', $lengkap->fresh()->status, '--apply tidak menyentuh status');
        $this->assertSame('PARTIAL', $partial->fresh()->status);

        // Dry run: tidak menulis.
        $this->jalankan(['--sync-po' => $runId])->assertExitCode(0);
        $this->assertSame('INVOICED', $lengkap->fresh()->status);

        $this->jalankan(['--sync-po' => $runId, '--apply' => true])->assertExitCode(0);
        $this->assertSame('BAST_SUBMITTED', $lengkap->fresh()->status);
        $this->assertSame('PARTIAL', $partial->fresh()->status, 'Tandai Lengkap tetap manual');
    }

    /* ------------------------------------------------------ AGREGAT, PRINT */

    public function test_recalculate_bast_aggregate_konsisten_setelah_pemecahan_dan_submit_berikutnya(): void
    {
        Storage::fake('secure');
        [$po] = $this->poSintetis('PARTIAL');
        $this->inv($po, 100_000_000);
        $this->jalankan(['--apply' => true]);

        // BAST aktif terakhir = sisa (id terbesar, tanggal sama): nomor & tanggal induk.
        $po = $po->fresh();
        $po->recalculateBastAggregate();
        $this->assertSame('B-001', $po->fresh()->no_bast);
        $this->assertSame('2026-09-01', $po->fresh()->tanggal_bast->toDateString());
        $this->assertSame(300_000_000, (int) $po->fresh()->nilai_bast);

        $po->update(['nominal' => 500_000_000]);
        $this->actingAs($this->userWithRole('superadmin'))->post(route('project.submit-bast.store', $po->project_id), [
            'po_numbers' => [$po->id => [
                'no_bast' => 'B-002', 'tanggal_bast' => '2026-10-10', 'nominal_bast' => '100000000',
                'attachments' => [UploadedFile::fake()->create('b.pdf', 10, 'application/pdf')],
            ]],
        ]);

        $po = $po->fresh();
        $po->recalculateBastAggregate();
        $this->assertSame('B-002', $po->fresh()->no_bast, 'BAST terbaru menurut tanggal, id');
        $this->assertSame(400_000_000, (int) $po->fresh()->nilai_bast);
    }

    public function test_print_invoice_tidak_memuat_no_bast(): void
    {
        $po = $this->makePoNumber($this->makeProject());
        $bast = $this->makeBast($po, 100_000_000, '2026-10-01', 'BAST-RAHASIA-777');
        $inv = $this->makeInvoice($po, ['billed_amount' => 100_000_000], $bast);

        $this->actingAs($this->userWithRole('superadmin'))
            ->get(route('invoice-cash-receipt.print', $inv->id))
            ->assertOk()
            ->assertDontSee('BAST-RAHASIA-777')
            ->assertDontSee('No BAST');
    }

    /* ------------------------------------------------------ bast:mark-backfill */

    public function test_mark_backfill_hanya_menulis_origin_dan_melaporkan_yang_meragukan(): void
    {
        $po1 = $this->makePoNumber($this->makeProject(), ['nilai_bast' => 100_000_000, 'no_bast' => 'B-1']);
        $ok = $this->makeBast($po1, 100_000_000, '2026-09-01', 'B-1');
        $po2 = $this->makePoNumber($this->makeProject(), ['nilai_bast' => 999, 'no_bast' => 'X']);
        $ragu = $this->makeBast($po2, 100_000_000, '2026-09-01', 'B-2'); // nilai tidak sama dengan agregat
        $inv = $this->makeInvoice($po1, ['billed_amount' => 100_000_000], $ok);
        $sebelum = $this->snapshotInvoice();
        $max = max($ok->id, $ragu->id);

        $this->artisan('bast:mark-backfill', ['--max-backfill-id' => $max])->assertExitCode(0);
        $this->assertNull($ok->fresh()->origin, 'dry run tidak menulis');

        $this->artisan('bast:mark-backfill', ['--max-backfill-id' => $max, '--apply' => true])
            ->expectsOutputToContain('Meragukan (TIDAK ditandai, periksa manual): 1')
            ->assertExitCode(0);

        $this->assertSame('BACKFILL', $ok->fresh()->origin);
        $this->assertNull($ragu->fresh()->origin);
        $this->assertSame($sebelum, $this->snapshotInvoice());
        $this->assertSame(1, DB::table('bast_split_log')->where('action', 'MARK_BACKFILL')->count());
        $this->assertNotNull($inv->fresh());
    }
}
