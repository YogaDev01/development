@extends('layouts.app')

@section('title', 'Beneficiary')

@section('content')

<!-- MAIN CONTENT -->
<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Beneficiary Master</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Master</a></li>
                <li class="breadcrumb-item active">Beneficiary</li>
            </ol>
        </div>

        <x-stat-cards :cards="[
            ['Total Beneficiary', $summary['total'], 'primary', 'fas fa-user-friends'],
            ['Active', $summary['active'], 'success', 'fas fa-check-circle'],
            ['Internal', $summary['internal'], 'info', 'fas fa-building'],
            ['External', $summary['external'], 'warning', 'fas fa-truck'],
            ['Billing', $summary['billing'], 'secondary', 'fas fa-file-invoice'],
        ]" />

        <div class="card card-primary card-outline shadow-sm">

            <!-- CARD HEADER -->
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-user-friends mr-1"></i> List Beneficiary</h3>
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
                        <button aria-label="Clear Filter" type="button" id="btnClearFilterBen" class="btn btn-outline-secondary btn-sm" title="Clear Filter">
                            <i class="fas fa-eraser"></i>
                        </button>
                        <div id="benRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>

                        {{-- MN-02 — filter Kategori & Status diproses di server
                             (BeneficiaryController::index) supaya angka kartu
                             summary di atas selalu konsisten dengan isi tabel. --}}
                        <form method="GET" action="{{ url('/beneficiaries') }}" id="formFilterBen"
                              class="d-flex align-items-center flex-wrap" style="gap:8px;">
                            <select name="category" class="form-control form-control-sm js-auto-submit"
                                    style="width:auto;" aria-label="Filter kategori">
                                <option value="">Semua Kategori</option>
                                @foreach(\App\Models\Beneficiary::CATEGORIES as $cat)
                                    <option value="{{ $cat }}" @selected(($filters['category'] ?? '') === $cat)>{{ $cat }}</option>
                                @endforeach
                            </select>
                            <select name="status" class="form-control form-control-sm js-auto-submit"
                                    style="width:auto;" aria-label="Filter status">
                                <option value="">Semua Status</option>
                                <option value="ACTIVE" @selected(($filters['status'] ?? '') === 'ACTIVE')>ACTIVE</option>
                                <option value="INACTIVE" @selected(($filters['status'] ?? '') === 'INACTIVE')>INACTIVE</option>
                            </select>
                            @if(($filters['q'] ?? '') !== '')
                                <input type="hidden" name="q" value="{{ $filters['q'] }}">
                            @endif
                        </form>
                    </div>
                    <div class="custom-toolbar-right d-flex flex-wrap" style="gap:8px;">
                        <a href="{{ url('/bank') }}" class="btn btn-info btn-sm">
                            <i class="fas fa-university mr-1"></i> Bank Master
                        </a>
                        <button type="button" class="btn btn-success btn-sm" id="btnTambah">
                            <i class="fas fa-plus mr-1"></i> Tambah Beneficiary
                        </button>
                    </div>
                </div>

                <!-- TABLE -->
                <div class="table-responsive">
                    <table id="beneficiaryTable" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" width="4%"  class="all">No</th>
                                <th scope="col" width="11%" class="all">Nickname</th>
                                <th scope="col" width="19%">Complete Name</th>
                                <th scope="col" width="10%">Kategori</th>
                                <th scope="col" width="12%">PIC</th>
                                <th scope="col" width="10%">Bank</th>
                                <th scope="col" width="13%">Account Number</th>
                                <th scope="col" width="11%">Kontak</th>
                                <th scope="col" width="8%">Status</th>
                                <th scope="col" width="10%" class="text-center all action-column">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($beneficiaries as $beneficiary)
                            <tr data-bank="{{ $beneficiary->bank->name ?? '' }}"
                                data-status="{{ $beneficiary->status }}"
                                data-category="{{ $beneficiary->category }}">
                                <td>{{ $loop->iteration }}</td>
                                <td><strong>{{ $beneficiary->nickname }}</strong></td>
                                <td>{{ $beneficiary->complete_name }}</td>
                                <td>
                                    @php
                                        $catBadge = ['INTERNAL' => 'badge-primary', 'EXTERNAL' => 'badge-warning', 'BILLING' => 'badge-secondary'][$beneficiary->category] ?? 'badge-secondary';
                                    @endphp
                                    <span class="badge {{ $catBadge }} px-2 py-1">{{ $beneficiary->category }}</span>
                                </td>
                                <td>
                                    @if($beneficiary->pic)
                                        {{ $beneficiary->pic->pic }}
                                        @if($beneficiary->pic->position)
                                            <div class="text-muted" style="font-size:11px;">{{ $beneficiary->pic->position }}</div>
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-info px-2 py-1">
                                        {{ $beneficiary->bank->name ?? '-' }}
                                    </span>
                                </td>
                                <td>{{ $beneficiary->account_number ?? '-' }}</td>
                                <td>{{ $beneficiary->contact ?? '-' }}</td>
                                <td>
                                    @if($beneficiary->status == 'ACTIVE')
                                        <span class="badge badge-success px-2 py-1">ACTIVE</span>
                                    @else
                                        <span class="badge badge-danger px-2 py-1">INACTIVE</span>
                                    @endif
                                </td>
                                <td class="text-center action-column">
                                    <div class="action-buttons ts-actions">

                                        <button type="button"
                                                class="ts-action-primary is-neutral btn-edit"
                                                data-id="{{ $beneficiary->id }}"
                                                title="Edit" aria-label="Edit {{ $beneficiary->name }}">
                                            <i class="fas fa-pen" aria-hidden="true"></i><span class="ts-al">Edit</span>
                                        </button>

                                        <button type="button" class="ts-action-more"
                                                aria-haspopup="true" aria-expanded="false"
                                                title="Aksi lain" aria-label="Aksi lain untuk {{ $beneficiary->name }}">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>

                                        <div class="ts-action-menu dropdown-menu" hidden>
                                            <form action="{{ url('/beneficiaries/' . $beneficiary->id) }}"
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
                                <td colspan="10">
                                    <x-empty title="Belum ada data beneficiary" icon="fas fa-folder-open" compact />
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
<!-- MODAL TAMBAH BENEFICIARY                                          -->
<!-- ================================================================ -->
<div class="modal fade" id="modalTambah" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg); overflow:hidden;">

            <div class="modal-header" style="background:var(--charcoal); border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-user-friends mr-2" style="color:var(--accent);"></i>
                    Tambah Beneficiary
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <form action="{{ url('/beneficiaries') }}" method="POST" id="formTambah">
                @csrf

                <div class="modal-body" style="padding:28px 32px;">
                    <div class="row">

                        <!-- NICKNAME -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-600">
                                    Nickname <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="nickname" id="tambahNickname"
                                       class="form-control"
                                       placeholder="Masukkan nickname"
                                       style="text-transform:uppercase;"
                                       autocomplete="off" required>
                            </div>
                        </div>

                        <!-- COMPLETE NAME -->
                        <div class="col-md-8">
                            <div class="form-group">
                                <label class="font-weight-600">
                                    Complete Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="complete_name" id="tambahCompleteName"
                                       class="form-control"
                                       placeholder="Masukkan nama lengkap"
                                       style="text-transform:uppercase;"
                                       autocomplete="off" required>
                            </div>
                        </div>

                        <!-- BANK -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Bank</label>
                                <select name="bank_id" id="tambahBank" class="form-control">
                                    <option value="">— Pilih Bank —</option>
                                    @foreach($banks as $bank)
                                        <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- ACCOUNT NUMBER -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Account Number</label>
                                <input type="text" name="account_number" id="tambahAccountNumber"
                                       class="form-control"
                                       placeholder="Masukkan nomor rekening"
                                       autocomplete="off">
                            </div>
                        </div>

                        <!-- KATEGORI (MN-02) -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">
                                    Kategori <span class="text-danger">*</span>
                                </label>
                                <select name="category" id="tambahCategory" class="form-control" required>
                                    @foreach(\App\Models\Beneficiary::CATEGORIES as $cat)
                                        <option value="{{ $cat }}" @selected($cat === 'EXTERNAL')>{{ $cat }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- PIC (MN-02) -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">PIC</label>
                                <select name="pic_id" id="tambahPic" class="form-control">
                                    <option value="">— Pilih PIC —</option>
                                    @foreach($pics as $pic)
                                        <option value="{{ $pic->id }}">{{ $pic->pic }}@if($pic->position) — {{ $pic->position }}@endif</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- CONTACT — nomor telepon / kontak yang bisa dihubungi.
                             Labelnya dulu tertulis "Email" padahal kolomnya
                             `contact`; tabel ini memang tidak punya kolom email. -->
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label class="font-weight-600">Kontak</label>
                                <input type="text" name="contact" id="tambahContact"
                                       class="form-control"
                                       placeholder="Nomor telepon / kontak"
                                       autocomplete="off">
                            </div>
                        </div>

                    </div>

                    <!-- STATUS hidden — default ACTIVE -->
                    <input type="hidden" name="status" value="ACTIVE">

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
<!-- MODAL EDIT BENEFICIARY                                            -->
<!-- ================================================================ -->
<div class="modal fade" id="modalEdit" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg); overflow:hidden;">

            <div class="modal-header" style="background:var(--charcoal); border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-pen mr-2" style="color:var(--accent);"></i>
                    Edit Beneficiary
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <form id="formEdit" method="POST">
                @csrf
                @method('PUT')

                <div class="modal-body" style="padding:28px 32px;">

                    <!-- Loading -->
                    <div id="editLoading" class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                        <p class="text-muted mt-2 mb-0">Memuat data...</p>
                    </div>

                    <div id="editFields" style="display:none;">
                        <div class="row">

                            <!-- NICKNAME -->
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="font-weight-600">
                                        Nickname <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="nickname" id="editNickname"
                                           class="form-control"
                                           style="text-transform:uppercase;"
                                           autocomplete="off" required>
                                </div>
                            </div>

                            <!-- COMPLETE NAME -->
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label class="font-weight-600">
                                        Complete Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="complete_name" id="editCompleteName"
                                           class="form-control"
                                           style="text-transform:uppercase;"
                                           autocomplete="off" required>
                                </div>
                            </div>

                            <!-- BANK -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">Bank</label>
                                    <select name="bank_id" id="editBank" class="form-control">
                                        <option value="">— Pilih Bank —</option>
                                        @foreach($banks as $bank)
                                            <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- ACCOUNT NUMBER -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">Account Number</label>
                                    <input type="text" name="account_number" id="editAccountNumber"
                                           class="form-control" autocomplete="off">
                                </div>
                            </div>

                            <!-- KATEGORI (MN-02) -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">
                                        Kategori <span class="text-danger">*</span>
                                    </label>
                                    <select name="category" id="editCategory" class="form-control" required>
                                        @foreach(\App\Models\Beneficiary::CATEGORIES as $cat)
                                            <option value="{{ $cat }}">{{ $cat }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- PIC (MN-02) -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">PIC</label>
                                    <select name="pic_id" id="editPic" class="form-control">
                                        <option value="">— Pilih PIC —</option>
                                        @foreach($pics as $pic)
                                            <option value="{{ $pic->id }}">{{ $pic->pic }}@if($pic->position) — {{ $pic->position }}@endif</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- CONTACT -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">Kontak</label>
                                    <input type="text" name="contact" id="editContact"
                                           class="form-control" autocomplete="off">
                                </div>
                            </div>

                            <!-- STATUS -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">
                                        Status <span class="text-danger">*</span>
                                    </label>
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
<style>

.action-header-buttons { display:flex; gap:8px; }
.card-header .btn      { border-radius:var(--radius-sm); font-weight:600; }
.font-weight-600       { font-weight:600; }

/* TOOLBAR */
.custom-toolbar      { display:flex; justify-content:space-between; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; margin-bottom:0; white-space:nowrap; font-weight:600; color:var(--text-dim); }
.toolbar-input       { min-width:180px; }
#customSearch        { width:100%; }
#customSearch input  { width:180px; border-radius:var(--radius-sm); }

/* TOMSELECT */
.ts-wrapper  { min-width:260px; width:100%; }
.ts-control  { border-radius:var(--radius-sm) !important; border:1px solid var(--border) !important; min-height:38px !important; padding:6px 10px !important; box-shadow:none !important; }
.ts-control.focus { border-color:var(--border-strong) !important; box-shadow:0 0 0 0.15rem rgba(0,123,255,.15) !important; }

/* TABLE */
#beneficiaryTable   { width:100% !important; }
.table td,.table th { vertical-align:middle !important; }
.action-column      { white-space:nowrap !important; min-width:80px !important; }
.action-buttons     { display:flex; align-items:center; justify-content:center; flex-wrap:nowrap; gap:4px; }
.action-buttons .btn { flex-shrink:0; }

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
    #btnClearFilterBen { width:100%; }
    #benRowFilters { width:100%; }
    #benRowFilters .tcf-row-btn { width:100%; }
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

    let table = $('#beneficiaryTable').DataTable({
        responsive: { details: { type: 'inline' } },
        autoWidth: false, pageLength: 10, lengthChange: true,
        searching: true, ordering: true, orderCellsTop: true,
        columnDefs: [
            { responsivePriority: 1,   targets: [0, 1, -1] },
            { responsivePriority: 2,   targets: [3] },
            { responsivePriority: 100, targets: [2, 4, 5, 6, 7, 8] }
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

    $('#beneficiaryTable_filter').hide();

    $('#customSearch').html(`
        <input type="search" id="globalSearch" class="form-control form-control-sm" placeholder="Cari data...">
    `);
    $('#globalSearch').on('keyup', function () { table.search(this.value).draw(); });

    TesysColumnFilter.initRowFilters('#beneficiaryTable', '#benRowFilters', [
        { key: 'category', label: 'Kategori' },
        { key: 'bank', label: 'Bank' },
        { key: 'status', label: 'Status' },
    ]);

    /* Filter Kategori/Status diproses di server: begitu dipilih, form-nya
       langsung disubmit supaya kartu summary & isi tabel berangkat dari query
       yang sama (tidak ada dua sumber angka). */
    $('.js-auto-submit').on('change', function () {
        $('#formFilterBen').trigger('submit');
    });

    $('#btnClearFilterBen').on('click', function () {
        TesysColumnFilter.clearAll('#beneficiaryTable');
        $('#globalSearch').val('');
        // Filter server-side ikut dibersihkan — kalau tidak, tombol ini terasa
        // "tidak bekerja" saat kategori/status sedang aktif di URL.
        if (window.location.search) window.location = '{{ url("/beneficiaries") }}';
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
            title: 'Hapus Beneficiary?',
            text: 'Data yang dihapus tidak dapat dikembalikan.',
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
        $('#tambahCategory').val('EXTERNAL');
        $('#modalTambah').modal('show');
        $('#modalTambah').one('shown.bs.modal', function () {
            $('#tambahNickname').focus();
        });
    });

    /*
    |--------------------------------------------------------------------------
    | MODAL EDIT — load data via AJAX
    |--------------------------------------------------------------------------
    */

    $(document).on('click', '.btn-edit', function () {
        let id = $(this).data('id');

        $('#editLoading').show();
        $('#editFields').hide();
        $('#btnSimpanEdit').hide();
        $('#modalEdit').modal('show');

        $('#formEdit').attr('action', '{{ url("/beneficiaries") }}/' + id);

        $.get('{{ url("/beneficiaries") }}/' + id + '/edit-data', function (res) {
            $('#editNickname').val(res.nickname);
            $('#editCompleteName').val(res.complete_name);
            $('#editCategory').val(res.category ?? 'EXTERNAL');
            $('#editPic').val(res.pic_id ?? '');
            $('#editBank').val(res.bank_id ?? '');
            $('#editAccountNumber').val(res.account_number ?? '');
            $('#editContact').val(res.contact ?? '');
            $('#editStatus').val(res.status);

            $('#editLoading').hide();
            $('#editFields').show();
            $('#btnSimpanEdit').show();

            $('#modalEdit').one('shown.bs.modal', function () {
                $('#editNickname').focus();
            });
        }).fail(function () {
            $('#editLoading').html(
                '<p class="text-danger"><i class="fas fa-exclamation-triangle mr-1"></i>Gagal memuat data. Silakan coba lagi.</p>'
            );
        });
    });

});

</script>
@endpush