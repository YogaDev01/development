<?php

namespace Tests\Feature;

use App\Models\CashReceipt;
use App\Models\CashReceiptHeader;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Index Cash Receipt: kolom Tagihan/PPh/Diterima dari detail, filter tanggal server-side.
 * Ditulis, BELUM dijalankan (gerbang CLAUDE.md 4.3).
 */
class CashReceiptIndexFilterTest extends TestCase
{
    use MakesTesysData, RefreshDatabase;

    private User $user;

    private function header(string $tanggal, string $status = 'ALLOCATED'): CashReceiptHeader
    {
        $this->user ??= $this->userWithRole('superadmin');
        static $n = 0;
        $n++;

        return CashReceiptHeader::create([
            'code' => 'CR/UJI/' . $n, 'received_date' => $tanggal, 'customer_id' => $this->makeCustomer()->id,
            'total_amount' => 999_000_000, 'status' => $status,
        ]);
    }

    private function invoice(): Invoice
    {
        return $this->makeInvoice($this->makePoNumber($this->makeProject()), [
            'billed_amount' => 100_000_000, 'tax_amount' => 11_000_000,
        ]);
    }

    private function detail(CashReceiptHeader $h, Invoice $inv, int $amount, int $pph = 0): CashReceipt
    {
        return CashReceipt::create([
            'cash_receipt_header_id' => $h->id, 'invoice_id' => $inv->id,
            'received_date' => $h->received_date, 'amount' => $amount, 'withholding_tax' => $pph,
        ]);
    }

    private function ambil(string $query = '')
    {
        return $this->actingAs($this->user)->get('/cash-receipt' . $query);
    }

    public function test_kolom_uang_dari_detail_bukan_total_amount(): void
    {
        $h = $this->header(now()->startOfMonth()->toDateString());
        $this->detail($h, $this->invoice(), 90_000_000, 3_000_000);

        $res = $this->ambil()->assertOk();
        $row = $res->viewData('headers')->first();
        $this->assertSame(90_000_000, $row->kolom_diterima);
        $this->assertSame(3_000_000, $row->kolom_pph);
        $this->assertSame(111_000_000, $row->kolom_tagihan);
        $this->assertSame(90_000_000, $res->viewData('summary')['total']);
    }

    public function test_tagihan_menghitung_invoice_unik_per_header(): void
    {
        $h = $this->header(now()->startOfMonth()->toDateString());
        $inv = $this->invoice();
        $this->detail($h, $inv, 10_000_000);
        $this->detail($h, $inv, 20_000_000);

        $row = $this->ambil()->viewData('headers')->first();
        $this->assertSame(111_000_000, $row->kolom_tagihan);
        $this->assertSame(30_000_000, $row->kolom_diterima);
    }

    public function test_filter_default_bulan_berjalan_inklusif(): void
    {
        $awal = now()->startOfMonth()->toDateString();
        $akhir = now()->endOfMonth()->toDateString();
        $dalamAwal = $this->header($awal);
        $dalamAkhir = $this->header($akhir);
        $luar = $this->header(now()->subMonth()->startOfMonth()->toDateString());

        $ids = $this->ambil()->viewData('headers')->pluck('id');
        $this->assertTrue($ids->contains($dalamAwal->id));
        $this->assertTrue($ids->contains($dalamAkhir->id));
        $this->assertFalse($ids->contains($luar->id));
    }

    public function test_date_from_date_to_dan_range(): void
    {
        $lama = $this->header('2026-01-15');
        $this->header(now()->toDateString());

        $res = $this->ambil('?date_from=2026-01-01&date_to=2026-01-31&range=custom');
        $this->assertSame([$lama->id], $res->viewData('headers')->pluck('id')->all());
        $this->assertSame('custom', $res->viewData('range'));
        $this->assertSame('2026-01-01', $res->viewData('dateFrom'));
    }

    public function test_tanggal_tidak_valid_atau_terbalik_kembali_ke_default(): void
    {
        $default = now()->startOfMonth()->toDateString();

        foreach (['?date_from=abc&date_to=2026-01-31', '?date_from=2026-02-10&date_to=2026-02-01'] as $q) {
            $this->assertSame($default, $this->ambil($q)->viewData('dateFrom'));
        }
    }

    public function test_header_batal_uang_nol_dan_kartu_mengikuti_filter(): void
    {
        $batal = $this->header(now()->startOfMonth()->toDateString(), 'CANCELLED');
        $this->header('2020-01-01');

        $res = $this->ambil();
        $row = $res->viewData('headers')->firstWhere('id', $batal->id);
        $this->assertSame(0, $row->kolom_diterima);
        $this->assertSame(0, $row->kolom_pph);
        $this->assertSame(0, $row->kolom_tagihan);
        $this->assertSame(1, $res->viewData('summary')['count']);
        $this->assertSame(0, $res->viewData('summary')['total']);
    }

    public function test_header_batal_belum_teralokasi_nol(): void
    {
        $batal = $this->header(now()->startOfMonth()->toDateString(), 'CANCELLED');
        $this->assertSame(0, $batal->unallocatedAmount());

        $aktif = $this->header(now()->startOfMonth()->toDateString(), 'DRAFT');
        $this->assertSame(999_000_000, $aktif->unallocatedAmount());

        $this->assertSame(999_000_000, $this->ambil()->viewData('summary')['unallocated']);
    }

    public function test_range_tidak_valid_kembali_ke_default_dan_range_cepat_memakai_jam_server(): void
    {
        $res = $this->ambil('?date_from=abc&date_to=2026-01-31&range=custom');
        $this->assertSame('', $res->viewData('range'));
        $this->assertSame(now()->startOfMonth()->toDateString(), $res->viewData('dateFrom'));

        $res = $this->ambil('?date_from=2000-01-01&date_to=2000-01-02&range=7d');
        $this->assertSame('7d', $res->viewData('range'));
        $this->assertSame(now()->subDays(7)->toDateString(), $res->viewData('dateFrom'));
        $this->assertSame(now()->toDateString(), $res->viewData('dateTo'));
    }
}
