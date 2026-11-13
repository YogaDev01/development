@extends('layouts.app')

@section('title', $data['title'])

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        <div class="tw:sr-only">
            <h1>{{ $data['title'] }}</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Cash &amp; Bank</li>
                <li class="breadcrumb-item"><a href="{{ route('cash-reports.index') }}">Cash Reports</a></li>
                <li class="breadcrumb-item active">{{ $data['title'] }}</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Cash Reports" title="{{ $data['title'] }}" description="{{ $data['description'] }}" />

        <x-tw.card padding="sm">
            <form method="GET" action="{{ route('cash-reports.show', $key) }}" class="form-row align-items-end">
                <div class="form-group col-md-2 col-6 mb-2"><label style="font-size:11px;">Dari</label><input type="date" name="from" value="{{ $filters['from'] }}" class="form-control form-control-sm"></div>
                <div class="form-group col-md-2 col-6 mb-2"><label style="font-size:11px;">Sampai / per tanggal</label><input type="date" name="to" value="{{ $filters['to'] }}" class="form-control form-control-sm"></div>
                <div class="form-group col-md-3 col-6 mb-2"><label style="font-size:11px;">Rekening</label>
                    <select name="fund" class="form-control form-control-sm"><option value="">Semua</option>@foreach($funds as $f)<option value="{{ $f->id }}" @selected($filters['fund'] === $f->id)>{{ $f->name }}</option>@endforeach</select></div>
                @if($key === 'forecast')
                    <div class="form-group col-md-1 col-6 mb-2"><label style="font-size:11px;">Horizon (hari)</label><input type="number" name="days" min="1" max="365" value="{{ $filters['days'] }}" class="form-control form-control-sm"></div>
                @endif
                <div class="form-group col-md-4 mb-2 d-flex" style="gap:6px;">
                    <button class="btn btn-sm btn-primary">Terapkan</button>
                    @if($canExport)
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('cash-reports.show', [$key] + array_merge(request()->query(), ['format' => 'csv'])) }}"><i class="fas fa-download"></i> CSV / Excel</a>
                        <a class="btn btn-sm btn-outline-secondary" target="_blank" href="{{ route('cash-reports.show', [$key] + array_merge(request()->query(), ['print' => 1])) }}"><i class="fas fa-print"></i> Cetak</a>
                    @endif
                </div>
            </form>
        </x-tw.card>

        <x-tw.card padding="sm">
            <div class="d-flex justify-content-end mb-2">
                <div class="d-flex" style="gap:6px; min-width:260px;">
                    <input type="search" id="crSearch" class="form-control form-control-sm" placeholder="Cari di laporan" aria-label="Cari di laporan" autocomplete="off">
                    <button type="button" id="crClear" class="btn btn-outline-secondary btn-sm text-nowrap" title="Hapus pencarian"><i class="fas fa-eraser" aria-hidden="true"></i></button>
                </div>
            </div>
            @include('cash-reports._table')
        </x-tw.card>
    </div>
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush

@push('scripts')
<script src="@aset('js/tesys-list-table.js')"></script>
<script>
$(function () {
    /* Periode dan rekening disaring di server (menentukan angka laporan & unduhan);
       tabel menambah pencarian, sortir, dan pagination. Baris Total (tfoot) tidak ikut disaring. */
    TesysListTable.init({ table: '#tblCashReport', search: '#crSearch', clear: '#crClear', pageLength: 50 });
});
</script>
@endpush
