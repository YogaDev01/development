@extends('layouts.app')

@section('title', 'Catat Penerimaan')

@section('content')

<div class="content">
    <div class="container-fluid">

        @if(session('error'))
        <div class="alert alert-danger rounded-0 shadow-sm">{{ session('error') }}</div>
        @endif

        @if($errors->any())
        <div class="alert alert-danger rounded-0 shadow-sm">
            <strong>Periksa kembali isian berikut:</strong>
            <ul class="mb-0 mt-1" style="font-size:12px;">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Catat Penerimaan</h1>
                <small class="text-muted">
                    Satu penerimaan dana atas beberapa invoice sekaligus — bentuknya sama dengan batch pembayaran di Treasury.
                </small>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="{{ route('cash-receipt.index') }}">Cash Receipt</a></li>
                <li class="breadcrumb-item active">Catat</li>
            </ol>
        </div>

        {{-- LANGKAH 1 — pilih customer. Invoice yang bisa dipilih dibatasi pada
             customer ini, jadi daftarnya tidak pernah bercampur antar-customer
             dalam satu bukti terima. --}}
        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-building mr-1"></i> 1. Pilih Customer</h3>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('cash-receipt.create') }}" class="form-row align-items-end">
                    <div class="form-group col-lg-5 col-md-8 mb-0">
                        <label class="font-weight-600">Customer <span class="text-danger">*</span></label>
                        <select name="customer_id" class="form-control form-control-sm" onchange="this.form.submit()">
                            <option value="">— Pilih Customer —</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" @selected((string) $customerId === (string) $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>
        </div>

        @if($customerId)
        <form action="{{ route('cash-receipt.store') }}" method="POST" enctype="multipart/form-data" id="formCr">
            @csrf
            <input type="hidden" name="customer_id" value="{{ $customerId }}">

            {{-- LANGKAH 2 — pilih invoice yang ditutup penerimaan ini. --}}
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header d-flex align-items-center flex-wrap" style="gap:8px;">
                    <h3 class="card-title"><i class="fas fa-file-invoice-dollar mr-1"></i> 2. Pilih Invoice ({{ $invoices->count() }})</h3>
                    <div class="ml-auto d-flex align-items-center" style="gap:6px;">
                        <input type="search" id="crSearch" class="form-control form-control-sm"
                               placeholder="Cari invoice / no. PO" style="width:220px;font-size:11.5px;">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="crSelectAll" style="font-size:11px;">
                            <i class="fas fa-check-double mr-1"></i>Pilih Semua
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="crClearAll" style="font-size:11px;">
                            <i class="fas fa-eraser mr-1"></i>Reset
                        </button>
                    </div>
                </div>

                <div class="card-body p-0">
                    @if($invoices->isEmpty())
                        <div class="p-3">
                            <x-empty title="Tidak ada invoice terbuka untuk customer ini"
                                     subtitle="Semua invoice-nya sudah lunas, atau belum sampai tahap penagihan."
                                     icon="fas fa-check-circle" compact />
                        </div>
                    @else
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0" id="tblCrInvoices" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:1%;">
                                        <input type="checkbox" id="crCheckAll" title="Pilih semua">
                                    </th>
                                    <th scope="col">Invoice</th>
                                    <th scope="col" class="text-nowrap">Jatuh Tempo</th>
                                    <th scope="col" class="text-center text-nowrap">Status</th>
                                    <th scope="col" class="text-right text-nowrap">Sisa Tagihan</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:16%;">Diterima</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:14%;">PPh</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoices as $inv)
                                @php $overdue = $inv->isOverdue(); @endphp
                                <tr class="cr-inv-row"
                                    data-search="{{ strtolower($inv->code . ' ' . ($inv->poNumber->po_number ?? '') . ' ' . ($inv->po->name ?? '')) }}">
                                    <td>
                                        <input type="checkbox" name="invoice_ids[]" value="{{ $inv->id }}"
                                               class="cr-check" data-balance="{{ $inv->computed_balance }}">
                                    </td>
                                    <td>
                                        <strong>{{ $inv->code }}</strong>
                                        <div class="text-muted" style="font-size:10px;">
                                            {{ $inv->poNumber->po_number ?? '-' }} &middot; {{ Str::limit($inv->po->name ?? '-', 40) }}
                                        </div>
                                    </td>
                                    <td class="text-nowrap">
                                        {{ ($inv->actual_due_date ?? $inv->due_date)?->format('d M Y') }}
                                        @if($overdue)
                                            <span class="badge badge-danger ml-1" style="font-size:9px;">OVERDUE</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-secondary px-2 py-1" style="font-size:9.5px;">{{ $inv->status }}</span>
                                    </td>
                                    <td class="text-right text-nowrap cr-balance" data-value="{{ $inv->computed_balance }}">
                                        <strong>{{ number_format($inv->computed_balance, 0, ',', '.') }}</strong>
                                    </td>
                                    <td>
                                        {{-- Nominal diisi otomatis dengan sisa tagihan saat
                                             invoice dicentang — pelunasan penuh adalah kasus
                                             yang paling sering, dan angkanya tetap bisa
                                             dikurangi untuk pembayaran sebagian. --}}
                                        <input type="text" name="amounts[{{ $inv->id }}]"
                                               class="form-control form-control-sm text-right cr-amount"
                                               placeholder="0" disabled>
                                    </td>
                                    <td>
                                        <input type="text" name="withholding[{{ $inv->id }}]"
                                               class="form-control form-control-sm text-right cr-wht"
                                               placeholder="0" disabled>
                                    </td>
                                </tr>
                                @endforeach
                                <tr id="crNoMatch" style="display:none;">
                                    <td colspan="7" class="text-center text-muted py-3">Tidak ada invoice yang cocok dengan pencarian.</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr style="background:var(--surface-2);font-weight:700;">
                                    <th colspan="5" class="text-right">Total Penerimaan</th>
                                    <th class="text-right text-nowrap" id="crTotalAmount">0</th>
                                    <th class="text-right text-nowrap" id="crTotalWht">0</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    @endif
                </div>
            </div>

            @if($invoices->isNotEmpty())
            {{-- LANGKAH 3 — informasi bukti terimanya. --}}
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-receipt mr-1"></i> 3. Bukti Penerimaan</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group col-lg-3 col-md-6">
                            <label class="font-weight-600">Tanggal Terima <span class="text-danger">*</span></label>
                            <input type="date" name="received_date" class="form-control form-control-sm"
                                   value="{{ old('received_date', now()->format('Y-m-d')) }}" required>
                        </div>

                        <div class="form-group col-lg-3 col-md-6">
                            <label class="font-weight-600">Rekening Penerima</label>
                            <select name="source_fund_id" class="form-control form-control-sm">
                                <option value="">— Pilih Rekening —</option>
                                @foreach($sourceFunds as $sf)
                                    <option value="{{ $sf->id }}" @selected((string) old('source_fund_id') === (string) $sf->id)>{{ $sf->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-lg-3 col-md-6">
                            <label class="font-weight-600">No. Referensi</label>
                            <input type="text" name="reference_number" class="form-control form-control-sm"
                                   value="{{ old('reference_number') }}" placeholder="No. bukti transfer">
                        </div>

                        <div class="form-group col-lg-3 col-md-6">
                            <label class="font-weight-600">Bukti Transfer</label>
                            <input type="file" name="proof_file" class="form-control-file" accept=".pdf,.jpg,.jpeg,.png">
                            <small class="form-text text-muted">PDF/JPG/PNG, maks 10 MB.</small>
                        </div>

                        <div class="form-group col-12 mb-0">
                            <label class="font-weight-600">Catatan</label>
                            <textarea name="notes" rows="2" class="form-control form-control-sm">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="card-footer d-flex align-items-center flex-wrap" style="gap:12px;">
                    <div style="font-size:12.5px;">
                        <strong id="crCount">0</strong> invoice dipilih &middot;
                        Total <strong id="crGrandTotal" class="text-success">Rp 0</strong>
                    </div>
                    <div class="ml-auto d-flex" style="gap:8px;">
                        <a href="{{ route('cash-receipt.index') }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-times mr-1"></i> Batal
                        </a>
                        <button type="submit" class="btn btn-success btn-sm" id="crSubmit" disabled>
                            <i class="fas fa-save mr-1"></i> Simpan Penerimaan
                        </button>
                    </div>
                </div>
            </div>
            @endif
        </form>
        @endif

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

    function angka($el) {
        return parseInt(($el.val() || '0').toString().replace(/\D/g, ''), 10) || 0;
    }

    function formatUang($el) {
        var digit = ($el.val() || '').toString().replace(/\D/g, '');
        $el.val(digit ? parseInt(digit, 10).toLocaleString('id-ID') : '');
    }

    /* Nominal & PPh hanya aktif untuk invoice yang dicentang. Membiarkannya
       selalu aktif membuat orang mengisi angka pada baris yang tidak ikut
       tersimpan — lalu bingung kenapa nominalnya hilang. */
    function setBaris($row, aktif) {
        var $amount = $('.cr-amount', $row);
        var $wht    = $('.cr-wht', $row);

        $amount.prop('disabled', !aktif);
        $wht.prop('disabled', !aktif);

        if (aktif && !angka($amount)) {
            // Usulan = sisa tagihan penuh; tinggal dikurangi kalau bayar sebagian.
            $amount.val(rp(parseInt($('.cr-check', $row).data('balance'), 10) || 0));
        }
        if (!aktif) {
            $amount.val('');
            $wht.val('');
        }
    }

    function hitungUlang() {
        var jumlah = 0, totalAmount = 0, totalWht = 0;

        $('.cr-inv-row').each(function () {
            if (!$('.cr-check', this).is(':checked')) return;
            jumlah++;
            totalAmount += angka($('.cr-amount', this));
            totalWht    += angka($('.cr-wht', this));
        });

        $('#crCount').text(jumlah);
        $('#crTotalAmount').text(rp(totalAmount));
        $('#crTotalWht').text(rp(totalWht));
        $('#crGrandTotal').text('Rp ' + rp(totalAmount + totalWht));
        $('#crSubmit').prop('disabled', jumlah === 0 || (totalAmount + totalWht) <= 0);

        var $tampil = $('.cr-inv-row:visible .cr-check');
        $('#crCheckAll').prop('checked', $tampil.length > 0 && $tampil.filter(':checked').length === $tampil.length);
    }

    $(document).on('change', '.cr-check', function () {
        setBaris($(this).closest('tr'), $(this).is(':checked'));
        hitungUlang();
    });

    $(document).on('input', '.cr-amount, .cr-wht', function () {
        formatUang($(this));

        /* Nominal + PPh tidak boleh melebihi sisa tagihan barisnya. Backend
           tetap memeriksa ulang di dalam transaksi terkunci — ini hanya supaya
           orang tahu sebelum menekan Simpan, bukan sesudah ditolak. */
        var $row    = $(this).closest('tr');
        var sisa    = parseInt($('.cr-balance', $row).data('value'), 10) || 0;
        var terisi  = angka($('.cr-amount', $row)) + angka($('.cr-wht', $row));

        $row.toggleClass('table-danger', terisi > sisa);
        hitungUlang();
    });

    $('#crCheckAll').on('change', function () {
        var pilih = $(this).is(':checked');
        $('.cr-inv-row:visible').each(function () {
            $('.cr-check', this).prop('checked', pilih);
            setBaris($(this), pilih);
        });
        hitungUlang();
    });

    $('#crSelectAll').on('click', function () {
        $('#crCheckAll').prop('checked', true).trigger('change');
    });

    $('#crClearAll').on('click', function () {
        $('#crCheckAll').prop('checked', false).trigger('change');
    });

    var cariTimer = null;
    $('#crSearch').on('input', function () {
        var q = $(this).val().toLowerCase().trim();
        clearTimeout(cariTimer);
        cariTimer = setTimeout(function () {
            var tampil = 0;
            $('.cr-inv-row').each(function () {
                var cocok = !q || ($(this).data('search') || '').toString().indexOf(q) !== -1;
                $(this).toggle(cocok);
                if (cocok) tampil++;
            });
            $('#crNoMatch').toggle(tampil === 0);
            hitungUlang();
        }, 150);
    });

    // Cegah kiriman kosong walau tombolnya sempat aktif.
    $('#formCr').on('submit', function (e) {
        if ($('.cr-check:checked').length === 0) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'Pilih minimal satu invoice', confirmButtonColor: '#92232b' });
        }
    });

    hitungUlang();
});
</script>
@endpush
