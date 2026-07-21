@extends('layouts.app')

@section('title', 'Cost Center')

@section('content')

<!-- CONTENT HEADER -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6 d-flex align-items-center">
                <h1 class="m-0 font-weight-bold">Cost Center</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="#">Master</a></li>
                    <li class="breadcrumb-item active">Cost Center</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="content">
    <div class="container-fluid">

        <!-- SUMMARY CARDS -->
        <div class="row">
            <div class="col-12 col-md-3">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>{{ $summary['total'] }}</h3>
                        <p>Total Cost Center</p>
                    </div>
                    <div class="icon"><i class="fas fa-sitemap"></i></div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3>{{ $summary['overhead'] }}</h3>
                        <p>Overhead</p>
                    </div>
                    <div class="icon"><i class="fas fa-building"></i></div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>{{ $summary['payroll'] }}</h3>
                        <p>Payroll</p>
                    </div>
                    <div class="icon"><i class="fas fa-users"></i></div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>{{ $summary['active'] }}</h3>
                        <p>Active</p>
                    </div>
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
        </div>

        <div class="card card-primary card-outline shadow-sm">

            <!-- CARD HEADER -->
            <div class="card-header d-flex align-items-center">
                <button id="refreshTable" class="btn btn-secondary btn-sm" type="button">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
                <button type="button" class="btn btn-success btn-sm ml-auto" id="btnTambah">
                    <i class="fas fa-plus mr-1"></i> Tambah Cost Center
                </button>
            </div>

            <!-- CARD BODY -->
            <div class="card-body">

                <!-- TOOLBAR -->
                <div class="custom-toolbar mb-3">
                    <div class="custom-toolbar-left">
                        <div class="toolbar-row">
                            <span class="toolbar-label">Cari :</span>
                            <div class="toolbar-input" id="customSearch"></div>
                        </div>
                        <div class="toolbar-row">
                            <span class="toolbar-label">Filter :</span>
                            <div class="toolbar-input">
                                <select id="ccFilter" multiple placeholder="Filter data...">
                                    <optgroup label="Tipe">
                                        <option value="TYPE:OVERHEAD">Overhead</option>
                                        <option value="TYPE:PAYROLL">Payroll</option>
                                    </optgroup>
                                    <optgroup label="Status">
                                        <option value="STATUS:ACTIVE">Active</option>
                                        <option value="STATUS:INACTIVE">Inactive</option>
                                    </optgroup>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TABLE -->
                <div class="table-responsive">
                    <table id="ccTable" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-nowrap" style="width:1%;">No</th>
                                <th class="text-nowrap" style="width:1%;">Kode</th>
                                <th>Nama</th>
                                <th class="text-nowrap" style="width:1%;">Tipe</th>
                                <th class="text-center text-nowrap" style="width:1%;">Budget Plan</th>
                                <th class="text-nowrap" style="width:1%;">Status</th>
                                <th class="text-center text-nowrap action-column" style="width:1%;">Action</th>
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

                                <td class="text-center text-nowrap">
                                    <button type="button" class="btn btn-info btn-sm btn-show"
                                            data-id="{{ $cc->id }}" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-warning btn-sm btn-edit"
                                            data-id="{{ $cc->id }}"
                                            data-name="{{ $cc->name }}"
                                            data-status="{{ $cc->status }}"
                                            data-notes="{{ $cc->notes }}"
                                            title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm btn-delete"
                                            data-id="{{ $cc->id }}"
                                            data-name="{{ $cc->name }}"
                                            title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
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
        <div class="modal-content" style="border-radius:12px; overflow:hidden;">

            <div class="modal-header" style="background:#0D1B2A; border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white">
                    <i class="fas fa-sitemap mr-2" style="color:#F5A623;"></i>
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
        <div class="modal-content" style="border-radius:12px; overflow:hidden;">

            <div class="modal-header" style="background:#0D1B2A; border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white">
                    <i class="fas fa-edit mr-2" style="color:#F5A623;"></i>
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
        <div class="modal-content" style="border-radius:12px; overflow:hidden;">

            <div class="modal-header" style="background:#0D1B2A; border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white">
                    <i class="fas fa-sitemap mr-2" style="color:#F5A623;"></i>
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
                            <th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;width:30%;">Kode</th>
                            <td id="showCode"></td>
                        </tr>
                        <tr>
                            <th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Tipe</th>
                            <td id="showType"></td>
                        </tr>
                        <tr>
                            <th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Status</th>
                            <td id="showStatus"></td>
                        </tr>
                        <tr>
                            <th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Catatan</th>
                            <td id="showNotes" style="white-space:pre-line;"></td>
                        </tr>
                        <tr>
                            <th style="background:#F9FAFB;color:#6B7280;font-size:11px;text-transform:uppercase;">Input By</th>
                            <td id="showInputBy"></td>
                        </tr>
                    </tbody>
                </table>

                <h6 class="font-weight-bold text-dark mb-2">
                    <i class="fas fa-wallet mr-1 text-success"></i> Budget Plan
                </h6>

                <div class="table-responsive">
                    <table class="table table-bordered mb-0" style="font-size:12px;">
                        <thead style="background:#F9FAFB;">
                            <tr>
                                <th style="color:#6B7280;font-size:11px;width:5%;">No</th>
                                <th style="color:#6B7280;font-size:11px;">Deskripsi</th>
                                <th style="color:#6B7280;font-size:11px;">Status</th>
                                <th style="color:#6B7280;font-size:11px;text-align:right;">Budget</th>
                                <th style="color:#6B7280;font-size:11px;text-align:right;">Used</th>
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

.card-header .btn  { border-radius:6px; font-weight:600; }
.font-weight-600   { font-weight:600; }
.action-column     { white-space:nowrap !important; width:1% !important; }

/* TOOLBAR */
.custom-toolbar      { display:flex; justify-content:space-between; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; margin-bottom:0; white-space:nowrap; font-weight:600; color:#495057; }
.toolbar-input       { min-width:180px; }
#customSearch        { width:100%; }
#customSearch input  { width:180px; border-radius:6px; }

/* FILTER DROPDOWN: bedakan header grup vs item */
.ts-dropdown .optgroup-header,
.selectize-dropdown .optgroup-header {
    font-weight: 700 !important;
    font-size: 11px !important;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #0D1B2A !important;
    background: #F1F3F5 !important;
    padding: 8px 12px !important;
    border-top: 1px solid #e3e6ea;
    cursor: default;
}
.ts-dropdown .optgroup:first-child .optgroup-header,
.selectize-dropdown .optgroup:first-child .optgroup-header { border-top: none; }
.ts-dropdown .optgroup .option,
.selectize-dropdown .optgroup .option { padding-left: 26px !important; }

@media (max-width: 767.98px) {
    .card-header         { flex-direction:column; gap:10px; align-items:stretch !important; }
    .card-header .btn    { width:100%; }
    .custom-toolbar      { flex-direction:column; align-items:stretch; }
    .custom-toolbar-left { flex-direction:column; align-items:stretch; }
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
    }

    // Filter bergrup
    new TomSelect('#ccFilter', {
        plugins: ['remove_button'],
        persist: false,
        create: false,
        lockOptgroupOrder: true,
        placeholder: 'Filter data...'
    });

    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'ccTable') return true;

        var filters = $('#ccFilter').val();
        if (!filters || !filters.length) return true;

        var row = settings.aoData[dataIndex].nTr;
        if (!row) return true;

        var rowType   = row.getAttribute('data-type')   || '';
        var rowStatus = row.getAttribute('data-status') || '';

        var selType = [], selStatus = [];

        filters.forEach(function (f) {
            var i = f.indexOf(':');
            if (i < 0) return;
            var key = f.substring(0, i);
            var val = f.substring(i + 1);
            if (key === 'TYPE')        selType.push(val);
            else if (key === 'STATUS') selStatus.push(val);
        });

        if (selType.length   && selType.indexOf(rowType)     === -1) return false;
        if (selStatus.length && selStatus.indexOf(rowStatus) === -1) return false;

        return true;
    });

    $('#ccFilter').on('change', function () {
        if (table) table.draw();
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
                    '<td class="text-right"><strong>Rp ' + amt.toLocaleString('id-ID') + '</strong></td>' +
                    '<td class="text-right" style="color:#D97706;">Rp ' + used.toLocaleString('id-ID') + '</td></tr>';
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
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
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