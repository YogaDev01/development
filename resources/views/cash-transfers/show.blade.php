@extends('layouts.app')

@section('title', $t->transfer_number)

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
            <h1>{{ $t->transfer_number }}</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Cash &amp; Bank</li>
                <li class="breadcrumb-item"><a href="{{ route('cash-transfers.index') }}">Internal Transfer</a></li>
                <li class="breadcrumb-item active">{{ $t->transfer_number }}</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Internal Transfer" title="{{ $t->transfer_number }}"
            description="{{ $t->fromFund->name ?? '-' }} → {{ $t->toFund->name ?? '-' }}" />

        <x-tw.card>
            <table class="table table-sm mb-3" style="font-size:13px;">
                <tr><th class="text-muted font-weight-normal" style="width:200px;">Status</th><td><span class="badge {{ $badge[$t->status] ?? 'badge-secondary' }}">{{ $lbl[$t->status] ?? $t->status }}</span></td></tr>
                <tr><th class="text-muted font-weight-normal">Tanggal</th><td>{{ $t->transfer_date->format('d M Y') }}</td></tr>
                <tr><th class="text-muted font-weight-normal">Nominal</th><td>{{ $rp($t->amount) }}</td></tr>
                <tr><th class="text-muted font-weight-normal">Biaya transfer</th><td>{{ $rp($t->fee) }} <span class="text-muted">(pengeluaran terpisah dari rekening asal)</span></td></tr>
                <tr><th class="text-muted font-weight-normal">Keterangan</th><td>{{ $t->description ?: '-' }}</td></tr>
                <tr><th class="text-muted font-weight-normal">Dibuat oleh</th><td>{{ $t->creator->username ?? '-' }}</td></tr>
                <tr><th class="text-muted font-weight-normal">Diputuskan oleh</th><td>{{ $t->approver->username ?? '-' }} {{ $t->approved_at?->format('d M Y H:i') }}</td></tr>
                @if($t->reject_reason)<tr><th class="text-muted font-weight-normal">Alasan penolakan</th><td class="text-danger">{{ $t->reject_reason }}</td></tr>@endif
                @if($t->attachment)<tr><th class="text-muted font-weight-normal">Lampiran</th><td><a href="{{ route('secure-file', $t->attachment) }}" target="_blank"><i class="fas fa-paperclip"></i> Buka</a></td></tr>@endif
            </table>
            @php $lockDok = \App\Support\PeriodLock::forModel($t, 'Internal Transfer'); @endphp
            @if($lockDok['locked'])
                <div class="alert alert-warning py-2" style="font-size:12px;"><i class="fas fa-lock mr-1"></i> {{ $lockDok['message'] }}</div>
            @endif
            @if($t->status === 'PENDING')
                <div class="d-flex flex-wrap" style="gap:8px;">
                    @if($canApprove && (int) $t->created_by !== (int) auth()->id())
                        <form method="POST" action="{{ route('cash-transfers.approve', $t->id) }}" class="form-inline" style="gap:6px;" onsubmit="return confirm('Setujui transfer ini? Saldo kedua rekening akan berubah.');">@csrf
                            @include('partials.period-lock-field', ['class' => 'mb-0 mr-2'])
                            <button class="btn btn-success btn-sm" @if($lockDok['locked'] && ! $lockDok['can_override']) disabled title="{{ $lockDok['message'] }}" @endif>Setujui</button></form>
                        <form method="POST" action="{{ route('cash-transfers.reject', $t->id) }}" class="form-inline" style="gap:6px;">@csrf
                            <input type="text" name="reason" class="form-control form-control-sm" placeholder="Alasan penolakan" required maxlength="255"><button class="btn btn-outline-danger btn-sm">Tolak</button></form>
                    @elseif($canApprove)
                        <span class="text-muted" style="font-size:12px;">Persetujuan harus oleh pengguna lain.</span>
                    @endif
                    @if($canCreate)
                        <form method="POST" action="{{ route('cash-transfers.cancel', $t->id) }}" onsubmit="return confirm('Batalkan transfer ini?');">@csrf<button class="btn btn-outline-secondary btn-sm">Batalkan</button></form>
                    @endif
                </div>
            @endif
            <div class="mt-3"><a href="{{ route('cash-transfers.index') }}">← Kembali</a></div>
        </x-tw.card>
    </div>
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush
