@extends('layouts.app')

@section('title', 'Cost Center')

@section('content')

<!-- MAIN CONTENT -->
<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Cost Center</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Master</a></li>
                <li class="breadcrumb-item active">Cost Center</li>
            </ol>
        </div>

        <x-stat-cards :cards="[
            ['Total Cost Center', $summary['total'], 'primary', 'fas fa-sitemap'],
            ['Overhead', $summary['overhead'], 'info', 'fas fa-building'],
            ['Payroll', $summary['payroll'], 'warning', 'fas fa-users'],
            ['Active', $summary['active'], 'success', 'fas fa-check-circle'],
        ]" />

        <div class="card card-primary card-outline shadow-sm">

            <!-- CARD HEADER -->
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-sitemap mr-1"></i> Daftar Cost Center</h3>
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
                        <button aria-label="Clear Filter" type="button" id="btnClearFilterCC" class="btn btn-outline-secondary btn-sm" title="Clear Filter">
                            <i class="fas fa-eraser"></i>
                        </button>
                        <div id="ccRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>
                    </div>
                    <div class="custom-toolbar-right d-flex flex-wrap" style="gap:8px;">
                        <button type="button" class="btn btn-success btn-sm" id="btnTambah">
                            <i class="fas fa-plus mr-1"></i> Tambah Cost Center
                        </button>
                    </div>
                </div>

                <!-- TABLE -->
                <div class="table-responsive">
                    <table id="ccTable" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" class="text-nowrap" style="width:1%;">No</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Kode</th>
                                <th scope="col">Nama</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Tipe</th>
                                <th scope="col" class="text-center text-nowrap" style="width:1%;">Budget Plan</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Status</th>
                                <th scope="col" class="text-center text-nowrap action-column" style="width:1%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($costCenters as $cc)
                            <tr data-type="{{ $cc->type }}" data-status="{{ $cc->status }}">
                                <td class="text-nowrap">{{ $loop->iteration }}</td>

                                <td class="text-nowrap">
                                    <span class="badge badge-dark px-2 py-1" style="font-size:11px;letter-spacing:0.03em;">
                                        {{ $cc->code }}
                                    </span>
                                </td>

                                <td>
                                    <div style="font-size:13px;font-weight:600;">{{ $cc->name }}</div>
                                    @if($cc->notes)
                                        <small class="text-muted">{{ Str::limit($cc->notes, 50) }}</small>
                                    @endif
                                </td>

                                <td class="text-nowrap">
                                    <span class="badge badge-{{ $cc->type_color }} px-2 py-1" style="font-size:10px;">
                                        {{ $cc->type }}
                                    </span>
                                </td>

                                <td class="text-center text-nowrap">
                                    <span class="badge badge-secondary px-2 py-1" style="font-size:10px;">
                                        {{ $cc->budget_plans_count }}
                                    </span>
                                </td>

                                <td class="text-nowrap">
                                    <span class="badge badge-{{ $cc->status_color }} px-2 py-1" style="font-size:10px;">
                                        {{ $cc->status }}
                                    </span>
                                </td>

                                <td class="text-center action-column">
                                    <div class="action-buttons ts-actions">

                                        <button type="button" class="ts-action-primary is-neutral btn-edit"
                                                data-id="{{ $cc->id }}"
                                                data-name="{{ $cc->name }}"
                                                data-status="{{ $cc->status }}"
                                                data-notes="{{ $cc->notes }}"
                                                title="Edit" aria-label="Edit {{ $cc->name }}">
                                            <i class="fas fa-pen" aria-hidden="true"></i><span class="ts-al">Edit</span>
                                        </button>

                                        <button type="button" class="ts-action-more"
                                                aria-haspopup="true" aria-expanded="false"
                                                title="Aksi lain" aria-label="Aksi lain untuk {{ $cc->name }}">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>

                                        <div class="ts-action-menu dropdown-menu" hidden>

                                            <button type="button" class="dropdown-item btn-show" data-id="{{ $cc->id }}">
                                                <i class="fas fa-eye"></i>Detail
                                            </button>

                                            <div class="dropdown-divider"></div>

                                            <button type="button" class="dropdown-item is-danger btn-delete"
                                                    data-id="{{ $cc->id }}" data-name="{{ $cc->name }}">
                                                <i class="fas fa-trash"></i>Hapus
                                            </button>

                                        </div>

                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr class="no-data">
                                <td colspan="7" class="text-center text-muted py-4">Belum ada cost center.</td>
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
<!-- MODAL TAMBAH                                                      -->
<!-- ================================================================ -->
<div class="modal fade" id="modalTambah" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg); overflow:hidden;">

            <div class="modal-header" style="background:var(--charcoal); border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-sitemap mr-2" style="color:var(--accent);"></i>
                    Tambah Cost Center
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <form action="{{ url('/cost-center') }}" method="POST" id="formTambah">
                @csrf

                <div class="modal-body" style="padding:24px 28px;">

                    <div class="form-group">
                        <label class="font-weight-600">
                            Tipe <span class="text-danger">*</span>
                        </label>
                        <select name="type" id="tambahType" class="form-control" required>
                            <option value="">— Pilih Tipe —</option>
                            <option value="OVERHEAD">OVERHEAD — biaya kantor / operasional</option>
                            <option value="PAYROLL">PAYROLL — gaji karyawan</option>
                            <option value="TAX">TAX — pajak</option>
                            <option value="ASSET">ASSET — aset</option>
                            <option value="LIABILITY">LIABILITY — liabilitas / utang</option>
                            <option value="CAPITAL">CAPITAL — modal</option>
                            <option value="ADJUSTMENT">ADJUSTMENT — penyesuaian</option>
                        </select>
                        <small class="text-muted mt-1 d-block">Tipe tidak dapat diubah setelah disimpan.</small>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-600">
                            Nama Cost Center <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="name" id="tambahName"
                               class="form-control"
                               placeholder="Contoh: OVERHEAD KANTOR PUSAT"
                               style="text-transform:uppercase;"
                               autocomplete="off" required>
                        <small class="text-muted mt-1 d-block">Kode dibuat otomatis (OH-001 / PR-001).</small>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-600">Catatan</label>
                        <textarea name="notes" id="tambahNotes" class="form-control" rows="2"
                                  placeholder="Opsional..."></textarea>
                    </div>

                </div>

                <div class="modal-footer">
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
<!-- MODAL EDIT                                                        -->
<!-- ================================================================ -->
<div class="modal fade" id="modalEdit" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg); overflow:hidden;">

            <div class="modal-header" style="background:var(--charcoal); border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-edit mr-2" style="color:var(--accent);"></i>
                    Edit Cost Center
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <form method="POST" id="formEdit">
                @csrf
                @method('PUT')

                <div class="modal-body" style="padding:24px 28px;">

                    <div class="form-group">
                        <label class="font-weight-600">
                            Nama Cost Center <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="name" id="editName"
                               class="form-control"
                               style="text-transform:uppercase;"
                               autocomplete="off" required>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-600">
                            Status <span class="text-danger">*</span>
                        </label>
                        <select name="status" id="editStatus" class="form-control" required>
                            <option value="ACTIVE">ACTIVE</option>
                            <option value="INACTIVE">INACTIVE</option>
                        </select>
                        <small class="text-muted mt-1 d-block">Nonaktifkan bila cost center tidak dipakai lagi.</small>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-600">Catatan</label>
                        <textarea name="notes" id="editNotes" class="form-control" rows="2"></textarea>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save mr-1"></i> Update
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- MODAL DETAIL                                                      -->
<!-- ================================================================ -->
<div class="modal fade" id="modalShow" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg); overflow:hidden;">

            <div class="modal-header" style="background:var(--charcoal); border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-sitemap mr-2" style="color:var(--accent);"></i>
                    <span id="showTitle"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body" style="padding:24px 28px;">

                <table class="table table-bordered mb-4" style="font-size:13px;">
                    <tbody>
                        <tr>
                            <th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;width:30%;">Kode</th>
                            <td id="showCode"></td>
                        </tr>
                        <tr>
                            <th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;">Tipe</th>
                            <td id="showType"></td>
                        </tr>
                        <tr>
                            <th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;">Status</th>
                            <td id="showStatus"></td>
                        </tr>
                        <tr>
                            <th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;">Catatan</th>
                            <td id="showNotes" style="white-space:pre-line;"></td>
                        </tr>
                        <tr>
                            <th style="background:var(--surface-2);color:var(--text-faint);font-size:11px;text-transform:uppercase;">Input By</th>
                            <td id="showInputBy"></td>
                        </tr>
                    </tbody>
                </table>

                <h6 class="font-weight-bold text-dark mb-2">
                    <i class="fas fa-wallet mr-1 text-success"></i> Budget Plan
                </h6>

                <div class="table-responsive">
                    <table class="table table-bordered mb-0" style="font-size:12px;">
                        <thead style="background:var(--surface-2);">
                            <tr>
                                <th scope="col" style="color:var(--text-faint);font-size:11px;width:5%;">No</th>
                                <th scope="col" style="color:var(--text-faint);font-size:11px;">Deskripsi</th>
                                <th scope="col" style="color:var(--text-faint);font-size:11px;">Status</th>
                                <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;">Budget</th>
                                <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;">Used</th>
                            </tr>
                        </thead>
                        <tbody id="showBudgetBody"></tbody>
                    </table>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Tutup
                </button>
            </div>

        </div>
    </div>
</div>

<form id="formDelete" method="POST" style="display:none;">
    @csrf
    @method('DELETE')
</form>

@endsection

@push('styles')
<style>

.card-header .btn  { border-radius:var(--radius-sm); font-weight:600; }
.font-weight-600   { font-weight:600; }
.action-column     { white-space:nowrap !important; width:1% !important; }

/* TOOLBAR */
.custom-toolbar      { display:flex; justify-content:space-between; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; margin-bottom:0; white-space:nowrap; font-weight:600; color:var(--text-dim); }
.toolbar-input       { min-width:180px; }
#customSearch        { width:100%; }
#customSearch input  { width:180px; border-radius:var(--radius-sm); }

/* FILTER DROPDOWN: bedakan header grup vs item */
.ts-dropdown .optgroup-header,
.selectize-dropdown .optgroup-header {
    font-weight: 700 !important;
    font-size: 11px !important;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--text) !important;
    background: var(--surface-2) !important;
    padding: 8px 12px !important;
    border-top: 1px solid var(--border);
    cursor: default;
}
.ts-dropdown .optgroup:first-child .optgroup-header,
.selectize-dropdown .optgroup:first-child .optgroup-header { border-top: none; }
.ts-dropdown .optgroup .option,
.selectize-dropdown .optgroup .option { padding-left: 26px !important; }

@media (max-width: 767.98px) {
    .card-header         { flex-direction:column; gap:10px; align-items:stretch !important; }
    .card-header .btn    { width:100%; }
    .card-header .card-title { margin-bottom:0; }
    .card-header .ml-auto { margin-left:0 !important; flex-wrap:wrap; }
    .custom-toolbar      { flex-direction:column !important; align-items:stretch !important; }
    .custom-toolbar-left { flex-direction:column !important; align-items:stretch !important; width:100%; }
    .custom-toolbar-right { width:100%; }
    .custom-toolbar-right .btn { width:100%; }
    #btnClearFilterCC    { width:100%; }
    #ccRowFilters        { width:100%; }
    #ccRowFilters .tcf-row-btn { width:100%; }
    .toolbar-row         { width:100%; }
    .toolbar-input       { flex:1; min-width:0; }
    #customSearch input  { width:100%; }
    .ts-wrapper          { width:100% !important; min-width:0 !important; }
}

</style>
@endpush

@push('scripts')
<script>
$(function () {

    var table = null;

    if ($('#ccTable tbody tr').not('.no-data').length > 0) {
        table = $('#ccTable').DataTable({
            autoWidth: false,
            pageLength: 10,
            lengthChange: true,
            searching: true,
            responsive: { details: { type: 'inline' } },
            dom: 'rt<"d-flex justify-content-between align-items-center mt-3"ip>',
            columnDefs: [
                { responsivePriority: 1,   targets: [0, 1, 2, -1] },
                { responsivePriority: 2,   targets: [5] },
                { responsivePriority: 100, targets: [3, 4] }
            ],
            language: {
                info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                paginate: { previous: 'Sebelumnya', next: 'Berikutnya' },
                zeroRecords: 'Data tidak ditemukan'
            }
        });

        // Search box custom
        $('#customSearch').html('<input type="text" class="form-control form-control-sm" placeholder="Cari data...">');
        $('#customSearch input').on('keyup', function () {
            table.search(this.value).draw();
        });

        TesysColumnFilter.initRowFilters('#ccTable', '#ccRowFilters', [
            { key: 'type', label: 'Tipe' },
            { key: 'status', label: 'Status' },
        ]);
    }

    $('#btnClearFilterCC').on('click', function () {
        if (!table) return;
        TesysColumnFilter.clearAll('#ccTable');
    });

    // Refresh
    $('#refreshTable').on('click', function () {
        location.reload();
    });

    // TAMBAH
    $('#btnTambah').on('click', function () {
        $('#formTambah')[0].reset();
        $('#modalTambah').modal('show');
    });

    // EDIT
    $(document).on('click', '.btn-edit', function () {
        var id = $(this).data('id');
        $('#editName').val($(this).data('name'));
        $('#editStatus').val($(this).data('status'));
        $('#editNotes').val($(this).data('notes'));
        $('#formEdit').attr('action', '/cost-center/' + id);
        $('#modalEdit').modal('show');
    });

    // DETAIL
    $(document).on('click', '.btn-show', function () {
        var id = $(this).data('id');

        $.getJSON('/cost-center/' + id + '/detail-json', function (d) {
            $('#showTitle').text(d.name);
            $('#showCode').html('<span class="badge badge-dark px-2 py-1">' + d.code + '</span>');
            $('#showType').html('<span class="badge badge-' + d.type_color + ' px-2 py-1">' + d.type + '</span>');
            $('#showStatus').html('<span class="badge badge-' + d.status_color + ' px-2 py-1">' + d.status + '</span>');
            $('#showNotes').text(d.notes || '-');
            $('#showInputBy').text(d.input_by || '-');

            var html = '';
            d.budget_plans.forEach(function (bp, i) {
                var amt  = parseInt(bp.amount || 0);
                var used = parseInt(bp.used || 0);
                html += '<tr><td class="text-center">' + (i + 1) + '</td>' +
                    '<td><div style="font-weight:600;">' + bp.description + '</div>' +
                    '<small class="text-muted">' + (bp.code || '-') + '</small></td>' +
                    '<td><span class="badge badge-' + bp.status_color + ' px-2 py-1" style="font-size:9px;">' + bp.status + '</span></td>' +
                    '<td class="text-right"><strong>' + amt.toLocaleString('id-ID') + '</strong></td>' +
                    '<td class="text-right" style="color:var(--warning);">' + used.toLocaleString('id-ID') + '</td></tr>';
            });
            $('#showBudgetBody').html(html || '<tr><td colspan="5" class="text-center text-muted py-3">Belum ada budget plan.</td></tr>');

            $('#modalShow').modal('show');
        });
    });

    // DELETE
    $(document).on('click', '.btn-delete', function (e) {
        e.preventDefault();

        var id   = $(this).data('id');
        var name = $(this).data('name');

        Swal.fire({
            title: 'Hapus Cost Center?',
            text: name + ' akan dihapus permanen.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: 'var(--danger)',
            cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(function (r) {
            if (r.isConfirmed) {
                var form = document.getElementById('formDelete');
                form.action = '/cost-center/' + id;
                form.submit();
            }
        });
    });

});
</script>
@endpush