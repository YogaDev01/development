@extends('layouts.app')

@section('title', 'Tambah Invoice')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ url('/invoice-cash-receipt') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Tambah Invoice</h1>
                    <small class="text-muted">Form pengajuan invoice baru</small>
                </div>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item"><a href="{{ url('/invoice-cash-receipt') }}">Invoice & Cash Receipt</a></li>
                <li class="breadcrumb-item active">Tambah</li>
            </ol>
        </div>

        <form action="{{ url('/invoice-cash-receipt') }}" method="POST" enctype="multipart/form-data" id="frmInvoice">
            @csrf
            @include('partials.period-lock-field', ['class' => 'mb-3'])

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

            {{--
                Tata letak menurun (bukan dua kolom kiri–kanan). Alasannya:
                pengisian invoice itu satu urutan kerja — pilih PO, isi
                identitas, susun rincian, lampirkan berkas — dan kolom kanan
                yang panjang membuat urutan itu terbaca melompat-lompat.
                Menurun juga memberi lebar penuh untuk tabel rincian, yang
                paling menderita saat dijejalkan ke 8 dari 12 kolom.
            --}}

            <!-- 1. INFORMASI -->
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i> Informasi Invoice</h3>
                </div>
                <div class="card-body form-clean">

                    <div class="form-group">
                        <label class="font-weight-600">No. PO / BAST <span class="text-danger">*</span></label>
                        <button type="button" id="btnPoPicker" class="btn btn-outline-secondary btn-block form-control-sm d-flex justify-content-between align-items-center">
                            <span id="btnPoPickerLabel" class="text-truncate">— Pilih BAST yang akan ditagih —</span>
                            <i class="fas fa-caret-down ml-2 text-muted"></i>
                        </button>
                        <select name="project_po_bast_id" id="selPo" style="display:none;">
                            <option value="">— Pilih BAST yang akan ditagih —</option>
                            @foreach($availableBasts as $bast)
                                @php
                                    $pn = $bast->poNumber;
                                    $poLabel = ($pn->project->customer->initial ?? '-') . ' - [' . $pn->po_number . '] ' . $bast->no_bast . ' - ' . number_format((int) $bast->nilai_bast, 0, ',', '.');
                                @endphp
                                <option value="{{ $bast->id }}" data-group="{{ $pn->po_number }}" data-label="{{ $poLabel }}" {{ old('project_po_bast_id') == $bast->id ? 'selected' : '' }}>
                                    {{ $poLabel }}
                                </option>
                            @endforeach
                        </select>
                        @if($availableBasts->isEmpty())
                            <small class="text-danger d-block mt-1">Tidak ada BAST yang belum ditagih saat ini.</small>
                        @endif
                    </div>

                    {{-- Ringkasan PO terpilih: daftar label–nilai berjajar, bukan
                         tumpukan baris "label: nilai" yang harus dibaca satu per satu. --}}
                    <div id="poInfoBox" class="inv-po-summary" style="display:none;">
                        <div class="inv-po-item"><span class="inv-po-label">Kode PO</span><span class="inv-po-value" id="poInfoCode"></span></div>
                        <div class="inv-po-item"><span class="inv-po-label">No. PO</span><span class="inv-po-value" id="poInfoNumber"></span></div>
                        <div class="inv-po-item inv-po-wide"><span class="inv-po-label">Scope of Work</span><span class="inv-po-value" id="poInfoProject"></span></div>
                        <div class="inv-po-item"><span class="inv-po-label">Customer</span><span class="inv-po-value" id="poInfoCustomer"></span></div>
                        <div class="inv-po-item"><span class="inv-po-label">No. BAST</span><span class="inv-po-value" id="poInfoBast"></span></div>
                        <div class="inv-po-item"><span class="inv-po-label">Term of Payment</span><span class="inv-po-value" id="poInfoTop"></span></div>
                        <div class="inv-po-item"><span class="inv-po-label">Nominal No. PO</span><span class="inv-po-value" id="poInfoValue"></span></div>
                        <div class="inv-po-item"><span class="inv-po-label">Nilai BAST</span><span class="inv-po-value" id="poInfoRemaining"></span></div>
                    </div>

                    {{-- Kolom isian berjajar: identitas penagihan, lalu termin &
                         tanggal, lalu angka penghitung. --}}
                    <div class="form-row">

                        <div class="form-group col-lg-4 col-md-6">
                            <label class="font-weight-600">No. Faktur Pajak <span class="text-danger">*</span></label>
                            <input type="text" name="faktur_number" class="form-control form-control-sm"
                                   value="{{ old('faktur_number') }}" placeholder="mis. 010.000-26.00000001" required>
                        </div>

                        <div class="form-group col-lg-4 col-md-6">
                            <label class="font-weight-600">Source Fund <span class="text-danger">*</span></label>
                            <select name="source_fund_id" class="form-control form-control-sm" required>
                                <option value="">— Pilih —</option>
                                @foreach($sourceFunds as $sf)
                                    <option value="{{ $sf->id }}" {{ old('source_fund_id') == $sf->id ? 'selected' : '' }}>{{ $sf->label }}</option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">Rekening tujuan pembayaran yang ditampilkan di invoice cetak.</small>
                        </div>

                        <div class="form-group col-lg-2 col-md-6">
                            <label class="font-weight-600">Payment Term <span class="text-danger">*</span></label>
                            <select name="payment_term_type" id="selPaymentTerm" class="form-control form-control-sm" required>
                                <option value="">— Pilih —</option>
                                @foreach(\App\Models\Invoice::PAYMENT_TERM_TYPES as $t)
                                    <option value="{{ $t }}" {{ old('payment_term_type') == $t ? 'selected' : '' }}>{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-lg-2 col-md-6" id="terminNumberBox" style="display:none;">
                            <label class="font-weight-600">Termin ke-</label>
                            <input type="number" name="termin_number" id="inpTerminNumber"
                                   class="form-control form-control-sm" min="1"
                                   value="{{ old('termin_number', 1) }}">
                        </div>

                        <div class="form-group col-lg-3 col-md-6">
                            <label class="font-weight-600">Tanggal Invoice <span class="text-danger">*</span></label>
                            <input type="date" name="invoice_date" class="form-control form-control-sm"
                                   value="{{ old('invoice_date', now()->format('Y-m-d')) }}" required>
                        </div>

                        <div class="form-group col-lg-3 col-md-6">
                            <label class="font-weight-600">Jatuh Tempo (Print) <span class="text-danger">*</span></label>
                            <input type="date" name="due_date" class="form-control form-control-sm"
                                   value="{{ old('due_date', now()->addDays(30)->format('Y-m-d')) }}" required>
                        </div>

                        <div class="form-group col-lg-2 col-md-6">
                            <label class="font-weight-600">Tax Rate (%)</label>
                            <input type="number" step="0.01" name="tax_rate" id="inpTaxRate"
                                   class="form-control form-control-sm" value="{{ old('tax_rate', 11) }}">
                            <small class="form-text text-muted">Isi 0 untuk customer non-PKP.</small>
                        </div>

                        {{-- MN-07 — Withholding Tax (PPh) yang dipotong di muka.
                             Beda dari "Settle PPh" di halaman detail invoice, yang
                             dipakai saat potongan baru ketahuan waktu uang masuk. --}}
                        <div class="form-group col-lg-3 col-md-6">
                            <label class="font-weight-600">Withholding Tax / PPh</label>
                            <input type="text" name="withholding_tax" id="inpWht"
                                   class="form-control form-control-sm text-right"
                                   value="{{ old('withholding_tax') }}" placeholder="0" autocomplete="off">
                            <small class="form-text text-muted">Kosongkan kalau tidak ada potongan PPh di muka.</small>
                        </div>

                        <div class="form-group col-lg-3 col-md-6">
                            <label class="font-weight-600">Persentase Penagihan (%)</label>
                            <input type="number" step="0.01" min="0.01" max="100" name="billing_percentage" id="inpBillingPct"
                                   class="form-control form-control-sm" value="{{ old('billing_percentage') }}" placeholder="Kosongkan jika tagih penuh (100%)">
                            <small class="form-text text-muted">Isi kalau invoice ini hanya menagih sebagian dari nilai baris rincian (mis. Termin 50%).</small>
                        </div>

                        <div class="form-group col-12">
                            <label class="font-weight-600">Remark</label>
                            <textarea name="remark" class="form-control form-control-sm" rows="2"
                                      placeholder="Catatan tambahan...">{{ old('remark') }}</textarea>
                        </div>

                    </div>

                </div>
            </div>

            <!-- 2. RINCIAN -->
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header d-flex align-items-center flex-wrap" style="gap:6px;">
                    <h3 class="card-title"><i class="fas fa-list-ul mr-1"></i> Rincian Invoice</h3>
                    <div class="ml-auto d-flex flex-wrap" style="gap:6px;">
                        <a href="#" id="btnDownloadTemplate" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-file-excel mr-1"></i> Download Template
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnImportExcel">
                            <i class="fas fa-file-import mr-1"></i> Import Excel
                        </button>
                        <input type="file" id="importFileInput" accept=".xlsx,.xls" style="display:none;">
                        <button type="button" class="btn btn-sm btn-primary" id="btnAddDetail">
                            <i class="fas fa-plus mr-1"></i> Tambah Baris
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 inv-detail-table">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:4%;" class="text-center">No</th>
                                    <th scope="col" style="width:30%;">Deskripsi</th>
                                    <th scope="col" style="width:14%;" class="text-right">Harga Satuan</th>
                                    <th scope="col" style="width:7%;" class="text-center">Qty</th>
                                    <th scope="col" style="width:11%;">Satuan</th>
                                    <th scope="col" style="width:14%;" class="text-right">Deduction</th>
                                    <th scope="col" style="width:15%;" class="text-right">Total</th>
                                    <th scope="col" style="width:5%;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="detailBody"></tbody>
                        </table>
                    </div>
                </div>

                {{-- Penghitungan menempel pada tabel yang menghasilkannya. Di tata
                     letak lama ia terdampar di dasar kolom kiri, jauh dari angka
                     yang menyusunnya. --}}
                <div class="card-footer inv-sum-footer">
                    <div class="inv-sum-note">
                        <i class="fas fa-info-circle mr-1"></i>
                        Total = (Harga Satuan × Qty) − Deduction. Deduction diisi kalau penagihan tidak sesuai nilai awal.
                    </div>
                    <div class="inv-sum">
                        <div class="inv-sum-row">
                            <span class="calc-caption">Subtotal</span>
                            <strong id="calcSubtotal">0</strong>
                        </div>
                        <div class="inv-sum-row" id="calcBilledRow" style="display:none;">
                            <span class="calc-caption" id="calcBilledLabel">Ditagih</span>
                            <strong id="calcBilled">0</strong>
                        </div>
                        <div class="inv-sum-row">
                            <span class="calc-caption">Total Deduction</span>
                            <strong id="calcDeduction" class="text-danger">0</strong>
                        </div>
                        <div class="inv-sum-row">
                            <span class="calc-caption">Tax</span>
                            <strong id="calcTax">0</strong>
                        </div>
                        {{-- MN-07 — PPh yang dipotong customer di muka. Baris ini
                             selalu tampil supaya rumus Captured Total terbaca utuh
                             di layar, walau nilainya 0. --}}
                        <div class="inv-sum-row">
                            <span class="calc-caption">Withholding Tax (PPh)</span>
                            <strong id="calcWht" class="text-danger">0</strong>
                        </div>
                        <div class="inv-sum-row inv-sum-total">
                            <span class="calc-caption">Captured Total</span>
                            <strong id="calcGrandTotal" class="text-success">0</strong>
                        </div>
                    </div>
                    <div id="calcBastCheck" class="alert mt-2 mb-0 py-2 inv-sum-warning" style="display:none;">
                        <i id="calcBastCheckIcon" class="fas mr-1"></i><span id="calcBastCheckText"></span>
                    </div>
                </div>
            </div>

            <!-- 3. LAMPIRAN PO / NO. PO / BAST (READ-ONLY) -->
            <div class="card card-info card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-paperclip mr-1"></i> Lampiran PO, No. PO &amp; BAST</h3>
                </div>
                <div class="card-body">
                    {{-- Tiga sumber berjajar: dengan lebar penuh, menumpuknya ke
                         bawah hanya memanjangkan halaman tanpa menambah kejelasan. --}}
                    <div class="row">
                        <div class="col-md-4 att-group-col">
                            <div class="att-group-title">Purchase Order</div>
                            <div id="poAttachmentList" class="att-group-body text-muted">
                                Pilih PO terlebih dahulu untuk menampilkan lampiran terkait.
                            </div>
                        </div>
                        <div class="col-md-4 att-group-col">
                            <div class="att-group-title">No. PO</div>
                            <div id="noPoAttachmentList" class="att-group-body text-muted">
                                Pilih PO terlebih dahulu untuk menampilkan lampiran terkait.
                            </div>
                        </div>
                        <div class="col-md-4 att-group-col">
                            <div class="att-group-title">BAST</div>
                            <div id="bastAttachmentList" class="att-group-body text-muted">
                                Pilih PO terlebih dahulu untuk menampilkan lampiran terkait.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. LAMPIRAN TAMBAHAN -->
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-paperclip mr-1"></i> Dokumen Pendukung Tambahan</h3>
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
                                    <th scope="col" style="width:5%;" class="text-center">No</th>
                                    <th scope="col" style="width:30%;">Judul</th>
                                    <th scope="col" style="width:55%;">File</th>
                                    <th scope="col" style="width:10%;" class="text-center">Aksi</th>
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

            {{-- Bilah aksi menempel di dasar layar: dengan tata letak menurun,
                 halaman jadi panjang dan tombol simpan tidak boleh ikut hanyut
                 ke bawah lipatan. Aksi utama di kanan, jalan keluar di kiri. --}}
            <!-- AKSI -->
            <div class="inv-actionbar">
                <a href="{{ url('/invoice-cash-receipt') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali
                </a>
                <button type="submit" class="btn btn-primary ml-auto">
                    <i class="fas fa-save mr-1"></i> Simpan sebagai Draft
                </button>
            </div>

        </form>

    </div>
</div>

<textarea id="poPayloadHolder" style="display:none;">{{ json_encode($poPayload) }}</textarea>
<textarea id="unitOptionsHolder" style="display:none;">{{ json_encode(\App\Models\BudgetPlanDetail::UNITS) }}</textarea>

@endsection

@push('styles')
<style>
.font-weight-600 { font-weight:600; }
.table td, .table th { vertical-align:middle !important; }

.calc-caption {
    font-size:11px; font-weight:700; letter-spacing:.4px;
    text-transform:uppercase; color:var(--text-faint);
}

.att-group-title {
    font-size:11px; font-weight:700; letter-spacing:.4px;
    text-transform:uppercase; color:var(--text-faint); margin-bottom:8px;
}

.att-dropzone {
    border: 2px dashed var(--border); border-radius: var(--radius-md); padding: 24px 20px;
    text-align: center; color: var(--text-faint); cursor: pointer; transition: all .2s ease; background: var(--surface-2);
}
.att-dropzone:hover { border-color: var(--accent); background: var(--warning-bg); }
.att-dropzone.dragover { border-color: var(--accent); background: var(--warning-bg); color: var(--accent-dark); }

/* No. PO picker — dropdown kustom (bukan Tom Select) supaya konsisten
   dengan pola filter dropdown lain di app (tombol + panel cari). */
#btnPoPicker { font-weight:400; font-size:13px; color:var(--text); background:var(--surface); }
#btnPoPicker .text-truncate { max-width: calc(100% - 20px); }
.po-picker-dropdown {
    position:absolute; z-index:3000; background:var(--surface); border:1px solid var(--border);
    border-radius:var(--radius-sm); box-shadow:0 6px 18px rgba(0,0,0,.12); padding:8px;
    display:flex; flex-direction:column;
    width:fit-content; min-width:320px; max-width:min(1600px, 92vw);
}
.po-picker-dropdown .po-picker-list { min-width:100%; }
.po-picker-item {
    padding:8px 10px; border-radius:var(--radius-sm); cursor:pointer; font-size:12.5px; line-height:1.4;
}
.po-picker-item:hover { background:var(--info-bg); }
</style>
@endpush

@push('scripts')
<script>
$(function () {

    var poPayload = JSON.parse(document.getElementById('poPayloadHolder').value || '{}');
    var unitOptions = JSON.parse(document.getElementById('unitOptionsHolder').value || '[]');

    // ── PO PICKER (dropdown kustom, searchable) ─────────────────────────
    (function () {
        var $btn = $('#btnPoPicker');
        var $select = $('#selPo');

        function syncButtonLabel() {
            var $opt = $select.find('option:selected');
            $('#btnPoPickerLabel').text($opt.val() ? $opt.data('label') : '— Pilih BAST yang akan ditagih —');
        }
        syncButtonLabel();

        // Satu tombol = buka DAN tutup: kalau panelnya sedang terbuka, klik
        // berikutnya pada tombol yang sama menutupnya, bukan menggambar ulang.
        function closePoPicker() {
            $('.po-picker-dropdown').remove();
            $(document).off('click.po-picker-outside');
        }

        $btn.on('click', function (e) {
            e.stopPropagation();
            if ($('.po-picker-dropdown').length) { closePoPicker(); return; }
            closePoPicker();

            var $dd = $('<div class="po-picker-dropdown"></div>').css({ maxHeight: '380px' });
            var $search = $('<input type="text" class="form-control form-control-sm mb-2" placeholder="Cari No. PO / BAST / customer / scope of work...">');
            var $list = $('<div class="po-picker-list" style="overflow-y:auto;max-height:300px;"></div>');

            var lastGroup = null;
            $select.find('option').each(function () {
                var val = $(this).val();
                if (!val) return;
                var grp = $(this).data('group');
                if (grp && grp !== lastGroup) {
                    $list.append($('<div class="po-picker-group"></div>').text('No. PO ' + grp)
                        .css({ fontSize: '11px', fontWeight: 700, padding: '6px 8px 2px', color: 'var(--text-faint)' }));
                    lastGroup = grp;
                }
                var $item = $('<div class="po-picker-item"></div>')
                    .text($(this).data('label'))
                    .attr('data-value', val);
                $list.append($item);
            });

            if ($list.children().length === 0) {
                $list.append('<div class="text-muted p-2" style="font-size:12px;">Tidak ada BAST yang belum ditagih.</div>');
            }

            $dd.append($search, $list);
            $('body').append($dd);

            TesysPlaceDropdown($dd, $btn);

            $search.on('input', function () {
                var q = $(this).val().toLowerCase();
                $list.children('.po-picker-item').each(function () {
                    $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1);
                });
            }).focus();

            $list.on('click', '.po-picker-item', function () {
                $select.val($(this).attr('data-value')).trigger('change');
                syncButtonLabel();
                $dd.remove();
            });

            $(document).one('click.po-picker-outside', function handler(ev) {
                if ($(ev.target).closest('.po-picker-dropdown, #btnPoPicker').length === 0) {
                    closePoPicker();
                } else {
                    $(document).one('click.po-picker-outside', handler);
                }
            });
        });

        // Select asli tidak lagi punya atribut `required` (karena hidden) —
        // validasi manual di sini supaya tetap wajib diisi.
        $('#frmInvoice').on('submit', function (e) {
            if (!$select.val()) {
                e.preventDefault();
                Swal.fire({ icon: 'warning', title: 'Pilih BAST terlebih dahulu', confirmButtonColor: '#92232b' });
                $btn.trigger('click');
            }
        });
    })();

    function fmt(v) {
        v = (v || '').toString().replace(/\D/g, '');
        return v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    function rp(v) { return Math.round(v).toLocaleString('id-ID'); }

    // ── PO INFO ──────────────────────────────────────────────────────
    function renderAttachmentList(selector, attachments, emptyText) {
        if (attachments && attachments.length) {
            var html = '<table class="table table-sm mb-0"><tbody>';
            attachments.forEach(function (a) {
                html += '<tr><td style="font-size:12px;">' + a.title + '</td>' +
                    '<td class="text-right" style="width:1%;"><a href="/storage/' + a.path + '" target="_blank" class="btn btn-info btn-xs"><i class="fas fa-eye"></i></a></td></tr>';
            });
            html += '</tbody></table>';
            $(selector).html(html);
        } else {
            $(selector).html('<div class="text-muted" style="font-size:12px;">' + emptyText + '</div>');
        }
    }

    $('#selPo').on('change', function () {
        var po = poPayload[this.value];
        if (!po) {
            $('#poInfoBox').hide();
            var placeholderMsg = 'Pilih PO terlebih dahulu untuk menampilkan lampiran terkait.';
            $('#poAttachmentList, #noPoAttachmentList, #bastAttachmentList').html('<div class="text-muted" style="font-size:12px;">' + placeholderMsg + '</div>');
            recalc();
            return;
        }
        $('#poInfoCode').text(po.project_code);
        $('#poInfoNumber').text(po.po_number);
        $('#poInfoProject').text(po.project_name);
        $('#poInfoCustomer').text(po.customer);
        $('#poInfoBast').text(po.no_bast);
        $('#poInfoTop').text(po.term_of_payment);
        $('#poInfoValue').text(po.nominal_formatted);
        $('#poInfoRemaining').text(po.nilai_bast_formatted);
        $('#poInfoBox').show();

        renderAttachmentList('#poAttachmentList', po.po_attachments, 'PO ini belum punya lampiran.');
        renderAttachmentList('#noPoAttachmentList', po.no_po_attachments, 'No. PO ini belum punya lampiran.');
        renderAttachmentList('#bastAttachmentList', po.bast_attachments, 'No. PO ini belum punya lampiran BAST.');

        recalc();
    });

    // ── PAYMENT TERM ─────────────────────────────────────────────────
    $('#selPaymentTerm').on('change', function () {
        $('#terminNumberBox').toggle(this.value === 'Termin');
    });
    $('#selPaymentTerm').trigger('change');

    // ── DETAIL ROWS ──────────────────────────────────────────────────
    var detailIdx = 0;

    function buildUnitOptions(selectedUnit) {
        var html = '<option value="">—</option>';
        unitOptions.forEach(function (u) {
            var label = u.charAt(0).toUpperCase() + u.slice(1);
            html += '<option value="' + u + '"' + (u === selectedUnit ? ' selected' : '') + '>' + label + '</option>';
        });
        return html;
    }

    function makeDetailRow(i) {
        return '<tr class="detail-row">' +
            '<td class="text-center align-middle row-no"></td>' +
            '<td><input type="text" name="details[' + i + '][description]" class="form-control form-control-sm" placeholder="Deskripsi item..." required></td>' +
            '<td><input type="text" name="details[' + i + '][unit_price]" class="form-control form-control-sm text-right inp-price" placeholder="0"></td>' +
            '<td><input type="number" name="details[' + i + '][qty]" class="form-control form-control-sm text-center inp-qty" value="1" min="1"></td>' +
            '<td><select name="details[' + i + '][unit]" class="form-control form-control-sm inp-unit">' + buildUnitOptions('') + '</select></td>' +
            '<td><input type="text" name="details[' + i + '][deduction]" class="form-control form-control-sm text-right inp-deduction" placeholder="0"></td>' +
            '<td><input type="text" class="form-control form-control-sm text-right inp-total" value="0" readonly style="background:var(--surface-2);"></td>' +
            '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-detail"><i class="fas fa-trash"></i></button></td>' +
        '</tr>';
    }

    $('#btnAddDetail').on('click', function () {
        $('#detailBody').append(makeDetailRow(detailIdx++));
        renumber();
    });
    $('#btnAddDetail').trigger('click');

    // ── Download Template & Import Excel ────────────────────────────────
    $('#btnDownloadTemplate').on('click', function (e) {
        e.preventDefault();
        window.location.href = '{{ route("invoice-cash-receipt.import-template") }}';
    });

    $('#btnImportExcel').on('click', function () {
        $('#importFileInput').val('').trigger('click');
    });

    $('#importFileInput').on('change', function () {
        var file = this.files[0];
        if (!file) return;

        var formData = new FormData();
        formData.append('file', file);
        formData.append('_token', '{{ csrf_token() }}');

        Swal.fire({
            title: 'Memproses file...', text: 'Mohon tunggu sebentar.',
            allowOutsideClick: false, didOpen: function () { Swal.showLoading(); }
        });

        $.ajax({
            url: '{{ route("invoice-cash-receipt.import-preview") }}',
            type: 'POST', data: formData, processData: false, contentType: false
        }).done(function (res) {
            Swal.close();

            if (res.rows && res.rows.length) {
                $('#detailBody').empty();
                detailIdx = 0;
                res.rows.forEach(function (r) {
                    var i = detailIdx++;
                    $('#detailBody').append(makeDetailRow(i));
                    var $row = $('#detailBody .detail-row').last();
                    $row.find('[name="details[' + i + '][description]"]').val(r.description || '');
                    $row.find('[name="details[' + i + '][unit_price]"]').val(fmt(r.unit_price));
                    $row.find('[name="details[' + i + '][qty]"]').val(r.qty);
                    $row.find('[name="details[' + i + '][unit]"]').html(buildUnitOptions(r.unit || ''));
                    $row.find('[name="details[' + i + '][deduction]"]').val(fmt(r.deduction));
                });
                renumber();
                recalc();

                if (res.errors && res.errors.length) {
                    Swal.fire({
                        icon: 'warning', title: 'Import selesai dengan catatan',
                        html: res.errors.map(function (m) { return '<div style="text-align:left;font-size:12px;">' + m + '</div>'; }).join(''),
                        confirmButtonColor: '#92232b'
                    });
                } else {
                    Swal.fire({ icon: 'success', title: 'Import berhasil', text: res.rows.length + ' baris dimuat.', timer: 2000, showConfirmButton: false });
                }
            } else if (res.errors && res.errors.length) {
                Swal.fire({
                    icon: 'error', title: 'Tidak ada data valid',
                    html: res.errors.map(function (m) { return '<div style="text-align:left;font-size:12px;">' + m + '</div>'; }).join(''),
                    confirmButtonColor: '#92232b'
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Tidak ada data', text: 'File tidak berisi baris data yang bisa dibaca.', confirmButtonColor: '#92232b' });
            }
        }).fail(function (xhr) {
            Swal.close();
            var msg = xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors[0]
                ? xhr.responseJSON.errors[0]
                : 'Gagal memproses file. Pastikan format sesuai template.';
            Swal.fire({ icon: 'error', title: 'Import Gagal', text: msg, confirmButtonColor: '#92232b' });
        });
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('.btn-remove-detail')) {
            if ($('.detail-row').length > 1) {
                e.target.closest('tr').remove();
                renumber();
                recalc();
            } else {
                Swal.fire({ icon: 'warning', title: 'Minimal 1 baris', confirmButtonColor: '#92232b' });
            }
        }
    });

    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('inp-price') || e.target.classList.contains('inp-deduction')) {
            e.target.value = fmt(e.target.value);
        }
        // PPh diketik sebagai rupiah — diformat ribuan seperti kolom uang lain.
        if (e.target.id === 'inpWht') {
            e.target.value = fmt(e.target.value);
        }
        if (e.target.closest('.detail-row') || e.target.id === 'inpTaxRate' || e.target.id === 'inpWht' || e.target.id === 'inpBillingPct' || e.target.id === 'inpTerminNumber') recalc();
    });
    $('#selPaymentTerm').on('change', recalc);

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

        var pct = parseFloat($('#inpBillingPct').val());
        var billedAmount = (pct > 0 && pct <= 100) ? Math.round(netSubtotal * (pct / 100)) : netSubtotal;

        var taxRate = parseFloat($('#inpTaxRate').val()) || 0;
        var tax = Math.round(billedAmount * (taxRate / 100));

        /* MN-07 — Captured Total = Ditagih + PPN − PPh.
           Rumus ini cerminan InvoiceCalculationService::compute() di backend
           (termasuk clamp-nya). Angka yang tersimpan tetap dihitung ulang di
           server; yang di sini murni pratinjau. */
        var wht = parseInt(($('#inpWht').val() || '0').replace(/\./g, '')) || 0;
        wht = Math.max(0, Math.min(wht, billedAmount + tax));

        var grandTotal = billedAmount + tax - wht;

        $('#calcSubtotal').text(rp(subtotal));
        $('#calcDeduction').text(rp(deduction));
        $('#calcTax').text(rp(tax));
        $('#calcWht').text(wht > 0 ? '-' + rp(wht) : rp(0));
        $('#calcGrandTotal').text(rp(grandTotal));

        if (pct > 0 && pct <= 100) {
            var termType = $('#selPaymentTerm').val() || '';
            var terminNo = $('#inpTerminNumber').val();
            var termLabel = (termType === 'Termin' && terminNo) ? ('Termin-' + terminNo) : termType;
            var pctLabel = (pct % 1 === 0) ? pct.toFixed(0) : pct;
            $('#calcBilledLabel').text(termLabel + ' (' + pctLabel + '%)');
            $('#calcBilled').text(rp(billedAmount));
            $('#calcBilledRow').show();
        } else {
            $('#calcBilledRow').hide();
        }

        // 1 Invoice = 1 BAST: nilai ditagih (sebelum PPN) harus sama persis dengan nilai BAST.
        var po = poPayload[$('#selPo').val()];
        if (po) {
            var selisihBast = Math.abs(billedAmount - po.nilai_bast);
            var sesuai = selisihBast === 0;
            $('#calcBastCheck').removeClass('alert-success alert-danger').addClass(sesuai ? 'alert-success' : 'alert-danger').show();
            $('#calcBastCheckIcon').attr('class', 'fas mr-1 ' + (sesuai ? 'fa-check-circle' : 'fa-exclamation-triangle'));
            $('#calcBastCheckText').text(sesuai
                ? 'Nilai ditagih sesuai nilai BAST (' + po.nilai_bast_formatted + ').'
                : 'Nilai ditagih ' + rp(billedAmount) + ' tidak sesuai nilai BAST ' + po.nilai_bast_formatted
                  + ' (selisih ' + rp(selisihBast) + '). Nilai harus sama persis, invoice akan ditolak.');
        } else {
            $('#calcBastCheck').hide();
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