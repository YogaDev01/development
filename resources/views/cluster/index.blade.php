@extends('layouts.app')

@section('title', 'Project')

@section('content')

<!-- MAIN CONTENT -->
<div class="content">
    <div class="container-fluid">

        <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        {{-- Judul & breadcrumb topbar: shell memindahkan <h1> dan .breadcrumb pertama ke topbar
             (tesys-shell.js hoistPageTitle); disembunyikan secara visual. --}}
        <div class="tw:sr-only">
            <h1>Project Master</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Master</li>
                <li class="breadcrumb-item active">Project</li>
            </ol>
        </div>

        <x-tw.page-header title="Daftar Project"
            description="Project beserta business group, penginput, dan statusnya.">
            <x-slot:actions>
                <x-tw.button id="btnTambahProject" class="ts-add-btn tw:max-sm:min-h-12"><i class="fas fa-plus" aria-hidden="true"></i> Tambah Project</x-tw.button>
            </x-slot:actions>
        </x-tw.page-header>

        {{-- Mobile: tombol di header disembunyikan dan diganti tombol melayang (tesys-ui-refine.css). --}}
        <button type="button" class="ts-fab" data-trigger="#btnTambahProject" aria-label="Tambah Project" title="Tambah Project"><i class="fas fa-plus" aria-hidden="true"></i></button>


        <x-stat-cards :cards="[
            ['Total Project', $summary['total'], 'primary', 'fas fa-layer-group'],
            ['Active', $summary['active'], 'success', 'fas fa-check-circle'],
            ['Inactive', $summary['inactive'], 'secondary', 'fas fa-pause-circle'],
        ]" />

        {{-- Kartu tunggal: toolbar + tabel + paginasi (pola Cost Request). --}}
        <x-tw.card padding="sm">


                <!-- FILTER BAR -->
                <div class="custom-toolbar mb-3 d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
                    <div class="custom-toolbar-left d-flex align-items-center flex-wrap" style="gap:8px;">
                        <div class="toolbar-row">
                            <span class="toolbar-label">Cari :</span>
                            <div class="toolbar-input" id="customSearch"></div>
                        </div>
                        <button aria-label="Clear Filter" type="button" id="btnClearFilterCluster" class="btn btn-outline-secondary btn-sm" title="Clear Filter">
                            <i class="fas fa-eraser"></i>
                        </button>
                        <div id="clusterRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>
                    </div>
                    <div class="custom-toolbar-right d-flex flex-wrap" style="gap:8px;">
                        <a href="{{ url('/groups') }}" class="btn btn-info btn-sm">
                            <i class="fas fa-layer-group mr-1"></i> Group Master
                        </a>
                        <x-tw.button id="refreshTable" variant="secondary" size="sm"><i class="fas fa-sync-alt" aria-hidden="true"></i> Refresh</x-tw.button>
                    </div>
                </div>

                <!-- TABLE -->
                <div class="table-responsive">
                    <table id="clusterTable" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" width="5%"  class="all">No</th>
                                <th scope="col" width="10%">Code</th>
                                <th scope="col" width="25%" class="all">Nama Project</th>
                                <th scope="col" width="20%">Business Group</th>
                                <th scope="col" width="10%">Input By</th>
                                <th scope="col" width="10%">Status</th>
                                <th scope="col" width="10%" class="text-center all action-column"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($clusters as $cluster)
                            <tr data-status="{{ $cluster->status }}">
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
                                    {{-- Detail dibuka lewat klik baris. Dua aksi (Edit, Hapus) = satu tombol titik tiga. --}}
                                    <div class="action-buttons ts-actions">
                                        <button type="button" class="ts-action-more"
                                                aria-haspopup="true" aria-expanded="false"
                                                title="Aksi" aria-label="Aksi">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>

                                        <div class="ts-action-menu dropdown-menu" hidden>
                                            <button type="button"
                                                class="js-row-open dropdown-item btn-edit"
                                                data-id="{{ $cluster->id }}"
                                                title="Edit" aria-label="Edit {{ $cluster->name ?? 'project' }}">
                                            <i class="fas fa-pen"></i>Edit
                                        </button>

                                            <div class="dropdown-divider"></div>

                                            <form action="{{ url('/cluster/' . $cluster->id) }}"
                                              method="POST" class="d-inline delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item is-danger"
                                                    title="Hapus" aria-label="Hapus {{ $cluster->name ?? 'project' }}">
                                                <i class="fas fa-trash"></i>Hapus
                                            </button>
                                        </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7">
                                    <x-empty title="Belum ada data project" icon="fas fa-folder-open" compact />
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
        </x-tw.card>
      </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL TAMBAH PROJECT                                          -->
<!-- ============================================================ -->
<div class="modal fade" id="modalTambah" tabindex="-1" role="dialog" aria-labelledby="modalTambahLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg); overflow:hidden;">

            <div class="modal-header" style="background:var(--charcoal); border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white" id="modalTambahLabel">
                    <i class="fas fa-layer-group mr-2" style="color:var(--accent);"></i>
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
                                        <span class="input-group-text" style="background:var(--surface-2);">
                                            <i class="fas fa-hashtag text-muted" style="font-size:12px;"></i>
                                        </span>
                                    </div>
                                    <input
                                        type="text"
                                        id="displayNextCode"
                                        class="form-control font-weight-bold"
                                        readonly
                                        style="background:var(--surface-2); letter-spacing:0.1em; font-size:15px; cursor:not-allowed; color:var(--text-dim);"
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

                <div class="modal-footer" style="background:var(--surface-2); border-top:1px solid var(--border);">
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
        <div class="modal-content" style="border-radius:var(--radius-lg); overflow:hidden;">

            <div class="modal-header" style="background:var(--charcoal); border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white" id="modalEditLabel">
                    <i class="fas fa-pen mr-2" style="color:var(--accent);"></i>
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
                                            <span class="input-group-text" style="background:var(--surface-2);">
                                                <i class="fas fa-hashtag text-muted" style="font-size:12px;"></i>
                                            </span>
                                        </div>
                                        <input
                                            type="text"
                                            id="editCode"
                                            class="form-control font-weight-bold"
                                            readonly
                                            style="background:var(--surface-2); letter-spacing:0.1em; font-size:15px; cursor:not-allowed; color:var(--text-dim);"
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

                <div class="modal-footer" style="background:var(--surface-2); border-top:1px solid var(--border);">
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
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
<style>

.action-header-buttons { display:flex; gap:8px; }
.card-header .btn      { border-radius:var(--radius-sm); font-weight:600; }

/* TOOLBAR */
.custom-toolbar       { display:flex; justify-content:space-between; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left  { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar-row          { display:flex; align-items:center; gap:8px; }
.toolbar-label        { min-width:50px; margin-bottom:0; white-space:nowrap; font-weight:600; color:var(--text-dim); }
.toolbar-input        { min-width:180px; }

/* SEARCH */
#customSearch       { width:100%; }
#customSearch input { width:180px; border-radius:var(--radius-sm); }

/* TOMSELECT */
.ts-wrapper  { min-width:260px; width:100%; }
.ts-control  { border-radius:var(--radius-sm) !important; border:1px solid var(--border) !important; min-height:38px !important; padding:6px 10px !important; box-shadow:none !important; }
.ts-control.focus { border-color:var(--border-strong) !important; box-shadow:0 0 0 0.15rem rgba(0,123,255,.15) !important; }

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
.dataTables_info { padding-top:0 !important; font-size:14px; color:var(--text-faint); }

/* PAGE SIZE */
.dataTables_length { display:flex; align-items:center; margin-left:18px; }
.dataTables_length label { display:flex; align-items:center; gap:10px; margin-bottom:0; font-size:14px; font-weight:600; color:var(--text-faint); }
.dataTables_length select {
    appearance:none !important; -webkit-appearance:none !important;
    background-color:var(--surface) !important; color:var(--text-dim) !important;
    border:1px solid var(--border) !important; border-radius:var(--radius-sm) !important;
    padding:4px 30px 4px 10px !important; height:36px !important; min-width:72px !important;
    font-size:14px !important; font-weight:600 !important; cursor:pointer; box-shadow:none !important;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M1.5 5.5l6 6 6-6'/%3E%3C/svg%3E") !important;
    background-repeat:no-repeat !important; background-position:right 10px center !important; background-size:12px !important;
}

/* PAGINATION — amber */
.dataTables_paginate .paginate_button { border-radius:var(--radius-sm) !important; margin:0 2px; transition:all .2s; }
.dataTables_paginate .paginate_button.current,
.dataTables_paginate .paginate_button.current:hover,
.dataTables_wrapper .pagination .page-item.active .page-link {
    background: var(--accent) !important;
    background-image: none !important;
    border-color: var(--accent-dark) !important;
    color:#fff !important;
    font-weight: 700 !important;
}
.dataTables_wrapper .pagination .page-link { color:var(--accent) !important; }
.dataTables_wrapper .pagination .page-item:not(.active) .page-link:hover {
    background:var(--warning-bg) !important; border-color:var(--accent) !important; color:var(--accent-dark) !important;
}
.dataTables_wrapper .pagination .page-link:focus {
    box-shadow:0 0 0 3px rgba(200,39,46,0.30) !important; outline:none !important;
}
.dataTables_wrapper .pagination .page-item.disabled .page-link { color:var(--text-faint) !important; }

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
    .card-header .card-title { margin-bottom:0; }
    .card-header .ml-auto { margin-left:0 !important; flex-wrap:wrap; }
    .custom-toolbar { flex-direction:column !important; align-items:stretch !important; }
    .custom-toolbar-left { flex-direction:column !important; align-items:stretch !important; width:100%; gap:10px; }
    .custom-toolbar-right { width:100%; }
    .custom-toolbar-right .btn { width:100%; }
    #btnClearFilterCluster { width:100%; }
    #clusterRowFilters { width:100%; }
    #clusterRowFilters .tcf-row-btn { width:100%; }
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

#clusterTable tbody tr:not(:has(td[colspan])) { cursor:pointer; }
</style>
@endpush

@push('scripts')
<script>

$(function () {

    /* Klik di mana saja pada baris membuka detail (atau edit bila tidak ada detail) — pola Cost
       Request. Kontrol di dalam baris (tombol, tautan, menu aksi, kontrol responsif) bekerja sendiri. */
    $(document).on('click', '#clusterTable tbody tr', function (e) {
        if ($(e.target).closest('a, button, input, select, textarea, label, form, .ts-actions, .ts-action-menu, td.dtr-control').length) return;
        if (window.getSelection && String(window.getSelection()).length) return;
        var href = $(this).attr('data-href');
        if (href) { window.location.href = href; return; }
        $(this).find('.js-row-open').first().trigger('click');
    });
    // Tombol melayang (HP) memicu tombol "Tambah" di header yang disembunyikan.
    $('.ts-fab[data-trigger]').on('click', function () { $($(this).data('trigger')).trigger('click'); });

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

    TesysColumnFilter.initRowFilters('#clusterTable', '#clusterRowFilters', [
        { key: 'status', label: 'Status' },
    ]);

    $('#btnClearFilterCluster').on('click', function () {
        TesysColumnFilter.clearAll('#clusterTable');
    });

    $('#refreshTable').on('click', function () {
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
            confirmButtonColor: '#9a3030',
            cancelButtonColor:  '#55595e',
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