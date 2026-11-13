@extends('layouts.app')

@section('title', $title)

@php use App\Support\AccountingMoney as M; @endphp

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        <div class="tw:sr-only">
            <h1>{{ $title }}</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Accounting</li>
                <li class="breadcrumb-item active">{{ $title }}</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Financial Statements" title="{{ $title }}"
            description="Dihitung dari buku besar (jurnal yang sudah diposting), bukan dari Treasury atau Cash Receipt." />

        <x-tw.card padding="sm">
            <form method="GET" class="form-row align-items-end">
                <div class="form-group col-md-3 mb-2"><label class="small mb-1">Periode</label>
                    <select name="period_id" class="form-control form-control-sm"><option value="">Rentang tanggal</option>
                        @foreach($periods as $p)<option value="{{ $p->id }}" @selected(request('period_id') == $p->id)>{{ $p->name }}</option>@endforeach</select></div>
                @unless($asOf)
                <div class="form-group col-md-2 mb-2"><label class="small mb-1">Dari</label><input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm"></div>
                @endunless
                <div class="form-group col-md-2 mb-2"><label class="small mb-1">{{ $asOf ? 'Per tanggal' : 'Sampai' }}</label><input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm"></div>
                <div class="form-group col-md-5 mb-2 d-flex flex-wrap" style="gap:6px;">
                    <button class="btn btn-sm btn-primary">Tampilkan</button>
                    @if($canExport)
                        <a class="btn btn-sm btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['csv' => 1]) }}"><i class="fas fa-file-csv"></i> CSV</a>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
                    @endif
                </div>
            </form>
        </x-tw.card>

        @foreach($data['checks'] as $c)
            @if(! $c['ok'])
                <div class="alert alert-danger rounded-0 mb-0"><strong>{{ $c['label'] }}:</strong>
                    {{ $c['note'] ?? 'tidak cocok (selisih ' . M::format(abs($c['diff'])) . '). Periksa klasifikasi akun dan jurnal sebelum memakai laporan ini.' }}</div>
            @endif
        @endforeach

        <x-tw.card padding="sm">
            <p class="mb-2 text-muted">{{ $asOf ? 'Per ' . \Carbon\Carbon::parse($to)->format('d M Y') : \Carbon\Carbon::parse($from)->format('d M Y') . ' s/d ' . \Carbon\Carbon::parse($to)->format('d M Y') }}</p>
            <div class="table-responsive">
                <table class="table table-sm mb-0" style="max-width:820px">
                    <tbody>
                    @forelse($data['lines'] as $l)
                        @if($l['t'] === 'section')
                            <tr class="bg-light"><td colspan="2"><strong>{{ $l['label'] }}</strong></td></tr>
                        @else
                            <tr class="{{ $l['t'] === 'grand' ? 'table-active font-weight-bold' : ($l['t'] === 'total' ? 'font-weight-bold' : '') }}">
                                <td style="padding-left:{{ $l['t'] === 'row' ? 28 : 8 }}px">
                                    @if(! empty($l['account_id']) && Route::has('accounting.ledger.index'))
                                        <a href="{{ route('accounting.ledger.index', ['account_id' => $l['account_id'], 'from' => $asOf ? \Carbon\Carbon::parse($to)->startOfYear()->toDateString() : $from, 'to' => $to]) }}">{{ $l['label'] }}</a>
                                    @else{{ $l['label'] }}@endif
                                </td>
                                <td class="text-right text-nowrap {{ ($l['amount'] ?? 0) < 0 ? 'text-danger' : '' }}">{{ M::format((int) $l['amount']) }}</td>
                            </tr>
                        @endif
                    @empty
                        <tr><td><x-empty title="Belum ada data" message="Belum ada jurnal yang diposting atau akun kas belum ditautkan." /></td></tr>
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
