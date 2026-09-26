<?php

namespace Tests\Feature;

use App\Models\CashReceipt;
use App\Models\SourceFund;
use App\Support\InvoiceTimeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Timeline invoice sebagai data, bukan potongan HTML di dalam Blade.
 *
 * Sebelum dipindah ke App\Support\InvoiceTimeline, logika ini tinggal di dalam
 * view dan karena itu tidak bisa diuji tanpa merender halaman — padahal urutan
 * peristiwa dan penandaan "PAYMENT + SETTLE" (yang bergantung pada ada-tidaknya
 * PPh Pasal 4(2)) adalah logika bisnis.
 */
class InvoiceTimelineTest extends TestCase
{
    use RefreshDatabase, MakesTesysData;

    public function test_selalu_memuat_peristiwa_draft(): void
    {
        $invoice = $this->buatInvoice();

        $timeline = InvoiceTimeline::untuk($invoice);

        $this->assertCount(1, $timeline);
        $this->assertSame('DRAFT', $timeline->first()['badge']);
    }

    public function test_peristiwa_diurutkan_kronologis(): void
    {
        $invoice = $this->buatInvoice();

        // created_at ikut diatur: tanpa itu invoice "dibuat sekarang" tapi
        // "disubmit tiga hari lalu", dan DRAFT wajar tersortir paling akhir.
        $invoice->forceFill([
            'created_at'   => now()->subDays(4),
            'submitted_at' => now()->subDays(3),
            'verified_at'  => now()->subDays(2),
            'approved_at'  => now()->subDay(),
        ])->save();

        $badge = InvoiceTimeline::untuk($invoice->fresh())->pluck('badge')->all();

        $this->assertSame(['DRAFT', 'SUBMIT', 'VERIFY', 'APPROVE'], $badge);
    }

    /**
     * Cash receipt BER-PPh ditandai berbeda — itu pembeda yang dipakai Finance
     * saat menelusuri settlement pajak, jadi kalau penandanya salah, salah
     * bacanya bukan kosmetik.
     */
    public function test_pembayaran_dengan_pph_ditandai_payment_plus_settle(): void
    {
        $invoice = $this->buatInvoice();

        CashReceipt::create([
            'invoice_id'      => $invoice->id,
            'source_fund_id'  => $this->buatSourceFund()->id,
            'amount'          => 5_000_000,
            'withholding_tax' => 100_000,
            'received_date'   => now(),
            'input_by'        => $this->userWithRole('finance')->id,
        ]);

        $timeline = InvoiceTimeline::untuk($invoice->fresh());
        $bayar    = $timeline->firstWhere('badge', 'PAYMENT + SETTLE');

        $this->assertNotNull($bayar, 'Peristiwa pembayaran ber-PPh tidak ditemukan.');

        $teks = collect($bayar['lines'])->pluck('teks')->implode(' | ');
        $this->assertStringContainsString('5.000.000 diterima', $teks);
        $this->assertStringContainsString('PPh Ps.4(2) di-settle: 100.000', $teks);
    }

    public function test_pembayaran_tanpa_pph_ditandai_payment_saja(): void
    {
        $invoice = $this->buatInvoice();

        CashReceipt::create([
            'invoice_id'      => $invoice->id,
            'source_fund_id'  => $this->buatSourceFund()->id,
            'amount'          => 1_000_000,
            'withholding_tax' => 0,
            'received_date'   => now(),
            'input_by'        => $this->userWithRole('finance')->id,
        ]);

        $badge = InvoiceTimeline::untuk($invoice->fresh())->pluck('badge')->all();

        $this->assertContains('PAYMENT', $badge);
        $this->assertNotContains('PAYMENT + SETTLE', $badge);
    }

    /**
     * Tidak ada HTML yang keluar dari kelas ini. Kalau suatu saat ada yang
     * menyelipkannya kembali, view harus memakai {!! !!} lagi dan pemeriksaan
     * escaping Blade hilang bersamanya.
     */
    public function test_tidak_mengembalikan_html(): void
    {
        $invoice = $this->buatInvoice();

        CashReceipt::create([
            'invoice_id'      => $invoice->id,
            'source_fund_id'  => $this->buatSourceFund()->id,
            'amount'          => 1_000_000,
            'withholding_tax' => 0,
            'received_date'   => now(),
            'input_by'        => $this->userWithRole('finance')->id,
        ]);

        $semua = InvoiceTimeline::untuk($invoice->fresh())->toJson();

        $this->assertStringNotContainsString('<div', $semua);
        $this->assertStringNotContainsString('<a ', $semua);
    }

    private function buatInvoice()
    {
        $project  = $this->makeProject();
        $poNumber = $this->makePoNumber($project);

        return $this->makeInvoice($poNumber);
    }

    private function buatSourceFund(): SourceFund
    {
        return SourceFund::create([
            'name'   => 'PT Uji Bank',
            'status' => 'ACTIVE',
        ]);
    }
}
