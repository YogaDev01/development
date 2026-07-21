@extends('layouts.app')

@section('title', 'Treasury')

@section('content')

<div class="content-header">
    <div class="container-fluid">

        <!-- SUMMARY CARDS -->
        <div class="row mb-3">
            <div class="col-6 col-md-3">
                <div class="small-box bg-info">
                    <div class="inner"><h3>{{ $summary['total'] }}</h3><p>Total Batch</p></div>
                    <div class="icon"><i class="fas fa-layer-group"></i></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small-box bg-warning">
                    <div class="inner"><h3>{{ $summary['pending_approver'] }}</h3><p>Submitted</p></div>
                    <div class="icon"><i class="fas fa-hourglass-half"></i></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small-box bg-primary">
                    <div class="inner"><h3>{{ $summary['approved'] }}</h3><p>Approved</p></div>
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small-box bg-success">
                    <div class="inner"><h3>{{ $summary['paid'] }}</h3><p>Paid</p></div>
                    <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
                </div>
            </div>
        </div>

        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold">Treasury</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="#">Finance</a></li>
                    <li class="breadcrumb-item active">Treasury</li>
                </ol>
            </div>
        </div>

    </div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="row">

            <!-- LIST BATCH -->
            <div class="col-md-12">
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title"><i class="fas fa-layer-group mr-1"></i> Daftar Batch</h3>
                        <div class="ml-auto d-flex" style="gap:8px;">
                            <button id="btnRefresh" class="btn btn-secondary btn-sm">
                                <i class="fas fa-sync-alt mr-1"></i> Refresh
                            </button>
                            @if(Auth::user()->hasRole(['superadmin','finance']))
                            <a href="{{ url('/daily-transaction/create') }}" class="btn btn-success btn-sm">
                                <i class="fas fa-plus-circle mr-1"></i> Buat Batch Baru
                            </a>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">

                        <!-- FILTER -->
                        <div class="custom-toolbar mb-3">
                            <div class="custom-toolbar-left">
                                <div class="toolbar-row">
                                    <span class="toolbar-label">Cari :</span>
                                    <div class="toolbar-input" id="batchSearchBox"></div>
                                </div>
                                <div class="toolbar-row">
                                    <span class="toolbar-label">Filter :</span>
                                    <div class="toolbar-input">
                                        @php
                                            $filterCategories = $transactions->pluck('sourceFund.name')->filter()->unique()->sort()->values();
                                        @endphp
                                        <select id="batchFilter" multiple placeholder="Filter data...">
                                            <optgroup label="Status">
                                                <option value="STATUS:PENDING_APPROVER">Submitted</option>
                                                <option value="STATUS:APPROVED">Approved</option>
                                                <option value="STATUS:PAID">Paid</option>
                                                <option value="STATUS:REJECTED">Rejected</option>
                                            </optgroup>
                                            <optgroup label="Kategori">
                                                @foreach($filterCategories as $cat)
                                                    <option value="CATEGORY:{{ $cat }}">{{ $cat }}</option>
                                                @endforeach
                                            </optgroup>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="tblBatch" class="table table-bordered table-hover table-striped mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th style="width:1%;" class="text-nowrap">No</th>
                                        <th style="width:1%;" class="text-nowrap">Kode Batch</th>
                                        <th>Deskripsi</th>
                                        <th style="width:1%;" class="text-nowrap">Kategori</th>
                                        <th style="width:1%;" class="text-nowrap text-right">Total</th>
                                        <th style="width:1%;" class="text-nowrap">Status</th>
                                        <th style="width:1%;" class="text-nowrap text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($transactions as $trx)
                                    @php
                                        $sc = [
                                            'PENDING_APPROVER' => 'warning',
                                            'APPROVED'         => 'primary',
                                            'PAID'             => 'success',
                                            'REJECTED'         => 'danger',
                                        ][$trx->status] ?? 'secondary';
                                        $crCount = $trx->includedItems()->count();
                                    @endphp
                                    <tr data-status="{{ $trx->status }}" data-category="{{ $trx->sourceFund->name ?? '' }}">
                                        <td class="text-nowrap">{{ $loop->iteration }}</td>
                                        <td class="text-nowrap">
                                            <span class="badge badge-dark px-2 py-1" style="font-size:10px;">
                                                {{ $trx->code }}
                                            </span>
                                            <div style="font-size:11px;color:#6B7280;margin-top:3px;">
                                                {{ $trx->createdBy->name ?? '-' }}
                                                · {{ $trx->date->format('d M Y') }}
                                            </div>
                                        </td>
                                        <td style="font-size:12px;font-weight:600;">
                                            {{ $trx->description ?? '-' }}
                                            <small class="d-block text-muted" style="font-size:11px;font-weight:400;">{{ $crCount }} Request</small>
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
                                            <span class="badge badge-{{ $sc }} px-2 py-1" style="font-size:10px;">
                                                {{ $trx->status_label }}
                                            </span>
                                        </td>
                                        <td class="text-center text-nowrap">
                                            <div class="d-flex justify-content-center" style="gap:4px;">
                                                <a href="{{ url('/daily-transaction/' . $trx->id) }}"
                                                   class="btn btn-info btn-sm" title="Detail">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                @if($trx->status === 'PENDING_APPROVER' && Auth::user()->hasRole(['superadmin','approver']))
                                                <button type="button"
                                                        class="btn btn-success btn-sm btn-approve-batch"
                                                        data-id="{{ $trx->id }}"
                                                        data-code="{{ $trx->code }}"
                                                        title="Approve Batch">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button"
                                                        class="btn btn-danger btn-sm btn-reject-batch"
                                                        data-id="{{ $trx->id }}"
                                                        data-code="{{ $trx->code }}"
                                                        title="Reject Batch">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="fas fa-folder-open d-block mb-2"></i>
                                            Belum ada batch
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                                <tfoot>
                                    <tr style="background:#F9FAFB;">
                                        <th colspan="4" class="text-right" style="font-size:12px;">Total (data tampil)</th>
                                        <th class="text-right text-nowrap" id="batchFooterTotal" style="font-size:12px;color:#2563EB;"></th>
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
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white"><i class="fas fa-check mr-2" style="color:#F5A623;"></i> Approve Batch</h5>
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
                <div class="modal-footer" style="background:#f8f9fa;">
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
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white"><i class="fas fa-times mr-2" style="color:#F5A623;"></i> Tolak Seluruh Batch</h5>
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
                <div class="modal-footer" style="background:#f8f9fa;">
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
.cr-check-item:hover { background:#f8f9fa; }
.cr-check-item.selected { background:#EFF6FF; }
.btn-xs { padding:2px 8px; font-size:11px; border-radius:4px; }
#tblBatch { width:100% !important; }

.custom-toolbar      { display:flex; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; flex-direction:row; align-items:center; gap:12px; flex-wrap:wrap; width:100%; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; font-weight:600; color:#495057; white-space:nowrap; flex-shrink:0; }
.toolbar-input       { min-width:180px; }
#batchSearchBox input { width:180px; border-radius:6px; }
.ts-wrapper          { min-width:260px; }
.ts-control          { border-radius:6px !important; border:1px solid #ced4da !important; min-height:38px !important; padding:6px 10px !important; box-shadow:none !important; }
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
.dataTables_info { padding-top:0 !important; font-size:14px; color:#6c757d; }
.dataTables_length { display:flex; align-items:center; margin-left:18px; }
.dataTables_length label { display:flex; align-items:center; gap:10px; margin-bottom:0; font-size:14px; font-weight:600; color:#6c757d; }
.dataTables_length select { appearance:none !important; background-color:#fff !important; color:#495057 !important; border:1px solid #ced4da !important; border-radius:6px !important; padding:4px 30px 4px 10px !important; height:36px !important; min-width:72px !important; font-size:14px !important; font-weight:600 !important; cursor:pointer; box-shadow:none !important; }
.dataTables_paginate .paginate_button { border-radius:6px !important; margin:0 2px; }
.dataTables_paginate .paginate_button.current { background:#F5A623 !important; background-image:none !important; border-color:#C97E0A !important; color:#0D1B2A !important; font-weight:700 !important; }
@media (max-width: 576px) {
    .custom-toolbar-left { flex-direction:column; align-items:stretch; }
    .toolbar-row { width:100%; }
    .toolbar-input { flex:1; min-width:0; }
    #batchSearchBox input { width:100%; }
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

    // ── Approve/Reject dibind duluan (defensif, konsisten dgn page lain) ──
    $(document).on('click', '.btn-approve-batch', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        $('#approveBatchText').text('Approve batch ' + code + '? Semua CR akan berstatus QUEUED.');
        $('#frmApproveBatch').attr('action', '/daily-transaction/' + id + '/approve');
        $('#frmApproveBatch textarea[name="notes"]').val('');
        $('#mdlApproveBatch').modal('show');
    });

    $(document).on('click', '.btn-reject-batch', function () {
        var id = $(this).data('id'), code = $(this).data('code');
        $('#rejectBatchText').text('Tolak batch ' + code + '? Semua CR dikembalikan ke VERIFIED.');
        $('#frmRejectBatch').attr('action', '/daily-transaction/' + id + '/reject-batch');
        $('#frmRejectBatch textarea[name="notes"]').val('');
        $('#mdlRejectBatch').modal('show');
    });

    $('#btnRefresh').on('click', function () { location.reload(); });

    try {

    var table = $('#tblBatch').DataTable({
        responsive: { details: { type: 'inline' } }, autoWidth: false, pageLength: 10,
        columnDefs: [
            { responsivePriority: 1,   targets: [0, 1, 2, -1] },
            { responsivePriority: 2,   targets: [5] },
            { responsivePriority: 100, targets: [3, 4] }
        ],
        dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex flex-wrap justify-content-between justify-content-md-end align-items-center gap-2"lp>>',
        language: {
            search: '', lengthMenu: 'Page Size _MENU_', zeroRecords: 'Tidak ada data',
            info: 'Menampilkan _START_-_END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data', paginate: { previous: 'Previous', next: 'Next' }
        }
    });

    $('#tblBatch_filter').hide();
    $('#batchSearchBox').html('<input type="search" id="batchSearchInput" class="form-control form-control-sm" placeholder="Cari data...">');
    $('#batchSearchInput').on('keyup', function () { table.search(this.value).draw(); });

    new TomSelect('#batchFilter', {
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
    | Filter membaca nilai MENTAH dari atribut data-* pada <tr>.
    | Aturan: OR di dalam satu grup, AND antar grup.
    */
    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'tblBatch') return true;

        var filters = $('#batchFilter').val();
        if (!filters || !filters.length) return true;

        var row = settings.aoData[dataIndex].nTr;
        if (!row) return true;

        var rowStatus   = row.getAttribute('data-status')   || '';
        var rowCategory = row.getAttribute('data-category') || '';

        var selStatus = [], selCategory = [];
        filters.forEach(function (f) {
            var i = f.indexOf(':');
            if (i < 0) return;
            var key = f.substring(0, i);
            var val = f.substring(i + 1);
            if (key === 'STATUS')   selStatus.push(val);
            if (key === 'CATEGORY') selCategory.push(val);
        });

        if (selStatus.length   && selStatus.indexOf(rowStatus)     === -1) return false;
        if (selCategory.length && selCategory.indexOf(rowCategory) === -1) return false;

        return true;
    });

    $('#batchFilter').on('change', function () { table.draw(); });

    /*
    | FOOTER KALKULASI — total dari SEMUA baris yang lolos filter/search saat ini.
    */
    function moneyFmtBatch(v) {
        return 'Rp ' + Math.round(v).toLocaleString('id-ID');
    }

    function recalcFooterBatch() {
        var total = 0;
        table.rows({ search: 'applied' }).every(function () {
            var node = this.node();
            total += parseFloat(node.querySelector('.col-raw-total')?.dataset.raw) || 0;
        });
        $('#batchFooterTotal').text(moneyFmtBatch(total));
    }

    table.on('draw', recalcFooterBatch);
    recalcFooterBatch();

    } catch (err) {
        console.error('Gagal inisialisasi DataTable Treasury:', err);
    }

});
</script>
@endpush