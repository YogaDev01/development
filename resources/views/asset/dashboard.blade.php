@extends('layouts.app')

@section('title', 'Asset Dashboard')

@php
    $rp = fn ($v) => 'Rp ' . number_format((int) $v, 0, ',', '.');
    $bars = function ($rows) {
        $max = max(1, (int) collect($rows)->max());
        return collect($rows)->map(fn ($v, $k) => ['label' => $k, 'value' => $v, 'pct' => round($v / $max * 100)]);
    };
@endphp

@section('content')
<div class="content"><div class="container-fluid">
    @include('asset._header', [
        'title'   => 'Asset Dashboard',
        'desc'    => 'Ringkasan aset tetap: jumlah, nilai, kondisi, dan pekerjaan yang perlu ditindaklanjuti.',
        'actions' => '<a href="' . route('asset.create') . '" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>Registrasi Aset</a>'
            . '<a href="' . route('asset.index') . '" class="btn btn-secondary btn-sm">All Assets</a>',
    ])

    <x-stat-cards :cards="[
        ['Total Aset Aktif', number_format($kpi['total'], 0, ',', '.'), 'primary', 'fas fa-boxes'],
        ['Harga Perolehan', $rp($kpi['cost']), 'info', 'fas fa-coins'],
        ['Nilai Buku', $rp($kpi['book']), 'success', 'fas fa-chart-line', 'Penyusutan: ' . $rp($kpi['cost'] - $kpi['book'])],
        ['Diserahterimakan', $kpi['assigned'], 'purple', 'fas fa-user-check'],
        ['Maintenance', $kpi['maintenance'], 'warning', 'fas fa-tools'],
        ['Disposed', $kpi['disposed'], 'secondary', 'fas fa-trash-alt'],
    ]" />

    @if($pendingApprovals || $overdueCheckouts)
        <div class="alert alert-warning py-2 mt-3" style="font-size:13px;">
            @if($pendingApprovals)<i class="fas fa-hourglass-half mr-1"></i>{{ $pendingApprovals }} pengajuan (Transfer / Disposal / PR) menunggu persetujuan. @endif
            @if($overdueCheckouts)<i class="fas fa-exclamation-triangle ml-2 mr-1"></i>{{ $overdueCheckouts }} check-out melewati tanggal kembali. @endif
        </div>
    @endif

    <div class="row mt-3">
        <div class="col-lg-4">
            <div class="ast-panel"><div class="ast-panel-h">Per Status</div><div class="ast-panel-b ast-bars">
                @forelse($bars($byStatus) as $b)
                    <div><span><x-status :status="$b['label']" context="asset" /></span><div class="ast-bar"><span style="width:{{ $b['pct'] }}%"></span></div><b class="text-right">{{ $b['value'] }}</b></div>
                @empty <x-empty title="Belum ada aset" :compact="true" /> @endforelse
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="ast-panel"><div class="ast-panel-h">Per Kategori</div><div class="ast-panel-b ast-bars">
                @forelse($bars($byCategory) as $b)
                    <div><span class="text-truncate">{{ $b['label'] }}</span><div class="ast-bar"><span style="width:{{ $b['pct'] }}%"></span></div><b class="text-right">{{ $b['value'] }}</b></div>
                @empty <x-empty title="Belum ada aset" :compact="true" /> @endforelse
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="ast-panel"><div class="ast-panel-h">Kondisi</div><div class="ast-panel-b ast-bars">
                @foreach($bars(collect(\App\Models\Asset::CONDITIONS)->map(fn ($l, $k) => $byCondition[$k] ?? 0)) as $b)
                    <div><span>{{ \App\Models\Asset::CONDITIONS[$b['label']] }}</span><div class="ast-bar"><span style="width:{{ $b['pct'] }}%"></span></div><b class="text-right">{{ $b['value'] }}</b></div>
                @endforeach
            </div></div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="ast-panel"><div class="ast-panel-h">Lokasi Terbanyak</div><div class="ast-panel-b ast-bars">
                @forelse($bars($byLocation) as $b)
                    <div><span class="text-truncate">{{ $b['label'] }}</span><div class="ast-bar"><span style="width:{{ $b['pct'] }}%"></span></div><b class="text-right">{{ $b['value'] }}</b></div>
                @empty <x-empty title="Belum ada aset" :compact="true" /> @endforelse
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="ast-panel"><div class="ast-panel-h">Maintenance Berjalan<span class="ast-r"><a href="{{ route('asset.maintenance.index') }}" class="small">Semua</a></span></div>
                <table class="ast-table"><tbody>
                @forelse($openMaintenance as $m)
                    <tr><td><span class="ast-code">{{ $m->number }}</span><div class="ast-sub">{{ $m->asset?->code }} — {{ $m->asset?->name }}</div></td><td class="text-right"><x-status :status="$m->status" context="asset" /></td></tr>
                @empty <tr><td><x-empty title="Tidak ada maintenance berjalan" :compact="true" /></td></tr> @endforelse
                </tbody></table>
            </div>
            <div class="ast-panel"><div class="ast-panel-h">Jadwal Jatuh Tempo</div>
                <table class="ast-table"><tbody>
                @forelse($dueSchedules as $s)
                    <tr><td>{{ $s->title }}<div class="ast-sub">{{ $s->asset?->code }} · {{ $s->next_due_date->format('d M Y') }}</div></td><td class="text-right"><x-status :status="$s->dueState()" context="asset" /></td></tr>
                @empty <tr><td><x-empty title="Tidak ada jadwal jatuh tempo" :compact="true" /></td></tr> @endforelse
                </tbody></table>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="ast-panel"><div class="ast-panel-h">Aktivitas Terbaru</div>
                <table class="ast-table"><tbody>
                @forelse($activities as $l)
                    <tr><td><a href="{{ route('asset.show', $l->asset_id) }}" class="ast-code">{{ $l->asset_code }}</a> {{ $l->description }}
                        <div class="ast-sub">{{ $l->user?->name ?? 'Sistem' }} · {{ $l->created_at?->format('d M Y H:i') }}</div></td></tr>
                @empty <tr><td><x-empty title="Belum ada aktivitas" :compact="true" /></td></tr> @endforelse
                </tbody></table>
            </div>
        </div>
    </div>
</div></div>
@endsection
