@extends('layouts.app')

@section('title', 'Pengaturan Aset')

@section('content')
<div class="content"><div class="container-fluid">
    @include('asset._header', [
        'title' => 'Pengaturan Aset',
        'desc'  => 'Kategori (termasuk default penyusutan) dan lokasi aset. Departemen & akses user memakai master TESYS (Team & User Access).',
    ])

    @unless($canManage)<div class="alert alert-light border py-1 small">Hanya Asset Admin yang bisa menambah atau mengubah data ini.</div>@endunless

    <ul class="nav ast-tabs">
        <li class="nav-item"><a class="nav-link {{ $tab === 'categories' ? 'active' : '' }}" href="{{ route('asset.settings.index') }}">Kategori Aset</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab === 'locations' ? 'active' : '' }}" href="{{ route('asset.settings.index', ['tab' => 'locations']) }}">Lokasi Aset</a></li>
    </ul>

    @if($tab === 'categories')
    <div class="ast-panel">
        @if($canManage)<div class="ast-toolbar"><span class="ast-sub align-self-center">Default penyusutan hanya berlaku untuk aset yang didaftarkan sesudahnya.</span>
            <button type="button" class="btn btn-primary btn-sm ml-auto js-cat" data-url="{{ route('asset.settings.category.store') }}"><i class="fas fa-plus mr-1"></i>Tambah Kategori</button></div>@endif
        <div class="table-responsive"><table class="ast-table">
            <thead><tr><th>Kode</th><th>Nama</th><th>Umur</th><th>Metode</th><th class="num">Residu</th><th>Akun</th><th class="num">Aset</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($categories as $c)
                <tr><td class="ast-code">{{ $c->code }}</td><td>{{ $c->name }}<div class="ast-sub">{{ $c->description }}</div></td>
                    <td>{{ $c->life_years }} thn</td><td>{{ \App\Models\AssetCategory::METHODS[$c->depreciation_method] ?? $c->depreciation_method }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($c->residual_percent, 2, ',', '.'), '0'), ',') }}%</td><td>{{ $c->account ?: '-' }}</td>
                    <td class="num">{{ $c->assets_count }}</td><td><x-status :status="$c->status" context="asset" /></td>
                    <td class="text-right">@if($canManage)<button type="button" class="btn btn-secondary btn-sm js-cat" data-url="{{ route('asset.settings.category.update', $c) }}" data-json="{{ json_encode($c->only(['code', 'name', 'life_years', 'depreciation_method', 'residual_percent', 'account', 'description', 'status'])) }}">Ubah</button>@endif</td></tr>
            @empty
                <tr><td colspan="9"><x-empty title="Belum ada kategori" message="Buat kategori dulu (mis. ITE — IT Equipment) sebelum mendaftarkan aset." /></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    @else
    <div class="ast-panel">
        @if($canManage)<div class="ast-toolbar"><button type="button" class="btn btn-primary btn-sm ml-auto js-loc" data-url="{{ route('asset.settings.location.store') }}"><i class="fas fa-plus mr-1"></i>Tambah Lokasi</button></div>@endif
        <div class="table-responsive"><table class="ast-table">
            <thead><tr><th>Kode</th><th>Nama</th><th>Tipe</th><th>Alamat</th><th>PIC</th><th class="num">Aset</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($locations as $l)
                <tr><td class="ast-code">{{ $l->code }}</td><td>{{ $l->name }}</td><td>{{ \App\Models\AssetLocation::TYPES[$l->type] ?? $l->type }}</td>
                    <td style="white-space:normal;">{{ $l->address ?: '-' }}</td><td>{{ $l->pic_name ?: '-' }}</td><td class="num">{{ $l->assets_count }}</td>
                    <td><x-status :status="$l->status" context="asset" /></td>
                    <td class="text-right">@if($canManage)<button type="button" class="btn btn-secondary btn-sm js-loc" data-url="{{ route('asset.settings.location.update', $l) }}" data-json="{{ json_encode($l->only(['code', 'name', 'type', 'address', 'pic_name', 'status'])) }}">Ubah</button>@endif</td></tr>
            @empty
                <tr><td colspan="8"><x-empty title="Belum ada lokasi" message="Tambahkan kantor, gudang, site, atau kantor proyek." /></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    @endif
</div></div>

@if($canManage)
<div class="modal fade" id="catModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" id="catForm" class="modal-content">
            @csrf <input type="hidden" name="_method" value="POST">
            <div class="modal-header"><h5 class="modal-title">Kategori Aset</h5><button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body"><div class="form-row">
                <div class="form-group col-md-3"><label class="small font-weight-bold">Kode <span class="text-danger">*</span></label><input name="code" class="form-control form-control-sm" maxlength="10" required>
                    <div class="ast-sub">Dipakai di kode aset; tidak bisa diubah.</div></div>
                <div class="form-group col-md-9"><label class="small font-weight-bold">Nama <span class="text-danger">*</span></label><input name="name" class="form-control form-control-sm" maxlength="255" required></div>
                <div class="form-group col-md-3"><label class="small font-weight-bold">Umur (tahun) <span class="text-danger">*</span></label><input type="number" name="life_years" class="form-control form-control-sm" min="1" max="50" value="4" required></div>
                <div class="form-group col-md-4"><label class="small font-weight-bold">Metode <span class="text-danger">*</span></label>
                    <select name="depreciation_method" class="form-control form-control-sm" required>@foreach(\App\Models\AssetCategory::METHODS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div class="form-group col-md-2"><label class="small font-weight-bold">Residu (%) <span class="text-danger">*</span></label><input type="number" name="residual_percent" class="form-control form-control-sm" min="0" max="100" step="0.01" value="10" required></div>
                <div class="form-group col-md-3"><label class="small font-weight-bold">Status</label><select name="status" class="form-control form-control-sm"><option value="ACTIVE">Active</option><option value="INACTIVE">Inactive</option></select></div>
                <div class="form-group col-md-6"><label class="small font-weight-bold">Akun</label><input name="account" class="form-control form-control-sm" maxlength="255" placeholder="mis. 1-5100 Peralatan Komputer"></div>
                <div class="form-group col-md-6"><label class="small font-weight-bold">Keterangan</label><input name="description" class="form-control form-control-sm" maxlength="2000"></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button><button class="btn btn-primary btn-sm">Simpan</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="locModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="POST" id="locForm" class="modal-content">
            @csrf <input type="hidden" name="_method" value="POST">
            <div class="modal-header"><h5 class="modal-title">Lokasi Aset</h5><button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body"><div class="form-row">
                <div class="form-group col-md-4"><label class="small font-weight-bold">Kode <span class="text-danger">*</span></label><input name="code" class="form-control form-control-sm" maxlength="20" required></div>
                <div class="form-group col-md-8"><label class="small font-weight-bold">Nama <span class="text-danger">*</span></label><input name="name" class="form-control form-control-sm" maxlength="255" required></div>
                <div class="form-group col-md-6"><label class="small font-weight-bold">Tipe <span class="text-danger">*</span></label>
                    <select name="type" class="form-control form-control-sm" required>@foreach(\App\Models\AssetLocation::TYPES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div class="form-group col-md-6"><label class="small font-weight-bold">Status</label><select name="status" class="form-control form-control-sm"><option value="ACTIVE">Active</option><option value="INACTIVE">Inactive</option></select></div>
                <div class="form-group col-12"><label class="small font-weight-bold">PIC</label><input name="pic_name" class="form-control form-control-sm" maxlength="255"></div>
                <div class="form-group col-12 mb-0"><label class="small font-weight-bold">Alamat</label><textarea name="address" class="form-control form-control-sm" rows="2" maxlength="2000"></textarea></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button><button class="btn btn-primary btn-sm">Simpan</button></div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    function open(modal, form, btn) {
        var f = $(form), d = $(btn).data('json') || null;
        f.attr('action', $(btn).data('url'));
        f[0].reset();
        f.find('[name=_method]').val(d ? 'PUT' : 'POST');
        // Kode kategori tidak bisa diubah (dipakai di kode aset).
        if (form === '#catForm') f.find('[name=code]').prop('disabled', !!d).prop('required', !d);
        if (d) Object.keys(d).forEach(function (k) { f.find('[name=' + k + ']').val(d[k] === null ? '' : d[k]); });
        $(modal).modal('show');
    }
    $(document).on('click', '.js-cat', function () { open('#catModal', '#catForm', this); });
    $(document).on('click', '.js-loc', function () { open('#locModal', '#locForm', this); });
})();
</script>
@endpush
