@extends('layouts.app')

@section('title', 'Budgeting')

@section('content')

<div class="content">
    <div class="container-fluid">
      <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        {{-- Judul & breadcrumb topbar: shell memindahkan <h1> dan .breadcrumb pertama ke topbar
             (tesys-shell.js hoistPageTitle); disembunyikan secara visual. --}}
        <div class="tw:sr-only">
            <h1>Budgeting</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Budgeting</li>
            </ol>
        </div>

        @php
            $bolehTambahBP = Auth::user()->hasRole(['superadmin','manager']);
            // Kolom centang bulk approve/reject hanya untuk peran yang punya tahap approval.
            $bolehBulkBP = Auth::user()->hasRole(['superadmin','head','finance','finance_manager','approver']);
        @endphp
        <x-tw.page-header title="Budget Plan"
            description="Anggaran per PO atau Cost Center beserta pemakaian, sisa, dan persetujuannya.">
            @if($bolehTambahBP)
            <x-slot:actions>
                <x-tw.button href="{{ url('/budget-plan/create') }}" class="ts-add-btn tw:max-sm:min-h-12"><i class="fas fa-plus" aria-hidden="true"></i> Tambah Budget Plan</x-tw.button>
            </x-slot:actions>
            @endif
        </x-tw.page-header>

        @if($bolehTambahBP)
        {{-- Mobile: tombol di header disembunyikan dan diganti tombol melayang (tesys-ui-refine.css). --}}
        <a href="{{ url('/budget-plan/create') }}" class="ts-fab" aria-label="Tambah Budget Plan" title="Tambah Budget Plan">
            <i class="fas fa-plus" aria-hidden="true"></i>
        </a>
        @endif

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
        <x-workflow-stepper :steps="$bpSteps" :compact="true" table="tblBudgetPlan" field="status" :groups="[['Fase 1: Pengajuan', ['DRAFT','SUBMITTED','REJECTED']], ['Fase 2: Persetujuan', ['CHECKED','VERIFIED']], ['Fase 3: Berjalan', ['ACTIVE','CLOSING','CLOSED']]]" />

        {{-- Kartu tunggal: toolbar + tabel + paginasi (pola Cost Request). --}}
        <x-tw.card padding="sm">

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
                        @if($bolehBulkBP)
                        {{-- Bar bulk: muncul bila ada Budget Plan yang dicentang (satu status per proses). --}}
                        <div class="bp-bulk-bar d-none tw:flex-wrap tw:items-center tw:gap-2 tw:rounded-md tw:border tw:border-accent-soft tw:bg-accent-tint tw:py-1 tw:pl-3 tw:pr-1">
                            <span class="bp-bulk-info tw:font-label tw:tabular-nums"></span>
                            <x-tw.button size="sm" class="bp-bulk-approve"><i class="fas fa-check" aria-hidden="true"></i> Approve</x-tw.button>
                            <x-tw.button variant="danger" size="sm" class="bp-bulk-reject"><i class="fas fa-times" aria-hidden="true"></i> Reject</x-tw.button>
                            <x-tw.button variant="ghost" size="sm" class="bp-bulk-clear" title="Batalkan pilihan" aria-label="Batalkan pilihan"><i class="fas fa-times" aria-hidden="true"></i></x-tw.button>
                        </div>
                        @endif
                        <x-tw.button id="btnRefreshBP" variant="secondary" size="sm"><i class="fas fa-sync-alt" aria-hidden="true"></i> Refresh</x-tw.button>
                        <x-tw.button href="{{ route('budget-plan.export') }}" id="btnExportBP" variant="secondary" size="sm">
                            <i class="fas fa-file-excel" aria-hidden="true"></i> Export Excel
                        </x-tw.button>
                    </div>
                </div>

                <!-- TABLE -->
                <div class="table-responsive">
                    <table id="tblBudgetPlan" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                        <thead class="bg-light">
                            <tr>
                                @if($bolehBulkBP)
                                <th scope="col" class="all text-center dt-col-check" style="width:1%;"><input type="checkbox" class="bp-check-all" aria-label="Pilih semua Budget Plan yang bisa diproses"></th>
                                @endif
                                <th scope="col" class="all text-nowrap" style="width:1%;">No</th>
                                <th scope="col" class="all text-nowrap" style="width:1%;">Code</th>
                                <th scope="col" class="all">Description</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">PM Internal</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;">Budget</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;">Used</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;">Var</th>
                                <th scope="col" class="text-center text-nowrap" style="width:1%;">Margin</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Status</th>
                                <th scope="col" class="text-center all action-column" style="width:1%;"><span class="sr-only">Aksi</span></th>
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
                            <tr data-id="{{ $bp->id }}" data-code="{{ $bp->code }}"
                                data-status="{{ $bp->status }}"
                                data-po="{{ $bp->project ? '[' . $bp->project->code . '] ' . $bp->project->name : '' }}"
                                data-customer="{{ $bp->project->customer->name ?? '' }}"
                                data-pminternal="{{ $bpPmInternal }}">
                                @if($bolehBulkBP)
                                @php
                                    // Hak centang dihitung di server: tahap sesuai status + role + bukan Budget Plan "via PO".
                                    $eligBulkBP = ! $bp->isBundled() && (
                                        ($bp->status === 'SUBMITTED' && Auth::user()->hasRole(['superadmin','head']))
                                        || ($bp->status === 'CHECKED' && Auth::user()->hasRole(['superadmin','finance','finance_manager']))
                                        || ($bp->status === 'VERIFIED' && Auth::user()->hasRole(['superadmin','approver']))
                                    );
                                @endphp
                                <td class="text-center dt-col-check">
                                    <input type="checkbox" class="bp-check-row" value="{{ $bp->id }}" data-elig="{{ $eligBulkBP ? '1' : '0' }}"
                                           aria-label="Pilih Budget Plan {{ $bp->code }}"
                                           @unless($eligBulkBP) disabled title="Tidak ada tahap approval untuk Anda pada Budget Plan ini" @endunless>
                                </td>
                                @endif
                                <td class="text-nowrap">{{ $loop->iteration }}</td>

                                <td class="text-nowrap">
                                    {{-- Klik kode = salin; detail dibuka lewat klik baris. --}}
                                    <button type="button" class="ts-copy-code" data-code="{{ $bp->code }}" title="Klik untuk menyalin kode">{{ $bp->code ?? '-' }}</button>
                                </td>

                                <td class="ts-col-desc" title="{{ $bp->description }}">{{ Str::limit($bp->description, 60) }}</td>

                                <td class="text-nowrap ts-col-desc" title="{{ $bpPmInternal }}">{{ $bpPmInternal ?: '-' }}</td>

                                <td class="text-right text-nowrap ts-col-money col-raw-amount" data-raw="{{ $bp->amount ?? 0 }}" data-order="{{ $bp->amount ?? 0 }}">
                                    <button type="button" class="bp-cell-link btn-detail-bp" data-id="{{ $bp->id }}" data-tab="#bpPaneDetails" title="Lihat Budget">{{ ($bp->amount ?? 0) == 0 ? '-' : $bp->amount_formatted }}</button>
                                </td>

                                <td class="text-right text-nowrap ts-col-money col-raw-actual" data-raw="{{ $bpActual }}" data-order="{{ $bpActual }}">
                                    <button type="button" class="bp-cell-link btn-detail-bp" data-id="{{ $bp->id }}" data-tab="#bpPaneRequests" title="Lihat Cost">{{ $bpActual == 0 ? '-' : number_format($bpActual, 0, ',', '.') }}</button>
                                </td>

                                <td class="text-right text-nowrap ts-col-money col-raw-var {{ $bpVar >= 0 ? 'text-var-pos' : 'text-var-neg' }}" data-raw="{{ $bpVar }}" data-order="{{ $bpVar }}">
                                    {{ $bpVar == 0 ? '-' : ($bpVar < 0 ? '-' : '') . number_format(abs($bpVar), 0, ',', '.') }}
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

                                        // Budget dari form PO (via_po_approval): statusnya ikut approval PO,
                                        // jadi tombol workflow/ubah/hapus Budget Plan disembunyikan
                                        // (BudgetPlanController menolaknya juga). Closing tetap normal.
                                        $viaPo           = $bp->isBundled();

                                        $bolehDraft      = !$viaPo && $stBp === 'DRAFT' && $pengguna->hasRole(['superadmin','manager']);
                                        $bolehHead       = !$viaPo && $stBp === 'SUBMITTED' && $pengguna->hasRole(['superadmin','head']);
                                        $bolehFinance    = !$viaPo && $stBp === 'CHECKED' && $pengguna->hasRole(['superadmin','finance','finance_manager']);
                                        $bolehCeo        = !$viaPo && $stBp === 'VERIFIED' && $pengguna->hasRole(['superadmin','approver']);
                                        $bolehRevisi     = !$viaPo && $stBp === 'REJECTED' && $pengguna->hasRole(['superadmin','manager']);
                                        // CLOSING punya dua tahap: sisa data lama yang berkasnya
                                        // belum diajukan masih tugas manager; baru setelah
                                        // diajukan jadi giliran Finance.
                                        $bolehClosing    = ($stBp === 'ACTIVE' || $bp->isClosingPending()) && $pengguna->hasRole(['superadmin','manager']);
                                        $bolehVerClosing = $bp->isClosingSubmitted() && $pengguna->hasRole(['superadmin','finance','finance_manager']);
                                        // Finance boleh menutup budget yang sudah ACTIVE tanpa
                                        // menunggu pengajuan closing dari manager.
                                        $bolehTutup      = $stBp === 'ACTIVE' && $pengguna->hasRole(['superadmin','finance','finance_manager']);
                                        // Tambah baris COA bernilai 0 ke budget ACTIVE (BudgetPlanController::addCoa).
                                        $bolehCoa        = $stBp === 'ACTIVE' && $pengguna->hasRole(['superadmin','manager']);

                                        $adaAksi = $bolehDraft || $bolehHead || $bolehFinance || $bolehCeo
                                            || $bolehRevisi || $bolehClosing || $bolehVerClosing || $bolehTutup;
                                    @endphp

                                    @php
                                        // Satu tombol saja per baris: tepat 1 aksi = tombol aksinya; lebih dari 1 =
                                        // satu tombol ⋮ berisi semua aksi (aksi utama di atas, penolakan/hapus
                                        // dipisah garis). Detail dibuka lewat klik baris, jadi tidak ada item "Detail".
                                        $aksiTambahan = ($bolehDraft ? 2 : 0) + (($bolehHead || $bolehFinance || $bolehCeo) ? 1 : 0)
                                            + ($bolehVerClosing ? 1 : 0) + (($bolehTutup && $bolehClosing) ? 1 : 0)
                                            + ($bolehCoa ? 1 : 0);
                                        $jumlahAksi = ($adaAksi ? 1 : 0) + $aksiTambahan;
                                        $satuAksi   = $jumlahAksi === 1;
                                        $kls    = $satuAksi ? 'ts-action-primary' : 'dropdown-item';
                                        $lbl    = $satuAksi ? 'ts-al' : '';
                                        $netral = $satuAksi ? 'is-neutral' : '';
                                        ob_start();
                                    @endphp
                                    @if($bolehDraft)
                                    <form action="{{ url('/budget-plan/' . $bp->id . '/submit') }}"
                                          method="POST" class="frmSubmitBP {{ $satuAksi ? 'd-inline' : '' }}">
                                        @csrf
                                        <button type="submit" class="{{ $kls }}" title="Submit ke Finance">
                                            <i class="fas fa-paper-plane"></i><span class="{{ $lbl }}">Submit</span>
                                        </button>
                                    </form>
                                    @elseif($bolehHead)
                                    <button type="button" class="{{ $kls }} btn-approve-bp"
                                            data-id="{{ $bp->id }}" data-stage="head" title="Approve → Checked">
                                        <i class="fas fa-check"></i><span class="{{ $lbl }}">Approve</span>
                                    </button>
                                    @elseif($bolehFinance)
                                    <button type="button" class="{{ $kls }} btn-approve-bp"
                                            data-id="{{ $bp->id }}" data-stage="finance" title="Verify → Verified">
                                        <i class="fas fa-check"></i><span class="{{ $lbl }}">Verify</span>
                                    </button>
                                    @elseif($bolehCeo)
                                    <button type="button" class="{{ $kls }} btn-approve-bp"
                                            data-id="{{ $bp->id }}" data-stage="ceo" title="Approve → Active">
                                        <i class="fas fa-check"></i><span class="{{ $lbl }}">Approve</span>
                                    </button>
                                    @elseif($bolehRevisi)
                                    <button type="button" class="{{ $kls }} {{ $netral }} btn-manager-revise-bp"
                                            data-id="{{ $bp->id }}" title="Kembalikan ke Draft untuk direvisi">
                                        <i class="fas fa-undo"></i><span class="{{ $lbl }}">Revise</span>
                                    </button>
                                    @elseif($bolehClosing)
                                    <button type="button" class="{{ $kls }} btn-submit-closing-bp"
                                            data-id="{{ $bp->id }}" data-desc="{{ $bp->description }}"
                                            title="Submit Closing">
                                        <i class="fas fa-flag-checkered"></i><span class="{{ $lbl }}">Submit Closing</span>
                                    </button>
                                    @elseif($bolehVerClosing)
                                    <button type="button" class="{{ $kls }} btn-verify-closing-bp"
                                            data-id="{{ $bp->id }}" title="Approve Closing → Closed">
                                        <i class="fas fa-check-double"></i><span class="{{ $lbl }}">Approve Closing</span>
                                    </button>
                                    @elseif($bolehTutup)
                                    <button type="button" class="{{ $kls }} btn-finance-close-bp"
                                            data-id="{{ $bp->id }}" data-desc="{{ $bp->description }}"
                                            title="Close → Closed">
                                        <i class="fas fa-lock"></i><span class="{{ $lbl }}">Close</span>
                                    </button>
                                    @endif
                                    @php $htmlUtama = ob_get_clean(); @endphp

                                    <div class="action-buttons ts-actions">

                                        @if($satuAksi)
                                        {!! $htmlUtama !!}
                                        @elseif($jumlahAksi > 1)
                                        <button type="button" class="ts-action-more"
                                                aria-haspopup="true" aria-expanded="false"
                                                title="Aksi" aria-label="Aksi untuk {{ $bp->code }}">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>

                                        <div class="ts-action-menu dropdown-menu" hidden>

                                            {!! $htmlUtama !!}

                                            @if($bolehCoa)
                                            <button type="button" class="dropdown-item btn-add-coa-bp"
                                                    data-id="{{ $bp->id }}" data-code="{{ $bp->code }}"
                                                    data-desc="{{ $bp->description }}"
                                                    data-category="{{ $coaCategoryOf[$bp->source_type] ?? '' }}">
                                                <i class="fas fa-plus"></i>Tambah COA
                                            </button>
                                            @endif

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

                                            @if($bolehTutup && $bolehClosing)
                                            {{-- Superadmin memegang dua peran sekaligus: aksi utamanya Submit
                                                 Closing, jadi Close disediakan di sini agar tidak hilang. --}}
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item btn-finance-close-bp"
                                                    data-id="{{ $bp->id }}" data-desc="{{ $bp->description }}">
                                                <i class="fas fa-lock"></i>Close
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
                                <th colspan="{{ $bolehBulkBP ? 5 : 4 }}" class="text-right" style="font-size:12px;">Total (data tampil)</th>
                                <th class="text-right text-nowrap" id="bpFooterAmount" style="font-size:12px;color:var(--info);"></th>
                                <th class="text-right text-nowrap" id="bpFooterActual" style="font-size:12px;color:var(--success);"></th>
                                <th class="text-right text-nowrap" id="bpFooterVar" style="font-size:12px;"></th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
        </x-tw.card>
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

                            </div>
                        </div>

                        <!-- RIGHT TABS -->
                        <div class="bp-main-panel">
                            <ul class="nav nav-tabs mb-3" id="bpDetailTabs" style="border-bottom:2px solid var(--border);">
                                <li class="nav-item">
                                    <a class="nav-link active" data-toggle="tab" href="#bpPaneInfo" style="font-size:13px;font-weight:600;">
                                        <i class="fas fa-clipboard-list mr-1"></i> Info
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
                                    <a class="nav-link" data-toggle="tab" href="#bpPaneCashAdvance" style="font-size:13px;font-weight:600;">
                                        <i class="fas fa-hand-holding-usd mr-1"></i> Cash Advance
                                        <span id="bpCaCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
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
                                    {{-- Kartu ringkasan (pola "Nilai pengajuan" drawer Cost Request). --}}
                                    <div class="bp-figcard mb-3">
                                        <div class="bp-fig-label">Total Budget</div>
                                        <div id="bpAmount" class="bp-fig-big"></div>
                                        <div class="bp-fig-chips">
                                            <span id="bpStatusBadge"></span>
                                            <span id="bpMarginBadge" class="badge px-2 py-1" style="font-size:10px;"></span>
                                        </div>
                                        <div class="bp-fig-sub">
                                            <div>
                                                <div class="bp-fig-label">Used</div>
                                                <div id="bpUsed" class="bp-fig-val"></div>
                                            </div>
                                            <div>
                                                <div class="bp-fig-label">Variance</div>
                                                <div id="bpRemaining" class="bp-fig-val"></div>
                                            </div>
                                        </div>
                                        <div class="bp-fig-bar"><div id="bpProgressBar" style="height:100%;background:var(--accent);border-radius:var(--radius-pill);transition:width .4s;width:0%;"></div></div>
                                    </div>
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

                                    <h6 class="ts-dw-section-title">Informasi anggaran</h6>
                                    <dl class="ts-dw-kv mb-3">
                                        <dt>Reference</dt><dd id="infoCodeRef"></dd>
                                        <dt>Description</dt><dd id="infoDesc"></dd>
                                        <dt>PO Reference</dt><dd id="infoPO"></dd>
                                        <div class="bp-kv-box" id="bpCustomerBox"><dt>Customer</dt><dd id="bpCustomer"></dd></div>
                                        <dt>Manager</dt><dd id="infoManager"></dd>
                                        <dt>Input By</dt><dd id="bpInputBy"></dd>
                                        <dt>Approved By</dt><dd id="infoApprovedBy"></dd>
                                        <dt>Approved At</dt><dd id="infoApprovedAt"></dd>
                                    </dl>
                                    {{-- Duplikat nilai di atas disembunyikan; JS lama tetap menulis ke ID-nya. --}}
                                    <span id="bpProject" hidden></span><span id="infoAmount" hidden></span>

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

                                </div>

                                <!-- TAB BUDGET — Account | Price | Qty | Unit | Total | Used | Variance | Notes -->
                                <div class="tab-pane fade" id="bpPaneDetails">
                                    <div class="d-flex justify-content-between align-items-center mb-2" style="gap:8px;">
                                        <div class="input-group input-group-sm" style="max-width:280px;">
                                            <div class="input-group-prepend"><span class="input-group-text" style="font-size:11px;"><i class="fas fa-search"></i></span></div>
                                            <input type="text" id="bpBudgetSearch" class="form-control" placeholder="Cari CoA / deskripsi..." style="font-size:12px;">
                                        </div>
                                        <div>
                                            <button type="button" class="btn btn-xs btn-outline-secondary" id="btnToggleAllBpDetail"><i class="fas fa-angle-double-down mr-1"></i>Expand All</button>
                                            <a id="bpExportBudgetBtn" href="#" target="_blank" class="btn btn-xs btn-outline-primary ml-1"><i class="fas fa-file-excel mr-1"></i>Export</a>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0" style="font-size:12px;">
                                            <thead style="background:var(--surface-2);">
                                                <tr>
                                                    <th scope="col" class="bp-bsort" data-sort="account" style="color:var(--text-faint);font-size:11px;cursor:pointer;">Chart of Account <i class="fas fa-sort bp-bsort-ic"></i></th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:14%;">Unit Price</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:center;width:10%;">Qty</th>
                                                    <th scope="col" class="bp-bsort" data-sort="budget" style="color:var(--text-faint);font-size:11px;text-align:right;width:14%;cursor:pointer;">Budget <i class="fas fa-sort bp-bsort-ic"></i></th>
                                                    <th scope="col" class="bp-bsort" data-sort="used" style="color:var(--text-faint);font-size:11px;text-align:right;width:14%;cursor:pointer;">Used <i class="fas fa-sort bp-bsort-ic"></i></th>
                                                    <th scope="col" class="bp-bsort" data-sort="variance" style="color:var(--text-faint);font-size:11px;text-align:right;width:14%;cursor:pointer;">Variance <i class="fas fa-sort bp-bsort-ic"></i></th>
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
                                    <div class="d-flex justify-content-between align-items-center mb-2" style="gap:8px;">
                                        <div class="input-group input-group-sm" style="max-width:280px;">
                                            <div class="input-group-prepend"><span class="input-group-text" style="font-size:11px;"><i class="fas fa-search"></i></span></div>
                                            <input type="text" id="bpCostSearch" class="form-control" placeholder="Cari kode / deskripsi / CoA..." style="font-size:12px;">
                                        </div>
                                        <a id="bpExportCostBtn" href="#" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-file-excel mr-1"></i> Export
                                        </a>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0 bp-sortable" style="font-size:12px;">
                                            <thead style="background:var(--surface-2);">
                                                <tr>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;width:1%;"></th>
                                                    <th scope="col" class="bp-sort" data-sort="description" data-type="text" style="color:var(--text-faint);font-size:11px;cursor:pointer;">Description <i class="fas fa-sort bp-sort-ic"></i></th>
                                                    <th scope="col" class="bp-sort" data-sort="amount" data-type="num" style="color:var(--text-faint);font-size:11px;text-align:right;width:1%;white-space:nowrap;cursor:pointer;">Request <i class="fas fa-sort bp-sort-ic"></i></th>
                                                    <th scope="col" class="bp-sort" data-sort="refund" data-type="num" style="color:var(--text-faint);font-size:11px;text-align:right;width:1%;white-space:nowrap;cursor:pointer;">Refund <i class="fas fa-sort bp-sort-ic"></i></th>
                                                    <th scope="col" class="bp-sort" data-sort="expense_claim" data-type="num" style="color:var(--text-faint);font-size:11px;text-align:right;width:1%;white-space:nowrap;cursor:pointer;">Expense Claim <i class="fas fa-sort bp-sort-ic"></i></th>
                                                    <th scope="col" class="bp-sort" data-sort="effective" data-type="num" style="color:var(--text-faint);font-size:11px;text-align:right;width:1%;white-space:nowrap;cursor:pointer;">Effective <i class="fas fa-sort bp-sort-ic"></i></th>
                                                    <th scope="col" class="bp-sort" data-sort="date" data-type="idx" style="color:var(--text-faint);font-size:11px;width:1%;white-space:nowrap;cursor:pointer;">Date <i class="fas fa-sort bp-sort-ic"></i></th>
                                                    <th scope="col" class="bp-sort" data-sort="status" data-type="text" style="color:var(--text-faint);font-size:11px;width:1%;white-space:nowrap;cursor:pointer;">Status <i class="fas fa-sort bp-sort-ic"></i></th>
                                                </tr>
                                            </thead>
                                            <tbody id="bpRequestBody"></tbody>
                                            <tfoot>
                                                <tr style="background:var(--surface-2);">
                                                    <th></th>
                                                    <th class="text-right">Total</th>
                                                    <th class="text-right" id="bpRequestTotal" style="color:var(--info);"></th>
                                                    <th class="text-right" id="bpRefundTotal" style="color:var(--warning);"></th>
                                                    <th class="text-right" id="bpClaimTotal" style="color:var(--danger);"></th>
                                                    <th class="text-right" id="bpEffectiveTotal" style="color:var(--success);"></th>
                                                    <th colspan="2"></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>

                                <!-- TAB CASH ADVANCE -->
                                <div class="tab-pane fade" id="bpPaneCashAdvance">
                                    <div class="bp-groupcard mb-3">
                                        <div class="bp-groupcard-title">Ringkasan Cash Advance</div>
                                        <div id="bpCaSummary"></div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0" style="font-size:12px;">
                                            <thead style="background:var(--surface-2);">
                                                <tr>
                                                    <th scope="col" style="width:1%;"></th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;">PIC / Cost Request</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;width:1%;white-space:nowrap;">Status</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;width:1%;white-space:nowrap;">Tanggal Paid</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;width:1%;white-space:nowrap;text-align:center;">Aging</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:1%;white-space:nowrap;">Value</th>
                                                </tr>
                                            </thead>
                                            <tbody id="bpCashAdvanceBody"></tbody>
                                            <tfoot>
                                                <tr style="background:var(--surface-2);">
                                                    <th colspan="5" class="text-right">Total</th>
                                                    <th class="text-right" id="bpCaValueTotal" style="color:var(--info);"></th>
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
                                    {{-- Kotak catatan gaya chat (sama dengan tab Log Cost Request); disembunyikan JS saat Closed. --}}
                                    <form id="bpLogForm" class="bp-log-form" hidden>
                                        <textarea id="bpLogInput" class="form-control" rows="2" maxlength="1000"
                                                  placeholder="Tulis catatan atau update…" aria-label="Catatan log"></textarea>
                                        <button type="submit" class="btn btn-sm btn-primary" id="bpLogSend"><i class="fas fa-paper-plane mr-1"></i> Kirim</button>
                                    </form>
                                    <div id="bpLogClosed" class="bp-log-closed" hidden><i class="fas fa-lock mr-1"></i> Budget Plan sudah Closed — catatan baru tidak dapat ditambahkan.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Kaki panel: aksi dokumen (pola drawer Cost Request). Tampil/sembunyi lewat JS. --}}
            <div class="modal-footer bp-dm-foot">
                <a id="bpEditBtnFooter" href="#" class="ts-action-primary" style="display:none;"><i class="fas fa-edit" aria-hidden="true"></i><span class="ts-al">Edit</span></a>
                <a id="bpExportReportBtn" href="#" class="ts-action-primary is-neutral" target="_blank"><i class="fas fa-file-excel" aria-hidden="true"></i><span class="ts-al">Export Report</span></a>
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

{{-- MODAL FINANCE CLOSE — ACTIVE → CLOSED --}}
<div class="modal fade" id="mdlFinanceCloseBP" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-lock mr-2" style="color:var(--accent);"></i> Close Budget Plan
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmFinanceCloseBP" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <p id="financeCloseBPText" class="text-muted mb-3"></p>
                    <div class="alert alert-warning mb-3" style="font-size:12px;">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Status langsung menjadi <strong>CLOSED</strong> tanpa melewati tahap Closing.
                        Setelah ditutup, tidak ada Cost Request baru yang bisa dibebankan ke budget ini.
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Alasan <span class="text-danger">*</span></label>
                        <textarea name="notes" class="form-control" rows="3"
                                  placeholder="Tuliskan alasan penutupan budget..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-lock mr-1"></i> Close
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL TAMBAH COA — ACTIVE (nilai budget tetap, baris baru bernilai 0) --}}
@if(!empty($coaAccounts))
<div class="modal fade" id="mdlAddCoaBP" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-plus mr-2" style="color:var(--accent);"></i> Tambah COA
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmAddCoaBP" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <p id="addCoaBPText" class="text-muted mb-3"></p>
                    <div class="alert alert-info mb-3" style="font-size:12px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        Baris COA baru ditambahkan dengan <strong>Harga Satuan 0</strong> dan <strong>Qty 0</strong>,
                        sehingga nilai budget tidak bertambah. Cost Request yang dibebankan ke COA ini akan tercatat NOB.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-2" id="tblAddCoaBP">
                            <thead style="background:var(--surface-2);">
                                <tr>
                                    <th style="width:30%;">Account <span class="text-danger">*</span></th>
                                    <th>Sub COA / Deskripsi <span class="text-danger">*</span></th>
                                    <th class="text-right" style="width:12%;">Harga Satuan</th>
                                    <th class="text-right" style="width:8%;">Qty</th>
                                    <th style="width:15%;">Unit <span class="text-danger">*</span></th>
                                    <th style="width:40px;"></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAddCoaRowBP">
                        <i class="fas fa-plus mr-1"></i> Tambah Baris
                    </button>
                    <div class="form-group mb-0 mt-3">
                        <label class="font-weight-600">Notes</label>
                        <textarea name="notes" class="form-control" rows="4" maxlength="2000"
                                  placeholder="Keterangan penambahan COA (tercatat di Log Budget Plan)..."></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Simpan COA
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

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

@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
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
/* Detail Budget Plan sebagai panel samping (menempel di kanan, tinggi penuh) —
   markup & JS modal tetap; hanya posisinya yang berubah. Ditulis dengan ID supaya
   menang atas .modal-dialog-xxl dan aturan modal di tesys-ui-luxe.css. Panel gelap
   ringkasan ditumpuk di atas (2 kolom), tab di bawahnya; seluruh badan menggulir. */
#mdlDetailBP .modal-dialog {
    position:absolute; top:0; right:0; bottom:0; margin:0;
    width:56%; min-width:640px; max-width:100%; height:100%; min-height:0;
    display:block; transition:transform .25s ease;
}
#mdlDetailBP.fade .modal-dialog { transform:translateX(100%); }
#mdlDetailBP.show .modal-dialog { transform:none; }
#mdlDetailBP .modal-content { height:100%; max-height:100%; border-radius:0 !important; }
/* Gaya terang seperti drawer Cost Request: kepala putih bergaris bawah (kode kecil di
   atas judul, tombol tutup), tab, lalu isi; kartu ringkasan angka di tab Info dan kaki
   berisi aksi dokumen. Warna panel gelap (tesys-ui-luxe.css bagian 10) dibalik di
   lingkup modal ini saja. */
#mdlDetailBP .modal-body.dm-body { overflow-y:auto; display:block; background:var(--surface) !important; }
#mdlDetailBP .bp-modal-flex { flex-direction:column; height:auto; min-height:100%; }
#mdlDetailBP .bp-sidebar {
    width:100%; flex:0 0 auto; min-height:0; display:block; align-self:auto;
    background:var(--surface) !important; border-radius:0; border-bottom:1px solid var(--border);
    padding:20px 24px 14px;
}
#mdlDetailBP .bp-sidebar-spacer { display:none; }
#mdlDetailBP .bp-sidebar .dm-head { margin:0; padding-right:34px; }
#mdlDetailBP .bp-sidebar .dm-head .dm-code { color:var(--text-faint); letter-spacing:.02em; font-size:11.5px; margin-bottom:3px; }
#mdlDetailBP .bp-sidebar .dm-head .dm-title { color:var(--text); font-size:18px; margin:0; }
#mdlDetailBP .bp-sidebar .dm-head .dm-close { top:0; right:0; color:var(--text-muted) !important; }
#mdlDetailBP .bp-sidebar .dm-head .dm-close:hover { background:var(--surface-2); color:var(--text) !important; }
#mdlDetailBP .bp-main-panel { overflow:visible; max-height:none; padding:16px 24px 24px; }

/* Kartu ringkasan di tab Info (pola kartu "Nilai pengajuan"). */
#mdlDetailBP .bp-figcard { border:1px solid var(--border); border-radius:var(--radius-lg); padding:16px 18px; background:var(--surface); }
#mdlDetailBP .bp-fig-label { font-size:12px; color:var(--text-dim); }
#mdlDetailBP .bp-fig-big { font-size:28px; line-height:1.15; font-weight:700; color:var(--text); font-variant-numeric:tabular-nums; margin:2px 0 8px; }
#mdlDetailBP .bp-fig-chips { display:flex; flex-wrap:wrap; gap:6px; align-items:center; margin-bottom:10px; }
#mdlDetailBP .bp-fig-chips:empty { display:none; }
#mdlDetailBP .bp-fig-sub { display:flex; flex-wrap:wrap; gap:6px 32px; }
#mdlDetailBP .bp-fig-val { font-size:13px; font-weight:600; color:var(--text); font-variant-numeric:tabular-nums; }
#mdlDetailBP .bp-fig-bar { height:5px; background:var(--surface-2); border-radius:var(--radius-pill); margin-top:12px; overflow:hidden; }
#mdlDetailBP .bp-fig-val.dm-calc-neg { color:var(--danger) !important; }
#mdlDetailBP .bp-fig-val.dm-calc-pos { color:var(--success) !important; }
#mdlDetailBP .bp-fig-val.dm-calc-zero { color:var(--text-dim) !important; }
#mdlDetailBP .bp-kv-box { display:contents; }
#mdlDetailBP .ts-dw-section-title { margin-top:18px; }

/* Kaki: aksi dokumen di kiri, garis atas tipis (tesys-ui-luxe.css membuat kaki modal tanpa garis). */
#mdlDetailBP .modal-footer.bp-dm-foot {
    justify-content:flex-start; background:var(--surface) !important;
    border-top:1px solid var(--border) !important; padding:12px 24px !important;
}
@media (max-width:640px) {
    #mdlDetailBP .modal-dialog { width:100%; min-width:0; }
}
#bpDetailBody tr.bp-group-row,
#bpDetailBody tr.bp-group-row td { background:var(--info-bg) !important; }
#bpDetailBody tr.bp-group-row:hover,
#bpDetailBody tr.bp-group-row:hover td { background:var(--surface-3) !important; }
.bp-caret.rotated { transform:rotate(90deg); }
#bpDetailBody td.bp-indent { padding-left:48px !important; }
/* Semua sel tabel Budget rata tengah secara vertikal (sebelumnya teks
   menempel di atas saat baris lebih tinggi dari isinya). */
#bpDetailBody td, #bpDetailBody th { vertical-align:middle; }

/* Deskripsi panjang: biar membungkus 2-3 baris dan tetap muat di modal,
   bukan memaksa tabel melebar. */
.bp-wrap {
    white-space:normal; word-break:break-word; overflow-wrap:anywhere;
    display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical;
    overflow:hidden; line-height:1.3;
}
#bpDetailBody td.bp-indent { max-width:0; }
#bpDetailBody td.bp-indent .bp-wrap { max-width:100%; }
/* Inner table grup CoA pada tab Cost */
.bp-cost-inner td.bp-leaf { white-space:normal; word-break:break-word; overflow-wrap:anywhere; }
.bp-cost-inner tr.bp-coa-grp { cursor:pointer; }
.bp-cost-inner tr.bp-coa-grp td { background:var(--info-bg); }
.bp-cost-inner tr.bp-coa-grp:hover td { background:var(--surface-3); }
.bp-coa-caret.rotated { transform:rotate(90deg); }

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
.bp-chat-av { overflow:hidden; }
.bp-chat-av img { width:100%; height:100%; object-fit:cover; }
.bp-chat-bubble { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:8px 12px; font-size:12px; }
.bp-chat-row.mine .bp-chat-bubble { border-color:var(--border); }
.bp-chat-meta { font-size:10px; color:var(--text-faint); margin-bottom:4px; }
#tblBudgetPlan th.dt-col-check, #tblBudgetPlan td.dt-col-check { width:40px; text-align:center; }
.bp-log-form { display:flex; gap:8px; align-items:flex-end; margin-top:16px; padding:12px 0; border-top:1px solid var(--border); position:sticky; bottom:0; background:var(--surface); }
.bp-log-form textarea { font-size:12px; resize:none; flex:1 1 auto; }
.bp-log-closed { margin-top:16px; padding-top:12px; border-top:1px solid var(--border); font-size:11.5px; color:var(--text-faint); }
#bpPaneLogs.active { display:flex; flex-direction:column; }
#bpPaneLogs #bpLogBody { margin-top:auto; }
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
    /* Expand All / Collapse All — satu tombol toggle (B.1). Label & ikon
       mengikuti keadaan: kalau masih ada grup tertutup -> aksi "Expand All". */
    $(document).on('click', '#btnToggleAllBpDetail', function () {
        var $btn = $(this);
        var anyCollapsed = $('#bpDetailBody .bp-group-row').toArray().some(function (row) {
            var target = $(row).data('target');
            return $('#bpDetailBody .' + target).first().is(':hidden');
        });
        $('#bpDetailBody .bp-group-row').each(function () {
            var target = $(this).data('target');
            if (anyCollapsed) {
                $('#bpDetailBody .' + target).show();
                $(this).find('.bp-crctrl').show();
                $(this).find('.bp-caret').addClass('rotated');
            } else {
                $('#bpDetailBody .' + target).hide();
                $('#bpDetailBody .' + target + '-crall').hide();
                $(this).find('.bp-crctrl').hide();
                $(this).find('.bp-caret').removeClass('rotated');
            }
        });
        if (!anyCollapsed) resetBpGroupCrState($('#bpDetailBody'));
        $btn.html(anyCollapsed
            ? '<i class="fas fa-angle-double-up mr-1"></i>Collapse All'
            : '<i class="fas fa-angle-double-down mr-1"></i>Expand All');
    });
    $(document).on('click', '.bp-cr-toggle', function (e) {
        e.stopPropagation();
        var target = $(this).data('target');
        $('#bpDetailBody .' + target).toggle();
        $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
    });

    /* Tab Budget — renderer pivot (grup per CoA), dipanggil ulang saat
       pencarian/sortir berubah. */
    window.renderBpBudget = function () {
        var st = window._bpBudget; if (!st) return;
        var moneyFmt = function (v) { return Math.round(v || 0).toLocaleString('id-ID'); };
        var q = (st.search || '').toLowerCase().trim();
        var NOB_RED = 'var(--danger)', NOB_YELLOW = 'var(--warning)', OK_GREEN = 'var(--success)';
        var nobBadge = '<span class="badge badge-danger ml-1" style="font-size:9px;">NOB</span>';

        function matchDet(det) {
            if (!q) return true;
            var hay = (det.account || '') + ' ' + (det.deskripsi || '');
            (det.cr_details || []).forEach(function (cr) {
                hay += ' ' + (cr.notes || '') + ' ' + (cr.beneficiary || '');
            });
            return hay.toLowerCase().indexOf(q) !== -1;
        }

        // Kelompokkan per Account
        var groups = {}, groupOrder = [];
        (st.rows || []).filter(matchDet).forEach(function (det) {
            var acc = det.account || '-';
            if (!groups[acc]) { groups[acc] = []; groupOrder.push(acc); }
            groups[acc].push(det);
        });

        // Agregat per grup untuk sortir
        var aggr = {};
        groupOrder.forEach(function (acc) {
            var b = 0, u = 0, r = 0;
            groups[acc].forEach(function (det) { b += det.total; u += det.used; r += det.remaining; });
            aggr[acc] = { budget: b, used: u, variance: r };
        });
        var sk = st.sort.key, dir = st.sort.dir === 'desc' ? -1 : 1;
        groupOrder.sort(function (a, b) {
            if (sk === 'account') return a.localeCompare(b) * dir;
            return (aggr[a][sk] - aggr[b][sk]) * dir;
        });

        var detHtml = '', totalBudget = 0, totalUsed = 0, totalRemaining = 0;
        if (groupOrder.length) {
            groupOrder.forEach(function (acc, gi) {
                var items = groups[acc];
                var gBudget = aggr[acc].budget, gUsed = aggr[acc].used, gRemaining = aggr[acc].variance;
                var anyItemNob = items.some(function (det) { return det.used > det.total; });
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
                        ? '<button type="button" class="btn btn-outline-secondary bp-cr-toggle" data-target="' + crBlockClass + '-row" style="font-size:10px;padding:0 9px;height:22px;line-height:20px;box-sizing:border-box;margin:-4px 0;white-space:nowrap;">' +
                            crDetails.length + ' CR <i class="fas fa-chevron-down ml-1"></i></button>'
                        : '';

                    detHtml += '<tr class="bp-group-child ' + groupClass + '" style="display:none;' + rowColor + '">' +
                        '<td class="bp-indent">' +
                            '<div class="d-flex justify-content-between align-items-center" style="gap:8px;">' +
                                '<span class="bp-wrap">' + (det.deskripsi || '-') + (isNobItem ? nobBadge : '') + '</span>' +
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
                            var settleBadge = cr.is_settle ? ' <span class="badge badge-success" style="font-size:8px;">Settle</span>' : '';
                            return '<tr>' +
                                '<td class="text-nowrap">' + cr.cr_date_formatted + '</td>' +
                                '<td><span class="bp-wrap">' + (cr.notes || '-') + settleBadge + '</span></td>' +
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
            detHtml = '<tr><td colspan="6" class="text-center text-muted py-3">' + (q ? 'Tidak ada yang cocok.' : 'No detail yet.') + '</td></tr>';
        }
        $('#bpDetailBody').html(detHtml);
        $('#bpDetailTotal').text(moneyFmt(totalBudget));
        $('#bpActualTotal').text(moneyFmt(totalUsed));
        var remTotalColor = totalRemaining >= 0 ? 'var(--success)' : 'var(--danger)';
        $('#bpGapTotal').html('<span style="color:' + remTotalColor + ';">' + moneyFmt(totalRemaining) + '</span>');

        // reset tombol toggle-all (semua grup kembali tertutup setelah render)
        $('#btnToggleAllBpDetail').html('<i class="fas fa-angle-double-down mr-1"></i>Expand All');

        // indikator arah sortir
        $('#bpPaneDetails .bp-bsort').each(function () {
            var ic = $(this).find('.bp-bsort-ic');
            if ($(this).data('sort') === st.sort.key) {
                ic.removeClass('fa-sort fa-sort-up fa-sort-down').addClass(st.sort.dir === 'desc' ? 'fa-sort-down' : 'fa-sort-up');
            } else {
                ic.removeClass('fa-sort-up fa-sort-down').addClass('fa-sort');
            }
        });
    };
    $(document).on('input', '#bpBudgetSearch', function () {
        if (!window._bpBudget) return;
        window._bpBudget.search = $(this).val();
        window.renderBpBudget();
    });
    $(document).on('click', '#bpPaneDetails .bp-bsort', function () {
        if (!window._bpBudget) return;
        var key = $(this).data('sort');
        var st = window._bpBudget;
        if (st.sort.key === key) { st.sort.dir = (st.sort.dir === 'asc') ? 'desc' : 'asc'; }
        else { st.sort.key = key; st.sort.dir = 'asc'; }
        window.renderBpBudget();
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
    /* Cost: collapse/expand grup CoA di dalam rincian CR */
    $(document).on('click', '#bpRequestBody .bp-coa-grp', function () {
        var target = $(this).data('target');
        $(this).find('.bp-coa-caret').toggleClass('rotated');
        $(this).closest('table').find('tr.' + target).toggle();
    });

    /* Tab Cost: renderer (grup CR -> CoA -> leaf), dipanggil ulang saat
       pencarian/sortir berubah. */
    window.renderBpCost = function () {
        var st = window._bpCost; if (!st) return;
        var moneyFmt = function (v) { return Math.round(v || 0).toLocaleString('id-ID'); };
        var q = (st.search || '').toLowerCase().trim();

        var rows = st.rows.filter(function (cr) {
            if (!q) return true;
            var hay = (cr.code || '') + ' ' + (cr.description || '') + ' ' + (cr.type || '') + ' ' + (cr.beneficiary || '');
            (cr.coa_groups || []).forEach(function (g) {
                hay += ' ' + (g.account || '');
                (g.rows || []).forEach(function (r) { hay += ' ' + (r.sub_coa || '') + ' ' + (r.description || ''); });
            });
            return hay.toLowerCase().indexOf(q) !== -1;
        });

        var sk = st.sort.key, dir = st.sort.dir === 'desc' ? -1 : 1;
        rows = rows.slice().sort(function (a, b) {
            if (sk === 'description' || sk === 'status') {
                var sa = (a[sk] || '').toLowerCase(), sb = (b[sk] || '').toLowerCase();
                return sa < sb ? -1 * dir : (sa > sb ? 1 * dir : 0);
            }
            var va = (sk === 'date') ? a._idx : (a[sk] || 0);
            var vb = (sk === 'date') ? b._idx : (b[sk] || 0);
            return (va - vb) * dir;
        });

        var html = '', tReq = 0, tReal = 0, tRef = 0, tClaim = 0, tEff = 0;
        if (rows.length) {
            rows.forEach(function (cr) {
                tReq += cr.amount || 0; tReal += cr.realization || 0;
                tRef += cr.refund || 0; tClaim += cr.expense_claim || 0; tEff += cr.effective || 0;
                var reqClass = 'bp-req-' + cr._idx;
                html += '<tr class="bp-req-row" data-target="' + reqClass + '">' +
                    '<td><i class="fas fa-chevron-right bp-req-caret"></i></td>' +
                    '<td><div style="font-weight:600;">' + (cr.description || '-') + '</div>' +
                        '<small class="text-muted">' + (cr.code || '-') + ' &bull; ' + (cr.type || '-') + ' &bull; ' + (cr.beneficiary || '-') + '</small></td>' +
                    '<td class="text-right text-nowrap"><strong>' + cr.amount_formatted + '</strong></td>' +
                    '<td class="text-right text-nowrap">' + ((cr.refund || 0) > 0 ? cr.refund_formatted : '<span class="text-muted">-</span>') + '</td>' +
                    '<td class="text-right text-nowrap">' + ((cr.expense_claim || 0) > 0 ? cr.expense_claim_formatted : '<span class="text-muted">-</span>') + '</td>' +
                    '<td class="text-right text-nowrap"><strong>' + cr.effective_formatted + '</strong></td>' +
                    '<td class="text-nowrap">' + cr.date + '</td>' +
                    '<td><span class="badge badge-' + (st.statusColors[cr.status] || 'secondary') + ' px-2 py-1" style="font-size:10px;">' + (cr.status_label || cr.status) + '</span></td>' +
                '</tr>';

                var inner = '';
                (cr.coa_groups || []).forEach(function (g, gi) {
                    var leafClass = 'bp-ca-leaf-' + cr._idx + '-' + gi;
                    inner += '<tr class="bp-coa-grp" data-target="' + leafClass + '" style="cursor:pointer;">' +
                        '<td style="font-weight:700;">' +
                            '<i class="fas fa-chevron-right mr-2 bp-coa-caret" style="font-size:9px;transition:transform .15s;display:inline-block;"></i>' +
                            (g.account || '-') + ' <span class="badge badge-secondary ml-1" style="font-size:8px;">' + (g.rows || []).length + '</span>' +
                        '</td>' +
                        '<td></td>' +
                        '<td class="text-right text-nowrap" style="font-weight:700;">' + g.effective_formatted + '</td></tr>';
                    (g.rows || []).forEach(function (r) {
                        var strike = r.is_takeout ? 'text-decoration:line-through;color:var(--text-faint);' : '';
                        var tbadge = r.is_takeout ? ' <span class="badge badge-warning" style="font-size:8px;">Takeout &middot; ' + (r.takeout_stage_label || '-') + '</span>' : '';
                        // Sub CoA & deskripsi sering identik -> tampilkan sekali saja.
                        var subcoa = (r.sub_coa || '').trim();
                        var desc   = (r.description || '').trim();
                        var label  = (subcoa && desc && subcoa.toLowerCase() === desc.toLowerCase())
                            ? (desc || '-')
                            : ((subcoa ? '<span class="text-muted">' + subcoa + '</span> &mdash; ' : '') + (desc || '-'));
                        inner += '<tr class="' + leafClass + '" style="display:none;"><td class="bp-leaf" style="' + strike + '">' +
                            label + tbadge +
                            (r.notes ? '<div><small class="text-muted" style="text-decoration:none;">' + r.notes + '</small></div>' : '') +
                            '</td><td class="text-right text-nowrap" style="' + strike + '">' + r.effective_formatted + '</td>' +
                            '<td></td></tr>';
                    });
                });
                if (!inner) inner = '<tr><td colspan="3" class="text-muted">No item detail.</td></tr>';
                html += '<tr class="' + reqClass + '" style="display:none;"><td></td>' +
                    '<td colspan="7" style="background:var(--surface-2);padding:8px 12px;">' +
                        '<table class="table table-sm table-bordered mb-0 bp-cost-inner" style="font-size:11px;">' +
                            '<colgroup><col><col style="width:18%;"><col style="width:18%;"></colgroup>' +
                            '<tbody>' + inner + '</tbody></table>' +
                    '</td></tr>';
            });
        } else {
            html = '<tr><td colspan="8" class="text-center text-muted py-3">' + (q ? 'Tidak ada yang cocok.' : 'No linked Cost Request yet.') + '</td></tr>';
        }
        $('#bpRequestBody').html(html);
        $('#bpRequestTotal').text(moneyFmt(tReq));
        $('#bpRefundTotal').text(moneyFmt(tRef));
        $('#bpClaimTotal').text(moneyFmt(tClaim));
        $('#bpEffectiveTotal').text(moneyFmt(tEff));

        // indikator arah sortir pada header
        $('#bpPaneRequests .bp-sort').each(function () {
            var ic = $(this).find('.bp-sort-ic');
            if ($(this).data('sort') === st.sort.key) {
                ic.removeClass('fa-sort fa-sort-up fa-sort-down').addClass(st.sort.dir === 'desc' ? 'fa-sort-down' : 'fa-sort-up');
            } else {
                ic.removeClass('fa-sort-up fa-sort-down').addClass('fa-sort');
            }
        });
    };
    $(document).on('input', '#bpCostSearch', function () {
        if (!window._bpCost) return;
        window._bpCost.search = $(this).val();
        window.renderBpCost();
    });
    $(document).on('click', '#bpPaneRequests .bp-sort', function () {
        if (!window._bpCost) return;
        var key = $(this).data('sort');
        var st = window._bpCost;
        if (st.sort.key === key) { st.sort.dir = (st.sort.dir === 'asc') ? 'desc' : 'asc'; }
        else { st.sort.key = key; st.sort.dir = 'asc'; }
        window.renderBpCost();
    });

    /* Cash Advance BP: expand/collapse per PIC untuk lihat rincian CA-nya */
    $(document).on('click', '#bpCashAdvanceBody .bp-req-row', function () {
        var target = $(this).data('target');
        $(this).find('.bp-req-caret').toggleClass('rotated');
        $('#bpCashAdvanceBody .' + target).toggle();
    });

    /* ── PENAMPIL LAMPIRAN — tombol View gambar tab Attachment (komponen bersama tesys-evidence-stage.js) ── */
    $(document).on('click', '.bp-att-view-img', function () {
        var $grup = $('#bpAttBody .bp-att-view-img');
        var daftar = $grup.map(function () {
            return { url: $(this).data('full'), name: $(this).data('name') };
        }).get();
        var mulai = $grup.index(this);
        TesysEvidenceStage.open(daftar, mulai < 0 ? 0 : mulai);
    });

    /* DATATABLE */
    var BP_ADA_CEK = {{ $bolehBulkBP ? 'true' : 'false' }};
    var BP_OFF = BP_ADA_CEK ? 1 : 0;
    var table = $('#tblBudgetPlan').DataTable({
        responsive: { details: { type: 'inline' } },
        autoWidth: false, pageLength: 10, lengthChange: true, searching: true,
        order: [], // pertahankan urutan asli dari server (input terbaru di atas)
        // Kolom centang bulk (bila dirender server) menggeser indeks semua kolom satu langkah.
        columnDefs: [
            { targets: [BP_OFF + 0], visible: false, orderable: false },
            { targets: [BP_OFF + 6], visible: false },
            // Kolom aksi: header kosong, tanpa sortir & tanpa ikut pencarian (pola Cost Request).
            { targets: [-1], orderable: false, searchable: false },
            { responsivePriority: 1,   targets: [BP_OFF + 0, BP_OFF + 1, BP_OFF + 2, -1] },
            { responsivePriority: 2,   targets: [BP_OFF + 4, BP_OFF + 5, BP_OFF + 6, BP_OFF + 7, BP_OFF + 8] },
            { responsivePriority: 3,   targets: [BP_OFF + 3] },
        ].concat(BP_ADA_CEK ? [{ targets: [0], orderable: false, searchable: false, responsivePriority: 1 }] : []),
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
        if (Math.round(v) === 0) return '-'; // nilai nol di kolom uang tabel daftar = "-"
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

    /* ── BULK APPROVE / REJECT (pola Cost Request & Treasury) ────────────────
       Hak centang dihitung server (data-elig). SATU status per proses, maks 50;
       melewati batas = seluruh pilihan dibatalkan + peringatan. Pilihan yang tak
       lagi lolos filter/tab dibuang supaya yang dikirim = yang terlihat. Server
       memproses tiap Budget Plan lewat method approval satuan dan melewati yang
       statusnya sudah berubah. */
    var BP_BULK_MAX = 50;
    var BP_TAHAP = {
        SUBMITTED: { dari: 'Submitted', ke: 'Checked',  tolak: 'Rejected' },
        CHECKED:   { dari: 'Checked',   ke: 'Verified', tolak: 'Rejected' },
        VERIFIED:  { dari: 'Verified',  ke: 'Active',   tolak: 'Rejected' }
    };
    var bpSel = new Set();

    function bpBulkInfo(tr) {
        var $tr = $(tr);
        return {
            id: String($tr.attr('data-id')), status: $tr.attr('data-status'), code: $tr.attr('data-code'),
            total: parseInt($tr.find('.col-raw-amount').attr('data-raw'), 10) || 0
        };
    }
    function bpBulkToast(tipe, pesan) { if (window.tsToast) window.tsToast(tipe, pesan); }
    function bpBulkStatusAktif() {
        var st = null;
        $(table.rows().nodes()).each(function () {
            var inf = bpBulkInfo(this);
            if (bpSel.has(inf.id)) { st = inf.status; return false; }
        });
        return st;
    }
    function bpBulkRefresh() {
        var tampil = new Set(), jml = 0, total = 0;
        table.rows({ search: 'applied' }).every(function () { tampil.add(bpBulkInfo(this.node()).id); });
        bpSel.forEach(function (id) { if (!tampil.has(id)) bpSel.delete(id); });

        $(table.rows().nodes()).each(function () {
            var inf = bpBulkInfo(this);
            $(this).find('.bp-check-row').prop('checked', bpSel.has(inf.id));
            if (bpSel.has(inf.id)) { jml++; total += inf.total; }
        });

        var aktif = bpBulkStatusAktif() || null, kandidat = 0, dipilih = 0;
        if (!aktif) {   // belum ada pilihan: kandidat "pilih semua" = status eligible pertama yang tampil
            table.rows({ search: 'applied' }).every(function () {
                var $n = $(this.node());
                if (!aktif && $n.find('.bp-check-row').attr('data-elig') === '1') aktif = $n.attr('data-status');
            });
        }
        table.rows({ search: 'applied' }).every(function () {
            var $n = $(this.node()), inf = bpBulkInfo(this.node());
            if ($n.find('.bp-check-row').attr('data-elig') === '1' && inf.status === aktif) {
                kandidat++;
                if (bpSel.has(inf.id)) dipilih++;
            }
        });
        var all = document.querySelector('#tblBudgetPlan .bp-check-all');
        if (all) {
            all.checked = kandidat > 0 && dipilih === kandidat;
            all.indeterminate = dipilih > 0 && dipilih < kandidat;
        }
        $('.bp-bulk-bar').toggleClass('d-none', jml === 0).toggleClass('d-flex', jml > 0);
        $('.bp-bulk-info').text(jml + ' dipilih · total Rp ' + Math.round(total).toLocaleString('id-ID'));
    }

    $(document).on('change', '.bp-check-row', function () {
        var id = String(this.value), st = $(this).closest('tr').attr('data-status');
        if (this.checked) {
            var aktif = bpBulkStatusAktif();
            if (aktif && aktif !== st) {
                this.checked = false;
                bpBulkToast('warning', 'Pilih Budget Plan dengan status yang sama dalam satu proses (sekarang: ' + aktif + ').');
                return;
            }
            if (bpSel.size >= BP_BULK_MAX) {
                this.checked = false;
                bpBulkToast('warning', 'Maksimal ' + BP_BULK_MAX + ' Budget Plan per proses. Persempit dengan filter lalu pilih lagi.');
                return;
            }
            bpSel.add(id);
        } else {
            bpSel.delete(id);
        }
        bpBulkRefresh();
    });

    $(document).on('change', '.bp-check-all', function () {
        if (!this.checked) { bpSel.clear(); bpBulkRefresh(); return; }
        var aktif = bpBulkStatusAktif(), gabungan = new Set(bpSel);
        table.rows({ search: 'applied' }).every(function () {
            var $n = $(this.node());
            if ($n.find('.bp-check-row').attr('data-elig') !== '1') return;
            if (!aktif) aktif = $n.attr('data-status');
            if ($n.attr('data-status') === aktif) gabungan.add(String($n.attr('data-id')));
        });
        if (gabungan.size > BP_BULK_MAX) {
            bpSel.clear(); bpBulkRefresh();
            bpBulkToast('warning', 'Maksimal ' + BP_BULK_MAX + ' Budget Plan per proses. Pilihan dibatalkan; persempit dengan filter lalu pilih lagi.');
            return;
        }
        gabungan.forEach(function (id) { bpSel.add(id); });
        bpBulkRefresh();
    });

    $(document).on('click', '.bp-bulk-clear', function () { bpSel.clear(); bpBulkRefresh(); });
    table.on('draw', bpBulkRefresh);

    var bpBulkKirim = false;
    $(document).on('click', '.bp-bulk-approve, .bp-bulk-reject', function () {
        if (bpBulkKirim) return;
        var tolak = $(this).hasClass('bp-bulk-reject'), $btn = $(this);
        var rows = [], total = 0, status = null;
        $(table.rows().nodes()).each(function () {
            var inf = bpBulkInfo(this);
            if (bpSel.has(inf.id)) { rows.push(inf); total += inf.total; status = inf.status; }
        });
        if (!rows.length || !BP_TAHAP[status]) return;
        var th = BP_TAHAP[status];

        var html = '<div style="text-align:left;font-size:13px;line-height:1.7;">'
            + '<div><strong>' + rows.length + ' Budget Plan</strong>: ' + th.dari + ' &rarr; ' + (tolak ? th.tolak : th.ke) + '</div>'
            + '<div>Total Budget: <strong>Rp ' + Math.round(total).toLocaleString('id-ID') + '</strong></div>'
            + '</div>';

        var opsi = tolak
            ? { title: 'Tolak ' + rows.length + ' Budget Plan?', icon: 'warning',
                html: html + '<div class="mt-2" style="text-align:left;font-size:12px;">Alasan penolakan wajib diisi:</div>',
                input: 'textarea', inputAttributes: { maxlength: 500, 'aria-label': 'Alasan penolakan' },
                inputValidator: function (v) { if (!v || !v.trim()) return 'Alasan penolakan wajib diisi.'; },
                confirmButtonColor: WARNA_BAHAYA, confirmButtonText: 'Ya, Tolak' }
            : { title: 'Approve ' + rows.length + ' Budget Plan?', icon: 'question', html: html,
                confirmButtonColor: WARNA_UTAMA, confirmButtonText: 'Ya, Approve' };
        opsi.showCancelButton = true;
        opsi.cancelButtonColor = WARNA_BATAL;
        opsi.cancelButtonText = 'Batal';

        Swal.fire(opsi).then(function (r) {
            if (!r.isConfirmed || bpBulkKirim) return;
            bpBulkKirim = true;
            var $f = $('<form method="POST"></form>').attr('action', tolak
                ? '{{ route('budget-plan.bulk-reject') }}' : '{{ route('budget-plan.bulk-approve') }}');
            $f.append($('<input type="hidden" name="_token">').val($('meta[name="csrf-token"]').attr('content')));
            $f.append($('<input type="hidden" name="status">').val(status));
            rows.forEach(function (x) { $f.append($('<input type="hidden" name="ids[]">').val(x.id)); });
            if (tolak) $f.append($('<input type="hidden" name="notes">').val(r.value));
            $('.bp-bulk-bar button').prop('disabled', true);
            if (window.TesysUI && TesysUI.busy) TesysUI.busy($btn[0], true);
            $('body').append($f);
            $f[0].submit();
        });
    });

    // Kembali lewat tombol Back (bfcache): kunci kirim dilepas lagi.
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        bpBulkKirim = false;
        $('.bp-bulk-bar button').prop('disabled', false).each(function () {
            if (window.TesysUI && TesysUI.busy) TesysUI.busy(this, false);
        });
    });

    /* Klik di mana saja pada baris membuka modal detail (pola Cost Request). Kontrol
       di dalam baris (kode yang disalin, tombol nilai/status, menu aksi, kontrol
       responsif DataTables) tetap bekerja sendiri. */
    $(document).on('click', '#tblBudgetPlan tbody tr[data-id]', function (e) {
        if ($(e.target).closest('a, button, input, select, textarea, label, form, .ts-actions, .ts-action-menu, td.dtr-control, .dt-col-check').length) return;
        if (window.getSelection && String(window.getSelection()).length) return;
        openBPDetail($(this).data('id'));
    });

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

            // Budget dari form PO diubah lewat Edit PO, bukan form Budget Plan.
            if (d.status === 'DRAFT' && !d.via_po_approval) {
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
                    line('Budget', b.budget_value_formatted || '0', 'var(--info)') +
                    line('Request', b.request_formatted || '0') +
                    line('Refund', b.refund_formatted || '0', 'var(--warning)') +
                    line('Expense Claim', b.expense_claim_formatted || '0', 'var(--danger)') +
                    line('Actual Used', b.actual_used_formatted || '0', 'var(--success)', true)
                );
            })();

            /* Cash Advance — dikelompokkan per PIC, expand/collapse untuk lihat
               rincian tiap Cost Request (pola sama seperti Tab Cost di bawah). */
            (function renderBpCashAdvance() {
                var ca     = d.cash_advance || {};
                var groups = ca.groups || [];
                $('#bpCaCount').text(ca.total || 0);

                function line(label, val, color, bold) {
                    return '<div class="bp-gc-row' + (bold ? ' bold' : '') + '">' +
                        '<span>' + label + '</span><span' + (color ? ' style="color:' + color + '"' : '') + '>' + val + '</span></div>';
                }
                $('#bpCaSummary').html(
                    line('Diajukan', ca.total || 0, '', true) +
                    line('Sudah Settle', ca.settled || 0, 'var(--success)') +
                    line('Belum Settle', ca.pending || 0, 'var(--warning)') +
                    ((ca.rejected || 0) > 0 ? line('Ditolak / Batal', ca.rejected, 'var(--danger)') : '')
                );

                var caStatusColors = {
                    'SUBMITTED':'warning','CHECKED':'info','VERIFIED':'primary','APPROVED':'info',
                    'QUEUED':'warning','PAID':'success','REALIZED':'success',
                    'SETTLE_CHECKED':'info','SETTLE_VERIFIED':'primary','SETTLE_APPROVED':'success',
                    'SECOND_APPROVED':'success','CLOSED':'dark','REJECTED':'danger','CANCELLED':'dark'
                };

                var caHtml = '';
                if (groups.length) {
                    groups.forEach(function (g, gi) {
                        var gClass = 'bp-ca-' + gi;
                        caHtml += '<tr class="bp-req-row" data-target="' + gClass + '" style="background:var(--surface-2);">' +
                            '<td><i class="fas fa-chevron-right bp-req-caret"></i></td>' +
                            '<td colspan="3">' +
                                '<strong>' + (g.pic_name || '-') + '</strong> ' +
                                '<span class="badge badge-secondary ml-1" style="font-size:9px;">' + g.total + ' CA</span>' +
                                ((g.settled || 0) > 0 ? ' <span class="badge badge-success" style="font-size:9px;">' + g.settled + ' settle</span>' : '') +
                                ((g.pending || 0) > 0 ? ' <span class="badge badge-warning" style="font-size:9px;">' + g.pending + ' belum</span>' : '') +
                            '</td>' +
                            '<td></td>' +
                            '<td class="text-right text-nowrap"><strong>' + g.value_formatted + '</strong></td>' +
                        '</tr>';

                        (g.items || []).forEach(function (cr) {
                            caHtml += '<tr class="' + gClass + '" style="display:none;">' +
                                '<td></td>' +
                                '<td class="bp-req-indent">' +
                                    '<div style="font-weight:600;">' + cr.code + '</div>' +
                                    '<small class="text-muted">' + cr.description + ' &bull; ' + (cr.beneficiary || '-') + '</small>' +
                                '</td>' +
                                '<td><span class="badge badge-' + (caStatusColors[cr.status] || 'secondary') + ' px-2 py-1" style="font-size:10px;">' + cr.status_label + '</span></td>' +
                                '<td class="text-nowrap">' + (cr.paid_date || '-') + '</td>' +
                                '<td class="text-center text-nowrap">' + (cr.aging !== null ? cr.aging + ' hari' : '-') + '</td>' +
                                '<td class="text-right text-nowrap">' + cr.amount_formatted + '</td>' +
                            '</tr>';
                        });
                    });
                } else {
                    caHtml = '<tr><td colspan="6" class="text-center text-muted py-3">Belum ada Cash Advance.</td></tr>';
                }
                $('#bpCashAdvanceBody').html(caHtml);
                $('#bpCaValueTotal').text(Math.round(ca.total_value || 0).toLocaleString('id-ID'));
            })();

            /* Tab Budget — pivot grup per Account, expand/collapse + pencarian +
               sortir grup. State di window agar handler bisa re-render. */
            $('#bpDetailCount').text((d.details || []).length);
            $('#bpExportBudgetBtn').attr('href', '/budget-plan/' + d.id + '/export-budget');
            $('#bpBudgetSearch').val('');
            window._bpBudget = {
                rows: d.details || [],
                sort: { key: 'account', dir: 'asc' },
                search: ''
            };
            window.renderBpBudget();

            /* Tab Cost — daftar Cost Request, grup CR -> CoA -> Sub CoA/Detail
               dengan effective cost. Mendukung pencarian & sortir kolom; state
               disimpan di window agar handler search/sort bisa re-render. */
            var crStatusColors = {
                'DRAFT':'secondary','SUBMITTED':'warning','CHECKED':'info','APPROVED':'info',
                'VERIFIED':'primary','QUEUED':'warning','PAID':'success',
                'REALIZED':'success','CLOSED':'dark','REJECTED':'danger','CANCELLED':'dark'
            };
            $('#bpRequestCount').text((d.requests || []).length);
            $('#bpExportCostBtn').attr('href', '/budget-plan/' + d.id + '/export-cost');

            // Index asli = urutan backend (tanggal terbaru dulu), dasar sort 'date'.
            (d.requests || []).forEach(function (cr, i) { cr._idx = i; });
            window._bpCost = {
                rows: d.requests || [],
                statusColors: crStatusColors,
                sort: { key: 'date', dir: 'asc' },
                search: ''
            };
            $('#bpCostSearch').val('');
            window.renderBpCost();

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
                d.logs.slice().reverse().forEach(function (log) { logHtml += renderBpLogRow(log); });
            } else {
                logHtml = '<p class="text-muted py-3 mb-0 bp-log-empty">No log yet.</p>';
            }
            $('#bpLogBody').html(logHtml);
            // Kotak catatan hanya selama Budget Plan belum Closed (server menolak juga).
            var bisaCatat = d.can_post_log_note !== false;
            $('#bpLogForm').prop('hidden', !bisaCatat).data('id', d.id);
            $('#bpLogClosed').prop('hidden', bisaCatat);
            $('#bpLogInput').val('');

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

    /* ── TAB LOG GAYA CHAT (riwayat workflow + catatan bebas) ── */
    function escBp(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }

    function renderBpLogRow(log) {
        var mine = log.user_id && bpCurrentUserId && log.user_id === bpCurrentUserId;
        var isNote = log.kind === 'note';
        var actor = log.user || 'System';
        var initials = actor.trim().split(/\s+/).map(function (w) { return w[0]; }).join('').slice(0, 2).toUpperCase() || '?';
        var isi = isNote
            ? '<div class="bp-chat-note" style="margin-top:0;">' + escBp(log.notes) + '</div>'
            : '<span class="badge badge-' + escBp(log.action_color) + ' px-2 py-1" style="font-size:9px;">' + escBp(log.action_label) + '</span>'
              + (log.status_from && log.status_to ? ' <span style="font-size:10px;opacity:.75;">' + escBp(log.status_from) + ' → ' + escBp(log.status_to) + '</span>' : '')
              + (log.notes ? '<div class="bp-chat-note">' + escBp(log.notes) + '</div>' : '');
        return '<div class="bp-chat-row ' + (mine ? 'mine' : '') + '">'
            + '<div class="bp-chat-av">' + (log.avatar ? '<img src="' + escBp(log.avatar) + '" alt="">' : escBp(initials)) + '</div>'
            + '<div><div class="bp-chat-meta">' + (mine ? '' : '<strong>' + escBp(actor) + '</strong> · ') + escBp(log.role || 'system') + ' · ' + escBp(log.date) + '</div>'
            + '<div class="bp-chat-bubble">' + isi + '</div></div></div>';
    }

    /* Tab Log setinggi area gulir panel supaya chat menempel di bawah walau isinya sedikit. */
    function aturTinggiLogBp(gulirKeBawah) {
        var body = document.querySelector('#mdlDetailBP .modal-body');
        if (!body) return;
        var tinggi = body.clientHeight - $('#mdlDetailBP .bp-sidebar').outerHeight() - $('#bpDetailTabs').outerHeight(true) - 40;
        $('#bpPaneLogs').css('min-height', Math.max(tinggi, 0));
        if (gulirKeBawah) body.scrollTop = body.scrollHeight;
    }
    $(document).on('shown.bs.tab', '#bpDetailTabs a[data-toggle="tab"]', function () {
        if ($(this).attr('href') === '#bpPaneLogs') aturTinggiLogBp(true);
    });

    $(document).on('submit', '#bpLogForm', function (e) {
        e.preventDefault();
        var $f = $(this), $in = $('#bpLogInput'), $btn = $('#bpLogSend');
        var teks = $.trim($in.val());
        if (!teks) { $in.focus(); return; }
        $btn.prop('disabled', true);
        $.post('{{ url('/budget-plan') }}/' + $f.data('id') + '/log-note', { _token: $('meta[name="csrf-token"]').attr('content'), notes: teks })
            .done(function (res) {
                if (!res || !res.log) return;
                $('#bpLogBody .bp-log-empty').remove();
                $('#bpLogBody').append(renderBpLogRow(res.log));
                $in.val('');
                aturTinggiLogBp(true);
            })
            .fail(function (xhr) {
                var pesan = (xhr.responseJSON && (xhr.responseJSON.message || (xhr.responseJSON.errors && xhr.responseJSON.errors.notes && xhr.responseJSON.errors.notes[0])))
                    || 'Catatan belum dapat dikirim. Coba lagi.';
                Swal.fire({ icon: 'error', title: 'Gagal mengirim', text: pesan });
            })
            .always(function () { $btn.prop('disabled', false); });
    });

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
    $(document).on('click', '.btn-finance-close-bp', function () {
        var id = $(this).data('id');
        $('#financeCloseBPText').text('Close budget: ' + ($(this).data('desc') || ''));
        $('#frmFinanceCloseBP').attr('action', '/budget-plan/' + id + '/finance-close');
        $('#frmFinanceCloseBP').find('textarea').val('');
        $('#mdlFinanceCloseBP').modal('show');
    });

    $(document).on('click', '.btn-reject-closing-bp', function () {
        var id = $(this).data('id');
        $('#frmRejectClosingBP').attr('action', '/budget-plan/' + id + '/reject-closing');
        $('#frmRejectClosingBP textarea[name="notes"]').val('');
        $('#mdlRejectClosingBP').modal('show');
    });

    /* ── TAMBAH COA (ACTIVE) — baris baru bernilai 0 ── */
    var COA_ACCOUNTS = @json($coaAccounts ?? []);
    var COA_UNITS    = @json(\App\Models\BudgetPlanDetail::UNITS);
    var coaCategory  = '';
    var coaRowSeq    = 0;

    function coaEsc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }

    function addCoaRow() {
        var i = coaRowSeq++;
        var accOpts = '<option value="">— Pilih Account —</option>' +
            (COA_ACCOUNTS[coaCategory] || []).map(function (a) {
                return '<option value="' + a.id + '">' + coaEsc(a.name) + '</option>';
            }).join('');
        var unitOpts = '<option value="">—</option>' + COA_UNITS.map(function (u) {
            return '<option value="' + coaEsc(u) + '">' + coaEsc(u) + '</option>';
        }).join('');

        var $tr = $('<tr>' +
            '<td><select name="rows[' + i + '][account_id]" class="form-control form-control-sm coa-account">' + accOpts + '</select></td>' +
            '<td><input type="text" name="rows[' + i + '][deskripsi]" class="form-control form-control-sm" maxlength="255" required></td>' +
            '<td><input type="text" class="form-control form-control-sm text-right" value="0" disabled></td>' +
            '<td><input type="text" class="form-control form-control-sm text-right" value="0" disabled></td>' +
            '<td><select name="rows[' + i + '][unit]" class="form-control form-control-sm coa-unit">' + unitOpts + '</select></td>' +
            '<td class="text-center"><button type="button" class="btn btn-sm btn-danger btn-remove-coa-row" title="Hapus baris"><i class="fas fa-trash"></i></button></td>' +
            '</tr>');

        $('#tblAddCoaBP tbody').append($tr);
        if (window.TesysSearchableSelect) {
            // Sama dengan form create: Account & Unit memakai dropdown searchable.
            TesysSearchableSelect.initOne($tr.find('.coa-account'), { placeholder: '— Pilih Account —', searchPlaceholder: 'Cari Account...' });
            TesysSearchableSelect.initOne($tr.find('.coa-unit'), { placeholder: '—', searchPlaceholder: 'Cari Unit...' });
        }
    }

    $(document).on('click', '.btn-add-coa-bp', function () {
        var id = $(this).data('id');
        coaCategory = $(this).data('category') || '';
        $('#addCoaBPText').text($(this).data('code') + ' — ' + ($(this).data('desc') || '') + ' (Account kategori ' + coaCategory + ')');
        $('#frmAddCoaBP').attr('action', '/budget-plan/' + id + '/add-coa');
        $('#tblAddCoaBP tbody').empty();
        $('#frmAddCoaBP textarea[name="notes"]').val('');
        coaRowSeq = 0;
        addCoaRow();
        $('#mdlAddCoaBP').modal('show');
    });

    $('#btnAddCoaRowBP').on('click', addCoaRow);

    $(document).on('click', '.btn-remove-coa-row', function () {
        if ($('#tblAddCoaBP tbody tr').length > 1) $(this).closest('tr').remove();
    });

    // <select> Account & Unit disembunyikan TesysSearchableSelect, jadi atribut
    // required tidak bisa dipakai — dicek di sini (server tetap memvalidasi).
    $('#frmAddCoaBP').on('submit', function (e) {
        var kosong = function (sel) {
            return $('#frmAddCoaBP').find(sel).filter(function () { return !$(this).val(); }).length;
        };
        if (kosong('.coa-account') || kosong('.coa-unit')) {
            e.preventDefault();
            alert('Pilih Account dan Unit di setiap baris.');
        }
    });

});
</script>
@endpush