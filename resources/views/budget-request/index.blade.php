@extends('layouts.app')

@section('title', 'Cost Request')

@section('content')

<div class="content-header">
    <div class="container-fluid">

        <!-- SUMMARY CARDS
        <div class="row mb-3">
            <div class="col-6 col-md" style="padding:0 6px;">
                <div class="small-box bg-secondary mb-2">
                    <div class="inner"><h3>{{ $summary['draft'] }}</h3><p>Draft</p></div>
                    <div class="icon"><i class="fas fa-file"></i></div>
                </div>
            </div>
            <div class="col-6 col-md" style="padding:0 6px;">
                <div class="small-box bg-warning mb-2">
                    <div class="inner"><h3>{{ $summary['submitted'] }}</h3><p>Submitted</p></div>
                    <div class="icon"><i class="fas fa-paper-plane"></i></div>
                </div>
            </div>
            <div class="col-6 col-md" style="padding:0 6px;">
                <div class="small-box bg-info mb-2">
                    <div class="inner"><h3>{{ $summary['approved'] }}</h3><p>Approved</p></div>
                    <div class="icon"><i class="fas fa-check"></i></div>
                </div>
            </div>
            <div class="col-6 col-md" style="padding:0 6px;">
                <div class="small-box bg-primary mb-2">
                    <div class="inner"><h3>{{ $summary['verified'] }}</h3><p>Verified</p></div>
                    <div class="icon"><i class="fas fa-check-double"></i></div>
                </div>
            </div>
            <div class="col-6 col-md" style="padding:0 6px;">
                <div class="small-box bg-success mb-2">
                    <div class="inner"><h3>{{ $summary['paid'] }}</h3><p>Paid</p></div>
                    <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
                </div>
            </div>
            <div class="col-6 col-md" style="padding:0 6px;">
                <div class="small-box mb-2" style="background:#7c3aed;">
                    <div class="inner" style="color:white;"><h3>{{ $summary['realized'] }}</h3><p>Realized</p></div>
                    <div class="icon"><i class="fas fa-receipt"></i></div>
                </div>
            </div>
            <div class="col-6 col-md" style="padding:0 6px;">
                <div class="small-box bg-dark mb-2">
                    <div class="inner"><h3>{{ $summary['closed'] }}</h3><p>Closed</p></div>
                    <div class="icon"><i class="fas fa-lock"></i></div>
                </div>
            </div>
        </div> -->

        <div class="row mb-2">
            <div class="col-sm-6"><h1 class="m-0 font-weight-bold">Cost Request</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="#">Finance</a></li>
                    <li class="breadcrumb-item active">Cost Request</li>
                </ol>
            </div>
        </div>

    </div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="card card-primary card-outline shadow-sm">

            <div class="card-header d-flex align-items-center">
                <button id="btnRefreshCR" class="btn btn-secondary btn-sm">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
                <a href="{{ url('/budget-request/export') }}" class="btn btn-info btn-sm ml-auto">
                    <i class="fas fa-file-excel mr-1"></i> Export Excel
                </a>
                @if(Auth::user()->hasRole(['superadmin','manager']))
                <a href="{{ url('/budget-request/create') }}" class="btn btn-success btn-sm ml-2">
                    <i class="fas fa-plus mr-1"></i> Tambah Request
                </a>
                @endif
            </div>

            <div class="card-body">

                <!-- FILTER -->
                <div class="custom-toolbar mb-3">
                    <div class="custom-toolbar-left">
                        <div class="toolbar-row">
                            <span class="toolbar-label">Cari :</span>
                            <div class="toolbar-input" id="crSearchBox"></div>
                        </div>
                        <div class="toolbar-row">
                            <span class="toolbar-label">Filter :</span>
                            <div class="toolbar-input">
                                @php
                                    $filterTypes      = $budgetRequests->pluck('type.name')->filter()->unique()->sort()->values();
                                    $filterApplicants = $budgetRequests->pluck('applicant.username')->filter()->unique()->sort()->values();
                                    $filterPOs        = $budgetRequests->pluck('budgetPlan.project.name')->filter()->unique()->sort()->values();
                                    $filterClusters   = $budgetRequests->pluck('cluster.name')->filter()->unique()->sort()->values();
                                @endphp
                                <select id="crFilter" multiple placeholder="Filter data...">
                                    <optgroup label="Status">
                                        <option value="STATUS:DRAFT">Draft</option>
                                        <option value="STATUS:SUBMITTED">Submitted</option>
                                        <option value="STATUS:APPROVED">Approved</option>
                                        <option value="STATUS:VERIFIED">Verified</option>
                                        <option value="STATUS:QUEUED">Queued</option>
                                        <option value="STATUS:PAID">Paid</option>
                                        <option value="STATUS:REALIZED">Realized</option>
                                        <option value="STATUS:CLOSED">Closed</option>
                                        <option value="STATUS:REJECTED">Rejected</option>
                                    </optgroup>
                                    <optgroup label="Tipe Request">
                                        @foreach($filterTypes as $tName)
                                            <option value="TYPE:{{ $tName }}">{{ $tName }}</option>
                                        @endforeach
                                    </optgroup>
                                    <optgroup label="Pemohon">
                                        @foreach($filterApplicants as $aName)
                                            <option value="APPLICANT:{{ strtoupper($aName) }}">{{ strtoupper($aName) }}</option>
                                        @endforeach
                                    </optgroup>
                                    <optgroup label="PO">
                                        @foreach($filterPOs as $poName)
                                            <option value="PO:{{ $poName }}">{{ $poName }}</option>
                                        @endforeach
                                    </optgroup>
                                    <optgroup label="Project">
                                        @foreach($filterClusters as $clName)
                                            <option value="CLUSTER:{{ $clName }}">{{ $clName }}</option>
                                        @endforeach
                                    </optgroup>
                                    <optgroup label="Lainnya">
                                        <option value="NOB:1">NOB (Not On Budget)</option>
                                    </optgroup>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TABLE -->
                <div class="table-responsive">
                    <table id="tblCR" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th width="4%"  class="all">No</th>
                                <th class="all text-nowrap" style="width:1%;">Kode</th>
                                <th class="all">Deskripsi</th>
                                <th class="text-right text-nowrap" style="width:1%;">Amount</th>
                                <th class="text-right text-nowrap" style="width:1%;">Realisasi</th>
                                <th class="text-right text-nowrap" style="width:1%;">Var</th>
                                <th class="text-nowrap" style="width:1%;">Status</th>
                                <th class="text-center all action-column" style="width:1%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($budgetRequests as $cr)
                            @php
                                $sc = [
                                    'DRAFT'     => 'secondary',
                                    'SUBMITTED' => 'warning',
                                    'APPROVED'  => 'info',
                                    'VERIFIED'  => 'primary',
                                    'QUEUED'    => 'warning',
                                    'PAID'      => 'success',
                                    'REALIZED'  => 'primary',
                                    'CLOSED'    => 'dark',
                                    'REJECTED'  => 'danger',
                                ][$cr->status] ?? 'secondary';
                                $isNob = str_starts_with($cr->description, '[NOB]');
                                $realisasi = $cr->total_realization;
                                $var       = ($cr->amount ?? 0) - $realisasi;
                            @endphp
                            <tr data-status="{{ $cr->status }}"
                                data-type="{{ $cr->type->name ?? '' }}"
                                data-applicant="{{ strtoupper($cr->applicant->username ?? '') }}"
                                data-po="{{ $cr->budgetPlan->project->name ?? '' }}"
                                data-cluster="{{ $cr->cluster->name ?? '' }}"
                                data-nob="{{ $isNob ? '1' : '0' }}"
                                class="{{ $isNob ? 'row-nob' : '' }}">
                                <td class="text-nowrap">{{ $loop->iteration }}</td>

                                <td class="text-nowrap">
                                    <span class="badge badge-dark px-2 py-1" style="font-size:10px;">
                                        {{ $cr->code }}
                                    </span>
                                    <div style="font-size:10px;color:#6B7280;margin-top:3px;">
                                        {{ strtoupper($cr->applicant->username ?? '-') }}
                                        · {{ $cr->type->name ?? '-' }}
                                        · {{ $cr->created_at->format('d M y') }}
                                    </div>
                                </td>

                                <td>
                                    <div style="font-size:13px;font-weight:600;">
                                        @if($isNob)
                                            <span class="badge badge-danger px-1 py-1 mr-1" style="font-size:9px;">NOB</span>
                                        @endif
                                        {{ Str::limit($isNob ? ltrim(preg_replace('/^\[NOB\]\s*/i','',$cr->description)) : $cr->description, 100) }}
                                    </div>
                                    <div style="font-size:10px;color:#6B7280;margin-top:2px;">
                                        <i class="fas fa-wallet" style="font-size:9px;"></i>
                                        {{ $cr->budgetPlan->description ?? '-' }}
                                        @if($cr->cluster)
                                            <span class="mx-1">•</span>{{ $cr->cluster->name }}
                                        @elseif($cr->budgetPlan->project->name ?? null)
                                            <span class="mx-1">•</span>{{ $cr->budgetPlan->project->name }}
                                        @endif
                                    </div>
                                </td>

                                <td class="text-right text-nowrap col-raw-amount" data-raw="{{ $cr->amount }}">
                                    <strong>{{ $cr->amount_formatted }}</strong>
                                </td>

                                <td class="text-right text-nowrap col-raw-realisasi" data-raw="{{ $realisasi }}">
                                    Rp {{ number_format($realisasi, 0, ',', '.') }}
                                </td>

                                <td class="text-right text-nowrap col-raw-var {{ $var >= 0 ? 'text-var-pos' : 'text-var-neg' }}" data-raw="{{ $var }}">
                                    {{ $var < 0 ? '-' : '' }}Rp {{ number_format(abs($var), 0, ',', '.') }}
                                </td>

                                <td class="text-nowrap">
                                    <span class="badge badge-{{ $sc }} px-2 py-1" style="font-size:10px;">
                                        {{ $cr->status }}
                                    </span>
                                </td>

                                <td class="text-center action-column">
                                    <div class="action-buttons">

                                        {{-- DETAIL --}}
                                        <button type="button" class="btn btn-info btn-sm btn-detail-cr"
                                                data-id="{{ $cr->id }}" title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </button>

                                        {{-- SUBMIT (DRAFT → SUBMITTED) — manager --}}
                                        @if($cr->status === 'DRAFT' && Auth::user()->hasRole(['superadmin','manager']))
                                        <form action="{{ url('/budget-request/'.$cr->id.'/submit') }}"
                                              method="POST" class="frmSubmitCR d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm" title="Submit ke Head">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </form>
                                        <a href="{{ url('/budget-request/'.$cr->id.'/edit') }}"
                                           class="btn btn-warning btn-sm" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <form action="{{ url('/budget-request/'.$cr->id) }}"
                                              method="POST" class="frmDeleteCR d-inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm" title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        @endif

                                        {{-- HEAD ACTIONS (SUBMITTED) --}}
                                        @if($cr->status === 'SUBMITTED' && Auth::user()->hasRole(['superadmin','head']))
                                        <button type="button" class="btn btn-success btn-sm btn-head-approve"
                                                data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Approve">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-sm btn-head-revise"
                                                data-id="{{ $cr->id }}" title="Revisi">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm btn-head-reject"
                                                data-id="{{ $cr->id }}" title="Reject">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        @endif

                                        {{-- FINANCE ACTIONS (APPROVED) --}}
                                        @if($cr->status === 'APPROVED' && Auth::user()->hasRole(['superadmin','finance']))
                                        <button type="button" class="btn btn-success btn-sm btn-finance-verify"
                                                data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Verify">
                                            <i class="fas fa-check-double"></i>
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-sm btn-finance-revise"
                                                data-id="{{ $cr->id }}" title="Revisi">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm btn-finance-reject"
                                                data-id="{{ $cr->id }}" title="Reject">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        @endif

                                        {{-- SUBMIT REALISASI (PAID) — manager --}}
                                        @if($cr->status === 'PAID' && Auth::user()->hasRole(['superadmin','manager']))
                                        <button type="button" class="btn btn-primary btn-sm btn-submit-realization"
                                                data-id="{{ $cr->id }}" data-code="{{ $cr->code }}"
                                                data-amount="{{ $cr->amount }}" title="Submit Realisasi">
                                            <i class="fas fa-receipt"></i>
                                        </button>
                                        @endif

                                        {{-- FINANCE VERIFY/REJECT REALIZATION (REALIZED) --}}
                                        @if($cr->status === 'REALIZED' && Auth::user()->hasRole(['superadmin','finance']))
                                        <button type="button" class="btn btn-success btn-sm btn-verify-realization"
                                                data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Approve → Closed">
                                            <i class="fas fa-check-double"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm btn-reject-realization"
                                                data-id="{{ $cr->id }}" data-code="{{ $cr->code }}" title="Tolak → Paid">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        @endif

                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr class="no-data">
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fas fa-folder-open d-block mb-2"></i>
                                    Belum ada data Cost Request
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr style="background:#F9FAFB;">
                                <th colspan="3" class="text-right" style="font-size:12px;">Total (data tampil)</th>
                                <th class="text-right text-nowrap" id="crFooterAmount" style="font-size:12px;color:#2563EB;"></th>
                                <th class="text-right text-nowrap" id="crFooterRealisasi" style="font-size:12px;color:#16a34a;"></th>
                                <th class="text-right text-nowrap" id="crFooterVar" style="font-size:12px;"></th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

{{-- ================================================================ --}}
{{-- MODAL DETAIL CR                                                    --}}
{{-- ================================================================ --}}
<div class="modal fade" id="mdlDetailCR" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;padding:16px 24px;">
                <div class="d-flex align-items-center">
                    <div style="width:36px;height:36px;background:#F5A623;border-radius:8px;display:flex;align-items:center;justify-content:center;margin-right:12px;flex-shrink:0;">
                        <i class="fas fa-file-invoice-dollar" style="color:#0D1B2A;font-size:15px;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0" id="crModalTitle">Detail CR</h5>
                        <small id="crModalSubtitle" style="color:#8A9BB0;font-size:11px;"></small>
                    </div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" style="padding:0;background:#F0F2F5;">
                <div id="crDetailLoading" class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x" style="color:#F5A623;"></i>
                </div>
                <div id="crDetailContent" style="display:none;">
                    <div class="row no-gutters">
                        <div class="col-md-3" style="background:#0D1B2A;padding:28px 20px;min-height:420px;">
                            <div class="text-center mb-3">
                                <div style="width:60px;height:60px;background:#1D2D3F;border:3px solid #F5A623;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;">
                                    <i class="fas fa-file-invoice-dollar" style="color:#F5A623;font-size:22px;"></i>
                                </div>
                                <div id="crCode" class="mt-2" style="font-size:10px;color:#5A7080;word-break:break-all;"></div>
                                <div id="crStatusBadge" class="mt-1"></div>
                                <div id="crNobBadge" class="mt-1"></div>
                            </div>
                            <hr style="border-color:rgba(255,255,255,0.08);">
                            <div class="mb-2"><span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Tipe</span><div id="crType" class="text-white mt-1" style="font-size:12px;"></div></div>
                            <div class="mb-2"><span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Total Pengajuan</span><div id="crAmount" style="color:#F5A623;font-size:18px;font-weight:700;margin-top:4px;"></div></div>
                            <div class="mb-2"><span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Total Realisasi</span><div id="crRealizationAmount" style="color:#28a745;font-size:14px;font-weight:700;margin-top:4px;"></div></div>
                            <div class="mb-2"><span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Beneficiary</span><div id="crBeneficiary" class="text-white mt-1" style="font-size:12px;"></div><div id="crBank" style="color:#8A9BB0;font-size:11px;"></div></div>
                            <div class="mb-2"><span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Pemohon</span><div id="crApplicant" class="text-white mt-1" style="font-size:12px;"></div></div>
                        </div>
                        <div class="col-md-9" style="padding:20px;">
                            <ul class="nav nav-tabs mb-3" id="crDetailTabs" style="border-bottom:2px solid #E5E7EB;flex-wrap:wrap;">
                                <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#crPaneInfo" style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-info-circle mr-1"></i> Info</a></li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#crPaneDetails" style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-list-ul mr-1"></i> Rincian <span id="crDetailCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span></a></li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#crPaneRealization" style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-receipt mr-1"></i> Realisasi <span id="crRealizationCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span></a></li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#crPaneAttachments" style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-paperclip mr-1"></i> Lampiran <span id="crAttCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span></a></li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#crPaneLogs" style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-history mr-1"></i> Log</a></li>
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="crPaneInfo">
                                    <table class="table table-bordered mb-0" style="font-size:12px;">
                                        <tr><th width="30%" style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Kode</th><td id="infoCode"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Deskripsi</th><td id="infoDesc"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Tipe</th><td id="infoType"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Scope of Work</th><td id="infoProject"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Project</th><td id="infoCluster"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Budget Plan</th><td id="infoBudgetPlan"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Total</th><td id="infoAmount"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Status</th><td id="infoStatus"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Email Notif</th><td id="infoEmail" style="word-break:break-all;"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Notes</th><td id="infoNotes" style="white-space:pre-line;"></td></tr>
                                    </table>
                                </div>
                                <div class="tab-pane fade" id="crPaneDetails">
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0" style="font-size:12px;">
                                            <thead style="background:#F9FAFB;">
                                                <tr>
                                                    <th style="color:#6B7280;font-size:11px;width:4%;">No</th>
                                                    <th style="color:#6B7280;font-size:11px;">Deskripsi</th>
                                                    <th style="color:#6B7280;font-size:11px;">Notes</th>
                                                    <th style="color:#6B7280;font-size:11px;text-align:right;width:13%;">Satuan</th>
                                                    <th style="color:#6B7280;font-size:11px;text-align:center;width:6%;">Qty</th>
                                                    <th style="color:#6B7280;font-size:11px;text-align:right;width:14%;">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody id="crDetailBody"></tbody>
                                            <tfoot><tr style="background:#F9FAFB;"><th colspan="5" class="text-right">Total</th><th class="text-right" id="crDetailTotal" style="color:#2563EB;"></th></tr></tfoot>
                                        </table>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="crPaneRealization">
                                    <div id="crNoRealization" class="text-center text-muted py-4" style="display:none;">
                                        <i class="fas fa-receipt d-block mb-2"></i> Belum ada data realisasi.
                                    </div>
                                    <div id="crRealizationWrapper">
                                        <div class="table-responsive">
                                            <table class="table table-bordered mb-0" style="font-size:12px;">
                                                <thead style="background:#F9FAFB;">
                                                    <tr>
                                                        <th style="color:#6B7280;font-size:11px;width:4%;">No</th>
                                                        <th style="color:#6B7280;font-size:11px;">CR Detail Item</th>
                                                        <th style="color:#6B7280;font-size:11px;text-align:right;width:16%;">Total CR</th>
                                                        <th style="color:#6B7280;font-size:11px;text-align:right;width:16%;">Actual Used</th>
                                                        <th style="color:#6B7280;font-size:11px;width:20%;">Notes</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="crRealizationBody"></tbody>
                                                <tfoot><tr style="background:#F9FAFB;"><th colspan="3" class="text-right">Total Realisasi</th><th class="text-right" id="crRealizationTotal" style="color:#16a34a;"></th><th></th></tr></tfoot>
                                            </table>
                                        </div>
                                        <div id="crRealizationAtts" class="mt-3"></div>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="crPaneAttachments">
                                    <div id="crAttBody"></div>
                                </div>
                                <div class="tab-pane fade" id="crPaneLogs">
                                    <div id="crLogBody"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="background:#F8F9FA;padding:12px 24px;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Tutup
                </button>
            </div>
        </div>
    </div>
</div>

{{-- MODAL HEAD APPROVE --}}
<div class="modal fade" id="mdlHeadApprove" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:12px;overflow:hidden;">
        <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
            <h5 class="modal-title text-white"><i class="fas fa-check mr-2" style="color:#F5A623;"></i> Head Approve</h5>
            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <form id="frmHeadApprove" method="POST">@csrf
            <div class="modal-body" style="padding:24px;">
                <p id="headApproveText" class="text-muted mb-3"></p>
                <div class="form-group mb-0">
                    <label class="font-weight-600">Catatan (opsional)</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Catatan..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="background:#f8f9fa;">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check mr-1"></i> Approve</button>
            </div>
        </form>
    </div></div>
</div>

{{-- MODAL NOTES (Head Revise/Reject, Finance Revise/Reject, Reject Realization) --}}
<div class="modal fade" id="mdlNotes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:12px;overflow:hidden;">
        <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
            <h5 class="modal-title text-white" id="mdlNotesTitle"></h5>
            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <form id="frmNotes" method="POST">@csrf
            <div class="modal-body" style="padding:24px;">
                <div id="mdlNotesAlert" class="alert alert-warning mb-3" style="font-size:12px;display:none;"></div>
                <div class="form-group mb-0">
                    <label class="font-weight-600" id="mdlNotesLabel">Catatan <span class="text-danger">*</span></label>
                    <textarea name="notes" class="form-control" rows="3" required placeholder="Tuliskan catatan..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="background:#f8f9fa;">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger" id="mdlNotesSubmitBtn">Konfirmasi</button>
            </div>
        </form>
    </div></div>
</div>

{{-- MODAL FINANCE VERIFY --}}
<div class="modal fade" id="mdlFinanceVerify" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:12px;overflow:hidden;">
        <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
            <h5 class="modal-title text-white"><i class="fas fa-check-double mr-2" style="color:#F5A623;"></i> Finance Verify</h5>
            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <form id="frmFinanceVerify" method="POST">@csrf
            <div class="modal-body" style="padding:24px;">
                <p id="financeVerifyText" class="text-muted mb-3"></p>
                <div class="form-group mb-0">
                    <label class="font-weight-600">Catatan (opsional)</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Catatan..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="background:#f8f9fa;">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check-double mr-1"></i> Verify</button>
            </div>
        </form>
    </div></div>
</div>

{{-- MODAL SUBMIT REALISASI --}}
<div class="modal fade" id="mdlSubmitRealization" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl"><div class="modal-content" style="border-radius:12px;overflow:hidden;">
        <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
            <h5 class="modal-title text-white"><i class="fas fa-receipt mr-2" style="color:#F5A623;"></i> Submit Realisasi</h5>
            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <form id="frmSubmitRealization" method="POST" enctype="multipart/form-data">@csrf
            <div class="modal-body" style="padding:24px;">
                <div class="d-flex align-items-center mb-3" style="gap:12px;">
                    <small id="realCRCode" class="badge badge-dark px-2 py-1" style="font-size:11px;"></small>
                    <small class="text-muted">Pengajuan: <strong id="realCRAmount"></strong></small>
                </div>

                <!-- Rincian Realisasi -->
                <div class="card mb-3 shadow-sm">
                    <div class="card-header d-flex align-items-center py-2">
                        <span class="font-weight-600"><i class="fas fa-list-ul mr-1"></i> Rincian Realisasi</span>
                        <div class="ml-auto d-flex" style="gap:8px;">
                            <span id="realTotalLabel" class="badge badge-success px-2 py-1" style="font-size:12px;">Total: Rp 0</span>
                            <button type="button" class="btn btn-sm btn-primary" id="btnAddRealizationRow">
                                <i class="fas fa-plus mr-1"></i> Tambah Baris
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0" style="font-size:12px;">
                                <thead class="bg-light">
                                    <tr>
                                        <th style="width:4%;" class="text-center">No</th>
                                        <th style="width:30%;">CR Detail Item</th>
                                        <th style="width:18%;" class="text-right">Total CR</th>
                                        <th style="width:18%;" class="text-right">Actual Used</th>
                                        <th style="width:25%;">Notes</th>
                                        <th style="width:5%;" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="realizationBody">
                                    <!-- Diisi oleh JS setelah data CR Detail Item berhasil di-fetch -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Lampiran Realisasi -->
                <div class="card shadow-sm">
                    <div class="card-header d-flex align-items-center py-2">
                        <span class="font-weight-600"><i class="fas fa-paperclip mr-1"></i> Lampiran Realisasi</span>
                        <button type="button" class="btn btn-sm btn-secondary ml-auto" id="btnAddRealAtt">
                            <i class="fas fa-plus mr-1"></i> Tambah File
                        </button>
                    </div>
                    <div class="card-body p-2" id="realAttList">
                        <div class="d-flex align-items-center mb-1" style="gap:8px;">
                            <input type="file" name="realization_attachments[]" class="form-control-file flex-grow-1" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                            <button type="button" class="btn btn-sm btn-danger btn-remove-real-att"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="card-footer py-1">
                        <small class="text-muted">Format: pdf, doc, xls, jpg. Maks 10 MB.</small>
                    </div>
                </div>

            </div>
            <div class="modal-footer" style="background:#f8f9fa;">
                <small id="realOverWarning" class="text-danger mr-auto" style="display:none;font-size:12px;">
                    <i class="fas fa-exclamation-triangle mr-1"></i> Total realisasi melebihi nominal pengajuan.
                </small>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary" id="btnSubmitRealization">
                    <i class="fas fa-receipt mr-1"></i> Submit Realisasi
                </button>
            </div>
        </form>
    </div></div>
</div>

{{-- MODAL VERIFY REALIZATION --}}
<div class="modal fade" id="mdlVerifyRealization" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:12px;overflow:hidden;">
        <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
            <h5 class="modal-title text-white"><i class="fas fa-check-double mr-2" style="color:#F5A623;"></i> Verifikasi Realisasi</h5>
            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <form id="frmVerifyRealization" method="POST">@csrf
            <div class="modal-body" style="padding:24px;">
                <p id="verifyRealText" class="text-muted mb-3"></p>
                <div class="form-group mb-0">
                    <label class="font-weight-600">Catatan (opsional)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Catatan..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="background:#f8f9fa;">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check-double mr-1"></i> Approve → Closed</button>
            </div>
        </form>
    </div></div>
</div>

{{-- accounts JSON untuk realisasi --}}
@endsection

@push('styles')
<style>
.row-nob td   { color:#DC2626 !important; }
.text-var-pos { color:#16a34a; }
.text-var-neg { color:#DC2626; }
.custom-toolbar      { display:flex; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; flex-direction:row; align-items:center; gap:12px; flex-wrap:wrap; width:100%; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; font-weight:600; color:#495057; white-space:nowrap; flex-shrink:0; }
.toolbar-input       { min-width:180px; }
#crSearchBox input   { width:180px; border-radius:6px; }
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
#tblCR               { width:100% !important; }
.table td,.table th  { vertical-align:middle !important; }
.action-column       { white-space:nowrap !important; width:1% !important; }
.action-buttons      { display:flex; align-items:center; justify-content:center; gap:3px; flex-wrap:nowrap; }
.font-weight-600     { font-weight:600; }
.dataTables_wrapper .row:last-child { margin-top:18px; align-items:center; }
.dataTables_info { padding-top:0 !important; font-size:14px; color:#6c757d; }
.dataTables_length { display:flex; align-items:center; margin-left:18px; }
.dataTables_length label { display:flex; align-items:center; gap:10px; margin-bottom:0; font-size:14px; font-weight:600; color:#6c757d; }
.dataTables_length select { appearance:none !important; background-color:#fff !important; color:#495057 !important; border:1px solid #ced4da !important; border-radius:6px !important; padding:4px 30px 4px 10px !important; height:36px !important; min-width:72px !important; font-size:14px !important; font-weight:600 !important; cursor:pointer; box-shadow:none !important; }
.dataTables_paginate .paginate_button { border-radius:6px !important; margin:0 2px; }
.dataTables_paginate .paginate_button.current { background:#F5A623 !important; background-image:none !important; border-color:#C97E0A !important; color:#0D1B2A !important; font-weight:700 !important; }
#crDetailTabs .nav-link { color:#6B7280; border:none; border-bottom:2px solid transparent; border-radius:0; padding:8px 12px; }
#crDetailTabs .nav-link.active { color:#F5A623 !important; border-bottom-color:#F5A623 !important; background:transparent !important; font-weight:700; }
@media (max-width:576px) {
    .custom-toolbar-left { flex-direction:column; align-items:stretch; }
    .toolbar-row { width:100%; }
    .toolbar-input { flex:1; min-width:0; }
    #crSearchBox input { width:100%; }
    .ts-wrapper { width:100% !important; min-width:0 !important; }
}

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

    // (accounts holder lama sudah tidak diperlukan — realisasi sekarang pakai CR Detail Item)

    /* ── DATATABLE ── */
    var table = $('#tblCR').DataTable({
        responsive:{ details:{ type:'inline' } },
        autoWidth:false, pageLength:10, lengthChange:true, searching:true,
        columnDefs:[
            { responsivePriority:1, targets:[0,1,2,-1] },
            { responsivePriority:2, targets:[3,4,5,6] },
        ],
        dom:'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex justify-content-end"lp>>',
        language:{ lengthMenu:'Page Size _MENU_', zeroRecords:'Data tidak ditemukan', info:'Menampilkan _START_ sampai _END_ dari _TOTAL_ data', infoEmpty:'Tidak ada data', paginate:{previous:'Previous',next:'Next'} }
    });

    $('#tblCR_filter').hide();
    $('#crSearchBox').html('<input type="search" id="crSearchInput" class="form-control form-control-sm" placeholder="Cari data...">');
    $('#crSearchInput').on('keyup', function () { table.search(this.value).draw(); });

    new TomSelect('#crFilter', {
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
    | FOOTER KALKULASI — total Amount, Realisasi & Var dari SEMUA baris yang lolos
    | filter/search saat ini (bukan cuma baris di halaman aktif). Dihitung ulang tiap draw.
    */
    function moneyFmtCR(v) {
        var sign = v < 0 ? '-' : '';
        return sign + 'Rp ' + Math.abs(Math.round(v)).toLocaleString('id-ID');
    }

    function recalcFooterCR() {
        var totalAmount = 0, totalRealisasi = 0, totalVar = 0;
        table.rows({ search: 'applied' }).every(function () {
            var node = this.node();
            totalAmount    += parseFloat(node.querySelector('.col-raw-amount')?.dataset.raw)    || 0;
            totalRealisasi += parseFloat(node.querySelector('.col-raw-realisasi')?.dataset.raw) || 0;
            totalVar       += parseFloat(node.querySelector('.col-raw-var')?.dataset.raw)       || 0;
        });
        $('#crFooterAmount').text(moneyFmtCR(totalAmount));
        $('#crFooterRealisasi').text(moneyFmtCR(totalRealisasi));
        $('#crFooterVar').html(
            '<span class="' + (totalVar >= 0 ? 'text-var-pos' : 'text-var-neg') + '">' + moneyFmtCR(totalVar) + '</span>'
        );
    }

    table.on('draw', recalcFooterCR);
    recalcFooterCR();

    /*
    | Filter membaca nilai MENTAH dari atribut data-* pada <tr>, bukan teks kolom
    | (label & badge di kolom membuat pencocokan teks rapuh).
    | Aturan: OR di dalam satu grup, AND antar grup.
    */
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        if (settings.nTable.id !== 'tblCR') return true;

        var filters = $('#crFilter').val();
        if (!filters || !filters.length) return true;

        var row = settings.aoData[dataIndex].nTr;
        if (!row) return true;

        var rowStatus    = row.getAttribute('data-status')    || '';
        var rowType      = row.getAttribute('data-type')      || '';
        var rowApplicant = row.getAttribute('data-applicant') || '';
        var rowPO        = row.getAttribute('data-po')        || '';
        var rowCluster   = row.getAttribute('data-cluster')   || '';
        var rowNob       = row.getAttribute('data-nob')       === '1';

        var selStatus = [], selType = [], selApplicant = [],
            selPO = [], selCluster = [], needNob = false;

        filters.forEach(function(f) {
            var i = f.indexOf(':');
            if (i < 0) return;
            var key = f.substring(0, i);
            var val = f.substring(i + 1);

            if (key === 'STATUS')         selStatus.push(val);
            else if (key === 'TYPE')      selType.push(val);
            else if (key === 'APPLICANT') selApplicant.push(val);
            else if (key === 'PO')        selPO.push(val);
            else if (key === 'CLUSTER')   selCluster.push(val);
            else if (key === 'NOB')       needNob = true;
        });

        if (selStatus.length    && selStatus.indexOf(rowStatus)       === -1) return false;
        if (selType.length      && selType.indexOf(rowType)           === -1) return false;
        if (selApplicant.length && selApplicant.indexOf(rowApplicant) === -1) return false;
        if (selPO.length        && selPO.indexOf(rowPO)               === -1) return false;
        if (selCluster.length   && selCluster.indexOf(rowCluster)     === -1) return false;
        if (needNob && !rowNob) return false;

        return true;
    });

    $('#crFilter').on('change', function () { table.draw(); });
    $('#btnRefreshCR').on('click', function () { location.reload(); });

    /* ── SUBMIT CONFIRM ── */
    $(document).on('submit', '.frmSubmitCR', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Submit CR?', text:'CR akan dikirim ke Head untuk direview.', icon:'question', showCancelButton:true, confirmButtonColor:'#007bff', cancelButtonColor:'#6c757d', confirmButtonText:'Ya, Submit', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) form.submit(); });
    });

    $(document).on('submit', '.frmDeleteCR', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Hapus CR?', icon:'warning', showCancelButton:true, confirmButtonColor:'#d33', cancelButtonColor:'#6c757d', confirmButtonText:'Ya, Hapus', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) form.submit(); });
    });

    /* ── HEAD APPROVE ── */
    $(document).on('click', '.btn-head-approve', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        $('#headApproveText').text('Approve CR ' + code + '? Status akan menjadi APPROVED dan diteruskan ke Finance.');
        $('#frmHeadApprove').attr('action', '/budget-request/' + id + '/head-approve');
        $('#frmHeadApprove textarea').val('');
        $('#mdlHeadApprove').modal('show');
    });

    /* ── GENERIC NOTES MODAL ── */
    function openNotesModal(id, action, title, label, btnClass, btnText, alertMsg) {
        $('#mdlNotesTitle').html('<i class="fas fa-exclamation-triangle mr-2" style="color:#F5A623;"></i>' + title);
        $('#mdlNotesLabel').html(label + ' <span class="text-danger">*</span>');
        if (alertMsg) { $('#mdlNotesAlert').html(alertMsg).show(); } else { $('#mdlNotesAlert').hide(); }
        $('#frmNotes').attr('action', '/budget-request/' + id + '/' + action);
        $('#frmNotes textarea').val('');
        $('#mdlNotesSubmitBtn').text(btnText).removeClass('btn-danger btn-warning').addClass(btnClass);
        $('#mdlNotes').modal('show');
    }

    $(document).on('click', '.btn-head-revise', function () {
        openNotesModal($(this).data('id'), 'head-revise', 'Kembalikan ke Manager', 'Catatan Revisi', 'btn-warning', 'Kembalikan', null);
    });
    $(document).on('click', '.btn-head-reject', function () {
        openNotesModal($(this).data('id'), 'head-reject', 'Tolak CR', 'Alasan Penolakan', 'btn-danger', 'Tolak', null);
    });
    $(document).on('click', '.btn-finance-revise', function () {
        openNotesModal($(this).data('id'), 'finance-revise', 'Kembalikan ke Manager', 'Catatan Revisi', 'btn-warning', 'Kembalikan', null);
    });
    $(document).on('click', '.btn-finance-reject', function () {
        openNotesModal($(this).data('id'), 'finance-reject', 'Tolak CR', 'Alasan Penolakan', 'btn-danger', 'Tolak', null);
    });
    $(document).on('click', '.btn-reject-realization', function () {
        openNotesModal($(this).data('id'), 'reject-realization', 'Tolak Realisasi', 'Alasan', 'btn-danger', 'Tolak → Paid',
            '<i class="fas fa-info-circle mr-1"></i> CR akan dikembalikan ke status <strong>PAID</strong> untuk direvisi manager.');
    });

    /* ── FINANCE VERIFY ── */
    $(document).on('click', '.btn-finance-verify', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        $('#financeVerifyText').text('Verifikasi CR ' + code + '? Status akan menjadi VERIFIED dan siap masuk batch.');
        $('#frmFinanceVerify').attr('action', '/budget-request/' + id + '/finance-verify');
        $('#frmFinanceVerify textarea').val('');
        $('#mdlFinanceVerify').modal('show');
    });

    /* ── SUBMIT REALISASI ── */
    var realIdx = 1;
    var crDetailsForReal = []; // detail item CR yang sedang diproses realisasinya (di-fetch via AJAX)

    function fmtRp(v) {
        v = (v || '').toString().replace(/[^\d]/g, '');
        return v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function buildCrItemOptions(selectedId) {
        var html = '<option value="">— Pilih Item CR —</option>';
        crDetailsForReal.forEach(function (d) {
            html += '<option value="' + d.id + '"' + (d.id == selectedId ? ' selected' : '') + '>' + d.description + '</option>';
        });
        return html;
    }

    function makeRealRow(i, prefill) {
        prefill = prefill || {};
        var opts = buildCrItemOptions(prefill.request_detail_id || null);
        var amountVal = prefill.amount ? fmtRp(String(prefill.amount)) : '';
        var notesVal  = prefill.notes || '';
        var totalCr   = '';

        if (prefill.request_detail_id) {
            var d = crDetailsForReal.find(function (x) { return String(x.id) === String(prefill.request_detail_id); });
            if (d) totalCr = d.total_formatted;
        }

        return '<tr class="realization-row">' +
            '<td class="text-center align-middle real-row-no"></td>' +
            '<td><select name="realizations[' + i + '][request_detail_id]" class="form-control form-control-sm real-item-select" required>' + opts + '</select></td>' +
            '<td class="text-right align-middle real-total-cr">' + (totalCr || '—') + '</td>' +
            '<td><input type="text" name="realizations[' + i + '][amount]" class="form-control form-control-sm text-right real-amount" value="' + amountVal + '" placeholder="0" required></td>' +
            '<td><input type="text" name="realizations[' + i + '][notes]" class="form-control form-control-sm" value="' + notesVal + '" placeholder="Catatan..."></td>' +
            '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-real-row"><i class="fas fa-trash"></i></button></td>' +
        '</tr>';
    }

    var realMaxAmount = 0;   // batas maksimal total realisasi (= nominal pengajuan CR)

    function recalcRealization() {
        var grand = 0;
        $('.realization-row').each(function () {
            var amt = parseInt(($('.real-amount', this).val() || '0').replace(/\./g, '')) || 0;
            grand += amt;
        });

        var over = realMaxAmount > 0 && grand > realMaxAmount;

        $('#realTotalLabel')
            .text('Total: Rp ' + grand.toLocaleString('id-ID'))
            .removeClass('badge-success badge-danger')
            .addClass(over ? 'badge-danger' : 'badge-success');

        if (over) {
            $('#realOverWarning').show();
            $('#btnSubmitRealization').prop('disabled', true);
        } else {
            $('#realOverWarning').hide();
            $('#btnSubmitRealization').prop('disabled', false);
        }
    }

    $(document).on('input', '.real-amount', function () {
        this.value = fmtRp(this.value);
        recalcRealization();
    });

    $(document).on('change', '.real-item-select', function () {
        var row = $(this).closest('tr');
        var d = crDetailsForReal.find(function (x) { return String(x.id) === String(row.find('.real-item-select').val()); });
        row.find('.real-total-cr').text(d ? d.total_formatted : '—');
    });

    // Guard: cegah submit bila total realisasi melebihi nominal pengajuan
    $('#frmSubmitRealization').on('submit', function (e) {
        var grand = 0;
        $('.realization-row').each(function () {
            var amt = parseInt(($('.real-amount', this).val() || '0').replace(/\./g, '')) || 0;
            grand += amt;
        });
        if (realMaxAmount > 0 && grand > realMaxAmount) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Melebihi Pengajuan',
                text: 'Total realisasi (Rp ' + grand.toLocaleString('id-ID') +
                      ') tidak boleh melebihi nominal pengajuan (Rp ' + realMaxAmount.toLocaleString('id-ID') + ').',
                confirmButtonColor: '#F5A623'
            });
            return false;
        }
    });

    $('#btnAddRealizationRow').on('click', function () {
        if (!crDetailsForReal.length) {
            Swal.fire({ icon:'warning', title:'Belum ada item CR', text:'Item CR gagal dimuat, coba buka ulang modal ini.', confirmButtonColor:'#F5A623' });
            return;
        }
        var i = realIdx++;
        $('#realizationBody').append(makeRealRow(i, null));
        renumberReal();
    });

    $(document).on('click', '.btn-remove-real-row', function () {
        if ($('.realization-row').length > 1) {
            $(this).closest('tr').remove();
            renumberReal();
            recalcRealization();
        } else {
            Swal.fire({ icon:'warning', title:'Minimal 1 baris', confirmButtonColor:'#F5A623' });
        }
    });

    function renumberReal() {
        $('.realization-row .real-row-no').each(function (i) { $(this).text(i + 1); });
    }

    $('#btnAddRealAtt').on('click', function () {
        $('#realAttList').append(
            '<div class="d-flex align-items-center mb-1" style="gap:8px;">' +
            '<input type="file" name="realization_attachments[]" class="form-control-file flex-grow-1" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">' +
            '<button type="button" class="btn btn-sm btn-danger btn-remove-real-att"><i class="fas fa-trash"></i></button>' +
            '</div>'
        );
    });
    $(document).on('click', '.btn-remove-real-att', function () {
        if ($('#realAttList .d-flex').length > 1) $(this).closest('.d-flex').remove();
    });

    $(document).on('click', '.btn-submit-realization', function () {
        var id     = $(this).data('id');
        var code   = $(this).data('code');
        var amount = parseInt($(this).data('amount')) || 0;

        realMaxAmount = amount;   // batas maksimal total realisasi = nominal pengajuan
        $('#realCRCode').text(code);
        $('#realCRAmount').text('Rp ' + amount.toLocaleString('id-ID'));
        $('#frmSubmitRealization').attr('action', '/budget-request/' + id + '/submit-realization');

        // Reset lampiran
        $('#realAttList').html(
            '<div class="d-flex align-items-center mb-1" style="gap:8px;">' +
            '<input type="file" name="realization_attachments[]" class="form-control-file flex-grow-1" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">' +
            '<button type="button" class="btn btn-sm btn-danger btn-remove-real-att"><i class="fas fa-trash"></i></button>' +
            '</div>'
        );

        // Muat item CR (Detail Item) via AJAX, baru bangun baris pertama
        crDetailsForReal = [];
        $('#realizationBody').html('<tr><td colspan="6" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin mr-1"></i> Memuat item CR...</td></tr>');
        $('#mdlSubmitRealization').modal('show');

        $.get('/budget-request/' + id + '/detail-json', function (d) {
            crDetailsForReal = d.details || [];
            realIdx = 1;
            $('#realizationBody').html(makeRealRow(0, null));
            renumberReal();
            recalcRealization();
        }).fail(function () {
            $('#realizationBody').html('<tr><td colspan="6" class="text-center text-danger py-3">Gagal memuat item CR.</td></tr>');
        });
    });

    /* ── VERIFY REALIZATION ── */
    $(document).on('click', '.btn-verify-realization', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        $('#verifyRealText').text('Verifikasi realisasi CR ' + code + '? Status akan berubah menjadi CLOSED.');
        $('#frmVerifyRealization').attr('action', '/budget-request/' + id + '/verify-realization');
        $('#frmVerifyRealization textarea').val('');
        $('#mdlVerifyRealization').modal('show');
    });

    /* ── MODAL DETAIL ── */
    // Set badge angka pada tab: tampil bila > 0, sembunyi bila 0
    function setTabCount(sel, n) {
        var el = $(sel);
        if (n && n > 0) { el.text(n).show(); }
        else { el.text('').hide(); }
    }

    var statusColors = {
        'DRAFT':'secondary','SUBMITTED':'warning','APPROVED':'info',
        'VERIFIED':'primary','QUEUED':'warning','PAID':'success',
        'REALIZED':'primary','CLOSED':'dark','REJECTED':'danger'
    };

    $(document).on('click', '.btn-detail-cr', function () {
        var id = $(this).data('id');
        $('#crDetailLoading').show(); $('#crDetailContent').hide();
        $('#mdlDetailCR').modal('show');

        $.get('/budget-request/' + id + '/detail-json', function (d) {
            $('#crModalTitle').text(d.description);
            $('#crModalSubtitle').text(d.code);
            $('#crCode').text(d.code);
            $('#crStatusBadge').html('<span class="badge badge-' + (statusColors[d.status]||'secondary') + ' px-2 py-1">' + d.status + '</span>');
            $('#crNobBadge').html(d.is_nob ? '<span class="badge badge-danger px-2 py-1 mt-1">NOB</span>' : '');
            $('#crType').text(d.type);
            $('#crAmount').text(d.amount_formatted);
            $('#crRealizationAmount').text(d.total_realization_formatted || 'Rp 0');
            $('#crBeneficiary').text(d.beneficiary);
            $('#crBank').text(d.bank + ' — ' + d.account_number);
            $('#crApplicant').text(d.applicant);

            // Info tab
            $('#infoCode').html('<span class="badge badge-dark px-2 py-1">' + d.code + '</span>');
            $('#infoDesc').html(d.is_nob ? '<span class="badge badge-danger mr-1" style="font-size:9px;">NOB</span>' + d.description : d.description);
            $('#infoType').html('<span class="badge badge-info px-2 py-1">' + d.type + '</span>');
            $('#infoProject').text(d.project);
            $('#infoCluster').text(d.cluster);
            $('#infoBudgetPlan').text(d.budget_plan);
            $('#infoAmount').html('<strong style="color:#2563EB;">' + d.amount_formatted + '</strong>');
            $('#infoStatus').html('<span class="badge badge-' + (statusColors[d.status]||'secondary') + ' px-2 py-1">' + d.status + '</span>');
            $('#infoEmail').text(d.email || '-');
            $('#infoNotes').text(d.notes || '-');

            // Detail tab
            setTabCount('#crDetailCount', d.details.length);
            var dHtml = '', grand = 0;
            d.details.forEach(function(det, i) {
                grand += det.total;
                var isNob    = det.notes && det.notes.indexOf('[NOB]') !== -1;
                var rowStyle = isNob ? ' style="color:#DC2626;"' : '';
                dHtml += '<tr' + rowStyle + '><td class="text-center">' + (i+1) + '</td><td>' + det.description + '</td><td>' + (det.notes || '-') + '</td><td class="text-right">' + det.amount_formatted + '</td><td class="text-center">' + det.qty + '</td><td class="text-right"><strong>' + det.total_formatted + '</strong></td></tr>';
            });
            $('#crDetailBody').html(dHtml || '<tr><td colspan="6" class="text-center text-muted py-3">Belum ada rincian.</td></tr>');
            $('#crDetailTotal').text('Rp ' + grand.toLocaleString('id-ID'));

            // Realization tab
            setTabCount('#crRealizationCount', d.realizations.length);
            if (d.realizations.length) {
                $('#crNoRealization').hide();
                $('#crRealizationWrapper').show();
                var rHtml = '', rGrand = 0;
                d.realizations.forEach(function(r, i) {
                    rGrand += r.total;
                    rHtml += '<tr><td class="text-center">' + (i+1) + '</td><td>' + r.cr_item + '</td><td class="text-right">' + r.total_cr_formatted + '</td><td class="text-right"><strong>' + r.actual_formatted + '</strong></td><td>' + (r.notes || '-') + '</td></tr>';
                });
                $('#crRealizationBody').html(rHtml);
                $('#crRealizationTotal').text('Rp ' + rGrand.toLocaleString('id-ID'));

                // Realization attachments
                var raHtml = '';
                if (d.realization_attachments && d.realization_attachments.length) {
                    raHtml = '<div class="mt-2"><strong style="font-size:12px;">Lampiran Realisasi:</strong>';
                    d.realization_attachments.forEach(function(a) {
                        raHtml += '<div class="d-flex align-items-center mt-1" style="gap:8px;"><span style="font-size:12px;">' + a.original_name + '</span><a href="/storage/' + a.path + '" target="_blank" class="btn btn-info btn-sm"><i class="fas fa-eye"></i></a></div>';
                    });
                    raHtml += '</div>';
                }
                $('#crRealizationAtts').html(raHtml);
            } else {
                $('#crNoRealization').show();
                $('#crRealizationWrapper').hide();
            }

            // Attachments tab
            var atts = d.attachments || [];
            setTabCount('#crAttCount', atts.length);
            var attHtml = '';
            if (atts.length) {
                attHtml += '<div class="table-responsive"><table class="table table-bordered mb-3" style="font-size:12px;"><tbody>';
                atts.forEach(function(a, i) {
                    attHtml += '<tr><td width="5%" class="text-center">' + (i+1) + '</td><td>' + a.original_name + '</td><td width="10%" class="text-center"><a href="/storage/' + a.path + '" target="_blank" class="btn btn-info btn-sm"><i class="fas fa-eye"></i></a></td></tr>';
                });
                attHtml += '</tbody></table></div>';
            }
            $('#crAttBody').html(attHtml || '<p class="text-muted py-3 mb-0">Belum ada lampiran.</p>');

            // Logs tab
            var logHtml = '';
            d.logs.forEach(function(log) {
                logHtml += '<div class="d-flex align-items-start mb-3" style="gap:10px;">' +
                    '<span class="badge badge-' + log.action_color + ' mt-1 px-2 py-1" style="font-size:10px;white-space:nowrap;">' + log.action_label + '</span>' +
                    '<div><div style="font-size:12px;font-weight:600;">' + log.actor + ' <span style="color:#9CA3AF;font-weight:400;">(' + (log.role||'system') + ')</span></div>' +
                    '<div style="font-size:11px;color:#6B7280;">' + log.date + '</div>' +
                    (log.notes ? '<div style="font-size:12px;margin-top:4px;padding:6px 10px;background:#F9FAFB;border-radius:6px;border:1px solid #E5E7EB;">' + log.notes + '</div>' : '') +
                    '</div></div>';
            });
            $('#crLogBody').html(logHtml || '<p class="text-muted py-3 mb-0">Belum ada log.</p>');

            $('#crDetailTabs a:first').tab('show');
            $('#crDetailLoading').hide(); $('#crDetailContent').show();

        }).fail(function () {
            $('#crDetailLoading').html('<p class="text-danger text-center py-4"><i class="fas fa-exclamation-triangle mr-1"></i>Gagal memuat data.</p>');
        });
    });

    $('#mdlDetailCR').on('hidden.bs.modal', function () {
        $('#crDetailContent').hide(); $('#crDetailLoading').show();
    });

});
</script>
@endpush