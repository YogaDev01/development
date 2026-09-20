@extends('layouts.app')

@section('title', 'Buat Surat Jalan')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ route('delivery-note.index') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Buat Surat Jalan</h1>
                    <small class="text-muted">Bundel invoice APPROVED menjadi satu Surat Jalan (satu customer)</small>
                </div>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item"><a href="{{ route('delivery-note.index') }}">Surat Jalan Invoice</a></li>
                <li class="breadcrumb-item active">Buat</li>
            </ol>
        </div>

        @if($errors->any())
        <div class="alert alert-danger rounded-0 shadow-sm">
            <strong>Terjadi kesalahan:</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif
        @if(session('error'))
        <div class="alert alert-danger rounded-0 shadow-sm">{{ session('error') }}</div>
        @endif

        <form action="{{ route('delivery-note.store') }}" method="POST" id="frmDeliveryNote">
            @csrf
            <div class="row">

                <!-- KIRI: PILIH INVOICE -->
                <div class="col-md-8">
                    <div class="card card-primary card-outline shadow-sm">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-file-invoice mr-1"></i> Pilih Invoice (status APPROVED)</h3>
                        </div>
                        <div class="card-body p-0">
                            @forelse($groupedByCustomer as $customerId => $invoices)
                            <div class="dn-customer-group">
                                <div class="dn-group-header">{{ $invoices->first()->po->customer->name ?? '-' }}</div>
                                @foreach($invoices as $inv)
                                <label class="dn-invoice-item d-flex align-items-start">
                                    <input type="checkbox" name="invoice_ids[]" value="{{ $inv->id }}"
                                           class="dn-checkbox mt-1" data-customer="{{ $customerId }}">
                                    <div class="dn-invoice-body">
                                        <div class="dn-invoice-row">
                                            <span class="dn-invoice-code">{{ $inv->code }}</span>
                                            <span class="dn-invoice-amount" data-raw="{{ $inv->captured_total }}">{{ $inv->captured_total_formatted }}</span>
                                        </div>
                                        <div class="dn-invoice-desc">{{ $inv->po->name ?? '-' }}</div>
                                    </div>
                                </label>
                                @endforeach
                            </div>
                            @empty
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-inbox d-block mb-2" style="font-size:28px;"></i>
                                Tidak ada invoice berstatus APPROVED yang tersedia.
                            </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- KANAN: RINGKASAN -->
                <div class="col-md-4">
                    <div class="card card-primary card-outline shadow-sm">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-clipboard-list mr-1"></i> Ringkasan</h3>
                        </div>
                        <div class="card-body form-clean">
                            <div class="d-flex justify-content-between align-items-baseline mb-2">
                                <span class="calc-caption">Customer</span>
                                <strong id="dnSelCustomer" style="font-size:13px;">-</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-baseline mb-2">
                                <span class="calc-caption">Jumlah Invoice</span>
                                <strong id="dnSelCount" style="font-size:13px;">0</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-baseline mb-2">
                                <span class="calc-caption">Total Nilai</span>
                                <strong id="dnSelTotal" class="text-primary" style="font-size:15px;">0</strong>
                            </div>

                            <hr>

                            <div class="form-group mb-0">
                                <label class="font-weight-600">Catatan (opsional)</label>
                                <textarea name="notes" class="form-control form-control-sm" rows="3" placeholder="Catatan pengiriman..."></textarea>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-success btn-block" id="btnSubmitDn" disabled>
                                <i class="fas fa-truck mr-1"></i> Buat Surat Jalan
                            </button>
                            <small class="text-muted d-block mt-2">
                                <i class="fas fa-info-circle mr-1"></i>
                                Invoice yang dipilih akan ditandai <strong>SENT</strong>.
                            </small>
                        </div>
                    </div>
                </div>

            </div>
        </form>

    </div>
</div>

@endsection

@push('styles')
<style>
.font-weight-600 { font-weight:600; }

.calc-caption {
    font-size:11px; font-weight:700; letter-spacing:.4px;
    text-transform:uppercase; color:var(--text-faint);
}

.dn-customer-group { border-bottom:1px solid var(--border); }
.dn-customer-group:last-child { border-bottom:none; }
.dn-group-header {
    padding:10px 16px; background:var(--surface-2); font-weight:700; font-size:12px;
    color:var(--text-dim); border-bottom:1px solid var(--border);
}
.dn-invoice-item {
    display:flex; align-items:flex-start; gap:10px; padding:10px 16px;
    margin-bottom:0; cursor:pointer; border-bottom:1px solid var(--border);
}
.dn-invoice-item:last-child { border-bottom:none; }
.dn-invoice-item:hover { background:var(--surface-2); }
.dn-invoice-body { flex:1; min-width:0; }
.dn-invoice-row { display:flex; justify-content:space-between; align-items:baseline; gap:10px; }
.dn-invoice-code { font-size:12px; font-weight:700; color:var(--text); }
.dn-invoice-amount { font-size:12.5px; font-weight:700; color:var(--info); white-space:nowrap; }
.dn-invoice-desc { font-size:12px; color:var(--text-faint); margin-top:2px; }
</style>
@endpush

@push('scripts')
<script>
$(function () {

    function recalc() {
        var total = 0, count = 0, customerName = '-';

        $('.dn-checkbox:checked').each(function () {
            var $item = $(this).closest('.dn-invoice-item');
            total += parseInt($item.find('.dn-invoice-amount').data('raw')) || 0;
            count++;
            customerName = $(this).closest('.dn-customer-group').find('.dn-group-header').text();
        });

        $('#dnSelCustomer').text(count ? customerName : '-');
        $('#dnSelCount').text(count);
        $('#dnSelTotal').text(total.toLocaleString('id-ID'));
        $('#btnSubmitDn').prop('disabled', count === 0);

        // Kunci grup customer lain begitu ada 1 invoice terpilih —
        // satu Surat Jalan hanya boleh dari satu customer.
        var activeCustomers = $('.dn-checkbox:checked').map(function () { return $(this).data('customer'); }).get();
        var uniqueCustomers = activeCustomers.filter(function (v, i, a) { return a.indexOf(v) === i; });

        $('.dn-checkbox').each(function () {
            var isChecked = $(this).is(':checked');
            var sameCustomer = uniqueCustomers.length === 0 || $(this).data('customer') === uniqueCustomers[0];
            $(this).prop('disabled', !isChecked && !sameCustomer);
            $(this).closest('.dn-invoice-item').toggleClass('text-muted', !isChecked && !sameCustomer);
        });
    }

    $(document).on('change', '.dn-checkbox', recalc);
    recalc();

    $('#frmDeliveryNote').on('submit', function (e) {
        e.preventDefault();
        var form = this;
        var count = $('.dn-checkbox:checked').length;
        Swal.fire({
            title: 'Buat Surat Jalan?',
            text: count + ' invoice akan dibundel dan ditandai SENT.',
            icon: 'question', showCancelButton: true,
            confirmButtonColor: 'var(--success)', cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Buat', cancelButtonText: 'Batal'
        }).then(function (r) { if (r.isConfirmed) form.submit(); });
    });

});
</script>
@endpush
