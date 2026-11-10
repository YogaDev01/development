@extends('layouts.app')

@section('title', 'Settlement Expense')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ route('cost-register.index') }}" class="btn btn-secondary btn-sm mr-3" aria-label="Kembali ke daftar Expense">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Settlement Expense {{ $cr->display_code }}</h1>
            </div>
        </div>

        @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @if($cr->status === 'SETTLE_REJECTED' && $penolakan)
        <div class="alert alert-warning" style="font-size:12.5px;">
            <i class="fas fa-undo mr-1"></i>
            Settlement sebelumnya ditolak CEO ({{ $penolakan->user_name }}, {{ $penolakan->created_at->format('d M Y H:i') }}):
            <strong>{{ $penolakan->notes }}</strong>
        </div>
        @endif

        <div class="card card-primary card-outline shadow-sm">
            <div class="card-body">
                <dl class="ts-dw-kv mb-3">
                    <dt>Expense</dt><dd>{{ $cr->display_code }} · {{ $cr->target_label }}</dd>
                    <dt>Beneficiary</dt><dd>{{ $cr->beneficiary->complete_name ?? $cr->beneficiary->nickname ?? '-' }}</dd>
                    <dt>Nominal dibayar</dt><dd><strong id="paidAmount" data-amount="{{ $cr->amount }}">{{ $cr->amount_formatted }}</strong></dd>
                    <dt>Periode</dt><dd>{{ $cr->period_label }}</dd>
                    <dt>Catatan</dt><dd style="white-space:pre-line;">{{ $cr->notes }}</dd>
                </dl>

                <form action="{{ route('cost-register.settle.store', $cr->id) }}" method="POST" enctype="multipart/form-data" id="frmSettle" novalidate>
                    @csrf

                    <div class="alert alert-light border" style="font-size:12px;">
                        Isi realisasi pemakaian dana per item. Aturan pembebanan sama dengan pengajuan (Variable Cost ke satu PO;
                        Fixed Cost / Overhead ke PO, seluruh PO per tahun, PO terpilih, atau Cost Center). Realisasi lebih kecil =
                        kelebihan dicatat sebagai refund; lebih besar = kekurangan dibayar lewat Second Payment di Treasury setelah
                        disetujui CEO. Pembebanan per bulan disesuaikan ke nilai realisasi saat disetujui.
                    </div>

                    <div class="form-group">
                        <label class="font-weight-600">Rincian Realisasi <span class="text-danger">*</span></label>
                        @include('cost-register._detail_rows', ['totalLabel' => 'Total Realisasi'])
                        <div class="text-right mt-2" style="font-size:13px;">
                            <span id="selisihLabel">Selisih</span>: <strong id="selisihValue" style="font-variant-numeric:tabular-nums;">0</strong>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-600" for="txtSettleNotes">Catatan Settlement</label>
                        <textarea name="settle_notes" id="txtSettleNotes" class="form-control form-control-sm" rows="2" maxlength="1000">{{ old('settle_notes') }}</textarea>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-600" for="inpAttachments">Bukti Realisasi @if($cr->settlementAttachments->isEmpty())<span class="text-danger">*</span>@endif</label>
                        @if($cr->settlementAttachments->isNotEmpty())
                        <div class="mb-1" style="font-size:12px;">
                            Sudah terlampir:
                            @foreach($cr->settlementAttachments as $a)
                                <a href="/storage/{{ $a->file_path }}" target="_blank" rel="noopener" class="mr-2"><i class="fas fa-paperclip"></i> {{ $a->title }}</a>
                            @endforeach
                        </div>
                        @endif
                        <input type="file" name="attachments[]" id="inpAttachments" class="form-control-file" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                        <small class="text-muted">Nota/kuitansi realisasi — bisa lebih dari satu file, maks. 10 MB per file.</small>
                    </div>

                    <div class="text-right mt-3">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane mr-1"></i> Ajukan Settlement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
@php $barisAwal = old('details') ? array_values(old('details')) : $prefill; @endphp
<script>
$(function () {
    var R = window.ExpenseRows;
    var DIBAYAR = parseInt($('#paidAmount').data('amount'), 10) || 0;
    var ADA_BUKTI = {{ $cr->settlementAttachments->isNotEmpty() ? 'true' : 'false' }};

    R.init({
        prefill: @json($barisAwal),
        onChange: function (grand) {
            var selisih = grand - DIBAYAR;
            $('#selisihLabel').text(selisih > 0 ? 'Kekurangan bayar (Second Payment)' : (selisih < 0 ? 'Kelebihan (refund)' : 'Selisih'));
            $('#selisihValue').text(R.fmt(String(Math.abs(selisih))) || '0');
        }
    });

    $('#frmSettle').on('submit', function (e) {
        var pesan = R.validate();
        if (!pesan && !ADA_BUKTI && !$('#inpAttachments').get(0).files.length) pesan = 'Lampirkan minimal satu bukti realisasi.';
        if (pesan) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'Periksa kembali', text: pesan });
        }
    });
});
</script>
@endpush
