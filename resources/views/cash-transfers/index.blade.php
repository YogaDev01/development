@extends('layouts.app')

@section('title', 'Internal Transfer')

@php
    $badge = ['PENDING' => 'badge-warning', 'APPROVED' => 'badge-success', 'REJECTED' => 'badge-danger', 'CANCELLED' => 'badge-secondary'];
    $lbl   = ['PENDING' => 'Menunggu', 'APPROVED' => 'Disetujui', 'REJECTED' => 'Ditolak', 'CANCELLED' => 'Dibatalkan'];
    $rp    = fn ($n) => 'Rp ' . number_format((int) $n, 0, ',', '.');
@endphp

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        @if(session('success'))<div class="alert alert-success rounded-0 shadow-sm mb-0">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ session('error') }}</div>@endif

        <div class="tw:sr-only">
            <h1>Internal Transfer</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Cash &amp; Bank</li>
                <li class="breadcrumb-item active">Internal Transfer</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Cash & Bank" title="Internal Transfer"
            description="Pindah dana antar rekening perusahaan. Saldo baru berubah setelah disetujui; total kas konsolidasi tidak berubah, biaya transfer dicatat sebagai pengeluaran terpisah.">
            <x-slot:actions>
                @if($canCreate)<x-tw.button href="{{ route('cash-transfers.create') }}"><i class="fas fa-plus" aria-hidden="true"></i> Transfer Baru</x-tw.button>@endif
            </x-slot:actions>
        </x-tw.page-header>

        @include('partials.list-toolbar', [
            'prefix' => 'trf',
            'filters' => [['id' => 'Status', 'label' => 'Status'], ['id' => 'From', 'label' => 'Dari Rekening'], ['id' => 'To', 'label' => 'Ke Rekening']],
            'placeholder' => 'No. / rekening',
        ])

        <x-tw.card padding="sm">
            <div class="table-responsive">
                <table id="tblTransfers" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                    <thead class="bg-light"><tr>
                        <th scope="col" class="text-nowrap">No.</th><th scope="col" class="text-nowrap">Tanggal</th><th scope="col">Dari</th><th scope="col">Ke</th>
                        <th scope="col" class="text-right text-nowrap">Nominal</th><th scope="col" class="text-right text-nowrap">Biaya</th><th scope="col">Dibuat</th><th scope="col">Status</th>
                    </tr></thead>
                    <tbody>
                    @forelse($transfers as $t)
                        <tr data-status="{{ $t->status }}" data-from="{{ $t->fromFund->name ?? '-' }}" data-to="{{ $t->toFund->name ?? '-' }}">
                            <td class="text-nowrap"><a href="{{ route('cash-transfers.show', $t->id) }}">{{ $t->transfer_number }}</a></td>
                            <td class="text-nowrap" data-order="{{ $t->transfer_date->format('Y-m-d') }}">{{ $t->transfer_date->format('d M Y') }}</td>
                            <td>{{ $t->fromFund->name ?? '-' }}</td>
                            <td>{{ $t->toFund->name ?? '-' }}</td>
                            <td class="text-right text-nowrap" data-order="{{ (int) $t->amount }}">{{ $rp($t->amount) }}</td>
                            <td class="text-right text-nowrap" data-order="{{ (int) $t->fee }}">{{ $rp($t->fee) }}</td>
                            <td>{{ $t->creator->username ?? '-' }}</td>
                            <td><span class="badge {{ $badge[$t->status] ?? 'badge-secondary' }}">{{ $lbl[$t->status] ?? $t->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty title="Belum ada transfer" message="Transfer antar rekening akan tampil di sini." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
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
    TesysListTable.init({
        table: '#tblTransfers', order: [[1, 'desc']], search: '#trfSearch', clear: '#trfClear',
        filters: [
            { target: '#trfFilterStatus', key: 'status', label: 'Semua Status', valueLabels: @json($lbl), search: false },
            { target: '#trfFilterFrom', key: 'from', label: 'Semua Rekening' },
            { target: '#trfFilterTo', key: 'to', label: 'Semua Rekening' }
        ]
    });
});
</script>
@endpush
