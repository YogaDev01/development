@extends('layouts.app')

@section('title', 'Feedback Inbox')

@section('content')

<div class="content">
    <div class="container-fluid">
        <div class="tw:mx-auto tw:flex tw:max-w-[1200px] tw:flex-col tw:gap-5 tw:py-4">

            {{-- Judul & breadcrumb topbar: shell memindahkan <h1> dan .breadcrumb pertama ke topbar lalu
                 menghapusnya dari konten (tesys-shell.js hoistPageTitle); disembunyikan secara visual. --}}
            <div class="tw:sr-only">
                <h1>Feedback Inbox</h1>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">General</a></li>
                    <li class="breadcrumb-item active">Feedback Inbox</li>
                </ol>
            </div>

            <x-tw.page-header eyebrow="General · Feedback" title="Feedback Inbox"
                description="Seluruh submission General Feedback dari karyawan — akses terbatas." />

            <div role="note" class="tw:flex tw:gap-3 tw:rounded-md tw:bg-warning-bg tw:p-3 tw:text-data tw:leading-body tw:text-warning">
                <i class="fas fa-user-shield tw:mt-0.5" aria-hidden="true"></i>
                <span>Halaman ini bersifat <strong>rahasia</strong> — hanya dapat diakses oleh pihak berwenang. Gunakan data ini
                semata untuk perbaikan komunikasi &amp; organisasi, <strong>bukan</strong> sebagai bahan penilaian kinerja
                individu manapun.</span>
            </div>

            <x-stat-cards :cards="[
                ['Total', $summary['total'], 'secondary', 'fas fa-inbox'],
                ['Submitted', $summary['submitted'], 'secondary', 'fas fa-paper-plane'],
                ['Reviewed', $summary['reviewed'], 'info', 'fas fa-eye'],
                ['Responded', $summary['responded'], 'primary', 'fas fa-reply'],
                ['Closed', $summary['closed'], 'success', 'fas fa-check-double'],
            ]" />

            {{-- Kartu tunggal: toolbar + tabel + paginasi (pola Cost Request). --}}
            <x-tw.card padding="sm">
                <div class="custom-toolbar mb-3 d-flex align-items-center flex-wrap" style="gap:8px;">
                    <div class="toolbar-row">
                        <span class="toolbar-label">Cari :</span>
                        <div class="toolbar-input">
                            <input type="search" id="inboxSearch" class="form-control form-control-sm" placeholder="Cari data...">
                        </div>
                    </div>
                </div>
                    <div class="table-responsive">
                        <table id="tblFeedbackInbox" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:16%;">Pengirim</th>
                                    <th scope="col" style="width:10%;">Kategori</th>
                                    <th scope="col">Judul</th>
                                    <th scope="col" style="width:10%;">Bersedia Diskusi</th>
                                    <th scope="col" style="width:10%;">Status</th>
                                    <th scope="col" style="width:12%;">Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($submissions as $s)
                                <tr data-status="{{ $s->status }}" data-href="{{ route('feedback.show', $s->id) }}">
                                    <td>{{ $s->submitter->name ?? '-' }}</td>
                                    <td><x-tw.badge dot :tone="$s->category_color">{{ $s->category_label }}</x-tw.badge></td>
                                    <td>{{ $s->title }}</td>
                                    <td>
                                        @if($s->willing_to_discuss)
                                        <x-tw.badge tone="positive">Ya</x-tw.badge>
                                        @else
                                        <x-tw.badge tone="neutral">Tidak</x-tw.badge>
                                        @endif
                                    </td>
                                    <td><x-tw.badge dot :tone="$s->status_color">{{ $s->status_label }}</x-tw.badge></td>
                                    <td class="text-nowrap" data-order="{{ $s->created_at->format('Y-m-d H:i:s') }}">{{ $s->created_at->format('d/m/Y H:i') }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6">
                                        <x-empty title="Belum ada submission." icon="fas fa-inbox" compact />
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

@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
<style>
#tblFeedbackInbox { width:100% !important; }
#tblFeedbackInbox tbody tr[data-href] { cursor:pointer; }
.toolbar-row   { display:flex; align-items:center; gap:8px; }
.toolbar-label { min-width:50px; font-weight:600; color:var(--text-dim); white-space:nowrap; margin-bottom:0; }
.toolbar-input { min-width:230px; }
.custom-toolbar .toolbar-input .form-control { width:100%; }
.table td,.table th { vertical-align:middle !important; }
.dataTables_wrapper .row:last-child { margin-top:18px; align-items:center; }
.dataTables_info { padding-top:0 !important; font-size:14px; color:var(--text-faint); }
.dataTables_length { display:flex; align-items:center; margin-left:18px; }
.dataTables_length label { display:flex; align-items:center; gap:10px; margin-bottom:0; font-size:14px; font-weight:600; color:var(--text-faint); }
.dataTables_length select { appearance:none !important; background-color:var(--surface) !important; color:var(--text-dim) !important; border:1px solid var(--border) !important; border-radius:var(--radius-sm) !important; padding:4px 30px 4px 10px !important; height:36px !important; min-width:72px !important; font-size:14px !important; font-weight:600 !important; cursor:pointer; box-shadow:none !important; }
.dataTables_paginate .paginate_button { border-radius:var(--radius-sm) !important; margin:0 2px; }
.dataTables_paginate .paginate_button.current { background:var(--accent) !important; background-image:none !important; border-color:var(--accent-dark) !important; color:#fff !important; font-weight:700 !important; }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    var table = $('#tblFeedbackInbox').DataTable({
        responsive: { details: { type: 'inline' } },
        autoWidth: false, pageLength: 10, lengthChange: true, searching: true,
        order: [[5, 'desc']],
        columnDefs: [{ responsivePriority: 1, targets: [0, 2, 4] }],
        dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex justify-content-end"lp>>',
        language: { lengthMenu:'Page Size _MENU_', zeroRecords:'Data tidak ditemukan', info:'Menampilkan _START_ sampai _END_ dari _TOTAL_ data', infoEmpty:'Tidak ada data', paginate:{previous:'Previous',next:'Next'} }
    });

    $('#inboxSearch').on('keyup search', function () { table.search(this.value).draw(); });

    /* Klik di mana saja pada baris membuka detail submission (pola Cost Request). */
    $(document).on('click', '#tblFeedbackInbox tbody tr[data-href]', function (e) {
        if ($(e.target).closest('a, button, input, select, textarea, label, td.dtr-control').length) return;
        if (window.getSelection && String(window.getSelection()).length) return;
        window.location.href = $(this).attr('data-href');
    });
});
</script>
@endpush
