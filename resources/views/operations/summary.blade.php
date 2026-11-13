@extends('layouts.app')

@section('title', 'Summary Operations')

@section('content')
@php $bar = fn ($n, $max) => $max > 0 ? round($n / $max * 100) : 0; @endphp
<div class="container-fluid">
    <div class="mb-3">
        <h4 class="mb-1">Summary Operations</h4>
        <div class="text-muted small">Analisa rinci Trouble Ticket: tren, kepatuhan SLA, penyebab, lokasi, dan beban kerja.</div>
    </div>


    @if ($slaReady)
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between" style="gap:8px">
            <b>SLA Site per Bulan · Tahun {{ $slaYear }}</b>
            <form method="GET" class="form-inline" style="gap:6px">
                <select name="sla_year" class="form-control form-control-sm" onchange="this.form.submit()">
                    @for ($y = now()->year + 1; $y >= 2024; $y--)
                        <option value="{{ $y }}" {{ $y == $slaYear ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
                <select name="sla_customer" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="">Semua customer</option>
                    @foreach ($slaCustomers as $c)
                        <option value="{{ $c->id }}" @selected(($slaFilters['customer_id'] ?? '') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
                @if ($canInputSla)
                    <a href="{{ route('site-sla.index', ['year' => $slaYear]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-edit mr-1"></i> Input SLA</a>
                @endif
            </form>
        </div>
        <div class="card-body p-2">
            @if ($slaSites->isEmpty())
                <x-empty title="Belum ada SLA {{ $slaYear }}" message="SLA diinput per customer per bulan di menu SLA Site." compact />
            @else
                @include('site-sla._table', ['sites' => $slaSites, 'matrix' => $slaMatrix, 'year' => $slaYear, 'tableId' => 'tblSlaSummary'])
            @endif
        </div>
    </div>
    @endif

    @if (! $canTickets)
        <div class="alert alert-secondary">Anda belum memiliki akses ke modul Trouble Ticket.</div>
    @else
        <div class="row mb-3">
            <div class="col-6 col-md-3 mb-2"><div class="card h-100"><div class="card-body py-3"><div class="h4 mb-0">{{ $avgResolutionHours !== null ? $avgResolutionHours . ' jam' : '-' }}</div><div class="small text-muted">Rata-rata resolusi (90 hari, {{ $closed90 }} tiket closed)</div></div></div></div>
            <div class="col-6 col-md-3 mb-2"><div class="card h-100"><div class="card-body py-3"><div class="h4 mb-0">{{ $slaResolutionRate !== null ? $slaResolutionRate . '%' : '-' }}</div><div class="small text-muted">Resolusi sesuai SLA</div></div></div></div>
            <div class="col-6 col-md-3 mb-2"><div class="card h-100"><div class="card-body py-3"><div class="h4 mb-0">{{ $slaResponseRate !== null ? $slaResponseRate . '%' : '-' }}</div><div class="small text-muted">Respons sesuai SLA</div></div></div></div>
        </div>

        <div class="row">
            <div class="col-lg-6 mb-3"><div class="card h-100"><div class="card-header"><b>Tiket dibuat per bulan</b></div><div class="card-body">
                @php $mx = $months->max('n'); @endphp
                @foreach ($months as $m)
                    <div class="d-flex align-items-center mb-1 small" style="gap:8px"><span style="width:70px">{{ $m['label'] }}</span>
                        <div class="progress flex-grow-1" style="height:10px"><div class="progress-bar" style="width:{{ $bar($m['n'], $mx) }}%"></div></div><span style="width:30px" class="text-right">{{ $m['n'] }}</span></div>
                @endforeach
            </div></div></div>

            <div class="col-lg-6 mb-3"><div class="card h-100"><div class="card-header"><b>Penyebab / masalah teratas</b></div><div class="card-body">
                @php $mx = $topProblems->max('n'); @endphp
                @forelse ($topProblems as $p)
                    <div class="d-flex align-items-center mb-1 small" style="gap:8px"><span style="width:150px" class="text-truncate" title="{{ $p->name }}">{{ $p->name }}</span>
                        <div class="progress flex-grow-1" style="height:10px"><div class="progress-bar bg-info" style="width:{{ $bar($p->n, $mx) }}%"></div></div><span style="width:30px" class="text-right">{{ $p->n }}</span></div>
                @empty<div class="text-muted small">Belum ada tiket</div>@endforelse
            </div></div></div>

            <div class="col-lg-6 mb-3"><div class="card h-100"><div class="card-header"><b>Site dengan tiket terbanyak</b></div><div class="card-body">
                @php $mx = $topSites->max('n'); @endphp
                @forelse ($topSites as $s)
                    <div class="d-flex align-items-center mb-1 small" style="gap:8px"><span style="width:150px" class="text-truncate" title="{{ $s->name }}">{{ $s->name }}</span>
                        <div class="progress flex-grow-1" style="height:10px"><div class="progress-bar bg-warning" style="width:{{ $bar($s->n, $mx) }}%"></div></div><span style="width:30px" class="text-right">{{ $s->n }}</span></div>
                @empty<div class="text-muted small">Belum ada tiket</div>@endforelse
            </div></div></div>

            <div class="col-lg-6 mb-3"><div class="card h-100"><div class="card-header"><b>Beban kerja teknisi (tiket terbuka)</b></div><div class="card-body">
                @php $mx = $workload->max('n'); @endphp
                @forelse ($workload as $w)
                    <div class="d-flex align-items-center mb-1 small" style="gap:8px"><span style="width:150px" class="text-truncate" title="{{ $w->name }}">{{ $w->name }}</span>
                        <div class="progress flex-grow-1" style="height:10px"><div class="progress-bar bg-success" style="width:{{ $bar($w->n, $mx) }}%"></div></div><span style="width:30px" class="text-right">{{ $w->n }}</span></div>
                @empty<div class="text-muted small">Tidak ada tiket terbuka yang ditugaskan</div>@endforelse
            </div></div></div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
$(function () {
    if (!$('#tblSlaSummary').length || !$.fn.DataTable) return;
    $('#tblSlaSummary').DataTable({ pageLength: 25, order: [[0, 'asc']], scrollX: false,
        language: { search: 'Cari:', lengthMenu: '_MENU_ baris', info: '_START_–_END_ dari _TOTAL_ site', paginate: { previous: '‹', next: '›' } } });
    if (window.TesysColumnFilter) TesysColumnFilter.init('#tblSlaSummary', [1, 4, 5, 6, 7]);
});
</script>
@endpush
