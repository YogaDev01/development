@extends('layouts.app')

@section('title', 'Role & Menu Access')

@section('content')

<!-- CONTENT HEADER -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6 d-flex align-items-center">
                <a href="{{ url('/user') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="m-0 font-weight-bold">Role & Menu Master</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="#">Master</a></li>
                    <li class="breadcrumb-item active">Role</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="content">
    <div class="container-fluid">

        <!-- INFO ALERT -->
        <div class="alert alert-warning alert-dismissible mb-3">
            <i class="fas fa-info-circle mr-2"></i>
            Atur menu yang dapat diakses oleh setiap role. <strong>Superadmin</strong> selalu memiliki akses penuh dan tidak dapat diubah.
        </div>

        <div class="card card-primary card-outline shadow-sm">

            <!-- CARD HEADER -->
            <div class="card-header d-flex align-items-center">
                <button id="refreshTable" class="btn btn-secondary btn-sm" type="button">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
            </div>

            <!-- CARD BODY -->
            <div class="card-body">

                <div class="table-responsive">
                    <table id="roleTable" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th width="5%"  class="all">No</th>
                                <th width="20%" class="all">Role</th>
                                <th width="10%">Status</th>
                                <th width="8%">Total User</th>
                                <th width="8%">Menu Access</th>
                                <th width="37%">Menu Yang Dapat Diakses</th>
                                <th width="12%" class="text-center all action-column">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($roles as $role)
                            @php
                                $isSuper   = strtolower($role->name) === 'superadmin';
                                $menuCount = $isSuper ? $totalMenus : $role->menuItems->count();
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>

                                <td>
                                    <div class="d-flex align-items-center" style="gap:8px;">
                                        @if($isSuper)
                                            <span class="badge badge-warning px-2 py-1">
                                                <i class="fas fa-crown mr-1"></i>
                                                {{ $role->name }}
                                            </span>
                                        @else
                                            <span class="badge badge-info px-2 py-1">
                                                <i class="fas fa-user-tag mr-1"></i>
                                                {{ $role->name }}
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                <td>
                                    @if($role->status === 'ACTIVE')
                                        <span class="badge badge-success px-2 py-1">ACTIVE</span>
                                    @else
                                        <span class="badge badge-danger px-2 py-1">INACTIVE</span>
                                    @endif
                                </td>

                                <td class="text-center">
                                    <span class="badge badge-secondary px-2 py-1">
                                        {{ $role->users->count() }}
                                    </span>
                                </td>

                                <td class="text-center">
                                    @if($isSuper)
                                        <span class="badge badge-warning px-2 py-1">
                                            {{ $totalMenus }} / {{ $totalMenus }}
                                        </span>
                                    @else
                                        <span class="badge badge-{{ $menuCount > 0 ? 'primary' : 'danger' }} px-2 py-1">
                                            {{ $menuCount }} / {{ $totalMenus }}
                                        </span>
                                    @endif
                                </td>

                                <td>
                                    @if($isSuper)
                                        <span class="badge badge-warning px-2 py-1">
                                            <i class="fas fa-infinity mr-1"></i> Full Access
                                        </span>
                                    @elseif($role->menuItems->count() === 0)
                                        <span class="text-danger" style="font-size:12px;">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>Belum ada menu access
                                        </span>
                                    @else
                                        <div class="d-flex flex-wrap" style="gap:4px;">
                                            @foreach($role->menuItems->take(6) as $menu)
                                                <span class="badge badge-light border px-2 py-1" style="font-size:11px;">
                                                    {{ $menu->label }}
                                                </span>
                                            @endforeach
                                            @if($role->menuItems->count() > 6)
                                                <span class="badge badge-primary px-2 py-1" style="font-size:11px;">
                                                    +{{ $role->menuItems->count() - 6 }} lagi
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </td>

                                <td class="text-center action-column">
                                    @if($isSuper)
                                        <button class="btn btn-secondary btn-sm" disabled title="Full Access">
                                            <i class="fas fa-lock"></i>
                                        </button>
                                    @else
                                        <button type="button"
                                                class="btn btn-primary btn-sm btn-configure"
                                                data-id="{{ $role->id }}"
                                                title="Atur Menu Access">
                                            <i class="fas fa-sliders-h"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-user-tag d-block mb-2"></i>
                                    Belum ada data role
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
<!-- MODAL CONFIGURE MENU ACCESS                                       -->
<!-- ================================================================ -->
<div class="modal fade" id="modalConfigure" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:12px; overflow:hidden;">

            <div class="modal-header" style="background:#0D1B2A; border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white">
                    <i class="fas fa-sliders-h mr-2" style="color:#F5A623;"></i>
                    <span id="modalTitle">Atur Menu Access</span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <form id="formConfigure" method="POST">
                @csrf
                @method('PUT')

                <div class="modal-body p-0" style="max-height:70vh; overflow-y:auto;">

                    <!-- Loading -->
                    <div id="configLoading" class="text-center py-5">
                        <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                        <p class="text-muted mt-3 mb-0">Memuat data...</p>
                    </div>

                    <!-- Content -->
                    <div id="configContent" style="display:none;">

                        <!-- Select All bar -->
                        <div class="d-flex align-items-center justify-content-between px-4 py-3"
                             style="background:#f8f9fa; border-bottom:1px solid #e9ecef;">
                            <div class="d-flex align-items-center" style="gap:8px;">
                                <input type="checkbox" id="selectAll">
                                <label for="selectAll" class="mb-0 font-weight-600" style="font-size:13px; cursor:pointer;">
                                    Pilih Semua Menu
                                </label>
                            </div>
                            <small id="selectedCount" class="text-muted">0 menu dipilih</small>
                        </div>

                        <!-- Menu sections -->
                        <div class="px-4 py-3" id="menuSections"></div>

                    </div>

                </div>

                <div class="modal-footer" style="background:#f8f9fa; border-top:1px solid #e9ecef;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnSimpan">
                        <i class="fas fa-save mr-1"></i> Simpan Menu Access
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

@endsection

@push('styles')
<style>

.card-header .btn  { border-radius:6px; font-weight:600; }
.font-weight-600   { font-weight:600; }

/* TABLE */
#roleTable          { width:100% !important; }
.table td,.table th { vertical-align:middle !important; }
.action-column      { white-space:nowrap !important; }
.action-buttons     { display:flex; align-items:center; justify-content:center; gap:4px; }

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

/* MODAL MENU ITEMS */
.menu-section-label {
    font-size:10px;
    font-weight:700;
    letter-spacing:0.12em;
    text-transform:uppercase;
    color:#9CA3AF;
    padding:12px 0 8px;
    border-bottom:1px solid #F3F4F6;
    margin-bottom:10px;
}

.menu-section-label:not(:first-child) { margin-top:20px; }

.menu-check-item {
    display:flex;
    align-items:center;
    gap:10px;
    padding:8px 10px;
    border-radius:6px;
    cursor:pointer;
    transition:background 0.12s;
    margin-bottom:4px;
}

.menu-check-item:hover { background:#F0F2F5; }

.menu-check-item input[type="checkbox"] {
    width:16px; height:16px;
    accent-color:#2563EB;
    flex-shrink:0; cursor:pointer;
}

.menu-check-item label {
    font-size:13px; font-weight:500;
    color:#374151; margin:0; cursor:pointer; flex:1;
}

.menu-check-item.is-checked {
    background:#EFF6FF;
    border:1px solid #BFDBFE;
}

.menu-check-item.is-checked label { color:#1E40AF; }

/* MOBILE */
@media (max-width: 768px) {
    .content-header .row { flex-direction:column; }
    .breadcrumb { float:left !important; margin-top:10px; }
    .card-header { flex-direction:column; gap:10px; align-items:stretch !important; }
    .card-header .btn { width:100%; }
    .dataTables_wrapper .row:last-child { display:flex; flex-direction:column; align-items:flex-start !important; gap:12px; }
    .dataTables_wrapper .row:last-child .col-md-6 { width:100%; max-width:100%; display:flex; justify-content:flex-start !important; }
    .dataTables_paginate { width:100%; overflow-x:auto; }
    .dataTables_length { margin-left:0; }
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

    let table = $('#roleTable').DataTable({
        responsive: { details: { type: 'inline' } },
        autoWidth: false, pageLength: 10, lengthChange: true,
        searching: true, ordering: true, orderCellsTop: true,
        columnDefs: [
            { responsivePriority: 1,   targets: [0, 1, -1] },
            { responsivePriority: 2,   targets: [2, 3] },
            { responsivePriority: 100, targets: [4, 5] }
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

    $('#refreshTable').on('click', function () { location.reload(); });

    /*
    |--------------------------------------------------------------------------
    | MODAL CONFIGURE
    |--------------------------------------------------------------------------
    */

    const sectionLabels = {
        'dashboard': { label: 'Dashboard',          icon: 'fas fa-tachometer-alt' },
        'master':    { label: 'Master Data',         icon: 'fas fa-database'       },
        'finance':   { label: 'Finance Management',  icon: 'fas fa-money-bill-wave'},
        'project':   { label: 'Project Management',  icon: 'fas fa-concierge-bell' },
    };

    function updateCount() {
        let count = $('#menuSections input[name="menu_item_ids[]"]:checked').length;
        let total = $('#menuSections input[name="menu_item_ids[]"]').length;
        $('#selectedCount').text(count + ' menu dipilih');
        $('#selectAll').prop('indeterminate', count > 0 && count < total);
        $('#selectAll').prop('checked', count === total && total > 0);
        $('#menuSections input[name="menu_item_ids[]"]').each(function () {
            $(this).closest('.menu-check-item').toggleClass('is-checked', $(this).is(':checked'));
        });
    }

    $(document).on('click', '.btn-configure', function () {
        let id = $(this).data('id');

        $('#configLoading').show();
        $('#configContent').hide();
        $('#modalTitle').text('Atur Menu Access');
        $('#modalConfigure').modal('show');

        $.get('{{ url("/role") }}/' + id + '/edit-data', function (d) {
            $('#modalTitle').text('Atur Menu Access — ' + d.role.name);
            $('#formConfigure').attr('action', '{{ url("/role") }}/' + d.role.id);

            const sectionOrder = ['dashboard', 'master', 'finance', 'project'];
            let html = '';

            sectionOrder.forEach(function (section) {
                if (!d.menu_items[section]) return;
                let sec = sectionLabels[section] || { label: section, icon: 'fas fa-circle' };
                html += '<div class="menu-section-label"><i class="' + sec.icon + ' mr-2"></i>' + sec.label + '</div>';
                html += '<div class="row">';
                d.menu_items[section].forEach(function (item) {
                    let checked = d.selected_ids.includes(item.id) ? 'checked' : '';
                    html += '<div class="col-md-6"><div class="menu-check-item ' + (checked ? 'is-checked' : '') + '">' +
                        '<input type="checkbox" name="menu_item_ids[]" value="' + item.id + '" id="m_' + item.id + '" ' + checked + '>' +
                        '<label for="m_' + item.id + '">' +
                            '<i class="' + item.icon + ' mr-2 text-muted" style="font-size:12px;"></i>' + item.label +
                        '</label>' +
                    '</div></div>';
                });
                html += '</div>';
            });

            $('#menuSections').html(html);
            updateCount();
            $('#configLoading').hide();
            $('#configContent').show();
        }).fail(function () {
            $('#configLoading').html('<p class="text-danger text-center py-4"><i class="fas fa-exclamation-triangle mr-1"></i>Gagal memuat data.</p>');
        });
    });

    $(document).on('change', '#menuSections input[name="menu_item_ids[]"]', updateCount);

    $('#selectAll').on('change', function () {
        $('#menuSections input[name="menu_item_ids[]"]').prop('checked', $(this).is(':checked'));
        updateCount();
    });

    $('#formConfigure').on('submit', function (e) {
        e.preventDefault();
        let btn = $('#btnSimpan');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

        $.ajax({
            url: $(this).attr('action'),
            method: 'POST',
            data: $(this).serialize(),
            success: function () {
                $('#modalConfigure').modal('hide');
                Swal.fire({ toast: true, position: 'top-end', icon: 'success',
                    title: 'Menu access berhasil disimpan.',
                    showConfirmButton: false, timer: 2500, timerProgressBar: true });
                setTimeout(() => location.reload(), 2600);
            },
            error: function (xhr) {
                Swal.fire({ icon: 'error', title: 'Gagal menyimpan',
                    text: xhr.responseJSON?.message ?? 'Terjadi kesalahan.', confirmButtonColor: '#d33' });
            },
            complete: function () {
                btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan Menu Access');
            }
        });
    });

    $('#modalConfigure').on('hidden.bs.modal', function () {
        $('#configContent').hide();
        $('#configLoading').show();
        $('#menuSections').html('');
    });

});

</script>
@endpush