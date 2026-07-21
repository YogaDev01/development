@extends('layouts.app')

@section('content')

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6 d-flex align-items-center">
                <h1 class="m-0 font-weight-bold">Karyawan</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="#">Master</a></li>
                    <li class="breadcrumb-item active">Karyawan</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12 col-md-4">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>{{ $summary['total'] }}</h3>
                        <p>Total Karyawan</p>
                    </div>
                    <div class="icon"><i class="fas fa-users"></i></div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>{{ $summary['active'] }}</h3>
                        <p>Active</p>
                    </div>
                    <div class="icon"><i class="fas fa-user-check"></i></div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="small-box bg-secondary">
                    <div class="inner">
                        <h3>{{ $summary['inactive'] }}</h3>
                        <p>Inactive</p>
                    </div>
                    <div class="icon"><i class="fas fa-user-slash"></i></div>
                </div>
            </div>
        </div>

        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header d-flex align-items-center">
                <button id="refreshTable" class="btn btn-secondary btn-sm" type="button">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
                <a href="{{ url('/employee/create') }}" class="btn btn-success btn-sm ml-auto">
                    <i class="fas fa-plus mr-1"></i> Tambah Karyawan
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
                                <select id="empFilter" multiple placeholder="Filter data...">
                                    <optgroup label="Status">
                                        <option value="STATUS:ACTIVE">Active</option>
                                        <option value="STATUS:INACTIVE">Inactive</option>
                                    </optgroup>
                                    <optgroup label="Jenis Kelamin">
                                        <option value="GENDER:L">Laki-laki</option>
                                        <option value="GENDER:P">Perempuan</option>
                                    </optgroup>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="empTable" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-nowrap" style="width:1%;">No</th>
                                <th class="text-nowrap" style="width:1%;">No Induk</th>
                                <th>Nama</th>
                                <th class="text-nowrap" style="width:1%;">Bank / Rekening</th>
                                <th class="text-nowrap" style="width:1%;">PTKP</th>
                                <th class="text-nowrap" style="width:1%;">Status</th>
                                <th class="text-center text-nowrap" style="width:1%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($employees as $emp)
                            <tr data-status="{{ $emp->status }}" data-gender="{{ $emp->gender }}">
                                <td class="text-nowrap">{{ $loop->iteration }}</td>
                                <td class="text-nowrap">
                                    <span class="badge badge-dark px-2 py-1" style="font-size:11px;">
                                        {{ $emp->employee_number ?? '-' }}
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size:13px;font-weight:600;">{{ $emp->name }}</div>
                                    @if($emp->email)
                                        <small class="text-muted">{{ $emp->email }}</small>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    <div style="font-size:12px;">{{ $emp->bank->name ?? '-' }}</div>
                                    <small class="text-muted">{{ $emp->account_number ?? '-' }}</small>
                                </td>
                                <td class="text-nowrap text-center">
                                    <span class="badge badge-light border px-2 py-1" style="font-size:10px;">
                                        {{ $emp->ptkp_status ?? '-' }}
                                    </span>
                                </td>
                                <td class="text-nowrap">
                                    <span class="badge badge-{{ $emp->status_color }} px-2 py-1" style="font-size:10px;">
                                        {{ $emp->status }}
                                    </span>
                                </td>
                                <td class="text-center text-nowrap">
                                    <button type="button" class="btn btn-info btn-sm btn-show" data-id="{{ $emp->id }}" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="{{ url('/employee/' . $emp->id . '/edit') }}" class="btn btn-warning btn-sm" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-danger btn-sm btn-delete"
                                            data-id="{{ $emp->id }}" data-name="{{ $emp->name }}" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr class="no-data">
                                <td colspan="7" class="text-center text-muted py-4">Belum ada karyawan.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

    </div>
</div>

{{-- MODAL DETAIL --}}
<div class="modal fade" id="modalShow" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:12px; overflow:hidden;">
            <div class="modal-header" style="background:#0D1B2A; border-bottom:3px solid #F5A623;">
                <h5 class="modal-title text-white">
                    <i class="fas fa-user mr-2" style="color:#F5A623;"></i>
                    <span id="showTitle"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" style="padding:24px 28px;">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-bordered mb-3" style="font-size:12px;">
                            <tbody>
                                <tr><th style="background:#F9FAFB;color:#6B7280;width:40%;">No Induk</th><td id="sNo"></td></tr>
                                <tr><th style="background:#F9FAFB;color:#6B7280;">NIK</th><td id="sNik"></td></tr>
                                <tr><th style="background:#F9FAFB;color:#6B7280;">Jenis Kelamin</th><td id="sGender"></td></tr>
                                <tr><th style="background:#F9FAFB;color:#6B7280;">Tempat Lahir</th><td id="sBirthPlace"></td></tr>
                                <tr><th style="background:#F9FAFB;color:#6B7280;">Tanggal Lahir</th><td id="sBirthDate"></td></tr>
                                <tr><th style="background:#F9FAFB;color:#6B7280;">No Telepon</th><td id="sPhone"></td></tr>
                                <tr><th style="background:#F9FAFB;color:#6B7280;">Email</th><td id="sEmail"></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-bordered mb-3" style="font-size:12px;">
                            <tbody>
                                <tr><th style="background:#F9FAFB;color:#6B7280;width:40%;">NPWP</th><td id="sNpwp"></td></tr>
                                <tr><th style="background:#F9FAFB;color:#6B7280;">Status PTKP</th><td id="sPtkp"></td></tr>
                                <tr><th style="background:#F9FAFB;color:#6B7280;">Bank</th><td id="sBank"></td></tr>
                                <tr><th style="background:#F9FAFB;color:#6B7280;">Nama Rekening</th><td id="sAccName"></td></tr>
                                <tr><th style="background:#F9FAFB;color:#6B7280;">No Rekening</th><td id="sAccNo"></td></tr>
                                <tr><th style="background:#F9FAFB;color:#6B7280;">Gaji Default</th><td id="sSalary"></td></tr>
                                <tr><th style="background:#F9FAFB;color:#6B7280;">Status</th><td id="sStatus"></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <table class="table table-bordered mb-0" style="font-size:12px;">
                    <tbody>
                        <tr><th style="background:#F9FAFB;color:#6B7280;width:20%;">Alamat</th><td id="sAddress" style="white-space:pre-line;"></td></tr>
                        <tr><th style="background:#F9FAFB;color:#6B7280;">Domisili</th><td id="sDomicile" style="white-space:pre-line;"></td></tr>
                    </tbody>
                </table>
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
.card-header .btn, .card-header .btn-sm { border-radius:6px; font-weight:600; }
.custom-toolbar      { display:flex; justify-content:space-between; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; margin-bottom:0; white-space:nowrap; font-weight:600; color:#495057; }
.toolbar-input       { min-width:180px; }
#customSearch input  { width:180px; border-radius:6px; }

.ts-dropdown .optgroup-header,
.selectize-dropdown .optgroup-header {
    font-weight: 700 !important; font-size: 11px !important; text-transform: uppercase;
    letter-spacing: .06em; color: #0D1B2A !important; background: #F1F3F5 !important;
    padding: 8px 12px !important; border-top: 1px solid #e3e6ea; cursor: default;
}
.ts-dropdown .optgroup:first-child .optgroup-header,
.selectize-dropdown .optgroup:first-child .optgroup-header { border-top: none; }
.ts-dropdown .optgroup .option,
.selectize-dropdown .optgroup .option { padding-left: 26px !important; }

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

    if ($('#empTable tbody tr').not('.no-data').length > 0) {
        table = $('#empTable').DataTable({
            autoWidth: false,
            pageLength: 10,
            searching: true,
            responsive: { details: { type: 'inline' } },
            dom: 'rt<"d-flex justify-content-between align-items-center mt-3"ip>',
            columnDefs: [
                { responsivePriority: 1,   targets: [0, 2, -1] },
                { responsivePriority: 2,   targets: [5] },
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

    new TomSelect('#empFilter', {
        plugins: ['remove_button'], persist: false, create: false,
        lockOptgroupOrder: true, placeholder: 'Filter data...'
    });

    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'empTable') return true;

        var filters = $('#empFilter').val();
        if (!filters || !filters.length) return true;

        var row = settings.aoData[dataIndex].nTr;
        if (!row) return true;

        var rowStatus = row.getAttribute('data-status') || '';
        var rowGender = row.getAttribute('data-gender') || '';

        var selStatus = [], selGender = [];
        filters.forEach(function (f) {
            var i = f.indexOf(':'); if (i < 0) return;
            var key = f.substring(0, i), val = f.substring(i + 1);
            if (key === 'STATUS')      selStatus.push(val);
            else if (key === 'GENDER') selGender.push(val);
        });

        if (selStatus.length && selStatus.indexOf(rowStatus) === -1) return false;
        if (selGender.length && selGender.indexOf(rowGender) === -1) return false;
        return true;
    });

    $('#empFilter').on('change', function () { if (table) table.draw(); });
    $('#refreshTable').on('click', function () { location.reload(); });

    // DETAIL
    $(document).on('click', '.btn-show', function () {
        var id = $(this).data('id');
        $.getJSON('/employee/' + id + '/detail-json', function (d) {
            $('#showTitle').text(d.name);
            $('#sNo').text(d.employee_number);
            $('#sNik').text(d.nik);
            $('#sGender').text(d.gender);
            $('#sBirthPlace').text(d.birth_place);
            $('#sBirthDate').text(d.birth_date);
            $('#sPhone').text(d.phone);
            $('#sEmail').text(d.email);
            $('#sNpwp').text(d.npwp);
            $('#sPtkp').text(d.ptkp_status);
            $('#sBank').text(d.bank);
            $('#sAccName').text(d.account_name);
            $('#sAccNo').text(d.account_number);
            $('#sSalary').text(d.default_salary);
            $('#sStatus').html('<span class="badge badge-' + d.status_color + ' px-2 py-1">' + d.status + '</span>');
            $('#sAddress').text(d.address);
            $('#sDomicile').text(d.domicile);
            $('#modalShow').modal('show');
        });
    });

    // DELETE
    $(document).on('click', '.btn-delete', function (e) {
        e.preventDefault();
        var id = $(this).data('id'), name = $(this).data('name');
        Swal.fire({
            title: 'Hapus Karyawan?',
            text: name + ' akan dihapus permanen.',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
        }).then(function (r) {
            if (r.isConfirmed) {
                var form = document.getElementById('formDelete');
                form.action = '/employee/' + id;
                form.submit();
            }
        });
    });

});
</script>
@endpush