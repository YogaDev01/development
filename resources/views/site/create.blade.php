@extends('layouts.app')

@php
    $isEdit = isset($site);
    $values = $isEdit ? $site->only(array_keys($fields)) : [];
    $pageTitle = $isEdit ? 'Edit Site ' . $site->tid : 'Tambah Site';
@endphp

@section('title', $pageTitle)

@section('content')
<div class="content master-page">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0" style="font-size:20px;font-weight:700;color:var(--text);">{{ $pageTitle }}</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Master</a></li>
                <li class="breadcrumb-item"><a href="{{ url('/site') }}">Sites</a></li>
                <li class="breadcrumb-item active">{{ $isEdit ? 'Edit' : 'Tambah' }}</li>
            </ol>
        </div>

        <form action="{{ $isEdit ? url('/site/' . $site->id) : url('/site') }}" method="POST" id="frmSiteCreate" novalidate>
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            @if($errors->any())
                <div class="alert alert-danger py-2" style="font-size:13px;">
                    <i class="fas fa-exclamation-triangle mr-1"></i> Periksa kembali isian yang ditandai.
                </div>
            @endif

            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-broadcast-tower mr-1"></i> Data Site</h3></div>
                <div class="card-body site-create-body">
                    @unless($isEdit)
                        <p class="text-muted mb-3" style="font-size:12px;"><i class="fas fa-info-circle mr-1"></i>Site baru otomatis berstatus <strong>Active</strong>.</p>
                    @endunless
                    <div class="row">
                        @foreach($fields as $name => $f)
                            @include('master-data._field', ['name' => $name, 'f' => $f, 'first' => $loop->first, 'values' => $values])
                        @endforeach
                    </div>
                </div>
            </div>

            @php
                $keeperOptions = \App\Http\Controllers\SiteController::keeperOptions();
                $keeperRows = old('keepers', $isEdit ? $site->keepers->map(fn ($k) => ['name' => $k->name])->all() : []);
                $keeperRows = $keeperRows ?: [['name' => '']];
            @endphp
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title"><i class="fas fa-user-shield mr-1"></i> Site Keeper</h3>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddKeeper"><i class="fas fa-plus mr-1"></i> Tambah Site Keeper</button>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-2" style="font-size:12px;">Penjaga site, dipilih dari daftar user aktif. Boleh lebih dari satu; baris kosong diabaikan.</p>
                    <div id="keeperRows">
                        @foreach($keeperRows as $i => $row)
                            <div class="form-row align-items-start js-keeper-row">
                                <div class="col-md-11">
                                    <div class="form-group">
                                        <select name="keepers[{{ $i }}][name]" data-searchable
                                                class="form-control @error('keepers.' . $i . '.name') is-invalid @enderror">
                                            <option value="">&mdash; Pilih Site Keeper &mdash;</option>
                                            @foreach($keeperOptions as $opt)
                                                <option value="{{ $opt }}" @selected(($row['name'] ?? '') === $opt)>{{ $opt }}</option>
                                            @endforeach
                                        </select>
                                        @error('keepers.' . $i . '.name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-outline-danger js-keeper-remove" title="Hapus baris"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    {{-- Cetakan baris kosong (belum di-init searchable) untuk tombol Tambah. --}}
                    <template id="keeperRowTemplate">
                        <div class="form-row align-items-start js-keeper-row">
                            <div class="col-md-11">
                                <div class="form-group">
                                    <select name="keepers[0][name]" data-searchable class="form-control">
                                        <option value="">&mdash; Pilih Site Keeper &mdash;</option>
                                        @foreach($keeperOptions as $opt)
                                            <option value="{{ $opt }}">{{ $opt }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-outline-danger js-keeper-remove" title="Hapus baris"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="d-flex justify-content-end mb-4" style="gap:8px;">
                <a href="{{ url('/site') }}" class="btn btn-secondary"><i class="fas fa-times mr-1"></i> Batal</a>
                <button type="submit" class="btn btn-success" id="btnSaveSite"><i class="fas fa-save mr-1"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Site' }}</button>
            </div>
        </form>

    </div>
</div>
@endsection

@include('master-data._styles')

@push('styles')
<style>
.site-create-body .master-form-section {
    font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase;
    color:var(--text-faint); border-bottom:1px solid var(--border);
    padding-bottom:6px; margin:6px 0 14px;
}
.site-create-body .master-form-section:not(.is-first) { margin-top:10px; }
.site-create-body .tss-btn, .master-page .tss-multi-btn { min-height:38px; }

/* Tombol hapus baris Site Keeper: kotak kecil sejajar tinggi select. */
.js-keeper-remove {
    width:38px; height:38px; padding:0; line-height:1;
    display:inline-flex; align-items:center; justify-content:center;
}
</style>
@endpush

@push('scripts')
<script>
$(function () {
    var $form = $('#frmSiteCreate');

    TesysSearchableSelect.init('#frmSiteCreate select[data-searchable]', {});

    function loadCascade($sel, parentVal, selected) {
        $sel.find('option').not(':first').remove();
        if (!parentVal) {
            $sel.val('').trigger('tss:sync');
            return $.Deferred().resolve().promise();
        }
        return $.getJSON(@json(url('/wilayah')) + '/' + $sel.data('level') + '/options', { parent_id: parentVal })
            .then(function (rows) {
                rows.forEach(function (r) { $sel.append($('<option>').val(r.id).text(r.name)); });
                $sel.val(selected ? String(selected) : '').trigger('tss:sync');
            });
    }

    $form.on('change', 'select[data-child]', function () {
        var $child = $('#fld_' + $(this).data('child'));
        loadCascade($child, this.value, null).then(function () { $child.trigger('change'); });
    });

    // Setelah validasi gagal: muat ulang opsi wilayah berjenjang sesuai isian lama.
    var old = {{ \Illuminate\Support\Js::from((object) collect($values)->merge(old())->only(['pulau_id', 'provinsi_id', 'kota_id', 'kecamatan_id', 'desa_id'])->all()) }};
    var chain = $.Deferred().resolve().promise();
    $form.find('select[data-level]').each(function () {
        var $sel = $(this);
        chain = chain.then(function () { return loadCascade($sel, old[$sel.data('parent')], old[$sel.attr('name')]); });
    });

    /*
    | Baris Site Keeper: indeks nama field ditulis ulang tiap kali baris
    | ditambah/dihapus supaya tetap berurutan saat divalidasi.
    */
    function reindexKeepers() {
        $('#keeperRows .js-keeper-row').each(function (i) {
            $(this).find('[name^="keepers["]').each(function () {
                this.name = this.name.replace(/^keepers\[\d+\]/, 'keepers[' + i + ']');
            });
        });
    }

    $('#btnAddKeeper').on('click', function () {
        var $row = $($('#keeperRowTemplate').html()).filter('.js-keeper-row');
        $('#keeperRows').append($row);
        reindexKeepers();
        TesysSearchableSelect.init($row.find('select[data-searchable]'), {});
    });

    $('#keeperRows').on('click', '.js-keeper-remove', function () {
        if ($('#keeperRows .js-keeper-row').length === 1) {
            $(this).closest('.js-keeper-row').find('select').val('').trigger('tss:sync');

            return;
        }
        $(this).closest('.js-keeper-row').remove();
        reindexKeepers();
    });

    $form.on('submit', function () {
        $('#btnSaveSite').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');
    });
});
</script>
@endpush
