@extends('layouts.app')

@php $baru = ! $asset->exists; @endphp

@section('title', $baru ? 'Registrasi Aset' : 'Ubah Aset')

@section('content')
<div class="content"><div class="container-fluid">
    @include('asset._header', [
        'title' => $baru ? 'Registrasi Aset' : 'Ubah Aset ' . $asset->code,
        'desc'  => $baru ? 'Kode aset dibuat otomatis: AST-{kode kategori}-{tahun perolehan}-{urut}.' : 'Status, pemegang, dan lokasi diubah lewat Serah Terima / Transfer, bukan dari form ini.',
    ])

    @if($categories->isEmpty() || $locations->isEmpty())
        <div class="alert alert-warning py-2 small">Kategori dan lokasi aset harus dibuat dulu di <a href="{{ route('asset.settings.index') }}">Pengaturan Aset</a>.</div>
    @endif

    <form method="POST" action="{{ $baru ? route('asset.store') : route('asset.update', $asset) }}" enctype="multipart/form-data">
        @csrf
        @unless($baru) @method('PUT') @endunless

        <div class="ast-panel"><div class="ast-panel-h">Identitas Aset</div><div class="ast-panel-b"><div class="form-row">
            <div class="form-group col-md-6"><label class="small font-weight-bold">Nama Aset <span class="text-danger">*</span></label>
                <input name="name" class="form-control form-control-sm" value="{{ old('name', $asset->name) }}" maxlength="255" required></div>
            <div class="form-group col-md-3"><label class="small font-weight-bold">Kategori <span class="text-danger">*</span></label>
                <select name="asset_category_id" id="astCategory" class="form-control form-control-sm" required>
                    <option value="">— Pilih —</option>
                    @foreach($categories as $c)
                        <option value="{{ $c->id }}" data-life="{{ $c->life_years }}" data-method="{{ $c->depreciation_method }}" data-residual="{{ $c->residual_percent }}"
                            @selected(old('asset_category_id', $asset->asset_category_id) == $c->id)>{{ $c->code }} — {{ $c->name }}</option>
                    @endforeach
                </select>
                @unless($baru)<div class="ast-sub">Mengganti kategori tidak mengubah kode aset.</div>@endunless</div>
            <div class="form-group col-md-3"><label class="small font-weight-bold">Tipe</label>
                <input name="type" class="form-control form-control-sm" value="{{ old('type', $asset->type) }}" maxlength="100" placeholder="Laptop, Genset, …"></div>
            <div class="form-group col-md-3"><label class="small font-weight-bold">Merek</label>
                <input name="brand" class="form-control form-control-sm" value="{{ old('brand', $asset->brand) }}" maxlength="100"></div>
            <div class="form-group col-md-3"><label class="small font-weight-bold">Model</label>
                <input name="model" class="form-control form-control-sm" value="{{ old('model', $asset->model) }}" maxlength="150"></div>
            <div class="form-group col-md-3"><label class="small font-weight-bold">Serial Number</label>
                <input name="serial_number" class="form-control form-control-sm" value="{{ old('serial_number', $asset->serial_number) }}" maxlength="150"></div>
            <div class="form-group col-md-3"><label class="small font-weight-bold">Kondisi <span class="text-danger">*</span></label>
                <select name="condition" class="form-control form-control-sm" required>
                    @foreach(\App\Models\Asset::CONDITIONS as $k => $v)<option value="{{ $k }}" @selected(old('condition', $asset->condition) === $k)>{{ $v }}</option>@endforeach
                </select></div>
            <div class="form-group col-12 mb-0"><label class="small font-weight-bold">Keterangan</label>
                <textarea name="description" class="form-control form-control-sm" rows="2" maxlength="5000">{{ old('description', $asset->description) }}</textarea></div>
        </div></div></div>

        <div class="ast-panel"><div class="ast-panel-h">Lokasi & Penanggung Jawab</div><div class="ast-panel-b"><div class="form-row">
            <div class="form-group col-md-4"><label class="small font-weight-bold">Lokasi <span class="text-danger">*</span></label>
                <select name="asset_location_id" class="form-control form-control-sm" required>
                    <option value="">— Pilih —</option>
                    @foreach($locations as $l)<option value="{{ $l->id }}" @selected(old('asset_location_id', $asset->asset_location_id) == $l->id)>{{ $l->name }}</option>@endforeach
                </select></div>
            <div class="form-group col-md-4"><label class="small font-weight-bold">Departemen</label>
                <select name="department_id" class="form-control form-control-sm">
                    <option value="">—</option>
                    @foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id', $asset->department_id) == $d->id)>{{ $d->name }}</option>@endforeach
                </select></div>
            <div class="form-group col-md-4"><label class="small font-weight-bold">Custodian (tanpa pemegang perorangan)</label>
                <input name="custodian_name" class="form-control form-control-sm" value="{{ old('custodian_name', $asset->custodian_name) }}" maxlength="255"></div>
            @if($baru)
                <div class="form-group col-md-4 mb-0"><label class="small font-weight-bold">Status Awal <span class="text-danger">*</span></label>
                    <select name="status" class="form-control form-control-sm" required>
                        <option value="AVAILABLE" @selected(old('status', 'AVAILABLE') === 'AVAILABLE')>Available — tersedia di lokasi</option>
                        <option value="IN_USE" @selected(old('status') === 'IN_USE')>In Use — terpasang/infrastruktur</option>
                    </select>
                    <div class="ast-sub">Penyerahan ke karyawan dilakukan lewat Serah Terima setelah registrasi.</div></div>
            @endif
        </div></div></div>

        <div class="ast-panel"><div class="ast-panel-h">Perolehan & Penyusutan</div><div class="ast-panel-b"><div class="form-row">
            <div class="form-group col-md-3"><label class="small font-weight-bold">Tanggal Perolehan <span class="text-danger">*</span></label>
                <input type="date" name="acquisition_date" class="form-control form-control-sm" value="{{ old('acquisition_date', $asset->acquisition_date?->toDateString() ?? $asset->acquisition_date) }}" max="{{ now()->toDateString() }}" required></div>
            <div class="form-group col-md-3"><label class="small font-weight-bold">Jenis Perolehan <span class="text-danger">*</span></label>
                <select name="acquisition_type" class="form-control form-control-sm" required>
                    @foreach(\App\Models\Asset::ACQUISITION_TYPES as $k => $v)<option value="{{ $k }}" @selected(old('acquisition_type', $asset->acquisition_type) === $k)>{{ $v }}</option>@endforeach
                </select></div>
            <div class="form-group col-md-3"><label class="small font-weight-bold">Supplier</label>
                <input name="supplier" class="form-control form-control-sm" value="{{ old('supplier', $asset->supplier) }}" maxlength="255"></div>
            <div class="form-group col-md-3"><label class="small font-weight-bold">No. PO / Faktur</label>
                <input name="po_number" class="form-control form-control-sm" value="{{ old('po_number', $asset->po_number) }}" maxlength="100"></div>
            <div class="form-group col-md-3"><label class="small font-weight-bold">Harga Perolehan (Rp) <span class="text-danger">*</span></label>
                <input type="number" name="cost" id="astCost" class="form-control form-control-sm" value="{{ old('cost', $asset->cost ?? 0) }}" min="0" step="1" required></div>
            <div class="form-group col-md-3"><label class="small font-weight-bold">Nilai Residu (Rp) <span class="text-danger">*</span></label>
                <input type="number" name="residual_value" id="astResidual" class="form-control form-control-sm" value="{{ old('residual_value', $asset->residual_value ?? 0) }}" min="0" step="1" required></div>
            <div class="form-group col-md-2"><label class="small font-weight-bold">Umur (tahun) <span class="text-danger">*</span></label>
                <input type="number" name="life_years" id="astLife" class="form-control form-control-sm" value="{{ old('life_years', $asset->life_years ?? 4) }}" min="1" max="50" required></div>
            <div class="form-group col-md-2"><label class="small font-weight-bold">Metode <span class="text-danger">*</span></label>
                <select name="depreciation_method" id="astMethod" class="form-control form-control-sm" required>
                    @foreach(\App\Models\AssetCategory::METHODS as $k => $v)<option value="{{ $k }}" @selected(old('depreciation_method', $asset->depreciation_method ?? 'STRAIGHT_LINE') === $k)>{{ $v }}</option>@endforeach
                </select></div>
            <div class="form-group col-md-2"><label class="small font-weight-bold">Garansi s/d</label>
                <input type="date" name="warranty_end" class="form-control form-control-sm" value="{{ old('warranty_end', $asset->warranty_end?->toDateString()) }}"></div>
            <div class="col-12 ast-sub">Memilih kategori mengisi umur, metode, dan residu dari default kategori (masih bisa diubah).</div>
        </div></div></div>

        <div class="ast-panel"><div class="ast-panel-h">Spesifikasi<span class="ast-r"><button type="button" class="btn btn-secondary btn-sm" id="astAddSpec"><i class="fas fa-plus"></i> Baris</button></span></div>
            <div class="ast-panel-b" id="astSpecs">
                @php $specs = old('spec_keys') ? array_combine(old('spec_keys'), array_slice(array_pad(old('spec_values', []), count(old('spec_keys')), ''), 0, count(old('spec_keys')))) : ($asset->specs ?? []); @endphp
                @foreach(($specs ?: ['' => '']) as $k => $v)
                    <div class="form-row mb-1"><div class="col-5"><input name="spec_keys[]" class="form-control form-control-sm" value="{{ $k }}" placeholder="Nama (mis. RAM)" maxlength="100"></div>
                        <div class="col-7"><input name="spec_values[]" class="form-control form-control-sm" value="{{ $v }}" placeholder="Nilai" maxlength="255"></div></div>
                @endforeach
            </div></div>

        <div class="ast-panel"><div class="ast-panel-h">Lampiran</div><div class="ast-panel-b">
            <input type="file" name="attachments[]" class="form-control-file" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
            <div class="ast-sub mt-1">PDF/gambar/Office, maks 5 MB per berkas.</div>
        </div></div>

        <div class="d-flex justify-content-end mb-4" style="gap:6px;">
            <a href="{{ $baru ? route('asset.index') : route('asset.show', $asset) }}" class="btn btn-secondary btn-sm">Batal</a>
            <button class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Simpan</button>
        </div>
    </form>
</div></div>
@endsection

@push('scripts')
<script>
(function () {
    $('#astCategory').on('change', function () {
        var o = $(this).find(':selected');
        if (!o.val()) return;
        $('#astLife').val(o.data('life'));
        $('#astMethod').val(o.data('method'));
        var cost = parseInt($('#astCost').val() || '0', 10);
        $('#astResidual').val(Math.round(cost * parseFloat(o.data('residual')) / 100));
    });
    $('#astAddSpec').on('click', function () {
        $('#astSpecs').append('<div class="form-row mb-1"><div class="col-5"><input name="spec_keys[]" class="form-control form-control-sm" placeholder="Nama" maxlength="100"></div><div class="col-7"><input name="spec_values[]" class="form-control form-control-sm" placeholder="Nilai" maxlength="255"></div></div>');
    });
})();
</script>
@endpush
