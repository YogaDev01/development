@extends('layouts.app')

@section('title', 'Summary Projects')

@section('content')
@php
    $bar = fn ($n, $max) => $max > 0 ? round($n / $max * 100) : 0;
    $rp  = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
@endphp
<div class="container-fluid">
    <div class="mb-3">
        <h4 class="mb-1">Summary Projects</h4>
        <div class="text-muted small">Analisa rinci: tren, komposisi customer & tipe, progres, dan keterlambatan milestone.</div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-3"><div class="card h-100"><div class="card-header"><b>Proyek dibuat per bulan</b></div><div class="card-body">
            @php $mx = $months->max('n'); @endphp
            @foreach ($months as $m)
                <div class="d-flex align-items-center mb-1 small" style="gap:8px"><span style="width:70px">{{ $m['label'] }}</span>
                    <div class="progress flex-grow-1" style="height:10px"><div class="progress-bar" style="width:{{ $bar($m['n'], $mx) }}%"></div></div><span style="width:30px" class="text-right">{{ $m['n'] }}</span></div>
            @endforeach
        </div></div></div>

        <div class="col-lg-6 mb-3"><div class="card h-100"><div class="card-header"><b>Komposisi per tipe</b></div><div class="card-body">
            @php $mx = $byType->max(); @endphp
            @forelse ($byType as $type => $n)
                <div class="d-flex align-items-center mb-1 small" style="gap:8px"><span style="width:110px">{{ $type }}</span>
                    <div class="progress flex-grow-1" style="height:10px"><div class="progress-bar bg-info" style="width:{{ $bar($n, $mx) }}%"></div></div><span style="width:30px" class="text-right">{{ $n }}</span></div>
            @empty<div class="text-muted small">Belum ada proyek</div>@endforelse
        </div></div></div>
    </div>

    <div class="card mb-3"><div class="card-header"><b>Customer teratas (nilai PO)</b></div>
        <div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Customer</th><th class="text-right">Proyek</th><th class="text-right">Nilai PO</th></tr></thead><tbody>
            @forelse ($byCustomer as $name => $v)
                <tr><td>{{ $name }}</td><td class="text-right">{{ $v['n'] }}</td><td class="text-right">{{ $rp($v['value']) }}</td></tr>
            @empty<tr><td colspan="3" class="text-center text-muted">Belum ada proyek</td></tr>@endforelse
        </tbody></table></div></div>

    <div class="card mb-3"><div class="card-header"><b>Progres proyek berjalan (terendah dulu)</b></div>
        <div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Proyek</th><th>PM Internal</th><th>Status</th><th class="text-right">Terlambat</th><th style="width:30%">Progres</th></tr></thead><tbody>
            @forelse ($progressRows as $r)
                <tr><td><a href="{{ route('project-monitoring.show', $r['id']) }}">{{ $r['name'] }}</a></td><td>{{ $r['manager'] }}</td><td class="small">{{ str_replace('_', ' ', $r['status']) }}</td>
                    <td class="text-right {{ $r['overdue'] ? 'text-danger' : '' }}">{{ $r['overdue'] }}</td>
                    <td><div class="d-flex align-items-center" style="gap:8px"><div class="progress flex-grow-1" style="height:8px"><div class="progress-bar bg-success" style="width:{{ $r['progress'] }}%"></div></div><span class="small">{{ $r['progress'] }}%</span></div></td></tr>
            @empty<tr><td colspan="5" class="text-center text-muted">Tidak ada proyek berjalan</td></tr>@endforelse
        </tbody></table></div></div>

    <div class="card mb-3"><div class="card-header"><b>Milestone paling terlambat</b></div>
        <div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Proyek</th><th>Milestone</th><th>Target</th><th class="text-right">Terlambat</th></tr></thead><tbody>
            @forelse ($overdueRows as $o)
                <tr><td><a href="{{ route('project-monitoring.show', $o['id']) }}">{{ $o['project'] }}</a></td><td>{{ $o['milestone'] }}</td><td>{{ $o['due']->format('d M Y') }}</td><td class="text-right text-danger">{{ $o['days'] }} hari</td></tr>
            @empty<tr><td colspan="4" class="text-center text-muted">Tidak ada milestone terlambat</td></tr>@endforelse
        </tbody></table></div></div>
</div>
@endsection
