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
                            <tr><th>Tipe Bangunan</th><td>{{ Site::TYPES[$site->type] ?? $site->type }}</td></tr>
                            <tr><th>Source Power</th><td>{{ Site::SOURCE_POWERS[$site->source_power] ?? $site->source_power }}</td></tr>
                            <tr><th>Program</th><td>{{ Site::PROGRAMS[$site->program] ?? $site->program }}</td></tr>
                            <tr><th>PIC Site</th><td>{{ \App\Http\Controllers\SiteController::technicians($site) ?: '-' }}</td></tr>
                            <tr><th>Cluster</th><td>{{ \App\Http\Controllers\SiteController::regions($site) ?: '-' }}</td></tr>
                            <tr><th>Site Keeper</th><td>{{ $site->keepers->pluck('name')->implode(', ') ?: '-' }}</td></tr>
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
                                        <form action="{{ url('/site/' . $site->id . '/pic/' . $link->id) }}" method="POST" class="frmConfirmDelete d-inline" data-title="Lepas PIC dari site?">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="ts-action-primary is-danger" title="Lepas" aria-label="Lepas PIC">
                                                <i class="fas fa-unlink" aria-hidden="true"></i><span class="ts-al">Lepas</span>
                                            </button>
                                        </form>
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
                                        <form action="{{ url('/site/' . $site->id . '/material/' . $link->id) }}" method="POST" class="frmConfirmDelete d-inline" data-title="Lepas material dari site?">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="ts-action-primary is-danger" title="Lepas" aria-label="Lepas material">
                                                <i class="fas fa-unlink" aria-hidden="true"></i><span class="ts-al">Lepas</span>
                                            </button>
                                        </form>
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

        {{-- ACCESSORIES --}}
        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-plug mr-1"></i> Accessories ({{ $site->accessoryLinks->count() }})</h3>
            </div>
            <div class="card-body">
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
                                        <form action="{{ url('/site/' . $site->id . '/accessories/' . $link->id) }}" method="POST" class="d-flex" style="gap:6px;">
                                            @csrf @method('PUT')
                                            <input type="number" name="qty" min="1" step="1" value="{{ $link->qty }}" class="form-control form-control-sm" style="width:90px;" aria-label="Qty">
                                            <button type="submit" class="btn btn-outline-secondary btn-sm" title="Simpan qty"><i class="fas fa-save"></i></button>
                                        </form>
                                    </td>
                                    <td class="text-center">
                                        <form action="{{ url('/site/' . $site->id . '/accessories/' . $link->id) }}" method="POST" class="frmConfirmDelete d-inline" data-title="Lepas accessories dari site?">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="ts-action-primary is-danger" title="Lepas" aria-label="Lepas accessories">
                                                <i class="fas fa-unlink" aria-hidden="true"></i><span class="ts-al">Lepas</span>
                                            </button>
                                        </form>
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
