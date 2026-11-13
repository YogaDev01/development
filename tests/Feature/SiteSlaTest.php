<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Site;
use App\Models\SiteSla;
use App\Models\SiteSlaPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * SLA Site: entri per customer + periode, rincian per site, rekap di Summary.
 * Dijalankan hanya sesuai prosedur §4.3 CLAUDE.md.
 */
class SiteSlaTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private function customer(string $initial = 'MSG'): Customer
    {
        return Customer::create(['name' => 'Customer ' . $initial, 'initial' => $initial, 'status' => 'ACTIVE']);
    }

    private function site(Customer $customer, string $tid): Site
    {
        return Site::create([
            'tid' => $tid, 'sitename' => 'Lokasi ' . $tid, 'type' => 'TOWER', 'source_power' => 'PLN',
            'program' => 'AKSES_INTERNET', 'status' => 'ACTIVE', 'customer_id' => $customer->id,
        ]);
    }

    private function user()
    {
        $this->giveMenuAccess($this->role('staff'), 'site-sla');

        return $this->userWithRole('staff');
    }

    public function test_tanpa_menu_site_sla_ditolak(): void
    {
        $this->actingAs($this->userWithRole('staff'))->get(route('site-sla.index'))->assertForbidden();
    }

    public function test_customer_wajib_dan_periode_unik_per_customer(): void
    {
        $user = $this->user();
        $c = $this->customer();

        $this->actingAs($user)->post(route('site-sla.store'), ['period' => '2026-01'])->assertSessionHasErrors('customer_id');

        $this->actingAs($user)->post(route('site-sla.store'), ['customer_id' => $c->id, 'period' => '2026-01'])->assertRedirect();
        $this->actingAs($user)->post(route('site-sla.store'), ['customer_id' => $c->id, 'period' => '2026-01'])->assertRedirect();
        $this->assertSame(1, SiteSlaPeriod::count());
    }

    public function test_simpan_rincian_dan_index_menampilkan_qty_dan_average(): void
    {
        $user = $this->user();
        $c = $this->customer();
        $a = $this->site($c, 'AM0001');
        $b = $this->site($c, 'AM0002');
        $p = SiteSlaPeriod::create(['customer_id' => $c->id, 'year' => 2026, 'month' => 1]);

        $this->actingAs($user)->get(route('site-sla.show', $p))->assertOk()->assertSee('AM0001')->assertSee('AM0002');

        $this->actingAs($user)->put(route('site-sla.update', $p), [
            'payload' => json_encode([$a->id => '100', $b->id => '96.7']),
        ])->assertRedirect();
        $this->assertSame(2, $p->details()->count());

        $this->actingAs($user)->get(route('site-sla.index', ['year' => 2026]))
            ->assertOk()->assertSee('Jan 2026')->assertSee('98,35%');

        // Dikosongkan = dihapus.
        $this->actingAs($user)->put(route('site-sla.update', $p), [
            'payload' => json_encode([$a->id => '', $b->id => '96.7']),
        ])->assertRedirect();
        $this->assertSame([$b->id], $p->details()->pluck('site_id')->all());
    }

    public function test_site_customer_lain_dan_nilai_di_luar_0_100_ditolak(): void
    {
        $user = $this->user();
        $c = $this->customer();
        $other = $this->site($this->customer('ZZZ'), 'X0001');
        $own = $this->site($c, 'AM0001');
        $p = SiteSlaPeriod::create(['customer_id' => $c->id, 'year' => 2026, 'month' => 1]);

        $this->actingAs($user)->put(route('site-sla.update', $p), ['payload' => json_encode([$other->id => '100'])])->assertSessionHas('error');
        $this->actingAs($user)->put(route('site-sla.update', $p), ['payload' => json_encode([$own->id => '101'])])->assertSessionHas('error');
        $this->assertSame(0, SiteSla::count());
    }

    public function test_summary_menampilkan_rekap_bulan_tanpa_tahun(): void
    {
        $this->giveMenuAccess($this->role('staff'), 'operations-overview');
        $c = $this->customer();
        $s = $this->site($c, 'AM0001');
        foreach ([1 => 100, 2 => 96.7] as $m => $v) {
            $p = SiteSlaPeriod::create(['customer_id' => $c->id, 'year' => 2026, 'month' => $m]);
            SiteSla::create(['site_sla_period_id' => $p->id, 'site_id' => $s->id, 'sla_percent' => $v]);
        }

        $this->actingAs($this->userWithRole('staff'))->get(route('operations.summary', ['sla_year' => 2026]))
            ->assertOk()->assertSee('AM0001')->assertSee('96,7')->assertSee('98,35')
            ->assertSee('>Jan<', false)->assertDontSee('Jan-26');
    }

    public function test_template_excel_bisa_diunduh(): void
    {
        $user = $this->user();
        $c = $this->customer();
        $this->site($c, 'AM0001');
        $p = SiteSlaPeriod::create(['customer_id' => $c->id, 'year' => 2026, 'month' => 10]);

        $res = $this->actingAs($user)->get(route('site-sla.template', $p));
        $res->assertOk();
        $this->assertStringContainsString('spreadsheetml', $res->headers->get('Content-Type'));
    }

    public function test_import_excel_mengembalikan_nilai_tanpa_menyimpan(): void
    {
        $user = $this->user();
        $c = $this->customer();
        $a = $this->site($c, 'AM0001');
        $this->site($this->customer('ZZZ'), 'X0001');
        $p = SiteSlaPeriod::create(['customer_id' => $c->id, 'year' => 2026, 'month' => 10]);

        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('SLA Site');
        $sheet->fromArray([
            ['Site ID', 'Nama Lokasi', 'Provinsi', 'Kabupaten/Kota', 'SLA (%)'],
            ['am0001', '', '', '', 98.5],
            ['X0001', '', '', '', 100],
            ['AM0001', '', '', '', 150],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'sla') . '.xlsx';
        (new Xlsx($book))->save($path);

        $res = $this->actingAs($user)->post(route('site-sla.import', $p), [
            'file' => new UploadedFile($path, 'sla.xlsx', null, null, true),
        ])->assertOk();

        $this->assertSame([['site_id' => $a->id, 'tid' => 'AM0001', 'value' => 98.5]], $res->json('rows'));
        $this->assertCount(2, $res->json('errors'));
        $this->assertSame(0, SiteSla::count());
        @unlink($path);
    }
}
