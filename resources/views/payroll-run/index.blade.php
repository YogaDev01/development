@extends('layouts.app')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Payroll Run</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Payroll</a></li>
                <li class="breadcrumb-item active">Payroll Run</li>
            </ol>
        </div>

        <!-- SUMMARY WORKFLOW (clickable, filter tabel Payroll Run) -->
        @php
            $runSteps = [
                ['Draft',     $summary['draft'],     'secondary', 'DRAFT'],
                ['Submitted', $summary['submitted'], 'warning',   'SUBMITTED'],
                ['Verified',  $summary['verified'],  'info',      'VERIFIED'],
                ['Approved',  $summary['approved'],  'primary',   'APPROVED'],
                ['Paid',      $summary['paid'],      'success',   'PAID'],
                ['Closed',    $summary['closed'],    'dark',      'CLOSED'],
                ['Rejected',  $summary['rejected'],  'danger',    'REJECTED'],
            ];
        @endphp
        <x-workflow-stepper :steps="$runSteps" :compact="true" table="runTable" field="status" />

        <div class="card card-primary card-outline shadow-sm mt-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-file-invoice-dollar mr-1"></i> Daftar Payroll Run</h3>
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
                        <button aria-label="Clear Filter" type="button" id="btnClearFilterRun" class="btn btn-outline-secondary btn-sm" title="Clear Filter">
                            <i class="fas fa-eraser"></i>
                        </button>
                    </div>
                    <div class="custom-toolbar-right d-flex flex-wrap" style="gap:8px;">
                        <a href="{{ url('/payroll-run/create') }}" class="btn btn-success btn-sm">
                            <i class="fas fa-plus mr-1"></i> Buat Payroll Run
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="runTable" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" class="text-nowrap" style="width:1%;">No</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Kode</th>
                                <th scope="col">Periode / Deskripsi</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Karyawan</th>
                                <th scope="col" class="text-nowrap text-right" style="width:1%;">Total</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Status</th>
                                <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
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
                                <td class="text-center action-column">
                                    <div class="action-buttons ts-actions">

                                        {{-- AKSI UTAMA — Edit selagi run masih bisa disunting,
                                             selebihnya Detail. Satu aksi utama per baris. --}}
                                        @if($run->isEditable())
                                        <a href="{{ url('/payroll-run/' . $run->id . '/edit') }}"
                                           class="ts-action-primary is-neutral"
                                           title="Edit" aria-label="Edit {{ $run->code }}">
                                            <i class="fas fa-pen" aria-hidden="true"></i><span class="ts-al">Edit</span>
                                        </a>
                                        @else
                                        <a href="{{ url('/payroll-run/' . $run->id) }}"
                                           class="ts-action-primary is-neutral"
                                           title="Detail" aria-label="Detail {{ $run->code }}">
                                            <i class="fas fa-eye" aria-hidden="true"></i><span class="ts-al">Detail</span>
                                        </a>
                                        @endif

                                        @if($run->isEditable())
                                        <button type="button" class="ts-action-more"
                                                aria-haspopup="true" aria-expanded="false"
                                                title="Aksi lain" aria-label="Aksi lain untuk {{ $run->code }}">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>

                                        <div class="ts-action-menu dropdown-menu" hidden>

                                            <a href="{{ url('/payroll-run/' . $run->id) }}" class="dropdown-item">
                                                <i class="fas fa-eye"></i>Detail
                                            </a>

                                            <div class="dropdown-divider"></div>

                                            <button type="button" class="dropdown-item is-danger btn-delete"
                                                    data-id="{{ $run->id }}" data-code="{{ $run->code }}">
                                                <i class="fas fa-trash"></i>Hapus
                                            </button>

                                        </div>
                                        @endif

                                    </div>
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
.card-header .btn, .card-header a.btn { border-radius:var(--radius-sm); font-weight:600; }
.custom-toolbar      { display:flex; justify-content:space-between; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; margin-bottom:0; white-space:nowrap; font-weight:600; color:var(--text-dim); }
.toolbar-input       { min-width:180px; }
#customSearch input  { width:180px; border-radius:var(--radius-sm); }

.ts-dropdown .optgroup-header, .selectize-dropdown .optgroup-header {
    font-weight:700 !important; font-size:11px !important; text-transform:uppercase;
    letter-spacing:.06em; color:var(--text) !important; background:var(--surface-2) !important;
    padding:8px 12px !important; border-top:1px solid var(--border); cursor:default;
}
.ts-dropdown .optgroup .option, .selectize-dropdown .optgroup .option { padding-left:26px !important; }

@media (max-width: 767.98px) {
    .card-header         { flex-direction:column; gap:10px; align-items:stretch !important; }
    .card-header .btn, .card-header a.btn { width:100%; }
    .card-header .card-title { margin-bottom:0; }
    .card-header .ml-auto { margin-left:0 !important; flex-wrap:wrap; }
    .custom-toolbar      { flex-direction:column !important; align-items:stretch !important; }
    .custom-toolbar-left { flex-direction:column !important; align-items:stretch !important; width:100%; }
    .custom-toolbar-right { width:100%; }
    .custom-toolbar-right .btn { width:100%; }
    #btnClearFilterRun   { width:100%; }
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
            order: [], // pertahankan urutan asli dari server (input terbaru di atas)
            dom: 'rt<"d-flex justify-content-between align-items-center mt-3"ip>',
            columnDefs: [
                { targets: [0], visible: false, orderable: false },
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

    $('#refreshTable').on('click', function () { location.reload(); });

    $('#btnClearFilterRun').on('click', function () {
        if (!table) return;
        TesysColumnFilter.clearAll('#runTable');
        TesysWorkflowStepper.clear('runTable');
    });

    $(document).on('click', '.btn-delete', function (e) {
        e.preventDefault();
        var id = $(this).data('id'), code = $(this).data('code');
        Swal.fire({
            title: 'Hapus Payroll Run?',
            text: code + ' akan dihapus permanen.',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: 'var(--danger)', cancelButtonColor: '#55595e',
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