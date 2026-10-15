@extends('layouts.app')

@section('content')

<!-- MAIN CONTENT -->
<div class="content reports-page">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="page-title">Reports</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item active">Reports</li>
            </ol>
        </div>

        <!-- FILTER -->
        <div class="section-heading">Filter</div>
        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-sliders-h mr-2"></i>Report filter</h3>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('reports.index') }}" id="reportFilterForm">

                    <div class="filter-top-row">
                        <div class="form-group ft-report-type">
                            <label class="field-label">Report type</label>
                            <select name="report_type" id="reportType" class="form-control form-control-sm">
                                <option value="">-- Pilih report type --</option>
                                <option value="po" {{ $reportType === 'po' ? 'selected' : '' }}>Purchase Order Statement</option>
                                <option value="account" {{ $reportType === 'account' ? 'selected' : '' }}>Bank / Account Statement</option>
                            </select>
                            <small class="text-muted d-block mt-1">General Ledger akan tersedia setelah modul Journal dibangun.</small>
                        </div>

                        <div class="filter-row-period {{ $reportType === 'po' ? 'd-none' : '' }}" id="periodFieldsWrap">
                            <div class="form-group">
                                <label class="field-label">Periode</label>
                                <select name="period" id="reportPeriod" class="form-control form-control-sm">
                                    <option value="this_month" {{ request('period', 'this_month') === 'this_month' ? 'selected' : '' }}>Bulan ini</option>
                                    <option value="last_month" {{ request('period') === 'last_month' ? 'selected' : '' }}>Bulan lalu</option>
                                    <option value="custom" {{ request('period') === 'custom' ? 'selected' : '' }}>Custom</option>
                                </select>
                            </div>
                            {{-- Satu kotak rentang (TesysDateRange): klik membuka dua
                                 kalender From/To. Hanya aktif saat period = custom,
                                 nilainya tetap dikirim sebagai date_from & date_to. --}}
                            <div class="form-group">
                                <label class="field-label">Periode</label>
                                <div>
                                    <input type="text" id="reportRange" class="form-control form-control-sm tdr-input" readonly
                                           {{ request('period') !== 'custom' ? 'disabled' : '' }}>
                                    <input type="hidden" name="date_from" id="reportStart"
                                           value="{{ request('date_from') }}" {{ request('period') !== 'custom' ? 'disabled' : '' }}>
                                    <input type="hidden" name="date_to" id="reportEnd"
                                           value="{{ request('date_to') }}" {{ request('period') !== 'custom' ? 'disabled' : '' }}>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group {{ $reportType === 'po' ? '' : 'd-none' }}" id="poFieldWrap">
                        <label class="field-label">Purchase order</label>
                        <select name="po_id" id="poSelect" class="form-control form-control-sm">
                            <option value="">-- Pilih PO --</option>
                            @foreach($projects as $p)
                            <option value="{{ $p->id }}" {{ (string) request('po_id') === (string) $p->id ? 'selected' : '' }}>
                                {{ $p->customer->name ?? '-' }} - {{ $p->code }} - {{ $p->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group {{ $reportType === 'account' ? '' : 'd-none' }}" id="accountFieldWrap">
                        <label class="field-label">Source fund / rekening</label>
                        <select name="source_fund_id" id="accountSelect" class="form-control form-control-sm">
                            <option value="">-- Pilih rekening --</option>
                            @foreach($sourceFunds as $sf)
                            <option value="{{ $sf->id }}" {{ (string) request('source_fund_id') === (string) $sf->id ? 'selected' : '' }}>
                                {{ $sf->name }}{{ $sf->bank ? ' — ' . $sf->bank->name : '' }} ({{ $sf->no_account ?? '-' }})
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" id="btnGenerate" class="btn btn-generate">
                            <i class="fas fa-bolt mr-2"></i> Generate
                        </button>
                        @if($result)
                        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary ml-2">
                            <i class="fas fa-eraser mr-1"></i> Reset Filter
                        </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <!-- EMPTY STATE -->
        @if(!$result)
        <div class="empty-state">
            <i class="fas fa-file-alt"></i>
            <p>Pilih report type dan filter di atas, lalu klik Generate untuk melihat hasil.</p>
        </div>
        @endif

        <!-- ================= PO STATEMENT ================= -->
        @if($result && $result['type'] === 'po')
        <div id="poResult">

            <div class="result-header">
                <div>
                    <h2 class="result-title">{{ $result['project']->code }} — {{ $result['project']->name }}</h2>
                    <span class="result-period">Semua data yang terhubung ke PO ini (tidak dibatasi periode)</span>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="Export akan tersedia setelah modul Journal siap">
                    <i class="fas fa-file-excel mr-1"></i> Export Excel
                </button>
            </div>

            <div class="section-heading">Summary</div>

            <div class="stat-grid mb-2">
                <div class="stat-tile stat-tile-icon">
                    <div class="stat-icon stat-icon-po"><i class="fas fa-file-contract"></i></div>
                    <div>
                        <div class="stat-label">Nilai PO</div>
                        <div class="stat-value">{{ number_format($result['value'], 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="stat-tile stat-tile-icon">
                    <div class="stat-icon stat-icon-budget"><i class="fas fa-wallet"></i></div>
                    <div>
                        <div class="stat-label">Budget</div>
                        <div class="stat-value">{{ number_format($result['budget'], 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="stat-tile stat-tile-icon">
                    <div class="stat-icon stat-icon-plan"><i class="fas fa-chart-line"></i></div>
                    <div>
                        <div class="stat-label">Gross Profit</div>
                        <div class="stat-value {{ $result['gross_profit'] >= 0 ? 'stat-positive' : 'stat-negative' }}">{{ number_format($result['gross_profit'], 0, ',', '.') }}</div>
                        <div class="stat-sub">{{ rtrim(rtrim(number_format($result['gross_margin_pct'], 1, ',', '.'), '0'), ',') }}%</div>
                    </div>
                </div>
                <div class="stat-tile stat-tile-icon">
                    <div class="stat-icon stat-icon-actual"><i class="fas fa-chart-bar"></i></div>
                    <div>
                        <div class="stat-label">Project Profit</div>
                        <div class="stat-value {{ $result['project_profit'] >= 0 ? 'stat-positive' : 'stat-negative' }}">{{ number_format($result['project_profit'], 0, ',', '.') }}</div>
                        <div class="stat-sub">{{ rtrim(rtrim(number_format($result['project_margin_pct'], 1, ',', '.'), '0'), ',') }}%</div>
                    </div>
                </div>
            </div>

            <div class="stat-grid mb-3">
                <div class="stat-tile stat-tile-icon">
                    <div class="stat-icon stat-icon-variable"><i class="fas fa-sliders-h"></i></div>
                    <div>
                        <div class="stat-label">Variable cost</div>
                        <div class="stat-value stat-negative">{{ number_format($result['variable_cost'], 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="stat-tile stat-tile-icon">
                    <div class="stat-icon stat-icon-fixed"><i class="fas fa-lock"></i></div>
                    <div>
                        <div class="stat-label">Fixed cost</div>
                        <div class="stat-value stat-negative">{{ number_format($result['fixed_cost'], 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="stat-tile stat-tile-icon">
                    <div class="stat-icon stat-icon-overhead"><i class="fas fa-layer-group"></i></div>
                    <div>
                        <div class="stat-label">Overhead</div>
                        <div class="stat-value stat-negative">{{ number_format($result['overhead_cost'], 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="stat-tile stat-tile-icon">
                    <div class="stat-icon stat-icon-margin"><i class="fas fa-balance-scale"></i></div>
                    <div>
                        <div class="stat-label">Net Profit</div>
                        <div class="stat-value {{ $result['net_profit'] >= 0 ? 'stat-positive' : 'stat-negative' }}">{{ number_format($result['net_profit'], 0, ',', '.') }}</div>
                        <div class="stat-sub">{{ rtrim(rtrim(number_format($result['net_profit_pct'], 1, ',', '.'), '0'), ',') }}%</div>
                    </div>
                </div>
            </div>

            <div class="section-heading d-flex align-items-center justify-content-between flex-wrap" style="gap:8px;">
                <span>Isi</span>
                <div class="d-flex" style="gap:6px;">
                    <button type="button" class="btn btn-xs btn-outline-secondary" id="btnExpandAllSections">
                        <i class="fas fa-angle-double-down mr-1"></i> Expand All
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-secondary" id="btnCollapseAllSections">
                        <i class="fas fa-angle-double-up mr-1"></i> Collapse All
                    </button>
                </div>
            </div>

            <div id="poResultAccordion">

                <!-- INFORMASI -->
                <div class="report-section">
                    <div class="report-section-header collapse-toggle" data-target="#accInfo">
                        <h3><i class="fas fa-info-circle"></i> Informasi</h3>
                        <i class="fas fa-chevron-up chevron-icon"></i>
                    </div>
                    <div class="report-section-body collapse show" id="accInfo">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm mb-0 kv-table">
                                    <tr>
                                        <td>Kode</td>
                                        <td>
                                            {{ $result['project']->code }}
                                            <span class="badge badge-{{ $result['project']->status_color }} ml-1">{{ $result['project']->status_label }}</span>
                                        </td>
                                    </tr>
                                    <tr><td>Scope of work</td><td>{{ $result['project']->name }}</td></tr>
                                    <tr><td>Nama customer</td><td>{{ $result['project']->customer->name ?? '-' }}</td></tr>
                                    <tr>
                                        <td>Durasi pengerjaan</td>
                                        <td>
                                            @if($result['plan_start'] && $result['plan_end'])
                                                {{ \Illuminate\Support\Carbon::parse($result['plan_start'])->format('d M Y') }}
                                                - {{ \Illuminate\Support\Carbon::parse($result['plan_end'])->format('d M Y') }}
                                                ({{ $result['duration_days'] }} hari)
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                    <tr><td>PM internal</td><td>{{ $result['pm_internal'] }}</td></tr>
                                    <tr><td>Term of payment</td><td>{{ $result['term_of_payment'] }}</td></tr>
                                    <tr><td>Financing</td><td>{{ $result['financing_label'] }}</td></tr>
                                </table>
                            </div>
                            <div class="col-md-6 report-pic-col">
                                <div class="report-pic-title">PIC Internal</div>
                                @forelse($result['pic_internal_list'] as $p)
                                <div class="report-pic-card">
                                    <div class="report-pic-name">{{ $p['name'] }}<span class="text-muted font-weight-normal">{{ $p['position'] ? ' — '.$p['position'] : '' }}</span></div>
                                    <div class="report-pic-meta"><i class="fas fa-phone mr-1"></i>{{ $p['phone'] ?: '-' }}</div>
                                    <div class="report-pic-meta"><i class="fas fa-envelope mr-1"></i>{{ $p['email'] ?: '-' }}</div>
                                </div>
                                @empty
                                <div class="text-muted" style="font-size:12px;">Belum ada PIC Internal.</div>
                                @endforelse

                                <div class="report-pic-title mt-3">PIC Customer</div>
                                @forelse($result['pic_customer_list'] as $p)
                                <div class="report-pic-card">
                                    <div class="report-pic-name">{{ $p['name'] }}<span class="text-muted font-weight-normal">{{ $p['position'] ? ' — '.$p['position'] : '' }}</span></div>
                                    <div class="report-pic-meta"><i class="fas fa-phone mr-1"></i>{{ $p['phone'] ?: '-' }}</div>
                                    <div class="report-pic-meta"><i class="fas fa-envelope mr-1"></i>{{ $p['email'] ?: '-' }}</div>
                                </div>
                                @empty
                                <div class="text-muted" style="font-size:12px;">Belum ada PIC Customer.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MILESTONE -->
                <div class="report-section">
                    <div class="report-section-header collapse-toggle" data-target="#accMilestone">
                        <h3><i class="fas fa-flag-checkered"></i> Milestone</h3>
                        <div class="d-flex align-items-center" style="gap:10px;">
                            <a href="{{ route('project-monitoring.show', $result['project']->id) }}" target="_blank"
                               class="btn btn-xs btn-outline-secondary" onclick="event.stopPropagation();" title="Buka Project Monitoring">
                                <i class="fas fa-binoculars mr-1"></i> Project Monitoring
                            </a>
                            <i class="fas fa-chevron-up chevron-icon"></i>
                        </div>
                    </div>
                    <div class="report-section-body p-0 collapse show" id="accMilestone">
                        @if($result['milestones']->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox d-block mb-2"></i> Belum ada milestone.
                        </div>
                        @else
                        <table class="table table-sm mb-0 data-table">
                            <thead>
                                <tr>
                                    <th scope="col">Milestone</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Plan start</th>
                                    <th scope="col">Plan end</th>
                                    <th scope="col">Actual start</th>
                                    <th scope="col">Actual end</th>
                                    <th scope="col">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($result['milestones'] as $m)
                                <tr class="{{ $m['is_late'] ? 'milestone-late-row' : '' }}">
                                    <td>{{ $m['stage'] }}</td>
                                    <td><span class="badge badge-{{ $m['status_color'] }}">{{ $m['status_label'] }}</span></td>
                                    <td>{{ $m['plan_start'] ? \Illuminate\Support\Carbon::parse($m['plan_start'])->format('d M Y') : '-' }}</td>
                                    <td>{{ $m['plan_end'] ? \Illuminate\Support\Carbon::parse($m['plan_end'])->format('d M Y') : '-' }}</td>
                                    <td>{{ $m['actual_start'] ? \Illuminate\Support\Carbon::parse($m['actual_start'])->format('d M Y') : '-' }}</td>
                                    <td>{{ $m['actual_end'] ? \Illuminate\Support\Carbon::parse($m['actual_end'])->format('d M Y') : '-' }}</td>
                                    <td>
                                        @if($m['is_late'])
                                            <span class="text-danger font-weight-700"><i class="fas fa-exclamation-triangle mr-1"></i>Terlambat ({{ $m['late_days'] }} hari)</span>
                                        @elseif($m['notes'])
                                            {{ Str::limit($m['notes'], 40) }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @endif
                    </div>
                </div>

                <!-- BUDGET -->
                <div class="report-section">
                    <div class="report-section-header collapse-toggle" data-target="#accBudget">
                        <h3><i class="fas fa-wallet"></i> Budget</h3>
                        <div class="d-flex align-items-center" style="gap:10px;">
                            @if($result['budget_plans']->isNotEmpty())
                            <button type="button" class="btn btn-xs btn-outline-secondary" id="btnExpandAllBudget" onclick="event.stopPropagation();">Expand All</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary" id="btnCollapseAllBudget" onclick="event.stopPropagation();">Collapse All</button>
                            @endif
                            <i class="fas fa-chevron-up chevron-icon"></i>
                        </div>
                    </div>
                    <div class="report-section-body p-0 collapse show" id="accBudget">
                        @if($result['budget_plans']->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox d-block mb-2"></i> Belum ada Budget Plan.
                        </div>
                        @else
                        @php
                            $NOB_RED = 'var(--danger)'; $NOB_YELLOW = 'var(--warning)'; $OK_GREEN = 'var(--success)';
                        @endphp
                        @foreach($result['budget_plans'] as $bpi => $bp)
                        <div class="{{ !$loop->first ? 'border-top' : '' }}">
                            <div class="report-section-header report-section-header-static">
                                <h3>
                                    <span class="badge badge-dark mr-2">{{ $bp['code'] }}</span>
                                    {{ $bp['description'] }}
                                    <span class="badge badge-{{ $bp['status_color'] }} ml-1">{{ $bp['status'] }}</span>
                                </h3>
                                <div class="bp-summary-inline">
                                    Budget: <strong>{{ number_format($bp['amount'], 0, ',', '.') }}</strong>
                                    &nbsp;Used: <strong class="text-warning">{{ number_format($bp['used'], 0, ',', '.') }}</strong>
                                    &nbsp;Var: <strong class="{{ $bp['variance'] >= 0 ? 'stat-positive' : 'stat-negative' }}">{{ number_format($bp['variance'], 0, ',', '.') }}</strong>
                                </div>
                            </div>
                            <table class="table table-sm mb-0 data-table">
                                <thead>
                                    <tr>
                                        <th scope="col" style="width:24px;"></th>
                                        <th scope="col">Description</th>
                                        <th scope="col" class="text-right">Price</th>
                                        <th scope="col" class="text-right">Qty</th>
                                        <th scope="col" class="text-right">Budget</th>
                                        <th scope="col" class="text-right">Used</th>
                                        <th scope="col" class="text-right">Variance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bp['groups'] as $gi => $group)
                                    @php
                                        $gVariance = $group['budget'] - $group['used'];
                                        $gClass = $group['is_nob_full'] ? 'nob-grp-red' : ($group['is_nob_partial'] ? 'nob-grp-yellow' : '');
                                        $gVarClass = $group['is_nob_full'] ? 'nob-grp-red' : ($group['is_nob_partial'] ? 'nob-grp-yellow' : ($gVariance >= 0 ? 'nob-grp-green' : 'nob-grp-red'));
                                    @endphp
                                    <tr class="coa-row collapse-toggle" data-target=".bp-{{ $bpi }}-grp-{{ $gi }}">
                                        <td><i class="fas fa-chevron-down chevron-icon chevron-icon-sm"></i></td>
                                        <td class="{{ $gClass }}">
                                            {{ $group['account'] }} <span class="badge badge-secondary">{{ count($group['items']) }}</span>
                                            @if($group['is_nob_full'])<span class="badge badge-danger ml-1" style="font-size:9px;">NOB</span>@endif
                                        </td>
                                        <td class="text-right"></td>
                                        <td class="text-right"></td>
                                        <td class="text-right {{ $gClass }}">{{ number_format($group['budget'], 0, ',', '.') }}</td>
                                        <td class="text-right {{ $gClass }}">{{ number_format($group['used'], 0, ',', '.') }}</td>
                                        <td class="text-right {{ $gVarClass }}">{{ number_format($gVariance, 0, ',', '.') }}</td>
                                    </tr>
                                    @foreach($group['items'] as $item)
                                    @php
                                        $itemVarClass = $item['is_nob'] ? 'nob-item-red' : ($item['variance'] >= 0 ? 'nob-item-green' : 'nob-item-red');
                                        $itemRowClass = $item['is_nob'] ? 'nob-item-red' : '';
                                    @endphp
                                    <tr class="coa-detail-row bp-{{ $bpi }}-grp-{{ $gi }} collapse {{ $itemRowClass }}">
                                        <td></td>
                                        <td class="pl-4">
                                            {{ $item['description'] }}
                                            @if($item['is_nob'])<span class="badge badge-danger ml-1" style="font-size:9px;">NOB</span>@endif
                                        </td>
                                        <td class="text-right">{{ number_format($item['price'], 0, ',', '.') }}</td>
                                        <td class="text-right">{{ rtrim(rtrim(number_format($item['qty'], 2, ',', '.'), '0'), ',') }} {{ $item['unit'] }}</td>
                                        <td class="text-right">{{ number_format($item['budget'], 0, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format($item['used'], 0, ',', '.') }}</td>
                                        <td class="text-right {{ $itemVarClass }}">{{ number_format($item['variance'], 0, ',', '.') }}</td>
                                    </tr>
                                    @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endforeach
                        @endif
                    </div>
                </div>

                <!-- FIXED COST & OVERHEAD -->
                <div class="report-section">
                    <div class="report-section-header collapse-toggle" data-target="#accCostRegister">
                        <h3><i class="fas fa-book"></i> Fixed Cost &amp; Overhead</h3>
                        <i class="fas fa-chevron-up chevron-icon"></i>
                    </div>
                    <div class="report-section-body p-0 collapse show" id="accCostRegister">
                        @if($result['cost_registers']->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox d-block mb-2"></i>
                            Belum ada Cost Register untuk PO ini.
                            <div style="font-size:11px;margin-top:4px;">Nominal Fixed Cost &amp; Overhead dicatat manual lewat modul Cost Register (khusus Finance).</div>
                        </div>
                        @else
                        <table class="table table-sm mb-0 data-table">
                            <thead>
                                <tr>
                                    <th scope="col">Tipe</th>
                                    <th scope="col" class="text-right">Nominal</th>
                                    <th scope="col">Periode</th>
                                    <th scope="col">Catatan</th>
                                    <th scope="col">Lampiran</th>
                                    <th scope="col">Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($result['cost_registers'] as $cr)
                                <tr>
                                    <td><span class="badge badge-{{ $cr->type_color }}">{{ $cr->type_label }}</span></td>
                                    <td class="text-right">{{ number_format($cr->amount, 0, ',', '.') }}</td>
                                    <td>{{ $cr->period ? $cr->period->format('M Y') : '-' }}</td>
                                    <td style="max-width:280px;">{{ $cr->notes }}</td>
                                    <td>
                                        @forelse($cr->attachments as $a)
                                        <a href="/storage/{{ $a->file_path }}" target="_blank" title="{{ $a->title }}"><i class="fas fa-paperclip"></i></a>
                                        @empty
                                        <span class="text-muted">-</span>
                                        @endforelse
                                    </td>
                                    <td>{{ $cr->created_at->format('d M Y') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @endif
                    </div>
                </div>

                <!-- INVOICE -->
                <div class="report-section">
                    <div class="report-section-header collapse-toggle" data-target="#accInvoice">
                        <h3><i class="fas fa-file-invoice"></i> Invoice</h3>
                        <i class="fas fa-chevron-up chevron-icon"></i>
                    </div>
                    <div class="report-section-body p-0 collapse show" id="accInvoice">
                        @if($result['invoices']->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox d-block mb-2"></i>
                            Belum ada Invoice.
                        </div>
                        @else
                        <table class="table table-sm mb-0 data-table">
                            <thead><tr><th scope="col">No. invoice</th><th scope="col">Tanggal</th><th scope="col">Payment term</th><th scope="col" class="text-right">Nilai</th><th scope="col">Status</th></tr></thead>
                            <tbody>
                                @foreach($result['invoices'] as $inv)
                                @php $ds = $inv->display_status; @endphp
                                <tr>
                                    <td>{{ $inv->code }}</td>
                                    <td>{{ $inv->invoice_date?->format('d M Y') }}</td>
                                    <td><span class="badge badge-light">{{ $inv->payment_term_label }}</span></td>
                                    <td class="text-right">{{ number_format($inv->captured_total, 0, ',', '.') }}</td>
                                    <td><span class="badge badge-{{ $ds === 'PAID' ? 'success' : ($ds === 'OVERDUE' ? 'danger' : 'secondary') }}">{{ $ds }}</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @endif
                    </div>
                </div>

                <!-- NO. PO -->
                <div class="report-section">
                    <div class="report-section-header collapse-toggle" data-target="#accPoNumbers">
                        <h3><i class="fas fa-file-signature"></i> No. PO</h3>
                        <i class="fas fa-chevron-up chevron-icon"></i>
                    </div>
                    <div class="report-section-body p-0 collapse show" id="accPoNumbers">
                        @if($result['po_numbers']->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox d-block mb-2"></i> Belum ada No. PO.
                        </div>
                        @else
                        <table class="table table-sm mb-0 data-table">
                            <thead>
                                <tr>
                                    <th scope="col">No. PO</th>
                                    <th scope="col" class="text-right">Nominal</th>
                                    <th scope="col">Tanggal rilis</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">BAST</th>
                                    <th scope="col">Lampiran</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($result['po_numbers'] as $pn)
                                <tr>
                                    <td>{{ $pn->po_number }}</td>
                                    <td class="text-right">{{ number_format($pn->nominal, 0, ',', '.') }}</td>
                                    <td>{{ $pn->release_date?->format('d M Y') ?? '-' }}</td>
                                    <td><span class="badge badge-{{ $pn->status_color }}">{{ $pn->status_label }}</span></td>
                                    <td>
                                        @if($pn->no_bast)
                                            {{ $pn->no_bast }}<br>
                                            <span class="text-muted" style="font-size:11px;">{{ $pn->tanggal_bast?->format('d M Y') ?? '-' }}</span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $pn->attachments->count() }} file</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @endif
                    </div>
                </div>

                <!-- LOG -->
                <div class="report-section">
                    <div class="report-section-header collapse-toggle" data-target="#accLog">
                        <h3><i class="fas fa-history"></i> Log</h3>
                        <i class="fas fa-chevron-up chevron-icon"></i>
                    </div>
                    <div class="report-section-body collapse show" id="accLog">
                        @if($result['logs']->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox d-block mb-2"></i> Belum ada log.
                        </div>
                        @else
                        <div class="rep-timeline">
                            @foreach($result['logs'] as $l)
                            <div class="rep-timeline-item">
                                <span class="rep-timeline-dot badge-{{ $l['action_color'] }}"><i class="fas fa-circle" style="font-size:5px;"></i></span>
                                <div class="rep-timeline-body">
                                    <span class="badge badge-{{ $l['action_color'] }} px-2 py-1 mr-2" style="font-size:10px;">{{ $l['action_label'] }}</span>
                                    <strong>{{ $l['actor'] }}</strong> <span class="text-muted">({{ $l['role'] }})</span>
                                    <div class="text-muted" style="font-size:11px;margin-top:2px;">{{ $l['date'] }}</div>
                                    @if($l['notes'])
                                    <div style="font-size:12px;margin-top:6px;padding:8px 12px;background:var(--surface-2);border-radius:var(--radius-sm);border:1px solid var(--border);">{{ $l['notes'] }}</div>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>

            </div>

        </div>
        @endif

        <!-- ================= BANK / ACCOUNT STATEMENT ================= -->
        @if($result && $result['type'] === 'account')
        <div id="accountResult">

            <div class="result-header">
                <div>
                    <h2 class="result-title">{{ $result['fund']->name }}{{ $result['fund']->bank ? ' — ' . $result['fund']->bank->name : '' }}</h2>
                    <span class="result-period">Periode: {{ $result['period_label'] }}</span>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="Export akan tersedia setelah modul Journal siap">
                    <i class="fas fa-file-excel mr-1"></i> Export Excel
                </button>
            </div>

            <div class="section-heading">Summary</div>

            <div class="summary-cards-row">
                <div class="stat-tile stat-tile-sm">
                    <div class="stat-icon stat-icon-in"><i class="fas fa-arrow-down"></i></div>
                    <div>
                        <div class="stat-label">Cash in</div>
                        <div class="stat-value stat-positive">{{ number_format($result['cash_in'], 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="stat-tile stat-tile-sm">
                    <div class="stat-icon stat-icon-out"><i class="fas fa-arrow-up"></i></div>
                    <div>
                        <div class="stat-label">Cash out</div>
                        <div class="stat-value stat-negative">{{ number_format($result['cash_out'], 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="stat-tile stat-tile-sm">
                    <div class="stat-icon stat-icon-charge"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div>
                        <div class="stat-label">Charge</div>
                        <div class="stat-value stat-negative">{{ number_format($result['charge'], 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="stat-tile stat-tile-sm">
                    <div class="stat-icon stat-icon-available"><i class="fas fa-wallet"></i></div>
                    <div>
                        <div class="stat-label">Available</div>
                        <div class="stat-value">{{ number_format($result['available'], 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>

            <div class="summary-table-wrap mb-4">
                <table class="table table-sm mb-0 data-table" id="summaryTable">
                    <thead>
                        <tr>
                            <th scope="col" style="width:36px;"></th>
                            <th scope="col">Customer / Purchase order</th>
                            <th scope="col" class="text-right">Cash in</th>
                            <th scope="col" class="text-right">Cash out</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($result['customer_summary'] as $ci => $cust)
                        <tr class="summary-row-customer collapse-toggle" data-target=".summary-po-{{ $ci }}">
                            <td><i class="fas fa-chevron-up chevron-icon chevron-icon-sm"></i></td>
                            <td><i class="fas fa-user mr-2 text-muted"></i>{{ $cust['customer'] }}</td>
                            <td class="text-right stat-positive"><span class="batch-subtotal">{{ number_format($cust['in'], 0, ',', '.') }}</span></td>
                            <td class="text-right stat-negative"><span class="batch-subtotal">{{ number_format($cust['out'], 0, ',', '.') }}</span></td>
                        </tr>
                        @foreach($cust['pos'] as $po)
                        <tr class="summary-row-po summary-po-{{ $ci }} collapse show">
                            <td></td>
                            <td class="pl-4">{{ $po['po'] }}</td>
                            <td class="text-right stat-positive">{{ number_format($po['in'], 0, ',', '.') }}</td>
                            <td class="text-right stat-negative">{{ number_format($po['out'], 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                <i class="fas fa-inbox d-block mb-2"></i> Tidak ada mutasi pada periode ini.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                    @if($result['customer_summary']->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td></td>
                            <td>Grand Total</td>
                            <td class="text-right stat-positive">{{ number_format($result['cash_in'], 0, ',', '.') }}</td>
                            <td class="text-right stat-negative">{{ number_format($result['cash_out'], 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>

            <div class="section-heading">Isi</div>

            <div class="isi-filter-bar">
                <div class="isi-search" style="max-width:320px;">
                    <i class="fas fa-search"></i>
                    <input type="text" id="isiSearch" class="form-control form-control-sm" placeholder="Cari keterangan...">
                </div>
            </div>

            <div class="summary-table-wrap">
                <table class="table table-sm mb-0 data-table" id="isiTable">
                    <thead>
                        <tr>
                            <th scope="col">Tanggal</th>
                            <th scope="col">Sumber</th>
                            <th scope="col">Keterangan</th>
                            <th scope="col" class="text-right">Debit</th>
                            <th scope="col" class="text-right">Kredit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($result['isi_groups'] as $gi => $group)
                            @if($group['type'] === 'batch')
                            <tr class="isi-batch-header collapse-toggle" data-target=".isi-batch-{{ $gi }}">
                                <td colspan="3"><i class="fas fa-chevron-up chevron-icon chevron-icon-sm mr-2"></i>{{ $group['label'] }}</td>
                                <td class="text-right"><span class="batch-subtotal">{{ number_format($group['debit_total'], 0, ',', '.') }}</span></td>
                                <td class="text-right"><span class="batch-subtotal">{{ number_format($group['credit_total'], 0, ',', '.') }}</span></td>
                            </tr>
                            @foreach($group['rows'] as $row)
                            <tr class="isi-child-row isi-batch-{{ $gi }} collapse show">
                                <td>{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}</td>
                                <td><span class="badge badge-{{ $row['badge'] }}">{{ $row['source'] }}</span></td>
                                <td>{{ $row['description'] }}</td>
                                <td class="text-right">{{ $row['debit'] > 0 ? number_format($row['debit'], 0, ',', '.') : '-' }}</td>
                                <td class="text-right">{{ $row['credit'] > 0 ? number_format($row['credit'], 0, ',', '.') : '-' }}</td>
                            </tr>
                            @endforeach
                            @else
                            @php $row = $group['row']; @endphp
                            <tr class="isi-child-row">
                                <td>{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}</td>
                                <td><span class="badge badge-{{ $row['badge'] }}">{{ $row['source'] }}</span></td>
                                <td>{{ $row['description'] }}</td>
                                <td class="text-right">{{ $row['debit'] > 0 ? number_format($row['debit'], 0, ',', '.') : '-' }}</td>
                                <td class="text-right">{{ $row['credit'] > 0 ? number_format($row['credit'], 0, ',', '.') : '-' }}</td>
                            </tr>
                            @endif
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Tidak ada transaksi pada periode ini.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                <p class="hint-text isi-empty d-none" id="isiEmpty">Tidak ada transaksi yang cocok dengan pencarian.</p>
            </div>

        </div>
        @endif

    </div>
</div>

@endsection

@push('styles')
<style>
.reports-page .page-title { margin:0; font-size:20px; font-weight:700; color:var(--text); }

.reports-page .field-label { font-size:12px; font-weight:600; color:var(--text-dim); margin-bottom:4px; }

.reports-page .card-body { padding:20px 22px; }

.reports-page .filter-top-row {
    display:flex; flex-wrap:wrap; align-items:flex-start; gap:28px; margin-bottom:18px;
}

.reports-page .ft-report-type { flex:0 0 340px; max-width:340px; margin-bottom:0; }

.reports-page .filter-row-period {
    display:flex; flex-wrap:wrap; gap:16px; margin-bottom:0;
}
.reports-page .filter-row-period .form-group { margin-bottom:0; width:150px; }
@media (max-width:767px) {
    .reports-page .filter-top-row { flex-direction:column; gap:18px; }
    .reports-page .ft-report-type { flex-basis:auto; max-width:100%; width:100%; }
    .reports-page .filter-row-period .form-group { width:auto; flex:1 1 140px; }
}

.reports-page #poFieldWrap, .reports-page #accountFieldWrap { max-width:640px; margin-bottom:0; }

.reports-page .filter-actions { margin-top:20px; }

.reports-page .btn-generate {
    background:var(--accent); border-color:var(--accent); color:var(--text); font-weight:700;
    font-size:14px; padding:10px 22px;
}
.reports-page .btn-generate:hover { background:var(--warning); border-color:var(--warning); color:var(--text); }

.reports-page .empty-state { text-align:center; color:var(--text-faint); padding:56px 0; }
.reports-page .empty-state i { font-size:36px; margin-bottom:10px; opacity:.5; }
.reports-page .empty-state p { font-size:13px; color:var(--text-faint); }

.reports-page .result-header {
    display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;
    background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-md); padding:14px 18px; margin-bottom:14px;
}
.reports-page .result-title { font-size:16px; font-weight:700; color:var(--text); margin:0; }
.reports-page .result-period { font-size:12px; color:var(--text-faint); }

.reports-page .stat-grid {
    display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:12px;
}
@media (max-width:767px) { .reports-page .stat-grid { grid-template-columns:repeat(2, minmax(0,1fr)); } }

.reports-page .stat-tile {
    background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-md); padding:14px; text-align:center;
}
.reports-page .stat-label { font-size:11px; color:var(--text-faint); text-transform:uppercase; letter-spacing:.03em; margin-bottom:4px; }
.reports-page .stat-value { font-size:18px; font-weight:700; color:var(--text); font-variant-numeric:tabular-nums; }
.reports-page .stat-sub { font-size:11.5px; font-weight:600; color:var(--text-faint); margin-top:2px; }
.reports-page .stat-positive { color:var(--success); }
.reports-page .stat-negative { color:var(--danger); }

.reports-page .stat-tile-icon { display:flex; align-items:center; gap:12px; text-align:left; }
.reports-page .stat-tile-icon .stat-value { font-size:16px; }
.reports-page .stat-icon-po { background:var(--surface-2); color:var(--info); }
.reports-page .stat-icon-budget { background:var(--success-bg); color:var(--success); }
.reports-page .stat-icon-plan { background:var(--info-bg); color:var(--info); }
.reports-page .stat-icon-actual { background:var(--info-bg); color:var(--info); }
.reports-page .stat-icon-variable { background:var(--danger-bg); color:var(--danger); }
.reports-page .stat-icon-fixed { background:var(--warning-bg); color:var(--warning); }
.reports-page .stat-icon-overhead { background:var(--warning-bg); color:var(--warning); }
.reports-page .stat-icon-margin { background:var(--success-bg); color:var(--success); }

.reports-page .coa-row { cursor:pointer; background:var(--surface-2); font-weight:600; }
.reports-page .coa-row:hover { background:var(--info-bg); }
.reports-page .coa-detail-row td { color:var(--text-faint); }
.reports-page .data-table thead th { background:var(--surface-3); color:var(--text-dim); }

.reports-page .section-heading {
    font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em;
    color:var(--text-faint); margin:20px 0 8px;
}
.reports-page .section-heading:first-of-type { margin-top:0; }

.reports-page .summary-table-wrap {
    background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-md); overflow-x:auto;
}
.reports-page .summary-table-wrap .data-table tfoot td {
    font-weight:700; border-top:2px solid var(--border); color:var(--text);
}

.reports-page .summary-cards-row { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
.reports-page .summary-cards-row .stat-tile-sm { flex:1 1 190px; display:flex; align-items:center; gap:12px; }
.reports-page .stat-tile-sm { padding:10px 14px; text-align:left; }
.reports-page .stat-tile-sm .stat-value { font-size:15px; }
.reports-page .stat-icon {
    width:36px; height:36px; border-radius:var(--radius-md); flex:0 0 36px;
    display:flex; align-items:center; justify-content:center; font-size:14px;
}
.reports-page .stat-icon-in { background:var(--success-bg); color:var(--success); }
.reports-page .stat-icon-out { background:var(--danger-bg); color:var(--danger); }
.reports-page .stat-icon-charge { background:var(--warning-bg); color:var(--warning); }
.reports-page .stat-icon-available { background:var(--info-bg); color:var(--info); }

.reports-page .summary-row-customer { cursor:pointer; background:var(--surface-2); font-weight:600; }
.reports-page .summary-row-customer:hover { background:var(--info-bg); }
.reports-page #summaryTable thead th { background:var(--border-strong); color:var(--text); }
.reports-page #summaryTable tfoot td { background:var(--border-strong); }
.reports-page .summary-row-customer td { border-top:1px solid var(--border); }
.reports-page .summary-row-customer .batch-subtotal { display:none; font-variant-numeric:tabular-nums; }
.reports-page .summary-row-customer.collapsed .batch-subtotal { display:inline; }
.reports-page .summary-row-po td { color:var(--text-dim); }
.reports-page .summary-row-po.collapsing td,
.reports-page .summary-row-po:not(.show) td { padding-top:0; padding-bottom:0; }

.reports-page .isi-batch-header { cursor:pointer; background:var(--surface-2); font-weight:600; }
.reports-page .isi-batch-header:hover { background:var(--surface-2); }
.reports-page .isi-batch-header td { border-top:1px solid var(--border); color:var(--text); }
.reports-page .isi-batch-header .batch-subtotal { display:none; font-variant-numeric:tabular-nums; }
.reports-page .isi-batch-header.collapsed .batch-subtotal { display:inline; }
.reports-page .isi-child-row td { color:var(--text-dim); }
.reports-page .isi-child-row td:nth-child(3) { padding-left:24px; }

.reports-page .isi-filter-bar { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:10px; }
.reports-page .isi-search { position:relative; flex:1 1 220px; max-width:320px; }
.reports-page .isi-search i { position:absolute; left:10px; top:50%; transform:translateY(-50%); font-size:11px; color:var(--text-faint); }
.reports-page .isi-search input { padding-left:28px; }
.reports-page .isi-empty { text-align:center; padding:18px 0; }

.reports-page #poResultTabs { border-bottom:2px solid var(--border); }
.reports-page #poResultTabs .nav-link { color:var(--text-faint); border:none; border-bottom:2px solid transparent; border-radius:0; padding:9px 16px; font-size:13px; font-weight:600; }
.reports-page #poResultTabs .nav-link.active { color:var(--accent) !important; border-bottom-color:var(--accent) !important; background:transparent !important; }
.reports-page .report-tab-content { padding-top:14px; }

.reports-page .report-section { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-md); margin-bottom:12px; overflow:hidden; }
.reports-page .report-section-header {
    display:flex; justify-content:space-between; align-items:center;
    padding:11px 16px; cursor:pointer; user-select:none; background:var(--surface-2); border-bottom:1px solid var(--border);
}
.reports-page .report-section-header-static { cursor:default; flex-wrap:wrap; gap:6px; }
.reports-page .report-section-header h3 { margin:0; font-size:13.5px; font-weight:600; color:var(--text); }
.reports-page .report-section-header h3 i { color:var(--text-faint); margin-right:8px; width:14px; text-align:center; }
.reports-page .report-section-body { padding:14px 16px; }
.reports-page .report-section-body.p-0 { padding:0; }
.reports-page .bp-summary-inline { font-size:12px; color:var(--text-dim); }

.reports-page .chevron-icon { transition:transform .18s ease; color:var(--text-faint); font-size:12px; }
.reports-page .chevron-icon-sm { font-size:10px; }
.reports-page .collapse-toggle.collapsed .chevron-icon { transform:rotate(180deg); }

.reports-page .kv-table td { font-size:12.5px; padding:6px 8px; border-top:none; }
.reports-page .kv-table td:first-child { width:170px; color:var(--text-faint); }

.reports-page .data-table { font-size:12.5px; margin-bottom:0; }
.reports-page .data-table thead th {
    font-size:10.5px; text-transform:uppercase; letter-spacing:.02em; color:var(--text-faint);
    font-weight:700; border-top:none; border-bottom:1px solid var(--border); padding:8px;
    background:var(--surface-2);
}
.reports-page .data-table tbody td { padding:8px; vertical-align:middle; color:var(--text); }
.reports-page .data-table td.text-right, .reports-page .data-table th.text-right { font-variant-numeric:tabular-nums; }
.reports-page .hint-text { font-size:11px; color:var(--text-faint); margin:8px 0 0; }

.reports-page .milestone-late-row td { background:var(--danger-bg); }
.reports-page .font-weight-700 { font-weight:700; }

.reports-page .nob-grp-red    { color:var(--danger); font-weight:700; }
.reports-page .nob-grp-yellow { color:var(--accent); font-weight:700; }
.reports-page .nob-grp-green  { color:var(--success); font-weight:700; }
.reports-page .nob-item-red   { color:var(--danger); }
.reports-page .nob-item-green { color:var(--success); }

.reports-page .btn-xs { padding:2px 8px; font-size:11px; border-radius:var(--radius-sm); }

.reports-page .report-pic-title {
    font-size:11px; font-weight:700; letter-spacing:.4px; text-transform:uppercase;
    color:var(--text-faint); margin-bottom:8px;
}
.reports-page .report-pic-card {
    border:1px solid var(--border); border-radius:var(--radius-sm); padding:8px 10px; margin-bottom:8px; font-size:12px;
}
.reports-page .report-pic-name { font-weight:700; color:var(--text); margin-bottom:2px; }
.reports-page .report-pic-meta { color:var(--text-faint); font-size:11.5px; margin-top:1px; }

.reports-page .rep-timeline { position:relative; padding-left:28px; }
.reports-page .rep-timeline::before { content:""; position:absolute; left:9px; top:6px; bottom:6px; width:2px; background:var(--surface-3); }
.reports-page .rep-timeline-item { position:relative; padding-bottom:22px; }
.reports-page .rep-timeline-item:last-child { padding-bottom:0; }
.reports-page .rep-timeline-dot {
    position:absolute; left:-28px; top:2px; width:20px; height:20px; border-radius:50%;
    display:flex; align-items:center; justify-content:center; font-size:9px; color:#fff;
    border:3px solid var(--border); box-shadow:0 0 0 1px var(--border);
}
.reports-page .rep-timeline-body { font-size:12px; }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    var reportType    = document.getElementById('reportType');
    var reportPeriod  = document.getElementById('reportPeriod');
    var reportStart   = document.getElementById('reportStart');
    var reportEnd     = document.getElementById('reportEnd');

    var periodFieldsWrap = document.getElementById('periodFieldsWrap');
    var poFieldWrap       = document.getElementById('poFieldWrap');
    var accountFieldWrap  = document.getElementById('accountFieldWrap');

    function toggleFields() {
        poFieldWrap.classList.add('d-none');
        accountFieldWrap.classList.add('d-none');
        periodFieldsWrap.classList.add('d-none');

        if (reportType.value === 'po') {
            poFieldWrap.classList.remove('d-none');
        } else if (reportType.value === 'account') {
            accountFieldWrap.classList.remove('d-none');
            periodFieldsWrap.classList.remove('d-none');
        }
    }

    reportType.addEventListener('change', toggleFields);
    toggleFields();

    /* Rentang tanggal memakai komponen bersama TesysDateRange (sama seperti
       Cost Request); kotaknya hanya aktif saat periode "Custom". */
    var reportRange = document.getElementById('reportRange');
    if (typeof TesysDateRange === 'object') {
        TesysDateRange.init({ input: '#reportRange', from: '#reportStart', to: '#reportEnd' });
    } else if (reportRange) {
        reportRange.remove();
        reportStart.type = 'date';
        reportEnd.type   = 'date';
        reportStart.classList.add('form-control', 'form-control-sm');
        reportEnd.classList.add('form-control', 'form-control-sm');
    }

    reportPeriod.addEventListener('change', function () {
        var isCustom = reportPeriod.value === 'custom';
        reportStart.disabled = !isCustom;
        reportEnd.disabled = !isCustom;
        if (reportRange) { reportRange.disabled = !isCustom; }
    });

    TesysSearchableSelect.init('#poSelect', { placeholder: '-- Pilih PO --', searchPlaceholder: 'Cari PO...' });
    TesysSearchableSelect.init('#accountSelect', { placeholder: '-- Pilih rekening --', searchPlaceholder: 'Cari rekening...' });

    document.querySelectorAll('.collapse-toggle').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            if (window.jQuery) {
                jQuery(toggle.getAttribute('data-target')).collapse('toggle');
                toggle.classList.toggle('collapsed');
            }
        });
    });

    // ── EXPAND ALL / COLLAPSE ALL — section accordion (Isi) ──
    function setAllCollapse(scopeSelector, expand) {
        document.querySelectorAll(scopeSelector).forEach(function (toggle) {
            if (!window.jQuery) return;
            jQuery(toggle.getAttribute('data-target')).collapse(expand ? 'show' : 'hide');
            toggle.classList.toggle('collapsed', !expand);
        });
    }
    var btnExpandAllSections = document.getElementById('btnExpandAllSections');
    var btnCollapseAllSections = document.getElementById('btnCollapseAllSections');
    if (btnExpandAllSections) {
        btnExpandAllSections.addEventListener('click', function () {
            setAllCollapse('#poResultAccordion > .report-section > .report-section-header.collapse-toggle', true);
        });
    }
    if (btnCollapseAllSections) {
        btnCollapseAllSections.addEventListener('click', function () {
            setAllCollapse('#poResultAccordion > .report-section > .report-section-header.collapse-toggle', false);
        });
    }

    // ── EXPAND ALL / COLLAPSE ALL — nested account group di tab Budget ──
    var btnExpandAllBudget = document.getElementById('btnExpandAllBudget');
    var btnCollapseAllBudget = document.getElementById('btnCollapseAllBudget');
    if (btnExpandAllBudget) {
        btnExpandAllBudget.addEventListener('click', function () {
            setAllCollapse('#accBudget .coa-row.collapse-toggle', true);
        });
    }
    if (btnCollapseAllBudget) {
        btnCollapseAllBudget.addEventListener('click', function () {
            setAllCollapse('#accBudget .coa-row.collapse-toggle', false);
        });
    }

    // Search — tabel Isi (Bank/Account Statement)
    var isiSearch = document.getElementById('isiSearch');
    var isiRows   = document.querySelectorAll('#isiTable tbody tr.isi-child-row');
    var isiEmpty  = document.getElementById('isiEmpty');

    if (isiSearch) {
        isiSearch.addEventListener('input', function () {
            var keyword = isiSearch.value.trim().toLowerCase();
            var visible = 0;
            isiRows.forEach(function (row) {
                var show = !keyword || row.textContent.toLowerCase().indexOf(keyword) !== -1;
                row.classList.toggle('d-none', !show);
                if (show) visible++;
            });
            if (isiEmpty) isiEmpty.classList.toggle('d-none', visible !== 0);
        });
    }
});
</script>
@endpush
