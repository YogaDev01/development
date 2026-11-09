<?php

namespace Tests\Feature;

use App\Models\CategoryItem;
use App\Models\Customer;
use App\Models\Homebase;
use App\Models\Kota;
use App\Models\MasterMaterial;
use App\Models\Provinsi;
use App\Models\Pulau;
use App\Models\Region;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

class SiteMasterImportTest extends TestCase
{
    use MakesTesysData;
    use RefreshDatabase;

    private const MATERIAL_HEADERS = ['Inisial Customer', 'Nama Kategori', 'Merk', 'Serial Number', 'Status'];

    private const SITE_HEADERS = ['Site ID', 'Nama Site', 'Customer', 'Tipe Bangunan', 'Source Power', 'Program',
        'Homebase', 'Cluster', 'Network ID', 'Provinsi', 'Kota/Kabupaten', 'Kecamatan', 'Desa/Kelurahan', 'Alamat',
        'Latitude', 'Longitude', 'Gateway', 'Outroute ID', 'Hub', 'Spotbeam', 'Polarisasi'];

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

    private function xlsx(array $headers, array $rows): UploadedFile
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Data');
        $sheet->fromArray(array_merge([$headers], $rows), null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($book))->save($path);

        return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_templates_download_as_xlsx(): void
    {
        foreach (['/master-material/import-template', '/site/import-template'] as $url) {
            $this->actingAs($this->admin)->get($url)
                ->assertOk()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
    }

    public function test_import_requires_menu_access(): void
    {
        $staff = $this->userWithRole('staff');
        $file = $this->xlsx(self::MATERIAL_HEADERS, []);

        $this->actingAs($staff)->get('/master-material/import-template')->assertForbidden();
        $this->actingAs($staff)->post('/master-material/import', ['file' => $file])->assertForbidden();
    }

    public function test_material_import_creates_rows_with_generated_codes(): void
    {
        Customer::create(['name' => 'PT BTS', 'initial' => 'BTS', 'status' => 'ACTIVE']);
        CategoryItem::create(['type' => 'MATERIAL', 'category_name' => 'Modem', 'merk' => 'iDirect']);

        $file = $this->xlsx(self::MATERIAL_HEADERS, [
            ['bts', 'Modem', 'iDirect', 'SN-1', 'Warehouse'],
            ['BTS', 'modem', 'IDIRECT', 'SN-2', 'installed'],
        ]);

        $this->actingAs($this->admin)->post('/master-material/import', ['file' => $file])
            ->assertRedirect('/master-material')->assertSessionHas('success');

        $this->assertSame(['PBTS-092600001', 'PBTS-092600002'], MasterMaterial::orderBy('id')->pluck('product_code')->all());
        $this->assertSame('INSTALLED', MasterMaterial::where('serial_number', 'SN-2')->value('status'));
    }

    public function test_material_import_is_all_or_nothing(): void
    {
        Customer::create(['name' => 'PT BTS', 'initial' => 'BTS', 'status' => 'ACTIVE']);
        CategoryItem::create(['type' => 'MATERIAL', 'category_name' => 'Modem', 'merk' => 'iDirect']);

        $file = $this->xlsx(self::MATERIAL_HEADERS, [
            ['BTS', 'Modem', 'iDirect', 'SN-1', 'Warehouse'],
            ['XXX', 'Router', 'Merk', '', 'Rusak'],
        ]);

        $this->actingAs($this->admin)->from('/master-material')->post('/master-material/import', ['file' => $file])
            ->assertRedirect('/master-material')
            ->assertSessionHas('error')
            ->assertSessionHas('import_errors', fn ($errors) => count($errors) === 4
                && str_contains($errors[0], 'Baris 3') && str_contains($errors[0], "inisial 'XXX'"));

        $this->assertDatabaseCount('master_material', 0);
    }

    public function test_import_rejects_wrong_template(): void
    {
        $file = $this->xlsx(['Kolom', 'Lain'], [['a', 'b']]);

        $this->actingAs($this->admin)->from('/site')->post('/site/import', ['file' => $file])
            ->assertSessionHas('error');
        $this->actingAs($this->admin)->from('/site')
            ->post('/site/import', ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('file');
    }

    public function test_site_import_resolves_wilayah_and_rejects_duplicate_tid_in_file(): void
    {
        Customer::create(['name' => 'PT BTS', 'initial' => 'BTS', 'status' => 'ACTIVE']);
        $jawa = Pulau::create(['name' => 'Jawa']);
        $jabar = Provinsi::create(['pulau_id' => $jawa->id, 'name' => 'Jawa Barat']);
        $bandung = Kota::create(['provinsi_id' => $jabar->id, 'name' => 'Kota Bandung']);

        $region = Region::create(['region_name' => 'JABAR-1', 'pulau_id' => $jawa->id]);
        Homebase::create(['region_id' => $region->id, 'address' => 'HB Bandung']);

        // Cluster sengaja dikosongkan: diambil dari Homebase.
        $row = ['TID-1', 'SD 1', 'PT BTS', 'Sekolah', 'PLN', 'Akses Internet', 'HB Bandung', '', '',
            'jawa barat', 'Kota Bandung', '', '', 'Jl. A', '-6.9', '107.6', 'GW-1', '', '', '', 'R'];

        $dup = $this->xlsx(self::SITE_HEADERS, [$row, $row]);
        $this->actingAs($this->admin)->from('/site')->post('/site/import', ['file' => $dup])
            ->assertSessionHas('import_errors');
        $this->assertDatabaseCount('sites', 0);

        $badGeo = $row;
        $badGeo[10] = 'Kota Tidak Ada';
        $this->actingAs($this->admin)->from('/site')
            ->post('/site/import', ['file' => $this->xlsx(self::SITE_HEADERS, [$badGeo])])
            ->assertSessionHas('import_errors', fn ($e) => str_contains($e[0], 'Kota Tidak Ada'));

        $second = $row;
        $second[0] = 'TID-2';
        $second[2] = 'bts';
        $second[10] = '';
        $this->actingAs($this->admin)->post('/site/import', ['file' => $this->xlsx(self::SITE_HEADERS, [$row, $second])])
            ->assertRedirect('/site')->assertSessionHas('success');

        $site = Site::where('tid', 'TID-1')->firstOrFail();
        $this->assertSame([$jawa->id, $jabar->id, $bandung->id], [$site->pulau_id, $site->provinsi_id, $site->kota_id]);
        $this->assertSame('SEKOLAH', $site->type);
        $this->assertSame($region->id, $site->region_id);
        $this->assertSame('GW-1', $site->gateway);
        $this->assertSame('R', $site->polarization);
        $this->assertNull(Site::where('tid', 'TID-2')->value('kota_id'));
    }
}
