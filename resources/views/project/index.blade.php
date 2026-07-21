@extends('layouts.app')

@section('title', 'Purchase Order')

@section('content')

<!-- CONTENT HEADER -->
<div class="content-header">
    <div class="container-fluid">

        <!-- SUMMARY CARDS -->
        <!-- <div class="row mb-3">
            <div class="col-6 col-md-2">
                <div class="small-box bg-info">
                    <div class="inner"><h3>{{ $summary['waiting_po'] }}</h3><p>Waiting PO</p></div>
                    <div class="icon"><i class="fas fa-hourglass-half"></i></div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="small-box bg-success">
                    <div class="inner"><h3>{{ $summary['active'] }}</h3><p>Active</p></div>
                    <div class="icon"><i class="fas fa-play-circle"></i></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small-box bg-primary">
                    <div class="inner"><h3>{{ $summary['progress_done'] }}</h3><p>Progress Done</p></div>
                    <div class="icon"><i class="fas fa-flag-checkered"></i></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small-box bg-warning">
                    <div class="inner"><h3>{{ $summary['billed_paid'] }}</h3><p>Billed / Paid</p></div>
                    <div class="icon"><i class="fas fa-file-invoice-dollar"></i></div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="small-box bg-dark">
                    <div class="inner"><h3>{{ $summary['closed'] }}</h3><p>Closed</p></div>
                    <div class="icon"><i class="fas fa-lock"></i></div>
                </div>
            </div>
        </div> -->

        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold">Purchase Order</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="#">Finance</a></li>
                    <li class="breadcrumb-item active">Purchase Order</li>
                </ol>
            </div>
        </div>

    </div>
</div>

<!-- MAIN CONTENT -->
<div class="content">
    <div class="container-fluid">

        <div class="card card-primary card-outline shadow-sm">

            <div class="card-header d-flex align-items-center">
                <button id="refreshTable" class="btn btn-secondary btn-sm">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
                @if(Auth::user()->hasRole(['superadmin','head']))
                <a href="{{ url('/project/create') }}" class="btn btn-success btn-sm ml-auto">
                    <i class="fas fa-plus mr-1"></i> Tambah PO
                </a>
                @endif
            </div>

            <div class="card-body">

                <!-- FILTER BAR -->
                <div class="custom-toolbar mb-3">
                    <div class="custom-toolbar-left">
                        <div class="toolbar-row">
                            <span class="toolbar-label">Cari :</span>
                            <div class="toolbar-input" id="customSearch"></div>
                        </div>
                        <div class="toolbar-row">
                            <span class="toolbar-label">Filter :</span>
                            <div class="toolbar-input">
                                @php
                                    $filterCustomers = $projects->pluck('customer.name')->filter()->unique()->sort()->values();
                                    $filterDoneBy    = $projects->pluck('done_by')->filter()->unique()->sort()->values();
                                @endphp
                                <select id="advancedFilter" multiple placeholder="Filter data...">
                                    <optgroup label="Status">
                                        <option value="STATUS:DRAFT">Draft</option>
                                        <option value="STATUS:SUBMITTED">Submitted</option>
                                        <option value="STATUS:WAITING_PO">Waiting PO</option>
                                        <option value="STATUS:ACTIVE">Active</option>
                                        <option value="STATUS:PROGRESS_DONE">Progress Done</option>
                                        <option value="STATUS:BILLED">Billed</option>
                                        <option value="STATUS:PAID">Paid</option>
                                        <option value="STATUS:CLOSING">Closing</option>
                                        <option value="STATUS:CLOSED">Closed</option>
                                    </optgroup>
                                    <optgroup label="Customer">
                                        @foreach($filterCustomers as $cName)
                                            <option value="CUSTOMER:{{ $cName }}">{{ $cName }}</option>
                                        @endforeach
                                    </optgroup>
                                    <optgroup label="Implement By">
                                        @foreach($filterDoneBy as $dBy)
                                            <option value="DONEBY:{{ $dBy }}">{{ $dBy }}</option>
                                        @endforeach
                                    </optgroup>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TABLE -->
                <div class="table-responsive">
                    <table id="projectTable" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="all text-nowrap" style="width:1%;">No</th>
                                <th class="all text-nowrap" style="width:1%;">Kode</th>
                                <th class="all">Scope of Work</th>
                                <th class="text-nowrap" style="width:1%;">Implement By</th>
                                <th class="text-right text-nowrap" style="width:1%;">Nilai PO</th>
                                <th class="text-right text-nowrap" style="width:1%;">Budget Plan</th>
                                <th class="text-right text-nowrap" style="width:1%;">Gross Profit</th>
                                <th class="text-center text-nowrap" style="width:1%;">Margin</th>
                                <th class="text-nowrap" style="width:1%;">Status</th>
                                <th class="text-center all action-column" style="width:1%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($projects as $project)
                            @php
                                $sc = [
                                    'DRAFT'         => 'secondary',
                                    'SUBMITTED'     => 'warning',
                                    'WAITING_PO'    => 'info',
                                    'ACTIVE'        => 'success',
                                    'PROGRESS_DONE' => 'primary',
                                    'BILLED'        => 'info',
                                    'PAID'          => 'success',
                                    'CLOSING'       => 'warning',
                                    'CLOSED'        => 'dark',
                                ][$project->status] ?? 'secondary';
                            @endphp
                            <tr data-status="{{ $project->status }}"
                                data-customer="{{ $project->customer->name ?? '' }}"
                                data-doneby="{{ $project->done_by ?? '' }}">
                                <td class="text-nowrap">{{ $loop->iteration }}</td>

                                <td class="text-nowrap">
                                    <span class="badge badge-dark px-2 py-1" style="font-size:11px;">
                                        {{ $project->code ?? '-' }}
                                    </span>
                                </td>

                                <td>
                                    <div style="font-size:13px;font-weight:600;">{{ $project->name }}</div>
                                    @php
                                        $subParts = [];
                                        if ($project->po_number) {
                                            $subParts[] = $project->po_number;
                                        }
                                        if ($project->customer) {
                                            $subParts[] = $project->customer->name;
                                        }
                                    @endphp
                                    @if(count($subParts))
                                        <small class="text-muted">{{ implode(' • ', $subParts) }}</small>
                                    @endif
                                </td>

                                <td class="text-nowrap">
                                    <span class="badge badge-secondary px-2 py-1" style="font-size:10px;">
                                        {{ $project->done_by ?? '-' }}
                                    </span>
                                </td>

                                <td class="text-right text-nowrap col-raw-value" data-raw="{{ $project->value ?? 0 }}">
                                    <strong>{{ $project->value_formatted }}</strong>
                                </td>

                                @php
                                    $bpTotal = $project->budgetPlanTotal();
                                    $gp      = $project->grossProfit();
                                    $gm      = $project->grossMargin();
                                @endphp

                                <td class="text-right text-nowrap col-raw-bp" data-raw="{{ $bpTotal }}">
                                    Rp {{ number_format($bpTotal, 0, ',', '.') }}
                                </td>

                                <td class="text-right text-nowrap col-raw-gp {{ $gp >= 0 ? 'text-gp-pos' : 'text-gp-neg' }}" data-raw="{{ $gp }}">
                                    {{ $gp < 0 ? '-' : '' }}Rp {{ number_format(abs($gp), 0, ',', '.') }}
                                </td>

                                <td class="text-center text-nowrap">
                                    <span class="badge badge-{{ $gm >= 20 ? 'success' : ($gm >= 0 ? 'warning' : 'danger') }} px-2 py-1" style="font-size:11px;">
                                        {{ rtrim(rtrim(number_format($gm, 1, ',', '.'), '0'), ',') }}%
                                    </span>
                                </td>

                                <td class="text-nowrap">
                                    <span class="badge badge-{{ $sc }} px-2 py-1" style="font-size:10px;">
                                        {{ $project->status_label }}
                                    </span>
                                </td>

                                <td class="text-center action-column">
                                    <div class="action-buttons">

                                        {{-- DETAIL --}}
                                        <button type="button"
                                                class="btn btn-info btn-sm btn-detail-project"
                                                data-id="{{ $project->id }}"
                                                title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </button>

                                        {{-- SUBMIT (DRAFT → SUBMITTED) — head --}}
                                        @if($project->status === 'DRAFT' && Auth::user()->hasRole(['superadmin','head']))
                                        <form action="{{ url('/project/' . $project->id . '/submit') }}"
                                              method="POST" class="frmSubmitProject d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm"
                                                    title="Submit ke Finance">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </form>
                                        @endif

                                        {{-- EDIT —head:DRAFT, finance:DRAFT/SUBMITTED/WAITING_PO --}}
                                        @if(
                                            (Auth::user()->hasRole(['superadmin','head']) && $project->status === 'DRAFT') ||
                                            (Auth::user()->hasRole(['finance']) && in_array($project->status, ['DRAFT','SUBMITTED','WAITING_PO']))
                                        )
                                        <a href="{{ url('/project/' . $project->id . '/edit') }}"
                                           class="btn btn-warning btn-sm" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        @endif

                                        {{-- FINANCE ACTIONS (SUBMITTED) --}}
                                        @if($project->status === 'SUBMITTED' && Auth::user()->hasRole(['superadmin','finance']))
                                        <button type="button"
                                                class="btn btn-success btn-sm btn-finance-approve"
                                                data-id="{{ $project->id }}"
                                                data-name="{{ $project->name }}"
                                                title="Approve">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button type="button"
                                                class="btn btn-secondary btn-sm btn-finance-revise"
                                                data-id="{{ $project->id }}"
                                                title="Revisi">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        @endif

                                        {{-- WAITING PO → ACTIVE (form edit lengkap oleh finance) --}}
                                        @if($project->status === 'WAITING_PO' && Auth::user()->hasRole(['superadmin','finance']))
                                        <a href="{{ url('/project/' . $project->id . '/activate') }}"
                                           class="btn btn-success btn-sm"
                                           title="Lengkapi Data & Aktifkan">
                                            <i class="fas fa-play-circle"></i>
                                        </a>
                                        @endif

                                        {{-- SUBMIT BAST (ACTIVE → PROGRESS_DONE) — head --}}
                                        @if($project->status === 'ACTIVE' && Auth::user()->hasRole(['superadmin','head']))
                                        <button type="button"
                                                class="btn btn-primary btn-sm btn-submit-bast"
                                                data-id="{{ $project->id }}"
                                                data-name="{{ $project->name }}"
                                                title="Submit BAST">
                                            <i class="fas fa-file-signature"></i>
                                        </button>
                                        @endif

                                        {{-- SUBMIT CLOSING (PAID → CLOSING) — head --}}
                                        @if($project->status === 'PAID' && Auth::user()->hasRole(['superadmin','head']))
                                        <button type="button"
                                                class="btn btn-warning btn-sm btn-submit-closing"
                                                data-id="{{ $project->id }}"
                                                data-name="{{ $project->name }}"
                                                title="Submit MoM Closing">
                                            <i class="fas fa-file-contract"></i>
                                        </button>
                                        @endif

                                        {{-- VERIFY / REJECT CLOSING (CLOSING) — finance --}}
                                        @if($project->status === 'CLOSING' && Auth::user()->hasRole(['superadmin','finance']))
                                        <button type="button"
                                                class="btn btn-success btn-sm btn-verify-closing"
                                                data-id="{{ $project->id }}"
                                                data-name="{{ $project->name }}"
                                                title="Approve Closing">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button type="button"
                                                class="btn btn-danger btn-sm btn-reject-closing"
                                                data-id="{{ $project->id }}"
                                                title="Tolak MoM">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        @endif

                                        {{-- DELETE (DRAFT) — head --}}
                                        @if($project->status === 'DRAFT' && Auth::user()->hasRole(['superadmin','head']))
                                        <form action="{{ url('/project/' . $project->id) }}"
                                              method="POST" class="frmDeleteProject d-inline">
                                            @csrf
                                            @method('DELETE')
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
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-folder-open d-block mb-2"></i>
                                    Belum ada data PO
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr style="background:#F9FAFB;">
                                <th colspan="4" class="text-right" style="font-size:12px;">Total (data tampil)</th>
                                <th class="text-right text-nowrap" id="poFooterValue" style="font-size:12px;color:#2563EB;"></th>
                                <th class="text-right text-nowrap" id="poFooterBp" style="font-size:12px;color:#6B7280;"></th>
                                <th class="text-right text-nowrap" id="poFooterGp" style="font-size:12px;"></th>
                                <th colspan="3"></th>
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
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;padding:16px 24px;">
                <div class="d-flex align-items-center">
                    <div style="width:36px;height:36px;background:#F5A623;border-radius:8px;display:flex;align-items:center;justify-content:center;margin-right:12px;flex-shrink:0;">
                        <i class="fas fa-file-contract" style="color:#0D1B2A;font-size:15px;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0" id="mdlProjectTitle">Detail PO</h5>
                        <small id="mdlProjectSubtitle" style="color:#8A9BB0;font-size:11px;"></small>
                    </div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:0.7;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding:0;background:#F0F2F5;">
                <div id="projectDetailLoading" class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x" style="color:#F5A623;"></i>
                    <p class="text-muted mt-3 mb-0">Memuat data...</p>
                </div>
                <div id="projectDetailContent" style="display:none;">
                    <div class="row no-gutters">
                        <!-- LEFT -->
                        <div class="col-md-3" style="background:#0D1B2A;padding:28px 20px;min-height:450px;">
                            <div class="text-center mb-3">
                                <div style="width:64px;height:64px;background:#1D2D3F;border:3px solid #F5A623;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;">
                                    <i class="fas fa-file-contract" style="color:#F5A623;font-size:24px;"></i>
                                </div>
                                <div id="prjCode" class="mt-2" style="font-size:10px;color:#5A7080;letter-spacing:0.08em;"></div>
                                <div id="prjStatusBadge" class="mt-1"></div>
                            </div>
                            <hr style="border-color:rgba(255,255,255,0.08);">
                            <div class="mb-3">
                                <span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Customer</span>
                                <div id="prjCustomer" class="text-white mt-1" style="font-size:12px;"></div>
                            </div>
                            <div class="mb-3">
                                <span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Implement By</span>
                                <div id="prjDoneBy" class="text-white mt-1" style="font-size:12px;"></div>
                            </div>
                            <div class="mb-3">
                                <span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Nilai Kontrak</span>
                                <div id="prjValue" style="color:#F5A623;font-size:18px;font-weight:700;margin-top:4px;"></div>
                            </div>
                            <div class="mb-3">
                                <span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">No PO</span>
                                <div id="prjPoNumber" class="text-white mt-1" style="font-size:12px;"></div>
                            </div>
                            <div class="mb-3">
                                <span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Input By</span>
                                <div id="prjInputBy" class="text-white mt-1" style="font-size:12px;"></div>
                            </div>
                            <div id="prjSideExtra"></div>
                        </div>

                        <!-- RIGHT TABS -->
                        <div class="col-md-9" style="padding:24px;">
                            <ul class="nav nav-tabs mb-3" id="prjDetailTabs" style="border-bottom:2px solid #E5E7EB;flex-wrap:wrap;">
                                <li class="nav-item">
                                    <a class="nav-link active" data-toggle="tab" href="#prjPaneInfo" style="font-size:12px;font-weight:600;padding:8px 10px;">
                                        <i class="fas fa-info-circle mr-1"></i> Informasi
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#prjPaneDetails" style="font-size:12px;font-weight:600;padding:8px 10px;">
                                        <i class="fas fa-project-diagram mr-1"></i> Detail
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
                                    <a class="nav-link" data-toggle="tab" href="#prjPaneUsers" style="font-size:12px;font-weight:600;padding:8px 10px;">
                                        <i class="fas fa-user-shield mr-1"></i> Akses
                                        <span id="prjUserCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#prjPaneAttachments" style="font-size:12px;font-weight:600;padding:8px 10px;">
                                        <i class="fas fa-paperclip mr-1"></i> Lampiran
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
                                <!-- TAB INFO -->
                                <div class="tab-pane fade show active" id="prjPaneInfo">
                                    <table class="table table-bordered mb-0" style="font-size:13px;">
                                        <tr><th width="30%" style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Kode</th><td id="infoCode"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Scope of Work</th><td id="infoName"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Customer</th><td id="infoCustomer"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Implement By</th><td id="infoDoneBy"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">No PO</th><td id="infoPoNumber"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Term of Payment</th><td id="infoToP"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Nilai</th><td id="infoValue"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Status</th><td id="infoStatus"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Notes</th><td id="infoNotes" style="white-space:pre-line;"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Submitted</th><td id="infoSubmittedAt"></td></tr>
                                        <tr><th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Approved By</th><td id="infoApprovedBy"></td></tr>
                                        <tr id="rowBast" style="display:none;">
                                            <th colspan="2" style="background:#EFF6FF;color:#1D4ED8;font-size:11px;font-weight:700;text-transform:uppercase;">Data BAST</th>
                                        </tr>
                                        <tr id="rowBastNo" class="rowBastItem" style="display:none;">
                                            <th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">No BAST</th>
                                            <td id="infoBastNo"></td>
                                        </tr>
                                        <tr id="rowBastTgl" class="rowBastItem" style="display:none;">
                                            <th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Tanggal BAST</th>
                                            <td id="infoBastTgl"></td>
                                        </tr>
                                        <tr id="rowBastNilai" class="rowBastItem" style="display:none;">
                                            <th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Nilai BAST</th>
                                            <td id="infoBastNilai"></td>
                                        </tr>
                                        <tr id="rowBastNotes" class="rowBastItem" style="display:none;">
                                            <th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Catatan BAST</th>
                                            <td id="infoBastNotes"></td>
                                        </tr>
                                        <tr id="rowBastBy" class="rowBastItem" style="display:none;">
                                            <th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Submitted By</th>
                                            <td id="infoBastBy"></td>
                                        </tr>
                                        <tr id="rowClosed" style="display:none;">
                                            <th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Closed By</th>
                                            <td id="infoClosedBy"></td>
                                        </tr>
                                        <tr id="rowClosingNotes" style="display:none;">
                                            <th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Closing Notes</th>
                                            <td id="infoClosingNotes" style="white-space:pre-line;"></td>
                                        </tr>
                                    </table>
                                </div>

                                <!-- TAB PROJECT DETAIL -->
                                <div class="tab-pane fade" id="prjPaneDetails">
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0" style="font-size:12px;">
                                            <thead style="background:#F9FAFB;">
                                                <tr>
                                                    <th style="color:#6B7280;font-size:11px;width:5%;">No</th>
                                                    <th style="color:#6B7280;font-size:11px;">Project</th>
                                                    <th style="color:#6B7280;font-size:11px;">Milestone</th>
                                                    <th style="color:#6B7280;font-size:11px;">Plan Start</th>
                                                    <th style="color:#6B7280;font-size:11px;">Plan End</th>
                                                    <th style="color:#6B7280;font-size:11px;">Actual Start</th>
                                                    <th style="color:#6B7280;font-size:11px;">Actual End</th>
                                                    <th style="color:#6B7280;font-size:11px;">Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody id="prjDetailBody"></tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- TAB PIC -->
                                <div class="tab-pane fade" id="prjPanePics">
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0" style="font-size:12px;">
                                            <thead style="background:#F9FAFB;">
                                                <tr>
                                                    <th style="color:#6B7280;font-size:11px;width:5%;">No</th>
                                                    <th style="color:#6B7280;font-size:11px;width:12%;">Tipe</th>
                                                    <th style="color:#6B7280;font-size:11px;">Nama PIC</th>
                                                    <th style="color:#6B7280;font-size:11px;">Jabatan</th>
                                                    <th style="color:#6B7280;font-size:11px;">Whatsapp</th>
                                                    <th style="color:#6B7280;font-size:11px;">Email</th>
                                                </tr>
                                            </thead>
                                            <tbody id="prjPicBody"></tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- TAB BUDGET -->
                                <div class="tab-pane fade" id="prjPaneBudgets">
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0" style="font-size:12px;">
                                            <thead style="background:#F9FAFB;">
                                                <tr>
                                                    <th style="color:#6B7280;font-size:11px;width:5%;">No</th>
                                                    <th style="color:#6B7280;font-size:11px;">Deskripsi</th>
                                                    <th style="color:#6B7280;font-size:11px;">Status</th>
                                                    <th style="color:#6B7280;font-size:11px;text-align:right;">Budget</th>
                                                    <th style="color:#6B7280;font-size:11px;text-align:right;">Used</th>
                                                    <th style="color:#6B7280;font-size:11px;text-align:right;">Var</th>
                                                </tr>
                                            </thead>
                                            <tbody id="prjBudgetBody"></tbody>
                                            <tfoot>
                                                <tr style="background:#F9FAFB;">
                                                    <th colspan="3" class="text-right" style="font-size:11px;">Total</th>
                                                    <th class="text-right" id="prjBudgetTotal" style="color:#2563EB;font-size:11px;"></th>
                                                    <th class="text-right" id="prjUsedTotal" style="color:#D97706;font-size:11px;"></th>
                                                    <th class="text-right" id="prjVarTotal" style="font-size:11px;"></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>

                                <!-- TAB USER AKSES -->
                                <div class="tab-pane fade" id="prjPaneUsers">
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0" style="font-size:12px;">
                                            <thead style="background:#F9FAFB;">
                                                <tr>
                                                    <th style="color:#6B7280;font-size:11px;width:5%;">No</th>
                                                    <th style="color:#6B7280;font-size:11px;">Nama</th>
                                                    <th style="color:#6B7280;font-size:11px;">Email</th>
                                                    <th style="color:#6B7280;font-size:11px;">Role</th>
                                                    <th style="color:#6B7280;font-size:11px;">Access Group</th>
                                                </tr>
                                            </thead>
                                            <tbody id="prjUserBody"></tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- TAB LAMPIRAN -->
                                <div class="tab-pane fade" id="prjPaneAttachments">
                                    <div id="prjAttBody"></div>
                                </div>

                                <!-- TAB LOG -->
                                <div class="tab-pane fade" id="prjPaneLogs">
                                    <div id="prjLogBody"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="background:#F8F9FA;border-top:1px solid #E9ECEF;padding:12px 24px;">
                <small class="text-muted mr-auto"><i class="fas fa-info-circle mr-1"></i> Read-only.</small>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Tutup
                </button>
                <a id="prjEditBtn" href="#" class="btn btn-warning btn-sm" style="display:none;">
                    <i class="fas fa-edit mr-1"></i> Edit
                </a>
            </div>
        </div>
    </div>
</div>

<!-- MODAL FINANCE APPROVE -->
<div class="modal fade" id="mdlFinanceApprove" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white">
                    <i class="fas fa-check mr-2" style="color:#F5A623;"></i> Approval Finance
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmFinanceApprove" method="POST">
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
                    <div class="form-group" id="poNumberGroup" style="display:none;">
                        <label class="font-weight-600">No PO <span class="text-danger">*</span></label>
                        <input type="text" name="po_number" class="form-control"
                               placeholder="Nomor PO dari customer...">
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Catatan (opsional)</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8f9fa;">
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
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white">
                    <i class="fas fa-undo mr-2" style="color:#F5A623;"></i> Kembalikan untuk Revisi
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmFinanceRevise" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Catatan Revisi <span class="text-danger">*</span></label>
                        <textarea name="notes" class="form-control" rows="3"
                                  placeholder="Tuliskan catatan revisi..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8f9fa;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-undo mr-1"></i> Kembalikan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL SUBMIT BAST -->
<div class="modal fade" id="mdlSubmitBast" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white">
                    <i class="fas fa-file-signature mr-2" style="color:#F5A623;"></i> Submit BAST
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmSubmitBast" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <p id="bastProjectName" class="font-weight-600 mb-3 text-muted"></p>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">No BAST <span class="text-danger">*</span></label>
                                <input type="text" name="no_bast" class="form-control"
                                       placeholder="Nomor BAST..." required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Tanggal BAST <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal_bast" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Nilai BAST <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                    <input type="text" name="nilai_bast" class="form-control text-right inp-money-bast"
                                           placeholder="0" required>
                                </div>
                                <small class="text-muted">Isi sesuai nilai BAST aktual (bisa berbeda dengan nilai PO).</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Catatan</label>
                                <textarea name="bast_notes" class="form-control" rows="2"
                                          placeholder="Catatan BAST / keterangan deduction..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">
                            Lampiran BAST <span class="text-danger">*</span>
                        </label>
                        <div id="bastAttList">
                            <div class="d-flex align-items-center mb-2" style="gap:8px;">
                                <input type="text" name="bast_attachments[0][title]"
                                       class="form-control bast-att-title" style="max-width:220px;text-transform:uppercase;"
                                       placeholder="Judul lampiran..." required>
                                <input type="file" name="bast_attachments[0][file]"
                                       class="form-control-file flex-grow-1"
                                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                                <button type="button" class="btn btn-sm btn-danger btn-remove-bast-att">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary mt-1" id="btnAddBastAtt">
                            <i class="fas fa-plus mr-1"></i> Tambah File
                        </button>
                        <small class="text-muted d-block mt-1">Format: pdf, jpg, png, doc(x). Maks 10 MB. Judul wajib diisi.</small>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8f9fa;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-file-signature mr-1"></i> Submit BAST
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL SUBMIT CLOSING -->
<div class="modal fade" id="mdlSubmitClosing" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white">
                    <i class="fas fa-file-contract mr-2" style="color:#F5A623;"></i> Submit MoM Closing
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmSubmitClosing" method="POST" enctype="multipart/form-data">
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
                <div class="modal-footer" style="background:#f8f9fa;">
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
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white">
                    <i class="fas fa-check mr-2" style="color:#F5A623;"></i> Verifikasi MoM Closing
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmVerifyClosing" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <p id="verifyClosingText" class="text-muted mb-3"></p>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Catatan (opsional)</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8f9fa;">
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
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white">
                    <i class="fas fa-times mr-2" style="color:#F5A623;"></i> Tolak MoM Closing
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmRejectClosing" method="POST">
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
                <div class="modal-footer" style="background:#f8f9fa;">
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
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white">
                    <i class="fas fa-lock mr-2" style="color:#F5A623;"></i> Tutup PO (Closing)
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
                <div class="modal-footer" style="background:#f8f9fa;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-dark">
                        <i class="fas fa-lock mr-1"></i> Tutup PO
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
.text-gp-pos { color:#16a34a; }
.text-gp-neg { color:#DC2626; }
.custom-toolbar      { display:flex; align-items:flex-start; gap:10px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; flex-direction:row; align-items:center; gap:12px; flex-wrap:wrap; width:100%; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; font-weight:600; color:#495057; white-space:nowrap; flex-shrink:0; }
.toolbar-input       { min-width:180px; }
#customSearch input  { width:180px; border-radius:6px; }
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

@media (max-width: 576px) {
    .custom-toolbar-left { flex-direction:column; align-items:stretch; }
    .toolbar-row         { width:100%; }
    .toolbar-input       { flex:1; min-width:0; }
    #customSearch input  { width:100%; }
    .ts-wrapper          { width:100% !important; min-width:0 !important; }
    .ts-control          { width:100% !important; }
}
#projectTable { width:100% !important; }
.table td,.table th { vertical-align:middle !important; }
.action-column { white-space:nowrap !important; width:1% !important; }
.action-buttons { display:flex; align-items:center; justify-content:center; gap:4px; flex-wrap:nowrap; }
.btn-xs { padding:2px 8px; font-size:11px; border-radius:4px; }
.dataTables_wrapper .row:last-child { margin-top:18px; align-items:center; }
.dataTables_info { padding-top:0 !important; font-size:14px; color:#6c757d; }
.dataTables_length { display:flex; align-items:center; margin-left:18px; }
.dataTables_length label { display:flex; align-items:center; gap:10px; margin-bottom:0; font-size:14px; font-weight:600; color:#6c757d; }
.dataTables_length select { appearance:none !important; background-color:#fff !important; color:#495057 !important; border:1px solid #ced4da !important; border-radius:6px !important; padding:4px 30px 4px 10px !important; height:36px !important; min-width:72px !important; font-size:14px !important; font-weight:600 !important; cursor:pointer; box-shadow:none !important; }
.dataTables_paginate .paginate_button { border-radius:6px !important; margin:0 2px; }
.dataTables_paginate .paginate_button.current { background:#F5A623 !important; background-image:none !important; border-color:#C97E0A !important; color:#0D1B2A !important; font-weight:700 !important; }
.dataTables_wrapper .pagination .page-link { color:#F5A623 !important; }
#prjDetailTabs .nav-link { color:#6B7280; border:none; border-bottom:2px solid transparent; border-radius:0; padding:8px 16px; }
#prjDetailTabs .nav-link.active { color:#F5A623 !important; border-bottom-color:#F5A623 !important; background:transparent !important; font-weight:700; }
.font-weight-600 { font-weight:600; }

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
/* item di dalam grup diberi jarak (indent) */
.ts-dropdown .optgroup .option,
.selectize-dropdown .optgroup .option {
    padding-left: 26px !important;
}
</style>
@endpush

@push('scripts')
<script>
$(function () {

    /*
    |--------------------------------------------------------------------------
    | DATATABLE
    |--------------------------------------------------------------------------
    */
    var table = $('#projectTable').DataTable({
        responsive: { details: { type: 'inline' } },
        autoWidth: false, pageLength: 10, lengthChange: true, searching: true,
        columnDefs: [
            // Kolom: 0=No 1=Kode 2=Scope 3=Implement By 4=Nilai PO 5=Budget Plan 6=Gross Profit 7=Margin 8=Status 9=Action
            { responsivePriority: 1,   targets: [0, 1, 2, -1] },
            { responsivePriority: 2,   targets: [8] },
            { responsivePriority: 100, targets: [3, 4, 5, 6, 7] }
        ],
        dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex flex-wrap justify-content-between justify-content-md-end align-items-center gap-2"lp>>',
        language: {
            lengthMenu: 'Page Size _MENU_', zeroRecords: 'Data tidak ditemukan',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data', paginate: { previous: 'Previous', next: 'Next' }
        }
    });

    $('#projectTable_filter').hide();
    $('#customSearch').html('<input type="search" id="searchInput" class="form-control form-control-sm" placeholder="Cari data...">');
    $('#searchInput').on('keyup', function () { table.search(this.value).draw(); });

    new TomSelect('#advancedFilter', {
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
    | FOOTER KALKULASI — total Nilai PO, Budget Plan & Gross Profit dari SEMUA
    | baris yang lolos filter/search saat ini (bukan cuma baris di halaman aktif).
    */
    function moneyFmtPO(v) {
        var sign = v < 0 ? '-' : '';
        return sign + 'Rp ' + Math.abs(Math.round(v)).toLocaleString('id-ID');
    }

    function recalcFooterPO() {
        var totalValue = 0, totalBp = 0, totalGp = 0;
        table.rows({ search: 'applied' }).every(function () {
            var node = this.node();
            totalValue += parseFloat(node.querySelector('.col-raw-value')?.dataset.raw) || 0;
            totalBp    += parseFloat(node.querySelector('.col-raw-bp')?.dataset.raw)    || 0;
            totalGp    += parseFloat(node.querySelector('.col-raw-gp')?.dataset.raw)    || 0;
        });
        $('#poFooterValue').text(moneyFmtPO(totalValue));
        $('#poFooterBp').text(moneyFmtPO(totalBp));
        $('#poFooterGp').html(
            '<span class="' + (totalGp >= 0 ? 'text-gp-pos' : 'text-gp-neg') + '">' + moneyFmtPO(totalGp) + '</span>'
        );
    }

    table.on('draw', recalcFooterPO);
    recalcFooterPO();

    /*
    | Filter membaca nilai MENTAH dari atribut data-* pada <tr>,
    | bukan teks kolom (label "Waiting PO" != nilai "WAITING_PO").
    | Aturan: OR di dalam satu grup, AND antar grup.
    */
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        if (settings.nTable.id !== 'projectTable') return true;

        var filters = $('#advancedFilter').val();
        if (!filters || !filters.length) return true;

        var row = settings.aoData[dataIndex].nTr;
        if (!row) return true;

        var rowStatus   = row.getAttribute('data-status')   || '';
        var rowCustomer = row.getAttribute('data-customer') || '';
        var rowDoneBy   = row.getAttribute('data-doneby')   || '';

        var selStatus = [], selCustomer = [], selDoneBy = [];

        filters.forEach(function(f) {
            var i = f.indexOf(':');
            if (i < 0) return;
            var key = f.substring(0, i);
            var val = f.substring(i + 1);
            if (key === 'STATUS')        selStatus.push(val);
            else if (key === 'CUSTOMER') selCustomer.push(val);
            else if (key === 'DONEBY')   selDoneBy.push(val);
        });

        if (selStatus.length   && selStatus.indexOf(rowStatus)     === -1) return false;
        if (selCustomer.length && selCustomer.indexOf(rowCustomer) === -1) return false;
        if (selDoneBy.length   && selDoneBy.indexOf(rowDoneBy)     === -1) return false;

        return true;
    });

    $('#advancedFilter').on('change', function () { table.draw(); });
    $('#refreshTable').on('click', function () { location.reload(); });

    /*
    |--------------------------------------------------------------------------
    | SUBMIT CONFIRM
    |--------------------------------------------------------------------------
    */
    $(document).on('submit', '.frmSubmitProject', function (e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: 'Submit PO?',
            text: 'PO akan dikirim ke Finance untuk direview.',
            icon: 'question', showCancelButton: true,
            confirmButtonColor: '#007bff', cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Submit', cancelButtonText: 'Batal'
        }).then(function (r) { if (r.isConfirmed) form.submit(); });
    });

    $(document).on('submit', '.frmDeleteProject', function (e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: 'Hapus PO?', text: 'Data tidak dapat dikembalikan.',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#d33', cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
        }).then(function (r) { if (r.isConfirmed) form.submit(); });
    });

    /*
    |--------------------------------------------------------------------------
    | FINANCE APPROVE
    |--------------------------------------------------------------------------
    */
    $(document).on('click', '.btn-finance-approve', function () {
        var id   = $(this).data('id');
        var name = $(this).data('name');
        $('#financeApproveText').text('Review PO: ' + name);
        $('#frmFinanceApprove').attr('action', '/project/' + id + '/finance-approve');
        $('#frmFinanceApprove')[0].reset();
        $('#poNumberGroup').hide();
        $('#mdlFinanceApprove').modal('show');
    });

    $('#approvalTypeSelect').on('change', function () {
        if ($(this).val() === 'ACTIVE') {
            $('#poNumberGroup').show();
            $('#poNumberGroup input').prop('required', true);
        } else {
            $('#poNumberGroup').hide();
            $('#poNumberGroup input').prop('required', false);
        }
    });

    /*
    |--------------------------------------------------------------------------
    | FINANCE REVISE
    |--------------------------------------------------------------------------
    */
    $(document).on('click', '.btn-finance-revise', function () {
        var id = $(this).data('id');
        $('#frmFinanceRevise').attr('action', '/project/' + id + '/finance-revise');
        $('#frmFinanceRevise')[0].reset();
        $('#mdlFinanceRevise').modal('show');
    });

    /*
    /*
    |--------------------------------------------------------------------------
    | SUBMIT BAST
    |--------------------------------------------------------------------------
    */
    function fmtMoney(v) {
        v = (v || '').toString().replace(/[^\d]/g, '');
        return v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    $(document).on('input', '.inp-money-bast', function () {
        this.value = fmtMoney(this.value);
    });

    var bastAttIdx = 0;

    function makeBastAttRow(required) {
        var i = bastAttIdx++;
        return '<div class="d-flex align-items-center mb-2" style="gap:8px;">' +
            '<input type="text" name="bast_attachments[' + i + '][title]" class="form-control bast-att-title" style="max-width:220px;text-transform:uppercase;" placeholder="Judul lampiran..." ' + (required ? 'required' : '') + '>' +
            '<input type="file" name="bast_attachments[' + i + '][file]" class="form-control-file flex-grow-1" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" ' + (required ? 'required' : '') + '>' +
            '<button type="button" class="btn btn-sm btn-danger btn-remove-bast-att"><i class="fas fa-trash"></i></button>' +
        '</div>';
    }

    $(document).on('click', '.btn-submit-bast', function () {
        var id   = $(this).data('id');
        var name = $(this).data('name');
        $('#bastProjectName').text(name);
        $('#frmSubmitBast').attr('action', '/project/' + id + '/submit-bast');
        $('#frmSubmitBast')[0].reset();
        bastAttIdx = 0;
        $('#bastAttList').html(makeBastAttRow(true));
        $('#mdlSubmitBast').modal('show');
    });

    $('#btnAddBastAtt').on('click', function () {
        $('#bastAttList').append(makeBastAttRow(false));
    });

    $(document).on('click', '.btn-remove-bast-att', function () {
        if ($('#bastAttList .d-flex').length > 1) $(this).closest('.d-flex').remove();
    });

    /*
    |--------------------------------------------------------------------------
    | SUBMIT CLOSING
    |--------------------------------------------------------------------------
    */
    var closingIdx2 = 0;

    function makeClosingAttRow2(required) {
        var i = closingIdx2++;
        return '<div class="d-flex align-items-center mb-2" style="gap:8px;">' +
            '<input type="text" name="closing_attachments[' + i + '][title]" class="form-control closing-att-title" style="max-width:220px;text-transform:uppercase;" placeholder="Judul lampiran..." ' + (required ? 'required' : '') + '>' +
            '<input type="file" name="closing_attachments[' + i + '][file]" class="form-control-file flex-grow-1" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" ' + (required ? 'required' : '') + '>' +
            '<button type="button" class="btn btn-sm btn-danger btn-remove-closing-att2"><i class="fas fa-trash"></i></button>' +
        '</div>';
    }

    $(document).on('click', '.btn-submit-closing', function () {
        var id   = $(this).data('id');
        var name = $(this).data('name');
        $('#closingProjectName').text(name);
        $('#frmSubmitClosing').attr('action', '/project/' + id + '/submit-closing');
        $('#frmSubmitClosing')[0].reset();
        closingIdx2 = 0;
        $('#closingAttList2').html(makeClosingAttRow2(true));
        $('#mdlSubmitClosing').modal('show');
    });

    $('#btnAddClosingAtt2').on('click', function () {
        $('#closingAttList2').append(makeClosingAttRow2(false));
    });

    $(document).on('click', '.btn-remove-closing-att2', function () {
        if ($('#closingAttList2 .d-flex').length > 1) $(this).closest('.d-flex').remove();
    });

    /*
    |--------------------------------------------------------------------------
    | VERIFY CLOSING
    |--------------------------------------------------------------------------
    */
    $(document).on('click', '.btn-verify-closing', function () {
        var id   = $(this).data('id');
        var name = $(this).data('name');
        $('#verifyClosingText').text('Verifikasi MoM Closing untuk PO: ' + name + '. Status akan berubah menjadi CLOSED.');
        $('#frmVerifyClosing').attr('action', '/project/' + id + '/verify-closing');
        $('#frmVerifyClosing textarea[name="notes"]').val('');
        $('#mdlVerifyClosing').modal('show');
    });

    /*
    |--------------------------------------------------------------------------
    | REJECT CLOSING
    |--------------------------------------------------------------------------
    */
    $(document).on('click', '.btn-reject-closing', function () {
        var id = $(this).data('id');
        $('#frmRejectClosing').attr('action', '/project/' + id + '/reject-closing');
        $('#frmRejectClosing textarea[name="notes"]').val('');
        $('#mdlRejectClosing').modal('show');
    });

    /*
    |--------------------------------------------------------------------------
    | CLOSE PROJECT
    |--------------------------------------------------------------------------
    */
    var closingIdx = 0;

    function makeClosingAttRow(required) {
        var i = closingIdx++;
        return '<div class="d-flex align-items-center mb-2" style="gap:8px;">' +
            '<input type="text" name="closing_attachments[' + i + '][title]" class="form-control closing-att-title" style="max-width:220px;text-transform:uppercase;" placeholder="Judul lampiran..." ' + (required ? 'required' : '') + '>' +
            '<input type="file" name="closing_attachments[' + i + '][file]" class="form-control-file flex-grow-1" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" ' + (required ? 'required' : '') + '>' +
            '<button type="button" class="btn btn-xs btn-danger btn-remove-closing-att"><i class="fas fa-trash"></i></button>' +
        '</div>';
    }

    $(document).on('click', '.btn-close-project', function () {
        var id   = $(this).data('id');
        var name = $(this).data('name');
        $('#closeProjectText').text('Tutup PO: ' + name + '. Pastikan MoM sudah siap diupload.');
        $('#frmCloseProject').attr('action', '/project/' + id + '/close');
        $('#frmCloseProject')[0].reset();
        closingIdx = 0;
        $('#closingAttList').html(makeClosingAttRow(true));
        $('#mdlCloseProject').modal('show');
    });

    $('#btnAddClosingAtt').on('click', function () {
        $('#closingAttList').append(makeClosingAttRow(false));
    });

    $(document).on('click', '.btn-remove-closing-att', function () {
        if ($('#closingAttList .d-flex').length > 1) $(this).closest('.d-flex').remove();
    });

    /*
    |--------------------------------------------------------------------------
    | MODAL DETAIL
    |--------------------------------------------------------------------------
    */
    var statusColors = {
        'DRAFT':'secondary','SUBMITTED':'warning',
        'WAITING_PO':'info','ACTIVE':'success','CLOSED':'dark'
    };

    $(document).on('click', '.btn-detail-project', function () {
        var id = $(this).data('id');
        $('#projectDetailLoading').show();
        $('#projectDetailContent').hide();
        $('#mdlDetailProject').modal('show');

        $.get('/project/' + id + '/detail-json', function (d) {
            $('#mdlProjectTitle').text(d.name);
            $('#mdlProjectSubtitle').text('Kode: ' + (d.code || '-'));

            $('#prjCode').text(d.code || '-');
            $('#prjStatusBadge').html('<span class="badge badge-' + (statusColors[d.status]||'secondary') + ' px-3 py-1">' + d.status_label + '</span>');
            $('#prjCustomer').text(d.customer);
            $('#prjDoneBy').text(d.done_by || '-');
            $('#prjValue').text(d.value_formatted);
            $('#prjPoNumber').text(d.po_number || '-');
            $('#prjInputBy').text(d.input_by);

            // Sidebar extra stats — dihitung dari budget_plans (sumber sama dgn tab Budget)
            var totalBudgetAmt = 0, totalUsedAmt = 0;
            d.budget_plans.forEach(function(bp) {
                totalBudgetAmt += parseInt(bp.amount) || 0;
                totalUsedAmt   += parseInt(bp.used)   || 0;
            });
            var totalVarAmt  = totalBudgetAmt - totalUsedAmt;
            var varColorSide = totalVarAmt < 0 ? '#DC2626' : '#28a745';

            var sideExtra = '';
            sideExtra += '<hr style="border-color:rgba(255,255,255,0.08);">';
            sideExtra += '<div class="mb-3"><span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Total Budget</span>';
            sideExtra += '<div style="color:#F5A623;font-size:14px;font-weight:700;margin-top:4px;">' + d.budget_plans.length + ' Plan &bull; Rp ' + totalBudgetAmt.toLocaleString('id-ID') + '</div></div>';
            sideExtra += '<div class="mb-3"><span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Total Used</span>';
            sideExtra += '<div style="color:#D97706;font-size:14px;font-weight:700;margin-top:4px;">Rp ' + totalUsedAmt.toLocaleString('id-ID') + '</div></div>';
            sideExtra += '<div class="mb-3"><span style="color:#5A7080;font-size:10px;text-transform:uppercase;font-weight:600;">Variance</span>';
            sideExtra += '<div style="color:' + varColorSide + ';font-size:14px;font-weight:700;margin-top:4px;">Rp ' + totalVarAmt.toLocaleString('id-ID') + '</div></div>';
            $('#prjSideExtra').html(sideExtra);

            // Tab Info
            $('#infoCode').html('<span class="badge badge-dark px-2 py-1">' + (d.code || '-') + '</span>');
            $('#infoName').text(d.name);
            $('#infoCustomer').text(d.customer);
            $('#infoDoneBy').text(d.done_by || '-');
            $('#infoPoNumber').text(d.po_number || '-');
            $('#infoToP').text(d.term_of_payment || '-');

            // Tab Project Detail
            $('#prjDetailCount').text(d.details.length);
            var detHtml = '';
            // Urutkan berdasarkan Project agar baris sejenis berurutan (syarat rowspan),
            // urutan asli dalam satu project tetap terjaga (stable sort).
            var dets = (d.details || []).slice().sort(function(a, b) {
                return String(a.cluster || '').localeCompare(String(b.cluster || ''));
            });
            // Gabungkan (rowspan) baris dengan Project yang sama agar tidak terlihat dobel
            var groupSize = {};
            dets.forEach(function(det) {
                var key = det.cluster || '-';
                groupSize[key] = (groupSize[key] || 0) + 1;
            });
            var groupSeen = {};
            dets.forEach(function(det, i) {
                var key = det.cluster || '-';
                detHtml += '<tr><td class="text-center">' + (i+1) + '</td>';
                if (!groupSeen[key]) {
                    groupSeen[key] = true;
                    detHtml += '<td rowspan="' + groupSize[key] + '" style="vertical-align:middle;font-weight:600;">' + key + '</td>';
                }
                detHtml += '<td>' + det.stage + '</td>' +
                    '<td>' + (det.plan_start||'-') + '</td>' +
                    '<td>' + (det.plan_end||'-') + '</td>' +
                    '<td>' + (det.actual_start||'-') + '</td>' +
                    '<td>' + (det.actual_end||'-') + '</td>' +
                    '<td>' + (det.notes||'-') + '</td></tr>';
            });
            $('#prjDetailBody').html(detHtml || '<tr><td colspan="8" class="text-center text-muted py-3">Belum ada data detail.</td></tr>');

            // Tab PIC
            var allPics = (d.pics_internal || []).map(function (p) { return Object.assign({}, p, { _type: 'Internal', _badge: 'secondary' }); })
                .concat((d.pics_customer || []).map(function (p) { return Object.assign({}, p, { _type: 'Customer', _badge: 'primary' }); }));
            $('#prjPicCount').text(allPics.length);
            var picHtml = '';
            allPics.forEach(function(p, i) {
                picHtml += '<tr><td class="text-center">' + (i+1) + '</td>' +
                    '<td><span class="badge badge-' + p._badge + ' px-2 py-1" style="font-size:10px;">' + p._type + '</span></td>' +
                    '<td>' + (p.pic||'-') + '</td>' +
                    '<td>' + (p.position||'-') + '</td>' +
                    '<td>' + (p.phone||'-') + '</td>' +
                    '<td>' + (p.email||'-') + '</td></tr>';
            });
            $('#prjPicBody').html(picHtml || '<tr><td colspan="6" class="text-center text-muted py-3">Belum ada PIC.</td></tr>');

            // Tab Budget (termasuk realisasi Cost: Used & Var)
            $('#prjBudgetCount').text(d.budget_plans.length);
            var budgetHtml = '';
            var sumBudget = 0, sumUsed = 0;
            d.budget_plans.forEach(function(bp, i) {
                var amt  = parseInt(bp.amount || 0);
                var used = parseInt(bp.used || 0);
                var vr   = amt - used;
                sumBudget += amt;
                sumUsed   += used;

                var varColor = vr < 0 ? '#DC2626' : '#059669';

                budgetHtml += '<tr><td class="text-center">' + (i+1) + '</td>' +
                    '<td><div style="font-size:12px;font-weight:600;">' + bp.description + '</div>' +
                    '<small class="text-muted">' + (bp.code || '-') + '</small></td>' +
                    '<td><span class="badge badge-' + bp.status_color + ' px-2 py-1" style="font-size:9px;">' + bp.status + '</span></td>' +
                    '<td class="text-right"><strong>Rp ' + amt.toLocaleString('id-ID') + '</strong></td>' +
                    '<td class="text-right" style="color:#D97706;">Rp ' + used.toLocaleString('id-ID') + '</td>' +
                    '<td class="text-right" style="color:' + varColor + ';font-weight:600;">Rp ' + vr.toLocaleString('id-ID') + '</td></tr>';
            });
            $('#prjBudgetBody').html(budgetHtml || '<tr><td colspan="6" class="text-center text-muted py-3">Belum ada Budget Plan.</td></tr>');

            var sumVar = sumBudget - sumUsed;
            $('#prjBudgetTotal').text('Rp ' + sumBudget.toLocaleString('id-ID'));
            $('#prjUsedTotal').text('Rp ' + sumUsed.toLocaleString('id-ID'));
            $('#prjVarTotal')
                .text('Rp ' + sumVar.toLocaleString('id-ID'))
                .css('color', sumVar < 0 ? '#DC2626' : '#059669');

            // Tab User Akses
            $('#prjUserCount').text(d.access_users.length);
            var userHtml = '';
            d.access_users.forEach(function(u, i) {
                userHtml += '<tr><td class="text-center">' + (i+1) + '</td>' +
                    '<td>' + u.name + '</td>' +
                    '<td>' + u.email + '</td>' +
                    '<td><span class="badge badge-secondary px-2 py-1" style="font-size:9px;">' + u.role + '</span></td>' +
                    '<td>' + u.access_group + '</td></tr>';
            });
            $('#prjUserBody').html(userHtml || '<tr><td colspan="5" class="text-center text-muted py-3">Tidak ada user dengan akses ke PO ini.</td></tr>');
            $('#infoValue').html('<strong style="color:#2563EB;">' + d.value_formatted + '</strong>');
            $('#infoStatus').html('<span class="badge badge-' + (statusColors[d.status]||'secondary') + ' px-2 py-1">' + d.status_label + '</span>');
            $('#infoNotes').text(d.notes || '-');
            $('#infoSubmittedAt').text(d.submitted_at || '-');
            $('#infoApprovedBy').text(d.approved_by + (d.approved_at ? ' · ' + d.approved_at : ''));

            // BAST rows - show/hide
            if (d.no_bast) {
                $('#rowBast, .rowBastItem').show();
                $('#infoBastNo').text(d.no_bast);
                $('#infoBastTgl').text(d.tanggal_bast || '-');
                $('#infoBastNilai').html('<strong style="color:#2563EB;">' + d.nilai_bast_formatted + '</strong>');
                $('#infoBastNotes').text(d.bast_notes || '-');
                $('#infoBastBy').text(d.bast_submitted_by + (d.bast_submitted_at ? ' · ' + d.bast_submitted_at : ''));
            } else {
                $('#rowBast, .rowBastItem').hide();
            }

            if (d.status === 'CLOSED') {
                $('#rowClosed').show();
                $('#rowClosingNotes').show();
                $('#infoClosedBy').text(d.closed_by + (d.closed_at ? ' · ' + d.closed_at : ''));
                $('#infoClosingNotes').text(d.closing_notes || '-');
            } else {
                $('#rowClosed').hide();
                $('#rowClosingNotes').hide();
            }

            // Edit button
            var canEdit = (d.status === 'DRAFT' || d.status === 'SUBMITTED' || d.status === 'WAITING_PO');
            if (canEdit) {
                $('#prjEditBtn').attr('href', '/project/' + d.id + '/edit').show();
            } else {
                $('#prjEditBtn').hide();
            }

            // Tab Lampiran
            var totalAtt = 0;
            var attHtml = '';
            var attTypes = { general: 'Lampiran Umum', closing: 'MoM Closing' };
            var attBadge = { general: 'secondary', closing: 'dark' };
            Object.keys(d.attachments).forEach(function(type) {
                var items = d.attachments[type];
                if (!items || !items.length) return;
                totalAtt += items.length;
                attHtml += '<h6 class="font-weight-600 mb-2 mt-2"><span class="badge badge-' + (attBadge[type]||'secondary') + ' mr-1">' + (attTypes[type]||type) + '</span></h6>';
                attHtml += '<div class="table-responsive"><table class="table table-bordered mb-3" style="font-size:12px;"><tbody>';
                items.forEach(function(a, i) {
                    attHtml += '<tr><td class="text-center" width="5%">' + (i+1) + '</td><td>' + (a.title||a.path) + '</td><td width="10%" class="text-center"><a href="/storage/' + a.path + '" target="_blank" class="btn btn-info btn-sm"><i class="fas fa-eye"></i></a></td></tr>';
                });
                attHtml += '</tbody></table></div>';
            });
            $('#prjAttCount').text(totalAtt);
            $('#prjAttBody').html(attHtml || '<p class="text-muted py-3 mb-0">Belum ada lampiran.</p>');

            // Tab Log
            var logHtml = '';
            d.logs.forEach(function(l) {
                logHtml += '<div class="d-flex align-items-start mb-3" style="gap:10px;">' +
                    '<span class="badge badge-' + l.action_color + ' mt-1 px-2 py-1" style="font-size:10px;white-space:nowrap;">' + l.action_label + '</span>' +
                    '<div><div style="font-size:12px;font-weight:600;">' + l.actor + ' <span style="color:#9CA3AF;font-weight:400;">(' + (l.role||'system') + ')</span></div>' +
                    '<div style="font-size:11px;color:#6B7280;">' + l.date + '</div>' +
                    (l.notes ? '<div style="font-size:12px;margin-top:4px;padding:6px 10px;background:#F9FAFB;border-radius:6px;border:1px solid #E5E7EB;">' + l.notes + '</div>' : '') +
                    '</div></div>';
            });
            $('#prjLogBody').html(logHtml || '<p class="text-muted py-3 mb-0">Belum ada log.</p>');

            $('#prjDetailTabs a:first').tab('show');
            $('#projectDetailLoading').hide();
            $('#projectDetailContent').show();

        }).fail(function () {
            $('#projectDetailLoading').html('<p class="text-danger text-center py-4"><i class="fas fa-exclamation-triangle mr-1"></i>Gagal memuat data.</p>');
        });
    });

    $('#mdlDetailProject').on('hidden.bs.modal', function () {
        $('#projectDetailContent').hide();
        $('#projectDetailLoading').show();
    });

});
</script>
@endpush