@extends('layouts.app')

@section('title', 'Budgeting')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Budgeting</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item active">Budgeting</li>
            </ol>
        </div>

        <!-- SUMMARY WORKFLOW (clickable, filter tabel Budget Plan) -->
        @php
            $bpSteps = [
                ['Draft',     $summary['draft'],    'secondary', 'DRAFT'],
                ['Submitted', $summary['pending'],   'warning',   'SUBMITTED'],
                ['Checked',   $summary['checked'],   'info',      'CHECKED'],
                ['Verified',  $summary['verified'],  'primary',   'VERIFIED'],
                ['Active',    $summary['active'],    'success',   'ACTIVE'],
                ['Closing',   $summary['closing'],   'info',      'CLOSING'],
                ['Closed',    $summary['closed'],    'dark',      'CLOSED'],
                ['Rejected',  $summary['rejected'],  'danger',    'REJECTED'],
            ];
        @endphp
        <x-workflow-stepper :steps="$bpSteps" :compact="true" table="tblBudgetPlan" field="status" />

        <div class="card card-primary card-outline shadow-sm mt-3">

            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-money-check-alt mr-1"></i> Daftar Budget Plan</h3>
                <div class="ml-auto d-flex" style="gap:8px;">
                    <button id="btnRefreshBP" class="btn btn-secondary btn-sm">
                        <i class="fas fa-sync-alt mr-1"></i> Refresh
                    </button>
                </div>
            </div>

            <div class="card-body">

                <!-- FILTER -->
                <div class="custom-toolbar mb-3 d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
                    <div class="custom-toolbar-left d-flex align-items-center flex-wrap" style="gap:8px;">
                        <div class="toolbar-row">
                            <span class="toolbar-label">Cari :</span>
                            <div class="toolbar-input" id="bpSearch"></div>
                        </div>
                        <button aria-label="Clear Filter" type="button" id="btnClearFilterBP" class="btn btn-outline-secondary btn-sm" title="Clear Filter">
                            <i class="fas fa-eraser"></i>
                        </button>
                        <div id="bpRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>
                    </div>
                    <div class="custom-toolbar-right d-flex flex-wrap" style="gap:8px;">
                        <a href="{{ route('budget-plan.export') }}" id="btnExportBP" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-file-excel mr-1"></i> Export Excel
                        </a>
                        @if(Auth::user()->hasRole(['superadmin','manager']))
                        <a href="{{ url('/budget-plan/create') }}" class="btn btn-success btn-sm">
                            <i class="fas fa-plus mr-1"></i> Tambah Budget Plan
                        </a>
                        @endif
                    </div>
                </div>

                <!-- TABLE -->
                <div class="table-responsive">
                    <table id="tblBudgetPlan" class="table table-bordered mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" class="all text-nowrap" style="width:1%;">No</th>
                                <th scope="col" class="all text-nowrap" style="width:1%;">Code</th>
                                <th scope="col" class="all">Description</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;">Budget</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;">Used</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;">Var</th>
                                <th scope="col" class="text-center text-nowrap" style="width:1%;">Margin</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Status</th>
                                <th scope="col" class="text-center all action-column" style="width:1%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($budgetPlans as $bp)
                            @php

                                $bpActual = $bp->usedAmount();
                                $bpVar    = ($bp->amount ?? 0) - $bpActual;
                                $bpMargin = ($bp->amount ?? 0) > 0 ? round(($bpVar / $bp->amount) * 100, 1) : 0;
                            @endphp
                            @php
                                $bpPmInternal = strtoupper($bp->project?->pm_internal_name && $bp->project->pm_internal_name !== '-' ? $bp->project->pm_internal_name : '');
                            @endphp
                            <tr data-id="{{ $bp->id }}"
                                data-status="{{ $bp->status }}"
                                data-po="{{ $bp->project ? '[' . $bp->project->code . '] ' . $bp->project->name : '' }}"
                                data-customer="{{ $bp->project->customer->name ?? '' }}"
                                data-pminternal="{{ $bpPmInternal }}">
                                <td class="text-nowrap">{{ $loop->iteration }}</td>

                                <td class="text-nowrap">
                                    <button type="button" class="bp-code-link btn-detail-bp" data-id="{{ $bp->id }}" title="Detail">{{ $bp->code ?? '-' }}</button>
                                </td>

                                 <td>
                                    <span class="font-weight-bold">
                                        {{ $bp->description }}
                                    </span>
                                    <div style="font-size:11px;color:var(--text-faint);margin-top:3px;">
                                        @if($bp->isProjectBudget())
                                            <span class="badge badge-light border" style="font-size:9px;">{{ $bp->project->code ?? '-' }}</span>
                                            {{ Str::limit($bp->project->name ?? '-', 70) }}
                                        @elseif($bp->isCostCenterBudget())
                                            <span class="badge badge-light border" style="font-size:9px;">{{ $bp->costCenter->code ?? '-' }}</span>
                                            {{ Str::limit($bp->costCenter->name ?? '-', 70) }}
                                        @else
                                            <span class="text-danger">No source fund</span>
                                        @endif
                                        @if($bpPmInternal)
                                            &bull; {{ $bpPmInternal }}
                                        @endif
                                    </div>
                                </td>

                                <td class="text-right text-nowrap col-raw-amount" data-raw="{{ $bp->amount ?? 0 }}" data-order="{{ $bp->amount ?? 0 }}">
                                    <button type="button" class="bp-cell-link btn-detail-bp" data-id="{{ $bp->id }}" data-tab="#bpPaneDetails" title="Lihat Budget">{{ $bp->amount_formatted }}</button>
                                </td>

                                <td class="text-right text-nowrap col-raw-actual" data-raw="{{ $bpActual }}" data-order="{{ $bpActual }}">
                                    <button type="button" class="bp-cell-link btn-detail-bp" data-id="{{ $bp->id }}" data-tab="#bpPaneRequests" title="Lihat Cost">{{ number_format($bpActual, 0, ',', '.') }}</button>
                                </td>

                                <td class="text-right text-nowrap col-raw-var {{ $bpVar >= 0 ? 'text-var-pos' : 'text-var-neg' }}" data-raw="{{ $bpVar }}" data-order="{{ $bpVar }}">
                                    {{ $bpVar < 0 ? '-' : '' }}{{ number_format(abs($bpVar), 0, ',', '.') }}
                                </td>

                                <td class="text-center text-nowrap" data-order="{{ $bpMargin }}">
                                    <span class="badge badge-{{ $bpMargin <= 0 ? 'danger' : ($bpMargin <= 15 ? 'warning' : 'light border') }} px-2 py-1" style="font-size:11px;">
                                        {{ rtrim(rtrim(number_format($bpMargin, 1, ',', '.'), '0'), ',') }}%
                                    </span>
                                </td>

                                <td class="text-nowrap">
                                    <x-status :status="$bp->status" context="budget-plan"
                                              class="bp-status-link btn-detail-bp"
                                              :data-id="$bp->id" data-tab="#bpPaneLogs" />
                                </td>

                                <td class="text-center action-column">
                                    @php
                                        /*
                                          Gerbang peran disalin apa adanya dari versi sebelumnya kolom ini —
                                          tidak ada aturan yang ditambah, dikurangi, atau digabung. Yang
                                          berubah hanya susunannya: satu aksi utama yang bernama, sisanya
                                          (termasuk semua penolakan) masuk menu.
                                        */
                                        $pengguna = Auth::user();
                                        $stBp = $bp->status;

                                        $bolehDraft      = $stBp === 'DRAFT' && $pengguna->hasRole(['superadmin','manager']);
                                        $bolehHead       = $stBp === 'SUBMITTED' && $pengguna->hasRole(['superadmin','head']);
                                        $bolehFinance    = $stBp === 'CHECKED' && $pengguna->hasRole(['superadmin','finance','finance_manager']);
                                        $bolehCeo        = $stBp === 'VERIFIED' && $pengguna->hasRole(['superadmin','approver']);
                                        $bolehRevisi     = $stBp === 'REJECTED' && $pengguna->hasRole(['superadmin','manager']);
                                        $bolehClosing    = $stBp === 'ACTIVE' && $pengguna->hasRole(['superadmin','manager']);
                                        $bolehVerClosing = $stBp === 'CLOSING' && $pengguna->hasRole(['superadmin','finance','finance_manager']);

                                        $adaAksi = $bolehDraft || $bolehHead || $bolehFinance || $bolehCeo
                                            || $bolehRevisi || $bolehClosing || $bolehVerClosing;
                                    @endphp

                                    <div class="action-buttons ts-actions">

                                        {{-- AKSI UTAMA — satu per baris, selalu bernama --}}

                                        @if($bolehDraft)
                                        <form action="{{ url('/budget-plan/' . $bp->id . '/submit') }}"
                                              method="POST" class="frmSubmitBP d-inline">
                                            @csrf
                                            <button type="submit" class="ts-action-primary" title="Submit ke Finance">
                                                <i class="fas fa-paper-plane"></i><span class="ts-al">Submit</span>
                                            </button>
                                        </form>
                                        @elseif($bolehHead)
                                        <button type="button" class="ts-action-primary btn-approve-bp"
                                                data-id="{{ $bp->id }}" data-stage="head" title="Approve → Checked">
                                            <i class="fas fa-check"></i><span class="ts-al">Approve</span>
                                        </button>
                                        @elseif($bolehFinance)
                                        <button type="button" class="ts-action-primary btn-approve-bp"
                                                data-id="{{ $bp->id }}" data-stage="finance" title="Verify → Verified">
                                            <i class="fas fa-check"></i><span class="ts-al">Verify</span>
                                        </button>
                                        @elseif($bolehCeo)
                                        <button type="button" class="ts-action-primary btn-approve-bp"
                                                data-id="{{ $bp->id }}" data-stage="ceo" title="Approve → Active">
                                            <i class="fas fa-check"></i><span class="ts-al">Approve</span>
                                        </button>
                                        @elseif($bolehRevisi)
                                        <button type="button" class="ts-action-primary is-neutral btn-manager-revise-bp"
                                                data-id="{{ $bp->id }}" title="Kembalikan ke Draft untuk direvisi">
                                            <i class="fas fa-undo"></i><span class="ts-al">Revise</span>
                                        </button>
                                        @elseif($bolehClosing)
                                        <button type="button" class="ts-action-primary btn-submit-closing-bp"
                                                data-id="{{ $bp->id }}" data-desc="{{ $bp->description }}"
                                                title="Submit Closing">
                                            <i class="fas fa-flag-checkered"></i><span class="ts-al">Submit Closing</span>
                                        </button>
                                        @elseif($bolehVerClosing)
                                        <button type="button" class="ts-action-primary btn-verify-closing-bp"
                                                data-id="{{ $bp->id }}" title="Approve Closing → Closed">
                                            <i class="fas fa-check-double"></i><span class="ts-al">Approve Closing</span>
                                        </button>
                                        @endif

                                        {{-- Tanpa aksi, barisnya tetap punya jalan masuk ke detail --}}
                                        @unless($adaAksi)
                                        <button type="button" class="ts-action-primary is-neutral btn-detail-bp"
                                                data-id="{{ $bp->id }}"
                                                title="Lihat detail" aria-label="Lihat detail {{ $bp->code }}">
                                            <i class="fas fa-eye" aria-hidden="true"></i>
                                        </button>
                                        @endunless

                                        {{-- AKSI LAIN — termasuk semua aksi mundur/merusak --}}
                                        @if($adaAksi)
                                        <button type="button" class="ts-action-more"
                                                aria-haspopup="true" aria-expanded="false"
                                                title="Aksi lain" aria-label="Aksi lain untuk {{ $bp->code }}">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>

                                        <div class="ts-action-menu dropdown-menu" hidden>

                                            <button type="button" class="dropdown-item btn-detail-bp"
                                                    data-id="{{ $bp->id }}">
                                                <i class="fas fa-eye"></i>Detail
                                            </button>

                                            @if($bolehDraft)
                                            <a href="{{ url('/budget-plan/' . $bp->id . '/edit') }}" class="dropdown-item">
                                                <i class="fas fa-pen"></i>Edit
                                            </a>
                                            @endif

                                            @if($bolehHead)
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item is-danger btn-reject-bp"
                                                    data-id="{{ $bp->id }}" data-stage="head">
                                                <i class="fas fa-times"></i>Reject
                                            </button>
                                            @endif

                                            @if($bolehFinance)
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item is-danger btn-reject-bp"
                                                    data-id="{{ $bp->id }}" data-stage="finance">
                                                <i class="fas fa-times"></i>Reject
                                            </button>
                                            @endif

                                            @if($bolehCeo)
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item is-danger btn-reject-bp"
                                                    data-id="{{ $bp->id }}" data-stage="ceo">
                                                <i class="fas fa-times"></i>Reject
                                            </button>
                                            @endif

                                            @if($bolehVerClosing)
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item is-danger btn-reject-closing-bp"
                                                    data-id="{{ $bp->id }}">
                                                <i class="fas fa-times"></i>Tolak Closing
                                            </button>
                                            @endif

                                            @if($bolehDraft)
                                            <div class="dropdown-divider"></div>
                                            <form action="{{ url('/budget-plan/' . $bp->id) }}"
                                                  method="POST" class="frmDeleteBP">
                                                @csrf @method('DELETE')
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
                            {{-- Sengaja tidak ada baris di sini. Tabel ini dikelola DataTables dan
                                 baris kosong buatan sendiri akan dihitung sebagai baris data lalu
                                 selnya dikosongkan; keadaan kosongnya diisi language.emptyTable. --}}
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr style="background:var(--surface-2);">
                                <th colspan="3" class="text-right" style="font-size:12px;">Total (data tampil)</th>
                                <th class="text-right text-nowrap" id="bpFooterAmount" style="font-size:12px;color:var(--info);"></th>
                                <th class="text-right text-nowrap" id="bpFooterActual" style="font-size:12px;color:var(--success);"></th>
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
    <div class="modal-dialog modal-xl modal-dialog-xxl" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            {{-- Tidak ada pita header di sini: judul, kode, dan tombol tutup
                 pindah ke kepala panel ringkas gelap. Lihat modal detail Cost
                 Request untuk pola yang sama. --}}
            <div class="modal-body dm-body" style="padding:0;background:var(--surface-2);">
                <div id="bpDetailLoading" class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x" style="color:var(--accent);"></i>
                    <p class="text-muted mt-3 mb-0">Loading data...</p>
                </div>
                <div id="bpDetailContent" style="display:none;">
                    <div class="bp-modal-flex">

                        <!-- LEFT SIDEBAR -->
                        <div class="bp-sidebar dm-side">

                            <div class="dm-head">
                                <button type="button" class="close dm-close" data-dismiss="modal"
                                        aria-label="Tutup"><span aria-hidden="true">&times;</span></button>

                                <div id="bpCode" class="dm-code"></div>
                                <h5 class="dm-title" id="mdlBPTitle">Budget Plan Detail</h5>
                                <small id="mdlBPSubtitle" class="dm-sub-hidden"></small>

                                <div class="dm-badges">
                                    <span id="bpStatusBadge"></span>
                                </div>
                            </div>
                            <hr style="border-color:rgba(255,255,255,0.08);">
                            <div class="mb-3">
                                <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Source Fund</span>
                                <div id="bpProject" class="text-white mt-1" style="font-size:12px;font-weight:600;"></div>
                            </div>
                            <div class="mb-3" id="bpCustomerBox">
                                <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Customer</span>
                                <div id="bpCustomer" class="text-white mt-1" style="font-size:12px;"></div>
                            </div>
                            <div class="mb-3">
                                <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Total Budget</span>
                                <div id="bpAmount" style="color:var(--accent);font-size:18px;font-weight:700;margin-top:4px;"></div>
                            </div>
                            <div class="mb-3">
                                <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Used</span>
                                <div id="bpUsed" class="text-white mt-1" style="font-size:12px;"></div>
                                <div style="height:5px;background:rgba(255,255,255,0.1);border-radius:var(--radius-pill);margin-top:6px;overflow:hidden;">
                                    <div id="bpProgressBar" style="height:100%;background:var(--accent);border-radius:var(--radius-pill);transition:width .4s;width:0%;"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Variance</span>
                                <div class="d-flex align-items-center mt-1" style="gap:8px;">
                                    <div id="bpRemaining" class="text-white" style="font-size:12px;"></div>
                                    <span id="bpMarginBadge" class="badge px-2 py-1" style="font-size:10px;"></span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Input By</span>
                                <div id="bpInputBy" class="text-white mt-1" style="font-size:12px;"></div>
                            </div>
                            <div class="bp-sidebar-spacer"></div>
                            <hr style="border-color:rgba(255,255,255,0.08);margin:16px 0;">
                            <a id="bpEditBtn" href="#" class="btn btn-block"
                               style="background:var(--accent);color:#fff;font-weight:700;font-size:13px;border-radius:var(--radius-sm);display:none;">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </a>
                        </div>

                        <!-- RIGHT TABS -->
                        <div class="bp-main-panel">
                            <ul class="nav nav-tabs mb-3" id="bpDetailTabs" style="border-bottom:2px solid var(--border);">
                                <li class="nav-item">
                                    <a class="nav-link active" data-toggle="tab" href="#bpPaneInfo" style="font-size:13px;font-weight:600;">
                                        <i class="fas fa-clipboard-list mr-1"></i> Summary
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#bpPaneDetails" style="font-size:13px;font-weight:600;">
                                        <i class="fas fa-list-ul mr-1"></i> Budget
                                        <span id="bpDetailCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#bpPaneRequests" style="font-size:13px;font-weight:600;">
                                        <i class="fas fa-hand-holding-usd mr-1"></i> Cost
                                        <span id="bpRequestCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#bpPaneBeneficiary" style="font-size:13px;font-weight:600;">
                                        <i class="fas fa-users mr-1"></i> Beneficiary
                                        <span id="bpBeneficiaryCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#bpPaneAttachments" style="font-size:13px;font-weight:600;">
                                        <i class="fas fa-paperclip mr-1"></i> Attachment
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

                                <!-- TAB SUMMARY -->
                                <div class="tab-pane fade show active" id="bpPaneInfo">
                                    <div class="bp-summary-cards mb-3">
                                        <div class="bp-groupcard">
                                            <div class="bp-groupcard-title">Cost Request per Status</div>
                                            <div id="bpSumStatus"></div>
                                        </div>
                                        <div class="bp-groupcard">
                                            <div class="bp-groupcard-title">Cost Request per Tipe</div>
                                            <div id="bpSumType"></div>
                                        </div>
                                        <div class="bp-groupcard">
                                            <div class="bp-groupcard-title">Budget</div>
                                            <div id="bpSumBudget"></div>
                                        </div>
                                    </div>

                                    {{-- Satu informasi satu baris (.ts-deflist), sama dengan
                                         modal Purchase Order. Pola kotak-per-informasi yang lama
                                         membuat wadahnya lebih menonjol daripada isinya. --}}
                                    <div class="ts-deflist mb-3">
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Reference</span>
                                            <span id="infoCodeRef" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Description</span>
                                            <span id="infoDesc" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Source Fund</span>
                                            <span id="infoPO" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Manager</span>
                                            <span id="infoManager" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Total Budget</span>
                                            <span id="infoAmount" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Approved By</span>
                                            <span id="infoApprovedBy" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Approved At</span>
                                            <span id="infoApprovedAt" class="ts-deflist-value"></span>
                                        </div>
                                    </div>

                                    <button type="button" class="btn btn-sm btn-link p-0" id="bpNotesToggle" style="font-size:12px;color:var(--text-faint);text-decoration:none;">
                                        <i class="fas fa-chevron-right mr-1" id="bpNotesToggleIcon" style="font-size:10px;transition:transform .15s;display:inline-block;"></i> Notes &amp; Approver Notes
                                    </button>
                                    <div id="bpNotesCollapse" style="display:none;" class="mt-2">
                                        <div class="ts-deflist">
                                            <div class="ts-deflist-row">
                                                <span class="ts-deflist-label">Notes</span>
                                                <span id="infoNotes" class="ts-deflist-value" style="white-space:pre-line;"></span>
                                            </div>
                                            <div class="ts-deflist-row">
                                                <span class="ts-deflist-label">Approver Notes</span>
                                                <span id="infoApproverNotes" class="ts-deflist-value" style="white-space:pre-line;"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end mt-3">
                                        <a id="bpExportReportBtn" href="#" class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class="fas fa-file-excel mr-1"></i> Export Report
                                        </a>
                                    </div>
                                </div>

                                <!-- TAB BUDGET — Account | Price | Qty | Unit | Total | Used | Variance | Notes -->
                                <div class="tab-pane fade" id="bpPaneDetails">
                                    <div class="d-flex justify-content-end mb-2">
                                        <button type="button" class="btn btn-xs btn-outline-secondary" id="btnExpandAllBpDetail"><i class="fas fa-angle-double-down mr-1"></i>Expand All</button>&nbsp;
                                        <button type="button" class="btn btn-xs btn-outline-secondary" id="btnCollapseAllBpDetail"><i class="fas fa-angle-double-up mr-1"></i>Collapse All</button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0" style="font-size:12px;">
                                            <thead style="background:var(--surface-2);">
                                                <tr>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;">Chart of Account</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:14%;">Unit Price</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:center;width:10%;">Qty</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:14%;">Budget</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:14%;">Used</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:14%;">Variance</th>
                                                </tr>
                                            </thead>
                                            <tbody id="bpDetailBody"></tbody>
                                            <tfoot>
                                                <tr style="background:var(--surface-2);">
                                                    <th colspan="3" class="text-right" style="font-size:12px;">Total</th>
                                                    <th class="text-right" id="bpDetailTotal" style="font-size:12px;color:var(--info);"></th>
                                                    <th class="text-right" id="bpActualTotal" style="font-size:12px;color:var(--success);"></th>
                                                    <th class="text-right" id="bpGapTotal" style="font-size:12px;"></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>

                                <!-- TAB COST -->
                                <div class="tab-pane fade" id="bpPaneRequests">
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0" style="font-size:12px;">
                                            <thead style="background:var(--surface-2);">
                                                <tr>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;width:1%;"></th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;">Description</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:1%;white-space:nowrap;">Request</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:1%;white-space:nowrap;">Realization</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:1%;white-space:nowrap;">Refund</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;width:1%;white-space:nowrap;">Date</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;width:1%;white-space:nowrap;">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody id="bpRequestBody"></tbody>
                                            <tfoot>
                                                <tr style="background:var(--surface-2);">
                                                    <th></th>
                                                    <th class="text-right">Total</th>
                                                    <th class="text-right" id="bpRequestTotal" style="color:var(--info);"></th>
                                                    <th class="text-right" id="bpRealizationTotal" style="color:var(--success);"></th>
                                                    <th class="text-right" id="bpRefundTotal" style="color:var(--warning);"></th>
                                                    <th colspan="2"></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>

                                <!-- TAB BENEFICIARY -->
                                <div class="tab-pane fade" id="bpPaneBeneficiary">
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0" style="font-size:12px;">
                                            <thead style="background:var(--surface-2);">
                                                <tr>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;">Beneficiary</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;">Request</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;">Realization</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;">Refund</th>
                                                </tr>
                                            </thead>
                                            <tbody id="bpBeneficiaryBody"></tbody>
                                            <tfoot>
                                                <tr style="background:var(--surface-2);">
                                                    <th class="text-right">Total</th>
                                                    <th class="text-right" id="bpBenReqTotal" style="color:var(--info);"></th>
                                                    <th class="text-right" id="bpBenRealTotal" style="color:var(--success);"></th>
                                                    <th class="text-right" id="bpBenRefundTotal" style="color:var(--warning);"></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>

                                <!-- TAB ATTACHMENT -->
                                <div class="tab-pane fade" id="bpPaneAttachments">
                                    <div id="bpAttBody"></div>
                                </div>

                                <!-- TAB LOG -->
                                <div class="tab-pane fade" id="bpPaneLogs">
                                    <div id="bpLogBody" class="bp-chat"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- MODAL APPROVE --}}
<div class="modal fade" id="mdlApproveBP" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white" id="mdlApproveTitle">
                    <i class="fas fa-check mr-2" style="color:var(--accent);"></i> Konfirmasi
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
                <div class="modal-footer" style="background:var(--surface-2);">
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
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white"><i class="fas fa-times mr-2" style="color:var(--accent);"></i> Tolak / Kembalikan</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmRejectBP" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Alasan / Catatan <span class="text-danger">*</span></label>
                        <textarea name="notes" class="form-control" rows="4"
                                  placeholder="Tuliskan alasan atau catatan revisi..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times mr-1"></i> Tolak</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL MANAGER REVISE (REJECTED → DRAFT) --}}
<div class="modal fade" id="mdlManagerReviseBP" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-undo mr-2" style="color:var(--accent);"></i> Revisi Budget Plan
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmManagerReviseBP" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <p class="text-muted mb-3">Kembalikan Budget Plan ini ke <strong>Draft</strong> untuk diperbaiki dan disubmit ulang?</p>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Catatan Revisi <span class="text-danger">*</span></label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Tuliskan catatan revisi..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-undo mr-1"></i> Kembalikan ke Draft</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL SUBMIT CLOSING --}}
<div class="modal fade" id="mdlSubmitClosingBP" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-flag-checkered mr-2" style="color:var(--accent);"></i> Submit Closing Budget Plan
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
                <div class="modal-footer" style="background:var(--surface-2);">
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
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-check-double mr-2" style="color:var(--accent);"></i> Verifikasi Closing
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
                <div class="modal-footer" style="background:var(--surface-2);">
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
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-times mr-2" style="color:var(--accent);"></i> Tolak Closing
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
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times mr-1"></i> Tolak Closing
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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

@push('styles')
<style>
.text-var-pos { color:var(--success); }
.text-var-neg { color:var(--danger); }

/* ── Gaya index Purchase Order: baris bersih + link kode/status biru-tua ── */
#tblBudgetPlan tbody tr,
#tblBudgetPlan tbody tr:hover { background-color:var(--surface) !important; }
#tblBudgetPlan tbody td { background-color:var(--surface); }
.bp-code-link, .bp-cell-link {
    display:inline-block; padding:2px 8px; border:0; border-radius:var(--radius-sm); background:transparent;
    font-family:inherit; font-weight:700; font-size:13px; line-height:1.4;
    color:var(--text); cursor:pointer; transition:background-color .12s ease, color .12s ease;
}
.bp-code-link { font-size:12px; letter-spacing:.03em; }
.bp-code-link:hover, .bp-code-link:focus,
.bp-cell-link:hover, .bp-cell-link:focus { background:var(--charcoal); color:#fff; outline:none; }
.bp-status-link { cursor:pointer; }
.bp-status-link:hover { filter:brightness(.92); }

/* Modal detail dibuat ukuran paling besar supaya banyak informasi
   (Informasi/Detail/Lampiran/Log) muat tanpa perlu pindah page — tapi tetap
   disisakan sedikit padding ke tepi layar supaya tidak terasa full-screen. */
.modal-dialog-xxl {
    max-width: calc(100vw - 32px);
    width: calc(100vw - 32px);
    height: calc(100vh - 32px);
    margin: 16px auto;
}
/* modal-content dijadikan flex-column dengan tinggi PASTI (bukan cuma
   max-height) supaya modal-body punya tinggi nyata yang bisa diwarisi via
   height:100% oleh sidebar — ini penting agar sidebar gelap selalu mencapai
   dasar modal konsisten di semua level zoom browser, bukan hanya mengikuti
   tinggi konten (yang bisa lebih pendek saat browser di-zoom out). */
.modal-dialog-xxl .modal-content {
    height: calc(100vh - 32px);
    max-height: calc(100vh - 32px);
    display: flex;
    flex-direction: column;
}
.modal-dialog-xxl .modal-header,
.modal-dialog-xxl .modal-footer { flex: 0 0 auto; }
.modal-dialog-xxl .modal-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
}
.modal-dialog-xxl #bpDetailContent { height: 100%; }

/* Sidebar & main panel sebagai flex row — sidebar diberi lebar tetap (tidak
   lagi terlalu lebar/mengambang) dan stretch mengikuti tinggi konten kanan,
   bukan tinggi tetap yang menyisakan ruang kosong. */
.bp-modal-flex { display:flex; align-items:stretch; min-height:100%; }
.bp-sidebar {
    width:280px; flex:0 0 280px; background:var(--charcoal); padding:28px 20px;
    display:flex; flex-direction:column;
}
.bp-sidebar-spacer { flex:1 1 auto; }
.bp-main-panel { flex:1 1 auto; padding:24px; min-width:0; }
@media (max-width:767.98px) {
    .bp-modal-flex { flex-direction:column; }
    .bp-sidebar { width:100%; flex:0 0 auto; }
}
#bpDetailBody tr.bp-group-row,
#bpDetailBody tr.bp-group-row td { background:var(--info-bg) !important; }
#bpDetailBody tr.bp-group-row:hover,
#bpDetailBody tr.bp-group-row:hover td { background:var(--surface-3) !important; }
.bp-caret.rotated { transform:rotate(90deg); }
#bpDetailBody td.bp-indent { padding-left:48px !important; }

/* TAB INFORMATION — clean grid layout */
.bp-info-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px 18px; }
.bp-info-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px 18px; }
.bp-info-grid-ref { display:grid; grid-template-columns:320px 1fr; gap:14px 18px; }
.bp-info-grid-ref .bp-info-value { white-space:nowrap; }
@media (max-width:576px) { .bp-info-grid-3 { grid-template-columns:1fr; } .bp-info-grid-ref { grid-template-columns:1fr; } }
#bpNotesToggleIcon.rotated { transform:rotate(90deg); }
.bp-info-span2 { grid-column: span 2; }
.bp-info-item { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-md); padding:12px 16px; }
.bp-info-label { display:block; font-size:10px; text-transform:uppercase; font-weight:700; color:var(--text-faint); letter-spacing:.03em; margin-bottom:5px; }
.bp-info-value { font-size:13px; color:var(--text); }
.bp-info-highlight { background:var(--warning-bg); border-color:var(--border-strong); }
.bp-info-highlight .bp-info-value { font-weight:700; color:var(--warning); }
@media (max-width:576px) {
    .bp-info-grid { grid-template-columns:1fr; }
    .bp-info-span2 { grid-column: span 1; }
}

/* TAB REQUEST — expand/collapse per CR, mirip pola pivot lain di app ini */
#bpRequestBody tr.bp-req-row { cursor:pointer; background:var(--info-bg) !important; }
#bpRequestBody tr.bp-req-row:hover { background:var(--surface-3) !important; }
#bpRequestBody tr.bp-req-row td { background:transparent !important; }
.bp-req-caret { transition:transform .15s ease; font-size:11px; color:var(--text-faint); }
.bp-req-caret.rotated { transform:rotate(90deg); }
#bpRequestBody td.bp-req-indent { padding-left:32px !important; }

/* ── TAB SUMMARY — kartu ringkasan ── */
.bp-summary-cards { display:grid; grid-template-columns:repeat(auto-fill, minmax(230px, 1fr)); gap:10px; align-items:start; }
.bp-groupcard { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:12px 14px; }
.bp-groupcard-title { font-size:10px; text-transform:uppercase; letter-spacing:.04em; font-weight:800; color:var(--text-faint); margin-bottom:8px; }
.bp-gc-row { display:flex; justify-content:space-between; gap:12px; font-size:12px; padding:3px 0; align-items:baseline; }
.bp-gc-row > span:first-child { color:var(--text-faint); }
.bp-gc-row > span:last-child { font-weight:700; white-space:nowrap; text-align:right; }
.bp-gc-row.bold { border-top:1px solid var(--border); margin-top:4px; padding-top:6px; }
.bp-gc-row.bold > span:first-child { color:var(--text); font-weight:700; }

/* ── TAB LOG — chat bubbles ── */
.bp-chat { display:flex; flex-direction:column; gap:14px; padding:4px 2px; }
.bp-chat-row { display:flex; gap:10px; max-width:78%; }
.bp-chat-row.mine { align-self:flex-end; flex-direction:row-reverse; text-align:right; }
.bp-chat-av { width:30px; height:30px; border-radius:50%; background:var(--info-bg); color:var(--text-dim); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; flex-shrink:0; }
.bp-chat-row.mine .bp-chat-av { background:var(--charcoal); color:#fff; }
.bp-chat-bubble { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:8px 12px; font-size:12px; }
.bp-chat-row.mine .bp-chat-bubble { border-color:var(--border); }
.bp-chat-meta { font-size:10px; color:var(--text-faint); margin-bottom:4px; }
.bp-chat-note { margin-top:6px; font-size:11.5px; line-height:1.45; white-space:pre-line; color:var(--text); }
.custom-toolbar      { display:flex; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; flex-direction:row; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; font-weight:600; color:var(--text-dim); white-space:nowrap; flex-shrink:0; margin-bottom:0; }
.toolbar-input       { min-width:180px; }
#bpSearch input      { width:180px; border-radius:var(--radius-sm); }
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
.ts-control          { border-radius:var(--radius-sm) !important; border:1px solid var(--border) !important; min-height:38px !important; padding:6px 10px !important; box-shadow:none !important; }
#tblBudgetPlan       { width:100% !important; }
.table td,.table th  { vertical-align:middle !important; }
.action-column       { white-space:nowrap !important; width:1% !important; }
.action-buttons      { display:flex; align-items:center; justify-content:center; gap:4px; flex-wrap:nowrap; }
.font-weight-600     { font-weight:600; }
.dataTables_wrapper .row:last-child { margin-top:18px; align-items:center; }
.dataTables_info { padding-top:0 !important; font-size:14px; color:var(--text-faint); }
.dataTables_length { display:flex; align-items:center; margin-left:18px; }
.dataTables_length label { display:flex; align-items:center; gap:10px; margin-bottom:0; font-size:14px; font-weight:600; color:var(--text-faint); }
.dataTables_length select { appearance:none !important; background-color:var(--surface) !important; color:var(--text-dim) !important; border:1px solid var(--border) !important; border-radius:var(--radius-sm) !important; padding:4px 30px 4px 10px !important; height:36px !important; min-width:72px !important; font-size:14px !important; font-weight:600 !important; cursor:pointer; box-shadow:none !important; }
.dataTables_paginate .paginate_button { border-radius:var(--radius-sm) !important; margin:0 2px; }
.dataTables_paginate .paginate_button.current { background:var(--accent) !important; background-image:none !important; border-color:var(--accent-dark) !important; color:#fff !important; font-weight:700 !important; }
#bpDetailTabs .nav-link { color:var(--text-faint); border:none; border-bottom:2px solid transparent; border-radius:0; padding:8px 16px; }
#bpDetailTabs .nav-link.active { color:var(--accent) !important; border-bottom-color:var(--accent) !important; background:transparent !important; font-weight:700; }
@media (max-width:576px) {
    .custom-toolbar { flex-direction:column !important; align-items:stretch !important; }
    .custom-toolbar-left { flex-direction:column !important; align-items:stretch !important; width:100%; }
    .custom-toolbar-right { width:100%; }
    .custom-toolbar-right .btn { width:100%; }
    #btnClearFilterBP { width:100%; }
    #bpRowFilters { width:100%; }
    #bpRowFilters .tcf-row-btn { width:100%; }
    .toolbar-row         { width:100%; }
    .toolbar-input       { flex:1; min-width:0; }
    #bpSearch input      { width:100%; }
    .ts-wrapper          { width:100% !important; min-width:0 !important; }
    .card-header { flex-direction:column; align-items:stretch !important; gap:10px; }
    .card-header .card-title { margin-bottom:0; }
    .card-header .ml-auto { margin-left:0 !important; flex-wrap:wrap; }
    .card-header .ml-auto .btn { flex:1 1 auto; }
    #bpRowFilters .tcf-row-btn { flex:1 1 auto; }
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
    color: var(--text) !important;
    background: var(--surface-2) !important;
    padding: 8px 12px !important;
    border-top: 1px solid var(--border);
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

/* ── IMAGE VIEWER (carousel + zoom, ala WhatsApp) ── */
#imgViewerOverlay {
    position:fixed; inset:0; background:rgba(0,0,0,.92); z-index:2000;
    display:none; align-items:center; justify-content:center; flex-direction:column;
}
#imgViewerOverlay.show { display:flex; }
#imgViewerStage { position:relative; width:100%; height:100%; display:flex; align-items:center; justify-content:center; overflow:hidden; }
#imgViewerImg {
    max-width:90vw; max-height:82vh; object-fit:contain; cursor:zoom-in;
    transition:transform .15s ease; transform-origin:center center; user-select:none; -webkit-user-drag:none;
}
#imgViewerImg.zoomed { cursor:grab; }
#imgViewerImg.dragging { cursor:grabbing; transition:none; }
#imgViewerClose {
    position:absolute; top:16px; right:20px; z-index:10; background:rgba(255,255,255,.12); border:none; color:#fff;
    width:38px; height:38px; border-radius:50%; font-size:20px; display:flex; align-items:center; justify-content:center; cursor:pointer;
}
#imgViewerClose:hover { background:rgba(255,255,255,.25); }
.imgViewerNav {
    position:absolute; top:50%; transform:translateY(-50%); background:rgba(255,255,255,.12); border:none; color:#fff;
    width:44px; height:44px; border-radius:50%; font-size:20px; display:flex; align-items:center; justify-content:center; cursor:pointer;
}
.imgViewerNav:hover { background:rgba(255,255,255,.25); }
#imgViewerPrev { left:16px; }
#imgViewerNext { right:16px; }
#imgViewerCounter { position:absolute; bottom:16px; left:50%; transform:translateX(-50%); color:#fff; font-size:12px; background:rgba(255,255,255,.12); padding:4px 12px; border-radius:var(--radius-lg); }
#imgViewerName { position:absolute; top:20px; left:20px; z-index:10; color:#fff; font-size:12px; opacity:.85; max-width:60vw; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
@media (max-width:576px) {
    .imgViewerNav { width:36px; height:36px; font-size:16px; }
    #imgViewerPrev { left:6px; } #imgViewerNext { right:6px; }
}
</style>
@endpush

@push('scripts')
<script>
var bpCurrentUserId = parseInt('{{ (int) Auth::id() }}', 10) || 0;
$(function () {

    /* Detail BP: expand/collapse grup per Chart of Account (pivot-style).
       Expand/Collapse di level ini CUMA buka/tutup baris sub-item — TIDAK ikut
       membuka dropdown CR di dalamnya (dikendalikan terpisah lewat tombol
       Expand/Collapse CR per grup, atau toggle "N CR" per sub-item). */
    function resetBpGroupCrState($groupRows) {
        $groupRows.find('.bp-cr-toggle i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
    }
    $(document).on('click', '#bpDetailBody .bp-group-row', function () {
        var target = $(this).data('target');
        var $subRows = $('#bpDetailBody .' + target);
        var expanding = $subRows.first().is(':hidden');
        if (expanding) {
            $subRows.show();
            $(this).find('.bp-crctrl').show();
        } else {
            $subRows.hide();
            $('#bpDetailBody .' + target + '-crall').hide();
            $(this).find('.bp-crctrl').hide();
            resetBpGroupCrState($subRows);
        }
        $(this).find('.bp-caret').toggleClass('rotated', expanding);
    });
    $(document).on('click', '#btnExpandAllBpDetail', function () {
        $('#bpDetailBody .bp-group-row').each(function () {
            var target = $(this).data('target');
            $('#bpDetailBody .' + target).show();
            $(this).find('.bp-crctrl').show();
            $(this).find('.bp-caret').addClass('rotated');
        });
    });
    $(document).on('click', '#btnCollapseAllBpDetail', function () {
        $('#bpDetailBody .bp-group-row').each(function () {
            var target = $(this).data('target');
            $('#bpDetailBody .' + target).hide();
            $('#bpDetailBody .' + target + '-crall').hide();
            $(this).find('.bp-crctrl').hide();
            $(this).find('.bp-caret').removeClass('rotated');
        });
        resetBpGroupCrState($('#bpDetailBody'));
    });
    $(document).on('click', '.bp-cr-expand-all', function (e) {
        e.stopPropagation();
        var grp = $(this).data('group');
        $('#bpDetailBody .' + grp + '-crall').show();
        $('#bpDetailBody .' + grp + ' .bp-cr-toggle i').removeClass('fa-chevron-down').addClass('fa-chevron-up');
    });
    $(document).on('click', '.bp-cr-collapse-all', function (e) {
        e.stopPropagation();
        var grp = $(this).data('group');
        $('#bpDetailBody .' + grp + '-crall').hide();
        $('#bpDetailBody .' + grp + ' .bp-cr-toggle i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
    });
    $(document).on('click', '.bp-cr-toggle', function (e) {
        e.stopPropagation();
        var target = $(this).data('target');
        $('#bpDetailBody .' + target).toggle();
        $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
    });

    /* Tab Information: Notes & Approver Notes disembunyikan default (jarang diisi) */
    $(document).on('click', '#bpNotesToggle', function () {
        $('#bpNotesCollapse').slideToggle(150);
        $('#bpNotesToggleIcon').toggleClass('rotated');
    });

    /* Request BP: expand/collapse per Cost Request untuk lihat rincian sub-item */
    $(document).on('click', '#bpRequestBody .bp-req-row', function () {
        var target = $(this).data('target');
        $(this).find('.bp-req-caret').toggleClass('rotated');
        $('#bpRequestBody .' + target).toggle();
    });

    /* ── IMAGE VIEWER — carousel + zoom (ala WhatsApp), untuk tab Attachment ── */
    (function () {
        var overlay  = document.getElementById('imgViewerOverlay');
        var stageImg = document.getElementById('imgViewerImg');
        var counter  = document.getElementById('imgViewerCounter');
        var images   = [];
        var idx      = 0;
        var zoomed   = false;
        var isDragging = false, dragStartX = 0, dragStartY = 0, imgX = 0, imgY = 0;
        var justSwiped = false;   // menandai klik yang sebenarnya gerakan geser

        function renderViewer() {
            var item = images[idx];
            if (!item) return;
            resetZoom();
            stageImg.src = item.full;
            $('#imgViewerName').text(item.name || '');
            counter.textContent = (idx + 1) + ' / ' + images.length;
            /* Panah hanya ada kalau memang ada tujuannya: tidak tampil untuk
               lampiran tunggal, tidak ada panah kiri di lampiran pertama dan
               tidak ada panah kanan di lampiran terakhir. */
            var banyak = images.length > 1;
            $('#imgViewerPrev').css('display', (banyak && idx > 0) ? 'flex' : 'none');
            $('#imgViewerNext').css('display', (banyak && idx < images.length - 1) ? 'flex' : 'none');
        }

        function resetZoom() {
            zoomed = false;
            imgX = 0; imgY = 0;
            stageImg.classList.remove('zoomed', 'dragging');
            stageImg.style.transform = 'translate(0px, 0px) scale(1)';
            stageImg.style.cursor = 'zoom-in';
        }

        function applyTransform() {
            stageImg.style.transform = 'translate(' + imgX + 'px, ' + imgY + 'px) scale(' + (zoomed ? 2.4 : 1) + ')';
        }

        function openViewer(groupImages, startIndex) {
            images = groupImages;
            idx = startIndex;
            renderViewer();
            overlay.classList.add('show');
            $('body').css('overflow', 'hidden');
        }

        function closeViewer() {
            overlay.classList.remove('show');
            $('body').css('overflow', '');
        }

        function showNext() { if (idx < images.length - 1) { idx++; renderViewer(); } }
        function showPrev() { if (idx > 0) { idx--; renderViewer(); } }

        // Trigger: tombol View gambar pada tabel Attachment
        $(document).on('click', '.bp-att-view-img', function () {
            var $btns = $('#bpAttBody .bp-att-view-img');
            var groupImages = $btns.map(function () {
                return { full: $(this).data('full'), name: $(this).data('name') };
            }).get();
            var startIndex = $btns.index(this);
            openViewer(groupImages, startIndex < 0 ? 0 : startIndex);
        });

        $('#imgViewerClose').on('click', closeViewer);
        $('#imgViewerNext').on('click', showNext);
        $('#imgViewerPrev').on('click', showPrev);
        $(overlay).on('click', function (e) {
            // Klik di area kosong menutup viewer — kecuali kalau klik itu
            // ujung dari gerakan geser antar lampiran.
            if (justSwiped) { justSwiped = false; return; }
            if (e.target === overlay) closeViewer();
        });

        $(document).on('keydown', function (e) {
            if (!overlay.classList.contains('show')) return;
            if (e.key === 'Escape') closeViewer();
            else if (e.key === 'ArrowRight') showNext();
            else if (e.key === 'ArrowLeft') showPrev();
        });

        stageImg.addEventListener('dblclick', function () {
            zoomed = !zoomed;
            if (!zoomed) { imgX = 0; imgY = 0; }
            stageImg.classList.toggle('zoomed', zoomed);
            stageImg.style.cursor = zoomed ? 'grab' : 'zoom-in';
            applyTransform();
        });
        stageImg.addEventListener('wheel', function (e) {
            e.preventDefault();
            zoomed = e.deltaY < 0 ? true : false;
            if (!zoomed) { imgX = 0; imgY = 0; }
            stageImg.classList.toggle('zoomed', zoomed);
            stageImg.style.cursor = zoomed ? 'grab' : 'zoom-in';
            applyTransform();
        }, { passive: false });

        stageImg.addEventListener('mousedown', function (e) {
            if (!zoomed) return;
            isDragging = true;
            dragStartX = e.clientX - imgX;
            dragStartY = e.clientY - imgY;
            stageImg.classList.add('dragging');
        });
        document.addEventListener('mousemove', function (e) {
            if (!isDragging) return;
            imgX = e.clientX - dragStartX;
            imgY = e.clientY - dragStartY;
            applyTransform();
        });
        document.addEventListener('mouseup', function () {
            isDragging = false;
            stageImg.classList.remove('dragging');
        });

        /* ── GESER KIRI/KANAN untuk pindah lampiran ─────────────────────────
           Saat gambar TIDAK di-zoom, tarikan mendatar (mouse maupun sentuh)
           berpindah ke lampiran sebelum/sesudahnya — pola yang sama dengan
           galeri ponsel. Saat di-zoom, tarikan tetap menggeser gambar
           (pan), jadi keduanya tidak saling rebutan. */
        var SWIPE_MIN = 50;          // px, biar klik/getar kecil tidak terbaca geser
        var swipeX = null, swipeY = null;

        function swipeStart(x, y) { if (!zoomed) { swipeX = x; swipeY = y; } }

        function swipeEnd(x, y) {
            if (swipeX === null || images.length < 2) { swipeX = swipeY = null; return; }
            var dx = x - swipeX, dy = y - swipeY;
            swipeX = swipeY = null;
            if (Math.abs(dx) < SWIPE_MIN || Math.abs(dx) <= Math.abs(dy)) return;
            if (dx < 0 && idx >= images.length - 1) return;   // sudah di lampiran terakhir
            if (dx > 0 && idx <= 0) return;                   // sudah di lampiran pertama
            justSwiped = true;
            if (dx < 0) showNext(); else showPrev();
        }

        stageImg.addEventListener('mousedown', function (e) { swipeStart(e.clientX, e.clientY); });
        document.addEventListener('mouseup', function (e) {
            if (!overlay.classList.contains('show')) { swipeX = swipeY = null; return; }
            swipeEnd(e.clientX, e.clientY);
        });

        stageImg.addEventListener('touchstart', function (e) {
            var t = e.changedTouches[0];
            swipeStart(t.clientX, t.clientY);
        }, { passive: true });
        stageImg.addEventListener('touchend', function (e) {
            var t = e.changedTouches[0];
            swipeEnd(t.clientX, t.clientY);
        }, { passive: true });

        /* Geser juga boleh lewat area kosong di kiri/kanan gambar. */
        overlay.addEventListener('mousedown', function (e) {
            if (e.target === stageImg) return;
            swipeStart(e.clientX, e.clientY);
        });
    })();

    /* DATATABLE */
    var table = $('#tblBudgetPlan').DataTable({
        responsive: { details: { type: 'inline' } },
        autoWidth: false, pageLength: 10, lengthChange: true, searching: true,
        order: [], // pertahankan urutan asli dari server (input terbaru di atas)
        columnDefs: [
            { targets: [0], visible: false, orderable: false },
            { targets: [5], visible: false },
            { responsivePriority: 1,   targets: [0,1,2,-1] },
            { responsivePriority: 2,   targets: [3,4,5,6,7] },
        ],
        dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex justify-content-end"lp>>',
        language: {
            lengthMenu: 'Page Size _MENU_',
            /* emptyTable = belum ada data sama sekali; zeroRecords = ada data tapi
               tidak ada yang cocok dengan pencarian/filter. Dua keadaan berbeda. */
            emptyTable: '<div class="ts-empty ts-empty-compact">'
                + '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-folder-open"></i></span>'
                + '<p class="ts-empty-title">Belum ada Budget Plan</p>'
                + '<p class="ts-empty-msg">Belum ada Budget Plan pada rentang tanggal yang dipilih.</p></div>',
            zeroRecords: '<div class="ts-empty ts-empty-compact">'
                + '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-search"></i></span>'
                + '<p class="ts-empty-title">Tidak ada yang cocok</p>'
                + '<p class="ts-empty-msg">Tidak ada Budget Plan yang cocok dengan pencarian atau filter yang sedang aktif.</p></div>',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            paginate: { previous: 'Previous', next: 'Next' }
        }
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
        return sign + Math.abs(Math.round(v)).toLocaleString('id-ID');
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
            '<span style="color:' + (totalVar >= 0 ? 'var(--success)' : 'var(--danger)') + ';">' + moneyFmt(totalVar) + '</span>'
        );
    }

    table.on('draw', recalcFooter);
    recalcFooter();

    TesysColumnFilter.initRowFilters('#tblBudgetPlan', '#bpRowFilters', [
        { key: 'po',         label: 'PO' },
        { key: 'customer',   label: 'Customer' },
        { key: 'pminternal', label: 'Manager' },
    ]);

    /* Export Excel mengikuti filter yang SEDANG aktif di tabel — lihat
       public/js/tesys-export-filtered.js untuk alasan & perilaku fallback-nya. */
    if (window.TesysExportFiltered) {
        TesysExportFiltered.wire('#btnExportBP', table, '{{ route('budget-plan.export') }}');
    }

    $('#btnRefreshBP').on('click', function () { location.reload(); });

    $('#btnClearFilterBP').on('click', function () {
        TesysColumnFilter.clearAll('#tblBudgetPlan');
        TesysWorkflowStepper.clear('tblBudgetPlan');
    });

    /* SUBMIT CONFIRM */
    $(document).on('submit', '.frmSubmitBP', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Submit Budget Plan?', text:'Budget Plan akan dikirim ke Finance untuk diverifikasi.', icon:'question', showCancelButton:true, confirmButtonColor:WARNA_UTAMA, cancelButtonColor:WARNA_BATAL, confirmButtonText:'Ya, Submit', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) { sibukkan(form); form.submit(); } });
    });

    /*
      Warna tombol konfirmasi dan keadaan sibuk — pola yang sama dengan Cost
      Request. Semua aksi di sini POST biasa yang memuat ulang halaman.
    */
    function tokenWarna(nama, cadangan) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(nama).trim();
        return v || cadangan;
    }
    var WARNA_UTAMA  = tokenWarna('--accent', '#92232b');
    var WARNA_BAHAYA = tokenWarna('--danger', '#9a3030');
    var WARNA_BATAL  = tokenWarna('--text-muted', '#6a6e72');

    function sibukkan(form) {
        if (form.target && form.target !== '_self') return;
        var tombol = form.querySelector('button[type="submit"]');
        if (tombol && window.TesysUI && TesysUI.busy) TesysUI.busy(tombol, true);
    }

    $(document).on('submit',
        '#frmApproveBP, #frmRejectBP, #frmManagerReviseBP, #frmSubmitClosingBP,'
        + ' #frmVerifyClosingBP, #frmRejectClosingBP',
        function () { sibukkan(this); });

    /* DELETE CONFIRM */
    $(document).on('submit', '.frmDeleteBP', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Hapus Budget Plan?', text:'Data tidak dapat dikembalikan.', icon:'warning', showCancelButton:true, confirmButtonColor:WARNA_BAHAYA, cancelButtonColor:WARNA_BATAL, confirmButtonText:'Ya, Hapus', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) { sibukkan(form); form.submit(); } });
    });

    /* ── MODAL DETAIL ── */
    /*
      Metadata status dikirim dari server memakai sumber yang sama dengan chip
      di tabel (App\Support\WorkflowStatus), supaya label, nada warna, dan
      keterangannya tidak bercabang antara tabel dan modal.
    */
    var bpStatusMeta = @json(collect(\App\Models\BudgetPlan::STATUSES)
        ->mapWithKeys(fn ($s) => [$s => \App\Support\WorkflowStatus::meta($s, 'budget-plan')]));

    function bpStatusBadge(kode) {
        var m = bpStatusMeta[kode] || { label: kode, tone: 'neutral', description: null };
        var judul = m.description ? m.label + ' — ' + m.description : m.label;
        return '<span class="ts-status ts-status-' + m.tone + ' ts-status-sm" title="'
            + $('<div>').text(judul).html() + '">' + $('<div>').text(m.label).html() + '</span>';
    }

    function openBPDetail(id, tabHref) {
        window._bpPendingTab = tabHref || null;
        $('#bpDetailLoading').show(); $('#bpDetailContent').hide();
        $('#mdlDetailBP').modal('show');

        $.get('/budget-plan/' + id + '/detail-json', function (d) {
            $('#mdlBPTitle').text(d.description);
            $('#mdlBPSubtitle').text((d.code_reference || '-') + ' - ' + d.code);
            $('#bpCode').text(d.code);
            $('#bpStatusBadge').html(bpStatusBadge(d.status));

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
            $('#infoCodeRef').text((d.code_reference || '-') + ' - ' + d.code);

            $('#bpExportReportBtn').attr('href', '/budget-plan/' + d.id + '/export-report');

            $('#bpAmount').text(d.amount_formatted);
            $('#bpUsed').text(d.used_amount.toLocaleString('id-ID'));
            $('#bpProgressBar').css('width', Math.min(d.usage_percentage, 100) + '%');
            $('#bpRemaining').text(d.remaining_amount.toLocaleString('id-ID'));
            $('#bpInputBy').text(d.input_by);

            var marginPct = d.amount > 0 ? Math.round((d.remaining_amount / d.amount) * 1000) / 10 : 0;
            var marginColor = marginPct <= 0 ? 'danger' : (marginPct <= 15 ? 'warning' : 'light border');
            $('#bpMarginBadge').removeClass('badge-danger badge-warning badge-light border')
                .addClass('badge-' + marginColor.replace(' border', '') + (marginColor.includes('border') ? ' border' : ''))
                .text(marginPct + '%');

            if (d.status === 'DRAFT') {
                $('#bpEditBtn, #bpEditBtnFooter').attr('href', '/budget-plan/' + id + '/edit').show();
            } else {
                $('#bpEditBtn, #bpEditBtnFooter').hide();
            }

            /* Tab Summary */
            $('#infoDesc').html(
                d.description +
                ' ' + bpStatusBadge(d.status)
            );
            $('#infoManager').text((d.manager || '-').toUpperCase());
            $('#infoAmount').html('<strong style="color:var(--info);">' + d.amount_formatted + '</strong>');
            $('#infoNotes').text(d.notes || '-');
            $('#infoApproverNotes').text(d.approver_notes || '-');
            $('#infoApprovedBy').text(d.approved_by || '-');
            $('#infoApprovedAt').text(d.approved_at || '-');

            /* Summary — 3 kartu sederhana */
            (function renderBpSummary() {
                var s = d.summary || {};
                function line(label, val, color, bold) {
                    return '<div class="bp-gc-row' + (bold ? ' bold' : '') + '">' +
                        '<span>' + label + '</span><span' + (color ? ' style="color:' + color + '"' : '') + '>' + val + '</span></div>';
                }
                // status
                var sc = (s.status_counts || []);
                $('#bpSumStatus').html(sc.length
                    ? sc.map(function (r) {
                        return '<div class="bp-gc-row"><span><span class="badge badge-' + r.color + ' px-2 py-1" style="font-size:9px;">' + r.label + '</span></span><span>' + r.count + '</span></div>';
                    }).join('') + line('Total', s.request_total || 0, '', true)
                    : '<div class="text-muted" style="font-size:12px;">Belum ada Cost Request.</div>');
                // tipe
                var tc = (s.type_counts || []);
                $('#bpSumType').html(tc.length
                    ? tc.map(function (r) { return line(r.type, r.count); }).join('')
                    : '<div class="text-muted" style="font-size:12px;">-</div>');
                // budget
                var b = s.budget || {};
                $('#bpSumBudget').html(
                    line('Budget Value', b.budget_value_formatted || '0', 'var(--info)') +
                    line('Request', b.request_formatted || '0') +
                    line('Refund', b.refund_formatted || '0', 'var(--warning)') +
                    line('Actual Used', b.actual_used_formatted || '0', 'var(--success)', true)
                );
            })();

            /* Beneficiary */
            (function renderBpBeneficiary() {
                var rows = d.beneficiaries || [];
                $('#bpBeneficiaryCount').text(rows.length);
                var tr = 0, treal = 0, tref = 0;
                $('#bpBeneficiaryBody').html(rows.length
                    ? rows.map(function (r) {
                        tr += r.request || 0; treal += r.realization || 0; tref += r.refund || 0;
                        return '<tr>' +
                            '<td>' + (r.beneficiary || '-') + '</td>' +
                            '<td class="text-right text-nowrap">' + r.request_formatted + '</td>' +
                            '<td class="text-right text-nowrap">' + r.realization_formatted + '</td>' +
                            '<td class="text-right text-nowrap">' + r.refund_formatted + '</td>' +
                        '</tr>';
                    }).join('')
                    : '<tr><td colspan="4" class="text-center text-muted py-3">Belum ada beneficiary.</td></tr>');
                var rp = function (v) { return Math.round(v).toLocaleString('id-ID'); };
                $('#bpBenReqTotal').text(rp(tr));
                $('#bpBenRealTotal').text(rp(treal));
                $('#bpBenRefundTotal').text(rp(tref));
            })();

            /* Tab Detail — pivot: grup per Account (expand/collapse), summary per grup.
            | Deskripsi | Harga Satuan | Qty (gabung unit) | Budget | Terpakai | Saving | Notes */
            $('#bpDetailCount').text(d.details.length);
            var detHtml = '', totalBudget = 0, totalUsed = 0, totalRemaining = 0;
            var moneyFmt = function (v) { return Math.round(v).toLocaleString('id-ID'); };

            // Kelompokkan per Account, urutan grup alfabetis (urutan item di dalam grup tetap sesuai input asli)
            var groups = {}, groupOrder = [];
            (d.details || []).forEach(function (det) {
                var acc = det.account || '-';
                if (!groups[acc]) { groups[acc] = []; groupOrder.push(acc); }
                groups[acc].push(det);
            });
            groupOrder.sort(function (a, b) { return a.localeCompare(b); });

            var NOB_RED = 'var(--danger)', NOB_YELLOW = 'var(--warning)', OK_GREEN = 'var(--success)';
            var nobBadge = '<span class="badge badge-danger ml-1" style="font-size:9px;">NOB</span>';

            if (groupOrder.length) {
                groupOrder.forEach(function (acc, gi) {
                    var items = groups[acc];
                    var gBudget = 0, gUsed = 0, gRemaining = 0;
                    var anyItemNob = false;
                    items.forEach(function (det) {
                        gBudget += det.total; gUsed += det.used; gRemaining += det.remaining;
                        if (det.used > det.total) anyItemNob = true;
                    });
                    totalBudget += gBudget; totalUsed += gUsed; totalRemaining += gRemaining;

                    var groupClass = 'bp-grp-' + gi;
                    var isNobCategoryFull = gUsed > gBudget;
                    var isNobCategoryPartial = !isNobCategoryFull && anyItemNob;
                    var gColor = isNobCategoryFull ? NOB_RED : (isNobCategoryPartial ? NOB_YELLOW : (gRemaining >= 0 ? OK_GREEN : NOB_RED));
                    var gTextColor = isNobCategoryFull ? NOB_RED : (isNobCategoryPartial ? NOB_YELLOW : '');

                    detHtml += '<tr class="bp-group-row" data-target="' + groupClass + '" style="cursor:pointer;">' +
                        '<td colspan="3" style="font-weight:700;' + (gTextColor ? 'color:' + gTextColor + ';' : '') + '">' +
                            '<i class="fas fa-chevron-right mr-2 bp-caret" style="font-size:10px;transition:transform .15s;display:inline-block;"></i>' +
                            acc + ' <span class="badge badge-secondary ml-1" style="font-size:9px;">' + items.length + '</span>' +
                            (isNobCategoryFull ? nobBadge : '') +
                            ' <span class="bp-crctrl" style="display:none;margin-left:10px;">' +
                                '<button type="button" class="btn btn-outline-secondary bp-cr-expand-all" data-group="' + groupClass + '" style="font-size:9px;padding:1px 7px;">Expand CR</button> ' +
                                '<button type="button" class="btn btn-outline-secondary bp-cr-collapse-all" data-group="' + groupClass + '" style="font-size:9px;padding:1px 7px;">Collapse CR</button>' +
                            '</span>' +
                        '</td>' +
                        '<td class="text-right" style="font-weight:700;' + (gTextColor ? 'color:' + gTextColor + ';' : '') + '">' + moneyFmt(gBudget) + '</td>' +
                        '<td class="text-right" style="font-weight:700;' + (gTextColor ? 'color:' + gTextColor + ';' : '') + '">' + moneyFmt(gUsed) + '</td>' +
                        '<td class="text-right" style="font-weight:700;color:' + gColor + ';">' + moneyFmt(gRemaining) + '</td>' +
                    '</tr>';

                    items.forEach(function (det, ii) {
                        var isNobItem = det.used > det.total;
                        var remColor = isNobItem ? NOB_RED : (det.remaining >= 0 ? OK_GREEN : NOB_RED);
                        var rowColor = isNobItem ? 'color:' + NOB_RED + ';' : '';
                        var qtyUnit = det.qty + (det.unit ? ' ' + det.unit : '');
                        var crDetails = det.cr_details || [];
                        var crBlockClass = groupClass + '-cr-' + ii;

                        var crToggleBtn = crDetails.length
                            ? '<button type="button" class="btn btn-outline-secondary bp-cr-toggle" data-target="' + crBlockClass + '-row" style="font-size:10px;padding:2px 8px;white-space:nowrap;">' +
                                crDetails.length + ' CR <i class="fas fa-chevron-down ml-1"></i></button>'
                            : '';

                        detHtml += '<tr class="bp-group-child ' + groupClass + '" style="display:none;' + rowColor + '">' +
                            '<td class="bp-indent">' +
                                '<div class="d-flex justify-content-between align-items-center" style="gap:8px;">' +
                                    '<span>' + (det.deskripsi || '-') + (isNobItem ? nobBadge : '') + '</span>' +
                                    crToggleBtn +
                                '</div>' +
                            '</td>' +
                            '<td class="text-right">' + det.amount_formatted + '</td>' +
                            '<td class="text-center">' + qtyUnit + '</td>' +
                            '<td class="text-right"><strong>' + det.total_formatted + '</strong></td>' +
                            '<td class="text-right">' + det.used_formatted + '</td>' +
                            '<td class="text-right" style="color:' + remColor + ';">' + det.remaining_formatted + '</td>' +
                        '</tr>';

                        if (crDetails.length) {
                            var crRows = crDetails.map(function (cr) {
                                var viewBtn = cr.cr_id
                                    ? '<a href="/budget-request?open=' + cr.cr_id + '" target="_blank" class="btn btn-info" style="padding:1px 6px;font-size:10px;" title="View CR Detail"><i class="fas fa-eye"></i></a>'
                                    : '';
                                return '<tr>' +
                                    '<td class="text-nowrap">' + cr.cr_date_formatted + '</td>' +
                                    '<td>' + (cr.notes || '-') + '</td>' +
                                    '<td class="text-nowrap">' + cr.beneficiary + '</td>' +
                                    '<td class="text-right text-nowrap">' + cr.unit_price_formatted + '</td>' +
                                    '<td class="text-center text-nowrap">' + cr.qty + '</td>' +
                                    '<td class="text-right text-nowrap"><strong>' + cr.amount_formatted + '</strong></td>' +
                                    '<td class="text-center text-nowrap">' + viewBtn + '</td>' +
                                '</tr>';
                            }).join('');
                            var crTableHtml = '<table class="table table-sm table-bordered mb-0" style="font-size:11px;">' +
                                '<thead style="background:var(--surface-2);"><tr>' +
                                    '<th scope="col" class="text-nowrap" style="width:1%;">Date</th><th scope="col">Description</th><th scope="col" class="text-nowrap" style="width:1%;">Beneficiary</th>' +
                                    '<th scope="col" class="text-right text-nowrap" style="width:1%;">Price</th><th scope="col" class="text-center text-nowrap" style="width:1%;">Qty</th>' +
                                    '<th scope="col" class="text-right text-nowrap" style="width:1%;">Total</th><th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>' +
                                '</tr></thead>' +
                                '<tbody>' + crRows + '</tbody>' +
                            '</table>';

                            detHtml += '<tr class="' + crBlockClass + '-row ' + groupClass + '-crall" style="display:none;">' +
                                '<td colspan="6" style="background:var(--surface-2);padding:10px 14px;">' + crTableHtml + '</td>' +
                            '</tr>';
                        }
                    });
                });
            } else {
                detHtml = '<tr><td colspan="6" class="text-center text-muted py-3">No detail yet.</td></tr>';
            }
            $('#bpDetailBody').html(detHtml);
            $('#bpDetailTotal').text(moneyFmt(totalBudget));
            $('#bpActualTotal').text(moneyFmt(totalUsed));
            var remTotalColor = totalRemaining >= 0 ? 'var(--success)' : 'var(--danger)';
            $('#bpGapTotal').html('<span style="color:' + remTotalColor + ';">' + moneyFmt(totalRemaining) + '</span>');

            /* Tab Request — daftar Cost Request terhubung, expand/collapse per baris untuk rincian sub-item */
            var crStatusColors = {
                'DRAFT':'secondary','SUBMITTED':'warning','APPROVED':'info',
                'VERIFIED':'primary','QUEUED':'warning','PAID':'success',
                'REALIZED':'success','CLOSED':'dark','REJECTED':'danger'
            };
            $('#bpRequestCount').text(d.requests.length);
            var reqHtml = '', reqTotalAmount = 0, reqTotalRealization = 0, reqTotalRefund = 0;
            if (d.requests.length) {
                d.requests.forEach(function (cr, ri) {
                    var isFinal = cr.status === 'REALIZED' || cr.status === 'CLOSED';
                    var refundVal = isFinal ? (cr.refund || 0) : 0;
                    reqTotalAmount += cr.amount || 0;
                    reqTotalRealization += cr.realization || 0;
                    reqTotalRefund += refundVal;
                    var reqClass = 'bp-req-' + ri;
                    reqHtml += '<tr class="bp-req-row" data-target="' + reqClass + '">' +
                        '<td><i class="fas fa-chevron-right bp-req-caret"></i></td>' +
                        '<td>' +
                            '<div style="font-weight:600;">' + cr.description + '</div>' +
                            '<small class="text-muted">' + (cr.applicant || '-') + ' &bull; ' + (cr.type || '-') + '</small>' +
                        '</td>' +
                        '<td class="text-right text-nowrap">' +
                            '<strong>' + cr.amount_formatted + '</strong>' +
                            '<div><small class="text-muted">' + (cr.beneficiary || '-') + '</small></div>' +
                        '</td>' +
                        '<td class="text-right text-nowrap">' + cr.realization_formatted + '</td>' +
                        '<td class="text-right text-nowrap">' + (isFinal ? cr.refund_formatted : '<span class="text-muted">-</span>') + '</td>' +
                        '<td class="text-nowrap">' + cr.date + '</td>' +
                        '<td><span class="badge badge-' + (crStatusColors[cr.status] || 'secondary') + ' px-2 py-1" style="font-size:10px;">' + cr.status + '</span></td>' +
                    '</tr>';

                    if (cr.details && cr.details.length) {
                        cr.details.forEach(function (det) {
                            var strike = det.is_takeout ? 'text-decoration:line-through;color:var(--text-faint);' : '';
                            var badge  = det.is_takeout ? ' <span class="badge badge-warning" style="font-size:8px;">Takeout &middot; ' + (det.takeout_stage_label || '-') + '</span>' : '';
                            reqHtml += '<tr class="' + reqClass + '" style="display:none;">' +
                                '<td></td>' +
                                '<td class="bp-req-indent" colspan="2" style="' + strike + '">' + det.description + badge +
                                    (det.notes ? '<div><small class="text-muted" style="text-decoration:none;">' + det.notes + '</small></div>' : '') +
                                '</td>' +
                                '<td class="text-right" colspan="3" style="' + strike + '">' + det.qty + ' &times; ' + det.amount_formatted + ' = <strong>' + det.total_formatted + '</strong></td>' +
                                '<td></td>' +
                            '</tr>';
                        });
                    } else {
                        reqHtml += '<tr class="' + reqClass + '" style="display:none;"><td></td><td colspan="6" class="text-muted bp-req-indent">No item detail.</td></tr>';
                    }
                });
            } else {
                reqHtml = '<tr><td colspan="7" class="text-center text-muted py-3">No linked Cost Request yet.</td></tr>';
            }
            $('#bpRequestBody').html(reqHtml);
            $('#bpRequestTotal').text(reqTotalAmount.toLocaleString('id-ID'));
            $('#bpRealizationTotal').text(reqTotalRealization.toLocaleString('id-ID'));
            $('#bpRefundTotal').text(reqTotalRefund.toLocaleString('id-ID'));

            /* Tab Attachment — thumbnail gambar (medium), file lain pakai ikon; klik gambar buka carousel viewer */
            $('#bpAttCount').text(d.attachments.length);
            /* Tab Attachment — tabel + tombol View */
            var attHtml = '';
            if (d.attachments.length) {
                var rows = d.attachments.map(function (a, i) {
                    var url = '/storage/' + a.path;
                    var typeLabel = a.is_image ? 'IMG' : (a.is_pdf ? 'PDF' : 'DOC');
                    var typeBadge = a.is_image ? 'info' : (a.is_pdf ? 'danger' : 'secondary');
                    var viewBtn = a.is_image
                        ? '<button aria-label="View" type="button" class="btn btn-info btn-xs bp-att-view-img" data-full="' + url + '" data-name="' + (a.original_name || '') + '" title="View"><i class="fas fa-eye"></i></button>'
                        : '<a href="' + url + '" target="_blank" rel="noopener" class="btn btn-info btn-xs" title="View"><i class="fas fa-eye"></i></a>';
                    return '<tr>' +
                        '<td class="text-center" style="width:1%;">' + (i + 1) + '</td>' +
                        '<td>' + (a.original_name || 'File') + '</td>' +
                        '<td class="text-center" style="width:1%;"><span class="badge badge-' + typeBadge + '" style="font-size:9px;">' + typeLabel + '</span></td>' +
                        '<td class="text-center" style="width:1%;">' + viewBtn + '</td>' +
                    '</tr>';
                }).join('');
                attHtml = '<div class="table-responsive"><table class="table table-bordered mb-0" style="font-size:12px;">' +
                    '<thead style="background:var(--surface-2);"><tr>' +
                        '<th scope="col" class="text-center" style="color:var(--text-faint);font-size:11px;">No</th>' +
                        '<th scope="col" style="color:var(--text-faint);font-size:11px;">Nama File</th>' +
                        '<th scope="col" class="text-center" style="color:var(--text-faint);font-size:11px;">Tipe</th>' +
                        '<th scope="col" class="text-center" style="color:var(--text-faint);font-size:11px;">Aksi</th>' +
                    '</tr></thead><tbody>' + rows + '</tbody></table></div>';
            } else {
                attHtml = '<p class="text-muted py-3 mb-0 text-center">No attachments yet.</p>';
            }
            $('#bpAttBody').html(attHtml);

            /* Tab Log — gaya chat: log dari user aktif di kanan, lainnya di kiri */
            var logHtml = '';
            if (d.logs.length) {
                d.logs.slice().reverse().forEach(function (log) {
                    var mine = log.user_id && bpCurrentUserId && log.user_id === bpCurrentUserId;
                    var actor = log.user || 'System';
                    var initials = actor.trim().split(/\s+/).map(function (w) { return w[0]; }).join('').slice(0, 2).toUpperCase() || '?';
                    logHtml += '<div class="bp-chat-row ' + (mine ? 'mine' : '') + '">' +
                        '<div class="bp-chat-av">' + initials + '</div>' +
                        '<div>' +
                            '<div class="bp-chat-meta">' + (mine ? '' : '<strong>' + actor + '</strong> · ') + (log.role || 'system') + ' · ' + log.date + '</div>' +
                            '<div class="bp-chat-bubble">' +
                                '<span class="badge badge-' + log.action_color + ' px-2 py-1" style="font-size:9px;">' + log.action_label + '</span>' +
                                (log.status_from && log.status_to ? ' <span style="font-size:10px;opacity:.75;">' + log.status_from + ' → ' + log.status_to + '</span>' : '') +
                                (log.notes ? '<div class="bp-chat-note">' + log.notes + '</div>' : '') +
                            '</div>' +
                        '</div>' +
                    '</div>';
                });
            } else {
                logHtml = '<p class="text-muted py-3 mb-0">No log yet.</p>';
            }
            $('#bpLogBody').html(logHtml);

            var pendingTab = window._bpPendingTab;
            if (pendingTab && $('#bpDetailTabs a[href="' + pendingTab + '"]').length) {
                $('#bpDetailTabs a[href="' + pendingTab + '"]').tab('show');
            } else {
                $('#bpDetailTabs a:first').tab('show');
            }
            window._bpPendingTab = null;
            $('#bpDetailLoading').hide(); $('#bpDetailContent').show();
        }).fail(function () {
            $('#bpDetailLoading').html('<p class="text-danger text-center py-4"><i class="fas fa-exclamation-triangle mr-1"></i>Failed to load data.</p>');
        });
    }

    $(document).on('click', '.btn-detail-bp', function () {
        openBPDetail($(this).data('id'), $(this).data('tab'));
    });

    $('#mdlDetailBP').on('hidden.bs.modal', function () {
        $('#bpDetailContent').hide(); $('#bpDetailLoading').show();
        $('#bpNotesCollapse').hide(); $('#bpNotesToggleIcon').removeClass('rotated');
    });

    // Auto-buka modal detail bila diakses dengan ?open=<id> (mis. dari link Treasury).
    // Parameternya dibuang dari URL setelah dipakai, supaya reload / redirect
    // back() tidak membuka modal detail itu lagi.
    (function () {
        var params = new URLSearchParams(window.location.search);
        var openId = params.get('open');
        if (!openId) return;

        params.delete('open');
        var sisa = params.toString();
        window.history.replaceState({}, '',
            window.location.pathname + (sisa ? '?' + sisa : '') + window.location.hash);

        openBPDetail(openId);
    })();

    /* ── 4 TAHAP APPROVAL: Manager submit -> Head check -> Finance verify ->
       CEO approve (Active). Approve/Reject reuse modal yang sama, action &
       teksnya di-swap sesuai data-stage tombolnya. ── */
    var bpStageConfig = {
        head: {
            approveUrl: 'head-approve', rejectUrl: 'head-reject',
            approveTitle: 'Approval Head',
            approveText: 'Setujui Budget Plan ini? Status akan berubah menjadi CHECKED dan diteruskan ke Finance.'
        },
        finance: {
            approveUrl: 'finance-verify', rejectUrl: 'finance-reject',
            approveTitle: 'Verifikasi Finance',
            approveText: 'Verifikasi Budget Plan ini? Status akan berubah menjadi VERIFIED dan diteruskan ke CEO.'
        },
        ceo: {
            approveUrl: 'ceo-approve', rejectUrl: 'ceo-reject',
            approveTitle: 'Approval CEO',
            approveText: 'Setujui Budget Plan ini? Status akan berubah menjadi ACTIVE.'
        }
    };

    /* ── APPROVE ── */
    $(document).on('click', '.btn-approve-bp', function () {
        var id  = $(this).data('id');
        var cfg = bpStageConfig[$(this).data('stage')];
        $('#mdlApproveTitle').html('<i class="fas fa-check mr-2" style="color:var(--accent);"></i> ' + cfg.approveTitle);
        $('#mdlApproveText').text(cfg.approveText);
        $('#frmApproveBP').attr('action', '/budget-plan/' + id + '/' + cfg.approveUrl);
        $('#frmApproveBP textarea[name="notes"]').val('');
        $('#mdlApproveBP').modal('show');
    });

    /* ── REJECT ── */
    $(document).on('click', '.btn-reject-bp', function () {
        var id  = $(this).data('id');
        var cfg = bpStageConfig[$(this).data('stage')];
        $('#frmRejectBP').attr('action', '/budget-plan/' + id + '/' + cfg.rejectUrl);
        $('#frmRejectBP textarea[name="notes"]').attr('placeholder', 'Tuliskan alasan penolakan...').val('');
        $('#mdlRejectBP .modal-title').html('<i class="fas fa-times mr-2" style="color:var(--accent);"></i> Tolak Budget Plan');
        $('#mdlRejectBP button[type="submit"]').text('Tolak').removeClass('btn-warning').addClass('btn-danger');
        $('#mdlRejectBP').modal('show');
    });

    /* ── MANAGER REVISE (REJECTED → DRAFT) ── */
    $(document).on('click', '.btn-manager-revise-bp', function () {
        var id = $(this).data('id');
        $('#frmManagerReviseBP').attr('action', '/budget-plan/' + id + '/manager-revise');
        $('#frmManagerReviseBP textarea[name="notes"]').val('');
        $('#mdlManagerReviseBP').modal('show');
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