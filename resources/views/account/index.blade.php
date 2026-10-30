@extends('layouts.app')

@section('title', 'Account Master')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        {{-- Judul & breadcrumb topbar: shell memindahkan <h1> dan .breadcrumb pertama ke topbar
             (tesys-shell.js hoistPageTitle); disembunyikan secara visual. --}}
        <div class="tw:sr-only">
            <h1>Account Master</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Master</li>
                <li class="breadcrumb-item active">Account</li>
            </ol>
        </div>

        <x-tw.page-header title="Daftar Account"
            description="Chart of Account per kategori (Variable Cost, Fixed Cost, Overhead) untuk rincian pengajuan dan budget.">
            <x-slot:actions>
                <x-tw.button id="btnTambahAccount" class="ts-add-btn tw:max-sm:min-h-12"><i class="fas fa-plus" aria-hidden="true"></i> Tambah Account</x-tw.button>
            </x-slot:actions>
        </x-tw.page-header>

        {{-- Mobile: tombol di header disembunyikan dan diganti tombol melayang (tesys-ui-refine.css). --}}
        <button type="button" class="ts-fab" data-trigger="#btnTambahAccount" aria-label="Tambah Account" title="Tambah Account"><i class="fas fa-plus" aria-hidden="true"></i></button>


        <x-stat-cards :cards="[
            ['Total Account', $summary['total'], 'primary', 'fas fa-list'],
            ['Variable Cost', $summary['variable_cost'], 'warning', 'fas fa-chart-line'],
            ['Fixed Cost', $summary['fixed_cost'], 'danger', 'fas fa-anchor'],
            ['Overhead', $summary['overhead'], 'secondary', 'fas fa-layer-group'],
        ]" />

        {{-- Kartu tunggal: toolbar + tabel + paginasi (pola Cost Request). --}}
        <x-tw.card padding="sm">


                <div class="custom-toolbar mb-3 d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
                    <div class="custom-toolbar-left d-flex align-items-center flex-wrap" style="gap:8px;">
                        <div class="toolbar-row">
                            <span class="toolbar-label">Cari :</span>
                            <div class="toolbar-input" id="accountSearch"></div>
                        </div>
                        <button aria-label="Clear Filter" type="button" id="btnClearFilterAcc" class="btn btn-outline-secondary btn-sm" title="Clear Filter">
                            <i class="fas fa-eraser"></i>
                        </button>
                        <div id="accRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>
                    </div>
                    <div class="custom-toolbar-right d-flex flex-wrap" style="gap:8px;">
                        <x-tw.button id="btnRefresh" variant="secondary" size="sm"><i class="fas fa-sync-alt" aria-hidden="true"></i> Refresh</x-tw.button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="tblAccount" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" width="5%"  class="all">No</th>
                                <th scope="col" width="40%" class="all">Nama Account</th>
                                <th scope="col" width="25%">Kategori</th>
                                <th scope="col" width="15%">Status</th>
                                <th scope="col" width="15%" class="text-center all"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($accounts as $account)
                            <tr data-category="{{ $account->category }}" data-status="{{ $account->status }}">
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
                                                class="js-row-open dropdown-item btn-edit-account"
                                                data-id="{{ $account->id }}"
                                                title="Edit" aria-label="Edit akun">
                                            <i class="fas fa-pen"></i>Edit
                                        </button>

                                            <div class="dropdown-divider"></div>

                                            <form action="{{ url('/account/' . $account->id) }}"
                                              method="POST" class="frmDeleteAccount d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="dropdown-item is-danger"
                                                    title="Hapus" aria-label="Hapus akun">
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
                                    <x-empty title="Belum ada data account" icon="fas fa-folder-open" compact />
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

{{-- ================================================================ --}}
{{-- MODAL TAMBAH                                                       --}}
{{-- ================================================================ --}}
<div class="modal fade" id="mdlTambahAccount" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">

            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-list mr-2" style="color:var(--accent);"></i> Tambah Account
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
                <div class="modal-footer" style="background:var(--surface-2);border-top:1px solid var(--border);">
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
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">

            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-pen mr-2" style="color:var(--accent);"></i> Edit Account
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
                <div class="modal-footer" style="background:var(--surface-2);border-top:1px solid var(--border);">
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
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
<style>
.custom-toolbar      { display:flex; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; font-weight:600; color:var(--text-dim); white-space:nowrap; margin-bottom:0; }
.toolbar-input       { min-width:180px; }
#accountSearch input { width:180px; border-radius:var(--radius-sm); }
.ts-wrapper  { min-width:240px; }
.ts-control  { border-radius:var(--radius-sm) !important; border:1px solid var(--border) !important; min-height:38px !important; padding:6px 10px !important; box-shadow:none !important; }
#tblAccount  { width:100% !important; }
.table td, .table th { vertical-align:middle !important; }
.dataTables_wrapper .row:last-child { margin-top:18px; align-items:center; }
.dataTables_info { padding-top:0 !important; font-size:14px; color:var(--text-faint); }
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
.dataTables_paginate .paginate_button { border-radius:var(--radius-sm) !important; margin:0 2px; }
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
@media (max-width: 767.98px) {
    .card-header { flex-direction:column; gap:10px; align-items:stretch !important; }
    .card-header .btn { width:100%; }
    .card-header .card-title { margin-bottom:0; }
    .card-header .ml-auto { margin-left:0 !important; flex-wrap:wrap; }
    .custom-toolbar { flex-direction:column !important; align-items:stretch !important; }
    .custom-toolbar-left { flex-direction:column !important; align-items:stretch !important; width:100%; }
    .custom-toolbar-right { width:100%; }
    .custom-toolbar-right .btn { width:100%; }
    #btnClearFilterAcc { width:100%; }
    #accRowFilters { width:100%; }
    #accRowFilters .tcf-row-btn { width:100%; }
    .toolbar-row { width:100%; }
    .toolbar-input { flex:1; min-width:0; }
    #accountSearch input { width:100%; }
}
#tblAccount tbody tr:not(:has(td[colspan])) { cursor:pointer; }
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

    /* Klik di mana saja pada baris membuka detail (atau edit bila tidak ada detail) — pola Cost
       Request. Kontrol di dalam baris (tombol, tautan, menu aksi, kontrol responsif) bekerja sendiri. */
    $(document).on('click', '#tblAccount tbody tr', function (e) {
        if ($(e.target).closest('a, button, input, select, textarea, label, form, .ts-actions, .ts-action-menu, td.dtr-control').length) return;
        if (window.getSelection && String(window.getSelection()).length) return;
        var href = $(this).attr('data-href');
        if (href) { window.location.href = href; return; }
        $(this).find('.js-row-open').first().trigger('click');
    });
    // Tombol melayang (HP) memicu tombol "Tambah" di header yang disembunyikan.
    $('.ts-fab[data-trigger]').on('click', function () { $($(this).data('trigger')).trigger('click'); });

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

    TesysColumnFilter.initRowFilters('#tblAccount', '#accRowFilters', [
        { key: 'category', label: 'Kategori' },
        { key: 'status', label: 'Status' },
    ]);

    $('#btnClearFilterAcc').on('click', function() {
        TesysColumnFilter.clearAll('#tblAccount');
    });

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
            confirmButtonColor: '#9a3030',
            cancelButtonColor: '#55595e',
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