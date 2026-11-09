<?php

namespace Tests\Feature;

use App\Http\Controllers\SiteController;
use App\Models\Accessory;
use App\Models\CategoryItem;
use App\Models\Customer;
use App\Models\Homebase;
use App\Models\HomebaseDetail;
use App\Models\Kota;
use App\Models\MasterMaterial;
use App\Models\MasterPic;
use App\Models\Network;
use App\Models\Provinsi;
use App\Models\Pulau;
use App\Models\Region;
use App\Models\Site;
use App\Models\SiteAccessory;
use App\Models\SiteKeeper;
use App\Models\SiteMaterial;
use App\Models\SitePic;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Master Data Site & Material: category item, material, accessories, network,
 * site (+ material/accessories), region, homebase (+ PIC), wilayah.
 */
class SiteMasterDataTest extends TestCase
{
    use MakesTesysData;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-15 10:00:00');
        $this->admin = $this->userWithRole('superadmin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function category(string $type = 'MATERIAL'): CategoryItem
    {
        static $n = 0;
        $n++;

        return CategoryItem::create(['type' => $type, 'category_name' => 'Kategori '.$n, 'merk' => 'Merk']);
    }

    private function customerBts(): Customer
    {
        return Customer::create(['name' => 'PT BTS', 'initial' => 'BTS', 'status' => 'ACTIVE']);
    }

    /**
     * Teknisi yang sah untuk site customer $customer: akun ber-role
     * technician yang menjadi anggota ACTIVE team yang melayani customer itu.
     * Tidak perlu Master PIC (sites.technician_user_id, 9 Nov 2026).
     */
    private function technicianFor(Customer $customer, string $name): User
    {
        $user = $this->userWithRole('technician', ['name' => $name]);
        $team = Team::create(['code' => 'TEAM-'.$user->id, 'name' => 'Team '.$name, 'customer_id' => $customer->id, 'status' => 'ACTIVE']);
        $team->customers()->attach($customer->id);
        TeamMember::create(['team_id' => $team->id, 'user_id' => $user->id, 'level' => 1, 'status' => 'ACTIVE']);

        return $user;
    }

    private function site(Customer $customer, array $attr = []): Site
    {
        static $n = 0;
        $n++;

        return Site::create(array_merge([
            'tid' => 'TID'.$n, 'sitename' => 'Site '.$n, 'type' => 'TOWER',
            'source_power' => 'PLN', 'program' => 'AKSES_INTERNET', 'status' => 'ACTIVE',
            'customer_id' => $customer->id,
        ], $attr));
    }

    private function material(Customer $customer): MasterMaterial
    {
        $m = new MasterMaterial([
            'customer_id' => $customer->id, 'category_id' => $this->category()->id,
            'serial_number' => 'SN-'.uniqid(), 'status' => 'WAREHOUSE',
        ]);
        $m->product_code = MasterMaterial::generateCode($customer);
        $m->save();

        return $m;
    }

    private function accessory(Customer $customer): Accessory
    {
        $a = new Accessory([
            'accessories_name' => 'Kabel', 'customer_id' => $customer->id,
            'category_id' => $this->category('ACCESSORIES')->id, 'status' => 'WAREHOUSE',
        ]);
        $a->accessories_code = Accessory::generateCode($customer);
        $a->save();

        return $a;
    }

    /* ------------------------------------------------------------ akses */

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/site')->assertRedirect();
        $this->post('/category-item', [])->assertRedirect();
        $this->assertDatabaseCount('category_item', 0);
    }

    public function test_role_without_menu_cannot_read_or_write_via_direct_url(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->get('/site')->assertForbidden();
        $this->actingAs($staff)->post('/category-item', [
            'type' => 'MATERIAL', 'category_name' => 'X', 'merk' => 'Y',
        ])->assertForbidden();
        $this->actingAs($staff)->get('/wilayah/provinsi/options?parent_id=1')->assertForbidden();

        $this->assertDatabaseCount('category_item', 0);
    }

    public function test_role_with_menu_can_access_only_that_module(): void
    {
        $role = $this->role('logistik');
        $this->giveMenuAccess($role, 'category-item');
        $user = $this->userWithRole('logistik');

        $this->actingAs($user)->get('/category-item')->assertOk();
        $this->actingAs($user)->get('/site')->assertForbidden();
    }

    public function test_all_index_pages_render(): void
    {
        $customer = $this->customerBts();
        $this->site($customer, ['sitename' => '<script>alert(1)</script>']);

        foreach (['/category-item', '/master-material', '/accessories', '/site', '/network', '/homebase', '/region',
            '/wilayah/pulau', '/wilayah/provinsi', '/wilayah/kota', '/wilayah/kecamatan', '/wilayah/desa'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        $this->actingAs($this->admin)->get('/site')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_unknown_ids_return_404(): void
    {
        $this->actingAs($this->admin)->get('/site/999999')->assertNotFound();
        $this->actingAs($this->admin)->get('/network/999999/edit-data')->assertNotFound();
        $this->actingAs($this->admin)->put('/region/999999', ['region_name' => 'X'])->assertNotFound();
        $this->actingAs($this->admin)->delete('/homebase/999999')->assertNotFound();
    }

    /* --------------------------------------------------- category item */

    public function test_category_item_validates_type_and_uniqueness(): void
    {
        $this->actingAs($this->admin)->post('/category-item', [
            'type' => 'MATERIAL', 'category_name' => 'Modem', 'merk' => 'iDirect',
        ])->assertRedirect('/category-item');
        $this->assertDatabaseHas('category_item', ['category_name' => 'Modem', 'type' => 'MATERIAL']);

        $this->actingAs($this->admin)->from('/category-item')->post('/category-item', [
            'type' => 'MATERIAL', 'category_name' => 'Modem', 'merk' => 'iDirect',
        ])->assertSessionHasErrors('category_name');

        $this->actingAs($this->admin)->from('/category-item')->post('/category-item', [
            'type' => 'BUKAN', 'category_name' => 'Lain', 'merk' => 'X',
        ])->assertSessionHasErrors('type');

        $this->assertDatabaseCount('category_item', 1);
    }

    public function test_category_in_use_cannot_be_deleted(): void
    {
        $material = $this->material($this->customerBts());

        $this->actingAs($this->admin)->from('/category-item')
            ->delete('/category-item/'.$material->category_id)
            ->assertSessionHas('error');

        $this->assertDatabaseHas('category_item', ['id' => $material->category_id]);
    }

    /* -------------------------------------------------- master material */

    public function test_material_code_is_generated_per_customer_and_month(): void
    {
        $customer = $this->customerBts();
        $category = $this->category();

        foreach ([1, 2] as $i) {
            $this->actingAs($this->admin)->post('/master-material', [
                'customer_id' => $customer->id, 'category_id' => $category->id,
                'serial_number' => 'SN-SAMA', 'status' => 'WAREHOUSE',
                'product_code' => 'HACK-001', // tidak boleh terisi lewat mass assignment
            ])->assertRedirect('/master-material');
        }

        $this->assertSame(
            ['PBTS-092600001', 'PBTS-092600002'],
            MasterMaterial::orderBy('id')->pluck('product_code')->all()
        );
        // Serial number boleh sama (tidak unik).
        $this->assertSame(2, MasterMaterial::where('serial_number', 'SN-SAMA')->count());
    }

    public function test_material_rejects_invalid_references(): void
    {
        $noInitial = Customer::create(['name' => 'Tanpa Inisial', 'status' => 'ACTIVE']);
        $accCategory = $this->category('ACCESSORIES');

        $this->actingAs($this->admin)->from('/master-material')->post('/master-material', [
            'customer_id' => $noInitial->id, 'category_id' => $accCategory->id,
            'serial_number' => '', 'status' => 'RUSAK',
        ])->assertSessionHasErrors(['customer_id', 'category_id', 'serial_number', 'status']);

        $this->actingAs($this->admin)->from('/master-material')->post('/master-material', [
            'customer_id' => 999999, 'category_id' => 999999, 'serial_number' => 'X', 'status' => 'WAREHOUSE',
        ])->assertSessionHasErrors(['customer_id', 'category_id']);

        $this->assertDatabaseCount('master_material', 0);
    }

    public function test_material_update_cannot_change_customer_or_code(): void
    {
        $material = $this->material($this->customerBts());
        $other = $this->makeCustomer();

        $this->actingAs($this->admin)->put('/master-material/'.$material->id, [
            'customer_id' => $other->id, 'product_code' => 'GANTI',
            'category_id' => $material->category_id, 'serial_number' => 'SN-BARU', 'status' => 'FAULTY',
        ])->assertRedirect('/master-material');

        $material->refresh();
        $this->assertSame('SN-BARU', $material->serial_number);
        $this->assertSame('FAULTY', $material->status);
        $this->assertSame('PBTS-092600001', $material->product_code);
        $this->assertNotSame($other->id, $material->customer_id);
    }

    public function test_accessory_code_uses_prefix_a(): void
    {
        $customer = $this->customerBts();

        $this->actingAs($this->admin)->post('/accessories', [
            'customer_id' => $customer->id, 'accessories_name' => 'Konektor',
            'category_id' => $this->category('ACCESSORIES')->id, 'status' => 'WAREHOUSE',
        ])->assertRedirect('/accessories');

        $this->assertDatabaseHas('accessories', ['accessories_code' => 'ABTS-092600001', 'accessories_name' => 'Konektor']);
    }

    /* ------------------------------------------------------------- site */

    public function test_site_validates_geo_chain_coordinates_and_unique_tid(): void
    {
        $customer = $this->customerBts();
        $jawa = Pulau::create(['name' => 'Jawa']);
        $sumatra = Pulau::create(['name' => 'Sumatra']);
        $jabar = Provinsi::create(['pulau_id' => $jawa->id, 'name' => 'Jawa Barat']);
        $bandung = Kota::create(['provinsi_id' => $jabar->id, 'name' => 'Bandung']);

        $base = [
            'tid' => 'TID-001', 'sitename' => 'SD Negeri 1', 'customer_id' => $customer->id,
            'type' => 'SEKOLAH', 'source_power' => 'SPS', 'program' => 'AKSES_INTERNET', 'status' => 'ACTIVE',
        ];

        $this->actingAs($this->admin)->post('/site', $base + [
            'pulau_id' => $jawa->id, 'provinsi_id' => $jabar->id, 'kota_id' => $bandung->id,
            'latitude' => '-6.9147', 'longitude' => '107.6098',
        ])->assertRedirect('/site');
        $this->assertDatabaseHas('sites', ['tid' => 'TID-001', 'kota_id' => $bandung->id]);

        // Provinsi tidak berada di pulau yang dipilih.
        $this->actingAs($this->admin)->from('/site')->post('/site', array_merge($base, ['tid' => 'TID-002']) + [
            'pulau_id' => $sumatra->id, 'provinsi_id' => $jabar->id,
        ])->assertSessionHasErrors('provinsi_id');

        // Kota diisi tanpa provinsi.
        $this->actingAs($this->admin)->from('/site')->post('/site', array_merge($base, ['tid' => 'TID-003']) + [
            'kota_id' => $bandung->id,
        ])->assertSessionHasErrors('provinsi_id');

        $this->actingAs($this->admin)->from('/site')->post('/site', array_merge($base, ['tid' => 'TID-004']) + [
            'latitude' => '95', 'longitude' => '200',
        ])->assertSessionHasErrors(['latitude', 'longitude']);

        $this->actingAs($this->admin)->from('/site')->post('/site', array_merge($base, ['type' => 'BUKAN']))
            ->assertSessionHasErrors(['tid', 'type']);

        $this->assertDatabaseCount('sites', 1);
    }

    public function test_wilayah_options_are_filtered_by_parent(): void
    {
        $jawa = Pulau::create(['name' => 'Jawa']);
        $bali = Pulau::create(['name' => 'Bali']);
        Provinsi::create(['pulau_id' => $jawa->id, 'name' => 'Jawa Barat']);
        Provinsi::create(['pulau_id' => $bali->id, 'name' => 'Bali']);

        $this->actingAs($this->admin)->getJson('/wilayah/provinsi/options?parent_id='.$jawa->id)
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Jawa Barat');

        $this->actingAs($this->admin)->getJson('/wilayah/provinsi/options')->assertOk()->assertJsonCount(0);
    }

    public function test_site_material_association_rules(): void
    {
        $customer = $this->customerBts();
        $site = $this->site($customer);
        $material = $this->material($customer);
        $foreign = $this->material(Customer::create(['name' => 'PT Lain', 'initial' => 'LAN', 'status' => 'ACTIVE']));

        $this->actingAs($this->admin)->post('/site/'.$site->id.'/material', ['material_id' => $material->id])
            ->assertRedirect('/site/'.$site->id);
        $this->assertDatabaseHas('site_material', ['site_id' => $site->id, 'material_id' => $material->id]);

        $this->actingAs($this->admin)->from('/site/'.$site->id)
            ->post('/site/'.$site->id.'/material', ['material_id' => $material->id])
            ->assertSessionHasErrors('material_id');

        $this->actingAs($this->admin)->from('/site/'.$site->id)
            ->post('/site/'.$site->id.'/material', ['material_id' => $foreign->id])
            ->assertSessionHasErrors('material_id');

        $this->assertSame(1, SiteMaterial::count());

        // Material & site yang masih terhubung tidak bisa dihapus.
        $this->actingAs($this->admin)->from('/master-material')->delete('/master-material/'.$material->id)->assertSessionHas('error');
        $this->actingAs($this->admin)->from('/site')->delete('/site/'.$site->id)->assertSessionHas('error');
        $this->assertDatabaseHas('master_material', ['id' => $material->id]);
        $this->assertDatabaseHas('sites', ['id' => $site->id]);

        $this->actingAs($this->admin)->get('/site/'.$site->id)->assertOk()->assertSee($material->product_code);

        // Link milik site lain tidak bisa dihapus lewat URL site ini.
        $otherSite = $this->site($customer);
        $link = SiteMaterial::first();
        $this->actingAs($this->admin)->delete('/site/'.$otherSite->id.'/material/'.$link->id)->assertNotFound();

        $this->actingAs($this->admin)->delete('/site/'.$site->id.'/material/'.$link->id)->assertRedirect('/site/'.$site->id);
        $this->assertSame(0, SiteMaterial::count());
        $this->assertDatabaseHas('master_material', ['id' => $material->id]);
    }

    public function test_site_accessory_qty_rules(): void
    {
        $customer = $this->customerBts();
        $site = $this->site($customer);
        $acc = $this->accessory($customer);

        $this->actingAs($this->admin)->from('/site/'.$site->id)
            ->post('/site/'.$site->id.'/accessories', ['accessories_id' => $acc->id, 'qty' => 0])
            ->assertSessionHasErrors('qty');

        $this->actingAs($this->admin)->post('/site/'.$site->id.'/accessories', ['accessories_id' => $acc->id, 'qty' => 3])
            ->assertRedirect('/site/'.$site->id);

        $this->actingAs($this->admin)->from('/site/'.$site->id)
            ->post('/site/'.$site->id.'/accessories', ['accessories_id' => $acc->id, 'qty' => 2])
            ->assertSessionHasErrors('accessories_id');

        $link = SiteAccessory::first();
        $this->actingAs($this->admin)->put('/site/'.$site->id.'/accessories/'.$link->id, ['qty' => 7]);
        $this->assertSame(7, $link->fresh()->qty);

        $this->actingAs($this->admin)->from('/site/'.$site->id)
            ->put('/site/'.$site->id.'/accessories/'.$link->id, ['qty' => -1])
            ->assertSessionHasErrors('qty');
        $this->assertSame(7, $link->fresh()->qty);

        $this->actingAs($this->admin)->from('/accessories')->delete('/accessories/'.$acc->id)->assertSessionHas('error');
        $this->assertDatabaseHas('accessories', ['id' => $acc->id]);
    }

    /* ------------------------------------------ region / homebase / PIC */

    public function test_homebase_pic_association_and_delete_guards(): void
    {
        $pulau = Pulau::create(['name' => 'Jawa']);

        $this->actingAs($this->admin)->post('/region', ['region_name' => 'Region Jabar', 'pulau_id' => $pulau->id])
            ->assertRedirect('/region');
        $region = Region::firstOrFail();

        $this->actingAs($this->admin)->post('/homebase', [
            'region_id' => $region->id, 'address' => 'Jl. Uji 1', 'latitude' => '-6.9', 'longitude' => '107.6',
        ])->assertRedirect('/homebase');
        $homebase = Homebase::firstOrFail();

        $pic = MasterPic::create(['type' => 'internal', 'pic' => 'Teknisi A', 'status' => 'ACTIVE']);

        $this->actingAs($this->admin)->post('/homebase/'.$homebase->id.'/pic', ['pic_id' => $pic->id])
            ->assertRedirect('/homebase/'.$homebase->id);
        $this->actingAs($this->admin)->from('/homebase/'.$homebase->id)
            ->post('/homebase/'.$homebase->id.'/pic', ['pic_id' => $pic->id])
            ->assertSessionHasErrors('pic_id');
        $this->assertSame(1, HomebaseDetail::count());

        $this->actingAs($this->admin)->get('/homebase/'.$homebase->id)->assertOk()->assertSee('Teknisi A');

        // Referensi aktif mencegah penghapusan.
        $this->actingAs($this->admin)->from('/master-pic')->delete('/master-pic/'.$pic->id)->assertSessionHas('error');
        $this->actingAs($this->admin)->from('/homebase')->delete('/homebase/'.$homebase->id)->assertSessionHas('error');
        $this->actingAs($this->admin)->from('/region')->delete('/region/'.$region->id)->assertSessionHas('error');
        $this->actingAs($this->admin)->from('/wilayah/pulau')->delete('/wilayah/pulau/'.$pulau->id)->assertSessionHas('error');

        $this->assertDatabaseHas('master_pics', ['id' => $pic->id]);
        $this->assertDatabaseHas('homebase', ['id' => $homebase->id]);
        $this->assertDatabaseHas('region', ['id' => $region->id]);
        $this->assertDatabaseHas('pulau', ['id' => $pulau->id]);

        $detail = HomebaseDetail::first();
        $this->actingAs($this->admin)->delete('/homebase/'.$homebase->id.'/pic/'.$detail->id);
        $this->actingAs($this->admin)->delete('/homebase/'.$homebase->id)->assertRedirect('/homebase');
        $this->assertDatabaseMissing('homebase', ['id' => $homebase->id]);
    }

    public function test_homebase_and_region_validation(): void
    {
        $this->actingAs($this->admin)->from('/region')->post('/region', ['region_name' => '', 'pulau_id' => 999])
            ->assertSessionHasErrors(['region_name', 'pulau_id']);

        $this->actingAs($this->admin)->from('/homebase')->post('/homebase', [
            'region_id' => 999, 'address' => '', 'latitude' => '-91',
        ])->assertSessionHasErrors(['region_id', 'address', 'latitude', 'longitude']);
    }

    public function test_network_edit_data_and_update(): void
    {
        $this->actingAs($this->admin)->from('/network')->post('/network', [])->assertSessionHasErrors('penyedia_lc');

        $this->actingAs($this->admin)->post('/network', ['penyedia_lc' => 'Telkomsat', 'satelit' => 'Merah Putih'])
            ->assertRedirect('/network');
        $id = Network::firstOrFail()->id;

        $this->actingAs($this->admin)->getJson('/network/'.$id.'/edit-data')
            ->assertOk()->assertJsonPath('penyedia_lc', 'Telkomsat');

        $this->actingAs($this->admin)->put('/network/'.$id, ['penyedia_lc' => 'Telkomsat', 'hub' => 'HUB-1'])
            ->assertRedirect('/network');
        $this->assertDatabaseHas('network', ['id' => $id, 'hub' => 'HUB-1']);
    }

    public function test_new_site_is_always_active_and_status_editable_later(): void
    {
        $customer = $this->customerBts();

        $this->actingAs($this->admin)->post('/site', [
            'tid' => 'TID-NEW', 'sitename' => 'Site Baru', 'customer_id' => $customer->id,
            'type' => 'TOWER', 'source_power' => 'PLN', 'program' => 'TOWER_POWER',
            'status' => 'INACTIVE', // diabaikan saat tambah
        ])->assertRedirect('/site');

        $site = Site::where('tid', 'TID-NEW')->firstOrFail();
        $this->assertSame('ACTIVE', $site->status);

        $this->actingAs($this->admin)->put('/site/'.$site->id, [
            'tid' => 'TID-NEW', 'sitename' => 'Site Baru', 'customer_id' => $customer->id,
            'type' => 'TOWER', 'source_power' => 'PLN', 'program' => 'TOWER_POWER', 'status' => 'INACTIVE',
        ])->assertRedirect('/site');
        $this->assertSame('INACTIVE', $site->fresh()->status);

        $this->actingAs($this->admin)->from('/site')->put('/site/'.$site->id, [
            'tid' => 'TID-NEW', 'sitename' => 'Site Baru', 'customer_id' => $customer->id,
            'type' => 'TOWER', 'source_power' => 'PLN', 'program' => 'TOWER_POWER',
        ])->assertSessionHasErrors('status');
    }

    public function test_create_page_saves_site_technician_and_keepers(): void
    {
        $customer = $this->customerBts();
        // Teknisi = akun user; tidak perlu Master PIC.
        $tech = $this->technicianFor($customer, 'Budi Teknisi');

        // Pilihan Site Keeper = PIC ACTIVE berjenis site_keeper (sejak 19 Okt
        // 2026, SiteController::keeperOptions()); validasi simpan masih
        // mewajibkan nama akun user (keeperRules()), jadi keduanya dibuat.
        $jaga = $this->userWithRole('staff', ['name' => 'Pak Jaga']);
        $jaga2 = $this->userWithRole('staff', ['name' => 'Bu Jaga']);
        MasterPic::create(['type' => Site::KEEPER_PIC_TYPE, 'pic' => 'Pak Jaga', 'status' => 'ACTIVE']);
        $this->userWithRole('staff', ['name' => 'Akun Bukan Keeper']);

        // Homebase, Cluster & Supervisor sudah tidak ada di form.
        $this->actingAs($this->admin)->get('/site/create')->assertOk()
            ->assertSee('Budi Teknisi')->assertSee('Gateway')->assertSee('Pak Jaga')
            // Akun user biasa tidak lagi muncul sebagai pilihan Site Keeper.
            ->assertDontSee('Akun Bukan Keeper')
            ->assertDontSee('Pilih Supervisor')->assertDontSee('Pilih Homebase');

        $payload = [
            'tid' => 'TID-PIC', 'sitename' => 'Site PIC', 'customer_id' => $customer->id,
            'type' => 'TOWER', 'source_power' => 'PLN', 'program' => 'AKSES_INTERNET',
            'gateway' => 'GW-1', 'polarization' => 'R',
        ];

        // Akun tanpa role technician ditolak.
        $bukan = $this->userWithRole('staff', ['name' => 'Bukan Teknisi']);
        $this->actingAs($this->admin)->from('/site/create')
            ->post('/site', $payload + ['technician_user_id' => $bukan->id])
            ->assertRedirect('/site/create')->assertSessionHasErrors('technician_user_id');

        // Role technician tanpa team customer ini ditolak.
        $tanpaTeam = $this->userWithRole('technician', ['name' => 'Tanpa Team']);
        $this->actingAs($this->admin)->from('/site/create')
            ->post('/site', $payload + ['technician_user_id' => $tanpaTeam->id])
            ->assertSessionHasErrors('technician_user_id');

        // Teknisi team customer lain ditolak.
        $lain = $this->technicianFor($this->customerBts2(), 'Teknisi Lain');
        $this->actingAs($this->admin)->from('/site/create')
            ->post('/site', $payload + ['technician_user_id' => $lain->id])
            ->assertSessionHasErrors('technician_user_id');
        $this->assertDatabaseCount('sites', 0);

        // Nama di luar daftar user ditolak.
        $this->actingAs($this->admin)->from('/site/create')
            ->post('/site', $payload + ['keepers' => [['name' => 'Orang Asing']]])
            ->assertSessionHasErrors('keepers.0.name');

        $this->actingAs($this->admin)
            ->post('/site', $payload + [
                'technician_user_id' => $tech->id,
                'keepers' => [
                    ['name' => $jaga->name],
                    ['name' => ''],              // baris kosong diabaikan
                    ['name' => $jaga2->name],
                ],
            ])
            ->assertRedirect('/site');

        $site = Site::where('tid', 'TID-PIC')->firstOrFail();
        $this->assertSame('ACTIVE', $site->status);
        $this->assertSame('GW-1', $site->gateway);
        $this->assertSame('R', $site->polarization);
        // Homebase & Cluster tidak lagi diisi dari form.
        $this->assertNull($site->hb_id);
        $this->assertNull($site->region_id);
        $this->assertSame($tech->id, (int) $site->technician_user_id);
        // Tidak ada baris site_pics yang dibuat untuk teknisi.
        $this->assertSame(0, $site->picLinks()->count());

        $this->assertSame('Budi Teknisi', SiteController::technicians($site));

        $this->assertSame(
            ['Pak Jaga', 'Bu Jaga'],
            $site->keepers()->orderBy('id')->pluck('name')->all()
        );
    }

    public function test_edit_page_replaces_legacy_technician_with_user(): void
    {
        $customer = $this->customerBts();
        $site = $this->site($customer);
        // A = teknisi lama dari Master PIC tanpa akun (site_pics); B = akun teknisi sah.
        $a = MasterPic::create(['type' => 'internal', 'pic' => 'Teknisi A', 'position' => 'Teknisi', 'status' => 'ACTIVE']);
        $b = $this->technicianFor($customer, 'Teknisi B');
        SitePic::create(['site_id' => $site->id, 'pic_id' => $a->id]);

        $this->actingAs($this->admin)->get('/site/'.$site->id.'/edit')
            ->assertOk()->assertSee('Edit Site '.$site->tid)->assertSee('value="'.$site->tid.'"', false)
            ->assertSee('Teknisi lama: <strong>Teknisi A</strong>', false);
        $this->assertSame('Teknisi A', SiteController::technicians($site->load('picLinks.pic')));

        $payload = [
            'tid' => $site->tid, 'sitename' => 'Nama Baru', 'customer_id' => $site->customer_id,
            'type' => 'TOWER', 'source_power' => 'PLN', 'program' => 'AKSES_INTERNET', 'status' => 'ACTIVE',
        ];

        // Field Teknisi tidak dikirim / dikirim kosong: teknisi lama tanpa akun tidak disentuh.
        $this->actingAs($this->admin)->put('/site/'.$site->id, $payload)->assertRedirect('/site');
        $this->actingAs($this->admin)->put('/site/'.$site->id, $payload + ['technician_user_id' => ''])
            ->assertRedirect('/site')->assertSessionHasNoErrors();
        $this->assertSame([$a->id], $site->picLinks()->pluck('pic_id')->all());

        // Teknisi baru dipilih: kolom terisi, teknisi lama di site_pics dilepas.
        $this->actingAs($this->admin)->put('/site/'.$site->id, $payload + ['technician_user_id' => $b->id])
            ->assertRedirect('/site')->assertSessionHasNoErrors();
        $site->refresh();
        $this->assertSame('Nama Baru', $site->sitename);
        $this->assertSame($b->id, (int) $site->technician_user_id);
        $this->assertSame(0, $site->picLinks()->count());

        // Teknisi tersimpan dikirim ulang walau kini di luar team: tetap diterima.
        TeamMember::where('user_id', $b->id)->update(['status' => 'INACTIVE']);
        $this->actingAs($this->admin)->put('/site/'.$site->id, $payload + ['technician_user_id' => $b->id])
            ->assertRedirect('/site')->assertSessionHasNoErrors();

        // Dikirim kosong = lepas Teknisi.
        $this->actingAs($this->admin)->put('/site/'.$site->id, $payload + ['technician_user_id' => '']);
        $this->assertNull($site->fresh()->technician_user_id);

        // Site Keeper: daftar yang dikirim menggantikan isi lama.
        $baru = $this->userWithRole('staff', ['name' => 'Jaga Baru']);
        SiteKeeper::create(['site_id' => $site->id, 'name' => 'Lama']);
        $this->actingAs($this->admin)->put('/site/'.$site->id, $payload + [
            'keepers' => [['name' => $baru->name]],
        ])->assertRedirect('/site');
        $this->assertSame(['Jaga Baru'], $site->keepers()->pluck('name')->all());
    }

    public function test_technician_options_follow_role_and_team_customer(): void
    {
        $customer = $this->customerBts();
        $other = $this->customerBts2();
        $sah = $this->technicianFor($customer, 'Teknisi Sah');
        $lain = $this->technicianFor($other, 'Teknisi Lain');
        // Role selain technician tidak ikut walau anggota team customer yang sama.
        $staf = $this->userWithRole('staff', ['name' => 'Staf Team']);
        TeamMember::create(['team_id' => Team::where('customer_id', $customer->id)->value('id'), 'user_id' => $staf->id, 'level' => 2, 'status' => 'ACTIVE']);

        $this->actingAs($this->admin)->get('/site/create')->assertOk()
            ->assertSee('Pilih Customer dahulu')->assertSee('Teknisi Sah')->assertDontSee('Staf Team');

        $payload = [
            'tid' => 'TID-BKP', 'sitename' => 'Site Backup', 'customer_id' => $customer->id,
            'type' => 'TOWER', 'source_power' => 'PLN', 'program' => 'AKSES_INTERNET',
        ];

        // Teknisi Backup: user team customer lain & role non-technician ditolak.
        $this->actingAs($this->admin)->from('/site/create')
            ->post('/site', $payload + ['backup_technicians' => [$lain->id]])
            ->assertSessionHasErrors('backup_technicians.0');
        $this->actingAs($this->admin)->from('/site/create')
            ->post('/site', $payload + ['backup_technicians' => [$staf->id]])
            ->assertSessionHasErrors('backup_technicians.0');
        $this->assertDatabaseCount('sites', 0);

        $this->actingAs($this->admin)
            ->post('/site', $payload + ['technician_user_id' => $sah->id, 'backup_technicians' => [$sah->id, '']])
            ->assertRedirect('/site')->assertSessionHasNoErrors();
        $site = Site::where('tid', 'TID-BKP')->firstOrFail();
        $this->assertSame([$sah->id], $site->backupTechnicians()->pluck('user_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_migration_backfills_technician_user_from_linked_master_pic(): void
    {
        $customer = $this->customerBts();
        $linkedSite = $this->site($customer);
        $unlinkedSite = $this->site($customer);
        $akun = $this->userWithRole('technician', ['name' => 'Akun Teknisi']);
        $linked = MasterPic::create(['type' => 'internal', 'pic' => 'Akun Teknisi', 'position' => 'Teknisi', 'status' => 'ACTIVE', 'user_id' => $akun->id]);
        $unlinked = MasterPic::create(['type' => 'internal', 'pic' => 'Tanpa Akun', 'position' => 'Teknisi', 'status' => 'ACTIVE']);
        SitePic::create(['site_id' => $linkedSite->id, 'pic_id' => $linked->id]);
        SitePic::create(['site_id' => $unlinkedSite->id, 'pic_id' => $unlinked->id]);

        // Kolom sudah ada dari RefreshDatabase; up() hanya mengulang backfill (tanpa DDL).
        (require base_path('database/migrations/2026_11_09_000300_add_technician_user_to_sites.php'))->up();

        $this->assertSame($akun->id, (int) $linkedSite->fresh()->technician_user_id);
        $this->assertNull($unlinkedSite->fresh()->technician_user_id);
        // Data asal tidak diubah.
        $this->assertSame(2, SitePic::count());
    }

    public function test_index_region_technician_card_and_inactive_marker(): void
    {
        $customer = $this->customerBts();
        $site = $this->site($customer, ['sitename' => 'Kantor Desa Sukamaju']);
        $this->site($customer, ['status' => 'INACTIVE']);
        // Cluster site lama tetap diturunkan dari homebase teknisi lama (site_pics).
        $tech = MasterPic::create(['type' => 'internal', 'pic' => 'Hadi Teknisi', 'position' => 'Teknisi', 'status' => 'ACTIVE']);
        SitePic::create(['site_id' => $site->id, 'pic_id' => $tech->id]);
        // Kartu Total Technician = seluruh pilihan dropdown Teknisi (akun role
        // technician di team bercustomer), bukan yang terpasang di site.
        $this->technicianFor($customer, 'Teknisi Kartu');

        $region = Region::create(['region_name' => 'Region Jabar', 'pulau_id' => Pulau::create(['name' => 'Jawa'])->id]);
        $hb = Homebase::create(['region_id' => $region->id, 'address' => 'HB Bandung']);
        HomebaseDetail::create(['hb_id' => $hb->id, 'pic_id' => $tech->id]);

        $this->assertSame('Region Jabar', SiteController::regions($site->load('picLinks.pic')));
        $this->assertSame(1, SiteController::technicianCount());

        $this->actingAs($this->admin)->get('/site')->assertOk()
            ->assertSee('Total Technician')->assertSee('Total TT')
            ->assertSee('Region Jabar')
            ->assertSee('data-row-status="INACTIVE"', false)
            ->assertSee('cell-trunc', false)
            ->assertDontSee('<th scope="col" width="5%">No</th>', false);
    }

    public function test_site_drawer_shows_tabs_and_map(): void
    {
        $site = $this->site($this->customerBts(), ['latitude' => '-6.9147440', 'longitude' => '107.6098100']);

        $this->actingAs($this->admin)->get('/site/'.$site->id.'/drawer')
            ->assertOk()
            ->assertSee($site->sitename)
            ->assertSee('openstreetmap.org/export/embed.html', false)
            ->assertSee('Informasi')->assertSee('Assets')->assertSee('Document');

        config(['services.google_maps.key' => 'KUNCI-UJI']);
        $this->actingAs($this->admin)->get('/site/'.$site->id.'/drawer')
            ->assertOk()
            ->assertSee('maps.googleapis.com/maps/api/staticmap', false)
            ->assertSee('feature%3Apoi%7Cvisibility%3Aoff', false)
            ->assertDontSee('export/embed.html', false);
        config(['services.google_maps.key' => null]);

        $noCoord = $this->site($this->customerBts2());
        $this->actingAs($this->admin)->get('/site/'.$noCoord->id.'/drawer')
            ->assertOk()->assertSee('Koordinat belum diisi')->assertDontSee('export/embed.html', false);

        $this->actingAs($this->admin)->get('/site/999999/drawer')->assertNotFound();
        $this->actingAs($this->userWithRole('staff'))->get('/site/'.$site->id.'/drawer')->assertForbidden();
    }

    private function customerBts2(): Customer
    {
        return Customer::create(['name' => 'PT Dua', 'initial' => 'DUA', 'status' => 'ACTIVE']);
    }

    public function test_site_pic_association_and_delete_guards(): void
    {
        $site = $this->site($this->customerBts());
        $pic = MasterPic::create(['type' => 'site_keeper', 'pic' => 'Penjaga Site', 'status' => 'ACTIVE']);
        $inactive = MasterPic::create(['type' => 'internal', 'pic' => 'Nonaktif', 'status' => 'INACTIVE']);

        $this->actingAs($this->admin)->post('/site/'.$site->id.'/pic', ['pic_id' => $pic->id])
            ->assertRedirect('/site/'.$site->id);
        $this->actingAs($this->admin)->from('/site/'.$site->id)
            ->post('/site/'.$site->id.'/pic', ['pic_id' => $pic->id])->assertSessionHasErrors('pic_id');
        $this->actingAs($this->admin)->from('/site/'.$site->id)
            ->post('/site/'.$site->id.'/pic', ['pic_id' => $inactive->id])->assertSessionHasErrors('pic_id');
        $this->assertSame(1, SitePic::count());

        $this->actingAs($this->admin)->get('/site/'.$site->id)->assertOk()->assertSee('Penjaga Site');

        $this->actingAs($this->admin)->from('/master-pic')->delete('/master-pic/'.$pic->id)->assertSessionHas('error');
        $this->actingAs($this->admin)->from('/site')->delete('/site/'.$site->id)->assertSessionHas('error');
        $this->assertDatabaseHas('master_pics', ['id' => $pic->id]);

        $link = SitePic::first();
        $this->actingAs($this->admin)->delete('/site/'.$site->id.'/pic/'.$link->id)->assertRedirect('/site/'.$site->id);
        $this->assertSame(0, SitePic::count());
        $this->actingAs($this->admin)->delete('/site/'.$site->id)->assertRedirect('/site');
    }

    /* ------------------------------------------- integrasi modul lama */

    public function test_customer_with_sites_cannot_be_deleted(): void
    {
        $customer = $this->customerBts();
        $this->site($customer);

        $this->actingAs($this->admin)->from('/customer')->delete('/customer/'.$customer->id)->assertSessionHas('error');
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }
}
