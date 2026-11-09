@extends('layouts.app')

@section('title', 'Site ' . $site->tid)

@php
    use App\Models\Site;
    use App\Models\MasterMaterial;
@endphp

@section('content')
<div class="content master-page">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0" style="font-size:20px;font-weight:700;color:var(--text);">
                {{ $site->tid }} — {{ $site->sitename }}
            </h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Master</a></li>
                <li class="breadcrumb-item"><a href="{{ url('/site') }}">Sites</a></li>
                <li class="breadcrumb-item active">{{ $site->tid }}</li>
            </ol>
        </div>

        <div class="mb-3">
            <a href="{{ url('/site') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-broadcast-tower mr-1"></i> Informasi Site</h3></div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0 detail-table">
                            <tr><th>Site ID</th><td>{{ $site->tid }}</td></tr>
                            <tr><th>Nama Site</th><td>{{ $site->sitename }}</td></tr>
                            <tr><th>Customer</th><td>{{ $site->customer?->name ?? '-' }}</td></tr>
                            <tr><th>Tipe Bangunan</th><td>{{ Site::typeOptions()[$site->type] ?? $site->type }}</td></tr>
                            <tr><th>Source Power</th><td>{{ Site::sourcePowerOptions()[$site->source_power] ?? $site->source_power }}</td></tr>
                            <tr><th>Program</th><td>{{ Site::programOptions()[$site->program] ?? $site->program }}</td></tr>
                            <tr><th>Teknisi</th><td>{{ \App\Http\Controllers\SiteController::technicians($site) ?: '-' }}</td></tr>
                            <tr><th>Cluster</th><td>{{ \App\Http\Controllers\SiteController::regions($site) ?: '-' }}</td></tr>
                            <tr><th>Site Keeper</th><td>{{ $site->keepers->pluck('name')->implode(', ') ?: '-' }}</td></tr>
                            <tr><th>Teknisi Backup</th><td>{{ $site->backupTechnicians->pluck('user.name')->filter()->implode(', ') ?: '-' }}</td></tr>
                            <tr><th>Status</th><td>
                                <span class="badge badge-{{ $site->status === 'ACTIVE' ? 'success' : 'danger' }} px-2 py-1">{{ Site::STATUSES[$site->status] ?? $site->status }}</span>
                            </td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-map-marker-alt mr-1"></i> Lokasi & Network</h3></div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0 detail-table">
                            <tr><th>Island</th><td>{{ $site->pulau?->name ?: '-' }}</td></tr>
                            <tr><th>Provinsi</th><td>{{ $site->provinsi?->name ?: '-' }}</td></tr>
                            <tr><th>Kota / Kabupaten</th><td>{{ $site->kota?->name ?: '-' }}</td></tr>
                            <tr><th>Kecamatan</th><td>{{ $site->kecamatan?->name ?: '-' }}</td></tr>
                            <tr><th>Desa</th><td>{{ $site->desa?->name ?: '-' }}</td></tr>
                            <tr><th>Koordinat</th><td>{{ $site->latitude !== null ? $site->latitude . ', ' . $site->longitude : '-' }}</td></tr>
                            <tr><th>Address</th><td>{{ $site->address ?: '-' }}</td></tr>
                            <tr><th>Gateway</th><td>{{ $site->gateway ?: '-' }}</td></tr>
                            <tr><th>Outroute ID</th><td>{{ $site->outroute_id ?: '-' }}</td></tr>
                            <tr><th>Hub</th><td>{{ $site->hub ?: '-' }}</td></tr>
                            <tr><th>Spotbeam</th><td>{{ $site->spotbeam ?: '-' }}</td></tr>
                            <tr><th>Polarisasi</th><td>{{ Site::POLARIZATIONS[$site->polarization] ?? ($site->polarization ?: '-') }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- PIC --}}
        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-user-tag mr-1"></i> PIC Site ({{ $site->picLinks->count() }})</h3>
            </div>
            <div class="card-body">
                @if(empty($readonly))
                    <form action="{{ url('/site/' . $site->id . '/pic') }}" method="POST" class="form-row align-items-start mb-3">
                        @csrf
                        <div class="col-md-8 mb-2">
                            <select name="pic_id" id="selPic" class="form-control @error('pic_id') is-invalid @enderror">
                                <option value="">— Pilih PIC —</option>
                                @foreach($availablePics as $pic)
                                    <option value="{{ $pic->id }}" @selected((string) old('pic_id') === (string) $pic->id)>
                                        {{ $pic->pic }}{{ $pic->position ? ' — ' . $pic->position : '' }} ({{ \App\Models\MasterPic::TYPES[$pic->type] ?? $pic->type }})
                                    </option>
                                @endforeach
                            </select>
                            @error('pic_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4 mb-2">
                            <button type="submit" class="btn btn-success btn-block" @disabled($availablePics->isEmpty())>
                                <i class="fas fa-plus mr-1"></i> Hubungkan PIC
                            </button>
                        </div>
                    </form>
                @endif

                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr><th width="5%">No</th><th>Nama PIC</th><th>Jabatan</th><th>Tipe</th><th>Telepon</th><th>Email</th><th class="text-center" width="90">Action</th></tr>
                        </thead>
                        <tbody>
                            @forelse($site->picLinks as $link)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td><strong>{{ $link->pic?->pic }}</strong></td>
                                    <td>{{ $link->pic?->position ?? '-' }}</td>
                                    <td>{{ \App\Models\MasterPic::TYPES[$link->pic?->type] ?? $link->pic?->type }}</td>
                                    <td>{{ $link->pic?->phone ?? '-' }}</td>
                                    <td>{{ $link->pic?->email ?? '-' }}</td>
                                    <td class="text-center">
                                        @if(empty($readonly))
                                            <form action="{{ url('/site/' . $site->id . '/pic/' . $link->id) }}" method="POST" class="frmConfirmDelete d-inline" data-title="Lepas PIC dari site?">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="ts-action-primary is-danger" title="Lepas" aria-label="Lepas PIC">
                                                    <i class="fas fa-unlink" aria-hidden="true"></i><span class="ts-al">Lepas</span>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7"><x-empty title="Belum ada PIC terhubung ke site ini" icon="fas fa-user-tag" compact /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- MATERIAL --}}
        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-microchip mr-1"></i> Material Terpasang ({{ $site->materialLinks->count() }})</h3>
            </div>
            <div class="card-body">
                @if(empty($readonly))
                    <form action="{{ url('/site/' . $site->id . '/material') }}" method="POST" class="form-row align-items-start mb-3">
                        @csrf
                        <div class="col-md-8 mb-2">
                            <select name="material_id" id="selMaterial" class="form-control @error('material_id') is-invalid @enderror">
                                <option value="">— Pilih Material —</option>
                                @foreach($availableMaterials as $mat)
                                    <option value="{{ $mat->id }}" @selected((string) old('material_id') === (string) $mat->id)>{{ $mat->product_code }} — SN {{ $mat->serial_number }}</option>
                                @endforeach
                            </select>
                            @error('material_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4 mb-2">
                            <button type="submit" class="btn btn-success btn-block" @disabled($availableMaterials->isEmpty())>
                                <i class="fas fa-plus mr-1"></i> Tambah Material
                            </button>
                        </div>
                    </form>
                @endif

                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr><th width="5%">No</th><th>Product Code</th><th>Kategori</th><th>Serial Number</th><th>Status</th><th class="text-center" width="90">Action</th></tr>
                        </thead>
                        <tbody>
                            @forelse($site->materialLinks as $link)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td><strong>{{ $link->material?->product_code }}</strong></td>
                                    <td>{{ $link->material?->category?->label ?? '-' }}</td>
                                    <td>{{ $link->material?->serial_number }}</td>
                                    <td><span class="badge badge-secondary px-2 py-1">{{ MasterMaterial::STATUSES[$link->material?->status] ?? $link->material?->status }}</span></td>
                                    <td class="text-center">
                                        @if(empty($readonly))
                                            <form action="{{ url('/site/' . $site->id . '/material/' . $link->id) }}" method="POST" class="frmConfirmDelete d-inline" data-title="Lepas material dari site?">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="ts-action-primary is-danger" title="Lepas" aria-label="Lepas material">
                                                    <i class="fas fa-unlink" aria-hidden="true"></i><span class="ts-al">Lepas</span>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-empty title="Belum ada material terpasang" icon="fas fa-microchip" compact /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Dokumen --}}
        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-folder-open mr-1"></i> Dokumen Site ({{ $site->documents->count() }})</h3>
            </div>
            <div class="card-body">
                @if(empty($readonly))
                    <form action="{{ url('/site/' . $site->id . '/document') }}" method="POST" enctype="multipart/form-data" class="form-row align-items-start mb-3">
                        @csrf
                        <div class="col-md-5">
                            <div class="form-group">
                                <label class="font-weight-bold" for="docTitle">Judul Dokumen</label>
                                <input type="text" name="title" id="docTitle" value="{{ old('title') }}" autocomplete="off"
                                       placeholder="Kosongkan untuk memakai nama berkas"
                                       class="form-control @error('title') is-invalid @enderror">
                                @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group">
                                <label class="font-weight-bold" for="docFile">Berkas <span class="text-danger">*</span></label>
                                <input type="file" name="file" id="docFile" class="form-control-file @error('file') is-invalid @enderror">
                                <small class="form-text text-muted">PDF, gambar, Word, atau Excel. Maksimal 10 MB.</small>
                                @error('file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label class="font-weight-bold d-none d-md-block">&nbsp;</label>
                                <button type="submit" class="btn btn-success btn-block"><i class="fas fa-upload mr-1"></i> Unggah</button>
                            </div>
                        </div>
                    </form>
                @endif

                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr><th width="5%">No</th><th>Judul</th><th>Tipe</th><th>Diunggah</th><th class="text-center" width="90">Action</th></tr>
                        </thead>
                        <tbody>
                            @forelse($site->documents as $i => $doc)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <a href="{{ url('storage/'.$doc->file_path) }}" data-evidence="site-doc" data-evidence-name="{{ $doc->title }}" target="_blank" rel="noopener">
                                            <i class="fas fa-paperclip mr-1"></i>{{ $doc->title }}
                                        </a>
                                    </td>
                                    <td>{{ $doc->file_type ?: '-' }}</td>
                                    <td>
                                        {{ $doc->inputBy?->name ?? '-' }}
                                        <div class="text-muted" style="font-size:11px;">{{ $doc->created_at?->format('d/m/Y H:i') }}</div>
                                    </td>
                                    <td class="text-center">
                                        @if(empty($readonly))
                                            <form action="{{ url('/site/' . $site->id . '/document/' . $doc->id) }}" method="POST" class="frmConfirmDelete d-inline" data-title="Hapus dokumen site?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="ts-action-primary is-danger" title="Hapus" aria-label="Hapus dokumen">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><x-empty title="Belum ada dokumen site" icon="fas fa-folder-open" compact /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Preventive Maintenance --}}
        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-tools mr-1"></i> Preventive Maintenance ({{ $site->maintenances->count() }})</h3>
            </div>
            <div class="card-body">
                @if($site->materialLinks->isEmpty())
                    <x-empty title="Belum ada material terpasang" message="Pasang material dulu; baris perangkat Preventive Maintenance mengikuti material terpasang." icon="fas fa-microchip" compact />
                @else
                    <p class="text-muted mb-2" style="font-size:12px;">
                        SN Sebelum diambil dari material terpasang. SN Sesudah yang diisi akan
                        <strong>menggantikan</strong> serial number material tersebut di Assets.
                    </p>
                    @if(empty($readonly))
                        <form action="{{ url('/site/' . $site->id . '/maintenance') }}" method="POST" class="mb-3">
                            @csrf
                            <div class="form-row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="font-weight-bold" for="pmDate">Tanggal Maintenance <span class="text-danger">*</span></label>
                                        <input type="date" name="performed_at" id="pmDate" value="{{ old('performed_at', now()->format('Y-m-d')) }}"
                                               class="form-control @error('performed_at') is-invalid @enderror">
                                        @error('performed_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="font-weight-bold" for="pmBy">Pelaksana</label>
                                        <input type="text" name="performed_by" id="pmBy" value="{{ old('performed_by') }}" placeholder="Nama teknisi"
                                               autocomplete="off" class="form-control @error('performed_by') is-invalid @enderror">
                                        @error('performed_by')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label class="font-weight-bold" for="pmNotes">Catatan</label>
                                        <input type="text" name="notes" id="pmNotes" value="{{ old('notes') }}" placeholder="Opsional"
                                               autocomplete="off" class="form-control @error('notes') is-invalid @enderror">
                                        @error('notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-2">
                                    <thead>
                                        <tr><th width="5%">No</th><th>Perangkat</th><th>SN Sebelum</th><th>SN Sesudah</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach($site->materialLinks as $i => $link)
                                            <tr>
                                                <td>{{ $i + 1 }}</td>
                                                <td>
                                                    <strong>{{ $link->material?->product_code }}</strong>
                                                    <div class="text-muted" style="font-size:11px;">{{ $link->material?->category?->label ?? '-' }}</div>
                                                    <input type="hidden" name="items[{{ $i }}][material_id]" value="{{ $link->material_id }}">
                                                </td>
                                                <td class="align-middle">{{ $link->material?->serial_number ?: '-' }}</td>
                                                <td>
                                                    <input type="text" name="items[{{ $i }}][sn_after]" value="{{ old('items.' . $i . '.sn_after') }}"
                                                           placeholder="Kosongkan bila tidak berubah" autocomplete="off"
                                                           class="form-control form-control-sm @error('items.' . $i . '.sn_after') is-invalid @enderror">
                                                    @error('items.' . $i . '.sn_after')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="text-right">
                                <button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i> Simpan Maintenance</button>
                            </div>
                        </form>
                    @endif
                @endif

                @forelse($site->maintenances as $pm)
                    <div class="border-top pt-2 mt-2">
                        <strong style="font-size:13px;">{{ $pm->performed_at?->format('d/m/Y') }}</strong>
                        <span class="text-muted" style="font-size:12px;">
                            @if($pm->performed_by)· {{ $pm->performed_by }}@endif
                            · dicatat {{ $pm->inputBy?->name ?? '-' }}
                        </span>
                        <table class="table table-sm table-bordered mt-2 mb-1" style="font-size:12px;">
                            <thead class="bg-light"><tr><th>Perangkat</th><th>SN Sebelum</th><th>SN Sesudah</th></tr></thead>
                            <tbody>
                                @foreach($pm->items as $item)
                                    <tr>
                                        <td>{{ $item->device_name }}</td>
                                        <td>{{ $item->sn_before ?: '-' }}</td>
                                        <td>{{ $item->sn_after ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if($pm->notes)<p class="text-muted mb-0" style="font-size:12px;">{{ $pm->notes }}</p>@endif
                    </div>
                @empty
                    <x-empty title="Belum ada riwayat Preventive Maintenance" icon="fas fa-tools" compact />
                @endforelse
            </div>
        </div>

        {{-- ACCESSORIES --}}
        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-plug mr-1"></i> Accessories ({{ $site->accessoryLinks->count() }})</h3>
            </div>
            <div class="card-body">
                @if(empty($readonly))
                    <form action="{{ url('/site/' . $site->id . '/accessories') }}" method="POST" class="form-row align-items-start mb-3">
                        @csrf
                        <div class="col-md-6 mb-2">
                            <select name="accessories_id" id="selAccessory" class="form-control @error('accessories_id') is-invalid @enderror">
                                <option value="">— Pilih Accessories —</option>
                                @foreach($availableAccessories as $acc)
                                    <option value="{{ $acc->id }}" @selected((string) old('accessories_id') === (string) $acc->id)>{{ $acc->accessories_code }} — {{ $acc->accessories_name }}</option>
                                @endforeach
                            </select>
                            @error('accessories_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-2 mb-2">
                            <input type="number" name="qty" min="1" step="1" value="{{ old('qty', 1) }}" placeholder="Qty"
                                   class="form-control @error('qty') is-invalid @enderror">
                            @error('qty')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4 mb-2">
                            <button type="submit" class="btn btn-success btn-block" @disabled($availableAccessories->isEmpty())>
                                <i class="fas fa-plus mr-1"></i> Tambah Accessories
                            </button>
                        </div>
                    </form>
                @endif

                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr><th width="5%">No</th><th>Accessories Code</th><th>Nama</th><th>Kategori</th><th width="200">Qty</th><th class="text-center" width="90">Action</th></tr>
                        </thead>
                        <tbody>
                            @forelse($site->accessoryLinks as $link)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td><strong>{{ $link->accessory?->accessories_code }}</strong></td>
                                    <td>{{ $link->accessory?->accessories_name }}</td>
                                    <td>{{ $link->accessory?->category?->label ?? '-' }}</td>
                                    <td>
                                        @if(empty($readonly))
                                            <form action="{{ url('/site/' . $site->id . '/accessories/' . $link->id) }}" method="POST" class="d-flex" style="gap:6px;">
                                                @csrf @method('PUT')
                                                <input type="number" name="qty" min="1" step="1" value="{{ $link->qty }}" class="form-control form-control-sm" style="width:90px;" aria-label="Qty">
                                                <button type="submit" class="btn btn-outline-secondary btn-sm" title="Simpan qty"><i class="fas fa-save"></i></button>
                                            </form>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if(empty($readonly))
                                            <form action="{{ url('/site/' . $site->id . '/accessories/' . $link->id) }}" method="POST" class="frmConfirmDelete d-inline" data-title="Lepas accessories dari site?">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="ts-action-primary is-danger" title="Lepas" aria-label="Lepas accessories">
                                                    <i class="fas fa-unlink" aria-hidden="true"></i><span class="ts-al">Lepas</span>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-empty title="Belum ada accessories di site ini" icon="fas fa-plug" compact /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@include('master-data._styles')
@include('master-data._confirm_delete')

@push('scripts')
<script>
    TesysSearchableSelect.init('#selPic', { placeholder: '— Pilih PIC —', searchPlaceholder: 'Cari PIC...' });
    TesysSearchableSelect.init('#selMaterial', { placeholder: '— Pilih Material —', searchPlaceholder: 'Cari product code / SN...' });
    TesysSearchableSelect.init('#selAccessory', { placeholder: '— Pilih Accessories —', searchPlaceholder: 'Cari accessories...' });
</script>
@endpush
