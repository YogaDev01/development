@extends('layouts.app')

@section('title', 'Bank Accounts')

@php $rp = fn ($n) => ($n < 0 ? '-' : '') . 'Rp ' . number_format(abs((int) $n), 0, ',', '.'); @endphp

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        @if(session('success'))<div class="alert alert-success rounded-0 shadow-sm mb-0">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ session('error') }}</div>@endif

        <div class="tw:sr-only">
            <h1>Bank Accounts</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item">Cash &amp; Bank</li>
                <li class="breadcrumb-item active">Bank Accounts</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Cash & Bank" title="Bank Accounts"
            description="Rekening kas dan bank perusahaan. Saldo tidak dapat diedit langsung: berasal dari saldo awal terverifikasi ditambah transaksi.">
            <x-slot:actions>
                @if($canWrite)
                    <x-tw.button href="{{ route('bank-accounts.create') }}"><i class="fas fa-plus" aria-hidden="true"></i> Tambah Rekening</x-tw.button>
                @endif
            </x-slot:actions>
        </x-tw.page-header>

        <x-stat-cards :cards="[
            ['Total Cash & Bank', $rp($kpi['total']), 'primary', 'fas fa-university'],
            ['Bank', $rp($kpi['bank']), 'secondary', 'fas fa-landmark'],
            ['Cash on Hand', $rp($kpi['cash_on_hand']), 'secondary', 'fas fa-coins'],
            ['Rekening Aktif', $accounts->where('active', true)->count() . ' / ' . $accounts->count(), 'success', 'fas fa-check'],
        ]" />

        @include('partials.list-toolbar', [
            'prefix' => 'ba',
            'filters' => [['id' => 'Type', 'label' => 'Tipe'], ['id' => 'Opening', 'label' => 'Saldo Awal'], ['id' => 'Active', 'label' => 'Status']],
            'placeholder' => 'Kode / nama / bank',
        ])

        <x-tw.card padding="sm">
            <div class="table-responsive">
                <table id="tblBankAccounts" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                    <thead class="bg-light">
                        <tr>
                            <th scope="col" class="text-nowrap">Kode</th><th scope="col">Nama</th><th scope="col">Tipe</th><th scope="col">Bank</th><th scope="col" class="text-nowrap">No. Rekening</th>
                            <th scope="col" class="text-right text-nowrap">Saldo Saat Ini</th><th scope="col" class="text-nowrap">Saldo Awal</th><th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($accounts as $a)
                        @php
                            $f = $a['fund'];
                            $openLabel = $a['has_opening'] ? 'Approved' : ($a['opening_status'] === 'PENDING' ? 'Menunggu' : ($a['opening_status'] === 'REJECTED' ? 'Ditolak' : 'Belum diatur'));
                        @endphp
                        <tr data-type="{{ $f->type_label }}" data-opening="{{ $openLabel }}" data-active="{{ $a['active'] ? 'Aktif' : 'Nonaktif' }}">
                            <td class="text-nowrap">{{ $f->code ?? '-' }}</td>
                            <td><a href="{{ route('bank-accounts.show', $f->id) }}">{{ $f->name }}</a>@if($f->is_main_account) <span class="badge badge-info">Main</span>@endif</td>
                            <td>{{ $f->type_label }}</td>
                            <td>{{ $f->bank->name ?? '-' }}</td>
                            <td class="text-nowrap">{{ $f->masked_account }}</td>
                            <td class="text-right text-nowrap font-weight-bold {{ $a['balance'] < 0 ? 'text-danger' : '' }}" data-order="{{ (int) $a['balance'] }}">{{ $rp($a['balance']) }}</td>
                            <td>
                                @if($a['has_opening'])<span class="badge badge-success">Approved</span>
                                @elseif($a['opening_status'] === 'PENDING')<span class="badge badge-warning">Menunggu</span>
                                @elseif($a['opening_status'] === 'REJECTED')<span class="badge badge-danger">Ditolak</span>
                                @else<span class="badge badge-secondary">Belum diatur</span>@endif
                            </td>
                            <td><span class="badge {{ $a['active'] ? 'badge-success' : 'badge-secondary' }}">{{ $a['active'] ? 'Aktif' : 'Nonaktif' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty title="Belum ada rekening" message="Tambahkan rekening untuk mulai memantau saldo." /></td></tr>
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
        table: '#tblBankAccounts', order: [[1, 'asc']], search: '#baSearch', clear: '#baClear',
        filters: [
            { target: '#baFilterType', key: 'type', label: 'Semua Tipe', search: false },
            { target: '#baFilterOpening', key: 'opening', label: 'Semua Saldo Awal', search: false },
            { target: '#baFilterActive', key: 'active', label: 'Semua Status', search: false }
        ]
    });
});
</script>
@endpush
