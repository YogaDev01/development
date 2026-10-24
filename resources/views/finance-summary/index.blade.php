@extends('layouts.app')

@section('title', 'Summary')

@section('content')

<div class="content">
    <div class="container-fluid">

        <ul class="nav nav-tabs ts-tabs-sticky mb-3" id="summaryTabs">
            @if($canViewExecutive)
            <li class="nav-item">
                <a class="nav-link {{ $initialTab === 'executive' ? 'active' : '' }}" id="summaryTabExecutiveLink" data-toggle="tab" href="#summaryPaneExecutive">
                    <i class="fas fa-chart-pie mr-1"></i>Summary Executive
                </a>
            </li>
            @endif
            @if($canViewCollection)
            <li class="nav-item">
                <a class="nav-link {{ $initialTab === 'collection' ? 'active' : '' }}" id="summaryTabCollectionLink" data-toggle="tab" href="#summaryPaneCollection">
                    <i class="fas fa-hand-holding-usd mr-1"></i>Summary Collection
                </a>
            </li>
            @endif
            <li class="nav-item">
                <a class="nav-link {{ $initialTab === 'budget' ? 'active' : '' }}" data-toggle="tab" href="#summaryPaneBudget">
                    <i class="fas fa-wallet mr-1"></i>Summary Budget
                </a>
            </li>
        </ul>

        @php
            /* Dipakai tab Executive DAN kartu Active Cash Advance di tab Budget.
               WAJIB di luar @if($canViewExecutive): kalau didefinisikan di dalam
               pane Executive, role tanpa akses tab itu (mis. Head) kena
               "Undefined variable $fmtRpFull" saat kartu CA dirender. */
            $fmtRpFull = fn ($v) => number_format($v, 0, ',', '.');
        @endphp

        <div class="tab-content" id="summaryTabContent">

            @if($canViewExecutive)
            <div class="tab-pane fade {{ $initialTab === 'executive' ? 'show active' : '' }}" id="summaryPaneExecutive">

                <div id="execChartData"
                     data-status="{{ json_encode($statusChart) }}"
                     data-progress="{{ json_encode($progressChart) }}"
                     data-customers="{{ json_encode($topCustomers) }}"
                     data-revenue="{{ json_encode($revenueChart) }}"
                     data-cashin="{{ json_encode($cashInChart) }}"
                     data-cashout="{{ json_encode($cashOutChart) }}"
                     data-revenue-breakdown="{{ json_encode($revenueBreakdown) }}"
                     data-cashin-breakdown="{{ json_encode($cashInBreakdown) }}"
                     data-cashout-breakdown="{{ json_encode($cashOutBreakdown) }}"
                     style="display:none;"></div>

                {{-- 6 KPI CARD — Contract -> Released PO -> Invoice -> Paid (Minor 1.1) --}}
                <div class="row col-kpi-row">
                    <div class="col-6 col-md-2 col-kpi-col">
                        <a href="{{ url('/project') }}" target="_blank" class="card shadow-sm col-kpi-card col-kpi-clickable col-kpi-accent-blue" title="Lihat semua PO">
                            <div class="col-kpi-head">
                                <span class="col-kpi-icon col-kpi-icon-blue"><i class="fas fa-file-contract"></i></span>
                                <span class="col-kpi-label">Total Contract</span>
                            </div>
                            <div class="col-kpi-value" style="color:var(--info);">{{ $fmtRpFull($kpiExecutive['total_contract']) }}</div>
                        </a>
                    </div>
                    <div class="col-6 col-md-2 col-kpi-col">
                        <a href="{{ url('/project') }}" target="_blank" class="card shadow-sm col-kpi-card col-kpi-clickable col-kpi-accent-green" title="PO yang sudah rilis nominal (No. PO terisi)">
                            <div class="col-kpi-head">
                                <span class="col-kpi-icon col-kpi-icon-green"><i class="fas fa-check-circle"></i></span>
                                <span class="col-kpi-label">Released PO</span>
                            </div>
                            <div class="col-kpi-value" style="color:var(--success);">{{ $fmtRpFull($kpiExecutive['released_po']) }}</div>
                        </a>
                    </div>
                    <div class="col-6 col-md-2 col-kpi-col">
                        <a href="{{ url('/project?status=WAITING_PO') }}" target="_blank" class="card shadow-sm col-kpi-card col-kpi-clickable col-kpi-accent-warn" title="Contract - Released PO">
                            <div class="col-kpi-head">
                                <span class="col-kpi-icon col-kpi-icon-warn"><i class="fas fa-hourglass-half"></i></span>
                                <span class="col-kpi-label">Waiting PO</span>
                            </div>
                            <div class="col-kpi-value" style="color:var(--warning);">{{ $fmtRpFull($kpiExecutive['waiting_po']) }}</div>
                        </a>
                    </div>
                    <div class="col-6 col-md-2 col-kpi-col">
                        <a href="{{ url('/invoice-cash-receipt') }}" target="_blank" class="card shadow-sm col-kpi-card col-kpi-clickable col-kpi-accent-purple" title="Total invoice (exclude Draft/Cancelled)">
                            <div class="col-kpi-head">
                                <span class="col-kpi-icon col-kpi-icon-purple"><i class="fas fa-file-invoice"></i></span>
                                <span class="col-kpi-label">Total Invoiced</span>
                            </div>
                            <div class="col-kpi-value" style="color:var(--purple);">{{ $fmtRpFull($kpiExecutive['total_invoiced']) }}</div>
                        </a>
                    </div>
                    <div class="col-6 col-md-2 col-kpi-col">
                        <a href="{{ url('/invoice-cash-receipt?status=PAID') }}" target="_blank" class="card shadow-sm col-kpi-card col-kpi-clickable col-kpi-accent-good" title="Invoice yang sudah lunas">
                            <div class="col-kpi-head">
                                <span class="col-kpi-icon col-kpi-icon-good"><i class="fas fa-hand-holding-usd"></i></span>
                                <span class="col-kpi-label">Total Paid</span>
                            </div>
                            <div class="col-kpi-value" style="color:var(--success);">{{ $fmtRpFull($kpiExecutive['total_paid']) }}</div>
                        </a>
                    </div>
                    <div class="col-6 col-md-2 col-kpi-col">
                        <a href="{{ url('/invoice-cash-receipt?status=SENT') }}" target="_blank" class="card shadow-sm col-kpi-card col-kpi-clickable col-kpi-accent-bad" title="Total Invoiced - Total Paid">
                            <div class="col-kpi-head">
                                <span class="col-kpi-icon col-kpi-icon-bad"><i class="fas fa-exclamation-triangle"></i></span>
                                <span class="col-kpi-label">Total Unpaid</span>
                            </div>
                            <div class="col-kpi-value" style="color:var(--danger);">{{ $fmtRpFull($kpiExecutive['total_unpaid']) }}</div>
                        </a>
                    </div>
                </div>

                {{-- PROGRESS INDICATOR Contract -> PO -> Invoice -> Paid --}}
                <div class="card shadow-sm mb-3" style="padding:14px 18px;">
                    <div class="d-flex justify-content-between mb-1" style="font-size:11px;font-weight:600;color:var(--text-faint);">
                        <span>Contract</span><span>Released PO ({{ $kpiExecutive['pct_po'] }}%)</span><span>Invoiced ({{ $kpiExecutive['pct_invoice'] }}%)</span><span>Paid ({{ $kpiExecutive['pct_paid'] }}%)</span>
                    </div>
                    <div class="col-kpi-track" style="height:10px;position:relative;background:var(--surface-3);">
                        <div class="col-kpi-fill col-kpi-fill-purple js-pct-width" data-width="{{ $kpiExecutive['pct_invoice'] }}" style="position:absolute;height:100%;opacity:.45;"></div>
                        <div class="col-kpi-fill col-kpi-fill-good js-pct-width" data-width="{{ $kpiExecutive['pct_paid'] }}" style="position:absolute;height:100%;"></div>
                    </div>
                </div>

                <div class="row exec-chart-row">
                    <div class="col-md-7 exec-chart-col">
                        <div class="card shadow-sm exec-chart-card">
                            <div class="card-header d-flex align-items-center">
                                <h3 class="card-title" style="font-size:13px;font-weight:700;"><i class="fas fa-chart-line mr-2" style="color:var(--info);"></i>Revenue Value</h3>
                                <select id="selRevenueYear" class="form-control form-control-sm ml-auto" style="max-width:110px;font-size:11px;"></select>
                            </div>
                            <div class="card-body">
                                <div class="exec-chart-wrap"><canvas id="chartRevenue"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5 exec-chart-col">
                        <div class="card shadow-sm exec-chart-card">
                            <div class="card-header d-flex align-items-center">
                                <h3 class="card-title" style="font-size:13px;font-weight:700;"><i class="fas fa-exchange-alt mr-2" style="color:var(--success);"></i>Cash Receipt vs Cash Out</h3>
                                <select id="selTreasuryYear" class="form-control form-control-sm ml-auto" style="max-width:110px;font-size:11px;"></select>
                            </div>
                            <div class="card-body">
                                <div class="exec-chart-wrap"><canvas id="chartTreasury"></canvas></div>
                                <div id="legendTreasury" class="exec-legend"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row exec-chart-row">
                    <div class="col-md-4 exec-chart-col">
                        <div class="card shadow-sm exec-chart-card">
                            <div class="card-header d-flex align-items-center">
                                <h3 class="card-title" style="font-size:13px;font-weight:700;"><i class="fas fa-chart-pie mr-2" style="color:var(--warning);"></i>Distribusi Status PO (Nilai)</h3>
                                <div class="ml-auto form-check" style="font-size:11px;">
                                    <input type="checkbox" class="form-check-input" id="chkShowClosedChart">
                                    <label class="form-check-label" for="chkShowClosedChart" style="cursor:pointer;">Tampilkan Closed</label>
                                </div>
                            </div>
                            <div class="card-body">
                                <canvas id="chartStatus" height="180"></canvas>
                                <div id="legendStatus" class="exec-legend"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 exec-chart-col">
                        <div class="card shadow-sm exec-chart-card">
                            <div class="card-header">
                                <h3 class="card-title" style="font-size:13px;font-weight:700;"><i class="fas fa-tasks mr-2" style="color:var(--purple);"></i>Progress Dokumen (R / B / I / P)</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="chartProgress" height="180"></canvas>
                                <div id="legendProgress" class="exec-legend"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 exec-chart-col">
                        <div class="card shadow-sm exec-chart-card">
                            <div class="card-header d-flex align-items-center">
                                <h3 class="card-title" style="font-size:13px;font-weight:700;"><i class="fas fa-building mr-2" style="color:#0d9488;"></i>Top Customer</h3>
                                <select id="selCustomerMetric" class="form-control form-control-sm ml-auto" style="max-width:150px;font-size:11px;">
                                    <option value="po_value">Nilai PO</option>
                                    <option value="gross_profit">Gross Profit</option>
                                    <option value="project_profit">Project Profit</option>
                                    <option value="outstanding">Outstanding</option>
                                </select>
                            </div>
                            <div class="card-body">
                                <canvas id="chartCustomers" height="180"></canvas>
                                <div id="legendCustomers" class="exec-legend"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title"><i class="fas fa-chart-line mr-1" style="color:var(--info);"></i> Rollup Purchase Order</h3>
                        <div class="ml-auto d-flex" style="gap:8px;">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="location.reload()" title="Refresh">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>

                    <div class="card-body">

                        <div class="d-flex align-items-center flex-wrap mb-3" style="gap:8px;">
                            <span class="font-weight-600 mr-1" style="font-size:13px;">Cari :</span>
                            <input type="search" id="execSearchInput" class="form-control form-control-sm" style="max-width:220px;" placeholder="Cari data...">
                            <button aria-label="Clear Filter" type="button" id="btnClearExecFilter" class="btn btn-outline-secondary btn-sm" title="Clear Filter">
                                <i class="fas fa-eraser"></i>
                            </button>
                            <button type="button" id="btnFilterStatus" class="btn btn-sm btn-outline-secondary tcf-row-btn">Status <i class="fas fa-caret-down ml-1"></i></button>
                            <button type="button" id="btnFilterPm" class="btn btn-sm btn-outline-secondary tcf-row-btn">Manager <i class="fas fa-caret-down ml-1"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary tcf-row-btn exec-progress-filter-btn" data-letter="r">PO Received <i class="fas fa-caret-down ml-1"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary tcf-row-btn exec-progress-filter-btn" data-letter="b">BAST <i class="fas fa-caret-down ml-1"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary tcf-row-btn exec-progress-filter-btn" data-letter="i">Invoice <i class="fas fa-caret-down ml-1"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary tcf-row-btn exec-progress-filter-btn" data-letter="p">Payment <i class="fas fa-caret-down ml-1"></i></button>
                        </div>

                        <div class="table-responsive exec-hscroll">
                            {{-- Tabel pivot (baris grup customer berisi angka per kolom): di HP tetap tabel bergulir, bukan card. --}}
                            <table id="tblExecutive" data-ts-card="off" class="table table-bordered mb-0" style="font-size:12.5px;">
                                <thead class="bg-light">
                                    <tr>
                                        <th scope="col" class="text-nowrap exec-col-kode">Kode</th>
                                        <th scope="col" class="text-nowrap">Scope of Work</th>
                                        <th scope="col" class="text-center text-nowrap">PO Status</th>
                                        <th scope="col" class="text-center text-nowrap">Implement</th>
                                        <th scope="col" class="text-center text-nowrap">Progress</th>
                                        <th scope="col" class="text-center text-nowrap">Margin</th>
                                        <th scope="col" class="text-right text-nowrap">Contract</th>
                                        <th scope="col" class="text-right text-nowrap">Value</th>
                                        <th scope="col" class="text-right text-nowrap">Budget</th>
                                        <th scope="col" class="text-right text-nowrap">Cost</th>
                                        <th scope="col" class="text-right text-nowrap">Received</th>
                                        <th scope="col" class="text-right text-nowrap">Gross Profit</th>
                                        <th scope="col" class="text-right text-nowrap">Overhead</th>
                                        <th scope="col" class="text-right text-nowrap">Fixed Cost</th>
                                        <th scope="col" class="text-right text-nowrap">Net Profit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $monColor = ['NOT_YET' => 'secondary', 'ON_GOING' => 'warning', 'DONE' => 'success'];
                                        $monLabels = ['NOT_YET' => 'Not Yet', 'ON_GOING' => 'On Going', 'DONE' => 'Done'];
                                        $monBucket = ['NOT_YET' => 'Not Started', 'ON_GOING' => 'Partial', 'DONE' => 'Done'];
                                        $marginColor = fn ($v) => $v >= 20 ? 'success' : ($v >= 0 ? 'warning' : 'danger');
                                        $pmCalc = fn ($budget, $realization) => $budget > 0 ? round((($budget - $realization) / $budget) * 100, 1) : 0.0;
                                        $gmCalc = fn ($value, $budget) => $value > 0 ? round((($value - $budget) / $value) * 100, 1) : 0.0;
                                        $grouped = collect($rows)->groupBy('customer');

                                        $grandValue = collect($rows)->sum('po_value');
                                        $grandContract = collect($rows)->sum('contract');
                                        $grandBudget = collect($rows)->sum('budget_value');
                                        $grandRealization = collect($rows)->sum('realization_value');
                                        $grandPayment = collect($rows)->sum('payment_value');
                                        $grandOverhead = collect($rows)->sum('overhead');
                                        $grandFixedCost = collect($rows)->sum('fixed_cost');
                                        $grandGrossProfit = $grandPayment - $grandRealization;
                                        $grandNetProfit = $grandGrossProfit - $grandOverhead - $grandFixedCost;
                                        $grandPm = $pmCalc($grandBudget, $grandRealization);
                                        $grandGm = $gmCalc($grandValue, $grandBudget);
                                    @endphp
                                    @forelse($grouped as $customerName => $customerRows)
                                    @php
                                        $pmList = $customerRows->pluck('pm_internal')->filter(fn ($v) => $v && $v !== '-')->unique()->map(fn ($v) => strtoupper($v))->implode(', ') ?: '-';

                                        $grpValue = $customerRows->sum('po_value');
                                        $grpContract = $customerRows->sum('contract');
                                        $grpBudget = $customerRows->sum('budget_value');
                                        $grpRealization = $customerRows->sum('realization_value');
                                        $grpPayment = $customerRows->sum('payment_value');
                                        $grpOverhead = $customerRows->sum('overhead');
                                        $grpFixedCost = $customerRows->sum('fixed_cost');
                                        $grpGrossProfit = $grpPayment - $grpRealization;
                                        $grpNetProfit = $grpGrossProfit - $grpOverhead - $grpFixedCost;
                                        $grpPm = $pmCalc($grpBudget, $grpRealization);
                                        $grpGm = $gmCalc($grpValue, $grpBudget);
                                    @endphp
                                    <tr class="exec-group-row" data-target="exec-grp-{{ $loop->index }}">
                                        <td colspan="5">
                                            <i class="fas fa-chevron-right exec-caret mr-2"></i>
                                            <strong>{{ $customerName }}</strong>
                                            <span class="text-muted" style="font-size:11px;">(<span class="exec-po-count">{{ $customerRows->count() }}</span> PO &middot; Manager: <span class="exec-pm-list">{{ $pmList }}</span>)</span>
                                        </td>
                                        <td class="text-center text-nowrap exec-header-subtotal exec-margin-cell">
                                            <div><span class="exec-margin-label">PM</span> <span class="exec-cell-pm text-{{ $marginColor($grpPm) }}">{{ rtrim(rtrim(number_format($grpPm, 1, ',', '.'), '0'), ',') }}%</span></div>
                                            <div><span class="exec-margin-label">GM</span> <span class="exec-cell-gm text-{{ $marginColor($grpGm) }}">{{ rtrim(rtrim(number_format($grpGm, 1, ',', '.'), '0'), ',') }}%</span></div>
                                        </td>
                                        <td class="text-right text-nowrap exec-header-subtotal exec-cell-contract">{{ number_format($grpContract, 0, ',', '.') }}</td>
                                        <td class="text-right text-nowrap exec-header-subtotal exec-cell-value">{{ number_format($grpValue, 0, ',', '.') }}</td>
                                        <td class="text-right text-nowrap exec-header-subtotal exec-cell-budget">{{ number_format($grpBudget, 0, ',', '.') }}</td>
                                        <td class="text-right text-nowrap exec-header-subtotal exec-cell-realization">{{ number_format($grpRealization, 0, ',', '.') }}</td>
                                        <td class="text-right text-nowrap exec-header-subtotal exec-cell-payment">{{ number_format($grpPayment, 0, ',', '.') }}</td>
                                        <td class="text-right text-nowrap exec-header-subtotal exec-cell-grossprofit {{ $grpGrossProfit < 0 ? 'text-danger' : '' }}">{{ $grpGrossProfit < 0 ? '-' : '' }}{{ number_format(abs($grpGrossProfit), 0, ',', '.') }}</td>
                                        <td class="text-right text-nowrap exec-header-subtotal exec-cell-overhead">{{ number_format($grpOverhead, 0, ',', '.') }}</td>
                                        <td class="text-right text-nowrap exec-header-subtotal exec-cell-fixedcost">{{ number_format($grpFixedCost, 0, ',', '.') }}</td>
                                        <td class="text-right text-nowrap exec-header-subtotal exec-cell-netprofit {{ $grpNetProfit < 0 ? 'text-danger' : '' }}">{{ $grpNetProfit < 0 ? '-' : '' }}{{ number_format(abs($grpNetProfit), 0, ',', '.') }}</td>
                                    </tr>
                                    @foreach($customerRows as $r)
                                    @php
                                        $bucketR = $monBucket[$r['po_received_status']] ?? 'Not Started';
                                        $bucketB = $r['work_badge']['label'];
                                        $bucketI = $r['billing_badge']['label'];
                                        $bucketP = $r['payment_badge']['label'];
                                    @endphp
                                    @php
                                        $rowGrossProfit = $r['payment_value'] - $r['realization_value'];
                                        $rowNetProfit = $rowGrossProfit - $r['overhead'] - $r['fixed_cost'];
                                    @endphp
                                    <tr class="exec-grp-{{ $loop->parent->index }} exec-data-row" data-status="{{ $r['status'] }}"
                                        data-progress-r="{{ $bucketR }}" data-progress-b="{{ $bucketB }}"
                                        data-progress-i="{{ $bucketI }}" data-progress-p="{{ $bucketP }}"
                                        data-pm-internal="{{ $r['pm_internal'] }}"
                                        data-value="{{ $r['po_value'] }}" data-contract="{{ $r['contract'] }}" data-budget="{{ $r['budget_value'] }}"
                                        data-realization="{{ $r['realization_value'] }}" data-payment="{{ $r['payment_value'] }}"
                                        data-overhead="{{ $r['overhead'] }}" data-fixedcost="{{ $r['fixed_cost'] }}"
                                        style="display:none;">
                                        <td class="exec-indent font-weight-600 exec-col-kode">
                                            <a href="{{ route('project.index') }}?open={{ $r['id'] }}" target="_blank" rel="noopener" class="exec-code-link" title="Lihat Detail PO">{{ $r['code'] }}</a>
                                        </td>
                                        <td class="text-nowrap">{{ $r['name'] }}</td>
                                        <td class="text-center text-nowrap">
                                            <span class="badge badge-{{ $r['status_color'] }} px-2 py-1">{{ $r['status_label'] }}</span>
                                        </td>
                                        <td class="text-center text-nowrap">
                                            <a href="{{ route('project-monitoring.show', $r['id']) }}" target="_blank" rel="noopener" class="exec-implement-link" title="Lihat Project Monitoring">{{ rtrim(rtrim(number_format($r['implementation'], 1, ',', '.'), '0'), ',') }}%</a>
                                        </td>
                                        <td class="text-center text-nowrap">
                                            <span class="badge badge-{{ $monColor[$r['po_received_status']] ?? 'secondary' }} po-mini-badge" title="Progress PO Received: {{ $monLabels[$r['po_received_status']] ?? '-' }}">R</span>
                                            <span class="badge badge-{{ $r['work_badge']['color'] }} po-mini-badge border border-{{ $monColor[$r['bast_status']] ?? 'secondary' }}" title="Uploaded BAST: {{ $r['work_badge']['label'] }} &middot; Progress BAST: {{ $monLabels[$r['bast_status']] ?? '-' }}">B</span>
                                            <span class="badge badge-{{ $r['billing_badge']['color'] }} po-mini-badge border border-{{ $monColor[$r['invoice_status']] ?? 'secondary' }}" title="Uploaded Invoice: {{ $r['billing_badge']['label'] }} &middot; Progress Invoice: {{ $monLabels[$r['invoice_status']] ?? '-' }}">I</span>
                                            <span class="badge badge-{{ $r['payment_badge']['color'] }} po-mini-badge border border-{{ $monColor[$r['payment_status']] ?? 'secondary' }}" title="Uploaded Payment: {{ $r['payment_badge']['label'] }} &middot; Progress Payment: {{ $monLabels[$r['payment_status']] ?? '-' }}">P</span>
                                        </td>
                                        <td class="text-nowrap exec-margin-cell">
                                            <div><span class="exec-margin-label">PM</span> <span class="text-{{ $marginColor($r['project_margin']) }}">{{ rtrim(rtrim(number_format($r['project_margin'], 1, ',', '.'), '0'), ',') }}%</span></div>
                                            <div><span class="exec-margin-label">GM</span> <span class="text-{{ $marginColor($r['plan_gross_margin']) }}">{{ rtrim(rtrim(number_format($r['plan_gross_margin'], 1, ',', '.'), '0'), ',') }}%</span></div>
                                        </td>
                                        <td class="text-right text-nowrap">{{ number_format($r['contract'], 0, ',', '.') }}</td>
                                        <td class="text-right text-nowrap">{{ number_format($r['po_value'], 0, ',', '.') }}</td>
                                        <td class="text-right text-nowrap">{{ number_format($r['budget_value'], 0, ',', '.') }}</td>
                                        <td class="text-right text-nowrap">{{ number_format($r['realization_value'], 0, ',', '.') }}</td>
                                        <td class="text-right text-nowrap">{{ number_format($r['payment_value'], 0, ',', '.') }}</td>
                                        <td class="text-right text-nowrap {{ $rowGrossProfit < 0 ? 'text-danger' : '' }}">{{ $rowGrossProfit < 0 ? '-' : '' }}{{ number_format(abs($rowGrossProfit), 0, ',', '.') }}</td>
                                        <td class="text-right text-nowrap">{{ number_format($r['overhead'], 0, ',', '.') }}</td>
                                        <td class="text-right text-nowrap">{{ number_format($r['fixed_cost'], 0, ',', '.') }}</td>
                                        <td class="text-right text-nowrap {{ $rowNetProfit < 0 ? 'text-danger' : '' }}">{{ $rowNetProfit < 0 ? '-' : '' }}{{ number_format(abs($rowNetProfit), 0, ',', '.') }}</td>
                                    </tr>
                                    @endforeach
                                    @empty
                                    <tr>
                                <td colspan="15">
                                    <x-empty title="Belum ada Purchase Order." icon="fas fa-folder-open" compact />
                                </td>
                            </tr>
                                    @endforelse
                                </tbody>
                                @if($rows->count())
                                <tfoot>
                                    <tr class="exec-grand-total">
                                        <td colspan="5" class="text-right font-weight-bold" id="grandTotalLabel">GRAND TOTAL</td>
                                        <td class="text-center exec-margin-cell">
                                            <div><span class="exec-margin-label">PM</span> <span id="grandTotalPm" class="text-{{ $marginColor($grandPm) }} font-weight-bold">{{ rtrim(rtrim(number_format($grandPm, 1, ',', '.'), '0'), ',') }}%</span></div>
                                            <div><span class="exec-margin-label">GM</span> <span id="grandTotalGm" class="text-{{ $marginColor($grandGm) }} font-weight-bold">{{ rtrim(rtrim(number_format($grandGm, 1, ',', '.'), '0'), ',') }}%</span></div>
                                        </td>
                                        <td class="text-right font-weight-bold" id="grandTotalContract">{{ number_format($grandContract, 0, ',', '.') }}</td>
                                        <td class="text-right font-weight-bold" id="grandTotalValue">{{ number_format($grandValue, 0, ',', '.') }}</td>
                                        <td class="text-right font-weight-bold" id="grandTotalBudget">{{ number_format($grandBudget, 0, ',', '.') }}</td>
                                        <td class="text-right font-weight-bold" id="grandTotalRealization">{{ number_format($grandRealization, 0, ',', '.') }}</td>
                                        <td class="text-right font-weight-bold" id="grandTotalPayment">{{ number_format($grandPayment, 0, ',', '.') }}</td>
                                        <td class="text-right font-weight-bold {{ $grandGrossProfit < 0 ? 'text-danger' : '' }}" id="grandTotalGrossProfit">{{ $grandGrossProfit < 0 ? '-' : '' }}{{ number_format(abs($grandGrossProfit), 0, ',', '.') }}</td>
                                        <td class="text-right font-weight-bold" id="grandTotalOverhead">{{ number_format($grandOverhead, 0, ',', '.') }}</td>
                                        <td class="text-right font-weight-bold" id="grandTotalFixedCost">{{ number_format($grandFixedCost, 0, ',', '.') }}</td>
                                        <td class="text-right font-weight-bold {{ $grandNetProfit < 0 ? 'text-danger' : '' }}" id="grandTotalNetProfit">{{ $grandNetProfit < 0 ? '-' : '' }}{{ number_format(abs($grandNetProfit), 0, ',', '.') }}</td>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>

            </div>
            @endif

            <div class="tab-pane fade {{ $initialTab === 'budget' ? 'show active' : '' }}" id="summaryPaneBudget">

                {{-- KPI CARDS --}}
                @php $fmtRpFullBp = fn ($v) => number_format($v, 0, ',', '.'); @endphp
                <div class="row col-kpi-row">
                    <div class="col-6 col-md-4 col-kpi-col">
                        <div class="card shadow-sm col-kpi-card col-kpi-accent-blue">
                            <div class="col-kpi-head">
                                <span class="col-kpi-icon col-kpi-icon-blue"><i class="fas fa-wallet"></i></span>
                                <span class="col-kpi-label">Total Budget Plan</span>
                            </div>
                            <div class="col-kpi-value" style="color:var(--info);">{{ $fmtRpFullBp($bp['total_amount']) }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-kpi-col">
                        <div class="card shadow-sm col-kpi-card col-kpi-accent-warn">
                            <div class="col-kpi-head">
                                <span class="col-kpi-icon col-kpi-icon-warn"><i class="fas fa-file-invoice-dollar"></i></span>
                                <span class="col-kpi-label">Total Cost Request</span>
                            </div>
                            <div class="col-kpi-value" style="color:var(--warning);">{{ $fmtRpFullBp($cr['total_amount']) }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-kpi-col">
                        <div class="card shadow-sm col-kpi-card col-kpi-accent-good">
                            <div class="col-kpi-head">
                                <span class="col-kpi-icon col-kpi-icon-good"><i class="fas fa-check-circle"></i></span>
                                <span class="col-kpi-label">Total Realisasi</span>
                            </div>
                            <div class="col-kpi-value" style="color:var(--success);">{{ $fmtRpFullBp($cr['realized_amount']) }}</div>
                        </div>
                    </div>
                </div>

                @php
                    /*
                    | Penampil kolom Aging. Diberi warna, bukan cuma angka:
                    | tabel ini dibaca untuk mencari CA yang perlu dikejar, dan
                    | 60 hari harus terlihat berbeda dari 3 hari tanpa dibaca
                    | satu per satu. Ambangnya lokal di sini - tidak ada aturan
                    | SLA settlement resmi di aplikasi yang bisa dirujuk.
                    */
                    /*
                    | Tautan nilai CA -> halaman Cost Request dengan filternya
                    | sudah terpasang. Rentang tanggal dibawa dari baris yang
                    | diklik (CR tertua di grup itu) karena halaman tujuan
                    | membuka dirinya pada bulan berjalan saja.
                    */
                    $acaCrUrl = function (array $param, ?string $dateFrom) use ($acaLinkStatuses, $acaLinkType) {
                        return route('budget-request.index', array_filter(array_merge($param, [
                            'date_from' => $dateFrom,
                            'date_to'   => now()->format('Y-m-d'),
                            // Himpunannya dipersempit persis seperti kartu ini:
                            // tipe Cash Advance, status yang masih berjalan.
                            'statuses'  => $acaLinkStatuses,
                            'type'      => $acaLinkType,
                        ])));
                    };

                    $acaAging = function (?int $hari) {
                        if ($hari === null) return '<span class="text-muted">-</span>';
                        // Lewat 7 hari = merah. Ambangnya lokal di sini - tidak ada
                        // aturan SLA settlement resmi di aplikasi yang bisa dirujuk.
                        $lewat = $hari > 7;
                        return '<span style="color:' . ($lewat ? 'var(--danger)' : 'var(--muted, #6c757d)')
                            . ';font-weight:' . ($lewat ? '700' : '400') . ';">' . $hari . ' hari</span>';
                    };
                @endphp

                {{-- ACTIVE CASH ADVANCE (Minor 1.2) — grouped per Manager, expand/collapse per
                     Beneficiary. Datanya dihitung controller untuk SEMUA role (lihat index()),
                     jadi card ini tidak digerbang role apa pun. --}}
                <div class="card shadow-sm mb-3">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title" style="font-size:13px;font-weight:700;"><i class="fas fa-money-check-alt mr-2" style="color:var(--purple);"></i>Active Cash Advance</h3>
                        <span class="badge badge-secondary ml-2">{{ $activeCaGrandQty }}</span>
                        <span class="text-muted ml-2" style="font-size:10px;">Manager &rsaquo; PIC &rsaquo; Cost Request &mdash; Paid / Settle Checked / Settle Verified</span>

                        {{-- Filter duduk sebaris dengan "Buka Semua": keduanya kendali
                             tabel ini, jadi tidak ada alasan menaruhnya di baris sendiri
                             yang menyita tinggi kartu.

                             Menyaring TIDAK memuat ulang halaman. Konsekuensinya subtotal
                             PIC, Manager, dan Grand Total harus dihitung ulang di browser
                             dari baris yang masih terlihat — kalau tidak, angkanya tetap
                             menghitung baris yang sudah disembunyikan dan berhenti cocok
                             dengan isinya. --}}
                        <div id="acaFilterBar" class="d-flex flex-wrap align-items-center ml-auto" style="gap:6px;">
                            @include('finance-summary._multi-filter', [
                                'name'     => 'aca_po',
                                'label'    => 'PO',
                                'options'  => $acaPoOptions,
                                'selected' => [],
                            ])

                            @include('finance-summary._multi-filter', [
                                'name'     => 'aca_pic',
                                'label'    => 'PIC',
                                'options'  => $acaPicOptions,
                                'selected' => [],
                            ])

                            @include('finance-summary._multi-filter', [
                                'name'     => 'aca_aging',
                                'label'    => 'Aging',
                                'options'  => $acaAgingOptions,
                                'selected' => [],
                                'search'   => false,
                            ])

                            {{-- Selalu tampil, tidak muncul-hilang mengikuti ada tidaknya
                                 filter: tombol yang timbul-tenggelam menggeser posisi
                                 "Buka Semua" di sebelahnya tiap kali filter dipasang atau
                                 dilepas, jadi sasaran klik berpindah justru saat sedang
                                 dipakai berturut-turut. --}}
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="acaFilterReset"
                                    title="Bersihkan filter" aria-label="Bersihkan filter">
                                <i class="fas fa-eraser"></i>
                            </button>
                        </div>

                        <button type="button" class="btn btn-outline-secondary btn-sm ml-2" id="acaExpandAll" style="font-size:11px;">
                            <i class="fas fa-angle-double-down mr-1"></i>Buka Semua
                        </button>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            {{-- col-pic-table = sistem gaya tabel matriks halaman ini (dipakai Summary
                                 Progress, PO Summary, Collection Performance): header pita
                                 berwarna per grup ukuran, sub-header Qty/Value, dan sel data
                                 bertint senada. Dipakai ulang, bukan disalin, supaya satu
                                 perubahan gaya berlaku untuk semua tabel itu. --}}
                            <table class="table table-sm mb-0 col-pic-table aca-fixed" id="tblAca">
                                {{-- Lebar kolom ditetapkan di colgroup, bukan di <th>.
                                     table-layout:fixed hanya membaca lebar dari BARIS PERTAMA
                                     header — sejak QTY & Value turun ke baris kedua, lebarnya
                                     tidak akan terbaca lagi dan dua kolom itu kembali melar
                                     mengikuti isi. colgroup berlaku untuk kolomnya, bukan
                                     barisnya, jadi susunan header bebas berubah. --}}
                                <colgroup>
                                    <col>                      {{-- Manager / PIC / Cost Request --}}
                                    <col style="width:200px;"> {{-- PO Reference --}}
                                    <col style="width:90px;">  {{-- Aging --}}
                                    <col style="width:120px;"> {{-- Tanggal --}}
                                    <col style="width:140px;"> {{-- Action --}}
                                    <col style="width:90px;">  {{-- QTY --}}
                                    <col style="width:160px;"> {{-- Value --}}
                                </colgroup>
                                <thead class="bg-light">
                                    <tr>
                                        {{-- KOLOM YANG BISA DIURUTKAN.
                                             Tombol, bukan tautan: mengurutkan tidak memuat ulang
                                             halaman. Kalau memuat ulang, manager yang sedang
                                             dibuka akan menutup kembali setiap kali diklik,
                                             sehingga membandingkan dua urutan justru jadi lambat
                                             — padahal itu satu-satunya gunanya.

                                             Penyortirannya ada di JS bagian bawah berkas ini,
                                             dan sengaja BUKAN DataTables: tabel ini pohon tiga
                                             tingkat, sedangkan DataTables memperlakukan tiap <tr>
                                             sebagai daftar datar — baris anak akan lepas dari
                                             induknya dan subtotal per manager berhenti bermakna.

                                             Klik pertama memberi urutan yang paling sering dicari
                                             lebih dulu (nama A→Z, nilai terbesar), klik berikutnya
                                             membalik. --}}
                                        <th scope="col" rowspan="2" class="text-left aca-head-plain">
                                            <button type="button" class="aca-sort" data-aca-sort="name"
                                                    title="Urutkan menurut nama PIC">
                                                Manager / PIC / Cost Request
                                                <i class="fas fa-sort aca-sort-ic" aria-hidden="true"></i>
                                            </button>
                                        </th>

                                        {{-- AGING & TANGGAL.
                                             "Tanggal" = kapan CA dibayar (paid_at), "Aging" =
                                             sudah berapa hari sejak itu dan belum tuntas.
                                             Keduanya TIDAK bisa diurutkan lewat header: penyortir
                                             di bawah cuma mengenal satu pasang data-sort-*, dan
                                             urutan umur sudah terwakili - aging berbanding lurus
                                             dengan tanggal bayar. --}}
                                        {{-- PO Reference = PO yang membiayai CA ini, lewat Budget
                                             Plan-nya. Hanya terisi di lapis CR (lihat controller). --}}
                                        <th scope="col" rowspan="2" class="text-left aca-head-plain">PO Reference</th>

                                        <th scope="col" rowspan="2" class="text-center aca-head-plain">Aging</th>
                                        <th scope="col" rowspan="2" class="text-center aca-head-plain">Tanggal Paid</th>

                                        {{-- ACTION = posisi dokumen di alur Cost Request
                                             (Paid -> Settle Checked -> Settle Verified), sama
                                             dengan stepper di modul Cost Request. Hanya diisi di
                                             lapis CR: manager & PIC adalah kumpulan dokumen yang
                                             tahapnya bisa berbeda-beda, jadi satu badge di sana
                                             akan mengaku mewakili sesuatu yang tidak tunggal. --}}
                                        <th scope="col" rowspan="2" class="text-center aca-head-plain">Status</th>

                                        {{-- Jumlah DOKUMEN CR: per PIC di lapis 2, dan totalnya
                                             di lapis manager. Lapis CR sendiri dikosongkan -
                                             satu baris CR selalu satu dokumen, jadi menulis "1"
                                             di tiap baris tidak menjawab apa pun. --}}
                                        {{-- Header dua baris, mengikuti pola Summary Progress:
                                             satu payung untuk ukurannya, lalu QTY & VALUE sebagai
                                             satuan di bawahnya. Bentuk yang sama dipakai dua tabel
                                             berarti pembacanya tidak perlu belajar dua kali. --}}
                                        <th scope="col" colspan="2" class="text-center col-grp-head">Cash Advance</th>
                                    </tr>
                                    <tr>
                                        <th scope="col" class="text-center aca-head-plain">Qty</th>

                                        <th scope="col" class="text-right aca-head-plain">
                                            <button type="button" class="aca-sort" data-aca-sort="value"
                                                    title="Urutkan menurut nilai CA">
                                                Value
                                                <i class="fas fa-sort aca-sort-ic" aria-hidden="true"></i>
                                            </button>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($activeCashAdvance as $gi => $g)
                                    {{-- LAPIS 1 — MANAGER --}}
                                    {{-- data-sort-* dipakai penyortir JS. Nilainya angka mentah
                                         (bukan "70.600.000") supaya tidak perlu membongkar
                                         format rupiah, dan namanya apa adanya supaya perbandingan
                                         abjadnya sama dengan yang dipakai server. --}}
                                    <tr class="aca-group-row" data-target="aca-grp-{{ $gi }}"
                                        data-grp="aca-grp-{{ $gi }}"
                                        data-sort-name="{{ $g['manager'] }}" data-sort-value="{{ $g['value'] }}"
                                        style="cursor:pointer;background:var(--surface-2);">
                                        <td class="aca-lv1">
                                            <i class="fas fa-caret-right aca-caret mr-2"></i>
                                            {{ $g['manager'] }}
                                        </td>
                                        <td></td>
                                        {{-- Aging & Action sengaja kosong di lapis grup: umur dan
                                             tahap melekat pada satu dokumen. Satu angka aging untuk
                                             sekumpulan CR yang dibayar pada hari berbeda-beda hanya
                                             mewakili yang paling tua, dan itu terbaca seolah berlaku
                                             untuk semuanya. --}}
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td class="text-center aca-qty" style="font-weight:700;">{{ $g['qty'] }}</td>
                                        <td class="text-right" style="font-weight:700;">
                                            <a href="{{ $acaCrUrl(['manager' => $g['manager'], 'pics' => $g['pics']], $g['date_from']) }}"
                                               target="_blank" rel="noopener" class="aca-value-link"
                                               title="Buka Cost Request milik {{ $g['manager'] }}"><span class="aca-val">{{ $fmtRpFull($g['value']) }}</span></a>
                                        </td>
                                    </tr>

                                    @foreach($g['beneficiaries'] as $bi => $b)
                                    {{-- LAPIS 2 — BENEFICIARY --}}
                                    <tr class="aca-ben-row aca-grp-{{ $gi }}" style="display:none;cursor:pointer;"
                                        data-target="aca-cr-{{ $gi }}-{{ $bi }}"
                                        data-grp="aca-grp-{{ $gi }}" data-pic="{{ $b['beneficiary'] }}"
                                        data-sort-name="{{ $b['beneficiary'] }}" data-sort-value="{{ $b['value'] }}">
                                        <td class="aca-lv2">
                                            <i class="fas fa-caret-right aca-caret mr-2"></i>
                                            {{ $b['beneficiary'] }}
                                        </td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td class="text-center aca-qty">{{ $b['qty'] }}</td>
                                        <td class="text-right">
                                            <a href="{{ $acaCrUrl(['pic' => $b['beneficiary']], $b['date_from']) }}"
                                               target="_blank" rel="noopener" class="aca-value-link"
                                               title="Buka Cost Request milik {{ $b['beneficiary'] }}"><span class="aca-val">{{ $fmtRpFull($b['value']) }}</span></a>
                                        </td>
                                    </tr>

                                    {{-- LAPIS 3 — COST REQUEST. Kelas aca-allcr-{{ $gi }} ikut
                                         dipasang supaya menutup Manager ikut menutup baris CR
                                         di dalamnya, bukan menyisakannya menggantung. --}}
                                    {{-- Nama variabelnya $caItem, BUKAN $cr: halaman ini sudah
                                         punya $cr (ringkasan status Cost Request) yang dipakai
                                         workflow stepper di bawah, dan variabel @foreach bocor ke
                                         scope sekitarnya. --}}
                                    @foreach($b['items'] as $caItem)
                                    {{-- data-pic / data-aging / data-amount dipakai penyaring di
                                         browser: dialah yang menghitung ulang subtotal PIC,
                                         Manager, dan Grand Total dari baris yang masih terlihat. --}}
                                    <tr class="aca-cr-row aca-cr-{{ $gi }}-{{ $bi }} aca-allcr-{{ $gi }}" style="display:none;"
                                        data-grp="aca-grp-{{ $gi }}" data-ben="aca-cr-{{ $gi }}-{{ $bi }}"
                                        data-pic="{{ $b['beneficiary'] }}"
                                        data-po="{{ $caItem['po_code'] }}"
                                        data-aging="{{ $caItem['aging'] === null ? '' : $caItem['aging'] }}"
                                        data-amount="{{ $caItem['amount'] }}"
                                        data-sort-name="{{ $caItem['code'] }}" data-sort-value="{{ $caItem['amount'] }}">
                                        <td class="aca-lv3">
                                            {{-- Kode CR membuka detail pengajuannya sebagai modal
                                                 DI HALAMAN INI — tidak pindah ke menu Cost
                                                 Request. Kelas .btn-detail-cr + data-id adalah
                                                 pemicu yang sudah dikenali partial
                                                 budget-request._detail_modal (dipakai juga oleh
                                                 Project Monitoring), jadi tidak ada markup atau
                                                 JS detail yang disalin ke sini. --}}
                                            <a href="#" class="aca-cr-code aca-cr-link btn-detail-cr"
                                               data-id="{{ $caItem['id'] }}"
                                               title="Lihat detail {{ $caItem['code'] }}">{{ $caItem['code'] }}</a>
                                            <span class="aca-cr-desc" title="{{ $caItem['description'] }}">{{ $caItem['description'] }}</span>
                                        </td>
                                        <td class="aca-po-ref" title="{{ $caItem['po_code'] ? '[' . $caItem['po_code'] . '] ' . $caItem['po_name'] : '' }}">
                                            @if($caItem['po_code'])
                                            @if($acaCanOpenPo)
                                            {{-- ?code=<kode> menyaring daftar Purchase Order pada
                                                 kode itu dan mengisi kotak "Cari"-nya, jadi
                                                 penyaringnya terlihat dan bisa dihapus. Bukan
                                                 ?open=<id> yang langsung membuka modal detail:
                                                 yang diminta di sini mendarat di daftarnya. --}}
                                            <a href="{{ route('project.index') }}?code={{ $caItem['po_code'] }}"
                                               target="_blank" rel="noopener" class="aca-po-code aca-po-link"
                                               title="Buka PO {{ $caItem['po_code'] }} — {{ $caItem['po_name'] }}">[{{ $caItem['po_code'] }}]</a>
                                            @else
                                            {{-- Role lain tetap melihat kodenya; yang dicabut
                                                 hanya tautannya. --}}
                                            <span class="aca-po-code">[{{ $caItem['po_code'] }}]</span>
                                            @endif
                                            <span class="aca-po-name">{{ $caItem['po_name'] }}</span>
                                            @else
                                            <span class="text-muted">&ndash;</span>
                                            @endif
                                        </td>
                                        <td class="text-center text-nowrap">{!! $acaAging($caItem['aging']) !!}</td>
                                        <td class="text-center text-nowrap">{{ $caItem['paid_date'] ?? '-' }}</td>
                                        <td class="text-center text-nowrap">
                                            <span class="badge badge-{{ $caItem['status_color'] }}" style="font-size:9px;">{{ $caItem['status_label'] }}</span>
                                        </td>
                                        {{-- Selalu "1 CR": satu baris di lapis ini memang satu
                                             dokumen. Ditulis samar karena bukan angka yang
                                             dijumlahkan - Grand Total menjumlahkan lapis manager,
                                             jadi baris ini tidak ikut terhitung dua kali. --}}
                                        <td class="text-center text-muted" style="font-size:11px;">1</td>
                                        <td class="text-right text-nowrap">{{ $fmtRpFull($caItem['amount']) }}</td>
                                    </tr>
                                    @endforeach
                                    @endforeach
                                    @empty
                                    <tr><td colspan="7" class="text-center text-muted py-3">Tidak ada Cash Advance aktif.</td></tr>
                                    @endforelse
                                </tbody>
                                @if($activeCashAdvance->isNotEmpty())
                                <tfoot>
                                    <tr style="background:var(--surface-2);">
                                        <th colspan="5" class="text-left">Grand Total</th>
                                        <th class="text-center aca-qty">{{ $activeCaGrandQty }}</th>
                                        <th class="text-right aca-val">{{ $fmtRpFull($activeCaGrandValue) }}</th>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>

                <div class="row">
                    {{-- STATUS WORKFLOW --}}
                    <div class="col-lg-6">
                        @php
                            $bpSteps = [
                                ['Draft', $bp['draft'] ?? 0, 'secondary', 'DRAFT'],
                                ['Submitted', $bp['submitted'] ?? 0, 'warning', 'SUBMITTED'],
                                ['Checked', $bp['checked'] ?? 0, 'info', 'CHECKED'],
                                ['Verified', $bp['verified'] ?? 0, 'primary', 'VERIFIED'],
                                ['Active', $bp['active'] ?? 0, 'success', 'ACTIVE'],
                                ['Closing', $bp['closing'] ?? 0, 'warning', 'CLOSING'],
                                ['Closed', $bp['closed'] ?? 0, 'dark', 'CLOSED'],
                                ['Rejected', $bp['rejected'] ?? 0, 'danger', 'REJECTED'],
                            ];
                        @endphp
                        <x-workflow-stepper :steps="$bpSteps" title="Status Budget Plan" icon="fas fa-wallet"
                            :total="$bp['total']" table="" field="status" />

                        @php
                            $crSteps = [
                                ['Draft', $cr['draft'] ?? 0, 'secondary', 'DRAFT'],
                                ['Submitted', $cr['submitted'] ?? 0, 'warning', 'SUBMITTED'],
                                ['Approved', $cr['approved'] ?? 0, 'primary', 'APPROVED'],
                                ['Verified', $cr['verified'] ?? 0, 'info', 'VERIFIED'],
                                ['Process', $cr['queued'] ?? 0, 'info', 'QUEUED'],
                                ['Paid', $cr['paid'] ?? 0, 'success', 'PAID'],
                                ['Settle', $cr['realized'] ?? 0, 'success', 'REALIZED'],
                                ['Closed', $cr['closed'] ?? 0, 'dark', 'CLOSED'],
                                ['Rejected', $cr['rejected'] ?? 0, 'danger', 'REJECTED'],
                            ];
                        @endphp
                        <x-workflow-stepper :steps="$crSteps" title="Status Cost Request" icon="fas fa-file-invoice-dollar"
                            :total="$cr['total']" table="" field="status" />
                    </div>

                    {{-- SERAPAN ANGGARAN + BREAKDOWN --}}
                    <div class="col-lg-6">
                        <div class="card shadow-sm">
                            <div class="card-header d-flex align-items-center">
                                <h3 class="card-title font-weight-bold"><i class="fas fa-chart-pie mr-1" style="color:var(--info);"></i> Serapan Anggaran</h3>
                                <form method="GET" action="{{ route('finance-summary.index') }}" class="ml-auto">
                                    <select name="budget_plan_id" class="form-control form-control-sm" style="max-width:220px;font-size:12px;" onchange="this.form.submit()">
                                        <option value="all" {{ $selectedBudgetId === 'all' ? 'selected' : '' }}>Semua Budget Plan Aktif</option>
                                        @foreach($activeBudgetPlans as $planOpt)
                                        <option value="{{ $planOpt->id }}" {{ (string) $selectedBudgetId === (string) $planOpt->id ? 'selected' : '' }}>
                                            {{ $planOpt->code }} — {{ Str::limit($planOpt->description, 30) }}
                                        </option>
                                        @endforeach
                                    </select>
                                </form>
                            </div>
                            <div class="card-body">
                                <div class="progress mb-2" style="height:10px;">
                                    <div class="progress-bar bg-warning js-pct-width" role="progressbar" data-width="{{ $finance['absorption_pct'] }}"></div>
                                </div>
                                <div class="d-flex justify-content-between mb-3" style="font-size:11px;color:var(--text-faint);">
                                    <span>Terpakai {{ rtrim(rtrim(number_format($finance['absorption_pct'], 1, ',', '.'), '0'), ',') }}%</span>
                                    <span>Margin {{ rtrim(rtrim(number_format($finance['remaining_pct'], 1, ',', '.'), '0'), ',') }}%</span>
                                </div>

                                <div class="row text-center">
                                    <div class="col-6 col-md-3 mb-3">
                                        <div class="font-weight-bold" style="font-size:14px;">{{ number_format($finance['total_budget'], 0, ',', '.') }}</div>
                                        <small class="text-muted">Total Budget</small>
                                    </div>
                                    <div class="col-6 col-md-3 mb-3">
                                        <div class="font-weight-bold text-warning" style="font-size:14px;">{{ number_format($finance['used'], 0, ',', '.') }}</div>
                                        <small class="text-muted">Terpakai</small>
                                    </div>
                                    <div class="col-6 col-md-3 mb-3">
                                        <div class="font-weight-bold text-success" style="font-size:14px;">{{ number_format($finance['remaining'], 0, ',', '.') }}</div>
                                        <small class="text-muted">Sisa</small>
                                    </div>
                                    <div class="col-6 col-md-3 mb-3">
                                        <div class="font-weight-bold text-info" style="font-size:14px;">{{ number_format($finance['realized'], 0, ',', '.') }}</div>
                                        <small class="text-muted">Realisasi ({{ rtrim(rtrim(number_format($finance['realized_of_used_pct'], 1, ',', '.'), '0'), ',') }}%)</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h3 class="card-title font-weight-bold"><i class="fas fa-list mr-1" style="color:var(--purple);"></i> Rincian Terserap per Account</h3>
                            </div>
                            <div class="card-body">
                                @if($accountBreakdown->isEmpty())
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-inbox d-block mb-2" style="font-size:22px;"></i>
                                    Belum ada Cost Request yang terpakai.
                                </div>
                                @else
                                <div id="accountBreakdownList">
                                    @foreach($accountBreakdown as $acc)
                                    @php $accPct = $breakdownTotal > 0 ? round($acc['total'] / $breakdownTotal * 100, 1) : 0; @endphp
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between" style="font-size:12.5px;">
                                            <span class="font-weight-600">{{ $acc['name'] }}</span>
                                            <span class="text-muted">{{ number_format($acc['total'], 0, ',', '.') }} &middot; {{ rtrim(rtrim(number_format($accPct, 1, ',', '.'), '0'), ',') }}%</span>
                                        </div>
                                        <div class="progress mt-1" style="height:6px;">
                                            <div class="progress-bar bg-primary js-pct-width" role="progressbar" data-width="{{ $accPct }}"></div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            @if($canViewCollection)
            <div class="tab-pane fade {{ $initialTab === 'collection' ? 'show active' : '' }}" id="summaryPaneCollection">

                @php
                    $fmtJt = fn ($v) => number_format($v / 1000000, ($v != 0 && abs($v) < 1000000) ? 1 : 0, ',', '.') . 'jt';
                    $fmtFull = fn ($v) => number_format($v, 0, ',', '.');
                @endphp

                <div id="colChartData"
                     data-aging="{{ json_encode($colAgingChart) }}"
                     data-aging-counts="{{ json_encode($colAgingCounts) }}"
                     data-status="{{ json_encode($colStatusChart) }}"
                     data-upcoming="{{ json_encode($colUpcomingDue) }}"
                     style="display:none;"></div>

                @php
                    $invoiceUrl  = url('/invoice-cash-receipt');
                    $overdueUrl  = url('/invoice-cash-receipt?status=OVERDUE');
                    // Outstanding = "Unpaid" (RECEIVED/PARTIALLY_PAID) + "Overdue". SENT yang
                    // belum jatuh tempo TIDAK termasuk. Di daftar invoice, baris yang lewat
                    // tempo tampil sebagai display-status OVERDUE, jadi OVERDUE wajib ikut.
                    $outstandingParam = 'RECEIVED,PARTIALLY_PAID,OVERDUE';
                    $outstandingUrl   = $invoiceUrl . '?statuses=' . $outstandingParam;
                @endphp
                @php
                    $pctPaid         = $colKpiTotalInvoiceValue > 0 ? round($colKpiPaidValue / $colKpiTotalInvoiceValue * 100) : 0;
                    $pctOutstanding  = $colKpiTotalInvoiceValue > 0 ? round($colKpiOutstanding / $colKpiTotalInvoiceValue * 100) : 0;
                    $pctOverdueValue = $colKpiTotalInvoiceValue > 0 ? round($colKpiOverdueValue / $colKpiTotalInvoiceValue * 100) : 0;
                    $collectedValue  = $colKpiCollectedValue;
                    $kpiCmpArgs = fn ($key) => $colCompare
                        ? ($key === 'rate'
                            ? ['m' => $colCompare['rate'], 'prevLabel' => $colCompare['prev_label'], 'prevHasData' => $colCompare['prev_has_data']]
                            : ['pair' => $colCompare[$key], 'prevLabel' => $colCompare['prev_label'], 'prevHasData' => $colCompare['prev_has_data']])
                        : [];
                @endphp

                {{-- FILTER COLLECTION --}}
                @php
                    $colFM = collect($colFilterManager)->map(fn ($v) => (string) $v);
                    $colFC = collect($colFilterCustomer)->map(fn ($v) => (string) $v);
                    $colFMonth = collect($colFilterMonth)->map(fn ($v) => (string) $v);
                @endphp
                {{-- Bilah kendali: memakai kelas .custom-toolbar yang sama dengan
                     halaman index lain, supaya filter terbaca sebagai kendali,
                     bukan sebagai bagian dari data. --}}
                {{-- justify-content-start: .custom-toolbar bawaan memakai space-between —
                     itu untuk halaman yang punya blok kiri (.custom-toolbar-left) dan
                     blok aksi kanan. Di sini semua isinya satu kelompok kendali, jadi
                     tanpa ini ketiga filter malah terlempar berjauhan dan Apply
                     menempel ke tepi kanan. --}}
                <form method="GET" action="{{ url('/finance-summary') }}" class="custom-toolbar d-flex flex-wrap align-items-center justify-content-start mb-3" style="gap:8px;">
                    <input type="hidden" name="tab" value="collection">
                    @include('finance-summary._multi-filter', ['name' => 'col_manager', 'label' => 'Manager', 'options' => $colManagerOptions, 'selected' => $colFM])
                    @include('finance-summary._multi-filter', ['name' => 'col_customer', 'label' => 'Customer', 'options' => $colCustomerOptions, 'selected' => $colFC])
                    @include('finance-summary._multi-filter', ['name' => 'col_month', 'label' => 'Bulan Invoice', 'options' => $colMonthOptions, 'selected' => $colFMonth])
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter mr-1"></i> Apply</button>
                    @if($colFM->isNotEmpty() || $colFC->isNotEmpty() || $colFMonth->isNotEmpty())
                    <a href="{{ url('/finance-summary?tab=collection') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                    @endif
                </form>
                @if($colFMonth->isNotEmpty())
                @php
                    $monthLabels = collect($colMonthOptions)->whereIn('value', $colFMonth->all())->pluck('label')->implode(', ');
                @endphp
                <div class="mb-3" style="font-size:11.5px;color:var(--text-faint);">
                    <i class="fas fa-info-circle mr-1" style="color:var(--info);"></i>
                    Menampilkan invoice periode <strong>{{ $monthLabels ?: $colFMonth->implode(', ') }}</strong>.
                    Umur piutang (aging) tetap dihitung terhadap tanggal hari ini.
                    @if($colCompare)<br><span style="color:var(--text-faint);">Angka di kartu KPI dibandingkan dengan <strong>{{ $colCompare['prev_label'] }}</strong>.</span>@endif
                </div>
                @endif

                <div class="row col-kpi-row">
                    <div class="col-6 col-md-4 col-xl col-kpi-col">
                        <a href="{{ $invoiceUrl }}" target="_blank" class="card shadow-sm col-kpi-card col-kpi-clickable col-kpi-accent-blue" title="Lihat semua invoice">
                            <div class="col-kpi-head">
                                <span class="col-kpi-icon col-kpi-icon-blue"><i class="fas fa-file-invoice"></i></span>
                                <span class="col-kpi-label">Total Invoice</span>
                            </div>
                            <div class="col-kpi-value" style="color:var(--info);">{{ $fmtFull($colKpiTotalInvoiceValue) }}</div>
                            <div class="col-kpi-qty">{{ number_format($colKpiTotalInvoiceCount, 0, ',', '.') }} <span class="col-kpi-qty-lbl">invoice</span></div>
                            @if($colCompare)@include('finance-summary._kpi-compare', $kpiCmpArgs('total_invoice'))@endif
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-xl col-kpi-col">
                        <a href="{{ $invoiceUrl }}?status=PAID" target="_blank" class="card shadow-sm col-kpi-card col-kpi-clickable col-kpi-accent-good" title="Lihat invoice lunas">
                            <div class="col-kpi-head">
                                <span class="col-kpi-icon col-kpi-icon-good"><i class="fas fa-check-circle"></i></span>
                                <span class="col-kpi-label">Paid</span>
                            </div>
                            <div class="col-kpi-value" style="color:var(--success);">{{ $fmtFull($colKpiPaidValue) }}</div>
                            <div class="col-kpi-qty">{{ number_format($colKpiPaidCount, 0, ',', '.') }} <span class="col-kpi-qty-lbl">invoice &middot; {{ $pctPaid }}% dari nilai</span></div>
                            <div class="col-kpi-track"><div class="col-kpi-fill col-kpi-fill-good js-pct-width" data-width="{{ $pctPaid }}"></div></div>
                            @if($colCompare)@include('finance-summary._kpi-compare', $kpiCmpArgs('paid'))@endif
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-xl col-kpi-col">
                        <a href="{{ $outstandingUrl }}" target="_blank" class="card shadow-sm col-kpi-card col-kpi-clickable col-kpi-accent-warn" title="Lihat invoice outstanding (unpaid + overdue)">
                            <div class="col-kpi-head">
                                <span class="col-kpi-icon col-kpi-icon-warn"><i class="fas fa-wallet"></i></span>
                                <span class="col-kpi-label">Outstanding</span>
                            </div>
                            <div class="col-kpi-value" style="color:var(--warning);">{{ $fmtFull($colKpiOutstanding) }}</div>
                            <div class="col-kpi-qty">{{ number_format($colKpiOutstandingCount, 0, ',', '.') }} <span class="col-kpi-qty-lbl">invoice &middot; {{ $pctOutstanding }}% dari nilai</span></div>
                            <div class="col-kpi-track"><div class="col-kpi-fill col-kpi-fill-warn js-pct-width" data-width="{{ $pctOutstanding }}"></div></div>
                            @if($colCompare)@include('finance-summary._kpi-compare', $kpiCmpArgs('outstanding'))@endif
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-xl col-kpi-col">
                        <a href="{{ $overdueUrl }}" target="_blank" class="card shadow-sm col-kpi-card col-kpi-clickable col-kpi-accent-bad" title="Lihat invoice overdue">
                            <div class="col-kpi-head">
                                <span class="col-kpi-icon col-kpi-icon-bad"><i class="fas fa-exclamation-triangle"></i></span>
                                <span class="col-kpi-label">Overdue</span>
                            </div>
                            <div class="col-kpi-value" style="color:var(--danger);">{{ $fmtFull($colKpiOverdueValue) }}</div>
                            <div class="col-kpi-qty">{{ number_format($colKpiOverdueCount, 0, ',', '.') }} <span class="col-kpi-qty-lbl">invoice &middot; {{ $pctOverdueValue }}% dari nilai</span></div>
                            <div class="col-kpi-track"><div class="col-kpi-fill col-kpi-fill-bad js-pct-width" data-width="{{ $pctOverdueValue }}"></div></div>
                            @if($colCompare)@include('finance-summary._kpi-compare', $kpiCmpArgs('overdue'))@endif
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-xl col-kpi-col">
                        <a href="{{ $invoiceUrl }}" target="_blank" class="card shadow-sm col-kpi-card col-kpi-clickable col-kpi-accent-good" title="Lihat semua invoice">
                            <div class="col-kpi-head">
                                <span class="col-kpi-icon col-kpi-icon-good"><i class="fas fa-chart-line"></i></span>
                                <span class="col-kpi-label">Collection Rate</span>
                            </div>
                            <div class="col-kpi-value" style="color:var(--success);">{{ $colKpiCollectionRate }}%</div>
                            <div class="col-kpi-qty">{{ $fmtFull($collectedValue) }} <span class="col-kpi-qty-lbl">tertagih</span></div>
                            <div class="col-kpi-track"><div class="col-kpi-fill col-kpi-fill-good js-pct-width" data-width="{{ $colKpiCollectionRate }}"></div></div>
                            @if($colCompare)@include('finance-summary._kpi-compare', $kpiCmpArgs('rate'))@endif
                        </a>
                    </div>
                </div>

                <div class="row exec-chart-row">
                    <div class="col-md-4 exec-chart-col">
                        <div class="card shadow-sm exec-chart-card">
                            <div class="card-header d-flex align-items-center">
                                <h3 class="card-title" style="font-size:13px;font-weight:700;"><i class="fas fa-chart-bar mr-2" style="color:var(--info);"></i>Aging Overdue</h3>
                                <span class="ml-auto" style="font-size:11px;color:var(--text-faint);">Total: {{ $fmtFull($colKpiOverdueValue) }}</span>
                            </div>
                            <div class="card-body">
                                <div class="col-chart-wrap"><canvas id="chartAging"></canvas></div>
                            </div>
                            @include('finance-summary._collection-detail', [
                                'id' => 'colDetailAging',
                                'groups' => $colAgingDetail,
                                'amountLabel' => 'Overdue',
                                'amountColor' => 'color:var(--danger);',
                            ])
                        </div>
                    </div>
                    <div class="col-md-4 exec-chart-col">
                        <div class="card shadow-sm exec-chart-card">
                            <div class="card-header">
                                <h3 class="card-title" style="font-size:13px;font-weight:700;"><i class="fas fa-chart-pie mr-2" style="color:var(--success);"></i>Status Invoice (Nilai)</h3>
                            </div>
                            <div class="card-body">
                                <div class="col-donut-row">
                                    <div class="col-donut-chart">
                                        <canvas id="chartInvStatus"></canvas>
                                        <div class="col-donut-center">
                                            <div class="col-donut-center-label">Total Invoice</div>
                                            <div class="col-donut-center-value" id="invStatusTotal"></div>
                                        </div>
                                    </div>
                                    <div id="legendInvStatus" class="col-donut-legend"></div>
                                </div>
                            </div>
                            @include('finance-summary._collection-detail', [
                                'id' => 'colDetailStatus',
                                'groups' => $colStatusDetail,
                                'amountLabel' => 'Amount',
                            ])
                        </div>
                    </div>
                    <div class="col-md-4 exec-chart-col">
                        <div class="card shadow-sm exec-chart-card">
                            <div class="card-header d-flex align-items-center">
                                <h3 class="card-title" style="font-size:13px;font-weight:700;"><i class="fas fa-calendar-alt mr-2" style="color:var(--warning);"></i>Upcoming Due</h3>
                                <span class="ml-auto" style="font-size:11px;color:var(--text-faint);" id="upcomingDueTotal"></span>
                            </div>
                            <div class="card-body">
                                <div class="col-chart-wrap"><canvas id="chartUpcoming"></canvas></div>
                            </div>
                            @include('finance-summary._collection-detail', [
                                'id' => 'colDetailUpcoming',
                                'groups' => $colUpcomingDetail,
                                'amountLabel' => 'Amount',
                            ])
                        </div>
                    </div>
                </div>

                {{-- ── COLLECTION PERFORMANCE — tabel flat per (Customer, Manager),
                     sortable & column visibility granular (Qty/Value per grup) ── --}}
                @php
                    $invUrl = url('/invoice-cash-receipt');
                    $cpGrandInv   = $collectionRows->sum('total_invoice');
                    $cpGrandOs    = $collectionRows->sum('outstanding');
                    $cpGrandRate  = $cpGrandInv > 0 ? round((($cpGrandInv - $cpGrandOs) / $cpGrandInv) * 100) : 0;
                    // Rata-rata aging digabung berbobot qty overdue tiap baris (bukan
                    // rata-rata polos per baris) supaya konsisten dgn cara avg_aging_days
                    // dihitung per baris di FinanceSummaryController::buildCollectionFlatRows().
                    $cpGrandOvCount = $collectionRows->sum('overdue_count');
                    $cpGrandAging   = $cpGrandOvCount > 0
                        ? round($collectionRows->sum(fn ($r) => $r['avg_aging_days'] * $r['overdue_count']) / $cpGrandOvCount)
                        : 0;
                @endphp
                <div class="row exec-chart-row">
                    <div class="col-12 exec-chart-col">
                        <div class="card shadow-sm exec-chart-card">
                            <div class="card-header d-flex align-items-center">
                                <h3 class="card-title" style="font-size:13px;font-weight:700;"><i class="fas fa-user-tie mr-2" style="color:var(--purple);"></i>Collection Performance</h3>
                                <div class="ml-auto d-flex align-items-center flex-wrap" style="gap:6px;">
                                    <label class="mb-0 d-flex align-items-center" style="font-size:11px;font-weight:600;color:var(--text-faint);gap:4px;cursor:pointer;">
                                        <input type="checkbox" id="cpShowRate100">Show Rate 100%
                                    </label>
                                    <div id="cpRowFilters" class="d-flex flex-wrap" style="gap:6px;"></div>
                                    {{-- Kembali ke data awal. Bentuk & ikonnya sama dengan
                                         tombol Clear Filter di tab Executive, jadi fungsinya
                                         dikenali tanpa dibaca. --}}
                                    <button type="button" id="btnClearCp" class="btn btn-outline-secondary btn-sm"
                                            title="Bersihkan filter" aria-label="Bersihkan filter">
                                        <i class="fas fa-eraser"></i>
                                    </button>
                                    <div class="dropdown" data-colvis="tblCollectionPerformance">
                                        <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-toggle="dropdown" data-display="static">
                                            <i class="fas fa-columns mr-1"></i>Kolom
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-right js-colvis-menu p-2" style="font-size:12px;min-width:200px;max-height:360px;overflow-y:auto;">
                                            <div class="text-muted font-weight-bold px-1" style="font-size:10px;letter-spacing:.03em;">TOTAL INVOICE</div>
                                            <label class="dropdown-item m-0 p-1"><input type="checkbox" class="js-colvis-toggle mr-1" data-col="2" checked>Qty</label>
                                            <label class="dropdown-item m-0 p-1"><input type="checkbox" class="js-colvis-toggle mr-1" data-col="3" checked>Value</label>
                                            <div class="text-muted font-weight-bold px-1 mt-1" style="font-size:10px;letter-spacing:.03em;">PAID</div>
                                            <label class="dropdown-item m-0 p-1"><input type="checkbox" class="js-colvis-toggle mr-1" data-col="4" checked>Qty</label>
                                            <label class="dropdown-item m-0 p-1"><input type="checkbox" class="js-colvis-toggle mr-1" data-col="5" checked>Value</label>
                                            <div class="text-muted font-weight-bold px-1 mt-1" style="font-size:10px;letter-spacing:.03em;">OUTSTANDING</div>
                                            <label class="dropdown-item m-0 p-1"><input type="checkbox" class="js-colvis-toggle mr-1" data-col="6" checked>Qty</label>
                                            <label class="dropdown-item m-0 p-1"><input type="checkbox" class="js-colvis-toggle mr-1" data-col="7" checked>Value</label>
                                            <div class="text-muted font-weight-bold px-1 mt-1" style="font-size:10px;letter-spacing:.03em;">OVERDUE</div>
                                            <label class="dropdown-item m-0 p-1"><input type="checkbox" class="js-colvis-toggle mr-1" data-col="8" checked>Qty</label>
                                            <label class="dropdown-item m-0 p-1"><input type="checkbox" class="js-colvis-toggle mr-1" data-col="9" checked>Value</label>
                                            <div class="text-muted font-weight-bold px-1 mt-1" style="font-size:10px;letter-spacing:.03em;">LAINNYA</div>
                                            <label class="dropdown-item m-0 p-1"><input type="checkbox" class="js-colvis-toggle mr-1" data-col="10" checked>Collection Rate</label>
                                            <label class="dropdown-item m-0 p-1"><input type="checkbox" class="js-colvis-toggle mr-1" data-col="11" checked>Aging</label>
                                            <label class="dropdown-item m-0 p-1"><input type="checkbox" class="js-colvis-toggle mr-1" data-col="12" checked>Status</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                    {{-- Sama seperti di Summary PO: baris ber-rate 100% memang
                                         disembunyikan secara bawaan, dan itu jadi menyesatkan
                                         begitu filter mempersempit data sampai tak ada baris
                                         lain yang tersisa. --}}
                                    <div class="js-rate-note alert alert-info m-3 mb-0 py-2" data-for="tblCollectionPerformance"
                                         style="display:none;font-size:11.5px;">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Semua baris pada filter ini sudah <strong>Collection Rate 100%</strong>, dan baris lunas disembunyikan secara bawaan.
                                        Centang <strong>Show Rate 100%</strong> di kanan atas untuk menampilkannya.
                                    </div>
                                    <table id="tblCollectionPerformance" class="table table-sm mb-0 col-pic-table">
                                        <thead>
                                            <tr>
                                                <th scope="col" rowspan="2" class="col-sticky-left" style="vertical-align:middle;border-bottom:2px solid var(--border);">Customer</th>
                                                <th scope="col" rowspan="2" style="vertical-align:middle;border-bottom:2px solid var(--border);">Manager</th>
                                                <th scope="col" colspan="2" class="text-center col-grp-head col-grp-blue">Total Invoice</th>
                                                <th scope="col" colspan="2" class="text-center col-grp-head col-grp-good">Paid</th>
                                                <th scope="col" colspan="2" class="text-center col-grp-head col-grp-warn">Outstanding</th>
                                                <th scope="col" colspan="2" class="text-center col-grp-head col-grp-bad">Overdue</th>
                                                <th scope="col" rowspan="2" class="text-center col-grp-head col-grp-good" style="vertical-align:middle;border-bottom:2px solid var(--success);min-width:110px;">Collection<br>Rate</th>
                                                <th scope="col" rowspan="2" class="text-center col-grp-head col-grp-bad" style="vertical-align:middle;border-bottom:2px solid var(--danger);">Aging<br>(hari)</th>
                                                <th scope="col" rowspan="2" class="col-sticky-right" style="vertical-align:middle;border-bottom:2px solid var(--border);">Status</th>
                                            </tr>
                                            <tr>
                                                <th scope="col" class="col-subhead-blue">Qty</th>
                                                <th scope="col" class="col-subhead-blue">Value</th>
                                                <th scope="col" class="col-subhead-good">Qty</th>
                                                <th scope="col" class="col-subhead-good">Value</th>
                                                <th scope="col" class="col-subhead-warn">Qty</th>
                                                <th scope="col" class="col-subhead-warn">Value</th>
                                                <th scope="col" class="col-subhead-bad">Qty</th>
                                                <th scope="col" class="col-subhead-bad">Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($collectionRows as $r)
                                            <tr data-manager="{{ $r['manager'] }}"
                                                data-total-invoice-count="{{ $r['total_invoice_count'] }}"
                                                data-total-invoice="{{ $r['total_invoice'] }}"
                                                data-paid-count="{{ $r['paid_count'] }}"
                                                data-paid="{{ $r['paid'] }}"
                                                data-outstanding-count="{{ $r['outstanding_count'] }}"
                                                data-outstanding="{{ $r['outstanding'] }}"
                                                data-overdue-count="{{ $r['overdue_count'] }}"
                                                data-overdue="{{ $r['overdue'] }}"
                                                data-avg-aging-days="{{ $r['avg_aging_days'] }}"
                                                data-collection-rate="{{ $r['collection_rate'] }}">
                                                <td class="col-sticky-left" style="font-weight:600;">{{ $r['customer'] }}</td>
                                                <td>{{ $r['manager'] }}</td>
                                                <td class="text-right col-cell-blue col-qty-blue" style="font-weight:700;">{{ $r['total_invoice_count'] }}</td>
                                                <td class="text-right col-cell-blue">
                                                    <a href="{{ $invUrl }}?customer={{ urlencode($r['customer']) }}&manager={{ urlencode($r['manager']) }}" target="_blank" class="col-outstanding-link">{{ number_format($r['total_invoice'], 0, ',', '.') }}</a>
                                                </td>
                                                <td class="text-right col-cell-good" style="font-weight:700;color:var(--success);">{{ $r['paid_count'] }}</td>
                                                <td class="text-right col-cell-good">
                                                    @if($r['paid'] > 0)
                                                    <a href="{{ $invUrl }}?status=PAID&customer={{ urlencode($r['customer']) }}&manager={{ urlencode($r['manager']) }}" target="_blank" class="col-paid-link">{{ number_format($r['paid'], 0, ',', '.') }}</a>
                                                    @else <span class="col-paid-link">0</span> @endif
                                                </td>
                                                <td class="text-right col-cell-warn" style="font-weight:700;"><span class="col-warn-text">{{ $r['outstanding_count'] }}</span></td>
                                                <td class="text-right col-cell-warn">
                                                    @if($r['outstanding'] > 0)
                                                    <a href="{{ $invUrl }}?statuses={{ $outstandingParam }}&customer={{ urlencode($r['customer']) }}&manager={{ urlencode($r['manager']) }}" target="_blank" class="col-warn-link">{{ number_format($r['outstanding'], 0, ',', '.') }}</a>
                                                    @else <span class="col-warn-link">0</span> @endif
                                                </td>
                                                <td class="text-right col-cell-bad" style="font-weight:700;color:var(--danger);">{{ $r['overdue_count'] }}</td>
                                                <td class="text-right col-cell-bad">
                                                    @if($r['overdue'] > 0)
                                                    <a href="{{ $invUrl }}?status=OVERDUE&customer={{ urlencode($r['customer']) }}&manager={{ urlencode($r['manager']) }}" target="_blank" class="col-overdue-link">{{ number_format($r['overdue'], 0, ',', '.') }}</a>
                                                    @else <span class="col-overdue-link">0</span> @endif
                                                </td>
                                                <td class="text-center col-cell-good" data-order="{{ $r['collection_rate'] }}">
                                                    <div class="cp-rate-wrap">
                                                        <div class="cp-rate-bar"><div class="cp-rate-fill js-pct-width" data-width="{{ min(100, $r['collection_rate']) }}"></div></div>
                                                        <span>{{ $r['collection_rate'] }}%</span>
                                                    </div>
                                                </td>
                                                <td class="text-center col-cell-bad" style="font-weight:700;">{{ $r['avg_aging_days'] > 0 ? $r['avg_aging_days'] : '-' }}</td>
                                                <td class="text-center col-sticky-right" data-order="{{ $r['aging_status'] ?? '' }}">
                                                    @if($r['aging_status'])
                                                    <span class="cp-status-badge cp-status-{{ Str::slug($r['aging_status']) }}">{{ $r['aging_status'] }}</span>
                                                    @else
                                                    <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            @empty
                                            <tr><td colspan="13" class="text-center text-muted py-3">Belum ada data.</td></tr>
                                            @endforelse
                                        </tbody>
                                        @if($collectionRows->isNotEmpty())
                                        <tfoot>
                                            <tr class="col-detail-subtotal">
                                                <td colspan="2" class="col-sticky-left">Grand Total</td>
                                                <td class="text-right cp-foot-inv-count">{{ $collectionRows->sum('total_invoice_count') }}</td>
                                                <td class="text-right cp-foot-inv-value">{{ number_format($cpGrandInv, 0, ',', '.') }}</td>
                                                <td class="text-right cp-foot-paid-count">{{ $collectionRows->sum('paid_count') }}</td>
                                                <td class="text-right cp-foot-paid-value">{{ number_format($collectionRows->sum('paid'), 0, ',', '.') }}</td>
                                                <td class="text-right cp-foot-os-count">{{ $collectionRows->sum('outstanding_count') }}</td>
                                                <td class="text-right cp-foot-os-value">{{ number_format($cpGrandOs, 0, ',', '.') }}</td>
                                                <td class="text-right cp-foot-ov-count">{{ $collectionRows->sum('overdue_count') }}</td>
                                                <td class="text-right cp-foot-ov-value">{{ number_format($collectionRows->sum('overdue'), 0, ',', '.') }}</td>
                                                <td class="text-center cp-foot-rate">{{ $cpGrandRate }}%</td>
                                                <td class="text-center cp-foot-aging">{{ $cpGrandAging > 0 ? $cpGrandAging : '-' }}</td>
                                                <td class="text-center col-sticky-right">-</td>
                                            </tr>
                                        </tfoot>
                                        @endif
                                    </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── PO SUMMARY — tabel flat per (Customer, Manager), sortable, colvis
                     granular, hyperlink ke Purchase Order dengan filter aktif ── --}}
                @php
                    $poUrl = url('/project');
                    $poGrand = [
                        'po_qty' => $poRows->sum('po_qty'), 'po_value' => $poRows->sum('po_value'),
                        'receive_qty' => $poRows->sum('receive_qty'), 'receive_value' => $poRows->sum('receive_value'),
                        'waiting_qty' => $poRows->sum('waiting_qty'), 'waiting_value' => $poRows->sum('waiting_value'),
                        'payment_qty' => $poRows->sum('payment_qty'), 'payment_value' => $poRows->sum('payment_value'),
                    ];
                    $poGrandPaymentRate = $poGrand['po_value'] > 0 ? round(($poGrand['payment_value'] / $poGrand['po_value']) * 100) : 0;
                @endphp
                <div class="row exec-chart-row">
                    <div class="col-12 exec-chart-col">
                        <div class="card shadow-sm exec-chart-card">
                            <div class="card-header d-flex align-items-center">
                                <h3 class="card-title" style="font-size:13px;font-weight:700;"><i class="fas fa-file-invoice-dollar mr-2" style="color:var(--info);"></i>Summary PO</h3>
                                <div class="ml-auto d-flex align-items-center flex-wrap" style="gap:6px;">
                                    <label class="mb-0 d-flex align-items-center" style="font-size:11px;font-weight:600;color:var(--text-faint);gap:4px;cursor:pointer;">
                                        <input type="checkbox" id="poShowRate100">Show Rate 100%
                                    </label>
                                    <div id="poRowFilters" class="d-flex flex-wrap" style="gap:6px;"></div>
                                    {{-- Kembali ke data awal. Bentuk & ikonnya sama dengan
                                         tombol Clear Filter di tab Executive, jadi fungsinya
                                         dikenali tanpa dibaca. --}}
                                    <button type="button" id="btnClearPo" class="btn btn-outline-secondary btn-sm"
                                            title="Bersihkan filter" aria-label="Bersihkan filter">
                                        <i class="fas fa-eraser"></i>
                                    </button>
                                    <div class="dropdown" data-colvis="tblPoSummary">
                                        <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-toggle="dropdown" data-display="static">
                                            <i class="fas fa-columns mr-1"></i>Kolom
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-right js-colvis-menu p-2" style="font-size:12px;min-width:200px;max-height:360px;overflow-y:auto;">
                                            <div class="text-muted font-weight-bold px-1" style="font-size:10px;letter-spacing:.03em;">TOTAL PO</div>
                                            <label class="dropdown-item m-0 p-1"><input type="checkbox" class="js-colvis-toggle mr-1" data-col="2" checked>Qty</label>
                                            <label class="dropdown-item m-0 p-1"><input type="checkbox" class="js-colvis-toggle mr-1" data-col="3" checked>Value</label>
                                            <div class="text-muted font-weight-bold px-1 mt-1" style="font-size:10px;letter-spacing:.03em;">PO RECEIVED</div>
                                            <label class="dropdown-item m-0 p-1"><input type="checkbox" class="js-colvis-toggle mr-1" data-col="4" checked>Qty</label>
                                            <label class="dropdown-item m-0 p-1"><input type="checkbox" class="js-colvis-toggle mr-1" data-col="5" checked>Value</label>
                                            <div class="text-muted font-weight-bold px-1 mt-1" style="font-size:10px;letter-spacing:.03em;">WAITING PO</div>
                                            <label class="dropdown-item m-0 p-1"><input type="checkbox" class="js-colvis-toggle mr-1" data-col="6" checked>Qty</label>
                                            <label class="dropdown-item m-0 p-1"><input type="checkbox" class="js-colvis-toggle mr-1" data-col="7" checked>Value</label>
                                            <div class="text-muted font-weight-bold px-1 mt-1" style="font-size:10px;letter-spacing:.03em;">PAYMENT</div>
                                            <label class="dropdown-item m-0 p-1"><input type="checkbox" class="js-colvis-toggle mr-1" data-col="8" checked>Qty</label>
                                            <label class="dropdown-item m-0 p-1"><input type="checkbox" class="js-colvis-toggle mr-1" data-col="9" checked>Value</label>
                                            <div class="text-muted font-weight-bold px-1 mt-1" style="font-size:10px;letter-spacing:.03em;">VARIANCE</div>
                                            <label class="dropdown-item m-0 p-1"><input type="checkbox" class="js-colvis-toggle mr-1" data-col="10" checked>Qty</label>
                                            <label class="dropdown-item m-0 p-1"><input type="checkbox" class="js-colvis-toggle mr-1" data-col="11" checked>Value</label>
                                            <div class="text-muted font-weight-bold px-1 mt-1" style="font-size:10px;letter-spacing:.03em;">LAINNYA</div>
                                            <label class="dropdown-item m-0 p-1"><input type="checkbox" class="js-colvis-toggle mr-1" data-col="12" checked>Payment Rate</label>
                                        </div>
                                    </div>
                                    <label class="mb-0" style="font-size:11px;color:var(--text-faint);">VAR:</label>
                                    <select id="poVarFormula" class="form-control form-control-sm" style="font-size:11px;width:auto;">
                                        <option value="po_minus_receive">PO − PO Receive</option>
                                        <option value="po_minus_payment">PO − Payment</option>
                                        <option value="receive_minus_payment">PO Receive − Payment</option>
                                    </select>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                    {{-- Jalan buntu yang mudah disalahpahami: baris ber-rate 100%
                                         memang sengaja disembunyikan secara bawaan. Selama tabel ini
                                         memuat semua customer, selalu ada baris lain yang tersisa.
                                         Begitu filter Collection mempersempit datanya ke satu
                                         customer yang kebetulan sudah lunas semua, tabelnya kosong
                                         total dan hanya berbunyi "No matching records found" —
                                         seolah datanya tidak ada, padahal hanya disembunyikan. --}}
                                    <div class="js-rate-note alert alert-info m-3 mb-0 py-2" data-for="tblPoSummary"
                                         style="display:none;font-size:11.5px;">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Semua baris pada filter ini sudah <strong>Payment Rate 100%</strong>, dan baris lunas disembunyikan secara bawaan.
                                        Centang <strong>Show Rate 100%</strong> di kanan atas untuk menampilkannya.
                                    </div>
                                    <table id="tblPoSummary" class="table table-sm mb-0 col-pic-table">
                                        <thead>
                                            <tr>
                                                <th scope="col" rowspan="2" class="col-sticky-left" style="vertical-align:middle;border-bottom:2px solid var(--border);">Customer</th>
                                                <th scope="col" rowspan="2" style="vertical-align:middle;border-bottom:2px solid var(--border);">Manager</th>
                                                <th scope="col" colspan="2" class="text-center col-grp-head col-grp-blue">Total PO</th>
                                                <th scope="col" colspan="2" class="text-center col-grp-head col-grp-good">PO Received</th>
                                                <th scope="col" colspan="2" class="text-center col-grp-head col-grp-warn">Waiting PO</th>
                                                <th scope="col" colspan="2" class="text-center col-grp-head col-grp-blue">Payment</th>
                                                <th scope="col" colspan="2" class="text-center col-grp-head col-grp-bad">Variance</th>
                                                <th scope="col" rowspan="2" class="col-sticky-right text-center col-grp-head col-grp-good" style="vertical-align:middle;border-bottom:2px solid var(--success);min-width:110px;">Payment<br>Rate</th>
                                            </tr>
                                            <tr>
                                                <th scope="col" class="col-subhead-blue">Qty</th>
                                                <th scope="col" class="col-subhead-blue">Value</th>
                                                <th scope="col" class="col-subhead-good">Qty</th>
                                                <th scope="col" class="col-subhead-good">Value</th>
                                                <th scope="col" class="col-subhead-warn">Qty</th>
                                                <th scope="col" class="col-subhead-warn">Value</th>
                                                <th scope="col" class="col-subhead-blue">Qty</th>
                                                <th scope="col" class="col-subhead-blue">Value</th>
                                                <th scope="col" class="col-subhead-bad po-var-qty-head">Qty</th>
                                                <th scope="col" class="col-subhead-bad po-var-value-head">Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($poRows as $r)
                                            @php
                                                $pQs = 'customer=' . urlencode($r['customer']) . '&manager=' . urlencode($r['manager']);
                                            @endphp
                                            <tr data-manager="{{ $r['manager'] }}"
                                                data-po-qty="{{ $r['po_qty'] }}"
                                                data-po-value="{{ $r['po_value'] }}"
                                                data-receive-qty="{{ $r['receive_qty'] }}"
                                                data-receive-value="{{ $r['receive_value'] }}"
                                                data-waiting-qty="{{ $r['waiting_qty'] }}"
                                                data-waiting-value="{{ $r['waiting_value'] }}"
                                                data-payment-qty="{{ $r['payment_qty'] }}"
                                                data-payment-value="{{ $r['payment_value'] }}"
                                                data-payment-rate="{{ $r['payment_rate'] }}">
                                                <td class="col-sticky-left" style="font-weight:600;">{{ $r['customer'] }}</td>
                                                <td>{{ $r['manager'] }}</td>
                                                <td class="text-right col-cell-blue col-qty-blue" style="font-weight:700;">{{ $r['po_qty'] }}</td>
                                                <td class="text-right col-cell-blue">
                                                    <a href="{{ $poUrl }}?{{ $pQs }}" target="_blank" class="col-outstanding-link">{{ number_format($r['po_value'], 0, ',', '.') }}</a>
                                                </td>
                                                <td class="text-right col-cell-good" style="font-weight:700;color:var(--success);">{{ $r['receive_qty'] }}</td>
                                                <td class="text-right col-cell-good">
                                                    <a href="{{ $poUrl }}?{{ $pQs }}" target="_blank" class="col-paid-link">{{ number_format($r['receive_value'], 0, ',', '.') }}</a>
                                                </td>
                                                <td class="text-right col-cell-warn" style="font-weight:700;"><span class="col-warn-text">{{ $r['waiting_qty'] }}</span></td>
                                                <td class="text-right col-cell-warn">
                                                    <a href="{{ $poUrl }}?{{ $pQs }}&status=WAITING_PO" target="_blank" class="col-warn-link">{{ number_format($r['waiting_value'], 0, ',', '.') }}</a>
                                                </td>
                                                <td class="text-right col-cell-blue col-qty-blue" style="font-weight:700;">{{ $r['payment_qty'] }}</td>
                                                <td class="text-right col-cell-blue">
                                                    <a href="{{ $poUrl }}?{{ $pQs }}" target="_blank" class="col-outstanding-link">{{ number_format($r['payment_value'], 0, ',', '.') }}</a>
                                                </td>
                                                <td class="text-right po-var-qty col-cell-bad" style="font-weight:700;color:var(--danger);"
                                                    data-po-qty="{{ $r['po_qty'] }}" data-receive-qty="{{ $r['receive_qty'] }}" data-payment-qty="{{ $r['payment_qty'] }}" data-waiting-qty="{{ $r['waiting_qty'] }}"></td>
                                                <td class="text-right col-cell-bad">
                                                    <a href="{{ $poUrl }}?{{ $pQs }}" target="_blank" class="po-var-value-link col-overdue-link"
                                                       data-po-value="{{ $r['po_value'] }}" data-receive-value="{{ $r['receive_value'] }}" data-payment-value="{{ $r['payment_value'] }}" data-waiting-value="{{ $r['waiting_value'] }}"></a>
                                                </td>
                                                <td class="text-center col-cell-good col-sticky-right" data-order="{{ $r['payment_rate'] }}">
                                                    <div class="cp-rate-wrap">
                                                        <div class="cp-rate-bar"><div class="cp-rate-fill js-pct-width" data-width="{{ min(100, $r['payment_rate']) }}"></div></div>
                                                        <span>{{ $r['payment_rate'] }}%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                            @empty
                                            <tr><td colspan="13" class="text-center text-muted py-3">Belum ada data PO.</td></tr>
                                            @endforelse
                                        </tbody>
                                        @if($poRows->isNotEmpty())
                                        <tfoot>
                                            <tr class="col-detail-subtotal">
                                                <td colspan="2" class="col-sticky-left">Grand Total</td>
                                                <td class="text-right po-foot-po-qty">{{ $poGrand['po_qty'] }}</td>
                                                <td class="text-right po-foot-po-value">{{ number_format($poGrand['po_value'], 0, ',', '.') }}</td>
                                                <td class="text-right po-foot-receive-qty">{{ $poGrand['receive_qty'] }}</td>
                                                <td class="text-right po-foot-receive-value">{{ number_format($poGrand['receive_value'], 0, ',', '.') }}</td>
                                                <td class="text-right po-foot-waiting-qty">{{ $poGrand['waiting_qty'] }}</td>
                                                <td class="text-right po-foot-waiting-value">{{ number_format($poGrand['waiting_value'], 0, ',', '.') }}</td>
                                                <td class="text-right po-foot-payment-qty">{{ $poGrand['payment_qty'] }}</td>
                                                <td class="text-right po-foot-payment-value">{{ number_format($poGrand['payment_value'], 0, ',', '.') }}</td>
                                                <td class="text-right po-var-qty"
                                                    data-po-qty="{{ $poGrand['po_qty'] }}" data-receive-qty="{{ $poGrand['receive_qty'] }}" data-payment-qty="{{ $poGrand['payment_qty'] }}" data-waiting-qty="{{ $poGrand['waiting_qty'] }}"></td>
                                                <td class="text-right">
                                                    <span class="po-var-value-link"
                                                        data-po-value="{{ $poGrand['po_value'] }}" data-receive-value="{{ $poGrand['receive_value'] }}" data-payment-value="{{ $poGrand['payment_value'] }}" data-waiting-value="{{ $poGrand['waiting_value'] }}"></span>
                                                </td>
                                                <td class="text-center col-sticky-right po-foot-rate">{{ $poGrandPaymentRate }}%</td>
                                            </tr>
                                        </tfoot>
                                        @endif
                                    </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── PROGRESS SUMMARY (MN-10B) — Customer > PO > No. PO.
                     Tiap tahap (BAST / Partial / Invoice / Paid) menampilkan Qty
                     dan Value. Datanya berasal dari dataset yang SAMA dengan
                     DataTable & Summary Card di atas (P-02), jadi begitu filter
                     Customer/Manager/Bulan dipakai, panel ini ikut menyempit. ── --}}
                <div class="row exec-chart-row">
                    <div class="col-12 exec-chart-col">
                        <div class="card shadow-sm exec-chart-card">
                            <div class="card-header d-flex align-items-center flex-wrap" style="gap:6px;">
                                <h3 class="card-title" style="font-size:13px;font-weight:700;">
                                    <i class="fas fa-tasks mr-2" style="color:var(--info);"></i>Summary Progress
                                </h3>
                                
                                <div class="ml-auto d-flex align-items-center" style="gap:6px;">
                                    <input type="search" id="progSearch" class="form-control form-control-sm"
                                           placeholder="Cari customer / PO / No. PO" style="font-size:11px;width:220px;">
                                    {{-- Kolom bisa disembunyikan seperti tabel lain. Tidak memakai
                                         applyColvis() milik Collection Performance/PO Summary karena
                                         yang itu bersandar pada DataTables (table.column().visible());
                                         tabel ini tabel biasa, jadi selnya disembunyikan langsung dan
                                         colspan header grupnya dihitung ulang. --}}
                                    {{-- Filter Manager lokal — pelengkap filter Collection di atas
                                         (col_manager, server-side). Dua tabel lain di panel ini
                                         punya chip Manager sendiri lewat TesysColumnFilter; tabel
                                         ini bukan DataTable, jadi filternya dibuat manual. --}}
                                {{-- Lima tahap ANTREAN, bukan pencapaian kumulatif: tiap No. PO
                                     jatuh di satu kolom saja - tempat ia sedang tertahan.
                                     Didefinisikan sekali di sini lalu dipakai header, baris
                                     data, dan menu Kolom, supaya menambah/menggeser tahap
                                     tidak perlu disunting di empat tempat yang harus tetap
                                     sinkron. --}}
                                @php
                                    $progGroups = [
                                        /*
                                        | Contract disembunyikan secara bawaan: ia patokan, bukan
                                        | tahap yang sedang dikejar, dan menempati dua kolom paling
                                        | kiri yang mendorong kolom antrean menjauh dari nama PO.
                                        | Tetap tersedia lewat menu Kolom bagi yang perlu
                                        | membandingkan nilai kontraknya.
                                        */
                                        'contract'     => ['label' => 'Contract',        'tone' => null, 'hidden_default' => true],
                                        'wait_po'      => ['label' => 'Waiting PO',      'tone' => 'blue'],
                                        'wait_bast'    => ['label' => 'Waiting BAST',    'tone' => 'good'],
                                        'wait_invoice' => ['label' => 'Waiting Invoice', 'tone' => 'warn'],
                                        // Label "Paid", bukan "Payment": tahap yang ditunggu di sini adalah
                                        // status Paid pada No. PO, dan itu pula sebutannya di
                                        // stepper Purchase Order.
                                        'wait_payment' => ['label' => 'Waiting Paid', 'tone' => 'bad'],
                                        // Bukan antrean, melainkan yang sudah tuntas - ujung alur
                                        // tahapan, dan membuat Contract sama dengan jumlah seluruh
                                        // kolom tahap di kanannya.
                                        'closed'       => ['label' => 'Closed',       'tone' => 'good'],
                                        /*
                                        | VAR dihitung di browser dari dua kolom yang dipilih di
                                        | dropdown, bukan dikirim server: mengganti formula tidak
                                        | perlu memuat ulang halaman, dan angkanya ikut berubah
                                        | saat filter Manager menghitung ulang subtotal. Ditandai
                                        | 'computed' supaya perulangan sel di bawah tahu baris ini
                                        | tidak punya data dari controller.
                                        |
                                        | Ditaruh paling kanan DAN dibekukan ('sticky'): VAR adalah
                                        | angka yang dibandingkan terus-menerus dengan tahap mana
                                        | pun, jadi ia harus tetap terlihat saat kolom tahap di
                                        | kirinya digulir - pola yang sama dengan Payment Rate di
                                        | Summary PO.
                                        */
                                        'var'          => ['label' => 'VAR', 'tone' => 'warn', 'computed' => true, 'sticky' => true],
                                    ];

                                    /*
                                    | Kolom yang boleh jadi operan VAR - semua kecuali VAR itu
                                    | sendiri. Dipakai menyusun atribut data-* di tiap sel VAR.
                                    */
                                    $progVarOperands = ['contract', 'wait_po', 'wait_bast', 'wait_invoice', 'wait_payment', 'closed'];

                                    // Kunci sub-kolom yang selalu mulai tersembunyi saat halaman
                                    // dimuat - diturunkan dari 'hidden_default' supaya daftarnya
                                    // tidak perlu ditulis ulang di JS.
                                    $progColvisSelaluOff = collect($progGroups)
                                        ->filter(fn ($g) => ! empty($g['hidden_default']))
                                        ->keys()
                                        ->flatMap(fn ($k) => [$k . '_qty', $k . '_value'])
                                        ->values();
                                    // Contract & Waiting PO hidup di tingkat PO: yang ditunggu
                                    // justru No. PO yang belum terbit, jadi baris No. PO tidak
                                    // punya angkanya dan menulis 0 di sana akan terbaca sebagai
                                    // "tidak ada sisa", bukan "tidak berlaku".
                                    $progPoLevel = ['contract', 'wait_po'];
                                    $progRp      = fn ($n) => number_format((int) $n, 0, ',', '.');
                                    $progCols    = 1 + count($progGroups) * 2;
                                    // Kelas pembeku kolom kanan, dipasang di SEMUA sel grup yang
                                    // ditandai 'sticky' (header grup, sub-header, tiap tingkat
                                    // baris, dan footer) supaya satu kolom membeku utuh.
                                    $progStick   = fn ($g) => !empty($g['sticky']) ? ' prog-sticky' : '';
                                @endphp
                                    @php
                                        $progManagers = collect($progressRows)
                                            ->flatMap(fn ($c) => collect($c['projects'])->pluck('manager'))
                                            ->filter(fn ($m) => $m !== '' && $m !== '-')
                                            ->unique()->sort()->values();
                                    @endphp
                                    @if($progManagers->isNotEmpty())
                                    <div class="dropdown" id="progMgrFilter">
                                        <button type="button" class="btn btn-sm btn-outline-secondary col-mf-btn" id="progMgrBtn"
                                                data-toggle="dropdown" data-display="static"
                                                aria-haspopup="true" aria-expanded="false">
                                            <span id="progMgrLabel">Manager</span>
                                            <i class="fas fa-caret-down ml-1"></i>
                                        </button>
                                        {{-- Multi-pilih + kotak cari, menyamai filter Manager di
                                             Summary PO. Di sana filternya dibangun
                                             TesysColumnFilter.initRowFilters(), yang bersandar
                                             pada DataTable — tabel ini pohon tiga tingkat dan
                                             bukan DataTable, jadi yang disamakan perilakunya,
                                             bukan kodenya. --}}
                                        <div class="dropdown-menu p-2 prog-mgr-menu" style="font-size:12px;min-width:200px;max-height:320px;overflow-y:auto;">
                                            <input type="text" class="form-control form-control-sm mb-2" id="progMgrSearch"
                                                   placeholder="Cari..." autocomplete="off" style="font-size:12px;">
                                            <div id="progMgrList">
                                                @foreach($progManagers as $mgr)
                                                <label class="dropdown-item m-0 p-1" style="cursor:pointer;">
                                                    <input type="checkbox" class="prog-mgr-check mr-1" value="{{ $mgr }}">{{ $mgr }}
                                                </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                    {{-- Kembali ke data awal. Bentuk & ikonnya sama dengan
                                         tombol Clear Filter di tab Executive, jadi fungsinya
                                         dikenali tanpa dibaca. --}}
                                    <button type="button" id="btnClearProg" class="btn btn-outline-secondary btn-sm"
                                            title="Bersihkan filter" aria-label="Bersihkan filter">
                                        <i class="fas fa-eraser"></i>
                                    </button>
                                    {{-- VAR dihitung ulang di frontend sesuai formula yang
                                         dipilih, pola yang sama dengan PO Summary: tiap sel
                                         membawa angka semua tahap lewat data-*, jadi ganti
                                         formula tidak perlu memuat ulang halaman. --}}
                                    <label class="mb-0" style="font-size:11px;color:var(--text-faint);">VAR:</label>
                                    <select id="progVarFormula" class="form-control form-control-sm" style="font-size:11px;width:auto;">
                                        <option value="contract_minus_closed">Contract &minus; Closed</option>
                                        <option value="contract_minus_wait_po">Contract &minus; Waiting PO</option>
                                        <option value="wait_bast_minus_wait_invoice">Waiting BAST &minus; Waiting Invoice</option>
                                        <option value="wait_invoice_minus_wait_payment">Waiting Invoice &minus; Waiting Paid</option>
                                        <option value="wait_payment_minus_closed">Waiting Paid &minus; Closed</option>
                                    </select>

                                    <div class="dropdown" id="progColvis">
                                        <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-toggle="dropdown" data-display="static">
                                            <i class="fas fa-columns mr-1"></i>Kolom
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-right js-colvis-menu p-2" style="font-size:12px;min-width:200px;max-height:360px;overflow-y:auto;">
                                            @foreach($progGroups as $grpKey => $grpDef)
                                            <div class="text-muted font-weight-bold px-1 {{ $loop->first ? '' : 'mt-1' }}" style="font-size:10px;letter-spacing:.03em;">{{ strtoupper($grpDef['label']) }}</div>
                                            @php $awal = empty($grpDef['hidden_default']) ? 'checked' : ''; @endphp
                                            <label class="dropdown-item m-0 p-1"><input type="checkbox" class="prog-colvis-toggle mr-1" data-c="{{ $grpKey }}_qty" {{ $awal }}>Qty</label>
                                            <label class="dropdown-item m-0 p-1"><input type="checkbox" class="prog-colvis-toggle mr-1" data-c="{{ $grpKey }}_value" {{ $awal }}>Value</label>
                                            @endforeach
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="progExpandAll" style="font-size:11px;">
                                        <i class="fas fa-angle-double-down mr-1"></i>Buka Semua
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                {{-- .col-pic-hscroll = scroller mendatar + kolom kiri membeku.
                                     Dua tabel lain di panel ini mendapatkannya dari JS setelah
                                     DataTables init; tabel ini BUKAN DataTable, jadi bungkusnya
                                     dipasang langsung di markup — tanpa itu tabelnya melebar
                                     keluar kartu. --}}
                                <div class="col-pic-hscroll table-responsive">
                                <table class="table table-sm mb-0 col-pic-table" id="tblProgressSummary">
                                    <thead>
                                        <tr>
                                            <th scope="col" rowspan="2" class="col-sticky-left" style="vertical-align:middle;border-bottom:2px solid var(--border);min-width:280px;">Customer / PO / No. PO</th>
                                            @foreach($progGroups as $gk => $g)
                                            <th scope="col" data-grp="{{ $gk }}" colspan="2"
                                                class="text-center col-grp-head {{ $g['tone'] ? 'col-grp-' . $g['tone'] : '' }}{{ $progStick($g) }}">{{ $g['label'] }}</th>
                                            @endforeach
                                        </tr>
                                        <tr>
                                            @foreach($progGroups as $gk => $g)
                                            <th scope="col" data-c="{{ $gk }}_qty"   class="{{ $g['tone'] ? 'col-subhead-' . $g['tone'] : 'prog-subhead-plain' }}{{ $progStick($g) }}">Qty</th>
                                            <th scope="col" data-c="{{ $gk }}_value" class="{{ $g['tone'] ? 'col-subhead-' . $g['tone'] : 'prog-subhead-plain' }}{{ $progStick($g) }}">Value</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($progressRows as $ci => $cust)
                                            @php $custKey = 'c' . $ci; @endphp
                                            <tr class="prog-row prog-row-customer" data-level="customer" data-key="{{ $custKey }}"
                                                data-text="{{ strtolower($cust['customer']) }}">
                                                <td class="col-sticky-left prog-lv1" style="cursor:pointer;">
                                                    <i class="fas fa-caret-right prog-caret mr-1"></i>{{ $cust['customer'] }}
                                                    <span class="badge badge-secondary ml-1 prog-cust-count" style="font-size:9px;">{{ $cust['po_qty'] }} PO &middot; {{ $cust['po_number_qty'] }} No. PO</span>
                                                </td>
                                                @foreach($progGroups as $gk => $g)
                                                @if(!empty($g['computed']))
                                                <td data-c="{{ $gk }}_qty" class="text-right col-cell-{{ $g['tone'] }} prog-var-qty{{ $progStick($g) }}" style="font-weight:700;"
                                                    @foreach($progVarOperands as $op) data-{{ str_replace('_', '-', $op) }}-qty="{{ $cust[$op . '_qty'] }}" @endforeach>0</td>
                                                <td data-c="{{ $gk }}_value" class="text-right col-cell-{{ $g['tone'] }} prog-var-value{{ $progStick($g) }}"
                                                    @foreach($progVarOperands as $op) data-{{ str_replace('_', '-', $op) }}-value="{{ $cust[$op . '_value'] }}" @endforeach>0</td>
                                                @else
                                                <td data-c="{{ $gk }}_qty" class="text-right {{ $g['tone'] ? 'col-cell-' . $g['tone'] : '' }}{{ $progStick($g) }}" style="font-weight:700;">{{ $cust[$gk . '_qty'] }}</td>
                                                <td data-c="{{ $gk }}_value" class="text-right {{ $g['tone'] ? 'col-cell-' . $g['tone'] : '' }}{{ $progStick($g) }}">{{ $progRp($cust[$gk . '_value']) }}</td>
                                                @endif
                                                @endforeach
                                            </tr>

                                            @foreach($cust['projects'] as $pi => $proj)
                                                @php $projKey = $custKey . 'p' . $pi; @endphp
                                                <tr class="prog-row prog-row-project" data-level="project" data-key="{{ $projKey }}"
                                                    data-parent="{{ $custKey }}" style="display:none;"
                                                    data-manager="{{ $proj['manager'] }}"
                                                    {{-- VAR dilewati: kolomnya dihitung di browser, tidak
                                                         punya angka dari controller untuk dibawa di sini. --}}
                                                    @foreach($progVarOperands as $gk)
                                                    data-{{ str_replace('_', '-', $gk) }}-qty="{{ $proj[$gk . '_qty'] }}" data-{{ str_replace('_', '-', $gk) }}-value="{{ $proj[$gk . '_value'] }}"
                                                    @endforeach
                                                    data-text="{{ strtolower($cust['customer'] . ' ' . $proj['project'] . ' ' . $proj['project_code']) }}">
                                                    <td class="col-sticky-left prog-lv2" style="cursor:pointer;">
                                                        <i class="fas fa-caret-right prog-caret mr-1"></i>
                                                        {{-- Kode PO di DEPAN nama, format sama dengan Todo List:
                                                             [0004] Nama (CUSTOMER - MANAGER). Kode adalah yang
                                                             dipakai orang untuk mencocokkan baris dengan dokumen,
                                                             jadi ia yang dibaca lebih dulu. --}}
                                                        <span class="prog-code">[{{ $proj['project_code'] }}]</span>
                                                        <span class="prog-name">{{ $proj['project'] }}</span>
                                                        <span class="text-muted ml-1" style="font-size:10px;">({{ $proj['manager'] }})</span>
                                                    </td>
                                                    @foreach($progGroups as $gk => $g)
                                                    @if(!empty($g['computed']))
                                                    <td data-c="{{ $gk }}_qty" class="text-right col-cell-{{ $g['tone'] }} prog-var-qty{{ $progStick($g) }}"
                                                        @foreach($progVarOperands as $op) data-{{ str_replace('_', '-', $op) }}-qty="{{ $proj[$op . '_qty'] }}" @endforeach>0</td>
                                                    <td data-c="{{ $gk }}_value" class="text-right col-cell-{{ $g['tone'] }} prog-var-value{{ $progStick($g) }}"
                                                        @foreach($progVarOperands as $op) data-{{ str_replace('_', '-', $op) }}-value="{{ $proj[$op . '_value'] }}" @endforeach>0</td>
                                                    @else
                                                    <td data-c="{{ $gk }}_qty" class="text-right {{ $g['tone'] ? 'col-cell-' . $g['tone'] : '' }}{{ $progStick($g) }}">{{ $proj[$gk . '_qty'] }}</td>
                                                    <td data-c="{{ $gk }}_value" class="text-right {{ $g['tone'] ? 'col-cell-' . $g['tone'] : '' }}{{ $progStick($g) }}">{{ $progRp($proj[$gk . '_value']) }}</td>
                                                    @endif
                                                    @endforeach
                                                </tr>

                                                @forelse($proj['po_numbers'] as $pn)
                                                    <tr class="prog-row prog-row-ponumber" data-level="ponumber"
                                                        data-parent="{{ $projKey }}" style="display:none;"
                                                        data-text="{{ strtolower($cust['customer'] . ' ' . $proj['project'] . ' ' . $pn['po_number']) }}">
                                                        <td class="col-sticky-left prog-lv3">
                                                            <span style="font-family:monospace;font-size:11px;">{{ $pn['po_number'] }}</span>
                                                            <span class="badge ml-1 {{ $pn['status'] === 'PAID' ? 'badge-success' : ($pn['status'] === 'INVOICED' ? 'badge-info' : ($pn['status'] === 'PARTIAL' ? 'badge-warning' : 'badge-secondary')) }}"
                                                                  style="font-size:9px;">{{ $pn['status'] }}</span>
                                                        </td>
                                                        @foreach($progGroups as $gk => $g)
                                                            @php $tone = $g['tone'] ? 'col-cell-' . $g['tone'] : ''; @endphp
                                                            @if(!empty($g['computed']))
                                                            {{-- Baris No. PO tidak punya angka Contract/Waiting PO
                                                                 (keduanya tingkat PO), jadi operan yang tidak
                                                                 berlaku dikirim 0 - VAR-nya tetap terhitung untuk
                                                                 pasangan tahap yang memang ada di baris ini. --}}
                                                            <td data-c="{{ $gk }}_qty" class="text-right {{ $tone }} prog-var-qty{{ $progStick($g) }}"
                                                                @foreach($progVarOperands as $op) data-{{ str_replace('_', '-', $op) }}-qty="{{ $pn[$op . '_qty'] ?? 0 }}" @endforeach>0</td>
                                                            <td data-c="{{ $gk }}_value" class="text-right {{ $tone }} prog-var-value{{ $progStick($g) }}"
                                                                @foreach($progVarOperands as $op) data-{{ str_replace('_', '-', $op) }}-value="{{ $pn[$op . '_value'] ?? 0 }}" @endforeach>0</td>
                                                            @elseif(in_array($gk, $progPoLevel, true))
                                                            <td data-c="{{ $gk }}_qty" class="text-right text-muted {{ $tone }}{{ $progStick($g) }}">&ndash;</td>
                                                            <td data-c="{{ $gk }}_value" class="text-right text-muted {{ $tone }}{{ $progStick($g) }}">&ndash;</td>
                                                            @else
                                                            <td data-c="{{ $gk }}_qty" class="text-right {{ $tone }}{{ $progStick($g) }}">{{ $pn[$gk . '_qty'] ?: '-' }}</td>
                                                            <td data-c="{{ $gk }}_value" class="text-right {{ $tone }}{{ $progStick($g) }}">{{ $pn[$gk . '_value'] ? $progRp($pn[$gk . '_value']) : '-' }}</td>
                                                            @endif
                                                        @endforeach
                                                    </tr>
                                                @empty
                                                    <tr class="prog-row prog-row-ponumber" data-level="ponumber"
                                                        data-parent="{{ $projKey }}" style="display:none;" data-text="">
                                                        <td colspan="{{ $progCols }}" class="text-muted prog-lv3" style="font-size:11px;">Belum ada No. PO terbit.</td>
                                                    </tr>
                                                @endforelse
                                            @endforeach
                                        @empty
                                            <tr><td colspan="{{ $progCols }}" class="text-center text-muted py-3">Tidak ada PO pada filter ini.</td></tr>
                                        @endforelse
                                        <tr id="progNoMatch" style="display:none;">
                                            <td colspan="{{ $progCols }}" class="text-center text-muted py-3">Tidak ada yang cocok dengan pencarian.</td>
                                        </tr>
                                    </tbody>
                                    @if($progressRows->isNotEmpty())
                                    <tfoot>
                                        <tr class="col-detail-subtotal">
                                            <td class="col-sticky-left">Grand Total &mdash; <span id="progGrandCount">{{ $progressTotals['customer_qty'] }} customer &middot; {{ $progressTotals['po_qty'] }} PO &middot; {{ $progressTotals['po_number_qty'] }} No. PO</span></td>
                                            @foreach($progGroups as $gk => $g)
                                            @if(!empty($g['computed']))
                                            <td data-c="{{ $gk }}_qty" class="text-right col-cell-{{ $g['tone'] }} prog-var-qty{{ $progStick($g) }}" style="font-weight:700;"
                                                @foreach($progVarOperands as $op) data-{{ str_replace('_', '-', $op) }}-qty="{{ $progressTotals[$op . '_qty'] }}" @endforeach>0</td>
                                            <td data-c="{{ $gk }}_value" class="text-right col-cell-{{ $g['tone'] }} prog-var-value{{ $progStick($g) }}"
                                                @foreach($progVarOperands as $op) data-{{ str_replace('_', '-', $op) }}-value="{{ $progressTotals[$op . '_value'] }}" @endforeach>0</td>
                                            @else
                                            <td data-c="{{ $gk }}_qty" class="text-right{{ $progStick($g) }}">{{ $progressTotals[$gk . '_qty'] }}</td>
                                            <td data-c="{{ $gk }}_value" class="text-right{{ $progStick($g) }}">{{ $progRp($progressTotals[$gk . '_value']) }}</td>
                                            @endif
                                            @endforeach
                                        </tr>
                                    </tfoot>
                                    @endif
                                </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            @endif

        </div>

    </div>
</div>

{{-- Modal detail Cost Request, dipicu kode CR di kartu Active Cash Advance.
     Partial yang SAMA dipakai Project Monitoring — markup dan JS-nya satu
     tempat, jadi perubahan tampilan detail CR tidak perlu dikerjakan dua kali
     dan tidak bisa melenceng antar halaman. --}}
@include('budget-request._detail_modal')

@endsection

@push('styles')
<style>
/* ── Active Cash Advance: tiga tingkat Manager > Beneficiary > Cost Request ── */
.aca-caret { font-size:10px; transition:transform .15s; display:inline-block; }
/* Tiga tingkat kini berbagi SATU kolom - kolom Manager tersendiri dicabut
   karena isinya cuma terisi di satu dari tiga jenis baris, sehingga dua
   pertiga tabel berupa sel kosong. Hierarkinya sekarang dinyatakan indentasi
   dan garis panduan vertikal, pola yang sama dengan Summary Progress.
   !important dipakai karena tesys-ui-luxe.css memasang padding-left:12px pada
   semua sel tabel; tanpa ini ketiga tingkat tampil rata kiri. */
#tblAca td.aca-lv1 { padding-left:10px !important; font-weight:700; position:relative; }
#tblAca td.aca-lv2 { padding-left:34px !important; font-weight:600; position:relative; }
#tblAca td.aca-lv3 { padding-left:62px !important; color:var(--text-dim); position:relative; }

/* Garis panduan vertikal: baris anak tetap terbaca milik induknya walau
   barisnya panjang dan kolom kirinya tidak lagi mengulang nama manager. */
#tblAca td.aca-lv2::before,
#tblAca td.aca-lv3::before {
    content:''; position:absolute; left:18px; top:0; bottom:0;
    border-left:1px solid var(--border);
}
#tblAca td.aca-lv3::after {
    content:''; position:absolute; left:42px; top:0; bottom:0;
    border-left:1px solid var(--border);
}
.aca-cr-code {
    font-family:monospace; font-size:10.5px; color:var(--text-faint);
    background:var(--surface-2); border:1px solid var(--border);
    border-radius:var(--radius-sm); padding:1px 5px; margin-right:6px;
}
.aca-cr-desc { font-size:11.5px; }

/* Nilai yang bisa diklik. Warnanya dibiarkan mengikuti teks dan baru berubah
   saat disentuh — kolom ini penuh angka, dan membirukan semuanya membuat
   perbandingan nominal jadi sulit dibaca. */
/* Aturannya diambil dari .po-cell-link di modul Purchase Order (lihat
   project/_index-assets.blade.php): angka hitam biasa, berubah jadi chip
   arang saat disorot. Ruang & sudutnya sudah disiapkan sejak keadaan diam
   supaya baris tidak bergeser atau berubah tinggi begitu kursor lewat. */
/* Kekhususan sengaja dinaikkan ke `body.tesys-ui a.…` (0-2-1).
   tesys-ui-compat.css memasang `body.tesys-ui a { color: var(--accent-text) }`
   pada 0-1-1 — seri dengan `a.aca-value-link`, dan yang belakangan menang, jadi
   angkanya tetap merah. .po-cell-link di modul Purchase Order tidak kena
   masalah ini semata karena di sana elemennya <button>, bukan <a>. */
body.tesys-ui a.aca-value-link,
a.aca-value-link {
    display:inline-block; padding:2px 8px; border:0; border-radius:var(--radius-sm);
    background:transparent; font-weight:700; line-height:1.4;
    color:var(--text); text-decoration:none; cursor:pointer;
    transition:background-color .12s ease, color .12s ease;
}
body.tesys-ui a.aca-value-link:hover,
body.tesys-ui a.aca-value-link:focus,
a.aca-value-link:hover, a.aca-value-link:focus {
    background:var(--charcoal); color:#fff; text-decoration:none; outline:none;
}

/* col-pic-table memasang width:auto supaya tabel matriksnya boleh melebar
   melampaui kartu dan digulung mendatar. Tabel ini tidak begitu: lebarnya
   dikunci colgroup justru supaya kolomnya tidak bergeser saat grup dibuka. */
#tblAca.col-pic-table { width:100% !important; font-size:12.5px; }

/* Kolom kiri & Tanggal/Action tidak mewakili satu ukuran berwarna, jadi
   headernya dibiarkan polos - memberi pita warna pada kolom yang bukan
   metrik membuat warnanya berhenti berarti. Garis tegas bawahnya tetap
   dipasang supaya batas blok header lurus satu garis. */
#tblAca thead th.aca-head-plain { border-bottom:2px solid var(--border) !important; }
#tblAca thead th.aca-head-rule-bad { border-bottom:2px solid var(--danger) !important; vertical-align:middle; }
#tblAca thead th.aca-head-rule-good { border-bottom:2px solid var(--success) !important; vertical-align:middle; }

/* Baris data lebih rapat dari bawaan col-pic-table: pohon ini bisa panjang
   (satu baris per CR), dan tinggi baris tabel matriks membuatnya perlu
   digulung jauh hanya untuk melihat dua manager. */
#tblAca.col-pic-table td { padding:7px 12px; }
#tblAca.col-pic-table thead th { padding:6px 12px; }

/* Lebar kolom dikunci.
   Tanpa table-layout:fixed, lebar tiap kolom dihitung ulang dari ISI baris yang
   sedang terlihat — membuka satu PIC memunculkan baris CR dengan deskripsi
   panjang dan badge Action, sehingga seluruh kolom bergeser dan angka yang
   sedang dibandingkan pembacanya pindah tempat. Dengan lebar tetap, melipat
   dan membuka grup tidak lagi menggeser apa pun. */
table.aca-fixed { table-layout:fixed; width:100%; }

/* Konsekuensi table-layout:fixed: teks panjang tidak lagi melebarkan kolomnya,
   jadi deskripsi CR dipotong dengan elipsis (judul lengkapnya tetap terbaca
   lewat tooltip baris) alih-alih membungkus dan meninggikan baris. */
table.aca-fixed td.aca-lv3 { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

/* Kode CR yang bisa diklik. Bentuknya sengaja tetap "chip" seperti kode yang
   tidak bisa diklik — yang membedakan hanya warna teks dan kursor, supaya
   barisnya tidak berubah tinggi saat role yang berbeda membukanya. */
/* PO Reference dipotong elipsis, sama alasannya dengan deskripsi CR: lebar
   kolomnya dikunci colgroup supaya tabel tidak bergeser saat grup dibuka. */
/* Baris yang tersaring keluar. !important-nya disengaja: buka/tutup pohon
   mengatur `display` lewat inline style, dan tanpa ini baris yang sudah
   tersaring akan muncul lagi begitu grupnya dibuka. */
#tblAca tr.aca-filtered-out { display:none !important; }

/* Pilihan PIC yang tidak berlaku untuk PO terpilih. Kelas sendiri, bukan
   atribut `hidden`: atribut itu sudah dipakai kotak cari di dalam menu, dan
   keduanya akan saling menimpa saat dipakai bersamaan. */
#acaFilterBar .col-mf-item.aca-opt-off { display:none !important; }

#tblAca td.aca-po-ref { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:11.5px; }
#tblAca .aca-po-code { font-weight:700; margin-right:4px; }
/* Kode PO yang bisa diklik — warnanya mengikuti teks dan baru menonjol saat
   disentuh, pola yang sama dengan kode CR di kolom sebelahnya. */
body.tesys-ui #tblAca a.aca-po-link { color:var(--text); text-decoration:none; }
body.tesys-ui #tblAca a.aca-po-link:hover,
body.tesys-ui #tblAca a.aca-po-link:focus { color:var(--primary); text-decoration:underline; }
#tblAca .aca-po-name { color:var(--text-dim); }

a.aca-cr-link { color:var(--primary); text-decoration:none; cursor:pointer; }
a.aca-cr-link:hover,
a.aca-cr-link:focus { color:var(--primary); border-color:var(--primary); text-decoration:underline; }

/* Judul kolom yang bisa diurutkan. Dibuat dari <button> supaya bisa difokus
   dan ditekan lewat keyboard, lalu dilucuti tampilan tombolnya agar tetap
   terbaca sebagai judul kolom — yang menandainya bisa diklik adalah ikon di
   kanannya, bukan warna atau bingkai. */
button.aca-sort {
    all:unset; box-sizing:border-box; display:inline-flex; align-items:center;
    white-space:nowrap; cursor:pointer; font:inherit; color:inherit;
}
button.aca-sort:hover .aca-sort-ic { opacity:.85; }
button.aca-sort:focus-visible { outline:2px solid var(--accent); outline-offset:2px; border-radius:var(--radius-sm); }
.aca-sort-ic { margin-left:6px; font-size:10px; opacity:.35; }
/* Kolom yang sedang dipakai mengurutkan — anak panahnya penuh dan berwarna
   aksen, supaya sekali lihat ketahuan tabel ini diurutkan menurut apa. */
.aca-sort-ic.is-on { opacity:1; color:var(--accent); }

/* ── Summary Progress: tiga tingkat Customer > PO > No. PO ──
   Indentasi dulu ditulis sebagai padding-left inline per baris (0/24/48px),
   jadi tidak ada satu tempat pun yang menyatakan "ini hierarki tiga tingkat".
   Di sini jaraknya dinaikkan supaya perbedaan tingkatnya terbaca sekilas, dan
   tiap tingkat dapat garis panduan vertikal supaya anak baris tetap terlihat
   milik induknya walau barisnya panjang. */
/* !important dipakai karena tesys-ui-luxe.css memasang
   `body.tesys-ui .table td { padding-left: 12px !important }` — tanpa ini,
   indentasinya kalah dan ketiga tingkat tampil rata kiri semua. */
/* JANGAN set `position` di sini. Sel ini juga ber-class .col-sticky-left, yang
   membekukan kolom kiri lewat `position:sticky` — dan selektor itu
   (.col-pic-hscroll .col-pic-table td.col-sticky-left) KALAH spesifik dari
   selektor ber-ID di bawah. Menulis `position:relative` di sini pernah membuat
   baris PO & No. PO kehilangan bekunya: teksnya ikut menggulung ke kiri lalu
   terpotong di balik kolom yang masih beku. Garis panduan di bawah tidak
   membutuhkannya — `position:sticky` sudah jadi containing block untuk anak
   yang absolute. */
/* Contract tidak berwarna: ia patokan, bukan salah satu antrean. Memberinya
   pita warna membuat kelima kolom terbaca sederajat, padahal empat kolom
   Waiting adalah bagian-bagian DARI Contract. Garis tegas bawahnya tetap
   dipasang supaya batas blok header lurus satu garis dengan grup berwarna. */
#tblProgressSummary thead th.prog-subhead-plain {
    background:var(--surface-2); border-bottom:2px solid var(--border) !important;
}

#tblProgressSummary .prog-lv1 { padding-left: 10px !important; font-weight: 700; }
#tblProgressSummary .prog-lv2 { padding-left: 34px !important; }
#tblProgressSummary .prog-lv3 { padding-left: 62px !important; }
#tblProgressSummary .prog-lv2 .prog-name { font-weight: 600; }
#tblProgressSummary .prog-lv2 .prog-code { font-weight: 700; margin-right: 4px; }

/* Garis panduan digambar sebagai LATAR sel, bukan elemen absolut.

   Versi absolut (::before/::after dengan top:0;bottom:0) bersandar pada sel
   sebagai containing block — dan sel ini mengandalkan `position:sticky` untuk
   itu. Begitu sticky tidak berlaku (tabel dengan border-collapse di sebagian
   peramban, atau saat seluruh pohon dibuka dan barisnya digambar ulang),
   acuannya melompat ke kartu terdekat: garisnya lalu memanjang menembus
   judul "Summary Progress" sampai ke ujung kartu. Gradien latar tidak punya
   acuan lain selain selnya sendiri, jadi tidak mungkin keluar dari baris. */
#tblProgressSummary .prog-lv2,
#tblProgressSummary .prog-lv3 {
    background-image: linear-gradient(to right,
        transparent 18px, var(--border) 18px, var(--border) 19px, transparent 19px);
    background-repeat: no-repeat;
    background-size: 100% 100%;
}
#tblProgressSummary .prog-lv3 {
    background-image:
        linear-gradient(to right,
            transparent 18px, var(--border) 18px, var(--border) 19px, transparent 19px),
        linear-gradient(to right,
            transparent 42px, var(--border) 42px, var(--border) 43px, transparent 43px);
}
#tblProgressSummary .prog-row-ponumber .col-sticky-left { color: var(--text-dim); }

/* Baris yang tersaring filter Manager. !important-nya disengaja: buka/tutup
   pohon dan kotak pencarian mengatur `display` lewat inline style, dan tanpa
   ini baris yang sudah tersaring akan muncul lagi begitu "Buka Semua" ditekan. */
#tblProgressSummary tr.prog-hidden-mgr { display: none !important; }
</style>
@endpush

@push('styles')
<style>
/* Tab halaman ini memakai gaya tab global (garis bawah, lihat
   tesys-ui-compat.css) — sama seperti Todo List dan halaman lain. Versi
   lamanya berupa bar gelap selebar layar yang ditarik keluar dari padding
   induknya; itu satu-satunya tab bergaya begitu di seluruh aplikasi, jadi
   halamannya terbaca seperti modul asing. Yang tersisa di sini hanya
   penyesuaian jarak. */
#summaryTabs { margin-bottom: 16px; }

#tblExecutive { width:100% !important; }
#tblExecutive, #tblExecutive th, #tblExecutive td { border-color:var(--border) !important; }
#tblExecutive td, #tblExecutive th { vertical-align:middle !important; }

/* Bersih: semua baris putih, tanpa efek hover row (kecuali baris grup customer) */
#tblExecutive tbody tr:not(.exec-group-row),
#tblExecutive tbody tr:not(.exec-group-row):hover { background-color:var(--surface) !important; }
#tblExecutive tbody td { background-color:var(--surface); }

/* Kolom Kode dibekukan saat scroll horizontal */
.exec-hscroll { overflow-x:auto; }
.exec-hscroll #tblExecutive th.exec-col-kode,
.exec-hscroll #tblExecutive td.exec-col-kode {
    position:sticky; left:0; z-index:3; background:var(--surface);
    box-shadow:2px 0 6px -2px rgba(0,0,0,.18);
}
.exec-hscroll #tblExecutive thead th.exec-col-kode { background:var(--surface-2); z-index:4; }

.exec-group-row { cursor:pointer; background:var(--info-bg) !important; }
.exec-group-row:hover { background:var(--surface-3) !important; }
.exec-group-row td { background:transparent !important; }
.exec-caret { transition:transform .15s ease; font-size:11px; color:var(--text-faint); }
.exec-caret.rotated { transform:rotate(90deg); }
td.exec-indent { padding-left:28px !important; }

.exec-grand-total td { background:var(--charcoal) !important; color:#fff !important; border-top:2px solid var(--accent); }
.exec-grand-total .exec-margin-label { color:var(--text-faint); }

.po-mini-badge       { display:inline-flex; align-items:center; justify-content:center; width:18px; height:18px; line-height:1; padding:0; margin:0 1px; font-size:9px; font-weight:700; border-radius:50%; text-align:center; cursor:default; opacity:.92; box-sizing:border-box; border:2px solid transparent; }
.po-mini-badge:hover { opacity:1; transform:scale(1.08); }

.exec-margin-cell  { font-size:11px; line-height:1.5; }
.exec-margin-cell div { display:flex; justify-content:space-between; gap:6px; }
.exec-margin-label { color:var(--text-faint); }

.exec-chart-card .card-body { padding:14px 16px 8px; }
.exec-chart-wrap { position:relative; height:260px; width:100%; }
.exec-chart-wrap canvas { width:100% !important; height:100% !important; }
.exec-code-link, .exec-implement-link {
    display:inline-block; padding:2px 8px; border:0; border-radius:var(--radius-sm);
    background:transparent; font-family:inherit; font-weight:700; font-size:13px; line-height:1.4;
    color:var(--text); text-decoration:none; cursor:pointer;
    transition:background-color .12s ease, color .12s ease;
}
.exec-code-link:hover, .exec-code-link:focus,
.exec-implement-link:hover, .exec-implement-link:focus {
    background:var(--charcoal); color:#fff; text-decoration:none; outline:none;
}
.exec-legend { display:flex; flex-wrap:wrap; gap:6px 14px; margin-top:10px; padding-top:10px; border-top:1px solid var(--border); }
.exec-legend-item { display:flex; align-items:center; gap:6px; font-size:11px; color:var(--text-dim); }
.exec-legend-swatch { width:10px; height:10px; border-radius:2px; flex-shrink:0; }

.exec-chart-col { margin-bottom:16px; display:flex; }
.exec-chart-col .exec-chart-card { width:100%; display:flex; flex-direction:column; }
.exec-chart-col .exec-chart-card .card-body { flex:1 1 auto; }

/* ── Summary Collection ── */
.col-kpi-row { margin-bottom:4px; }
.col-kpi-col { margin-bottom:16px; }
.col-kpi-card { padding:14px 16px 12px; height:100%; position:relative; border-left:3px solid transparent; overflow:hidden; }
.col-kpi-clickable { display:block; color:inherit; text-decoration:none; transition:transform .12s ease, box-shadow .12s ease, border-color .12s ease; }
.col-kpi-clickable:hover { color:inherit; text-decoration:none; transform:translateY(-2px); box-shadow:0 4px 12px rgba(27,27,34,0.1) !important; }
.col-kpi-accent-blue { border-left-color:var(--info); }
.col-kpi-accent-green { border-left-color:var(--success); }
.col-kpi-accent-warn { border-left-color:var(--warning); }
.col-kpi-accent-bad { border-left-color:var(--danger); }
.col-kpi-accent-good { border-left-color:var(--success); }
.col-kpi-accent-purple { border-left-color:var(--purple); }
.col-kpi-head { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
.col-kpi-icon { flex:none; width:22px; height:22px; border-radius:var(--radius-sm); display:flex; align-items:center; justify-content:center; font-size:11px; }
.col-kpi-icon-blue { background:rgba(42,120,214,0.12); color:var(--info); }
.col-kpi-icon-green { background:rgba(27,175,122,0.12); color:var(--success); }
.col-kpi-icon-warn { background:rgba(237,161,0,0.12); color:var(--warning); }
.col-kpi-icon-bad { background:rgba(227,73,72,0.12); color:var(--danger); }
.col-kpi-icon-good { background:rgba(12,163,12,0.12); color:var(--success); }
.col-kpi-icon-purple { background:rgba(111,66,193,0.12); color:var(--purple); }
.col-kpi-label { font-size:10px; font-weight:700; letter-spacing:.03em; text-transform:uppercase; color:var(--text-faint); white-space:nowrap; }
.col-kpi-value { font-size:20px; font-weight:700; letter-spacing:-.01em; line-height:1.2; }
.col-kpi-foot { font-size:10.5px; color:var(--text-faint); margin-top:6px; }
.col-kpi-qty { font-size:12px; font-weight:700; color:var(--text); margin-top:5px; }
.col-kpi-qty-lbl { font-weight:400; color:var(--text-faint); font-size:10.5px; }
.col-kpi-compare { display:flex; align-items:center; flex-wrap:wrap; gap:4px 8px; margin-top:8px; padding-top:6px; border-top:1px dashed var(--border); font-size:10px; line-height:1.3; }
.col-kpi-cmp-item { display:inline-flex; align-items:center; gap:3px; font-weight:700; }
.col-kpi-cmp-item i { font-size:11px; }
.col-kpi-cmp-lbl { color:var(--text-faint); font-weight:400; margin-right:1px; }
.col-kpi-cmp-up   { color:var(--success); }
.col-kpi-cmp-down { color:var(--danger); }
.col-kpi-cmp-neu  { color:var(--text-faint); }
.col-kpi-compare-ref { color:var(--text-faint); font-weight:400; }
.col-kpi-subvalue { font-size:12.5px; font-weight:700; margin-top:6px; }
.col-kpi-subvalue-label { font-size:10.5px; font-weight:400; color:var(--text-faint); }
.col-kpi-track { margin-top:8px; height:4px; border-radius:2px; background:var(--surface-3); overflow:hidden; }
.col-kpi-fill { height:100%; border-radius:2px; }
.col-kpi-fill-warn { background:var(--warning); }
.col-kpi-fill-bad { background:var(--danger); }
.col-kpi-fill-good { background:var(--success); }
.col-kpi-fill-purple { background:var(--purple); }

/* border-collapse:separate + border-spacing:0 — WAJIB supaya position:sticky
   di <th>/<td> jalan konsisten lintas browser (border-collapse:collapse
   bawaan Bootstrap .table membuat sticky column/header gagal nempel). */
/* Dropdown toolbar kartu (Kolom / Manager).
   Semuanya memakai data-display="static" supaya Bootstrap TIDAK memposisikan
   ulang menunya lewat Popper: di dalam card-header yang ikut bergulung, Popper
   menghitung ulang posisi tiap kali dibuka dan sempat membalik ke atas/ke kiri,
   sehingga menunya seperti berpindah-pindah tiap klik. Dengan static, letaknya
   ditentukan CSS relatif terhadap tombolnya dan selalu sama.

   z-index dinaikkan karena tabel di bawahnya membuat konteks penumpukan
   sendiri (position:sticky pada kolom beku) — tanpa ini menu yang tidak lagi
   dipindah ke body akan tertimbun baris tabel. */
.card-header .dropdown-menu { z-index:1080; }
.card-header .dropdown-menu.dropdown-menu-right { right:0; left:auto; }

.col-pic-table { font-size:12px; width:auto !important; border-collapse:separate !important; border-spacing:0; }
.col-pic-table thead th { text-align:center; vertical-align:middle; }
.col-pic-table thead th { font-size:10px; font-weight:700; letter-spacing:.03em; text-transform:uppercase; color:var(--text-faint); border-top:none; padding:8px 12px; white-space:nowrap; }
.col-pic-table td { padding:10px 12px; vertical-align:middle; white-space:nowrap; }

.col-chart-wrap { position:relative; height:240px; width:100%; }
.col-chart-wrap canvas { max-height:240px; width:100% !important; }

.col-donut-row { display:flex; align-items:center; gap:20px; flex-wrap:wrap; }
.col-donut-chart { position:relative; flex:0 0 220px; height:220px; width:220px; }
.col-donut-legend { flex:1 1 auto; display:flex; flex-direction:column; gap:16px; min-width:0; }
.col-donut-legend-item { display:flex; align-items:flex-start; gap:8px; }
.col-donut-swatch { width:12px; height:12px; border-radius:3px; margin-top:3px; flex-shrink:0; }
.col-donut-legend-label { font-size:13px; font-weight:600; color:var(--text); }
.col-donut-legend-pct { color:var(--text-faint); font-weight:500; }
.col-donut-legend-value { font-size:12.5px; color:var(--text-faint); margin-top:2px; }
.col-donut-center { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); text-align:center; pointer-events:none; width:130px; }
.col-donut-center-label { font-size:10px; color:var(--text-faint); text-transform:uppercase; letter-spacing:.02em; }
.col-donut-center-value { font-size:13.5px; font-weight:700; color:var(--text); line-height:1.3; margin-top:3px; }

.col-status-badge { font-size:10px; font-weight:700; padding:3px 10px; border-radius:var(--radius-pill); text-transform:uppercase; letter-spacing:.02em; }
.col-status-critical   { background:var(--danger-bg); color:var(--danger); }
.col-status-escalation { background:var(--warning-bg); color:var(--orange); }
.col-status-priority   { background:var(--warning-bg); color:#c98a00; }
.col-status-follow-up  { background:var(--success-bg); color:var(--success); }
.col-status-lancar     { background:var(--surface-2); color:var(--text-faint); }

.col-rate-cell { gap:8px; }
.col-rate-track { flex:1 1 auto; height:6px; border-radius:var(--radius-pill); background:var(--surface-2); overflow:hidden; }
.col-rate-fill { height:100%; border-radius:var(--radius-pill); width:0; }
.col-rate-fill-good  { background:var(--success); }
.col-rate-fill-warn  { background:var(--warning); }
.col-rate-fill-bad   { background:var(--danger); }
.col-rate-text { font-weight:700; font-size:11.5px; white-space:nowrap; }
.col-rate-text-good  { color:var(--success); }
.col-rate-text-warn  { color:var(--warning); }
.col-rate-text-bad   { color:var(--danger); }
.col-text-bad   { color:var(--danger); font-weight:700; }
.col-overdue-link {
    color:var(--danger); font-weight:700; text-decoration:none; display:inline-block;
    padding:2px 8px; border-radius:var(--radius-sm);
    transition:background-color .12s ease, box-shadow .12s ease;
}
.col-overdue-link:hover {
    color:var(--danger); text-decoration:none; background-color:rgba(227,73,72,0.12);
    box-shadow:0 0 0 1px rgba(227,73,72,0.25);
}

.col-outstanding-link {
    color:var(--info); font-weight:700; text-decoration:none; display:inline-block;
    padding:2px 8px; border-radius:var(--radius-sm);
    transition:background-color .12s ease, box-shadow .12s ease;
}
.col-outstanding-link:hover {
    color:var(--info); text-decoration:none; background-color:rgba(42,120,214,0.12);
    box-shadow:0 0 0 1px rgba(42,120,214,0.25);
}

/* ── Outstanding — kuning, menyesuaikan warna kolomnya sendiri ── */
.col-warn-link {
    color:var(--warning); font-weight:700; text-decoration:none; display:inline-block;
    padding:2px 8px; border-radius:var(--radius-sm);
    transition:background-color .12s ease, box-shadow .12s ease;
}
.col-warn-link:hover {
    color:var(--warning); text-decoration:none; background-color:rgba(197,138,0,0.12);
    box-shadow:0 0 0 1px rgba(197,138,0,0.25);
}
.col-warn-text { color:var(--warning); font-weight:700; }

/* ── Detail collapsible di bawah chart ── */
.col-detail { border-top:1px solid var(--border); }
.col-detail-toggle {
    display:flex; align-items:center; width:100%; border:0; background:var(--surface-2);
    padding:8px 16px; font-size:11px; font-weight:700; letter-spacing:.02em;
    text-transform:uppercase; color:var(--text-faint); cursor:pointer;
}
.col-detail-toggle:hover { background:var(--surface-2); color:var(--text); }
.col-detail-caret { transition:transform .15s ease; font-size:10px; }
.col-detail-toggle[aria-expanded="true"] .col-detail-caret { transform:rotate(180deg); }
.col-detail-tabs { display:flex; flex-wrap:wrap; gap:4px; padding:10px 12px 0; }
.col-detail-tab {
    border:1px solid var(--border); background:var(--surface); border-radius:var(--radius-sm); padding:3px 10px;
    font-size:11px; font-weight:600; color:var(--text-faint); cursor:pointer;
}
.col-detail-tab.active { background:var(--charcoal); border-color:var(--text); color:#fff; }
.col-detail-tab-count { opacity:.65; font-weight:700; margin-left:2px; }
.col-detail-table { font-size:11.5px; }
.col-detail-table thead th {
    font-size:9.5px; font-weight:700; letter-spacing:.03em; text-transform:uppercase;
    color:var(--text-faint); border-top:none; padding:7px 12px; white-space:nowrap;
}
.col-detail-table td { padding:7px 12px; vertical-align:middle; }

/* ── Sortable header + grup header + subtotal ── */
.js-sortable-table th.js-sort { cursor:pointer; user-select:none; position:relative; }
.js-sortable-table th.js-sort:hover { color:var(--text); }
.js-sortable-table th.js-sort::after { content:"\2195"; opacity:.3; margin-left:4px; font-size:9px; }
.js-sortable-table th.js-sort.sort-asc::after { content:"\2191"; opacity:.9; }
.js-sortable-table th.js-sort.sort-desc::after { content:"\2193"; opacity:.9; }
.col-grp-head { font-size:9.5px; font-weight:700; letter-spacing:.03em; text-transform:uppercase; padding:6px 12px; }

/* ── Header pita warna (baris judul grup, mis. "TOTAL INVOICE") — TIDAK ada
   garis tegas di sini; garis dipindah ke baris Qty/Value di bawahnya (lihat
   .col-subhead-*) supaya blok "judul grup + Qty/Value" menyatu sebagai satu
   header, baru dipisah garis tegas sebelum masuk baris data. Specificity
   dinaikkan (.col-pic-table thead th di atas sudah set color:var(--text-faint)) supaya
   warna beneran kepakai. ── */
.col-pic-table thead th.col-grp-good { color:var(--success) !important; background:var(--success-bg) !important; }
.col-pic-table thead th.col-grp-warn { color:var(--warning) !important; background:var(--warning-bg) !important; }
.col-pic-table thead th.col-grp-bad  { color:var(--danger) !important; background:var(--danger-bg) !important; }
.col-pic-table thead th.col-grp-blue { color:var(--info) !important; background:var(--info-bg) !important; }

/* ── Sub-header (baris Qty/Value) — background SAMA dengan header di atasnya
   (satu blok warna menyatu), garis tegas 2px ditaruh di sini sebagai batas
   bawah blok header, sebelum baris data. ── */
.col-pic-table thead th.col-subhead-good { color:var(--success) !important; background:var(--success-bg) !important; border-bottom:2px solid var(--success) !important; }
.col-pic-table thead th.col-subhead-warn { color:var(--warning) !important; background:var(--warning-bg) !important; border-bottom:2px solid #eda100 !important; }
.col-pic-table thead th.col-subhead-bad  { color:var(--danger) !important; background:var(--danger-bg) !important; border-bottom:2px solid var(--danger) !important; }
.col-pic-table thead th.col-subhead-blue { color:var(--info) !important; background:var(--info-bg) !important; border-bottom:2px solid #2a78d6 !important; }

/* ── Data cells — JELAS lebih muda dari header/sub-header (bukan beda tipis) ── */
.col-pic-table td.col-cell-blue { background:var(--info-bg) !important; }
.col-pic-table td.col-cell-good { background:var(--success-bg) !important; }
.col-pic-table td.col-cell-warn { background:var(--warning-bg) !important; }
.col-pic-table td.col-cell-bad  { background:var(--danger-bg) !important; }

/* ── Qty — warna font menyesuaikan warna Value di grup yang sama ── */
.col-qty-blue { color:var(--info); }
.col-qty-good { color:var(--success); }
.col-qty-warn { color:var(--warning); }
.col-qty-bad  { color:var(--danger); }
.col-pic-table thead th[data-col] { white-space:normal; }

/* ── Collection Rate — progress bar ── */
.cp-rate-wrap { display:flex; align-items:center; gap:6px; justify-content:center; }
.cp-rate-bar { width:56px; height:6px; border-radius:3px; background:var(--surface-3); overflow:hidden; flex:0 0 auto; }
.cp-rate-fill { height:100%; background:var(--success); border-radius:3px; }
.cp-rate-wrap span { font-size:11px; font-weight:700; color:var(--success); min-width:30px; text-align:left; }

/* ── Status tindak lanjut, berdasar aging ── */
.cp-status-badge { display:inline-block; padding:3px 10px; border-radius:var(--radius-lg); font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.02em; white-space:nowrap; }
.cp-status-follow-up  { background:var(--info-bg); color:var(--info); }
.cp-status-priority   { background:var(--warning-bg); color:var(--warning); }
.cp-status-escalation { background:var(--danger-bg); color:#c9583a; }
.cp-status-critical   { background:var(--danger); color:#fff; }

/* ── Pita warna per grup 2 kolom (Total Invoice / Paid / Outstanding / Overdue) ──
   Diterapkan lewat data-col / data-grp supaya tak perlu class per sel. */
#tblColManager th[data-grp="1,2"],
#tblColManager th[data-col="1"], #tblColManager th[data-col="2"],
#tblColManager td[data-col="1"], #tblColManager td[data-col="2"] { background:var(--info-bg); }
#tblColManager th[data-grp="3,4"],
#tblColManager th[data-col="3"], #tblColManager th[data-col="4"],
#tblColManager td[data-col="3"], #tblColManager td[data-col="4"] { background:var(--success-bg); }
#tblColManager th[data-grp="5,6"],
#tblColManager th[data-col="5"], #tblColManager th[data-col="6"],
#tblColManager td[data-col="5"], #tblColManager td[data-col="6"] { background:var(--warning-bg); }
#tblColManager th[data-grp="7,8"],
#tblColManager th[data-col="7"], #tblColManager th[data-col="8"],
#tblColManager td[data-col="7"], #tblColManager td[data-col="8"] { background:var(--danger-bg); }

#tblColManager th[data-grp="1,2"], #tblColManager th[data-col="1"], #tblColManager td[data-col="1"] { border-left:2px solid var(--border-strong); }
#tblColManager th[data-grp="1,2"], #tblColManager th[data-col="2"], #tblColManager td[data-col="2"] { border-right:2px solid var(--border-strong); }
#tblColManager th[data-grp="3,4"], #tblColManager th[data-col="3"], #tblColManager td[data-col="3"] { border-left:2px solid var(--border); }
#tblColManager th[data-grp="3,4"], #tblColManager th[data-col="4"], #tblColManager td[data-col="4"] { border-right:2px solid var(--border); }
#tblColManager th[data-grp="5,6"], #tblColManager th[data-col="5"], #tblColManager td[data-col="5"] { border-left:2px solid var(--border-strong); }
#tblColManager th[data-grp="5,6"], #tblColManager th[data-col="6"], #tblColManager td[data-col="6"] { border-right:2px solid var(--border-strong); }
#tblColManager th[data-grp="7,8"], #tblColManager th[data-col="7"], #tblColManager td[data-col="7"] { border-left:2px solid var(--border); }
#tblColManager th[data-grp="7,8"], #tblColManager th[data-col="8"], #tblColManager td[data-col="8"] { border-right:2px solid var(--border); }

#tblColTopCustomer th[data-grp="2,3"],
#tblColTopCustomer th[data-col="2"], #tblColTopCustomer th[data-col="3"],
#tblColTopCustomer td[data-col="2"], #tblColTopCustomer td[data-col="3"] { background:var(--warning-bg); }
#tblColTopCustomer th[data-grp="4,5"],
#tblColTopCustomer th[data-col="4"], #tblColTopCustomer th[data-col="5"],
#tblColTopCustomer td[data-col="4"], #tblColTopCustomer td[data-col="5"] { background:var(--danger-bg); }
#tblColTopCustomer th[data-grp="2,3"], #tblColTopCustomer th[data-col="2"], #tblColTopCustomer td[data-col="2"] { border-left:2px solid var(--border-strong); }
#tblColTopCustomer th[data-grp="2,3"], #tblColTopCustomer th[data-col="3"], #tblColTopCustomer td[data-col="3"] { border-right:2px solid var(--border-strong); }
#tblColTopCustomer th[data-grp="4,5"], #tblColTopCustomer th[data-col="4"], #tblColTopCustomer td[data-col="4"] { border-left:2px solid var(--border); }
#tblColTopCustomer th[data-grp="4,5"], #tblColTopCustomer th[data-col="5"], #tblColTopCustomer td[data-col="5"] { border-right:2px solid var(--border); }
.col-paid-link {
    color:var(--success); font-weight:700; text-decoration:none; display:inline-block;
    padding:2px 8px; border-radius:var(--radius-sm);
    transition:background-color .12s ease, box-shadow .12s ease;
}
.col-paid-link:hover {
    color:var(--success); text-decoration:none; background-color:rgba(12,163,12,0.12);
    box-shadow:0 0 0 1px rgba(12,163,12,0.25);
}
.col-detail-subtotal td {
    background:var(--surface-2); font-weight:700; font-size:11.5px; color:var(--text);
    border-top:2px solid var(--border);
}
.js-colvis-menu { max-height:320px; overflow:auto; }

/* ── Freeze kolom Customer (kiri) & kolom terakhir (kanan) saat scroll
   horizontal — pola SAMA PERSIS dengan project/_index-assets.blade.php
   (.po-hscroll), position:sticky biasa lewat overflow-x:auto pada wrapper,
   BUKAN scrollX/FixedColumns DataTables (itu yang bikin ada celah header
   & kolom kanan ketarik saat kolom disembunyikan). ── */
.col-pic-hscroll { overflow-x:auto; }
.col-pic-hscroll .col-pic-table { width:auto !important; min-width:100%; margin-bottom:0; }

.col-pic-hscroll .col-pic-table th.col-sticky-left,
.col-pic-hscroll .col-pic-table td.col-sticky-left {
    position:sticky; left:0; z-index:3; background:var(--surface);
    box-shadow:2px 0 6px -2px rgba(0,0,0,.18);
}
.col-pic-hscroll .col-pic-table th.col-sticky-right,
.col-pic-hscroll .col-pic-table td.col-sticky-right {
    position:sticky; right:0; z-index:3; background:var(--surface);
    box-shadow:-2px 0 6px -2px rgba(0,0,0,.18);
}
/* `position` DIULANG dengan !important di sini, bukan mubazir. Tema memasang
       body.tesys-ui table.dataTable > thead > tr > th:not(.sorting_disabled) { position:relative }
   (tesys-ui-compat.css & tesys-ui-luxe.css, penjangkar panah urut :before/:after).
   Spesifisitasnya (0,3,5) menang atas aturan sticky di atas (0,3,1), jadi HEADER
   kolom beku balik jadi relative dan ikut tergulir sementara sel tubuhnya tetap
   nempel — kolom Status/Payment Rate lalu menutupi kolom di sebelah kirinya.
   Hanya mengenai tabel DataTables, itu sebabnya Summary Progress (tabel biasa)
   tidak terdampak. Aturan tema TIDAK diubah: ia berlaku untuk semua DataTable di
   aplikasi, dan `position:sticky` tetap jadi containing block bagi panah urutnya. */
.col-pic-hscroll .col-pic-table thead th.col-sticky-left,
.col-pic-hscroll .col-pic-table thead th.col-sticky-right { position:sticky !important; z-index:4; }
.col-pic-hscroll .col-pic-table tfoot td.col-sticky-left,
.col-pic-hscroll .col-pic-table tfoot td.col-sticky-right { background:var(--surface-2); }

/* ── Summary Progress: kolom VAR dibekukan di kanan ──
   Dua kolom bersebelahan (Qty + Value), jadi tidak bisa dua-duanya right:0.
   Offset kanan Qty = lebar kolom Value, diisi JS ke --prog-var-value-w dan
   dihitung ulang tiap kali menu Kolom menyembunyikan salah satunya (kalau Value
   disembunyikan nilainya 0 dan Qty otomatis merapat ke tepi).
   Latar sengaja TIDAK diatur: .col-cell-warn / .col-subhead-warn / .col-grp-warn
   sudah memberi warna tone yang pekat lewat !important. */
#tblProgressSummary .prog-sticky { position:sticky !important; z-index:3; }
#tblProgressSummary thead .prog-sticky { z-index:4; }
#tblProgressSummary [data-c="var_value"].prog-sticky { right:0; }
#tblProgressSummary [data-c="var_qty"].prog-sticky   { right:var(--prog-var-value-w, 0px); }
#tblProgressSummary thead th[data-grp="var"].prog-sticky { right:0; }
/* Bayangan hanya di sisi terluar kiri dari blok beku, supaya tidak muncul garis
   bayangan di antara Qty dan Value. Kelasnya dipindah JS mengikuti kolom mana
   yang sedang jadi paling kiri. */
#tblProgressSummary .prog-sticky-edge { box-shadow:-2px 0 6px -2px rgba(0,0,0,.18); }

/* ── Filter multi-pilih (tombol-dropdown checklist) ── */
.col-mf { position:relative; display:inline-block; }
.col-mf-btn { display:inline-flex; align-items:center; gap:6px; font-weight:600; white-space:nowrap; }
/* Warna aktif mengikuti aksen aplikasi (var(--accent)), bukan biru lepas.
   Biru #2563EB di sini adalah satu-satunya di seluruh aplikasi yang memakai
   warna merek lain untuk keadaan aktif — halaman ini jadi terbaca seperti
   modul asing. */
.col-mf-btn.col-mf-active { background:var(--accent); color:#fff; border-color:var(--accent); }
.col-mf-btn.col-mf-active:hover { background:var(--accent-dark); color:#fff; }
.col-mf-badge { display:inline-flex; align-items:center; justify-content:center; min-width:16px; height:16px; padding:0 4px; border-radius:var(--radius-pill); background:var(--accent); color:#fff; font-size:10px; font-weight:700; line-height:1; }
.col-mf-btn.col-mf-active .col-mf-badge { background:rgba(255,255,255,.28); }
.col-mf-menu {
    position:absolute; z-index:3000; top:calc(100% + 4px); left:0; background:var(--surface);
    border:1px solid var(--border); border-radius:var(--radius-sm); box-shadow:0 6px 18px rgba(0,0,0,.12);
    padding:8px; width:max-content; min-width:230px; max-width:360px;
    display:flex; flex-direction:column;
}
.col-mf-list { overflow-y:auto; overflow-x:hidden; max-height:230px; margin-top:6px; }
.col-mf-item {
    display:flex; align-items:center; gap:7px; font-size:12px; font-weight:400;
    cursor:pointer; padding:4px 6px; border-radius:var(--radius-sm); margin:0; white-space:nowrap;
}
.col-mf-item:hover { background:var(--info-bg); }
.col-mf-item span { overflow:hidden; text-overflow:ellipsis; }
</style>
@endpush

@push('scripts')
<script>
/* Halaman ini selalu dibuka dari atas.
   Browser mengembalikan posisi gulir terakhir saat halaman dimuat ulang
   (history.scrollRestoration bawaan 'auto'). Di halaman sepanjang ini efeknya
   membuka Summary langsung di tengah tabel paling bawah, seolah tab yang dibuka
   bukan yang dipilih. Dimatikan sebelum $(function) supaya berlaku juga saat
   browser memulihkan posisi sebelum skrip lain jalan.

   ?progress= dikecualikan: deep-link dari Daftar Purchase Order memang menggulir
   ke baris PO yang dituju (lihat blok deep-link di bawah). */
if ('scrollRestoration' in history && !new URLSearchParams(window.location.search).get('progress')) {
    history.scrollRestoration = 'manual';
    window.scrollTo(0, 0);
}

$(function () {
    $('.js-pct-width').each(function () {
        $(this).css('width', $(this).data('width') + '%');
    });

    /* ── Filter Active Cash Advance (PIC & Aging) ──
       Menyaring di browser, tanpa memuat ulang. Memakai komponen _multi-filter
       yang sama dengan filter Collection, tapi penanganannya dipasang terpisah:
       handler milik Collection terikat ke #summaryPaneCollection DAN berada di
       blok skrip yang digerbang role Collection, sehingga role yang hanya
       melihat tab Budget tidak akan mendapatkannya sama sekali. */
    var $acaBar = $('#acaFilterBar');

    var ACA_EMBER = @json(\App\Http\Controllers\FinanceSummaryController::ACA_AGING_BUCKETS);

    function acaPilihan(name) {
        return $acaBar.find('.col-mf[data-name="' + name + '"] .col-mf-list input:checked')
            .map(function () { return this.value; }).get();
    }

    function acaRp(n) { return Number(n || 0).toLocaleString('id-ID'); }

    function acaCocokAging(baris, ember) {
        if (ember.length === 0) return true;

        var umur = $(baris).attr('data-aging');
        // paid_at kosong = umurnya tidak diketahui; tidak bisa diklaim masuk
        // ember mana pun, jadi ikut tersaring keluar.
        if (umur === '' || umur === undefined) return false;
        umur = parseInt(umur, 10);

        for (var i = 0; i < ember.length; i++) {
            var e = ACA_EMBER[ember[i]];
            if (!e) continue;
            if (umur >= e.min && (e.max === null || umur <= e.max)) return true;
        }
        return false;
    }

    /* Pilihan PIC menyusut mengikuti PO yang sedang dipilih: menawarkan PIC yang
       tidak memegang satu pun CA di PO itu berarti menawarkan pilihan yang pasti
       mengosongkan tabel. PIC yang terlanjur tercentang lalu jadi tidak berlaku
       ikut dilepas — kalau dibiarkan, ia menyaring diam-diam dari balik daftar
       yang sudah tidak menampilkannya. */
    function acaSelaraskanPic(po) {
        var sah = {};
        $('#tblAca tbody tr.aca-cr-row').each(function () {
            var $r = $(this);
            if (po.length === 0 || po.indexOf($r.attr('data-po')) !== -1) {
                sah[$r.attr('data-pic')] = true;
            }
        });

        var $mf = $acaBar.find('.col-mf[data-name="aca_pic"]');
        $mf.find('.col-mf-item').each(function () {
            var cb = $(this).find('input')[0];
            var berlaku = po.length === 0 || sah[cb.value] === true;
            $(this).toggleClass('aca-opt-off', !berlaku);
            if (!berlaku && cb.checked) cb.checked = false;
        });

        var n = $mf.find('.col-mf-list input:checked').length;
        $mf.find('.col-mf-badge').text(n).prop('hidden', n === 0);
        $mf.find('.col-mf-btn').toggleClass('col-mf-active', n > 0);
    }

    function acaTerapkanFilter() {
        var po    = acaPilihan('aca_po');
        acaSelaraskanPic(po);

        var pic   = acaPilihan('aca_pic');
        var ember = acaPilihan('aca_aging');
        var aktif = pic.length > 0 || po.length > 0 || ember.length > 0;

        var grand = { qty: 0, val: 0 };
        var perGrup = {}, perBen = {};

        $('#tblAca tbody tr.aca-cr-row').each(function () {
            var $r = $(this);
            var lolos = (pic.length === 0 || pic.indexOf($r.attr('data-pic')) !== -1)
                && (po.length === 0 || po.indexOf($r.attr('data-po')) !== -1)
                && acaCocokAging(this, ember);

            $r.toggleClass('aca-filtered-out', !lolos);
            if (!lolos) return;

            var g = $r.attr('data-grp'), b = $r.attr('data-ben');
            var nilai = parseInt($r.attr('data-amount'), 10) || 0;

            perGrup[g] = perGrup[g] || { qty: 0, val: 0 };
            perBen[b]  = perBen[b]  || { qty: 0, val: 0 };
            perGrup[g].qty++; perGrup[g].val += nilai;
            perBen[b].qty++;  perBen[b].val  += nilai;
            grand.qty++;      grand.val      += nilai;
        });

        // Baris PIC & Manager ikut hilang kalau tidak menyisakan satu CR pun —
        // menyisakannya bernilai 0 akan terbaca seperti PIC yang kebetulan tidak
        // punya CA, padahal ia memang sedang disaring keluar.
        $('#tblAca tbody tr.aca-ben-row').each(function () {
            var $r = $(this);
            var d = perBen[$r.attr('data-target')] || { qty: 0, val: 0 };
            $r.toggleClass('aca-filtered-out', d.qty === 0);
            $r.find('.aca-qty').text(d.qty);
            $r.find('.aca-val').text(acaRp(d.val));
        });

        $('#tblAca tbody tr.aca-group-row').each(function () {
            var $r = $(this);
            var d = perGrup[$r.attr('data-grp')] || { qty: 0, val: 0 };
            $r.toggleClass('aca-filtered-out', d.qty === 0);
            $r.find('.aca-qty').text(d.qty);
            $r.find('.aca-val').text(acaRp(d.val));
        });

        $('#tblAca tfoot .aca-qty').text(grand.qty);
        $('#tblAca tfoot .aca-val').text(acaRp(grand.val));


        /* Filter aktif -> pohonnya langsung dibuka sampai baris CR. Hasil
           saringan selalu sedikit, dan menyisakannya terlipat berarti pengguna
           memilih PIC lalu hanya melihat baris manager — yang dicarinya justru
           PIC itu sendiri. Dilepas lagi saat filter dibersihkan. */
        acaBukaSemua(aktif);
    }

    function acaTutupMenu() { $acaBar.find('.col-mf-menu').prop('hidden', true); }

    $acaBar.on('click', '.col-mf-btn', function (e) {
        var $menu = $(this).siblings('.col-mf-menu');
        if ($menu.length === 0) return;
        e.stopPropagation();
        var willOpen = $menu.prop('hidden');
        acaTutupMenu();
        $menu.prop('hidden', !willOpen);
        if (willOpen) $menu.find('.col-mf-search').val('').trigger('input').trigger('focus');
    });

    $acaBar.on('click', '.col-mf-menu', function (e) { e.stopPropagation(); });
    $(document).on('click.acamf', acaTutupMenu);

    $acaBar.on('input', '.col-mf-search', function () {
        var q = this.value.toLowerCase();
        $(this).siblings('.col-mf-list').find('.col-mf-item').each(function () {
            this.hidden = this.textContent.toLowerCase().indexOf(q) === -1;
        });
    });

    // Tanpa tombol Apply: tabel menyesuaikan di detik yang sama kotak dicentang.
    $acaBar.on('change', '.col-mf-menu input[type=checkbox]', function () {
        var $mf = $(this).closest('.col-mf');
        var n = $mf.find('.col-mf-list input:checked').length;
        $mf.find('.col-mf-badge').text(n).prop('hidden', n === 0);
        $mf.find('.col-mf-btn').toggleClass('col-mf-active', n > 0);
        acaTerapkanFilter();
    });

    $('#acaFilterReset').on('click', function () {
        $acaBar.find('.col-mf-list input:checked').prop('checked', false);
        $acaBar.find('.col-mf-badge').text(0).prop('hidden', true);
        $acaBar.find('.col-mf-btn').removeClass('col-mf-active');
        acaTerapkanFilter();
    });

    /* Active Cash Advance — tiga lapis: Manager > Beneficiary > Cost Request.
       Menutup Manager WAJIB ikut menutup baris CR di dalamnya; kalau tidak,
       baris CR menggantung tanpa induk yang terlihat. */
    function acaCaret($row, expanding) {
        $row.find('.aca-caret').css('transform', expanding ? 'rotate(90deg)' : 'none');
    }

    /* JANGAN pakai :hidden di sini. Kartu ini berada di dalam tab pane, dan
       :hidden ikut bernilai true begitu SALAH SATU induknya tersembunyi —
       sehingga saat tabnya belum aktif, setiap klik selalu dibaca "membuka" dan
       barisnya tidak pernah bisa ditutup. display miliknya sendiri tidak
       terpengaruh induk, jadi itu yang dipakai. */
    function acaTertutup($rows) {
        return $rows.first().css('display') === 'none';
    }

    $(document).on('click', '.aca-group-row', function () {
        var gi = String($(this).data('target')).replace('aca-grp-', '');
        var $ben = $('.aca-grp-' + gi);
        var expanding = acaTertutup($ben);

        $ben.toggle(expanding);
        if (!expanding) {
            // Tutup juga lapis CR-nya, dan kembalikan caret beneficiary.
            $('.aca-allcr-' + gi).hide();
            acaCaret($ben, false);
        }
        acaCaret($(this), expanding);
    });

    $(document).on('click', '.aca-ben-row', function () {
        var $rows = $('.' + $(this).data('target'));
        var expanding = acaTertutup($rows);
        $rows.toggle(expanding);
        acaCaret($(this), expanding);
    });

    /* ── Active Cash Advance: penyortiran di layar, tanpa memuat ulang ──
       Barisnya DIPINDAH, bukan dibuat ulang: manager yang sedang dibuka tetap
       terbuka karena `display` dan putaran caret melekat pada elemen yang sama.
       Membangun ulang HTML-nya akan mengembalikan semuanya ke keadaan tertutup
       setiap kali diklik — persis yang membuat versi sebelumnya (yang memuat
       ulang halaman) tidak enak dipakai. */
    (function () {
        var $tbl = $('#tblAca');
        if (!$tbl.length) { return; }

        var $tbody = $tbl.find('tbody');
        /* Urutan awal ditentukan server (bawaan: nilai terbesar). Ditulis di
           sini supaya klik pertama pada CA Value langsung membalik, bukan
           mengulang urutan yang sudah tampak. */
        var kunci = 'value';
        var turun = true;

        function angka($row) { return parseInt($row.attr('data-sort-value'), 10) || 0; }
        function nama($row)  { return ($row.attr('data-sort-name') || '').toLowerCase(); }

        /* localeCompare dengan sensitivity 'base': nickname ditulis campur
           huruf besar-kecil ("Abe", "DEVASYA"), dan perbandingan biasa menaruh
           SEMUA huruf besar sebelum huruf kecil — "DEVASYA" akan mendahului
           "Abe" pada A→Z. Itu bukan urutan abjad yang dimaksud siapa pun.
           Aturan yang sama dipakai server (SORT_NATURAL|SORT_FLAG_CASE). */
        function banding($a, $b) {
            var hasil = (kunci === 'name')
                ? nama($a).localeCompare(nama($b), 'id', { sensitivity: 'base', numeric: true })
                : angka($a) - angka($b);
            return turun ? -hasil : hasil;
        }

        function urutkan() {
            /* Lapis MANAGER tidak ikut diurutkan — urutannya tetap seperti
               dirender server (nilai terbesar). Yang dibandingkan orang di
               kartu ini adalah penerima dananya; manager cuma pengelompokan.
               Kalau bloknya ikut berpindah, manager yang sedang dibuka
               melompat ke tempat lain tiap kali urutan diubah. */
            var $manajer = $tbody.children('tr.aca-group-row').get();

            $.each($manajer, function (_, mgr) {
                var $mgr = $(mgr);
                var gi   = String($mgr.data('target')).replace('aca-grp-', '');

                var $ben = $tbody.children('tr.aca-ben-row.aca-grp-' + gi).get();
                $ben.sort(function (a, b) { return banding($(a), $(b)); });

                // Manager dipindah ke bawah, lalu anak-anaknya menyusul tepat
                // di belakangnya — itu yang menjaga blok tetap utuh.
                $tbody.append($mgr);

                $.each($ben, function (__, ben) {
                    var $b  = $(ben);
                    var cls = String($b.data('target'));

                    var $cr = $tbody.children('tr.' + cls).get();
                    $cr.sort(function (a, b) { return banding($(a), $(b)); });

                    $tbody.append($b);
                    $.each($cr, function (___, cr) { $tbody.append(cr); });
                });
            });
        }

        function perbaruiIkon() {
            $tbl.find('.aca-sort').each(function () {
                var aktif = $(this).data('aca-sort') === kunci;
                var arah  = aktif ? (turun ? '-down' : '-up') : '';

                $(this).find('.aca-sort-ic')
                    .attr('class', 'fas fa-sort' + arah + ' aca-sort-ic' + (aktif ? ' is-on' : ''));
                $(this).attr('aria-sort', aktif ? (turun ? 'descending' : 'ascending') : 'none');
            });
        }

        $tbl.on('click', '.aca-sort', function () {
            var pilih = $(this).data('aca-sort');

            if (pilih === kunci) {
                turun = !turun;
            } else {
                kunci = pilih;
                // Bawaan tiap kolom: nama mulai dari A→Z, nilai dari terbesar.
                turun = (pilih === 'value');
            }

            urutkan();
            perbaruiIkon();
        });

        perbaruiIkon();
    })();

    /* Satu fungsi untuk tombol Buka/Tutup Semua DAN untuk pembukaan otomatis
       saat filter aktif, supaya label tombolnya tidak pernah berbohong tentang
       keadaan pohon. */
    function acaBukaSemua(membuka) {
        $('.aca-group-row, .aca-ben-row').each(function () {
            $('.' + $(this).data('target')).toggle(membuka);
            acaCaret($(this), membuka);
        });

        $('#acaExpandAll').data('state', membuka ? 'open' : 'closed')
            .html(membuka
                ? '<i class="fas fa-angle-double-up mr-1"></i>Tutup Semua'
                : '<i class="fas fa-angle-double-down mr-1"></i>Buka Semua');
    }

    $('#acaExpandAll').on('click', function () {
        acaBukaSemua($(this).data('state') !== 'open');
    });

    /* PO Summary — VAR dihitung ulang di frontend sesuai formula dropdown,
       tanpa reload (data Qty/Value tiap baris sudah dikirim lewat data-*). */
    var poVarLabels = {
        po_minus_receive:      { a: 'po', b: 'receive' },
        po_minus_payment:      { a: 'po', b: 'payment' },
        receive_minus_payment: { a: 'receive', b: 'payment' }
    };
    // window.poFooterSyncing: guard supaya rows().invalidate().draw(false) di bawah
    // tidak memicu ulang handler 'draw' footer di block script lain (lihat
    // recalcPoSummaryFooter, dibind ke #tblPoSummary di initCollectionTables) →
    // infinite loop. Ditaruh di window (bukan var lokal) karena recalcPoVar()
    // didefinisikan di sini tapi dipanggil lagi dari closure/script tag lain.
    window.poFooterSyncing = false;
    function recalcPoVar() {
        var f = poVarLabels[$('#poVarFormula').val()];
        $('.po-var-qty').each(function () {
            var d = $(this).data();
            $(this).text((parseInt(d[f.a + 'Qty']) || 0) - (parseInt(d[f.b + 'Qty']) || 0));
        });
        $('.po-var-value-link').each(function () {
            var d = $(this).data();
            var v = (parseInt(d[f.a + 'Value']) || 0) - (parseInt(d[f.b + 'Value']) || 0);
            $(this).text(v.toLocaleString('id-ID'));
        });
        // DataTables cache nilai sel saat init — invalidate supaya sort ikut
        // formula VAR terbaru (bukan nilai lama saat halaman pertama dimuat).
        if ($.fn.dataTable.isDataTable('#tblPoSummary') && !window.poFooterSyncing) {
            $('#tblPoSummary').DataTable().rows().invalidate().draw(false);
        }
    }
    window.recalcPoVar = recalcPoVar;
    $('#poVarFormula').on('change', recalcPoVar);
    if ($('#poVarFormula').length) recalcPoVar();
});
</script>

@if($canViewExecutive || $canViewCollection)
<script src="{{ asset('plugins/chart.js/Chart.min.js') }}"></script>
@endif

@if($canViewExecutive)
<script>
$(function () {
    /* ── CHARTS (lazy-init: tab Executive bisa non-aktif saat load, canvas
       hidden bakal ke-render 0x0 kalau Chart.js langsung jalan) ── */
    /* Setiap warna grafik WAJIB lewat W(). Chart.js menggambar ke <canvas>,
       dan canvas tidak mengenal var(--…): string token yang dikirim apa
       adanya gagal diurai dan jatuh ke HITAM, tanpa peringatan apa pun di
       konsol. W() menyelesaikan token jadi nilai sebenarnya, dan
       meneruskan warna biasa tanpa diubah. */
    var W = window.tesysWarna || function (v) { return v; };

    var palette = ['var(--info)', '#eb6834', '#1baf7a', 'var(--warning)', '#e87ba4', '#008300', '#4a3aa7', 'var(--danger)'].map(W);
    var statusPalette = { DRAFT: palette[0], SUBMITTED: palette[1], WAITING_PO: palette[2], ACTIVE: palette[3], PROGRESS_DONE: palette[4], PARTIALLY_INVOICED: palette[4], PARTIALLY_PAID: palette[5], PAID: palette[5], CLOSING: palette[6], CLOSED: palette[7] };
    var statusColorFor = { good: W('var(--success)'), warning: '#fab219', neutral: '#c3c2b7' };
    var inkSecondary = '#52514E';
    var gridColor = '#E1E0D9';

    Chart.defaults.global.defaultFontFamily = "'Nunito', -apple-system, 'Segoe UI', sans-serif";
    Chart.defaults.global.defaultFontColor = inkSecondary;
    Chart.defaults.global.defaultFontSize = 11;

    var chartDataEl = document.getElementById('execChartData');
    var statusData = JSON.parse(chartDataEl.dataset.status);
    var chartStatus = null;

    function fmtRp(v) {
        return Number(v || 0).toLocaleString('id-ID');
    }

    function renderStatusChart() {
        var showClosed = $('#chkShowClosedChart').is(':checked');
        var data = statusData.filter(function (s) { return showClosed || s.status !== 'CLOSED'; });

        if (chartStatus) { chartStatus.destroy(); chartStatus = null; }
        if (!data.length) { $('#legendStatus').html(''); return; }

        chartStatus = new Chart(document.getElementById('chartStatus').getContext('2d'), {
            type: 'bar',
            data: {
                labels: data.map(function (s) { return s.label; }),
                datasets: [{
                    data: data.map(function (s) { return s.value; }),
                    backgroundColor: data.map(function (s) { return statusPalette[s.status] || palette[0]; }),
                    borderRadius: 4,
                    barPercentage: 0.6
                }]
            },
            options: {
                legend: { display: false },
                tooltips: { callbacks: { label: function (item) { return fmtRp(item.yLabel); } } },
                scales: {
                    xAxes: [{ gridLines: { display: false }, ticks: { autoSkip: false } }],
                    yAxes: [{ gridLines: { color: gridColor, drawBorder: false }, ticks: {
                        beginAtZero: true,
                        callback: function (v) { return v >= 1000000 ? (v / 1000000) + 'jt' : v; }
                    } }]
                }
            }
        });

        var legendStatusHtml = '';
        data.forEach(function (s) {
            legendStatusHtml += '<div class="exec-legend-item"><span class="exec-legend-swatch" style="background:' + (statusPalette[s.status] || palette[0]) + ';"></span>' + s.label + '</div>';
        });
        $('#legendStatus').html(legendStatusHtml);
    }

    var progressData = JSON.parse(chartDataEl.dataset.progress);
    var progressLabels = ['R', 'B', 'I', 'P'];
    var progressBuckets = [
        { key: 'Not Started', color: statusColorFor.neutral },
        { key: 'Partial',     color: statusColorFor.warning },
        { key: 'Done',        color: statusColorFor.good }
    ];

    function renderProgressChart() {
        new Chart(document.getElementById('chartProgress').getContext('2d'), {
            type: 'bar',
            data: {
                labels: progressLabels,
                datasets: progressBuckets.map(function (b) {
                    return {
                        label: b.key,
                        data: progressLabels.map(function (l) { return progressData[l][b.key] || 0; }),
                        backgroundColor: b.color,
                        borderRadius: 3
                    };
                })
            },
            options: {
                legend: { display: false },
                tooltips: { callbacks: { label: function (item, data) { return data.datasets[item.datasetIndex].label + ': ' + item.yLabel + ' PO'; } } },
                scales: {
                    xAxes: [{ stacked: true, gridLines: { display: false } }],
                    yAxes: [{ stacked: true, gridLines: { color: gridColor, drawBorder: false }, ticks: { beginAtZero: true, precision: 0 } }]
                }
            }
        });
        var legendProgressHtml = '';
        progressBuckets.forEach(function (b) {
            legendProgressHtml += '<div class="exec-legend-item"><span class="exec-legend-swatch" style="background:' + b.color + ';"></span>' + b.key + '</div>';
        });
        $('#legendProgress').html(legendProgressHtml);
    }

    var customerData = JSON.parse(chartDataEl.dataset.customers);
    var chartCustomers = null;

    function renderCustomerChart() {
        var metric = $('#selCustomerMetric').val();
        var sorted = customerData.slice().sort(function (a, b) { return Math.abs(b[metric]) - Math.abs(a[metric]); }).slice(0, 8);

        if (chartCustomers) { chartCustomers.destroy(); chartCustomers = null; }
        if (!sorted.length) { $('#legendCustomers').html(''); return; }

        chartCustomers = new Chart(document.getElementById('chartCustomers').getContext('2d'), {
            type: 'pie',
            data: {
                labels: sorted.map(function (c) { return c.customer_initial; }),
                datasets: [{
                    data: sorted.map(function (c) { return Math.max(c[metric], 0); }),
                    backgroundColor: sorted.map(function (c, i) { return palette[i % palette.length]; }),
                    borderWidth: 1,
                    borderColor: '#fff'
                }]
            },
            options: {
                legend: { display: false },
                tooltips: { callbacks: {
                    title: function (items) { return sorted[items[0].index].customer; },
                    label: function (item, data) { return fmtRp(data.datasets[0].data[item.index]); }
                } }
            }
        });

        var legendHtml = '';
        sorted.forEach(function (c, i) {
            legendHtml += '<div class="exec-legend-item"><span class="exec-legend-swatch" style="background:' + palette[i % palette.length] + ';"></span>' + c.customer_initial + '</div>';
        });
        $('#legendCustomers').html(legendHtml);
    }

    var revenueData = JSON.parse(chartDataEl.dataset.revenue);
    var revenueBreakdown = JSON.parse(chartDataEl.dataset.revenueBreakdown || '{}');
    var revenueYears = Object.keys(revenueData).sort().reverse();
    var monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    var chartRevenue = null;

    function breakdownLines(breakdownData, year, monthIndex, totalLabel, totalValue) {
        var month = monthIndex + 1;
        var entries = (breakdownData[year] && breakdownData[year][month]) || [];
        var lines = [totalLabel + ': ' + fmtRp(totalValue)];
        entries.forEach(function (e) { lines.push(e.label + ': ' + fmtRp(e.value)); });
        return lines;
    }

    function renderRevenueChart() {
        var year = $('#selRevenueYear').val();
        var data = revenueData[year] || new Array(12).fill(0);

        if (chartRevenue) { chartRevenue.destroy(); chartRevenue = null; }

        chartRevenue = new Chart(document.getElementById('chartRevenue').getContext('2d'), {
            type: 'line',
            data: {
                labels: monthLabels,
                datasets: [{
                    label: 'Revenue Value',
                    data: data,
                    borderColor: palette[0],
                    backgroundColor: 'rgba(42,120,214,0.12)',
                    pointBackgroundColor: palette[0],
                    pointRadius: 3,
                    lineTension: 0.35,
                    fill: true,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                tooltips: { callbacks: {
                    label: function (item) { return breakdownLines(revenueBreakdown, year, item.index, 'Total', item.yLabel); }
                } },
                scales: {
                    xAxes: [{ gridLines: { display: false } }],
                    yAxes: [{ gridLines: { color: gridColor, drawBorder: false }, ticks: {
                        beginAtZero: true,
                        callback: function (v) { return v >= 1000000 ? (v / 1000000) + 'jt' : v; }
                    } }]
                }
            }
        });
    }

    var cashInData = JSON.parse(chartDataEl.dataset.cashin);
    var cashOutData = JSON.parse(chartDataEl.dataset.cashout);
    var cashInBreakdown = JSON.parse(chartDataEl.dataset.cashinBreakdown || '{}');
    var cashOutBreakdown = JSON.parse(chartDataEl.dataset.cashoutBreakdown || '{}');
    var treasuryYears = Array.from(new Set(Object.keys(cashInData).concat(Object.keys(cashOutData)))).sort().reverse();
    var chartTreasury = null;

    function renderTreasuryChart() {
        var year = $('#selTreasuryYear').val();
        var cashIn = cashInData[year] || new Array(12).fill(0);
        var cashOut = cashOutData[year] || new Array(12).fill(0);

        if (chartTreasury) { chartTreasury.destroy(); chartTreasury = null; }

        chartTreasury = new Chart(document.getElementById('chartTreasury').getContext('2d'), {
            type: 'bar',
            data: {
                labels: monthLabels,
                datasets: [
                    { label: 'Cash Receipt', data: cashIn, backgroundColor: statusColorFor.good, borderRadius: 3 },
                    { label: 'Cash Out', data: cashOut, backgroundColor: palette[7], borderRadius: 3 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                tooltips: { callbacks: {
                    label: function (item, data) {
                        var isCashIn = item.datasetIndex === 0;
                        var breakdown = isCashIn ? cashInBreakdown : cashOutBreakdown;
                        var totalLabel = data.datasets[item.datasetIndex].label;
                        return breakdownLines(breakdown, year, item.index, totalLabel, item.yLabel);
                    }
                } },
                scales: {
                    xAxes: [{ gridLines: { display: false } }],
                    yAxes: [{ gridLines: { color: gridColor, drawBorder: false }, ticks: {
                        beginAtZero: true,
                        callback: function (v) { return v >= 1000000 ? (v / 1000000) + 'jt' : v; }
                    } }]
                }
            }
        });

        $('#legendTreasury').html(
            '<div class="exec-legend-item"><span class="exec-legend-swatch" style="background:' + statusColorFor.good + ';"></span>Cash Receipt</div>' +
            '<div class="exec-legend-item"><span class="exec-legend-swatch" style="background:' + palette[7] + ';"></span>Cash Out</div>'
        );
    }

    var execChartsInited = false;
    function initExecutiveCharts() {
        if (execChartsInited) return;
        execChartsInited = true;

        renderStatusChart();
        $('#chkShowClosedChart').on('change', renderStatusChart);

        renderProgressChart();

        renderCustomerChart();
        $('#selCustomerMetric').on('change', renderCustomerChart);

        revenueYears.forEach(function (y) { $('#selRevenueYear').append('<option value="' + y + '">' + y + '</option>'); });
        $('#selRevenueYear').val(String(new Date().getFullYear()));
        renderRevenueChart();
        $('#selRevenueYear').on('change', renderRevenueChart);

        treasuryYears.forEach(function (y) { $('#selTreasuryYear').append('<option value="' + y + '">' + y + '</option>'); });
        $('#selTreasuryYear').val(String(new Date().getFullYear()));
        renderTreasuryChart();
        $('#selTreasuryYear').on('change', renderTreasuryChart);
    }

    if ($('#summaryPaneExecutive').hasClass('active')) {
        initExecutiveCharts();
    }
    $('#summaryTabExecutiveLink').on('shown.bs.tab', initExecutiveCharts);

    /* ── GROUP TOGGLE (manual click) — status expand/collapse per grup disimpan
       di expandedGroups, supaya tidak ke-reset/auto-expand tiap kali filter
       berubah. Default semua grup collapsed, tetap collapsed sampai user klik. ── */
    var expandedGroups = new Set();

    $(document).on('click', '.exec-group-row', function () {
        var target = $(this).data('target');
        if (expandedGroups.has(target)) {
            expandedGroups.delete(target);
        } else {
            expandedGroups.add(target);
        }
        refreshExecTable();
    });

    /* ── FILTERS: search + Status + Progress R / B / I / P (masing-masing terpisah, AND) ── */
    var filterState = {
        status: new Set(),
        pm: new Set(),
        progress: { r: new Set(), b: new Set(), i: new Set(), p: new Set() }
    };

    function hasAnyProgressFilter() {
        return filterState.progress.r.size > 0 || filterState.progress.b.size > 0
            || filterState.progress.i.size > 0 || filterState.progress.p.size > 0;
    }

    function rowMatchesFilters($row, q, headerMatches) {
        var textOk = q === '' || headerMatches || $row.text().toLowerCase().indexOf(q) !== -1;
        if (!textOk) return false;

        if (filterState.status.size > 0 && !filterState.status.has($row.attr('data-status'))) return false;

        if (filterState.pm.size > 0) {
            var pmNames = ($row.attr('data-pm-internal') || '').split(',').map(function (s) { return s.trim(); });
            var anyPm = pmNames.some(function (n) { return filterState.pm.has(n); });
            if (!anyPm) return false;
        }

        var letters = ['r', 'b', 'i', 'p'];
        for (var idx = 0; idx < letters.length; idx++) {
            var letter = letters[idx];
            var set = filterState.progress[letter];
            if (set.size > 0 && !set.has($row.attr('data-progress-' + letter))) return false;
        }

        return true;
    }

    /*
     | Filter bertingkat: daftar pilihan satu tombol mengikuti filter LAIN yang
     | sedang aktif. Begitu Manager dipilih, dropdown Status dan Progress hanya
     | menawarkan nilai yang benar-benar dimiliki proyek manager itu — daftar
     | penuh di situ hanya menawarkan pilihan yang pasti mengosongkan tabel.
     | Filter tombol itu sendiri diabaikan supaya pilihan yang sudah tercentang
     | tidak lenyap dari daftarnya sendiri dan masih bisa dilepas.
     */
    function execRowPassesExcept($row, except) {
        if (except !== 'status' && filterState.status.size > 0 &&
            !filterState.status.has($row.attr('data-status'))) return false;

        if (except !== 'pm' && filterState.pm.size > 0) {
            var pmNames = ($row.attr('data-pm-internal') || '').split(',').map(function (n) { return n.trim(); });
            if (!pmNames.some(function (n) { return filterState.pm.has(n); })) return false;
        }

        var letters = ['r', 'b', 'i', 'p'];
        for (var i = 0; i < letters.length; i++) {
            var letter = letters[i];
            if (except === 'progress:' + letter) continue;
            var set = filterState.progress[letter];
            if (set.size > 0 && !set.has($row.attr('data-progress-' + letter))) return false;
        }
        return true;
    }

    // Baris data proyek — induk grup customer tidak membawa data-pm-internal.
    function execDataRows(except) {
        return $('[data-pm-internal]').filter(function () {
            return execRowPassesExcept($(this), except);
        });
    }

    // Nilai yang masih tersisa untuk satu atribut, setelah filter lain dipakai.
    function execNilaiTersisa(attr, except, pecahKoma) {
        var ada = {};
        execDataRows(except).each(function () {
            var raw = ($(this).attr(attr) || '').toString();
            if (pecahKoma) {
                raw.split(',').forEach(function (n) { n = n.trim(); if (n) ada[n] = true; });
            } else if (raw !== '') {
                ada[raw] = true;
            }
        });
        return ada;
    }

    function marginColorClass(v) {
        return v >= 20 ? 'success' : (v >= 0 ? 'warning' : 'danger');
    }

    function fmtPct(v) {
        var s = v.toFixed(1);
        if (s.endsWith('.0')) s = s.slice(0, -2);
        return s.replace('.', ',') + '%';
    }

    function computeTotals($rowsSet) {
        var value = 0, contract = 0, budget = 0, realization = 0, payment = 0, overhead = 0, fixedcost = 0;

        $rowsSet.each(function () {
            var $r = $(this);
            value += parseFloat($r.attr('data-value')) || 0;
            contract += parseFloat($r.attr('data-contract')) || 0;
            budget += parseFloat($r.attr('data-budget')) || 0;
            realization += parseFloat($r.attr('data-realization')) || 0;
            payment += parseFloat($r.attr('data-payment')) || 0;
            overhead += parseFloat($r.attr('data-overhead')) || 0;
            fixedcost += parseFloat($r.attr('data-fixedcost')) || 0;
        });

        var grossProfit = payment - realization;
        var netProfit = grossProfit - overhead - fixedcost;

        return {
            value: value,
            contract: contract,
            budget: budget,
            realization: realization,
            payment: payment,
            overhead: overhead,
            fixedcost: fixedcost,
            grossProfit: grossProfit,
            netProfit: netProfit,
            pm: budget > 0 ? ((budget - realization) / budget) * 100 : 0,
            gm: value > 0 ? ((value - budget) / value) * 100 : 0,
        };
    }

    function fmtRpSigned(v) {
        return (v < 0 ? '-' : '') + fmtRp(Math.abs(v));
    }

    // PO count & daftar PM di header grup — ikut baris yang lolos filter saat
    // ini, supaya kalau filter PM diaktifkan (mis. cuma 1 dari 3 PM customer
    // itu) yang tampil cuma PM yang sedang difilter, bukan semua PM customer.
    function applyMetaToHeader($header, $rowsSet) {
        var pmNames = [];
        $rowsSet.each(function () {
            (($(this).attr('data-pm-internal') || '').split(',')).forEach(function (n) {
                n = n.trim();
                if (n && n !== '-' && pmNames.indexOf(n) === -1) pmNames.push(n);
            });
        });
        $header.find('.exec-po-count').text($rowsSet.length);
        $header.find('.exec-pm-list').text(pmNames.length ? pmNames.join(', ').toUpperCase() : '-');
    }

    function applyTotalsToRow($row, totals) {
        $row.find('.exec-cell-pm').text(fmtPct(totals.pm)).attr('class', 'exec-cell-pm text-' + marginColorClass(totals.pm));
        $row.find('.exec-cell-gm').text(fmtPct(totals.gm)).attr('class', 'exec-cell-gm text-' + marginColorClass(totals.gm));
        $row.find('.exec-cell-contract').text(fmtRp(totals.contract));
        $row.find('.exec-cell-value').text(fmtRp(totals.value));
        $row.find('.exec-cell-budget').text(fmtRp(totals.budget));
        $row.find('.exec-cell-realization').text(fmtRp(totals.realization));
        $row.find('.exec-cell-payment').text(fmtRp(totals.payment));
        $row.find('.exec-cell-overhead').text(fmtRp(totals.overhead));
        $row.find('.exec-cell-fixedcost').text(fmtRp(totals.fixedcost));
        $row.find('.exec-cell-grossprofit').text(fmtRpSigned(totals.grossProfit)).toggleClass('text-danger', totals.grossProfit < 0);
        $row.find('.exec-cell-netprofit').text(fmtRpSigned(totals.netProfit)).toggleClass('text-danger', totals.netProfit < 0);
    }

    function recalcGrandTotal($includedRows) {
        var totals = computeTotals($includedRows);
        $('#grandTotalLabel').text('GRAND TOTAL (' + $includedRows.length + ' PO)');
        $('#grandTotalPm').text(fmtPct(totals.pm)).attr('class', 'text-' + marginColorClass(totals.pm) + ' font-weight-bold');
        $('#grandTotalGm').text(fmtPct(totals.gm)).attr('class', 'text-' + marginColorClass(totals.gm) + ' font-weight-bold');
        $('#grandTotalContract').text(fmtRp(totals.contract));
        $('#grandTotalValue').text(fmtRp(totals.value));
        $('#grandTotalBudget').text(fmtRp(totals.budget));
        $('#grandTotalRealization').text(fmtRp(totals.realization));
        $('#grandTotalPayment').text(fmtRp(totals.payment));
        $('#grandTotalOverhead').text(fmtRp(totals.overhead));
        $('#grandTotalFixedCost').text(fmtRp(totals.fixedcost));
        $('#grandTotalGrossProfit').text(fmtRpSigned(totals.grossProfit)).toggleClass('text-danger', totals.grossProfit < 0);
        $('#grandTotalNetProfit').text(fmtRpSigned(totals.netProfit)).toggleClass('text-danger', totals.netProfit < 0);
    }

    function refreshExecTable() {
        var q = $('#execSearchInput').val().toLowerCase();
        var hasActiveFilter = q !== '' || filterState.status.size > 0 || filterState.pm.size > 0 || hasAnyProgressFilter();
        var $includedRows = $();

        $('.exec-group-row').each(function () {
            var $header = $(this);
            var target = $header.data('target');
            var $childRows = $('.' + target);
            var isExpanded = expandedGroups.has(target);

            if (!hasActiveFilter) {
                $header.show();
                $childRows.toggle(isExpanded);
                $header.find('.exec-caret').toggleClass('rotated', isExpanded);
                applyTotalsToRow($header, computeTotals($childRows));
                applyMetaToHeader($header, $childRows);
                $includedRows = $includedRows.add($childRows);
                return;
            }

            var headerMatches = q !== '' && $header.text().toLowerCase().indexOf(q) !== -1;
            var anyMatch = false;
            var $groupIncluded = $();

            $childRows.each(function () {
                var match = rowMatchesFilters($(this), q, headerMatches);
                if (match) {
                    anyMatch = true;
                    $groupIncluded = $groupIncluded.add($(this));
                    $includedRows = $includedRows.add($(this));
                }
                $(this).toggle(match && isExpanded);
            });

            var groupTotals = computeTotals($groupIncluded);
            applyTotalsToRow($header, groupTotals);
            applyMetaToHeader($header, $groupIncluded);

            $header.toggle(anyMatch);
            $header.find('.exec-caret').toggleClass('rotated', isExpanded);
        });

        recalcGrandTotal($includedRows);
    }

    $('#execSearchInput').on('keyup', refreshExecTable);

    function closeExecFilterDropdowns() {
        $('.tcf-dropdown').remove();
        $(document).off('click.tcf-outside-exec');
    }

    /* Satu tombol = buka DAN tutup: pemicu panel dicatat di elemen panelnya,
       jadi klik kedua pada tombol yang sama menutup, bukan menggambar ulang. */
    function isExecDropdownOpenFor($btn) {
        var el = $btn.get(0), open = false;
        $('.tcf-dropdown').each(function () {
            if ($(this).data('tcfOwner') === el) open = true;
        });
        return open;
    }

    function openExecFilterDropdown($btn, options, selectedSet) {
        if (isExecDropdownOpenFor($btn)) { closeExecFilterDropdowns(); return; }
        closeExecFilterDropdowns();

        var $dd = $('<div class="tcf-dropdown"></div>').css({
            position: 'absolute', zIndex: 3000, background: '#fff', border: '1px solid #d0d5dd',
            borderRadius: '6px', boxShadow: '0 6px 18px rgba(0,0,0,.12)', padding: '8px',
            width: 'max-content', minWidth: '200px', maxWidth: '320px', maxHeight: '280px',
            display: 'flex', flexDirection: 'column'
        });
        var $list = $('<div class="tcf-list" style="overflow-y:auto;overflow-x:hidden;max-height:240px;"></div>');

        options.forEach(function (opt) {
            var checked = selectedSet.has(opt.value) ? 'checked' : '';
            $list.append(
                '<label class="tcf-item mb-1" style="display:flex;align-items:center;font-size:12px;font-weight:400;cursor:pointer;gap:6px;">' +
                '<input type="checkbox" class="tcf-check" value="' + opt.value + '" ' + checked + '> <span>' + opt.label + '</span>' +
                '</label>'
            );
        });

        $dd.append($list);
        $dd.data('tcfOwner', $btn.get(0));
        $('body').append($dd);

        TesysPlaceDropdown($dd, $btn);

        $list.on('change', '.tcf-check', function () {
            var selected = new Set();
            $list.find('.tcf-check:checked').each(function () { selected.add($(this).val()); });
            selectedSet.clear();
            selected.forEach(function (v) { selectedSet.add(v); });
            $btn.toggleClass('tcf-row-active', selected.size > 0);
            refreshExecTable();
        });

        $(document).one('click.tcf-outside-exec', function handler(e) {
            if ($(e.target).closest('.tcf-dropdown, .tcf-row-btn').length === 0) {
                closeExecFilterDropdowns();
            } else {
                $(document).one('click.tcf-outside-exec', handler);
            }
        });
    }

    $('#btnFilterStatus').on('click', function (e) {
        e.stopPropagation();
        var tersisa = execNilaiTersisa('data-status', 'status', false);
        var options = statusData
            .map(function (s) { return { value: s.status, label: s.label }; })
            .filter(function (o) { return tersisa[o.value] || filterState.status.has(o.value); });
        openExecFilterDropdown($(this), options, filterState.status);
    });

    $('#btnFilterPm').on('click', function (e) {
        e.stopPropagation();
        var names = new Set();
        execDataRows('pm').each(function () {
            ($(this).attr('data-pm-internal') || '').split(',').forEach(function (n) {
                n = n.trim();
                if (n && n !== '-') names.add(n);
            });
        });
        filterState.pm.forEach(function (n) { names.add(n); });
        var options = Array.from(names).sort().map(function (n) { return { value: n, label: n.toUpperCase() }; });
        openExecFilterDropdown($(this), options, filterState.pm);
    });

    var progressBucketOptions = [
        { value: 'Not Started', label: 'Not Started' },
        { value: 'Partial', label: 'Partial' },
        { value: 'Done', label: 'Done' }
    ];
    $('.exec-progress-filter-btn').on('click', function (e) {
        e.stopPropagation();
        var letter = $(this).data('letter');
        var sisa = execNilaiTersisa('data-progress-' + letter, 'progress:' + letter, false);
        var options = progressBucketOptions.filter(function (o) {
            return sisa[o.value] || filterState.progress[letter].has(o.value);
        });
        openExecFilterDropdown($(this), options, filterState.progress[letter]);
    });

    $('#btnClearExecFilter').on('click', function () {
        filterState.status.clear();
        filterState.pm.clear();
        filterState.progress.r.clear();
        filterState.progress.b.clear();
        filterState.progress.i.clear();
        filterState.progress.p.clear();
        $('#execSearchInput').val('');
        $('#btnFilterStatus, #btnFilterPm, .exec-progress-filter-btn').removeClass('tcf-row-active');
        refreshExecTable();
    });

    refreshExecTable();
});
</script>
@endif

@if($canViewCollection)
<script>
$(function () {
    /* ── Filter multi-pilih (Manager / Customer / Bulan) — tombol-dropdown checklist,
       submit via tombol Apply (reload halaman). ── */
    var $colPane = $('#summaryPaneCollection');
    $colPane.on('click', '.col-mf-btn', function (e) {
        var $menu = $(this).siblings('.col-mf-menu');

        // Bukan tombol milik komponen _multi-filter (tidak punya .col-mf-menu) —
        // biarkan kliknya lewat. Kelas .col-mf-btn juga dipakai hanya untuk gaya
        // oleh tombol lain (mis. filter Manager di Summary Progress) yang
        // dropdown-nya ditangani Bootstrap lewat data-toggle="dropdown", dan
        // Bootstrap mendengarkannya di document.
        if ($menu.length === 0) return;

        e.stopPropagation();
        var willOpen = $menu.prop('hidden');
        $('.col-mf-menu').prop('hidden', true);
        $menu.prop('hidden', !willOpen);
        if (willOpen) $menu.find('.col-mf-search').val('').trigger('input').trigger('focus');
    });
    $colPane.on('click', '.col-mf-menu', function (e) { e.stopPropagation(); });
    $(document).on('click.colmf', function () { $('.col-mf-menu').prop('hidden', true); });
    $colPane.on('input', '.col-mf-search', function () {
        var q = this.value.toLowerCase();
        $(this).siblings('.col-mf-list').find('.col-mf-item').each(function () {
            this.hidden = this.textContent.toLowerCase().indexOf(q) === -1;
        });
    });
    $colPane.on('change', '.col-mf-menu input[type=checkbox]', function () {
        var $mf = $(this).closest('.col-mf');
        var name = $mf.data('name');
        var vals = $mf.find('.col-mf-list input:checked').map(function () { return this.value; }).get();
        $mf.find('.col-mf-inputs').html(vals.map(function (v) {
            return '<input type="hidden" name="' + name + '[]" value="' + $('<div>').text(v).html() + '">';
        }).join(''));
        $mf.find('.col-mf-badge').text(vals.length).prop('hidden', vals.length === 0);
        $mf.find('.col-mf-btn').toggleClass('col-mf-active', vals.length > 0);
    });

    /* ── SUMMARY COLLECTION — lazy-init sama seperti tab Executive (canvas
       hidden di tab non-aktif ke-render 0x0 kalau Chart.js langsung jalan). ── */
    var Wc = window.tesysWarna || function (v) { return v; };
    var colPalette = { good: Wc('var(--success)'), warn: Wc('var(--warning)'), bad: Wc('var(--danger)'), blue: Wc('var(--info)'), neutral: '#c3c2b7' };
    var colGridColor = '#E1E0D9';

    var colDataEl = document.getElementById('colChartData');
    var colAging = JSON.parse(colDataEl.dataset.aging);
    var colAgingCounts = JSON.parse(colDataEl.dataset.agingCounts);
    var colStatus = JSON.parse(colDataEl.dataset.status);
    var colUpcoming = JSON.parse(colDataEl.dataset.upcoming);

    function colFmtRp(v) { return Number(v || 0).toLocaleString('id-ID'); }
    function colFmtAxis(v) { return v >= 1000000 ? (v / 1000000) + 'jt' : v; }

    function renderAgingChart() {
        var labels = Object.keys(colAging);
        var values = Object.values(colAging);
        var counts = labels.map(function (l) { return colAgingCounts[l] || 0; });
        var colors = ['var(--success)', 'var(--warning)', 'var(--orange)', 'var(--danger)'].map(Wc);

        new Chart(document.getElementById('chartAging').getContext('2d'), {
            type: 'bar',
            data: { labels: labels, datasets: [{ data: values, backgroundColor: colors, borderRadius: 4, barPercentage: 0.7, categoryPercentage: 0.8 }] },
            options: {
                responsive: true, maintainAspectRatio: false,
                legend: { display: false },
                tooltips: { callbacks: { label: function (item) { return colFmtRp(item.yLabel) + ' (' + counts[item.index] + ' invoice)'; } } },
                scales: {
                    xAxes: [{ gridLines: { display: false } }],
                    yAxes: [{ gridLines: { color: colGridColor, drawBorder: false }, ticks: { beginAtZero: true, callback: colFmtAxis } }]
                }
            }
        });
    }

    function renderInvStatusChart() {
        var order = ['Paid', 'Not Due', 'Overdue'];
        var colorFor = { Paid: colPalette.good, 'Not Due': colPalette.blue, Overdue: colPalette.bad };
        var labels = order.filter(function (k) { return colStatus[k] > 0; });
        var values = labels.map(function (k) { return colStatus[k]; });
        var colors = labels.map(function (k) { return colorFor[k]; });
        var total = values.reduce(function (a, b) { return a + b; }, 0);
        $('#invStatusTotal').text(colFmtRp(total));

        new Chart(document.getElementById('chartInvStatus').getContext('2d'), {
            type: 'doughnut',
            data: { labels: labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
            options: {
                responsive: true, maintainAspectRatio: false,
                legend: { display: false },
                cutoutPercentage: 60,
                tooltips: { callbacks: { label: function (item, chartData) {
                    var v = chartData.datasets[0].data[item.index];
                    return chartData.labels[item.index] + ': ' + colFmtRp(v) + ' (' + Math.round(v / total * 100) + '%)';
                } } }
            }
        });

        $('#legendInvStatus').html(labels.map(function (l, i) {
            var pct = total > 0 ? Math.round(values[i] / total * 100) : 0;
            return '<div class="col-donut-legend-item">' +
                '<span class="col-donut-swatch" style="background:' + colors[i] + ';"></span>' +
                '<div>' +
                    '<div class="col-donut-legend-label">' + l + ' <span class="col-donut-legend-pct">— ' + pct + '%</span></div>' +
                    '<div class="col-donut-legend-value">' + colFmtRp(values[i]) + '</div>' +
                '</div>' +
            '</div>';
        }).join(''));
    }

    function renderUpcomingChart() {
        var labels = Object.keys(colUpcoming);
        var values = labels.map(function (l) { return colUpcoming[l].value; });
        var counts = labels.map(function (l) { return colUpcoming[l].count; });
        var grandTotal = values.length ? values[values.length - 1] : 0;

        $('#upcomingDueTotal').text('Total: ' + colFmtRp(grandTotal));

        new Chart(document.getElementById('chartUpcoming').getContext('2d'), {
            type: 'bar',
            data: { labels: labels, datasets: [{ data: values, backgroundColor: colPalette.blue, borderRadius: 4, barPercentage: 0.7, categoryPercentage: 0.8 }] },
            options: {
                responsive: true, maintainAspectRatio: false,
                legend: { display: false },
                tooltips: { callbacks: { label: function (item) { return colFmtRp(item.yLabel) + ' (' + counts[item.index] + ' invoice)'; } } },
                scales: {
                    xAxes: [{ gridLines: { display: false } }],
                    yAxes: [{ gridLines: { color: colGridColor, drawBorder: false }, ticks: { beginAtZero: true, callback: colFmtAxis } }]
                }
            }
        });
    }

    var colChartsInited = false;
    function initCollectionCharts() {
        if (colChartsInited) return;
        colChartsInited = true;

        renderAgingChart();
        renderInvStatusChart();
        renderUpcomingChart();
    }

    if ($('#summaryPaneCollection').hasClass('active')) {
        initCollectionCharts();
    }
    $('#summaryTabCollectionLink').on('shown.bs.tab', initCollectionCharts);

    /* ── Detail collapsible: toggle & tab ── */
    $('#summaryPaneCollection').on('click', '.js-col-detail-toggle', function () {
        var $btn = $(this);
        var $body = $('#' + $btn.data('target'));
        var open = $body.prop('hidden');
        $body.prop('hidden', !open);
        $btn.attr('aria-expanded', open ? 'true' : 'false');
    });
    $('#summaryPaneCollection').on('click', '.js-col-detail-tab', function () {
        var $btn = $(this);
        var grp = $btn.data('detail-group');
        var idx = String($btn.data('detail-idx'));
        $('.js-col-detail-tab[data-detail-group="' + grp + '"]').removeClass('active');
        $btn.addClass('active');
        $('.col-detail-pane[data-detail-group="' + grp + '"]').each(function () {
            $(this).toggleClass('d-none', String($(this).data('detail-idx')) !== idx);
        });
    });

    /* ── Sortable tables (klik header) ── */
    function colSortValue(cell, type) {
        var raw = cell.getAttribute('data-sort');
        if (raw === null) raw = cell.textContent.trim();
        if (type === 'num') {
            var n = parseFloat(String(raw).replace(/[^0-9.\-]/g, ''));
            return isNaN(n) ? 0 : n;
        }
        return String(raw).toLowerCase();
    }
    $('#summaryPaneCollection').on('click', '.js-sortable-table th.js-sort', function () {
        var th = this;
        var table = $(th).closest('table')[0];
        var tbody = table.tBodies[0];
        if (!tbody) return;
        var col = th.hasAttribute('data-col') ? parseInt(th.getAttribute('data-col'), 10) : th.cellIndex;
        var type = th.getAttribute('data-sort-type') || 'text';
        var asc = !th.classList.contains('sort-asc');

        $(table).find('th.js-sort').removeClass('sort-asc sort-desc');
        th.classList.add(asc ? 'sort-asc' : 'sort-desc');

        var rows = Array.prototype.slice.call(tbody.rows).filter(function (r) {
            return r.cells.length > col && !r.querySelector('td[colspan]');
        });
        rows.sort(function (a, b) {
            var va = colSortValue(a.cells[col], type);
            var vb = colSortValue(b.cells[col], type);
            if (va < vb) return asc ? -1 : 1;
            if (va > vb) return asc ? 1 : -1;
            return 0;
        });
        rows.forEach(function (r) { tbody.appendChild(r); });
    });

    /* ── Kolom (column visibility) — didorong lewat DataTables API asli
       (table.column(idx).visible()), colspan header grup menyesuaikan
       otomatis (fitur bawaan DataTables, tidak perlu logic manual). ── */
    $('#summaryPaneCollection').on('click', '.js-colvis-menu', function (e) { e.stopPropagation(); });

    function applyColvis(wrap) {
        var $wrap = $(wrap);
        var tableId = $wrap.data('colvis');
        var $table = $('#' + tableId);
        if (!$table.length || !$.fn.dataTable.isDataTable($table[0])) return;
        var table = $table.DataTable();

        $wrap.find('.js-colvis-toggle').each(function () {
            var col = parseInt(this.getAttribute('data-col'), 10);
            if (!isNaN(col)) table.column(col).visible(this.checked);
        });

        // column().visible() memindah node <td>/<th> masuk-keluar DOM — jaga-jaga
        // supaya footer sel yang baru dimunculkan lagi tidak menampilkan angka
        // basi dari sebelum kolom disembunyikan.
        if (tableId === 'tblCollectionPerformance' && typeof recalcCollectionFooter === 'function') {
            recalcCollectionFooter();
        } else if (tableId === 'tblPoSummary' && typeof recalcPoSummaryFooter === 'function') {
            recalcPoSummaryFooter();
        }
    }

    $('#summaryPaneCollection').on('change', '.js-colvis-toggle', function () {
        applyColvis($(this).closest('[data-colvis]'));
    });

    /* Footer "Grand Total" tabel Collection Performance & Summary PO — dihitung
       ulang dari baris yang lolos filter Manager saja (table.rows({search:'applied'})),
       bukan dari total keseluruhan data, tiap kali DataTables redraw (filter
       berubah/dihapus). Nilai mentah per baris diambil dari data-* pada <tr>
       (lihat tbody), bukan parsing teks sel yang sudah diformat. */
    function recalcCollectionFooter() {
        if (!$.fn.dataTable.isDataTable('#tblCollectionPerformance')) return;
        var table = $('#tblCollectionPerformance').DataTable();
        var $rows = $(table.rows({ search: 'applied' }).nodes());

        var s = { invCount: 0, inv: 0, paidCount: 0, paid: 0, osCount: 0, os: 0, ovCount: 0, ov: 0, agingWeighted: 0 };
        $rows.each(function () {
            var d = $(this).data();
            s.invCount  += parseInt(d.totalInvoiceCount, 10) || 0;
            s.inv       += parseInt(d.totalInvoice, 10) || 0;
            s.paidCount += parseInt(d.paidCount, 10) || 0;
            s.paid      += parseInt(d.paid, 10) || 0;
            s.osCount   += parseInt(d.outstandingCount, 10) || 0;
            s.os        += parseInt(d.outstanding, 10) || 0;
            s.ovCount   += parseInt(d.overdueCount, 10) || 0;
            s.ov        += parseInt(d.overdue, 10) || 0;
            s.agingWeighted += (parseInt(d.avgAgingDays, 10) || 0) * (parseInt(d.overdueCount, 10) || 0);
        });

        var rate  = s.inv > 0 ? Math.round(((s.inv - s.os) / s.inv) * 100) : 0;
        var aging = s.ovCount > 0 ? Math.round(s.agingWeighted / s.ovCount) : 0;

        var $foot = $('#tblCollectionPerformance tfoot');
        $foot.find('.cp-foot-inv-count').text(s.invCount);
        $foot.find('.cp-foot-inv-value').text(s.inv.toLocaleString('id-ID'));
        $foot.find('.cp-foot-paid-count').text(s.paidCount);
        $foot.find('.cp-foot-paid-value').text(s.paid.toLocaleString('id-ID'));
        $foot.find('.cp-foot-os-count').text(s.osCount);
        $foot.find('.cp-foot-os-value').text(s.os.toLocaleString('id-ID'));
        $foot.find('.cp-foot-ov-count').text(s.ovCount);
        $foot.find('.cp-foot-ov-value').text(s.ov.toLocaleString('id-ID'));
        $foot.find('.cp-foot-rate').text(rate + '%');
        $foot.find('.cp-foot-aging').text(aging > 0 ? aging : '-');
    }

    function recalcPoSummaryFooter() {
        if (!$.fn.dataTable.isDataTable('#tblPoSummary')) return;
        var table = $('#tblPoSummary').DataTable();
        var $rows = $(table.rows({ search: 'applied' }).nodes());

        var s = { poQty: 0, poValue: 0, recvQty: 0, recvValue: 0, waitQty: 0, waitValue: 0, payQty: 0, payValue: 0 };
        $rows.each(function () {
            var d = $(this).data();
            s.poQty     += parseInt(d.poQty, 10) || 0;
            s.poValue   += parseInt(d.poValue, 10) || 0;
            s.recvQty   += parseInt(d.receiveQty, 10) || 0;
            s.recvValue += parseInt(d.receiveValue, 10) || 0;
            s.waitQty   += parseInt(d.waitingQty, 10) || 0;
            s.waitValue += parseInt(d.waitingValue, 10) || 0;
            s.payQty    += parseInt(d.paymentQty, 10) || 0;
            s.payValue  += parseInt(d.paymentValue, 10) || 0;
        });

        var rate = s.poValue > 0 ? Math.round((s.payValue / s.poValue) * 100) : 0;

        var $foot = $('#tblPoSummary tfoot');
        $foot.find('.po-foot-po-qty').text(s.poQty);
        $foot.find('.po-foot-po-value').text(s.poValue.toLocaleString('id-ID'));
        $foot.find('.po-foot-receive-qty').text(s.recvQty);
        $foot.find('.po-foot-receive-value').text(s.recvValue.toLocaleString('id-ID'));
        $foot.find('.po-foot-waiting-qty').text(s.waitQty);
        $foot.find('.po-foot-waiting-value').text(s.waitValue.toLocaleString('id-ID'));
        $foot.find('.po-foot-payment-qty').text(s.payQty);
        $foot.find('.po-foot-payment-value').text(s.payValue.toLocaleString('id-ID'));
        $foot.find('.po-foot-rate').text(rate + '%');

        // Refresh sumber data-* footer VAR dgn total yang sudah difilter, lalu
        // pakai ulang recalcPoVar() (didefinisikan di script tag lain, lihat
        // window.recalcPoVar) supaya teks VAR footer ikut formula aktif. Guard
        // window.poFooterSyncing dipasang biar rows().invalidate().draw(false)
        // di dalam recalcPoVar() tidak memicu event 'draw' ini lagi (infinite loop).
        $foot.find('.po-var-qty').data({ poQty: s.poQty, receiveQty: s.recvQty, paymentQty: s.payQty, waitingQty: s.waitQty });
        $foot.find('.po-var-value-link').data({ poValue: s.poValue, receiveValue: s.recvValue, paymentValue: s.payValue, waitingValue: s.waitValue });
        window.poFooterSyncing = true;
        if (typeof window.recalcPoVar === 'function') window.recalcPoVar();
        window.poFooterSyncing = false;
    }

    /* ── Collection Performance & PO Summary — inisialisasi DataTables (tanpa
       chrome bawaan: no paging/search-box/info, cuma dipakai sebagai mesin
       sort native + filter Manager via TesysColumnFilter). Diinisialisasi lazy
       saat tab Collection benar-benar terlihat — DataTables salah hitung lebar
       kolom kalau tabelnya masih display:none (pola sama dengan initCollectionCharts). ── */
    var cpTablesInited = false;
    function initCollectionTables() {
        if (cpTablesInited) return;
        cpTablesInited = true;

        ['tblCollectionPerformance', 'tblPoSummary'].forEach(function (id) {
            if (!$('#' + id).length) return;
            var dtOpts = {
                paging: false, searching: true, info: false, ordering: true,
                autoWidth: false, dom: 't'
            };
            // Default sort: Collection Rate ascending (kolom index 10 — lihat urutan
            // <th> di thead: Customer,Manager,InvQty,InvValue,PaidQty,PaidValue,
            // OSQty,OSValue,OVQty,OVValue,CollRate,Aging,Status).
            if (id === 'tblCollectionPerformance') dtOpts.order = [[10, 'asc']];
            $('#' + id).DataTable(dtOpts);
            $('#' + id).wrap('<div class="col-pic-hscroll table-responsive"></div>');
        });
        if ($('#tblCollectionPerformance').length) {
            TesysColumnFilter.initRowFilters('#tblCollectionPerformance', '#cpRowFilters', [
                { key: 'manager', label: 'Manager' }
            ]);
            $('#tblCollectionPerformance').DataTable().on('draw', recalcCollectionFooter);
            recalcCollectionFooter();
        }
        if ($('#tblPoSummary').length) {
            TesysColumnFilter.initRowFilters('#tblPoSummary', '#poRowFilters', [
                { key: 'manager', label: 'Manager' }
            ]);
            $('#tblPoSummary').DataTable().on('draw', recalcPoSummaryFooter);
            recalcPoSummaryFooter();
        }

        // Rate 100% disembunyikan secara default di kedua tabel (Collection Rate /
        // Payment Rate) — checkbox "Show Rate 100%" di sebelah filter Manager
        // membatalkan penyembunyian itu. Satu predicate menangani dua tabel
        // (dibedakan lewat settings.nTable.id), gaya sama seperti row-filter
        // Manager di tesys-column-filter.js.
        $.fn.dataTable.ext.search.push(function (settings, searchData, index, rowData, counter) {
            var row = settings.aoData[index].nTr;
            if (!row) return true;
            if (settings.nTable.id === 'tblCollectionPerformance') {
                if ($('#cpShowRate100').is(':checked')) return true;
                return parseFloat(row.getAttribute('data-collection-rate')) !== 100;
            }
            if (settings.nTable.id === 'tblPoSummary') {
                if ($('#poShowRate100').is(':checked')) return true;
                return parseFloat(row.getAttribute('data-payment-rate')) !== 100;
            }
            return true;
        });
        $('#cpShowRate100').on('change', function () {
            $('#tblCollectionPerformance').DataTable().draw();
        });
        $('#poShowRate100').on('change', function () {
            $('#tblPoSummary').DataTable().draw();
        });

        /* Catatan "semua sudah 100%" — muncul HANYA kalau tabelnya memang punya
           data tapi tidak ada satu pun yang lolos saringan rate. Kalau datanya
           memang kosong, pesan bawaan DataTables sudah benar dan catatan ini
           justru akan menyesatkan. */
        function perbaruiCatatanRate(tableId, checkboxId) {
            var $tabel = $('#' + tableId);
            if (!$tabel.length || !$.fn.dataTable.isDataTable($tabel)) return;

            var api      = $tabel.DataTable();
            var adaData  = api.rows().count() > 0;
            var tampil   = api.rows({ search: 'applied' }).count() > 0;
            var disaring = !$('#' + checkboxId).is(':checked');

            $('.js-rate-note[data-for="' + tableId + '"]').toggle(adaData && !tampil && disaring);
        }

        $('#tblPoSummary').on('draw.dt', function () { perbaruiCatatanRate('tblPoSummary', 'poShowRate100'); });
        $('#tblCollectionPerformance').on('draw.dt', function () { perbaruiCatatanRate('tblCollectionPerformance', 'cpShowRate100'); });
        // Terapkan langsung saat init (bukan nunggu interaksi pertama) — draw()
        // ini juga memicu recalcCollectionFooter/recalcPoSummaryFooter di atas
        // supaya footer ikut hanya menghitung baris yang tampil.
        if ($('#tblCollectionPerformance').length) $('#tblCollectionPerformance').DataTable().draw();
        if ($('#tblPoSummary').length) $('#tblPoSummary').DataTable().draw();
    }

    /* ── PROGRESS SUMMARY (MN-10B) — grouping Customer > PO > No. PO.
       Sengaja BUKAN DataTable: baris di sini hierarkis (parent/child), dan
       paging DataTable akan memotong anak dari induknya. Yang dibutuhkan cuma
       expand/collapse + pencarian teks, keduanya ringan di sisi klien. Semua
       angka sudah dihitung di backend dari dataset yang difilter (P-02), jadi
       tidak ada agregasi yang dikerjakan di sini. ── */
    function progChildren(key) {
        return $('#tblProgressSummary tbody tr[data-parent="' + key + '"]');
    }

    function progCollapse($row) {
        var key = $row.data('key');
        if (!key) return;
        progChildren(key).each(function () {
            var $c = $(this);
            progCollapse($c);
            $c.hide();
        });
        $row.find('.prog-caret').removeClass('fa-caret-down').addClass('fa-caret-right');
    }

    $('#tblProgressSummary').on('click', 'tr.prog-row-customer, tr.prog-row-project', function () {
        var $row = $(this);
        var key  = $row.data('key');
        var $kids = progChildren(key);
        if (!$kids.length) return;

        if ($kids.filter(':visible').length) {
            progCollapse($row);
        } else {
            $kids.show();
            $row.find('.prog-caret').removeClass('fa-caret-right').addClass('fa-caret-down');
        }
    });

    $('#progExpandAll').on('click', function () {
        var $rows = $('#tblProgressSummary tbody tr.prog-row');
        var expand = $rows.filter('[data-parent]:visible').length === 0;
        if (expand) {
            $rows.show();
            $('#tblProgressSummary .prog-caret').removeClass('fa-caret-right').addClass('fa-caret-down');
            $(this).html('<i class="fas fa-angle-double-up mr-1"></i>Tutup Semua');
        } else {
            $rows.filter('[data-parent]').hide();
            $('#tblProgressSummary .prog-caret').removeClass('fa-caret-down').addClass('fa-caret-right');
            $(this).html('<i class="fas fa-angle-double-down mr-1"></i>Buka Semua');
        }
        $('#progNoMatch').hide();
    });

    /* Pencarian: baris yang cocok ditampilkan BESERTA seluruh induknya, supaya
       hasilnya tetap terbaca sebagai hierarki dan bukan daftar No. PO lepas. */
    var progSearchTimer = null;
    $('#progSearch').on('input', function () {
        var q = $(this).val().toLowerCase().trim();
        clearTimeout(progSearchTimer);
        progSearchTimer = setTimeout(function () {
            var $rows = $('#tblProgressSummary tbody tr.prog-row');

            if (!q) {
                $rows.hide();
                $rows.filter('.prog-row-customer').show();
                $('#tblProgressSummary .prog-caret').removeClass('fa-caret-down').addClass('fa-caret-right');
                $('#progNoMatch').hide();
                return;
            }

            $rows.hide();
            var hit = 0;

            $rows.each(function () {
                var $r = $(this);
                if (($r.data('text') || '').toString().indexOf(q) === -1) return;
                hit++;
                $r.show();
                // Naikkan seluruh rantai induknya.
                var parent = $r.data('parent');
                while (parent) {
                    var $p = $('#tblProgressSummary tbody tr[data-key="' + parent + '"]');
                    if (!$p.length) break;
                    $p.show().find('.prog-caret').removeClass('fa-caret-right').addClass('fa-caret-down');
                    parent = $p.data('parent');
                }
            });

            $('#progNoMatch').toggle(hit === 0);
        }, 150);
    });

    /* Deep-link dari kolom Qty PO di Daftar Purchase Order:
       ?tab=collection&progress=<kode PO>. Kotak cari diisi kodenya supaya
       filternya jadi keadaan yang bisa dilihat & dihapus user (bukan
       penyembunyian diam-diam), lalu baris PO-nya dibuka sampai No. PO dan
       digulirkan ke layar — panel ini duduk paling bawah di tab Collection. */
    (function () {
        var wanted = new URLSearchParams(window.location.search).get('progress');
        if (!wanted) return;

        var $search = $('#progSearch');
        if (!$search.length) return;

        $search.val(wanted).trigger('input');

        // Menunggu debounce pencarian (150ms) selesai sebelum membuka anaknya.
        setTimeout(function () {
            var q = wanted.toLowerCase();
            var $proj = $('#tblProgressSummary tbody tr.prog-row-project').filter(function () {
                return ($(this).data('text') || '').toString().indexOf(q) !== -1;
            }).first();
            if (!$proj.length) return;

            progChildren($proj.data('key')).show();
            $proj.find('.prog-caret').removeClass('fa-caret-right').addClass('fa-caret-down');
            $proj[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 250);
    })();

    /* Summary Progress — VAR dihitung ulang di frontend sesuai formula
       dropdown, tanpa reload: tiap sel VAR sudah membawa angka semua tahap
       lewat data-*. Pola yang sama dengan PO Summary (lihat recalcPoVar). */
    var progVarPairs = {
        contract_minus_closed:          ['contract', 'closed'],
        contract_minus_wait_po:         ['contract', 'waitPo'],
        wait_bast_minus_wait_invoice:   ['waitBast', 'waitInvoice'],
        wait_invoice_minus_wait_payment:['waitInvoice', 'waitPayment'],
        wait_payment_minus_closed:      ['waitPayment', 'closed']
    };

    function recalcProgVar() {
        var pair = progVarPairs[$('#progVarFormula').val()] || progVarPairs.contract_minus_closed;
        var a = pair[0], b = pair[1];

        $('#tblProgressSummary .prog-var-qty').each(function () {
            var d = $(this).data();
            var v = (parseInt(d[a + 'Qty'], 10) || 0) - (parseInt(d[b + 'Qty'], 10) || 0);
            $(this).text(v).toggleClass('text-danger', v < 0);
        });

        $('#tblProgressSummary .prog-var-value').each(function () {
            var d = $(this).data();
            var v = (parseInt(d[a + 'Value'], 10) || 0) - (parseInt(d[b + 'Value'], 10) || 0);
            $(this).text(v.toLocaleString('id-ID')).toggleClass('text-danger', v < 0);
        });
    }

    $('#progVarFormula').on('change', recalcProgVar);
    if ($('#progVarFormula').length) recalcProgVar();

    /* ── Summary Progress — filter Manager ──
       Manager melekat di baris PO (project). Menyembunyikan barisnya saja tidak
       cukup: baris Customer dan Grand Total adalah angka yang sudah dijumlah di
       server, jadi keduanya HARUS dihitung ulang dari baris PO yang lolos —
       kalau tidak, tabel menampilkan total yang tidak sesuai isinya. */
    /* Harus sama dengan kunci $progGroups di markup: tiap tahap dibaca dari
       atribut data-<tahap>-qty/-value milik baris PO. Garis bawah ditulis
       sebagai tanda hubung karena begitulah atributnya ditulis di HTML. */
    var PROG_GROUPS = ['contract', 'wait_po', 'wait_bast', 'wait_invoice', 'wait_payment', 'closed'];
    function progAttr(g) { return g.replace(/_/g, '-'); }

    function progSetAngka($row, data) {
        PROG_GROUPS.forEach(function (g) {
            $row.find('[data-c="' + g + '_qty"]').text(data[g + 'Qty']);
            $row.find('[data-c="' + g + '_value"]').text(data[g + 'Value'].toLocaleString('id-ID'));
        });

        /* Sel VAR membaca angkanya dari .data() (bukan teks), jadi cache
           jQuery-nya ikut diperbarui supaya recalcProgVar() memakai angka hasil
           filter, bukan angka bawaan server. */
        var $vq = $row.find('.prog-var-qty'), $vv = $row.find('.prog-var-value');
        PROG_GROUPS.forEach(function (g) {
            var k = g.replace(/_([a-z])/g, function (m, c) { return c.toUpperCase(); });
            $vq.data(k + 'Qty', data[g + 'Qty']);
            $vv.data(k + 'Value', data[g + 'Value']);
        });
    }

    /* mgrSet = Set nama manager yang dicentang; kosong berarti "semua".
       Dulu parameternya satu nama karena filternya pilihan tunggal. */
    function applyProgManager(mgrSet) {
        var adaFilter = mgrSet && mgrSet.size > 0;
        var $tbl = $('#tblProgressSummary');
        var grand = {};
        PROG_GROUPS.forEach(function (g) { grand[g + 'Qty'] = 0; grand[g + 'Value'] = 0; });
        var totalCustomer = 0, totalPo = 0, totalPoNumber = 0;

        $tbl.find('tbody tr.prog-row-customer').each(function () {
            var $cust = $(this);
            var custKey = $cust.data('key');
            var sub = {};
            PROG_GROUPS.forEach(function (g) { sub[g + 'Qty'] = 0; sub[g + 'Value'] = 0; });
            var adaYangLolos = false;

            $tbl.find('tr.prog-row-project[data-parent="' + custKey + '"]').each(function () {
                var $proj = $(this);
                var lolos = !adaFilter || mgrSet.has(String($proj.data('manager')));

                $proj.toggleClass('prog-hidden-mgr', !lolos);
                // Baris No. PO ikut nasib induknya.
                $tbl.find('tr.prog-row-ponumber[data-parent="' + $proj.data('key') + '"]')
                    .toggleClass('prog-hidden-mgr', !lolos);

                if (!lolos) return;
                adaYangLolos = true;
                PROG_GROUPS.forEach(function (g) {
                    sub[g + 'Qty']   += parseInt($proj.attr('data-' + progAttr(g) + '-qty'), 10) || 0;
                    sub[g + 'Value'] += parseInt($proj.attr('data-' + progAttr(g) + '-value'), 10) || 0;
                });
            });

            $cust.toggleClass('prog-hidden-mgr', !adaYangLolos);
            if (adaYangLolos) {
                progSetAngka($cust, sub);

                // Badge "N PO · M No. PO" ikut hasil filter — kalau tidak, ia
                // menjanjikan lebih banyak baris daripada yang bisa dibuka.
                var $projLolos = $tbl.find('tr.prog-row-project[data-parent="' + custKey + '"]')
                    .not('.prog-hidden-mgr');
                var nPoNumber = 0;
                $projLolos.each(function () {
                    nPoNumber += $tbl.find('tr.prog-row-ponumber[data-parent="' + $(this).data('key') + '"]').length;
                });
                $cust.find('.prog-cust-count')
                    .html($projLolos.length + ' PO &middot; ' + nPoNumber + ' No. PO');

                totalCustomer++;
                totalPo += $projLolos.length;
                totalPoNumber += nPoNumber;
                PROG_GROUPS.forEach(function (g) {
                    grand[g + 'Qty']   += sub[g + 'Qty'];
                    grand[g + 'Value'] += sub[g + 'Value'];
                });
            }
        });

        var $foot = $tbl.find('tfoot tr').first();
        if ($foot.length) progSetAngka($foot, grand);
        $('#progGrandCount').html(totalCustomer + ' customer &middot; ' + totalPo + ' PO &middot; ' + totalPoNumber + ' No. PO');

        /* Label tombol: satu pilihan ditulis apa adanya, lebih dari satu
           diringkas jadi jumlahnya — menderetkan semua nama membuat tombolnya
           melebar dan mendorong kontrol di sebelahnya. */
        var terpilih = [];
        mgrSet && mgrSet.forEach(function (m) { terpilih.push(m); });
        $('#progMgrLabel').text(
            terpilih.length === 0 ? 'Manager'
                : (terpilih.length === 1 ? terpilih[0] : terpilih.length + ' Manager')
        );
        $('#progMgrBtn').toggleClass('col-mf-active', adaFilter);

        recalcProgVar();
    }

    /* ── Bersihkan filter per tabel ──
       Tiga tabel, tiga mekanisme filter yang berbeda, jadi tidak ada satu
       pemanggilan yang bisa membereskan ketiganya:
       - Collection Performance & Summary PO memakai DataTables, filternya
         disimpan TesysColumnFilter -> clearAll() yang mencabut filter kolom
         maupun filter baris sekaligus, termasuk chip-nya.
       - Summary Progress bukan DataTable; filternya centang manager + kotak
         cari yang mengatur display baris sendiri.
       Yang TIDAK ikut direset: pilihan kolom (Kolom) dan formula VAR — itu
       cara menampilkan, bukan penyaringan data, dan mengembalikannya diam-diam
       berarti mencabut pengaturan yang sengaja dipasang pengguna. */
    $('#btnClearCp').on('click', function () {
        TesysColumnFilter.clearAll('#tblCollectionPerformance');
        $('#cpShowRate100').prop('checked', false);
        $('#tblCollectionPerformance').DataTable().search('').draw();
    });

    $('#btnClearPo').on('click', function () {
        TesysColumnFilter.clearAll('#tblPoSummary');
        $('#poShowRate100').prop('checked', false);
        $('#tblPoSummary').DataTable().search('').draw();
    });

    $('#btnClearProg').on('click', function () {
        $('#progMgrList .prog-mgr-check').prop('checked', false);
        $('#progMgrSearch').val('').trigger('input');
        applyProgManager(new Set());
        $('#progSearch').val('').trigger('input');
    });

    function progMgrTerpilih() {
        var set = new Set();
        $('#progMgrList .prog-mgr-check:checked').each(function () { set.add(String(this.value)); });
        return set;
    }

    /* Menu tetap terbuka saat mencentang: Bootstrap menutup dropdown pada klik
       apa pun di dalamnya, dan menutup setiap satu manager dicentang membuat
       memilih tiga manager berarti membuka menunya tiga kali. */
    $('#progMgrFilter').on('click', '.prog-mgr-menu', function (e) { e.stopPropagation(); });

    $('#progMgrFilter').on('change', '.prog-mgr-check', function () {
        applyProgManager(progMgrTerpilih());
    });

    $('#progMgrSearch').on('input', function () {
        var q = $(this).val().toLowerCase();
        $('#progMgrList label').each(function () {
            $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1);
        });
    });

    /* ── Summary Progress — sembunyikan/tampilkan kolom ──
       Tabel ini bukan DataTable, jadi tidak bisa memakai applyColvis() yang
       bersandar pada table.column().visible(). Selnya disembunyikan langsung
       lewat data-c, lalu colspan tiap header grup dihitung ulang dari berapa
       sub-kolomnya yang masih tampak — kalau tidak, header grup tetap selebar
       dua kolom dan seluruh baris judul bergeser. */
    var PROG_COLVIS_KEY = 'progColvis_v1';

    function progColvisState() {
        var state = {};
        $('#progColvis .prog-colvis-toggle').each(function () {
            state[$(this).data('c')] = this.checked;
        });
        return state;
    }

    /* Kolom VAR dibekukan di kanan dan lebarnya dua kolom, jadi offset kanan
       kolom Qty harus sama dengan lebar kolom Value — dan itu berubah begitu
       salah satunya disembunyikan lewat menu Kolom, atau saat lebar jendela
       berubah. Nilainya ditaruh di custom property --prog-var-value-w yang
       dibaca CSS, plus penanda .prog-sticky-edge untuk menaruh bayangan hanya
       di kolom beku paling kiri. */
    function syncProgVarSticky() {
        var $tabel = $('#tblProgressSummary');
        if (!$tabel.length) return;

        var $thQty   = $tabel.find('thead th[data-c="var_qty"]');
        var $thValue = $tabel.find('thead th[data-c="var_value"]');

        // :visible ikut false saat tab Collection masih tersembunyi — lebarnya
        // memang belum bisa diukur di situ, makanya fungsi ini dipanggil lagi
        // pada shown.bs.tab.
        var lebarValue = $thValue.is(':visible') ? $thValue.outerWidth() : 0;
        $tabel[0].style.setProperty('--prog-var-value-w', lebarValue + 'px');

        // Bayangan hanya di kolom beku terkiri yang masih tampak.
        var kiri = $thQty.is(':visible') ? 'var_qty' : ($thValue.is(':visible') ? 'var_value' : null);
        $tabel.find('.prog-sticky-edge').removeClass('prog-sticky-edge');
        if (kiri) $tabel.find('.prog-sticky[data-c="' + kiri + '"]').addClass('prog-sticky-edge');
    }
    window.syncProgVarSticky = syncProgVarSticky;

    function applyProgColvis(simpan) {
        var state = progColvisState();

        $.each(state, function (key, tampil) {
            $('#tblProgressSummary [data-c="' + key + '"]').toggle(tampil);
        });

        // colspan header grup = jumlah sub-kolom yang masih tampak (0 = grup ikut hilang)
        $('#tblProgressSummary thead th[data-grp]').each(function () {
            var grp = $(this).data('grp');
            var n = (state[grp + '_qty'] ? 1 : 0) + (state[grp + '_value'] ? 1 : 0);
            $(this).attr('colspan', Math.max(1, n)).toggle(n > 0);
        });

        // Lebar kolom baru selesai dihitung ulang browser setelah toggle di atas.
        syncProgVarSticky();

        if (simpan !== false) {
            try { localStorage.setItem(PROG_COLVIS_KEY, JSON.stringify(state)); } catch (e) {}
        }
    }

    // Menu tetap terbuka saat mencentang beberapa kolom berturut-turut.
    $('#progColvis').on('click', '.js-colvis-menu', function (e) { e.stopPropagation(); });
    $('#progColvis').on('change', '.prog-colvis-toggle', function () { applyProgColvis(); });

    if ($('#progColvis').length) {
        try {
            /* Kolom yang SELALU mulai tersembunyi tiap halaman dimuat, walau
               sesi sebelumnya sempat menampilkannya. Pilihan kolom lain tetap
               diingat lewat localStorage; yang ini sengaja tidak, karena
               statusnya bawaan tampilan - bukan preferensi yang menempel. */
            var progColvisSelaluOff = @json($progColvisSelaluOff);

            var tersimpan = JSON.parse(localStorage.getItem(PROG_COLVIS_KEY) || '{}');
            $('#progColvis .prog-colvis-toggle').each(function () {
                var key = $(this).data('c');
                if (progColvisSelaluOff.indexOf(key) !== -1) { this.checked = false; return; }
                var v = tersimpan[key];
                if (typeof v === 'boolean') this.checked = v;
            });
        } catch (e) {}
        applyProgColvis(false);
    } else {
        syncProgVarSticky();
    }

    /* Tab Collection bisa belum terlihat saat load — lebar kolom baru benar
       setelah tabelnya tampil, jadi offset dihitung ulang di sana (pola sama
       dengan initCollectionTables / initCollectionCharts). */
    $('#summaryTabCollectionLink').on('shown.bs.tab', syncProgVarSticky);
    $(window).on('resize', syncProgVarSticky);

    if ($('#summaryPaneCollection').hasClass('active')) {
        initCollectionTables();
    }
    $('#summaryTabCollectionLink').on('shown.bs.tab', initCollectionTables);
});
</script>
@endif
@endpush
