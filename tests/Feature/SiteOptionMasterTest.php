<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Site;
use App\Models\SiteProgram;
use App\Models\SiteSourcePower;
use App\Models\SiteType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Master Tipe Site / Source Power / Program Site (migrasi 2026_11_09_000200)
 * dan pemakaiannya di form Site: Tipe & Source Power opsional, Program wajib.
 */
class SiteOptionMasterTest extends TestCase
{
    use MakesTesysData;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->userWithRole('superadmin');
    }

    private function customer(): Customer
    {
        return Customer::create(['name' => 'PT BTS', 'initial' => 'BTS', 'status' => 'ACTIVE']);
    }

    public function test_migration_seeds_previous_constant_values(): void
    {
        $this->assertSame('Sekolah', SiteType::where('code', 'SEKOLAH')->value('name'));
        $this->assertSame('Hybrid', SiteSourcePower::where('code', 'HYBRID')->value('name'));
        $this->assertSame('Tower & Power', SiteProgram::where('code', 'TOWER_POWER')->value('name'));
    }

    public function test_index_pages_render_and_require_menu(): void
    {
        foreach (['/site-type', '/site-source-power', '/site-program'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        $user = $this->userWithRole('staff');
        $this->actingAs($user)->get('/site-type')->assertForbidden();
        $this->actingAs($user)->post('/site-type', ['name' => 'X'])->assertForbidden();
    }

    public function test_create_generates_unique_code_and_rename_keeps_code(): void
    {
        $this->actingAs($this->admin)->post('/site-type', ['name' => 'Puskesmas Desa'])->assertRedirect('/site-type');
        $type = SiteType::where('name', 'Puskesmas Desa')->firstOrFail();
        $this->assertSame('PUSKESMAS_DESA', $type->code);

        // Nama kembar ditolak.
        $this->actingAs($this->admin)->from('/site-type')->post('/site-type', ['name' => 'Puskesmas Desa'])
            ->assertSessionHasErrors('name');

        // Kode tidak ikut berubah saat nama diedit.
        $this->actingAs($this->admin)->put('/site-type/'.$type->id, ['name' => 'Puskesmas'])->assertRedirect('/site-type');
        $this->assertSame('PUSKESMAS_DESA', $type->fresh()->code);
        $this->assertSame('Puskesmas', $type->fresh()->name);

        // Nama baru yang menghasilkan kode sama mendapat akhiran.
        $this->actingAs($this->admin)->post('/site-type', ['name' => 'Puskesmas-Desa'])->assertRedirect('/site-type');
        $this->assertSame('PUSKESMAS_DESA_2', SiteType::where('name', 'Puskesmas-Desa')->value('code'));
    }

    public function test_option_in_use_cannot_be_deleted(): void
    {
        Site::create([
            'tid' => 'TID-1', 'sitename' => 'Site 1', 'type' => 'TOWER', 'source_power' => 'PLN',
            'program' => 'AKSES_INTERNET', 'status' => 'ACTIVE', 'customer_id' => $this->customer()->id,
        ]);

        $used = SiteSourcePower::where('code', 'PLN')->firstOrFail();
        $this->actingAs($this->admin)->delete('/site-source-power/'.$used->id)->assertSessionHas('error');
        $this->assertDatabaseHas('site_source_powers', ['code' => 'PLN']);

        $free = SiteSourcePower::where('code', 'SPS')->firstOrFail();
        $this->actingAs($this->admin)->delete('/site-source-power/'.$free->id)->assertSessionHas('success');
        $this->assertDatabaseMissing('site_source_powers', ['code' => 'SPS']);
    }

    public function test_site_type_and_source_power_are_optional_program_required(): void
    {
        $base = ['tid' => 'TID-1', 'sitename' => 'Site 1', 'customer_id' => $this->customer()->id, 'program' => 'AKSES_INTERNET'];

        $this->actingAs($this->admin)->post('/site', $base)->assertRedirect('/site');
        $this->assertDatabaseHas('sites', ['tid' => 'TID-1', 'type' => null, 'source_power' => null]);

        // Nilai di luar master ditolak; Program tetap wajib.
        $this->actingAs($this->admin)->from('/site')->post('/site', array_merge($base, [
            'tid' => 'TID-2', 'type' => 'BUKAN', 'source_power' => 'BUKAN', 'program' => '',
        ]))->assertSessionHasErrors(['type', 'source_power', 'program']);

        // Opsi baru dari master langsung bisa dipakai di form Site.
        $this->actingAs($this->admin)->post('/site-type', ['name' => 'Gudang']);
        $this->actingAs($this->admin)->post('/site', array_merge($base, ['tid' => 'TID-3', 'type' => 'GUDANG']))
            ->assertRedirect('/site');
        $this->assertDatabaseHas('sites', ['tid' => 'TID-3', 'type' => 'GUDANG']);

        $this->actingAs($this->admin)->get('/site/create')->assertOk()->assertSee('Gudang');
    }
}
