@extends('layouts.app')

@section('title', 'Edit Invoice')

@section('content')

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <div class="d-flex align-items-center">
                    <a href="{{ url('/invoice-cash-receipt/' . $invoice->id) }}" class="btn btn-secondary btn-sm mr-3">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="m-0 font-weight-bold">Edit Invoice</h1>
                        <small class="text-muted">{{ $invoice->code }}</small>
                    </div>
                </div>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right mb-0">
                    <li class="breadcrumb-item"><a href="#">Finance</a></li>
                    <li class="breadcrumb-item"><a href="{{ url('/invoice-cash-receipt') }}">Invoice & Cash Receipt</a></li>
                    <li class="breadcrumb-item active">Edit</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">

        <form action="{{ url('/invoice-cash-receipt/' . $invoice->id) }}" method="POST" enctype="multipart/form-data" id="frmInvoice">
            @csrf
            @method('PUT')

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

            <div class="row">

                <!-- KIRI: INFORMASI -->
                <div class="col-md-4">
                    <div class="card card-primary card-outline shadow-sm">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i> Informasi Invoice</h3>
                        </div>
                        <div class="card-body">

                            <div class="form-group">
                                <label class="font-weight-600">Purchase Order <span class="text-danger">*</span></label>
                                <select name="po_id" id="selPo" class="form-control form-control-sm" required>
                                    <option value="">— Pilih PO (Progress Done) —</option>
                                    @foreach($availablePOs as $po)
                                        <option value="{{ $po->id }}" {{ old('po_id', $invoice->po_id) == $po->id ? 'selected' : '' }}>
                                            [{{ $po->code ?? '-' }}] {{ $po->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div id="poInfoBox" class="alert alert-light border mb-3" style="display:none;font-size:12px;">
                                <div><strong>Customer:</strong> <span id="poInfoCustomer"></span></div>
                                <div><strong>Nilai Kontrak:</strong> <span id="poInfoValue"></span></div>
                                <div><strong>Sisa Belum Ditagih:</strong> <span id="poInfoRemaining"></span></div>
                            </div>

                            <div class="form-group">
                                <label class="font-weight-600">Payment Term <span class="text-danger">*</span></label>
                                <select name="payment_term_type" id="selPaymentTerm" class="form-control form-control-sm" required>
                                    <option value="">— Pilih —</option>
                                    @foreach(\App\Models\Invoice::PAYMENT_TERM_TYPES as $t)
                                        <option value="{{ $t }}" {{ old('payment_term_type', $invoice->payment_term_type) == $t ? 'selected' : '' }}>{{ $t }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group" id="terminNumberBox" style="display:none;">
                                <label class="font-weight-600">Termin ke-</label>
                                <input type="number" name="termin_number" id="inpTerminNumber"
                                       class="form-control form-control-sm" min="1"
                                       value="{{ old('termin_number', $invoice->termin_number ?? 1) }}">
                            </div>

                            <div class="form-group">
                                <label class="font-weight-600">Tanggal Invoice <span class="text-danger">*</span></label>
                                <input type="date" name="invoice_date" class="form-control form-control-sm"
                                       value="{{ old('invoice_date', $invoice->invoice_date->format('Y-m-d')) }}" required>
                            </div>

                            <div class="form-group">
                                <label class="font-weight-600">Jatuh Tempo <span class="text-danger">*</span></label>
                                <input type="date" name="due_date" class="form-control form-control-sm"
                                       value="{{ old('due_date', $invoice->due_date->format('Y-m-d')) }}" required>
                            </div>

                            <div class="form-group">
                                <label class="font-weight-600">Tax Rate (%)</label>
                                <input type="number" step="0.01" name="tax_rate" id="inpTaxRate"
                                       class="form-control form-control-sm" value="{{ old('tax_rate', $invoice->tax_rate) }}">
                                <small class="text-muted">Isi 0 untuk customer non-PKP.</small>
                            </div>

                            <div class="form-group mb-0">
                                <label class="font-weight-600">Remark</label>
                                <textarea name="remark" class="form-control form-control-sm" rows="2"
                                          placeholder="Catatan tambahan...">{{ old('remark', $invoice->remark) }}</textarea>
                            </div>

                        </div>
                    </div>

                    <!-- KALKULASI VISUAL -->
                    <div class="card shadow-sm">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between align-items-baseline mb-1">
                                <span class="text-muted" style="font-size:12px;">Subtotal</span>
                                <strong id="calcSubtotal" style="font-size:13px;">Rp 0</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-baseline mb-1">
                                <span class="text-muted" style="font-size:12px;">Total Deduction</span>
                                <strong id="calcDeduction" class="text-danger" style="font-size:13px;">Rp 0</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-baseline mb-1">
                                <span class="text-muted" style="font-size:12px;">Tax (<span id="calcTaxRateLabel">11</span>%)</span>
                                <strong id="calcTax" style="font-size:13px;">Rp 0</strong>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between align-items-baseline">
                                <span class="font-weight-600" style="font-size:13px;">Captured Total</span>
                                <strong id="calcGrandTotal" class="text-success" style="font-size:16px;">Rp 0</strong>
                            </div>
                            <div id="calcAdditionalWarning" class="alert alert-warning mt-2 mb-0 py-2" style="display:none;font-size:11px;">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                <strong>ADDITIONAL</strong> — melebihi sisa kontrak PO sebesar <span id="calcAdditionalAmount"></span>.
                                Wajib jelaskan alasan di Remark.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KANAN: DETAIL & LAMPIRAN -->
                <div class="col-md-8">

                    <!-- RINCIAN -->
                    <div class="card card-primary card-outline shadow-sm">
                        <div class="card-header d-flex align-items-center">
                            <h3 class="card-title"><i class="fas fa-list-ul mr-1"></i> Rincian Invoice</h3>
                            <button type="button" class="btn btn-sm btn-primary ml-auto" id="btnAddDetail">
                                <i class="fas fa-plus mr-1"></i> Tambah Baris
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th style="width:4%;" class="text-center">No</th>
                                            <th style="width:30%;">Deskripsi</th>
                                            <th style="width:16%;" class="text-right">Harga Satuan</th>
                                            <th style="width:8%;" class="text-center">Qty</th>
                                            <th style="width:16%;" class="text-right">Deduction</th>
                                            <th style="width:16%;" class="text-right">Total</th>
                                            <th style="width:5%;" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="detailBody">
                                        @foreach($invoice->details as $i => $d)
                                        <tr class="detail-row">
                                            <td class="text-center align-middle row-no">{{ $i + 1 }}</td>
                                            <td><input type="text" name="details[{{ $i }}][description]" class="form-control form-control-sm" value="{{ $d->description }}" required></td>
                                            <td><input type="text" name="details[{{ $i }}][unit_price]" class="form-control form-control-sm text-right inp-price" value="{{ number_format($d->unit_price, 0, ',', '.') }}"></td>
                                            <td><input type="number" name="details[{{ $i }}][qty]" class="form-control form-control-sm text-center inp-qty" value="{{ $d->qty }}" min="1"></td>
                                            <td><input type="text" name="details[{{ $i }}][deduction]" class="form-control form-control-sm text-right inp-deduction" value="{{ number_format($d->deduction, 0, ',', '.') }}"></td>
                                            <td><input type="text" class="form-control form-control-sm text-right inp-total" value="{{ number_format($d->total, 0, ',', '.') }}" readonly style="background:#f8f9fa;"></td>
                                            <td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-detail"><i class="fas fa-trash"></i></button></td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer">
                            <small class="text-muted">
                                <i class="fas fa-info-circle mr-1"></i>
                                Semua baris lama akan diganti saat disimpan. Total = (Harga Satuan × Qty) − Deduction.
                            </small>
                        </div>
                    </div>

                    <!-- LAMPIRAN PO (READ-ONLY) -->
                    <div class="card card-info card-outline shadow-sm">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-paperclip mr-1"></i> Lampiran Purchase Order</h3>
                        </div>
                        <div class="card-body p-0">
                            <div id="poAttachmentList" class="p-3 text-muted" style="font-size:12px;">
                                —
                            </div>
                        </div>
                    </div>

                    <!-- LAMPIRAN EXISTING -->
                    @if($invoice->attachments->count())
                    <div class="card card-secondary card-outline shadow-sm">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-paperclip mr-1"></i> Lampiran Existing</h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th style="width:5%;" class="text-center">No</th>
                                            <th>Judul</th>
                                            <th style="width:15%;" class="text-center">Lihat</th>
                                            <th style="width:10%;" class="text-center">Hapus</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($invoice->attachments as $i => $att)
                                        <tr>
                                            <td class="text-center">{{ $i + 1 }}</td>
                                            <td>{{ $att->title }}</td>
                                            <td class="text-center">
                                                <a href="{{ asset('storage/' . $att->file_path) }}" target="_blank" class="btn btn-info btn-sm">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                            <td class="text-center">
                                                <div class="form-check d-flex justify-content-center">
                                                    <input type="checkbox" name="delete_attachments[]" value="{{ $att->id }}" class="form-check-input" style="position:static;">
                                                </div>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer">
                            <small class="text-muted">Centang untuk menghapus lampiran saat disimpan.</small>
                        </div>
                    </div>
                    @endif

                    <!-- LAMPIRAN TAMBAHAN BARU -->
                    <div class="card card-primary card-outline shadow-sm">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-paperclip mr-1"></i> Tambah Dokumen Pendukung</h3>
                        </div>
                        <div class="card-body">
                            <div id="dropZoneInv" class="att-dropzone mb-3">
                                <i class="fas fa-cloud-upload-alt fa-2x mb-2 d-block"></i>
                                <p class="mb-0">
                                    Drag &amp; drop file ke sini, atau
                                    <span class="text-primary" style="text-decoration:underline;cursor:pointer;" id="dropZoneBrowseInv">klik untuk pilih</span>
                                </p>
                                <small class="text-muted">Bisa pilih/drop beberapa file sekaligus</small>
                                <input type="file" id="dropZoneInputInv" multiple style="display:none;"
                                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th style="width:5%;" class="text-center">No</th>
                                            <th style="width:30%;">Judul</th>
                                            <th style="width:55%;">File</th>
                                            <th style="width:10%;" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="attBody"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer">
                            <small class="text-muted">Format: pdf, jpg, png, doc(x), xls(x). Maks 10 MB per file.</small>
                        </div>
                    </div>

                </div>
            </div>

            <!-- AKSI -->
            <div class="card shadow-sm">
                <div class="card-body d-flex">
                    <a href="{{ url('/invoice-cash-receipt/' . $invoice->id) }}" class="btn btn-secondary mr-2">
                        <i class="fas fa-arrow-left mr-1"></i> Kembali
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Simpan Perubahan
                    </button>
                </div>
            </div>

        </form>

    </div>
</div>

<textarea id="poPayloadHolder" style="display:none;">{{ json_encode($poPayload) }}</textarea>
<input type="hidden" id="detailCountHolder" value="{{ $invoice->details->count() }}">

@endsection

@push('styles')
<style>
.font-weight-600 { font-weight:600; }
.table td, .table th { vertical-align:middle !important; }
.att-dropzone {
    border: 2px dashed #ced4da; border-radius: 8px; padding: 24px 20px;
    text-align: center; color: #6c757d; cursor: pointer; transition: all .2s ease; background: #fafbfc;
}
.att-dropzone:hover { border-color: #F5A623; background: #FFFBF3; }
.att-dropzone.dragover { border-color: #F5A623; background: #FFF3DC; color: #C97E0A; }
</style>
@endpush

@push('scripts')
<script>
$(function () {

    var poPayload = JSON.parse(document.getElementById('poPayloadHolder').value || '{}');

    function fmt(v) {
        v = (v || '').toString().replace(/\D/g, '');
        return v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    function rp(v) { return 'Rp ' + Math.round(v).toLocaleString('id-ID'); }

    // ── PO INFO ──────────────────────────────────────────────────────
    $('#selPo').on('change', function () {
        var po = poPayload[this.value];
        if (!po) {
            $('#poInfoBox').hide();
            $('#poAttachmentList').html('<div class="p-3 text-muted" style="font-size:12px;">Pilih PO terlebih dahulu untuk menampilkan lampiran terkait.</div>');
            recalc();
            return;
        }
        $('#poInfoCustomer').text(po.customer);
        $('#poInfoValue').text(po.value_formatted);
        $('#poInfoRemaining').text(po.remaining_formatted);
        $('#poInfoBox').show();

        if (po.attachments && po.attachments.length) {
            var html = '<table class="table table-sm mb-0"><tbody>';
            po.attachments.forEach(function (a) {
                html += '<tr><td style="font-size:12px;">' + a.title + '</td>' +
                    '<td class="text-right" style="width:1%;"><a href="/storage/' + a.path + '" target="_blank" class="btn btn-info btn-xs"><i class="fas fa-eye"></i></a></td></tr>';
            });
            html += '</tbody></table>';
            $('#poAttachmentList').html(html);
        } else {
            $('#poAttachmentList').html('<div class="p-3 text-muted" style="font-size:12px;">PO ini belum punya lampiran.</div>');
        }

        recalc();
    });
    $('#selPo').trigger('change'); // init: tampilkan info PO yang sudah terpilih

    // ── PAYMENT TERM ─────────────────────────────────────────────────
    $('#selPaymentTerm').on('change', function () {
        $('#terminNumberBox').toggle(this.value === 'Termin');
    });
    $('#selPaymentTerm').trigger('change');

    // ── DETAIL ROWS ──────────────────────────────────────────────────
    var detailIdx = parseInt(document.getElementById('detailCountHolder').value, 10) || 0;

    function makeDetailRow(i) {
        return '<tr class="detail-row">' +
            '<td class="text-center align-middle row-no"></td>' +
            '<td><input type="text" name="details[' + i + '][description]" class="form-control form-control-sm" placeholder="Deskripsi item..." required></td>' +
            '<td><input type="text" name="details[' + i + '][unit_price]" class="form-control form-control-sm text-right inp-price" placeholder="0"></td>' +
            '<td><input type="number" name="details[' + i + '][qty]" class="form-control form-control-sm text-center inp-qty" value="1" min="1"></td>' +
            '<td><input type="text" name="details[' + i + '][deduction]" class="form-control form-control-sm text-right inp-deduction" placeholder="0"></td>' +
            '<td><input type="text" class="form-control form-control-sm text-right inp-total" value="0" readonly style="background:#f8f9fa;"></td>' +
            '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-detail"><i class="fas fa-trash"></i></button></td>' +
        '</tr>';
    }

    $('#btnAddDetail').on('click', function () {
        $('#detailBody').append(makeDetailRow(detailIdx++));
        renumber();
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('.btn-remove-detail')) {
            if ($('.detail-row').length > 1) {
                e.target.closest('tr').remove();
                renumber();
                recalc();
            } else {
                Swal.fire({ icon: 'warning', title: 'Minimal 1 baris', confirmButtonColor: '#F5A623' });
            }
        }
    });

    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('inp-price') || e.target.classList.contains('inp-deduction')) {
            e.target.value = fmt(e.target.value);
        }
        if (e.target.closest('.detail-row') || e.target.id === 'inpTaxRate') recalc();
    });

    function renumber() {
        $('#detailBody .row-no').each(function (i) { $(this).text(i + 1); });
    }

    // ── KALKULASI ────────────────────────────────────────────────────
    function recalc() {
        var subtotal = 0, deduction = 0;
        $('.detail-row').each(function () {
            var price = parseInt(($('.inp-price', this).val() || '0').replace(/\./g, '')) || 0;
            var qty   = parseInt($('.inp-qty', this).val()) || 1;
            var ded   = parseInt(($('.inp-deduction', this).val() || '0').replace(/\./g, '')) || 0;
            var total = Math.max(0, (price * qty) - ded);
            $('.inp-total', this).val(total.toLocaleString('id-ID'));
            subtotal  += (price * qty);
            deduction += ded;
        });

        var netSubtotal = Math.max(0, subtotal - deduction);
        var taxRate = parseFloat($('#inpTaxRate').val()) || 0;
        var tax = Math.round(netSubtotal * (taxRate / 100));
        var grandTotal = netSubtotal + tax;

        $('#calcSubtotal').text(rp(subtotal));
        $('#calcDeduction').text(rp(deduction));
        $('#calcTaxRateLabel').text(taxRate);
        $('#calcTax').text(rp(tax));
        $('#calcGrandTotal').text(rp(grandTotal));

        var po = poPayload[$('#selPo').val()];
        if (po && grandTotal > po.remaining) {
            $('#calcAdditionalAmount').text(rp(grandTotal - po.remaining));
            $('#calcAdditionalWarning').show();
        } else {
            $('#calcAdditionalWarning').hide();
        }
    }

    // ── LAMPIRAN TAMBAHAN (drag & drop) ─────────────────────────────
    var attIdx = 0;

    function addAttRow(file) {
        var i = attIdx++;
        var html =
            '<tr>' +
                '<td class="text-center align-middle row-no-att"></td>' +
                '<td><input type="text" name="attachments[' + i + '][title]" class="form-control form-control-sm att-title" style="text-transform:uppercase;" placeholder="Judul lampiran..."></td>' +
                '<td><input type="file" name="attachments[' + i + '][file]" class="form-control-file"></td>' +
                '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-att"><i class="fas fa-trash"></i></button></td>' +
            '</tr>';
        document.getElementById('attBody').insertAdjacentHTML('beforeend', html);

        if (file) {
            var rows = document.querySelectorAll('#attBody tr');
            var lastRow = rows[rows.length - 1];
            var input = lastRow.querySelector('input[type="file"]');
            var titleInput = lastRow.querySelector('.att-title');
            try {
                var dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
            } catch (err) {}
            if (titleInput && !titleInput.value) {
                titleInput.value = file.name.replace(/\.[^/.]+$/, '');
            }
        }
        renumberAtt();
    }

    function renumberAtt() {
        $('#attBody .row-no-att').each(function (i) { $(this).text(i + 1); });
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('.btn-remove-att')) {
            e.target.closest('tr').remove();
            renumberAtt();
        }
    });

    (function () {
        var zone   = document.getElementById('dropZoneInv');
        var input  = document.getElementById('dropZoneInputInv');
        var browse = document.getElementById('dropZoneBrowseInv');

        zone.addEventListener('click', function () { input.click(); });
        browse.addEventListener('click', function (e) { e.stopPropagation(); input.click(); });

        input.addEventListener('change', function () {
            Array.from(this.files).forEach(function (f) { addAttRow(f); });
            this.value = '';
        });

        ['dragenter', 'dragover'].forEach(function (evt) {
            zone.addEventListener(evt, function (e) { e.preventDefault(); e.stopPropagation(); zone.classList.add('dragover'); });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            zone.addEventListener(evt, function (e) { e.preventDefault(); e.stopPropagation(); zone.classList.remove('dragover'); });
        });
        zone.addEventListener('drop', function (e) {
            var files = e.dataTransfer ? e.dataTransfer.files : null;
            if (files && files.length) Array.from(files).forEach(function (f) { addAttRow(f); });
        });
    })();

    recalc();

});
</script>
@endpush