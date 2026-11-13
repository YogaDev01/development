<?php

namespace Tests\Feature;

use App\Models\Closing;
use App\Services\ClosingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * drift() ikut membandingkan nominal (keputusan pemilik 2026-11-13): perubahan nominal pada
 * dokumen yang sudah direkap hanya ditampilkan sebagai selisih, tidak diblokir.
 * Ditulis 2026-11-13; belum dijalankan — lihat CLAUDE.md §4.3.
 */
class PeriodLockDriftTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function rekap(): Closing
    {
        $svc = app(ClosingService::class);
        $closing = Closing::create([
            'code' => 'CLS/2026/10', 'period' => '2026-10', 'start_date' => '2026-10-01',
            'end_date' => '2026-10-31', 'status' => 'CLOSED',
        ]);
        foreach ($svc->collect('2026-10-01', '2026-10-31') as $row) {
            $closing->details()->create($row);
        }

        return $closing->load('details');
    }

    public function test_tanpa_perubahan_tidak_ada_selisih(): void
    {
        $this->makeInvoice($this->makePoNumber($this->makeProject()), ['status' => 'SENT', 'invoice_date' => '2026-10-15']);
        $closing = $this->rekap();

        $drift = app(ClosingService::class)->drift($closing);

        $this->assertFalse($drift['has_difference']);
        $this->assertTrue($drift['changed']->isEmpty());
    }

    public function test_perubahan_nominal_dokumen_yang_sudah_direkap_terdeteksi(): void
    {
        $invoice = $this->makeInvoice($this->makePoNumber($this->makeProject()), ['status' => 'SENT', 'invoice_date' => '2026-10-15']);
        $closing = $this->rekap();

        // Mis. PPh aktual dari Cash Receipt di periode terbuka menyinkron ulang total invoice.
        $invoice->forceFill(['captured_total' => 105_000_000])->save();

        $drift = app(ClosingService::class)->drift($closing->fresh('details'));

        $this->assertTrue($drift['has_difference']);
        $this->assertTrue($drift['missing']->isEmpty());
        $this->assertTrue($drift['stale']->isEmpty());
        $baris = $drift['changed']->firstWhere('source_module', 'INVOICE');
        $this->assertSame(111_000_000, $baris['recorded_amount']);
        $this->assertSame(105_000_000, $baris['amount']);
    }
}
