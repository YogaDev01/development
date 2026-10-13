@extends('layouts.app')

@section('title', 'Cost Request')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Cost Request</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item active">Cost Request</li>
            </ol>
        </div>

        @php
            $n = fn ($counts, $s) => (int) ($counts[$s] ?? 0);

            // Cash Advance: alur penuh sampai Settlement.
            $caSteps = [
                ['Draft',           $n($caCounts, 'DRAFT'),           'secondary', 'DRAFT'],
                ['Submitted',       $n($caCounts, 'SUBMITTED'),       'warning',   'SUBMITTED'],
                ['Checked',         $n($caCounts, 'CHECKED'),         'info',      'CHECKED'],
                ['Verified',        $n($caCounts, 'VERIFIED'),        'primary',   'VERIFIED'],
                ['Approved',        $n($caCounts, 'APPROVED'),        'success',   'APPROVED'],
                ['Process',         $n($caCounts, 'QUEUED'),          'warning',   'QUEUED'],
                ['Paid',            $n($caCounts, 'PAID'),            'success',   'PAID'],
                ['Settle',          $n($caCounts, 'REALIZED'),        'primary',   'REALIZED'],
                ['Settle Checked',  $n($caCounts, 'SETTLE_CHECKED'),  'info',      'SETTLE_CHECKED'],
                ['Settle Verified', $n($caCounts, 'SETTLE_VERIFIED'), 'primary',   'SETTLE_VERIFIED'],
                ['Settle Approved', $n($caCounts, 'SETTLE_APPROVED'), 'success',   'SETTLE_APPROVED'],
                ['Second Approved', $n($caCounts, 'SECOND_APPROVED'), 'success',   'SECOND_APPROVED'],
                ['Closed',          $n($caCounts, 'CLOSED'),          'dark',      'CLOSED'],
            ];

            // Cost Payment: setelah Process langsung Closed (tanpa Settlement).
            $cpSteps = [
                ['Draft',     $n($cpCounts, 'DRAFT'),     'secondary', 'DRAFT'],
                ['Submitted', $n($cpCounts, 'SUBMITTED'), 'warning',   'SUBMITTED'],
                ['Checked',   $n($cpCounts, 'CHECKED'),   'info',      'CHECKED'],
                ['Verified',  $n($cpCounts, 'VERIFIED'),  'primary',   'VERIFIED'],
                ['Approved',  $n($cpCounts, 'APPROVED'),  'success',   'APPROVED'],
                ['Process',   $n($cpCounts, 'QUEUED'),    'warning',   'QUEUED'],
            ];
            // Data lama (sebelum pemisahan alur): non-CA yang masih tertahan di Paid — tampilkan hanya bila ada.
            if ($n($cpCounts, 'PAID') > 0) {
                $cpSteps[] = ['Paid', $n($cpCounts, 'PAID'), 'success', 'PAID'];
            }
            $cpSteps[] = ['Closed', $n($cpCounts, 'CLOSED'), 'dark', 'CLOSED'];
        @endphp

        {{-- Tab + rentang tanggal (filter tanggal berlaku untuk semua tab) --}}
        <div class="d-flex justify-content-between align-items-end flex-wrap mt-2" style="gap:8px;">
            <ul class="nav nav-tabs cr-main-tabs" id="crTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'ca' ? 'active' : '' }}" data-toggle="tab" data-crtab="ca" href="#crPaneCA" role="tab">
                        <i class="fas fa-hand-holding-usd mr-1"></i> Cash Advance
                        <span class="badge badge-secondary ml-1">{{ number_format($caTotal, 0, ',', '.') }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'cp' ? 'active' : '' }}" data-toggle="tab" data-crtab="cp" href="#crPaneCP" role="tab">
                        <i class="fas fa-file-invoice-dollar mr-1"></i> Cost Payment
                        <span class="badge badge-secondary ml-1">{{ number_format($cpTotal, 0, ',', '.') }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'db' ? 'active' : '' }}" data-toggle="tab" data-crtab="db" href="#crPaneDB" role="tab">
                        <i class="fas fa-database mr-1"></i> Database
                        <span class="badge badge-secondary ml-1">{{ number_format($caTotal + $cpTotal, 0, ',', '.') }}</span>
                    </a>
                </li>
            </ul>
            <div class="d-flex align-items-center flex-wrap pb-1" style="gap:8px;">
                <div class="d-flex align-items-center cr-date-range" style="gap:4px;">
                    <input type="text" id="crDateFrom" class="form-control form-control-sm" value="{{ $dateFrom }}" readonly>
                    <span class="text-muted" style="font-size:12px;">s/d</span>
                    <input type="text" id="crDateTo" class="form-control form-control-sm" value="{{ $dateTo }}" readonly>
                </div>
                <button id="btnRefreshCR" class="btn btn-secondary btn-sm">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
            </div>
        </div>

        {{-- Worklist approval per Budget Plan (dulu section "Review Cost Request") sudah
             dipindah ke halaman Todo List Request. --}}
        <div class="tab-content pt-3">
            @foreach([
                'ca' => ['pane' => 'crPaneCA', 'steps' => $caSteps, 'table' => 'tblCA'],
                'cp' => ['pane' => 'crPaneCP', 'steps' => $cpSteps, 'table' => 'tblCP'],
                'db' => ['pane' => 'crPaneDB', 'steps' => null,     'table' => 'tblDB'],
            ] as $tabKey => $cfg)
            <div class="tab-pane fade {{ $activeTab === $tabKey ? 'show active' : '' }} cr-pane" id="{{ $cfg['pane'] }}" data-tab="{{ $tabKey }}" role="tabpanel">

                @if($cfg['steps'])
                {{-- Angka stepper dihitung di server (query agregat) — tampil segera walau tabel belum dimuat. --}}
                <x-workflow-stepper :steps="$cfg['steps']" :compact="true" :table="$cfg['table']" field="status" />
                @endif

                <div class="card card-primary card-outline shadow-sm mt-3">
                    <div class="card-body">
                        @include('budget-request._toolbar', ['tab' => $tabKey])

                        {{-- Diisi AJAX saat tab pertama kali dibuka (data baru dimuat hanya bila tab dibuka). --}}
                        <div class="cr-tab-host" data-tab="{{ $tabKey }}"
                             data-url="{{ url('/budget-request/tab/' . $tabKey) }}">
                            <x-skeleton type="table" :rows="8" :cols="6" />
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ================================================================ --}}
{{-- DRAWER TINJAU CR                                                   --}}
{{-- Permukaan tinjau cepat: status + konteks, ringkasan, timeline,     --}}
{{-- lampiran, dan aksi. Memakai endpoint detail-json yang sudah ada.   --}}
{{-- Tabel rincian yang lebar tetap di modal (tombol "Rincian lengkap").--}}
{{-- ================================================================ --}}
<x-drawer id="crDrawer" label="Detail Cost Request" width="580px">
    <div id="crDrawerContent" hidden></div>

    <div id="crDrawerLoading">
        <x-skeleton type="lines" :rows="2" />
        <div style="height:14px"></div>
        <x-skeleton type="card" />
        <div style="height:14px"></div>
        <x-skeleton type="lines" :rows="4" />
    </div>

    <x-slot:footer>
        <div id="crDrawerActions" class="ts-actions" style="flex:1;justify-content:flex-start;"></div>
        <button type="button" class="ts-action-primary is-neutral" id="crDrawerFull">
            <i class="fas fa-list-alt"></i><span class="ts-al">Rincian lengkap</span>
        </button>
    </x-slot:footer>
</x-drawer>

{{-- ================================================================ --}}
{{-- MODAL DETAIL CR                                                    --}}
{{-- ================================================================ --}}
<div class="modal fade" id="mdlDetailCR" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-xxl" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            {{-- Tidak ada pita header di sini. Judul dokumen, kodenya, dan
                 tombol tutup pindah ke kepala panel ringkas gelap di bawah,
                 supaya modal terbaca sebagai SATU lembar: kolom gelap penuh
                 di kiri, ruang kerja terang di kanan. --}}
            <div class="modal-body dm-body" style="padding:0;background:var(--surface);">
                {{-- Kerangka mengikuti bentuk isi modal (kolom kiri ringkasan,
                     kanan tabel) supaya tidak ada lompatan tata letak saat data tiba. --}}
                <div id="crDetailLoading" style="padding:18px;display:flex;gap:16px;flex-wrap:wrap;min-height:420px;">
                    <div style="flex:0 0 200px;max-width:100%;background:var(--surface);border-radius:var(--radius-lg);padding:16px;">
                        <x-skeleton type="card" />
                        <x-skeleton type="lines" :rows="6" />
                    </div>
                    <div style="flex:1;min-width:240px;background:var(--surface);border-radius:var(--radius-lg);padding:16px;">
                        <x-skeleton type="lines" :rows="2" />
                        <x-skeleton type="table" :rows="9" :cols="4" />
                    </div>
                </div>
                <div id="crDetailContent" style="display:none;">
                    <div class="cr-modal-flex">
                        <div class="cr-sidebar dm-side">

                            {{-- Kepala panel. Lingkaran ikon 60px yang dulu ada di
                                 sini dilepas: ia mengambil sepertiga tinggi panel
                                 untuk mengulang informasi yang sudah dinyatakan
                                 oleh modul tempat modal ini dibuka. --}}
                            <div class="dm-head">
                                <button type="button" class="close dm-close" data-dismiss="modal"
                                        aria-label="Tutup"><span aria-hidden="true">&times;</span></button>

                                <div id="crCode" class="dm-code"></div>
                                <h5 class="dm-title" id="crModalTitle">Cost Request Detail</h5>
                                <small id="crModalSubtitle" class="dm-sub-hidden"></small>

                                <div class="dm-badges">
                                    <span id="crStatusBadge"></span>
                                    <span id="crNobBadge"></span>
                                </div>
                            </div>
                            <hr style="border-color:rgba(255,255,255,0.08);">
                            <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Type</span><div id="crType" class="text-white mt-1" style="font-size:12px;"></div></div>
                            <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Total Request</span><div id="crAmount" style="color:var(--accent);font-size:18px;font-weight:700;margin-top:4px;"></div></div>
                            <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Total Settlement</span><div id="crRealizationAmount" style="color:var(--success);font-size:14px;font-weight:700;margin-top:4px;"></div></div>
                            {{-- Label & warnanya diisi JS: "Refund" kalau settlement lebih
                                 kecil dari pengajuan, "Second Payment" kalau justru lebih besar
                                 (lihat pengisian #crRefundLabel di bawah). --}}
                            <div class="mb-2"><span id="crRefundLabel" style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Refund</span><div id="crRefundAmount" class="dm-calc-zero" style="font-size:14px;font-weight:700;margin-top:4px;"></div></div>
                            <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Beneficiary</span><div id="crBeneficiary" class="text-white mt-1" style="font-size:12px;"></div><div id="crBank" style="color:var(--text-faint);font-size:11px;"></div></div>
                            <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Requestor</span><div id="crApplicant" class="text-white mt-1" style="font-size:12px;"></div></div>
                            <div class="cr-sidebar-spacer"></div>
                        </div>
                        <div class="cr-main-panel">
                            <ul class="nav nav-tabs mb-3" id="crDetailTabs" style="border-bottom:2px solid var(--border);flex-wrap:wrap;">
                                <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#crPaneInfo" style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-info-circle mr-1"></i> Info</a></li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#crPaneDetails" style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-list-ul mr-1"></i> Request <span id="crDetailCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span></a></li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#crPaneRealization" style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-receipt mr-1"></i> Settlement <span id="crRealizationCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span></a></li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#crPaneLogs" style="font-size:12px;font-weight:600;padding:8px 12px;"><i class="fas fa-history mr-1"></i> Log</a></li>
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="crPaneInfo">
                                    {{-- Satu informasi satu baris (.ts-deflist), sama dengan
                                         modal Purchase Order & Budgeting. --}}
                                    <div class="ts-deflist mb-3">
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Reference</span>
                                            <span class="ts-deflist-value">
                                                <span id="infoCode"></span>
                                                <button type="button" class="cr-copy-btn" data-copy-target="infoCode" title="Salin Reference"><i class="far fa-copy"></i></button>
                                            </span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Kode</span>
                                            <span class="ts-deflist-value">
                                                <span id="infoCrCode"></span>
                                                <button type="button" class="cr-copy-btn" data-copy-target="infoCrCode" title="Salin Kode"><i class="far fa-copy"></i></button>
                                            </span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Description</span>
                                            <span id="infoDesc" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Type</span>
                                            <span id="infoType" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Total</span>
                                            <span id="infoAmount" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Email Notification</span>
                                            <span id="infoEmail" class="ts-deflist-value" style="word-break:break-all;"></span>
                                        </div>
                                    </div>
                                    {{-- Baris yang hanya muncul untuk sebagian CR
                                         dipisah supaya id="crScopeRow" tetap bisa
                                         disembunyikan seluruhnya seperti sebelumnya. --}}
                                    <div class="ts-deflist mb-3" id="crScopeRow">
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Scope of Work</span>
                                            <span id="infoProject" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Project</span>
                                            <span id="infoCluster" class="ts-deflist-value"></span>
                                        </div>
                                        <div class="ts-deflist-row" id="crPaymentNotesCard" style="display:none;">
                                            <span class="ts-deflist-label">Payment Notes</span>
                                            <span id="infoPaymentNotes" class="ts-deflist-value"></span>
                                        </div>
                                        {{-- Notes ikut sebaris dengan informasi lain, tidak lagi
                                             di balik tombol buka-tutup: ia satu keterangan biasa,
                                             dan menyembunyikannya membuat catatan penting terlewat. --}}
                                        <div class="ts-deflist-row">
                                            <span class="ts-deflist-label">Notes</span>
                                            <span id="infoNotes" class="ts-deflist-value" style="white-space:pre-line;"></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="crPaneDetails">
                                    {{-- Rincian dikelompokkan per Chart of Account, pola yang sama
                                         dengan tab Budget di modal Budgeting. --}}
                                    <div class="d-flex justify-content-end mb-2">
                                        <button type="button" class="btn btn-xs btn-outline-secondary cr-toggle-all" id="btnToggleCrDetail" data-body="crDetailBody" data-expanded="0"><i class="fas fa-angle-double-down mr-1"></i>Expand All</button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0" style="font-size:12px;">
                                            <thead style="background:var(--surface-2);">
                                                <tr>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;">Chart of Account / Description</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:13%;">Price</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:center;width:6%;">Qty</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:14%;">Total</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:center;width:1%;white-space:nowrap;" id="crDetailActionTh" class="d-none">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody id="crDetailBody"></tbody>
                                            <tfoot><tr style="background:var(--surface-2);"><th colspan="3" class="text-right">Total (item aktif)</th><th class="text-right" id="crDetailTotal" style="color:var(--info);"></th><th class="crDetailActionCell d-none"></th></tr></tfoot>
                                        </table>
                                    </div>
                                    <div class="mt-3">
                                        <h6 style="font-size:12px;font-weight:700;color:var(--text-faint);text-transform:uppercase;">
                                            <i class="fas fa-paperclip mr-1"></i> Attachment <span id="crAttCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                                        </h6>
                                        <div id="crAttBody" class="cr-att-grid"></div>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="crPaneRealization">
                                    <div id="crNoRealization" class="text-center text-muted py-4" style="display:none;">
                                        <i class="fas fa-receipt d-block mb-2"></i> No settlement data yet.
                                    </div>
                                    <div id="crRealizationWrapper">
                                        {{-- Dikelompokkan per Chart of Account, sama dengan tab Request & Budgeting. --}}
                                        <div class="d-flex justify-content-end mb-2">
                                            <button type="button" class="btn btn-xs btn-outline-secondary cr-toggle-all" id="btnToggleCrReal" data-body="crRealizationBody" data-expanded="0"><i class="fas fa-angle-double-down mr-1"></i>Expand All</button>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-bordered mb-0" style="font-size:12px;">
                                                <thead style="background:var(--surface-2);">
                                                    <tr>
                                                        <th scope="col" style="color:var(--text-faint);font-size:11px;">Chart of Account / Description</th>
                                                        <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:13%;">Request</th>
                                                        <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:13%;">Settlement</th>
                                                        <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;width:12%;">Var</th>
                                                        <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:center;width:1%;">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="crRealizationBody"></tbody>
                                                <tfoot><tr style="background:var(--surface-2);"><th colspan="2" class="text-right">Total Settlement</th><th class="text-right" id="crRealizationTotal" style="color:var(--success);"></th><th colspan="2"></th></tr></tfoot>
                                            </table>
                                        </div>
                                        <div class="mt-3">
                                            <h6 style="font-size:12px;font-weight:700;color:var(--text-faint);text-transform:uppercase;">
                                                <i class="fas fa-paperclip mr-1"></i> Settlement Attachment (Global)
                                            </h6>
                                            <div id="crRealizationAtts" class="cr-att-grid"></div>
                                        </div>
                                        <div id="crSecondPaymentBox" class="mt-3" style="display:none;"></div>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="crPaneLogs">
                                    <div id="crLogBody" class="cr-timeline cr-chat"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL HEAD APPROVE --}}
<div class="modal fade" id="mdlHeadApprove" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
        <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
            <h5 class="modal-title text-white"><i class="fas fa-check mr-2" style="color:var(--accent);"></i> Head Approve</h5>
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
            <div class="modal-footer" style="background:var(--surface-2);">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check mr-1"></i> Approve</button>
            </div>
        </form>
    </div></div>
</div>

{{-- MODAL NOTES (Head Revise/Reject, Finance Revise/Reject, Reject Realization) --}}
<div class="modal fade" id="mdlNotes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
        <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
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
            <div class="modal-footer" style="background:var(--surface-2);">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger" id="mdlNotesSubmitBtn">Konfirmasi</button>
            </div>
        </form>
    </div></div>
</div>

{{-- MODAL FINANCE VERIFY --}}
<div class="modal fade" id="mdlFinanceVerify" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
        <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
            <h5 class="modal-title text-white"><i class="fas fa-check-double mr-2" style="color:var(--accent);"></i> Finance Verify</h5>
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
            <div class="modal-footer" style="background:var(--surface-2);">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check-double mr-1"></i> Verify</button>
            </div>
        </form>
    </div></div>
</div>

{{-- MODAL CEO APPROVE --}}
<div class="modal fade" id="mdlCeoApprove" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
        <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
            <h5 class="modal-title text-white"><i class="fas fa-check mr-2" style="color:var(--accent);"></i> CEO Approve</h5>
            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <form id="frmCeoApprove" method="POST">@csrf
            <div class="modal-body" style="padding:24px;">
                <p id="ceoApproveText" class="text-muted mb-3"></p>
                <div class="form-group mb-0">
                    <label class="font-weight-600">Catatan (opsional)</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Catatan..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="background:var(--surface-2);">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check mr-1"></i> Approve</button>
            </div>
        </form>
    </div></div>
</div>

{{-- MODAL SUBMIT REALISASI --}}
{{-- MODAL VERIFY REALIZATION --}}
<div class="modal fade" id="mdlVerifyRealization" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
        <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
            <h5 class="modal-title text-white"><i class="fas fa-check-double mr-2" style="color:var(--accent);"></i> Settlement</h5>
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
            <div class="modal-footer" style="background:var(--surface-2);">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" id="btnVerifyRealSubmit" class="btn btn-success"><i class="fas fa-check-double mr-1"></i> Approve</button>
            </div>
        </form>
    </div></div>
</div>

{{-- accounts JSON untuk realisasi --}}

<!-- IMAGE VIEWER (carousel + zoom) -->
<div id="imgViewerOverlay">
    <button aria-label="Close" type="button" id="imgViewerClose" title="Close"><i class="fas fa-times"></i></button>
    <div id="imgViewerName"></div>
    <div id="imgViewerStage">
        <button aria-label="Previous" type="button" class="imgViewerNav" id="imgViewerPrev" title="Previous"><i class="fas fa-chevron-left"></i></button>
        <img id="imgViewerImg" src="" alt="">
        {{-- File non-gambar (PDF/dokumen) tetap ikut urutan carousel, tapi
             ditampilkan sebagai kartu logo + nama file yang bisa diklik. --}}
        <a id="imgViewerFile" href="#" target="_blank" rel="noopener" style="display:none;">
            <i class="fas fa-file-pdf" id="imgViewerFileIcon"></i>
            <span id="imgViewerFileName"></span>
            <small>Klik untuk membuka di tab baru</small>
        </a>
        <button aria-label="Next" type="button" class="imgViewerNav" id="imgViewerNext" title="Next"><i class="fas fa-chevron-right"></i></button>
    </div>
    <div id="imgViewerCounter"></div>
</div>

@endsection

@push('styles')
<style>
.row-nob td      { color:var(--danger) !important; }
.cr-date-range input.form-control { width:100px !important; padding:2px 6px !important; font-size:12px !important; }
.row-nob-item td { color:var(--warning) !important; }
.text-var-pos { color:var(--success); }
.text-var-neg { color:var(--danger); }

/* ── Gaya index Purchase Order: baris bersih + link kode/status biru-tua ── */
.cr-tbl tbody tr,
.cr-tbl tbody tr:hover { background-color:var(--surface) !important; }
.cr-tbl tbody td { background-color:var(--surface); }
.cr-number-btn, .cr-cell-link {
    display:inline-block; background:transparent; border:none; padding:2px 8px; border-radius:var(--radius-sm);
    color:var(--text); font-weight:700; line-height:1.4;
    transition:background-color .12s ease, color .12s ease;
}
.cr-number-btn { font-size:12px; }
.cr-cell-link { font-size:13px; }
.cr-number-btn:hover, .cr-number-btn:focus,
.cr-cell-link:hover, .cr-cell-link:focus { background:var(--charcoal); color:#fff; outline:none; }
.cr-status-link { cursor:pointer; }
.cr-status-link:hover { filter:brightness(.92); }

/* ── TABEL: scroll horizontal, semua kolom fit-content kecuali Description,
   kolom Code & Action dibekukan (pola sama seperti invoice-cash-receipt &
   project/Purchase Order). ── */
/*
| .cr-table-wrap dipakai di _table.blade.php sebagai pembungkus tabel, tapi
| sebelumnya TIDAK PERNAH didefinisikan di CSS mana pun. Akibatnya markup yang
| dikirim server tidak punya area geser sama sekali: pada layar sempit, tabel
| ~20 kolom memaksa SELURUH halaman menggeser sampai JavaScript di bawah
| membungkusnya ulang dengan .cr-hscroll. Ada jenak tata letak rusak di setiap
| pemuatan, dan kalau JavaScript gagal, tabel tersibuk di aplikasi merusak
| layout secara permanen.
|
| Didefinisikan di sini supaya markup server sudah benar sendiri; pembungkusan
| oleh JavaScript tetap bekerja seperti sebelumnya (dan kini hanya menambahkan
| perilaku, bukan menjadi syarat).
*/
.cr-table-wrap { overflow-x:auto; }
.cr-table-wrap .cr-tbl { width:auto !important; min-width:100%; margin-bottom:0; }

.cr-hscroll { overflow-x:auto; }
.cr-hscroll .cr-tbl { width:auto !important; min-width:100%; margin-bottom:0; }
.cr-hscroll .cr-tbl th,
.cr-hscroll .cr-tbl td { white-space:nowrap; }

/* Kolom Code & Action dibekukan di tepi kiri/kanan — termasuk sel header dan
   footer-nya. position:sticky pada <th> perlu !important karena CSS DataTables
   memasang position:relative pada setiap header yang bisa diurutkan
   (table.dataTable>thead .sorting), yang kalau menang akan membatalkan freeze
   di baris header saja: datanya beku, judul kolomnya ikut menggeser.
   Latar sel wajib pekat, kalau tidak isi kolom lain terlihat menembusnya. */
.cr-hscroll .cr-tbl th.cr-col-code,
.cr-hscroll .cr-tbl td.cr-col-code {
    position:sticky !important; left:0; z-index:3;
    background:var(--surface);
    box-shadow:2px 0 6px -2px rgba(0,0,0,.18);
}
.cr-hscroll .cr-tbl th.action-column,
.cr-hscroll .cr-tbl td.action-column {
    position:sticky !important; right:0; z-index:3;
    background:var(--surface);
    box-shadow:-2px 0 6px -2px rgba(0,0,0,.18);
}
.cr-hscroll .cr-tbl thead th.cr-col-code,
.cr-hscroll .cr-tbl thead th.action-column,
.cr-hscroll .cr-tbl tfoot th.cr-col-code,
.cr-hscroll .cr-tbl tfoot th.action-column {
    background:var(--surface-2) !important; z-index:4;
}
.cr-hscroll .cr-tbl tbody tr:hover > td.cr-col-code,
.cr-hscroll .cr-tbl tbody tr:hover > td.action-column { background:var(--surface-2); }

/* Modal detail CR — ukuran & style disamakan dengan modal show Budget Plan. */
.modal-dialog-xxl {
    max-width: calc(100vw - 32px);
    width: calc(100vw - 32px);
    height: calc(100vh - 32px);
    margin: 16px auto;
}
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
.modal-dialog-xxl #crDetailContent { height: 100%; }

.cr-modal-flex { display:flex; align-items:stretch; min-height:100%; }
.cr-sidebar {
    width:280px; flex:0 0 280px; background:var(--charcoal); padding:28px 20px;
    display:flex; flex-direction:column;
}
.cr-sidebar-spacer { flex:1 1 auto; }
.cr-main-panel { flex:1 1 auto; padding:24px; min-width:0; }
@media (max-width:767.98px) {
    .cr-modal-flex { flex-direction:column; }
    .cr-sidebar { width:100%; flex:0 0 auto; }
}

/* Log tab — gaya chat sama persis dengan tab Log Purchase Order
   (project/_index-assets.blade.php: .prj-chat-*) — log dari user yang sedang
   login rata kanan ("mine"), lainnya rata kiri, dengan avatar inisial. */
.cr-timeline { padding:14px 4px; }
.cr-chat { display:flex; flex-direction:column; gap:14px; padding:4px 2px; }
.cr-chat-row { display:flex; gap:10px; max-width:78%; }
.cr-chat-row.mine { align-self:flex-end; flex-direction:row-reverse; text-align:right; }
.cr-chat-av { width:30px; height:30px; border-radius:50%; background:var(--info-bg); color:var(--text-dim); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; flex-shrink:0; }
.cr-chat-row.mine .cr-chat-av { background:var(--charcoal); color:#fff; }
.cr-chat-bubble { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:8px 12px; font-size:12px; }
.cr-chat-row.mine .cr-chat-bubble { border-color:var(--border); }
.cr-chat-meta { font-size:10px; color:var(--text-faint); margin-bottom:4px; }
.cr-chat-note { margin-top:6px; font-size:11.5px; line-height:1.45; white-space:pre-line; color:var(--text); }

/* Budget tab — reuse pola pivot grup/NOB dari Budget Plan */
.bp-caret.rotated { transform:rotate(90deg); }
.cr-req-badge { font-size:9px; }

/* Tombol salin (Reference & Kode di tab Info). */
.cr-copy-btn { background:none; border:0; padding:0 0 0 6px; color:var(--text-faint); cursor:pointer; font-size:12px; line-height:1; }
.cr-copy-btn:hover { color:var(--accent); }

/* Teks deskripsi panjang di tab Settlement: dipotong satu baris dengan "…",
   melebar penuh saat barisnya disorot kursor. */
#crRealizationBody .cr-clip { display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical; overflow:hidden; text-overflow:ellipsis; }
#crRealizationBody tr:hover .cr-clip { -webkit-line-clamp:unset; overflow:visible; white-space:normal; }

/* TAB INFO (Cost Request) — clean grid layout, sama seperti Budget Plan */
.cr-info-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px 18px; }
.cr-info-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px 18px; }
.cr-info-grid-ref { display:grid; grid-template-columns:320px 1fr; gap:14px 18px; }
.cr-info-grid-scope { display:grid; grid-template-columns:3fr 1fr; gap:14px 18px; }
.cr-info-grid-scope.has-payment { grid-template-columns:12fr 5fr 3fr; }
.cr-info-item { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-md); padding:12px 16px; }
.cr-info-label { display:block; font-size:10px; text-transform:uppercase; font-weight:700; color:var(--text-faint); letter-spacing:.03em; margin-bottom:5px; }
.cr-info-value { font-size:13px; color:var(--text); white-space:nowrap; }
.cr-info-grid-scope .cr-info-value { white-space:normal; }
.cr-info-highlight { background:var(--warning-bg); border-color:var(--border-strong); }
.cr-info-highlight .cr-info-value { font-weight:700; color:var(--warning); }
@media (max-width:576px) {
    .cr-info-grid, .cr-info-grid-3, .cr-info-grid-ref, .cr-info-grid-scope, .cr-info-grid-scope.has-payment { grid-template-columns:1fr; }
    .cr-info-value { white-space:normal; }
}

/* TAB REQUEST & REALIZATION — thumbnail grid lampiran */
.cr-att-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:12px; margin-top:8px; }

/* Grup Chart of Account pada tab Request — mengikuti tampilan grup di modal Budgeting. */
#crDetailBody tr.cr-group-row, #crRealizationBody tr.cr-group-row,
#crDetailBody tr.cr-group-row td, #crRealizationBody tr.cr-group-row td { background:var(--info-bg) !important; }
#crDetailBody tr.cr-group-row:hover, #crRealizationBody tr.cr-group-row:hover,
#crDetailBody tr.cr-group-row:hover td, #crRealizationBody tr.cr-group-row:hover td { background:var(--surface-3) !important; }
#crDetailBody .cr-caret.rotated, #crRealizationBody .cr-caret.rotated { transform:rotate(90deg); }
#crDetailBody td.cr-indent, #crRealizationBody td.cr-indent { padding-left:32px !important; }
.cr-att-card { border:1px solid var(--border); border-radius:var(--radius-md); overflow:hidden; background:var(--surface); }
.cr-att-thumb { width:100%; height:120px; object-fit:cover; display:block; cursor:pointer; background:var(--surface-2); }
.cr-att-icon { width:100%; height:120px; display:flex; align-items:center; justify-content:center; background:var(--surface-2); color:var(--text-faint); font-size:30px; }
.cr-att-name { font-size:11px; padding:6px 8px; color:var(--text); word-break:break-word; border-top:1px solid var(--border); }

.custom-toolbar      { display:flex; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; flex-direction:row; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; font-weight:600; color:var(--text-dim); white-space:nowrap; flex-shrink:0; }
.toolbar-input       { min-width:180px; }
.cr-search-box input   { width:180px; border-radius:var(--radius-sm); }
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
.cr-tbl               { width:100% !important; }
.table td,.table th  { vertical-align:middle !important; }
.action-column       { white-space:nowrap !important; width:1% !important; }
.action-buttons      { display:flex; align-items:center; justify-content:center; gap:3px; flex-wrap:nowrap; }
.font-weight-600     { font-weight:600; }
.dataTables_wrapper .row:last-child { margin-top:18px; align-items:center; }
.dataTables_info { padding-top:0 !important; font-size:14px; color:var(--text-faint); }
.dataTables_length { display:flex; align-items:center; margin-left:18px; }
.dataTables_length label { display:flex; align-items:center; gap:10px; margin-bottom:0; font-size:14px; font-weight:600; color:var(--text-faint); }
.dataTables_length select { appearance:none !important; background-color:var(--surface) !important; color:var(--text-dim) !important; border:1px solid var(--border) !important; border-radius:var(--radius-sm) !important; padding:4px 30px 4px 10px !important; height:36px !important; min-width:72px !important; font-size:14px !important; font-weight:600 !important; cursor:pointer; box-shadow:none !important; }
.dataTables_paginate .paginate_button { border-radius:var(--radius-sm) !important; margin:0 2px; }
.dataTables_paginate .paginate_button.current { background:var(--accent) !important; background-image:none !important; border-color:var(--accent-dark) !important; color:#fff !important; font-weight:700 !important; }
/* Tab utama: Cash Advance / Cost Payment / Database */
.cr-main-tabs { border-bottom:2px solid var(--border); flex-wrap:wrap; }
.cr-main-tabs .nav-link { color:var(--text-faint); border:none; border-bottom:2px solid transparent; border-radius:0; padding:9px 16px; font-weight:600; font-size:13px; margin-bottom:-2px; }
.cr-main-tabs .nav-link:hover { color:var(--text); }
.cr-main-tabs .nav-link.active { color:var(--accent) !important; border-bottom-color:var(--accent) !important; background:transparent !important; }
.cr-tab-host { min-height:120px; }
#crDetailTabs .nav-link { color:var(--text-faint); border:none; border-bottom:2px solid transparent; border-radius:0; padding:8px 12px; }
#crDetailTabs .nav-link.active { color:var(--accent) !important; border-bottom-color:var(--accent) !important; background:transparent !important; font-weight:700; }
@media (max-width:576px) {
    .custom-toolbar { flex-direction:column !important; align-items:stretch !important; }
    .custom-toolbar-left { flex-direction:column !important; align-items:stretch !important; width:100%; }
    .custom-toolbar-right { width:100%; }
    .custom-toolbar-right .btn { width:100%; }
    .cr-btn-clear { width:100%; }
    .cr-row-filters { width:100%; }
    .cr-row-filters .tcf-row-btn { width:100%; }
    .toolbar-row { width:100%; }
    .toolbar-input { flex:1; min-width:0; }
    .cr-search-box input { width:100%; }
    .ts-wrapper { width:100% !important; min-width:0 !important; }
    .card-header { flex-direction:column; align-items:stretch !important; gap:10px; }
    .card-header .card-title { margin-bottom:0; }
    .card-header .ml-auto { margin-left:0 !important; flex-wrap:wrap; }
    .card-header .ml-auto .btn { flex:1 1 auto; }
    .cr-row-filters .tcf-row-btn { flex:1 1 auto; }
}

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
#imgViewerFile {
    display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px;
    background:#fff; color:var(--text); border-radius:var(--radius-lg);
    padding:40px 48px; max-width:80vw; text-decoration:none;
}
#imgViewerFile:hover { background:var(--surface-2); }
#imgViewerFile i { font-size:72px; color:var(--danger); }
#imgViewerFile span { font-size:14px; font-weight:600; word-break:break-all; text-align:center; }
#imgViewerFile small { font-size:11px; color:var(--text-faint); }
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

/* Panel filter gabungan: dua kolom, rapat, dan tidak terpotong kontainer. */
.cr-filter-panel { max-height: 70vh; overflow-y: auto; }
.cr-filter-panel .cr-row-filters {
    display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px;
}
.cr-filter-panel .tcf-row-btn { width: 100%; text-align: left; justify-content: space-between; overflow: hidden; }
.cr-filter-panel .tcf-row-btn > i { margin-left: 6px; flex: 0 0 auto; }
.cr-filter-title {
    font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
    color: var(--text-faint); margin-bottom: 10px;
}
.cr-filter-foot { margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--border); }
.cr-filter-toggle.cr-filter-on { background: var(--accent); border-color: var(--accent); color: #fff; }
@media (max-width: 575.98px) {
    .cr-filter-panel { min-width: 260px !important; }
    .cr-filter-panel .cr-row-filters { grid-template-columns: 1fr; }
}

/* Popover toolbar (Filter & Kolom) — ditempel pada tombolnya sendiri. */
.cr-pop { position: relative; }
.cr-pop-menu {
    display: none; position: absolute; top: calc(100% + 4px); left: 0; z-index: 1080;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); box-shadow: 0 8px 24px rgba(0,0,0,.14);
}
.cr-pop.show .cr-pop-menu { display: block; }
</style>
@endpush

@push('scripts')
<script>
var currentUserId = parseInt('{{ (int) Auth::id() }}', 10) || 0;

$(function () {

    // Keterangan status berasal dari App\Support\WorkflowStatus (satu sumber).
    var statusMeta = @json(collect(\App\Models\BudgetRequest::STATUSES)->mapWithKeys(fn ($s) => [
        $s => \App\Support\WorkflowStatus::meta($s, 'budget-request'),
    ]));


    /* ── FILTER TANGGAL (flatpickr) — diinisialisasi PALING AWAL & dibungkus
       try/catch supaya error di bagian script manapun (DataTable, dsb.) tidak
       pernah bisa membuat date-picker ini gagal terpasang. Kalau library
       flatpickr sendiri gagal termuat (CDN diblokir jaringan, atau file plugin
       lokal belum ter-upload ke server), lepas readonly supaya user tetap bisa
       mengetik tanggal manual (format YYYY-MM-DD) sebagai fallback. */
    try {
        if (typeof flatpickr === 'function') {
            flatpickr('#crDateFrom', {
                dateFormat: 'Y-m-d', altInput: true, altFormat: 'd/m/Y',
                defaultDate: '{{ $dateFrom }}',
                onChange: function (selectedDates, dateStr) { reloadWithDateRange(dateStr, $('#crDateTo').val()); }
            });
            flatpickr('#crDateTo', {
                dateFormat: 'Y-m-d', altInput: true, altFormat: 'd/m/Y',
                defaultDate: '{{ $dateTo }}',
                onChange: function (selectedDates, dateStr) { reloadWithDateRange($('#crDateFrom').val(), dateStr); }
            });
        } else {
            throw new Error('flatpickr is not a function');
        }
    } catch (err) {
        console.warn('Flatpickr gagal diinisialisasi — filter tanggal beralih ke mode input manual.', err);
        $('#crDateFrom, #crDateTo').removeAttr('readonly').attr('placeholder', 'YYYY-MM-DD');
        $('#crDateFrom, #crDateTo').on('change', function () {
            reloadWithDateRange($('#crDateFrom').val(), $('#crDateTo').val());
        });
    }

    // (accounts holder lama sudah tidak diperlukan — realisasi sekarang pakai CR Detail Item)

    /* ── TAB CASH ADVANCE / COST PAYMENT / DATABASE — data dimuat saat tab dibuka ──
    | Halaman awal hanya membawa kerangka + angka stepper. Baris tabel diambil
    | AJAX (BudgetRequestController::tab) HANYA ketika tab pertama kali dibuka,
    | lalu DataTables dipasang pada tabel tab itu saja.
    | Catatan: jangan menulis tag komponen atau direktif Blade di komentar. */
    var CR_DATE_FROM = '{{ $dateFrom }}';
    var CR_DATE_TO   = '{{ $dateTo }}';
    var CR_ROW_FILTERS = [
        { key: 'po',          label: 'PO' },
        { key: 'type',        label: 'Tipe' },
        { key: 'beneficiary', label: 'Beneficiary' },
        { key: 'pic',         label: 'PIC' },
        { key: 'pminternal',  label: 'Manager' },
        { key: 'status',      label: 'Status' },
    ];
    var CR_TABS = {
        // Tab Cash Advance isinya sudah pasti satu tipe, jadi filter Tipe
        // tidak menyaring apa pun di sini.
        ca: { id: 'tblCA', mapVis: { tanggal: 3, paid_at: 4, pengaju: 5, pic: 6, realisasi: 8, refund: 9, claim: 10 },
              filters: CR_ROW_FILTERS.filter(function (f) { return f.key !== 'type'; }) },
        cp: { id: 'tblCP', mapVis: { tanggal: 3, paid_at: 4, pengaju: 5, pic: 6 },
              filters: CR_ROW_FILTERS },
        db: { id: 'tblDB', mapVis: { tanggal: 4, paid_at: 5, pengaju: 6, pic: 7 },
              filters: CR_ROW_FILTERS },
    };
    var crVisDefault = { tanggal: true, paid_at: true, pengaju: true, pic: true, realisasi: false, refund: true, claim: true };

    // Preferensi kolom per tab (kunci tab Cash Advance tetap sama seperti sebelumnya).
    function crVisKey(tab, key) { return 'crColVis_' + (tab === 'ca' ? '' : tab + '_') + key; }
    function getCrColVis(tab, key) {
        var stored = localStorage.getItem(crVisKey(tab, key));
        return stored === null ? !!crVisDefault[key] : stored === '1';
    }
    function setCrColVis(tab, key, visible) { localStorage.setItem(crVisKey(tab, key), visible ? '1' : '0'); }

    function crMoney(v) {
        var sign = v < 0 ? '-' : '';
        return sign + Math.abs(Math.round(v)).toLocaleString('id-ID');
    }
    function crPane(tab) { return $('.cr-pane[data-tab="' + tab + '"]'); }

    /* ── PANEL FILTER GABUNGAN ─────────────────────────────────────────────
    | Dulu tiap field punya tombol dropdown sendiri sehingga toolbar penuh
    | tombol dan posisinya bergeser-geser saat dibuka. Sekarang semuanya
    | dikumpulkan di satu panel: satu <select> per field, pilihannya diambil
    | dari nilai yang benar-benar ada pada baris tabel, lalu diterapkan
    | sekaligus lewat API filter yang sama (jadi chip & reset tetap jalan). */
    function buildFilterPanel($pane, cfg) {
        /*
        | Panel ini TIDAK membuat kontrol sendiri: tombol per-field bawaan
        | TesysColumnFilter dipindahkan ke dalamnya. Dengan begitu tiap filter
        | tetap punya kotak pencarian dan daftar centang (bisa pilih lebih dari
        | satu nilai), lengkap dengan chip dan penyorotan tombol — perilaku yang
        | sudah dipakai modul lain, tanpa menduplikasi logikanya.
        */
        var $filters = $pane.find('.cr-row-filters');
        if (!$filters.length) return;

        function tandaiAktif() {
            var ada = $filters.find('.tcf-row-active').length > 0;
            $pane.find('.cr-filter-toggle').toggleClass('cr-filter-on', ada);
        }

        $filters.off('click.crfilter').on('click.crfilter', '.tcf-row-btn', function () {
            setTimeout(tandaiAktif, 50);
        });

        $pane.find('.cr-filter-reset').off('click').on('click', function () {
            TesysColumnFilter.clearAll('#' + cfg.id);
            tandaiAktif();
        });

        tandaiAktif();
    }


    /* ── POPOVER FILTER & KOLOM ────────────────────────────────────────────
    | Tidak memakai dropdown Bootstrap: Popper menghitung posisinya terhadap
    | kontainer tabel yang bisa digulir, sehingga panelnya melompat-lompat
    | saat dibuka. Di sini panel cukup ditempel absolut di bawah tombolnya,
    | lalu digeser ke kiri kalau ujungnya melewati tepi layar. */
    $(document).on('click', '.cr-pop-toggle', function (e) {
        e.stopPropagation();
        var $pop = $(this).closest('.cr-pop');
        var buka = !$pop.hasClass('show');

        $('.cr-pop').removeClass('show');
        $pop.toggleClass('show', buka);
        if (!buka) return;

        // Geser kalau panel keluar dari tepi kanan layar.
        var $menu = $pop.find('.cr-pop-menu');
        $menu.css({ left: 0, right: 'auto' });
        var rect = $menu[0].getBoundingClientRect();
        if (rect.right > window.innerWidth - 8) $menu.css({ left: 'auto', right: 0 });
    });

    $(document).on('click', function (e) {
        /*
        | Daftar centang tiap filter (.tcf-dropdown) dipasang di <body>, bukan
        | di dalam panel. Tanpa pengecualian ini, mencentang satu nilai dianggap
        | "klik di luar" sehingga panel Filter-nya ikut tertutup dan daftarnya
        | tertinggal mengambang.
        */
        if ($(e.target).closest('.cr-pop, .tcf-dropdown').length) return;
        $('.cr-pop').removeClass('show');
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') $('.cr-pop').removeClass('show');
    });

    function crCurrentTab() { return $('#crTabs .nav-link.active').data('crtab') || 'ca'; }

    function initCrTable(tab) {
        var cfg = CR_TABS[tab], $pane = crPane(tab), $host = $pane.find('.cr-tab-host');
        var $tbl = $('#' + cfg.id);
        if (!$tbl.length) return;

        var emptyTpl = $host.find('.cr-empty-template')[0];
        var defs = [{ targets: [0], visible: false, orderable: false }];
        Object.keys(cfg.mapVis).forEach(function (k) {
            defs.push({ targets: [cfg.mapVis[k]], visible: getCrColVis(tab, k) });
        });

        var table;
        try {
            // TIDAK pakai extension "responsive" DataTables (rebentrok dengan toggle
            // visibility manual). Tabel selalu scroll horizontal lewat .cr-hscroll.
            table = $tbl.DataTable({
                autoWidth: false, pageLength: 10, lengthChange: true, searching: true,
                order: [], // pertahankan urutan dari server (input terbaru di atas)
                columnDefs: defs,
                dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex justify-content-end"lp>>',
                language: { lengthMenu: 'Page Size _MENU_',
                    emptyTable: (emptyTpl && emptyTpl.innerHTML) || 'Belum ada data',
                    zeroRecords: '<div class="ts-empty ts-empty-compact">'
                        + '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-search"></i></span>'
                        + '<p class="ts-empty-title">Tidak ada yang cocok</p>'
                        + '<p class="ts-empty-msg">Tidak ada Cost Request yang cocok dengan pencarian atau filter yang sedang aktif.</p>'
                        + '<div class="ts-empty-action"><button type="button" class="ts-action-primary is-neutral cr-empty-reset">'
                        + '<i class="fas fa-undo"></i><span class="ts-al">Reset filter</span></button></div></div>',
                    info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data', infoEmpty: 'Tidak ada data',
                    paginate: { previous: 'Previous', next: 'Next' } }
            });
        } catch (err) {
            console.error('Gagal inisialisasi DataTable tab ' + tab + ':', err);
            return;
        }
        cfg.table = table;

        $tbl.wrap('<div class="cr-hscroll table-responsive"></div>');

        // Toggle kolom
        $pane.find('.cr-col-vis-toggle').each(function () {
            var key = $(this).data('column');
            if (cfg.mapVis[key] == null) return;
            $(this).prop('checked', table.column(cfg.mapVis[key]).visible());
        }).off('change').on('change', function () {
            var key = $(this).data('column');
            if (cfg.mapVis[key] == null) return;
            var visible = $(this).is(':checked');
            table.column(cfg.mapVis[key]).visible(visible);
            setCrColVis(tab, key, visible);
            try { table.columns.adjust(); } catch (e) {}
        });
        $pane.find('.cr-col-dropdown .dropdown-menu').off('click').on('click', function (e) { e.stopPropagation(); });
        table.on('column-visibility.dt', function () { try { table.columns.adjust(); } catch (e) {} });

        // Pencarian (debounce — hindari full-scan tabel pada tiap ketukan)
        $('#' + cfg.id + '_filter').hide();
        $pane.find('.cr-search-box').html('<input type="search" class="form-control form-control-sm cr-search-input" placeholder="Cari data...">');
        var searchTimer = null;
        $pane.find('.cr-search-input').on('keyup search', function () {
            var val = this.value;
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { table.search(val).draw(); }, 300);
        });

        // Footer: total dari SEMUA baris yang lolos filter/search (bukan cuma halaman aktif).
        var recalcFooter = function () {
            var amount = 0, real = 0, vr = 0, cost = 0, claim = 0;
            table.rows({ search: 'applied' }).every(function () {
                var node = this.node();
                amount += parseFloat(node.querySelector('.col-raw-amount')?.dataset.raw)    || 0;
                real   += parseFloat(node.querySelector('.col-raw-realisasi')?.dataset.raw) || 0;
                vr     += parseFloat(node.querySelector('.col-raw-var')?.dataset.raw)       || 0;
                cost   += parseFloat(node.querySelector('.col-raw-cost')?.dataset.raw)      || 0;
                claim  += parseFloat(node.querySelector('.col-raw-claim')?.dataset.raw)     || 0;
            });
            $tbl.find('.cr-foot-amount').text(crMoney(amount));
            $tbl.find('.cr-foot-realisasi').text(crMoney(real));
            $tbl.find('.cr-foot-cost').text(crMoney(cost));
            $tbl.find('.cr-foot-var').html('<span class="' + (vr >= 0 ? 'text-var-pos' : 'text-var-neg') + '">' + crMoney(vr) + '</span>');
            $tbl.find('.cr-foot-claim').text(crMoney(claim));
        };
        table.on('draw', recalcFooter);
        recalcFooter();

        TesysColumnFilter.initRowFilters('#' + cfg.id, $pane.find('.cr-row-filters'), cfg.filters);
        buildFilterPanel($pane, cfg);

        $pane.find('.cr-btn-clear').off('click').on('click', function () {
            TesysColumnFilter.clearAll('#' + cfg.id);
            TesysWorkflowStepper.clear(cfg.id);
            var url = new URL(window.location.href);
            url.searchParams.delete('status');
            window.history.replaceState({}, '', url.toString());
        });

        // Filter status dari URL (?status=) hanya untuk tab yang sedang aktif dibuka dari URL.
        var urlStatus = new URLSearchParams(window.location.search).get('status');
        if (urlStatus && crCurrentTab() === tab) {
            var $step = $('[data-wf-table="' + cfg.id + '"] .wf-tl-step[data-status="' + urlStatus + '"]');
            if ($step.length) $step.trigger('click');
        }

        /* Filter dari tautan luar (kartu Active Cash Advance di Summary).
           Dipasang lewat setRowFilter supaya hasilnya persis seperti user
           mencentang sendiri: chip filternya muncul dan bisa dilepas.
           Sama seperti ?status=, hanya berlaku untuk tab yang dibuka dari URL. */
        if (crCurrentTab() === tab) {
            (function applyRowFilterFromUrl() {
                var q   = new URLSearchParams(window.location.search);
                var sel = '#' + cfg.id;
                var manager = q.get('manager');
                var pic     = q.get('pic');
                if (manager) TesysColumnFilter.setRowFilter(sel, 'pminternal', manager.toUpperCase());
                if (pic)     TesysColumnFilter.setRowFilter(sel, 'pic', pic);

                /* Tipe & daftar status ikut dibawa supaya daftar di sini memuat
                   himpunan yang sama dengan kartu asalnya. setRowFilter menambah
                   (bukan mengganti) nilai pada field yang sama, jadi beberapa
                   status bisa dipasang berurutan. */
                var type = q.get('type');
                if (type) TesysColumnFilter.setRowFilter(sel, 'type', type);

                /* Daftar PIC di bawah manager yang dipilih. Dipasang sebagai filter
                   agar namanya terbaca di chip - bukan untuk mempersempit, karena
                   filter Manager sudah melakukannya. */
                (q.get('pics') || '').split(',').forEach(function (nm) {
                    nm = nm.trim();
                    if (nm) TesysColumnFilter.setRowFilter(sel, 'pic', nm);
                });

                (q.get('statuses') || '').split(',').forEach(function (st) {
                    st = st.trim();
                    if (st) TesysColumnFilter.setRowFilter(sel, 'status', st);
                });
            })();
        }
    }

    function crLoadTab(tab) {
        var cfg = CR_TABS[tab];
        if (!cfg) return;
        if (cfg.loaded) { try { cfg.table && cfg.table.columns.adjust(); } catch (e) {} return; }
        if (cfg.loading) return;

        var $host = crPane(tab).find('.cr-tab-host');
        cfg.loading = true;
        $.get($host.data('url'), { date_from: CR_DATE_FROM, date_to: CR_DATE_TO })
            .done(function (html) {
                $host.html(html);
                cfg.loaded = true;
                initCrTable(tab);
            })
            .fail(function () {
                $host.html('<div class="text-center text-muted py-4">'
                    + '<i class="fas fa-exclamation-triangle mr-1"></i> Gagal memuat data. '
                    + '<button type="button" class="btn btn-sm btn-outline-secondary ml-2 cr-tab-retry" data-tab="' + tab + '">Coba lagi</button></div>');
            })
            .always(function () { cfg.loading = false; });
    }

    $(document).on('click', '.cr-tab-retry', function () { crLoadTab($(this).data('tab')); });

    // Buka tab -> muat datanya (sekali), sinkronkan ?tab= di URL, buang ?status= milik tab sebelumnya.
    $('#crTabs a[data-toggle="tab"]').on('shown.bs.tab', function () {
        var tab = $(this).data('crtab');
        var url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        url.searchParams.delete('status');
        window.history.replaceState({}, '', url.toString());
        crLoadTab(tab);
    });

    crLoadTab('{{ $activeTab }}');

    // Sinkron filter stepper status <-> URL (?status=) supaya tidak hilang setelah aksi (POST lalu redirect back()).
    $(document).on('click', '.wf-tl-step', function () {
        var $step = $(this);
        if (!$step.closest('[data-wf-table]').length) return;
        var url = new URL(window.location.href);
        if ($step.hasClass('wf-active')) {
            url.searchParams.set('status', $step.data('status'));
        } else {
            url.searchParams.delete('status');
        }
        window.history.replaceState({}, '', url.toString());
    });

    $('#btnRefreshCR').on('click', function () { location.reload(); });

    /* Tombol "Reset filter" pada keadaan kosong memakai ulang tombol Clear Filter tab-nya. */
    $(document).on('click', '.cr-empty-reset', function () {
        var $pane = $(this).closest('.cr-pane');
        $pane.find('.cr-search-input').val('').trigger('keyup');
        $pane.find('.cr-btn-clear').trigger('click');
    });

    /* ── FILTER TANGGAL — server-side (reload halaman dengan ?date_from=&date_to=) ──
    | Berlaku untuk semua tab; ?tab= ikut terbawa di URL. Pakai "date_from"/"date_to"
    | (bukan "from"/"to") karena "from" sudah dipakai link sidebar (?from=finance). */
    function reloadWithDateRange(from, to) {
        var url = new URL(window.location.href);
        url.searchParams.delete('from');
        url.searchParams.set('date_from', from);
        url.searchParams.set('date_to', to);
        window.location.href = url.toString();
    }

    // Export mengikuti filter yang SEDANG AKTIF di tabel tab tsb (search, dropdown, stepper).
    // Tanpa filter tambahan -> export penuh rentang tanggal (bukan hanya baris ter-render/ter-cap).
    $(document).on('click', '.cr-btn-export', function (e) {
        e.preventDefault();
        var tab = $(this).data('tab');
        var cfg = CR_TABS[tab];
        var fallback = '{{ url("/budget-request/export") }}?date_from=' + encodeURIComponent(CR_DATE_FROM)
            + '&date_to=' + encodeURIComponent(CR_DATE_TO) + '&tab=' + encodeURIComponent(tab);

        try {
            var table = cfg && cfg.table;
            if (!table) { window.location.href = fallback; return; }

            // Kolom export = kolom yang AKTIF (terlihat) di tabel tab ini, urut sesuai tabel.
            var cols = [];
            table.columns().every(function () {
                var k = $(this.header()).data('col');
                if (k && this.visible()) cols.push(k);
            });
            fallback += cols.map(function (k) { return '&cols[]=' + encodeURIComponent(k); }).join('');

            var total    = table.rows().count();
            var filtered = table.rows({ search: 'applied' }).count();
            if (filtered === total) { window.location.href = fallback; return; }

            var ids = [];
            table.rows({ search: 'applied' }).every(function () {
                var id = $(this.node()).data('id');
                if (id) ids.push(id);
            });

            var $f = $('<form method="POST" action="' + '{{ url("/budget-request/export") }}' + '"></form>');
            $f.append($('<input type="hidden" name="_token">').val($('meta[name="csrf-token"]').attr('content')));
            $f.append($('<input type="hidden" name="tab">').val(tab));
            cols.forEach(function (k) { $f.append($('<input type="hidden" name="cols[]">').val(k)); });
            ids.forEach(function (id) { $f.append($('<input type="hidden" name="ids[]">').val(id)); });
            $('body').append($f);
            $f[0].submit();
            $f.remove();
        } catch (err) {
            window.location.href = fallback;
        }
    });

    /* ── WARNA TOMBOL KONFIRMASI ──
       Diambil dari token desain, bukan biru/merah bawaan Bootstrap, supaya
       dialog konfirmasi memakai warna yang sama dengan tombol di halaman. */
    function tokenWarna(nama, cadangan) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(nama).trim();
        return v || cadangan;
    }
    var WARNA_UTAMA  = tokenWarna('--accent', '#92232b');
    var WARNA_BAHAYA = tokenWarna('--danger', '#9a3030');
    var WARNA_BATAL  = tokenWarna('--text-muted', '#6a6e72');

    /* ── KEADAAN SIBUK ──
       Setiap aksi CR adalah POST biasa yang memuat ulang halaman. Tanpa
       penanda ini tidak ada umpan balik sama sekali antara klik dan halaman
       berganti, sehingga tombol mudah tertekan dua kali. */
    function sibukkan(form) {
        var tombol = form.querySelector('button[type="submit"]');
        if (tombol && window.TesysUI && TesysUI.busy) TesysUI.busy(tombol, true);
    }

    $(document).on('submit',
        '#frmHeadApprove, #frmNotes, #frmFinanceVerify, #frmCeoApprove, #frmVerifyRealization',
        function () { sibukkan(this); });

    /* ── SUBMIT CONFIRM ── */
    $(document).on('submit', '.frmSubmitCR', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Submit CR?', text:'CR akan dikirim ke Head untuk direview.', icon:'question', showCancelButton:true, confirmButtonColor:WARNA_UTAMA, cancelButtonColor:WARNA_BATAL, confirmButtonText:'Ya, Submit', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) { sibukkan(form); form.submit(); } });
    });

    $(document).on('submit', '.frmDeleteCR', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Hapus CR?', icon:'warning', showCancelButton:true, confirmButtonColor:WARNA_BAHAYA, cancelButtonColor:WARNA_BATAL, confirmButtonText:'Ya, Hapus', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) { sibukkan(form); form.submit(); } });
    });

    /* ── HEAD APPROVE ── */
    $(document).on('click', '.btn-head-approve', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        $('#headApproveText').text('Approve CR ' + code + '? Status akan menjadi CHECKED dan diteruskan ke Finance.');
        $('#frmHeadApprove').attr('action', '/budget-request/' + id + '/head-approve');
        $('#frmHeadApprove textarea').val('');
        $('#mdlHeadApprove').modal('show');
    });

    /* ── CEO APPROVE ── */
    $(document).on('click', '.btn-ceo-approve', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        $('#ceoApproveText').text('Approve CR ' + code + '? Status akan menjadi APPROVED dan siap masuk batch pembayaran.');
        $('#frmCeoApprove').attr('action', '/budget-request/' + id + '/ceo-approve');
        $('#frmCeoApprove textarea').val('');
        $('#mdlCeoApprove').modal('show');
    });

    /* ── GENERIC NOTES MODAL ── */
    function openNotesModal(id, action, title, label, btnClass, btnText, alertMsg) {
        $('#mdlNotesTitle').html('<i class="fas fa-exclamation-triangle mr-2" style="color:var(--accent);"></i>' + title);
        $('#mdlNotesLabel').html(label + ' <span class="text-danger">*</span>');
        if (alertMsg) { $('#mdlNotesAlert').html(alertMsg).show(); } else { $('#mdlNotesAlert').hide(); }
        $('#frmNotes').attr('action', '/budget-request/' + id + '/' + action);
        $('#frmNotes textarea').val('');
        $('#mdlNotesSubmitBtn').text(btnText).removeClass('btn-danger btn-warning').addClass(btnClass);
        $('#mdlNotes').modal('show');
    }

    $(document).on('click', '.btn-head-reject', function () {
        openNotesModal($(this).data('id'), 'head-reject', 'Tolak CR', 'Alasan Penolakan', 'btn-danger', 'Tolak', null);
    });
    $(document).on('click', '.btn-finance-reject', function () {
        openNotesModal($(this).data('id'), 'finance-reject', 'Tolak CR', 'Alasan Penolakan', 'btn-danger', 'Tolak', null);
    });
    $(document).on('click', '.btn-ceo-reject', function () {
        openNotesModal($(this).data('id'), 'ceo-reject', 'Tolak CR', 'Alasan Penolakan', 'btn-danger', 'Tolak', null);
    });
    $(document).on('click', '.btn-manager-revise', function () {
        openNotesModal($(this).data('id'), 'manager-revise', 'Revise CR → Draft', 'Catatan Revisi', 'btn-warning', 'Revise',
            '<i class="fas fa-info-circle mr-1"></i> CR akan dikembalikan ke status <strong>DRAFT</strong> untuk diperbaiki.');
    });
    $(document).on('click', '.btn-manager-cancel', function () {
        openNotesModal($(this).data('id'), 'manager-cancel', 'Cancel CR', 'Alasan Pembatalan', 'btn-dark', 'Cancel',
            '<i class="fas fa-exclamation-triangle mr-1"></i> CR akan menjadi <strong>CANCELLED</strong> secara permanen dan tidak bisa diproses lagi.');
    });
    /* ── TAKEOUT / RESTORE SUB-ITEM ── */
    function submitPost(url) {
        var $f = $('<form method="POST" action="' + url + '"></form>').append(
            '<input type="hidden" name="_token" value="' + $('meta[name="csrf-token"]').attr('content') + '">'
        );
        $('body').append($f); $f.submit();
    }

    $(document).on('click', '.btn-takeout-detail', function () {
        var crId = $(this).data('cr'), detailId = $(this).data('detail'), desc = $(this).data('desc') || 'item ini';
        openNotesModal(crId, 'detail/' + detailId + '/takeout', 'Takeout Item', 'Alasan Takeout', 'btn-danger', 'Takeout',
            '<i class="fas fa-info-circle mr-1"></i> Item "<strong>' + esc(desc) + '</strong>" tidak akan dibawa proses. Nilai CR otomatis berkurang. Jika semua item di-takeout, CR menjadi <strong>REJECTED</strong>.');
    });

    $(document).on('click', '.btn-restore-detail', function () {
        var crId = $(this).data('cr'), detailId = $(this).data('detail');
        Swal.fire({
            title: 'Kembalikan item?', text: 'Item akan ikut diproses lagi dan nilai CR bertambah.',
            icon: 'question', showCancelButton: true, confirmButtonColor: WARNA_UTAMA, cancelButtonColor: WARNA_BATAL,
            confirmButtonText: 'Ya, Kembalikan', cancelButtonText: 'Batal'
        }).then(function (r) {
            if (r.isConfirmed) submitPost('/budget-request/' + crId + '/detail/' + detailId + '/restore');
        });
    });

    /* ── FINANCE VERIFY ── */
    $(document).on('click', '.btn-finance-verify', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        $('#financeVerifyText').text('Verifikasi CR ' + code + '? Status akan menjadi VERIFIED dan menunggu approval CEO.');
        $('#frmFinanceVerify').attr('action', '/budget-request/' + id + '/finance-verify');
        $('#frmFinanceVerify textarea').val('');
        $('#mdlFinanceVerify').modal('show');
    });

    /* ── SETTLEMENT — HEAD CHECK / FINANCE VERIFY / CEO CLOSE (modal approve
       yang sama, action & teks disesuaikan per tahap) ── */
    function openSettlementApproveModal(id, code, action, text, btnText) {
        $('#verifyRealText').text(text);
        $('#frmVerifyRealization').attr('action', '/budget-request/' + id + '/' + action);
        $('#frmVerifyRealization textarea').val('');
        $('#btnVerifyRealSubmit').html('<i class="fas fa-check-double mr-1"></i> ' + btnText);
        $('#mdlVerifyRealization').modal('show');
    }

    $(document).on('click', '.btn-head-check-settlement', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        openSettlementApproveModal(id, code, 'head-check-settlement',
            'Check Settlement CR ' + code + '? Status akan berubah menjadi SETTLE CHECKED, menunggu verifikasi Finance.',
            'Check → Settle Checked');
    });
    $(document).on('click', '.btn-finance-verify-settlement', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        openSettlementApproveModal(id, code, 'finance-verify-settlement',
            'Verifikasi Settlement CR ' + code + '? Status akan berubah menjadi SETTLE VERIFIED. CR dengan Expense Claim lanjut ke Settle Approve, selebihnya langsung menunggu Close.',
            'Verify → Settle Verified');
    });
    $(document).on('click', '.btn-settle-approve', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        openSettlementApproveModal(id, code, 'settle-approve',
            'Setujui Expense Claim CR ' + code + '? Pengajuan lanjut ke antrean Second Approve Approver.',
            'Approve → Second Approve');
    });
    $(document).on('click', '.btn-second-approve', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        openSettlementApproveModal(id, code, 'second-approve',
            'Setujui Expense Claim CR ' + code + '? Pengajuan masuk antrean Buat Batch Baru Treasury, jadi Process setelah dibatch, dan CLOSED otomatis setelah dibayar.',
            'Approve → Treasury');
    });
    $(document).on('click', '.btn-settle-reject', function () {
        openNotesModal($(this).data('id'), 'settle-reject', 'Reject Expense Claim', 'Alasan', 'btn-danger', 'Reject → Paid',
            '<i class="fas fa-info-circle mr-1"></i> CR kembali ke <strong>PAID</strong> untuk disettle ulang. Expense Claim yang sudah terbentuk dibatalkan.');
    });
    $(document).on('click', '.btn-second-reject', function () {
        openNotesModal($(this).data('id'), 'second-reject', 'Reject Expense Claim', 'Alasan', 'btn-danger', 'Reject → Paid',
            '<i class="fas fa-info-circle mr-1"></i> CR kembali ke <strong>PAID</strong> untuk disettle ulang. Expense Claim yang sudah terbentuk dibatalkan.');
    });
    $(document).on('click', '.btn-ceo-close-settlement', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        openSettlementApproveModal(id, code, 'ceo-close-settlement',
            'Close Settlement CR ' + code + '? Status akan berubah menjadi CLOSED.',
            'Close');
    });

    $(document).on('click', '.btn-head-reject-settlement', function () {
        openNotesModal($(this).data('id'), 'head-reject-settlement', 'Tolak Settlement', 'Alasan', 'btn-danger', 'Tolak → Paid',
            '<i class="fas fa-info-circle mr-1"></i> CR akan dikembalikan ke status <strong>PAID</strong> untuk diajukan ulang Settlement-nya.');
    });
    $(document).on('click', '.btn-manager-reject-settlement', function () {
        openNotesModal($(this).data('id'), 'manager-reject-settlement', 'Tolak Settlement', 'Alasan', 'btn-danger', 'Tolak → Paid',
            '<i class="fas fa-info-circle mr-1"></i> CR akan dikembalikan ke status <strong>PAID</strong> untuk diajukan ulang Settlement-nya.');
    });
    $(document).on('click', '.btn-finance-reject-settlement', function () {
        openNotesModal($(this).data('id'), 'finance-reject-settlement', 'Tolak Settlement', 'Alasan', 'btn-danger', 'Tolak → Paid',
            '<i class="fas fa-info-circle mr-1"></i> CR akan dikembalikan ke status <strong>PAID</strong> untuk diajukan ulang Settlement-nya.');
    });

    /* ── MODAL DETAIL ── */
    // Set badge angka pada tab: tampil bila > 0, sembunyi bila 0
    function setTabCount(sel, n) {
        var el = $(sel);
        if (n && n > 0) { el.text(n).show(); }
        else { el.text('').hide(); }
    }

    /* Badge status di modal memakai peta yang sama dengan tabel dan drawer
       (statusMeta, berasal dari App\Support\WorkflowStatus). Peta warna
       kedua yang dulu ada di sini sudah dihapus. */
    function statusBadgeHtml(kode, labelCadangan) {
        var m = statusMeta[kode] || { label: labelCadangan || kode, tone: 'neutral', description: null };
        return '<span class="ts-status ts-status-' + m.tone + ' ts-status-sm"'
             + (m.description ? ' title="' + esc(m.label + ' — ' + m.description) + '"' : '')
             + '>' + esc(m.label) + '</span>';
    }

    /* ── IMAGE VIEWER — carousel + zoom (ala WhatsApp), untuk tab Request & Realization ── */
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

            /* Lampiran non-gambar tidak bisa digambar di <img>; slide-nya diganti
               kartu logo + nama file, posisinya tetap sesuai urutan unggahan. */
            if (item.is_image === false) {
                stageImg.style.display = 'none';
                $('#imgViewerFile').css('display', 'flex').attr('href', item.full);
                $('#imgViewerFileIcon').attr('class', 'fas ' + (item.is_pdf ? 'fa-file-pdf' : 'fa-file-alt'));
                $('#imgViewerFileName').text(item.name || '');
            } else {
                $('#imgViewerFile').hide();
                stageImg.style.display = '';
                stageImg.src = item.full;
            }

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
            images = (groupImages || []).filter(function (it) { return it && it.full; });
            if (!images.length) return; // jangan buka overlay kosong
            idx = Math.min(Math.max(startIndex || 0, 0), images.length - 1);
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

        // Trigger: klik thumbnail gambar pada grid Attachment (Request maupun Realization)
        $(document).on('click', '.cr-att-thumb', function () {
            // Grup carousel = .cr-att-grid terdekat. Kalau thumbnail dirender
            // tanpa wrapper (mis. langsung di sel tabel Settlement), pakai
            // parent terdekat supaya daftar gambar tidak pernah kosong.
            var $group = $(this).closest('.cr-att-grid');
            if (!$group.length) $group = $(this).closest('td').length ? $(this).closest('td') : $(this).parent();
            var $thumbs = $group.find('.cr-att-thumb');
            if (!$thumbs.length) $thumbs = $(this);
            var groupImages = $thumbs.map(function () {
                return { full: $(this).data('full'), name: $(this).data('name') };
            }).get();
            var startIndex = $thumbs.index(this);
            openViewer(groupImages, startIndex < 0 ? 0 : startIndex);
        });

        // Dipakai tombol "View" pada kolom Aksi tab Request (lampiran per item).
        window.__crOpenImgViewer = openViewer;

        /* Membuka lampiran satu baris. Gambar masuk carousel; PDF dan dokumen lain
           TIDAK bisa ditampilkan di carousel, jadi begitu ada file non-gambar
           seluruh lampiran dirinci dalam satu daftar: gambar membuka carousel,
           PDF/dokumen dibuka di tab baru. Sebelumnya file non-gambar ikut hilang
           kalau baris itu juga punya gambar. */
        function bukaLampiran(atts) {
            atts = atts || [];
            if (!atts.length) return;

            // Semua lampiran masuk satu carousel dengan urutan seperti saat
            // diunggah; yang bukan gambar dirender sebagai kartu logo + nama file.
            openViewer(atts.map(function (a) {
                return {
                    full:     '/storage/' + a.path,
                    name:     a.original_name,
                    is_image: !!a.is_image,
                    is_pdf:   !!a.is_pdf
                };
            }), 0);
        }

        // Tombol View lampiran per item pada tab Request.
        $(document).on('click', '.btn-view-detail-att', function () {
            var id = $(this).data('detail');
            bukaLampiran((window.__crDetailAtt && window.__crDetailAtt[id]) || []);
        });

        /* Buka/tutup grup Chart of Account pada tab Request. */
        $(document).on('click', '#crDetailBody .cr-group-row', function () {
            var $sub = $('#crDetailBody .' + $(this).data('target'));
            var buka = $sub.first().is(':hidden');
            $sub.toggle(buka);
            $(this).find('.cr-caret').toggleClass('rotated', buka);
        });
        /* Grup yang sama untuk tab Settlement. */
        $(document).on('click', '#crRealizationBody .cr-group-row', function () {
            var $sub = $('#crRealizationBody .' + $(this).data('target'));
            var buka = $sub.first().is(':hidden');
            $sub.toggle(buka);
            $(this).find('.cr-caret').toggleClass('rotated', buka);
        });

        /* Satu tombol Expand/Collapse All: label & ikonnya berganti mengikuti
           keadaan. data-body menunjuk tbody yang digrup (Request/Settlement). */
        $(document).on('click', '.cr-toggle-all', function () {
            var $btn = $(this);
            var sel = '#' + $btn.data('body');
            var expand = $btn.data('expanded') ? false : true;
            $(sel + ' .cr-group-item').toggle(expand);
            $(sel + ' .cr-caret').toggleClass('rotated', expand);
            $btn.data('expanded', expand ? 1 : 0)
                .html(expand
                    ? '<i class="fas fa-angle-double-up mr-1"></i>Collapse All'
                    : '<i class="fas fa-angle-double-down mr-1"></i>Expand All');
        });

        // Tombol View lampiran baris Settlement — pola sama dengan tab Request.
        $(document).on('click', '.btn-view-real-att', function () {
            bukaLampiran((window.__crRealAtt && window.__crRealAtt[$(this).data('row')]) || []);
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

    function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
    function escAttr(s) { return esc(s).replace(/"/g, '&quot;'); }

    /* Keadaan kosong ringkas untuk ruang sempit di dalam modal — bentuknya
       sama dengan komponen keadaan kosong yang dipakai tabel dan drawer. */
    function kosongRingkas(pesan) {
        return '<div class="ts-empty ts-empty-compact"><p class="ts-empty-msg">' + esc(pesan) + '</p></div>';
    }

    /* Markup kerangka disimpan sekali karena saat pemuatan gagal isinya
       diganti pesan galat; tanpa ini pembukaan berikutnya kehilangan kerangka. */
    var kerangkaDetail = null;
    function pulihkanKerangka() {
        var el = $('#crDetailLoading');
        if (kerangkaDetail === null) kerangkaDetail = el.html();
        else el.html(kerangkaDetail);
    }

    /* Memisah label gabungan "Sub COA — Deskripsi" menjadi dua bagian supaya
       tab Request & Settlement bisa menampilkannya dua baris. Pemisahnya bisa
       hyphen atau em/en dash yang diapit spasi. */
    function splitCoaLabel(teks) {
        var s = (teks || '').trim();
        var m = s.split(/\s+[–—-]\s+/);
        if (m.length >= 2) return { sub: m[0], rest: m.slice(1).join(' — ') };
        return { sub: s, rest: '' };
    }

    function openCRDetail(id, tab) {
        pulihkanKerangka();
        $('#crDetailLoading').show(); $('#crDetailContent').hide();
        $('#mdlDetailCR').modal('show');

        $.get('/budget-request/' + id + '/detail-json', function (d) {
            $('#crModalTitle').text(d.description);
            $('#crModalSubtitle').text(d.code);
            $('#crCode').text(d.code);
            $('#crStatusBadge').html(statusBadgeHtml(d.status, d.status_label));
            $('#crNobBadge').html(d.is_nob ? '<span class="badge badge-danger px-2 py-1 mt-1">NOB</span>' : '');
            $('#crType').text(d.type);
            $('#crAmount').text(d.amount_formatted);
            $('#crRealizationAmount').text(d.total_realization_formatted || '0');
            /* Settlement > pengajuan bukan refund — itu kekurangan bayar yang
               diselesaikan lewat Second Payment (istilah yang sudah dipakai
               modul ini; lihat tab Settlement & Todo List). Menyebutnya
               "Refund 0" menutupi bahwa masih ada kewajiban yang belum dibayar. */
            (function () {
                var lebih = parseInt(d.settlement_overage, 10) || 0;
                $('#crRefundLabel').text(lebih > 0 ? 'Second Payment' : 'Refund');
                /* Warna mengikuti arah selisihnya, bukan nama barisnya:
                   kurang bayar (Second Payment) merah terang, dana kembali
                   (Refund) hijau, dan nol ikut nada panel supaya tidak
                   terbaca sebagai peringatan. */
                var refund = parseInt(d.refund, 10) || 0;
                $('#crRefundAmount')
                    .text(lebih > 0 ? (d.settlement_overage_formatted || '0') : (d.refund_formatted || '0'))
                    .css('color', '')
                    .removeClass('dm-calc-neg dm-calc-pos dm-calc-zero')
                    .addClass(lebih > 0 ? 'dm-calc-neg' : (refund > 0 ? 'dm-calc-pos' : 'dm-calc-zero'));
            })();
            $('#crBeneficiary').text(d.beneficiary);
            $('#crBank').text(d.bank + ' — ' + d.account_number);
            $('#crApplicant').text(d.applicant);

            // Info tab
            // Reference = [Kode PO] Scope of Work, mis. [0084] MS MSG - SEP 2026.
            var refScope = (d.reference_scope && d.reference_scope !== '-') ? d.reference_scope : '';
            var refKode  = (d.code_reference && d.code_reference !== '-') ? d.code_reference : '';
            $('#infoCode').text((refKode ? '[' + refKode + '] ' : '') + (refScope || '-'));
            $('#infoCrCode').text(d.code);
            // Badge status DIHAPUS dari deskripsi — status sudah tampil di panel kiri.
            $('#infoDesc').html(
                (d.is_nob ? '<span class="badge badge-danger mr-1" style="font-size:9px;">NOB</span>' : '') +
                esc(d.description)
            );
            // Badge tipe: Cash Advance burgundy lembut, selain itu biru lembut.
            var gayaTipeModal = d.is_cash_advance
                ? 'background:var(--accent-tint);color:var(--accent-text);border:1px solid var(--accent-tint-2);'
                : 'background:var(--info-bg);color:var(--info);border:1px solid var(--info-bg);';
            $('#infoType').html('<span style="' + gayaTipeModal + 'font-size:11px;font-weight:700;padding:2px 10px;border-radius:var(--radius-sm);">' + esc(d.type) + '</span>');
            $('#infoProject').text(d.project);
            $('#infoCluster').text(d.cluster);
            $('#infoAmount').html('<strong style="color:var(--info);">' + d.amount_formatted + '</strong>');
            $('#infoEmail').text(d.email || '-');

            if (d.payment_notes) {
                $('#infoPaymentNotes').text(d.payment_notes);
                $('#crPaymentNotesCard').show();
                $('#crScopeRow').addClass('has-payment');
            } else {
                $('#crPaymentNotesCard').hide();
                $('#crScopeRow').removeClass('has-payment');
            }
            $('#infoNotes').text(d.notes || '-');

            // Detail tab
            setTabCount('#crDetailCount', d.details.length);
            var canModerate = !!d.can_moderate_items;
            window.__crDetailAtt = {};
            var anyAtt = d.details.some(function (x) { return x.attachments && x.attachments.length; });
            var showActionCol = canModerate || anyAtt;
            $('#crDetailActionTh, .crDetailActionCell').toggleClass('d-none', !showActionCol);
            /* Rincian dikelompokkan per Chart of Account (pola tab Budget di
               Budgeting): satu baris grup berisi nama akun + totalnya, baris
               itemnya disembunyikan sampai grupnya dibuka. Urutan grup mengikuti
               kemunculan pertama akun pada data, jadi tidak mengacak urutan asli. */
            var dHtml = '', grand = 0, nomor = 0, grupIdx = 0;
            var urutanAkun = [], perAkun = {};
            d.details.forEach(function (det) {
                var acc = det.account || '-';
                if (!perAkun[acc]) { perAkun[acc] = []; urutanAkun.push(acc); }
                perAkun[acc].push(det);
            });

            urutanAkun.forEach(function (acc) {
              var items = perAkun[acc];
              var grupClass = 'cr-dgrp-' + (grupIdx++);
              var grupTotal = items.reduce(function (t, x) { return t + (x.is_takeout ? 0 : x.total); }, 0);

              dHtml += '<tr class="cr-group-row" data-target="' + grupClass + '" style="cursor:pointer;">' +
                  '<td style="font-weight:700;">' +
                      '<i class="fas fa-chevron-right mr-2 cr-caret" style="font-size:10px;transition:transform .15s;display:inline-block;"></i>' +
                      esc(acc) + ' <span class="badge badge-secondary ml-1" style="font-size:9px;">' + items.length + '</span>' +
                  '</td>' +
                  '<td></td><td></td>' +
                  '<td class="text-right" style="font-weight:700;">' + grupTotal.toLocaleString('id-ID') + '</td>' +
                  (showActionCol ? '<td></td>' : '') +
              '</tr>';

              items.forEach(function(det) {
                var i = nomor++;
                var isNob    = !!det.is_nob; // kolom, bukan tebakan dari teks catatan
                var isTakeout = !!det.is_takeout;
                if (!isTakeout) grand += det.total;

                // Hanya isi CSS-nya (tanpa atribut), karena baris item juga perlu
                // display:none saat grupnya tertutup.
                var rowStyle = isTakeout
                    ? 'color:var(--text-faint);background:var(--surface-2);'
                    : (isNob ? 'color:var(--danger);' : '');
                var strike = isTakeout ? 'text-decoration:line-through;' : '';

                var takeoutBadge = isTakeout
                    ? ' <span class="badge badge-warning" style="font-size:9px;" title="' + escAttr(det.takeout_notes || '') + '">Takeout &middot; ' + esc(det.takeout_stage_label || '-') + '</span>'
                    : '';

                var atts = det.attachments || [];
                window.__crDetailAtt[det.id] = atts;
                var attBadge = atts.length
                    ? ' <span class="badge badge-light border text-muted" style="font-size:9px;"><i class="fas fa-paperclip mr-1"></i>' + atts.length + '</span>'
                    : '';

                var btnStyle = 'white-space:nowrap;padding:2px 8px;font-size:11px;line-height:1.4;';
                var actionInner = '';
                if (atts.length) {
                    actionInner += '<button type="button" class="btn btn-info btn-view-detail-att mr-1" style="' + btnStyle + '" data-detail="' + det.id + '" title="Lihat lampiran"><i class="fas fa-eye"></i></button>';
                }
                if (canModerate) {
                    actionInner += isTakeout
                        ? '<button type="button" class="btn btn-outline-secondary btn-restore-detail" style="' + btnStyle + '" data-cr="' + d.id + '" data-detail="' + det.id + '" title="Kembalikan item"><i class="fas fa-undo"></i></button>'
                        : '<button type="button" class="btn btn-outline-danger btn-takeout-detail" style="' + btnStyle + '" data-cr="' + d.id + '" data-detail="' + det.id + '" data-desc="' + escAttr(det.description || '') + '" title="Takeout item"><i class="fas fa-cut"></i></button>';
                }
                var actionCell = showActionCol ? '<td class="text-center text-nowrap">' + actionInner + '</td>' : '';

                // Dua baris: Sub COA (+ jumlah lampiran) di atas, deskripsi di bawah.
                var belah = splitCoaLabel(det.item_label || det.description || '-');
                dHtml += '<tr class="' + grupClass + ' cr-group-item" style="display:none;' + rowStyle + '">' +
                    '<td class="cr-indent" style="' + strike + '">' +
                        '<div style="font-weight:600;">' + esc(belah.sub) + takeoutBadge + attBadge + '</div>' +
                        (belah.rest ? '<div style="color:var(--text-faint);font-size:11px;margin-top:2px;">' + esc(belah.rest) + '</div>' : '') +
                        (isTakeout && det.takeout_notes ? '<small class="text-muted" style="text-decoration:none;display:block;margin-top:2px;">Alasan: ' + esc(det.takeout_notes) + '</small>' : '') +
                    '</td>' +
                    '<td class="text-right" style="' + strike + '">' + det.amount_formatted + '</td>' +
                    '<td class="text-center" style="' + strike + '">' + det.qty + '</td>' +
                    '<td class="text-right" style="' + strike + '"><strong>' + det.total_formatted + '</strong></td>' +
                    actionCell +
                '</tr>';
              });
            });
            $('#crDetailBody').html(dHtml || '<tr><td colspan="6" class="text-center text-muted py-3">No item detail yet.</td></tr>');
            $('#crDetailTotal').text(grand.toLocaleString('id-ID'));
            $('#btnToggleCrDetail').data('expanded', 0).html('<i class="fas fa-angle-double-down mr-1"></i>Expand All');

            // Helper: render grid thumbnail untuk attachments (dipakai tab Request & Realization)
            function renderAttGrid(atts) {
                if (!atts || !atts.length) return kosongRingkas('Belum ada lampiran.');
                var html = '';
                atts.forEach(function (a) {
                    var url = '/storage/' + a.path;
                    if (a.is_image) {
                        html += '<div class="cr-att-card">' +
                            '<img loading="lazy" src="' + url + '" class="cr-att-thumb" data-full="' + url + '" data-name="' + a.original_name + '">' +
                            '<div class="cr-att-name">' + a.original_name + '</div>' +
                        '</div>';
                    } else {
                        var icon = a.is_pdf ? 'fa-file-pdf' : 'fa-file-alt';
                        html += '<div class="cr-att-card">' +
                            '<a href="' + url + '" target="_blank" rel="noopener" class="cr-att-icon"><i class="fas ' + icon + '"></i></a>' +
                            '<div class="cr-att-name">' + a.original_name + '</div>' +
                        '</div>';
                    }
                });
                return html;
            }

            /* Tab Settlement hanya berlaku untuk Cash Advance — jenis pengajuan
               lain (Cost Payment) tidak punya tahap settlement sama sekali, jadi
               tabnya disembunyikan, bukan ditampilkan kosong. */
            var $tabSettle = $('#crDetailTabs a[href="#crPaneRealization"]').closest('li');
            if (d.is_cash_advance) {
                $tabSettle.show();
            } else {
                $tabSettle.hide();
                // Kalau tab ini sedang aktif dari CR sebelumnya, balik ke Info.
                if ($('#crPaneRealization').hasClass('active')) {
                    $('#crDetailTabs a[href="#crPaneInfo"]').tab('show');
                }
            }

            // Realization tab
            setTabCount('#crRealizationCount', d.realizations.length);
            if (d.is_cash_advance && d.realizations.length) {
                $('#crNoRealization').hide();
                $('#crRealizationWrapper').show();
                var rHtml = '', rGrand = 0, rNomor = 0, rGrupIdx = 0;
                // Lampiran per baris tidak lagi digelar sebagai grid thumbnail —
                // cukup satu ikon "view" di kolom Action yang membuka viewer.
                window.__crRealAtt = {};

                // Dikelompokkan per Chart of Account, pola yang sama dengan tab Request.
                var rUrutan = [], rPerAkun = {};
                d.realizations.forEach(function (r) {
                    var acc = r.account || '-';
                    if (!rPerAkun[acc]) { rPerAkun[acc] = []; rUrutan.push(acc); }
                    rPerAkun[acc].push(r);
                });

                // Var = Request - Settlement per baris (angka yang ditampilkan di
                // kolom Request & Settlement baris itu). Hijau = sisa, merah = lebih.
                function fmtVar(n) {
                    n = Math.round(n || 0);
                    if (n === 0) return '<span class="text-muted">0</span>';
                    var warna = n > 0 ? 'var(--success)' : 'var(--danger)';
                    return '<span style="color:' + warna + ';font-weight:600;">'
                        + (n > 0 ? '' : '-') + Math.abs(n).toLocaleString('id-ID') + '</span>';
                }

                rUrutan.forEach(function (acc) {
                  var items = rPerAkun[acc];
                  var grupClass = 'cr-rgrp-' + (rGrupIdx++);
                  var grupReq    = items.reduce(function (t, x) { return t + (x.total_cr || 0); }, 0);
                  var grupSettle = items.reduce(function (t, x) { return t + (x.actual || 0); }, 0);

                  rHtml += '<tr class="cr-group-row" data-target="' + grupClass + '" style="cursor:pointer;">'
                      + '<td style="font-weight:700;">'
                          + '<i class="fas fa-chevron-right mr-2 cr-caret" style="font-size:10px;transition:transform .15s;display:inline-block;"></i>'
                          + esc(acc) + ' <span class="badge badge-secondary ml-1" style="font-size:9px;">' + items.length + '</span>'
                      + '</td>'
                      + '<td class="text-right" style="font-weight:700;">' + grupReq.toLocaleString('id-ID') + '</td>'
                      + '<td class="text-right" style="font-weight:700;">' + grupSettle.toLocaleString('id-ID') + '</td>'
                      + '<td class="text-right" style="font-weight:700;">' + fmtVar(grupReq - grupSettle) + '</td>'
                      + '<td></td>'
                      + '</tr>';

                  items.forEach(function (r) {
                    var i = rNomor++;
                    rGrand += r.total;
                    var itemAtts = r.attachments || [];
                    window.__crRealAtt[i] = itemAtts;
                    var aksi = itemAtts.length
                        ? '<button type="button" class="btn btn-info btn-view-real-att" '
                          + 'style="white-space:nowrap;padding:2px 8px;font-size:11px;line-height:1.4;" '
                          + 'data-row="' + i + '" title="Lihat lampiran item (' + itemAtts.length + ')">'
                          + '<i class="fas fa-eye"></i></button>'
                        : '<span class="text-muted" style="font-size:11px;">-</span>';

                    // Kolom pertama digabung: Sub COA (+lampiran), lalu deskripsi
                    // request dan deskripsi settlement masing-masing satu baris.
                    var subCoa   = r.sub_coa || r.des_request || r.cr_item || '-';
                    var reqDesc  = r.des_request || r.cr_item || '-';
                    var setDesc  = r.des_settle || '-';
                    var noteDesc = r.change_note || '-';
                    var attR     = itemAtts.length
                        ? ' <span class="badge badge-light border text-muted" style="font-size:9px;"><i class="fas fa-paperclip mr-1"></i>' + itemAtts.length + '</span>'
                        : '';

                    rHtml += '<tr class="' + grupClass + ' cr-group-item" style="display:none;">'
                        + '<td class="cr-indent">'
                            + '<div style="font-weight:600;">' + esc(subCoa) + attR + '</div>'
                            + '<div class="cr-clip" style="font-size:11px;"><span class="text-muted">Request:</span> ' + esc(reqDesc) + '</div>'
                            + '<div class="cr-clip" style="font-size:11px;"><span class="text-muted">Settle:</span> ' + esc(setDesc) + '</div>'
                            + '<div class="cr-clip" style="font-size:11px;"><span class="text-muted">Note:</span> ' + esc(noteDesc) + '</div>'
                        + '</td>'
                        + '<td class="text-right">' + r.total_cr_formatted + '</td>'
                        + '<td class="text-right"><strong>' + r.actual_formatted + '</strong></td>'
                        + '<td class="text-right">' + fmtVar((r.total_cr || 0) - (r.actual || 0)) + '</td>'
                        + '<td class="text-center">' + aksi + '</td>'
                        + '</tr>';
                  });
                });
                $('#crRealizationBody').html(rHtml);
                $('#crRealizationTotal').text(rGrand.toLocaleString('id-ID'));
                $('#btnToggleCrReal').data('expanded', 0).html('<i class="fas fa-angle-double-down mr-1"></i>Expand All');
                $('#crRealizationAtts').html(renderAttGrid(d.realization_attachments));

                // Second Payment — cuma tampil kalau Settlement pernah melebihi
                // nilai pengajuan awal (ada baris di second_payments).
                var sp = d.second_payments || [];
                if (sp.length) {
                    var spHtml = '<h6 style="font-size:12px;font-weight:700;color:var(--text-faint);text-transform:uppercase;">' +
                        '<i class="fas fa-hand-holding-usd mr-1"></i> Second Payment</h6>' +
                        '<table class="table table-sm table-bordered mb-0" style="font-size:11.5px;">' +
                        '<tbody>' +
                        '<tr><td>Original Request</td><td class="text-right"><strong>' + esc(d.amount_formatted) + '</strong></td></tr>' +
                        '<tr><td>Settlement</td><td class="text-right"><strong>' + esc(d.total_realization_formatted) + '</strong></td></tr>' +
                        sp.map(function (p) {
                            // Pembayarannya lewat batch Treasury — tanpa kode
                            // batch, "Pending" saja tidak memberi tahu apa pun
                            // tentang di mana uangnya sekarang.
                            var badge;
                            if (p.status === 'PAID') {
                                badge = '<span class="badge badge-success">Paid' + (p.paid_at ? ' — ' + esc(p.paid_at) : '') + '</span>'
                                      + (p.batch_code ? ' <span class="badge badge-light border">' + esc(p.batch_code) + '</span>' : '');
                            } else if (p.batch_code) {
                                badge = '<span class="badge badge-info">Menunggu pembayaran batch ' + esc(p.batch_code) + '</span>';
                            } else {
                                badge = '<span class="badge badge-warning">Belum masuk batch Treasury</span>';
                            }
                            return '<tr><td>Additional Payment</td><td class="text-right"><strong>' + esc(p.amount_formatted) + '</strong> ' + badge + '</td></tr>';
                        }).join('') +
                        '</tbody></table>';
                    $('#crSecondPaymentBox').html(spHtml).show();
                } else {
                    $('#crSecondPaymentBox').hide();
                }
            } else {
                $('#crNoRealization').show();
                $('#crRealizationWrapper').hide();
                $('#crSecondPaymentBox').hide();
            }

            // Attachment (di bawah tabel Request)
            var atts = d.attachments || [];
            setTabCount('#crAttCount', atts.length);
            $('#crAttBody').html(renderAttGrid(atts));

            // Logs tab — gaya chat, sama persis dengan tab Log Purchase Order:
            // log dari user yang sedang login rata kanan ("mine"), lainnya rata
            // kiri, avatar inisial dari nama actor. Tanpa nama role.
            var logHtml = '';
            (d.logs || []).slice().reverse().forEach(function (log) {
                var mine = log.user_id && currentUserId && log.user_id === currentUserId;
                var actor = log.actor || 'System';
                var initials = actor.trim().split(/\s+/).map(function (w) { return w[0]; }).join('').slice(0, 2).toUpperCase() || '?';
                logHtml += '<div class="cr-chat-row ' + (mine ? 'mine' : '') + '">' +
                    '<div class="cr-chat-av">' + initials + '</div>' +
                    '<div>' +
                        '<div class="cr-chat-meta"><strong>' + actor + '</strong> · ' + log.date + '</div>' +
                        '<div class="cr-chat-bubble">' +
                            '<span class="badge badge-' + log.action_color + ' px-2 py-1" style="font-size:9px;">' + log.action_label + '</span>' +
                            (log.status_from && log.status_to ? ' <span style="font-size:10px;opacity:.75;">' + (log.status_from_label || log.status_from) + ' → ' + (log.status_to_label || log.status_to) + '</span>' : '') +
                            (log.notes ? '<div class="cr-chat-note">' + log.notes + '</div>' : '') +
                        '</div>' +
                    '</div>' +
                '</div>';
            });
            $('#crLogBody').html(logHtml || kosongRingkas('Belum ada riwayat persetujuan.'));

            // Buka tab tertentu bila diminta pemanggil (mis. klik nilai Request ->
            // tab Request, klik badge Status -> tab Log) — default tab pertama (Info).
            var $targetTab = tab ? $('#crDetailTabs a[href="' + tab + '"]') : $();
            ($targetTab.length ? $targetTab : $('#crDetailTabs a:first')).tab('show');
            $('#crDetailLoading').hide(); $('#crDetailContent').show();

        }).fail(function () {
            $('#crDetailLoading').html(
                '<div class="ts-empty"><span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-exclamation-triangle"></i></span>' +
                '<p class="ts-empty-title">Detail gagal dimuat</p>' +
                '<p class="ts-empty-msg">Periksa koneksi Anda, lalu buka kembali Cost Request ini.</p></div>'
            );
            if (window.toast) toast.error('Gagal memuat', 'Detail Cost Request tidak bisa ditampilkan.');
        });
    }

    $(document).on('click', '.btn-detail-cr', function () {
        openCRDetail($(this).data('id'), $(this).data('tab'));
    });

    /* ================================================================
       DRAWER TINJAU CR

       Memakai endpoint /budget-request/{id}/detail-json yang sudah ada —
       tidak ada query, route, maupun aturan bisnis baru. Drawer hanya
       menyusun ulang data yang sama menjadi permukaan tinjau: status
       beserta konteksnya, ringkasan, timeline, dan lampiran.

       Tombol aksinya DISALIN dari baris tabel yang bersangkutan, sehingga
       gerbang peran dan status persis sama dengan yang sudah dirender
       server — tidak ada logika izin kedua di sisi klien.
       ================================================================ */

    var crDrawerId = null;

    /* esc() sudah didefinisikan di atas (dipakai modal detail); tidak dibuat
       ulang di sini supaya tidak ada dua fungsi dengan nama yang sama. */

    function drawerKV(pasangan) {
        var html = '<dl class="ts-dw-kv">';
        pasangan.forEach(function (p) {
            if (p[1] === null || p[1] === undefined || p[1] === '') return;
            html += '<dt>' + esc(p[0]) + '</dt><dd' + (p[2] ? ' class="is-amount"' : '') + '>' + esc(p[1]) + '</dd>';
        });
        return html + '</dl>';
    }

    function openCRDrawer(id, pemicu) {
        crDrawerId = id;

        $('#crDrawerContent').attr('hidden', true).empty();
        $('#crDrawerLoading').show();
        $('#crDrawer-eyebrow').text('');
        $('#crDrawer-title').text('Memuat…');
        $('#crDrawerActions').empty();

        TesysDrawer.open('crDrawer', pemicu);

        // Aksi baris disalin apa adanya, termasuk form dan token CSRF-nya.
        var baris = pemicu ? $(pemicu).closest('tr') : $('.cr-tbl:not([data-tab="db"]) tbody tr[data-id="' + id + '"]').first();
        var aksi = baris.find('.ts-actions').children('.ts-action-primary, form').clone(true, true);
        if (aksi.length) $('#crDrawerActions').append(aksi);

        function tampilkanGagal(pesan) {
            $('#crDrawerLoading').hide();
            $('#crDrawerContent').html(
                '<div class="ts-empty">'
                + '<span class="ts-empty-ic"><i class="fas fa-exclamation-triangle"></i></span>'
                + '<p class="ts-empty-title">Detail belum dapat dimuat</p>'
                + '<p class="ts-empty-msg">' + esc(pesan) + '</p>'
                + '<div class="ts-empty-action"><button type="button" class="ts-action-primary is-neutral" id="crDrawerRetry">'
                + '<i class="fas fa-redo"></i><span class="ts-al">Coba lagi</span></button></div></div>'
            ).removeAttr('hidden');
        }

        $.get('/budget-request/' + id + '/detail-json')
            .done(function (d) {
                /*
                  Saat sesi habis, Laravel mengalihkan permintaan AJAX ini ke
                  halaman login — jQuery mengikuti pengalihan itu dan menerima
                  HTML berstatus 200, bukan galat. Tanpa penjagaan ini drawer
                  akan merender isi kosong seolah datanya memang tidak ada.
                */
                if (!d || typeof d !== 'object' || !d.code) {
                    tampilkanGagal('Sesi Anda mungkin sudah berakhir. Muat ulang halaman, lalu masuk kembali.');
                    return;
                }

                var meta = statusMeta[d.status] || { label: d.status_label || d.status, description: null, tone: 'neutral' };

                $('#crDrawer-eyebrow').text(d.code + (d.type ? ' · ' + d.type : ''));
                $('#crDrawer-title').text(d.description);

                var html = '';

                /* ---- Badge tipe CR: Cash Advance burgundy lembut, selain itu
                   (Cost Payment dll.) biru lembut. Memakai token warna yang ada
                   (--accent-tint / --info-bg), bukan hex baru. */
                var tipeBadge = '';
                if (d.type) {
                    var gayaTipe = d.is_cash_advance
                        ? 'background:var(--accent-tint);color:var(--accent-text);border:1px solid var(--accent-tint-2);'
                        : 'background:var(--info-bg);color:var(--info);border:1px solid var(--info-bg);';
                    tipeBadge = '<span style="' + gayaTipe + 'font-size:10px;font-weight:700;'
                              + 'padding:2px 8px;border-radius:var(--radius-sm);white-space:nowrap;">'
                              + esc(d.type) + '</span>';
                }

                /* ---- Nilai: nominal pengajuan + status di pojok kanan kartu.
                   Settlement/Refund hanya ditampilkan kalau nilainya > 0. */
                var sisi = '';
                var nilaiSettle = parseInt(d.total_realization, 10) || 0;
                if (nilaiSettle > 0) {
                    sisi += '<span class="ts-dw-amount-side"><span>Settlement</span>'
                          + '<strong>' + esc(d.total_realization_formatted) + '</strong></span>';
                }
                /* Kelebihan settlement dinamai Second Payment, bukan "Refund 0". */
                var lebihBayar = parseInt(d.settlement_overage, 10) || 0;
                var nilaiRefund = parseInt(d.refund, 10) || 0;
                if (lebihBayar > 0) {
                    sisi += '<span class="ts-dw-amount-side"><span>Second Payment</span>'
                          + '<strong>' + esc(d.settlement_overage_formatted) + '</strong></span>';
                } else if (nilaiRefund > 0) {
                    sisi += '<span class="ts-dw-amount-side"><span>Refund</span>'
                          + '<strong>' + esc(d.refund_formatted) + '</strong></span>';
                }

                html += '<div class="ts-dw-section"><div class="ts-dw-amount">'
                     +    '<span class="ts-dw-amount-main">'
                     +      '<span class="ts-dw-amount-label">Nilai pengajuan</span>'
                     +      '<span class="ts-dw-amount-value">' + esc(d.amount_formatted || '-') + '</span>'
                     +      '<span style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-top:8px;">'
                     +        tipeBadge
                     +        '<span class="ts-status ts-status-' + meta.tone + ' ts-status-sm">' + esc(meta.label) + '</span>'
                     +      '</span>'
                     +    '</span>'
                     +    sisi
                     +  '</div>'
                     +  (window.TesysWorkflow ? TesysWorkflow.render('budget-request', d.status) : '')
                     +  '</div>';

                /* ---- Badge Chart of Account: daftar COA unik yang dipakai CR,
                   supaya langsung terlihat biaya ini menyentuh akun apa saja. */
                var coaUnik = [];
                (d.details || []).forEach(function (det) {
                    var acc = det.account || '';
                    if (acc && acc !== '-' && coaUnik.indexOf(acc) === -1) coaUnik.push(acc);
                });
                if (coaUnik.length) {
                    html += '<div class="ts-dw-section"><p class="ts-dw-section-title">Chart of Account</p>'
                         +  '<div style="display:flex;gap:6px;flex-wrap:wrap;">';
                    coaUnik.forEach(function (acc) {
                        html += '<span style="background:var(--surface-2);color:var(--text-dim);'
                             +  'border:1px solid var(--border);font-size:11px;font-weight:600;'
                             +  'padding:3px 10px;border-radius:var(--radius-sm);">' + esc(acc) + '</span>';
                    });
                    html += '</div></div>';
                }

                /* ---- Informasi pengajuan: siapa & atas dasar apa.
                   Penerima menampilkan PIC-nya; Rekening memuat beneficiary +
                   bank & nomor. Purchase Order / Budget Plan dilebur jadi satu
                   baris Reference (Kode - Scope of Work). */
                var referensi = (d.code_reference && d.code_reference !== '-')
                    ? d.code_reference + (d.reference_scope && d.reference_scope !== '-' ? ' - ' + d.reference_scope : '')
                    : (d.reference_scope || null);
                html += '<div class="ts-dw-section"><p class="ts-dw-section-title">Informasi pengajuan</p>'
                     + drawerKV([
                         ['Requestor', d.applicant],
                         ['Beneficiary', d.pic],
                         ['Bank Account', d.beneficiary && d.bank
                             ? d.beneficiary + ' (' + d.bank + ' - ' + d.account_number + ')'
                             : (d.beneficiary || null)],
                         ['Reference', referensi],
                         ['Email', d.email],
                       ])
                     + '</div>';

                /* ---- Tanggal: dipisah dari informasi supaya keduanya
                   bisa dipindai sendiri-sendiri. */
                html += '<div class="ts-dw-section"><p class="ts-dw-section-title">Tanggal</p>'
                     + drawerKV([
                         ['Dibuat', d.created_at],
                         ['Diajukan', d.submitted_at],
                         ['Disetujui', d.approved_at],
                         ['Dibayar', d.paid_at],
                       ])
                     + '</div>';

                /* Timeline approval — memakai renderer bersama TesysTimeline,
                   bukan HTML sendiri, supaya modul lain (PO, Invoice) tinggal
                   memanggil fungsi yang sama. Payload datang terbaru-dulu dan
                   pembalikan urutannya ditangani di dalam renderer. */
                html += '<div class="ts-dw-section"><p class="ts-dw-section-title">Riwayat persetujuan</p>'
                     + TesysTimeline.render(d.logs, { pesanKosong: 'Belum ada riwayat persetujuan.', terbaruDulu: false })
                     + '</div>';

                /* Lampiran */
                html += '<div class="ts-dw-section"><p class="ts-dw-section-title">Lampiran'
                     + (d.attachments_count ? ' (' + d.attachments_count + ')' : '') + '</p>';
                if (d.attachments && d.attachments.length) {
                    html += '<div class="ts-dw-files">';
                    d.attachments.forEach(function (a) {
                        var ikon = a.is_image ? 'fa-image' : (a.is_pdf ? 'fa-file-pdf' : 'fa-paperclip');
                        html += '<a class="ts-dw-file" href="/storage/' + esc(a.path) + '" target="_blank" rel="noopener">'
                             +    '<i class="fas ' + ikon + '"></i>'
                             +    '<span>' + esc(a.original_name) + '</span>'
                             +    (a.size_formatted ? '<small>' + esc(a.size_formatted) + '</small>' : '')
                             +  '</a>';
                    });
                    html += '</div>';
                } else {
                    html += '<div class="ts-empty ts-empty-compact">'
                         +    '<p class="ts-empty-msg">Tidak ada lampiran.</p></div>';
                }
                html += '</div>';

                $('#crDrawerContent').html(html).removeAttr('hidden');
                $('#crDrawerLoading').hide();
            })
            .fail(function () {
                tampilkanGagal('Sambungan ke server terputus atau data sedang tidak tersedia.');
            });
    }

    $(document).on('click', '.btn-review-cr', function () {
        openCRDrawer($(this).data('id'), this);
    });

    $(document).on('click', '#crDrawerRetry', function () {
        if (crDrawerId) openCRDrawer(crDrawerId);
    });

    /* Tombol salin pada Reference & Kode (modal Info). Menyalin teks elemen
       target ke clipboard lalu memberi umpan balik ikon centang sejenak. */
    $(document).on('click', '.cr-copy-btn', function () {
        var btn = this;
        var teks = ($('#' + $(btn).data('copy-target')).text() || '').trim();
        if (!teks) return;
        var tandai = function () {
            var $i = $(btn).find('i');
            var lama = $i.attr('class');
            $i.attr('class', 'fas fa-check');
            setTimeout(function () { $i.attr('class', lama); }, 1200);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(teks).then(tandai).catch(function () {});
        } else {
            var ta = document.createElement('textarea');
            ta.value = teks; document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); tandai(); } catch (e) {}
            document.body.removeChild(ta);
        }
    });

    // "Rincian lengkap" membuka modal lama beserta seluruh tabelnya.
    $(document).on('click', '#crDrawerFull', function () {
        if (!crDrawerId) return;
        TesysDrawer.close('crDrawer');
        openCRDetail(crDrawerId, '#crPaneDetails');
    });

    $('#mdlDetailCR').on('hidden.bs.modal', function () {
        $('#crDetailContent').hide(); $('#crDetailLoading').show();
    });

    // Auto-buka modal detail bila diakses dengan ?open=<id> (mis. dari To Do).
    // Parameternya langsung dibuang dari URL setelah dipakai: kalau dibiarkan,
    // setiap reload (atau redirect back() dari aksi lain) akan membuka modal
    // detail itu lagi seolah-olah bug.
    (function () {
        var params = new URLSearchParams(window.location.search);
        var openId = params.get('open');
        if (!openId) return;

        params.delete('open');
        var sisa = params.toString();
        window.history.replaceState({}, '',
            window.location.pathname + (sisa ? '?' + sisa : '') + window.location.hash);

        openCRDetail(openId);
    })();

});
</script>
@endpush
