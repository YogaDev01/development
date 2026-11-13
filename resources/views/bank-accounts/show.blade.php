@extends('layouts.app')

@section('title', $fund->name)

@php
    $rp = fn ($n) => ($n < 0 ? '-' : '') . 'Rp ' . number_format(abs((int) $n), 0, ',', '.');
    $uid = auth()->id();
    $canActOnOpening = $canWrite && $opening && $opening->status === 'PENDING' && (int) $opening->created_by !== (int) $uid;
@endphp

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        @if(session('success'))<div class="alert alert-success rounded-0 shadow-sm mb-0">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ session('error') }}</div>@endif
        @if($errors->any())
            <div class="alert alert-danger rounded-0 shadow-sm mb-0"><ul class="mb-0 pl-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="tw:sr-only">
            <h1>{{ $fund->name }}</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Cash &amp; Bank</li>
                <li class="breadcrumb-item"><a href="{{ route('bank-accounts.index') }}">Bank Accounts</a></li>
                <li class="breadcrumb-item active">{{ $fund->name }}</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="{{ $fund->type_label }} · {{ $fund->code ?? '-' }}" title="{{ $fund->name }}"
            description="{{ $fund->bank->name ?? 'Tanpa bank' }} · {{ $fund->masked_account }} · {{ $fund->currency ?? 'IDR' }}">
            <x-slot:actions>
                @if($canWrite)
                    <x-tw.button variant="secondary" href="{{ route('bank-accounts.edit', $fund->id) }}"><i class="fas fa-pen" aria-hidden="true"></i> Ubah</x-tw.button>
                    <form method="POST" action="{{ route('bank-accounts.toggle', $fund->id) }}" class="d-inline" onsubmit="return confirm('{{ $account['active'] ? 'Nonaktifkan' : 'Aktifkan' }} rekening ini?');">
                        @csrf
                        <x-tw.button type="submit" variant="secondary">{{ $account['active'] ? 'Nonaktifkan' : 'Aktifkan' }}</x-tw.button>
                    </form>
                @endif
            </x-slot:actions>
        </x-tw.page-header>

        <x-stat-cards :cards="[
            ['Saldo Saat Ini', $rp($account['balance']), $account['balance'] < 0 ? 'danger' : 'primary', 'fas fa-wallet'],
            ['Saldo Awal', $account['has_opening'] ? $rp($account['opening']) : 'Belum disetujui', 'secondary', 'fas fa-flag'],
            ['Total Masuk', $rp($account['in']), 'success', 'fas fa-arrow-down'],
            ['Total Keluar', $rp($account['out']), 'danger', 'fas fa-arrow-up'],
        ]" />

        <div class="row">
            {{-- INFORMASI --}}
            <div class="col-lg-5 mb-3">
                <x-tw.card padding="sm">
                    <x-slot:header>Informasi Rekening</x-slot:header>
                    <table class="table table-sm mb-0" style="font-size:13px;">
                        <tr><th class="text-muted font-weight-normal">Pemegang</th><td>{{ $fund->account_holder ?: '-' }}</td></tr>
                        <tr><th class="text-muted font-weight-normal">Cabang</th><td>{{ $fund->branch ?: '-' }}</td></tr>
                        <tr><th class="text-muted font-weight-normal">Status</th><td>{{ $account['active'] ? 'Aktif' : 'Nonaktif' }}</td></tr>
                        <tr><th class="text-muted font-weight-normal">Keterangan</th><td>{{ $fund->description ?: '-' }}</td></tr>
                    </table>
                </x-tw.card>
            </div>

            {{-- SALDO AWAL --}}
            <div class="col-lg-7 mb-3">
                <x-tw.card padding="sm">
                    <x-slot:header>Saldo Awal</x-slot:header>
                    @if($opening)
                        <div style="font-size:13px;">
                            <div><strong>{{ $rp($opening->amount) }}</strong> per {{ $opening->effective_date->format('d M Y') }}
                                @if($opening->status === 'APPROVED')<span class="badge badge-success">Approved · terkunci</span>
                                @elseif($opening->status === 'PENDING')<span class="badge badge-warning">Menunggu verifikasi</span>
                                @else<span class="badge badge-danger">Ditolak</span>@endif
                            </div>
                            <div class="text-muted">Dibuat oleh {{ $opening->creator->username ?? '-' }}
                                @if($opening->verifier) · diverifikasi {{ $opening->verifier->username }} ({{ $opening->verified_at?->format('d M Y H:i') }})@endif</div>
                            @if($opening->reject_reason)<div class="text-danger">Alasan penolakan: {{ $opening->reject_reason }}</div>@endif
                            @if($opening->notes)<div>Catatan: {{ $opening->notes }}</div>@endif
                            @if($opening->supporting_document)
                                <div><i class="fas fa-paperclip"></i> <a href="{{ route('secure-file', $opening->supporting_document) }}" target="_blank">Dokumen pendukung</a></div>
                            @endif
                        </div>
                    @else
                        <div class="text-muted" style="font-size:13px;">Belum ada saldo awal. Tanpa saldo awal terverifikasi, saldo dihitung dari 0.</div>
                    @endif

                    @if($canActOnOpening)
                        <div class="d-flex mt-2" style="gap:8px;">
                            <form method="POST" action="{{ route('bank-accounts.opening.verify', $opening->id) }}" onsubmit="return confirm('Setujui dan kunci saldo awal ini?');">
                                @csrf <button class="btn btn-success btn-sm">Setujui</button>
                            </form>
                            <form method="POST" action="{{ route('bank-accounts.opening.reject', $opening->id) }}" class="form-inline" style="gap:6px;">
                                @csrf
                                <input type="text" name="reason" class="form-control form-control-sm" placeholder="Alasan penolakan" required maxlength="255">
                                <button class="btn btn-outline-danger btn-sm">Tolak</button>
                            </form>
                        </div>
                    @elseif($canWrite && $opening && $opening->status === 'PENDING')
                        <div class="text-muted mt-2" style="font-size:12px;">Verifikasi harus dilakukan pengguna lain (bukan pembuat).</div>
                    @endif

                    @if($canWrite && (! $opening || $opening->status !== 'APPROVED'))
                        <hr>
                        <form method="POST" action="{{ route('bank-accounts.opening.store', $fund->id) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label style="font-size:12px;">Saldo Awal (Rp)</label>
                                    <input type="text" name="amount" class="form-control form-control-sm" value="{{ old('amount') }}" required placeholder="mis. 10.000.000">
                                </div>
                                <div class="form-group col-md-4">
                                    <label style="font-size:12px;">Tanggal Efektif</label>
                                    <input type="date" name="effective_date" class="form-control form-control-sm" value="{{ old('effective_date') }}" required>
                                </div>
                                <div class="form-group col-md-4">
                                    <label style="font-size:12px;">Dokumen (PDF/JPG/PNG)</label>
                                    <input type="file" name="document" class="form-control-file" accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                                <div class="form-group col-12">
                                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="Catatan (opsional)" maxlength="1000" value="{{ old('notes') }}">
                                </div>
                            </div>
                            <div class="text-muted mb-2" style="font-size:11px;">Transaksi sebelum tanggal efektif tidak dihitung ke saldo. Setelah disetujui, saldo awal terkunci.</div>
                            <button class="btn btn-primary btn-sm">{{ $opening ? 'Ajukan Ulang' : 'Ajukan Saldo Awal' }}</button>
                        </form>
                    @elseif($opening && $opening->status === 'APPROVED')
                        <div class="text-muted mt-2" style="font-size:12px;">Koreksi saldo awal hanya melalui Cash Adjustment (belum tersedia; fase berikutnya).</div>
                    @endif
                </x-tw.card>
            </div>
        </div>

        {{-- TREN BULANAN --}}
        <x-tw.card padding="sm">
            <x-slot:header>Tren Saldo Bulanan</x-slot:header>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0" style="font-size:13px;">
                    <thead class="bg-light"><tr><th>Bulan</th><th class="text-right">Masuk</th><th class="text-right">Keluar</th><th class="text-right">Saldo Penutup</th></tr></thead>
                    <tbody>
                    @forelse($trend as $t)
                        <tr><td>{{ $t['period'] }}</td><td class="text-right">{{ $rp($t['in']) }}</td><td class="text-right">{{ $rp($t['out']) }}</td><td class="text-right font-weight-bold">{{ $rp($t['closing']) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">Belum ada transaksi.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-tw.card>

        {{-- LEDGER REKENING --}}
        <x-tw.card padding="sm">
            <x-slot:header>Riwayat Transaksi (Ledger)</x-slot:header>
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 ts-idx-tbl">
                    <thead class="bg-light"><tr><th>Tanggal</th><th>Referensi</th><th>Deskripsi</th><th>Sumber</th><th class="text-right">Masuk</th><th class="text-right">Keluar</th></tr></thead>
                    <tbody>
                    @forelse($ledger as $r)
                        <tr>
                            <td class="text-nowrap">{{ $r['date']->format('d M Y') }}</td>
                            <td>@if($r['url'])<a href="{{ $r['url'] }}">{{ $r['reference_number'] }}</a>@else{{ $r['reference_number'] }}@endif</td>
                            <td>{{ $r['description'] }}</td>
                            <td>{{ $r['category'] }}</td>
                            <td class="text-right">{{ $r['direction'] === 'IN' ? $rp($r['amount']) : '' }}</td>
                            <td class="text-right">{{ $r['direction'] === 'OUT' ? $rp($r['amount']) : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty title="Belum ada transaksi" message="Pembayaran Treasury, penerimaan, dan entri kas yang terhubung ke rekening ini akan tampil di sini." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-tw.card>

        {{-- RIWAYAT REKONSILIASI --}}
        <x-tw.card padding="sm">
            <x-slot:header>Riwayat Rekonsiliasi</x-slot:header>
            <table class="table table-sm table-bordered mb-0" style="font-size:13px;">
                <thead class="bg-light"><tr><th>No.</th><th>Periode</th><th class="text-right">Saldo Rekening Koran</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($recons as $r)
                    <tr>
                        <td><a href="{{ route('cash-reconciliation.show', $r->id) }}">{{ $r->number }}</a></td>
                        <td>{{ $r->period_start->format('d M Y') }} – {{ $r->period_end->format('d M Y') }}</td>
                        <td class="text-right">{{ $rp($r->statement_ending_balance) }}</td>
                        <td>{{ ['DRAFT' => 'Draft', 'SUBMITTED' => 'Menunggu approval', 'APPROVED' => 'Disetujui · terkunci'][$r->status] ?? $r->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">Belum pernah direkonsiliasi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-tw.card>
    </div>
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush
