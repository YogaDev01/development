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
                {{-- Tidak lagi di dalam form GET: memilih customer cukup mengambil
                     daftar invoice-nya lewat JSON, halaman tidak dimuat ulang. --}}
                <div class="form-row align-items-end">
                    <div class="form-group col-lg-5 col-md-8 mb-0">
                        <label class="font-weight-600" for="crCustomer">Customer <span class="text-danger">*</span></label>
                        <select id="crCustomer" class="form-control form-control-sm">
                            <option value="">— Pilih Customer —</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" @selected((string) $customerId === (string) $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-lg-3 col-md-4 mb-0">
                        <span id="crLoading" class="text-muted" style="font-size:12px;display:none;">
                            <i class="fas fa-circle-notch fa-spin mr-1"></i> Memuat invoice…
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Langkah 2 & 3 ikut keadaan pilihan customer, disembunyikan sampai ada
             yang dipilih. Dulu bagian ini hanya dicetak kalau ?customer_id= ada —
             itulah yang memaksa halaman dimuat ulang. --}}
        <form action="{{ route('cash-receipt.store') }}" method="POST" enctype="multipart/form-data"
              id="formCr" style="display:none;">
            @csrf
            <input type="hidden" name="customer_id" id="crCustomerId" value="{{ $customerId }}">
            {{-- Diisi lewat dialog konfirmasi saat ada invoice yang dibayar tidak penuh. --}}
            <input type="hidden" name="partial_note" id="crPartialNote" value="">

            {{-- LANGKAH 2 — pilih invoice yang ditutup penerimaan ini. --}}
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header d-flex align-items-center flex-wrap" style="gap:8px;">
                    <h3 class="card-title"><i class="fas fa-file-invoice-dollar mr-1"></i> 2. Pilih Invoice (<span id="crInvCount">0</span>)</h3>
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
                    <div class="p-3" id="crEmptyState" style="display:none;">
                        <x-empty title="Tidak ada invoice terbuka untuk customer ini"
                                 subtitle="Semua invoice-nya sudah lunas, atau belum sampai tahap penagihan."
                                 icon="fas fa-check-circle" compact />
                    </div>

                    <div class="table-responsive" id="crTableWrap" style="display:none;">
                        <table class="table table-bordered table-hover mb-0" id="tblCrInvoices" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:1%;">
                                        <input type="checkbox" id="crCheckAll" title="Pilih semua">
                                    </th>
                                    <th scope="col">Invoice</th>
                                    <th scope="col" class="text-nowrap">Jatuh Tempo</th>
                                    <th scope="col" class="text-center text-nowrap">Status</th>
                                    <th scope="col" class="text-right text-nowrap" title="Invoice + PPN dikurangi penerimaan dan PPh sebelumnya">Sisa Tagihan</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:16%;">Diterima</th>
                                    <th scope="col" class="text-right text-nowrap" style="width:14%;">PPh</th>
                                </tr>
                            </thead>
                            {{-- Baris diisi JS dari cash-receipt.open-invoices. --}}
                            <tbody id="crInvBody"></tbody>
                            <tfoot>
                                <tr style="background:var(--surface-2);font-weight:700;">
                                    <th colspan="5" class="text-right">Total Penerimaan</th>
                                    <th class="text-right text-nowrap" id="crTotalAmount">0</th>
                                    <th class="text-right text-nowrap" id="crTotalWht">0</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

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
                            {{-- Input file asli tetap sumber nilai (name=proof_file); zona ini hanya mengisinya
                                 dari klik, drag & drop, atau paste (Ctrl+V). --}}
                            <div id="crProofZone" class="cr-dropzone" tabindex="0">
                                <input type="file" name="proof_file" id="crProofInput" accept=".pdf,.jpg,.jpeg,.png" style="display:none;">
                                <div id="crProofEmpty">
                                    <i class="fas fa-cloud-upload-alt mr-1"></i>
                                    Drop, paste (Ctrl+V), atau <span class="text-primary" style="text-decoration:underline;">klik</span>
                                </div>
                                <div id="crProofFile" style="display:none;">
                                    <i class="fas fa-paperclip mr-1"></i><span id="crProofName"></span>
                                    <button type="button" id="crProofRemove" class="btn btn-link btn-sm text-danger p-0 ml-1" title="Hapus berkas">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
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
        </form>

    </div>
</div>

@endsection

@push('styles')
<style>
.font-weight-600 { font-weight:600; }
.table td, .table th { vertical-align:middle !important; }

.cr-dropzone { border:1px dashed var(--border, #ced4da); border-radius:4px; padding:7px 10px; font-size:12px;
               color:var(--text-dim); cursor:pointer; background:var(--surface, #fff); outline:none; }
.cr-dropzone:hover, .cr-dropzone:focus, .cr-dropzone.dragover { border-color:var(--accent); background:var(--warning-bg); }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    function rp(n) { return (n || 0).toLocaleString('id-ID'); }

    /* Batas kewajaran PPh terhadap DPP invoice (keputusan pemilik). Hanya
       peringatan sisi klien: PPh otomatis tidak diisi di atas batas ini, dan
       dialog simpan memberi peringatan merah. Server TIDAK membatasi. */
    var BATAS_PPH_PERSEN = 15;

    // Integer rupiah, tanpa float: pph/dpp > 15%  <=>  pph*100 > 15*dpp.
    // DPP 0/kosong: hanya PPh 0 yang dianggap wajar.
    function pphMelebihiBatas(pph, dpp) {
        return pph * 100 > BATAS_PPH_PERSEN * (dpp || 0);
    }

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
            $wht.val('').removeAttr('data-manual');
            $('.cr-pph-info', $row).remove();
        }
        $row.removeClass('table-danger');
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
        // Diterima + PPh tidak boleh melebihi sisa tagihan baris mana pun.
        var melebihi = $('.cr-inv-row.table-danger').filter(function () {
            return $('.cr-check', this).is(':checked');
        }).length > 0;
        $('#crSubmit').prop('disabled', jumlah === 0 || (totalAmount + totalWht) <= 0 || melebihi);

        var $tampil = $('.cr-inv-row:visible .cr-check');
        $('#crCheckAll').prop('checked', $tampil.length > 0 && $tampil.filter(':checked').length === $tampil.length);
    }

    $(document).on('change', '.cr-check', function () {
        setBaris($(this).closest('tr'), $(this).is(':checked'));
        hitungUlang();
    });

    $(document).on('input', '.cr-amount, .cr-wht', function () {
        formatUang($(this));

        var $row    = $(this).closest('tr');
        var sisa    = parseInt($('.cr-balance', $row).data('value'), 10) || 0;
        var $wht    = $('.cr-wht', $row);

        /* PPh otomatis = selisih sisa tagihan dan nominal diterima, sampai user
           mengetik PPh sendiri (data-manual dipasang HANYA oleh ketikan user di
           kolom PPh, bukan oleh kode ini; dihapus bila kolom dikosongkan atau
           baris di-uncheck). Diterima kosong -> PPh dikosongkan; Diterima
           melebihi sisa -> PPh 0 dan baris ditandai merah (lihat di bawah). */
        if ($(this).hasClass('cr-wht')) {
            if ($wht.val()) { $wht.attr('data-manual', '1'); }
            else            { $wht.removeAttr('data-manual'); }
        } else if (!$wht.attr('data-manual')) {
            var diterima = angka($(this));
            var dpp      = parseInt($('.cr-check', $row).data('dpp'), 10) || 0;
            var usulan   = Math.max(0, sisa - diterima);
            $wht.val('');
            $('.cr-pph-info', $row).remove();
            if ($(this).val() && diterima <= sisa) {
                if (pphMelebihiBatas(usulan, dpp)) {
                    // Tidak diisi otomatis; beri tahu alasannya di bawah kolom PPh.
                    $wht.after('<div class="cr-pph-info text-danger" style="font-size:10px;">'
                        + 'PPh tidak diisi otomatis: Rp ' + rp(usulan) + ' &gt; ' + BATAS_PPH_PERSEN + '% DPP</div>');
                } else if (usulan > 0) {
                    $wht.val(rp(usulan));
                }
            }
        }
        // PPh diketik/dikosongkan sendiri oleh user: catatan "tidak diisi otomatis" tidak relevan lagi.
        if ($(this).hasClass('cr-wht')) { $('.cr-pph-info', $row).remove(); }

        /* Nominal + PPh tidak boleh melebihi sisa tagihan barisnya. Backend
           tetap memeriksa ulang di dalam transaksi terkunci — ini hanya supaya
           orang tahu sebelum menekan Simpan, bukan sesudah ditolak. */
        var terisi  = angka($('.cr-amount', $row)) + angka($wht);

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

    /*
     |--------------------------------------------------------------------------
     | MEMUAT INVOICE SAAT CUSTOMER DIPILIH
     |--------------------------------------------------------------------------
     | Hanya isi tabel yang diganti; isian Langkah 3 (tanggal, rekening, catatan)
     | yang sudah diketik tetap utuh. Itu bedanya dengan versi lama yang memuat
     | ulang halaman dan membuang semuanya.
     |
     | Daftar yang dipakai TETAP datang dari server lewat
     | cash-receipt.open-invoices — bukan dicetak sekaligus untuk semua customer
     | di halaman, supaya invoice customer lain tidak pernah ikut terkirim.
     */
    var URL_INVOICE = '{{ route('cash-receipt.open-invoices') }}';
    var permintaan  = null;

    function esc(t) {
        return String(t == null ? '' : t)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function barisInvoice(inv) {
        var cari = (inv.code + ' ' + inv.po_number + ' ' + inv.project).toLowerCase();

        return '<tr class="cr-inv-row" data-search="' + esc(cari) + '">'
            + '<td><input type="checkbox" name="invoice_ids[]" value="' + inv.id + '" class="cr-check" data-balance="' + inv.balance
            +     '" data-code="' + esc(inv.code) + '" data-dpp="' + (inv.dpp || 0) + '"></td>'
            + '<td><strong>' + esc(inv.code) + '</strong>'
            +     '<div class="text-muted" style="font-size:10px;">' + esc(inv.po_number) + ' &middot; ' + esc(inv.project) + '</div>'
            +     (inv.wht_muka > 0
                    ? '<div class="text-warning" style="font-size:10px;">PPh di muka Rp ' + rp(inv.wht_muka) + ' akan diganti PPh aktual</div>'
                    : '')
            +     '</td>'
            + '<td class="text-nowrap">' + esc(inv.due_date || '-')
            +     (inv.overdue ? ' <span class="badge badge-danger ml-1" style="font-size:9px;">OVERDUE</span>' : '') + '</td>'
            + '<td class="text-center"><span class="badge badge-secondary px-2 py-1" style="font-size:9.5px;">' + esc(inv.status) + '</span></td>'
            + '<td class="text-right text-nowrap cr-balance" data-value="' + inv.balance + '"><strong>' + rp(inv.balance) + '</strong></td>'
            + '<td><input type="text" name="amounts[' + inv.id + ']" class="form-control form-control-sm text-right cr-amount" placeholder="0" disabled></td>'
            + '<td><input type="text" name="withholding[' + inv.id + ']" class="form-control form-control-sm text-right cr-wht" placeholder="0" disabled></td>'
            + '</tr>';
    }

    function gambarInvoice(list) {
        var html = list.map(barisInvoice).join('');
        html += '<tr id="crNoMatch" style="display:none;"><td colspan="7" class="text-center text-muted py-3">'
             +  'Tidak ada invoice yang cocok dengan pencarian.</td></tr>';

        $('#crInvBody').html(html);
        $('#crInvCount').text(list.length);
        $('#crCheckAll').prop('checked', false);
        $('#crSearch').val('');
        $('#crTableWrap').toggle(list.length > 0);
        $('#crEmptyState').toggle(list.length === 0);
        hitungUlang();
    }

    function muatInvoice(customerId) {
        $('#crCustomerId').val(customerId || '');

        if (!customerId) {
            $('#formCr').hide();
            gambarInvoice([]);
            $('#crEmptyState').hide();
            return;
        }

        // Permintaan sebelumnya dibatalkan: kalau customer diganti cepat, jawaban
        // yang datang belakangan bisa milik pilihan yang sudah tidak aktif.
        if (permintaan) permintaan.abort();

        $('#crLoading').show();

        permintaan = $.getJSON(URL_INVOICE, { customer_id: customerId })
            .done(function (res) {
                $('#formCr').show();
                gambarInvoice((res && res.invoices) || []);
            })
            .fail(function (xhr, status) {
                if (status === 'abort') return;
                $('#formCr').hide();
                Swal.fire({ icon: 'error', title: 'Gagal memuat invoice',
                            text: 'Coba pilih ulang customer-nya.', confirmButtonColor: 'var(--accent)' });
            })
            .always(function () { $('#crLoading').hide(); permintaan = null; });
    }

    TesysSearchableSelect.init('#crCustomer', {
        placeholder: '— Pilih Customer —',
        searchPlaceholder: 'Cari customer...'
    });

    $('#crCustomer').on('change', function () { muatInvoice($(this).val()); });

    // ?customer_id= dari tautan lama tetap dilayani.
    if ($('#crCustomer').val()) muatInvoice($('#crCustomer').val());

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

    /*
     | Bukti transfer: klik, drag & drop, atau paste. File dimasukkan ke input
     | asli lewat DataTransfer, jadi form tetap terkirim apa adanya.
     */
    (function () {
        var zone  = document.getElementById('crProofZone');
        var input = document.getElementById('crProofInput');
        var BOLEH = /\.(pdf|jpe?g|png)$/i;

        function tampil() {
            var f = input.files && input.files[0];
            $('#crProofEmpty').toggle(!f);
            $('#crProofFile').toggle(!!f);
            $('#crProofName').text(f ? f.name : '');
        }

        function pasang(file) {
            if (!file) return;
            if (!BOLEH.test(file.name) && !/^(image\/(png|jpe?g)|application\/pdf)$/.test(file.type)) {
                Swal.fire({ icon: 'warning', title: 'Format tidak didukung', text: 'Gunakan PDF, JPG, atau PNG.', confirmButtonColor: 'var(--accent)' });
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                Swal.fire({ icon: 'warning', title: 'Berkas terlalu besar', text: 'Maksimal 10 MB.', confirmButtonColor: 'var(--accent)' });
                return;
            }
            // Gambar hasil paste biasanya bernama generik ("image.png"); beri nama yang jelas.
            if (!file.name || /^image\.\w+$/i.test(file.name)) {
                var ext = (file.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
                file = new File([file], 'bukti-transfer.' + ext, { type: file.type });
            }
            var dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            tampil();
        }

        zone.addEventListener('click', function (e) {
            if ($(e.target).closest('#crProofRemove').length) return;
            input.click();
        });
        input.addEventListener('click', function (e) { e.stopPropagation(); });
        input.addEventListener('change', tampil);

        $('#crProofRemove').on('click', function (e) {
            e.stopPropagation();
            input.value = '';
            tampil();
        });

        ['dragenter', 'dragover'].forEach(function (evt) {
            zone.addEventListener(evt, function (e) { e.preventDefault(); e.stopPropagation(); zone.classList.add('dragover'); });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            zone.addEventListener(evt, function (e) { e.preventDefault(); e.stopPropagation(); zone.classList.remove('dragover'); });
        });
        zone.addEventListener('drop', function (e) {
            var files = e.dataTransfer ? e.dataTransfer.files : null;
            if (files && files.length) pasang(files[0]);
        });

        // Paste di mana saja pada halaman ini, kecuali saat mengetik di kolom teks.
        document.addEventListener('paste', function (e) {
            var t = e.target;
            if (t && /^(input|textarea|select)$/i.test(t.tagName)) return;
            var files = (e.clipboardData && e.clipboardData.files) || [];
            if (files.length) { e.preventDefault(); pasang(files[0]); }
        });
    })();

    /*
     | Simpan: kiriman kosong dicegah; kalau ada invoice yang dibayar tidak
     | penuh (Diterima + PPh < Sisa Tagihan), minta konfirmasi plus komentar yang
     | disimpan ke Log & Approval tiap invoice terkait. Backend memeriksa ulang.
     */
    $('#formCr').on('submit', function (e) {
        if ($('.cr-check:checked').length === 0) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'Pilih minimal satu invoice', confirmButtonColor: 'var(--accent)' });
            return;
        }

        var form = this, parsial = 0, melebihi = 0, adaPph = 0, ringkasan = '', peringatan = '';
        $('.cr-inv-row').each(function () {
            if (!$('.cr-check', this).is(':checked')) return;
            var sisa   = parseInt($('.cr-balance', this).data('value'), 10) || 0;
            var diterima = angka($('.cr-amount', this));
            var pph    = angka($('.cr-wht', this));
            var terisi = diterima + pph;
            if (terisi > sisa) melebihi++;
            else if (terisi < sisa) parsial++;

            /* Ringkasan per invoice ber-PPh: PPh yang diinput akan MENGGANTIKAN PPh pada
               invoice, jadi user diminta memastikannya sebelum menyimpan. */
            if (pph > 0) {
                adaPph++;
                var $cek = $('.cr-check', this);
                var dpp  = parseInt($cek.data('dpp'), 10) || 0;
                var persen = dpp > 0 ? ' (' + (pph / dpp * 100).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + '% dari DPP)' : '';
                var lebih = pphMelebihiBatas(pph, dpp);
                if (lebih) {
                    peringatan += '<li><strong>' + esc($cek.data('code')) + '</strong>: PPh Rp ' + rp(pph)
                        + (dpp > 0 ? ' = ' + (pph / dpp * 100).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + '% dari DPP' : ' (DPP kosong)')
                        + '</li>';
                }
                ringkasan += '<li' + (lebih ? ' class="text-danger"' : '') + '><strong>' + esc($cek.data('code')) + '</strong>: diterima Rp ' + rp(diterima)
                    + ', PPh Rp ' + rp(pph) + persen + ', sisa setelah ini Rp ' + rp(Math.max(0, sisa - terisi)) + '</li>';
            }
        });
        var htmlRingkasan = ringkasan
            ? '<div class="text-left" style="font-size:12px;">PPh yang diisi akan menggantikan PPh pada invoice:'
              + '<ul class="mb-0 mt-1 pl-3">' + ringkasan + '</ul></div>'
            : '';
        if (peringatan) {
            htmlRingkasan += '<div class="text-left text-danger mt-2" style="font-size:12px;">'
                + '<strong>PERINGATAN: PPh melebihi ' + BATAS_PPH_PERSEN + '% DPP invoice.</strong> '
                + 'Nilai ini akan mengubah PPh dan nilai invoice. Pastikan angkanya benar sebelum menyimpan.'
                + '<ul class="mb-0 mt-1 pl-3">' + peringatan + '</ul></div>';
        }

        if (melebihi > 0) {
            e.preventDefault();
            Swal.fire({ icon: 'error', title: 'Nominal melebihi sisa tagihan',
                        text: 'Diterima + PPh tidak boleh lebih besar dari Sisa Tagihan.', confirmButtonColor: 'var(--accent)' });
            return;
        }

        if (adaPph > 0 && parsial === 0 && !form.dataset.konfirmasi) {
            e.preventDefault();
            Swal.fire({
                icon: 'question',
                title: 'Simpan penerimaan dengan PPh?',
                html: htmlRingkasan,
                showCancelButton: true,
                confirmButtonColor: 'var(--accent)', cancelButtonColor: 'var(--text-muted)',
                confirmButtonText: 'Ya, Simpan', cancelButtonText: 'Batal'
            }).then(function (r) {
                if (!r.isConfirmed) return;
                form.dataset.konfirmasi = '1';
                form.submit();
            });
            return;
        }

        if (parsial > 0 && !form.dataset.konfirmasi) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Apakah anda yakin, pembayaran yang diterima tidak penuh?',
                html: '<div style="font-size:13px;">' + parsial + ' invoice dibayar kurang dari sisa tagihannya. Komentar ini masuk ke Log &amp; Approval invoice terkait.</div>'
                    + htmlRingkasan,
                input: 'textarea',
                inputPlaceholder: 'Komentar / alasan pembayaran tidak penuh (wajib)',
                inputValidator: function (v) { return (v && v.trim()) ? undefined : 'Komentar wajib diisi'; },
                showCancelButton: true,
                confirmButtonColor: 'var(--accent)', cancelButtonColor: 'var(--text-muted)',
                confirmButtonText: 'Ya, Simpan', cancelButtonText: 'Batal'
            }).then(function (r) {
                if (!r.isConfirmed) return;
                $('#crPartialNote').val(r.value.trim());
                form.dataset.konfirmasi = '1';
                form.submit();
            });
        }
    });

    hitungUlang();
});
</script>
@endpush
