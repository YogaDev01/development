@extends('layouts.app')

@section('title', 'Treasury')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Treasury</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item active">Treasury</li>
            </ol>
        </div>

        <!-- SUMMARY WORKFLOW (clickable, filter tabel Daftar Batch) -->
        @php
            $batchSteps = [
                ['Submitted', $summary['pending_approver'], 'warning', 'PENDING_APPROVER'],
                ['Done',      $summary['done'],             'primary', 'DONE'],
                ['Paid',      $summary['paid'],              'success', 'PAID'],
                ['Rejected',  $summary['rejected'] ?? 0,     'danger',  'REJECTED'],
            ];
        @endphp
        <x-workflow-stepper :steps="$batchSteps" :compact="true" table="tblBatch" field="status" />

        <div class="row mt-3">

            <!-- LIST BATCH -->
            <div class="col-md-12">
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header d-flex align-items-center flex-wrap" style="gap:8px;">
                        <h3 class="card-title"><i class="fas fa-layer-group mr-1"></i> Daftar Batch</h3>
                        <div class="ml-auto d-flex align-items-center flex-wrap" style="gap:8px;">
                            {{-- Satu kotak rentang (TesysDateRange): klik membuka dua
                                 kalender From/To. Nilainya tersimpan di input hidden. --}}
                            <div class="d-flex align-items-center batch-date-range" style="gap:4px;">
                                <input type="text" id="batchDateRange" class="form-control form-control-sm tdr-input" readonly>
                                <input type="hidden" id="batchDateFrom" value="{{ substr($dateFrom, 0, 10) }}">
                                <input type="hidden" id="batchDateTo" value="{{ substr($dateTo, 0, 10) }}">
                            </div>
                            <button id="btnRefresh" class="btn btn-secondary btn-sm">
                                <i class="fas fa-sync-alt mr-1"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="card-body">

                        {{-- Daftar dibatasi 1.000 baris terbaru (Concerns\CapsLargeLists);
                             angka pada kartu ringkasan tetap dihitung dari seluruh data. --}}
                        @if(!empty($truncated))
                        <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:12.5px;">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Menampilkan <strong>{{ number_format($maxRows, 0, ',', '.') }}</strong> batch terbaru dari total
                            <strong>{{ number_format($total, 0, ',', '.') }}</strong>.
                            Persempit rentang tanggal untuk melihat sisanya.
                        </div>
                        @endif

                        <!-- FILTER -->
                        <div class="custom-toolbar mb-3 d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
                            <div class="custom-toolbar-left d-flex align-items-center flex-wrap" style="gap:8px;">
                                <div class="toolbar-row">
                                    <span class="toolbar-label">Cari :</span>
                                    <div class="toolbar-input" id="batchSearchBox"></div>
                                </div>
                                <button aria-label="Clear Filter" type="button" id="btnClearFilterBatch" class="btn btn-outline-secondary btn-sm" title="Clear Filter">
                                    <i class="fas fa-eraser"></i>
                                </button>
                                <div id="batchRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>
                            </div>
                            <div class="custom-toolbar-right d-flex flex-wrap" style="gap:8px;">
                                <a href="{{ url('/daily-transaction/export') }}" id="btnExportBatch" class="btn btn-info btn-sm">
                                    <i class="fas fa-file-excel mr-1"></i> Export Excel
                                </a>
                                @if(Auth::user()->hasRole(['superadmin','finance','finance_manager','treasury']))
                                <a href="{{ url('/daily-transaction/create') }}" class="btn btn-success btn-sm">
                                    <i class="fas fa-plus-circle mr-1"></i> Buat Batch Baru
                                </a>
                                @endif
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="tblBatch" class="table table-bordered table-hover table-striped mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th scope="col" style="width:1%;" class="text-nowrap">No</th>
                                        <th scope="col" style="width:1%;" class="text-nowrap">Kode Batch</th>
                                        <th scope="col">Deskripsi</th>
                                        <th scope="col" style="width:1%;" class="text-nowrap">Type</th>
                                        <th scope="col" style="width:1%;" class="text-nowrap">Kategori</th>
                                        <th scope="col" style="width:1%;" class="text-nowrap text-right">Total</th>
                                        <th scope="col" style="width:1%;" class="text-nowrap">Status</th>
                                        <th scope="col" style="width:1%;" class="text-nowrap text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($transactions as $trx)
                                    @php
                                        // Relasi sudah di-eager load di controller; ()->count() memicu satu query per baris.
                                        $crCount = $trx->includedItems->count();
                                    @endphp
                                    <tr data-id="{{ $trx->id }}" data-status="{{ $trx->status }}" data-category="{{ $trx->sourceFund->name ?? '' }}" data-type="{{ $trx->type_label }}">
                                        <td class="text-nowrap">{{ $loop->iteration }}</td>
                                        <td class="text-nowrap">
                                            <span class="badge badge-dark px-2 py-1" style="font-size:10px;">
                                                {{ $trx->code }}
                                            </span>
                                            <div style="font-size:11px;color:var(--text-faint);margin-top:3px;">
                                                {{ $trx->createdBy->name ?? '-' }}
                                                · {{ $trx->date->format('d M Y') }}
                                            </div>
                                        </td>
                                        <td style="font-size:12px;font-weight:600;">
                                            {{ $trx->description ?? '-' }}
                                            @if($trx->isTopUp())
                                                <small class="d-block text-muted" style="font-size:11px;font-weight:400;">{{ $trx->sourceFund->name ?? '-' }} → {{ $trx->toSourceFund->name ?? '-' }}</small>
                                            @else
                                                <small class="d-block text-muted" style="font-size:11px;font-weight:400;">{{ $crCount }} Request</small>
                                            @endif
                                        </td>
                                        <td class="text-nowrap">
                                            <span class="badge badge-{{ $trx->type_color }} px-2 py-1" style="font-size:10px;">
                                                {{ $trx->type_label }}
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            @if($trx->sourceFund)
                                                <span class="badge badge-secondary px-2 py-1" style="font-size:10px;">
                                                    {{ $trx->sourceFund->name }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-right text-nowrap col-raw-total" data-raw="{{ $trx->grand_total }}">
                                            <strong style="font-size:12px;">{{ $trx->grand_total_formatted }}</strong>
                                        </td>
                                        <td class="text-nowrap">
                                            <x-status :status="$trx->status" context="daily-transaction" />
                                        </td>
                                        <td class="text-center text-nowrap">
                                            @php
                                                // Gerbang peran disalin apa adanya; yang berubah hanya susunannya.
                                                $bolehPutus = $trx->status === 'PENDING_APPROVER' && Auth::user()->hasRole(['superadmin','approver']);
                                            @endphp
                                            <div class="ts-actions">
                                                @if($bolehPutus)
                                                <button type="button" class="ts-action-primary btn-approve-batch"
                                                        data-id="{{ $trx->id }}" data-code="{{ $trx->code }}"
                                                        title="Approve Batch">
                                                    <i class="fas fa-check"></i><span class="ts-al">Approve</span>
                                                </button>
                                                @endif

                                                <button type="button" class="ts-action-more"
                                                        aria-haspopup="true" aria-expanded="false"
                                                        title="Aksi lain" aria-label="Aksi lain untuk batch {{ $trx->code }}">
                                                    <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                                </button>

                                                <div class="ts-action-menu dropdown-menu" hidden>
                                                    <a href="{{ url('/daily-transaction/' . $trx->id) }}" class="dropdown-item">
                                                        <i class="fas fa-eye"></i>Detail
                                                    </a>

                                                    @if($bolehPutus)
                                                    <div class="dropdown-divider"></div>
                                                    <button type="button" class="dropdown-item is-danger btn-reject-batch"
                                                            data-id="{{ $trx->id }}" data-code="{{ $trx->code }}">
                                                        <i class="fas fa-times"></i>Reject
                                                    </button>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    {{-- Sengaja tidak ada baris di sini. Tabel ini dikelola DataTables, dan
                                         baris kosong buatan sendiri akan dianggap baris data lalu selnya
                                         dikosongkan. Keadaan kosongnya diisi language.emptyTable, sedangkan
                                         language.zeroRecords untuk hasil pencarian yang tidak cocok. --}}
                                    @endforelse
                                </tbody>
                                <tfoot>
                                    <tr style="background:var(--surface-2);">
                                        <th colspan="5" class="text-right" style="font-size:12px;">Total (data tampil)</th>
                                        <th class="text-right text-nowrap" id="batchFooterTotal" style="font-size:12px;color:var(--info);"></th>
                                        <th colspan="2"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>


        </div>
    </div>
</div>

<!-- MODAL APPROVE BATCH -->
<div class="modal fade" id="mdlApproveBatch" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white"><i class="fas fa-check mr-2" style="color:var(--accent);"></i> Approve Batch</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmApproveBatch" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <p id="approveBatchText" class="text-muted mb-3"></p>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Catatan (opsional)</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Catatan approval..."></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check mr-1"></i> Ya, Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL REJECT BATCH -->
<div class="modal fade" id="mdlRejectBatch" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white"><i class="fas fa-times mr-2" style="color:var(--accent);"></i> Tolak Seluruh Batch</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmRejectBatch" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <p id="rejectBatchText" class="text-muted mb-3"></p>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Alasan <span class="text-danger">*</span></label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Tuliskan alasan penolakan..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times mr-1"></i> Tolak Batch</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Data CR amounts untuk JS --}}
@endsection

@push('styles')
<style>
.font-weight-600 { font-weight:600; }
.table td,.table th { vertical-align:middle !important; }
.cr-check-item:hover { background:var(--surface-2); }
.cr-check-item.selected { background:var(--info-bg); }
.btn-xs { padding:2px 8px; font-size:11px; border-radius:var(--radius-sm); }
#tblBatch { width:100% !important; }

.custom-toolbar      { display:flex; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; flex-direction:row; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; font-weight:600; color:var(--text-dim); white-space:nowrap; flex-shrink:0; }
.toolbar-input       { min-width:180px; }
#batchSearchBox input { width:180px; border-radius:var(--radius-sm); }
.ts-wrapper          { min-width:260px; }
.ts-control          { border-radius:var(--radius-sm) !important; border:1px solid var(--border) !important; min-height:38px !important; padding:6px 10px !important; box-shadow:none !important; }
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

.dataTables_wrapper .row:last-child { margin-top:18px; align-items:center; }
.dataTables_info { padding-top:0 !important; font-size:14px; color:var(--text-faint); }
.dataTables_length { display:flex; align-items:center; margin-left:18px; }
.dataTables_length label { display:flex; align-items:center; gap:10px; margin-bottom:0; font-size:14px; font-weight:600; color:var(--text-faint); }
.dataTables_length select { appearance:none !important; background-color:var(--surface) !important; color:var(--text-dim) !important; border:1px solid var(--border) !important; border-radius:var(--radius-sm) !important; padding:4px 30px 4px 10px !important; height:36px !important; min-width:72px !important; font-size:14px !important; font-weight:600 !important; cursor:pointer; box-shadow:none !important; }
.dataTables_paginate .paginate_button { border-radius:var(--radius-sm) !important; margin:0 2px; }
.dataTables_paginate .paginate_button.current { background:var(--accent) !important; background-image:none !important; border-color:var(--accent-dark) !important; color:#fff !important; font-weight:700 !important; }
@media (max-width: 576px) {
    .custom-toolbar { flex-direction:column !important; align-items:stretch !important; }
    .custom-toolbar-left { flex-direction:column !important; align-items:stretch !important; width:100%; }
    .custom-toolbar-right { width:100%; }
    .custom-toolbar-right .btn { width:100%; }
    .toolbar-row { width:100%; }
    .toolbar-input { flex:1; min-width:0; }
    #batchSearchBox input { width:100%; }
    #btnClearFilterBatch { width:100%; }
    #batchRowFilters { width:100%; }
    #batchRowFilters .tcf-row-btn { width:100%; }
    .ts-wrapper { width:100% !important; min-width:0 !important; }
    .card-header { flex-direction:column; align-items:stretch !important; gap:10px; }
    .card-header .card-title { margin-bottom:0; }
    .card-header .ml-auto { margin-left:0 !important; flex-wrap:wrap; }
    .card-header .ml-auto .btn { flex:1 1 auto; }
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
</style>
@endpush

@push('scripts')
<script>
$(function () {

    /* ── FILTER TANGGAL ────────────────────────────────────────────────────
    | Komponen bersama TesysDateRange (public/js/tesys-date-range.js), sama
    | seperti Cost Request: satu kotak teks yang membuka dua kalender From/To.
    | Rentang baru langsung memuat ulang daftar batch (filternya server-side).
    | Kalau flatpickr gagal termuat, filter turun ke dua input teks manual. */
    function reloadWithDateRangeBatch(from, to, range) {
        var url = new URL(window.location.href);
        url.searchParams.set('date_from', from);
        url.searchParams.set('date_to', to);
        if (range) { url.searchParams.set('range', range); }
        else       { url.searchParams.delete('range'); }
        window.location.href = url.toString();
    }

    var batchPeriode = (typeof TesysDateRange === 'object')
        ? TesysDateRange.init({
            input: '#batchDateRange', from: '#batchDateFrom', to: '#batchDateTo',
            quick: true,
            onApply: function (from, to, range) {
                /* Pemanggilan pertama hanya menuliskan nilai awal. */
                if (from === '{{ substr($dateFrom, 0, 10) }}' && to === '{{ substr($dateTo, 0, 10) }}') return;
                reloadWithDateRangeBatch(from, to, range);
            }
        })
        : null;

    if (!batchPeriode) {
        console.warn('TesysDateRange tidak tersedia - filter tanggal beralih ke mode input manual.');
        $('#batchDateRange').remove();
        $('#batchDateFrom, #batchDateTo').attr('type', 'text')
            .addClass('form-control form-control-sm')
            .attr('placeholder', 'YYYY-MM-DD')
            .on('change', function () {
                reloadWithDateRangeBatch($('#batchDateFrom').val(), $('#batchDateTo').val());
            });
    }

    /*
      Warna tombol konfirmasi dan keadaan sibuk — pola yang sama dengan Cost
      Request. Aksi batch adalah POST biasa yang memuat ulang halaman, jadi
      tanda sibuk memang berguna di sini.
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

    $(document).on('submit', '#frmApproveBatch, #frmRejectBatch', function () { sibukkan(this); });

    // ── Approve/Reject dibind duluan (defensif, konsisten dgn page lain) ──
    $(document).on('click', '.btn-approve-batch', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        $('#approveBatchText').text('Approve batch ' + code + '? Batch siap diproses untuk pembayaran.');
        $('#frmApproveBatch').attr('action', '/daily-transaction/' + id + '/approve');
        $('#frmApproveBatch textarea[name="notes"]').val('');
        $('#mdlApproveBatch').modal('show');
    });

    $(document).on('click', '.btn-reject-batch', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        $('#rejectBatchText').text('Tolak batch ' + code + '? Semua CR dikembalikan ke APPROVED.');
        $('#frmRejectBatch').attr('action', '/daily-transaction/' + id + '/reject-batch');
        $('#frmRejectBatch textarea[name="notes"]').val('');
        $('#mdlRejectBatch').modal('show');
    });

    $('#btnRefresh').on('click', function () { location.reload(); });

    (function updateExportBatchLink() {
        var url = '{{ url("/daily-transaction/export") }}?date_from=' + encodeURIComponent('{{ $dateFrom }}') + '&date_to=' + encodeURIComponent('{{ $dateTo }}');
        $('#btnExportBatch').attr('href', url);
    })();

    $('#btnClearFilterBatch').on('click', function () {
        TesysColumnFilter.clearAll('#tblBatch');
        TesysWorkflowStepper.clear('tblBatch');
    });

    try {

    var table = $('#tblBatch').DataTable({
        responsive: { details: { type: 'inline' } }, autoWidth: false, pageLength: 10,
        order: [], // pertahankan urutan asli dari server (input terbaru di atas)
        columnDefs: [
            { targets: [0], visible: false, orderable: false },
            { responsivePriority: 1,   targets: [0, 1, 2, -1] },
            { responsivePriority: 2,   targets: [6] },
            { responsivePriority: 3,   targets: [3] },
            { responsivePriority: 100, targets: [4, 5] }
        ],
        dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex flex-wrap justify-content-between justify-content-md-end align-items-center gap-2"lp>>',
        language: {
            search: '', lengthMenu: 'Page Size _MENU_',
            emptyTable: '<div class="ts-empty ts-empty-compact">'
                + '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-folder-open"></i></span>'
                + '<p class="ts-empty-title">Belum ada batch pembayaran</p>'
                + '<p class="ts-empty-msg">Belum ada batch pada rentang tanggal yang dipilih.</p></div>',
            zeroRecords: '<div class="ts-empty ts-empty-compact">'
                + '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-search"></i></span>'
                + '<p class="ts-empty-title">Tidak ada yang cocok</p>'
                + '<p class="ts-empty-msg">Tidak ada batch yang cocok dengan pencarian atau filter yang sedang aktif.</p></div>',
            info: 'Menampilkan _START_-_END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data', paginate: { previous: 'Previous', next: 'Next' }
        }
    });

    TesysColumnFilter.initRowFilters('#tblBatch', '#batchRowFilters', [
        { key: 'type', label: 'Type' },
        { key: 'category', label: 'Kategori' },
    ]);

    $('#tblBatch_filter').hide();
    $('#batchSearchBox').html('<input type="search" id="batchSearchInput" class="form-control form-control-sm" placeholder="Cari data...">');
    $('#batchSearchInput').on('keyup', function () { table.search(this.value).draw(); });

    /*
    | FOOTER KALKULASI — total dari SEMUA baris yang lolos filter/search saat ini.
    */
    function moneyFmtBatch(v) {
        return Math.round(v).toLocaleString('id-ID');
    }

    function recalcFooterBatch() {
        var total = 0;
        table.rows({ search: 'applied' }).every(function () {
            var node = this.node();
            total += parseFloat(node.querySelector('.col-raw-total')?.dataset.raw) || 0;
        });
        $('#batchFooterTotal').text(moneyFmtBatch(total));
    }

    /* Export Excel mengikuti filter yang SEDANG aktif di tabel — lihat
       public/js/tesys-export-filtered.js. Tanpa filter tambahan, tautannya
       tetap GET dengan rentang tanggal yang sudah dipasang di atas. */
    if (window.TesysExportFiltered) {
        TesysExportFiltered.wire('#btnExportBatch', table, '{{ url("/daily-transaction/export") }}');
    }

    table.on('draw', recalcFooterBatch);
    recalcFooterBatch();

    } catch (err) {
        console.error('Gagal inisialisasi DataTable Treasury:', err);
    }

});
</script>
@endpush