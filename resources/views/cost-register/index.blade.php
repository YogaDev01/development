@extends('layouts.app')

@section('title', 'Cost Register')

@section('content')

<div class="content">
    <div class="container-fluid">
      <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        {{-- Judul & breadcrumb topbar: shell memindahkan <h1> dan .breadcrumb pertama ke topbar
             (tesys-shell.js hoistPageTitle); disembunyikan secara visual. --}}
        <div class="tw:sr-only">
            <h1>Cost Register</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Cost Register</li>
            </ol>
        </div>

        @php
            $pengguna  = Auth::user();
            $bolehAjukan  = $pengguna->hasRole(\App\Models\CostRegister::SUBMIT_ROLES);
            $bolehSetuju  = $pengguna->hasRole(\App\Models\CostRegister::APPROVE_ROLES);
        @endphp
        <x-tw.page-header title="Daftar Cost Register"
            description="Fixed Cost dan Overhead per No. PO beserta persetujuan dan pembayarannya lewat Treasury.">
            @if($bolehAjukan)
            <x-slot:actions>
                <x-tw.button href="{{ route('cost-register.create') }}" class="ts-add-btn tw:max-sm:min-h-12"><i class="fas fa-plus" aria-hidden="true"></i> Tambah Cost Register</x-tw.button>
            </x-slot:actions>
            @endif
        </x-tw.page-header>

        @if($bolehAjukan)
        {{-- Mobile: tombol di header disembunyikan dan diganti tombol melayang (tesys-ui-refine.css). --}}
        <a href="{{ route('cost-register.create') }}" class="ts-fab" aria-label="Tambah Cost Register" title="Tambah Cost Register">
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
                ['Approved',  $summary['approved'], 'primary',   'APPROVED'],
                ['Process',   $summary['queued'],   'info',      'QUEUED'],
                ['Closed',    $summary['closed'],   'success',   'CLOSED'],
            ];
        @endphp
        <x-workflow-stepper :steps="$regSteps" :compact="true" table="tblCostRegister" field="status"
            :groups="[['Fase 1: Pengajuan', ['DRAFT','VERIFIED','APPROVED']], ['Fase 2: Pembayaran', ['QUEUED','CLOSED']]]" />

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
                            <th scope="col" class="text-nowrap" data-col="tanggal">Tanggal</th>
                            <th scope="col" class="text-nowrap" data-col="po">No. PO</th>
                            <th scope="col" data-col="desc">Deskripsi</th>
                            <th scope="col" class="text-nowrap" data-col="tipe">Tipe</th>
                            <th scope="col" class="text-nowrap" data-col="status">Status</th>
                            <th scope="col" class="text-nowrap" data-col="ben">Beneficiary</th>
                            <th scope="col" class="text-nowrap" data-col="periode">Periode</th>
                            <th scope="col" class="text-nowrap text-right" data-col="nominal">Nominal</th>
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
                            $bisaSetuju  = $cr->isVerified() && $bolehSetuju;
                            $bisaRevisi  = $cr->isVerified() && $bolehSetuju;
                            $bisaBatal   = $cr->isCancellable() && $bolehAjukan;
                            $aksi = array_values(array_filter([
                                $bisaSubmit ? 'submit' : null,
                                $bisaSetuju ? 'approve' : null,
                                $bisaRevisi ? 'revise' : null,
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
                                    'revise' => '<button type="button" class="' . $kls . ' is-danger btn-revise-cr" data-id="' . $cr->id . '" title="Kembalikan untuk revisi"><i class="fas fa-undo"></i><span class="' . $lbl . '">Minta Revisi</span></button>',
                                    'cancel' => '<button type="button" class="' . $kls . ' is-danger btn-cancel-cr" data-id="' . $cr->id . '" title="Batalkan"><i class="fas fa-ban"></i><span class="' . $lbl . '">Batalkan</span></button>',
                                    'delete' => '<form action="' . route('cost-register.destroy', $cr->id) . '" method="POST" class="frmDeleteCr d-inline">' . $csrf
                                        . '<input type="hidden" name="_method" value="DELETE"><button type="submit" class="' . $kls . ' is-danger"><i class="fas fa-trash"></i><span class="' . $lbl . '">Hapus</span></button></form>',
                                    default => '',
                                };
                            };
                            $kodePo = $cr->project->code ?? '-';
                        @endphp
                        <tr data-id="{{ $cr->id }}" data-status="{{ $cr->status }}" data-code="{{ $kodePo }}"
                            data-tipe="{{ $cr->type_label }}" data-ben="{{ $cr->beneficiary->nickname ?? '' }}">
                            <td class="text-nowrap" data-order="{{ $cr->created_at->format('Y-m-d H:i:s') }}">{{ $cr->created_at->format('d/m/Y') }}</td>
                            <td class="text-nowrap">
                                {{-- Klik kode = salin; detail dibuka lewat klik baris. --}}
                                <button type="button" class="ts-copy-code" data-code="{{ $kodePo }}" title="Klik untuk menyalin kode">{{ $kodePo }}</button>
                            </td>
                            <td class="ts-col-desc" title="{{ $cr->notes }}">{{ Str::limit($cr->notes, 60) }}</td>
                            <td class="text-nowrap">{{ $cr->type_label }}</td>
                            <td class="text-nowrap"><x-status :status="$cr->status" context="cost-register" /></td>
                            <td class="text-nowrap ts-col-desc">{{ $cr->beneficiary->nickname ?? '-' }}</td>
                            <td class="text-nowrap">{{ $cr->period ? $cr->period->format('M Y') : '-' }}</td>
                            <td class="text-right text-nowrap ts-col-money" data-order="{{ $cr->amount }}">{{ $cr->amount == 0 ? '-' : number_format($cr->amount, 0, ',', '.') }}</td>
                            <td class="text-nowrap ts-col-desc">{{ $cr->inputBy->name ?? '-' }}</td>
                            <td class="text-center action-column">
                                {{-- 0 aksi = sel kosong (detail lewat klik baris); 1 aksi = tombolnya; >1 = satu tombol titik tiga. --}}
                                <div class="action-buttons ts-actions">
                                    @if($satuAksi)
                                    {!! $htmlAksi($aksi[0]) !!}
                                    @elseif(count($aksi) > 1)
                                    <button type="button" class="ts-action-more"
                                            aria-haspopup="true" aria-expanded="false"
                                            title="Aksi" aria-label="Aksi untuk Cost Register {{ $kodePo }}">
                                        <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                    </button>
                                    <div class="ts-action-menu dropdown-menu" hidden>
                                        @foreach($aksi as $i => $k)
                                            @if(in_array($k, ['revise', 'cancel', 'delete'], true) && $i > 0 && ! in_array($aksi[$i - 1], ['revise', 'cancel', 'delete'], true))
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
                        <tr style="background:var(--surface-2);">
                            <th colspan="7" class="text-right" style="font-size:12px;">Total (data tampil)</th>
                            <th class="text-right text-nowrap" id="regFooterTotal" style="font-size:12px;"></th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-tw.card>
      </div>
    </div>
</div>

{{-- Drawer detail (klik baris): Info / Rincian / Log. Data dari cost-register.show-json. --}}
<x-drawer id="regDrawer" label="Detail Cost Register" width="max(50vw, 560px)">
    <div id="regDrawerContent" hidden>
        <ul class="nav nav-tabs mb-3" id="regDetailTabs" style="border-bottom:2px solid var(--border);">
            <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#regPaneInfo"><i class="fas fa-info-circle mr-1"></i> Info</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#regPaneDetails"><i class="fas fa-list-ul mr-1"></i> Rincian <span id="regDetailCount" class="badge badge-secondary ml-1"></span></a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#regPaneLog"><i class="fas fa-history mr-1"></i> Log</a></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="regPaneInfo"></div>
            <div class="tab-pane fade" id="regPaneDetails">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead style="background:var(--surface-2);">
                            <tr>
                                <th scope="col" style="width:1%;">No</th>
                                <th scope="col">Akun</th>
                                <th scope="col">Deskripsi</th>
                                <th scope="col" class="text-right" style="width:1%;white-space:nowrap;">Qty</th>
                                <th scope="col" class="text-right" style="width:1%;white-space:nowrap;">Harga</th>
                                <th scope="col" class="text-right" style="width:1%;white-space:nowrap;">Total</th>
                            </tr>
                        </thead>
                        <tbody id="regDetailBody"></tbody>
                        <tfoot><tr style="background:var(--surface-2);"><th colspan="5" class="text-right">Total</th><th class="text-right" id="regDetailTotal"></th></tr></tfoot>
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
                <div id="regLogClosed" class="cr-log-closed" hidden><i class="fas fa-lock mr-1"></i> Cost Register sudah selesai — catatan baru tidak dapat ditambahkan.</div>
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
    var table = $('#tblCostRegister').DataTable({
        autoWidth: false, pageLength: 10, lengthChange: true, searching: true,
        order: [], // urutan asli dari server (terbaru di atas)
        columnDefs: [{ targets: [-1], orderable: false, searchable: false }],
        dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex justify-content-end"lp>>',
        language: {
            lengthMenu: 'Page Size _MENU_',
            emptyTable: '<div class="ts-empty ts-empty-compact">'
                + '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-folder-open"></i></span>'
                + '<p class="ts-empty-title">Belum ada Cost Register</p>'
                + '<p class="ts-empty-msg">Belum ada Cost Register pada rentang tanggal yang dipilih.</p></div>',
            zeroRecords: '<div class="ts-empty ts-empty-compact">'
                + '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-search"></i></span>'
                + '<p class="ts-empty-title">Tidak ada yang cocok</p>'
                + '<p class="ts-empty-msg">Tidak ada Cost Register yang cocok dengan pencarian atau filter yang sedang aktif.</p></div>',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data', paginate: { previous: 'Previous', next: 'Next' }
        }
    });

    // Scrollbar mendatar hanya untuk tabel (footer info + paginasi tetap diam).
    $('#tblCostRegister').wrap('<div class="ts-dt-scroll"></div>');

    $('#tblCostRegister_filter').hide();
    $('#regSearchBox').html('<input type="search" id="regSearchInput" class="form-control form-control-sm" placeholder="Cari data...">');
    $('#regSearchInput').on('keyup', function () { table.search(this.value).draw(); });

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
            total += parseInt($(this.node()).find('td.ts-col-money').attr('data-order'), 10) || 0;
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
        Swal.fire({ title: 'Ajukan Cost Register?', text: 'Cost Register berstatus Submitted dan menunggu approval CEO.', icon: 'question',
            showCancelButton: true, confirmButtonColor: WARNA_UTAMA, cancelButtonColor: WARNA_BATAL, confirmButtonText: 'Ya, Ajukan', cancelButtonText: 'Batal' })
        .then(function (r) { if (r.isConfirmed) form.submit(); });
    });
    $(document).on('submit', '.frmDeleteCr', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title: 'Hapus Cost Register?', text: 'Data tidak dapat dikembalikan.', icon: 'warning', showCancelButton: true,
            confirmButtonColor: WARNA_BAHAYA, cancelButtonColor: WARNA_BATAL, confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal' })
        .then(function (r) { if (r.isConfirmed) form.submit(); });
    });
    $(document).on('click', '.btn-approve-cr', function () {
        var id = $(this).data('id');
        Swal.fire({ title: 'Approve Cost Register?', text: 'Setelah disetujui, Treasury dapat memasukkannya ke batch pembayaran.', icon: 'question',
            showCancelButton: true, confirmButtonColor: WARNA_UTAMA, cancelButtonColor: WARNA_BATAL, confirmButtonText: 'Ya, Approve', cancelButtonText: 'Batal' })
        .then(function (r) { if (r.isConfirmed) kirimForm('{{ url('/cost-register') }}/' + id + '/approve'); });
    });
    $(document).on('click', '.btn-revise-cr', function () {
        var id = $(this).data('id');
        Swal.fire({ title: 'Kembalikan untuk revisi?', input: 'textarea', inputAttributes: { maxlength: 500, 'aria-label': 'Alasan revisi' },
            inputLabel: 'Alasan revisi wajib diisi', inputValidator: function (v) { if (!v || !v.trim()) return 'Alasan revisi wajib diisi.'; },
            icon: 'warning', showCancelButton: true, confirmButtonColor: WARNA_BAHAYA, cancelButtonColor: WARNA_BATAL,
            confirmButtonText: 'Ya, Kembalikan', cancelButtonText: 'Batal' })
        .then(function (r) { if (r.isConfirmed) kirimForm('{{ url('/cost-register') }}/' + id + '/revise', { notes: r.value }); });
    });
    $(document).on('click', '.btn-cancel-cr', function () {
        var id = $(this).data('id');
        Swal.fire({ title: 'Batalkan Cost Register?', input: 'textarea', inputAttributes: { maxlength: 500, 'aria-label': 'Alasan pembatalan' },
            inputLabel: 'Alasan pembatalan (opsional)', icon: 'warning', showCancelButton: true,
            confirmButtonColor: WARNA_BAHAYA, cancelButtonColor: WARNA_BATAL, confirmButtonText: 'Ya, Batalkan', cancelButtonText: 'Kembali' })
        .then(function (r) { if (r.isConfirmed) kirimForm('{{ url('/cost-register') }}/' + id + '/cancel', { reason: r.value || '' }); });
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

        // Aksi baris (Ajukan/Approve/Revisi/Batalkan/Hapus) disalin sebagai tombol drawer.
        var $aksi = $baris.find('.ts-actions').children(':not(.ts-action-more):not(.ts-action-menu)')
            .add($baris.find('.ts-action-menu').children(':not(.dropdown-divider)')).clone(true, true);
        $aksi.each(function () {
            var $b = $(this);
            // Form (Ajukan/Hapus) dibungkus tombol di dalamnya; butir lain adalah tombol itu sendiri.
            $b.find('button').addBack('button').removeClass('dropdown-item').addClass('ts-action-primary');
        });
        $('#regDrawerActions').append($aksi);

        function gagal(pesan) {
            $('#regDrawerLoading').hide();
            $('#regDrawerError').html('<div class="ts-empty"><span class="ts-empty-ic"><i class="fas fa-exclamation-triangle"></i></span>'
                + '<p class="ts-empty-title">Detail belum dapat dimuat</p><p class="ts-empty-msg">' + escReg(pesan) + '</p></div>').removeAttr('hidden');
            $('#regDrawer-title').text($baris.data('code') || 'Detail Cost Register');
        }

        $.get('{{ url('/cost-register') }}/' + encodeURIComponent(id) + '/detail-json')
            .done(function (d) {
                if (!d || typeof d !== 'object' || !d.id) { gagal('Sesi Anda mungkin sudah berakhir. Muat ulang halaman, lalu masuk kembali.'); return; }

                $('#regDrawer-eyebrow').html('<button type="button" class="ts-copy-code" data-code="' + escAttrReg(d.project_code) + '" title="Klik untuk menyalin kode">'
                    + escReg(d.project_code) + '</button> · ' + escReg(d.type_label));
                $('#regDrawer-title').text(d.project_name || d.project_code);

                var siapa = function (nama, waktu) { return (nama && nama !== '-') ? nama + (waktu ? ' · ' + waktu : '') : '-'; };
                var info = '<div class="mb-3" style="border:1px solid var(--border);border-radius:var(--radius-lg);padding:16px 18px;">'
                    + '<div style="font-size:12px;color:var(--text-dim);">Nominal</div>'
                    + '<div style="font-size:28px;line-height:1.15;font-weight:700;font-variant-numeric:tabular-nums;margin:2px 0 8px;">' + escReg(d.amount ? d.amount_formatted : '-') + '</div>'
                    + '<span class="ts-status ts-status-' + escAttrReg(d.status_tone || 'neutral') + ' ts-status-sm">' + escReg(d.status_label) + '</span></div>'
                    + '<dl class="ts-dw-kv">'
                    + '<dt>Customer</dt><dd>' + escReg(isiReg(d.customer)) + '</dd>'
                    + '<dt>Tipe</dt><dd>' + escReg(isiReg(d.type_label)) + '</dd>'
                    + '<dt>Periode</dt><dd>' + escReg(isiReg(d.period)) + '</dd>'
                    + '<dt>Beneficiary</dt><dd>' + escReg(isiReg(d.beneficiary)) + '</dd>'
                    + '<dt>Rekening Beneficiary</dt><dd>' + escReg(isiReg(d.beneficiary_bank)) + '</dd>'
                    + '<dt>Rekening Sumber</dt><dd>' + escReg(isiReg(d.source_fund)) + '</dd>'
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
                    return '<tr><td>' + (i + 1) + '</td><td>' + escReg(isiReg(x.account)) + '</td><td>' + escReg(x.description) + '</td>'
                        + '<td class="text-right">' + escReg(x.qty) + '</td><td class="text-right" style="font-variant-numeric:tabular-nums;">' + escReg(x.unit_price) + '</td>'
                        + '<td class="text-right" style="font-variant-numeric:tabular-nums;">' + escReg(x.total) + '</td></tr>';
                }).join('') : '<tr><td colspan="6" class="text-center text-muted py-3">Tanpa rincian (data lama).</td></tr>');
                $('#regDetailTotal').text(d.amount ? d.amount_formatted : '-');

                var atts = d.attachments || [];
                $('#regAttCount').text(atts.length);
                $('#regAttBody').html(atts.length ? atts.map(function (a) {
                    return '<a class="ts-dw-file" href="/storage/' + escAttrReg(a.path) + '" target="_blank" rel="noopener"><i class="fas fa-file"></i><span>' + escReg(a.title) + '</span></a>';
                }).join('') : '<p class="text-muted mb-0" style="font-size:12px;">Belum ada lampiran.</p>');

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
                gagal(xhr && xhr.status === 404 ? 'Cost Register tidak ditemukan.' : 'Terjadi kesalahan saat memuat data. Coba lagi.');
            });
    }

    $(document).on('click', '#tblCostRegister tbody tr[data-id]', function (e) {
        if ($(e.target).closest('a, button, input, select, textarea, label, form, .ts-actions, .ts-action-menu, td.dtr-control').length) return;
        if (window.getSelection && String(window.getSelection()).length) return;
        bukaDrawerReg($(this).data('id'), this);
    });

    // Aksi dari kaki drawer: drawer ditutup dulu supaya konfirmasi tidak tertutup panel.
    $(document).on('click', '#regDrawerActions .btn-approve-cr, #regDrawerActions .btn-revise-cr, #regDrawerActions .btn-cancel-cr, #regDrawerActions form button', function () {
        TesysDrawer.close('regDrawer');
    });

    $(document).on('submit', '#regLogForm', function (e) {
        e.preventDefault();
        var $f = $(this), $in = $('#regLogInput'), $btn = $('#regLogSend');
        var teks = $.trim($in.val());
        if (!teks) { $in.focus(); return; }
        $btn.prop('disabled', true);
        $.post('{{ url('/cost-register') }}/' + $f.data('id') + '/log-note', { _token: $('meta[name="csrf-token"]').attr('content'), notes: teks })
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
