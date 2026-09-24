@extends('layouts.app')

@section('title', 'Purchase Order')

@section('content')

<!-- MAIN CONTENT -->
<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Purchase Order</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item active">Purchase Order</li>
            </ol>
        </div>

        <!-- SUMMARY WORKFLOW (clickable, filter tabel Purchase Order) -->
        @php
            $poSteps = [
                ['Draft',         $summary['draft'],         'secondary', 'DRAFT'],
                ['Submitted',     $summary['submitted'],     'warning',   'SUBMITTED'],
                ['Waiting PO',    $summary['waiting_po'],    'info',      'WAITING_PO'],
                ['Active',        $summary['active'],        'success',   'ACTIVE'],
                ['Progress Done', $summary['progress_done'], 'primary',   'PROGRESS_DONE'],
                ['Paid',          $summary['paid'],          'success',   'PAID'],
                /*
                  Tahap "Closing" sengaja TIDAK ditampilkan di stepper ini —
                  penyembunyian visual saja. Statusnya tetap hidup di data,
                  tetap dihitung di $summary, dan aksi Submit/Approve Closing
                  di kolom Action tidak berubah; yang hilang cuma kotaknya di
                  bilah ringkasan (termasuk penyaringan tabel lewat klik).
                */
                ['Closed',        $summary['closed'],        'dark',      'CLOSED'],
            ];
        @endphp
        <x-workflow-stepper :steps="$poSteps" :compact="true" table="projectTable" field="status" />

        <div class="card card-primary card-outline shadow-sm mt-3">

            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-file-contract mr-1"></i> Daftar Purchase Order</h3>
                <div class="ml-auto d-flex" style="gap:8px;">
                    <button id="refreshTable" class="btn btn-secondary btn-sm">
                        <i class="fas fa-sync-alt mr-1"></i> Refresh
                    </button>
                </div>
            </div>

            <div class="card-body">

                {{-- Daftar dibatasi 1.000 baris terbaru (Concerns\CapsLargeLists);
                     angka pada stepper tetap dihitung dari seluruh data. --}}
                @if(!empty($truncated))
                <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:12.5px;">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Menampilkan <strong>{{ number_format($maxRows, 0, ',', '.') }}</strong> PO terbaru dari total
                    <strong>{{ number_format($total, 0, ',', '.') }}</strong>.
                    Gunakan pencarian/filter untuk mempersempit, atau <strong>Export Excel</strong> untuk data lengkap.
                </div>
                @endif


                <!-- FILTER BAR -->
                <div class="custom-toolbar mb-3 d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
                    <div class="custom-toolbar-left d-flex align-items-center flex-wrap" style="gap:8px;">
                        <div class="toolbar-row">
                            <span class="toolbar-label">Cari :</span>
                            <div class="toolbar-input" id="customSearch"></div>
                        </div>
                        <button aria-label="Clear Filter" type="button" id="btnClearFilterPO" class="btn btn-outline-secondary btn-sm" title="Clear Filter">
                            <i class="fas fa-eraser"></i>
                        </button>
                        <div id="poRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>
                    </div>
                    <div class="custom-toolbar-right d-flex flex-wrap align-items-center" style="gap:8px;">
                        <div class="dropdown" id="colVisDropdown">
                            {{-- data-display="static" mematikan Popper: menu tetap menempel di bawah
                                 tombol. Tanpa ini, daftar kolom yang panjang bikin Popper "flip" ke atas
                                 dan menutupi baris tombol toolbar, apalagi karena tiap centang memicu
                                 columns.adjust() yang mengubah tinggi/lebar halaman di belakang menu. --}}
                            <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle"
                                    data-toggle="dropdown" data-display="static">
                                <i class="fas fa-columns mr-1"></i> Kolom
                            </button>
                            <div class="dropdown-menu dropdown-menu-right p-2" style="min-width:200px;max-height:340px;overflow-y:auto;">
                                @php
                                    $poHideableCols = [
                                        'poqty'      => 'Qty PO',
                                        'type'       => 'Tipe',
                                        'uploaded'   => 'Uploaded',
                                        'margin'     => 'Margin',
                                        'contract'   => 'Contract',
                                        'povalue'    => 'PO Value',
                                        'budget'     => 'Budget',
                                        'varcost'    => 'Variable Cost',
                                        'payment'    => 'Received Payment',
                                        'profit'     => 'Gross Profit',
                                        'overhead'   => 'Overhead',
                                        'fixedcost'  => 'Fixed Cost',
                                        'netprofit'  => 'Net Profit',
                                    ];
                                @endphp
                                @foreach($poHideableCols as $key => $label)
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input col-vis-toggle" id="colVis_{{ $key }}" data-column="{{ $key }}">
                                    <label class="custom-control-label" for="colVis_{{ $key }}" style="font-size:12px;">{{ $label }}</label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @if(Auth::user()->hasRole(['superadmin','approver']))
                        <a href="{{ route('project.export-excel') }}" id="btnExportPO" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-file-excel mr-1"></i> Export Excel
                        </a>
                        @endif
                        @if(Auth::user()->hasRole(['superadmin','head']))
                        <a href="{{ url('/project/create') }}" class="btn btn-success btn-sm">
                            <i class="fas fa-plus mr-1"></i> Tambah PO
                        </a>
                        @endif
                    </div>
                </div>

                <!-- TABLE -->
                <div class="po-table-wrap" id="poTableWrap">
                    <table id="projectTable" class="table table-bordered mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" class="text-nowrap" style="width:1%;">No</th>
                                <th scope="col" class="text-nowrap po-col-kode" style="width:1%;">Kode</th>
                                <th scope="col">Scope of Work</th>
                                <th scope="col" class="text-center text-nowrap" style="width:1%;">Status</th>
                                <th scope="col" class="text-center text-nowrap" style="width:1%;" title="Jumlah No. PO yang sudah dientri pada kode ini (dibanding PO Quantity kalau diisi).">Qty PO</th>
                                <th scope="col" class="text-center text-nowrap" style="width:1%;">Tipe</th>
                                <th scope="col" class="text-center text-nowrap" style="width:1%;">Progress</th>
                                <th scope="col" class="text-center col-wrap-header" style="width:110px;" title="Lingkaran isi = status Upload dokumen (No. PO). Cincin luar = status Progress milestone (Project Monitoring).">Uploaded</th>
                                <th scope="col" class="text-center col-wrap-header" style="width:80px;">Margin</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;">Contract</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;">PO Value</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;">Budget</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;">Variable Cost</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;">Received Payment</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;">Gross Profit</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;">Overhead</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;">Fixed Cost</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;">Net Profit</th>
                                <th scope="col" class="text-center action-column" style="width:1%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($projects as $project)
                            @php
                                $bpTotal    = $project->budgetPlanTotal();
                                $costTotal  = $project->variableCostTotal();
                                $paymentTotal = $project->paymentTotal();
                                $grossProfitTotal = $paymentTotal - $costTotal;
                                $netProfitTotal = $grossProfitTotal - ($project->overhead ?? 0) - ($project->fixed_cost ?? 0);
                                $projMargin = $project->projectMargin();
                                $planGm     = $project->grossMargin();
                                $progress   = $project->implementationProgress();
                                $pmInternal = strtoupper($project->pm_internal_name ?: '-');
                                $hasOverdueInvoice = $project->invoices->contains(fn ($inv) => $inv->display_status === 'OVERDUE');
                                $waitingInvoiceCount = $project->waitingInvoicePoCount();
                                $waitingBudget = $project->isWaitingBudget();
                                $poActual = $project->poNumbers->count();
                                $marginBadge = fn ($v) => $v >= 20 ? 'success' : ($v >= 0 ? 'warning' : 'danger');
                                $workBadge    = \App\Models\Project::progressBadge($project->workProgressStatus());
                                $billingBadge = \App\Models\Project::progressBadge($project->billingProgressStatus());
                                $paymentBadge = \App\Models\Project::progressBadge($project->paymentProgressStatus());
                                $monSc     = $project->monitoring_status_color;
                                $monLabels = ['NOT_YET' => 'Not Yet', 'ON_GOING' => 'On Going', 'DONE' => 'Done'];
                                $monLabel  = fn ($v) => $monLabels[$v] ?? $v;
                            @endphp
                            <tr data-id="{{ $project->id }}"
                                data-status="{{ $project->status }}"
                                data-customer="{{ $project->customer->name ?? '' }}"
                                data-financing="{{ $project->financing ?? '' }}"
                                data-pm="{{ $pmInternal }}"
                                data-type="{{ $project->type_label ?? '' }}">
                                <td class="text-nowrap">{{ $loop->iteration }}</td>

                                <td class="text-nowrap po-col-kode">
                                    <button type="button" class="po-code-link" data-id="{{ $project->id }}" title="Detail PO">{{ $project->code ?? '-' }}</button>
                                </td>

                                <td>
                                    <div style="font-size:13px;font-weight:600;">
                                        {{ $project->name }}
                                        @if(in_array($project->status, ['WAITING_PO', 'ACTIVE']))
                                            @if($project->status === 'WAITING_PO' && $project->monitoring_po_received_status === 'DONE')
                                                <span class="badge badge-warning ml-1" style="font-size:9px;">Waiting Upload PO</span>
                                            @elseif($project->monitoring_bast_status === 'DONE')
                                                <span class="badge badge-warning ml-1" style="font-size:9px;">Waiting Upload BAST</span>
                                            @elseif($project->monitoring_payment_status === 'DONE')
                                                <span class="badge badge-warning ml-1" style="font-size:9px;">Waiting Closed</span>
                                            @endif
                                        @endif
                                        @if($waitingInvoiceCount > 0)
                                            <span class="badge badge-info ml-1" style="font-size:9px;"
                                                  title="{{ $waitingInvoiceCount }} No. PO sudah BAST tapi belum ditagihkan">Waiting Invoice</span>
                                        @endif
                                        @if($waitingBudget)
                                            <span class="badge badge-secondary ml-1" style="font-size:9px;"
                                                  title="Sudah ada No. PO tapi belum ada Budget Plan yang final (Active/Closed)">Waiting Budget</span>
                                        @endif
                                        @if($hasOverdueInvoice)
                                            <span class="badge badge-danger ml-1" style="font-size:9px;">Invoice Overdue</span>
                                        @endif
                                    </div>
                                    @php
                                        $subParts = [];
                                        if ($project->customer) {
                                            $subParts[] = $project->customer->name;
                                        }
                                        $subParts[] = $pmInternal;
                                    @endphp
                                    @if(count($subParts))
                                        <small class="text-muted">{{ implode(' • ', $subParts) }}</small>
                                    @endif
                                </td>

                                <td class="text-center text-nowrap">
                                    <x-status :status="$project->status" context="project"
                                              class="po-status-link" :data-id="$project->id" />
                                </td>

                                {{-- Qty PO = jumlah No. PO yang benar-benar sudah dientri pada 1 kode.
                                     Angka pembanding di kanan = PO Quantity (ekspektasi manual), ditandai
                                     kuning kalau keduanya tidak cocok — sama seperti sub-teks di PO Value. --}}
                                <td class="text-center text-nowrap col-raw-poqty" data-raw="{{ $poActual }}"
                                    data-order="{{ $poActual }}">
                                    <button type="button" class="po-cell-link po-value-link {{ $project->poAdjustmentRequired() ? 'text-warning' : '' }}"
                                            data-id="{{ $project->id }}"
                                            title="{{ $poActual }} No. PO dientri{{ $project->po_quantity ? ' dari ' . $project->po_quantity . ' PO Quantity' : '' }}">
                                        {{ $poActual }}{{ $project->po_quantity ? ' / ' . $project->po_quantity : '' }}
                                    </button>
                                </td>

                                <td class="text-center text-nowrap">
                                    @if($project->type)
                                        <span class="badge badge-{{ $project->type_color }}" style="font-size:10px;">{{ $project->type_label }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>

                                <td class="text-center text-nowrap">
                                    <a href="{{ route('project-monitoring.show', $project->id) }}" target="_blank"
                                       class="po-implement-link" title="Project Monitoring">{{ rtrim(rtrim(number_format($progress, 1, ',', '.'), '0'), ',') }}%</a>
                                </td>

                                <td class="text-center text-nowrap">
                                    <span class="badge badge-{{ $monSc['po_received'] }} po-mini-badge" title="Progress PO Received: {{ $monLabel($project->monitoring_po_received_status) }}">R</span>
                                    <span class="badge badge-{{ $workBadge['color'] }} po-mini-badge border border-{{ $monSc['bast'] }}" title="Uploaded BAST: {{ $workBadge['label'] }} &middot; Progress BAST: {{ $monLabel($project->monitoring_bast_status) }}">B</span>
                                    <span class="badge badge-{{ $billingBadge['color'] }} po-mini-badge border border-{{ $monSc['invoice'] }}" title="Uploaded Invoice: {{ $billingBadge['label'] }} &middot; Progress Invoice: {{ $monLabel($project->monitoring_invoice_status) }}">I</span>
                                    <span class="badge badge-{{ $paymentBadge['color'] }} po-mini-badge border border-{{ $monSc['payment'] }}" title="Uploaded Payment: {{ $paymentBadge['label'] }} &middot; Progress Payment: {{ $monLabel($project->monitoring_payment_status) }}">P</span>
                                </td>

                                <td class="text-nowrap po-margin-cell">
                                    <div><span class="po-margin-label">PM</span> <span class="text-{{ $marginBadge($projMargin) }}">{{ rtrim(rtrim(number_format($projMargin, 1, ',', '.'), '0'), ',') }}%</span></div>
                                    <div><span class="po-margin-label">GM</span> <span class="text-{{ $marginBadge($planGm) }}">{{ rtrim(rtrim(number_format($planGm, 1, ',', '.'), '0'), ',') }}%</span></div>
                                </td>

                                <td class="text-right text-nowrap col-raw-contract" data-raw="{{ $project->contract ?? 0 }}">
                                    <button type="button" class="po-cell-link po-summary-link" data-id="{{ $project->id }}" title="Lihat Summary">{{ $project->contract_formatted }}</button>
                                </td>

                                <td class="text-right text-nowrap col-raw-value" data-raw="{{ $project->value ?? 0 }}">
                                    <button type="button" class="po-cell-link po-value-link" data-id="{{ $project->id }}" title="Lihat No. PO">{{ $project->value_formatted }}</button>
                                    {{-- PO Quantity = ekspektasi manual; angka kiri = No. PO yang benar-benar
                                         sudah dientri. Ditandai kuning kalau tidak cocok (poAdjustmentRequired). --}}
                                    <div class="po-qty-sub {{ $project->poAdjustmentRequired() ? 'is-mismatch' : '' }}"
                                         title="{{ $poActual }} No. PO dientri{{ $project->po_quantity ? ' dari ' . $project->po_quantity . ' PO Quantity' : '' }}">
                                        {{ $poActual }}{{ $project->po_quantity ? ' / ' . $project->po_quantity : '' }} No. PO
                                    </div>
                                </td>

                                <td class="text-right text-nowrap col-raw-bp" data-raw="{{ $bpTotal }}">
                                    <button type="button" class="po-cell-link po-budget-link" data-id="{{ $project->id }}" title="Lihat Budget">{{ number_format($bpTotal, 0, ',', '.') }}</button>
                                </td>

                                <td class="text-right text-nowrap col-raw-cr" data-raw="{{ $costTotal }}">
                                    <button type="button" class="po-cell-link po-cost-link" data-id="{{ $project->id }}" title="Lihat Cost">{{ number_format($costTotal, 0, ',', '.') }}</button>
                                </td>

                                <td class="text-right text-nowrap col-raw-payment" data-raw="{{ $paymentTotal }}">
                                    <button type="button" class="po-cell-link po-invoice-link" data-id="{{ $project->id }}" title="Lihat Invoice">{{ number_format($paymentTotal, 0, ',', '.') }}</button>
                                </td>

                                <td class="text-right text-nowrap col-raw-profit" data-raw="{{ $grossProfitTotal }}">
                                    <button type="button" class="po-cell-link po-summary-link {{ $grossProfitTotal < 0 ? 'po-neg' : '' }}" data-id="{{ $project->id }}" title="Lihat Summary">{{ ($grossProfitTotal < 0 ? '-' : '') }}{{ number_format(abs($grossProfitTotal), 0, ',', '.') }}</button>
                                </td>

                                <td class="text-right text-nowrap col-raw-overhead" data-raw="{{ $project->overhead ?? 0 }}">
                                    <button type="button" class="po-cell-link po-cost-link" data-id="{{ $project->id }}" title="Lihat Cost">{{ $project->overhead_formatted }}</button>
                                </td>

                                <td class="text-right text-nowrap col-raw-fixedcost" data-raw="{{ $project->fixed_cost ?? 0 }}">
                                    <button type="button" class="po-cell-link po-cost-link" data-id="{{ $project->id }}" title="Lihat Cost">{{ $project->fixed_cost_formatted }}</button>
                                </td>

                                <td class="text-right text-nowrap col-raw-net" data-raw="{{ $netProfitTotal }}">
                                    <button type="button" class="po-cell-link po-summary-link {{ $netProfitTotal < 0 ? 'po-neg' : '' }}" data-id="{{ $project->id }}" title="Lihat Summary">{{ ($netProfitTotal < 0 ? '-' : '') }}{{ number_format(abs($netProfitTotal), 0, ',', '.') }}</button>
                                </td>

                                <td class="text-center action-column">
                                    @php
                                        /*
                                          Gerbang peran disalin apa adanya dari versi sebelumnya kolom ini —
                                          tidak ada aturan yang ditambah, dikurangi, atau digabung. Yang
                                          berubah hanya susunannya: satu aksi utama yang bernama, sisanya
                                          masuk menu, supaya baris tidak lagi berisi deretan ikon tanpa label.
                                        */
                                        $pengguna = Auth::user();

                                        $bolehSubmit    = $project->status === 'DRAFT' && $pengguna->hasRole(['superadmin','head']);
                                        $bolehEditPenuh = ($pengguna->hasRole(['superadmin','finance','finance_manager']) && in_array($project->status, ['DRAFT','SUBMITTED']))
                                            || ($pengguna->hasRole(['head']) && $project->status === 'DRAFT');
                                        // Menambah No. PO dan mengaktifkan dijaga gerbang yang sama persis.
                                        $bolehWaitingPo = $project->status === 'WAITING_PO' && $pengguna->hasRole(['superadmin','finance','finance_manager','head']);
                                        $bolehFinance   = $project->status === 'SUBMITTED' && $pengguna->hasRole(['superadmin','finance','finance_manager']);
                                        $bolehClosing   = $project->status === 'PAID' && $pengguna->hasRole(['superadmin','head']);
                                        $bolehVerClosing = $project->status === 'CLOSING' && $pengguna->hasRole(['superadmin','finance','finance_manager']);
                                        $bolehHapus     = $project->status === 'DRAFT' && $pengguna->hasRole(['superadmin','head']);

                                        /*
                                          Submit BAST: muncul begitu PO ini punya No. PO yang sudah
                                          dirilis dan BAST-nya belum tuntas — gerbang perannya sama
                                          persis dengan halaman Submit BAST (ProjectController::
                                          showBastPage) dan daftar No. PO yang dianggap layak di sana
                                          (status RELEASED atau PARTIAL). Sebelumnya tombol ini hanya
                                          ada di dalam modal detail tab "No. PO", jadi tugas rutin itu
                                          menuntut dua klik pembuka sebelum bisa dimulai.
                                          poNumbers sudah ikut ter-eager-load di ProjectController::
                                          index(), jadi pengecekan ini tidak menambah query per baris.
                                        */
                                        $bolehBast = $pengguna->hasRole(['superadmin','head'])
                                            && $project->poNumbers->whereIn('status', ['RELEASED', 'PARTIAL'])->isNotEmpty();

                                        $adaAksi = $bolehSubmit || $bolehEditPenuh || $bolehWaitingPo || $bolehFinance
                                            || $bolehClosing || $bolehVerClosing || $bolehHapus;
                                    @endphp

                                    <div class="action-buttons ts-actions">

                                        {{-- Tanpa aksi, barisnya tetap punya jalan masuk ke detail --}}
                                        @unless($adaAksi)
                                        <button type="button" class="ts-action-primary is-neutral po-summary-link"
                                                data-id="{{ $project->id }}"
                                                title="Lihat detail" aria-label="Lihat detail {{ $project->code }}">
                                            <i class="fas fa-eye" aria-hidden="true"></i>
                                        </button>
                                        @endunless

                                        {{-- AKSI UTAMA — satu per baris, selalu bernama --}}

                                        @if($bolehSubmit)
                                        <form action="{{ url('/project/' . $project->id . '/submit') }}"
                                              method="POST" class="frmSubmitProject d-inline" target="_blank">
                                            @csrf
                                            <button type="submit" class="ts-action-primary" title="Submit ke Finance">
                                                <i class="fas fa-paper-plane"></i><span class="ts-al">Submit</span>
                                            </button>
                                        </form>
                                        @elseif($bolehFinance)
                                        <button type="button" class="ts-action-primary btn-finance-approve"
                                                data-id="{{ $project->id }}" data-name="{{ $project->name }}"
                                                title="Approve">
                                            <i class="fas fa-check"></i><span class="ts-al">Approve</span>
                                        </button>
                                        @elseif($bolehWaitingPo)
                                        <a href="{{ url('/project/' . $project->id . '/activate') }}"
                                           class="ts-action-primary" title="Lengkapi Data &amp; Aktifkan" target="_blank">
                                            <i class="fas fa-play-circle" aria-hidden="true"></i><span class="ts-al">Aktifkan</span>
                                        </a>
                                        @elseif($bolehClosing)
                                        <button type="button" class="ts-action-primary btn-submit-closing"
                                                data-id="{{ $project->id }}" data-name="{{ $project->name }}"
                                                title="Submit MoM Closing">
                                            <i class="fas fa-file-contract"></i><span class="ts-al">Submit Closing</span>
                                        </button>
                                        @elseif($bolehVerClosing)
                                        <button type="button" class="ts-action-primary btn-verify-closing"
                                                data-id="{{ $project->id }}" data-name="{{ $project->name }}"
                                                title="Approve Closing">
                                            <i class="fas fa-check"></i><span class="ts-al">Approve Closing</span>
                                        </button>
                                        @elseif($bolehEditPenuh)
                                        <a href="{{ url('/project/' . $project->id . '/edit') }}"
                                           class="ts-action-primary is-neutral" title="Edit" target="_blank">
                                            <i class="fas fa-pen" aria-hidden="true"></i><span class="ts-al">Edit</span>
                                        </a>
                                        @elseif($bolehBast)
                                        <a href="{{ url('/project/' . $project->id . '/submit-bast') }}"
                                           class="ts-action-primary" title="Submit BAST" target="_blank">
                                            <i class="fas fa-file-signature" aria-hidden="true"></i><span class="ts-al">Submit BAST</span>
                                        </a>
                                        @endif

                                        {{-- AKSI LAIN — termasuk semua aksi mundur/merusak --}}
                                        @php
                                            // Edit hanya masuk menu bila slot aksi utama sudah terpakai
                                            // Submit atau Approve, supaya tidak muncul dua kali.
                                            $editDiMenu     = $bolehEditPenuh && ($bolehSubmit || $bolehFinance);
                                            $tambahPoDiMenu = $bolehWaitingPo;
                                            // Submit BAST menempati slot aksi utama hanya kalau slot itu
                                            // masih kosong; bila sudah dipakai aksi yang lebih mendesak
                                            // (Approve Closing, dsb.) ia turun ke menu — tetap satu klik
                                            // lebih dekat daripada harus lewat modal detail.
                                            $bastDiMenu = $bolehBast && ($bolehSubmit || $bolehFinance || $bolehWaitingPo
                                                || $bolehClosing || $bolehVerClosing || $bolehEditPenuh);
                                            $adaMenu = $editDiMenu || $tambahPoDiMenu || $bastDiMenu || $bolehFinance || $bolehVerClosing || $bolehHapus;
                                        @endphp

                                        @if($adaMenu)
                                        <button type="button" class="ts-action-more"
                                                aria-haspopup="true" aria-expanded="false"
                                                title="Aksi lain" aria-label="Aksi lain untuk {{ $project->code }}">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>

                                        <div class="ts-action-menu dropdown-menu" hidden>

                                            @if($editDiMenu)
                                            <a href="{{ url('/project/' . $project->id . '/edit') }}"
                                               class="dropdown-item" target="_blank">
                                                <i class="fas fa-pen"></i>Edit
                                            </a>
                                            @endif

                                            @if($bastDiMenu)
                                            <a href="{{ url('/project/' . $project->id . '/submit-bast') }}"
                                               class="dropdown-item" target="_blank">
                                                <i class="fas fa-file-signature"></i>Submit BAST
                                            </a>
                                            @endif

                                            @if($tambahPoDiMenu)
                                            <a href="{{ url('/project/' . $project->id . '/edit-po-numbers') }}"
                                               class="dropdown-item" target="_blank">
                                                <i class="fas fa-pen"></i>Tambah No. PO
                                            </a>
                                            @endif

                                            @if($bolehFinance)
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item is-danger btn-finance-revise"
                                                    data-id="{{ $project->id }}">
                                                <i class="fas fa-undo"></i>Minta Revisi
                                            </button>
                                            @endif

                                            @if($bolehVerClosing)
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item is-danger btn-reject-closing"
                                                    data-id="{{ $project->id }}">
                                                <i class="fas fa-times"></i>Tolak MoM
                                            </button>
                                            @endif

                                            @if($bolehHapus)
                                            <div class="dropdown-divider"></div>
                                            <form action="{{ url('/project/' . $project->id) }}"
                                                  method="POST" class="frmDeleteProject" target="_blank">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item is-danger">
                                                    <i class="fas fa-trash"></i>Hapus
                                                </button>
                                            </form>
                                            @endif

                                        </div>
                                        @endif

                                    </div>
                                </td>
                            </tr>
                            @empty
                            {{-- Sengaja tidak ada baris di sini — lihat catatan di language.emptyTable;
                                 baris kosong buatan sendiri akan dihitung DataTables sebagai baris data. --}}
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr style="background:var(--surface-2);">
                                <th></th>
                                <th class="text-nowrap po-col-kode" style="font-size:12px;">Total</th>
                                <th class="text-nowrap" style="font-size:11px;color:var(--text-faint);">(data tampil)</th>
                                <th></th>
                                <th class="text-center text-nowrap" id="poFooterQty" style="font-size:12px;color:var(--info);"></th>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th class="text-right text-nowrap" id="poFooterContract" style="font-size:12px;color:var(--info);"></th>
                                <th class="text-right text-nowrap" id="poFooterValue" style="font-size:12px;color:var(--info);"></th>
                                <th class="text-right text-nowrap" id="poFooterBp" style="font-size:12px;color:var(--text-faint);"></th>
                                <th class="text-right text-nowrap" id="poFooterCr" style="font-size:12px;color:var(--warning);"></th>
                                <th class="text-right text-nowrap" id="poFooterPayment" style="font-size:12px;color:var(--success);"></th>
                                <th class="text-right text-nowrap" id="poFooterProfit" style="font-size:12px;color:var(--success);"></th>
                                <th></th>
                                <th></th>
                                <th class="text-right text-nowrap" id="poFooterNet" style="font-size:12px;color:var(--success);"></th>
                                <th class="action-column"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            </div>
        </div>

    </div>
</div>

<!-- ================================================================ -->
<!-- MODAL DETAIL PROJECT                                              -->
<!-- ================================================================ -->
<div class="modal fade" id="mdlDetailProject" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-xxl" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            {{-- Tidak ada pita header di sini: judul, kode, dan tombol tutup
                 pindah ke kepala panel ringkas gelap. Lihat modal detail Cost
                 Request untuk pola yang sama. --}}
            <div class="modal-body dm-body" style="padding:0;background:var(--surface-2);">
                {{-- Kerangka mengikuti bentuk isi modal (kolom kiri ringkasan,
                     kanan tabel) supaya tidak ada lompatan tata letak saat data tiba. --}}
                <div id="projectDetailLoading" style="padding:18px;display:flex;gap:16px;flex-wrap:wrap;min-height:420px;">
                    <div style="flex:0 0 200px;max-width:100%;background:var(--surface);border-radius:var(--radius-lg);padding:16px;">
                        <x-skeleton type="card" />
                        <x-skeleton type="lines" :rows="6" />
                    </div>
                    <div style="flex:1;min-width:240px;background:var(--surface);border-radius:var(--radius-lg);padding:16px;">
                        <x-skeleton type="lines" :rows="2" />
                        <x-skeleton type="table" :rows="9" :cols="4" />
                    </div>
                </div>
                <div id="projectDetailContent" style="display:none;">
                    <div class="prj-modal-flex">
                        <!-- LEFT -->
                        <div class="prj-sidebar dm-side" style="background:var(--charcoal);padding:28px 20px;">

                            <div class="dm-head">
                                <button type="button" class="close dm-close" data-dismiss="modal"
                                        aria-label="Tutup"><span aria-hidden="true">&times;</span></button>

                                <div id="prjCode" class="dm-code"></div>
                                <h5 class="dm-title" id="mdlProjectTitle">Detail PO</h5>
                                <small id="mdlProjectSubtitle" class="dm-sub-hidden"></small>

                                <div class="dm-badges">
                                    <span id="prjStatusBadge"></span>
                                </div>
                            </div>
                            <hr style="border-color:rgba(255,255,255,0.08);">
                            <div class="mb-3">
                                <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Customer</span>
                                <div id="prjCustomer" class="text-white mt-1" style="font-size:12px;"></div>
                            </div>
                            <div class="mb-3">
                                <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Manager</span>
                                <div id="prjManager" class="text-white mt-1" style="font-size:12px;"></div>
                            </div>
                            <div class="mb-3">
                                <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Nilai Kontrak</span>
                                <div id="prjValue" style="color:var(--accent);font-size:18px;font-weight:700;margin-top:4px;"></div>
                            </div>
                            <div class="mb-3">
                                <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Nilai PO</span>
                                <div id="prjPoValue" class="text-white mt-1" style="font-size:13px;font-weight:600;"></div>
                            </div>
                            <div class="mb-3">
                                <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Input By</span>
                                <div id="prjInputBy" class="text-white mt-1" style="font-size:12px;"></div>
                            </div>
                            <div id="prjSideExtra"></div>
                            <div class="prj-sidebar-spacer"></div>

                            {{-- Aksi dokumen duduk di kaki panel gelap. Dulu ia
                                 berada di pita footer bersama tombol Close. --}}
                            <div class="dm-actions">
                                <a id="prjEditBtn" href="#" class="btn btn-warning btn-sm" style="display:none;">
                                    <i class="fas fa-edit mr-1"></i> Edit
                                </a>
                            </div>
                        </div>

                        <!-- RIGHT TABS -->
                        <div class="prj-main-panel" style="padding:24px;">
                            <ul class="nav nav-tabs mb-3" id="prjDetailTabs" style="border-bottom:2px solid var(--border);flex-wrap:wrap;">
                                <li class="nav-item">
                                    <a class="nav-link active" data-toggle="tab" href="#prjPaneInfo" style="font-size:12px;font-weight:600;padding:8px 10px;">
                                        <i class="fas fa-clipboard-list mr-1"></i> Summary
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#prjPaneDetails" style="font-size:12px;font-weight:600;padding:8px 10px;">
                                        <i class="fas fa-project-diagram mr-1"></i> Milestone
                                        <span id="prjDetailCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#prjPanePics" style="font-size:12px;font-weight:600;padding:8px 10px;">
                                        <i class="fas fa-users mr-1"></i> PIC
                                        <span id="prjPicCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#prjPaneBudgets" style="font-size:12px;font-weight:600;padding:8px 10px;">
                                        <i class="fas fa-money-check-alt mr-1"></i> Budget
                                        <span id="prjBudgetCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#prjPanePoNumbers" style="font-size:12px;font-weight:600;padding:8px 10px;">
                                        <i class="fas fa-file-signature mr-1"></i> No. PO
                                        <span id="prjPoNumberCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#prjPaneCost" style="font-size:12px;font-weight:600;padding:8px 10px;">
                                        <i class="fas fa-hand-holding-usd mr-1"></i> Cost
                                        <span id="prjCostCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#prjPaneInvoice" style="font-size:12px;font-weight:600;padding:8px 10px;">
                                        <i class="fas fa-file-invoice-dollar mr-1"></i> Invoice
                                        <span id="prjInvoiceCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#prjPaneUsers" style="font-size:12px;font-weight:600;padding:8px 10px;">
                                        <i class="fas fa-user-shield mr-1"></i> Access
                                        <span id="prjUserCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#prjPaneAttachments" style="font-size:12px;font-weight:600;padding:8px 10px;">
                                        <i class="fas fa-paperclip mr-1"></i> Attachment
                                        <span id="prjAttCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#prjPaneLogs" style="font-size:12px;font-weight:600;padding:8px 10px;">
                                        <i class="fas fa-history mr-1"></i> Log
                                    </a>
                                </li>
                            </ul>
                            <div class="tab-content">
                                <!-- TAB SUMMARY -->
                                <div class="tab-pane fade show active" id="prjPaneInfo">
                                    <!-- KPI strip -->
                                    <div id="prjSummaryKpi" class="mb-3"></div>

                                    {{-- Satu informasi satu baris. Pola kotak-per-informasi
                                         yang lama membuat wadahnya lebih menonjol daripada
                                         isinya, dan mata harus melompat mengikuti grid alih-alih
                                         menyusuri satu kolom. --}}
                                    <div class="ts-deflist mb-3">
                                        <div class="ts-deflist-head">Identitas</div>

                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Reference</span>
                                            <span id="infoCode" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Scope of Work</span>
                                            <span class="ts-deflist-value d-flex align-items-center justify-content-end" style="gap:8px;">
                                                <span id="infoStatus"></span>
                                                <span id="infoName"></span>
                                            </span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Customer</span>
                                            <span id="infoCustomer" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Manager</span>
                                            <span id="infoManager" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Tipe</span>
                                            <span id="infoType" class="ts-deflist-value"></span>
                                        </div>
                                        @if(!Auth::user()->hasRole(['head']))
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Financing</span>
                                            <span id="infoDoneBy" class="ts-deflist-value"></span>
                                        </div>
                                        @endif

                                        <div class="ts-deflist-head">Administrasi</div>

                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">PO Quantity</span>
                                            <span id="infoPoQuantity" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Term of Payment</span>
                                            <span id="infoToP" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Submitted</span>
                                            <span id="infoSubmittedAt" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Approved By</span>
                                            <span id="infoApprovedBy" class="ts-deflist-value"></span>
                                        </div>
                                    </div>
                                    <span id="infoPoNumber" style="display:none;"></span>

                                    <div id="prjBastToggleWrap" style="display:none;">
                                        <button type="button" class="btn btn-sm btn-link p-0" id="prjBastToggle" style="font-size:12px;color:var(--text-faint);text-decoration:none;">
                                            <i class="fas fa-chevron-right mr-1" id="prjBastToggleIcon" style="font-size:10px;transition:transform .15s;display:inline-block;"></i> BAST Info
                                        </button>
                                        <div id="prjBastCollapse" style="display:none;" class="mt-2 mb-3">
                                            <div class="ts-deflist">
                                                <div class="ts-deflist-row"><span class="ts-deflist-label">No BAST</span><span id="infoBastNo" class="ts-deflist-value"></span></div>
                                                <div class="ts-deflist-row"><span class="ts-deflist-label">Tanggal BAST</span><span id="infoBastTgl" class="ts-deflist-value"></span></div>
                                                <div class="ts-deflist-row"><span class="ts-deflist-label">Nilai BAST</span><span id="infoBastNilai" class="ts-deflist-value"></span></div>
                                                <div class="ts-deflist-row"><span class="ts-deflist-label">Submitted By</span><span id="infoBastBy" class="ts-deflist-value"></span></div>
                                                <div class="ts-deflist-row"><span class="ts-deflist-label">Catatan BAST</span><span id="infoBastNotes" class="ts-deflist-value" style="white-space:pre-line;"></span></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="prjClosedToggleWrap" style="display:none;">
                                        <button type="button" class="btn btn-sm btn-link p-0" id="prjClosedToggle" style="font-size:12px;color:var(--text-faint);text-decoration:none;">
                                            <i class="fas fa-chevron-right mr-1" id="prjClosedToggleIcon" style="font-size:10px;transition:transform .15s;display:inline-block;"></i> Closing Info
                                        </button>
                                        <div id="prjClosedCollapse" style="display:none;" class="mt-2 mb-3">
                                            <div class="ts-deflist">
                                                <div class="ts-deflist-row"><span class="ts-deflist-label">Closed By</span><span id="infoClosedBy" class="ts-deflist-value"></span></div>
                                                <div class="ts-deflist-row"><span class="ts-deflist-label">Closing Notes</span><span id="infoClosingNotes" class="ts-deflist-value" style="white-space:pre-line;"></span></div>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="button" class="btn btn-sm btn-link p-0" id="prjNotesToggle" style="font-size:12px;color:var(--text-faint);text-decoration:none;">
                                        <i class="fas fa-chevron-right mr-1" id="prjNotesToggleIcon" style="font-size:10px;transition:transform .15s;display:inline-block;"></i> Notes
                                    </button>
                                    <div id="prjNotesCollapse" style="display:none;" class="mt-2">
                                        <div class="ts-deflist">
                                            <div class="ts-deflist-row">
                                                <span class="ts-deflist-label">Notes</span>
                                                <span id="infoNotes" class="ts-deflist-value" style="white-space:pre-line;"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- TAB MILESTONE -->
                                <div class="tab-pane fade" id="prjPaneDetails">
                                    <div id="prjMilestoneDuration" class="mb-2"></div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered prj-tab-table mb-0">
                                            <thead style="background:var(--surface-2);">
                                                <tr>
                                                    <th scope="col" style="width:1%;">No</th>
                                                    <th scope="col" class="prj-col-wide">Milestone</th>
                                                    <th scope="col">Status</th>
                                                    <th scope="col">Plan Start</th>
                                                    <th scope="col">Plan Finish</th>
                                                    <th scope="col">Actual Start</th>
                                                    <th scope="col">Actual Finish</th>
                                                    <th scope="col">Remark</th>
                                                    <th scope="col" class="text-center">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="prjDetailBody"></tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- TAB PIC -->
                                <div class="tab-pane fade" id="prjPanePics">
                                    <div class="table-responsive">
                                        <table class="table table-bordered prj-tab-table mb-0">
                                            <thead style="background:var(--surface-2);">
                                                <tr>
                                                    <th scope="col" style="width:1%;">No</th>
                                                    <th scope="col" class="prj-col-wide">Nama PIC</th>
                                                    <th scope="col">Jabatan</th>
                                                    <th scope="col">Whatsapp</th>
                                                    <th scope="col">Email</th>
                                                </tr>
                                            </thead>
                                            <tbody id="prjPicBody"></tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- TAB BUDGET -->
                                <div class="tab-pane fade" id="prjPaneBudgets">
                                    <div id="prjBudgetBody"></div>
                                </div>

                                <!-- TAB NO. PO -->
                                <div class="tab-pane fade" id="prjPanePoNumbers">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-muted">Submit BAST (termasuk multi No. PO sekaligus) sekarang dilakukan di halaman terpisah.</small>
                                        <a href="#" id="btnOpenBastPage" target="_blank" class="btn btn-primary btn-sm prj-can-submit-bast" style="display:none;">
                                            <i class="fas fa-file-signature mr-1"></i> Submit BAST
                                        </a>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered prj-tab-table mb-0">
                                            <thead style="background:var(--surface-2);">
                                                <tr>
                                                    <th scope="col" class="prj-col-wide">No. PO</th>
                                                    <th scope="col" class="text-right">Nominal</th>
                                                    <th scope="col">Tanggal Rilis</th>
                                                    <th scope="col">Status</th>
                                                    <th scope="col">No BAST</th>
                                                    <th scope="col" class="text-right">Nilai BAST</th>
                                                    <th scope="col">Lampiran</th>
                                                    <th scope="col" class="text-center">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody id="prjPoNumberBody"></tbody>
                                            <tfoot>
                                                <tr style="background:var(--surface-2);">
                                                    <th>Grand Total</th>
                                                    <th class="text-right" id="prjPoNumberGrandTotal" style="color:var(--info);"></th>
                                                    <th colspan="6"></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>

                                <!-- TAB COST -->
                                <div class="tab-pane fade" id="prjPaneCost">
                                    <div id="prjCostSummary" class="mb-3"></div>
                                    <div class="text-muted mb-2" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">
                                        Variable Cost
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered prj-tab-table mb-0">
                                            <thead style="background:var(--surface-2);">
                                                <tr>
                                                    <th scope="col">Code</th>
                                                    <th scope="col">Date</th>
                                                    <th scope="col" class="prj-col-wide">Description</th>
                                                    <th scope="col">Beneficiary</th>
                                                    <th scope="col" class="text-right">Request</th>
                                                    <th scope="col" class="text-right">Refund</th>
                                                    <th scope="col">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody id="prjCostBody"></tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- TAB INVOICE -->
                                <div class="tab-pane fade" id="prjPaneInvoice">
                                    <div class="table-responsive">
                                        <table class="table table-bordered prj-tab-table mb-0">
                                            <thead style="background:var(--surface-2);">
                                                <tr>
                                                    <th scope="col">No. Invoice</th>
                                                    <th scope="col" class="prj-col-wide">No. PO</th>
                                                    <th scope="col">Payment Term</th>
                                                    <th scope="col">Tgl Invoice</th>
                                                    <th scope="col">Due Date</th>
                                                    <th scope="col" class="text-right prj-money-col">Nilai</th>
                                                    <th scope="col" class="prj-money-col">Tgl Diterima</th>
                                                    <th scope="col">Status</th>
                                                    <th scope="col" class="text-center">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody id="prjInvoiceBody"></tbody>
                                            <tfoot>
                                                <tr style="background:var(--surface-2);">
                                                    <th colspan="5" class="text-right">Total</th>
                                                    <th class="text-right prj-money-col" id="prjInvoiceFootValue" style="color:var(--info);"></th>
                                                    <th colspan="3"></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>

                                <!-- TAB ACCESS -->
                                <div class="tab-pane fade" id="prjPaneUsers">
                                    <div id="prjUserBody"></div>
                                </div>

                                <!-- TAB ATTACHMENT -->
                                <div class="tab-pane fade" id="prjPaneAttachments">
                                    <div id="prjAttBody"></div>
                                </div>

                                <!-- TAB LOG -->
                                <div class="tab-pane fade" id="prjPaneLogs">
                                    <div id="prjLogBody" class="prj-chat"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL FINANCE APPROVE -->
<div class="modal fade" id="mdlFinanceApprove" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-check mr-2" style="color:var(--accent);"></i> Approval Finance
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmFinanceApprove" method="POST" target="_blank">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <p id="financeApproveText" class="text-muted mb-3"></p>
                    <div class="form-group">
                        <label class="font-weight-600">Pilih Status <span class="text-danger">*</span></label>
                        <select name="approval_type" class="form-control" id="approvalTypeSelect" required>
                            <option value="">— Pilih —</option>
                            <option value="WAITING_PO">Waiting PO (PO dari customer belum ada)</option>
                            <option value="ACTIVE">Active (PO sudah diterima)</option>
                        </select>
                    </div>
                    <div class="alert alert-warning py-2 px-3" id="poNumberGroup" style="display:none;font-size:12px;">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Pastikan minimal 1 No. PO sudah ditambahkan lewat Edit sebelum memilih Active — kalau belum ada, pilih Waiting PO dulu.
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Catatan (opsional)</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check mr-1"></i> Konfirmasi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL FINANCE REVISE -->
<div class="modal fade" id="mdlFinanceRevise" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-undo mr-2" style="color:var(--accent);"></i> Kembalikan untuk Revisi
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmFinanceRevise" method="POST" target="_blank">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Catatan Revisi <span class="text-danger">*</span></label>
                        <textarea name="notes" class="form-control" rows="3"
                                  placeholder="Tuliskan catatan revisi..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-undo mr-1"></i> Kembalikan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="frmCompletePoBast" method="POST" style="display:none;">
    @csrf
</form>

<form id="frmDeletePoNumber" method="POST" style="display:none;">
    @csrf
    @method('DELETE')
</form>

<!-- MODAL SUBMIT CLOSING -->
<div class="modal fade" id="mdlSubmitClosing" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-file-contract mr-2" style="color:var(--accent);"></i> Submit MoM Closing
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmSubmitClosing" method="POST" enctype="multipart/form-data" target="_blank">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <p id="closingProjectName" class="font-weight-600 mb-3 text-muted"></p>
                    <div class="form-group">
                        <label class="font-weight-600">Catatan Closing (MoM)</label>
                        <textarea name="closing_notes" class="form-control" rows="3"
                                  placeholder="Ringkasan Minutes of Meeting..."></textarea>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">
                            Lampiran MoM <span class="text-danger">*</span>
                        </label>
                        <div id="closingAttList2">
                            <div class="d-flex align-items-center mb-2" style="gap:8px;">
                                <input type="text" name="closing_attachments[0][title]"
                                       class="form-control closing-att-title" style="max-width:220px;text-transform:uppercase;"
                                       placeholder="Judul lampiran..." required>
                                <input type="file" name="closing_attachments[0][file]"
                                       class="form-control-file flex-grow-1"
                                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                                <button type="button" class="btn btn-sm btn-danger btn-remove-closing-att2">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary mt-1" id="btnAddClosingAtt2">
                            <i class="fas fa-plus mr-1"></i> Tambah File
                        </button>
                        <small class="text-muted d-block mt-1">Format: pdf, jpg, png, doc(x). Maks 10 MB. Judul wajib diisi.</small>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-paper-plane mr-1"></i> Ajukan ke Finance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL VERIFY CLOSING -->
<div class="modal fade" id="mdlVerifyClosing" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-check mr-2" style="color:var(--accent);"></i> Verifikasi MoM Closing
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmVerifyClosing" method="POST" target="_blank">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <p id="verifyClosingText" class="text-muted mb-3"></p>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Catatan (opsional)</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check mr-1"></i> Approve → Closed
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL REJECT CLOSING -->
<div class="modal fade" id="mdlRejectClosing" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-times mr-2" style="color:var(--accent);"></i> Tolak MoM Closing
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmRejectClosing" method="POST" target="_blank">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <div class="alert alert-warning mb-3" style="font-size:12px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        PO akan dikembalikan ke status <strong>PAID</strong> untuk direvisi head.
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Alasan Penolakan <span class="text-danger">*</span></label>
                        <textarea name="notes" class="form-control" rows="3"
                                  placeholder="Tuliskan alasan penolakan MoM..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times mr-1"></i> Tolak MoM
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL CLOSE PROJECT -->
<div class="modal fade" id="mdlCloseProject" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-lock mr-2" style="color:var(--accent);"></i> Tutup PO (Closing)
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmCloseProject" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <p id="closeProjectText" class="text-muted mb-3"></p>
                    <div class="form-group">
                        <label class="font-weight-600">Closing Notes (MoM)</label>
                        <textarea name="closing_notes" class="form-control" rows="3"
                                  placeholder="Catatan closing / Minutes of Meeting..."></textarea>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">
                            Lampiran MoM <span class="text-danger">*</span>
                        </label>
                        <div id="closingAttList">
                            <div class="d-flex align-items-center mb-2" style="gap:8px;">
                                <input type="text" name="closing_attachments[0][title]"
                                       class="form-control closing-att-title" style="max-width:220px;text-transform:uppercase;"
                                       placeholder="Judul lampiran..." required>
                                <input type="file" name="closing_attachments[0][file]"
                                       class="form-control-file flex-grow-1"
                                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                                <button type="button" class="btn btn-xs btn-danger btn-remove-closing-att">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary mt-1" id="btnAddClosingAtt">
                            <i class="fas fa-plus mr-1"></i> Tambah File
                        </button>
                        <small class="text-muted d-block mt-1">Format: pdf, jpg, png, doc(x). Maks 10 MB. Judul wajib diisi.</small>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-dark">
                        <i class="fas fa-lock mr-1"></i> Tutup PO
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="prjCopyToast"><i class="fas fa-check mr-1"></i> Disalin ke clipboard</div>

<!-- IMAGE VIEWER (carousel + zoom) -->
<div id="imgViewerOverlay">
    <button aria-label="Close" type="button" id="imgViewerClose" title="Close"><i class="fas fa-times"></i></button>
    <div id="imgViewerName"></div>
    <div id="imgViewerStage">
        <button aria-label="Previous" type="button" class="imgViewerNav" id="imgViewerPrev" title="Previous"><i class="fas fa-chevron-left"></i></button>
        <img id="imgViewerImg" src="" alt="">
        <button aria-label="Next" type="button" class="imgViewerNav" id="imgViewerNext" title="Next"><i class="fas fa-chevron-right"></i></button>
    </div>
    <div id="imgViewerCounter"></div>
</div>

@endsection

@include('project._index-assets')
