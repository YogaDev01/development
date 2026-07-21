@extends('layouts.app')

@section('title', 'Invoice & Cash Receipt')

@section('content')

<div class="content-header">
    <div class="container-fluid">

        <div class="row mb-2">
            <div class="col-sm-6"><h1 class="m-0 font-weight-bold">Invoice & Cash Receipt</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="#">Finance</a></li>
                    <li class="breadcrumb-item active">Invoice & Cash Receipt</li>
                </ol>
            </div>
        </div>

        <div class="row">
            <div class="col-6 col-md-3">
                <div class="small-box bg-info">
                    <div class="inner"><h3>{{ $summary['total'] }}</h3><p>Total Invoice</p></div>
                    <div class="icon"><i class="fas fa-file-invoice"></i></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small-box bg-warning">
                    <div class="inner"><h3>{{ $summary['submitted'] + $summary['verified'] + $summary['approved'] }}</h3><p>Dalam Proses</p></div>
                    <div class="icon"><i class="fas fa-hourglass-half"></i></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small-box bg-danger">
                    <div class="inner"><h3>{{ $summary['overdue'] }}</h3><p>Overdue</p></div>
                    <div class="icon"><i class="fas fa-exclamation-circle"></i></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small-box bg-success">
                    <div class="inner"><h3>{{ $summary['paid'] }}</h3><p>Paid</p></div>
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="card card-primary card-outline shadow-sm">

            <div class="card-header d-flex align-items-center">
                <button id="btnRefreshInv" class="btn btn-secondary btn-sm">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
                @if(Auth::user()->hasRole(['superadmin','admin','finance']))
                <a href="{{ route('invoice-cash-receipt.create') }}" class="btn btn-success btn-sm ml-auto">
                    <i class="fas fa-plus mr-1"></i> Buat Invoice
                </a>
                @endif
            </div>

            <div class="card-body">

                <!-- FILTER -->
                <div class="custom-toolbar mb-3">
                    <div class="custom-toolbar-left">
                        <div class="toolbar-row">
                            <span class="toolbar-label">Cari :</span>
                            <div class="toolbar-input" id="invSearch"></div>
                        </div>
                        <div class="toolbar-row">
                            <span class="toolbar-label">Filter :</span>
                            <div class="toolbar-input">
                                @php
                                    $filterCustomers = $invoices->pluck('po.customer.name')->filter()->unique()->sort()->values();
                                    $filterPOs       = $invoices->pluck('po.name')->filter()->unique()->sort()->values();
                                @endphp
                                <select id="invFilter" multiple placeholder="Filter data...">
                                    <optgroup label="Status">
                                        <option value="STATUS:DRAFT">Draft</option>
                                        <option value="STATUS:SUBMITTED">Submitted</option>
                                        <option value="STATUS:VERIFIED">Verified</option>
                                        <option value="STATUS:APPROVED">Approved</option>
                                        <option value="STATUS:SENT">Sent</option>
                                        <option value="STATUS:PARTIALLY_PAID">Partially Paid</option>
                                        <option value="STATUS:OVERDUE">Overdue</option>
                                        <option value="STATUS:PAID">Paid</option>
                                        <option value="STATUS:CANCELLED">Cancelled</option>
                                    </optgroup>
                                    @if($filterCustomers->count())
                                    <optgroup label="Customer">
                                        @foreach($filterCustomers as $cName)
                                            <option value="CUSTOMER:{{ $cName }}">{{ $cName }}</option>
                                        @endforeach
                                    </optgroup>
                                    @endif
                                    @if($filterPOs->count())
                                    <optgroup label="PO">
                                        @foreach($filterPOs as $poName)
                                            <option value="PO:{{ $poName }}">{{ $poName }}</option>
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
                    <table id="tblInvoice" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="all text-nowrap" style="width:1%;">No</th>
                                <th class="all text-nowrap">Kode Invoice</th>
                                <th class="all">PO / Customer</th>
                                <th class="text-nowrap" style="width:1%;">Termin</th>
                                <th class="text-nowrap" style="width:1%;">Jatuh Tempo</th>
                                <th class="text-right text-nowrap" style="width:1%;">Total Tagihan</th>
                                <th class="text-right text-nowrap" style="width:1%;">Diterima</th>
                                <th class="text-right text-nowrap" style="width:1%;">Sisa</th>
                                <th class="text-nowrap" style="width:1%;">Status</th>
                                <th class="text-center all action-column" style="width:1%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($invoices as $invoice)
                            @php
                                $received = $invoice->totalReceived();
                                $balance  = $invoice->remainingBalance();
                                $dStatus  = $invoice->display_status;
                                $sc = [
                                    'DRAFT'           => 'secondary',
                                    'SUBMITTED'       => 'warning',
                                    'VERIFIED'        => 'info',
                                    'APPROVED'        => 'primary',
                                    'SENT'            => 'indigo',
                                    'PARTIALLY_PAID'  => 'warning',
                                    'OVERDUE'         => 'danger',
                                    'PAID'            => 'success',
                                    'CANCELLED'       => 'dark',
                                ][$dStatus] ?? 'secondary';
                            @endphp
                            <tr data-status="{{ $dStatus }}"
                                data-po="{{ $invoice->po->name ?? '' }}"
                                data-customer="{{ $invoice->po->customer->name ?? '' }}">
                                <td class="text-nowrap">{{ $loop->iteration }}</td>

                                <td class="text-nowrap">
                                    <span class="badge badge-dark px-2 py-1" style="font-size:11px;letter-spacing:0.03em;">
                                        {{ $invoice->code }}
                                    </span>
                                    @if($invoice->is_additional)
                                        <span class="badge badge-warning" style="font-size:9px;" title="Melebihi sisa nilai kontrak PO">ADD</span>
                                    @endif
                                </td>

                                <td>
                                    <span>{{ Str::limit($invoice->po->name ?? '-', 60) }}</span>
                                    <div style="font-size:11px;color:#6B7280;margin-top:3px;">
                                        {{ $invoice->po->customer->name ?? '-' }}
                                    </div>
                                </td>

                                <td class="text-nowrap">{{ $invoice->payment_term_label }}</td>

                                <td class="text-nowrap">
                                    {{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-' }}
                                </td>

                                <td class="text-right text-nowrap col-raw-total" data-raw="{{ $invoice->captured_total ?? 0 }}">
                                    <strong>{{ $invoice->captured_total_formatted }}</strong>
                                </td>

                                <td class="text-right text-nowrap col-raw-received" data-raw="{{ $received }}">
                                    Rp {{ number_format($received, 0, ',', '.') }}
                                </td>

                                <td class="text-right text-nowrap col-raw-balance" data-raw="{{ $balance }}">
                                    Rp {{ number_format($balance, 0, ',', '.') }}
                                </td>

                                <td class="text-nowrap">
                                    <span class="badge badge-{{ $sc }} px-2 py-1" style="font-size:10px;">
                                        {{ str_replace('_', ' ', $dStatus) }}
                                    </span>
                                </td>

                                <td class="text-center action-column">
                                    <div class="action-buttons">

                                        {{-- DETAIL --}}
                                        <a href="{{ route('invoice-cash-receipt.show', $invoice->id) }}"
                                           class="btn btn-info btn-sm" title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        {{-- EDIT (DRAFT only) --}}
                                        @if($invoice->status === 'DRAFT' && Auth::user()->hasRole(['superadmin','admin','finance']))
                                        <a href="{{ route('invoice-cash-receipt.edit', $invoice->id) }}"
                                           class="btn btn-warning btn-sm" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        @endif

                                        {{-- SUBMIT (DRAFT -> SUBMITTED) --}}
                                        @if($invoice->status === 'DRAFT' && Auth::user()->hasRole(['superadmin','admin','finance']))
                                        <form action="{{ route('invoice-cash-receipt.submit', $invoice->id) }}"
                                              method="POST" class="frmSubmitInv d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm" title="Submit">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </form>
                                        @endif

                                        {{-- VERIFY (SUBMITTED -> VERIFIED) — finance manager --}}
                                        @if($invoice->status === 'SUBMITTED' && Auth::user()->hasRole(['superadmin','finance_manager']))
                                        <form action="{{ route('invoice-cash-receipt.verify', $invoice->id) }}"
                                              method="POST" class="frmVerifyInv d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-success btn-sm" title="Verifikasi">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                        @endif

                                        {{-- APPROVE (VERIFIED -> APPROVED) — approver --}}
                                        @if($invoice->status === 'VERIFIED' && Auth::user()->hasRole(['superadmin','approver']))
                                        <form action="{{ route('invoice-cash-receipt.approve', $invoice->id) }}"
                                              method="POST" class="frmApproveInv d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-success btn-sm" title="Approve">
                                                <i class="fas fa-check-double"></i>
                                            </button>
                                        </form>
                                        @endif

                                        {{-- SENT (APPROVED -> SENT) --}}
                                        @if($invoice->status === 'APPROVED' && Auth::user()->hasRole(['superadmin','admin','finance']))
                                        <form action="{{ route('invoice-cash-receipt.sent', $invoice->id) }}"
                                              method="POST" class="frmSentInv d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-indigo btn-sm" title="Tandai Terkirim">
                                                <i class="fas fa-share"></i>
                                            </button>
                                        </form>
                                        @endif

                                        {{-- REVISE (SUBMITTED/VERIFIED) — finance manager / approver --}}
                                        @if(in_array($invoice->status, ['SUBMITTED','VERIFIED']) && Auth::user()->hasRole(['superadmin','finance_manager','approver']))
                                        <button type="button" class="btn btn-secondary btn-sm btn-revise-inv"
                                                data-id="{{ $invoice->id }}" data-code="{{ $invoice->code }}" title="Kembalikan untuk Revisi">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        @endif

                                        {{-- CANCEL — finance manager / approver, not PAID/CANCELLED --}}
                                        @if(!in_array($invoice->status, ['PAID','CANCELLED']) && Auth::user()->hasRole(['superadmin','finance_manager','approver']))
                                        <button type="button" class="btn btn-danger btn-sm btn-cancel-inv"
                                                data-id="{{ $invoice->id }}" data-code="{{ $invoice->code }}" title="Batalkan">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                        @endif

                                        {{-- DELETE (DRAFT only) --}}
                                        @if($invoice->status === 'DRAFT' && Auth::user()->hasRole(['superadmin','admin','finance']))
                                        <form action="{{ route('invoice-cash-receipt.destroy', $invoice->id) }}"
                                              method="POST" class="frmDeleteInv d-inline">
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
                                <td colspan="10" class="text-center text-muted py-4">
                                    <i class="fas fa-folder-open d-block mb-2"></i>
                                    Belum ada data Invoice
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr style="background:#F9FAFB;">
                                <th colspan="5" class="text-right" style="font-size:12px;">Total (data tampil)</th>
                                <th class="text-right text-nowrap" id="invFooterTotal" style="font-size:12px;color:#2563EB;"></th>
                                <th class="text-right text-nowrap" id="invFooterReceived" style="font-size:12px;color:#16a34a;"></th>
                                <th class="text-right text-nowrap" id="invFooterBalance" style="font-size:12px;color:#DC2626;"></th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL REVISE --}}
<div class="modal fade" id="mdlReviseInv" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white"><i class="fas fa-undo mr-2" style="color:#F5A623;"></i> Kembalikan untuk Revisi</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmReviseInv" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <p id="reviseInvText" class="text-muted mb-3"></p>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Catatan Revisi <span class="text-danger">*</span></label>
                        <textarea name="notes" class="form-control" rows="4"
                                  placeholder="Tuliskan alasan atau catatan revisi..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8f9fa;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-undo mr-1"></i> Kembalikan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL CANCEL --}}
<div class="modal fade" id="mdlCancelInv" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white"><i class="fas fa-ban mr-2" style="color:#F5A623;"></i> Batalkan Invoice</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="frmCancelInv" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <div class="alert alert-warning mb-3" style="font-size:12px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        Invoice <strong id="cancelInvCode"></strong> akan dibatalkan dan tidak dapat diproses lagi.
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Alasan Pembatalan <span class="text-danger">*</span></label>
                        <textarea name="cancel_reason" class="form-control" rows="3"
                                  placeholder="Tuliskan alasan pembatalan..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8f9fa;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-ban mr-1"></i> Ya, Batalkan</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
.badge-indigo           { color:#fff; background-color:#6610f2; }
.btn-indigo              { color:#fff; background-color:#6610f2; border-color:#6610f2; }
.btn-indigo:hover        { color:#fff; background-color:#560bd0; border-color:#5209c4; }
.custom-toolbar      { display:flex; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; flex-direction:row; align-items:center; gap:12px; flex-wrap:wrap; width:100%; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; font-weight:600; color:#495057; white-space:nowrap; flex-shrink:0; margin-bottom:0; }
.toolbar-input       { min-width:180px; }
#invSearch input     { width:180px; border-radius:6px; }
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
#tblInvoice          { width:100% !important; }
.table td,.table th  { vertical-align:middle !important; }
.action-column       { white-space:nowrap !important; min-width:130px !important; width:1% !important; }
.action-buttons      { display:flex; align-items:center; justify-content:center; gap:4px; flex-wrap:nowrap; }
.font-weight-600     { font-weight:600; }
.dataTables_wrapper .row:last-child { margin-top:18px; align-items:center; }
.dataTables_info { padding-top:0 !important; font-size:14px; color:#6c757d; }
.dataTables_length { display:flex; align-items:center; margin-left:18px; }
.dataTables_length label { display:flex; align-items:center; gap:10px; margin-bottom:0; font-size:14px; font-weight:600; color:#6c757d; }
.dataTables_length select { appearance:none !important; background-color:#fff !important; color:#495057 !important; border:1px solid #ced4da !important; border-radius:6px !important; padding:4px 30px 4px 10px !important; height:36px !important; min-width:72px !important; font-size:14px !important; font-weight:600 !important; cursor:pointer; box-shadow:none !important; }
.dataTables_paginate .paginate_button { border-radius:6px !important; margin:0 2px; }
.dataTables_paginate .paginate_button.current { background:#F5A623 !important; background-image:none !important; border-color:#C97E0A !important; color:#0D1B2A !important; font-weight:700 !important; }
@media (max-width:576px) {
    .custom-toolbar-left { flex-direction:column; align-items:stretch; }
    .toolbar-row         { width:100%; }
    .toolbar-input       { flex:1; min-width:0; }
    #invSearch input     { width:100%; }
    .ts-wrapper          { width:100% !important; min-width:0 !important; }
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

    /* DATATABLE */
    var table = $('#tblInvoice').DataTable({
        responsive: { details: { type: 'inline' } },
        autoWidth: false, pageLength: 10, lengthChange: true, searching: true,
        columnDefs: [
            { responsivePriority: 1,   targets: [0,1,2,-1] },
            { responsivePriority: 2,   targets: [3,4,5,6,7,8] },
        ],
        order: [[0, 'desc']],
        dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex justify-content-end"lp>>',
        language: { lengthMenu:'Page Size _MENU_', zeroRecords:'Data tidak ditemukan', info:'Menampilkan _START_ sampai _END_ dari _TOTAL_ data', infoEmpty:'Tidak ada data', paginate:{previous:'Previous',next:'Next'} }
    });

    $('#tblInvoice_filter').hide();
    $('#invSearch').html('<input type="search" id="invSearchInput" class="form-control form-control-sm" placeholder="Cari data...">');
    $('#invSearchInput').on('keyup', function () { table.search(this.value).draw(); });

    /*
    | FOOTER KALKULASI — total Tagihan, Diterima, Sisa dari SEMUA baris yang
    | lolos filter/search saat ini (bukan cuma baris di halaman aktif).
    */
    function moneyFmt(v) {
        var sign = v < 0 ? '-' : '';
        return sign + 'Rp ' + Math.abs(Math.round(v)).toLocaleString('id-ID');
    }

    function recalcFooter() {
        var totalAmount = 0, totalReceived = 0, totalBalance = 0;

        table.rows({ search: 'applied' }).every(function () {
            var node = this.node();
            totalAmount   += parseFloat(node.querySelector('.col-raw-total')?.dataset.raw)    || 0;
            totalReceived += parseFloat(node.querySelector('.col-raw-received')?.dataset.raw) || 0;
            totalBalance  += parseFloat(node.querySelector('.col-raw-balance')?.dataset.raw)  || 0;
        });

        $('#invFooterTotal').text(moneyFmt(totalAmount));
        $('#invFooterReceived').text(moneyFmt(totalReceived));
        $('#invFooterBalance').text(moneyFmt(totalBalance));
    }

    table.on('draw', recalcFooter);
    recalcFooter();

    new TomSelect('#invFilter', {
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
        if (settings.nTable.id !== 'tblInvoice') return true;

        var filters = $('#invFilter').val();
        if (!filters || !filters.length) return true;

        var row = settings.aoData[dataIndex].nTr;
        if (!row) return true;

        var rowStatus   = row.getAttribute('data-status')   || '';
        var rowPO       = row.getAttribute('data-po')       || '';
        var rowCustomer = row.getAttribute('data-customer') || '';

        var selStatus = [], selPO = [], selCustomer = [];

        filters.forEach(function(f) {
            var i = f.indexOf(':');
            if (i < 0) return;
            var key = f.substring(0, i);
            var val = f.substring(i + 1);
            if (key === 'STATUS')        selStatus.push(val);
            else if (key === 'PO')       selPO.push(val);
            else if (key === 'CUSTOMER') selCustomer.push(val);
        });

        if (selStatus.length   && selStatus.indexOf(rowStatus)     === -1) return false;
        if (selPO.length       && selPO.indexOf(rowPO)             === -1) return false;
        if (selCustomer.length && selCustomer.indexOf(rowCustomer) === -1) return false;

        return true;
    });

    $('#invFilter').on('change', function () { table.draw(); });
    $('#btnRefreshInv').on('click', function () { location.reload(); });

    /* SUBMIT CONFIRM */
    $(document).on('submit', '.frmSubmitInv', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Submit Invoice?', text:'Invoice akan dikirim untuk diverifikasi Finance Manager.', icon:'question', showCancelButton:true, confirmButtonColor:'#007bff', cancelButtonColor:'#6c757d', confirmButtonText:'Ya, Submit', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) form.submit(); });
    });

    /* VERIFY CONFIRM */
    $(document).on('submit', '.frmVerifyInv', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Verifikasi Invoice?', text:'Status akan berubah menjadi VERIFIED.', icon:'question', showCancelButton:true, confirmButtonColor:'#28a745', cancelButtonColor:'#6c757d', confirmButtonText:'Ya, Verifikasi', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) form.submit(); });
    });

    /* APPROVE CONFIRM */
    $(document).on('submit', '.frmApproveInv', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Approve Invoice?', text:'Status akan berubah menjadi APPROVED.', icon:'question', showCancelButton:true, confirmButtonColor:'#28a745', cancelButtonColor:'#6c757d', confirmButtonText:'Ya, Approve', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) form.submit(); });
    });

    /* SENT CONFIRM */
    $(document).on('submit', '.frmSentInv', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Tandai Sudah Terkirim?', text:'Invoice akan ditandai sudah dikirim ke customer.', icon:'question', showCancelButton:true, confirmButtonColor:'#6610f2', cancelButtonColor:'#6c757d', confirmButtonText:'Ya, Kirim', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) form.submit(); });
    });

    /* DELETE CONFIRM */
    $(document).on('submit', '.frmDeleteInv', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Hapus Invoice?', text:'Data tidak dapat dikembalikan.', icon:'warning', showCancelButton:true, confirmButtonColor:'#d33', cancelButtonColor:'#6c757d', confirmButtonText:'Ya, Hapus', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) form.submit(); });
    });

    /* ── REVISE ── */
    $(document).on('click', '.btn-revise-inv', function () {
        var id   = $(this).data('id');
        var code = $(this).data('code');
        $('#reviseInvText').text('Kembalikan invoice ' + code + ' ke tahap sebelumnya untuk direvisi.');
        $('#frmReviseInv').attr('action', '/invoice-cash-receipt/' + id + '/revise');
        $('#frmReviseInv textarea[name="notes"]').val('');
        $('#mdlReviseInv').modal('show');
    });

    /* ── CANCEL ── */
    $(document).on('click', '.btn-cancel-inv', function () {
        var id   = $(this).data('id');
        var code = $(this).data('code');
        $('#cancelInvCode').text(code);
        $('#frmCancelInv').attr('action', '/invoice-cash-receipt/' + id + '/cancel');
        $('#frmCancelInv textarea[name="cancel_reason"]').val('');
        $('#mdlCancelInv').modal('show');
    });

});
</script>
@endpush