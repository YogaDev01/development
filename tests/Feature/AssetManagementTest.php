<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetLocation;
use App\Models\AssetMaintenance;
use App\Models\AssetMovement;
use App\Models\AssetOpname;
use App\Models\AssetPurchaseRequest;
use App\Models\Employee;
use App\Models\User;
use App\Support\AssetDepreciation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Modul Asset Management: akses (menu + ASSET_MANAGE), penomoran,
 * transisi status aset lewat serah terima/transfer/check-out/maintenance/
 * disposal, opname, penerimaan PR, dan rumus penyusutan.
 */
class AssetManagementTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    private AssetCategory $cat;

    private AssetLocation $loc;

    private AssetLocation $loc2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cat  = AssetCategory::create(['code' => 'ITE', 'name' => 'IT Equipment', 'life_years' => 4, 'depreciation_method' => 'STRAIGHT_LINE', 'residual_percent' => 10, 'status' => 'ACTIVE']);
        $this->loc  = AssetLocation::create(['code' => 'HO', 'name' => 'Head Office', 'type' => 'OFFICE', 'status' => 'ACTIVE']);
        $this->loc2 = AssetLocation::create(['code' => 'WH', 'name' => 'Gudang', 'type' => 'WAREHOUSE', 'status' => 'ACTIVE']);
    }

    private function staff(): User
    {
        $u = $this->userWithRole('finance');
        $this->giveMenuAccess($u->role, 'asset-management');

        return $u;
    }

    private function admin(): User
    {
        $u = $this->userWithRole('asset_admin');
        $this->giveMenuAccess($u->role, 'asset-management');

        return $u;
    }

    private function asset(array $attr = []): Asset
    {
        static $n = 0;
        $n++;

        return Asset::create(array_merge([
            'code' => 'AST-ITE-2024-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT), 'name' => 'Laptop ' . $n,
            'asset_category_id' => $this->cat->id, 'asset_location_id' => $this->loc->id,
            'acquisition_date' => '2024-01-15', 'cost' => 12_000_000, 'residual_value' => 1_200_000,
            'life_years' => 4, 'depreciation_method' => 'STRAIGHT_LINE', 'condition' => 'GOOD', 'status' => 'AVAILABLE',
        ], $attr));
    }

    private function employee(): Employee
    {
        return Employee::create(['name' => 'Karyawan ' . uniqid(), 'status' => 'ACTIVE']);
    }

    public function test_tanpa_menu_asset_ditolak(): void
    {
        $this->actingAs($this->userWithRole('finance'))->get('/asset')->assertForbidden();
    }

    public function test_semua_halaman_asset_dapat_dibuka(): void
    {
        $a = $this->asset();
        $u = $this->staff();
        foreach (['/asset', '/asset/list', '/asset/create', "/asset/list/{$a->id}", "/asset/list/{$a->id}/edit",
            '/asset/movements', '/asset/movements?tab=transfer', '/asset/movements?tab=checkout', '/asset/movements?tab=disposal',
            '/asset/maintenance', '/asset/maintenance?tab=schedule', '/asset/inspection', '/asset/opname',
            '/asset/procurement', '/asset/procurement?tab=receiving', '/asset/procurement?tab=history',
            '/asset/reports', '/asset/reports?type=depreciation', '/asset/reports?type=movement',
            '/asset/reports?type=maintenance', '/asset/reports?type=condition', '/asset/settings', '/asset/settings?tab=locations'] as $url) {
            $this->actingAs($u)->get($url)->assertOk();
        }
    }

    public function test_registrasi_memberi_kode_berurutan_per_kategori_dan_tahun(): void
    {
        $u = $this->staff();
        $payload = [
            'name' => 'Laptop Baru', 'asset_category_id' => $this->cat->id, 'asset_location_id' => $this->loc->id,
            'acquisition_date' => '2025-03-01', 'acquisition_type' => 'PURCHASE', 'cost' => 10_000_000,
            'residual_value' => 1_000_000, 'life_years' => 4, 'depreciation_method' => 'STRAIGHT_LINE',
            'condition' => 'EXCELLENT', 'status' => 'AVAILABLE', 'spec_keys' => ['RAM'], 'spec_values' => ['16 GB'],
        ];
        $this->actingAs($u)->post('/asset/list', $payload)->assertRedirect();
        $this->actingAs($u)->post('/asset/list', $payload)->assertRedirect();

        $this->assertSame(['AST-ITE-2025-0001', 'AST-ITE-2025-0002'], Asset::orderBy('id')->pluck('code')->all());
        $this->assertSame(['RAM' => '16 GB'], Asset::first()->specs);
    }

    public function test_residu_tidak_boleh_melebihi_harga(): void
    {
        $this->actingAs($this->staff())->post('/asset/list', [
            'name' => 'X', 'asset_category_id' => $this->cat->id, 'asset_location_id' => $this->loc->id,
            'acquisition_date' => '2025-03-01', 'acquisition_type' => 'PURCHASE', 'cost' => 100, 'residual_value' => 200,
            'life_years' => 4, 'depreciation_method' => 'STRAIGHT_LINE', 'condition' => 'GOOD', 'status' => 'AVAILABLE',
        ])->assertSessionHasErrors('residual_value');
    }

    public function test_serah_terima_dan_pengembalian_mengubah_status_aset(): void
    {
        $a = $this->asset();
        $e = $this->employee();
        $u = $this->staff();

        $this->actingAs($u)->post('/asset/movements/handover', [
            'asset_id' => $a->id, 'to_employee_id' => $e->id, 'movement_date' => now()->toDateString(),
        ])->assertRedirect();
        $a->refresh();
        $this->assertSame('ASSIGNED', $a->status);
        $this->assertSame($e->id, $a->employee_id);
        $this->assertSame('COMPLETED', AssetMovement::first()->status);
        $this->assertStringStartsWith('BAST-AST/' . now()->year . '/', AssetMovement::first()->number);

        // Aset yang sudah dipegang tidak bisa diserahkan lagi.
        $this->actingAs($u)->post('/asset/movements/handover', [
            'asset_id' => $a->id, 'to_employee_id' => $e->id, 'movement_date' => now()->toDateString(),
        ])->assertSessionHas('error');

        $this->actingAs($u)->post('/asset/movements/return', [
            'asset_id' => $a->id, 'to_location_id' => $this->loc2->id, 'movement_date' => now()->toDateString(), 'condition' => 'FAIR',
        ])->assertRedirect();
        $a->refresh();
        $this->assertSame(['AVAILABLE', null, $this->loc2->id, 'FAIR'], [$a->status, $a->employee_id, $a->asset_location_id, $a->condition]);

        $this->actingAs($u)->get('/asset/movements/' . AssetMovement::first()->id . '/document')->assertOk()->assertSee('BERITA ACARA SERAH TERIMA ASET');
    }

    public function test_transfer_butuh_persetujuan_asset_admin(): void
    {
        $a = $this->asset();
        $staff = $this->staff();

        $this->actingAs($staff)->post('/asset/movements/transfer', [
            'asset_id' => $a->id, 'to_location_id' => $this->loc2->id, 'movement_date' => now()->toDateString(), 'reason' => 'Relokasi',
        ])->assertRedirect();
        $m = AssetMovement::first();
        $this->assertSame('PENDING', $m->status);
        $this->assertSame($this->loc->id, $a->fresh()->asset_location_id);

        // Pengajuan kedua untuk aset yang sama ditolak selama masih PENDING.
        $this->actingAs($staff)->post('/asset/movements/disposal', [
            'asset_id' => $a->id, 'movement_date' => now()->toDateString(), 'reason' => 'Rusak', 'disposal_method' => 'SCRAPPED',
        ])->assertSessionHas('error');

        $this->actingAs($staff)->post("/asset/movements/{$m->id}/approve")->assertForbidden();

        $this->actingAs($this->admin())->post("/asset/movements/{$m->id}/approve")->assertRedirect();
        $this->assertSame('APPROVED', $m->fresh()->status);
        $this->assertSame($this->loc2->id, $a->fresh()->asset_location_id);
    }

    public function test_disposal_disetujui_membuat_aset_disposed_dan_nilai_buku_nol(): void
    {
        $a = $this->asset();
        $this->actingAs($this->staff())->post('/asset/movements/disposal', [
            'asset_id' => $a->id, 'movement_date' => now()->toDateString(), 'reason' => 'Rusak total',
            'disposal_method' => 'SOLD', 'disposal_value' => 500_000,
        ])->assertRedirect();
        $m = AssetMovement::first();

        $this->actingAs($this->admin())->post("/asset/movements/{$m->id}/approve")->assertRedirect();
        $a->refresh();
        $this->assertSame('DISPOSED', $a->status);
        $this->assertSame(0, $a->bookValue());
        $this->assertSame(500_000, $m->fresh()->disposal_value);
    }

    public function test_disposal_dijual_wajib_nilai(): void
    {
        $a = $this->asset();
        $this->actingAs($this->staff())->post('/asset/movements/disposal', [
            'asset_id' => $a->id, 'movement_date' => now()->toDateString(), 'reason' => 'x', 'disposal_method' => 'SOLD',
        ])->assertSessionHasErrors('disposal_value');
    }

    public function test_tolak_transfer(): void
    {
        $a = $this->asset();
        $this->actingAs($this->staff())->post('/asset/movements/transfer', [
            'asset_id' => $a->id, 'to_location_id' => $this->loc2->id, 'movement_date' => now()->toDateString(), 'reason' => 'x',
        ]);
        $m = AssetMovement::first();
        $this->actingAs($this->admin())->post("/asset/movements/{$m->id}/reject", ['reject_reason' => 'Tidak perlu'])->assertRedirect();
        $this->assertSame(['REJECTED', 'Tidak perlu'], [$m->fresh()->status, $m->fresh()->reject_reason]);
        $this->assertSame($this->loc->id, $a->fresh()->asset_location_id);
    }

    public function test_checkout_lalu_checkin_mengembalikan_status_awal(): void
    {
        $a = $this->asset(['status' => 'IN_USE']);
        $u = $this->staff();
        $this->actingAs($u)->post('/asset/movements/checkout', [
            'asset_id' => $a->id, 'to_employee_id' => $this->employee()->id, 'movement_date' => now()->toDateString(),
            'expected_return_date' => now()->addDays(3)->toDateString(),
        ])->assertRedirect();
        $this->assertSame('CHECKED_OUT', $a->fresh()->status);

        $m = AssetMovement::first();
        $this->actingAs($u)->post("/asset/movements/{$m->id}/checkin", [
            'returned_date' => now()->toDateString(), 'condition' => 'POOR',
        ])->assertRedirect();
        $this->assertSame(['IN_USE', 'POOR'], [$a->fresh()->status, $a->fresh()->condition]);
        $this->assertSame('RETURNED', $m->fresh()->status);
    }

    public function test_maintenance_menahan_lalu_mengembalikan_status_aset_dan_memajukan_jadwal(): void
    {
        $a = $this->asset(['status' => 'IN_USE']);
        $u = $this->staff();
        $this->actingAs($u)->post('/asset/maintenance/schedule', [
            'asset_id' => $a->id, 'title' => 'Service rutin', 'maintenance_type' => 'PREVENTIVE',
            'interval_days' => 30, 'next_due_date' => now()->toDateString(),
        ])->assertRedirect();
        $jadwal = $a->schedules()->first();

        $this->actingAs($u)->post('/asset/maintenance', [
            'asset_id' => $a->id, 'asset_maintenance_schedule_id' => $jadwal->id, 'maintenance_type' => 'PREVENTIVE',
            'problem' => 'Service rutin', 'start_date' => now()->toDateString(), 'estimated_cost' => 250_000,
        ])->assertRedirect();
        $this->assertSame('MAINTENANCE', $a->fresh()->status);

        // Aset dalam maintenance tidak bisa di-check-out.
        $this->actingAs($u)->post('/asset/movements/checkout', [
            'asset_id' => $a->id, 'to_employee_id' => $this->employee()->id, 'movement_date' => now()->toDateString(),
            'expected_return_date' => now()->toDateString(),
        ])->assertSessionHas('error');

        $mr = AssetMaintenance::first();
        $this->actingAs($u)->put("/asset/maintenance/{$mr->id}", [
            'status' => 'COMPLETED', 'end_date' => now()->toDateString(), 'actual_cost' => 300_000, 'condition' => 'EXCELLENT',
        ])->assertRedirect();

        $this->assertSame(['IN_USE', 'EXCELLENT'], [$a->fresh()->status, $a->fresh()->condition]);
        $this->assertSame(300_000, $mr->fresh()->actual_cost);
        $this->assertSame(now()->addDays(30)->toDateString(), $jadwal->fresh()->next_due_date->toDateString());
    }

    public function test_opname_memotret_aset_lokasi_dan_hanya_admin_yang_menyelesaikan(): void
    {
        $a = $this->asset();
        $this->asset(['asset_location_id' => $this->loc2->id]);
        $this->asset(['status' => 'DISPOSED']);
        $staff = $this->staff();

        $this->actingAs($staff)->post('/asset/opname', ['asset_location_id' => $this->loc->id, 'opname_date' => now()->toDateString()])->assertRedirect();
        $o = AssetOpname::first();
        $this->assertSame([$a->id], $o->items()->pluck('asset_id')->all());

        $item = $o->items()->first();
        $this->actingAs($staff)->put("/asset/opname/{$o->id}", ['items' => [$item->id => ['result' => 'MATCHED', 'condition' => 'FAIR']]])->assertRedirect();
        $this->actingAs($staff)->post("/asset/opname/{$o->id}/complete")->assertForbidden();

        $this->actingAs($this->admin())->post("/asset/opname/{$o->id}/complete")->assertRedirect();
        $this->assertSame('COMPLETED', $o->fresh()->status);
        $this->assertSame('FAIR', $a->fresh()->condition);
    }

    public function test_penerimaan_pr_mendaftarkan_aset_per_unit(): void
    {
        $staff = $this->staff();
        $this->actingAs($staff)->post('/asset/procurement/pr', [
            'item_name' => 'Monitor 24', 'asset_category_id' => $this->cat->id, 'qty' => 3,
            'estimated_unit_cost' => 3_000_000, 'justification' => 'Tim baru',
        ])->assertRedirect();
        $pr = AssetPurchaseRequest::first();

        // Belum disetujui -> penerimaan ditolak.
        $terima = fn (int $qty) => $this->actingAs($staff)->post('/asset/procurement/receiving', [
            'asset_purchase_request_id' => $pr->id, 'item_name' => 'Monitor 24', 'asset_category_id' => $this->cat->id,
            'asset_location_id' => $this->loc->id, 'received_date' => now()->toDateString(), 'qty' => $qty,
            'unit_cost' => 3_100_000, 'serial_numbers' => "SN1\nSN2",
        ]);
        $terima(2)->assertSessionHas('error');

        $this->actingAs($this->admin())->post("/asset/procurement/pr/{$pr->id}/approve")->assertRedirect();
        $terima(2)->assertRedirect();
        $terima(2)->assertSessionHas('error'); // melebihi sisa (1)
        $this->assertSame('APPROVED', $pr->fresh()->status);
        $terima(1)->assertRedirect();

        $this->assertSame('RECEIVED', $pr->fresh()->status);
        $this->assertSame(3, Asset::where('name', 'Monitor 24')->count());
        $first = Asset::orderBy('id')->first();
        $this->assertSame(['SN1', 310_000, 'AVAILABLE'], [$first->serial_number, $first->residual_value, $first->status]);
    }

    public function test_hanya_admin_boleh_ubah_kategori(): void
    {
        $this->actingAs($this->staff())->post('/asset/settings/category', [
            'code' => 'VEH', 'name' => 'Kendaraan', 'life_years' => 8, 'depreciation_method' => 'STRAIGHT_LINE', 'residual_percent' => 10, 'status' => 'ACTIVE',
        ])->assertForbidden();

        $this->actingAs($this->admin())->post('/asset/settings/category', [
            'code' => 'veh', 'name' => 'Kendaraan', 'life_years' => 8, 'depreciation_method' => 'STRAIGHT_LINE', 'residual_percent' => 10, 'status' => 'ACTIVE',
        ])->assertRedirect();
        $this->assertTrue(AssetCategory::where('code', 'VEH')->exists());
    }

    public function test_aset_dengan_riwayat_tidak_bisa_dihapus(): void
    {
        $a = $this->asset();
        $this->actingAs($this->staff())->post('/asset/movements/handover', [
            'asset_id' => $a->id, 'to_employee_id' => $this->employee()->id, 'movement_date' => now()->toDateString(),
        ]);
        $this->actingAs($this->admin())->delete("/asset/list/{$a->id}", ['delete_reason' => 'x'])->assertSessionHas('error');
        $this->assertNull($a->fresh()->deleted_at);

        $b = $this->asset();
        $this->actingAs($this->staff())->delete("/asset/list/{$b->id}", ['delete_reason' => 'x'])->assertForbidden();
        $this->actingAs($this->admin())->delete("/asset/list/{$b->id}", ['delete_reason' => 'Duplikat'])->assertRedirect();
        $this->assertSoftDeleted('assets', ['id' => $b->id, 'delete_reason' => 'Duplikat']);
    }

    public function test_penyusutan_garis_lurus_dan_saldo_menurun(): void
    {
        $sl = new Asset(['acquisition_date' => '2024-01-15', 'cost' => 12_000_000, 'residual_value' => 1_200_000, 'life_years' => 4, 'depreciation_method' => 'STRAIGHT_LINE']);
        // 12 bulan x (10.800.000 / 48) = 2.700.000
        $this->assertSame(9_300_000, AssetDepreciation::bookValue($sl, Carbon::parse('2025-01-20')));
        $this->assertSame(1_200_000, AssetDepreciation::bookValue($sl, Carbon::parse('2030-01-01')));
        $this->assertSame(12_000_000, AssetDepreciation::bookValue($sl, Carbon::parse('2024-01-31')));

        $ddb = new Asset(['acquisition_date' => '2024-01-01', 'cost' => 10_000_000, 'residual_value' => 1_000_000, 'life_years' => 4, 'depreciation_method' => 'DECLINING_BALANCE']);
        // Bulan 1: 10.000.000 - round(10.000.000 x 2/48) = 9.583.333
        $this->assertSame(9_583_333, AssetDepreciation::bookValue($ddb, Carbon::parse('2024-02-01')));
        $this->assertGreaterThanOrEqual(1_000_000, AssetDepreciation::bookValue($ddb, Carbon::parse('2040-01-01')));

        $schedule = AssetDepreciation::schedule($sl);
        $this->assertCount(4, $schedule);
        $this->assertSame(1_200_000, end($schedule)['book_value']);
        $this->assertSame(10_800_000, array_sum(array_column($schedule, 'depreciation')));
    }
}
