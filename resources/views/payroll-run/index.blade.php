@extends('layouts.app')

@section('content')

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6 d-flex align-items-center">
                <h1 class="m-0 font-weight-bold">Payroll Run</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="#">Payroll</a></li>
                    <li class="breadcrumb-item active">Payroll Run</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12 col-md-3">
                <div class="small-box bg-info">
                    <div class="inner"><h3>{{ $summary['total'] }}</h3><p>Total Run</p></div>
                    <div class="icon"><i class="fas fa-file-invoice-dollar"></i></div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="small-box bg-secondary">
                    <div class="inner"><h3>{{ $summary['draft'] }}</h3><p>Draft</p></div>
                    <div class="icon"><i class="fas fa-pen"></i></div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="small-box bg-warning">
                    <div class="inner"><h3>{{ $summary['process'] }}</h3><p>Dalam Proses</p></div>
                    <div class="icon"><i class="fas fa-hourglass-half"></i></div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="small-box bg-success">
                    <div class="inner"><h3>{{ $summary['paid'] }}</h3><p>Dibayar</p></div>
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
        </div>

        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header d-flex align-items-center">
                <button id="refreshTable" class="btn btn-secondary btn-sm" type="button">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
                <a href="{{ url('/payroll-run/create') }}" class="btn btn-success btn-sm ml-auto">
                    <i class="fas fa-plus mr-1"></i> Buat Payroll Run
                </a>
            </div>

            <div class="card-body">

                <div class="custom-toolbar mb-3">
                    <div class="custom-toolbar-left">
                        <div class="toolbar-row">
                            <span class="toolbar-label">Cari :</span>
                            <div class="toolbar-input" id="customSearch"></div>
                        </div>
                        <div class="toolbar-row">
                            <span class="toolbar-label">Filter :</span>
                            <div class="toolbar-input">
                                <select id="runFilter" multiple placeholder="Filter data...">
                                    <optgroup label="Status">
                                        <option value="STATUS:DRAFT">Draft</option>
                                        <option value="STATUS:SUBMITTED">Submitted</option>
                                        <option value="STATUS:VERIFIED">Verified</option>
                                        <option value="STATUS:APPROVED">Approved</option>
                                        <option value="STATUS:PAID">Paid</option>
                                        <option value="STATUS:CLOSED">Closed</option>
                                        <option value="STATUS:REJECTED">Rejected</option>
                                    </optgroup>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="runTable" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-nowrap" style="width:1%;">No</th>
                                <th class="text-nowrap" style="width:1%;">Kode</th>
                                <th>Periode / Deskripsi</th>
                                <th class="text-nowrap" style="width:1%;">Karyawan</th>
                                <th class="text-nowrap text-right" style="width:1%;">Total</th>
                                <th class="text-nowrap" style="width:1%;">Status</th>
                                <th class="text-center text-nowrap" style="width:1%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($runs as $run)
                            <tr data-status="{{ $run->status }}">
                                <td class="text-nowrap">{{ $loop->iteration }}</td>
                                <td class="text-nowrap">
                                    <span class="badge badge-dark px-2 py-1" style="font-size:11px;">{{ $run->code }}</span>
                                </td>
                                <td>
                                    <div style="font-size:13px;font-weight:600;">{{ $run->period_label }}</div>
                                    @if($run->description)
                                        <small class="text-muted">{{ $run->description }}</small>
                                    @endif
                                </td>
                                <td class="text-nowrap text-center">
                                    <span class="badge badge-secondary px-2 py-1">{{ $run->lines->count() }}</span>
                                </td>
                                <td class="text-nowrap text-right">
                                    <strong>{{ $run->total_formatted }}</strong>
                                </td>
                                <td class="text-nowrap">
                                    <span class="badge badge-{{ $run->status_color }} px-2 py-1" style="font-size:10px;">
                                        {{ $run->status }}
                                    </span>
                                </td>
                                <td class="text-center text-nowrap">
                                    <a href="{{ url('/payroll-run/' . $run->id) }}" class="btn btn-info btn-sm" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @if($run->isEditable())
                                    <a href="{{ url('/payroll-run/' . $run->id . '/edit') }}" class="btn btn-warning btn-sm" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-danger btn-sm btn-delete"
                                            data-id="{{ $run->id }}" data-code="{{ $run->code }}" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr class="no-data">
                                <td colspan="7" class="text-center text-muted py-4">Belum ada payroll run.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

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
.card-header .btn, .card-header a.btn { border-radius:6px; font-weight:600; }
.custom-toolbar      { display:flex; justify-content:space-between; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; margin-bottom:0; white-space:nowrap; font-weight:600; color:#495057; }
.toolbar-input       { min-width:180px; }
#customSearch input  { width:180px; border-radius:6px; }

.ts-dropdown .optgroup-header, .selectize-dropdown .optgroup-header {
    font-weight:700 !important; font-size:11px !important; text-transform:uppercase;
    letter-spacing:.06em; color:#0D1B2A !important; background:#F1F3F5 !important;
    padding:8px 12px !important; border-top:1px solid #e3e6ea; cursor:default;
}
.ts-dropdown .optgroup .option, .selectize-dropdown .optgroup .option { padding-left:26px !important; }

@media (max-width: 767.98px) {
    .card-header         { flex-direction:column; gap:10px; align-items:stretch !important; }
    .card-header .btn, .card-header a.btn { width:100%; }
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

    if ($('#runTable tbody tr').not('.no-data').length > 0) {
        table = $('#runTable').DataTable({
            autoWidth: false,
            pageLength: 10,
            searching: true,
            responsive: { details: { type: 'inline' } },
            dom: 'rt<"d-flex justify-content-between align-items-center mt-3"ip>',
            columnDefs: [
                { responsivePriority: 1,   targets: [0, 2, 5, -1] },
                { responsivePriority: 100, targets: [3, 4] }
            ],
            language: {
                info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                paginate: { previous: 'Sebelumnya', next: 'Berikutnya' },
                zeroRecords: 'Data tidak ditemukan'
            }
        });

        $('#customSearch').html('<input type="text" class="form-control form-control-sm" placeholder="Cari data...">');
        $('#customSearch input').on('keyup', function () { table.search(this.value).draw(); });
    }

    new TomSelect('#runFilter', {
        plugins: ['remove_button'], persist: false, create: false,
        lockOptgroupOrder: true, placeholder: 'Filter data...'
    });

    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'runTable') return true;
        var filters = $('#runFilter').val();
        if (!filters || !filters.length) return true;
        var row = settings.aoData[dataIndex].nTr;
        if (!row) return true;
        var rowStatus = row.getAttribute('data-status') || '';
        var selStatus = [];
        filters.forEach(function (f) {
            var i = f.indexOf(':'); if (i < 0) return;
            if (f.substring(0, i) === 'STATUS') selStatus.push(f.substring(i + 1));
        });
        if (selStatus.length && selStatus.indexOf(rowStatus) === -1) return false;
        return true;
    });

    $('#runFilter').on('change', function () { if (table) table.draw(); });
    $('#refreshTable').on('click', function () { location.reload(); });

    $(document).on('click', '.btn-delete', function (e) {
        e.preventDefault();
        var id = $(this).data('id'), code = $(this).data('code');
        Swal.fire({
            title: 'Hapus Payroll Run?',
            text: code + ' akan dihapus permanen.',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
        }).then(function (r) {
            if (r.isConfirmed) {
                var form = document.getElementById('formDelete');
                form.action = '/payroll-run/' + id;
                form.submit();
            }
        });
    });

});
</script>
@endpush