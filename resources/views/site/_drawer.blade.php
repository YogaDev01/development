@php
    use App\Models\Site;
    use App\Models\MasterMaterial;
    use App\Models\MasterPic;

    $hasCoord = $site->latitude !== null && $site->longitude !== null;
    if ($hasCoord) {
        $lat = (float) $site->latitude;
        $lng = (float) $site->longitude;
        $mapLink = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($lat . ',' . $lng);

        $gmapsKey = config('services.google_maps.key');
        if ($gmapsKey) {
            // Gaya bersih: tanpa POI/transit, bangunan monokrom, jalan putih.
            $styles = [
                'feature:poi|visibility:off',
                'feature:transit|visibility:off',
                'feature:landscape.natural|element:geometry|color:0xf4f4f4',
                'feature:landscape.man_made|element:geometry|color:0xe4e4e4',
                'feature:landscape.man_made|element:geometry.stroke|color:0xd2d2d2',
                'feature:road|element:geometry|color:0xffffff',
                'feature:road|element:geometry.stroke|color:0xdadada',
                'feature:road|element:labels.icon|visibility:off',
                'feature:road|element:labels.text.fill|color:0x8c8c8c',
                'feature:road.local|element:labels|visibility:off',
                'feature:water|element:geometry|color:0xd5dbe0',
                'feature:administrative|element:labels.text.fill|color:0x9a9a9a',
            ];
            $staticMap = 'https://maps.googleapis.com/maps/api/staticmap?'
                . http_build_query([
                    'center'  => $lat . ',' . $lng,
                    'zoom'    => 17,
                    'size'    => '640x300',
                    'scale'   => 2,
                    'maptype' => 'roadmap',
                    'markers' => 'color:0xC8272E|' . $lat . ',' . $lng,
                    'key'     => $gmapsKey,
                ])
                . collect($styles)->map(fn ($s) => '&style=' . rawurlencode($s))->implode('');
        } else {
            $d = 0.006;
            $mapSrc = 'https://www.openstreetmap.org/export/embed.html?layer=mapnik&bbox='
                . rawurlencode(($lng - $d) . ',' . ($lat - $d) . ',' . ($lng + $d) . ',' . ($lat + $d))
                . '&marker=' . rawurlencode($lat . ',' . $lng);
        }
    }

    $kv = fn ($label, $value) => ['label' => $label, 'value' => ($value === null || $value === '') ? '-' : $value];
@endphp

<div data-drawer-meta data-eyebrow="{{ $site->tid }}" data-title="{{ $site->sitename }}"></div>

<div class="d-flex flex-wrap align-items-center mb-3" style="gap:6px;">
    <span class="badge badge-{{ $site->status === 'ACTIVE' ? 'success' : 'danger' }} px-2 py-1">{{ Site::STATUSES[$site->status] ?? $site->status }}</span>
    <span class="badge badge-light border px-2 py-1">{{ Site::PROGRAMS[$site->program] ?? $site->program }}</span>
    <span class="badge badge-light border px-2 py-1">{{ $site->customer?->name ?? '-' }}</span>
</div>

<ul class="nav nav-tabs site-dw-tabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#sdwInfo" role="tab">Informasi</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#sdwPic" role="tab">PIC <span class="badge badge-secondary">{{ $site->picLinks->count() }}</span></a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#sdwTt" role="tab">TT</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#sdwAssets" role="tab">Assets <span class="badge badge-secondary">{{ $site->materialLinks->count() + $site->accessoryLinks->count() }}</span></a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#sdwDoc" role="tab">Document</a></li>
</ul>

<div class="tab-content pt-3">

    {{-- INFORMASI --}}
    <div class="tab-pane fade show active" id="sdwInfo" role="tabpanel">
        @if($hasCoord)
            <div class="site-dw-map mb-2">
                @isset($staticMap)
                    <a href="{{ $mapLink }}" target="_blank" rel="noopener noreferrer" title="Buka di Google Maps">
                        <img src="{{ $staticMap }}" alt="Peta lokasi {{ $site->sitename }}" loading="lazy">
                    </a>
                @else
                    <iframe src="{{ $mapSrc }}" title="Peta lokasi {{ $site->sitename }}" loading="lazy" referrerpolicy="no-referrer"></iframe>
                @endisset
            </div>
            <p class="mb-3" style="font-size:12px;">
                <i class="fas fa-map-marker-alt mr-1 text-danger"></i>{{ $site->latitude }}, {{ $site->longitude }}
                · <a href="{{ $mapLink }}" target="_blank" rel="noopener noreferrer">Buka di Google Maps</a>
            </p>
        @else
            <x-empty title="Koordinat belum diisi" icon="fas fa-map-marked-alt" compact />
        @endif

        <div class="ts-dw-section">
            <p class="ts-dw-section-title">Identitas</p>
            <dl class="ts-dw-kv">
                @foreach([
                    $kv('Site ID', $site->tid),
                    $kv('Nama Site', $site->sitename),
                    $kv('Customer', $site->customer?->name),
                    $kv('Tipe Bangunan', Site::TYPES[$site->type] ?? $site->type),
                    $kv('Source Power', Site::SOURCE_POWERS[$site->source_power] ?? $site->source_power),
                    $kv('Program', Site::PROGRAMS[$site->program] ?? $site->program),
                    $kv('Status', Site::STATUSES[$site->status] ?? $site->status),
                    $kv('PIC Site', \App\Http\Controllers\SiteController::technicians($site)),
                    $kv('Region', \App\Http\Controllers\SiteController::regions($site)),
                ] as $p)
                    <dt>{{ $p['label'] }}</dt><dd>{{ $p['value'] }}</dd>
                @endforeach
            </dl>
        </div>

        <div class="ts-dw-section">
            <p class="ts-dw-section-title">Location</p>
            <dl class="ts-dw-kv">
                @foreach([
                    $kv('Island', $site->pulau?->name),
                    $kv('Provinsi', $site->provinsi?->name),
                    $kv('Kota / Kabupaten', $site->kota?->name),
                    $kv('Kecamatan', $site->kecamatan?->name),
                    $kv('Desa', $site->desa?->name),
                    $kv('Latitude', $site->latitude),
                    $kv('Longitude', $site->longitude),
                    $kv('Address', $site->address),
                ] as $p)
                    <dt>{{ $p['label'] }}</dt><dd>{{ $p['value'] }}</dd>
                @endforeach
            </dl>
        </div>

        <div class="ts-dw-section">
            <p class="ts-dw-section-title">Network</p>
            <dl class="ts-dw-kv">
                @foreach([
                    $kv('Gateway', $site->gateway),
                    $kv('Outroute ID', $site->outroute_id),
                    $kv('Hub', $site->hub),
                    $kv('Spotbeam', $site->spotbeam),
                    $kv('Polarisasi', Site::POLARIZATIONS[$site->polarization] ?? $site->polarization),
                ] as $p)
                    <dt>{{ $p['label'] }}</dt><dd>{{ $p['value'] }}</dd>
                @endforeach
            </dl>
        </div>

        <div class="ts-dw-section">
            <p class="ts-dw-section-title">Site Keeper</p>
            @forelse($site->keepers as $keeper)
                <div class="site-dw-pic">{{ $keeper->name }}</div>
            @empty
                <p class="text-muted mb-0" style="font-size:12px;">Belum ada site keeper.</p>
            @endforelse
        </div>
    </div>

    {{-- PIC --}}
    <div class="tab-pane fade" id="sdwPic" role="tabpanel">
        @forelse($site->picLinks as $link)
            <div class="site-dw-pic">
                <div class="d-flex justify-content-between align-items-start">
                    <strong>{{ $link->pic?->pic }}</strong>
                    <span class="badge badge-{{ $link->pic?->type === 'internal' ? 'info' : 'secondary' }}">{{ MasterPic::TYPES[$link->pic?->type] ?? $link->pic?->type }}</span>
                </div>
                <div class="text-muted" style="font-size:12px;">
                    {{ $link->pic?->position ?? '-' }}
                    @if($link->pic?->phone) · <i class="fas fa-phone-alt"></i> {{ $link->pic->phone }} @endif
                    @if($link->pic?->email) · <i class="fas fa-envelope"></i> {{ $link->pic->email }} @endif
                </div>
            </div>
        @empty
            <x-empty title="Belum ada PIC terhubung" icon="fas fa-user-tag" compact />
        @endforelse
    </div>

    {{-- TT --}}
    <div class="tab-pane fade" id="sdwTt" role="tabpanel">
        @php $bukaTt = (bool) auth()->user()?->effectiveRole()?->hasMenu('trouble-ticket'); @endphp
        @forelse($tickets as $tt)
            <div class="d-flex justify-content-between align-items-start py-2" style="border-bottom:1px solid var(--border-soft);font-size:12.5px;">
                <div style="min-width:0;">
                    @if($bukaTt)
                        <a href="{{ route('trouble-ticket.show', $tt) }}" style="font-weight:600;">{{ $tt->ticket_number }}</a>
                    @else
                        <span style="font-weight:600;">{{ $tt->ticket_number }}</span>
                    @endif
                    <span class="text-muted">· {{ $tt->severity_label }}</span>
                    <div class="text-truncate" style="max-width:320px;color:var(--text-dim);">{{ $tt->issue }}</div>
                    <div class="text-muted" style="font-size:11px;">{{ $tt->technician?->name ?? '-' }} · {{ $tt->created_at?->format('d/m/Y H:i') }}</div>
                </div>
                <x-status :status="$tt->status" context="trouble-ticket" />
            </div>
        @empty
            <x-empty title="Belum ada Trouble Ticket" message="Belum ada tiket untuk site ini yang bisa Anda lihat." icon="fas fa-ticket-alt" compact />
        @endforelse
    </div>

    {{-- ASSETS --}}
    <div class="tab-pane fade" id="sdwAssets" role="tabpanel">
        <p class="ts-dw-section-title">Material ({{ $site->materialLinks->count() }})</p>
        @if($site->materialLinks->isEmpty())
            <x-empty title="Belum ada material terpasang" icon="fas fa-microchip" compact />
        @else
            <table class="table table-sm table-bordered mb-3" style="font-size:12px;">
                <thead class="bg-light"><tr><th>Product Code</th><th>Kategori</th><th>SN</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach($site->materialLinks as $link)
                        <tr>
                            <td><strong>{{ $link->material?->product_code }}</strong></td>
                            <td>{{ $link->material?->category?->label ?? '-' }}</td>
                            <td>{{ $link->material?->serial_number }}</td>
                            <td>{{ MasterMaterial::STATUSES[$link->material?->status] ?? $link->material?->status }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <p class="ts-dw-section-title">Accessories ({{ $site->accessoryLinks->count() }})</p>
        @if($site->accessoryLinks->isEmpty())
            <x-empty title="Belum ada accessories" icon="fas fa-plug" compact />
        @else
            <table class="table table-sm table-bordered mb-0" style="font-size:12px;">
                <thead class="bg-light"><tr><th>Code</th><th>Nama</th><th>Kategori</th><th class="text-right">Qty</th></tr></thead>
                <tbody>
                    @foreach($site->accessoryLinks as $link)
                        <tr>
                            <td><strong>{{ $link->accessory?->accessories_code }}</strong></td>
                            <td>{{ $link->accessory?->accessories_name }}</td>
                            <td>{{ $link->accessory?->category?->label ?? '-' }}</td>
                            <td class="text-right">{{ number_format($link->qty) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- DOCUMENT --}}
    <div class="tab-pane fade" id="sdwDoc" role="tabpanel">
        <x-empty title="Dokumen site belum tersedia" icon="fas fa-folder-open" compact />
        <p class="text-muted text-center" style="font-size:12px;">Lampiran dokumen site akan ditambahkan kemudian.</p>
    </div>
</div>

<div class="mt-3 pt-3 border-top text-right">
    <a href="{{ url('/site/' . $site->id) }}" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-cog mr-1"></i> Kelola PIC & Assets
    </a>
</div>
