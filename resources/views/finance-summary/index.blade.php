@extends('layouts.app')

@section('title', 'Summary Finance')

@section('content')

<style>
    /* ---- Timeline status (grid: kolom seragam, garis tidak melar saat wrap) ---- */
    .tl-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(88px, 1fr));
        row-gap: 18px;
    }
    .tl-step {
        position: relative;
        text-align: center;
        padding: 0 2px;
    }
    /* garis penghubung di belakang badge */
    .tl-step::before {
        content: "";
        position: absolute;
        top: 11px;
        left: 0;
        right: 0;
        height: 2px;
        background: #e3e6ea;
        z-index: 0;
    }
    .tl-step:first-child::before { left: 50%; }
    .tl-step:last-child::before  { right: 50%; }

    .tl-node {
        position: relative;
        z-index: 1;
        min-width: 26px;
        padding: 5px 8px;
        font-size: 12px;
        box-shadow: 0 0 0 3px #fff;   /* garis seolah lewat di belakang badge */
    }
    .tl-label {
        margin-top: 7px;
        font-size: 12px;
        line-height: 1.25;
    }
    .tl-clickable { cursor: pointer; transition: transform .15s ease; }
    .tl-clickable:hover { transform: translateY(-2px); }
    .tl-clickable:hover .tl-node { box-shadow: 0 0 0 3px #fff, 0 0 0 5px rgba(245,166,35,0.35); }
    .tl-clickable:focus { outline: none; }
    .tl-clickable:focus .tl-node { box-shadow: 0 0 0 3px #fff, 0 0 0 5px rgba(245,166,35,0.5); }

    /* ---- Bar distribusi ---- */
    .dist-bar {
        display: flex;
        height: 6px;
        border-radius: 3px;
        overflow: hidden;
        background: #f1f3f5;
    }
    .dist-bar .seg { flex: 0 0 auto; height: 100%; }
</style>

<!-- CONTENT HEADER -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold">
                    <i class="fas fa-chart-pie mr-2" style="color:#F5A623;"></i>Finance Summary
                </h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="#">Finance</a></li>
                    <li class="breadcrumb-item active">Summary</li>
                </ol>
            </div>
        </div>
    </div>
</div>

@php
    // [Label, jumlah, warna bootstrap, raw status key di DB]
    $poStatuses = [
        ['Submitted',     $po['submitted'],     'warning', 'SUBMITTED'],
        ['Waiting PO',    $po['waiting_po'],    'info',    'WAITING_PO'],
        ['Active',        $po['active'],        'success', 'ACTIVE'],
        ['Progress Done', $po['progress_done'], 'primary', 'PROGRESS_DONE'],
        ['Billed',        $po['billed'],        'info',    'BILLED'],
        ['Paid',          $po['paid'],          'success', 'PAID'],
        ['Closed',        $po['closed'],        'dark',    'CLOSED'],
    ];
    $crStatuses = [
        ['Submitted', $cr['submitted'], 'warning', 'SUBMITTED'],
        ['Approved',  $cr['approved'],  'info',    'APPROVED'],
        ['Verified',  $cr['verified'],  'primary', 'VERIFIED'],
        ['Paid',      $cr['paid'],      'success', 'PAID'],
        ['Realized',  $cr['realized'],  'primary', 'REALIZED'],
        ['Closed',    $cr['closed'],    'dark',    'CLOSED'],
        ['Rejected',  $cr['rejected'],  'danger', 'REJECTED'],
    ];
@endphp

<!-- MAIN CONTENT -->
<div class="content">
    <div class="container-fluid">

        <div class="row">

            {{-- ======================================================== --}}
            {{-- KIRI (75%)                                               --}}
            {{-- ======================================================== --}}
            <div class="col-lg-9">

                {{-- KPI CARDS --}}
                <div class="row">

                    <!-- PO -->
                    @if(!$hidePo)
                    <div class="col-12 col-md-3">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3>{{ $po['total'] }}</h3>
                                <p>Total PO</p>
                                @if($po['can_see_value'])
                                    <span style="font-size:14px;font-weight:600;">
                                        Rp {{ number_format($po['total_value'], 0, ',', '.') }}
                                    </span>
                                @else
                                    <span style="font-size:12px;font-weight:600;opacity:.75;">
                                        <i class="fas fa-lock mr-1"></i> Nilai tersembunyi
                                    </span>
                                @endif
                            </div>
                            <div class="icon"><i class="fas fa-file-signature"></i></div>
                            @if($po['can_see_value'])
                                <a href="{{ url('/project') }}" class="small-box-footer">
                                    Lihat PO <i class="fas fa-arrow-circle-right"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                    @endif

                    <!-- BUDGETING -->
                    <div class="col-12 col-md-3">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3>{{ $bp['total'] }}</h3>
                                <p>Total Budget Plan</p>
                                <span style="font-size:14px;font-weight:600;">
                                    Rp {{ number_format($bp['total_amount'], 0, ',', '.') }}
                                </span>
                            </div>
                            <div class="icon"><i class="fas fa-wallet"></i></div>
                            <a href="{{ url('/budget-plan') }}" class="small-box-footer">
                                Lihat Budgeting <i class="fas fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- COST REQUEST -->
                    <div class="col-12 col-md-3">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3>{{ $cr['total'] }}</h3>
                                <p>Total Cost Request</p>
                                <span style="font-size:14px;font-weight:600;">
                                    Rp {{ number_format($cr['total_amount'], 0, ',', '.') }}
                                </span>
                            </div>
                            <div class="icon"><i class="fas fa-receipt"></i></div>
                            <a href="{{ url('/budget-request') }}" class="small-box-footer">
                                Lihat Cost Request <i class="fas fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- REALISASI -->
                    <div class="col-12 col-md-3">
                        <div class="small-box" style="background:#7C3AED;color:#fff;">
                            <div class="inner">
                                <h3 style="color:#fff;">{{ $cr['closed'] }}</h3>
                                <p>Total Realisasi</p>
                                <span style="font-size:14px;font-weight:600;">
                                    Rp {{ number_format($cr['realized_amount'], 0, ',', '.') }}
                                </span>
                            </div>
                            <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
                            <a href="{{ url('/budget-request') }}" class="small-box-footer" style="background:rgba(0,0,0,0.1);color:#fff;">
                                Lihat Realisasi <i class="fas fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    </div>

                </div>

                {{-- STATUS PO --}}
                @if(!$hidePo)
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title font-weight-bold text-dark">
                            <i class="fas fa-file-signature mr-1 text-info"></i> Status PO
                        </h3>
                    </div>
                    <div class="card-body py-3">

                        {{-- Bar distribusi: di mana volume menumpuk --}}
                        <div class="dist-bar mb-4">
                            @foreach($poStatuses as $s)
                                @if($s[1] > 0)
                                    @php $segPct = round($s[1] / max(1, $po['total']) * 100, 2); @endphp
                                    <div class="seg bg-{{ $s[2] }} js-bar" data-width="{{ $segPct }}" title="{{ $s[0] }}: {{ $s[1] }}"></div>
                                @endif
                            @endforeach
                        </div>

                        {{-- Timeline --}}
                        <div class="tl-grid">
                            @foreach($poStatuses as $s)
                                <div class="tl-step tl-clickable" role="button" tabindex="0"
                                     data-module="po" data-status="{{ $s[3] }}" data-label="{{ $s[0] }}" data-count="{{ $s[1] }}" data-color="{{ $s[2] }}">
                                    <span class="badge tl-node {{ $s[1] > 0 ? 'badge-' . $s[2] : 'badge-light text-muted' }}">{{ $s[1] }}</span>
                                    <div class="tl-label {{ $s[1] > 0 ? 'text-dark font-weight-600' : 'text-muted' }}">{{ $s[0] }}</div>
                                </div>
                            @endforeach
                        </div>

                    </div>
                </div>
                @endif

                {{-- STATUS COST REQUEST --}}
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title font-weight-bold text-dark">
                            <i class="fas fa-receipt mr-1 text-warning"></i> Status Cost Request
                        </h3>
                    </div>
                    <div class="card-body py-3">

                        <div class="dist-bar mb-4">
                            @foreach($crStatuses as $s)
                                @if($s[1] > 0)
                                    @php $segPct = round($s[1] / max(1, $cr['total']) * 100, 2); @endphp
                                    <div class="seg bg-{{ $s[2] }} js-bar" data-width="{{ $segPct }}" title="{{ $s[0] }}: {{ $s[1] }}"></div>
                                @endif
                            @endforeach
                        </div>

                        <div class="tl-grid">
                            @foreach($crStatuses as $s)
                                <div class="tl-step tl-clickable" role="button" tabindex="0"
                                     data-module="cr" data-status="{{ $s[3] }}" data-label="{{ $s[0] }}" data-count="{{ $s[1] }}" data-color="{{ $s[2] }}">
                                    <span class="badge tl-node {{ $s[1] > 0 ? 'badge-' . $s[2] : 'badge-light text-muted' }}">{{ $s[1] }}</span>
                                    <div class="tl-label {{ $s[1] > 0 ? 'text-dark font-weight-600' : 'text-muted' }}">{{ $s[0] }}</div>
                                </div>
                            @endforeach
                        </div>

                    </div>
                </div>

            </div>

            {{-- ======================================================== --}}
            {{-- KANAN (25%) — SERAPAN ANGGARAN                           --}}
            {{-- ======================================================== --}}
            <div class="col-lg-3">

                <div class="card card-outline card-primary shadow-sm">

                    <div class="card-header">
                        <h3 class="card-title font-weight-bold text-dark">
                            <i class="fas fa-coins mr-1 text-warning"></i> Budget Overview
                        </h3>
                    </div>

                    <div class="card-body">

                        {{-- FILTER BUDGET --}}
                        <form method="GET" action="{{ url('/finance-summary') }}" id="frmBudgetFilter">
                            <div class="form-group mb-3">
                                <label class="text-muted mb-1" style="font-size:11px;letter-spacing:.05em;text-transform:uppercase;font-weight:600;">
                                    Filter Budget
                                </label>
                                <select name="budget_plan_id" id="selBudgetFilter" class="form-control form-control-sm">
                                    <option value="all" {{ $selectedBudgetId === 'all' ? 'selected' : '' }}>
                                        — All Active Budget —
                                    </option>
                                    @foreach($activeBudgetPlans as $plan)
                                        <option value="{{ $plan->id }}" {{ (string) $selectedBudgetId === (string) $plan->id ? 'selected' : '' }}>
                                            [{{ $plan->code }}] {{ $plan->description }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </form>

                        {{-- TOTAL BUDGET --}}
                        <div class="mb-3 pb-2 border-bottom">
                            <div class="text-muted" style="font-size:11px;letter-spacing:.05em;text-transform:uppercase;font-weight:600;">Total Budget</div>
                            <div style="font-size:20px;font-weight:700;color:#2563EB;">
                                Rp {{ number_format($finance['total_budget'], 0, ',', '.') }}
                            </div>
                        </div>

                        {{-- DONUT CHART: Sisa Budget vs Terpakai (Request) --}}
                        <div class="mb-3 pb-3 border-bottom text-center">
                            <div style="position:relative;display:inline-block;">
                                <canvas id="chartBudgetRealisasi" width="150" height="150"
                                        data-total-budget="{{ $finance['total_budget'] }}"
                                        data-used="{{ $finance['used'] }}"></canvas>
                                <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;pointer-events:none;">
                                    <div style="font-size:22px;font-weight:700;color:#7C3AED;line-height:1.1;">{{ $finance['absorption_pct'] }}%</div>
                                    <div style="font-size:9px;color:#6B7280;text-transform:uppercase;letter-spacing:.04em;">Terpakai</div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-center mt-2" style="gap:16px;font-size:11px;">
                                <span><i class="fas fa-circle" style="color:#7C3AED;"></i> Terpakai (Request)</span>
                                <span><i class="fas fa-circle" style="color:#E5E7EB;"></i> Sisa Budget</span>
                            </div>
                        </div>

                        @php
                            $realizedOfUsedW = min(100, $finance['realized_of_used_pct']);
                        @endphp

                        {{-- REALISASI DARI TERPAKAI (CR) --}}
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-baseline">
                                <span class="text-muted" style="font-size:11px;letter-spacing:.05em;text-transform:uppercase;font-weight:600;">Terealisasi dari Request</span>
                                <span class="font-weight-bold" style="font-size:12px;color:#7C3AED;">{{ $finance['realized_of_used_pct'] }}%</span>
                            </div>
                            <div style="font-size:16px;font-weight:700;color:#7C3AED;">
                                Rp {{ number_format($finance['realized'], 0, ',', '.') }}
                            </div>
                            <div class="text-muted" style="font-size:11px;">
                                dari Rp {{ number_format($finance['used'], 0, ',', '.') }} (Request)
                            </div>
                            <div class="progress mt-1" style="height:8px;">
                                <div class="progress-bar js-bar" style="background:#7C3AED;" role="progressbar" data-width="{{ $realizedOfUsedW }}"></div>
                            </div>
                        </div>

                        {{-- RINCIAN TERSERAP PER ACCOUNT — PIE CHART --}}
                        <div>
                            <div class="text-muted mb-2" style="font-size:11px;letter-spacing:.05em;text-transform:uppercase;font-weight:600;">
                                Rincian Terpakai (CR Detail)
                            </div>

                            @if($breakdownTotal > 0)
                                <canvas id="chartAccountBreakdown" height="220"
                                        data-breakdown="{{ json_encode($accountBreakdown->map(fn($a) => ['name' => $a['name'], 'total' => $a['total']])) }}"></canvas>
                                <div id="accountBreakdownLegend" class="mt-3"></div>
                            @else
                                <p class="text-muted mb-0" style="font-size:12px;">
                                    <i class="fas fa-inbox mr-1"></i> Belum terpakai.
                                </p>
                            @endif

                        </div>

                    </div>
                </div>

            </div>

        </div>

    </div>
</div>

{{-- ================================================================ --}}
{{-- MODAL DETAIL STATUS (timeline PO / Cost Request)                  --}}
{{-- ================================================================ --}}
<div class="modal fade" id="mdlStatusDetail" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white" id="statusDetailTitle"></h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" style="padding:24px;">
                <div class="d-flex align-items-center justify-content-between mb-3" style="gap:14px;">
                    <div class="d-flex align-items-center" style="gap:14px;">
                        <div id="statusDetailCount"
                             style="width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:#fff;flex-shrink:0;"></div>
                        <div>
                            <div id="statusDetailModule" class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;font-weight:600;"></div>
                            <div id="statusDetailStatus" style="font-size:16px;font-weight:700;"></div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-muted" style="font-size:9px;text-transform:uppercase;letter-spacing:.05em;">Total Nilai</div>
                        <div id="statusDetailGrandTotal" style="font-size:16px;font-weight:700;color:#2563EB;"></div>
                    </div>
                </div>
                <p id="statusDetailDesc" class="text-muted mb-3" style="font-size:13px;"></p>

                <div class="table-responsive" style="max-height:340px;overflow-y:auto;border:1px solid #E5E7EB;border-radius:8px;">
                    <table class="table table-sm table-bordered mb-0" style="font-size:12px;">
                        <thead style="background:#F9FAFB;position:sticky;top:0;">
                            <tr>
                                <th id="statusDetailColLeft" style="color:#6B7280;font-size:11px;text-transform:uppercase;"></th>
                                <th style="color:#6B7280;font-size:11px;text-transform:uppercase;text-align:right;width:30%;">Nilai</th>
                            </tr>
                        </thead>
                        <tbody id="statusDetailBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="background:#f8f9fa;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
                <a href="#" id="statusDetailLink" class="btn btn-primary btn-sm">
                    Lihat Semua di Halaman <i class="fas fa-arrow-circle-right ml-1"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<textarea id="poListHolder" style="display:none;">{{ json_encode($poList) }}</textarea>
<textarea id="crListHolder" style="display:none;">{{ json_encode($crList) }}</textarea>

@endsection

@push('scripts')
<script src="{{ asset('plugins/chart.js/Chart.min.js') }}"></script>
<script>
$(function () {
    // Set lebar semua progress bar dari data-width (hindari CSS inline berisi Blade)
    document.querySelectorAll('.js-bar').forEach(function (el) {
        el.style.width = (el.getAttribute('data-width') || 0) + '%';
    });

    // Auto-submit saat filter budget diubah
    var sel = document.getElementById('selBudgetFilter');
    if (sel) {
        sel.addEventListener('change', function () {
            document.getElementById('frmBudgetFilter').submit();
        });
    }

    // Donut chart: Sisa Budget vs Terpakai (Request)
    var chartEl = document.getElementById('chartBudgetRealisasi');
    if (chartEl && window.Chart) {
        var totalBudget = parseFloat(chartEl.dataset.totalBudget) || 0;
        var used         = parseFloat(chartEl.dataset.used) || 0;
        var sisa         = Math.max(0, totalBudget - used);

        new Chart(chartEl.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Terpakai (Request)', 'Sisa Budget'],
                datasets: [{
                    data: [used, sisa],
                    backgroundColor: ['#7C3AED', '#E5E7EB'],
                    borderWidth: 0
                }]
            },
            options: {
                cutoutPercentage: 68,
                legend: { display: false },
                tooltips: {
                    callbacks: {
                        label: function (item, data) {
                            var val = data.datasets[0].data[item.index];
                            return data.labels[item.index] + ': Rp ' + val.toLocaleString('id-ID');
                        }
                    }
                }
            }
        });
    }

    // Pie chart: Rincian Terpakai (CR Detail) per Account
    var breakdownEl = document.getElementById('chartAccountBreakdown');
    if (breakdownEl && window.Chart) {
        var breakdown = JSON.parse(breakdownEl.dataset.breakdown || '[]');
        var palette = ['#2563EB', '#7C3AED', '#F5A623', '#16a34a', '#DC2626', '#0EA5E9', '#D97706', '#DB2777', '#6B7280', '#059669'];
        var labels  = breakdown.map(function (b) { return b.name; });
        var values  = breakdown.map(function (b) { return b.total; });
        var colors  = breakdown.map(function (b, i) { return palette[i % palette.length]; });
        var grandTotal = values.reduce(function (a, b) { return a + b; }, 0);

        new Chart(breakdownEl.getContext('2d'), {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{ data: values, backgroundColor: colors, borderWidth: 1, borderColor: '#fff' }]
            },
            options: {
                legend: { display: false },
                tooltips: {
                    callbacks: {
                        label: function (item, data) {
                            var val = data.datasets[0].data[item.index];
                            var pct = grandTotal > 0 ? (val / grandTotal * 100).toFixed(1) : 0;
                            return data.labels[item.index] + ': Rp ' + val.toLocaleString('id-ID') + ' (' + pct + '%)';
                        }
                    }
                }
            }
        });

        // Legend manual (nama + nilai + persen), supaya presisi tetap kebaca
        var legendHtml = '';
        breakdown.forEach(function (b, i) {
            var pct = grandTotal > 0 ? (b.total / grandTotal * 100).toFixed(1) : 0;
            legendHtml +=
                '<div class="d-flex align-items-center justify-content-between mb-1" style="font-size:11px;">' +
                    '<span><i class="fas fa-circle mr-1" style="color:' + colors[i] + ';font-size:8px;"></i>' + b.name + '</span>' +
                    '<span class="text-muted">Rp ' + b.total.toLocaleString('id-ID') + ' <strong class="text-dark">(' + pct + '%)</strong></span>' +
                '</div>';
        });
        document.getElementById('accountBreakdownLegend').innerHTML = legendHtml;
    }

    // ── Modal detail status (timeline PO / Cost Request) ────────────
    var poList = JSON.parse(document.getElementById('poListHolder').value || '{}');
    var crList = JSON.parse(document.getElementById('crListHolder').value || '{}');

    var statusMeta = {
        po: {
            label: 'Purchase Order',
            url: '{{ url("/project") }}',
            colHeader: 'Scope of Work',
            data: poList,
            colorMap: { warning:'#F5A623', info:'#3B82F6', success:'#16a34a', primary:'#2563EB', dark:'#111827', secondary:'#6B7280', danger:'#DC2626' },
            desc: {
                'Submitted':     'PO sudah diajukan Head dan menunggu review Finance sebelum bisa diaktifkan.',
                'Waiting PO':    'PO sudah disetujui Finance dan menunggu diaktifkan (nomor PO resmi terbit).',
                'Active':        'PO sedang berjalan — pekerjaan di lapangan aktif dikerjakan.',
                'Progress Done': 'Pekerjaan di lapangan sudah selesai, menunggu proses BAST/billing.',
                'Billed':        'PO sudah masuk proses penagihan (invoice) ke customer.',
                'Paid':          'Pembayaran dari customer untuk PO ini sudah diterima.',
                'Closed':        'PO sudah selesai seluruh prosesnya dan ditutup.'
            }
        },
        cr: {
            label: 'Cost Request',
            url: '{{ url("/budget-request") }}',
            colHeader: 'Deskripsi',
            data: crList,
            colorMap: { warning:'#F5A623', info:'#3B82F6', success:'#16a34a', primary:'#2563EB', dark:'#111827', secondary:'#6B7280', danger:'#DC2626' },
            desc: {
                'Submitted': 'Cost Request sudah diajukan manager dan menunggu approval Head.',
                'Approved':  'Sudah disetujui Head, menunggu verifikasi Finance.',
                'Verified':  'Sudah diverifikasi Finance, menunggu masuk batch pembayaran.',
                'Queued':    'Sedang antre dalam batch pembayaran — status transisi, akan segera diproses.',
                'Paid':      'Dana sudah dicairkan ke beneficiary.',
                'Realized':  'Manager sudah submit bukti realisasi penggunaan dana, menunggu verifikasi Finance.',
                'Closed':    'Realisasi sudah diverifikasi Finance dan pengajuan ini selesai.',
                'Rejected':  'Pengajuan ditolak pada salah satu tahap approval.'
            }
        }
    };

    function escapeHtml(str) {
        return $('<div>').text(str || '').html();
    }

    document.querySelectorAll('.tl-clickable').forEach(function (el) {
        function openStatusModal() {
            var module = el.dataset.module;
            var status = el.dataset.status;   // raw key, mis. WAITING_PO — dipakai untuk lookup data
            var label  = el.dataset.label;    // label tampilan, mis. Waiting PO
            var count  = parseInt(el.dataset.count) || 0;
            var color  = el.dataset.color;
            var meta   = statusMeta[module];
            if (!meta) return;

            var hex = meta.colorMap[color] || '#6B7280';

            $('#statusDetailModule').text(meta.label);
            $('#statusDetailStatus').text(label);
            $('#statusDetailTitle').text(label + ' — ' + meta.label);
            $('#statusDetailCount').text(count).css('background', hex);
            $('#statusDetailDesc').text(meta.desc[label] || '');
            $('#statusDetailLink').attr('href', meta.url);
            $('#statusDetailColLeft').text(meta.colHeader);

            var items = meta.data[status] || [];
            var rows = '';
            var grandTotal = 0;
            items.forEach(function (item) {
                grandTotal += parseFloat(item.value_raw) || 0;
                var titleColor = item.is_nob ? 'color:#DC2626;' : '';
                rows += '<tr style="' + titleColor + '">' +
                    '<td>' +
                        '<div style="font-weight:600;">' + escapeHtml(item.title) + '</div>' +
                        '<small class="text-muted">' + escapeHtml(item.subtitle) + '</small>' +
                    '</td>' +
                    '<td class="text-right align-middle" style="white-space:nowrap;">' + escapeHtml(item.value) + '</td>' +
                '</tr>';
            });
            if (!items.length) {
                rows = '<tr><td colspan="2" class="text-center text-muted py-3">Tidak ada data.</td></tr>';
            }
            $('#statusDetailBody').html(rows);
            $('#statusDetailGrandTotal').text('Rp ' + grandTotal.toLocaleString('id-ID'));

            $('#mdlStatusDetail').modal('show');
        }

        el.addEventListener('click', openStatusModal);
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openStatusModal();
            }
        });
    });
});
</script>
@endpush