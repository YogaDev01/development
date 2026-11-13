@extends('layouts.app')

@section('title', 'SLA Site')

@php
    $fmt = fn ($v) => $v === null ? '-' : rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    $allAvg = $periods->whereNotNull('details_avg_sla_percent')->avg('details_avg_sla_percent');
@endphp

@section('content')
<div class="content">
    <div class="container-fluid">
      <div class="tw:flex tw:flex-col tw:gap-4 tw:py-4">

        {{-- Judul & breadcrumb dipindah shell ke topbar (tesys-shell.js hoistPageTitle). --}}
        <div class="tw:sr-only">
            <h1>SLA Site</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Operations</a></li>
                <li class="breadcrumb-item active">SLA Site</li>
            </ol>
        </div>

        <x-tw.page-header title="SLA Site per Bulan"
            description="Input SLA per customer per periode. Klik baris untuk mengisi rincian SLA per site; rekap tahunan tampil di Summary Operations.">
            <x-slot:actions>
                <x-tw.button data-toggle="modal" data-target="#mdlSlaNew"><i class="fas fa-plus" aria-hidden="true"></i> Tambah SLA</x-tw.button>
            </x-slot:actions>
        </x-tw.page-header>

        <div class="row" style="margin-left:-6px;margin-right:-6px;">
            <div class="col-6 col-lg-3 px-2 mb-2"><x-tw.stat label="Periode {{ $year }}" :value="$periods->count()" tone="neutral" hint="entri customer × bulan" /></div>
            <div class="col-6 col-lg-3 px-2 mb-2"><x-tw.stat label="Customer" :value="$periods->pluck('customer_id')->unique()->count()" tone="progress" /></div>
            <div class="col-6 col-lg-3 px-2 mb-2"><x-tw.stat label="Total Site Terisi" :value="number_format($periods->sum('details_count'), 0, ',', '.')" tone="done" /></div>
            <div class="col-6 col-lg-3 px-2 mb-2"><x-tw.stat label="Average SLA" :value="$allAvg === null ? '-' : $fmt(round($allAvg, 2)) . '%'" :tone="$allAvg !== null && $allAvg < 100 ? 'danger' : 'positive'" hint="rata-rata seluruh periode" /></div>
        </div>

        <x-tw.card padding="sm">
            <form method="GET" action="{{ route('site-sla.index') }}" class="tw:mb-3 tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-3">
                <div class="tw:flex tw:flex-wrap tw:items-center tw:gap-2">
                    <div class="input-group input-group-sm" style="width:240px;">
                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span></div>
                        <input type="search" id="slaSearch" class="form-control" placeholder="Cari customer / periode..." aria-label="Cari data">
                    </div>
                    <select name="year" class="form-control form-control-sm" style="width:auto;" onchange="this.form.submit()" aria-label="Tahun">
                        @for ($y = now()->year + 1; $y >= 2024; $y--)
                            <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                    <select name="customer_id" class="form-control form-control-sm" style="width:auto;max-width:260px;" onchange="this.form.submit()" aria-label="Customer">
                        <option value="">Semua customer</option>
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}" {{ $customerId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                    @if ($customerId)
                        <x-tw.button variant="ghost" size="sm" href="{{ route('site-sla.index', ['year' => $year]) }}" title="Hapus filter" aria-label="Hapus filter"><i class="fas fa-eraser" aria-hidden="true"></i></x-tw.button>
                    @endif
                </div>
            </form>

            @if ($periods->isEmpty())
                <x-empty title="Belum ada SLA {{ $year }}" message="Klik Tambah SLA, pilih customer dan periode, lalu isi SLA per site." />
            @else
                <x-tw.table id="tblSlaPeriods">
                    <x-slot:head>
                        <tr>
                            <th style="width:50px">No</th>
                            <th>Customer</th>
                            <th>Periode</th>
                            <th class="text-right">Qty Site</th>
                            <th class="text-right">Average SLA</th>
                            <th>Terakhir Diubah</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($periods as $i => $p)
                        @php $avg = $p->details_avg_sla_percent; @endphp
                        <tr style="cursor:pointer" data-href="{{ route('site-sla.show', $p) }}">
                            <td>{{ $i + 1 }}</td>
                            <td><a href="{{ route('site-sla.show', $p) }}" class="tw:font-heading">{{ $p->customer->name ?? '-' }}</a></td>
                            <td data-order="{{ $p->year * 100 + $p->month }}">{{ $p->periodLabel() }}</td>
                            <td class="text-right tw:tabular-nums">{{ number_format($p->details_count, 0, ',', '.') }}</td>
                            <td class="text-right tw:tabular-nums tw:font-heading {{ $avg !== null && $avg < 100 ? 'tw:text-danger' : '' }}" data-order="{{ $avg ?? -1 }}">{{ $avg === null ? '-' : $fmt(round($avg, 2)) . '%' }}</td>
                            <td class="tw:text-text-dim">{{ $p->updater->name ?? '-' }} · {{ $p->updated_at?->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                    <x-slot:foot>
                        <tr>
                            <th colspan="3" class="text-right">Total</th>
                            <th class="text-right">{{ number_format($periods->sum('details_count'), 0, ',', '.') }}</th>
                            <th class="text-right">{{ $allAvg === null ? '-' : $fmt(round($allAvg, 2)) . '%' }}</th>
                            <th></th>
                        </tr>
                    </x-slot:foot>
                </x-tw.table>
            @endif
        </x-tw.card>
      </div>
    </div>
</div>

<x-tw.modal id="mdlSlaNew">
    <x-slot:title>Tambah SLA Site</x-slot:title>
    <form method="POST" action="{{ route('site-sla.store') }}" id="frmSlaNew">
        @csrf
        <div class="form-group">
            <label class="small mb-1" for="slaNewCustomer">Customer <span class="text-danger">*</span></label>
            <select name="customer_id" id="slaNewCustomer" class="form-control form-control-sm" required>
                <option value="">Pilih customer</option>
                @foreach ($customers as $c)
                    <option value="{{ $c->id }}" {{ old('customer_id', $customerId) == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group mb-0">
            <label class="small mb-1" for="slaNewPeriod">Periode <span class="text-danger">*</span></label>
            <input type="month" name="period" id="slaNewPeriod" class="form-control form-control-sm" required value="{{ old('period', now()->subMonth()->format('Y-m')) }}">
            <small class="text-muted">Bila customer + periode sudah ada, entri yang ada akan dibuka.</small>
        </div>
    </form>
    <x-slot:footer>
        <x-tw.button variant="secondary" size="sm" data-dismiss="modal">Batal</x-tw.button>
        <x-tw.button size="sm" type="submit" form="frmSlaNew">Lanjut isi SLA</x-tw.button>
    </x-slot:footer>
</x-tw.modal>
@endsection

@push('scripts')
<script>
$(function () {
    var $t = $('#tblSlaPeriods');
    if (!$t.length) return;
    $t.on('click', 'tbody tr[data-href]', function (e) {
        if (!$(e.target).closest('a,button').length) window.location = $(this).data('href');
    });
    if (!$.fn.DataTable) return;
    var dt = $t.DataTable({ paging: false, info: false, order: [[2, 'desc']], dom: 't',
        columnDefs: [{ targets: 0, orderable: false }] });
    $('#slaSearch').on('input', function () { dt.search(this.value).draw(); });
});
</script>
@endpush

@push('styles')
{{-- Utilitas tw: (x-tw.*) dimuat per halaman, sama seperti Cost Request. --}}
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush
