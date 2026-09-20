@extends('layouts.app')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ url('/payroll-run') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Detail Payroll Run</h1>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="{{ url('/payroll-run') }}">Payroll</a></li>
                <li class="breadcrumb-item active">{{ $run->code }}</li>
            </ol>
        </div>

        <div class="row">

            {{-- SIDEBAR --}}
            <div class="col-md-4">
                <div class="card shadow-sm" style="background:var(--charcoal);">
                    <div class="card-body text-white">
                        <div class="text-center mb-3">
                            <div style="width:64px;height:64px;border-radius:50%;background:var(--accent);display:inline-flex;align-items:center;justify-content:center;">
                                <i class="fas fa-file-invoice-dollar fa-2x text-white"></i>
                            </div>
                            <h4 class="mt-2 mb-0 font-weight-bold">{{ $run->code }}</h4>
                            <span class="badge badge-{{ $run->status_color }} px-3 py-1 mt-2">{{ $run->status }}</span>
                        </div>

                        <hr style="border-color:rgba(255,255,255,0.08);">

                        <div class="mb-3">
                            <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Periode</span>
                            <div class="text-white mt-1" style="font-size:14px;font-weight:600;">{{ $run->period_label }}</div>
                        </div>
                        @if($run->description)
                        <div class="mb-3">
                            <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Deskripsi</span>
                            <div class="text-white mt-1" style="font-size:13px;">{{ $run->description }}</div>
                        </div>
                        @endif
                        <div class="mb-3">
                            <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Budget Payroll</span>
                            <div class="text-white mt-1" style="font-size:12px;">
                                @if($run->budgetPlan)
                                    [{{ $run->budgetPlan->code }}] {{ $run->budgetPlan->costCenter->name ?? '-' }}
                                @else
                                    <span style="color:var(--text-faint);">—</span>
                                @endif
                            </div>
                        </div>
                        <div class="mb-3">
                            <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Jumlah Karyawan</span>
                            <div class="text-white mt-1" style="font-size:14px;font-weight:600;">{{ $run->lines->count() }} orang</div>
                        </div>

                        <hr style="border-color:rgba(255,255,255,0.08);">

                        <div class="text-center">
                            <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Total Net</span>
                            <div class="mt-1" style="font-size:22px;font-weight:700;color:var(--accent);">{{ $run->total_formatted }}</div>
                        </div>

                        <hr style="border-color:rgba(255,255,255,0.08);">

                        {{-- AKSI WORKFLOW --}}
                        @if($run->isEditable())
                        <a href="{{ url('/payroll-run/' . $run->id . '/edit') }}" class="btn btn-warning btn-block">
                            <i class="fas fa-edit mr-1"></i> Edit
                        </a>
                        @endif

                        @if($perms['can_submit'])
                        <form action="{{ url('/payroll-run/' . $run->id . '/submit') }}" method="POST" class="frm-action">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-block"
                                    data-confirm="Submit payroll ini untuk diverifikasi?">
                                <i class="fas fa-paper-plane mr-1"></i> Submit
                            </button>
                        </form>
                        @endif

                        @if($perms['can_verify'])
                        <form action="{{ url('/payroll-run/' . $run->id . '/verify') }}" method="POST" class="frm-action">
                            @csrf
                            <button type="submit" class="btn btn-info btn-block"
                                    data-confirm="Verifikasi payroll ini?">
                                <i class="fas fa-check-double mr-1"></i> Verifikasi
                            </button>
                        </form>
                        @endif

                        @if($perms['can_approve'])
                        <form action="{{ url('/payroll-run/' . $run->id . '/approve') }}" method="POST" class="frm-action">
                            @csrf
                            <button type="submit" class="btn btn-success btn-block"
                                    data-confirm="Setujui payroll ini? Setelah disetujui, payroll siap dibayar.">
                                <i class="fas fa-check-circle mr-1"></i> Setujui
                            </button>
                        </form>
                        @endif

                        @if($perms['can_reject'])
                        <button type="button" class="btn btn-danger btn-block" id="btnReject">
                            <i class="fas fa-times-circle mr-1"></i> Tolak
                        </button>
                        @endif

                        @if($perms['can_reopen'])
                        <form action="{{ url('/payroll-run/' . $run->id . '/reopen') }}" method="POST" class="frm-action">
                            @csrf
                            <button type="submit" class="btn btn-warning btn-block"
                                    data-confirm="Buka kembali payroll ke DRAFT untuk diperbaiki?">
                                <i class="fas fa-undo mr-1"></i> Buka Kembali
                            </button>
                        </form>
                        @endif

                        @if($perms['can_export'])
                        <a href="{{ url('/payroll-run/' . $run->id . '/export') }}" class="btn btn-outline-light btn-block">
                            <i class="fas fa-file-download mr-1"></i> Export Bank (CSV)
                        </a>
                        @endif

                        @if($perms['can_pay'])
                        <form action="{{ url('/payroll-run/' . $run->id . '/paid') }}" method="POST" class="frm-action">
                            @csrf
                            <button type="submit" class="btn btn-success btn-block"
                                    data-confirm="Tandai payroll ini sudah dibayar? Lakukan setelah transfer bank selesai.">
                                <i class="fas fa-money-check-alt mr-1"></i> Tandai Dibayar
                            </button>
                        </form>
                        @endif

                        @if($perms['can_close'])
                        <form action="{{ url('/payroll-run/' . $run->id . '/close') }}" method="POST" class="frm-action">
                            @csrf
                            <button type="submit" class="btn btn-dark btn-block"
                                    data-confirm="Tutup payroll ini? Status menjadi CLOSED.">
                                <i class="fas fa-lock mr-1"></i> Tutup (Close)
                            </button>
                        </form>
                        @endif
                    </div>
                </div>
            </div>

            {{-- MAIN --}}
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title font-weight-bold text-dark">
                            <i class="fas fa-users mr-1 text-primary"></i> Daftar Karyawan
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0" style="font-size:12px;">
                                <thead style="background:var(--surface-2);">
                                    <tr>
                                        <th scope="col" style="color:var(--text-faint);font-size:11px;width:5%;">No</th>
                                        <th scope="col" style="color:var(--text-faint);font-size:11px;">Karyawan</th>
                                        <th scope="col" style="color:var(--text-faint);font-size:11px;">Rekening</th>
                                        <th scope="col" style="color:var(--text-faint);font-size:11px;">Dibebankan</th>
                                        <th scope="col" style="color:var(--text-faint);font-size:11px;text-align:right;">Net</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($run->lines as $line)
                                    <tr>
                                        <td class="text-center">{{ $loop->iteration }}</td>
                                        <td>
                                            <div style="font-weight:600;">{{ $line->employee_name }}</div>
                                            @if($line->position)
                                                <small class="text-muted">{{ $line->position }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <div>{{ $line->bank->name ?? '-' }}</div>
                                            <small class="text-muted">{{ $line->account_number ?? '-' }}</small>
                                        </td>
                                        <td>
                                            @if($line->isChargedToProject())
                                                <span class="badge badge-info px-2 py-1" style="font-size:9px;">PROJECT</span>
                                                <div style="font-size:11px;margin-top:2px;">{{ $line->chargeProject->name ?? '-' }}</div>
                                            @else
                                                <span class="badge badge-secondary px-2 py-1" style="font-size:9px;">OVERHEAD</span>
                                            @endif
                                        </td>
                                        <td class="text-right"><strong>{{ $line->net_formatted }}</strong></td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="5" class="text-center text-muted py-3">Belum ada karyawan.</td></tr>
                                    @endforelse
                                </tbody>
                                @if($run->lines->count())
                                <tfoot style="background:var(--surface-2);">
                                    <tr>
                                        <th colspan="4" class="text-right" style="font-size:12px;">TOTAL</th>
                                        <th class="text-right" style="font-size:13px;color:var(--info);">{{ $run->total_formatted }}</th>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>

                {{-- RINGKASAN PEMBEBANAN --}}
                @php
                    $overheadTotal = $run->lines->where('charge_type', 'OVERHEAD')->sum('net_amount');
                    $projectTotal  = $run->lines->where('charge_type', 'PROJECT')->sum('net_amount');
                @endphp
                <div class="row">
                    <div class="col-md-6">
                        <div class="card shadow-sm">
                            <div class="card-body text-center py-3">
                                <span class="text-muted" style="font-size:11px;text-transform:uppercase;font-weight:600;">Beban Overhead</span>
                                <div style="font-size:18px;font-weight:700;color:var(--text-faint);">{{ number_format($overheadTotal, 0, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card shadow-sm">
                            <div class="card-body text-center py-3">
                                <span class="text-muted" style="font-size:11px;text-transform:uppercase;font-weight:600;">Beban Project</span>
                                <div style="font-size:18px;font-weight:700;color:var(--info);">{{ number_format($projectTotal, 0, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- CATATAN PENOLAKAN --}}
                @if($run->status === 'REJECTED' && $run->reject_notes)
                <div class="alert alert-danger mt-2" style="font-size:13px;">
                    <strong><i class="fas fa-times-circle mr-1"></i> Ditolak:</strong>
                    {{ $run->reject_notes }}
                </div>
                @endif

                {{-- TIMELINE APPROVAL --}}
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title font-weight-bold text-dark">
                            <i class="fas fa-stream mr-1 text-secondary"></i> Jejak Persetujuan
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-3">
                                <div style="font-size:11px;color:var(--text-faint);text-transform:uppercase;font-weight:600;">Dibuat</div>
                                <div style="font-size:12px;font-weight:600;margin-top:4px;">{{ $run->inputBy->name ?? '-' }}</div>
                                <div style="font-size:10px;color:var(--text-faint);">{{ $run->created_at?->format('d M Y H:i') ?? '-' }}</div>
                            </div>
                            <div class="col-3">
                                <div style="font-size:11px;color:var(--text-faint);text-transform:uppercase;font-weight:600;">Submit (HR)</div>
                                <div style="font-size:12px;font-weight:600;margin-top:4px;">{{ $run->submittedBy->name ?? '—' }}</div>
                                <div style="font-size:10px;color:var(--text-faint);">{{ $run->submitted_at?->format('d M Y H:i') ?? '' }}</div>
                            </div>
                            <div class="col-3">
                                <div style="font-size:11px;color:var(--text-faint);text-transform:uppercase;font-weight:600;">Verifikasi (Fin.Mgr)</div>
                                <div style="font-size:12px;font-weight:600;margin-top:4px;">{{ $run->verifiedBy->name ?? '—' }}</div>
                                <div style="font-size:10px;color:var(--text-faint);">{{ $run->verified_at?->format('d M Y H:i') ?? '' }}</div>
                            </div>
                            <div class="col-3">
                                <div style="font-size:11px;color:var(--text-faint);text-transform:uppercase;font-weight:600;">Setuju (CEO)</div>
                                <div style="font-size:12px;font-weight:600;margin-top:4px;">{{ $run->approvedBy->name ?? '—' }}</div>
                                <div style="font-size:10px;color:var(--text-faint);">{{ $run->approved_at?->format('d M Y H:i') ?? '' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- MODAL REJECT --}}
@if($perms['can_reject'])
<div class="modal fade" id="modalReject" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg); overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal); border-bottom:3px solid var(--danger);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-times-circle mr-2 text-danger"></i> Tolak Payroll
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="{{ url('/payroll-run/' . $run->id . '/reject') }}" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Alasan Penolakan</label>
                        <textarea name="reject_notes" class="form-control" rows="3"
                                  placeholder="Jelaskan alasan penolakan (opsional)..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times-circle mr-1"></i> Tolak Payroll
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
$(function () {
    // Konfirmasi aksi workflow
    $(document).on('click', '.frm-action button[type=submit]', function (e) {
        e.preventDefault();
        var btn  = this;
        var form = btn.closest('form');
        var msg  = btn.getAttribute('data-confirm') || 'Lanjutkan aksi ini?';
        Swal.fire({
            title: 'Konfirmasi',
            text: msg,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#92232b',
            cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Lanjutkan',
            cancelButtonText: 'Batal'
        }).then(function (r) { if (r.isConfirmed) form.submit(); });
    });

    // Buka modal reject
    $('#btnReject').on('click', function () {
        $('#modalReject').modal('show');
    });
});
</script>
@endpush