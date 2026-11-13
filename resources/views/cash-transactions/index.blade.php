@extends('layouts.app')

@section('title', 'Cash Transactions')

@php $rp = fn ($n) => 'Rp ' . number_format((int) $n, 0, ',', '.'); @endphp

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        <div class="tw:sr-only">
            <h1>Cash Transactions</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Cash &amp; Bank</li>
                <li class="breadcrumb-item active">Cash Transactions</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Cash & Bank" title="Cash Transactions"
            description="Seluruh pergerakan kas per rekening dari Treasury, Cash Receipt, entri manual, transfer, dan adjustment. Sisi transfer internal ditandai dan tidak dihitung ke total Cash In/Out." />

        <x-stat-cards :cards="[
            ['Cash In (tanpa transfer)', $rp($sum['in']), 'success', 'fas fa-arrow-down'],
            ['Cash Out (tanpa transfer)', $rp($sum['out']), 'danger', 'fas fa-arrow-up'],
            ['Net', $rp($sum['in'] - $sum['out']), 'primary', 'fas fa-balance-scale'],
            ['Volume transfer internal', $rp($sum['transfer']), 'secondary', 'fas fa-random'],
        ]" />

        <x-tw.card padding="sm">
            <form method="GET" action="{{ route('cash-transactions.index') }}" class="form-row align-items-end">
                <div class="form-group col-md-2 col-6 mb-2"><label style="font-size:11px;">Dari</label><input type="date" name="from" value="{{ $filters['from'] }}" class="form-control form-control-sm"></div>
                <div class="form-group col-md-2 col-6 mb-2"><label style="font-size:11px;">Sampai</label><input type="date" name="to" value="{{ $filters['to'] }}" class="form-control form-control-sm"></div>
                <div class="form-group col-md-2 col-6 mb-2"><label style="font-size:11px;">Rekening</label>
                    <select name="source_fund_id" class="form-control form-control-sm"><option value="">Semua</option>@foreach($funds as $f)<option value="{{ $f->id }}" @selected((string) $filters['source_fund_id'] === (string) $f->id)>{{ $f->name }}</option>@endforeach</select></div>
                <div class="form-group col-md-2 col-6 mb-2"><label style="font-size:11px;">Sumber</label>
                    <select name="module" class="form-control form-control-sm"><option value="">Semua</option>@foreach($modules as $k => $l)<option value="{{ $k }}" @selected($filters['module'] === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="form-group col-md-1 col-6 mb-2"><label style="font-size:11px;">Arah</label>
                    <select name="direction" class="form-control form-control-sm"><option value="">Semua</option><option value="IN" @selected($filters['direction'] === 'IN')>In</option><option value="OUT" @selected($filters['direction'] === 'OUT')>Out</option></select></div>
                <div class="form-group col-md-2 col-6 mb-2"><label style="font-size:11px;">Cari</label><input type="search" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="Deskripsi / referensi"></div>
                <div class="form-group col-md-1 col-12 mb-2 d-flex" style="gap:6px;">
                    <button class="btn btn-sm btn-primary">Filter</button>
                    <a class="btn btn-sm btn-outline-secondary" title="Unduh CSV" href="{{ route('cash-transactions.index', array_merge(request()->query(), ['export' => 'csv'])) }}"><i class="fas fa-download"></i></a>
                </div>
            </form>
        </x-tw.card>

        <x-tw.card padding="sm">
            <div class="table-responsive">
                <table id="tblCashTx" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                    <thead class="bg-light"><tr>
                        <th scope="col" class="text-nowrap">Tanggal</th><th scope="col" class="text-nowrap">Referensi</th><th scope="col">Deskripsi</th><th scope="col">Sumber</th><th scope="col">Rekening</th>
                        <th scope="col" class="text-right text-nowrap">Cash In</th><th scope="col" class="text-right text-nowrap">Cash Out</th>
                    </tr></thead>
                    <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td class="text-nowrap" data-order="{{ $r['date']->format('Y-m-d') }}">{{ $r['date']->format('d M Y') }}</td>
                            <td class="text-nowrap">@if($r['url'])<a href="{{ $r['url'] }}">{{ $r['reference_number'] }}</a>@else{{ $r['reference_number'] }}@endif</td>
                            <td>{{ $r['description'] }}</td>
                            <td>{{ $r['category'] }}@if(!empty($r['is_transfer'])) <span class="badge badge-info">Transfer</span>@endif</td>
                            <td>{{ $r['source_fund'] }}</td>
                            <td class="text-right text-nowrap" data-order="{{ $r['direction'] === 'IN' ? (int) $r['amount'] : 0 }}">{{ $r['direction'] === 'IN' ? $rp($r['amount']) : '' }}</td>
                            <td class="text-right text-nowrap" data-order="{{ $r['direction'] === 'OUT' ? (int) $r['amount'] : 0 }}">{{ $r['direction'] === 'OUT' ? $rp($r['amount']) : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty title="Tidak ada transaksi" message="Ubah filter atau periode." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($total > 1000)<div class="text-muted mt-2" style="font-size:12px;">Menampilkan 1.000 dari {{ $total }} baris. Persempit filter atau unduh CSV.</div>@endif
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
    /* Rentang, rekening, sumber, arah, dan kata kunci disaring di server (menentukan dataset
       dan isi unduhan CSV); tabel ini menambah sortir, pagination, dan ukuran halaman. */
    TesysListTable.init({ table: '#tblCashTx', order: [[0, 'desc']] });
});
</script>
@endpush
