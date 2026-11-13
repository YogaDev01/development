@extends('layouts.app')

@section('title', 'Cash Overview')

@php
    $rp = fn ($n) => ($n < 0 ? '-' : '') . 'Rp ' . number_format(abs((int) $n), 0, ',', '.');
    $maxMonthly = max(1, (int) $monthly->max(fn ($m) => max($m['total_in'], $m['total_out'])));
    $maxDaily   = max(1, (int) collect($daily)->max(fn ($d) => max($d['in'], $d['out'])));
    $totalPos   = max(1, (int) $accounts->where('balance', '>', 0)->sum('balance'));

    // Garis tren saldo (SVG sederhana, tanpa pustaka grafik).
    $pts   = collect($trend);
    $vals  = $pts->pluck('balance');
    $vMin  = $vals->min() ?? 0;
    $vMax  = $vals->max() ?? 0;
    $span  = max(1, $vMax - $vMin);
    $nPts  = max(1, $pts->count() - 1);
    $poly  = $pts->values()->map(fn ($p, $i) => round(($i / $nPts) * 600, 1) . ',' . round(70 - (($p['balance'] - $vMin) / $span) * 60, 1))->implode(' ');
@endphp

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        <div class="tw:sr-only">
            <h1>Cash Overview</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item">Cash &amp; Bank</li>
                <li class="breadcrumb-item active">Cash Overview</li>
            </ol>
        </div>

        <x-tw.page-header eyebrow="Cash & Bank" title="Cash Overview"
            description="Posisi kas dan bank saat ini. Hanya transaksi yang sudah dibayar/diterima/diposting yang memengaruhi saldo; Forecast tidak pernah dihitung sebagai saldo.">
        </x-tw.page-header>

        {{-- PERIODE --}}
        <x-tw.card padding="sm">
            <form method="GET" action="{{ route('cash-overview.index') }}" class="form-row align-items-end" id="formPeriod">
                <div class="form-group col-md-3 col-6 mb-2">
                    <label class="font-weight-600" style="font-size:11px;">Periode</label>
                    <select name="period" class="form-control form-control-sm" onchange="document.getElementById('customRange').style.display = this.value === 'custom' ? '' : 'none'; if (this.value !== 'custom') this.form.submit();">
                        @foreach($presets as $k => $label)
                            <option value="{{ $k }}" @selected($preset === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="customRange" class="form-row col-md-6 col-12 p-0 m-0" style="{{ $preset === 'custom' ? '' : 'display:none;' }}">
                    <div class="form-group col-5 mb-2">
                        <label class="font-weight-600" style="font-size:11px;">Dari</label>
                        <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm">
                    </div>
                    <div class="form-group col-5 mb-2">
                        <label class="font-weight-600" style="font-size:11px;">Sampai</label>
                        <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm">
                    </div>
                    <div class="form-group col-2 mb-2">
                        <button class="btn btn-sm btn-outline-secondary btn-block">Terapkan</button>
                    </div>
                </div>
                <div class="col text-right text-muted mb-2" style="font-size:12px;">
                    {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}
                </div>
            </form>
        </x-tw.card>

        {{-- KPI UTAMA — total saldo di baris sendiri; lima angka periode memakai seluruh lebar
             supaya nominal penuh tidak terpotong. --}}
        <x-tw.card>
            <div class="d-flex flex-wrap align-items-end justify-content-between" style="gap:12px;">
                <div style="min-width:0;">
                    <div class="text-muted font-weight-600" style="font-size:11px; letter-spacing:.06em; text-transform:uppercase;">Total Cash &amp; Bank Balance</div>
                    <div class="font-weight-bold {{ $kpi['total'] < 0 ? 'text-danger' : '' }}" style="font-size:clamp(1.6rem, 2.6vw, 2.2rem); line-height:1.2; white-space:nowrap;">{{ $rp($kpi['total']) }}</div>
                    <div class="text-muted" style="font-size:12px;">Saldo per {{ $to->format('d M Y') }} · {{ $accounts->count() }} rekening</div>
                </div>
                <div class="text-muted text-md-right" style="font-size:12px;">
                    Hanya transaksi yang sudah dibayar/diterima/diposting yang memengaruhi saldo.
                </div>
            </div>
        </x-tw.card>

        <x-stat-cards :cards="[
            ['Total Bank Balance', $rp($kpi['bank']), 'primary', 'fas fa-landmark'],
            ['Cash on Hand', $rp($kpi['cash_on_hand']), 'secondary', 'fas fa-coins'],
            ['Cash In (periode)', $rp($period['total_in']), 'success', 'fas fa-arrow-down'],
            ['Cash Out (periode)', $rp($period['total_out']), 'danger', 'fas fa-arrow-up'],
            ['Net Cashflow', $rp($period['net']), $period['net'] >= 0 ? 'primary' : 'warning', 'fas fa-balance-scale'],
        ]" />

        {{-- ATTENTION --}}
        @php
            $att = $attention;
            $awaiting = $accounts->filter(fn ($a) => $a['active'] && $a['fund']->account_type === 'BANK' && ! $recon['last']->has($a['fund']->id));
            $hasAtt = $att['no_opening']->isNotEmpty() || $att['pending_opening']->isNotEmpty() || $att['negative']->isNotEmpty() || $att['unassigned']['count'] > 0
                || $awaiting->isNotEmpty() || $recon['unmatched_bank_lines'] > 0;
        @endphp
        <x-tw.card padding="sm">
            <x-slot:header>Attention Required</x-slot:header>
            @php
                $attItems = collect();
                if ($att['no_opening']->isNotEmpty()) {
                    $attItems->push(['warning', '<strong>' . $att['no_opening']->count() . '</strong> rekening aktif belum punya saldo awal terverifikasi (saldonya dihitung dari 0): ' . e($att['no_opening']->map(fn ($a) => $a['fund']->name)->implode(', '))]);
                }
                if ($att['pending_opening']->isNotEmpty()) {
                    $attItems->push(['warning', '<strong>' . $att['pending_opening']->count() . '</strong> saldo awal menunggu verifikasi: ' . e($att['pending_opening']->map(fn ($a) => $a['fund']->name)->implode(', '))]);
                }
                if ($att['negative']->isNotEmpty()) {
                    $attItems->push(['danger', '<strong>' . $att['negative']->count() . '</strong> rekening bersaldo negatif: ' . e($att['negative']->map(fn ($a) => $a['fund']->name . ' (' . $rp($a['balance']) . ')')->implode(', '))]);
                }
                if ($att['unassigned']['count'] > 0) {
                    $attItems->push(['warning', '<strong>' . $att['unassigned']['count'] . '</strong> transaksi belum terhubung ke rekening (In ' . $rp($att['unassigned']['in']) . ' / Out ' . $rp($att['unassigned']['out']) . ') — tidak ikut saldo rekening mana pun.']);
                }
                if ($awaiting->isNotEmpty()) {
                    $attItems->push(['info', '<strong>' . $awaiting->count() . '</strong> rekening bank belum pernah direkonsiliasi: ' . e($awaiting->map(fn ($a) => $a['fund']->name)->implode(', '))]);
                }
                if ($recon['unmatched_bank_lines'] > 0) {
                    $attItems->push(['info', '<strong>' . $recon['unmatched_bank_lines'] . '</strong> baris rekening koran belum cocok (rekonsiliasi berjalan).']);
                }
                $attIcon = ['danger' => 'fa-exclamation-circle text-danger', 'warning' => 'fa-exclamation-triangle text-warning', 'info' => 'fa-info-circle text-info'];
            @endphp
            @if($attItems->isEmpty())
                <div class="text-muted" style="font-size:13px;"><i class="fas fa-check-circle text-success mr-1"></i> Tidak ada yang perlu perhatian.</div>
            @else
                <ul class="list-unstyled mb-0" style="font-size:13px;">
                    @foreach($attItems as [$tone, $html])
                        <li class="d-flex py-1" style="gap:8px;">
                            <i class="fas {{ $attIcon[$tone] }} mt-1" aria-hidden="true"></i>
                            <span class="{{ $tone === 'danger' ? 'text-danger' : '' }}">{!! $html !!}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-tw.card>

        {{-- REKENING --}}
        @include('partials.list-toolbar', [
            'prefix' => 'ov',
            'filters' => [['id' => 'Type', 'label' => 'Tipe'], ['id' => 'Opening', 'label' => 'Saldo Awal'], ['id' => 'Recon', 'label' => 'Rekonsiliasi']],
            'placeholder' => 'Rekening / bank / no. rekening',
        ])
        <x-tw.card padding="sm">
            <x-slot:header>Saldo per Rekening</x-slot:header>
            <div class="table-responsive">
                <table id="tblOvAccounts" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                    <thead class="bg-light">
                        <tr>
                            <th scope="col">Rekening</th><th scope="col">Bank</th><th scope="col" class="text-nowrap">No. Rekening</th><th scope="col">Tipe</th>
                            <th scope="col" class="text-right text-nowrap">Saldo Awal</th><th scope="col" class="text-right text-nowrap">Cash In</th><th scope="col" class="text-right text-nowrap">Cash Out</th>
                            <th scope="col" class="text-right text-nowrap">Saldo Saat Ini</th><th scope="col" class="text-nowrap">Status Saldo Awal</th><th scope="col">Rekonsiliasi</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($accounts as $a)
                        @php
                            $f  = $a['fund'];
                            $lr = $recon['last']->get($f->id);
                            $ip = $recon['in_progress']->get($f->id);
                            $openLabel  = $a['has_opening'] ? 'Approved' : ($a['opening_status'] === 'PENDING' ? 'Menunggu' : 'Belum diatur');
                            $reconLabel = $lr ? 'Sudah direkonsiliasi' : 'Belum pernah';
                        @endphp
                        <tr class="{{ $a['active'] ? '' : 'text-muted' }}" data-type="{{ $f->type_label }}" data-opening="{{ $openLabel }}" data-recon="{{ $reconLabel }}">
                            <td>
                                @if($canDetail)<a href="{{ route('bank-accounts.show', $f->id) }}">{{ $f->name }}</a>@else{{ $f->name }}@endif
                                @unless($a['active'])<span class="badge badge-secondary">Nonaktif</span>@endunless
                            </td>
                            <td>{{ $f->bank->name ?? '-' }}</td>
                            <td class="text-nowrap">{{ $f->masked_account }}</td>
                            <td>{{ $f->type_label }}</td>
                            <td class="text-right text-nowrap" data-order="{{ (int) $a['opening'] }}">{{ $rp($a['opening']) }}</td>
                            <td class="text-right text-nowrap" data-order="{{ (int) $a['in'] }}">{{ $rp($a['in']) }}</td>
                            <td class="text-right text-nowrap" data-order="{{ (int) $a['out'] }}">{{ $rp($a['out']) }}</td>
                            <td class="text-right text-nowrap font-weight-bold {{ $a['balance'] < 0 ? 'text-danger' : '' }}" data-order="{{ (int) $a['balance'] }}">{{ $rp($a['balance']) }}</td>
                            <td>
                                @if($a['has_opening'])<span class="badge badge-success">Approved</span>
                                @elseif($a['opening_status'] === 'PENDING')<span class="badge badge-warning">Menunggu</span>
                                @else<span class="badge badge-secondary">Belum diatur</span>@endif
                            </td>
                            <td>
                                @if($lr)<span class="badge badge-success">Terakhir: {{ $lr->period_end->format('d M Y') }}</span>
                                @else<span class="badge badge-secondary">Belum pernah</span>@endif
                                @if($ip)<a href="{{ route('cash-reconciliation.show', $ip->id) }}" class="badge badge-warning">{{ $ip->status === 'SUBMITTED' ? 'Menunggu approval' : 'Draft' }}</a>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><x-empty message="Belum ada rekening." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-tw.card>

        {{-- ANALITIK --}}
        <div class="row">
            <div class="col-lg-6 mb-3">
                <x-tw.card padding="sm">
                    <x-slot:header>Cash In vs Cash Out (6 bulan)</x-slot:header>
                    @forelse($monthly as $m)
                        <div class="mb-2" style="font-size:12px;">
                            <div class="d-flex justify-content-between"><span>{{ $m['period'] }}</span><span class="text-muted">{{ $rp($m['net']) }}</span></div>
                            <div class="progress mb-1" style="height:8px;"><div class="progress-bar bg-success" style="width:{{ round($m['total_in'] / $maxMonthly * 100) }}%"></div></div>
                            <div class="progress" style="height:8px;"><div class="progress-bar bg-danger" style="width:{{ round($m['total_out'] / $maxMonthly * 100) }}%"></div></div>
                        </div>
                    @empty
                        <div class="text-muted" style="font-size:13px;">Belum ada pergerakan kas.</div>
                    @endforelse
                    <div class="text-muted" style="font-size:11px;"><span class="text-success">■</span> Cash In &nbsp; <span class="text-danger">■</span> Cash Out</div>
                </x-tw.card>
            </div>
            <div class="col-lg-6 mb-3">
                <x-tw.card padding="sm">
                    <x-slot:header>Tren Saldo Konsolidasi</x-slot:header>
                    <svg viewBox="0 0 600 80" width="100%" height="90" preserveAspectRatio="none" role="img" aria-label="Tren saldo">
                        <polyline points="{{ $poly }}" fill="none" stroke="currentColor" stroke-width="2" class="text-danger" vector-effect="non-scaling-stroke"/>
                    </svg>
                    <div class="d-flex justify-content-between text-muted" style="font-size:11px;">
                        <span>{{ $pts->first()['date'] ?? '' }} · {{ $rp($pts->first()['balance'] ?? 0) }}</span>
                        <span>{{ $pts->last()['date'] ?? '' }} · {{ $rp($pts->last()['balance'] ?? 0) }}</span>
                    </div>
                </x-tw.card>
            </div>
            <div class="col-lg-6 mb-3">
                <x-tw.card padding="sm">
                    <x-slot:header>Pergerakan Harian</x-slot:header>
                    <div class="d-flex align-items-end" style="height:90px; gap:2px;">
                        @foreach($daily as $d)
                            <div class="flex-fill d-flex align-items-end" style="gap:1px; height:100%;" title="{{ $d['date'] }} · In {{ $rp($d['in']) }} / Out {{ $rp($d['out']) }}">
                                <div class="bg-success flex-fill" style="height:{{ round($d['in'] / $maxDaily * 100) }}%; min-height:{{ $d['in'] ? 2 : 0 }}px;"></div>
                                <div class="bg-danger flex-fill" style="height:{{ round($d['out'] / $maxDaily * 100) }}%; min-height:{{ $d['out'] ? 2 : 0 }}px;"></div>
                            </div>
                        @endforeach
                    </div>
                </x-tw.card>
            </div>
            <div class="col-lg-6 mb-3">
                <x-tw.card padding="sm">
                    <x-slot:header>Distribusi Kas per Rekening</x-slot:header>
                    @forelse($accounts->where('balance', '>', 0)->sortByDesc('balance') as $a)
                        <div class="mb-2" style="font-size:12px;">
                            <div class="d-flex justify-content-between"><span>{{ $a['fund']->name }}</span><span>{{ round($a['balance'] / $totalPos * 100) }}% · {{ $rp($a['balance']) }}</span></div>
                            <div class="progress" style="height:8px;"><div class="progress-bar" style="width:{{ round($a['balance'] / $totalPos * 100) }}%"></div></div>
                        </div>
                    @empty
                        <div class="text-muted" style="font-size:13px;">Belum ada saldo positif.</div>
                    @endforelse
                </x-tw.card>
            </div>
        </div>

        {{-- TRANSAKSI TERBARU --}}
        @include('partials.list-toolbar', [
            'prefix' => 'ovr',
            'filters' => [['id' => 'Source', 'label' => 'Sumber'], ['id' => 'Fund', 'label' => 'Rekening'], ['id' => 'Dir', 'label' => 'Arah']],
            'placeholder' => 'Referensi / deskripsi',
        ])
        <x-tw.card padding="sm">
            <x-slot:header>Transaksi Terbaru (Treasury, Cash Receipt, Entri Manual)</x-slot:header>
            <div class="table-responsive">
                <table id="tblOvRecent" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                    <thead class="bg-light">
                        <tr>
                            <th scope="col" class="text-nowrap">Tanggal</th><th scope="col">Referensi</th><th scope="col">Deskripsi</th><th scope="col">Sumber</th><th scope="col">Rekening</th>
                            <th scope="col" class="text-right text-nowrap">Cash In</th><th scope="col" class="text-right text-nowrap">Cash Out</th><th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($recent as $r)
                        <tr data-source="{{ $r['category'] }}" data-fund="{{ $r['source_fund'] }}" data-dir="{{ $r['direction'] }}">
                            <td class="text-nowrap" data-order="{{ $r['date']->format('Y-m-d') }}">{{ $r['date']->format('d M Y') }}</td>
                            <td class="text-nowrap">@if($r['url'])<a href="{{ $r['url'] }}">{{ $r['reference_number'] }}</a>@else{{ $r['reference_number'] }}@endif</td>
                            <td>{{ $r['description'] }}</td>
                            <td>{{ $r['category'] }}</td>
                            <td>{{ $r['source_fund'] }}</td>
                            <td class="text-right text-nowrap" data-order="{{ $r['direction'] === 'IN' ? (int) $r['amount'] : 0 }}">{{ $r['direction'] === 'IN' ? $rp($r['amount']) : '' }}</td>
                            <td class="text-right text-nowrap" data-order="{{ $r['direction'] === 'OUT' ? (int) $r['amount'] : 0 }}">{{ $r['direction'] === 'OUT' ? $rp($r['amount']) : '' }}</td>
                            <td><span class="badge badge-success">Posted</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty message="Belum ada transaksi." /></td></tr>
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

@push('scripts')
<script src="@aset('js/tesys-list-table.js')"></script>
<script>
$(function () {
    TesysListTable.init({
        table: '#tblOvAccounts', order: [[7, 'desc']], search: '#ovSearch', clear: '#ovClear',
        filters: [
            { target: '#ovFilterType', key: 'type', label: 'Semua Tipe', search: false },
            { target: '#ovFilterOpening', key: 'opening', label: 'Semua Saldo Awal', search: false },
            { target: '#ovFilterRecon', key: 'recon', label: 'Semua Rekonsiliasi', search: false }
        ]
    });
    TesysListTable.init({
        table: '#tblOvRecent', order: [[0, 'desc']], search: '#ovrSearch', clear: '#ovrClear',
        filters: [
            { target: '#ovrFilterSource', key: 'source', label: 'Semua Sumber' },
            { target: '#ovrFilterFund', key: 'fund', label: 'Semua Rekening' },
            { target: '#ovrFilterDir', key: 'dir', label: 'Semua Arah', valueLabels: { IN: 'Cash In', OUT: 'Cash Out' }, search: false }
        ]
    });
});
</script>
@endpush
