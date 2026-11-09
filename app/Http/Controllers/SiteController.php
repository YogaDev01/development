<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ImportsMasterRecords;
use App\Http\Controllers\Concerns\ManagesMasterRecords;
use App\Models\Accessory;
use App\Models\Customer;
use App\Models\Homebase;
use App\Models\HomebaseDetail;
use App\Models\MasterMaterial;
use App\Models\MasterPic;
use App\Models\Network;
use App\Models\Pulau;
use App\Models\Region;
use App\Models\Site;
use App\Models\SiteAccessory;
use App\Models\SiteBackupTechnician;
use App\Models\SiteDocument;
use App\Models\SiteKeeper;
use App\Models\SiteMaintenance;
use App\Models\SiteMaintenanceItem;
use App\Models\SiteMaterial;
use App\Models\SitePic;
use App\Models\Team;
use App\Models\TroubleTicket;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Support\AccessRoles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SiteController extends Controller
{
    /*
    | Aksi tulis bawaan trait (hapus, import, data modal edit) dibungkus ulang
    | di bawah supaya ikut melewati guardWrite().
    */
    use ImportsMasterRecords {
        import as importRecords;
        importTemplate as importTemplateFile;
    }
    use ManagesMasterRecords {
        destroy as destroyRecord;
        editData as editDataRecord;
    }

    public function destroy($id)
    {
        $this->guardWrite();

        return $this->destroyRecord($id);
    }

    public function editData($id)
    {
        $this->guardWrite();

        return $this->editDataRecord($id);
    }

    public function import(Request $request)
    {
        $this->guardWrite();

        return $this->importRecords($request);
    }

    public function importTemplate()
    {
        $this->guardWrite();

        return $this->importTemplateFile();
    }

    protected function importConfig(): array
    {
        $customers = Customer::get(['id', 'name', 'initial']);
        $byName = $customers->mapWithKeys(fn ($c) => [mb_strtolower($c->name) => $c->id]);
        $byInitial = $customers->filter(fn ($c) => $c->initial)->mapWithKeys(fn ($c) => [mb_strtolower($c->initial) => $c->id]);

        // Cluster dicocokkan dari nama region; Homebase dari alamatnya.
        $regions = Region::get(['id', 'region_name'])->mapWithKeys(fn ($r) => [mb_strtolower($r->region_name) => $r->id]);
        $homebases = Homebase::get(['id', 'address', 'region_id'])->mapWithKeys(fn ($h) => [mb_strtolower(trim($h->address)) => $h]);

        // Cache per import: nama wilayah dicari di bawah induknya.
        $memo = [];
        $find = function (string $table, ?string $parentCol, $parentId, $name) use (&$memo) {
            $name = trim((string) $name);
            if ($name === '') {
                return null;
            }
            $key = $table.'|'.$parentId.'|'.mb_strtolower($name);
            if (! array_key_exists($key, $memo)) {
                $q = DB::table($table)->where('name', $name);
                if ($parentCol) {
                    $q->where($parentCol, $parentId);
                }
                $hits = $q->limit(2)->get();
                // Nama kembar dalam satu induk (desa vs kelurahan) tidak ditebak.
                $memo[$key] = $hits->count() === 1 ? $hits->first() : ($hits->isEmpty() ? null : false);
            }

            return $memo[$key];
        };

        return [
            'headers' => [
                'Site ID' => 'TID-0001',
                'Nama Site' => 'SD Negeri 1 Contoh',
                'Customer' => 'BTS',
                'Tipe Bangunan' => 'Sekolah',
                'Source Power' => 'PLN',
                'Program' => 'Akses Internet',
                'Homebase' => 'Jl. Homebase Bandung No. 10',
                'Cluster' => 'JABAR-1',
                'Network ID' => '',
                'Provinsi' => 'Jawa Barat',
                'Kota/Kabupaten' => 'Kota Bandung',
                'Kecamatan' => '',
                'Desa/Kelurahan' => '',
                'Alamat' => 'Jl. Contoh No. 1',
                'Latitude' => '-6.9147440',
                'Longitude' => '107.6098100',
                'Gateway' => '',
                'Outroute ID' => '',
                'Hub' => '',
                'Spotbeam' => '',
                'Polarisasi' => '',
            ],
            'unique' => ['tid'],
            'map' => function (array $c) use ($byName, $byInitial, $find, $regions, $homebases) {
                $errors = [];

                $cust = mb_strtolower((string) $c['Customer']);
                $customerId = $byName[$cust] ?? $byInitial[$cust] ?? null;
                if ($cust !== '' && ! $customerId) {
                    $errors['customer_id'] = "Customer '{$c['Customer']}' tidak ditemukan (isi nama atau inisial).";
                }

                // Homebase & Cluster wajib, sama seperti form Tambah Site.
                $hb = $homebases[mb_strtolower(trim((string) $c['Homebase']))] ?? null;
                if (! blank($c['Homebase']) && ! $hb) {
                    $errors['hb_id'] = "Homebase '{$c['Homebase']}' tidak ditemukan (isi alamat persis seperti di menu Homebase).";
                }
                $regionId = $regions[mb_strtolower(trim((string) $c['Cluster']))] ?? null;
                if (! blank($c['Cluster']) && ! $regionId) {
                    $errors['region_id'] = "Cluster '{$c['Cluster']}' tidak ditemukan di master Cluster.";
                }

                $geo = ['pulau_id' => null, 'provinsi_id' => null, 'kota_id' => null, 'kecamatan_id' => null, 'desa_id' => null];
                $chain = [
                    ['provinsi_id', 'provinsi', null, 'Provinsi'],
                    ['kota_id', 'kota', 'provinsi_id', 'Kota/Kabupaten'],
                    ['kecamatan_id', 'kecamatan', 'kota_id', 'Kecamatan'],
                    ['desa_id', 'desa', 'kecamatan_id', 'Desa/Kelurahan'],
                ];
                foreach ($chain as [$field, $table, $parentField, $col]) {
                    if (blank($c[$col])) {
                        continue;
                    }
                    if ($parentField && ! $geo[$parentField]) {
                        $errors[$field] = "{$col} diisi tetapi jenjang di atasnya kosong/tidak ditemukan.";
                        break;
                    }
                    $row = $find($table, $parentField, $parentField ? $geo[$parentField] : null, $c[$col]);
                    if ($row === false) {
                        $errors[$field] = "{$col} '{$c[$col]}' bernama ganda di wilayah tersebut; pilih lewat form Edit Site.";
                        break;
                    }
                    if (! $row) {
                        $errors[$field] = "{$col} '{$c[$col]}' tidak ditemukan di master Wilayah.";
                        break;
                    }
                    $geo[$field] = $row->id;
                    if ($table === 'provinsi') {
                        $geo['pulau_id'] = $row->pulau_id;
                    }
                }

                return [array_merge([
                    'tid' => $c['Site ID'] === null ? null : (string) $c['Site ID'],
                    'sitename' => $c['Nama Site'],
                    'customer_id' => $customerId,
                    'network_id' => blank($c['Network ID']) ? null : $c['Network ID'],
                    'hb_id' => $hb?->id,
                    // Cluster boleh dikosongkan di Excel: diambil dari Homebase.
                    'region_id' => $regionId ?: $hb?->region_id,
                    'gateway' => blank($c['Gateway']) ? null : $c['Gateway'],
                    'outroute_id' => blank($c['Outroute ID']) ? null : $c['Outroute ID'],
                    'hub' => blank($c['Hub']) ? null : $c['Hub'],
                    'spotbeam' => blank($c['Spotbeam']) ? null : $c['Spotbeam'],
                    'polarization' => blank($c['Polarisasi']) ? null : self::matchOption(Site::POLARIZATIONS, $c['Polarisasi']),
                    'type' => self::matchOption(Site::typeOptions(), $c['Tipe Bangunan']),
                    'source_power' => self::matchOption(Site::sourcePowerOptions(), $c['Source Power']),
                    'program' => self::matchOption(Site::programOptions(), $c['Program']),
                    'address' => blank($c['Alamat']) ? null : $c['Alamat'],
                    'latitude' => blank($c['Latitude']) ? null : $c['Latitude'],
                    'longitude' => blank($c['Longitude']) ? null : $c['Longitude'],
                ], $geo), $errors];
            },
            'reference' => fn () => [
                'Customer (nama)' => Customer::orderBy('name')->pluck('name')->all(),
                'Tipe Bangunan' => array_values(Site::typeOptions()),
                'Source Power' => array_values(Site::sourcePowerOptions()),
                'Program' => array_values(Site::programOptions()),
                'Network ID | Nama' => Network::orderBy('id')->get()->map(fn ($n) => $n->id.' | '.$n->label)->all(),
                'Homebase (alamat)' => Homebase::orderBy('address')->pluck('address')->all(),
                'Cluster' => Region::orderBy('region_name')->pluck('region_name')->all(),
                'Polarisasi' => array_values(Site::POLARIZATIONS),
                'Catatan' => [
                    'Wilayah diisi dengan NAMA persis seperti di menu Wilayah.',
                    'Pulau terisi otomatis dari Provinsi.',
                    'Kolom wilayah boleh kosong dari bawah (mis. hanya Provinsi + Kota).',
                    'Latitude & Longitude diisi berpasangan atau dikosongkan keduanya.',
                    'Homebase wajib; Cluster boleh kosong - otomatis mengikuti Homebase.',
                ],
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | AKSES ROLE LIHAT-SAJA (AccessRoles::SITE_READ_ONLY, mis. teknisi)
    |--------------------------------------------------------------------------
    | Role ini hanya boleh melihat daftar site, membuka detail, dan mengunduh
    | dokumen site — terbatas pada customer miliknya sendiri. Tombol memang
    | disembunyikan di tampilan, tapi gerbang sebenarnya ada di sini supaya URL
    | yang diketik langsung tetap ditolak.
    */

    /** True bila user login hanya boleh melihat menu Site. */
    private function isReadOnlyUser(): bool
    {
        return (bool) auth()->user()?->hasRole(AccessRoles::SITE_READ_ONLY);
    }

    /**
     * Customer yang "dimiliki" user lihat-saja: customer dari site tempat ia
     * terdaftar sebagai Teknisi (site_pics → master_pics.user_id) atau Teknisi
     * Backup. null = tanpa batas (role biasa); array kosong = tidak punya site,
     * sehingga daftarnya kosong. Dihitung sekali per request.
     */
    private function visibleCustomerIds(): ?array
    {
        $user = auth()->user();
        if (! $user || ! $this->isReadOnlyUser()) {
            return null;
        }

        $attrs = request()->attributes;
        if (! $attrs->has('site_visible_customers')) {
            $siteIds = SitePic::query()
                ->join('master_pics', 'master_pics.id', '=', 'site_pics.pic_id')
                ->where('master_pics.user_id', $user->id)
                ->where('master_pics.status', 'ACTIVE')
                ->pluck('site_pics.site_id')
                ->merge(SiteBackupTechnician::where('user_id', $user->id)->pluck('site_id'))
                ->unique();

            $attrs->set('site_visible_customers', Site::whereIn('id', $siteIds)
                ->whereNotNull('customer_id')
                ->distinct()
                ->pluck('customer_id')
                ->map(fn ($c) => (int) $c)
                ->all());
        }

        return $attrs->get('site_visible_customers');
    }

    /** Menolak semua aksi tulis bagi role lihat-saja. */
    private function guardWrite(): void
    {
        abort_if($this->isReadOnlyUser(), 403, 'Anda hanya memiliki akses lihat pada menu Site.');
    }

    /** Site per-baris sesuai batas customer; versi per-record dari scopeIndexQuery(). */
    private function findVisibleSite($id, array $with = []): Site
    {
        $site = Site::with($with)->findOrFail($id);
        $allowed = $this->visibleCustomerIds();

        abort_if($allowed !== null && ! in_array((int) $site->customer_id, $allowed, true),
            403, 'Site ini berada di luar customer Anda.');

        return $site;
    }

    /** Daftar site disaring ke customer milik user lihat-saja. */
    protected function scopeIndexQuery($query): void
    {
        $allowed = $this->visibleCustomerIds();
        if ($allowed !== null) {
            $query->whereIn('customer_id', $allowed);
        }
    }

    protected function master(): array
    {
        return [
            'model' => Site::class,
            'url' => '/site',
            'title' => 'Sites',
            'singular' => 'Site',
            'icon' => 'fas fa-broadcast-tower',
            'with' => ['customer:id,name', 'pulau:id,name', 'provinsi:id,name', 'kota:id,name', 'kecamatan:id,name', 'desa:id,name',
                'picLinks.pic:id,pic,type,position', 'homebase.region:id,region_name', 'region:id,region_name'],
            'create_url' => '/site/create',
            'edit_url' => '/site/{id}/edit',
            'drawer' => true,
            // Tujuh tab (Informasi … Preventive Maintenance) tidak muat di
            // lebar bawaan 640px.
            'drawer_width' => '860px',
            'import' => ! $this->isReadOnlyUser(),
            'numbered' => false,
            'compact' => true,
            'column_picker' => 'site-index',
            'hide_inactive' => true,
            'cards' => fn () => [
                ['Total TT', TroubleTicket::visibleTo(auth()->user())->count(), 'warning', 'fas fa-ticket-alt',
                    array_sum(self::openTicketCounts()).' terbuka'],
                ['Total Technician', self::technicianCount(), 'info', 'fas fa-user-cog'],
            ],
            'modal' => 'modal-lg',
            /*
            | Susunan dan isian field disamakan dengan form Tambah site SLAmeet
            | (app/Support/Entities.php, entitas `sites`): tiga bagian
            | Detail Site - Location - Network.
            |
            | `network_id` sengaja tidak lagi ada di form; data jaringan diisi
            | per site di bagian Network. Kolomnya masih ada untuk site lama.
            |
            | spv_id & pic_id bukan kolom `sites` (lihat Site::getSpvIdAttribute)
            | - disimpan sebagai baris `site_pics` lewat syncSitePics().
            */
            'fields' => [
                'tid' => ['label' => 'Site ID', 'type' => 'text', 'required' => true, 'col' => 6, 'section' => 'Detail Site'],
                'sitename' => ['label' => 'Site name', 'type' => 'text', 'required' => true, 'col' => 6],
                'type' => ['label' => 'Tipe', 'type' => 'select', 'options' => fn () => Site::typeOptions(), 'searchable' => true, 'col' => 6],
                'source_power' => ['label' => 'Source Power', 'type' => 'select', 'options' => fn () => Site::sourcePowerOptions(), 'searchable' => true, 'col' => 6],
                'program' => ['label' => 'Program', 'type' => 'select', 'options' => fn () => Site::programOptions(), 'required' => true, 'searchable' => true, 'col' => 6],
                // Site baru selalu ACTIVE (beforeCreate); status hanya bisa diubah saat edit.
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => Site::STATUSES, 'required' => true, 'searchable' => true, 'col' => 6, 'edit_only' => true],
                'customer_id' => ['label' => 'Customer', 'type' => 'select', 'options' => fn () => Customer::orderBy('name')->pluck('name', 'id')->all(), 'required' => true, 'searchable' => true, 'col' => 6],
                // Homebase, Cluster & Supervisor tidak lagi diisi di form: ketiganya
                // diturunkan dari Teknisi yang dipilih - lihat self::regions()
                // yang membaca homebase/region si teknisi.
                // Opsinya disaring lagi per customer di browser (site/create.blade.php).
                'pic_id' => ['label' => 'Teknisi', 'type' => 'select', 'options' => fn () => collect(self::technicianOptions())->pluck('name', 'id')->all(), 'searchable' => true, 'col' => 6, 'virtual' => true],
                'pulau_id' => ['label' => 'Island', 'type' => 'select', 'options' => fn () => Pulau::orderBy('name')->pluck('name', 'id')->all(), 'searchable' => true, 'col' => 6, 'cascade_child' => 'provinsi_id', 'section' => 'Location'],
                'provinsi_id' => ['label' => 'Provinsi', 'type' => 'cascade', 'level' => 'provinsi', 'parent' => 'pulau_id', 'col' => 6, 'cascade_child' => 'kota_id'],
                'kota_id' => ['label' => 'Kota / Kabupaten', 'type' => 'cascade', 'level' => 'kota', 'parent' => 'provinsi_id', 'col' => 6, 'cascade_child' => 'kecamatan_id'],
                'kecamatan_id' => ['label' => 'Kecamatan', 'type' => 'cascade', 'level' => 'kecamatan', 'parent' => 'kota_id', 'col' => 6, 'cascade_child' => 'desa_id'],
                'desa_id' => ['label' => 'Desa', 'type' => 'cascade', 'level' => 'desa', 'parent' => 'kecamatan_id', 'col' => 6],
                'latitude' => ['label' => 'Latitude', 'type' => 'decimal', 'col' => 6, 'placeholder' => '-6.2000000'],
                'longitude' => ['label' => 'Longitude', 'type' => 'decimal', 'col' => 6, 'placeholder' => '106.8166660'],
                'address' => ['label' => 'Address', 'type' => 'textarea'],
                'gateway' => ['label' => 'Gateway', 'type' => 'text', 'col' => 6, 'section' => 'Network'],
                'outroute_id' => ['label' => 'Outroute ID', 'type' => 'text', 'col' => 6],
                'hub' => ['label' => 'Hub', 'type' => 'text', 'col' => 6],
                'spotbeam' => ['label' => 'Spotbeam', 'type' => 'text', 'col' => 6],
                'polarization' => ['label' => 'Polarisasi', 'type' => 'select', 'options' => Site::POLARIZATIONS, 'searchable' => true, 'col' => 6],
            ],
            /*
            | Kolom tabel mengikuti isian form Tambah Site. Bagian Location &
            | Network diringkas jadi satu kolom: isinya ringkasan, rincian
            | lengkapnya muncul sebagai tooltip saat disorot (opsi `tooltip`
            | di resources/views/master-data/index.blade.php).
            */
            'columns' => [
                ['key' => 'tid', 'label' => 'Site ID', 'value' => fn ($r) => $r->tid, 'strong' => true, 'drawer_link' => true],
                ['key' => 'sitename', 'label' => 'Nama Site', 'value' => fn ($r) => $r->sitename, 'truncate' => 34,
                    'sub' => fn ($r) => $r->customer?->name],
                ['key' => 'type', 'label' => 'Tipe', 'value' => fn ($r) => Site::typeOptions()[$r->type] ?? $r->type, 'default' => false],
                ['key' => 'power', 'label' => 'Source Power', 'value' => fn ($r) => Site::sourcePowerOptions()[$r->source_power] ?? $r->source_power],
                ['key' => 'program', 'label' => 'Program', 'value' => fn ($r) => Site::programOptions()[$r->program] ?? $r->program, 'default' => false],
                ['key' => 'technician', 'label' => 'Teknisi', 'value' => fn ($r) => self::technicians($r) ?: '-'],
                ['key' => 'region', 'label' => 'Cluster', 'value' => fn ($r) => self::regions($r) ?: '-'],
                ['key' => 'location', 'label' => 'Location', 'value' => fn ($r) => $r->provinsi?->name ?: '-',
                    'sub' => fn ($r) => $r->kota?->name, 'tooltip' => fn ($r) => self::locationDetail($r)],
                ['key' => 'network', 'label' => 'Network', 'value' => fn ($r) => $r->gateway ?: '-', 'tooltip' => fn ($r) => self::networkDetail($r), 'default' => false],
                // Ringkasan Trouble Ticket terbuka yang terlihat oleh user ini.
                ['key' => 'tt', 'label' => 'TT Open', 'value' => fn ($r) => self::ticketStatusLabel($r)],
                ['key' => 'status', 'label' => 'Status', 'value' => fn ($r) => Site::STATUSES[$r->status] ?? $r->status, 'badge' => true, 'default' => false],
            ],
            'filters' => [
                ['key' => 'customer', 'label' => 'Customer', 'value' => fn ($r) => $r->customer?->name],
                ['key' => 'type', 'label' => 'Tipe', 'value' => fn ($r) => Site::typeOptions()[$r->type] ?? $r->type],
                ['key' => 'power', 'label' => 'Source Power', 'value' => fn ($r) => Site::sourcePowerOptions()[$r->source_power] ?? $r->source_power],
                ['key' => 'program', 'label' => 'Program', 'value' => fn ($r) => Site::programOptions()[$r->program] ?? $r->program],
                ['key' => 'region', 'label' => 'Cluster', 'value' => fn ($r) => self::regions($r) ?: '-'],
                ['key' => 'pulau', 'label' => 'Island', 'value' => fn ($r) => $r->pulau?->name],
                ['key' => 'provinsi', 'label' => 'Provinsi', 'value' => fn ($r) => $r->provinsi?->name],
                ['key' => 'kota', 'label' => 'Kota / Kabupaten', 'value' => fn ($r) => $r->kota?->name],
                ['key' => 'polarization', 'label' => 'Polarisasi', 'value' => fn ($r) => Site::POLARIZATIONS[$r->polarization] ?? $r->polarization],
                ['key' => 'tt', 'label' => 'TT Open', 'value' => fn ($r) => self::ticketStatusLabel($r)],
                ['key' => 'status', 'label' => 'Status', 'value' => fn ($r) => Site::STATUSES[$r->status] ?? $r->status],
            ],
            'dependents' => [
                'materialLinks' => 'masih memiliki Material terpasang',
                'accessoryLinks' => 'masih memiliki Accessories terpasang',
                'picLinks' => 'masih memiliki PIC terhubung',
                'troubleTickets' => 'sudah memiliki Trouble Ticket',
                'maintenances' => 'sudah memiliki catatan Preventive Maintenance',
            ],
            'describe' => fn ($r) => $r->tid.' - '.$r->sitename,
            // Role lihat-saja: tanpa Tambah / Edit / Hapus / Import.
            'readonly' => $this->isReadOnlyUser(),
        ];
    }

    protected function rules(Request $request, ?Model $record): array
    {
        // Tiap jenjang wilayah wajib berada di bawah jenjang induk yang dipilih.
        $geo = fn (string $table, string $parent, ?string $child = null) => array_merge(
            ['nullable', 'integer'],
            $child ? ['required_with:'.$child] : [],
            [Rule::exists($table, 'id')->where($parent, $request->input($parent))]
        );

        return [
            'tid' => ['required', 'string', 'max:50', Rule::unique('sites', 'tid')->ignore($record?->id)],
            'sitename' => ['required', 'string', 'max:200'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'type' => ['nullable', Rule::exists('site_types', 'code')],
            'source_power' => ['nullable', Rule::exists('site_source_powers', 'code')],
            'program' => ['required', Rule::exists('site_programs', 'code')],
            'status' => ['required', Rule::in(array_keys(Site::STATUSES))],
            'pulau_id' => ['nullable', 'integer', 'required_with:provinsi_id', 'exists:pulau,id'],
            'provinsi_id' => $geo('provinsi', 'pulau_id', 'kota_id'),
            'kota_id' => $geo('kota', 'provinsi_id', 'kecamatan_id'),
            'kecamatan_id' => $geo('kecamatan', 'kota_id', 'desa_id'),
            'desa_id' => $geo('desa', 'kecamatan_id'),
            'address' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'gateway' => ['nullable', 'string', 'max:150'],
            'outroute_id' => ['nullable', 'string', 'max:150'],
            'hub' => ['nullable', 'string', 'max:150'],
            'spotbeam' => ['nullable', 'string', 'max:150'],
            'polarization' => ['nullable', Rule::in(array_keys(Site::POLARIZATIONS))],
            // Field virtual: disimpan sebagai baris site_pics, bukan kolom sites.
            // Teknisi lama yang tidak diubah (customer pun sama) tetap boleh
            // disimpan walau kini di luar syarat, agar edit field lain tidak
            // tertolak; pilihan BARU wajib dari technicianOptions().
            'pic_id' => ['nullable', 'integer', function ($attribute, $value, $fail) use ($request, $record) {
                if ($record && (int) $value === (int) $record->pic_id
                    && (int) $request->input('customer_id') === (int) $record->customer_id) {
                    return;
                }
                if (! self::technicianAllowedFor('pic', (int) $value, (int) $request->input('customer_id'))) {
                    $fail('Teknisi harus user ber-role technician dari team yang melayani customer site ini.');
                }
            }],
        ];
    }

    protected function beforeCreate(Model $record, array $data): void
    {
        $record->status = 'ACTIVE';
    }

    /** Field form yang bukan kolom `sites` (lihat master()['fields']). */
    public const VIRTUAL_FIELDS = ['spv_id', 'pic_id'];

    /**
     * Peta user_id => [customer_id] untuk akun ACTIVE ber-role `technician`
     * yang menjadi anggota ACTIVE team ACTIVE. Customer-nya adalah customer
     * team tersebut (Master Data > Team; team_customers, atau teams.customer_id
     * selama tabel itu belum dimigrasi). Teknisi tanpa team bercustomer tidak
     * masuk peta. Dimuat sekali per request.
     *
     * @return array<int, array<int, int>>
     */
    public static function technicianTeamCustomers(): array
    {
        $attrs = request()->attributes;
        if ($attrs->has('site_tech_team_customers')) {
            return $attrs->get('site_tech_team_customers');
        }

        $map = [];
        if (Team::supportsCustomer()) {
            $members = DB::table('team_members')
                ->join('teams', 'teams.id', '=', 'team_members.team_id')
                ->join('users', 'users.id', '=', 'team_members.user_id')
                ->join('roles', 'roles.id', '=', 'users.role_id')
                ->where('team_members.status', 'ACTIVE')
                ->where('teams.status', 'ACTIVE')
                ->where('users.status', 'ACTIVE')
                ->whereRaw('LOWER(roles.name) = ?', [Site::TECHNICIAN_ROLE])
                ->get(['team_members.user_id', 'teams.id as team_id', 'teams.customer_id']);

            $teamCustomers = Team::supportsCustomerList()
                ? DB::table('team_customers')
                    ->whereIn('team_id', $members->pluck('team_id')->unique()->all())
                    ->get(['team_id', 'customer_id'])
                    ->groupBy('team_id')
                    ->map(fn ($rows) => $rows->pluck('customer_id')->all())
                : $members->filter(fn ($m) => $m->customer_id)
                    ->mapWithKeys(fn ($m) => [$m->team_id => [$m->customer_id]]);

            foreach ($members as $m) {
                foreach ($teamCustomers[$m->team_id] ?? [] as $customerId) {
                    $map[(int) $m->user_id][] = (int) $customerId;
                }
            }
            $map = array_map(fn ($ids) => array_values(array_unique($ids)), $map);
        }

        $attrs->set('site_tech_team_customers', $map);

        return $map;
    }

    /**
     * Pilihan field Teknisi: user ber-role `technician` dari team yang
     * melayani customer site (technicianTeamCustomers()). Nilai yang disimpan
     * tetap id PIC internal ACTIVE berjabatan Teknisi milik akun itu, karena
     * site_pics → master_pics dipakai Trouble Ticket (eligibleTechnicians,
     * picIdFor). Akun tanpa Master PIC internal berjabatan Teknisi tidak ikut.
     *
     * @return array<int, array{id:int, name:string, customers:array<int,int>}>
     */
    public static function technicianOptions(): array
    {
        $map = self::technicianTeamCustomers();
        if (! $map) {
            return [];
        }

        return MasterPic::with('user:id,name')
            ->where('type', 'internal')
            ->where('status', 'ACTIVE')
            ->where('position', 'like', '%'.Site::TECHNICIAN_POSITION.'%')
            ->whereIn('user_id', array_keys($map))
            ->get(['id', 'pic', 'user_id'])
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'name' => $p->user?->name ?? $p->pic,
                'customers' => $map[(int) $p->user_id] ?? [],
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * Apakah $id termasuk pilihan Teknisi ($kind = 'pic', id master_pics) atau
     * Teknisi Backup ($kind = 'backup', id users) untuk customer $customerId?
     */
    protected static function technicianAllowedFor(string $kind, int $id, int $customerId): bool
    {
        // Dimuat sekali per request: validasi Teknisi Backup memanggil ini per baris.
        $attrs = request()->attributes;
        $key = 'site_tech_options_'.$kind;
        if (! $attrs->has($key)) {
            $attrs->set($key, $kind === 'pic' ? self::technicianOptions() : self::backupTechnicianOptions());
        }
        $opt = collect($attrs->get($key))->firstWhere('id', $id);

        return $opt !== null && in_array($customerId, $opt['customers'], true);
    }

    /**
     * Pilihan Site Keeper: PIC aktif berjenis `site_keeper` di Personal Master
     * > PIC. Sebelum 19 Okt 2026 daftarnya diambil dari seluruh akun user
     * aktif, yang tidak ada kaitannya dengan data penjaga site. Nilai yang
     * disimpan tetap NAMA (kolom `site_keepers.name`), jadi hanya sumber
     * daftarnya yang berubah.
     */
    public static function keeperOptions(): array
    {
        return MasterPic::where('status', 'ACTIVE')
            ->where('type', Site::KEEPER_PIC_TYPE)
            ->orderBy('pic')
            ->pluck('pic')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Pilihan Teknisi Backup: user ber-role `technician` dari team yang
     * melayani customer site (technicianTeamCustomers()), lengkap dengan daftar
     * customer-nya — form hanya menampilkan teknisi customer yang dipilih.
     * Leader team tetap tidak ikut: ia menyetujui plan, bukan ditugaskan ke tiket.
     *
     * @return array<int, array{id:int, name:string, customers:array<int,int>}>
     */
    public static function backupTechnicianOptions(): array
    {
        $map = self::technicianTeamCustomers();
        if (! $map) {
            return [];
        }

        return User::query()
            ->whereIn('id', array_keys($map))
            ->whereNotIn('id', Team::query()
                ->where('status', 'ACTIVE')
                ->whereNotNull('leader_id')
                ->select('leader_id'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($u) => [
                'id' => (int) $u->id,
                'name' => $u->name,
                'customers' => $map[(int) $u->id] ?? [],
            ])
            ->values()
            ->all();
    }

    /**
     * Validasi baris Teknisi Backup (id user), boleh lebih dari satu. Teknisi
     * Backup yang sudah tersimpan di site ini (customer tidak berubah) tetap
     * boleh dipertahankan; tambahan baru wajib dari backupTechnicianOptions().
     */
    protected function backupTechnicianRules(Request $request, ?Site $site = null): array
    {
        $customerId = (int) $request->input('customer_id');
        $kept = $site && $customerId === (int) $site->customer_id
            ? $site->backupTechnicians()->pluck('user_id')->map(fn ($id) => (int) $id)->all()
            : [];

        return [
            'backup_technicians' => ['nullable', 'array', 'max:20'],
            'backup_technicians.*' => ['nullable', 'integer', function ($attribute, $value, $fail) use ($customerId, $kept) {
                if (in_array((int) $value, $kept, true)) {
                    return;
                }
                if (! self::technicianAllowedFor('backup', (int) $value, $customerId)) {
                    $fail('Teknisi Backup harus user ber-role technician dari team yang melayani customer site ini.');
                }
            }],
        ];
    }

    /**
     * Menyimpan daftar Teknisi Backup. Baris kosong diabaikan; daftar yang
     * dikirim menggantikan seluruh cadangan site. Form yang tidak mengirim
     * `backup_technicians` sama sekali tidak menyentuh data lama.
     */
    protected function syncBackupTechnicians(Site $site, Request $request): void
    {
        if (! $request->has('backup_technicians')) {
            return;
        }

        $ids = collect($request->input('backup_technicians', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        SiteBackupTechnician::where('site_id', $site->id)->delete();
        foreach ($ids as $id) {
            SiteBackupTechnician::create(['site_id' => $site->id, 'user_id' => $id]);
        }
    }

    /** Validasi baris Site Keeper (nama user), boleh lebih dari satu. */
    protected function keeperRules(): array
    {
        return [
            'keepers' => ['nullable', 'array', 'max:20'],
            'keepers.*.name' => ['nullable', 'string', 'max:150', 'exists:users,name'],
        ];
    }

    /**
     * Menyimpan daftar Site Keeper. Baris tanpa nama diabaikan; daftar yang
     * dikirim menggantikan seluruh penjaga site. Form yang tidak mengirim
     * `keepers` sama sekali tidak menyentuh data lama.
     */
    protected function syncSiteKeepers(Site $site, Request $request): void
    {
        if (! $request->has('keepers')) {
            return;
        }

        $rows = collect($request->input('keepers', []))
            ->map(fn ($k) => ['name' => trim((string) ($k['name'] ?? ''))])
            ->unique('name')
            ->filter(fn ($k) => $k['name'] !== '')
            ->values();

        SiteKeeper::where('site_id', $site->id)->delete();
        foreach ($rows as $row) {
            SiteKeeper::create([
                'site_id' => $site->id,
                'name' => $row['name'],
            ]);
        }
    }

    /**
     * Menyimpan Supervisor & Teknisi sebagai baris `site_pics`. Field yang
     * tidak dikirim tidak diartikan sebagai "lepas PIC-nya".
     */
    protected function syncSitePics(Site $site, array $virtual): void
    {
        $roles = ['spv_id' => Site::SUPERVISOR_POSITION, 'pic_id' => Site::TECHNICIAN_POSITION];

        foreach ($roles as $field => $position) {
            if (! array_key_exists($field, $virtual)) {
                continue;
            }
            $picId = $virtual[$field] ? (int) $virtual[$field] : null;

            $site->load('picLinks.pic');
            $current = $position === Site::SUPERVISOR_POSITION ? $site->supervisorPic() : $site->technicianPic();

            if ($current && $current->id !== $picId) {
                SitePic::where('site_id', $site->id)->where('pic_id', $current->id)->delete();
            }
            if ($picId && ! SitePic::where('site_id', $site->id)->where('pic_id', $picId)->exists()) {
                SitePic::create(['site_id' => $site->id, 'pic_id' => $picId]);
            }
        }

        $site->load('picLinks.pic');
    }

    /** Tooltip kolom Location: seluruh isian bagian Location di form site. */
    public static function locationDetail(Site $site): string
    {
        $rows = [
            'Island' => $site->pulau?->name,
            'Provinsi' => $site->provinsi?->name,
            'Kota / Kabupaten' => $site->kota?->name,
            'Kecamatan' => $site->kecamatan?->name,
            'Desa' => $site->desa?->name,
            'Koordinat' => $site->latitude !== null && $site->longitude !== null
                ? $site->latitude.', '.$site->longitude
                : null,
            'Address' => $site->address,
        ];

        return self::detailLines($rows, 'Lokasi belum diisi.');
    }

    /** Tooltip kolom Network: seluruh isian bagian Network di form site. */
    public static function networkDetail(Site $site): string
    {
        $rows = [
            'Gateway' => $site->gateway,
            'Outroute ID' => $site->outroute_id,
            'Hub' => $site->hub,
            'Spotbeam' => $site->spotbeam,
            'Polarisasi' => Site::POLARIZATIONS[$site->polarization] ?? $site->polarization,
        ];

        return self::detailLines($rows, 'Data network belum diisi.');
    }

    /** Baris "Label: nilai" untuk tooltip; baris kosong dilewati. */
    private static function detailLines(array $rows, string $empty): string
    {
        $lines = collect($rows)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v, $k) => $k.': '.$v)
            ->values();

        return $lines->isEmpty() ? $empty : $lines->implode(PHP_EOL);
    }

    /** Kolom TT Open: ringkasan tiket terbuka yang terlihat oleh user ini. */
    public static function ticketStatusLabel(Site $site): string
    {
        $open = self::openTicketCounts()[$site->id] ?? 0;

        return $open > 0 ? $open.' Tiket' : 'Tidak ada';
    }

    /**
     * Kolom Teknisi: HANYA teknisi site, yaitu PIC internal berjabatan
     * "teknisi" (field Teknisi di form). Supervisor & PIC internal lain yang
     * terpasang di site_pics sengaja tidak ikut - lihat tab PIC di detail site.
     */
    public static function technicians(Site $site): string
    {
        return $site->technicianPic()?->pic ?? '';
    }

    /**
     * Peta site_id => jumlah Trouble Ticket terbuka, hanya tiket yang terlihat
     * oleh user login (TroubleTicket::scopeVisibleTo). Dimuat sekali per request.
     */
    public static function openTicketCounts(): array
    {
        $attrs = request()->attributes;
        if (! $attrs->has('site_open_tt_map')) {
            $attrs->set('site_open_tt_map', TroubleTicket::visibleTo(auth()->user())
                ->open()
                ->toBase()
                ->selectRaw('site_id, COUNT(*) AS jumlah')
                ->groupBy('site_id')
                ->pluck('jumlah', 'site_id')
                ->map(fn ($n) => (int) $n)
                ->all());
        }

        return $attrs->get('site_open_tt_map');
    }

    /** Jumlah PIC Internal unik yang terhubung ke site mana pun. */
    /**
     * Jumlah teknisi yang DIMILIKI perusahaan, bukan yang sudah terpasang di
     * site: sumbernya master PIC internal aktif berjabatan "teknisi" — sama
     * persis dengan daftar pada dropdown Teknisi di form Site
     * (lihat picOptions()). Sebelum 14 Okt 2026 kartu ini menghitung PIC
     * internal yang sudah tertaut ke site, sehingga angkanya lebih kecil dari
     * isi dropdown dan kedua layar tampak bertentangan.
     */
    public static function technicianCount(): int
    {
        return (int) MasterPic::where('type', 'internal')
            ->where('status', 'ACTIVE')
            ->where('position', 'like', '%'.Site::TECHNICIAN_POSITION.'%')
            ->count();
    }

    /**
     * Cluster site = `sites.region_id` bila sudah diisi; bila belum (site lama),
     * jatuh ke turunan lama: cluster homebase tempat Technician site terdaftar.
     * Peta pic_id => [cluster] dimuat sekali per request.
     */
    public static function regions(Site $site): string
    {
        $attrs = request()->attributes;
        if (! $attrs->has('site_region_map')) {
            $attrs->set('site_region_map', HomebaseDetail::with('homebase.region:id,region_name')->get()
                ->groupBy('pic_id')
                ->map(fn ($rows) => $rows->map(fn ($d) => $d->homebase?->region?->region_name)->filter()->all()));
        }
        $map = $attrs->get('site_region_map');

        if ($site->region_id) {
            return (string) $site->region?->region_name;
        }

        return $site->picLinks
            ->filter(fn ($l) => $l->pic?->type === 'internal')
            ->flatMap(fn ($l) => $map[$l->pic_id] ?? [])
            ->unique()
            ->implode(', ');
    }

    public function edit($id)
    {
        $this->guardWrite();

        $site = Site::with(['picLinks.pic', 'keepers', 'backupTechnicians.user:id,name'])->findOrFail($id);
        $fields = $this->resolvedFields($this->formFields($site));

        return view('site.create', compact('fields', 'site'));
    }

    public function update(Request $request, $id)
    {
        $this->guardWrite();

        $site = Site::with('picLinks.pic')->findOrFail($id);
        $data = $this->validatedData($request, $site);
        $request->validate($this->keeperRules(), [], ['keepers.*.name' => 'Site Keeper']);
        $request->validate($this->backupTechnicianRules($request, $site), [], ['backup_technicians.*' => 'Teknisi Backup']);
        $virtual = array_intersect_key($data, array_flip(self::VIRTUAL_FIELDS));
        $data = array_diff_key($data, $virtual);

        DB::transaction(function () use ($site, $data, $virtual, $request) {
            $site->update($data);
            $this->syncSitePics($site, $virtual);
            $this->syncSiteKeepers($site, $request);
            $this->syncBackupTechnicians($site, $request);
        });

        UserActivityLog::record('master_data', 'update', 'Mengubah Site '.$site->tid.' - '.$site->sitename, $site);

        return redirect('/site')->with('success', 'Site berhasil diperbarui');
    }

    private function resolvedFields(array $fields): array
    {
        return collect($fields)->map(function ($f) {
            if (isset($f['options']) && $f['options'] instanceof \Closure) {
                $f['options'] = ($f['options'])();
            }

            return $f;
        })->all();
    }

    public function create()
    {
        $this->guardWrite();

        $fields = $this->resolvedFields($this->formFields(null));

        return view('site.create', compact('fields'));
    }

    public function store(Request $request)
    {
        $this->guardWrite();

        $data = $this->validatedData($request, null);
        $request->validate($this->keeperRules(), [], ['keepers.*.name' => 'Site Keeper']);
        $request->validate($this->backupTechnicianRules($request), [], ['backup_technicians.*' => 'Teknisi Backup']);
        $virtual = array_intersect_key($data, array_flip(self::VIRTUAL_FIELDS));
        $data = array_diff_key($data, $virtual);

        $site = DB::transaction(function () use ($data, $virtual, $request) {
            $site = new Site($data);
            $this->beforeCreate($site, $data);
            $site->save();
            $site->setRelation('picLinks', collect());
            $this->syncSitePics($site, $virtual);
            $this->syncSiteKeepers($site, $request);
            $this->syncBackupTechnicians($site, $request);

            return $site;
        });

        UserActivityLog::record('master_data', 'create', 'Menambah Site '.$site->tid.' - '.$site->sitename, $site);

        return redirect('/site')->with('success', 'Site berhasil ditambahkan');
    }

    /** Isi drawer detail (HTML parsial). */
    public function drawer($id)
    {
        $site = $this->findVisibleSite($id, [
            'customer:id,name', 'pulau', 'provinsi', 'kota', 'kecamatan', 'desa', 'keepers.user:id,name,phone,email',
            'backupTechnicians.user:id,name,phone,email',
            'picLinks.pic', 'materialLinks.material.category', 'accessoryLinks.accessory.category',
        ]);

        // Tab TT: tiket terbaru site ini yang terlihat oleh user login.
        $tickets = TroubleTicket::visibleTo(auth()->user())
            ->where('site_id', $site->id)
            ->with(['technician:id,name', 'severityRef'])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $readonly = $this->isReadOnlyUser();

        return view('site._drawer', compact('site', 'tickets', 'readonly'));
    }

    public function show($id)
    {
        $site = $this->findVisibleSite($id, [
            'customer:id,name', 'pulau', 'provinsi', 'kota', 'kecamatan', 'desa', 'keepers.user:id,name,phone,email',
            'backupTechnicians.user:id,name,phone,email',
            'materialLinks.material.category', 'accessoryLinks.accessory.category', 'picLinks.pic',
            'maintenances.items.material:id,product_code', 'documents.inputBy:id,name',
        ]);

        $availablePics = MasterPic::where('status', 'ACTIVE')
            ->whereNotIn('id', $site->picLinks->pluck('pic_id'))
            ->orderBy('pic')
            ->get(['id', 'pic', 'type', 'position']);

        // Hanya item milik customer yang sama dengan site, dan belum terpasang di site ini.
        $availableMaterials = MasterMaterial::where('customer_id', $site->customer_id)
            ->whereNotIn('id', $site->materialLinks->pluck('material_id'))
            ->orderBy('product_code')
            ->get(['id', 'product_code', 'serial_number']);

        $availableAccessories = Accessory::where('customer_id', $site->customer_id)
            ->whereNotIn('id', $site->accessoryLinks->pluck('accessories_id'))
            ->orderBy('accessories_code')
            ->get(['id', 'accessories_code', 'accessories_name']);

        $readonly = $this->isReadOnlyUser();

        return view('site.show', compact('site', 'availableMaterials', 'availableAccessories', 'availablePics', 'readonly'));
    }

    /**
     * Menyimpan satu catatan Preventive Maintenance.
     *
     * SN "sebelum" diambil dari material yang sedang terpasang (tab Assets),
     * SN "sesudah" diisi sesuai perangkat aktual di lapangan. Setiap SN sesudah
     * yang berbeda dari SN sekarang LANGSUNG MENGGANTI `master_material.serial_number`
     * material tersebut, sehingga tab Assets ikut terbarui. SN lama tetap
     * tersimpan sebagai `sn_before` di catatan ini.
     */
    public function storeMaintenance(Request $request, $id)
    {
        $this->guardWrite();

        $site = Site::with('materialLinks.material')->findOrFail($id);
        $materialIds = $site->materialLinks->pluck('material_id')->all();

        $data = $request->validate([
            'performed_at' => ['required', 'date'],
            'performed_by' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'integer', Rule::in($materialIds)],
            'items.*.sn_after' => ['nullable', 'string', 'max:150'],
        ], [], [
            'performed_at' => 'Tanggal Maintenance',
            'performed_by' => 'Pelaksana',
            'items.*.sn_after' => 'SN Sesudah',
        ]);

        $byId = $site->materialLinks->keyBy('material_id');

        $maintenance = DB::transaction(function () use ($site, $data, $byId) {
            $maintenance = SiteMaintenance::create([
                'site_id' => $site->id,
                'performed_at' => $data['performed_at'],
                'performed_by' => $data['performed_by'] ?? null,
                'notes' => $data['notes'] ?? null,
                'input_by' => auth()->id(),
            ]);

            foreach ($data['items'] as $row) {
                $material = $byId[(int) $row['material_id']]?->material;
                $before = $material?->serial_number;
                $after = trim((string) ($row['sn_after'] ?? '')) ?: null;

                SiteMaintenanceItem::create([
                    'maintenance_id' => $maintenance->id,
                    'material_id' => $material?->id,
                    'device_name' => $material?->product_code ?? '-',
                    'sn_before' => $before,
                    'sn_after' => $after,
                ]);

                // SN sesudah menggantikan SN material; yang kosong/sama dilewati.
                if ($material && $after !== null && $after !== $before) {
                    $material->update(['serial_number' => $after]);
                }
            }

            return $maintenance;
        });

        $diganti = $maintenance->items()->whereNotNull('sn_after')->count();
        UserActivityLog::record('master_data', 'create',
            'Mencatat Preventive Maintenance Site '.$site->tid.' ('.$diganti.' SN diperbarui)', $maintenance);

        return back()->with('success', 'Preventive Maintenance tersimpan'.($diganti ? '; '.$diganti.' serial number diperbarui' : ''));
    }

    /** Mengunggah dokumen site ke disk `secure` (bukan disk public). */
    public function addDocument(Request $request, $id)
    {
        $this->guardWrite();

        $site = Site::findOrFail($id);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ], [], ['title' => 'Judul Dokumen', 'file' => 'Berkas']);

        $file = $request->file('file');
        $path = $file->store('site_documents', 'secure');

        SiteDocument::create([
            'site_id' => $site->id,
            'title' => ($data['title'] ?? null) ?: $file->getClientOriginalName(),
            'file_path' => $path,
            'file_name' => basename($path),
            'file_type' => $file->getClientMimeType(),
            'input_by' => auth()->id(),
        ]);

        UserActivityLog::record('master_data', 'create', 'Menambah dokumen Site '.$site->tid, $site);

        return back()->with('success', 'Dokumen berhasil diunggah');
    }

    public function removeDocument($id, $docId)
    {
        $this->guardWrite();

        $site = Site::findOrFail($id);
        $doc = SiteDocument::where('site_id', $site->id)->findOrFail($docId);

        DB::transaction(function () use ($doc) {
            Storage::disk('secure')->delete($doc->file_path);
            $doc->delete();
        });

        UserActivityLog::record('master_data', 'delete', 'Menghapus dokumen Site '.$site->tid.' - '.$doc->title, $site);

        return back()->with('success', 'Dokumen berhasil dihapus');
    }

    public function addPic(Request $request, $id)
    {
        $this->guardWrite();

        $site = Site::findOrFail($id);

        $data = $request->validate([
            'pic_id' => [
                'required', 'integer',
                Rule::exists('master_pics', 'id')->where('status', 'ACTIVE'),
                Rule::unique('site_pics', 'pic_id')->where('site_id', $site->id),
            ],
        ], [
            'pic_id.unique' => 'PIC ini sudah terhubung ke site tersebut.',
        ], ['pic_id' => 'PIC']);

        $link = SitePic::create(['site_id' => $site->id, 'pic_id' => $data['pic_id']]);

        UserActivityLog::record('master_data', 'create', 'Menghubungkan PIC #'.$link->pic_id.' ke Site '.$site->tid, $link);

        return redirect('/site/'.$site->id)->with('success', 'PIC berhasil dihubungkan ke site');
    }

    public function removePic($id, $linkId)
    {
        $this->guardWrite();

        $link = SitePic::where('site_id', $id)->findOrFail($linkId);
        $link->delete();

        UserActivityLog::record('master_data', 'delete', 'Melepas PIC #'.$link->pic_id.' dari Site #'.$id);

        return redirect('/site/'.$id)->with('success', 'PIC berhasil dilepas dari site');
    }

    public function addMaterial(Request $request, $id)
    {
        $this->guardWrite();

        $site = Site::findOrFail($id);

        $data = $request->validate([
            'material_id' => [
                'required', 'integer',
                Rule::exists('master_material', 'id')->where('customer_id', $site->customer_id),
                Rule::unique('site_material', 'material_id')->where('site_id', $site->id),
            ],
        ], [
            'material_id.exists' => 'Material tidak ditemukan atau bukan milik customer site ini.',
            'material_id.unique' => 'Material ini sudah terpasang di site tersebut.',
        ], ['material_id' => 'Material']);

        $link = SiteMaterial::create(['site_id' => $site->id, 'material_id' => $data['material_id']]);

        UserActivityLog::record('master_data', 'create', 'Memasang Material #'.$link->material_id.' ke Site '.$site->tid, $link);

        return redirect('/site/'.$site->id)->with('success', 'Material berhasil ditambahkan ke site');
    }

    public function removeMaterial($id, $linkId)
    {
        $this->guardWrite();

        $link = SiteMaterial::where('site_id', $id)->findOrFail($linkId);
        $link->delete();

        UserActivityLog::record('master_data', 'delete', 'Melepas Material #'.$link->material_id.' dari Site #'.$id);

        return redirect('/site/'.$id)->with('success', 'Material berhasil dilepas dari site');
    }

    public function addAccessory(Request $request, $id)
    {
        $this->guardWrite();

        $site = Site::findOrFail($id);

        $data = $request->validate([
            'accessories_id' => [
                'required', 'integer',
                Rule::exists('accessories', 'id')->where('customer_id', $site->customer_id),
                Rule::unique('site_accessories', 'accessories_id')->where('site_id', $site->id),
            ],
            'qty' => ['required', 'integer', 'min:1', 'max:1000000'],
        ], [
            'accessories_id.exists' => 'Accessories tidak ditemukan atau bukan milik customer site ini.',
            'accessories_id.unique' => 'Accessories ini sudah ada di site tersebut — ubah qty-nya saja.',
        ], ['accessories_id' => 'Accessories', 'qty' => 'Qty']);

        $link = SiteAccessory::create(['site_id' => $site->id] + $data);

        UserActivityLog::record('master_data', 'create', 'Memasang Accessories #'.$link->accessories_id.' (qty '.$link->qty.') ke Site '.$site->tid, $link);

        return redirect('/site/'.$site->id)->with('success', 'Accessories berhasil ditambahkan ke site');
    }

    public function updateAccessory(Request $request, $id, $linkId)
    {
        $this->guardWrite();

        $link = SiteAccessory::where('site_id', $id)->findOrFail($linkId);

        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:1', 'max:1000000'],
        ], [], ['qty' => 'Qty']);

        $old = $link->qty;
        $link->update(['qty' => $data['qty']]);

        UserActivityLog::record('master_data', 'update', 'Mengubah qty Accessories #'.$link->accessories_id.' di Site #'.$id.' dari '.$old.' ke '.$link->qty, $link);

        return redirect('/site/'.$id)->with('success', 'Qty accessories berhasil diperbarui');
    }

    public function removeAccessory($id, $linkId)
    {
        $this->guardWrite();

        $link = SiteAccessory::where('site_id', $id)->findOrFail($linkId);
        $link->delete();

        UserActivityLog::record('master_data', 'delete', 'Melepas Accessories #'.$link->accessories_id.' dari Site #'.$id);

        return redirect('/site/'.$id)->with('success', 'Accessories berhasil dilepas dari site');
    }
}
