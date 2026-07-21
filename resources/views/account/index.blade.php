@extends('layouts.app')

@section('title', 'Account Master')

@section('content')

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12 col-md-3">
                <div class="small-box bg-info">
                    <div class="inner"><h3>{{ $summary['total'] }}</h3><p>Total Account</p></div>
                    <div class="icon"><i class="fas fa-list"></i></div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="small-box bg-warning">
                    <div class="inner"><h3>{{ $summary['variable_cost'] }}</h3><p>Variable Cost</p></div>
                    <div class="icon"><i class="fas fa-chart-line"></i></div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="small-box bg-danger">
                    <div class="inner"><h3>{{ $summary['fixed_cost'] }}</h3><p>Fixed Cost</p></div>
                    <div class="icon"><i class="fas fa-anchor"></i></div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="small-box bg-secondary">
                    <div class="inner"><h3>{{ $summary['overhead'] }}</h3><p>Overhead</p></div>
                    <div class="icon"><i class="fas fa-layer-group"></i></div>
                </div>
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold">Account Master</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="#">Master</a></li>
                    <li class="breadcrumb-item active">Account</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">

        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header d-flex align-items-center">
                <button id="btnRefresh" class="btn btn-secondary btn-sm">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
                <button type="button" class="btn btn-success btn-sm ml-auto" id="btnTambahAccount">
                    <i class="fas fa-plus mr-1"></i> Tambah Account
                </button>
            </div>

            <div class="card-body">

                <div class="custom-toolbar mb-3">
                    <div class="custom-toolbar-left">
                        <div class="toolbar-row">
                            <span class="toolbar-label">Cari :</span>
                            <div class="toolbar-input" id="accountSearch"></div>
                        </div>
                        <div class="toolbar-row">
                            <span class="toolbar-label">Filter :</span>
                            <div class="toolbar-input">
                                <select id="accountFilter" multiple placeholder="Filter data...">
                                    <option value="CAT:Variable Cost">Variable Cost</option>
                                    <option value="CAT:Fixed Cost">Fixed Cost</option>
                                    <option value="CAT:Overhead">Overhead</option>
                                    <option value="STATUS:ACTIVE">Status : ACTIVE</option>
                                    <option value="STATUS:INACTIVE">Status : INACTIVE</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="tblAccount" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th width="5%"  class="all">No</th>
                                <th width="40%" class="all">Nama Account</th>
                                <th width="25%">Kategori</th>
                                <th width="15%">Status</th>
                                <th width="15%" class="text-center all">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($accounts as $account)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><strong>{{ $account->name }}</strong></td>
                                <td>
                                    @php
                                        $cc = ['Variable Cost'=>'warning','Fixed Cost'=>'danger','Overhead'=>'secondary'];
                                    @endphp
                                    <span class="badge badge-{{ $cc[$account->category] ?? 'secondary' }} px-2 py-1">
                                        {{ $account->category }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-{{ $account->status === 'ACTIVE' ? 'success' : 'danger' }} px-2 py-1">
                                        {{ $account->status }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center" style="gap:4px;">
                                        <button type="button"
                                                class="btn btn-warning btn-sm btn-edit-account"
                                                data-id="{{ $account->id }}"
                                                title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <form action="{{ url('/account/' . $account->id) }}"
                                              method="POST" class="frmDeleteAccount" style="display:inline;">
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
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="fas fa-folder-open d-block mb-2"></i>
                                    Belum ada data account
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

{{-- ================================================================ --}}
{{-- MODAL TAMBAH                                                       --}}
{{-- ================================================================ --}}
<div class="modal fade" id="mdlTambahAccount" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">

            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white">
                    <i class="fas fa-list mr-2" style="color:#F5A623;"></i> Tambah Account
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <form action="{{ url('/account') }}" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">

                    <div class="form-group">
                        <label class="font-weight-bold">Kategori <span class="text-danger">*</span></label>
                        <select name="category" class="form-control" required>
                            <option value="">— Pilih Kategori —</option>
                            <option value="Variable Cost">Variable Cost</option>
                            <option value="Fixed Cost">Fixed Cost</option>
                            <option value="Overhead">Overhead</option>
                        </select>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold">Nama Account <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control"
                               placeholder="Contoh: Akomodasi, Transport, Sewa..."
                               autocomplete="off" required>
                    </div>

                </div>
                <div class="modal-footer" style="background:#f8f9fa;border-top:1px solid #e9ecef;">
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

{{-- ================================================================ --}}
{{-- MODAL EDIT                                                         --}}
{{-- ================================================================ --}}
<div class="modal fade" id="mdlEditAccount" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">

            <div class="modal-header" style="background:#0D1B2A;border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white">
                    <i class="fas fa-pen mr-2" style="color:#F5A623;"></i> Edit Account
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <form id="frmEditAccount" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body" style="padding:24px 28px;">

                    <div id="editAccountLoading" class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                        <p class="text-muted mt-2 mb-0">Memuat data...</p>
                    </div>

                    <div id="editAccountFields" style="display:none;">

                        <div class="form-group">
                            <label class="font-weight-bold">Kategori <span class="text-danger">*</span></label>
                            <select name="category" id="editAccountCategory" class="form-control" required>
                                <option value="">— Pilih Kategori —</option>
                                <option value="Variable Cost">Variable Cost</option>
                                <option value="Fixed Cost">Fixed Cost</option>
                                <option value="Overhead">Overhead</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Nama Account <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="editAccountName"
                                   class="form-control" autocomplete="off" required>
                        </div>

                        <div class="form-group mb-0">
                            <label class="font-weight-bold">Status</label>
                            <select name="status" id="editAccountStatus" class="form-control">
                                <option value="ACTIVE">ACTIVE</option>
                                <option value="INACTIVE">INACTIVE</option>
                            </select>
                        </div>

                    </div>

                </div>
                <div class="modal-footer" style="background:#f8f9fa;border-top:1px solid #e9ecef;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-warning" id="btnUpdateAccount" style="display:none;">
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
.custom-toolbar      { display:flex; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; font-weight:600; color:#495057; white-space:nowrap; margin-bottom:0; }
.toolbar-input       { min-width:180px; }
#accountSearch input { width:180px; border-radius:6px; }
.ts-wrapper  { min-width:240px; }
.ts-control  { border-radius:6px !important; border:1px solid #ced4da !important; min-height:38px !important; padding:6px 10px !important; box-shadow:none !important; }
#tblAccount  { width:100% !important; }
.table td, .table th { vertical-align:middle !important; }
.dataTables_wrapper .row:last-child { margin-top:18px; align-items:center; }
.dataTables_info { padding-top:0 !important; font-size:14px; color:#6c757d; }
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
.dataTables_paginate .paginate_button { border-radius:6px !important; margin:0 2px; }
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
</style>
@endpush

@push('scripts')
<script>

    // Edit button
    $(document).on('click', '.btn-edit-account', function() {
        var id = $(this).data('id');
        $('#editAccountLoading').show();
        $('#editAccountFields').hide();
        $('#btnUpdateAccount').hide();
        $('#frmEditAccount').attr('action', '/account/' + id);
        $('#mdlEditAccount').modal('show');

        $.get('/account/' + id + '/edit-data', function(res) {
            $('#editAccountCategory').val(res.category);
            $('#editAccountName').val(res.name);
            $('#editAccountStatus').val(res.status);
            $('#editAccountLoading').hide();
            $('#editAccountFields').show();
            $('#btnUpdateAccount').show();
        }).fail(function() {
            $('#editAccountLoading').html(
                '<p class="text-danger text-center"><i class="fas fa-exclamation-triangle mr-1"></i>Gagal memuat data.</p>'
            );
        });
    });

$(function () {

    // Datatable
    var table = $('#tblAccount').DataTable({
        responsive: true,
        autoWidth: false,
        pageLength: 10,
        lengthChange: true,
        searching: true,
        ordering: true,
        dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex flex-wrap justify-content-between justify-content-md-end align-items-center gap-2"lp>>',
        language: {
            lengthMenu: 'Page Size _MENU_',
            zeroRecords: 'Data tidak ditemukan',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            paginate: { previous: 'Previous', next: 'Next' }
        }
    });

    $('#tblAccount_filter').hide();

    // Search
    $('#accountSearch').html('<input type="search" id="accountSearchInput" class="form-control form-control-sm" placeholder="Cari data...">');
    $('#accountSearchInput').on('keyup', function() { table.search(this.value).draw(); });

    // TomSelect filter
    new TomSelect('#accountFilter', {
        plugins: ['remove_button'], persist: false, create: false, placeholder: 'Filter data...'
    });

    $.fn.dataTable.ext.search.push(function(settings, data) {
        if (settings.nTable.id !== 'tblAccount') return true;
        var filters = $('#accountFilter').val();
        if (!filters || !filters.length) return true;
        var cat    = data[2].replace(/<[^>]*>/g, '').trim();
        var status = data[3].replace(/<[^>]*>/g, '').trim();
        var selCats = [], selStatuses = [];
        filters.forEach(function(f) {
            var p = f.split(':'), k = p[0], v = p.slice(1).join(':');
            if (k === 'CAT') selCats.push(v);
            if (k === 'STATUS') selStatuses.push(v);
        });
        if (selCats.length && !selCats.includes(cat)) return false;
        if (selStatuses.length && !selStatuses.includes(status)) return false;
        return true;
    });

    $('#accountFilter').on('change', function() { table.draw(); });

    // Refresh
    $('#btnRefresh').on('click', function() { location.reload(); });

    // Tombol Tambah
    $('#btnTambahAccount').on('click', function() {
        $('#mdlTambahAccount').modal('show');
    });

    // Delete confirm
    $(document).on('submit', '.frmDeleteAccount', function(e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: 'Hapus Account?',
            text: 'Account yang masih digunakan tidak dapat dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(function(r) { if (r.isConfirmed) form.submit(); });
    });

    // Reset modal saat ditutup
    $('#mdlTambahAccount').on('hidden.bs.modal', function() {
        $(this).find('form')[0].reset();
    });

});
</script>
@endpush