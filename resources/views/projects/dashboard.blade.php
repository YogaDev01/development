@extends('layouts.app')

@section('title', 'Dashboard Projects')

@section('content')
@php $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.'); @endphp
<div class="container-fluid">
    <div class="mb-3">
        <h4 class="mb-1">Dashboard Projects</h4>
        <div class="text-muted small">Gambaran besar proyek yang dapat Anda akses. Rincian & analisa ada di Summary.</div>
    </div>

    <div class="row mb-3">
        <div class="col-6 col-md-3 mb-2"><a href="{{ route('project-monitoring.index') }}" class="card text-decoration-none h-100"><div class="card-body py-3">
            <div class="h3 mb-0 text-dark">{{ $total }}</div><div class="small text-muted">Total proyek</div></div></a></div>
        <div class="col-6 col-md-3 mb-2"><div class="card h-100"><div class="card-body py-3">
            <div class="h3 mb-0 text-dark">{{ $activeCount }}</div><div class="small text-muted">Proyek berjalan</div></div></div></div>
        <div class="col-6 col-md-3 mb-2"><div class="card h-100"><div class="card-body py-3">
            <div class="h4 mb-0 text-dark">{{ $rp($activeValue) }}</div><div class="small text-muted">Nilai PO proyek berjalan</div></div></div></div>
        <div class="col-6 col-md-3 mb-2"><div class="card h-100"><div class="card-body py-3">
            <div class="h3 mb-0 text-dark">{{ $avgProgress !== null ? $avgProgress . '%' : '-' }}</div><div class="small text-muted">Rata-rata progres milestone</div></div></div></div>
        <div class="col-6 col-md-3 mb-2"><div class="card h-100 {{ $overdue ? 'border-danger' : '' }}"><div class="card-body py-3">
            <div class="h3 mb-0 {{ $overdue ? 'text-danger' : 'text-dark' }}">{{ $overdue }}</div><div class="small text-muted">Milestone terlambat</div></div></div></div>
        <div class="col-6 col-md-3 mb-2"><div class="card h-100"><div class="card-body py-3">
            <div class="h3 mb-0 text-dark">{{ $noManager }}</div><div class="small text-muted">Proyek tanpa PM Internal</div></div></div></div>
    </div>

    <h6 class="text-muted text-uppercase small">Per status</h6>
    <div class="row">
        @foreach (\App\Models\Project::STATUSES as $s)
            @continue(empty($byStatus[$s]))
            <div class="col-6 col-md-3 col-xl-2 mb-2"><div class="card h-100"><div class="card-body py-3">
                <div class="h3 mb-0 text-dark">{{ $byStatus[$s] }}</div><div class="small text-muted">{{ str_replace('_', ' ', $s) }}</div></div></div></div>
        @endforeach
    </div>
</div>
@endsection
