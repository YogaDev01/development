@extends('layouts.app')

@section('title', 'Feedback Inbox')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Feedback Inbox</h1>
                <small class="text-muted">Seluruh submission General Feedback dari karyawan — akses terbatas.</small>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">General</a></li>
                <li class="breadcrumb-item active">Feedback Inbox</li>
            </ol>
        </div>

        <div class="alert alert-warning" style="font-size:12px;">
            <i class="fas fa-user-shield mr-1"></i>
            Halaman ini bersifat <strong>rahasia</strong> — hanya dapat diakses oleh pihak berwenang. Gunakan data ini
            semata untuk perbaikan komunikasi &amp; organisasi, <strong>bukan</strong> sebagai bahan penilaian kinerja
            individu manapun.
        </div>

        <x-stat-cards :cards="[
            ['Total', $summary['total'], 'secondary', 'fas fa-inbox'],
            ['Submitted', $summary['submitted'], 'secondary', 'fas fa-paper-plane'],
            ['Reviewed', $summary['reviewed'], 'info', 'fas fa-eye'],
            ['Responded', $summary['responded'], 'primary', 'fas fa-reply'],
            ['Closed', $summary['closed'], 'success', 'fas fa-check-double'],
        ]" />

        <div class="card card-primary card-outline shadow-sm mt-3">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-envelope-open-text mr-1"></i> Daftar Submission</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="tblFeedbackInbox" class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" style="width:16%;">Pengirim</th>
                                <th scope="col" style="width:10%;">Kategori</th>
                                <th scope="col">Judul</th>
                                <th scope="col" style="width:10%;">Bersedia Diskusi</th>
                                <th scope="col" style="width:10%;">Status</th>
                                <th scope="col" style="width:12%;">Tanggal</th>
                                <th scope="col" style="width:6%;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($submissions as $s)
                            <tr data-status="{{ $s->status }}">
                                <td>{{ $s->submitter->name ?? '-' }}</td>
                                <td><span class="badge badge-{{ $s->category_color }} px-2 py-1">{{ $s->category_label }}</span></td>
                                <td>{{ $s->title }}</td>
                                <td>
                                    @if($s->willing_to_discuss)
                                    <span class="badge badge-success px-2 py-1">Ya</span>
                                    @else
                                    <span class="badge badge-light border px-2 py-1">Tidak</span>
                                    @endif
                                </td>
                                <td><span class="badge badge-{{ $s->status_color }} px-2 py-1">{{ $s->status_label }}</span></td>
                                <td>{{ $s->created_at->format('d/m/Y H:i') }}</td>
                                <td class="text-center">
                                    <a href="{{ route('feedback.show', $s->id) }}" class="btn btn-info btn-sm" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7">
                                    <x-empty title="Belum ada submission." icon="fas fa-inbox" compact />
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
#tblFeedbackInbox { width:100% !important; }
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
    $('#tblFeedbackInbox').DataTable({
        responsive: { details: { type: 'inline' } },
        autoWidth: false, pageLength: 10, lengthChange: true, searching: true,
        order: [[5, 'desc']],
        dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex justify-content-end"lp>>',
        language: { lengthMenu:'Page Size _MENU_', zeroRecords:'Data tidak ditemukan', info:'Menampilkan _START_ sampai _END_ dari _TOTAL_ data', infoEmpty:'Tidak ada data', paginate:{previous:'Previous',next:'Next'} }
    });
});
</script>
@endpush
