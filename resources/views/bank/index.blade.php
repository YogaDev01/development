@extends('layouts.app')

@section('content')

<!-- MAIN CONTENT -->
<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ url('/beneficiaries') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Bank Master</h1>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Master</a></li>
                <li class="breadcrumb-item active">Bank</li>
            </ol>
        </div>

        <x-stat-cards :cards="[
            ['Total Bank', $summary['total'], 'primary', 'fas fa-university'],
            ['Punya Default Method', $summary['with_method'], 'info', 'fas fa-wallet'],
            ['Sudah Dipakai Beneficiary', $summary['in_use'], 'success', 'fas fa-link'],
        ]" />

        <div class="card card-primary card-outline shadow-sm">

            <!-- CARD HEADER -->
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-university mr-1"></i> Daftar Bank</h3>
                <div class="ml-auto d-flex" style="gap:8px;">
                    <button id="refreshTable" class="btn btn-secondary btn-sm" type="button">
                        <i class="fas fa-sync-alt mr-1"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- CARD BODY -->
            <div class="card-body">

                <!-- FILTER BAR -->
                <div class="custom-toolbar mb-3 d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
                    <div class="custom-toolbar-left d-flex align-items-center flex-wrap" style="gap:8px;">
                        <div class="toolbar-row">
                            <span class="toolbar-label">Cari :</span>
                            <div class="toolbar-input" id="customSearch"></div>
                        </div>
                        <button aria-label="Clear Filter" type="button" id="btnClearFilterBank" class="btn btn-outline-secondary btn-sm" title="Clear Filter">
                            <i class="fas fa-eraser"></i>
                        </button>
                        <div id="bankRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>
                    </div>
                    <div class="custom-toolbar-right d-flex flex-wrap" style="gap:8px;">
                        <button type="button" class="btn btn-success btn-sm" id="btnTambah">
                            <i class="fas fa-plus mr-1"></i> Tambah Bank
                        </button>
                    </div>
                </div>

                <!-- TABLE -->
                <div class="table-responsive">
                    <table id="bankTable" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" width="6%"  class="all">No</th>
                                <th scope="col" width="20%" class="all">Kode</th>
                                <th scope="col" class="all">Nama Bank</th>
                                <th scope="col" width="18%" class="all">Default Method</th>
                                <th scope="col" width="1%" class="text-center all action-column" style="white-space:nowrap;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($banks as $bank)
                            <tr data-defaultmethod="{{ $bank->default_method ?? '' }}">
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $bank->code ?? '-' }}</td>
                                <td><strong>{{ $bank->name }}</strong></td>
                                <td>
                                    @if($bank->default_method)
                                        <span class="badge badge-info">{{ $bank->default_method }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-center action-column">
                                    <div class="action-buttons ts-actions">

                                        {{-- AKSI UTAMA — Edit. Master data bukan alur kerja
                                             persetujuan, jadi tombolnya netral: merah disimpan
                                             untuk aksi yang benar-benar menentukan. --}}
                                        <button type="button"
                                                class="ts-action-primary is-neutral btn-edit"
                                                data-id="{{ $bank->id }}"
                                                data-name="{{ $bank->name }}"
                                                data-code="{{ $bank->code }}"
                                                data-method="{{ $bank->default_method }}"
                                                title="Edit" aria-label="Edit {{ $bank->name }}">
                                            <i class="fas fa-pen" aria-hidden="true"></i><span class="ts-al">Edit</span>
                                        </button>

                                        <button type="button" class="ts-action-more"
                                                aria-haspopup="true" aria-expanded="false"
                                                title="Aksi lain" aria-label="Aksi lain untuk {{ $bank->name }}">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>

                                        <div class="ts-action-menu dropdown-menu" hidden>

                                            <a href="{{ url('/bank/' . $bank->id) }}" class="dropdown-item">
                                                <i class="fas fa-eye"></i>Detail
                                            </a>

                                            <div class="dropdown-divider"></div>

                                            <form action="{{ url('/bank/' . $bank->id) }}"
                                                  method="POST" class="delete-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item is-danger">
                                                    <i class="fas fa-trash"></i>Hapus
                                                </button>
                                            </form>

                                        </div>

                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5">
                                    <x-empty title="Belum ada data bank" icon="fas fa-folder-open" compact />
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
<!-- MODAL TAMBAH BANK                                                 -->
<!-- ================================================================ -->
<div class="modal fade" id="modalTambah" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg); overflow:hidden;">

            <div class="modal-header" style="background:var(--charcoal); border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-university mr-2" style="color:var(--accent);"></i>
                    Tambah Bank
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <form action="{{ url('/bank') }}" method="POST" id="formTambah">
                @csrf

                <div class="modal-body" style="padding:24px 28px;">
                    <div class="form-group">
                        <label class="font-weight-600">
                            Nama Bank <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="name" id="tambahName"
                               class="form-control"
                               placeholder="Contoh: BCA, Mandiri, BNI"
                               style="text-transform:uppercase;"
                               autocomplete="off" required>
                        <small class="text-muted mt-1 d-block">Nama harus unik</small>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-600">Kode Bank</label>
                        <input type="text" name="code" id="tambahCode"
                               class="form-control"
                               placeholder="Contoh: 014, 008"
                               style="text-transform:uppercase;"
                               autocomplete="off">
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Default Method</label>
                        <select name="default_method" id="tambahMethod" class="form-control">
                            <option value="">— Pilih Method —</option>
                            @foreach(\App\Models\Bank::METHODS as $m)
                                <option value="{{ $m }}">{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="modal-footer" style="background:var(--surface-2); border-top:1px solid var(--border);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save mr-1"></i> Simpan
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- MODAL EDIT BANK                                                   -->
<!-- ================================================================ -->
<div class="modal fade" id="modalEdit" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg); overflow:hidden;">

            <div class="modal-header" style="background:var(--charcoal); border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-pen mr-2" style="color:var(--accent);"></i>
                    Edit Bank
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <form id="formEdit" method="POST">
                @csrf
                @method('PUT')

                <div class="modal-body" style="padding:24px 28px;">
                    <div class="form-group">
                        <label class="font-weight-600">
                            Nama Bank <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="name" id="editName"
                               class="form-control"
                               style="text-transform:uppercase;"
                               autocomplete="off" required>
                        <small class="text-muted mt-1 d-block">Nama harus unik</small>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-600">Kode Bank</label>
                        <input type="text" name="code" id="editCode"
                               class="form-control"
                               style="text-transform:uppercase;"
                               autocomplete="off">
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Default Method</label>
                        <select name="default_method" id="editMethod" class="form-control">
                            <option value="">— Pilih Method —</option>
                            @foreach(\App\Models\Bank::METHODS as $m)
                                <option value="{{ $m }}">{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="modal-footer" style="background:var(--surface-2); border-top:1px solid var(--border);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-warning">
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

.card-header .btn  { border-radius:var(--radius-sm); font-weight:600; }
.font-weight-600   { font-weight:600; }

/* TOOLBAR */
.custom-toolbar      { display:flex; justify-content:space-between; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; margin-bottom:0; white-space:nowrap; font-weight:600; color:var(--text-dim); }
.toolbar-input       { min-width:180px; }
#customSearch        { width:100%; }
#customSearch input  { width:180px; border-radius:var(--radius-sm); }

/* TABLE */
#bankTable          { width:100% !important; }
.table td,.table th { vertical-align:middle !important; }
.action-column      { white-space:nowrap !important; min-width:100px !important; }
.action-buttons     { display:flex; align-items:center; justify-content:center; flex-wrap:nowrap; gap:4px; }
.action-buttons .btn { flex-shrink:0; }

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
    background:var(--accent) !important; background-image:none !important;
    border-color:var(--accent-dark) !important; color:#fff !important; font-weight:700 !important;
}
.dataTables_wrapper .pagination .page-link { color:var(--accent) !important; }
.dataTables_wrapper .pagination .page-item:not(.active) .page-link:hover {
    background:var(--warning-bg) !important; border-color:var(--accent) !important; color:var(--accent-dark) !important;
}
.dataTables_wrapper .pagination .page-link:focus {
    box-shadow:0 0 0 3px rgba(200,39,46,0.30) !important; outline:none !important;
}
.dataTables_wrapper .pagination .page-item.disabled .page-link { color:var(--text-faint) !important; }

/* MOBILE */
@media (max-width: 768px) {
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
    #btnClearFilterBank { width:100%; }
    #bankRowFilters { width:100%; }
    #bankRowFilters .tcf-row-btn { width:100%; }
    .toolbar-row { width:100%; align-items:flex-start; }
    .toolbar-input { flex:1; width:100%; min-width:0; }
    #customSearch input { width:100% !important; }
    .dataTables_wrapper .row:last-child { display:flex; flex-direction:column; align-items:flex-start !important; gap:12px; }
    .dataTables_wrapper .row:last-child .col-md-6 { width:100%; max-width:100%; display:flex; justify-content:flex-start !important; }
    .dataTables_paginate { width:100%; overflow-x:auto; }
    .dataTables_length { margin-left:0; }
    /* Modal full-width on mobile */
    .modal-dialog.modal-sm { max-width:92%; margin:1rem auto; }
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

    let table = $('#bankTable').DataTable({
        responsive: true,
        autoWidth: false, pageLength: 10, lengthChange: true,
        searching: true, ordering: true, orderCellsTop: true,
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

    TesysColumnFilter.initRowFilters('#bankTable', '#bankRowFilters', [
        { key: 'defaultmethod', label: 'Default Method' },
    ]);

    $('#btnClearFilterBank').on('click', function () {
        TesysColumnFilter.clearAll('#bankTable');
    });

    $('#bankTable_filter').hide();

    $('#customSearch').html(`
        <input type="search" id="globalSearch" class="form-control form-control-sm" placeholder="Cari data...">
    `);
    $('#globalSearch').on('keyup', function () { table.search(this.value).draw(); });

    $('#refreshTable').on('click', function () {
        $('#globalSearch').val('');
        table.search('').page('first').draw();
        location.reload();
    });

    $('.delete-form').submit(function (e) {
        e.preventDefault();
        let form = this;
        Swal.fire({
            title: 'Hapus Bank?',
            text: 'Pastikan bank ini tidak digunakan oleh beneficiary manapun.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#9a3030',
            cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(r => { if (r.isConfirmed) form.submit(); });
    });

    /*
    |--------------------------------------------------------------------------
    | MODAL TAMBAH
    |--------------------------------------------------------------------------
    */

    $('#btnTambah').on('click', function () {
        $('#formTambah')[0].reset();
        $('#modalTambah').modal('show');
        $('#modalTambah').one('shown.bs.modal', function () {
            $('#tambahName').focus();
        });
    });

    /*
    |--------------------------------------------------------------------------
    | MODAL EDIT
    | Data sudah ada di data-* attribute tombol — tidak perlu AJAX
    |--------------------------------------------------------------------------
    */

    $(document).on('click', '.btn-edit', function () {
        let id     = $(this).data('id');
        let name   = $(this).data('name');
        let code   = $(this).data('code') || '';
        let method = $(this).data('method') || '';

        $('#editName').val(name);
        $('#editCode').val(code);
        $('#editMethod').val(method);
        $('#formEdit').attr('action', '{{ url("/bank") }}/' + id);
        $('#modalEdit').modal('show');
        $('#modalEdit').one('shown.bs.modal', function () {
            $('#editName').focus().select();
        });
    });

});

</script>
@endpush