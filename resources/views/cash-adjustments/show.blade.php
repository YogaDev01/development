@extends('layouts.app')

@section('title', $a->adjustment_number)

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
            <h1>{{ $a->adjustment_number }}</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Cash &amp; Bank</li>
                <li class="breadcrumb-item"><a href="{{ route('cash-adjustments.index') }}">Cash Adjustment</a></li>
                <li class="breadcrumb-item active">{{ $a->adjustment_number }}</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Cash Adjustment" title="{{ $a->adjustment_number }}" description="{{ $a->type_label }} · {{ $a->sourceFund->name ?? '-' }}" />

        <x-tw.card>
            <table class="table table-sm mb-3" style="font-size:13px;">
                <tr><th class="text-muted font-weight-normal" style="width:200px;">Status</th><td><span class="badge {{ $badge[$a->status] ?? 'badge-secondary' }}">{{ $lbl[$a->status] ?? $a->status }}</span></td></tr>
                <tr><th class="text-muted font-weight-normal">Tanggal efektif</th><td>{{ $a->effective_date->format('d M Y') }}</td></tr>
                <tr><th class="text-muted font-weight-normal">Arah / nominal</th><td>{{ $a->direction === 'IN' ? 'Masuk' : 'Keluar' }} · {{ $rp($a->amount) }}</td></tr>
                <tr><th class="text-muted font-weight-normal">Alasan</th><td>{{ $a->reason }}</td></tr>
                <tr><th class="text-muted font-weight-normal">Dibuat oleh</th><td>{{ $a->creator->username ?? '-' }}</td></tr>
                <tr><th class="text-muted font-weight-normal">Diputuskan oleh</th><td>{{ $a->approver->username ?? '-' }} {{ $a->approved_at?->format('d M Y H:i') }}</td></tr>
                @if($a->reject_reason)<tr><th class="text-muted font-weight-normal">Alasan penolakan</th><td class="text-danger">{{ $a->reject_reason }}</td></tr>@endif
                @if($a->attachment)<tr><th class="text-muted font-weight-normal">Lampiran</th><td><a href="{{ route('secure-file', $a->attachment) }}" target="_blank"><i class="fas fa-paperclip"></i> Buka</a></td></tr>@endif
            </table>
            @php $lockDok = \App\Support\PeriodLock::forModel($a, 'Cash Adjustment'); @endphp
            @if($lockDok['locked'])
                <div class="alert alert-warning py-2" style="font-size:12px;"><i class="fas fa-lock mr-1"></i> {{ $lockDok['message'] }}</div>
            @endif
            @if($a->status === 'PENDING')
                <div class="d-flex flex-wrap" style="gap:8px;">
                    @if($canApprove && (int) $a->created_by !== (int) auth()->id())
                        <form method="POST" action="{{ route('cash-adjustments.approve', $a->id) }}" class="form-inline" style="gap:6px;" onsubmit="return confirm('Setujui adjustment ini? Saldo rekening akan berubah.');">@csrf
                            @include('partials.period-lock-field', ['class' => 'mb-0 mr-2'])
                            <button class="btn btn-success btn-sm" @if($lockDok['locked'] && ! $lockDok['can_override']) disabled title="{{ $lockDok['message'] }}" @endif>Setujui</button></form>
                        <form method="POST" action="{{ route('cash-adjustments.reject', $a->id) }}" class="form-inline" style="gap:6px;">@csrf
                            <input type="text" name="reason" class="form-control form-control-sm" placeholder="Alasan penolakan" required maxlength="255"><button class="btn btn-outline-danger btn-sm">Tolak</button></form>
                    @elseif($canApprove)
                        <span class="text-muted" style="font-size:12px;">Persetujuan harus oleh pengguna lain.</span>
                    @endif
                    @if($canCreate)
                        <form method="POST" action="{{ route('cash-adjustments.cancel', $a->id) }}" onsubmit="return confirm('Batalkan adjustment ini?');">@csrf<button class="btn btn-outline-secondary btn-sm">Batalkan</button></form>
                    @endif
                </div>
            @endif
            <div class="mt-3"><a href="{{ route('cash-adjustments.index') }}">← Kembali</a></div>
        </x-tw.card>
    </div>
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush
