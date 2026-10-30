@extends('layouts.app')

@section('title', 'Cash Receipt ' . $header->code)

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
            $allocated   = $header->allocatedAmount();
            $unallocated = $header->unallocatedAmount();
        @endphp

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">
                    {{ $header->code }}
                    <x-status :status="$header->status" context="cash-receipt" size="md" class="ml-2 align-middle" />
                </h1>
                <small class="text-muted">{{ $header->customer->name ?? '-' }} &middot; {{ $header->received_date?->format('d M Y') }}</small>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="{{ route('cash-receipt.index') }}">Cash Receipt</a></li>
                <li class="breadcrumb-item active">{{ $header->code }}</li>
            </ol>
        </div>

        @if($header->isCancelled())
        <div class="alert alert-danger rounded-0 shadow-sm" style="font-size:12px;">
            <i class="fas fa-ban mr-1"></i>
            Dibatalkan oleh {{ strtoupper($header->cancelledBy->username ?? '-') }}
            pada {{ $header->cancelled_at?->format('d M Y H:i') }} — {{ $header->cancel_reason }}.
            Seluruh alokasinya ikut dibatalkan dan status invoice sudah disesuaikan.
        </div>
        @endif

        <x-stat-cards :cards="[
            ['Total Diterima', 'Rp ' . number_format($header->isCancelled() ? 0 : (int) $header->details->sum('amount'), 0, ',', '.'), 'success', 'fas fa-hand-holding-usd'],
            ['Teralokasi', 'Rp ' . number_format($allocated, 0, ',', '.'), 'primary', 'fas fa-link'],
            ['Belum Teralokasi', 'Rp ' . number_format(max(0, $unallocated), 0, ',', '.'), $unallocated > 0 ? 'warning' : 'secondary', 'fas fa-unlink'],
        ]" />

        <div class="row mt-3">
            <div class="col-lg-4">
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-info-circle mr-1"></i> Informasi</h3></div>
                    <div class="card-body" style="font-size:12px;">
                        <dl class="row mb-0">
                            <dt class="col-5 text-muted">Customer</dt>
                            <dd class="col-7">{{ $header->customer->name ?? '-' }}</dd>

                            <dt class="col-5 text-muted">Tanggal Terima</dt>
                            <dd class="col-7">{{ $header->received_date?->format('d M Y') }}</dd>

                            <dt class="col-5 text-muted">Rekening</dt>
                            <dd class="col-7">{{ $header->sourceFund->name ?? '-' }}</dd>

                            <dt class="col-5 text-muted">No. Referensi</dt>
                            <dd class="col-7">{{ $header->reference_number ?: '-' }}</dd>

                            <dt class="col-5 text-muted">Dicatat Oleh</dt>
                            <dd class="col-7">{{ strtoupper($header->createdBy->username ?? '-') }}</dd>

                            <dt class="col-5 text-muted">Catatan</dt>
                            <dd class="col-7">{{ $header->notes ?: '-' }}</dd>
                        </dl>
                    </div>
                    @if(!$header->isCancelled())
                    <div class="card-footer d-flex flex-wrap" style="gap:6px;">
                        <button type="button" class="btn btn-sm btn-outline-danger" data-toggle="modal" data-target="#modalCancel">
                            <i class="fas fa-ban mr-1"></i> Batalkan Penerimaan
                        </button>
                        @if($header->details->isEmpty())
                        <form action="{{ route('cash-receipt.destroy', $header->id) }}" method="POST" class="js-confirm d-inline"
                              data-title="Hapus penerimaan?" data-text="Belum ada alokasi, jadi aman dihapus.">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-trash mr-1"></i> Hapus</button>
                        </form>
                        @endif
                    </div>
                    @endif
                </div>
            </div>

            <div class="col-lg-8">
                {{-- ALOKASI TERCATAT --}}
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-link mr-1"></i> Alokasi ke Invoice</h3></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th scope="col" style="width:4%;" class="text-center">No</th>
                                        <th scope="col">Invoice</th>
                                        <th scope="col" class="text-right text-nowrap">Nominal</th>
                                        <th scope="col" class="text-right text-nowrap">PPh</th>
                                        <th scope="col">Catatan</th>
                                        <th scope="col" class="text-center" style="width:8%;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($header->details as $d)
                                    <tr>
                                        <td class="text-center">{{ $loop->iteration }}</td>
                                        <td>
                                            <a href="{{ url('/invoice-cash-receipt/' . $d->invoice_id) }}" target="_blank">
                                                {{ $d->invoice->code ?? '-' }}
                                            </a>
                                            @if($d->invoice)
                                                <span class="badge badge-secondary ml-1" style="font-size:9px;">{{ $d->invoice->status }}</span>
                                            @endif
                                        </td>
                                        <td class="text-right text-nowrap">{{ number_format($d->amount, 0, ',', '.') }}</td>
                                        <td class="text-right text-nowrap">{{ $d->withholding_tax > 0 ? number_format($d->withholding_tax, 0, ',', '.') : '-' }}</td>
                                        <td>{{ $d->notes ?: '-' }}</td>
                                        <td class="text-center">
                                            @if(!$header->isCancelled())
                                            <form action="{{ route('cash-receipt.remove-allocation', [$header->id, $d->id]) }}" method="POST" class="js-confirm d-inline"
                                                  data-title="Batalkan alokasi?" data-text="Status invoice akan disesuaikan kembali.">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Batalkan alokasi">
                                                    <i class="fas fa-unlink"></i>
                                                </button>
                                            </form>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6">
                                            <x-empty title="Belum ada alokasi" icon="fas fa-unlink" compact />
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                                @if($header->details->isNotEmpty())
                                <tfoot>
                                    <tr style="background:var(--surface-2);font-weight:700;">
                                        <th colspan="2" class="text-right">Total Teralokasi</th>
                                        <th class="text-right">{{ number_format($header->details->sum('amount'), 0, ',', '.') }}</th>
                                        <th class="text-right">{{ number_format($header->details->sum('withholding_tax'), 0, ',', '.') }}</th>
                                        <th colspan="2"></th>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>

                {{-- INVOICE YANG MASIH TERBUKA --}}
                @if(!$header->isCancelled() && $unallocated > 0)
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-file-invoice-dollar mr-1"></i> Invoice Terbuka — {{ $header->customer->name ?? '-' }}</h3>
                    </div>
                    <div class="card-body p-0">
                        <p class="text-muted px-3 pt-3 mb-2" style="font-size:11px;">
                            Sisa tagihan = Invoice + PPN dikurangi nominal yang sudah diterima dan PPh yang sudah dicatat, bukan dari label status —
                            invoice baru menjadi <strong>PAID</strong> setelah diterima + PPh menutup seluruh tagihan.
                        </p>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th scope="col">Invoice</th>
                                        <th scope="col" class="text-nowrap">Jatuh Tempo</th>
                                        <th scope="col" class="text-center">Status</th>
                                        <th scope="col" class="text-right text-nowrap">Tagihan (Invoice + PPN)</th>
                                        <th scope="col" class="text-right text-nowrap">Sisa Tagihan</th>
                                        <th scope="col" class="text-center" style="width:12%;">Alokasi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($openInvoices as $inv)
                                    @php $isOverdue = $inv->isOverdue(); @endphp
                                    <tr>
                                        <td>
                                            <a href="{{ url('/invoice-cash-receipt/' . $inv->id) }}" target="_blank">{{ $inv->code }}</a>
                                            @if($inv->poNumber)
                                                <div class="text-muted" style="font-size:10px;">{{ $inv->poNumber->po_number }}</div>
                                            @endif
                                        </td>
                                        <td class="text-nowrap">
                                            {{ ($inv->actual_due_date ?? $inv->due_date)?->format('d M Y') }}
                                            @if($isOverdue)
                                                <span class="badge badge-danger ml-1" style="font-size:9px;">OVERDUE</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-secondary px-2 py-1">{{ $inv->status }}</span>
                                        </td>
                                        <td class="text-right text-nowrap">{{ number_format((int) $inv->billed_amount + (int) $inv->tax_amount, 0, ',', '.') }}</td>
                                        <td class="text-right text-nowrap text-danger">
                                            <strong>{{ number_format($inv->computed_balance, 0, ',', '.') }}</strong>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-success js-allocate"
                                                    data-id="{{ $inv->id }}"
                                                    data-code="{{ $inv->code }}"
                                                    data-balance="{{ $inv->computed_balance }}"
                                                    data-unallocated="{{ max(0, $unallocated) }}">
                                                <i class="fas fa-link mr-1"></i> Alokasi
                                            </button>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6">
                                            <x-empty title="Tidak ada invoice terbuka untuk customer ini" icon="fas fa-check-circle" compact />
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>

    </div>
</div>

{{-- MODAL ALOKASI --}}
<div class="modal fade" id="modalAllocate" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white"><i class="fas fa-link mr-2" style="color:var(--accent);"></i>Alokasi Pembayaran</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="{{ route('cash-receipt.allocate', $header->id) }}" method="POST">
                @csrf
                <input type="hidden" name="invoice_id" id="allocInvoiceId">
                <div class="modal-body" style="padding:24px 28px;">
                    <p class="mb-3" style="font-size:12px;">
                        Invoice <strong id="allocInvoiceCode"></strong> &middot;
                        sisa tagihan <strong id="allocBalanceText"></strong>
                    </p>
                    <div class="form-group">
                        <label class="font-weight-600">Nominal Dialokasikan <span class="text-danger">*</span></label>
                        <input type="text" name="amount" id="allocAmount" class="form-control text-right" required>
                        <small class="form-text text-muted" id="allocHint"></small>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-600">PPh yang Di-settle</label>
                        <input type="text" name="withholding_tax" id="allocWht" class="form-control text-right" placeholder="0">
                        <small class="form-text text-muted">Isi kalau customer memotong PPh dari pembayaran ini. PPh ini akan menggantikan PPh pada invoice.</small>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Catatan</label>
                        <input type="text" name="notes" class="form-control" maxlength="1000">
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);border-top:1px solid var(--border);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Simpan Alokasi</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL BATALKAN --}}
<div class="modal fade" id="modalCancel" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white"><i class="fas fa-ban mr-2" style="color:var(--accent);"></i>Batalkan Penerimaan</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="{{ route('cash-receipt.cancel', $header->id) }}" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <div class="alert alert-warning" style="font-size:12px;">
                        Seluruh alokasi ke invoice ikut dibatalkan, dan status invoice yang terdampak dihitung ulang.
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Alasan Pembatalan <span class="text-danger">*</span></label>
                        <input type="text" name="cancel_reason" class="form-control" maxlength="255" required>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);border-top:1px solid var(--border);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Batalkan Penerimaan</button>
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
    function rp(n) { return (n || 0).toLocaleString('id-ID'); }
    function money(el) {
        $(el).on('input', function () {
            var digits = this.value.replace(/\D/g, '');
            this.value = digits ? parseInt(digits, 10).toLocaleString('id-ID') : '';
        });
    }
    money('#allocAmount');
    money('#allocWht');

    $('.js-allocate').on('click', function () {
        var balance     = parseInt($(this).data('balance'), 10) || 0;
        var unallocated = parseInt($(this).data('unallocated'), 10) || 0;

        // Usulan nominal = yang lebih kecil antara sisa tagihan invoice dan
        // sisa uang yang belum dialokasikan. Backend tetap memeriksa keduanya.
        var suggested = Math.min(balance, unallocated);

        $('#allocInvoiceId').val($(this).data('id'));
        $('#allocInvoiceCode').text($(this).data('code'));
        $('#allocBalanceText').text('Rp ' + rp(balance));
        $('#allocAmount').val(rp(suggested));
        $('#allocWht').val('');
        $('#allocHint').text('Maksimal Rp ' + rp(suggested)
            + ' (sisa tagihan Rp ' + rp(balance) + ', sisa uang belum teralokasi Rp ' + rp(unallocated) + ').');
        $('#modalAllocate').modal('show');
    });

    $('.js-confirm').on('submit', function (e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: $(form).data('title') || 'Lanjutkan?',
            text: $(form).data('text') || '',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: 'var(--danger)',
            cancelButtonColor: 'var(--text-muted)',
            confirmButtonText: 'Ya, Lanjutkan',
            cancelButtonText: 'Batal'
        }).then(function (r) { if (r.isConfirmed) form.submit(); });
    });
});
</script>
@endpush
