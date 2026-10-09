<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ImportsMasterRecords;
use App\Http\Controllers\Concerns\ManagesMasterRecords;
use Illuminate\Support\Facades\DB;
use App\Models\Accessory;
use App\Models\Customer;
use App\Models\MasterMaterial;
use App\Models\Network;
use App\Models\Pulau;
use App\Models\Site;
use App\Models\SiteAccessory;
use App\Models\SiteMaterial;
use App\Models\SitePic;
use App\Models\MasterPic;
use App\Models\UserActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SiteController extends Controller
{
    use ManagesMasterRecords;
    use ImportsMasterRecords;

    protected function importConfig(): array
    {
        $customers = Customer::get(['id', 'name', 'initial']);
        $byName    = $customers->mapWithKeys(fn ($c) => [mb_strtolower($c->name) => $c->id]);
        $byInitial = $customers->filter(fn ($c) => $c->initial)->mapWithKeys(fn ($c) => [mb_strtolower($c->initial) => $c->id]);

        // Cache per import: nama wilayah dicari di bawah induknya.
        $memo = [];
        $find = function (string $table, ?string $parentCol, $parentId, $name) use (&$memo) {
            $name = trim((string) $name);
            if ($name === '') {
                return null;
            }
            $key = $table . '|' . $parentId . '|' . mb_strtolower($name);
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
                'Site ID'         => 'TID-0001',
                'Nama Site'       => 'SD Negeri 1 Contoh',
                'Customer'        => 'BTS',
                'Tipe Bangunan'   => 'Pendidikan',
                'Source Power'    => 'PLN',
                'Program'         => 'Akses Internet',
                'Network ID'      => '',
                'Provinsi'        => 'Jawa Barat',
                'Kota/Kabupaten'  => 'Kota Bandung',
                'Kecamatan'       => '',
                'Desa/Kelurahan'  => '',
                'Alamat'          => 'Jl. Contoh No. 1',
                'Latitude'        => '-6.9147440',
                'Longitude'       => '107.6098100',
            ],
            'unique' => ['tid'],
            'map' => function (array $c) use ($byName, $byInitial, $find) {
                $errors = [];

                $cust       = mb_strtolower((string) $c['Customer']);
                $customerId = $byName[$cust] ?? $byInitial[$cust] ?? null;
                if ($cust !== '' && ! $customerId) {
                    $errors['customer_id'] = "Customer '{$c['Customer']}' tidak ditemukan (isi nama atau inisial).";
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
                    'tid'          => $c['Site ID'] === null ? null : (string) $c['Site ID'],
                    'sitename'     => $c['Nama Site'],
                    'customer_id'  => $customerId,
                    'network_id'   => blank($c['Network ID']) ? null : $c['Network ID'],
                    'type'         => self::matchOption(Site::TYPES, $c['Tipe Bangunan']),
                    'source_power' => self::matchOption(Site::SOURCE_POWERS, $c['Source Power']),
                    'program'      => self::matchOption(Site::PROGRAMS, $c['Program']),
                    'address'      => blank($c['Alamat']) ? null : $c['Alamat'],
                    'latitude'     => blank($c['Latitude']) ? null : $c['Latitude'],
                    'longitude'    => blank($c['Longitude']) ? null : $c['Longitude'],
                ], $geo), $errors];
            },
            'reference' => fn () => [
                'Customer (nama)'  => Customer::orderBy('name')->pluck('name')->all(),
                'Tipe Bangunan'    => array_values(Site::TYPES),
                'Source Power'     => array_values(Site::SOURCE_POWERS),
                'Program'          => array_values(Site::PROGRAMS),
                'Network ID | Nama' => Network::orderBy('id')->get()->map(fn ($n) => $n->id . ' | ' . $n->label)->all(),
                'Catatan'          => [
                    'Wilayah diisi dengan NAMA persis seperti di menu Wilayah.',
                    'Pulau terisi otomatis dari Provinsi.',
                    'Kolom wilayah boleh kosong dari bawah (mis. hanya Provinsi + Kota).',
                    'Latitude & Longitude diisi berpasangan atau dikosongkan keduanya.',
                ],
            ],
        ];
    }

    protected function master(): array
    {
        return [
            'model'    => Site::class,
            'url'      => '/site',
            'title'    => 'Sites',
            'singular' => 'Site',
            'icon'     => 'fas fa-broadcast-tower',
            'with'     => ['customer:id,name', 'kota:id,name', 'provinsi:id,name', 'picLinks.pic:id,pic,type'],
            'create_url' => '/site/create',
            'edit_url' => '/site/{id}/edit',
            'drawer'   => true,
            'import'   => true,
            'numbered' => false,
            'compact'  => true,
            'column_picker' => 'site-index',
            'hide_inactive' => true,
            'cards'    => fn () => [
                ['Total TT', \App\Models\TroubleTicket::visibleTo(auth()->user())->count(), 'warning', 'fas fa-ticket-alt',
                    array_sum(self::openTicketCounts()) . ' terbuka'],
                ['Total Technician', self::technicianCount(), 'info', 'fas fa-user-cog'],
            ],
            'modal'    => 'modal-lg',
            'fields'   => [
                'tid'          => ['label' => 'Site ID', 'type' => 'text', 'required' => true, 'col' => 4, 'section' => 'Identitas Site'],
                'sitename'     => ['label' => 'Nama Site', 'type' => 'text', 'required' => true, 'col' => 8],
                'customer_id'  => ['label' => 'Customer', 'type' => 'select', 'options' => fn () => Customer::orderBy('name')->pluck('name', 'id')->all(), 'required' => true, 'searchable' => true, 'col' => 6],
                'network_id'   => ['label' => 'Network', 'type' => 'select', 'options' => fn () => Network::orderBy('penyedia_lc')->get()->mapWithKeys(fn ($n) => [$n->id => $n->label])->all(), 'searchable' => true, 'col' => 6],
                'type'         => ['label' => 'Tipe Bangunan', 'type' => 'select', 'options' => Site::TYPES, 'required' => true, 'searchable' => true, 'col' => 4, 'section' => 'Klasifikasi'],
                'source_power' => ['label' => 'Source Power', 'type' => 'select', 'options' => Site::SOURCE_POWERS, 'required' => true, 'searchable' => true, 'col' => 4],
                'program'      => ['label' => 'Program', 'type' => 'select', 'options' => Site::PROGRAMS, 'required' => true, 'searchable' => true, 'col' => 4],
                // Site baru selalu ACTIVE (beforeCreate); status hanya bisa diubah saat edit.
                'status'       => ['label' => 'Status', 'type' => 'select', 'options' => Site::STATUSES, 'required' => true, 'searchable' => true, 'col' => 4, 'edit_only' => true],
                'pulau_id'     => ['label' => 'Pulau', 'type' => 'select', 'options' => fn () => Pulau::orderBy('name')->pluck('name', 'id')->all(), 'searchable' => true, 'col' => 4, 'cascade_child' => 'provinsi_id', 'section' => 'Lokasi'],
                'provinsi_id'  => ['label' => 'Provinsi', 'type' => 'cascade', 'level' => 'provinsi', 'parent' => 'pulau_id', 'col' => 4, 'cascade_child' => 'kota_id'],
                'kota_id'      => ['label' => 'Kota/Kabupaten', 'type' => 'cascade', 'level' => 'kota', 'parent' => 'provinsi_id', 'col' => 4, 'cascade_child' => 'kecamatan_id'],
                'kecamatan_id' => ['label' => 'Kecamatan', 'type' => 'cascade', 'level' => 'kecamatan', 'parent' => 'kota_id', 'col' => 6, 'cascade_child' => 'desa_id'],
                'desa_id'      => ['label' => 'Desa/Kelurahan', 'type' => 'cascade', 'level' => 'desa', 'parent' => 'kecamatan_id', 'col' => 6],
                'address'      => ['label' => 'Alamat', 'type' => 'textarea'],
                'latitude'     => ['label' => 'Latitude', 'type' => 'decimal', 'col' => 6, 'placeholder' => '-6.2000000'],
                'longitude'    => ['label' => 'Longitude', 'type' => 'decimal', 'col' => 6, 'placeholder' => '106.8166660'],
            ],
            'columns' => [
                ['key' => 'tid', 'label' => 'Site ID', 'value' => fn ($r) => $r->tid, 'strong' => true],
                ['key' => 'sitename', 'label' => 'Nama Site', 'value' => fn ($r) => $r->sitename, 'truncate' => 60],
                ['key' => 'customer', 'label' => 'Customer', 'value' => fn ($r) => $r->customer?->name],
                ['key' => 'type', 'label' => 'Tipe', 'value' => fn ($r) => Site::TYPES[$r->type] ?? $r->type, 'default' => false],
                ['key' => 'program', 'label' => 'Program', 'value' => fn ($r) => Site::PROGRAMS[$r->program] ?? $r->program, 'default' => false],
                ['key' => 'power', 'label' => 'Power', 'value' => fn ($r) => Site::SOURCE_POWERS[$r->source_power] ?? $r->source_power],
                ['key' => 'provinsi', 'label' => 'Provinsi', 'value' => fn ($r) => $r->provinsi?->name ?? '-', 'default' => false],
                ['key' => 'kota', 'label' => 'Kota', 'value' => fn ($r) => $r->kota?->name ?? '-', 'default' => false],
                ['key' => 'region', 'label' => 'Region', 'value' => fn ($r) => self::regions($r) ?: '-'],
                ['key' => 'technician', 'label' => 'Technician', 'value' => fn ($r) => self::technicians($r) ?: '-'],
                // Jumlah Trouble Ticket terbuka yang terlihat oleh user ini.
                ['key' => 'tt', 'label' => 'TT', 'value' => fn ($r) => self::openTicketCounts()[$r->id] ?? 0],
                ['key' => 'status', 'label' => 'Status', 'value' => fn ($r) => Site::STATUSES[$r->status] ?? $r->status, 'badge' => true, 'default' => false],
            ],
            'filters' => [
                ['key' => 'customer', 'label' => 'Customer', 'value' => fn ($r) => $r->customer?->name],
                ['key' => 'region', 'label' => 'Region', 'value' => fn ($r) => self::regions($r) ?: '-'],
                ['key' => 'program', 'label' => 'Program', 'value' => fn ($r) => Site::PROGRAMS[$r->program] ?? $r->program],
                ['key' => 'provinsi', 'label' => 'Provinsi', 'value' => fn ($r) => $r->provinsi?->name],
                ['key' => 'status', 'label' => 'Status', 'value' => fn ($r) => Site::STATUSES[$r->status] ?? $r->status],
            ],
            'dependents' => [
                'materialLinks'  => 'masih memiliki Material terpasang',
                'accessoryLinks' => 'masih memiliki Accessories terpasang',
                'picLinks'       => 'masih memiliki PIC terhubung',
                'troubleTickets' => 'sudah memiliki Trouble Ticket',
            ],
            'describe' => fn ($r) => $r->tid . ' - ' . $r->sitename,
        ];
    }

    protected function rules(Request $request, ?Model $record): array
    {
        // Tiap jenjang wilayah wajib berada di bawah jenjang induk yang dipilih.
        $geo = fn (string $table, string $parent, ?string $child = null) => array_merge(
            ['nullable', 'integer'],
            $child ? ['required_with:' . $child] : [],
            [Rule::exists($table, 'id')->where($parent, $request->input($parent))]
        );

        return [
            'tid'          => ['required', 'string', 'max:50', Rule::unique('sites', 'tid')->ignore($record?->id)],
            'sitename'     => ['required', 'string', 'max:200'],
            'customer_id'  => ['required', 'integer', 'exists:customers,id'],
            'network_id'   => ['nullable', 'integer', 'exists:network,id'],
            'type'         => ['required', Rule::in(array_keys(Site::TYPES))],
            'source_power' => ['required', Rule::in(array_keys(Site::SOURCE_POWERS))],
            'program'      => ['required', Rule::in(array_keys(Site::PROGRAMS))],
            'status'       => ['required', Rule::in(array_keys(Site::STATUSES))],
            'pulau_id'     => ['nullable', 'integer', 'required_with:provinsi_id', 'exists:pulau,id'],
            'provinsi_id'  => $geo('provinsi', 'pulau_id', 'kota_id'),
            'kota_id'      => $geo('kota', 'provinsi_id', 'kecamatan_id'),
            'kecamatan_id' => $geo('kecamatan', 'kota_id', 'desa_id'),
            'desa_id'      => $geo('desa', 'kecamatan_id'),
            'address'      => ['nullable', 'string', 'max:1000'],
            'latitude'     => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude'    => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ];
    }

    protected function beforeCreate(Model $record, array $data): void
    {
        $record->status = 'ACTIVE';
    }

    /** Technician = PIC site bertipe Internal. */
    public static function technicians(Site $site): string
    {
        return $site->picLinks
            ->filter(fn ($l) => $l->pic?->type === 'internal')
            ->map(fn ($l) => $l->pic->pic)
            ->implode(', ');
    }

    /**
     * Peta site_id => jumlah Trouble Ticket terbuka, hanya tiket yang terlihat
     * oleh user login (TroubleTicket::scopeVisibleTo). Dimuat sekali per request.
     */
    public static function openTicketCounts(): array
    {
        $attrs = request()->attributes;
        if (! $attrs->has('site_open_tt_map')) {
            $attrs->set('site_open_tt_map', \App\Models\TroubleTicket::visibleTo(auth()->user())
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
    public static function technicianCount(): int
    {
        return (int) SitePic::query()
            ->join('master_pics', 'master_pics.id', '=', 'site_pics.pic_id')
            ->where('master_pics.type', 'internal')
            ->distinct()
            ->count('site_pics.pic_id');
    }

    /**
     * Region site = region homebase tempat Technician site (PIC internal) terdaftar.
     * Peta pic_id => [region] dimuat sekali per request.
     */
    public static function regions(Site $site): string
    {
        $attrs = request()->attributes;
        if (! $attrs->has('site_region_map')) {
            $attrs->set('site_region_map', \App\Models\HomebaseDetail::with('homebase.region:id,region_name')->get()
                ->groupBy('pic_id')
                ->map(fn ($rows) => $rows->map(fn ($d) => $d->homebase?->region?->region_name)->filter()->all()));
        }
        $map = $attrs->get('site_region_map');

        return $site->picLinks
            ->filter(fn ($l) => $l->pic?->type === 'internal')
            ->flatMap(fn ($l) => $map[$l->pic_id] ?? [])
            ->unique()
            ->implode(', ');
    }

    public function edit($id)
    {
        $site = Site::with('picLinks')->findOrFail($id);
        $fields = $this->resolvedFields($this->formFields($site));
        $pics = MasterPic::where('status', 'ACTIVE')
            ->orWhereIn('id', $site->picLinks->pluck('pic_id'))
            ->orderBy('pic')->get(['id', 'pic', 'type', 'position', 'status']);

        return view('site.create', compact('fields', 'pics', 'site'));
    }

    public function update(Request $request, $id)
    {
        $site = Site::findOrFail($id);
        $data = $this->validatedData($request, $site);

        // PIC yang sudah terhubung boleh tetap dipertahankan walau kini INACTIVE.
        $kept = $site->picLinks()->pluck('pic_id')->all();
        $picIds = $request->validate([
            'pic_ids'   => ['nullable', 'array', 'max:50'],
            'pic_ids.*' => ['integer', 'distinct', Rule::exists('master_pics', 'id')->where(
                fn ($q) => $q->where('status', 'ACTIVE')->orWhereIn('id', $kept ?: [0])
            )],
        ], [], ['pic_ids' => 'PIC', 'pic_ids.*' => 'PIC'])['pic_ids'] ?? [];

        DB::transaction(function () use ($site, $data, $picIds, $kept) {
            $site->update($data);

            if (! request()->boolean('pic_sync')) {
                return;
            }
            $picIds = array_map('intval', array_unique($picIds));
            SitePic::where('site_id', $site->id)->whereNotIn('pic_id', $picIds ?: [0])->delete();
            foreach (array_diff($picIds, $kept) as $picId) {
                SitePic::create(['site_id' => $site->id, 'pic_id' => $picId]);
            }
        });

        UserActivityLog::record('master_data', 'update', 'Mengubah Site ' . $site->tid . ' - ' . $site->sitename, $site);

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
        $fields = collect($this->formFields(null))->map(function ($f) {
            if (isset($f['options']) && $f['options'] instanceof \Closure) {
                $f['options'] = ($f['options'])();
            }
            return $f;
        })->all();

        $pics = MasterPic::where('status', 'ACTIVE')->orderBy('pic')->get(['id', 'pic', 'type', 'position']);

        return view('site.create', compact('fields', 'pics'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request, null);
        $picIds = $request->validate([
            'pic_ids'   => ['nullable', 'array', 'max:50'],
            'pic_ids.*' => ['integer', 'distinct', Rule::exists('master_pics', 'id')->where('status', 'ACTIVE')],
        ], [], ['pic_ids' => 'PIC', 'pic_ids.*' => 'PIC'])['pic_ids'] ?? [];

        $site = DB::transaction(function () use ($data, $picIds) {
            $site = new Site($data);
            $this->beforeCreate($site, $data);
            $site->save();

            foreach (array_unique($picIds) as $picId) {
                SitePic::create(['site_id' => $site->id, 'pic_id' => $picId]);
            }

            return $site;
        });

        UserActivityLog::record('master_data', 'create', 'Menambah Site ' . $site->tid . ' - ' . $site->sitename . ' (' . count($picIds) . ' PIC)', $site);

        return redirect('/site')->with('success', 'Site berhasil ditambahkan');
    }

    /** Isi drawer detail (HTML parsial). */
    public function drawer($id)
    {
        $site = Site::with([
            'customer:id,name', 'network', 'pulau', 'provinsi', 'kota', 'kecamatan', 'desa',
            'picLinks.pic', 'materialLinks.material.category', 'accessoryLinks.accessory.category',
        ])->findOrFail($id);

        // Tab TT: tiket terbaru site ini yang terlihat oleh user login.
        $tickets = \App\Models\TroubleTicket::visibleTo(auth()->user())
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
            'customer:id,name', 'network', 'pulau', 'provinsi', 'kota', 'kecamatan', 'desa',
            'materialLinks.material.category', 'accessoryLinks.accessory.category', 'picLinks.pic',
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

        UserActivityLog::record('master_data', 'create', 'Menghubungkan PIC #' . $link->pic_id . ' ke Site ' . $site->tid, $link);

        return redirect('/site/' . $site->id)->with('success', 'PIC berhasil dihubungkan ke site');
    }

    public function removePic($id, $linkId)
    {
        $link = SitePic::where('site_id', $id)->findOrFail($linkId);
        $link->delete();

        UserActivityLog::record('master_data', 'delete', 'Melepas PIC #' . $link->pic_id . ' dari Site #' . $id);

        return redirect('/site/' . $id)->with('success', 'PIC berhasil dilepas dari site');
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

        UserActivityLog::record('master_data', 'create', 'Memasang Material #' . $link->material_id . ' ke Site ' . $site->tid, $link);

        return redirect('/site/' . $site->id)->with('success', 'Material berhasil ditambahkan ke site');
    }

    public function removeMaterial($id, $linkId)
    {
        $link = SiteMaterial::where('site_id', $id)->findOrFail($linkId);
        $link->delete();

        UserActivityLog::record('master_data', 'delete', 'Melepas Material #' . $link->material_id . ' dari Site #' . $id);

        return redirect('/site/' . $id)->with('success', 'Material berhasil dilepas dari site');
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

        UserActivityLog::record('master_data', 'create', 'Memasang Accessories #' . $link->accessories_id . ' (qty ' . $link->qty . ') ke Site ' . $site->tid, $link);

        return redirect('/site/' . $site->id)->with('success', 'Accessories berhasil ditambahkan ke site');
    }

    public function updateAccessory(Request $request, $id, $linkId)
    {
        $link = SiteAccessory::where('site_id', $id)->findOrFail($linkId);

        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:1', 'max:1000000'],
        ], [], ['qty' => 'Qty']);

        $old = $link->qty;
        $link->update(['qty' => $data['qty']]);

        UserActivityLog::record('master_data', 'update', 'Mengubah qty Accessories #' . $link->accessories_id . ' di Site #' . $id . ' dari ' . $old . ' ke ' . $link->qty, $link);

        return redirect('/site/' . $id)->with('success', 'Qty accessories berhasil diperbarui');
    }

    public function removeAccessory($id, $linkId)
    {
        $link = SiteAccessory::where('site_id', $id)->findOrFail($linkId);
        $link->delete();

        UserActivityLog::record('master_data', 'delete', 'Melepas Accessories #' . $link->accessories_id . ' dari Site #' . $id);

        return redirect('/site/' . $id)->with('success', 'Accessories berhasil dilepas dari site');
    }
}
