@extends('layouts.app')

@section('title', 'Cash Adjustment')

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
            <h1>Cash Adjustment</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Cash &amp; Bank</li>
                <li class="breadcrumb-item active">Cash Adjustment</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Cash & Bank" title="Cash Adjustment"
            description="Satu-satunya jalan menyesuaikan saldo. Wajib alasan dan persetujuan pengguna lain; tidak ada edit saldo langsung.">
            <x-slot:actions>
                @if($canCreate)<x-tw.button href="{{ route('cash-adjustments.create') }}"><i class="fas fa-plus" aria-hidden="true"></i> Adjustment Baru</x-tw.button>@endif
            </x-slot:actions>
        </x-tw.page-header>

        @include('partials.list-toolbar', [
            'prefix' => 'adj',
            'filters' => [['id' => 'Status', 'label' => 'Status'], ['id' => 'Fund', 'label' => 'Rekening'], ['id' => 'Type', 'label' => 'Jenis']],
            'placeholder' => 'No. / alasan',
        ])

        <x-tw.card padding="sm">
            <div class="table-responsive">
                <table id="tblAdjustments" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                    <thead class="bg-light"><tr>
                        <th scope="col" class="text-nowrap">No.</th><th scope="col" class="text-nowrap">Tanggal Efektif</th><th scope="col">Rekening</th><th scope="col">Jenis</th>
                        <th scope="col" class="text-right text-nowrap">Masuk</th><th scope="col" class="text-right text-nowrap">Keluar</th><th scope="col">Alasan</th><th scope="col">Status</th>
                    </tr></thead>
                    <tbody>
                    @forelse($adjustments as $a)
                        <tr data-status="{{ $a->status }}" data-fund="{{ $a->sourceFund->name ?? '-' }}" data-type="{{ $a->type_label }}">
                            <td class="text-nowrap"><a href="{{ route('cash-adjustments.show', $a->id) }}">{{ $a->adjustment_number }}</a></td>
                            <td class="text-nowrap" data-order="{{ $a->effective_date->format('Y-m-d') }}">{{ $a->effective_date->format('d M Y') }}</td>
                            <td>{{ $a->sourceFund->name ?? '-' }}</td>
                            <td>{{ $a->type_label }}</td>
                            <td class="text-right text-nowrap" data-order="{{ $a->direction === 'IN' ? (int) $a->amount : 0 }}">{{ $a->direction === 'IN' ? $rp($a->amount) : '' }}</td>
                            <td class="text-right text-nowrap" data-order="{{ $a->direction === 'OUT' ? (int) $a->amount : 0 }}">{{ $a->direction === 'OUT' ? $rp($a->amount) : '' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($a->reason, 60) }}</td>
                            <td><span class="badge {{ $badge[$a->status] ?? 'badge-secondary' }}">{{ $lbl[$a->status] ?? $a->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty title="Belum ada adjustment" message="Penyesuaian saldo yang diajukan akan tampil di sini." /></td></tr>
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
        table: '#tblAdjustments', order: [[1, 'desc']], search: '#adjSearch', clear: '#adjClear',
        filters: [
            { target: '#adjFilterStatus', key: 'status', label: 'Semua Status', valueLabels: @json($lbl), search: false },
            { target: '#adjFilterFund', key: 'fund', label: 'Semua Rekening' },
            { target: '#adjFilterType', key: 'type', label: 'Semua Jenis' }
        ]
    });
});
</script>
@endpush
