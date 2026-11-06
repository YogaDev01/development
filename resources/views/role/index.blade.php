@extends('layouts.app')

@section('title', 'Role & Menu Access')

@section('content')

<!-- MAIN CONTENT -->
<div class="content">
    <div class="container-fluid">

        <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        {{-- Judul & breadcrumb topbar: shell memindahkan <h1> dan .breadcrumb pertama ke topbar
             (tesys-shell.js hoistPageTitle); disembunyikan secara visual. --}}
        <div class="tw:sr-only">
            <h1>Role & Menu Master</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Master</li>
                <li class="breadcrumb-item active">Role</li>
            </ol>
        </div>

        <x-tw.page-header title="Daftar Role"
            description="Role pengguna dan pengaturan akses menu per role.">
            <x-slot:actions>
                <x-tw.button href="{{ url('/user') }}" variant="secondary"><i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali</x-tw.button>
                {{-- Tambah role (D3, 2026-11-07): route role.store, role:superadmin — sama dengan halaman ini. --}}
                <x-tw.button size="sm" data-toggle="modal" data-target="#modalTambahRole"><i class="fas fa-plus" aria-hidden="true"></i> Tambah Role</x-tw.button>
                <x-tw.button id="refreshTable" variant="secondary" size="sm"><i class="fas fa-sync-alt" aria-hidden="true"></i> Refresh</x-tw.button>
            </x-slot:actions>
        </x-tw.page-header>


        <x-stat-cards :cards="[
            ['Total Role', $summary['total'], 'primary', 'fas fa-user-tag'],
            ['Active', $summary['active'], 'success', 'fas fa-check-circle'],
            ['Inactive', $summary['inactive'], 'secondary', 'fas fa-pause-circle'],
        ]" />

        <!-- INFO ALERT -->
        <div class="alert alert-warning alert-dismissible mb-3">
            <i class="fas fa-info-circle mr-2"></i>
            Atur menu yang dapat diakses oleh setiap role. <strong>Superadmin</strong> selalu memiliki akses penuh dan tidak dapat diubah.
        </div>

        {{-- Kartu tunggal: toolbar + tabel + paginasi (pola Cost Request). --}}
        <x-tw.card padding="sm">


                <div class="custom-toolbar mb-3 d-flex align-items-center flex-wrap" style="gap:8px;">
                    <div class="toolbar-row">
                        <span class="toolbar-label">Cari :</span>
                        <div class="toolbar-input" id="customSearch"></div>
                    </div>
                    <button aria-label="Clear Filter" type="button" id="btnClearFilterRole" class="btn btn-outline-secondary btn-sm" title="Clear Filter">
                        <i class="fas fa-eraser"></i>
                    </button>
                    <div id="roleRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>
                </div>

                <div class="table-responsive">
                    <table id="roleTable" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" width="5%"  class="all">No</th>
                                <th scope="col" width="20%" class="all">Role</th>
                                <th scope="col" width="10%">Status</th>
                                <th scope="col" width="8%">Total User</th>
                                <th scope="col" width="8%">Menu Access</th>
                                <th scope="col" width="37%">Menu Yang Dapat Diakses</th>
                                <th scope="col" width="12%" class="text-center all action-column"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($roles as $role)
                            @php
                                $isSuper   = strtolower($role->name) === 'superadmin';
                                $menuCount = $isSuper ? $totalMenus : $role->menuItems->count();
                            @endphp
                            <tr data-status="{{ $role->status }}">
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
                                    <div class="action-buttons ts-actions">
                                        @if($isSuper)
                                        {{-- Superadmin tidak bisa diatur; tombolnya tetap dirender
                                             supaya tinggi dan perataan barisnya sama dengan baris
                                             lain, hanya dimatikan. --}}
                                        <button aria-label="Full Access" class="ts-action-primary is-neutral"
                                                disabled title="Full Access">
                                            <i class="fas fa-lock" aria-hidden="true"></i><span class="ts-al">Full Access</span>
                                        </button>
                                        @else
                                        <button type="button"
                                                class="ts-action-primary btn-configure"
                                                data-id="{{ $role->id }}"
                                                title="Atur Menu Access">
                                            <i class="fas fa-sliders-h" aria-hidden="true"></i><span class="ts-al">Atur Menu Access</span>
                                        </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7">
                                    <x-empty title="Belum ada data role" icon="fas fa-user-tag" compact />
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

<!-- ================================================================ -->
<!-- MODAL CONFIGURE MENU ACCESS                                       -->
<!-- ================================================================ -->
<div class="modal fade" id="modalConfigure" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg); overflow:hidden;">

            <div class="modal-header" style="background:var(--charcoal); border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-sliders-h mr-2" style="color:var(--accent);"></i>
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
                             style="background:var(--surface-2); border-bottom:1px solid var(--border);">
                            <div class="d-flex align-items-center" style="gap:8px;">
                                <input type="checkbox" id="selectAll">
                                <label for="selectAll" class="mb-0 font-weight-600" style="font-size:13px; cursor:pointer;">
                                    Pilih Semua Menu (seluruh Tesys)
                                </label>
                            </div>
                            <small id="selectedCount" class="text-muted">0 menu dipilih</small>
                        </div>

                        <!-- Menu sections -->
                        <div class="px-4 py-3" id="menuSections"></div>

                    </div>

                </div>

                <div class="modal-footer" style="background:var(--surface-2); border-top:1px solid var(--border);">
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

{{-- MODAL TAMBAH ROLE — role baru tidak punya akses menu apa pun (default deny). --}}
<div class="modal fade" id="modalTambahRole" tabindex="-1" role="dialog" aria-labelledby="modalTambahRoleTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg); overflow:hidden;">
            <form method="POST" action="{{ route('role.store') }}">
                @csrf
                <div class="modal-header" style="background:var(--charcoal); border-bottom:3px solid var(--accent);">
                    <h5 class="modal-title text-white" id="modalTambahRoleTitle">
                        <i class="fas fa-user-tag mr-2" aria-hidden="true"></i> Tambah Role
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-2">
                        <label for="newRoleName" class="font-weight-600">Nama Role</label>
                        <input type="text" name="name" id="newRoleName" class="form-control form-control-sm @error('name') is-invalid @enderror"
                               value="{{ old('name') }}" required maxlength="40" pattern="[a-z][a-z0-9_]{2,39}" autocomplete="off"
                               placeholder="mis. auditor_internal">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="form-text text-muted">Huruf kecil, angka, dan garis bawah; 3–40 karakter, diawali huruf.</small>
                    </div>
                    <div class="alert alert-warning mb-0" style="font-size:12px;">
                        <i class="fas fa-info-circle mr-1" aria-hidden="true"></i>
                        Role baru dibuat <strong>tanpa akses menu apa pun</strong> dan tidak mendapat kewenangan bawaan role lain.
                        Setelah dibuat, atur menunya lewat tombol <strong>Atur Menu Access</strong> di tabel.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1" aria-hidden="true"></i> Simpan Role</button>
                </div>
            </form>
        </div>
    </div>
</div>
@if($errors->has('name'))
    @push('scripts')
    <script>$(function () { $('#modalTambahRole').modal('show'); });</script>
    @endpush
@endif

@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
<style>

.card-header .btn  { border-radius:var(--radius-sm); font-weight:600; }
.font-weight-600   { font-weight:600; }

/* TOOLBAR */
.custom-toolbar      { display:flex; align-items:flex-start; justify-content:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; margin-bottom:0; white-space:nowrap; font-weight:600; color:var(--text-dim); }
.toolbar-input       { min-width:180px; }
#customSearch input  { width:180px; border-radius:var(--radius-sm); }

/* TABLE */
#roleTable          { width:100% !important; }
.table td,.table th { vertical-align:middle !important; }
.action-column      { white-space:nowrap !important; }
.action-buttons     { display:flex; align-items:center; justify-content:center; gap:4px; }

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

/* MODAL MENU ITEMS */
.menu-section-label {
    font-size:10px;
    font-weight:700;
    letter-spacing:0.12em;
    text-transform:uppercase;
    color:var(--text-faint);
    padding:12px 0 8px;
    border-bottom:1px solid var(--border);
    margin-bottom:10px;
}

.menu-section-label:not(:first-child) { margin-top:20px; }

.menu-check-item {
    display:flex;
    align-items:center;
    gap:10px;
    padding:8px 10px;
    border-radius:var(--radius-sm);
    cursor:pointer;
    transition:background 0.12s;
    margin-bottom:4px;
}

.menu-check-item:hover { background:var(--surface-2); }

.menu-check-item input[type="checkbox"] {
    width:16px; height:16px;
    accent-color:var(--info);
    flex-shrink:0; cursor:pointer;
}

.menu-check-item label {
    font-size:13px; font-weight:500;
    color:var(--text); margin:0; cursor:pointer; flex:1;
}

.menu-check-item.is-checked {
    background:var(--info-bg);
    border:1px solid var(--border-strong);
}

.menu-check-item.is-checked label { color:#1e40af; }

/* Satu blok per aplikasi Tesys (+ blok "Semua Tesys" untuk Master Data) */
.menu-group {
    border:1px solid var(--border);
    border-radius:var(--radius-sm);
    margin-bottom:16px;
    overflow:hidden;
}
.menu-group-head {
    display:flex; align-items:center; justify-content:space-between;
    padding:10px 14px;
    background:var(--surface-2);
    border-bottom:1px solid var(--border);
    font-size:13px; font-weight:700; color:var(--text);
}
.menu-group-head input[type="checkbox"] { width:16px; height:16px; accent-color:var(--info); cursor:pointer; }
.menu-group-note {
    padding:8px 14px 0;
    font-size:12px; color:var(--text-faint);
}
.menu-group-body { padding:4px 14px 8px; }
.menu-group-body .menu-section-label:first-child { margin-top:0; }

/* MOBILE */
@media (max-width: 768px) {
    .content-header .row { flex-direction:column; }
    .breadcrumb { float:left !important; margin-top:10px; }
    .card-header { flex-direction:column; gap:10px; align-items:stretch !important; }
    .card-header .btn { width:100%; }
    .card-header .card-title { margin-bottom:0; }
    .card-header .ml-auto { margin-left:0 !important; flex-wrap:wrap; }
    .custom-toolbar { flex-direction:column !important; align-items:stretch !important; }
    #btnClearFilterRole { width:100%; }
    #roleRowFilters { width:100%; }
    #roleRowFilters .tcf-row-btn { width:100%; }
    .toolbar-row { width:100%; }
    .toolbar-input { flex:1; min-width:0; }
    #customSearch input { width:100%; }
    .dataTables_wrapper .row:last-child { display:flex; flex-direction:column; align-items:flex-start !important; gap:12px; }
    .dataTables_wrapper .row:last-child .col-md-6 { width:100%; max-width:100%; display:flex; justify-content:flex-start !important; }
    .dataTables_paginate { width:100%; overflow-x:auto; }
    .dataTables_length { margin-left:0; }
}

#roleTable tbody tr:not(:has(td[colspan])) { cursor:pointer; }
</style>
@endpush

@push('scripts')
<script>

$(function () {

    /* Klik di mana saja pada baris membuka detail (atau edit bila tidak ada detail) — pola Cost
       Request. Kontrol di dalam baris (tombol, tautan, menu aksi, kontrol responsif) bekerja sendiri. */
    $(document).on('click', '#roleTable tbody tr', function (e) {
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

    $('#roleTable_filter').hide();
    $('#customSearch').html('<input type="search" id="globalSearch" class="form-control form-control-sm" placeholder="Cari data...">');
    $('#globalSearch').on('keyup', function () { table.search(this.value).draw(); });

    TesysColumnFilter.initRowFilters('#roleTable', '#roleRowFilters', [
        { key: 'status', label: 'Status' },
    ]);

    $('#btnClearFilterRole').on('click', function () {
        TesysColumnFilter.clearAll('#roleTable');
    });

    $('#refreshTable').on('click', function () { location.reload(); });

    /*
    |--------------------------------------------------------------------------
    | MODAL CONFIGURE
    |--------------------------------------------------------------------------
    */

    /*
     | Susunan checklist datang dari server (App\Support\Navigation::accessTree),
     | yang membacanya dari config/navigation.php — sumber yang sama dengan
     | sidebar. Tidak ada daftar seksi yang ditulis tangan di sini lagi.
     |
     | Satu menu boleh muncul di lebih dari satu aplikasi (mis. Project
     | Monitoring di Operations dan Project). Hak aksesnya tetap SATU baris,
     | jadi semua kotak dengan value yang sama selalu disamakan, dan hitungan
     | memakai id unik.
     */
    const CB = '#menuSections input[name="menu_item_ids[]"]';

    function esc(s) {
        return $('<div>').text(s == null ? '' : s).html();
    }

    function renderItems(items, selected) {
        let html = '<div class="row">';
        items.forEach(function (item) {
            let checked = selected.includes(item.id) ? 'checked' : '';
            let uid = 'm_' + item.id + '_' + Math.random().toString(36).slice(2, 7);
            html += '<div class="col-md-6"><div class="menu-check-item ' + (checked ? 'is-checked' : '') + '">' +
                '<input type="checkbox" name="menu_item_ids[]" value="' + item.id + '" id="' + uid + '" ' + checked + '>' +
                '<label for="' + uid + '">' +
                    '<i class="' + esc(item.icon) + ' mr-2 text-muted" style="font-size:12px;"></i>' + esc(item.label) +
                '</label>' +
            '</div></div>';
        });
        return html + '</div>';
    }

    function renderGroup(title, icon, sections, selected, note) {
        let html = '<div class="menu-group">' +
            '<div class="menu-group-head">' +
                '<label class="mb-0 d-flex align-items-center" style="gap:8px; cursor:pointer;">' +
                    '<input type="checkbox" class="group-select">' +
                    '<i class="' + esc(icon) + '" style="color:var(--accent);"></i>' +
                    '<span>' + esc(title) + '</span>' +
                '</label>' +
                '<small class="text-muted group-count"></small>' +
            '</div>';
        if (note) {
            html += '<div class="menu-group-note">' + note + '</div>';
        }
        html += '<div class="menu-group-body">';
        sections.forEach(function (sec) {
            html += '<div class="menu-section-label">' + esc(sec.label) + '</div>';
            html += renderItems(sec.items, selected);
        });
        return html + '</div></div>';
    }

    function uniqueChecked($scope) {
        let ids = {};
        $scope.find('input[name="menu_item_ids[]"]:checked').each(function () { ids[this.value] = 1; });
        return Object.keys(ids).length;
    }

    function uniqueTotal($scope) {
        let ids = {};
        $scope.find('input[name="menu_item_ids[]"]').each(function () { ids[this.value] = 1; });
        return Object.keys(ids).length;
    }

    function updateCount() {
        let $all = $('#menuSections');
        let count = uniqueChecked($all), total = uniqueTotal($all);
        $('#selectedCount').text(count + ' / ' + total + ' menu dipilih');
        $('#selectAll').prop('indeterminate', count > 0 && count < total);
        $('#selectAll').prop('checked', count === total && total > 0);

        $('#menuSections .menu-group').each(function () {
            let $g = $(this);
            let c = uniqueChecked($g), t = uniqueTotal($g);
            $g.find('.group-count').text(c + ' / ' + t);
            $g.find('.group-select').prop('indeterminate', c > 0 && c < t).prop('checked', c === t && t > 0);
        });

        $(CB).each(function () {
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

            let tree = d.tree || {};
            let html = '';

            if ((tree.shared || []).length) {
                html += renderGroup('Semua Tesys', 'fas fa-globe', tree.shared, d.selected_ids,
                    '<i class="fas fa-info-circle mr-1"></i>Master Data berlaku di <strong>semua aplikasi</strong>: ' +
                    'menu yang dicentang di sini muncul di sidebar Master Data setiap Tesys.');
            }
            (tree.apps || []).forEach(function (app) {
                html += renderGroup(app.label, app.icon, app.sections, d.selected_ids);
            });
            if ((tree.others || []).length) {
                html += renderGroup('Lainnya', 'fas fa-ellipsis-h',
                    [{ label: 'Belum dipetakan ke aplikasi', items: tree.others }], d.selected_ids);
            }

            $('#menuSections').html(html);
            updateCount();
            $('#configLoading').hide();
            $('#configContent').show();
        }).fail(function () {
            $('#configLoading').html('<p class="text-danger text-center py-4"><i class="fas fa-exclamation-triangle mr-1"></i>Gagal memuat data.</p>');
        });
    });

    // Menu yang tampil di beberapa aplikasi: samakan semua kotaknya.
    $(document).on('change', CB, function () {
        $(CB + '[value="' + this.value + '"]').prop('checked', this.checked);
        updateCount();
    });

    // Pilih semua per aplikasi.
    $(document).on('change', '#menuSections .group-select', function () {
        let on = this.checked;
        $(this).closest('.menu-group').find('input[name="menu_item_ids[]"]').each(function () {
            $(CB + '[value="' + this.value + '"]').prop('checked', on);
        });
        updateCount();
    });

    $('#selectAll').on('change', function () {
        $(CB).prop('checked', $(this).is(':checked'));
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
                if (window.tsToast) window.tsToast('success', 'Menu access berhasil disimpan.');
                setTimeout(() => location.reload(), 2600);
            },
            error: function (xhr) {
                Swal.fire({ icon: 'error', title: 'Gagal menyimpan',
                    text: xhr.responseJSON?.message ?? 'Terjadi kesalahan.', confirmButtonColor: '#9a3030' });
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