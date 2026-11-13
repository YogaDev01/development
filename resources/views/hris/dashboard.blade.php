@extends('layouts.app')

@section('title', 'Dashboard HRIS')

@section('content')
@php
    $stat = fn ($label, $value, $sub = null, $url = null) => compact('label', 'value', 'sub', 'url');
    $lbl  = \App\Models\Letter::STATUSES;
@endphp
<div class="container-fluid">
    <div class="mb-3">
        <h4 class="mb-1">Dashboard HRIS</h4>
        <div class="text-muted small">Gambaran besar surat, kebutuhan pegawai, dan rekrutmen. Rincian & analisa ada di Summary.</div>
    </div>

    @if (! array_filter($access))
        <div class="alert alert-secondary">Anda belum memiliki akses ke modul HRIS mana pun.</div>
    @endif

    @if ($access['letter'])
        <h6 class="text-muted text-uppercase small">Letter Management</h6>
        <div class="row mb-3">
            @foreach (['draft', 'submitted', 'approved', 'sent', 'complete', 'rejected'] as $s)
                <div class="col-6 col-md-4 col-xl-2 mb-2"><a href="{{ route('letter.index', ['status' => $s]) }}" class="card text-decoration-none h-100"><div class="card-body py-3">
                    <div class="h3 mb-0 text-dark">{{ $letterByStatus[$s] ?? 0 }}</div><div class="small text-muted">{{ $lbl[$s][0] }}</div></div></a></div>
            @endforeach
        </div>
    @endif

    @if ($access['manpower'])
        <h6 class="text-muted text-uppercase small">Manpower Planning</h6>
        <div class="row mb-3">
            <div class="col-6 col-md-3 mb-2"><div class="card h-100"><div class="card-body py-3"><div class="h3 mb-0">{{ $headcountFilled }} / {{ $headcountTotal }}</div><div class="small text-muted">Headcount terisi</div>
                @if ($headcountTotal)<div class="progress mt-2" style="height:6px"><div class="progress-bar" style="width:{{ round($headcountFilled / $headcountTotal * 100) }}%"></div></div>@endif</div></div></div>
            @foreach (['SUBMITTED', 'APPROVED', 'PROCESS', 'PARTIALLY DONE', 'DONE'] as $s)
                <div class="col-6 col-md-3 col-xl mb-2"><a href="{{ route('manpower.index', ['status' => $s]) }}" class="card text-decoration-none h-100"><div class="card-body py-3">
                    <div class="h3 mb-0 text-dark">{{ $mpByStatus[$s] ?? 0 }}</div><div class="small text-muted">{{ $s }}</div></div></a></div>
            @endforeach
        </div>
    @endif

    @if ($access['recruitment'] || $access['candidate'])
        <h6 class="text-muted text-uppercase small">Recruitment &amp; Kandidat</h6>
        <div class="row mb-3">
            @if ($access['recruitment'])
                @foreach (\App\Models\Recruitment::STAGES as $s)
                    <div class="col-6 col-md-4 col-xl-2 mb-2"><a href="{{ route('recruitment.index', ['stage' => $s]) }}" class="card text-decoration-none h-100"><div class="card-body py-3">
                        <div class="h3 mb-0 text-dark">{{ $pipeline[$s] ?? 0 }}</div><div class="small text-muted">{{ $s }}</div></div></a></div>
                @endforeach
            @endif
            @if ($access['candidate'])
                <div class="col-6 col-md-4 col-xl-2 mb-2"><a href="{{ route('candidates.index') }}" class="card text-decoration-none h-100"><div class="card-body py-3">
                    <div class="h3 mb-0 text-dark">{{ $candTotal - $candBusy - $candHired }}</div><div class="small text-muted">Kandidat tersedia (dari {{ $candTotal }})</div></div></a></div>
            @endif
        </div>
    @endif
</div>
@endsection
