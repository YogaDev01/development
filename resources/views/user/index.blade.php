@extends('layouts.app')

@section('title', 'User & Access')

@section('content')

<!-- CONTENT HEADER -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold">User Master</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="#">Master</a></li>
                    <li class="breadcrumb-item active">User</li>
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
                                    @foreach($roles as $role)
                                        <option value="ROLE:{{ $role->name }}">Role : {{ $role->name }}</option>
                                    @endforeach
                                    @foreach($accessGroups as $group)
                                        <option value="GROUP:{{ $group->name }}">Access Group : {{ $group->name }}</option>
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
                    <table id="userTable" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th width="5%"  class="all">No</th>
                                <th width="18%" class="all">Name</th>
                                <th width="20%">Email</th>
                                <th width="15%">Role</th>
                                <th width="18%">Access Group</th>
                                <th width="10%">Status</th>
                                <th width="14%" class="text-center all action-column">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $user)
                            <tr>
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
                                    <div class="action-buttons">

                                        {{-- DETAIL → modal --}}
                                        <button type="button"
                                                class="btn btn-info btn-sm btn-detail"
                                                data-id="{{ $user->id }}"
                                                title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </button>

                                        <a href="{{ url('/user/' . $user->id . '/edit') }}"
                                           class="btn btn-warning btn-sm" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>

                                        <form action="{{ url('/user/' . $user->id) }}"
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
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-folder-open d-block mb-2"></i>
                                    Belum ada data user
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
        <div class="modal-content" style="border-radius:12px; overflow:hidden;">

            <!-- HEADER -->
            <div class="modal-header" style="background:#0D1B2A; border-bottom:3px solid #F5A623; padding:16px 24px;">
                <div class="d-flex align-items-center">
                    <div style="width:36px;height:36px;background:#F5A623;border-radius:8px;display:flex;align-items:center;justify-content:center;margin-right:12px;flex-shrink:0;">
                        <i class="fas fa-user" style="color:#0D1B2A;font-size:15px;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0" id="modalDetailTitle">User Profile</h5>
                        <small id="modalDetailSubtitle" style="color:#8A9BB0;font-size:11px;letter-spacing:0.05em;"></small>
                    </div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:0.7;">
                    <span>&times;</span>
                </button>
            </div>

            <!-- BODY -->
            <div class="modal-body" style="padding:0; background:#F0F2F5;">

                <!-- Loading -->
                <div id="detailLoading" class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x" style="color:#F5A623;"></i>
                    <p class="text-muted mt-3 mb-0">Memuat data user...</p>
                </div>

                <!-- Content -->
                <div id="detailContent" style="display:none;">
                    <div class="row no-gutters">

                        <!-- ── LEFT: PROFILE ── -->
                        <div class="col-md-4" style="background:#0D1B2A; padding:28px 20px; min-height:420px;">

                            <!-- Avatar -->
                            <div class="text-center mb-3">
                                <img id="detailAvatar" src="" alt=""
                                     style="width:80px;height:80px;border-radius:50%;border:3px solid #F5A623;object-fit:cover;">
                                <h6 id="detailName" class="text-white mt-3 mb-1 font-weight-bold" style="font-size:14px;line-height:1.4;"></h6>
                                <div id="detailStatusBadge" class="mt-1"></div>
                            </div>

                            <hr style="border-color:rgba(255,255,255,0.08); margin:16px 0;">

                            <div class="detail-stat-item">
                                <span style="color:#5A7080;font-size:10px;letter-spacing:0.1em;text-transform:uppercase;font-weight:600;">Username</span>
                                <div id="detailUsername" class="text-white mt-1" style="font-size:13px;font-weight:600;word-break:break-all;"></div>
                            </div>
                            <div class="detail-stat-item mt-3">
                                <span style="color:#5A7080;font-size:10px;letter-spacing:0.1em;text-transform:uppercase;font-weight:600;">Role</span>
                                <div id="detailRole" class="mt-1"></div>
                            </div>
                            <div class="detail-stat-item mt-3">
                                <span style="color:#5A7080;font-size:10px;letter-spacing:0.1em;text-transform:uppercase;font-weight:600;">Access Group</span>
                                <div id="detailGroup" class="mt-1"></div>
                            </div>
                            <div class="detail-stat-item mt-3">
                                <span style="color:#5A7080;font-size:10px;letter-spacing:0.1em;text-transform:uppercase;font-weight:600;">Project Access</span>
                                <div id="detailProjectCount" style="color:#F5A623;font-size:22px;font-weight:700;margin-top:4px;"></div>
                            </div>
                            <div class="detail-stat-item mt-3">
                                <span style="color:#5A7080;font-size:10px;letter-spacing:0.1em;text-transform:uppercase;font-weight:600;">Dibuat</span>
                                <div id="detailCreatedAt" class="text-white mt-1" style="font-size:12px;"></div>
                            </div>

                            <hr style="border-color:rgba(255,255,255,0.08); margin:20px 0 16px;">

                            <a id="detailEditBtn" href="#"
                               class="btn btn-block"
                               style="background:#F5A623;color:#0D1B2A;font-weight:700;font-size:13px;border-radius:6px;">
                                <i class="fas fa-edit mr-1"></i> Edit User
                            </a>

                        </div>

                        <!-- ── RIGHT: TABS ── -->
                        <div class="col-md-8" style="padding:24px;">

                            <ul class="nav nav-tabs mb-3" id="detailTabs" role="tablist"
                                style="border-bottom:2px solid #E5E7EB;">
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
                                            <th width="35%" style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Full Name</th>
                                            <td id="infoName"></td>
                                        </tr>
                                        <tr>
                                            <th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Username</th>
                                            <td id="infoUsername"></td>
                                        </tr>
                                        <tr>
                                            <th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Email</th>
                                            <td id="infoEmail"></td>
                                        </tr>
                                        <tr>
                                            <th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Role</th>
                                            <td id="infoRole"></td>
                                        </tr>
                                        <tr>
                                            <th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Access Group</th>
                                            <td id="infoGroup"></td>
                                        </tr>
                                        <tr>
                                            <th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Status</th>
                                            <td id="infoStatus"></td>
                                        </tr>
                                        <tr>
                                            <th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Created At</th>
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
            <div class="modal-footer" style="background:#F8F9FA; border-top:1px solid #E9ECEF; padding:12px 24px;">
                <small class="text-muted mr-auto">
                    <i class="fas fa-info-circle mr-1"></i> Data read-only. Klik Edit untuk mengubah.
                </small>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Tutup
                </button>
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
.card-header .btn      { border-radius:6px; font-weight:600; }

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
.ts-control.focus { border-color:#80bdff !important; box-shadow:0 0 0 0.15rem rgba(0,123,255,.15) !important; }

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
    color:#6B7280; border:none;
    border-bottom:2px solid transparent;
    border-radius:0; padding:8px 16px;
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

    new TomSelect('#advancedFilter', {
        plugins: ['remove_button'], persist: false, create: false, placeholder: 'Filter data...'
    });

    $.fn.dataTable.ext.search.push(function(settings, data) {
        let filters = $('#advancedFilter').val();
        if (!filters || filters.length === 0) return true;

        let role   = data[3].replace(/<[^>]*>/g, '').trim();
        let group  = data[4].replace(/<[^>]*>/g, '').trim();
        let status = data[5].replace(/<[^>]*>/g, '').trim();

        let selRoles = [], selGroups = [], selStatuses = [];
        filters.forEach(function(f) {
            let p = f.split(':'), cat = p[0], val = p.slice(1).join(':');
            if (cat === 'ROLE')   selRoles.push(val);
            if (cat === 'GROUP')  selGroups.push(val);
            if (cat === 'STATUS') selStatuses.push(val);
        });

        if (selRoles.length > 0    && !selRoles.includes(role))     return false;
        if (selGroups.length > 0   && !selGroups.includes(group))    return false;
        if (selStatuses.length > 0 && !selStatuses.includes(status)) return false;
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
            title: 'Hapus User?', text: 'Data yang dihapus tidak dapat dikembalikan.',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#d33', cancelButtonColor: '#6c757d',
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
                                 style="background:#fff;border:1px solid #E5E7EB;gap:12px;">
                                <div style="width:36px;height:36px;background:#EFF6FF;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fas fa-project-diagram" style="color:#2563EB;font-size:14px;"></i>
                                </div>
                                <div>
                                    <div style="font-size:13px;font-weight:600;color:#1A202C;">${p.name}</div>
                                    <div style="font-size:11px;color:#6B7280;">${p.code ?? ''}</div>
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