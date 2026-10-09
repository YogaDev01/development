{{--
    Halaman daftar + modal tambah/edit bersama untuk master data sederhana.
    Konfigurasi ($m) berasal dari Concerns\ManagesMasterRecords::master().
--}}
@extends('layouts.app')

@section('title', $m['title'])

@php
    $fields = collect($m['fields'])->map(function ($f) {
        if (isset($f['options']) && $f['options'] instanceof \Closure) {
            $f['options'] = ($f['options'])();
        }
        return $f;
    })->all();

    $numbered = $m['numbered'] ?? true;
    $offset   = $numbered ? 1 : 0;

    // Indeks kolom DataTables per key + visibilitas default untuk pemilih kolom.
    $colIndex = [];
    $colDefault = [];
    foreach ($m['columns'] as $i => $col) {
        if (!empty($col['key'])) {
            $colIndex[$col['key']]   = $i + $offset;
            $colDefault[$col['key']] = $col['default'] ?? true;
        }
    }

    $badgeColors = [
        'Active' => 'success', 'Inactive' => 'danger',
        'Installed' => 'success', 'Faulty' => 'danger', 'Warehouse' => 'info',
        'Returned' => 'warning', 'Unknown' => 'secondary',
    ];

@endphp

@section('content')
<div class="content master-page">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0" style="font-size:20px;font-weight:700;color:var(--text);">{{ $m['title'] }}</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Master</a></li>
                <li class="breadcrumb-item active">{{ $m['singular'] }}</li>
            </ol>
        </div>

        @if(!empty($m['tabs']))
            <ul class="nav nav-pills master-tabs mb-3">
                @foreach($m['tabs'] as $tab)
                    <li class="nav-item">
                        <a class="nav-link {{ $tab['active'] ? 'active' : '' }}" href="{{ url($tab['url']) }}">{{ $tab['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        @endif

        <x-stat-cards :cards="array_merge(
            [['Total ' . $m['singular'], $total, 'primary', $m['icon']]],
            isset($m['cards']) ? ($m['cards'])() : []
        )" />

        @if(session('import_errors'))
            <div class="alert alert-danger py-2" style="font-size:13px;">
                <strong><i class="fas fa-exclamation-triangle mr-1"></i> Kesalahan import (perbaiki file lalu import ulang):</strong>
                <ul class="mb-0 mt-1 pl-3" style="max-height:220px;overflow:auto;">
                    @foreach(session('import_errors') as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($truncated)
            <div class="alert alert-warning py-2" style="font-size:13px;">
                <i class="fas fa-info-circle mr-1"></i>
                Menampilkan {{ number_format($rows->count()) }} data terbaru dari {{ number_format($total) }}. Gunakan pencarian untuk mempersempit.
            </div>
        @endif

        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="{{ $m['icon'] }} mr-1"></i> Daftar {{ $m['singular'] }}</h3>
                <div class="ml-auto d-flex" style="gap:8px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="location.reload()">
                        <i class="fas fa-sync-alt mr-1"></i> Refresh
                    </button>
                </div>
            </div>

            <div class="card-body">
                @if(!empty($m['note']))
                    <p class="text-muted mb-3" style="font-size:12px;"><i class="fas fa-info-circle mr-1"></i>{{ $m['note'] }}</p>
                @endif

                <div class="custom-toolbar mb-3 d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
                    <div class="custom-toolbar-left">
                        <div class="toolbar-row">
                            <span class="toolbar-label">Cari :</span>
                            <div class="toolbar-input">
                                <input type="search" id="masterSearch" class="form-control form-control-sm" placeholder="Cari data...">
                            </div>
                        </div>
                        @if(!empty($m['filters']))
                            <button type="button" id="btnClearFilterMaster" class="btn btn-outline-secondary btn-sm" title="Clear Filter" aria-label="Clear Filter">
                                <i class="fas fa-eraser"></i>
                            </button>
                            <div id="masterRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>
                        @endif
                        @if(!empty($m['column_picker']))
                            <div class="dropdown" id="masterColVis">
                                <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-toggle="dropdown" data-display="static">
                                    <i class="fas fa-columns mr-1"></i> Kolom
                                </button>
                                <div class="dropdown-menu p-2" style="min-width:200px;max-height:340px;overflow-y:auto;">
                                    @foreach($m['columns'] as $i => $col)
                                        @continue(empty($col['key']))
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input master-col-toggle" id="colVis_{{ $col['key'] }}" data-key="{{ $col['key'] }}">
                                            <label class="custom-control-label" for="colVis_{{ $col['key'] }}" style="font-size:12px;">{{ $col['label'] }}</label>
                                        </div>
                                    @endforeach
                                    <div class="dropdown-divider"></div>
                                    <button type="button" class="btn btn-link btn-sm p-0" id="btnColVisReset" style="font-size:12px;">Kembalikan kolom default</button>
                                </div>
                            </div>
                        @endif
                        @if(!empty($m['hide_inactive']))
                            <div class="custom-control custom-switch ml-1">
                                <input type="checkbox" class="custom-control-input" id="chkShowInactive">
                                <label class="custom-control-label" for="chkShowInactive" style="font-size:12px;">Tampilkan Inactive</label>
                            </div>
                        @endif
                    </div>
                    <div class="custom-toolbar-right d-flex flex-wrap" style="gap:8px;">
                        @if(!empty($m['import']))
                            <a href="{{ url($m['url'] . '/import-template') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-file-download mr-1"></i> Template
                            </a>
                            <button type="button" class="btn btn-outline-success btn-sm" data-toggle="modal" data-target="#mdlImportMaster">
                                <i class="fas fa-file-excel mr-1"></i> Import Excel
                            </button>
                        @endif
                        @if(!empty($m['create_url']))
                            <a href="{{ url($m['create_url']) }}" class="btn btn-success btn-sm">
                                <i class="fas fa-plus mr-1"></i> Tambah {{ $m['singular'] }}
                            </a>
                        @else
                            <button type="button" class="btn btn-success btn-sm" id="btnTambahMaster">
                                <i class="fas fa-plus mr-1"></i> Tambah {{ $m['singular'] }}
                            </button>
                        @endif
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="tblMaster" class="table table-bordered table-hover table-striped mb-0 {{ !empty($m['compact']) ? 'master-compact' : '' }}">
                        <thead class="bg-light">
                            <tr>
                                @if($numbered)
                                    <th scope="col" width="5%">No</th>
                                @endif
                                @foreach($m['columns'] as $col)
                                    <th scope="col" class="{{ !empty($col['truncate']) ? 'col-trunc' : '' }}">{{ $col['label'] }}</th>
                                @endforeach
                                <th scope="col" class="text-center col-action">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                <tr @foreach($m['filters'] ?? [] as $flt) data-{{ $flt['key'] }}="{{ $flt['value']($row) }}" @endforeach
                                    @if(!empty($m['hide_inactive'])) data-row-status="{{ $row->status }}" @endif>
                                    @if($numbered)
                                        <td>{{ $loop->iteration }}</td>
                                    @endif
                                    @foreach($m['columns'] as $col)
                                        @php $val = $col['value']($row); @endphp
                                        <td class="{{ !empty($col['truncate']) ? 'col-trunc' : '' }}">
                                            @if(!empty($col['truncate']))
                                                <span class="cell-trunc" style="max-width:{{ (int) $col['truncate'] }}ch;" title="{{ $val }}">{{ $val ?? '-' }}</span>
                                            @elseif(!empty($col['badge']))
                                                <span class="badge badge-{{ $badgeColors[$val] ?? 'secondary' }} px-2 py-1">{{ $val }}</span>
                                            @elseif(!empty($col['strong']))
                                                <strong>{{ $val }}</strong>
                                            @else
                                                {{ $val ?? '-' }}
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="text-center action-column">
                                        <div class="action-buttons ts-actions">
                                            @if(!empty($m['drawer']))
                                                <button type="button" class="ts-action-primary is-neutral btn-drawer-master" data-id="{{ $row->id }}" title="Detail" aria-label="Detail">
                                                    <i class="fas fa-eye" aria-hidden="true"></i><span class="ts-al">Detail</span>
                                                </button>
                                            @elseif(!empty($m['show']))
                                                <a href="{{ url($m['url'] . '/' . $row->id) }}" class="ts-action-primary is-neutral" title="Detail" aria-label="Detail">
                                                    <i class="fas fa-eye" aria-hidden="true"></i><span class="ts-al">Detail</span>
                                                </a>
                                            @endif
                                            @if(!empty($m['edit_url']))
                                                <a href="{{ url(str_replace('{id}', $row->id, $m['edit_url'])) }}" class="ts-action-primary is-neutral" title="Edit" aria-label="Edit">
                                                    <i class="fas fa-pen" aria-hidden="true"></i><span class="ts-al">Edit</span>
                                                </a>
                                            @else
                                                <button type="button" class="ts-action-primary is-neutral btn-edit-master" data-id="{{ $row->id }}" title="Edit" aria-label="Edit">
                                                    <i class="fas fa-pen" aria-hidden="true"></i><span class="ts-al">Edit</span>
                                                </button>
                                            @endif
                                            <form action="{{ url($m['url'] . '/' . $row->id) }}" method="POST" class="frmDeleteMaster d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="ts-action-primary is-danger" title="Hapus" aria-label="Hapus">
                                                    <i class="fas fa-trash" aria-hidden="true"></i><span class="ts-al">Hapus</span>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- MODAL TAMBAH / EDIT --}}
<div class="modal fade" id="mdlMaster" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog {{ $m['modal'] ?? '' }}" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="{{ $m['icon'] }} mr-2" style="color:var(--accent);"></i>
                    <span id="mdlMasterTitle">Tambah {{ $m['singular'] }}</span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <form id="frmMaster" action="{{ url($m['url']) }}" method="POST" novalidate>
                @csrf
                <input type="hidden" name="_method" value="PUT" id="frmMasterMethod" disabled>
                <input type="hidden" name="_mode" id="frmMasterMode" value="create">
                <input type="hidden" name="_id" id="frmMasterId" value="">

                <div class="modal-body" style="padding:24px 28px;">
                    @if($errors->any() && old('_mode'))
                        <div class="alert alert-danger py-2" style="font-size:13px;">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Periksa kembali isian yang ditandai.
                        </div>
                    @endif

                    <div id="frmMasterLoading" class="text-center py-4" style="display:none;">
                        <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                        <p class="text-muted mt-2 mb-0">Memuat data...</p>
                    </div>

                    <div id="frmMasterFields" class="row">
                        @foreach($fields as $name => $f)
                            @include('master-data._field', ['name' => $name, 'f' => $f, 'first' => $loop->first])
                        @endforeach
                    </div>
                </div>

                <div class="modal-footer" style="background:var(--surface-2);border-top:1px solid var(--border);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times mr-1"></i> Batal</button>
                    <button type="submit" class="btn btn-success" id="btnSaveMaster"><i class="fas fa-save mr-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@if(!empty($m['import']))
<div class="modal fade" id="mdlImportMaster" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white"><i class="fas fa-file-excel mr-2" style="color:var(--accent);"></i> Import {{ $m['singular'] }}</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="{{ url($m['url'] . '/import') }}" method="POST" enctype="multipart/form-data" id="frmImportMaster">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <ol class="pl-3 mb-3" style="font-size:13px;color:var(--text-dim);">
                        <li>Unduh <a href="{{ url($m['url'] . '/import-template') }}">template Excel</a>, isi mulai baris ke-2 di sheet <strong>Data</strong> (hapus baris contoh).</li>
                        <li>Nilai yang sah ada di sheet <strong>Referensi</strong>.</li>
                        <li>Bila ada satu baris salah, <strong>tidak ada data yang disimpan</strong> dan daftar kesalahan ditampilkan.</li>
                    </ol>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold" for="importFile">File Excel (.xlsx / .xls, maks 5 MB, maks 1.000 baris) <span class="text-danger">*</span></label>
                        <input type="file" name="file" id="importFile" accept=".xlsx,.xls" required
                               class="form-control-file @error('file') is-invalid @enderror">
                        @error('file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);border-top:1px solid var(--border);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times mr-1"></i> Batal</button>
                    <button type="submit" class="btn btn-success" id="btnImportMaster"><i class="fas fa-upload mr-1"></i> Import</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@if(!empty($m['drawer']))
    @push('scripts')
    <x-drawer id="masterDrawer" label="Detail {{ $m['singular'] }}" width="640px">
        <div id="masterDrawerLoading" class="text-center py-5 text-muted">
            <i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2 mb-0">Memuat data...</p>
        </div>
        <div id="masterDrawerContent" hidden></div>
    </x-drawer>
    <script>
    $(document).on('click', '.btn-drawer-master', function () {
        var id = $(this).data('id');
        $('#masterDrawerContent').attr('hidden', true).empty();
        $('#masterDrawerLoading').html('<i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2 mb-0">Memuat data...</p>').show();
        $('#masterDrawer-eyebrow').text('');
        $('#masterDrawer-title').text('Memuat…');
        TesysDrawer.open('masterDrawer', this);

        $.get(@json(url($m['url'])) + '/' + id + '/drawer')
            .done(function (html) {
                var $c = $('#masterDrawerContent').html(html);
                var $meta = $c.find('[data-drawer-meta]');
                $('#masterDrawer-eyebrow').text($meta.data('eyebrow') || '');
                $('#masterDrawer-title').text($meta.data('title') || '');
                $('#masterDrawerLoading').hide();
                $c.removeAttr('hidden');
            })
            .fail(function () {
                $('#masterDrawer-title').text('Gagal memuat');
                $('#masterDrawerLoading').html('<p class="text-danger mb-0"><i class="fas fa-exclamation-triangle mr-1"></i>Data tidak dapat dimuat.</p>');
            });
    });
    </script>
    @endpush
@endif

@include('master-data._styles')

@push('scripts')
<script>
$(function () {
    var baseUrl  = @json(url($m['url']));
    var singular = @json($m['singular']);
    var fieldNames = @json(array_keys($fields));
    var $form = $('#frmMaster');

    // Tabel diinisialisasi paling akhir: galat di sini tidak boleh mematikan tombol & modal.
    function initTable() {
        var table = $('#tblMaster').DataTable({
            responsive: {{ empty($m['compact']) ? 'true' : 'false' }}, autoWidth: false, pageLength: 10, ordering: true,
            lengthMenu: [10, 25, 50, 100],
            order: [],
            columnDefs: [{ orderable: false, targets: -1 }],
            dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex flex-wrap justify-content-between justify-content-md-end align-items-center gap-2"lp>>',
            language: {
                lengthMenu: 'Page Size _MENU_', zeroRecords: 'Data tidak ditemukan',
                emptyTable: @json('Belum ada data ' . strtolower($m['singular'])),
                info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data', infoEmpty: 'Tidak ada data',
                paginate: { previous: 'Previous', next: 'Next' }
            }
        });
        $('#masterSearch').on('keyup search', function () { table.search(this.value).draw(); });

        @if(!empty($m['column_picker']))
            // Pilihan kolom per pengguna disimpan di browser (pola sama dengan PO/Invoice).
            var colIndex   = @json($colIndex);
            var colDefault = @json($colDefault);
            var colKey     = 'tesys-colvis:' + @json($m['column_picker']);
            function readPref() {
                try { return JSON.parse(localStorage.getItem(colKey) || '{}') || {}; } catch (e) { return {}; }
            }
            function writePref(p) {
                try { localStorage.setItem(colKey, JSON.stringify(p)); } catch (e) {}
            }
            function applyColVis() {
                var pref = readPref();
                Object.keys(colIndex).forEach(function (k) {
                    var vis = pref.hasOwnProperty(k) ? !!pref[k] : !!colDefault[k];
                    table.column(colIndex[k]).visible(vis, false);
                    $('#colVis_' + k).prop('checked', vis);
                });
                table.columns.adjust().draw(false);
            }
            applyColVis();
            $('.master-col-toggle').on('change', function () {
                var pref = readPref();
                pref[$(this).data('key')] = this.checked;
                writePref(pref);
                applyColVis();
            });
            $('#btnColVisReset').on('click', function () { writePref({}); applyColVis(); });
            $('#masterColVis .dropdown-menu').on('click', function (e) { e.stopPropagation(); });
        @endif

        @if(!empty($m['hide_inactive']))
            // Baris INACTIVE disembunyikan kecuali sakelar "Tampilkan Inactive" aktif.
            $.fn.dataTable.ext.search.push(function (settings, data, idx) {
                if (settings.nTable.id !== 'tblMaster' || $('#chkShowInactive').is(':checked')) return true;
                var row = settings.aoData[idx].nTr;
                return !row || row.getAttribute('data-row-status') !== 'INACTIVE';
            });
            $('#chkShowInactive').on('change', function () { table.draw(); });
            table.draw();
        @endif

        @if(!empty($m['filters']))
            TesysColumnFilter.initRowFilters('#tblMaster', '#masterRowFilters', @json(collect($m['filters'])->map(fn ($f) => ['key' => $f['key'], 'label' => $f['label']])->values()));
            $('#btnClearFilterMaster').on('click', function () {
                TesysColumnFilter.clearAll('#tblMaster');
                $('#masterSearch').val('');
                table.search('').draw();
            });
        @endif
    }

    TesysSearchableSelect.init('#frmMaster select[data-searchable]', {});

    function field(name) { return $('#fld_' + name); }

    // Dropdown wilayah berjenjang: opsi anak diambil dari /wilayah/{level}/options.
    function loadCascade($sel, parentVal, selected) {
        $sel.find('option').not(':first').remove();
        if (!parentVal) {
            $sel.val('').trigger('tss:sync');
            return $.Deferred().resolve().promise();
        }
        return $.getJSON(@json(url('/wilayah')) + '/' + $sel.data('level') + '/options', { parent_id: parentVal })
            .then(function (rows) {
                rows.forEach(function (r) { $sel.append($('<option>').val(r.id).text(r.name)); });
                $sel.val(selected ? String(selected) : '').trigger('tss:sync');
            });
    }

    $form.on('change', 'select[data-child]', function () {
        var $child = field($(this).data('child'));
        loadCascade($child, this.value, null).then(function () { $child.trigger('change'); });
    });

    function fillForm(values) {
        var chain = $.Deferred().resolve().promise();
        fieldNames.forEach(function (name) {
            var $el = field(name);
            var val = values[name] === null || values[name] === undefined ? '' : String(values[name]);
            if ($el.data('level')) {
                chain = chain.then(function () { return loadCascade($el, values[$el.data('parent')], val); });
            } else {
                $el.val(val);
                if ($el.is('select')) $el.trigger('tss:sync');
            }
        });
        return chain;
    }

    function setMode(mode, id) {
        var isEdit = mode === 'edit';
        $('#frmMasterMode').val(mode);
        $('#frmMasterId').val(id || '');
        $('#frmMasterMethod').prop('disabled', !isEdit);
        $form.attr('action', isEdit ? baseUrl + '/' + id : baseUrl);
        $('#mdlMasterTitle').text((isEdit ? 'Edit ' : 'Tambah ') + singular);
        $form.find('.js-create-only').toggle(!isEdit).find(':input').prop('disabled', isEdit);
        $form.find('.js-edit-only').toggle(isEdit).find(':input').prop('disabled', !isEdit);
    }

    function clearErrors() {
        $form.find('.is-invalid').removeClass('is-invalid');
        $form.find('.invalid-feedback, .alert-danger').remove();
    }

    $('#btnTambahMaster').on('click', function () {
        clearErrors();
        setMode('create');
        $form.find('input.js-master-field, textarea.js-master-field').val('');
        $form.find('select.js-master-field').each(function () {
            this.selectedIndex = 0;
            $(this).trigger('tss:sync');
        });
        fillForm({});
        $('#frmMasterLoading').hide();
        $('#frmMasterFields, #btnSaveMaster').show();
        $('#mdlMaster').modal('show');
    });

    $(document).on('click', '.btn-edit-master', function () {
        var id = $(this).data('id');
        clearErrors();
        setMode('edit', id);
        $('#frmMasterFields, #btnSaveMaster').hide();
        $('#frmMasterLoading').show().html('<i class="fas fa-spinner fa-spin fa-2x text-muted"></i><p class="text-muted mt-2 mb-0">Memuat data...</p>');
        $('#mdlMaster').modal('show');

        $.getJSON(baseUrl + '/' + id + '/edit-data')
            .then(fillForm)
            .then(function () {
                $('#frmMasterLoading').hide();
                $('#frmMasterFields, #btnSaveMaster').show();
            }, function () {
                $('#frmMasterLoading').html('<p class="text-danger text-center"><i class="fas fa-exclamation-triangle mr-1"></i>Gagal memuat data.</p>');
            });
    });

    $form.on('submit', function () {
        $('#btnSaveMaster').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');
    });

    $(document).on('submit', '.frmDeleteMaster', function (e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: 'Hapus ' + singular + '?',
            text: 'Data yang masih dipakai data lain tidak dapat dihapus.',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#9a3030', cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
        }).then(function (r) { if (r.isConfirmed) form.submit(); });
    });

    // Validasi gagal: buka lagi modal dengan isian sebelumnya.
    @if(old('_mode'))
        setMode({{ \Illuminate\Support\Js::from(old('_mode') === 'edit' ? 'edit' : 'create') }}, {{ \Illuminate\Support\Js::from((int) old('_id')) }});
        fillForm(@json(collect(old())->only(array_keys($fields))));
        $('#mdlMaster').modal('show');
    @elseif(!empty($m['import']) && $errors->has('file'))
        $('#mdlImportMaster').modal('show');
    @endif

    $('#frmImportMaster').on('submit', function () {
        $('#btnImportMaster').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Memproses...');
    });

    initTable();
});
</script>
@endpush
