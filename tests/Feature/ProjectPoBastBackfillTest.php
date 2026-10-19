<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Backfill migrasi project_po_basts: tanggal selalu valid dan up() idempoten.
 */
class ProjectPoBastBackfillTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    private function jalankanBackfill(): void
    {
        // Tabel/kolom sudah ada dari RefreshDatabase; up() hanya mengulang backfill.
        (require database_path('migrations/2026_10_18_000100_create_project_po_basts_table.php'))->up();
    }

    public function test_backfill_tanpa_sumber_tanggal_tetap_menghasilkan_tanggal_valid(): void
    {
        $project = $this->makeProject();
        $po = $this->makePoNumber($project);
        DB::table('project_po_numbers')->where('id', $po->id)->update([
            'no_bast' => 'BAST-LAMA', 'nilai_bast' => 5_000_000,
            'tanggal_bast' => null, 'bast_submitted_at' => null, 'updated_at' => null,
        ]);

        $this->jalankanBackfill();

        $baris = DB::table('project_po_basts')->where('project_po_number_id', $po->id)->first();
        $this->assertNotNull($baris);
        $this->assertSame(5_000_000, (int) $baris->nilai_bast);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}/', (string) $baris->tanggal_bast);
    }

    public function test_backfill_dua_kali_tidak_menggandakan_baris(): void
    {
        $project = $this->makeProject();
        $po = $this->makePoNumber($project);
        DB::table('project_po_numbers')->where('id', $po->id)->update([
            'no_bast' => 'BAST-LAMA', 'nilai_bast' => 5_000_000, 'tanggal_bast' => '2026-09-01',
        ]);

        $this->jalankanBackfill();
        $this->jalankanBackfill();

        $this->assertSame(1, DB::table('project_po_basts')->where('project_po_number_id', $po->id)->count());
    }
}
