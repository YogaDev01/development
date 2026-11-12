@extends('layouts.app')

@section('title', 'Laporan Aset')

@section('content')
<div class="content"><div class="container-fluid">
    @include('asset._header', [
        'title'   => 'Laporan Aset',
        'desc'    => 'Register, penyusutan, pergerakan, maintenance, dan kondisi aset.',
        'actions' => '<a href="' . route('asset.reports.export', request()->query() + ['type' => $type]) . '" class="btn btn-secondary btn-sm"><i class="fas fa-file-csv mr-1"></i>Export CSV</a>',
    ])

    <ul class="nav ast-tabs">
        @foreach(\App\Http\Controllers\AssetController::REPORTS as $k => $l)
            <li class="nav-item"><a class="nav-link {{ $type === $k ? 'active' : '' }}" href="{{ route('asset.reports', ['type' => $k]) }}">{{ $l }}</a></li>
        @endforeach
    </ul>

    <div class="ast-panel">
        <form method="GET" class="ast-toolbar">
            <input type="hidden" name="type" value="{{ $type }}">
            @if(in_array($type, ['depreciation', 'movement', 'maintenance'], true))
                <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm" title="Dari">
                <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm" title="Sampai">
            @elseif($type === 'register')
                <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm" title="Nilai buku per tanggal">
            @endif
            <select name="category" class="form-control form-control-sm"><option value="">Semua kategori</option>
                @foreach($categories as $c)<option value="{{ $c->id }}" @selected(($filters['category'] ?? '') == $c->id)>{{ $c->name }}</option>@endforeach</select>
            <select name="location" class="form-control form-control-sm"><option value="">Semua lokasi</option>
                @foreach($locations as $l)<option value="{{ $l->id }}" @selected(($filters['location'] ?? '') == $l->id)>{{ $l->name }}</option>@endforeach</select>
            <button class="btn btn-primary btn-sm">Tampilkan</button>
        </form>

        @if($type === 'depreciation')
            <div class="alert alert-light border m-2 py-1 small">Penyusutan bersifat informasional (bulanan, sesuai metode per aset) dan tidak diposting ke jurnal maupun Closing.</div>
        @endif

        <div class="table-responsive"><table class="ast-table">
            <thead><tr>@foreach($report['headers'] as $i => $h)<th class="{{ in_array($i, $report['money'], true) || isset($report['totals'][$i]) ? 'num' : '' }}">{{ $h }}</th>@endforeach</tr></thead>
            <tbody>
            @forelse($report['rows'] as $r)
                <tr>@foreach($r as $i => $v)
                    <td class="{{ in_array($i, $report['money'], true) || isset($report['totals'][$i]) ? 'num' : '' }}">{{ in_array($i, $report['money'], true) ? number_format((int) $v, 0, ',', '.') : $v }}</td>
                @endforeach</tr>
            @empty
                <tr><td colspan="{{ count($report['headers']) }}"><x-empty title="Tidak ada data" message="Tidak ada data untuk filter dan periode ini." /></td></tr>
            @endforelse
            </tbody>
            @if($report['totals'] && $report['rows']->isNotEmpty())
                <tfoot><tr>@foreach($report['headers'] as $i => $h)
                    <td class="num font-weight-bold">{{ $i === 0 ? 'Total' : (isset($report['totals'][$i]) ? number_format((int) $report['totals'][$i], 0, ',', '.') : '') }}</td>
                @endforeach</tr></tfoot>
            @endif
        </table></div>
    </div>
</div></div>
@endsection
