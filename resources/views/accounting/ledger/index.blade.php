@extends('layouts.app')

@section('title', 'General Ledger')

@php use App\Support\AccountingMoney as M; @endphp

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        <div class="tw:sr-only">
            <h1>General Ledger</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Accounting</li>
                <li class="breadcrumb-item active">General Ledger</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Accounting" title="General Ledger"
            description="Buku besar per akun dari jurnal yang sudah diposting. Draft dan jurnal yang menunggu posting tidak ikut dihitung." />

        <x-tw.card padding="sm">
            <form method="GET" class="form-row align-items-end">
                <div class="form-group col-md-4 mb-2"><label class="small mb-1">Akun</label>
                    <select name="account_id" class="form-control form-control-sm" required>
                        <option value="">— pilih akun —</option>
                        @foreach($accounts as $a)<option value="{{ $a->id }}" @selected(request('account_id') == $a->id)>{{ $a->code }} — {{ $a->name }}</option>@endforeach
                    </select></div>
                <div class="form-group col-md-2 mb-2"><label class="small mb-1">Periode</label>
                    <select name="period_id" class="form-control form-control-sm">
                        <option value="">Rentang tanggal</option>
                        @foreach($periods as $p)<option value="{{ $p->id }}" @selected(request('period_id') == $p->id)>{{ $p->name }}</option>@endforeach
                    </select></div>
                <div class="form-group col-md-2 mb-2"><label class="small mb-1">Dari</label><input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm"></div>
                <div class="form-group col-md-2 mb-2"><label class="small mb-1">Sampai</label><input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm"></div>
                <div class="form-group col-md-2 mb-2"><label class="small mb-1">Project</label>
                    <select name="project_id" class="form-control form-control-sm"><option value="">Semua</option>
                        @foreach($projects as $p)<option value="{{ $p->id }}" @selected(request('project_id') == $p->id)>{{ \Illuminate\Support\Str::limit($p->name, 24) }}</option>@endforeach</select></div>
                <div class="form-group col-md-2 mb-2"><label class="small mb-1">Cost Center</label>
                    <select name="cost_center_id" class="form-control form-control-sm"><option value="">Semua</option>
                        @foreach($centers as $c)<option value="{{ $c->id }}" @selected(request('cost_center_id') == $c->id)>{{ $c->code }}</option>@endforeach</select></div>
                <div class="form-group col-md-4 mb-2 d-flex" style="gap:6px;">
                    <button class="btn btn-sm btn-primary">Tampilkan</button>
                    @if($acc && $canExport)
                        <a class="btn btn-sm btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['csv' => 1]) }}"><i class="fas fa-file-csv"></i> CSV</a>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
                    @endif
                </div>
            </form>
        </x-tw.card>

        @if(! $acc)
            <x-tw.card padding="sm"><x-empty title="Pilih akun" message="Pilih akun dan rentang tanggal untuk menampilkan buku besar." /></x-tw.card>
        @else
        <x-stat-cards :cards="[
            ['Saldo Awal', M::format($data['opening']), 'secondary', 'fas fa-flag'],
            ['Total Debit', M::format($data['debit']), 'primary', 'fas fa-plus-circle'],
            ['Total Kredit', M::format($data['credit']), 'info', 'fas fa-minus-circle'],
            ['Saldo Akhir', M::format($data['closing']), 'success', 'fas fa-check-circle'],
        ]" />

        <x-tw.card padding="sm">
            <p class="mb-2"><strong>{{ $acc->code }} — {{ $acc->name }}</strong>
                <span class="text-muted">· saldo normal {{ $acc->normal_balance === 'DEBIT' ? 'Debit' : 'Kredit' }} · {{ \Carbon\Carbon::parse($from)->format('d M Y') }} s/d {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</span></p>
            @if($rowTotal > 1000)
                <div class="alert alert-warning rounded-0">Menampilkan 1.000 dari {{ number_format($rowTotal, 0, ',', '.') }} mutasi. Saldo berjalan dan total sudah mencakup seluruh mutasi; persempit rentang tanggal untuk melihat sisanya, atau unduh CSV.</div>
            @endif
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 ts-idx-tbl">
                    <thead class="bg-light"><tr><th>Tanggal</th><th>No. Jurnal</th><th>Referensi</th><th>Deskripsi</th><th class="text-right">Debit</th><th class="text-right">Kredit</th><th class="text-right">Saldo</th></tr></thead>
                    <tbody>
                        <tr class="table-active"><td colspan="6"><em>Saldo awal</em></td><td class="text-right text-nowrap"><strong>{{ M::format($data['opening']) }}</strong></td></tr>
                    @forelse($data['rows'] as $r)
                        <tr>
                            <td class="text-nowrap">{{ \Carbon\Carbon::parse($r->journal_date)->format('d M Y') }}</td>
                            <td class="text-nowrap"><a href="{{ route('accounting.journal.show', $r->journal_id) }}">{{ $r->journal_number }}</a>@if($r->status === 'REVERSED')<span class="badge badge-dark ml-1">Reversed</span>@endif</td>
                            <td>{{ $r->reference_number ?: '-' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($r->line_description ?: $r->journal_description, 70) }}</td>
                            <td class="text-right text-nowrap">{{ M::format($r->debit, true) }}</td>
                            <td class="text-right text-nowrap">{{ M::format($r->credit, true) }}</td>
                            <td class="text-right text-nowrap">{{ M::format($r->balance) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">Tidak ada mutasi pada rentang ini.</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot class="bg-light"><tr><th colspan="4" class="text-right">Total / Saldo akhir</th>
                        <th class="text-right text-nowrap">{{ M::format($data['debit']) }}</th><th class="text-right text-nowrap">{{ M::format($data['credit']) }}</th><th class="text-right text-nowrap">{{ M::format($data['closing']) }}</th></tr></tfoot>
                </table>
            </div>
        </x-tw.card>
        @endif
    </div>
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush
