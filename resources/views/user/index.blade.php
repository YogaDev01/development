@extends('layouts.app')

@section('title', 'User & Access')

@section('content')

<!-- MAIN CONTENT -->
<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">User Master</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Master</a></li>
                <li class="breadcrumb-item active">User</li>
            </ol>
        </div>

        <x-stat-cards :cards="[
            ['Total User', $summary['total'], 'primary', 'fas fa-users'],
            ['Active', $summary['active'], 'success', 'fas fa-check-circle'],
            ['Inactive', $summary['inactive'], 'secondary', 'fas fa-pause-circle'],
        ]" />

        <div class="card card-primary card-outline shadow-sm">

            <!-- CARD HEADER -->
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-users mr-1"></i> Daftar User</h3>
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
                        <button aria-label="Clear Filter" type="button" id="btnClearFilterUser" class="btn btn-outline-secondary btn-sm" title="Clear Filter">
                            <i class="fas fa-eraser"></i>
                        </button>
                        <div id="userRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>
                    </div>
                    <div class="custom-toolbar-right d-flex flex-wrap" style="gap:8px;">
                        <a href="{{ url('/role') }}" class="btn btn-info btn-sm">
                            <i class="fas fa-layer-group mr-1"></i> Role Master
                        </a>
                        <a href="{{ url('/accessgroup') }}" class="btn btn-info btn-sm">
                            <i class="fas fa-layer-group mr-1"></i> Access Master
                        </a>
                        <a href="{{ url('/user/create') }}" class="btn btn-success btn-sm">
                            <i class="fas fa-plus mr-1"></i> Tambah User
                        </a>
                    </div>
                </div>

                <!-- TABLE -->
                <div class="table-responsive">
                    <table id="userTable" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" width="5%"  class="all">No</th>
                                <th scope="col" width="18%" class="all">Name</th>
                                <th scope="col" width="20%">Email</th>
                                <th scope="col" width="15%">Role</th>
                                <th scope="col" width="18%">Access Group</th>
                                <th scope="col" width="10%">Status</th>
                                <th scope="col" width="14%" class="text-center all action-column">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $user)
                            <tr data-role="{{ $user->role->name ?? '' }}" data-accessgroup="{{ $user->accessGroup->name ?? '' }}" data-status="{{ $user->status }}">
                                <td>{{ $loop->iteration }}</td>
                                <td><strong>{{ $user->name }}</strong></td>
                                <td><small>{{ $user->email }}</small></td>
                                <td>
                                    <span class="badge badge-info px-3 py-2">
                                        {{ $user->role->name ?? '-' }}
                                    </span>
                                </td>
                                <td>
                                    @if($user->accessGroup)
                                        <span class="badge badge-primary px-2 py-1">{{ $user->accessGroup->name }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($user->status == 'ACTIVE')
                                        <span class="badge badge-success px-2 py-1">ACTIVE</span>
                                    @else
                                        <span class="badge badge-danger px-2 py-1">INACTIVE</span>
                                    @endif
                                </td>
                                <td class="text-center action-column">
                                    <div class="action-buttons ts-actions">

                                        <a href="{{ url('/user/' . $user->id . '/edit') }}"
                                           class="ts-action-primary is-neutral"
                                           title="Edit" aria-label="Edit {{ $user->name }}">
                                            <i class="fas fa-pen" aria-hidden="true"></i><span class="ts-al">Edit</span>
                                        </a>

                                        <button type="button" class="ts-action-more"
                                                aria-haspopup="true" aria-expanded="false"
                                                title="Aksi lain" aria-label="Aksi lain untuk {{ $user->name }}">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>

                                        <div class="ts-action-menu dropdown-menu" hidden>

                                            <button type="button" class="dropdown-item btn-detail" data-id="{{ $user->id }}">
                                                <i class="fas fa-eye"></i>Detail
                                            </button>

                                            <div class="dropdown-divider"></div>

                                            <form action="{{ url('/user/' . $user->id) }}"
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
                                <td colspan="7">
                                    <x-empty title="Belum ada data user" icon="fas fa-folder-open" compact />
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
<!-- MODAL DETAIL USER                                                 -->
<!-- ================================================================ -->
<div class="modal fade" id="modalDetail" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg); overflow:hidden;">

            <!-- HEADER -->
            <div class="modal-header" style="background:var(--charcoal); border-bottom:3px solid var(--accent); padding:16px 24px;">
                <div class="d-flex align-items-center">
                    <div style="width:36px;height:36px;background:var(--accent);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;margin-right:12px;flex-shrink:0;">
                        <i class="fas fa-user" style="color:#fff;font-size:15px;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0" id="modalDetailTitle">User Profile</h5>
                        <small id="modalDetailSubtitle" style="color:var(--text-faint);font-size:11px;letter-spacing:0.05em;"></small>
                    </div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:0.7;">
                    <span>&times;</span>
                </button>
            </div>

            <!-- BODY -->
            <div class="modal-body" style="padding:0; background:var(--surface-2);">

                <!-- Loading -->
                <div id="detailLoading" class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x" style="color:var(--accent);"></i>
                    <p class="text-muted mt-3 mb-0">Memuat data user...</p>
                </div>

                <!-- Content -->
                <div id="detailContent" style="display:none;">
                    <div class="row no-gutters">

                        <!-- ── LEFT: PROFILE ── -->
                        <div class="col-md-4" style="background:var(--charcoal); padding:28px 20px; min-height:420px;">

                            <!-- Avatar -->
                            <div class="text-center mb-3">
                                <img id="detailAvatar" src="" alt=""
                                     style="width:80px;height:80px;border-radius:50%;border:3px solid var(--accent);object-fit:cover;">
                                <h6 id="detailName" class="text-white mt-3 mb-1 font-weight-bold" style="font-size:14px;line-height:1.4;"></h6>
                                <div id="detailStatusBadge" class="mt-1"></div>
                            </div>

                            <hr style="border-color:rgba(255,255,255,0.08); margin:16px 0;">

                            <div class="detail-stat-item">
                                <span style="color:var(--text-faint);font-size:10px;letter-spacing:0.1em;text-transform:uppercase;font-weight:600;">Username</span>
                                <div id="detailUsername" class="text-white mt-1" style="font-size:13px;font-weight:600;word-break:break-all;"></div>
                            </div>
                            <div class="detail-stat-item mt-3">
                                <span style="color:var(--text-faint);font-size:10px;letter-spacing:0.1em;text-transform:uppercase;font-weight:600;">Role</span>
                                <div id="detailRole" class="mt-1"></div>
                            </div>
                            <div class="detail-stat-item mt-3">
                                <span style="color:var(--text-faint);font-size:10px;letter-spacing:0.1em;text-transform:uppercase;font-weight:600;">Access Group</span>
                                <div id="detailGroup" class="mt-1"></div>
                            </div>
                            <div class="detail-stat-item mt-3">
                                <span style="color:var(--text-faint);font-size:10px;letter-spacing:0.1em;text-transform:uppercase;font-weight:600;">Project Access</span>
                                <div id="detailProjectCount" style="color:var(--accent);font-size:22px;font-weight:700;margin-top:4px;"></div>
                            </div>
                            <div class="detail-stat-item mt-3">
                                <span style="color:var(--text-faint);font-size:10px;letter-spacing:0.1em;text-transform:uppercase;font-weight:600;">Dibuat</span>
                                <div id="detailCreatedAt" class="text-white mt-1" style="font-size:12px;"></div>
                            </div>

                            <hr style="border-color:rgba(255,255,255,0.08); margin:20px 0 16px;">

                            <a id="detailEditBtn" href="#"
                               class="btn btn-block"
                               style="background:var(--accent);color:#fff;font-weight:700;font-size:13px;border-radius:var(--radius-sm);">
                                <i class="fas fa-edit mr-1"></i> Edit User
                            </a>

                        </div>

                        <!-- ── RIGHT: TABS ── -->
                        <div class="col-md-8" style="padding:24px;">

                            <ul class="nav nav-tabs mb-3" id="detailTabs" role="tablist"
                                style="border-bottom:2px solid var(--border);">
                                <li class="nav-item">
                                    <a class="nav-link active" data-toggle="tab" href="#pane-detail" style="font-size:13px;font-weight:600;">
                                        <i class="fas fa-info-circle mr-1"></i> Detail
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#pane-access" style="font-size:13px;font-weight:600;">
                                        <i class="fas fa-project-diagram mr-1"></i> Project Access
                                        <span id="accessCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                                    </a>
                                </li>
                            </ul>

                            <div class="tab-content">

                                <!-- TAB DETAIL -->
                                <div class="tab-pane fade show active" id="pane-detail">
                                    <table class="table table-bordered mb-0" style="font-size:13px;">
                                        <tr>
                                            <th width="35%" style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Full Name</th>
                                            <td id="infoName"></td>
                                        </tr>
                                        <tr>
                                            <th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Username</th>
                                            <td id="infoUsername"></td>
                                        </tr>
                                        <tr>
                                            <th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Email</th>
                                            <td id="infoEmail"></td>
                                        </tr>
                                        <tr>
                                            <th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Role</th>
                                            <td id="infoRole"></td>
                                        </tr>
                                        <tr>
                                            <th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Access Group</th>
                                            <td id="infoGroup"></td>
                                        </tr>
                                        <tr>
                                            <th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Status</th>
                                            <td id="infoStatus"></td>
                                        </tr>
                                        <tr>
                                            <th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Created At</th>
                                            <td id="infoCreatedAt"></td>
                                        </tr>
                                    </table>
                                </div>

                                <!-- TAB PROJECT ACCESS -->
                                <div class="tab-pane fade" id="pane-access">
                                    <div id="accessBody"></div>
                                </div>

                            </div>

                        </div>

                    </div>
                </div>

            </div>

            <!-- FOOTER -->
            <div class="modal-footer" style="background:var(--surface-2); border-top:1px solid var(--border); padding:12px 24px;">
                <small class="text-muted mr-auto">
                    <i class="fas fa-info-circle mr-1"></i> Data read-only. Klik Edit untuk mengubah.
                </small>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Tutup
                </button>
                @if(auth()->user()->isRealSuperadmin())
                <a id="detailActivityLogBtn" href="#" class="btn btn-info btn-sm">
                    <i class="fas fa-stream mr-1"></i> Activity Log
                </a>
                @endif
                <a id="detailEditBtnFooter" href="#" class="btn btn-warning btn-sm">
                    <i class="fas fa-edit mr-1"></i> Edit User
                </a>
            </div>

        </div>
    </div>
</div>

@endsection

@push('styles')
<style>

.action-header-buttons { display:flex; gap:8px; }
.card-header .btn      { border-radius:var(--radius-sm); font-weight:600; }

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
#userTable          { width:100% !important; }
.table td,.table th { vertical-align:middle !important; }
.action-column      { white-space:nowrap !important; min-width:100px !important; }
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

/* MODAL TABS */
#detailTabs .nav-link {
    color:var(--text-faint); border:none;
    border-bottom:2px solid transparent;
    border-radius:0; padding:8px 16px;
    transition:color .15s, border-color .15s;
}
#detailTabs .nav-link:hover { color:var(--accent); }
#detailTabs .nav-link.active {
    color:var(--accent) !important;
    border-bottom-color:var(--accent) !important;
    background:transparent !important;
    font-weight:700;
}

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
    #btnClearFilterUser { width:100%; }
    #userRowFilters { width:100%; }
    #userRowFilters .tcf-row-btn { width:100%; }
    .toolbar-row { width:100%; align-items:flex-start; }
    .toolbar-input { flex:1; width:100%; min-width:0; }
    #customSearch input { width:100% !important; }
    .ts-wrapper,.ts-control { width:100% !important; min-width:0 !important; }
    .ts-control > input { min-width:0 !important; width:100% !important; }
    .dataTables_wrapper .row:last-child { display:flex; flex-direction:column; align-items:flex-start !important; gap:12px; }
    .dataTables_wrapper .row:last-child .col-md-6 { width:100%; max-width:100%; display:flex; justify-content:flex-start !important; }
    .dataTables_paginate { width:100%; overflow-x:auto; }
    .dataTables_length { margin-left:0; }
    #modalDetail .col-md-4 { min-height:auto !important; }
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

    let table = $('#userTable').DataTable({
        responsive: { details: { type: 'inline' } },
        autoWidth: false, pageLength: 10, lengthChange: true,
        searching: true, ordering: true, orderCellsTop: true,
        columnDefs: [
            { responsivePriority: 1,   targets: [0, 1, -1] },
            { responsivePriority: 2,   targets: [3] },
            { responsivePriority: 100, targets: [2, 4, 5] }
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

    $('#userTable_filter').hide();

    $('#customSearch').html(`
        <input type="search" id="globalSearch" class="form-control form-control-sm" placeholder="Cari data...">
    `);
    $('#globalSearch').on('keyup', function () { table.search(this.value).draw(); });

    TesysColumnFilter.initRowFilters('#userTable', '#userRowFilters', [
        { key: 'role', label: 'Role' },
        { key: 'accessgroup', label: 'Access Group' },
        { key: 'status', label: 'Status' },
    ]);

    $('#btnClearFilterUser').on('click', function () {
        TesysColumnFilter.clearAll('#userTable');
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
            title: 'Hapus User?', text: 'Data yang dihapus tidak dapat dikembalikan.',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#9a3030', cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
        }).then(r => { if (r.isConfirmed) form.submit(); });
    });

    /*
    |--------------------------------------------------------------------------
    | MODAL DETAIL — fetch JSON
    |--------------------------------------------------------------------------
    */

    $(document).on('click', '.btn-detail', function () {
        let id = $(this).data('id');

        $('#detailLoading').show();
        $('#detailContent').hide();
        $('#modalDetailTitle').text('User Profile');
        $('#modalDetailSubtitle').text('');
        $('#modalDetail').modal('show');

        $.get('{{ url("/user") }}/' + id + '/detail-json', function (d) {

            /* LEFT */
            $('#detailAvatar').attr('src', 'https://ui-avatars.com/api/?name=' + encodeURIComponent(d.name) + '&background=0D8ABC&color=fff&size=128');
            $('#detailName').text(d.name);
            $('#modalDetailTitle').text(d.name);
            $('#modalDetailSubtitle').text('@' + d.username + ' · ' + (d.role ?? '-'));

            let statusHtml = d.status === 'ACTIVE'
                ? '<span class="badge badge-success px-3 py-1">ACTIVE</span>'
                : '<span class="badge badge-danger px-3 py-1">INACTIVE</span>';
            $('#detailStatusBadge').html(statusHtml);

            $('#detailUsername').text('@' + d.username);
            $('#detailRole').html('<span class="badge badge-info px-2 py-1" style="font-size:12px;">' + (d.role ?? '-') + '</span>');
            $('#detailGroup').html(d.access_group
                ? '<span class="badge badge-primary px-2 py-1" style="font-size:12px;">' + d.access_group + '</span>'
                : '<span class="text-muted" style="font-size:12px;">-</span>');
            $('#detailProjectCount').text(d.project_count);
            $('#detailCreatedAt').text(d.created_at);

            let editUrl = '{{ url("/user") }}/' + id + '/edit';
            $('#detailEditBtn').attr('href', editUrl);
            $('#detailEditBtnFooter').attr('href', editUrl);
            $('#detailActivityLogBtn').attr('href', '{{ url("/user") }}/' + id + '/activity-log');

            /* TAB DETAIL */
            $('#infoName').text(d.name);
            $('#infoUsername').html('<code>@' + d.username + '</code>');
            $('#infoEmail').text(d.email);
            $('#infoRole').html('<span class="badge badge-info px-2 py-1">' + (d.role ?? '-') + '</span>');
            $('#infoGroup').html(d.access_group
                ? '<span class="badge badge-primary px-2 py-1">' + d.access_group + '</span>'
                : '-');
            $('#infoStatus').html(statusHtml);
            $('#infoCreatedAt').text(d.created_at_full);

            /* TAB PROJECT ACCESS */
            $('#accessCount').text(d.project_count);
            let accessHtml = '';
            if (d.projects && d.projects.length) {
                accessHtml = '<div class="row">';
                d.projects.forEach(function(p) {
                    accessHtml += `
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center p-3 rounded"
                                 style="background:var(--surface);border:1px solid var(--border);gap:12px;">
                                <div style="width:36px;height:36px;background:var(--info-bg);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fas fa-project-diagram" style="color:var(--info);font-size:14px;"></i>
                                </div>
                                <div>
                                    <div style="font-size:13px;font-weight:600;color:var(--text);">${p.name}</div>
                                    <div style="font-size:11px;color:var(--text-faint);">${p.code ?? ''}</div>
                                </div>
                            </div>
                        </div>`;
                });
                accessHtml += '</div>';
            } else {
                accessHtml = '<div class="alert alert-warning mb-0"><i class="fas fa-exclamation-triangle mr-1"></i>User belum memiliki project access.</div>';
            }
            $('#accessBody').html(accessHtml);

            /* Show tabs first */
            $('#detailTabs a:first').tab('show');

            $('#detailLoading').hide();
            $('#detailContent').show();

        }).fail(function () {
            $('#detailLoading').html(
                '<p class="text-danger py-4"><i class="fas fa-exclamation-triangle mr-1"></i>Gagal memuat data. Silakan coba lagi.</p>'
            );
        });
    });

    $('#modalDetail').on('hidden.bs.modal', function () {
        $('#detailContent').hide();
        $('#detailLoading').show();
    });

});

</script>
@endpush