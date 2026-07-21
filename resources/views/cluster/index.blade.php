@extends('layouts.app')

@section('title', 'Project')

@section('content')

<!-- CONTENT HEADER -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold">Project Master</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="#">Master</a></li>
                    <li class="breadcrumb-item active">Project</li>
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

                <div class="ml-auto action-header-buttons">
                    <a href="{{ url('/groups') }}" class="btn btn-info btn-sm">
                        <i class="fas fa-layer-group mr-1"></i> Group Master
                    </a>
                    <button type="button" class="btn btn-success btn-sm" id="btnTambahProject">
                        <i class="fas fa-plus mr-1"></i> Tambah Project
                    </button>
                </div>

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
                                    <option value="STATUS:ACTIVE">Status : ACTIVE</option>
                                    <option value="STATUS:INACTIVE">Status : INACTIVE</option>
                                </select>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- TABLE -->
                <div class="table-responsive">
                    <table id="clusterTable" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th width="5%"  class="all">No</th>
                                <th width="10%">Code</th>
                                <th width="25%" class="all">Nama Project</th>
                                <th width="20%">Business Group</th>
                                <th width="10%">Input By</th>
                                <th width="10%">Status</th>
                                <th width="10%" class="text-center all action-column">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($clusters as $cluster)
                            <tr>
                                <td>{{ $loop->iteration }}</td>

                                <td>
                                    <span class="badge badge-dark px-2 py-1">{{ $cluster->code }}</span>
                                </td>

                                <td>{{ $cluster->name }}</td>

                                <td>
                                    <span class="badge badge-secondary px-2 py-1">
                                        {{ $cluster->group->name ?? '-' }}
                                    </span>
                                </td>

                                <td>{{ $cluster->user->name ?? '-' }}</td>

                                <td>
                                    @if($cluster->status == 'ACTIVE')
                                        <span class="badge badge-success px-2 py-1">ACTIVE</span>
                                    @else
                                        <span class="badge badge-danger px-2 py-1">INACTIVE</span>
                                    @endif
                                </td>

                                <td class="text-center action-column">
                                    <div class="action-buttons">

                                        <button
                                            type="button"
                                            class="btn btn-warning btn-sm btn-edit"
                                            data-id="{{ $cluster->id }}"
                                            title="Edit"
                                        >
                                            <i class="fas fa-pen"></i>
                                        </button>

                                        <form
                                            action="{{ url('/cluster/' . $cluster->id) }}"
                                            method="POST"
                                            style="display:inline-block;"
                                            class="delete-form"
                                        >
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
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-folder-open d-block mb-2"></i>
                                    Belum ada data project
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

<!-- ============================================================ -->
<!-- MODAL TAMBAH PROJECT                                          -->
<!-- ============================================================ -->
<div class="modal fade" id="modalTambah" tabindex="-1" role="dialog" aria-labelledby="modalTambahLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:12px; overflow:hidden;">

            <div class="modal-header" style="background:#0D1B2A; border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white" id="modalTambahLabel">
                    <i class="fas fa-layer-group mr-2" style="color:#F5A623;"></i>
                    Tambah Project
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <form action="{{ url('/cluster') }}" method="POST" id="formTambah">
                @csrf

                <div class="modal-body" style="padding:28px 32px;">

                    <div class="row">

                        <!-- KODE AUTO -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="font-weight-600">
                                    Kode Project
                                    <span class="badge badge-secondary ml-1" style="font-size:10px;">Auto</span>
                                </label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" style="background:#f4f6f9;">
                                            <i class="fas fa-hashtag text-muted" style="font-size:12px;"></i>
                                        </span>
                                    </div>
                                    <input
                                        type="text"
                                        id="displayNextCode"
                                        class="form-control font-weight-bold"
                                        readonly
                                        style="background:#f4f6f9; letter-spacing:0.1em; font-size:15px; cursor:not-allowed; color:#495057;"
                                        value="..."
                                    >
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Digenerate otomatis oleh sistem
                                </small>
                            </div>
                        </div>

                        <!-- NAMA PROJECT -->
                        <div class="col-md-9">
                            <div class="form-group">
                                <label class="font-weight-600">
                                    Nama Project <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="text"
                                    name="name"
                                    id="tambahName"
                                    class="form-control"
                                    placeholder="Masukkan nama project"
                                    style="text-transform:uppercase;"
                                    autocomplete="off"
                                    required
                                >
                            </div>
                        </div>

                        <!-- BUSINESS GROUP -->
                        <div class="col-md-8">
                            <div class="form-group mb-0">
                                <label class="font-weight-600">Business Group</label>
                                <select name="group_id" id="tambahGroup" class="form-control">
                                    <option value="">— Pilih Business Group —</option>
                                    @foreach($groups as $group)
                                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                    </div>

                    <!-- STATUS hidden -->
                    <input type="hidden" name="status" value="ACTIVE">

                </div>

                <div class="modal-footer" style="background:#f8f9fa; border-top:1px solid #e9ecef;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-success" id="btnSimpanTambah">
                        <i class="fas fa-save mr-1"></i> Simpan
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL EDIT PROJECT                                            -->
<!-- ============================================================ -->
<div class="modal fade" id="modalEdit" tabindex="-1" role="dialog" aria-labelledby="modalEditLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:12px; overflow:hidden;">

            <div class="modal-header" style="background:#0D1B2A; border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white" id="modalEditLabel">
                    <i class="fas fa-pen mr-2" style="color:#F5A623;"></i>
                    Edit Project
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <form id="formEdit" method="POST">
                @csrf
                @method('PUT')

                <div class="modal-body" style="padding:28px 32px;">

                    <!-- Loading state -->
                    <div id="editLoading" class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                        <p class="text-muted mt-2 mb-0">Memuat data...</p>
                    </div>

                    <div id="editFields" style="display:none;">

                        <div class="row">

                            <!-- KODE (readonly) -->
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="font-weight-600">
                                        Kode Project
                                        <span class="badge badge-secondary ml-1" style="font-size:10px;">Fixed</span>
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text" style="background:#f4f6f9;">
                                                <i class="fas fa-hashtag text-muted" style="font-size:12px;"></i>
                                            </span>
                                        </div>
                                        <input
                                            type="text"
                                            id="editCode"
                                            class="form-control font-weight-bold"
                                            readonly
                                            style="background:#f4f6f9; letter-spacing:0.1em; font-size:15px; cursor:not-allowed; color:#495057;"
                                        >
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-lock mr-1"></i>
                                        Kode tidak dapat diubah
                                    </small>
                                </div>
                            </div>

                            <!-- NAMA PROJECT -->
                            <div class="col-md-9">
                                <div class="form-group">
                                    <label class="font-weight-600">
                                        Nama Project <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        name="name"
                                        id="editName"
                                        class="form-control"
                                        placeholder="Masukkan nama project"
                                        style="text-transform:uppercase;"
                                        autocomplete="off"
                                        required
                                    >
                                </div>
                            </div>

                            <!-- BUSINESS GROUP -->
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label class="font-weight-600">Business Group</label>
                                    <select name="group_id" id="editGroup" class="form-control">
                                        <option value="">— Pilih Business Group —</option>
                                        @foreach($groups as $group)
                                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- STATUS -->
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="font-weight-600">Status <span class="text-danger">*</span></label>
                                    <select name="status" id="editStatus" class="form-control" required>
                                        <option value="ACTIVE">ACTIVE</option>
                                        <option value="INACTIVE">INACTIVE</option>
                                    </select>
                                </div>
                            </div>

                        </div>

                    </div>

                </div>

                <div class="modal-footer" style="background:#f8f9fa; border-top:1px solid #e9ecef;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-warning" id="btnSimpanEdit" style="display:none;">
                        <i class="fas fa-save mr-1"></i> Update
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

@endsection

@push('styles')
<style>

.action-header-buttons { display:flex; gap:8px; }
.card-header .btn      { border-radius:6px; font-weight:600; }

/* TOOLBAR */
.custom-toolbar       { display:flex; justify-content:space-between; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left  { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar-row          { display:flex; align-items:center; gap:8px; }
.toolbar-label        { min-width:50px; margin-bottom:0; white-space:nowrap; font-weight:600; color:#495057; }
.toolbar-input        { min-width:180px; }

/* SEARCH */
#customSearch       { width:100%; }
#customSearch input { width:180px; border-radius:6px; }

/* TOMSELECT */
.ts-wrapper  { min-width:260px; width:100%; }
.ts-control  { border-radius:6px !important; border:1px solid #ced4da !important; min-height:38px !important; padding:6px 10px !important; box-shadow:none !important; }
.ts-control.focus { border-color:#80bdff !important; box-shadow:0 0 0 0.15rem rgba(0,123,255,.15) !important; }

/* TABLE */
#clusterTable     { width:100% !important; }
.table td,.table th { vertical-align:middle !important; }

/* ACTION */
.action-column    { white-space:nowrap !important; min-width:80px !important; }
.action-buttons   { display:flex; align-items:center; justify-content:center; flex-wrap:nowrap; gap:4px; }
.action-buttons .btn { flex-shrink:0; }

/* RESPONSIVE ICON */
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
    background-color:#ffffff !important; color:#495057 !important;
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
    background: #F5A623 !important;
    background-image: none !important;
    border-color: #C97E0A !important;
    color: #0D1B2A !important;
    font-weight: 700 !important;
}
.dataTables_wrapper .pagination .page-link { color:#F5A623 !important; }
.dataTables_wrapper .pagination .page-item:not(.active) .page-link:hover {
    background:#FFF3DC !important; border-color:#F5A623 !important; color:#C97E0A !important;
}
.dataTables_wrapper .pagination .page-link:focus {
    box-shadow:0 0 0 3px rgba(245,166,35,0.30) !important; outline:none !important;
}
.dataTables_wrapper .pagination .page-item.disabled .page-link { color:#C0C8D0 !important; }

/* MODAL */
.modal-content { box-shadow:0 20px 60px rgba(0,0,0,0.25); }
.font-weight-600 { font-weight:600; }

/* MOBILE */
@media (max-width: 768px) {
    .action-header-buttons { width:100%; flex-direction:column; gap:10px; }
    .action-header-buttons .btn { width:100%; }
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
    .modal-body { padding:20px !important; }
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

    let table = $('#clusterTable').DataTable({
        responsive: { details: { type: 'inline' } },
        autoWidth:    false,
        pageLength:   10,
        lengthChange: true,
        searching:    true,
        ordering:     true,
        orderCellsTop: true,
        columnDefs: [
            { responsivePriority: 1,   targets: [0, 2, -1] },
            { responsivePriority: 100, targets: [1, 3, 4, 5] }
        ],
        dom:
            'rt' +
            '<"row mt-3 align-items-center"' +
                '<"col-md-6 col-12 mb-2 mb-md-0"i>' +
                '<"col-md-6 col-12 d-flex flex-wrap justify-content-between justify-content-md-end align-items-center gap-2"lp>' +
            '>',
        language: {
            search:      '',
            lengthMenu:  'Page Size _MENU_',
            zeroRecords: 'Data tidak ditemukan',
            info:        'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty:   'Tidak ada data',
            paginate:    { previous: 'Previous', next: 'Next' }
        }
    });

    $('#clusterTable_filter').hide();

    $('#customSearch').html(`
        <input type="search" id="globalSearch" class="form-control form-control-sm" placeholder="Cari data...">
    `);
    $('#globalSearch').on('keyup', function () { table.search(this.value).draw(); });

    new TomSelect('#advancedFilter', { plugins:['remove_button'], persist:false, create:false, placeholder:'Filter data...' });

    $.fn.dataTable.ext.search.push(function(settings, data) {
        let filters = $('#advancedFilter').val();
        if (!filters || filters.length === 0) return true;
        let status = data[5].replace(/<[^>]*>/g, '').trim();
        let selectedStatuses = [];
        filters.forEach(function(f) {
            let p = f.split(':');
            if (p[0] === 'STATUS') selectedStatuses.push(p.slice(1).join(':'));
        });
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
            title: 'Hapus Project?',
            text:  'Data yang dihapus tidak dapat dikembalikan.',
            icon:  'warning',
            showCancelButton:   true,
            confirmButtonColor: '#d33',
            cancelButtonColor:  '#6c757d',
            confirmButtonText:  'Ya, Hapus',
            cancelButtonText:   'Batal'
        }).then(r => { if (r.isConfirmed) form.submit(); });
    });

    /*
    |--------------------------------------------------------------------------
    | MODAL TAMBAH — fetch next code saat modal dibuka
    |--------------------------------------------------------------------------
    */

    $('#btnTambahProject').on('click', function () {
        // Reset form
        $('#formTambah')[0].reset();
        $('#displayNextCode').val('...');
        $('#tambahName').val('');
        $('#tambahGroup').val('');

        // Fetch next code
        $.get('{{ url("/cluster/next-code") }}', function (res) {
            $('#displayNextCode').val(res.code);
        }).fail(function () {
            $('#displayNextCode').val('ERROR');
        });

        $('#modalTambah').modal('show');
    });

    // Refetch code jika modal dibuka lagi (cegah stale code)
    $('#modalTambah').on('show.bs.modal', function () {
        $.get('{{ url("/cluster/next-code") }}', function (res) {
            $('#displayNextCode').val(res.code);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | MODAL EDIT — fetch data cluster saat tombol edit diklik
    |--------------------------------------------------------------------------
    */

    $(document).on('click', '.btn-edit', function () {
        let id = $(this).data('id');

        // Reset ke loading state
        $('#editLoading').show();
        $('#editFields').hide();
        $('#btnSimpanEdit').hide();
        $('#modalEdit').modal('show');

        // Set form action
        $('#formEdit').attr('action', '{{ url("/cluster") }}/' + id);

        // Fetch data
        $.get('{{ url("/cluster") }}/' + id + '/edit-data', function (res) {
            $('#editCode').val(res.code);
            $('#editName').val(res.name);
            $('#editGroup').val(res.group_id ?? '');
            $('#editStatus').val(res.status);

            $('#editLoading').hide();
            $('#editFields').show();
            $('#btnSimpanEdit').show();
        }).fail(function () {
            $('#editLoading').html('<p class="text-danger"><i class="fas fa-exclamation-triangle mr-1"></i>Gagal memuat data. Silakan coba lagi.</p>');
        });
    });

});

</script>
@endpush