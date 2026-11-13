@extends('layouts.app')

@section('title', 'SLA Site ' . $period->periodLabel())

@php
    $filled = $values->count();
    $avg = \App\Models\SiteSla::average($values->all());
    $below = $values->filter(fn ($v) => $v < 100)->count();
    $fmt = fn ($v) => $v === null ? '-' : rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
@endphp

@section('content')
<div class="content">
    <div class="container-fluid">
      <div class="tw:flex tw:flex-col tw:gap-4 tw:py-4">

        <div class="tw:sr-only">
            <h1>SLA Site</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('site-sla.index', ['year' => $period->year]) }}">SLA Site</a></li>
                <li class="breadcrumb-item active">{{ $period->periodLabel() }}</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="SLA Site · {{ $period->periodLabel() }}" title="{{ $period->customer->name ?? '-' }}"
            description="Isi SLA (0–100) per site. Kosongkan untuk site yang belum dihitung; nilai di bawah 100 ditandai merah.">
            <x-slot:actions>
                <x-tw.button variant="secondary" size="sm" href="{{ route('site-sla.index', ['year' => $period->year]) }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali</x-tw.button>
                <x-tw.button variant="danger" size="sm" type="submit" form="frmSlaDelete"><i class="fas fa-trash" aria-hidden="true"></i> Hapus</x-tw.button>
                <x-tw.button id="slaSave"><i class="fas fa-save" aria-hidden="true"></i> Simpan</x-tw.button>
            </x-slot:actions>
        </x-tw.page-header>

        <div class="row" style="margin-left:-6px;margin-right:-6px;">
            <div class="col-6 col-lg-3 px-2 mb-2"><x-tw.stat label="Site Customer" :value="$sites->count()" tone="neutral" /></div>
            <div class="col-6 col-lg-3 px-2 mb-2"><x-tw.stat id="slaQty" label="Qty Site Terisi" :value="$filled" tone="progress" /></div>
            <div class="col-6 col-lg-3 px-2 mb-2"><x-tw.stat id="slaBelow" label="Di Bawah 100%" :value="$below" tone="attention" /></div>
            <div class="col-6 col-lg-3 px-2 mb-2"><x-tw.stat id="slaAvg" label="Average SLA" :value="$avg === null ? '-' : $fmt($avg) . '%'" tone="positive" /></div>
        </div>

        <x-tw.card padding="sm">
            <div class="tw:mb-3 tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-3">
                <div class="input-group input-group-sm" style="width:260px;">
                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span></div>
                    <input type="search" id="slaSearch" class="form-control" placeholder="Cari Site ID / lokasi..." aria-label="Cari site">
                </div>
                <div class="tw:flex tw:flex-wrap tw:items-center tw:gap-2">
                    <label class="small mb-0 tw:text-text-dim" for="slaFillAll">Isi semua yang kosong</label>
                    <input type="number" id="slaFillAll" step="0.01" min="0" max="100" class="form-control form-control-sm text-right" style="width:90px;" placeholder="100">
                    <x-tw.button variant="secondary" size="sm" id="slaFillBtn">Terapkan</x-tw.button>
                    <x-tw.button variant="secondary" size="sm" href="{{ route('site-sla.template', $period) }}"><i class="fas fa-file-download" aria-hidden="true"></i> Template Excel</x-tw.button>
                    <x-tw.button variant="secondary" size="sm" data-toggle="modal" data-target="#mdlSlaImport"><i class="fas fa-file-import" aria-hidden="true"></i> Import Excel</x-tw.button>
                </div>
            </div>
            <div id="slaImportMsg" class="d-none mb-3"></div>

            @if ($sites->isEmpty())
                <x-empty title="Customer ini belum punya site" message="Tambahkan site untuk customer ini di Master Data Site." />
            @else
                <x-tw.table id="tblSlaDetail" dense>
                    <x-slot:head>
                        <tr>
                            <th style="width:50px">No</th>
                            <th>Site ID</th>
                            <th>Nama Lokasi</th>
                            <th>Provinsi</th>
                            <th>Kabupaten/Kota</th>
                            <th class="text-right" style="width:140px">SLA (%)</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($sites as $i => $s)
                        @php $v = $values[$s->id] ?? null; @endphp
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td class="text-nowrap">{{ $s->tid }} @if ($s->status === 'INACTIVE')<x-tw.badge tone="neutral">Inactive</x-tw.badge>@endif</td>
                            <td>{{ $s->sitename }}</td>
                            <td>{{ $s->provinsi->name ?? '-' }}</td>
                            <td>{{ $s->kota->name ?? '-' }}</td>
                            <td><input type="number" step="0.01" min="0" max="100"
                                class="form-control form-control-sm text-right sla-input {{ $v !== null && $v < 100 ? 'tw:text-danger' : '' }}"
                                data-site="{{ $s->id }}" value="{{ $v }}" aria-label="SLA {{ $s->tid }}"></td>
                        </tr>
                    @endforeach
                </x-tw.table>
            @endif
        </x-tw.card>
      </div>
    </div>
</div>

<form id="frmSlaDelete" method="POST" action="{{ route('site-sla.destroy', $period) }}" class="d-none"
      onsubmit="return confirm('Hapus periode SLA {{ $period->periodLabel() }} beserta seluruh rinciannya?')">
    @csrf @method('DELETE')
</form>
<form id="slaForm" method="POST" action="{{ route('site-sla.update', $period) }}" class="d-none">
    @csrf @method('PUT')
    <input type="hidden" name="payload" id="slaPayload">
</form>
<x-tw.modal id="mdlSlaImport">
    <x-slot:title>Import SLA dari Excel</x-slot:title>
    <form id="frmSlaImport" enctype="multipart/form-data">
        <p class="small tw:text-text-dim mb-2">Pakai file dari <b>Template Excel</b> (kolom Site ID dan SLA (%)). Nilai hasil import diisikan ke tabel — periksa lalu tekan <b>Simpan</b>. Sel SLA kosong di file dilewati.</p>
        <input type="file" name="file" id="slaImportFile" class="form-control-file" accept=".xlsx,.xls" required>
    </form>
    <x-slot:footer>
        <x-tw.button variant="secondary" size="sm" data-dismiss="modal">Batal</x-tw.button>
        <x-tw.button size="sm" id="slaImportBtn"><i class="fas fa-file-import" aria-hidden="true"></i> Import</x-tw.button>
    </x-slot:footer>
</x-tw.modal>
@endsection

@push('scripts')
<script>
(function () {
    var dirty = false;
    function fmt(v) { return v.toFixed(2).replace(/\.?0+$/, '').replace('.', ','); }
    // Angka x-tw.stat ada di elemen teks kedua (setelah label).
    function setStat(id, text) { $('#' + id).children().eq(1).text(text); }

    function recalc() {
        var sum = 0, n = 0, below = 0;
        $('.sla-input').each(function () {
            var v = this.value.trim(), bad = v !== '' && (isNaN(+v) || +v < 0 || +v > 100);
            $(this).toggleClass('is-invalid', bad).toggleClass('tw:text-danger', !bad && v !== '' && +v < 100);
            if (v !== '' && !bad) { sum += +v; n++; if (+v < 100) below++; }
        });
        setStat('slaQty', n);
        setStat('slaBelow', below);
        setStat('slaAvg', n ? fmt(sum / n) + '%' : '-');
    }

    $('#tblSlaDetail').on('input', '.sla-input', function () { dirty = true; recalc(); });

    $('#slaSearch').on('input', function () {
        var q = this.value.toLowerCase();
        $('#tblSlaDetail tbody tr').each(function () { $(this).toggle($(this).text().toLowerCase().indexOf(q) > -1); });
    });

    $('#slaFillBtn').on('click', function () {
        var v = $('#slaFillAll').val().trim();
        if (v === '' || isNaN(+v) || +v < 0 || +v > 100) { alert('Isi nilai 0–100.'); return; }
        $('.sla-input:visible').filter(function () { return this.value.trim() === ''; }).val(v);
        dirty = true; recalc();
    });

    $('#slaImportBtn').on('click', function () {
        var file = $('#slaImportFile')[0].files[0];
        if (!file) { alert('Pilih file Excel terlebih dahulu.'); return; }
        var fd = new FormData();
        fd.append('file', file);
        fd.append('_token', $('meta[name="csrf-token"]').attr('content') || $('#slaForm input[name="_token"]').val());
        var $btn = $(this).prop('disabled', true);
        $.ajax({ url: @json(route('site-sla.import', $period)), method: 'POST', data: fd, processData: false, contentType: false })
            .done(function (res) { applyImport(res); })
            .fail(function (xhr) {
                var r = xhr.responseJSON || {};
                applyImport({ rows: [], errors: r.errors ? (Array.isArray(r.errors) ? r.errors : Object.values(r.errors).flat()) : ['Import gagal (kode ' + xhr.status + ').'] });
            })
            .always(function () { $btn.prop('disabled', false); });
    });

    function applyImport(res) {
        var n = 0;
        (res.rows || []).forEach(function (r) {
            var $i = $('.sla-input[data-site="' + r.site_id + '"]');
            if ($i.length) { $i.val(r.value); n++; }
        });
        if (n) { dirty = true; recalc(); }
        var errs = res.errors || [];
        var html = '<div class="alert ' + (errs.length ? 'alert-warning' : 'alert-success') + ' py-2 mb-0" style="font-size:12.5px;">'
            + '<b>' + n + ' nilai SLA diisi dari Excel.</b> ' + (n ? 'Periksa lalu tekan Simpan.' : '');
        if (errs.length) {
            html += '<ul class="mb-0 mt-1 pl-3">' + errs.slice(0, 20).map(function (e) { return '<li>' + $('<div>').text(e).html() + '</li>'; }).join('')
                + (errs.length > 20 ? '<li>… dan ' + (errs.length - 20) + ' lainnya</li>' : '') + '</ul>';
        }
        $('#slaImportMsg').html(html + '</div>').removeClass('d-none');
        $('#mdlSlaImport').modal('hide');
        $('#slaImportFile').val('');
    }

    $('#slaSave').on('click', function () {
        if ($('.sla-input.is-invalid').length) { alert('Perbaiki nilai yang tidak valid (0–100).'); return; }
        var payload = {};
        $('.sla-input').each(function () { payload[$(this).data('site')] = this.value.trim(); });
        $('#slaPayload').val(JSON.stringify(payload));
        dirty = false;
        $('#slaForm').trigger('submit');
    });

    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
})();
</script>
@endpush

@push('styles')
{{-- Utilitas tw: (x-tw.*) dimuat per halaman, sama seperti Cost Request. --}}
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush
