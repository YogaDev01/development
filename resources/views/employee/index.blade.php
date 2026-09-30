@extends('layouts.app')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Karyawan</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Master</a></li>
                <li class="breadcrumb-item active">Karyawan</li>
            </ol>
        </div>

        <x-stat-cards :cards="[
            ['Total Karyawan', $summary['total'], 'primary', 'fas fa-users'],
            ['Active', $summary['active'], 'success', 'fas fa-user-check'],
            ['Inactive', $summary['inactive'], 'secondary', 'fas fa-user-slash'],
        ]" />

        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-users mr-1"></i> Daftar Karyawan</h3>
                <div class="ml-auto d-flex" style="gap:8px;">
                    <button id="refreshTable" class="btn btn-secondary btn-sm" type="button">
                        <i class="fas fa-sync-alt mr-1"></i> Refresh
                    </button>
                </div>
            </div>

            <div class="card-body">

                <div class="custom-toolbar mb-3 d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
                    <div class="custom-toolbar-left d-flex align-items-center flex-wrap" style="gap:8px;">
                        <div class="toolbar-row">
                            <span class="toolbar-label">Cari :</span>
                            <div class="toolbar-input" id="customSearch"></div>
                        </div>
                        <button aria-label="Clear Filter" type="button" id="btnClearFilterEmp" class="btn btn-outline-secondary btn-sm" title="Clear Filter">
                            <i class="fas fa-eraser"></i>
                        </button>
                        <div id="empRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>
                    </div>
                    <div class="custom-toolbar-right d-flex flex-wrap" style="gap:8px;">
                        <a href="{{ url('/employee/create') }}" class="btn btn-success btn-sm">
                            <i class="fas fa-plus mr-1"></i> Tambah Karyawan
                        </a>
                    </div>
                </div>

                {{-- Daftar dibatasi baris terbaru (Concerns\CapsLargeLists);
                     angka kartu ringkasan tetap dihitung dari SELURUH data
                     lewat query agregat, jadi tidak ikut terpotong. --}}
                @if(!empty($truncated))
                <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:12.5px;">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Menampilkan <strong>{{ number_format($maxRows, 0, ',', '.') }}</strong> karyawan dari total
                    <strong>{{ number_format($total, 0, ',', '.') }}</strong>.
                    Gunakan pencarian/filter untuk mempersempit.
                </div>
                @endif
                <div class="table-responsive">
                    <table id="empTable" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" class="text-nowrap" style="width:1%;">No</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">No Induk</th>
                                <th scope="col">Nama</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Bank / Rekening</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">PTKP</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Status</th>
                                <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
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
                                <td class="text-center action-column">
                                    <div class="action-buttons ts-actions">

                                        <a href="{{ url('/employee/' . $emp->id . '/edit') }}"
                                           class="ts-action-primary is-neutral"
                                           title="Edit" aria-label="Edit {{ $emp->name }}">
                                            <i class="fas fa-pen" aria-hidden="true"></i><span class="ts-al">Edit</span>
                                        </a>

                                        <button type="button" class="ts-action-more"
                                                aria-haspopup="true" aria-expanded="false"
                                                title="Aksi lain" aria-label="Aksi lain untuk {{ $emp->name }}">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>

                                        <div class="ts-action-menu dropdown-menu" hidden>

                                            <button type="button" class="dropdown-item btn-show" data-id="{{ $emp->id }}">
                                                <i class="fas fa-eye"></i>Detail
                                            </button>

                                            <div class="dropdown-divider"></div>

                                            <button type="button" class="dropdown-item is-danger btn-delete"
                                                    data-id="{{ $emp->id }}" data-name="{{ $emp->name }}">
                                                <i class="fas fa-trash"></i>Hapus
                                            </button>

                                        </div>

                                    </div>
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
        <div class="modal-content" style="border-radius:var(--radius-lg); overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal); border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-user mr-2" style="color:var(--accent);"></i>
                    <span id="showTitle"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" style="padding:24px 28px;">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-bordered mb-3" style="font-size:12px;">
                            <tbody>
                                <tr><th style="background:var(--surface-2);color:var(--text-faint);width:40%;">No Induk</th><td id="sNo"></td></tr>
                                <tr><th style="background:var(--surface-2);color:var(--text-faint);">NIK</th><td id="sNik"></td></tr>
                                <tr><th style="background:var(--surface-2);color:var(--text-faint);">Jenis Kelamin</th><td id="sGender"></td></tr>
                                <tr><th style="background:var(--surface-2);color:var(--text-faint);">Tempat Lahir</th><td id="sBirthPlace"></td></tr>
                                <tr><th style="background:var(--surface-2);color:var(--text-faint);">Tanggal Lahir</th><td id="sBirthDate"></td></tr>
                                <tr><th style="background:var(--surface-2);color:var(--text-faint);">No Telepon</th><td id="sPhone"></td></tr>
                                <tr><th style="background:var(--surface-2);color:var(--text-faint);">Email</th><td id="sEmail"></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-bordered mb-3" style="font-size:12px;">
                            <tbody>
                                <tr><th style="background:var(--surface-2);color:var(--text-faint);width:40%;">NPWP</th><td id="sNpwp"></td></tr>
                                <tr><th style="background:var(--surface-2);color:var(--text-faint);">Status PTKP</th><td id="sPtkp"></td></tr>
                                <tr><th style="background:var(--surface-2);color:var(--text-faint);">Bank</th><td id="sBank"></td></tr>
                                <tr><th style="background:var(--surface-2);color:var(--text-faint);">Nama Rekening</th><td id="sAccName"></td></tr>
                                <tr><th style="background:var(--surface-2);color:var(--text-faint);">No Rekening</th><td id="sAccNo"></td></tr>
                                <tr><th style="background:var(--surface-2);color:var(--text-faint);">Gaji Default</th><td id="sSalary"></td></tr>
                                <tr><th style="background:var(--surface-2);color:var(--text-faint);">Status</th><td id="sStatus"></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <table class="table table-bordered mb-0" style="font-size:12px;">
                    <tbody>
                        <tr><th style="background:var(--surface-2);color:var(--text-faint);width:20%;">Alamat</th><td id="sAddress" style="white-space:pre-line;"></td></tr>
                        <tr><th style="background:var(--surface-2);color:var(--text-faint);">Domisili</th><td id="sDomicile" style="white-space:pre-line;"></td></tr>
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
.card-header .btn, .card-header .btn-sm { border-radius:var(--radius-sm); font-weight:600; }
.custom-toolbar      { display:flex; justify-content:space-between; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; margin-bottom:0; white-space:nowrap; font-weight:600; color:var(--text-dim); }
.toolbar-input       { min-width:180px; }
#customSearch input  { width:180px; border-radius:var(--radius-sm); }

.ts-dropdown .optgroup-header,
.selectize-dropdown .optgroup-header {
    font-weight: 700 !important; font-size: 11px !important; text-transform: uppercase;
    letter-spacing: .06em; color: var(--text) !important; background: var(--surface-2) !important;
    padding: 8px 12px !important; border-top: 1px solid var(--border); cursor: default;
}
.ts-dropdown .optgroup:first-child .optgroup-header,
.selectize-dropdown .optgroup:first-child .optgroup-header { border-top: none; }
.ts-dropdown .optgroup .option,
.selectize-dropdown .optgroup .option { padding-left: 26px !important; }

@media (max-width: 767.98px) {
    .card-header         { flex-direction:column; gap:10px; align-items:stretch !important; }
    .card-header .btn, .card-header a.btn { width:100%; }
    .card-header .card-title { margin-bottom:0; }
    .card-header .ml-auto { margin-left:0 !important; flex-wrap:wrap; }
    .custom-toolbar      { flex-direction:column !important; align-items:stretch !important; }
    .custom-toolbar-left { flex-direction:column !important; align-items:stretch !important; width:100%; }
    .custom-toolbar-right { width:100%; }
    .custom-toolbar-right .btn { width:100%; }
    #btnClearFilterEmp   { width:100%; }
    #empRowFilters       { width:100%; }
    #empRowFilters .tcf-row-btn { width:100%; }
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
            dom: 'rt<"d-flex justify-content-between align-items-center mt-3"i<"d-flex align-items-center"lp>>',
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

        TesysColumnFilter.initRowFilters('#empTable', '#empRowFilters', [
            { key: 'status', label: 'Status' },
            { key: 'gender', label: 'Jenis Kelamin', valueLabels: { 'L': 'Laki-laki', 'P': 'Perempuan' } },
        ]);
    }

    $('#btnClearFilterEmp').on('click', function () {
        if (!table) return;
        TesysColumnFilter.clearAll('#empTable');
    });

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
            confirmButtonColor: 'var(--danger)', cancelButtonColor: '#55595e',
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