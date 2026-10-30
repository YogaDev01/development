@extends('layouts.app')

@section('content')

<div class="content">
    <div class="container-fluid">
      <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        {{-- Judul & breadcrumb topbar: shell memindahkan <h1> dan .breadcrumb pertama ke topbar
             (tesys-shell.js hoistPageTitle); disembunyikan secara visual. --}}
        <div class="tw:sr-only">
            <h1>Payroll Run</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Payroll</li>
                <li class="breadcrumb-item active">Payroll Run</li>
            </ol>
        </div>

        <x-tw.page-header title="Daftar Payroll Run"
            description="Penggajian per periode beserta persetujuan dan pembayarannya lewat Treasury.">
            <x-slot:actions>
                <x-tw.button href="{{ url('/payroll-run/create') }}" class="ts-add-btn tw:max-sm:min-h-12"><i class="fas fa-plus" aria-hidden="true"></i> Buat Payroll Run</x-tw.button>
            </x-slot:actions>
        </x-tw.page-header>

        {{-- Mobile: tombol di header disembunyikan dan diganti tombol melayang (tesys-ui-refine.css). --}}
        <a href="{{ url('/payroll-run/create') }}" class="ts-fab" aria-label="Buat Payroll Run" title="Buat Payroll Run">
            <i class="fas fa-plus" aria-hidden="true"></i>
        </a>

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
        <x-workflow-stepper :steps="$runSteps" :compact="true" table="runTable" field="status" :groups="[['Fase 1: Pengajuan', ['DRAFT','SUBMITTED','REJECTED']], ['Fase 2: Persetujuan', ['VERIFIED','APPROVED']], ['Fase 3: Pembayaran', ['PAID','CLOSED']]]" />

        {{-- Kartu tunggal: toolbar + tabel + paginasi (pola Cost Request). --}}
        <x-tw.card padding="sm">

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
                        <x-tw.button id="refreshTable" variant="secondary" size="sm"><i class="fas fa-sync-alt" aria-hidden="true"></i> Refresh</x-tw.button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="runTable" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" class="text-nowrap" style="width:1%;">No</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Kode</th>
                                <th scope="col" class="text-nowrap">Periode</th>
                                <th scope="col">Deskripsi</th>
                                <th scope="col" class="text-nowrap text-right" style="width:1%;">Karyawan</th>
                                <th scope="col" class="text-nowrap text-right" style="width:1%;">Total</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Status</th>
                                <th scope="col" class="text-center action-column" style="width:1%;"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($runs as $run)
                            <tr data-status="{{ $run->status }}" data-href="{{ url('/payroll-run/' . $run->id) }}">
                                <td class="text-nowrap">{{ $loop->iteration }}</td>
                                <td class="text-nowrap">
                                    {{-- Klik kode = salin; detail dibuka lewat klik baris. --}}
                                    <button type="button" class="ts-copy-code" data-code="{{ $run->code }}" title="Klik untuk menyalin kode">{{ $run->code }}</button>
                                </td>
                                <td class="text-nowrap">{{ $run->period_label }}</td>
                                <td class="ts-col-desc" title="{{ $run->description }}">{{ $run->description ? Str::limit($run->description, 60) : '-' }}</td>
                                <td class="text-nowrap text-right">{{ $run->lines->count() }}</td>
                                {{-- Nilai nol di kolom uang ditulis "-". --}}
                                <td class="text-nowrap text-right ts-col-money">{{ (int) $run->total === 0 ? '-' : $run->total_formatted }}</td>
                                <td class="text-nowrap">
                                    <x-status :status="$run->status" context="payroll-run" />
                                </td>
                                <td class="text-center action-column">
                                    {{-- Detail dibuka lewat klik baris. Run yang masih bisa disunting punya dua aksi (Edit,
                                         Hapus) = satu tombol titik tiga; selebihnya tanpa aksi (sel kosong). --}}
                                    <div class="action-buttons ts-actions">
                                        @if($run->isEditable())
                                        <button type="button" class="ts-action-more"
                                                aria-haspopup="true" aria-expanded="false"
                                                title="Aksi" aria-label="Aksi untuk {{ $run->code }}">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>

                                        <div class="ts-action-menu dropdown-menu" hidden>
                                            <a href="{{ url('/payroll-run/' . $run->id . '/edit') }}" class="dropdown-item">
                                                <i class="fas fa-pen"></i>Edit
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
                                <td colspan="8" class="p-0"><x-empty title="Belum ada payroll run" message="Payroll run yang disiapkan HR akan tampil di sini." icon="fas fa-money-check-alt" compact /></td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

        </x-tw.card>
      </div>
    </div>
</div>

<form id="formDelete" method="POST" style="display:none;">
    @csrf
    @method('DELETE')
</form>

@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
<style>
#runTable tbody tr[data-href] { cursor:pointer; }
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
            dom: 'rt<"d-flex justify-content-between align-items-center mt-3"i<"d-flex align-items-center"lp>>',
            columnDefs: [
                { targets: [0], visible: false, orderable: false },
                { targets: [-1], orderable: false, searchable: false },
                { responsivePriority: 1,   targets: [0, 1, 2, 6, -1] },
                { responsivePriority: 100, targets: [3, 4, 5] }
            ],
            language: {
                info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                paginate: { previous: 'Sebelumnya', next: 'Berikutnya' },
                zeroRecords: '<div class="ts-empty ts-empty-compact">'
                    + '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-search"></i></span>'
                    + '<p class="ts-empty-title">Tidak ada yang cocok</p>'
                    + '<p class="ts-empty-msg">Tidak ada payroll run yang cocok dengan pencarian atau filter yang sedang aktif.</p></div>'
            }
        });

        $('#customSearch').html('<input type="text" class="form-control form-control-sm" placeholder="Cari data...">');
        $('#customSearch input').on('keyup', function () { table.search(this.value).draw(); });
    }

    $('#refreshTable').on('click', function () { location.reload(); });

    /* Klik di mana saja pada baris membuka detail payroll run (pola Cost Request). Kontrol di
       dalam baris (kode yang disalin, menu aksi, kontrol responsif DataTables) bekerja sendiri. */
    $(document).on('click', '#runTable tbody tr[data-href]', function (e) {
        if ($(e.target).closest('a, button, input, select, textarea, label, form, .ts-actions, .ts-action-menu, td.dtr-control').length) return;
        if (window.getSelection && String(window.getSelection()).length) return;
        window.location.href = $(this).attr('data-href');
    });

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
            confirmButtonColor: 'var(--danger)', cancelButtonColor: 'var(--text-muted)',
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