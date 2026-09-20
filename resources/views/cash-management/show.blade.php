@extends('layouts.app')

@section('title', 'Cash Flow ' . $cashFlow->code)

@section('content')

<div class="content">
    <div class="container-fluid">

        @if(session('success'))
        <div class="alert alert-success rounded-0 shadow-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div class="alert alert-danger rounded-0 shadow-sm">{{ session('error') }}</div>
        @endif

        @php
            $stBadge = ['DRAFT' => 'badge-secondary', 'POSTED' => 'badge-success', 'CANCELLED' => 'badge-danger'][$cashFlow->status] ?? 'badge-secondary';
        @endphp

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">
                    {{ $cashFlow->code }}
                    <span class="badge {{ $stBadge }} ml-2" style="font-size:11px;vertical-align:middle;">{{ $cashFlow->status }}</span>
                </h1>
                <small class="text-muted">{{ $cashFlow->description }}</small>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="{{ url('/cash-management') }}">Cash Management</a></li>
                <li class="breadcrumb-item active">{{ $cashFlow->code }}</li>
            </ol>
        </div>

        @if($cashFlow->isCancelled())
        <div class="alert alert-danger rounded-0 shadow-sm" style="font-size:12px;">
            <i class="fas fa-ban mr-1"></i>
            Dibatalkan oleh {{ strtoupper($cashFlow->cancelledBy->username ?? '-') }}
            pada {{ $cashFlow->cancelled_at?->format('d M Y H:i') }} — {{ $cashFlow->cancel_reason }}.
            Dokumen ini tidak dihitung di Buku Kas.
        </div>
        @endif

        <x-stat-cards :cards="[
            ['Cash In', 'Rp ' . number_format($cashFlow->total_in, 0, ',', '.'), 'success', 'fas fa-arrow-down'],
            ['Cash Out', 'Rp ' . number_format($cashFlow->total_out, 0, ',', '.'), 'danger', 'fas fa-arrow-up'],
            ['Net', 'Rp ' . number_format($cashFlow->net_amount, 0, ',', '.'), $cashFlow->net_amount >= 0 ? 'primary' : 'warning', 'fas fa-balance-scale'],
        ]" />

        <div class="row mt-3">
            <div class="col-lg-4">
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-info-circle mr-1"></i> Informasi</h3></div>
                    <div class="card-body" style="font-size:12px;">
                        <dl class="row mb-0">
                            <dt class="col-5 text-muted">Tanggal</dt>
                            <dd class="col-7">{{ $cashFlow->date?->format('d M Y') }}</dd>

                            <dt class="col-5 text-muted">Rekening</dt>
                            <dd class="col-7">{{ $cashFlow->sourceFund->name ?? '-' }}</dd>

                            <dt class="col-5 text-muted">No. Referensi</dt>
                            <dd class="col-7">{{ $cashFlow->reference_number ?: '-' }}</dd>

                            @if($cashFlow->source_module)
                            <dt class="col-5 text-muted">Dokumen Asal</dt>
                            <dd class="col-7">{{ $cashFlow->source_module }} #{{ $cashFlow->source_id }}</dd>
                            @endif

                            <dt class="col-5 text-muted">Dibuat Oleh</dt>
                            <dd class="col-7">{{ strtoupper($cashFlow->createdBy->username ?? '-') }}</dd>

                            @if($cashFlow->posted_at)
                            <dt class="col-5 text-muted">Di-post Oleh</dt>
                            <dd class="col-7">
                                {{ strtoupper($cashFlow->postedBy->username ?? '-') }}<br>
                                <span class="text-muted">{{ $cashFlow->posted_at->format('d M Y H:i') }}</span>
                            </dd>
                            @endif

                            <dt class="col-5 text-muted">Catatan</dt>
                            <dd class="col-7">{{ $cashFlow->notes ?: '-' }}</dd>
                        </dl>
                    </div>
                    <div class="card-footer d-flex flex-wrap" style="gap:6px;">
                        @if($cashFlow->isDraft())
                            <a href="{{ route('cash-management.edit', $cashFlow->id) }}" class="btn btn-sm btn-warning">
                                <i class="fas fa-pen mr-1"></i> Edit
                            </a>
                            <form action="{{ route('cash-management.post', $cashFlow->id) }}" method="POST" class="d-inline js-confirm"
                                  data-title="Post ke Buku Kas?"
                                  data-text="Setelah di-post, dokumen tidak bisa diubah lagi.">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="fas fa-check mr-1"></i> Post ke Buku Kas
                                </button>
                            </form>
                            <form action="{{ route('cash-management.destroy', $cashFlow->id) }}" method="POST" class="d-inline js-confirm"
                                  data-title="Hapus Cash Flow?"
                                  data-text="Dokumen DRAFT ini akan dihapus.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash mr-1"></i> Hapus
                                </button>
                            </form>
                        @endif

                        @if($cashFlow->isPosted())
                            <button type="button" class="btn btn-sm btn-outline-danger" data-toggle="modal" data-target="#modalCancel">
                                <i class="fas fa-ban mr-1"></i> Batalkan
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-list-ul mr-1"></i> Rincian</h3></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th scope="col" style="width:4%;" class="text-center">No</th>
                                        <th scope="col">Kategori</th>
                                        <th scope="col">Deskripsi</th>
                                        <th scope="col" class="text-nowrap">Referensi</th>
                                        <th scope="col" class="text-right text-nowrap">Cash In</th>
                                        <th scope="col" class="text-right text-nowrap">Cash Out</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($cashFlow->details as $d)
                                    <tr>
                                        <td class="text-center">{{ $loop->iteration }}</td>
                                        <td>{{ $d->category_label }}</td>
                                        <td>{{ $d->description }}</td>
                                        <td class="text-nowrap">{{ $d->reference_number ?: '-' }}</td>
                                        <td class="text-right text-success">{{ $d->direction === 'IN' ? number_format($d->amount, 0, ',', '.') : '-' }}</td>
                                        <td class="text-right text-danger">{{ $d->direction === 'OUT' ? number_format($d->amount, 0, ',', '.') : '-' }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6">
                                            <x-empty title="Belum ada rincian" icon="fas fa-list-ul" compact />
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                                @if($cashFlow->details->isNotEmpty())
                                <tfoot>
                                    <tr style="background:var(--surface-2);font-weight:700;">
                                        <th colspan="4" class="text-right">Total</th>
                                        <th class="text-right text-success">{{ number_format($cashFlow->total_in, 0, ',', '.') }}</th>
                                        <th class="text-right text-danger">{{ number_format($cashFlow->total_out, 0, ',', '.') }}</th>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- MODAL BATALKAN --}}
<div class="modal fade" id="modalCancel" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white"><i class="fas fa-ban mr-2" style="color:var(--accent);"></i>Batalkan Cash Flow</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="{{ route('cash-management.cancel', $cashFlow->id) }}" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <p class="text-muted" style="font-size:12px;">
                        Dokumen tidak dihapus — hanya berhenti dihitung di Buku Kas, dan jejaknya tetap bisa ditelusuri.
                    </p>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Alasan Pembatalan <span class="text-danger">*</span></label>
                        <input type="text" name="cancel_reason" class="form-control" maxlength="255" required>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);border-top:1px solid var(--border);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Batalkan Dokumen</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
.font-weight-600 { font-weight:600; }
.table td, .table th { vertical-align:middle !important; }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    // Konfirmasi untuk aksi yang tidak bisa dibatalkan begitu saja.
    $('.js-confirm').on('submit', function (e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: $(form).data('title') || 'Lanjutkan?',
            text: $(form).data('text') || '',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#9a3030',
            cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Lanjutkan',
            cancelButtonText: 'Batal'
        }).then(function (r) { if (r.isConfirmed) form.submit(); });
    });
});
</script>
@endpush
