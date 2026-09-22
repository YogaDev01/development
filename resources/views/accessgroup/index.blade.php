@extends('layouts.app')

@section('content')

<!-- MAIN CONTENT -->
<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ url('/user') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Access Group Master</h1>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Master</a></li>
                <li class="breadcrumb-item active">Access Group</li>
            </ol>
        </div>

        <x-stat-cards :cards="[
            ['Total Access Group', $summary['total'], 'primary', 'fas fa-users-cog'],
            ['Total PO Assignment', $summary['total_po'], 'info', 'fas fa-project-diagram'],
        ]" />

        <div class="card card-secondary card-outline shadow-sm">

            <!-- CARD HEADER -->
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-users-cog mr-1"></i> Daftar Access Group</h3>
                <div class="ml-auto d-flex" style="gap:8px;">
                    <button id="refreshTable" class="btn btn-secondary btn-sm" type="button">
                        <i class="fas fa-sync-alt mr-1"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- CARD BODY -->
            <div class="card-body">

                <!-- TOOLBAR -->
                <div class="custom-toolbar mb-3 d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
                    <div class="custom-toolbar-left d-flex align-items-center flex-wrap" style="gap:8px;">
                        <div class="toolbar-row">
                            <span class="toolbar-label">Cari :</span>
                            <div class="toolbar-input" id="customSearch"></div>
                        </div>
                        <button aria-label="Clear Filter" type="button" id="btnClearFilterAg" class="btn btn-outline-secondary btn-sm" title="Clear Filter">
                            <i class="fas fa-eraser"></i>
                        </button>
                        <div class="toolbar-row">
                            <span class="toolbar-label">Filter :</span>
                            <div class="toolbar-input">
                                <select id="advancedFilter" multiple placeholder="Filter PO...">
                                    @foreach(
                                        $accessGroups->pluck('details')->flatten()
                                            ->pluck('project.name')->filter()->unique()->sort()->values()
                                        as $projectName
                                    )
                                        <option value="{{ $projectName }}">{{ $projectName }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="custom-toolbar-right d-flex flex-wrap" style="gap:8px;">
                        <a href="{{ url('/accessgroup/create') }}" class="btn btn-success btn-sm">
                            <i class="fas fa-plus mr-1"></i> Tambah Access Group
                        </a>
                    </div>
                </div>

                <!-- TABLE -->
                <div class="table-responsive">
                    <table id="accessGroupTable" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" width="5%"  class="all">No</th>
                                <th scope="col" width="22%" class="all">Access Group</th>
                                <th scope="col" width="43%">PO Access</th>
                                <th scope="col" width="8%">Total PO</th>
                                <th scope="col" width="12%" class="text-center all action-column">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($accessGroups as $accessGroup)
                            <tr>
                                <td>{{ $loop->iteration }}</td>

                                <td><strong>{{ $accessGroup->name }}</strong></td>

                                <td>
                                    @php
                                        $projects     = $accessGroup->details;
                                        $firstThree   = $projects->take(3);
                                        $remaining    = $projects->count() - 3;
                                    @endphp
                                    <div class="d-flex flex-wrap align-items-center">
                                        @foreach($firstThree as $detail)
                                            <span class="badge badge-primary mr-1 mb-1 px-2 py-1">
                                                {{ $detail->project->name ?? '-' }}
                                            </span>
                                        @endforeach
                                        @if($remaining > 0)
                                            <button type="button"
                                                    class="btn btn-xs btn-light border mb-1 btn-detail"
                                                    data-id="{{ $accessGroup->id }}"
                                                    style="font-size:11px; padding:2px 8px;">
                                                +{{ $remaining }} lainnya
                                            </button>
                                        @endif
                                    </div>
                                </td>

                                <td>
                                    <span class="badge badge-secondary px-2 py-1">
                                        {{ $accessGroup->details->count() }} PO
                                    </span>
                                </td>

                                <td class="text-center action-column">
                                    <div class="action-buttons ts-actions">

                                        <a href="{{ url('/accessgroup/' . $accessGroup->id . '/edit') }}"
                                           class="ts-action-primary is-neutral"
                                           title="Edit" aria-label="Edit access group">
                                            <i class="fas fa-pen" aria-hidden="true"></i><span class="ts-al">Edit</span>
                                        </a>

                                        <button type="button" class="ts-action-more"
                                                aria-haspopup="true" aria-expanded="false"
                                                title="Aksi lain" aria-label="Aksi lain">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>

                                        <div class="ts-action-menu dropdown-menu" hidden>

                                            <button type="button" class="dropdown-item btn-detail" data-id="{{ $accessGroup->id }}">
                                                <i class="fas fa-eye"></i>Detail
                                            </button>

                                            <div class="dropdown-divider"></div>

                                            <form action="{{ url('/accessgroup/' . $accessGroup->id) }}"
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
                                    <x-empty title="Belum ada data access group" icon="fas fa-folder-open" compact />
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
<!-- MODAL DETAIL ACCESS GROUP                                         -->
<!-- ================================================================ -->
<div class="modal fade" id="modalDetail" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg); overflow:hidden;">

            <!-- HEADER -->
            <div class="modal-header" style="background:var(--charcoal); border-bottom:3px solid var(--accent); padding:16px 24px;">
                <div class="d-flex align-items-center">
                    <div style="width:36px;height:36px;background:var(--accent);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;margin-right:12px;flex-shrink:0;">
                        <i class="fas fa-users-cog" style="color:#fff;font-size:15px;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0" id="modalDetailTitle">Detail Access Group</h5>
                        <small id="modalDetailSubtitle" style="color:var(--text-faint);font-size:11px;"></small>
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
                    <p class="text-muted mt-3 mb-0">Memuat data...</p>
                </div>

                <!-- Content -->
                <div id="detailContent" style="display:none;">
                    <div class="row no-gutters">

                        <!-- ── LEFT PANEL ── -->
                        <div class="col-md-4" style="background:var(--charcoal); padding:28px 20px; min-height:420px;">

                            <div class="text-center mb-3">
                                <div style="width:72px;height:72px;background:var(--charcoal-2);border:3px solid var(--accent);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;">
                                    <i class="fas fa-users-cog" style="color:var(--accent);font-size:28px;"></i>
                                </div>
                                <h6 id="detailName" class="text-white mt-3 mb-0 font-weight-bold" style="font-size:14px;word-break:break-word;"></h6>
                            </div>

                            <hr style="border-color:rgba(255,255,255,0.08); margin:16px 0;">

                            <div class="detail-stat-item">
                                <span style="color:var(--text-faint);font-size:10px;letter-spacing:0.1em;text-transform:uppercase;font-weight:600;">Total PO Access</span>
                                <div id="detailProjectCount" style="color:var(--accent);font-size:28px;font-weight:700;margin-top:4px;"></div>
                            </div>
                            <div class="detail-stat-item mt-3">
                                <span style="color:var(--text-faint);font-size:10px;letter-spacing:0.1em;text-transform:uppercase;font-weight:600;">Total User</span>
                                <div id="detailUserCount" class="text-white mt-1" style="font-size:22px;font-weight:700;"></div>
                            </div>
                            <div class="detail-stat-item mt-3">
                                <span style="color:var(--text-faint);font-size:10px;letter-spacing:0.1em;text-transform:uppercase;font-weight:600;">Dibuat</span>
                                <div id="detailCreatedAt" class="text-white mt-1" style="font-size:12px;"></div>
                            </div>

                            <hr style="border-color:rgba(255,255,255,0.08); margin:20px 0 16px;">

                            <a id="detailEditBtn" href="#"
                               class="btn btn-block"
                               style="background:var(--accent);color:#fff;font-weight:700;font-size:13px;border-radius:var(--radius-sm);">
                                <i class="fas fa-edit mr-1"></i> Edit Access Group
                            </a>

                        </div>

                        <!-- ── RIGHT: TABS ── -->
                        <div class="col-md-8" style="padding:24px;">

                            <ul class="nav nav-tabs mb-3" id="detailTabs"
                                style="border-bottom:2px solid var(--border);">
                                <li class="nav-item">
                                    <a class="nav-link active" data-toggle="tab" href="#pane-projects"
                                       style="font-size:13px;font-weight:600;">
                                        <i class="fas fa-file-contract mr-1"></i> PO Access
                                        <span id="poCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#pane-users"
                                       style="font-size:13px;font-weight:600;">
                                        <i class="fas fa-users mr-1"></i> Users
                                        <span id="userCount" class="badge badge-secondary ml-1" style="font-size:10px;"></span>
                                    </a>
                                </li>
                            </ul>

                            <div class="tab-content">

                                <!-- TAB PO ACCESS -->
                                <div class="tab-pane fade show active" id="pane-projects">
                                    <div id="projectsBody"></div>
                                </div>

                                <!-- TAB USERS -->
                                <div class="tab-pane fade" id="pane-users">
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0" style="font-size:13px;">
                                            <thead style="background:var(--surface-2);">
                                                <tr>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-transform:uppercase;letter-spacing:0.05em;width:5%;">No</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Nama</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Email</th>
                                                    <th scope="col" style="color:var(--text-faint);font-size:11px;text-transform:uppercase;letter-spacing:0.05em;width:18%;">Role</th>
                                                </tr>
                                            </thead>
                                            <tbody id="usersBody"></tbody>
                                        </table>
                                    </div>
                                </div>

                            </div>

                        </div>

                    </div>
                </div>

            </div>

            <!-- FOOTER -->
            <div class="modal-footer" style="background:var(--surface-2); border-top:1px solid var(--border); padding:12px 24px;">
                <small class="text-muted mr-auto">
                    <i class="fas fa-info-circle mr-1"></i> Read-only. Klik Edit untuk mengubah.
                </small>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Tutup
                </button>
                <a id="detailEditBtnFooter" href="#" class="btn btn-warning btn-sm">
                    <i class="fas fa-edit mr-1"></i> Edit
                </a>
            </div>

        </div>
    </div>
</div>

@endsection

@push('styles')
<style>

.card-header .btn  { border-radius:var(--radius-sm); font-weight:600; }

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
#accessGroupTable   { width:100% !important; }
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
    #btnClearFilterAg { width:100%; }
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

    let table = $('#accessGroupTable').DataTable({
        responsive: { details: { type: 'inline' } },
        autoWidth: false, pageLength: 10, lengthChange: true,
        searching: true, ordering: true, orderCellsTop: true,
        columnDefs: [
            { responsivePriority: 1,   targets: [0, 1, -1] },
            { responsivePriority: 2,   targets: [3] },
            { responsivePriority: 100, targets: [2] }
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

    $('#accessGroupTable_filter').hide();

    $('#customSearch').html(`
        <input type="search" id="globalSearch" class="form-control form-control-sm" placeholder="Cari access group...">
    `);
    $('#globalSearch').on('keyup', function () { table.search(this.value).draw(); });

    new TomSelect('#advancedFilter', {
        plugins: ['remove_button'], persist: false, create: false, placeholder: 'Filter PO...'
    });

    $.fn.dataTable.ext.search.push(function(settings, data) {
        let filters = $('#advancedFilter').val();
        if (!filters || filters.length === 0) return true;
        let col = data[2].replace(/<[^>]*>/g, '').trim();
        let match = false;
        filters.forEach(function(p) { if (col.includes(p)) match = true; });
        return match;
    });

    $('#advancedFilter').on('change', function () { table.draw(); });

    $('#btnClearFilterAg').on('click', function () {
        $('#advancedFilter')[0].tomselect.clear();
        table.draw();
    });

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
            title: 'Hapus Access Group?',
            text: 'Semua user dalam group ini akan kehilangan akses.',
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
        $('#modalDetailTitle').text('Detail Access Group');
        $('#modalDetailSubtitle').text('');
        $('#modalDetail').modal('show');

        $.get('{{ url("/accessgroup") }}/' + id + '/detail-json', function (d) {

            /* LEFT */
            $('#detailName').text(d.name);
            $('#modalDetailTitle').text(d.name);
            $('#modalDetailSubtitle').text(d.project_count + ' PO · ' + d.user_count + ' User');
            $('#detailProjectCount').text(d.project_count);
            $('#detailUserCount').text(d.user_count);
            $('#detailCreatedAt').text(d.created_at);

            let editUrl = '{{ url("/accessgroup") }}/' + id + '/edit';
            $('#detailEditBtn').attr('href', editUrl);
            $('#detailEditBtnFooter').attr('href', editUrl);

            /* TAB PO ACCESS */
            $('#poCount').text(d.project_count);
            let poHtml = '';
            if (d.projects && d.projects.length) {
                poHtml = '<div class="d-flex flex-wrap" style="gap:8px;">';
                d.projects.forEach(function(p) {
                    poHtml += `
                        <div class="d-flex align-items-center px-3 py-2 rounded"
                             style="background:var(--info-bg);border:1px solid var(--border-strong);gap:8px;min-width:160px;">
                            <i class="fas fa-file-contract" style="color:var(--info);font-size:13px;flex-shrink:0;"></i>
                            <span style="font-size:13px;font-weight:600;color:#1e40af;">${p.name}</span>
                        </div>`;
                });
                poHtml += '</div>';
            } else {
                poHtml = '<div class="alert alert-warning mb-0"><i class="fas fa-exclamation-triangle mr-1"></i>Belum ada PO access.</div>';
            }
            $('#projectsBody').html(poHtml);

            /* TAB USERS */
            $('#userCount').text(d.user_count);
            let userHtml = '';
            if (d.users && d.users.length) {
                d.users.forEach(function(u, i) {
                    userHtml += `<tr>
                        <td class="text-center">${i+1}</td>
                        <td><strong>${u.name}</strong></td>
                        <td><small class="text-muted">${u.email}</small></td>
                        <td><span class="badge badge-info px-2 py-1">${u.role ?? '-'}</span></td>
                    </tr>`;
                });
            } else {
                userHtml = '<tr><td colspan="4" class="text-center text-muted py-3"><i class="fas fa-users d-block mb-1"></i>Belum ada user.</td></tr>';
            }
            $('#usersBody').html(userHtml);

            $('#detailTabs a:first').tab('show');
            $('#detailLoading').hide();
            $('#detailContent').show();

        }).fail(function () {
            $('#detailLoading').html(
                '<p class="text-danger py-4"><i class="fas fa-exclamation-triangle mr-1"></i>Gagal memuat data.</p>'
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