@extends('layouts.app')

@section('title', 'Detail Submission')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ $authorized && !$isOwner ? route('feedback.inbox') : route('feedback.index') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">
                        {{ $submission->title }}
                        <span class="badge badge-{{ $submission->status_color }} px-2 py-1 align-middle ml-1" style="font-size:12px;">
                            {{ $submission->status_label }}
                        </span>
                    </h1>
                    <small class="text-muted">
                        <span class="badge badge-{{ $submission->category_color }} px-2 py-1">{{ $submission->category_label }}</span>
                        &middot; {{ $submission->created_at->format('d/m/Y H:i') }}
                    </small>
                </div>
            </div>
        </div>

        @if(session('success'))
        <div class="alert alert-success rounded-0 shadow-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div class="alert alert-danger rounded-0 shadow-sm">{{ session('error') }}</div>
        @endif

        @include('feedback._disclaimer', ['compact' => true])

        <div class="row">
            <div class="col-md-7">

                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-comment-dots mr-1"></i> Isi Submission</h3>
                    </div>
                    <div class="card-body">
                        @if($submission->category === 'CRITICISM')
                        <div class="mb-3">
                            <div class="text-muted font-weight-600 mb-1" style="font-size:11px;text-transform:uppercase;">Kritik</div>
                            <div style="white-space:pre-line;font-size:13px;">{{ $submission->criticism_text }}</div>
                        </div>
                        <div>
                            <div class="text-muted font-weight-600 mb-1" style="font-size:11px;text-transform:uppercase;">Saran Perbaikan</div>
                            <div style="white-space:pre-line;font-size:13px;">{{ $submission->suggestion_text }}</div>
                        </div>
                        @else
                        <div style="white-space:pre-line;font-size:13px;">{{ $submission->message }}</div>
                        @endif
                    </div>
                </div>

                @if($submission->response_text)
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-reply mr-1"></i> Tanggapan CEO</h3>
                    </div>
                    <div class="card-body">
                        <div style="white-space:pre-line;font-size:13px;">{{ $submission->response_text }}</div>
                        <div class="text-muted mt-2" style="font-size:11px;">
                            Oleh {{ $submission->respondedBy->name ?? '-' }} &middot; {{ $submission->responded_at?->format('d/m/Y H:i') }}
                        </div>
                    </div>
                </div>
                @endif

                @if($authorized && $submission->status !== 'CLOSED')
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-reply mr-1"></i> Beri Tanggapan</h3>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('feedback.respond', $submission->id) }}" method="POST">
                            @csrf
                            <div class="form-group">
                                <textarea name="response_text" class="form-control form-control-sm" rows="4"
                                          placeholder="Tuliskan tanggapan Anda kepada karyawan ini..." required>{{ $submission->response_text }}</textarea>
                            </div>
                            <div class="text-right">
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-paper-plane mr-1"></i> Kirim Tanggapan</button>
                            </div>
                        </form>
                    </div>
                </div>
                @endif

                @if($authorized && $submission->status === 'RESPONDED')
                <div class="card shadow-sm">
                    <div class="card-body d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
                        <small class="text-muted">Kalau tanggapan sudah cukup dan tidak ada tindak lanjut lagi, tandai submission ini selesai.</small>
                        <form action="{{ route('feedback.close', $submission->id) }}" method="POST" class="frmCloseFeedback">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check-double mr-1"></i> Tandai Selesai (Closed)</button>
                        </form>
                    </div>
                </div>
                @endif

            </div>

            <div class="col-md-5">
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i> Informasi</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0" style="font-size:12.5px;">
                            @if($authorized)
                            <tr>
                                <th style="width:42%;color:var(--text-faint);font-weight:600;">Pengirim</th>
                                <td>{{ $submission->submitter->name ?? '-' }}</td>
                            </tr>
                            @endif
                            <tr>
                                <th style="color:var(--text-faint);font-weight:600;">Kategori</th>
                                <td><span class="badge badge-{{ $submission->category_color }} px-2 py-1">{{ $submission->category_label }}</span></td>
                            </tr>
                            <tr>
                                <th style="color:var(--text-faint);font-weight:600;">Status</th>
                                <td><span class="badge badge-{{ $submission->status_color }} px-2 py-1">{{ $submission->status_label }}</span></td>
                            </tr>
                            <tr>
                                <th style="color:var(--text-faint);font-weight:600;">Bersedia Diskusi</th>
                                <td>
                                    @if($submission->willing_to_discuss)
                                        <span class="badge badge-success px-2 py-1">Ya</span> — {{ $submission->whatsapp_number }}
                                    @else
                                        <span class="badge badge-light border px-2 py-1">Tidak</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th style="color:var(--text-faint);font-weight:600;">Tanggal Submit</th>
                                <td>{{ $submission->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                            @if($submission->reviewed_at)
                            <tr>
                                <th style="color:var(--text-faint);font-weight:600;">Direview</th>
                                <td>{{ $submission->reviewed_at->format('d/m/Y H:i') }}</td>
                            </tr>
                            @endif
                            @if($submission->responded_at)
                            <tr>
                                <th style="color:var(--text-faint);font-weight:600;">Ditanggapi</th>
                                <td>{{ $submission->responded_at->format('d/m/Y H:i') }}</td>
                            </tr>
                            @endif
                            @if($submission->closed_at)
                            <tr>
                                <th style="color:var(--text-faint);font-weight:600;">Ditutup</th>
                                <td>{{ $submission->closed_at->format('d/m/Y H:i') }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(function () {
    $(document).on('submit', '.frmCloseFeedback', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Tandai Selesai?', text:'Submission ini akan ditandai Closed.', icon:'question', showCancelButton:true, confirmButtonColor:'var(--success)', cancelButtonColor:'#55595e', confirmButtonText:'Ya, Tandai Selesai', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) form.submit(); });
    });
});
</script>
@endpush
