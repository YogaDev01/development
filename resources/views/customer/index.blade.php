@extends('layouts.app')

@section('title', 'Customer')

@section('content')

<!-- CONTENT HEADER -->
<div class="content-header">
    <div class="container-fluid">

        <!-- SUMMARY CARDS
        <div class="row mb-3">
            <div class="col-12 col-md-3">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>{{ $customers->count() }}</h3>
                        <p>Total Customer</p>
                    </div>
                    <div class="icon"><i class="fas fa-building"></i></div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>{{ $customers->filter(fn($c) => strtoupper(optional($c->customerType)->name ?? '') === 'TEREX' && $c->status === 'ACTIVE')->count() }}</h3>
                        <p>Done By Terex Active</p>
                    </div>
                    <div class="icon"><i class="fas fa-user-check"></i></div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>{{ $customers->filter(fn($c) => strtoupper(optional($c->customerType)->name ?? '') === 'HANASTA' && $c->status === 'ACTIVE')->count() }}</h3>
                        <p>Done By Hanasta Active</p>
                    </div>
                    <div class="icon"><i class="fas fa-handshake"></i></div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="small-box bg-secondary">
                    <div class="inner">
                        <h3>{{ $customers->where('status', 'INACTIVE')->count() }}</h3>
                        <p>Inactive Customer</p>
                    </div>
                    <div class="icon"><i class="fas fa-ban"></i></div>
                </div>
            </div>
        </div> -->

        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold">Customer Master</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="#">Master</a></li>
                    <li class="breadcrumb-item active">Customer</li>
                </ol>
            </div>
        </div>

    </div>
</div>

<!-- MAIN CONTENT -->
<div class="content">
    <div class="container-fluid">

        <div class="card card-primary card-outline shadow-sm">

            <!-- CARD HEADER -->
            <div class="card-header d-flex align-items-center">
                <button id="refreshTable" class="btn btn-secondary btn-sm" type="button">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
                <a href="{{ url('/customer/create') }}" class="btn btn-success btn-sm ml-auto">
                    <i class="fas fa-plus mr-1"></i> Tambah Customer
                </a>
            </div>

            <!-- CARD BODY -->
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
                                <select id="advancedFilter" multiple placeholder="Filter data...">
                                    @foreach($types as $type)
                                        <option value="TYPE:{{ $type->name }}">Type : {{ $type->name }}</option>
                                    @endforeach
                                    <option value="STATUS:ACTIVE">Status : ACTIVE</option>
                                    <option value="STATUS:INACTIVE">Status : INACTIVE</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TABLE -->
                <div class="table-responsive">
                    <table id="customerTable" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th width="5%"  class="all">No</th>
                                <th width="35%" class="all">Nama Customer</th>
                                <th width="25%">Alamat</th>
                                <th width="10%">Type</th>
                                <th width="10%">Status</th>
                                <th width="5%" class="text-center all action-column">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($customers as $customer)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $customer->name }}</td>
                                <td>{{ $customer->address ?? '-' }}</td>
                                <td>
                                    <span class="badge badge-info px-3 py-2">
                                        {{ $customer->customerType->name ?? '-' }}
                                    </span>
                                </td>
                                <td>
                                    @if($customer->status == 'ACTIVE')
                                        <span class="badge badge-success px-2 py-1">ACTIVE</span>
                                    @else
                                        <span class="badge badge-danger px-2 py-1">INACTIVE</span>
                                    @endif
                                </td>
                                <td class="text-center action-column">
                                    <div class="action-buttons">

                                        {{-- DETAIL → modal --}}
                                        <button type="button"
                                                class="btn btn-info btn-sm btn-detail"
                                                data-id="{{ $customer->id }}"
                                                title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </button>

                                        <a href="{{ url('/customer/' . $customer->id . '/edit') }}"
                                           class="btn btn-warning btn-sm" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>

                                        <form action="{{ url('/customer/' . $customer->id) }}"
                                              method="POST" style="display:inline-block;"
                                              class="delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>

                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fas fa-folder-open d-block mb-2"></i>
                                    Belum ada data customer
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

    </div>
</div>

<!-- ================================================================ -->
<!-- MODAL DETAIL CUSTOMER                                             -->
<!-- ================================================================ -->
<div class="modal fade" id="modalDetail" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="border-radius:12px; overflow:hidden;">

            <!-- HEADER -->
            <div class="modal-header" style="background:#0D1B2A; border-bottom:3px solid #F5A623; padding:16px 24px;">
                <div class="d-flex align-items-center">
                    <div style="width:36px;height:36px;background:#F5A623;border-radius:8px;display:flex;align-items:center;justify-content:center;margin-right:12px;flex-shrink:0;">
                        <i class="fas fa-building" style="color:#0D1B2A;font-size:16px;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0" id="modalDetailTitle">Detail Customer</h5>
                        <small id="modalDetailSubtitle" style="color:#8A9BB0;font-size:11px;letter-spacing:0.05em;"></small>
                    </div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:0.7;">
                    <span>&times;</span>
                </button>
            </div>

            <!-- BODY -->
            <div class="modal-body" style="padding:0; background:#F0F2F5;">

                <!-- Loading state -->
                <div id="detailLoading" class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x" style="color:#F5A623;"></i>
                    <p class="text-muted mt-3 mb-0">Memuat data customer...</p>
                </div>

                <!-- Content (hidden until loaded) -->
                <div id="detailContent" style="display:none;">

                    <div class="row no-gutters">

                        <!-- ── LEFT: PROFILE ── -->
                        <div class="col-md-3" style="background:#0D1B2A; padding:28px 20px; min-height:500px;">

                            <!-- Avatar -->
                            <div class="text-center mb-4">
                                <div id="detailAvatar"
                                     style="width:80px;height:80px;border-radius:50%;background:#1D2D3F;border:3px solid #F5A623;display:inline-flex;align-items:center;justify-content:center;margin:0 auto;">
                                    <i class="fas fa-users" style="font-size:32px;color:#F5A623;"></i>
                                </div>
                                <h6 id="detailName" class="text-white mt-3 mb-1 font-weight-bold" style="font-size:14px; line-height:1.4;"></h6>
                                <div id="detailStatusBadge" class="mt-1"></div>
                            </div>

                            <hr style="border-color:rgba(255,255,255,0.08); margin:16px 0;">

                            <!-- Stats -->
                            <div class="detail-stat-item">
                                <span style="color:#5A7080;font-size:10px;letter-spacing:0.1em;text-transform:uppercase;font-weight:600;">Total PO</span>
                                <div id="detailTotalPO" class="text-white mt-1" style="font-size:22px;font-weight:700;"></div>
                            </div>
                            <div class="detail-stat-item mt-3">
                                <span style="color:#5A7080;font-size:10px;letter-spacing:0.1em;text-transform:uppercase;font-weight:600;">
                                    Total Nilai PO
                                </span>

                                <div id="detailTotalPOAmount"
                                    style="color:#F5A623;font-size:16px;font-weight:700;margin-top:4px;">
                                </div>
                            </div>
                            <div class="detail-stat-item mt-3">
                                <span style="color:#5A7080;font-size:10px;letter-spacing:0.1em;text-transform:uppercase;font-weight:600;">Dibuat</span>
                                <div id="detailCreatedAt" class="text-white mt-1" style="font-size:12px;"></div>
                            </div>

                            <hr style="border-color:rgba(255,255,255,0.08); margin:20px 0 16px;">

                            <!-- Action buttons -->
                            <a id="detailEditBtn" href="#"
                               class="btn btn-block"
                               style="background:#F5A623;color:#0D1B2A;font-weight:700;font-size:13px;border-radius:6px;">
                                <i class="fas fa-edit mr-1"></i> Edit Customer
                            </a>

                        </div>

                        <!-- ── RIGHT: TABS ── -->
                        <div class="col-md-9" style="padding:24px;">

                            <!-- Tab nav -->
                            <ul class="nav nav-tabs mb-3" id="detailTabs" role="tablist"
                                style="border-bottom:2px solid #E5E7EB;">
                                <li class="nav-item">
                                    <a class="nav-link active" id="tab-info" data-toggle="tab" href="#pane-info" role="tab"
                                       style="font-size:13px;font-weight:600;">
                                        <i class="fas fa-info-circle mr-1"></i> Informasi
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="tab-pic" data-toggle="tab" href="#pane-pic" role="tab"
                                       style="font-size:13px;font-weight:600;">
                                        <i class="fas fa-user-tie mr-1"></i> PIC
                                        <span id="picCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="tab-attachment" data-toggle="tab" href="#pane-attachment" role="tab"
                                       style="font-size:13px;font-weight:600;">
                                        <i class="fas fa-paperclip mr-1"></i> Lampiran
                                        <span id="attCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="tab-po" data-toggle="tab" href="#pane-po" role="tab"
                                       style="font-size:13px;font-weight:600;">
                                        <i class="fas fa-file-contract mr-1"></i> PO
                                        <span id="poCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                                    </a>
                                </li>
                            </ul>

                            <div class="tab-content">

                                <!-- TAB: INFORMASI -->
                                <div class="tab-pane fade show active" id="pane-info" role="tabpanel">
                                    <table class="table table-bordered mb-0" style="font-size:13px;">
                                        <tr>
                                            <th width="35%" style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Nama Customer</th>
                                            <td id="infoName"></td>
                                        </tr>
                                        <tr>
                                            <th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Alamat</th>
                                            <td id="infoAddress" style="white-space:pre-line;"></td>
                                        </tr>
                                        <tr>
                                            <th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">NPWP</th>
                                            <td id="infoNpwp"></td>
                                        </tr>
                                        <tr>
                                            <th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Type</th>
                                            <td id="infoDoneBy"></td>
                                        </tr>
                                        <tr>
                                            <th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Status</th>
                                            <td id="infoStatus"></td>
                                        </tr>
                                    </table>
                                </div>

                                <!-- TAB: PIC -->
                                <div class="tab-pane fade" id="pane-pic" role="tabpanel">
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0" style="font-size:13px;">
                                            <thead style="background:#F9FAFB;">
                                                <tr>
                                                    <th style="color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;width:5%;">No</th>
                                                    <th style="color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">PIC</th>
                                                    <th style="color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Position</th>
                                                    <th style="color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;width:20%;">Whatsapp</th>
                                                    <th style="color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Email</th>
                                                </tr>
                                            </thead>
                                            <tbody id="picBody"></tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- TAB: LAMPIRAN -->
                                <div class="tab-pane fade" id="pane-attachment" role="tabpanel">
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0" style="font-size:13px;">
                                            <thead style="background:#F9FAFB;">
                                                <tr>
                                                    <th style="color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;width:5%;">No</th>
                                                    <th style="color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Judul</th>
                                                    <th style="color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">File Name</th>
                                                    <th style="color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;width:10%;">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody id="attBody"></tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- TAB: PO -->
                                <div class="tab-pane fade" id="pane-po" role="tabpanel">
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0" style="font-size:13px;">
                                            <thead style="background:#F9FAFB;">
                                                <tr>
                                                    <th style="color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;width:5%;">No</th>
                                                    <th style="color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Deskripsi PO</th>
                                                    <th style="color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;width:20%;">Total Nilai PO</th>
                                                    <th style="color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;width:12%;">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody id="poBody"></tbody>
                                        </table>
                                    </div>
                                </div>

                            </div>{{-- /.tab-content --}}

                        </div>{{-- /.col-md-9 --}}

                    </div>{{-- /.row --}}

                </div>{{-- /#detailContent --}}

            </div>{{-- /.modal-body --}}

            <!-- FOOTER -->
            <div class="modal-footer" style="background:#F8F9FA; border-top:1px solid #E9ECEF; padding:12px 24px;">
                <small class="text-muted mr-auto">
                    <i class="fas fa-info-circle mr-1"></i>
                    Data read-only. Klik Edit untuk mengubah.
                </small>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Tutup
                </button>
                <a id="detailEditBtnFooter" href="#" class="btn btn-warning btn-sm">
                    <i class="fas fa-edit mr-1"></i> Edit Customer
                </a>
            </div>

        </div>
    </div>
</div>

@endsection

@push('styles')
<style>

/* TOOLBAR */
.custom-toolbar      { display:flex; justify-content:space-between; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; margin-bottom:0; white-space:nowrap; font-weight:600; color:#495057; }
.toolbar-input       { min-width:180px; }
#customSearch        { width:100%; }
#customSearch input  { width:180px; border-radius:6px; }

/* TOMSELECT */
.ts-wrapper  { min-width:260px; width:100%; }
.ts-control  { border-radius:6px !important; border:1px solid #ced4da !important; min-height:38px !important; padding:6px 10px !important; box-shadow:none !important; }
.ts-control.focus { border-color:#F5A623 !important; box-shadow:0 0 0 0.15rem rgba(245,166,35,.15) !important; }

/* TABLE */
#customerTable       { width:100% !important; }
.table td,.table th  { vertical-align:middle !important; }
.action-column       { white-space:nowrap !important; min-width:100px !important; }
.action-buttons      { display:flex; align-items:center; justify-content:center; flex-wrap:nowrap; gap:4px; }
.action-buttons .btn { flex-shrink:0; }

table.dataTable.dtr-inline.collapsed > tbody > tr > td:first-child:before,
table.dataTable.dtr-inline.collapsed > tbody > tr > th:first-child:before {
    top:50%; transform:translateY(-50%);
}

/* TABLE BOTTOM */
.dataTables_wrapper .row:last-child { margin-top:18px; align-items:center; }
.dataTables_info { padding-top:0 !important; font-size:14px; color:#6c757d; }

/* PAGE SIZE */
.dataTables_length { display:flex; align-items:center; margin-left:18px; }
.dataTables_length label { display:flex; align-items:center; gap:10px; margin-bottom:0; font-size:14px; font-weight:600; color:#6c757d; }
.dataTables_length select {
    appearance:none !important; -webkit-appearance:none !important;
    background-color:#fff !important; color:#495057 !important;
    border:1px solid #ced4da !important; border-radius:6px !important;
    padding:4px 30px 4px 10px !important; height:36px !important; min-width:72px !important;
    font-size:14px !important; font-weight:600 !important; cursor:pointer; box-shadow:none !important;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M1.5 5.5l6 6 6-6'/%3E%3C/svg%3E") !important;
    background-repeat:no-repeat !important; background-position:right 10px center !important; background-size:12px !important;
}

/* PAGINATION — amber */
.dataTables_paginate .paginate_button { border-radius:6px !important; margin:0 2px; transition:all .2s; }
.dataTables_paginate .paginate_button.current,
.dataTables_paginate .paginate_button.current:hover,
.dataTables_wrapper .pagination .page-item.active .page-link {
    background:#F5A623 !important; background-image:none !important;
    border-color:#C97E0A !important; color:#0D1B2A !important; font-weight:700 !important;
}
.dataTables_wrapper .pagination .page-link { color:#F5A623 !important; }
.dataTables_wrapper .pagination .page-item:not(.active) .page-link:hover {
    background:#FFF3DC !important; border-color:#F5A623 !important; color:#C97E0A !important;
}
.dataTables_wrapper .pagination .page-link:focus {
    box-shadow:0 0 0 3px rgba(245,166,35,0.30) !important; outline:none !important;
}
.dataTables_wrapper .pagination .page-item.disabled .page-link { color:#C0C8D0 !important; }

/* MODAL TABS */
#detailTabs .nav-link {
    color:#6B7280;
    border:none;
    border-bottom:2px solid transparent;
    border-radius:0;
    padding:8px 16px;
    transition:color .15s, border-color .15s;
}
#detailTabs .nav-link:hover { color:#F5A623; }
#detailTabs .nav-link.active {
    color:#F5A623 !important;
    border-bottom-color:#F5A623 !important;
    background:transparent !important;
    font-weight:700;
}

/* MOBILE */
@media (max-width: 768px) {
    .content-header .row { flex-direction:column; }
    .breadcrumb { float:left !important; margin-top:10px; }
    .card-header { flex-direction:column; gap:10px; align-items:stretch !important; }
    .card-header .btn { width:100%; }
    .custom-toolbar-left { flex-direction:column; align-items:stretch; width:100%; gap:10px; }
    .toolbar-row { width:100%; align-items:flex-start; }
    .toolbar-input { flex:1; width:100%; min-width:0; }
    #customSearch input { width:100% !important; }
    .ts-wrapper,.ts-control { width:100% !important; min-width:0 !important; }
    .ts-control > input { min-width:0 !important; width:100% !important; }
    .dataTables_wrapper .row:last-child { display:flex; flex-direction:column; align-items:flex-start !important; gap:12px; }
    .dataTables_wrapper .row:last-child .col-md-6 { width:100%; max-width:100%; display:flex; justify-content:flex-start !important; }
    .dataTables_paginate { width:100%; overflow-x:auto; }
    .dataTables_length { margin-left:0; }
    /* Modal full width mobile */
    #modalDetail .col-md-3 { min-height:auto !important; }
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

    let table = $('#customerTable').DataTable({
        responsive: { details: { type: 'inline' } },
        autoWidth: false, pageLength: 10, lengthChange: true,
        searching: true, ordering: true, orderCellsTop: true,
        columnDefs: [
            { responsivePriority: 1,   targets: [0, 1, -1] },
            { responsivePriority: 100, targets: [2, 3, 4]  }
        ],
        dom:
            'rt' +
            '<"row mt-3 align-items-center"' +
                '<"col-md-6 col-12 mb-2 mb-md-0"i>' +
                '<"col-md-6 col-12 d-flex flex-wrap justify-content-between justify-content-md-end align-items-center gap-2"lp>' +
            '>',
        language: {
            search: '', lengthMenu: 'Page Size _MENU_',
            zeroRecords: 'Data tidak ditemukan',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            paginate: { previous: 'Previous', next: 'Next' }
        }
    });

    $('#customerTable_filter').hide();

    $('#customSearch').html(`
        <input type="search" id="globalSearch" class="form-control form-control-sm" placeholder="Cari data...">
    `);
    $('#globalSearch').on('keyup', function () { table.search(this.value).draw(); });

    new TomSelect('#advancedFilter', {
        plugins: ['remove_button'], persist: false, create: false, placeholder: 'Filter data...'
    });

    $.fn.dataTable.ext.search.push(function(settings, data) {
        let filters = $('#advancedFilter').val();
        if (!filters || filters.length === 0) return true;
        let type   = data[3].replace(/<[^>]*>/g, '').trim();
        let status = data[4].replace(/<[^>]*>/g, '').trim();
        let selectedTypes = [], selectedStatuses = [];
        filters.forEach(function(f) {
            let p = f.split(':');
            if (p[0] === 'TYPE')   selectedTypes.push(p[1]);
            if (p[0] === 'STATUS') selectedStatuses.push(p[1]);
        });
        if (selectedTypes.length > 0    && !selectedTypes.includes(type))     return false;
        if (selectedStatuses.length > 0 && !selectedStatuses.includes(status)) return false;
        return true;
    });

    $('#advancedFilter').on('change', function () { table.draw(); });

    $('#refreshTable').on('click', function () {
        $('#advancedFilter')[0].tomselect.clear();
        $('#globalSearch').val('');
        table.search('').page('first').draw();
        location.reload();
    });

    $('.delete-form').submit(function (e) {
        e.preventDefault();
        let form = this;
        Swal.fire({
            title: 'Hapus Customer?', text: 'Data yang dihapus tidak dapat dikembalikan.',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#d33', cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
        }).then(r => { if (r.isConfirmed) form.submit(); });
    });

    /*
    |--------------------------------------------------------------------------
    | MODAL DETAIL — fetch JSON saat tombol mata diklik
    |--------------------------------------------------------------------------
    */

    $(document).on('click', '.btn-detail', function () {
        let id = $(this).data('id');

        // Reset
        $('#detailLoading').show();
        $('#detailContent').hide();
        $('#modalDetailTitle').text('Detail Customer');
        $('#modalDetailSubtitle').text('');
        $('#modalDetail').modal('show');

        // Fetch
        $.get('{{ url("/customer") }}/' + id + '/detail-json', function (d) {

            /* ── LEFT PANEL ── */
            $('#detailName').text(d.name);
            $('#modalDetailTitle').text(d.name);
            $('#modalDetailSubtitle').text('ID #' + d.id + ' · ' + (d.customer_type ?? '-'));

            let statusHtml = d.status === 'ACTIVE'
                ? '<span class="badge badge-success px-3 py-1">ACTIVE</span>'
                : '<span class="badge badge-danger px-3 py-1">INACTIVE</span>';
            $('#detailStatusBadge').html(statusHtml);

            $('#detailTotalPO').text(d.total_po);

            $('#detailTotalPOAmount').text(
                'Rp ' + numberFormat(d.total_po_amount)
            );

            $('#detailCreatedAt').text(d.created_at);

            let editUrl = '{{ url("/customer") }}/' + id + '/edit';
            $('#detailEditBtn').attr('href', editUrl);
            $('#detailEditBtnFooter').attr('href', editUrl);

            /* ── TAB INFO ── */
            $('#infoName').text(d.name);
            $('#infoAddress').text(d.address || '-');
            $('#infoNpwp').text(d.npwp || '-');
            $('#infoDoneBy').html(d.customer_type
                ? '<span class="badge badge-info px-2 py-1">' + d.customer_type + '</span>'
                : '-');
            $('#infoStatus').html(statusHtml);

            /* ── TAB PIC ── */
            $('#picCount').text(d.pics.length);
            let picHtml = '';
            if (d.pics.length) {
                d.pics.forEach(function(p, i) {
                    picHtml += `<tr>
                        <td class="text-center">${i+1}</td>
                        <td>${p.pic || '-'}</td>
                        <td>${p.position || '-'}</td>
                        <td>${p.phone || '-'}</td>
                        <td>${p.email || '-'}</td>
                    </tr>`;
                });
            } else {
                picHtml = '<tr><td colspan="5" class="text-center text-muted py-3"><i class="fas fa-user-tie d-block mb-1"></i>Belum ada PIC</td></tr>';
            }
            $('#picBody').html(picHtml);

            /* ── TAB LAMPIRAN ── */
            $('#attCount').text(d.attachments.length);
            let attHtml = '';
            if (d.attachments.length) {
                d.attachments.forEach(function(a, i) {
                    attHtml += `<tr>
                        <td class="text-center">${i+1}</td>
                        <td>${a.title || '-'}</td>
                        <td>${a.file_name || '-'}</td>
                        <td class="text-center">
                            <a href="/storage/${a.file_path}" target="_blank" class="btn btn-info btn-sm">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>`;
                });
            } else {
                attHtml = '<tr><td colspan="4" class="text-center text-muted py-3"><i class="fas fa-paperclip d-block mb-1"></i>Belum ada lampiran</td></tr>';
            }
            $('#attBody').html(attHtml);

            /* ── TAB PO ── */
            $('#poCount').text(d.projects.length);
            let poHtml = '';
            if (d.projects.length) {
                d.projects.forEach(function(p, i) {
                    let stBadge = poStatusBadge(p.status);
                    poHtml += `<tr>
                        <td class="text-center">${i+1}</td>
                        <td>${p.name}</td>
                        <td><strong class="text-primary">Rp ${numberFormat(p.value)}</strong></td>
                        <td>${stBadge}</td>
                    </tr>`;
                });
            } else {
                poHtml = '<tr><td colspan="5" class="text-center text-muted py-3"><i class="fas fa-folder-open d-block mb-1"></i>Belum ada PO</td></tr>';
            }
            $('#poBody').html(poHtml);

            // Aktifkan tab pertama
            $('#detailTabs a:first').tab('show');

            $('#detailLoading').hide();
            $('#detailContent').show();

        }).fail(function (xhr) {
            var serverMsg = (xhr.responseJSON && xhr.responseJSON.message)
                ? xhr.responseJSON.message
                : 'Gagal memuat data. Silakan coba lagi.';
            $('#detailLoading').html(
                '<p class="text-danger py-4"><i class="fas fa-exclamation-triangle mr-1"></i>' +
                $('<div>').text(serverMsg).html() +
                '</p>'
            );
        });
    });

    // Reset modal saat ditutup
    $('#modalDetail').on('hidden.bs.modal', function () {
        $('#detailContent').hide();
        $('#detailLoading').show();
    });

    /*
    |--------------------------------------------------------------------------
    | HELPER
    |--------------------------------------------------------------------------
    */

    function numberFormat(n) {
        return parseInt(n || 0).toLocaleString('id-ID');
    }

    // Badge status PO sesuai workflow lengkap (selaras Project model)
    function poStatusBadge(status) {
        var map = {
            'DRAFT':         { color: 'secondary', label: 'Draft' },
            'SUBMITTED':     { color: 'warning',   label: 'Submitted' },
            'WAITING_PO':    { color: 'info',      label: 'Waiting PO' },
            'ACTIVE':        { color: 'success',   label: 'Active' },
            'PROGRESS_DONE': { color: 'primary',   label: 'Progress Done' },
            'BILLED':        { color: 'info',      label: 'Billed' },
            'PAID':          { color: 'success',   label: 'Paid' },
            'CLOSING':       { color: 'warning',   label: 'Closing' },
            'CLOSED':        { color: 'dark',      label: 'Closed' }
        };
        var s = map[status] || { color: 'secondary', label: (status || '-') };
        return '<span class="badge badge-' + s.color + '">' + s.label + '</span>';
    }

});

</script>
@endpush