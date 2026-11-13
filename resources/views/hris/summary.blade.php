@extends('layouts.app')

@section('title', 'Summary HRIS')

@section('content')
@php
    // Batang horizontal sederhana (tanpa pustaka grafik): nilai terhadap maksimum.
    $bar = fn ($n, $max) => $max > 0 ? round($n / $max * 100) : 0;
@endphp
<div class="container-fluid">
    <div class="mb-3">
        <h4 class="mb-1">Summary HRIS</h4>
        <div class="text-muted small">Analisa rinci: tren surat, pemenuhan kebutuhan pegawai, dan efektivitas rekrutmen.</div>
    </div>

    @if (! array_filter($access))
        <div class="alert alert-secondary">Anda belum memiliki akses ke modul HRIS mana pun.</div>
    @endif

    @if ($access['letter'])
        <div class="card mb-3">
            <div class="card-header"><b>Letter Management</b></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3"><div class="h4 mb-0">{{ $letterAvgApprovalHours !== null ? $letterAvgApprovalHours . ' jam' : '-' }}</div><div class="small text-muted">Rata-rata lama approval (submit → approve)</div></div>
                    <div class="col-md-3 mb-3"><div class="h4 mb-0">{{ $letterRejectRate }}%</div><div class="small text-muted">Surat berstatus Rejected</div></div>
                </div>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="small text-muted mb-2">Surat dibuat per bulan (6 bulan terakhir)</div>
                        @php $mx = $letterMonths->max('n'); @endphp
                        @foreach ($letterMonths as $m)
                            <div class="d-flex align-items-center mb-1 small" style="gap:8px"><span style="width:70px">{{ $m['label'] }}</span>
                                <div class="progress flex-grow-1" style="height:10px"><div class="progress-bar" style="width:{{ $bar($m['n'], $mx) }}%"></div></div><span style="width:28px" class="text-right">{{ $m['n'] }}</span></div>
                        @endforeach
                    </div>
                    <div class="col-lg-6">
                        <div class="small text-muted mb-2">Surat per kategori</div>
                        @php $mx = $letterByCategory->max('n'); @endphp
                        @forelse ($letterByCategory as $c)
                            <div class="d-flex align-items-center mb-1 small" style="gap:8px"><span style="width:70px" title="{{ $c->name }}">{{ $c->code }}</span>
                                <div class="progress flex-grow-1" style="height:10px"><div class="progress-bar bg-info" style="width:{{ $bar($c->n, $mx) }}%"></div></div><span style="width:28px" class="text-right">{{ $c->n }}</span></div>
                        @empty<div class="text-muted small">Belum ada surat</div>@endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($access['manpower'])
        <div class="card mb-3">
            <div class="card-header"><b>Manpower Planning</b></div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3"><div class="h4 mb-0">{{ $mpAvgDaysToFill !== null ? $mpAvgDaysToFill . ' hari' : '-' }}</div><div class="small text-muted">Rata-rata waktu pemenuhan (disetujui → terisi)</div></div>
                    <div class="col-md-3"><div class="h4 mb-0">{{ $mpNewVsReplace['NEW'] ?? 0 }} / {{ $mpNewVsReplace['REPLACE'] ?? 0 }}</div><div class="small text-muted">Kebutuhan NEW / REPLACE</div></div>
                </div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>Posisi</th><th class="text-right">Kebutuhan</th><th class="text-right">Terisi</th><th style="width:35%">Pemenuhan</th></tr></thead><tbody>
                    @forelse ($mpByPosition as $pos => $v)
                        <tr><td>{{ $pos }}</td><td class="text-right">{{ $v['total'] }}</td><td class="text-right">{{ $v['done'] }}</td>
                            <td><div class="progress" style="height:8px"><div class="progress-bar bg-success" style="width:{{ $bar($v['done'], $v['total']) }}%"></div></div></td></tr>
                    @empty<tr><td colspan="4" class="text-center text-muted">Belum ada kebutuhan yang disetujui</td></tr>@endforelse
                </tbody></table></div>
            </div>
        </div>
    @endif

    @if ($access['recruitment'])
        <div class="card mb-3">
            <div class="card-header"><b>Efektivitas Rekrutmen</b></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2"><div class="h4 mb-0">{{ $recHireRate !== null ? $recHireRate . '%' : '-' }}</div><div class="small text-muted">Tingkat hire ({{ $recDecided }} proses selesai)</div></div>
                    @foreach (['ON_PROCESS' => 'Berjalan', 'APPROVED' => 'Hired', 'REJECTED' => 'Ditolak', 'CANCELED' => 'Batal'] as $k => $l)
                        <div class="col-6 col-md-2 mb-2"><div class="h4 mb-0">{{ $recByStatus[$k] ?? 0 }}</div><div class="small text-muted">{{ $l }}</div></div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if ($access['candidate'])
        <div class="card mb-3">
            <div class="card-header"><b>Efektivitas Sumber Kandidat</b></div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>Sumber</th><th class="text-right">Kandidat</th><th class="text-right">Hired</th><th class="text-right">Konversi</th></tr></thead><tbody>
                @forelse ($bySource as $s)
                    <tr><td>{{ $s->source ?: '(tidak diisi)' }}</td><td class="text-right">{{ $s->n }}</td><td class="text-right">{{ (int) $s->hired }}</td>
                        <td class="text-right">{{ $s->n ? round($s->hired / $s->n * 100, 1) : 0 }}%</td></tr>
                @empty<tr><td colspan="4" class="text-center text-muted">Belum ada kandidat</td></tr>@endforelse
            </tbody></table></div>
        </div>
    @endif
</div>
@endsection
