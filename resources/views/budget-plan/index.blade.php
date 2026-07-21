@extends('layouts.app')

@section('title', 'Budgeting')

@section('content')

<div class="content-header">
    <div class="container-fluid">

        <!-- SUMMARY CARDS
        <div class="row mb-3">
            <div class="col-6 col-md-3">
                <div class="small-box bg-info">
                    <div class="inner"><h3>{{ $summary['total'] }}</h3><p>Total Budget</p></div>
                    <div class="icon"><i class="fas fa-money-check-alt"></i></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small-box bg-warning">
                    <div class="inner"><h3>{{ $summary['pending'] }}</h3><p>Pending Approval</p></div>
                    <div class="icon"><i class="fas fa-hourglass-half"></i></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small-box bg-success">
                    <div class="inner"><h3>{{ $summary['active'] }}</h3><p>Active</p></div>
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small-box bg-dark">
                    <div class="inner"><h3>{{ $summary['closed'] }}</h3><p>Closed</p></div>
                    <div class="icon"><i class="fas fa-lock"></i></div>
                </div>
            </div>
        </div> -->

        <div class="row mb-2">
            <div class="col-sm-6"><h1 class="m-0 font-weight-bold">Budgeting</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="#">Finance</a></li>
                    <li class="breadcrumb-item active">Budgeting</li>
                </ol>
            </div>
        </div>

    </div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="card card-primary card-outline shadow-sm">

            <div class="card-header d-flex align-items-center">
                <button id="btnRefreshBP" class="btn btn-secondary btn-sm">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
                @if(Auth::user()->hasRole(['superadmin','manager']))
                <a href="{{ url('/budget-plan/create') }}" class="btn btn-success btn-sm ml-auto">
                    <i class="fas fa-plus mr-1"></i> Tambah Budget Plan
                </a>
                @endif
            </div>

            <div class="card-body">

                <!-- FILTER -->
                <div class="custom-toolbar mb-3">
                    <div class="custom-toolbar-left">
                        <div class="toolbar-row">
                            <span class="toolbar-label">Cari :</span>
                            <div class="toolbar-input" id="bpSearch"></div>
                        </div>
                        <div class="toolbar-row">
                            <span class="toolbar-label">Filter :</span>
                            <div class="toolbar-input">
                                @php
                                    $filterPOs          = $budgetPlans->pluck('project.name')->filter()->unique()->sort()->values();
                                    $filterCustomers    = $budgetPlans->pluck('project.customer.name')->filter()->unique()->sort()->values();
                                    $filterCostCenters  = $budgetPlans->pluck('costCenter.name')->filter()->unique()->sort()->values();
                                @endphp
                                <select id="bpFilter" multiple placeholder="Filter data...">
                                    <optgroup label="Sumber Dana">
                                        <option value="SOURCE:PROJECT">PO (Project)</option>
                                        <option value="SOURCE:OVERHEAD">Overhead</option>
                                        <option value="SOURCE:PAYROLL">Payroll</option>
                                    </optgroup>
                                    <optgroup label="Status">
                                        <option value="STATUS:DRAFT">Draft</option>
                                        <option value="STATUS:SUBMITTED">Submitted</option>
                                        <option value="STATUS:ACTIVE">Active</option>
                                        <option value="STATUS:CLOSING">Closing</option>
                                        <option value="STATUS:CLOSED">Closed</option>
                                        <option value="STATUS:REJECTED">Rejected</option>
                                    </optgroup>
                                    @if($filterPOs->count())
                                    <optgroup label="PO">
                                        @foreach($filterPOs as $poName)
                                            <option value="PO:{{ $poName }}">{{ $poName }}</option>
                                        @endforeach
                                    </optgroup>
                                    @endif
                                    @if($filterCostCenters->count())
                                    <optgroup label="Cost Center">
                                        @foreach($filterCostCenters as $ccName)
                                            <option value="COSTCENTER:{{ $ccName }}">{{ $ccName }}</option>
                                        @endforeach
                                    </optgroup>
                                    @endif
                                    @if($filterCustomers->count())
                                    <optgroup label="Customer">
                                        @foreach($filterCustomers as $cName)
                                            <option value="CUSTOMER:{{ $cName }}">{{ $cName }}</option>
                                        @endforeach
                                    </optgroup>
                                    @endif
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TABLE -->
                <div class="table-responsive">
                    <table id="tblBudgetPlan" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="all text-nowrap" style="width:1%;">No</th>
                                <th class="all text-nowrap" style="width:1%;">Kode</th>
                                <th class="all">Deskripsi</th>
                                <th class="text-right text-nowrap" style="width:1%;">Amount</th>
                                <th class="text-right text-nowrap" style="width:1%;">Actual</th>
                                <th class="text-right text-nowrap" style="width:1%;">Var</th>
                                <th class="text-center text-nowrap" style="width:1%;">Used</th>
                                <th class="text-nowrap" style="width:1%;">Status</th>
                                <th class="text-center all action-column" style="width:1%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($budgetPlans as $bp)
                            @php
                                $sc = [
                                    'DRAFT'     => 'secondary',
                                    'SUBMITTED' => 'warning',
                                    'ACTIVE'    => 'success',
                                    'CLOSING'   => 'info',
                                    'CLOSED'    => 'dark',
                                    'REJECTED'  => 'danger',
                                ][$bp->status] ?? 'secondary';

                                $bpActual = $bp->usedAmount();
                                $bpVar    = ($bp->amount ?? 0) - $bpActual;
                                $bpUsed   = ($bp->amount ?? 0) > 0 ? round(($bpActual / $bp->amount) * 100, 1) : 0;
                            @endphp
                            <tr data-status="{{ $bp->status }}"
                                data-source="{{ $bp->source_type }}"
                                data-po="{{ $bp->project->name ?? '' }}"
                                data-customer="{{ $bp->project->customer->name ?? '' }}"
                                data-costcenter="{{ $bp->costCenter->name ?? '' }}">
                                <td class="text-nowrap">{{ $loop->iteration }}</td>

                                <td class="text-nowrap">
                                    <span class="badge badge-dark px-2 py-1" style="font-size:11px;letter-spacing:0.03em;">
                                        {{ $bp->code ?? '-' }}
                                    </span>
                                </td>

                                 <td>
                                    <span>
                                        {{ $bp->description }}
                                    </span>
                                    <div style="font-size:11px;color:#6B7280;margin-top:3px;">
                                        @if($bp->isProjectBudget())
                                            <span class="badge badge-light border" style="font-size:9px;">PO</span>
                                            {{ Str::limit($bp->project->name ?? '-', 70) }}
                                        @elseif($bp->isCostCenterBudget())
                                            <span class="badge badge-{{ $bp->source_type === 'PAYROLL' ? 'warning' : 'info' }}" style="font-size:9px;">
                                                {{ $bp->source_type }}
                                            </span>
                                            {{ Str::limit($bp->costCenter->name ?? '-', 70) }}
                                        @else
                                            <span class="text-danger">Tanpa sumber dana</span>
                                        @endif
                                    </div>
                                </td>

                                <td class="text-right text-nowrap col-raw-amount" data-raw="{{ $bp->amount ?? 0 }}">
                                    <strong>{{ $bp->amount_formatted }}</strong>
                                </td>

                                <td class="text-right text-nowrap col-raw-actual" data-raw="{{ $bpActual }}">
                                    Rp {{ number_format($bpActual, 0, ',', '.') }}
                                </td>

                                <td class="text-right text-nowrap col-raw-var {{ $bpVar >= 0 ? 'text-var-pos' : 'text-var-neg' }}" data-raw="{{ $bpVar }}">
                                    {{ $bpVar < 0 ? '-' : '' }}Rp {{ number_format(abs($bpVar), 0, ',', '.') }}
                                </td>

                                <td class="text-center text-nowrap">
                                    <span class="badge badge-{{ $bpUsed >= 100 ? 'danger' : ($bpUsed >= 80 ? 'warning' : 'light border') }} px-2 py-1" style="font-size:11px;">
                                        {{ rtrim(rtrim(number_format($bpUsed, 1, ',', '.'), '0'), ',') }}%
                                    </span>
                                </td>

                                <td class="text-nowrap">
                                    <span class="badge badge-{{ $sc }} px-2 py-1" style="font-size:10px;">
                                        {{ $bp->status }}
                                    </span>
                                </td>

                                <td class="text-center action-column">
                                    <div class="action-buttons">

                                        {{-- DETAIL --}}
                                        <button type="button"
                                                class="btn btn-info btn-sm btn-detail-bp"
                                                data-id="{{ $bp->id }}" title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </button>

                                        {{-- SUBMIT (DRAFT → SUBMITTED) — manager --}}
                                        @if($bp->status === 'DRAFT' && Auth::user()->hasRole(['superadmin','manager']))
                                        <form action="{{ url('/budget-plan/' . $bp->id . '/submit') }}"
                                              method="POST" class="frmSubmitBP d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm" title="Submit ke Finance">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </form>
                                        @endif

                                        {{-- EDIT (DRAFT only) — manager --}}
                                        @if($bp->status === 'DRAFT' && Auth::user()->hasRole(['superadmin','manager']))
                                        <a href="{{ url('/budget-plan/' . $bp->id . '/edit') }}"
                                           class="btn btn-warning btn-sm" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        @endif

                                        {{-- FINANCE: SUBMITTED → ACTIVE --}}
                                        @if($bp->status === 'SUBMITTED' && Auth::user()->hasRole(['superadmin','finance']))
                                        <button type="button" class="btn btn-success btn-sm btn-approve-bp"
                                                data-id="{{ $bp->id }}" title="Verify → Active">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-sm btn-revise-bp"
                                                data-id="{{ $bp->id }}" title="Kembalikan ke Draft">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm btn-reject-bp"
                                                data-id="{{ $bp->id }}" title="Reject">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        @endif

                                        {{-- SUBMIT CLOSING (ACTIVE → CLOSING) — manager --}}
                                        @if($bp->status === 'ACTIVE' && Auth::user()->hasRole(['superadmin','manager']))
                                        <button type="button" class="btn btn-dark btn-sm btn-submit-closing-bp"
                                                data-id="{{ $bp->id }}"
                                                data-desc="{{ $bp->description }}"
                                                title="Submit Closing">
                                            <i class="fas fa-flag-checkered"></i>
                                        </button>
                                        @endif

                                        {{-- FINANCE: CLOSING → CLOSED / ACTIVE --}}
                                        @if($bp->status === 'CLOSING' && Auth::user()->hasRole(['superadmin','finance']))
                                        <button type="button" class="btn btn-success btn-sm btn-verify-closing-bp"
                                                data-id="{{ $bp->id }}" title="Approve Closing → Closed">
                                            <i class="fas fa-check-double"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm btn-reject-closing-bp"
                                                data-id="{{ $bp->id }}" title="Tolak Closing → Active">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        @endif

                                        {{-- DELETE (DRAFT) — manager --}}
                                        @if($bp->status === 'DRAFT' && Auth::user()->hasRole(['superadmin','manager']))
                                        <form action="{{ url('/budget-plan/' . $bp->id) }}"
                                              method="POST" class="frmDeleteBP d-inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm" title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        @endif

                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fas fa-folder-open d-block mb-2"></i>
                                    Belum ada data Budget Plan
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr style="background:#F9FAFB;">
                                <th colspan="3" class="text-right" style="font-size:12px;">Total (data tampil)</th>
                                <th class="text-right text-nowrap" id="bpFooterAmount" style="font-size:12px;color:#2563EB;"></th>
                                <th class="text-right text-nowrap" id="bpFooterActual" style="font-size:12px;color:#16a34a;"></th>
                                <th class="text-right text-nowrap" id="bpFooterVar" style="font-size:12px;"></th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================ --}}
{{-- MODAL DETAIL BUDGET PLAN                                      --}}
{{-- ============================================================ --}}
<div class="modal fade" id="mdlDetailBP" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;padding:16px 24px;">
                <div class="d-flex align-items-center">
                    <div style="width:36px;height:36px;background:#F5A623;border-radius:8px;display:flex;align-items:center;justify-content:center;margin-right:12px;flex-shrink:0;">
                        <i class="fas fa-money-check-alt" style="color:#0D1B2A;font-size:15px;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0" id="mdlBPTitle">Detail Budget Plan</h5>
                        <small id="mdlBPSubtitle" style="color:#8A9BB0;font-size:11px;"></small>
                    </div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:0.7;"><span>&times;</span></button>
            </div>

            <div class="modal-body" style="padding:0;background:#F0F2F5;">
                <div id="bpDetailLoading" class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x" style="color:#F5A623;"></i>
                    <p class="text-muted mt-3 mb-0">Memuat data...</p>
                </div>
                <div id="bpDetailContent" style="display:none;">
                    <div class="row no-gutters">

                        <!-- LEFT SIDEBAR -->
                        <div class="col-md-3" style="background:#0D1B2A;padding:28px 20px;min-height:500px;">
                            <div class="text-center mb-3">
                                <div style="width:64px;height:64px;background:#1D2D3F;border:3px solid #F5A623;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;">
                                    <i class="fas fa-money-check-alt" style="color:#F5A623;font-size:24px;"></i>
                                </div>
                                <div id="bpCode" class="mt-2" style="font-size:10px;color:#5A7080;letter-spacing:0.08em;word-break:break-all;"></div>
                                <div id="bpStatusBadge" class="mt-2"></div>
                            </div>
                            <hr style="border-color:rgba(255,255,255,0.08);">
                            <div class="mb-3">
                                <span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Sumber Dana</span>
                                <div id="bpProject" class="text-white mt-1" style="font-size:12px;font-weight:600;"></div>
                            </div>
                            <div class="mb-3" id="bpCustomerBox">
                                <span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Customer</span>
                                <div id="bpCustomer" class="text-white mt-1" style="font-size:12px;"></div>
                            </div>
                            <div class="mb-3">
                                <span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Total Budget</span>
                                <div id="bpAmount" style="color:#F5A623;font-size:18px;font-weight:700;margin-top:4px;"></div>
                            </div>
                            <div class="mb-3">
                                <span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Terpakai</span>
                                <div id="bpUsed" class="text-white mt-1" style="font-size:12px;"></div>
                                <div style="height:5px;background:rgba(255,255,255,0.1);border-radius:99px;margin-top:6px;overflow:hidden;">
                                    <div id="bpProgressBar" style="height:100%;background:#F5A623;border-radius:99px;transition:width .4s;width:0%;"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Sisa</span>
                                <div id="bpRemaining" class="text-white mt-1" style="font-size:12px;"></div>
                            </div>
                            <div class="mb-3">
                                <span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Input By</span>
                                <div id="bpInputBy" class="text-white mt-1" style="font-size:12px;"></div>
                            </div>
                            <hr style="border-color:rgba(255,255,255,0.08);margin:16px 0;">
                            <a id="bpEditBtn" href="#" class="btn btn-block"
                               style="background:#F5A623;color:#0D1B2A;font-weight:700;font-size:13px;border-radius:6px;display:none;">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </a>
                        </div>

                        <!-- RIGHT TABS -->
                        <div class="col-md-9" style="padding:24px;">
                            <ul class="nav nav-tabs mb-3" id="bpDetailTabs" style="border-bottom:2px solid #E5E7EB;">
                                <li class="nav-item">
                                    <a class="nav-link active" data-toggle="tab" href="#bpPaneInfo" style="font-size:13px;font-weight:600;">
                                        <i class="fas fa-info-circle mr-1"></i> Informasi
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#bpPaneDetails" style="font-size:13px;font-weight:600;">
                                        <i class="fas fa-list-ul mr-1"></i> Detail
                                        <span id="bpDetailCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#bpPaneAttachments" style="font-size:13px;font-weight:600;">
                                        <i class="fas fa-paperclip mr-1"></i> Lampiran
                                        <span id="bpAttCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#bpPaneLogs" style="font-size:13px;font-weight:600;">
                                        <i class="fas fa-history mr-1"></i> Log
                                    </a>
                                </li>
                            </ul>

                            <div class="tab-content">

                                <!-- TAB INFO -->
                                <div class="tab-pane fade show active" id="bpPaneInfo">
                                    <table class="table table-bordered mb-0" style="font-size:13px;">
                                        <tr><th width="30%" style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Kode</th><td id="infoCode"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Deskripsi</th><td id="infoDesc"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Sumber Dana</th><td id="infoPO"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Total Budget</th><td id="infoAmount"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Status</th><td id="infoStatus"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Notes</th><td id="infoNotes" style="white-space:pre-line;"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Catatan Approver</th><td id="infoApproverNotes" style="white-space:pre-line;"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Approved By</th><td id="infoApprovedBy"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Approved At</th><td id="infoApprovedAt"></td></tr>
                                    </table>
                                </div>

                                <!-- TAB DETAIL — Account | Harga Satuan | Qty | Unit | Total | Terpakai | Sisa | Notes -->
                                <div class="tab-pane fade" id="bpPaneDetails">
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0" style="font-size:12px;">
                                            <thead style="background:#F9FAFB;">
                                                <tr>
                                                    <th style="color:#6B7280;font-size:11px;width:3%;">No</th>
                                                    <th style="color:#6B7280;font-size:11px;">Account</th>
                                                    <th style="color:#6B7280;font-size:11px;">Deskripsi</th>
                                                    <th style="color:#6B7280;font-size:11px;text-align:right;width:12%;">Harga Satuan</th>
                                                    <th style="color:#6B7280;font-size:11px;text-align:center;width:6%;">Qty</th>
                                                    <th style="color:#6B7280;font-size:11px;width:8%;">Unit</th>
                                                    <th style="color:#6B7280;font-size:11px;text-align:right;width:13%;">Total</th>
                                                    <th style="color:#6B7280;font-size:11px;text-align:right;width:13%;">Terpakai</th>
                                                    <th style="color:#6B7280;font-size:11px;text-align:right;width:13%;">Sisa</th>
                                                    <th style="color:#6B7280;font-size:11px;width:15%;">Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody id="bpDetailBody"></tbody>
                                            <tfoot>
                                                <tr style="background:#F9FAFB;">
                                                    <th colspan="6" class="text-right" style="font-size:12px;">Total</th>
                                                    <th class="text-right" id="bpDetailTotal" style="font-size:12px;color:#2563EB;"></th>
                                                    <th class="text-right" id="bpActualTotal" style="font-size:12px;color:#16a34a;"></th>
                                                    <th class="text-right" id="bpGapTotal" style="font-size:12px;"></th>
                                                    <th></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>

                                <!-- TAB LAMPIRAN -->
                                <div class="tab-pane fade" id="bpPaneAttachments">
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0" style="font-size:13px;">
                                            <thead style="background:#F9FAFB;">
                                                <tr>
                                                    <th style="color:#6B7280;font-size:11px;width:5%;">No</th>
                                                    <th style="color:#6B7280;font-size:11px;">Nama File</th>
                                                    <th style="color:#6B7280;font-size:11px;width:10%;">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody id="bpAttBody"></tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- TAB LOG -->
                                <div class="tab-pane fade" id="bpPaneLogs">
                                    <div id="bpLogBody"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="background:#F8F9FA;border-top:1px solid #E9ECEF;padding:12px 24px;">
                <small class="text-muted mr-auto"><i class="fas fa-info-circle mr-1"></i> Read-only. Klik Edit untuk mengubah.</small>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Tutup
                </button>
                <a id="bpEditBtnFooter" href="#" class="btn btn-warning btn-sm" style="display:none;">
                    <i class="fas fa-edit mr-1"></i> Edit
                </a>
            </div>
        </div>
    </div>
</div>

{{-- MODAL APPROVE --}}
<div class="modal fade" id="mdlApproveBP" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white" id="mdlApproveTitle">
                    <i class="fas fa-check mr-2" style="color:#F5A623;"></i> Konfirmasi
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmApproveBP" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <p id="mdlApproveText" class="text-muted mb-3"></p>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Catatan (opsional)</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Catatan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8f9fa;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check mr-1"></i> Ya, Setujui</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL REJECT/REVISE --}}
<div class="modal fade" id="mdlRejectBP" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white"><i class="fas fa-times mr-2" style="color:#F5A623;"></i> Tolak / Kembalikan</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmRejectBP" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Alasan / Catatan <span class="text-danger">*</span></label>
                        <textarea name="approver_notes" class="form-control" rows="4"
                                  placeholder="Tuliskan alasan atau catatan revisi..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8f9fa;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times mr-1"></i> Tolak</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL SUBMIT CLOSING --}}
<div class="modal fade" id="mdlSubmitClosingBP" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white">
                    <i class="fas fa-flag-checkered mr-2" style="color:#F5A623;"></i> Submit Closing Budget Plan
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmSubmitClosingBP" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <p id="closingBPText" class="text-muted mb-3"></p>
                    <div class="form-group">
                        <label class="font-weight-600">Catatan Closing</label>
                        <textarea name="closing_notes" class="form-control" rows="3"
                                  placeholder="Ringkasan atau catatan penutupan budget..."></textarea>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Lampiran <span class="text-danger">*</span></label>
                        <div id="closingBPAttList">
                            <div class="d-flex align-items-center mb-2" style="gap:8px;">
                                <input type="file" name="closing_attachments[]"
                                       class="form-control-file flex-grow-1"
                                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" required>
                                <button type="button" class="btn btn-sm btn-danger btn-remove-closing-bp-att">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary mt-1" id="btnAddClosingBPAtt">
                            <i class="fas fa-plus mr-1"></i> Tambah File
                        </button>
                        <small class="text-muted d-block mt-1">Format: pdf, doc, xls, jpg. Maks 10 MB.</small>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8f9fa;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-dark">
                        <i class="fas fa-flag-checkered mr-1"></i> Submit Closing
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL VERIFY CLOSING --}}
<div class="modal fade" id="mdlVerifyClosingBP" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white">
                    <i class="fas fa-check-double mr-2" style="color:#F5A623;"></i> Verifikasi Closing
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmVerifyClosingBP" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <p class="text-muted mb-3">Verifikasi closing budget plan ini? Status akan berubah menjadi <strong>CLOSED</strong>.</p>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Catatan (opsional)</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8f9fa;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check-double mr-1"></i> Approve → Closed
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL REJECT CLOSING --}}
<div class="modal fade" id="mdlRejectClosingBP" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white">
                    <i class="fas fa-times mr-2" style="color:#F5A623;"></i> Tolak Closing
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmRejectClosingBP" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <div class="alert alert-warning mb-3" style="font-size:12px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        Budget Plan akan dikembalikan ke status <strong>ACTIVE</strong>.
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Alasan <span class="text-danger">*</span></label>
                        <textarea name="notes" class="form-control" rows="3"
                                  placeholder="Tuliskan alasan penolakan closing..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8f9fa;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times mr-1"></i> Tolak Closing
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
.text-var-pos { color:#16a34a; }
.text-var-neg { color:#DC2626; }
.custom-toolbar      { display:flex; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; flex-direction:row; align-items:center; gap:12px; flex-wrap:wrap; width:100%; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; font-weight:600; color:#495057; white-space:nowrap; flex-shrink:0; margin-bottom:0; }
.toolbar-input       { min-width:180px; }
#bpSearch input      { width:180px; border-radius:6px; }
.ts-wrapper          { min-width:260px; }
.ts-control .item {
    max-width: 240px;
    display: flex;
    align-items: center;
    overflow: visible;
}
.ts-control .item > div {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 200px;
}
.ts-control .item .remove { flex-shrink: 0; }
.ts-control          { border-radius:6px !important; border:1px solid #ced4da !important; min-height:38px !important; padding:6px 10px !important; box-shadow:none !important; }
#tblBudgetPlan       { width:100% !important; }
.table td,.table th  { vertical-align:middle !important; }
.action-column       { white-space:nowrap !important; min-width:130px !important; }
.action-buttons      { display:flex; align-items:center; justify-content:center; gap:4px; flex-wrap:nowrap; }
.font-weight-600     { font-weight:600; }
.dataTables_wrapper .row:last-child { margin-top:18px; align-items:center; }
.dataTables_info { padding-top:0 !important; font-size:14px; color:#6c757d; }
.dataTables_length { display:flex; align-items:center; margin-left:18px; }
.dataTables_length label { display:flex; align-items:center; gap:10px; margin-bottom:0; font-size:14px; font-weight:600; color:#6c757d; }
.dataTables_length select { appearance:none !important; background-color:#fff !important; color:#495057 !important; border:1px solid #ced4da !important; border-radius:6px !important; padding:4px 30px 4px 10px !important; height:36px !important; min-width:72px !important; font-size:14px !important; font-weight:600 !important; cursor:pointer; box-shadow:none !important; }
.dataTables_paginate .paginate_button { border-radius:6px !important; margin:0 2px; }
.dataTables_paginate .paginate_button.current { background:#F5A623 !important; background-image:none !important; border-color:#C97E0A !important; color:#0D1B2A !important; font-weight:700 !important; }
#bpDetailTabs .nav-link { color:#6B7280; border:none; border-bottom:2px solid transparent; border-radius:0; padding:8px 16px; }
#bpDetailTabs .nav-link.active { color:#F5A623 !important; border-bottom-color:#F5A623 !important; background:transparent !important; font-weight:700; }
@media (max-width:576px) {
    .custom-toolbar-left { flex-direction:column; align-items:stretch; }
    .toolbar-row         { width:100%; }
    .toolbar-input       { flex:1; min-width:0; }
    #bpSearch input      { width:100%; }
    .ts-wrapper          { width:100% !important; min-width:0 !important; }
}

/* Kolom action fit ke isinya */
.action-column { white-space:nowrap !important; width:1% !important; }

/* --- Filter dropdown: bedakan header grup vs item --- */
.ts-dropdown .optgroup-header,
.selectize-dropdown .optgroup-header {
    font-weight: 700 !important;
    font-size: 11px !important;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #0D1B2A !important;
    background: #F1F3F5 !important;
    padding: 8px 12px !important;
    border-top: 1px solid #e3e6ea;
    cursor: default;
}
.ts-dropdown .optgroup:first-child .optgroup-header,
.selectize-dropdown .optgroup:first-child .optgroup-header {
    border-top: none;
}
.ts-dropdown .optgroup .option,
.selectize-dropdown .optgroup .option {
    padding-left: 26px !important;
}
</style>
@endpush

@push('scripts')
<script>
$(function () {

    /* DATATABLE */
    var table = $('#tblBudgetPlan').DataTable({
        responsive: { details: { type: 'inline' } },
        autoWidth: false, pageLength: 10, lengthChange: true, searching: true,
        columnDefs: [
            { responsivePriority: 1,   targets: [0,1,2,-1] },
            { responsivePriority: 2,   targets: [3,4,5,6,7] },
        ],
        dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex justify-content-end"lp>>',
        language: { lengthMenu:'Page Size _MENU_', zeroRecords:'Data tidak ditemukan', info:'Menampilkan _START_ sampai _END_ dari _TOTAL_ data', infoEmpty:'Tidak ada data', paginate:{previous:'Previous',next:'Next'} }
    });

    $('#tblBudgetPlan_filter').hide();
    $('#bpSearch').html('<input type="search" id="bpSearchInput" class="form-control form-control-sm" placeholder="Cari data...">');
    $('#bpSearchInput').on('keyup', function () { table.search(this.value).draw(); });

    /*
    | FOOTER KALKULASI — total Amount, Actual, Var dari SEMUA baris yang
    | lolos filter/search saat ini (bukan cuma baris di halaman aktif).
    | Dihitung ulang tiap kali tabel di-draw (search, filter, atau paging).
    */
    function moneyFmt(v) {
        var sign = v < 0 ? '-' : '';
        return sign + 'Rp ' + Math.abs(Math.round(v)).toLocaleString('id-ID');
    }

    function recalcFooter() {
        var totalAmount = 0, totalActual = 0, totalVar = 0;

        table.rows({ search: 'applied' }).every(function () {
            var node = this.node();
            totalAmount += parseFloat(node.querySelector('.col-raw-amount')?.dataset.raw) || 0;
            totalActual += parseFloat(node.querySelector('.col-raw-actual')?.dataset.raw) || 0;
            totalVar    += parseFloat(node.querySelector('.col-raw-var')?.dataset.raw)    || 0;
        });

        $('#bpFooterAmount').text(moneyFmt(totalAmount));
        $('#bpFooterActual').text(moneyFmt(totalActual));
        $('#bpFooterVar').html(
            '<span style="color:' + (totalVar >= 0 ? '#16a34a' : '#DC2626') + ';">' + moneyFmt(totalVar) + '</span>'
        );
    }

    table.on('draw', recalcFooter);
    recalcFooter();

    new TomSelect('#bpFilter', {
        plugins: ['remove_button'],
        persist: false,
        create: false,
        lockOptgroupOrder: true,
        placeholder: 'Filter data...',
        render: {
            item: function (data, escape) {
                var label = data.text || '';
                var truncated = label.length > 28 ? label.substring(0, 28) + '…' : label;
                return '<div title="' + escape(label) + '">' + escape(truncated) + '</div>';
            }
        }
    });

    /*
    | Filter membaca nilai MENTAH dari atribut data-* pada <tr>, bukan teks kolom.
    | Aturan: OR di dalam satu grup, AND antar grup.
    */
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        if (settings.nTable.id !== 'tblBudgetPlan') return true;

        var filters = $('#bpFilter').val();
        if (!filters || !filters.length) return true;

        var row = settings.aoData[dataIndex].nTr;
        if (!row) return true;

        var rowStatus     = row.getAttribute('data-status')     || '';
        var rowSource     = row.getAttribute('data-source')     || '';
        var rowPO         = row.getAttribute('data-po')         || '';
        var rowCustomer   = row.getAttribute('data-customer')   || '';
        var rowCostCenter = row.getAttribute('data-costcenter') || '';

        var selStatus = [], selSource = [], selPO = [], selCustomer = [], selCostCenter = [];

        filters.forEach(function(f) {
            var i = f.indexOf(':');
            if (i < 0) return;
            var key = f.substring(0, i);
            var val = f.substring(i + 1);
            if (key === 'STATUS')          selStatus.push(val);
            else if (key === 'SOURCE')     selSource.push(val);
            else if (key === 'PO')         selPO.push(val);
            else if (key === 'CUSTOMER')   selCustomer.push(val);
            else if (key === 'COSTCENTER') selCostCenter.push(val);
        });

        if (selStatus.length     && selStatus.indexOf(rowStatus)         === -1) return false;
        if (selSource.length     && selSource.indexOf(rowSource)         === -1) return false;
        if (selPO.length         && selPO.indexOf(rowPO)                 === -1) return false;
        if (selCustomer.length   && selCustomer.indexOf(rowCustomer)     === -1) return false;
        if (selCostCenter.length && selCostCenter.indexOf(rowCostCenter) === -1) return false;

        return true;
    });

    $('#bpFilter').on('change', function () { table.draw(); });
    $('#btnRefreshBP').on('click', function () { location.reload(); });

    /* SUBMIT CONFIRM */
    $(document).on('submit', '.frmSubmitBP', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Submit Budget Plan?', text:'Budget Plan akan dikirim ke Finance untuk diverifikasi.', icon:'question', showCancelButton:true, confirmButtonColor:'#007bff', cancelButtonColor:'#6c757d', confirmButtonText:'Ya, Submit', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) form.submit(); });
    });

    /* DELETE CONFIRM */
    $(document).on('submit', '.frmDeleteBP', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Hapus Budget Plan?', text:'Data tidak dapat dikembalikan.', icon:'warning', showCancelButton:true, confirmButtonColor:'#d33', cancelButtonColor:'#6c757d', confirmButtonText:'Ya, Hapus', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) form.submit(); });
    });

    /* ── MODAL DETAIL ── */
    var statusColors = { 'DRAFT':'secondary','SUBMITTED':'warning','ACTIVE':'success','CLOSING':'info','CLOSED':'dark','REJECTED':'danger' };

    $(document).on('click', '.btn-detail-bp', function () {
        var id = $(this).data('id');
        $('#bpDetailLoading').show(); $('#bpDetailContent').hide();
        $('#mdlDetailBP').modal('show');

        $.get('/budget-plan/' + id + '/detail-json', function (d) {
            $('#mdlBPTitle').text(d.description);
            $('#mdlBPSubtitle').text(d.code);
            $('#bpCode').text(d.code);
            $('#bpStatusBadge').html('<span class="badge badge-' + (statusColors[d.status]||'secondary') + ' px-3 py-1">' + d.status + '</span>');

            // Sumber dana: PO (project) ATAU cost center (overhead/payroll)
            var isProjectBudget = d.source_type === 'PROJECT';

            if (isProjectBudget) {
                $('#bpProject').text('[' + (d.project_code || '-') + '] ' + (d.project || '-'));
                $('#bpCustomer').text(d.customer || '-');
                $('#bpCustomerBox').show();
                $('#infoPO').html('<span class="badge badge-secondary mr-1">' + (d.project_code || '-') + '</span>' + (d.project || '-'));
            } else {
                var srcBadge = d.source_type === 'PAYROLL' ? 'warning' : 'info';
                $('#bpProject').text(d.source_name || '-');
                $('#bpCustomerBox').hide();   // cost center tidak punya customer
                $('#infoPO').html('<span class="badge badge-' + srcBadge + ' mr-1">' + d.source_type + '</span>' + (d.source_name || '-'));
            }

            $('#bpAmount').text(d.amount_formatted);
            $('#bpUsed').text('Rp ' + d.used_amount.toLocaleString('id-ID'));
            $('#bpProgressBar').css('width', Math.min(d.usage_percentage, 100) + '%');
            $('#bpRemaining').text('Rp ' + d.remaining_amount.toLocaleString('id-ID'));
            $('#bpInputBy').text(d.input_by);

            if (d.status === 'DRAFT') {
                $('#bpEditBtn, #bpEditBtnFooter').attr('href', '/budget-plan/' + id + '/edit').show();
            } else {
                $('#bpEditBtn, #bpEditBtnFooter').hide();
            }

            /* Tab Info */
            $('#infoCode').html('<span class="badge badge-dark px-2 py-1">' + d.code + '</span>');
            $('#infoDesc').text(d.description);
            $('#infoAmount').html('<strong style="color:#2563EB;">' + d.amount_formatted + '</strong>');
            $('#infoStatus').html('<span class="badge badge-' + (statusColors[d.status]||'secondary') + ' px-2 py-1">' + d.status + '</span>');
            $('#infoNotes').text(d.notes || '-');
            $('#infoApproverNotes').text(d.approver_notes || '-');
            $('#infoApprovedBy').text(d.approved_by || '-');
            $('#infoApprovedAt').text(d.approved_at || '-');

            /* Tab Detail — Account | Deskripsi | Harga Satuan | Qty | Unit | Total | Terpakai | Sisa | Notes */
            $('#bpDetailCount').text(d.details.length);
            var detHtml = '', totalBudget = 0, totalUsed = 0, totalRemaining = 0;

            // Urutkan per Account (stable sort — baris dengan account sama jadi berdekatan,
            // urutan relatif antar baris dengan account sama tetap sesuai input asli).
            var sortedDetails = d.details.slice().sort(function (a, b) {
                var accA = (a.account || '').toString();
                var accB = (b.account || '').toString();
                if (accA < accB) return -1;
                if (accA > accB) return 1;
                return 0;
            });

            if (sortedDetails.length) {
                sortedDetails.forEach(function (det, i) {
                    totalBudget    += det.total;
                    totalUsed      += det.used;
                    totalRemaining += det.remaining;
                    var remColor = det.remaining >= 0 ? '#16a34a' : '#DC2626';

                    // Merge kolom Account bila beruntun sama dengan baris sebelumnya
                    var accountCell = '';
                    if (i === 0 || sortedDetails[i - 1].account !== det.account) {
                        var span = 1;
                        for (var j = i + 1; j < sortedDetails.length; j++) {
                            if (sortedDetails[j].account === det.account) span++; else break;
                        }
                        accountCell = '<td rowspan="' + span + '" style="vertical-align:middle;">' + det.account + '</td>';
                    }

                    detHtml += '<tr>' +
                        '<td class="text-center">' + (i+1) + '</td>' +
                        accountCell +
                        '<td>' + (det.deskripsi || '-') + '</td>' +
                        '<td class="text-right">' + det.amount_formatted + '</td>' +
                        '<td class="text-center">' + det.qty + '</td>' +
                        '<td>' + (det.unit || '-') + '</td>' +
                        '<td class="text-right"><strong>' + det.total_formatted + '</strong></td>' +
                        '<td class="text-right">' + det.used_formatted + '</td>' +
                        '<td class="text-right" style="color:' + remColor + ';">' + det.remaining_formatted + '</td>' +
                        '<td><small class="text-muted">' + (det.notes||'-') + '</small></td>' +
                    '</tr>';
                });
            } else {
                detHtml = '<tr><td colspan="10" class="text-center text-muted py-3">Belum ada detail.</td></tr>';
            }
            $('#bpDetailBody').html(detHtml);
            $('#bpDetailTotal').text('Rp ' + totalBudget.toLocaleString('id-ID'));
            $('#bpActualTotal').text('Rp ' + totalUsed.toLocaleString('id-ID'));
            var remTotalColor = totalRemaining >= 0 ? '#16a34a' : '#DC2626';
            $('#bpGapTotal').html('<span style="color:' + remTotalColor + ';">Rp ' + totalRemaining.toLocaleString('id-ID') + '</span>');

            /* Tab Lampiran */
            $('#bpAttCount').text(d.attachments.length);
            var attHtml = '';
            if (d.attachments.length) {
                d.attachments.forEach(function(a, i) {
                    attHtml += '<tr><td class="text-center">' + (i+1) + '</td><td><small class="text-muted">' + a.original_name + '</small></td><td class="text-center"><a href="/storage/' + a.path + '" target="_blank" class="btn btn-info btn-sm"><i class="fas fa-eye"></i></a></td></tr>';
                });
            } else {
                attHtml = '<tr><td colspan="3" class="text-center text-muted py-3">Belum ada lampiran.</td></tr>';
            }
            $('#bpAttBody').html(attHtml);

            /* Tab Log */
            var logHtml = '';
            if (d.logs.length) {
                d.logs.forEach(function(log) {
                    logHtml += '<div class="d-flex align-items-start mb-3" style="gap:12px;">' +
                        '<span class="badge badge-' + log.action_color + ' px-2 py-1 mt-1" style="font-size:10px;white-space:nowrap;">' + log.action_label + '</span>' +
                        '<div><div style="font-size:12px;font-weight:600;">' + log.user + ' <span style="color:#9CA3AF;font-weight:400;">(' + log.role + ')</span></div>' +
                        '<div style="font-size:11px;color:#6B7280;">' + log.date + '</div>' +
                        (log.notes ? '<div style="font-size:12px;margin-top:4px;padding:6px 10px;background:#F9FAFB;border-radius:6px;border:1px solid #E5E7EB;">' + log.notes + '</div>' : '') +
                        '</div></div>';
                });
            } else {
                logHtml = '<p class="text-muted py-3 mb-0">Belum ada log.</p>';
            }
            $('#bpLogBody').html(logHtml);

            $('#bpDetailTabs a:first').tab('show');
            $('#bpDetailLoading').hide(); $('#bpDetailContent').show();
        }).fail(function () {
            $('#bpDetailLoading').html('<p class="text-danger text-center py-4"><i class="fas fa-exclamation-triangle mr-1"></i>Gagal memuat data.</p>');
        });
    });

    $('#mdlDetailBP').on('hidden.bs.modal', function () {
        $('#bpDetailContent').hide(); $('#bpDetailLoading').show();
    });

    /* ── APPROVE ── */
    $(document).on('click', '.btn-approve-bp', function () {
        var id = $(this).data('id');
        $('#mdlApproveTitle').html('<i class="fas fa-check mr-2" style="color:#F5A623;"></i> Verifikasi Budget Plan');
        $('#mdlApproveText').text('Verifikasi budget plan ini? Status akan berubah menjadi ACTIVE.');
        $('#frmApproveBP').attr('action', '/budget-plan/' + id + '/finance-verify');
        $('#frmApproveBP textarea[name="notes"]').val('');
        $('#mdlApproveBP').modal('show');
    });

    /* ── REVISE ── */
    $(document).on('click', '.btn-revise-bp', function () {
        var id = $(this).data('id');
        $('#frmRejectBP').attr('action', '/budget-plan/' + id + '/finance-revise');
        $('#frmRejectBP textarea[name="approver_notes"]').attr('placeholder', 'Tuliskan catatan revisi...');
        $('#mdlRejectBP .modal-title').html('<i class="fas fa-undo mr-2" style="color:#F5A623;"></i> Kembalikan untuk Revisi');
        $('#mdlRejectBP button[type="submit"]').text('Kembalikan').removeClass('btn-danger').addClass('btn-warning');
        $('#mdlRejectBP').modal('show');
    });

    /* ── REJECT ── */
    $(document).on('click', '.btn-reject-bp', function () {
        var id = $(this).data('id');
        $('#frmRejectBP').attr('action', '/budget-plan/' + id + '/finance-reject');
        $('#frmRejectBP textarea[name="approver_notes"]').attr('placeholder', 'Tuliskan alasan penolakan...');
        $('#mdlRejectBP .modal-title').html('<i class="fas fa-times mr-2" style="color:#F5A623;"></i> Tolak Budget Plan');
        $('#mdlRejectBP button[type="submit"]').text('Tolak').removeClass('btn-warning').addClass('btn-danger');
        $('#mdlRejectBP').modal('show');
    });

    /* ── SUBMIT CLOSING ── */
    $(document).on('click', '.btn-submit-closing-bp', function () {
        var id   = $(this).data('id');
        var desc = $(this).data('desc');
        $('#closingBPText').text('Submit closing untuk: ' + desc);
        $('#frmSubmitClosingBP').attr('action', '/budget-plan/' + id + '/submit-closing');
        $('#frmSubmitClosingBP')[0].reset();
        $('#closingBPAttList').html(
            '<div class="d-flex align-items-center mb-2" style="gap:8px;">' +
            '<input type="file" name="closing_attachments[]" class="form-control-file flex-grow-1" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" required>' +
            '<button type="button" class="btn btn-sm btn-danger btn-remove-closing-bp-att"><i class="fas fa-trash"></i></button>' +
            '</div>'
        );
        $('#mdlSubmitClosingBP').modal('show');
    });

    $('#btnAddClosingBPAtt').on('click', function () {
        $('#closingBPAttList').append(
            '<div class="d-flex align-items-center mb-2" style="gap:8px;">' +
            '<input type="file" name="closing_attachments[]" class="form-control-file flex-grow-1" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">' +
            '<button type="button" class="btn btn-sm btn-danger btn-remove-closing-bp-att"><i class="fas fa-trash"></i></button>' +
            '</div>'
        );
    });

    $(document).on('click', '.btn-remove-closing-bp-att', function () {
        if ($('#closingBPAttList .d-flex').length > 1) $(this).closest('.d-flex').remove();
    });

    /* ── VERIFY CLOSING ── */
    $(document).on('click', '.btn-verify-closing-bp', function () {
        var id = $(this).data('id');
        $('#frmVerifyClosingBP').attr('action', '/budget-plan/' + id + '/verify-closing');
        $('#frmVerifyClosingBP textarea[name="notes"]').val('');
        $('#mdlVerifyClosingBP').modal('show');
    });

    /* ── REJECT CLOSING ── */
    $(document).on('click', '.btn-reject-closing-bp', function () {
        var id = $(this).data('id');
        $('#frmRejectClosingBP').attr('action', '/budget-plan/' + id + '/reject-closing');
        $('#frmRejectClosingBP textarea[name="notes"]').val('');
        $('#mdlRejectClosingBP').modal('show');
    });

});
</script>
@endpush