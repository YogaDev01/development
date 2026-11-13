@extends('layouts.app')

@section('title', 'Dashboard Operations')

@section('content')
<div class="container-fluid">
    <div class="mb-3">
        <h4 class="mb-1">Dashboard Operations</h4>
        <div class="text-muted small">Gambaran besar Trouble Ticket yang dapat Anda lihat. Rincian & analisa ada di Summary.</div>
    </div>

    @if (! $canTickets)
        <div class="alert alert-secondary">Anda belum memiliki akses ke modul Trouble Ticket.</div>
    @else
        <div class="row mb-3">
            <div class="col-6 col-md-3 mb-2"><a href="{{ route('trouble-ticket.index') }}" class="card text-decoration-none h-100"><div class="card-body py-3">
                <div class="h3 mb-0 text-dark">{{ $openTotal }}</div><div class="small text-muted">Tiket terbuka</div></div></a></div>
            <div class="col-6 col-md-3 mb-2"><div class="card h-100 {{ $overdue ? 'border-danger' : '' }}"><div class="card-body py-3">
                <div class="h3 mb-0 {{ $overdue ? 'text-danger' : 'text-dark' }}">{{ $overdue }}</div><div class="small text-muted">Melewati SLA</div></div></div></div>
            <div class="col-6 col-md-3 mb-2"><div class="card h-100"><div class="card-body py-3">
                <div class="h3 mb-0 text-dark">{{ $closedMonth }}</div><div class="small text-muted">Closed bulan ini</div></div></div></div>
            <div class="col-6 col-md-3 mb-2"><a href="{{ route('trouble-ticket.index', ['keuangan' => 'open']) }}" class="card text-decoration-none h-100"><div class="card-body py-3">
                <div class="h3 mb-0 text-dark">{{ $financeOpen }}</div><div class="small text-muted">Keuangan belum selesai</div></div></a></div>
        </div>

        <h6 class="text-muted text-uppercase small">Per tahap</h6>
        <div class="row mb-3">
            @foreach (['OPEN', 'REMOTE', 'PLAN', 'TRIP', 'IN_PROGRESS', 'PROGRESS_DONE'] as $s)
                <div class="col-6 col-md-4 col-xl-2 mb-2"><div class="card h-100"><div class="card-body py-3">
                    <div class="h3 mb-0 text-dark">{{ $byStatus[$s] ?? 0 }}</div><div class="small text-muted">{{ str_replace('_', ' ', $s) }}</div></div></div></div>
            @endforeach
        </div>

        <h6 class="text-muted text-uppercase small">Tiket terbuka per severity</h6>
        <div class="card"><div class="card-body">
            @php $mx = max(1, $bySeverity->max() ?? 0); @endphp
            @forelse ($bySeverity as $sev => $n)
                <div class="d-flex align-items-center mb-1 small" style="gap:8px"><span style="width:110px">{{ $sev ?: 'Tidak diisi' }}</span>
                    <div class="progress flex-grow-1" style="height:10px"><div class="progress-bar" style="width:{{ round($n / $mx * 100) }}%"></div></div><span style="width:30px" class="text-right">{{ $n }}</span></div>
            @empty<div class="text-muted small">Tidak ada tiket terbuka</div>@endforelse
        </div></div>
    @endif
</div>
@endsection
