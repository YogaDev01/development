@extends('layouts.app')

@section('title', 'Reconciliation')

@php
    $badge = ['DRAFT' => 'badge-secondary', 'SUBMITTED' => 'badge-warning', 'APPROVED' => 'badge-success'];
    $lbl   = ['DRAFT' => 'Draft', 'SUBMITTED' => 'Menunggu approval', 'APPROVED' => 'Disetujui · terkunci'];
    $rp    = fn ($n) => ($n < 0 ? '-' : '') . 'Rp ' . number_format(abs((int) $n), 0, ',', '.');
@endphp

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        @if(session('success'))<div class="alert alert-success rounded-0 shadow-sm mb-0">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ session('error') }}</div>@endif

        <div class="tw:sr-only">
            <h1>Reconciliation</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Cash &amp; Bank</li>
                <li class="breadcrumb-item active">Reconciliation</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Cash & Bank" title="Reconciliation"
            description="Bandingkan ledger TESYS dengan rekening koran per rekening dan periode. Rekonsiliasi disetujui = periode terkunci.">
            <x-slot:actions>
                @if($canWork)<x-tw.button href="{{ route('cash-reconciliation.create') }}"><i class="fas fa-plus" aria-hidden="true"></i> Rekonsiliasi Baru</x-tw.button>@endif
            </x-slot:actions>
        </x-tw.page-header>

        @include('partials.list-toolbar', [
            'prefix' => 'rec',
            'filters' => [['id' => 'Status', 'label' => 'Status'], ['id' => 'Fund', 'label' => 'Rekening']],
            'placeholder' => 'No. / rekening',
        ])

        <x-tw.card padding="sm">
            <div class="table-responsive">
                <table id="tblRecon" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                    <thead class="bg-light"><tr>
                        <th scope="col" class="text-nowrap">No.</th><th scope="col">Rekening</th><th scope="col" class="text-nowrap">Periode</th>
                        <th scope="col" class="text-right text-nowrap">Saldo Rekening Koran</th><th scope="col">Dibuat</th><th scope="col">Status</th>
                    </tr></thead>
                    <tbody>
                    @forelse($recs as $r)
                        <tr data-status="{{ $r->status }}" data-fund="{{ $r->sourceFund->name ?? '-' }}">
                            <td class="text-nowrap"><a href="{{ route('cash-reconciliation.show', $r->id) }}">{{ $r->number }}</a></td>
                            <td>{{ $r->sourceFund->name ?? '-' }}</td>
                            <td class="text-nowrap" data-order="{{ $r->period_end->format('Y-m-d') }}">{{ $r->period_start->format('d M Y') }} – {{ $r->period_end->format('d M Y') }}</td>
                            <td class="text-right text-nowrap" data-order="{{ (int) $r->statement_ending_balance }}">{{ $rp($r->statement_ending_balance) }}</td>
                            <td>{{ $r->creator->username ?? '-' }}</td>
                            <td><span class="badge {{ $badge[$r->status] ?? 'badge-secondary' }}">{{ $lbl[$r->status] ?? $r->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty title="Belum ada rekonsiliasi" message="Mulai dengan memilih rekening dan periode rekening koran." /></td></tr>
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
        table: '#tblRecon', order: [[2, 'desc']], search: '#recSearch', clear: '#recClear',
        filters: [
            { target: '#recFilterStatus', key: 'status', label: 'Semua Status', valueLabels: @json($lbl), search: false },
            { target: '#recFilterFund', key: 'fund', label: 'Semua Rekening' }
        ]
    });
});
</script>
@endpush
