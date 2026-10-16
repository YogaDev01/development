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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SiteController extends Controller
{
    use ImportsMasterRecords;
    use ManagesMasterRecords;

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
                'Tipe Bangunan' => 'Pendidikan',
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
                    'type' => self::matchOption(Site::TYPES, $c['Tipe Bangunan']),
                    'source_power' => self::matchOption(Site::SOURCE_POWERS, $c['Source Power']),
                    'program' => self::matchOption(Site::PROGRAMS, $c['Program']),
                    'address' => blank($c['Alamat']) ? null : $c['Alamat'],
                    'latitude' => blank($c['Latitude']) ? null : $c['Latitude'],
                    'longitude' => blank($c['Longitude']) ? null : $c['Longitude'],
                ], $geo), $errors];
            },
            'reference' => fn () => [
                'Customer (nama)' => Customer::orderBy('name')->pluck('name')->all(),
                'Tipe Bangunan' => array_values(Site::TYPES),
                'Source Power' => array_values(Site::SOURCE_POWERS),
                'Program' => array_values(Site::PROGRAMS),
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
            'import' => true,
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
                'type' => ['label' => 'Tipe', 'type' => 'select', 'options' => Site::TYPES, 'required' => true, 'searchable' => true, 'col' => 6],
                'source_power' => ['label' => 'Source Power', 'type' => 'select', 'options' => Site::SOURCE_POWERS, 'required' => true, 'searchable' => true, 'col' => 6],
                'program' => ['label' => 'Program', 'type' => 'select', 'options' => Site::PROGRAMS, 'required' => true, 'searchable' => true, 'col' => 6],
                // Site baru selalu ACTIVE (beforeCreate); status hanya bisa diubah saat edit.
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => Site::STATUSES, 'required' => true, 'searchable' => true, 'col' => 6, 'edit_only' => true],
                'customer_id' => ['label' => 'Customer', 'type' => 'select', 'options' => fn () => Customer::orderBy('name')->pluck('name', 'id')->all(), 'required' => true, 'searchable' => true, 'col' => 6],
                // Homebase, Cluster & Supervisor tidak lagi diisi di form: ketiganya
                // diturunkan dari Teknisi yang dipilih - lihat self::regions()
                // yang membaca homebase/region si teknisi.
                'pic_id' => ['label' => 'Teknisi', 'type' => 'select', 'options' => fn () => self::picOptions(Site::TECHNICIAN_POSITION), 'searchable' => true, 'col' => 6, 'virtual' => true],
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
                ['key' => 'sitename', 'label' => 'Nama Site', 'value' => fn ($r) => $r->sitename, 'truncate' => 60,
                    'sub' => fn ($r) => $r->customer?->name],
                ['key' => 'type', 'label' => 'Tipe', 'value' => fn ($r) => Site::TYPES[$r->type] ?? $r->type, 'default' => false],
                ['key' => 'power', 'label' => 'Source Power', 'value' => fn ($r) => Site::SOURCE_POWERS[$r->source_power] ?? $r->source_power],
                ['key' => 'program', 'label' => 'Program', 'value' => fn ($r) => Site::PROGRAMS[$r->program] ?? $r->program, 'default' => false],
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
                ['key' => 'type', 'label' => 'Tipe', 'value' => fn ($r) => Site::TYPES[$r->type] ?? $r->type],
                ['key' => 'power', 'label' => 'Source Power', 'value' => fn ($r) => Site::SOURCE_POWERS[$r->source_power] ?? $r->source_power],
                ['key' => 'program', 'label' => 'Program', 'value' => fn ($r) => Site::PROGRAMS[$r->program] ?? $r->program],
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
            'type' => ['required', Rule::in(array_keys(Site::TYPES))],
            'source_power' => ['required', Rule::in(array_keys(Site::SOURCE_POWERS))],
            'program' => ['required', Rule::in(array_keys(Site::PROGRAMS))],
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
            'pic_id' => ['nullable', 'integer', Rule::exists('master_pics', 'id')->where('type', 'internal')],
        ];
    }

    protected function beforeCreate(Model $record, array $data): void
    {
        $record->status = 'ACTIVE';
    }

    /** Field form yang bukan kolom `sites` (lihat master()['fields']). */
    public const VIRTUAL_FIELDS = ['spv_id', 'pic_id'];

    /** PIC internal yang jabatannya mengandung kata $position. */
    protected static function picOptions(string $position): array
    {
        return MasterPic::where('type', 'internal')
            ->where('status', 'ACTIVE')
            ->where('position', 'like', '%'.$position.'%')
            ->orderBy('pic')
            ->get(['id', 'pic', 'position'])
            ->mapWithKeys(fn ($p) => [$p->id => $p->pic.($p->position ? ' - '.$p->position : '')])
            ->all();
    }

    /** Pilihan Site Keeper: nama user aktif. */
    public static function keeperOptions(): array
    {
        return User::where('status', 'ACTIVE')
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Pilihan Teknisi Backup: akun ACTIVE milik PIC internal ACTIVE yang
     * jabatannya Teknisi, lengkap dengan daftar customer tempat ia menjadi PIC
     * site — dipakai form untuk menampilkan hanya teknisi customer yang sama
     * dengan site. Supervisor, CSO, dan jabatan lain tidak ikut; begitu juga
     * leader team: ia menyetujui plan, bukan ditugaskan ke tiket.
     *
     * @return array<int, array{id:int, name:string, customers:array<int,int>}>
     */
    public static function backupTechnicianOptions(): array
    {
        $rows = User::query()
            ->where('users.status', 'ACTIVE')
            ->whereNotIn('users.id', Team::query()
                ->where('status', 'ACTIVE')
                ->whereNotNull('leader_id')
                ->select('leader_id'))
            ->join('master_pics', 'master_pics.user_id', '=', 'users.id')
            ->where('master_pics.type', 'internal')
            ->where('master_pics.status', 'ACTIVE')
            ->where('master_pics.position', 'like', '%'.Site::TECHNICIAN_POSITION.'%')
            ->leftJoin('site_pics', 'site_pics.pic_id', '=', 'master_pics.id')
            ->leftJoin('sites', 'sites.id', '=', 'site_pics.site_id')
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'sites.customer_id']);

        return $rows->groupBy('id')->map(fn ($g) => [
            'id' => (int) $g->first()->id,
            'name' => $g->first()->name,
            'customers' => $g->pluck('customer_id')->filter()->map(fn ($c) => (int) $c)->unique()->values()->all(),
        ])->values()->all();
    }

    /** Validasi baris Teknisi Backup (id user), boleh lebih dari satu. */
    protected function backupTechnicianRules(): array
    {
        return [
            'backup_technicians' => ['nullable', 'array', 'max:20'],
            'backup_technicians.*' => ['nullable', 'integer', 'exists:users,id'],
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
        $site = Site::with(['picLinks.pic', 'keepers', 'backupTechnicians'])->findOrFail($id);
        $fields = $this->resolvedFields($this->formFields($site));

        return view('site.create', compact('fields', 'site'));
    }

    public function update(Request $request, $id)
    {
        $site = Site::with('picLinks.pic')->findOrFail($id);
        $data = $this->validatedData($request, $site);
        $request->validate($this->keeperRules(), [], ['keepers.*.name' => 'Site Keeper']);
        $request->validate($this->backupTechnicianRules(), [], ['backup_technicians.*' => 'Teknisi Backup']);
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
        $fields = $this->resolvedFields($this->formFields(null));

        return view('site.create', compact('fields'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request, null);
        $request->validate($this->keeperRules(), [], ['keepers.*.name' => 'Site Keeper']);
        $request->validate($this->backupTechnicianRules(), [], ['backup_technicians.*' => 'Teknisi Backup']);
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
        $site = Site::with([
            'customer:id,name', 'pulau', 'provinsi', 'kota', 'kecamatan', 'desa', 'keepers.user:id,name,phone,email',
            'backupTechnicians.user:id,name,phone,email',
            'picLinks.pic', 'materialLinks.material.category', 'accessoryLinks.accessory.category',
        ])->findOrFail($id);

        // Tab TT: tiket terbaru site ini yang terlihat oleh user login.
        $tickets = TroubleTicket::visibleTo(auth()->user())
            ->where('site_id', $site->id)
            ->with(['technician:id,name', 'severityRef'])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return view('site._drawer', compact('site', 'tickets'));
    }

    public function show($id)
    {
        $site = Site::with([
            'customer:id,name', 'pulau', 'provinsi', 'kota', 'kecamatan', 'desa', 'keepers.user:id,name,phone,email',
            'backupTechnicians.user:id,name,phone,email',
            'materialLinks.material.category', 'accessoryLinks.accessory.category', 'picLinks.pic',
            'maintenances.items.material:id,product_code', 'documents.inputBy:id,name',
        ])->findOrFail($id);

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

        return view('site.show', compact('site', 'availableMaterials', 'availableAccessories', 'availablePics'));
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
        $link = SitePic::where('site_id', $id)->findOrFail($linkId);
        $link->delete();

        UserActivityLog::record('master_data', 'delete', 'Melepas PIC #'.$link->pic_id.' dari Site #'.$id);

        return redirect('/site/'.$id)->with('success', 'PIC berhasil dilepas dari site');
    }

    public function addMaterial(Request $request, $id)
    {
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
        $link = SiteMaterial::where('site_id', $id)->findOrFail($linkId);
        $link->delete();

        UserActivityLog::record('master_data', 'delete', 'Melepas Material #'.$link->material_id.' dari Site #'.$id);

        return redirect('/site/'.$id)->with('success', 'Material berhasil dilepas dari site');
    }

    public function addAccessory(Request $request, $id)
    {
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
        $link = SiteAccessory::where('site_id', $id)->findOrFail($linkId);
        $link->delete();

        UserActivityLog::record('master_data', 'delete', 'Melepas Accessories #'.$link->accessories_id.' dari Site #'.$id);

        return redirect('/site/'.$id)->with('success', 'Accessories berhasil dilepas dari site');
    }
}
