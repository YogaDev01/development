@extends('layouts.app')

@section('title', 'Expense')

@section('content')

<div class="content">
    <div class="container-fluid">
      <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        {{-- Judul & breadcrumb topbar: shell memindahkan <h1> dan .breadcrumb pertama ke topbar
             (tesys-shell.js hoistPageTitle); disembunyikan secara visual. --}}
        <div class="tw:sr-only">
            <h1>Expense</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Expense</li>
            </ol>
        </div>

        @php
            $pengguna  = Auth::user();
            $bolehAjukan  = $pengguna->hasRole(\App\Models\CostRegister::SUBMIT_ROLES);
            $bolehSetuju  = $pengguna->hasRole(\App\Models\CostRegister::APPROVE_ROLES);
            $bolehSettle  = $pengguna->hasRole(\App\Models\CostRegister::SETTLE_ROLES);
        @endphp
        <x-tw.page-header title="Daftar Expense"
            description="Biaya di luar Cost Request (tanpa budgeting) ke PO, multi-PO, atau Cost Center — diajukan Finance Manager, disetujui CEO, dibayar lewat Treasury.">
            @if($bolehAjukan)
            <x-slot:actions>
                <x-tw.button href="{{ route('cost-register.create') }}" class="ts-add-btn tw:max-sm:min-h-12"><i class="fas fa-plus" aria-hidden="true"></i> Tambah Expense</x-tw.button>
            </x-slot:actions>
            @endif
        </x-tw.page-header>

        @if($bolehAjukan)
        {{-- Mobile: tombol di header disembunyikan dan diganti tombol melayang (tesys-ui-refine.css). --}}
        <a href="{{ route('cost-register.create') }}" class="ts-fab" aria-label="Tambah Expense" title="Tambah Expense">
            <i class="fas fa-plus" aria-hidden="true"></i>
        </a>
        @endif

        {{-- Rentang tanggal input (filter server-side) + Refresh. --}}
        <div class="tw:flex tw:flex-wrap tw:items-center tw:justify-end tw:gap-2">
            <div class="d-flex align-items-center reg-date-range" style="gap:6px;">
                <input type="text" id="regDateRange" class="form-control form-control-sm tdr-input" readonly aria-label="Rentang tanggal input">
                <input type="hidden" id="regDateFrom" value="{{ $dateFrom }}">
                <input type="hidden" id="regDateTo" value="{{ $dateTo }}">
            </div>
            <x-tw.button id="btnRefresh" variant="secondary" size="sm"><i class="fas fa-sync-alt" aria-hidden="true"></i> Refresh</x-tw.button>
        </div>

        {{-- Stepper per fase: klik tahap memfilter tabel di bawahnya. VERIFIED tampil "Submitted". --}}
        @php
            $regSteps = [
                ['Draft',     $summary['draft'],    'secondary', 'DRAFT'],
                ['Submitted', $summary['verified'], 'warning',   'VERIFIED'],
                ['Rejected',  $summary['rejected'], 'danger',    'REJECTED'],
                ['Approved',  $summary['approved'], 'primary',   'APPROVED'],
                ['Process',   $summary['queued'],   'info',      'QUEUED'],
                ['Paid',      $summary['paid'],     'info',      'PAID'],
                ['Settle Submitted', $summary['settle_submitted'], 'warning', 'SETTLE_SUBMITTED'],
                ['Settle Rejected',  $summary['settle_rejected'],  'danger',  'SETTLE_REJECTED'],
                ['Settle Approved',  $summary['settle_approved'],  'primary', 'SETTLE_APPROVED'],
                ['Closed',    $summary['closed'],   'success',   'CLOSED'],
            ];
        @endphp
        {{-- Paid / Settle hanya dilalui Expense Cash Advance. --}}
        <x-workflow-stepper :steps="$regSteps" :compact="true" table="tblCostRegister" field="status"
            :groups="[['Fase 1: Pengajuan', ['DRAFT','VERIFIED','REJECTED','APPROVED']], ['Fase 2: Pembayaran', ['QUEUED']], ['Fase 3: Settlement (CA)', ['PAID','SETTLE_SUBMITTED','SETTLE_REJECTED','SETTLE_APPROVED']], ['Selesai', ['CLOSED']]]" />

        {{-- Kartu tunggal: toolbar + tabel + paginasi (pola Cost Request). --}}
        <x-tw.card padding="sm">

            {{-- Daftar dibatasi baris terbaru (Concerns\CapsLargeLists); angka stepper tetap
                 dihitung dari SELURUH data pada rentang tanggal lewat query agregat. --}}
            @if(!empty($truncated))
            <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:12.5px;">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                Menampilkan <strong>@rupiah($maxRows)</strong> catatan terbaru dari total
                <strong>@rupiah($total)</strong>.
                Persempit rentang tanggal untuk melihat sisanya, atau <strong>Export Excel</strong> untuk data lengkap.
            </div>
            @endif

            <div class="custom-toolbar mb-3 d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
                <div class="custom-toolbar-left d-flex align-items-center flex-wrap" style="gap:8px;">
                    <div class="toolbar-row">
                        <span class="toolbar-label">Cari :</span>
                        <div class="toolbar-input" id="regSearchBox"></div>
                    </div>
                    <button aria-label="Clear Filter" type="button" id="btnClearFilterReg" class="btn btn-outline-secondary btn-sm" title="Clear Filter">
                        <i class="fas fa-eraser"></i>
                    </button>
                    <div id="regRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>
                    {{-- Custom kolom: pilihan disimpan per pengguna di browser (localStorage). --}}
                    <div class="cr-pop reg-col-dropdown">
                        <button type="button" class="btn btn-outline-secondary btn-sm cr-pop-toggle" aria-haspopup="true">
                            <i class="fas fa-columns mr-1" aria-hidden="true"></i> Kolom
                        </button>
                        <div class="cr-pop-menu p-2" style="min-width:200px;max-height:340px;overflow-y:auto;">
                            @foreach(['desc' => 'Deskripsi', 'ben' => 'Beneficiary', 'periode' => 'Periode', 'nilai' => 'Nilai', 'tanggal' => 'Tanggal',
                                      'tujuan' => 'Pembebanan', 'tipe' => 'Tipe', 'status' => 'Status', 'oleh' => 'Diinput Oleh'] as $key => $label)
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input reg-col-vis" id="regColVis_{{ $key }}" data-column="{{ $key }}">
                                <label class="custom-control-label" for="regColVis_{{ $key }}" style="font-size:12px;">{{ $label }}</label>
                            </div>
                            @endforeach
                            <button type="button" class="btn btn-link btn-sm p-0 mt-1" id="regColReset" style="font-size:12px;">Kembalikan bawaan</button>
                        </div>
                    </div>
                </div>
                <div class="custom-toolbar-right d-flex flex-wrap" style="gap:8px;">
                    <x-tw.button href="{{ route('cost-register.export') }}" id="btnExportReg" variant="secondary" size="sm">
                        <i class="fas fa-file-excel" aria-hidden="true"></i> Export Excel
                    </x-tw.button>
                </div>
            </div>

            <div class="table-responsive">
                <table id="tblCostRegister" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                    <thead class="bg-light">
                        <tr>
                            <th scope="col" class="text-nowrap" data-col="no">No. Expense</th>
                            <th scope="col" data-col="desc">Deskripsi</th>
                            <th scope="col" class="text-nowrap" data-col="ben">Beneficiary</th>
                            <th scope="col" class="text-nowrap" data-col="periode">Periode</th>
                            <th scope="col" class="text-nowrap text-right" data-col="nilai">Nilai</th>
                            <th scope="col" class="text-nowrap" data-col="tanggal">Tanggal</th>
                            <th scope="col" class="text-nowrap" data-col="tujuan">Pembebanan</th>
                            <th scope="col" class="text-nowrap" data-col="tipe">Tipe</th>
                            <th scope="col" class="text-nowrap" data-col="status">Status</th>
                            <th scope="col" class="text-nowrap" data-col="oleh">Diinput Oleh</th>
                            <th scope="col" class="text-center action-column" style="width:1%;"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($registers as $cr)
                        @php
                            $csrf = csrf_field();
                            // Aksi per baris (gerbang role sama dengan route + controller).
                            $bisaSubmit  = $cr->isDraft()    && $bolehAjukan;
                            $bisaHapus   = $cr->isEditable() && $bolehAjukan;
                            $bisaEdit    = $cr->isEditable() && ! $cr->isLegacy() && $bolehAjukan;
                            $bisaSetuju  = $cr->isVerified() && $bolehSetuju;
                            $bisaTolak   = $cr->isVerified() && $bolehSetuju;
                            $bisaRevisi  = $cr->isRejected() && $bolehAjukan;
                            $bisaBatal   = $cr->isCancellable() && $bolehAjukan;
                            // Expense Cash Advance (Fase 2).
                            $bisaSettle      = $cr->canInputSettlement() && $bolehSettle;
                            $bisaSetujuSettle = $cr->isSettleSubmitted() && $bolehSetuju;
                            $aksi = array_values(array_filter([
                                $bisaSubmit ? 'submit' : null,
                                $bisaEdit ? 'edit' : null,
                                $bisaRevisi ? 'reopen' : null,
                                $bisaSetuju ? 'approve' : null,
                                $bisaSettle ? 'settle' : null,
                                $bisaSetujuSettle ? 'settle-approve' : null,
                                $bisaTolak ? 'reject' : null,
                                $bisaSetujuSettle ? 'settle-reject' : null,
                                $bisaBatal  ? 'cancel' : null,
                                $bisaHapus  ? 'delete' : null,
                            ]));
                            $satuAksi = count($aksi) === 1;
                            $kls = $satuAksi ? 'ts-action-primary' : 'dropdown-item';
                            $lbl = $satuAksi ? 'ts-al' : '';
                            // HTML satu aksi (dipakai sebagai tombol tunggal atau butir menu).
                            $htmlAksi = function (string $k) use ($cr, $kls, $lbl, $csrf) {
                                return match ($k) {
                                    'submit' => '<form action="' . route('cost-register.submit', $cr->id) . '" method="POST" class="frmSubmitCr d-inline">' . $csrf
                                        . '<button type="submit" class="' . $kls . '" title="Ajukan"><i class="fas fa-paper-plane"></i><span class="' . $lbl . '">Ajukan</span></button></form>',
                                    'approve' => '<button type="button" class="' . $kls . ' btn-approve-cr" data-id="' . $cr->id . '" title="Approve"><i class="fas fa-check"></i><span class="' . $lbl . '">Approve</span></button>',
                                    'settle' => '<a href="' . route('cost-register.settle', $cr->id) . '" class="' . $kls . '" title="Input realisasi"><i class="fas fa-receipt"></i><span class="' . $lbl . '">Settle</span></a>',
                                    'settle-approve' => '<button type="button" class="' . $kls . ' btn-settle-approve-cr" data-id="' . $cr->id . '" data-paid="' . e($cr->amount_formatted) . '" data-settled="' . e(number_format((int) $cr->settled_amount, 0, ',', '.')) . '" title="Approve settlement"><i class="fas fa-check-double"></i><span class="' . $lbl . '">Approve Settle</span></button>',
                                    'settle-reject' => '<button type="button" class="' . $kls . ' is-danger btn-settle-reject-cr" data-id="' . $cr->id . '" title="Tolak settlement"><i class="fas fa-times"></i><span class="' . $lbl . '">Tolak Settle</span></button>',
                                    'edit' => '<a href="' . route('cost-register.edit', $cr->id) . '" class="' . $kls . '" title="Edit"><i class="fas fa-edit"></i><span class="' . $lbl . '">Edit</span></a>',
                                    'reopen' => '<form action="' . route('cost-register.reopen', $cr->id) . '" method="POST" class="frmReopenCr d-inline">' . $csrf
                                        . '<button type="submit" class="' . $kls . '" title="Revisi: kembali ke Draft untuk diedit"><i class="fas fa-undo"></i><span class="' . $lbl . '">Revisi</span></button></form>',
                                    'reject' => '<button type="button" class="' . $kls . ' is-danger btn-reject-cr" data-id="' . $cr->id . '" title="Tolak"><i class="fas fa-times"></i><span class="' . $lbl . '">Reject</span></button>',
                                    'cancel' => '<button type="button" class="' . $kls . ' is-danger btn-cancel-cr" data-id="' . $cr->id . '" title="Batalkan"><i class="fas fa-ban"></i><span class="' . $lbl . '">Batalkan</span></button>',
                                    'delete' => '<form action="' . route('cost-register.destroy', $cr->id) . '" method="POST" class="frmDeleteCr d-inline">' . $csrf
                                        . '<input type="hidden" name="_method" value="DELETE"><button type="submit" class="' . $kls . ' is-danger"><i class="fas fa-trash"></i><span class="' . $lbl . '">Hapus</span></button></form>',
                                    default => '',
                                };
                            };
                            $kodePo = $cr->display_code;
                            $tujuan = $cr->target_label;
                        @endphp
                        {{-- data-amount: total footer dihitung dari atribut baris, karena sel kolom
                             yang disembunyikan (custom kolom) dilepas DataTables dari DOM. --}}
                        <tr data-id="{{ $cr->id }}" data-status="{{ $cr->status }}" data-code="{{ $kodePo }}" data-amount="{{ (int) $cr->amount }}"
                            data-tipe="{{ $cr->type_label }}" data-ben="{{ $cr->beneficiary->nickname ?? '' }}">
                            <td class="text-nowrap">
                                {{-- Klik kode = salin; detail dibuka lewat klik baris. --}}
                                <button type="button" class="ts-copy-code" data-code="{{ $kodePo }}" title="Klik untuk menyalin kode">{{ $kodePo }}</button>
                            </td>
                            <td class="ts-col-desc" title="{{ $cr->notes }}">{{ Str::limit($cr->notes, 60) }}</td>
                            <td class="text-nowrap ts-col-desc">{{ $cr->beneficiary->nickname ?? '-' }}</td>
                            <td class="text-nowrap">{{ $cr->period_label }}</td>
                            <td class="text-right text-nowrap ts-col-money" data-order="{{ $cr->amount }}">{{ $cr->amount == 0 ? '-' : number_format($cr->amount, 0, ',', '.') }}</td>
                            <td class="text-nowrap" data-order="{{ $cr->created_at->format('Y-m-d H:i:s') }}">{{ $cr->created_at->format('d/m/Y') }}</td>
                            <td class="text-nowrap ts-col-desc" title="{{ $tujuan }}">{{ $tujuan }}</td>
                            <td class="text-nowrap">{{ $cr->type_label }}</td>
                            <td class="text-nowrap"><x-status :status="$cr->status" context="cost-register" /></td>
                            <td class="text-nowrap ts-col-desc">{{ $cr->inputBy->name ?? '-' }}</td>
                            <td class="text-center action-column">
                                {{-- 0 aksi = sel kosong (detail lewat klik baris); 1 aksi = tombolnya; >1 = satu tombol titik tiga. --}}
                                <div class="action-buttons ts-actions">
                                    @if($satuAksi)
                                    {!! $htmlAksi($aksi[0]) !!}
                                    @elseif(count($aksi) > 1)
                                    <button type="button" class="ts-action-more"
                                            aria-haspopup="true" aria-expanded="false"
                                            title="Aksi" aria-label="Aksi untuk Expense {{ $kodePo }}">
                                        <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                    </button>
                                    <div class="ts-action-menu dropdown-menu" hidden>
                                        @foreach($aksi as $i => $k)
                                            @if(in_array($k, ['reject', 'settle-reject', 'cancel', 'delete'], true) && $i > 0 && ! in_array($aksi[$i - 1], ['reject', 'settle-reject', 'cancel', 'delete'], true))
                                            <div class="dropdown-divider"></div>
                                            @endif
                                            {!! $htmlAksi($k) !!}
                                        @endforeach
                                    </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        {{-- Sengaja tidak ada baris di sini: tabel dikelola DataTables, keadaan
                             kosongnya diisi language.emptyTable. --}}
                        @endforelse
                    </tbody>
                    <tfoot>
                        {{-- Satu sel per kolom (tanpa colspan) supaya ikut tersembunyi bersama kolomnya. --}}
                        <tr style="background:var(--surface-2);">
                            <th class="text-nowrap" style="font-size:12px;">Total (data tampil)</th>
                            <th></th><th></th><th></th>
                            <th class="text-right text-nowrap" id="regFooterTotal" style="font-size:12px;"></th>
                            <th></th><th></th><th></th><th></th><th></th><th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-tw.card>
      </div>
    </div>
</div>

{{-- Drawer detail (klik baris): Info / Rincian / Log. Data dari cost-register.show-json. --}}
<x-drawer id="regDrawer" label="Detail Expense" width="max(50vw, 560px)">
    <div id="regDrawerContent" hidden>
        <ul class="nav nav-tabs mb-3" id="regDetailTabs" style="border-bottom:2px solid var(--border);">
            <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#regPaneInfo"><i class="fas fa-info-circle mr-1"></i> Info</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#regPaneDetails"><i class="fas fa-list-ul mr-1"></i> Rincian <span id="regDetailCount" class="badge badge-secondary ml-1"></span></a></li>
            <li class="nav-item" id="regSettleTabItem" hidden><a class="nav-link" data-toggle="tab" href="#regPaneSettle"><i class="fas fa-receipt mr-1"></i> Settlement</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#regPaneLog"><i class="fas fa-history mr-1"></i> Log</a></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="regPaneInfo"></div>
            <div class="tab-pane fade" id="regPaneSettle">
                {{-- Expense Cash Advance: realisasi, selisih, Second Payment (diisi JS). --}}
                <div id="regSettleSummary"></div>
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead style="background:var(--surface-2);">
                            <tr>
                                <th scope="col" style="width:1%;">No</th>
                                <th scope="col">Akun</th>
                                <th scope="col">Pembebanan</th>
                                <th scope="col">Deskripsi</th>
                                <th scope="col" class="text-right" style="width:1%;white-space:nowrap;">Harga</th>
                                <th scope="col" class="text-right" style="width:1%;white-space:nowrap;">Qty</th>
                                <th scope="col" class="text-right" style="width:1%;white-space:nowrap;">Total</th>
                            </tr>
                        </thead>
                        <tbody id="regSettleBody"></tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <h6 class="ts-dw-section-title"><i class="fas fa-paperclip mr-1"></i> Bukti Realisasi</h6>
                    <div id="regSettleAttBody" class="ts-dw-files"></div>
                </div>
            </div>
            <div class="tab-pane fade" id="regPaneDetails">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead style="background:var(--surface-2);">
                            <tr>
                                <th scope="col" style="width:1%;">No</th>
                                <th scope="col">Akun</th>
                                <th scope="col">Pembebanan</th>
                                <th scope="col">Deskripsi</th>
                                <th scope="col" class="text-right" style="width:1%;white-space:nowrap;">Harga</th>
                                <th scope="col" class="text-right" style="width:1%;white-space:nowrap;">Qty</th>
                                <th scope="col" class="text-right" style="width:1%;white-space:nowrap;">Total</th>
                            </tr>
                        </thead>
                        <tbody id="regDetailBody"></tbody>
                        <tfoot><tr style="background:var(--surface-2);"><th colspan="6" class="text-right">Total</th><th class="text-right" id="regDetailTotal"></th></tr></tfoot>
                    </table>
                </div>
                <div class="mt-3">
                    <h6 class="ts-dw-section-title"><i class="fas fa-paperclip mr-1"></i> Lampiran <span id="regAttCount" class="badge badge-secondary ml-1"></span></h6>
                    <div id="regAttBody" class="ts-dw-files"></div>
                </div>
            </div>
            <div class="tab-pane fade" id="regPaneLog">
                <div id="regLogBody" class="cr-chat"></div>
                {{-- Kotak catatan gaya chat (sama dengan tab Log Cost Request); disembunyikan JS saat Closed/Cancelled. --}}
                <form id="regLogForm" class="cr-log-form" hidden>
                    <textarea id="regLogInput" class="form-control" rows="2" maxlength="1000"
                              placeholder="Tulis catatan atau update…" aria-label="Catatan log"></textarea>
                    <button type="submit" class="btn btn-sm btn-primary" id="regLogSend"><i class="fas fa-paper-plane mr-1"></i> Kirim</button>
                </form>
                <div id="regLogClosed" class="cr-log-closed" hidden><i class="fas fa-lock mr-1"></i> Expense sudah selesai — catatan baru tidak dapat ditambahkan.</div>
            </div>
        </div>
    </div>

    <div id="regDrawerError" hidden></div>

    <div id="regDrawerLoading">
        <x-skeleton type="lines" :rows="2" />
        <div style="height:14px"></div>
        <x-skeleton type="card" />
        <div style="height:14px"></div>
        <x-skeleton type="lines" :rows="4" />
    </div>

    <x-slot:footer>
        <div id="regDrawerActions" class="ts-actions" style="flex:1;justify-content:flex-start;"></div>
    </x-slot:footer>
</x-drawer>

@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
<style>
#regSearchBox input  { width:220px; border-radius:var(--radius-sm); }
/* Popover tombol Kolom (salinan kecil blok .cr-pop Cash Receipt). */
.cr-pop { position: relative; }
.cr-pop-menu { display: none; position: absolute; top: calc(100% + 4px); left: 0; z-index: 1080;
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md); box-shadow: 0 8px 24px rgba(0,0,0,.14); }
.cr-pop.show .cr-pop-menu { display: block; }
.dataTables_wrapper .row:last-child { margin-top:18px; align-items:center; }
.dataTables_info { padding-top:0 !important; font-size:14px; color:var(--text-faint); }
.dataTables_length { display:flex; align-items:center; margin-left:18px; }
.dataTables_length label { display:flex; align-items:center; gap:10px; margin-bottom:0; font-size:14px; font-weight:600; color:var(--text-faint); }
.dataTables_length select { appearance:none !important; background-color:var(--surface) !important; color:var(--text-dim) !important; border:1px solid var(--border) !important; border-radius:var(--radius-sm) !important; padding:4px 30px 4px 10px !important; height:36px !important; min-width:72px !important; font-size:14px !important; font-weight:600 !important; cursor:pointer; box-shadow:none !important; }
.dataTables_paginate .paginate_button { border-radius:var(--radius-sm) !important; margin:0 2px; }
.dataTables_paginate .paginate_button.current { background:var(--accent) !important; background-image:none !important; border-color:var(--accent-dark) !important; color:#fff !important; font-weight:700 !important; }
/* Gulir mendatar hanya pada tabel, bukan footer (info + paginasi). */
.table-responsive:has(> .dataTables_wrapper) { overflow-x:visible; }
.ts-dt-scroll { overflow-x:auto; width:100%; }
/* Tab Log drawer gaya chat — disalin dari tab Log Cost Request (budget-request/index). */
.cr-chat { display:flex; flex-direction:column; gap:14px; padding:4px 2px; }
.cr-chat-row { display:flex; gap:10px; max-width:78%; }
.cr-chat-row.mine { align-self:flex-end; flex-direction:row-reverse; text-align:right; }
.cr-chat-av { width:30px; height:30px; border-radius:50%; background:var(--info-bg); color:var(--text-dim); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; flex-shrink:0; overflow:hidden; }
.cr-chat-row.mine .cr-chat-av { background:var(--charcoal); color:#fff; }
.cr-chat-av img { width:100%; height:100%; object-fit:cover; }
.cr-chat-bubble { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:8px 12px; font-size:12px; }
.cr-chat-meta { font-size:10px; color:var(--text-faint); margin-bottom:4px; }
.cr-chat-note { margin-top:6px; font-size:11.5px; line-height:1.45; white-space:pre-line; color:var(--text); }
#regPaneLog.active { display:flex; flex-direction:column; }
#regPaneLog #regLogBody { margin-top:auto; }
#regPaneLog .cr-log-form, #regPaneLog .cr-log-closed { position:sticky; bottom:-24px; margin-bottom:-24px; padding-bottom:24px; background:var(--surface); }
.cr-log-form { display:flex; gap:8px; align-items:flex-end; margin-top:16px; padding-top:12px; border-top:1px solid var(--border); }
.cr-log-form textarea { font-size:12px; resize:none; flex:1 1 auto; }
.cr-log-closed { margin-top:16px; padding-top:12px; border-top:1px solid var(--border); font-size:11.5px; color:var(--text-faint); }
</style>
@endpush

@push('scripts')
<script>
$(function () {

    function escReg(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
    function escAttrReg(s) { return escReg(s).replace(/"/g, '&quot;'); }
    function isiReg(v) { return (v === null || v === undefined || v === '') ? '-' : v; }
    function tokenWarna(nama, cadangan) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(nama).trim();
        return v || cadangan;
    }
    var WARNA_UTAMA  = tokenWarna('--accent', '#92232b');
    var WARNA_BAHAYA = tokenWarna('--danger', '#9a3030');
    var WARNA_BATAL  = tokenWarna('--text-muted', '#6a6e72');
    var regUserId = {{ (int) Auth::id() }};

    /* ── FILTER TANGGAL (server-side, komponen bersama TesysDateRange) ── */
    function reloadWithDateRangeReg(from, to, range) {
        var url = new URL(window.location.href);
        url.searchParams.set('date_from', from);
        url.searchParams.set('date_to', to);
        if (range) { url.searchParams.set('range', range); } else { url.searchParams.delete('range'); }
        window.location.href = url.toString();
    }
    var regPeriode = (typeof TesysDateRange === 'object')
        ? TesysDateRange.init({
            input: '#regDateRange', from: '#regDateFrom', to: '#regDateTo', quick: true,
            onApply: function (from, to, range) {
                if (from === '{{ $dateFrom }}' && to === '{{ $dateTo }}') return;   // panggilan awal hanya menulis nilai
                reloadWithDateRangeReg(from, to, range);
            }
        })
        : null;
    if (!regPeriode) {
        $('#regDateRange').remove();
        $('#regDateFrom, #regDateTo').attr('type', 'text').addClass('form-control form-control-sm')
            .attr('placeholder', 'YYYY-MM-DD')
            .on('change', function () { reloadWithDateRangeReg($('#regDateFrom').val(), $('#regDateTo').val()); });
    }

    /* ── DATATABLE ── */
    /* Custom kolom. Indeks kolom tabel; No. Expense (0) & Aksi (10) selalu tampil.
       Bawaan: No. Expense, Deskripsi, Beneficiary, Periode, Nilai, Tanggal. */
    var MAP_VIS = { desc: 1, ben: 2, periode: 3, nilai: 4, tanggal: 5, tujuan: 6, tipe: 7, status: 8, oleh: 9 };
    var VIS_BAWAAN = { desc: true, ben: true, periode: true, nilai: true, tanggal: true, tujuan: false, tipe: false, status: false, oleh: false };
    function visKey(k) { return 'expenseColVis_' + k; }
    function getVis(k) {
        try { var v = localStorage.getItem(visKey(k)); return v === null ? VIS_BAWAAN[k] : v === '1'; }
        catch (e) { return VIS_BAWAAN[k]; }
    }
    function setVis(k, tampil) { try { localStorage.setItem(visKey(k), tampil ? '1' : '0'); } catch (e) {} }
    var colDefs = [{ targets: [-1], orderable: false, searchable: false }];
    Object.keys(MAP_VIS).forEach(function (k) { colDefs.push({ targets: [MAP_VIS[k]], visible: getVis(k) }); });

    var table = $('#tblCostRegister').DataTable({
        autoWidth: false, pageLength: 10, lengthChange: true, searching: true,
        order: [], // urutan asli dari server (terbaru di atas)
        columnDefs: colDefs,
        dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex justify-content-end"lp>>',
        language: {
            lengthMenu: 'Page Size _MENU_',
            emptyTable: '<div class="ts-empty ts-empty-compact">'
                + '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-folder-open"></i></span>'
                + '<p class="ts-empty-title">Belum ada Expense</p>'
                + '<p class="ts-empty-msg">Belum ada Expense pada rentang tanggal yang dipilih.</p></div>',
            zeroRecords: '<div class="ts-empty ts-empty-compact">'
                + '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-search"></i></span>'
                + '<p class="ts-empty-title">Tidak ada yang cocok</p>'
                + '<p class="ts-empty-msg">Tidak ada Expense yang cocok dengan pencarian atau filter yang sedang aktif.</p></div>',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data', paginate: { previous: 'Previous', next: 'Next' }
        }
    });

    // Scrollbar mendatar hanya untuk tabel (footer info + paginasi tetap diam).
    $('#tblCostRegister').wrap('<div class="ts-dt-scroll"></div>');

    $('#tblCostRegister_filter').hide();
    $('#regSearchBox').html('<input type="search" id="regSearchInput" class="form-control form-control-sm" placeholder="Cari data...">');
    $('#regSearchInput').on('keyup', function () { table.search(this.value).draw(); });

    /* ── TOMBOL KOLOM ── */
    function syncColChecks() {
        $('.reg-col-vis').each(function () { $(this).prop('checked', table.column(MAP_VIS[$(this).data('column')]).visible()); });
    }
    syncColChecks();
    $(document).on('change', '.reg-col-vis', function () {
        var k = $(this).data('column'), tampil = $(this).is(':checked');
        if (MAP_VIS[k] == null) return;
        table.column(MAP_VIS[k]).visible(tampil);
        setVis(k, tampil);
        try { table.columns.adjust(); } catch (e) {}
    });
    $('#regColReset').on('click', function () {
        Object.keys(MAP_VIS).forEach(function (k) {
            try { localStorage.removeItem(visKey(k)); } catch (e) {}
            table.column(MAP_VIS[k]).visible(VIS_BAWAAN[k]);
        });
        syncColChecks();
        try { table.columns.adjust(); } catch (e) {}
    });
    $(document).on('click', '.reg-col-dropdown .cr-pop-toggle', function (e) {
        e.stopPropagation();
        $(this).closest('.cr-pop').toggleClass('show');
    });
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.reg-col-dropdown').length) $('.reg-col-dropdown').removeClass('show');
    });

    TesysColumnFilter.initRowFilters('#tblCostRegister', '#regRowFilters', [
        { key: 'tipe', label: 'Tipe' },
        { key: 'ben',  label: 'Beneficiary' },
    ]);
    $('#btnClearFilterReg').on('click', function () {
        TesysColumnFilter.clearAll('#tblCostRegister');
        TesysWorkflowStepper.clear('tblCostRegister');
    });
    $('#btnRefresh').on('click', function () { location.reload(); });

    /* Footer: total nominal dari SEMUA baris yang lolos filter/search saat ini. */
    function recalcFooterReg() {
        var total = 0;
        table.rows({ search: 'applied' }).every(function () {
            total += parseInt($(this.node()).attr('data-amount'), 10) || 0;
        });
        $('#regFooterTotal').text(total === 0 ? '-' : Math.round(total).toLocaleString('id-ID'));
    }
    table.on('draw', recalcFooterReg);
    recalcFooterReg();

    /* Export Excel mengikuti filter yang SEDANG aktif di tabel; tanpa filter = rentang tanggal. */
    (function () {
        var url = '{{ route('cost-register.export') }}?date_from=' + encodeURIComponent('{{ $dateFrom }}') + '&date_to=' + encodeURIComponent('{{ $dateTo }}');
        $('#btnExportReg').attr('href', url);
        if (window.TesysExportFiltered) {
            TesysExportFiltered.wire('#btnExportReg', table, '{{ route('cost-register.export') }}');
        }
    })();

    /* ── AKSI (konfirmasi Swal lalu kirim form POST) ── */
    function kirimForm(url, data) {
        var $f = $('<form method="POST"></form>').attr('action', url);
        $f.append($('<input type="hidden" name="_token">').val($('meta[name="csrf-token"]').attr('content')));
        $.each(data || {}, function (k, v) { $f.append($('<input type="hidden">').attr('name', k).val(v)); });
        $('body').append($f);
        $f[0].submit();
    }
    $(document).on('submit', '.frmSubmitCr', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title: 'Ajukan Expense?', text: 'Expense berstatus Submitted dan menunggu approval CEO.', icon: 'question',
            showCancelButton: true, confirmButtonColor: WARNA_UTAMA, cancelButtonColor: WARNA_BATAL, confirmButtonText: 'Ya, Ajukan', cancelButtonText: 'Batal' })
        .then(function (r) { if (r.isConfirmed) form.submit(); });
    });
    $(document).on('submit', '.frmDeleteCr', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title: 'Hapus Expense?', text: 'Data tidak dapat dikembalikan.', icon: 'warning', showCancelButton: true,
            confirmButtonColor: WARNA_BAHAYA, cancelButtonColor: WARNA_BATAL, confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal' })
        .then(function (r) { if (r.isConfirmed) form.submit(); });
    });
    $(document).on('click', '.btn-approve-cr', function () {
        var id = $(this).data('id');
        Swal.fire({ title: 'Approve Expense?', text: 'Setelah disetujui, Treasury dapat memasukkannya ke batch pembayaran.', icon: 'question',
            showCancelButton: true, confirmButtonColor: WARNA_UTAMA, cancelButtonColor: WARNA_BATAL, confirmButtonText: 'Ya, Approve', cancelButtonText: 'Batal' })
        .then(function (r) { if (r.isConfirmed) kirimForm('{{ url('/expense') }}/' + id + '/approve'); });
    });
    $(document).on('click', '.btn-reject-cr', function () {
        var id = $(this).data('id');
        Swal.fire({ title: 'Tolak Expense?', input: 'textarea', inputAttributes: { maxlength: 500, 'aria-label': 'Alasan penolakan' },
            inputLabel: 'Alasan wajib diisi — Finance Manager bisa merevisi ke Draft atau membatalkan',
            inputValidator: function (v) { if (!v || !v.trim()) return 'Alasan wajib diisi.'; },
            icon: 'warning', showCancelButton: true, confirmButtonColor: WARNA_BAHAYA, cancelButtonColor: WARNA_BATAL,
            confirmButtonText: 'Ya, Tolak', cancelButtonText: 'Batal' })
        .then(function (r) { if (r.isConfirmed) kirimForm('{{ url('/expense') }}/' + id + '/reject', { notes: r.value }); });
    });
    $(document).on('submit', '.frmReopenCr', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title: 'Revisi Expense?', text: 'Expense kembali ke Draft lalu dibuka di halaman edit untuk diperbaiki dan diajukan ulang.', icon: 'question',
            showCancelButton: true, confirmButtonColor: WARNA_UTAMA, cancelButtonColor: WARNA_BATAL, confirmButtonText: 'Ya, Revisi', cancelButtonText: 'Batal' })
        .then(function (r) { if (r.isConfirmed) form.submit(); });
    });
    $(document).on('click', '.btn-settle-approve-cr', function () {
        var id = $(this).data('id');
        Swal.fire({ title: 'Approve settlement?', html: 'Dibayar <strong>' + escReg($(this).data('paid')) + '</strong>, realisasi <strong>'
                + escReg($(this).data('settled')) + '</strong>.<br>Kekurangan bayar akan masuk antrean Treasury; kelebihan dicatat sebagai refund.',
            input: 'textarea', inputLabel: 'Catatan (opsional)', inputAttributes: { maxlength: 500, 'aria-label': 'Catatan' },
            icon: 'question', showCancelButton: true, confirmButtonColor: WARNA_UTAMA, cancelButtonColor: WARNA_BATAL,
            confirmButtonText: 'Ya, Approve', cancelButtonText: 'Batal' })
        .then(function (r) { if (r.isConfirmed) kirimForm('{{ url('/expense') }}/' + id + '/settle-approve', { notes: r.value || '' }); });
    });
    $(document).on('click', '.btn-settle-reject-cr', function () {
        var id = $(this).data('id');
        Swal.fire({ title: 'Tolak settlement?', input: 'textarea', inputAttributes: { maxlength: 500, 'aria-label': 'Alasan penolakan' },
            inputLabel: 'Alasan wajib diisi — Expense kembali ke Paid untuk diperbaiki Finance Manager',
            inputValidator: function (v) { if (!v || !v.trim()) return 'Alasan wajib diisi.'; },
            icon: 'warning', showCancelButton: true, confirmButtonColor: WARNA_BAHAYA, cancelButtonColor: WARNA_BATAL,
            confirmButtonText: 'Ya, Tolak', cancelButtonText: 'Batal' })
        .then(function (r) { if (r.isConfirmed) kirimForm('{{ url('/expense') }}/' + id + '/settle-reject', { notes: r.value }); });
    });
    $(document).on('click', '.btn-cancel-cr', function () {
        var id = $(this).data('id');
        Swal.fire({ title: 'Batalkan Expense?', input: 'textarea', inputAttributes: { maxlength: 500, 'aria-label': 'Alasan pembatalan' },
            inputLabel: 'Alasan pembatalan (opsional)', icon: 'warning', showCancelButton: true,
            confirmButtonColor: WARNA_BAHAYA, cancelButtonColor: WARNA_BATAL, confirmButtonText: 'Ya, Batalkan', cancelButtonText: 'Kembali' })
        .then(function (r) { if (r.isConfirmed) kirimForm('{{ url('/expense') }}/' + id + '/cancel', { reason: r.value || '' }); });
    });

    /* ── DRAWER DETAIL (klik baris) ── */
    function renderLogRowReg(log) {
        var mine = log.user_id && log.user_id === regUserId;
        var isNote = log.kind === 'note';
        var actor = log.actor || 'System';
        var initials = actor.trim().split(/\s+/).map(function (w) { return w[0]; }).join('').slice(0, 2).toUpperCase() || '?';
        var isi = isNote
            ? '<div class="cr-chat-note" style="margin-top:0;">' + escReg(log.notes) + '</div>'
            : '<span class="badge badge-' + escAttrReg(log.action_color || 'secondary') + ' px-2 py-1" style="font-size:9px;">' + escReg(log.action_label) + '</span>'
              + (log.status_from && log.status_to ? ' <span style="font-size:10px;opacity:.75;">' + escReg(log.status_from) + ' → ' + escReg(log.status_to) + '</span>' : '')
              + (log.notes ? '<div class="cr-chat-note">' + escReg(log.notes) + '</div>' : '');
        return '<div class="cr-chat-row ' + (mine ? 'mine' : '') + '">'
            + '<div class="cr-chat-av">' + (log.avatar ? '<img src="' + escAttrReg(log.avatar) + '" alt="">' : escReg(initials)) + '</div>'
            + '<div><div class="cr-chat-meta"><strong>' + escReg(actor) + '</strong> · ' + escReg(log.date) + '</div>'
            + '<div class="cr-chat-bubble">' + isi + '</div></div></div>';
    }

    function aturTinggiLogReg(gulirKeBawah) {
        var body = document.getElementById('regDrawer-body');
        var $tabs = $('#regDetailTabs');
        if (!body || !$tabs.length) return;
        var cs = getComputedStyle(body);
        var tinggi = body.clientHeight - parseFloat(cs.paddingTop) - parseFloat(cs.paddingBottom)
                   - $tabs.outerHeight() - parseFloat($tabs.css('margin-bottom')) - parseFloat($tabs.css('margin-top'));
        $('#regPaneLog').css('min-height', Math.max(tinggi, 0));
        if (gulirKeBawah) body.scrollTop = body.scrollHeight;
    }
    $(document).on('shown.bs.tab', '#regDetailTabs a[data-toggle="tab"]', function () {
        if ($(this).attr('href') === '#regPaneLog') aturTinggiLogReg(true);
    });

    /* Carousel lampiran di kiri drawer (layar >= 1200px) — komponen bersama tesys-evidence-stage.js. */
    var stageEvidence = TesysEvidenceStage.attach('regDrawer');

    function bukaDrawerReg(id, pemicu) {
        var $baris = pemicu ? $(pemicu).closest('tr') : $('#tblCostRegister tbody tr[data-id="' + id + '"]').first();

        $('#regDrawerContent').attr('hidden', true);
        $('#regDrawerError').attr('hidden', true).empty();
        $('#regDrawerLoading').show();
        $('#regDrawer-eyebrow').text('');
        $('#regDrawer-title').text('Memuat…');
        $('#regDrawerActions').empty();
        $('#regDetailTabs a[href="#regPaneInfo"]').tab('show');
        TesysDrawer.open('regDrawer', pemicu);
        stageEvidence.clear();

        // Aksi baris (Ajukan/Approve/Revisi/Batalkan/Hapus) disalin sebagai tombol drawer.
        var $aksi = $baris.find('.ts-actions').children(':not(.ts-action-more):not(.ts-action-menu)')
            .add($baris.find('.ts-action-menu').children(':not(.dropdown-divider)')).clone(true, true);
        $aksi.each(function () {
            var $b = $(this);
            // Form (Ajukan/Hapus) dibungkus tombol di dalamnya; butir lain adalah tombol itu sendiri.
            $b.find('button, a').addBack('button, a').removeClass('dropdown-item').addClass('ts-action-primary');
        });
        $('#regDrawerActions').append($aksi);

        function gagal(pesan) {
            $('#regDrawerLoading').hide();
            $('#regDrawerError').html('<div class="ts-empty"><span class="ts-empty-ic"><i class="fas fa-exclamation-triangle"></i></span>'
                + '<p class="ts-empty-title">Detail belum dapat dimuat</p><p class="ts-empty-msg">' + escReg(pesan) + '</p></div>').removeAttr('hidden');
            $('#regDrawer-title').text($baris.data('code') || 'Detail Expense');
        }

        $.get('{{ url('/expense') }}/' + encodeURIComponent(id) + '/detail-json')
            .done(function (d) {
                if (!d || typeof d !== 'object' || !d.id) { gagal('Sesi Anda mungkin sudah berakhir. Muat ulang halaman, lalu masuk kembali.'); return; }

                $('#regDrawer-eyebrow').html('<button type="button" class="ts-copy-code" data-code="' + escAttrReg(d.code) + '" title="Klik untuk menyalin kode">'
                    + escReg(d.code) + '</button> · ' + escReg(d.type_label));
                // Cost Register lama: nama PO; Expense: ringkasan tujuan (PO / multi-PO / Cost Center).
                $('#regDrawer-title').text(d.is_legacy ? (d.project_name || d.project_code) : d.target_label);

                var siapa = function (nama, waktu) { return (nama && nama !== '-') ? nama + (waktu ? ' · ' + waktu : '') : '-'; };
                var info = '<div class="mb-3" style="border:1px solid var(--border);border-radius:var(--radius-lg);padding:16px 18px;">'
                    + '<div style="font-size:12px;color:var(--text-dim);">Nominal</div>'
                    + '<div style="font-size:28px;line-height:1.15;font-weight:700;font-variant-numeric:tabular-nums;margin:2px 0 8px;">' + escReg(d.amount ? d.amount_formatted : '-') + '</div>'
                    + '<span class="ts-status ts-status-' + escAttrReg(d.status_tone || 'neutral') + ' ts-status-sm">' + escReg(d.status_label) + '</span></div>'
                    + '<dl class="ts-dw-kv">'
                    + '<dt>Pembebanan</dt><dd>' + escReg(isiReg(d.target_label)) + '</dd>'
                    + (d.is_legacy ? '<dt>Customer</dt><dd>' + escReg(isiReg(d.customer)) + '</dd>' : '')
                    + '<dt>Tipe</dt><dd>' + escReg(isiReg(d.type_label)) + '</dd>'
                    + '<dt>Periode</dt><dd>' + escReg(isiReg(d.period))
                    + ((d.periods || []).length > 1 ? '<div class="text-muted" style="font-size:11.5px;">' + d.periods.map(function (p) {
                        return escReg(p.period) + ': ' + escReg(p.amount); }).join('<br>') + '</div>' : '') + '</dd>'
                    + '<dt>Beneficiary</dt><dd>' + escReg(isiReg(d.beneficiary)) + '</dd>'
                    + '<dt>Rekening Beneficiary</dt><dd>' + escReg(isiReg(d.beneficiary_bank)) + '</dd>'
                    + (d.source_fund && d.source_fund !== '-' ? '<dt>Rekening Sumber</dt><dd>' + escReg(d.source_fund) + '</dd>' : '')
                    + (d.batch_code ? '<dt>Batch Alokasi</dt><dd>' + escReg(d.batch_code) + '</dd>' : '')
                    + '<dt>Catatan</dt><dd style="white-space:pre-line;">' + escReg(isiReg(d.notes)) + '</dd>'
                    + '<dt>Diinput</dt><dd>' + escReg(siapa(d.input_by, d.created_at)) + '</dd>'
                    + '<dt>Submitted</dt><dd>' + escReg(siapa(d.verified_by, d.verified_at)) + '</dd>'
                    + '<dt>Approved</dt><dd>' + escReg(siapa(d.approved_by, d.approved_at)) + '</dd>'
                    + '<dt>Dibayar</dt><dd>' + escReg(isiReg(d.paid_at)) + '</dd>'
                    + '</dl>';
                $('#regPaneInfo').html(info);

                var det = d.details || [];
                $('#regDetailCount').text(det.length);
                $('#regDetailBody').html(det.length ? det.map(function (x, i) {
                    return '<tr><td>' + (i + 1) + '</td><td>' + escReg(isiReg(x.account))
                        + (x.category && x.category !== '-' ? '<div class="text-muted" style="font-size:11px;">' + escReg(x.category) + '</div>' : '') + '</td>'
                        + '<td>' + escReg(isiReg(x.target)) + '</td><td>' + escReg(x.description) + '</td>'
                        + '<td class="text-right" style="font-variant-numeric:tabular-nums;">' + escReg(x.unit_price) + '</td><td class="text-right">' + escReg(x.qty) + '</td>'
                        + '<td class="text-right" style="font-variant-numeric:tabular-nums;">' + escReg(x.total) + '</td></tr>';
                }).join('') : '<tr><td colspan="7" class="text-center text-muted py-3">Tanpa rincian (data lama).</td></tr>');
                $('#regDetailTotal').text(d.amount ? d.amount_formatted : '-');

                // Expense Cash Advance: tab Settlement.
                $('#regSettleTabItem').prop('hidden', !d.is_cash_advance);
                if (d.is_cash_advance) {
                    var sp = (d.second_payments || []).map(function (x) {
                        return escReg(x.amount) + ' (' + escReg(x.status === 'PAID' ? 'dibayar ' + (x.paid_at || '') : 'menunggu Treasury') + ')';
                    }).join(', ');
                    $('#regSettleSummary').html('<dl class="ts-dw-kv mb-3">'
                        + '<dt>Dibayar</dt><dd>' + escReg(d.amount_formatted) + '</dd>'
                        + '<dt>Realisasi</dt><dd>' + escReg(isiReg(d.settled_amount)) + '</dd>'
                        + '<dt>Kelebihan (refund)</dt><dd>' + escReg(isiReg(d.refund_amount)) + '</dd>'
                        + '<dt>Kekurangan bayar</dt><dd>' + escReg(isiReg(d.shortfall_amount)) + '</dd>'
                        + '<dt>Second Payment</dt><dd>' + (sp || '-') + '</dd>'
                        + '<dt>Settle diajukan</dt><dd>' + escReg(siapa(d.settle_submitted_by, d.settle_submitted_at)) + '</dd>'
                        + '<dt>Settle disetujui</dt><dd>' + escReg(siapa(d.settle_approved_by, d.settle_approved_at)) + '</dd>'
                        + '</dl>');
                    var sd = d.settlement_details || [];
                    $('#regSettleBody').html(sd.length ? sd.map(function (x, i) {
                        return '<tr><td>' + (i + 1) + '</td><td>' + escReg(isiReg(x.account))
                            + (x.category && x.category !== '-' ? '<div class="text-muted" style="font-size:11px;">' + escReg(x.category) + '</div>' : '') + '</td>'
                            + '<td>' + escReg(isiReg(x.target)) + '</td><td>' + escReg(x.description) + '</td>'
                            + '<td class="text-right" style="font-variant-numeric:tabular-nums;">' + escReg(x.unit_price) + '</td><td class="text-right">' + escReg(x.qty) + '</td>'
                            + '<td class="text-right" style="font-variant-numeric:tabular-nums;">' + escReg(x.total) + '</td></tr>';
                    }).join('') : '<tr><td colspan="7" class="text-center text-muted py-3">Realisasi belum diisi.</td></tr>');
                    var satts = d.settlement_attachments || [];
                    $('#regSettleAttBody').html(satts.length ? satts.map(function (a) {
                        return '<a class="ts-dw-file" href="/storage/' + escAttrReg(a.path) + '" target="_blank" rel="noopener"><i class="fas fa-file"></i><span>' + escReg(a.title) + '</span></a>';
                    }).join('') : '<p class="text-muted mb-0" style="font-size:12px;">Belum ada bukti realisasi.</p>');
                }

                var atts = d.attachments || [];
                $('#regAttCount').text(atts.length);
                $('#regAttBody').html(atts.length ? atts.map(function (a) {
                    return '<a class="ts-dw-file" href="/storage/' + escAttrReg(a.path) + '" data-evidence="reg-att" data-evidence-name="' + escAttrReg(a.title) + '" target="_blank" rel="noopener"><i class="fas fa-file"></i><span>' + escReg(a.title) + '</span></a>';
                }).join('') : '<p class="text-muted mb-0" style="font-size:12px;">Belum ada lampiran.</p>');

                stageEvidence.render(TesysEvidenceStage.fromAttachments(d.attachments, 'Lampiran'));

                var logs = (d.logs || []).slice().reverse();
                $('#regLogBody').html(logs.length ? logs.map(renderLogRowReg).join('')
                    : '<p class="text-muted mb-0 reg-log-empty" style="font-size:12px;">Belum ada riwayat.</p>');
                var bisaCatat = d.can_post_log_note !== false;
                $('#regLogForm').prop('hidden', !bisaCatat).data('id', d.id);
                $('#regLogClosed').prop('hidden', bisaCatat);
                $('#regLogInput').val('');

                $('#regDrawerLoading').hide();
                $('#regDrawerContent').removeAttr('hidden');
            })
            .fail(function (xhr) {
                gagal(xhr && xhr.status === 404 ? 'Expense tidak ditemukan.' : 'Terjadi kesalahan saat memuat data. Coba lagi.');
            });
    }

    $(document).on('click', '#tblCostRegister tbody tr[data-id]', function (e) {
        if ($(e.target).closest('a, button, input, select, textarea, label, form, .ts-actions, .ts-action-menu, td.dtr-control').length) return;
        if (window.getSelection && String(window.getSelection()).length) return;
        bukaDrawerReg($(this).data('id'), this);
    });

    // Aksi dari kaki drawer: drawer ditutup dulu supaya konfirmasi tidak tertutup panel.
    $(document).on('click', '#regDrawerActions .btn-approve-cr, #regDrawerActions .btn-reject-cr, #regDrawerActions .btn-cancel-cr, #regDrawerActions .btn-settle-approve-cr, #regDrawerActions .btn-settle-reject-cr, #regDrawerActions form button', function () {
        TesysDrawer.close('regDrawer');
    });

    $(document).on('submit', '#regLogForm', function (e) {
        e.preventDefault();
        var $f = $(this), $in = $('#regLogInput'), $btn = $('#regLogSend');
        var teks = $.trim($in.val());
        if (!teks) { $in.focus(); return; }
        $btn.prop('disabled', true);
        $.post('{{ url('/expense') }}/' + $f.data('id') + '/log-note', { _token: $('meta[name="csrf-token"]').attr('content'), notes: teks })
            .done(function (res) {
                if (!res || !res.log) return;
                $('#regLogBody .reg-log-empty').remove();
                $('#regLogBody').append(renderLogRowReg(res.log));
                $in.val('');
                aturTinggiLogReg(true);
            })
            .fail(function (xhr) {
                var pesan = (xhr.responseJSON && (xhr.responseJSON.message || (xhr.responseJSON.errors && xhr.responseJSON.errors.notes && xhr.responseJSON.errors.notes[0])))
                    || 'Catatan belum dapat dikirim. Coba lagi.';
                Swal.fire({ icon: 'error', title: 'Gagal mengirim', text: pesan });
            })
            .always(function () { $btn.prop('disabled', false); });
    });
});
</script>
@endpush
