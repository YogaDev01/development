{{-- Bilah alat tabel daftar: filter daftar-centang + kotak cari + hapus filter.
     Pasangannya: TesysListTable.init (public/js/tesys-list-table.js), yang mengisi
     tombol filter ke wadah #{prefix}Filter{Id} dan membaca data-<key> pada tiap <tr>.

       @include('partials.list-toolbar', [
           'prefix'      => 'tf',                       // awalan id (wajib)
           'filters'     => [['id' => 'Status', 'label' => 'Status']],   // opsional
           'placeholder' => 'No. / rekening',           // opsional
       ])
--}}
@php
    $tbFilters = $filters ?? [];
    $tbPlaceholder = $placeholder ?? 'Cari di tabel';
@endphp
<x-tw.card padding="sm">
    <div class="form-row align-items-end ts-list-toolbar">
        @foreach($tbFilters as $f)
            <div class="form-group col-lg-2 col-md-4 col-6 mb-2">
                <label class="font-weight-600" style="font-size:11px;">{{ $f['label'] }}</label>
                <div id="{{ $prefix }}Filter{{ $f['id'] }}" class="ts-list-filters"></div>
            </div>
        @endforeach
        <div class="form-group col-lg-3 col-md-4 col-12 mb-2 ml-lg-auto">
            <label class="font-weight-600" style="font-size:11px;" for="{{ $prefix }}Search">Cari</label>
            <div class="d-flex" style="gap:6px;">
                <input type="search" id="{{ $prefix }}Search" class="form-control form-control-sm" placeholder="{{ $tbPlaceholder }}" autocomplete="off">
                <button type="button" id="{{ $prefix }}Clear" class="btn btn-outline-secondary btn-sm text-nowrap" title="Hapus filter dan pencarian">
                    <i class="fas fa-eraser" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </div>
</x-tw.card>

@once
@push('styles')
<style>
/* Tombol filter dibuat TesysColumnFilter; disamakan tingginya dengan kotak isian
   lain di bilah ini supaya satu baris terlihat rata. */
.ts-list-filters .tcf-row-btn {
    width:100%; display:flex; align-items:center; justify-content:space-between;
    height:calc(1.5em + .5rem + 2px); font-size:13px; text-align:left; overflow:hidden;
}
</style>
@endpush
@endonce
