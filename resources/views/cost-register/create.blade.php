@extends('layouts.app')

@section('title', 'Tambah Cost Register')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ route('cost-register.index') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Tambah Cost Register</h1>
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

        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="crModeTabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#paneSingle" id="tabSingle">
                            <i class="fas fa-file mr-1"></i> Entri Tunggal
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#paneBatch" id="tabBatch">
                            <i class="fas fa-layer-group mr-1"></i> Alokasi Overhead (Batch)
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">

                    <!-- ENTRI TUNGGAL -->
                    <div class="tab-pane fade show active" id="paneSingle">
                        <form action="{{ route('cost-register.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="mode" value="single">

                            <div class="alert alert-light border" style="font-size:12px;">
                                Fixed Cost atau Overhead untuk satu No. PO, dengan rincian per item (akun, deskripsi, qty,
                                harga). Nominal register = jumlah total rincian. Wajib isi catatan sebagai dasar perhitungan
                                (mis. hasil prorate personel, atau porsi alokasi overhead). Setelah diajukan, register menunggu
                                approval CEO sebelum bisa dibayar lewat Treasury.
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label class="font-weight-600">No. PO <span class="text-danger">*</span></label>
                                    <select name="project_id" id="singleProjectSelect" class="form-control form-control-sm" required>
                                        <option value="">-- Pilih PO --</option>
                                        @foreach($projects as $p)
                                        <option value="{{ $p->id }}">{{ $p->customer->name ?? '-' }} - {{ $p->code }} - {{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="font-weight-600">Tipe <span class="text-danger">*</span></label>
                                    <select name="type" class="form-control form-control-sm" required>
                                        <option value="FIXED_COST">Fixed Cost</option>
                                        <option value="OVERHEAD">Overhead</option>
                                    </select>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="font-weight-600">Periode</label>
                                    <input type="month" name="period" class="form-control form-control-sm">
                                </div>
                            </div>

                            {{-- Rincian per item (pola Cost Request). Total dihitung ulang di server. --}}
                            <div class="form-group">
                                <label class="font-weight-600">Rincian <span class="text-danger">*</span></label>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm mb-1" id="tblDetails" style="table-layout:fixed;min-width:720px;">
                                        <thead style="background:var(--surface-2);">
                                            <tr>
                                                <th style="width:44px;">No</th>
                                                <th style="width:220px;">Akun</th>
                                                <th>Deskripsi</th>
                                                <th class="text-right" style="width:72px;">Qty</th>
                                                <th class="text-right" style="width:140px;">Harga Satuan</th>
                                                <th class="text-right" style="width:140px;">Total</th>
                                                <th style="width:48px;"><span class="sr-only">Aksi</span></th>
                                            </tr>
                                        </thead>
                                        <tbody id="detailBody"></tbody>
                                        <tfoot>
                                            <tr style="background:var(--surface-2);">
                                                <th colspan="5" class="text-right">Total Nominal</th>
                                                <th class="text-right" id="detailGrand">0</th>
                                                <th></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <button type="button" class="btn btn-sm btn-secondary" id="btnAddDetail"><i class="fas fa-plus mr-1"></i> Tambah Rincian</button>
                                <template id="tplAccountOptions">
                                    <option value="">— Akun —</option>
                                    @foreach($accounts->groupBy('category') as $kategori => $akun)
                                    <optgroup label="{{ $kategori }}">
                                        @foreach($akun as $a)
                                        <option value="{{ $a->id }}">{{ $a->name }}</option>
                                        @endforeach
                                    </optgroup>
                                    @endforeach
                                </template>
                            </div>

                            {{-- MN-09 — tujuan pembayaran. Tanpa Beneficiary,
                                 Cost Register tidak bisa diajukan ke Treasury:
                                 Treasury perlu tahu ke mana uangnya ditransfer. --}}
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label class="font-weight-600">Beneficiary</label>
                                    <select name="beneficiary_id" class="form-control form-control-sm">
                                        <option value="">— Pilih Beneficiary —</option>
                                        @foreach($beneficiaries as $b)
                                            <option value="{{ $b->id }}">{{ $b->nickname }} — {{ $b->complete_name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Wajib diisi sebelum diajukan ke Treasury.</small>
                                </div>
                                <div class="form-group col-md-6">
                                    <label class="font-weight-600">Rekening Sumber</label>
                                    <select name="source_fund_id" class="form-control form-control-sm">
                                        <option value="">— Pilih Rekening —</option>
                                        @foreach($sourceFunds as $sf)
                                            <option value="{{ $sf->id }}">{{ $sf->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="font-weight-600">Catatan / Dasar Perhitungan <span class="text-danger">*</span></label>
                                <textarea name="notes" class="form-control form-control-sm" rows="3" placeholder="Jelaskan dasar perhitungan nominal ini..." required></textarea>
                            </div>

                            <div class="form-group mb-0">
                                <label class="font-weight-600">Lampiran</label>
                                <input type="file" name="attachments[]" class="form-control-file" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                                <small class="text-muted">Bukti perhitungan/approval — bisa lebih dari satu file.</small>
                            </div>

                            <div class="text-right mt-3">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
                            </div>
                        </form>
                    </div>

                    <!-- ALOKASI OVERHEAD (BATCH) -->
                    <div class="tab-pane fade" id="paneBatch">
                        <form action="{{ route('cost-register.store') }}" method="POST" enctype="multipart/form-data" id="frmBatch">
                            @csrf
                            <input type="hidden" name="mode" value="batch">

                            <div class="alert alert-light border" style="font-size:12px;">
                                Satu pool Overhead (mis. 108.000.000) dibagi ke beberapa No. PO sekaligus — rata,
                                atau nominal manual per PO kalau porsinya tidak sama. Baris yang dihasilkan ditandai
                                kode batch yang sama supaya bisa ditelusuri bersama.
                            </div>

                            <div class="row">
                                <div class="col-md-8 form-group">
                                    <label class="font-weight-600">No. PO (bisa pilih beberapa) <span class="text-danger">*</span></label>
                                    <select name="project_ids[]" id="batchProjectSelect" class="form-control form-control-sm" multiple required>
                                        @foreach($projects as $p)
                                        <option value="{{ $p->id }}">{{ $p->customer->name ?? '-' }} - {{ $p->code }} - {{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label class="font-weight-600">Periode</label>
                                    <input type="month" name="period" class="form-control form-control-sm">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label class="font-weight-600">Total Overhead <span class="text-danger">*</span></label>
                                    <input type="text" name="total_amount" id="inpBatchTotal" class="form-control form-control-sm text-right" placeholder="0" required>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="font-weight-600">Metode Distribusi <span class="text-danger">*</span></label>
                                    <select name="distribution" id="selDistribution" class="form-control form-control-sm" required>
                                        <option value="equal">Rata (dibagi sama besar)</option>
                                        <option value="manual">Manual (nominal per PO)</option>
                                    </select>
                                </div>
                            </div>

                            {{-- MN-09 — tujuan pembayaran untuk SELURUH batch.
                                 Diturunkan ke setiap baris yang terbentuk, supaya
                                 tiap baris langsung siap diajukan ke Treasury tanpa
                                 harus diedit satu per satu lebih dulu. --}}
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label class="font-weight-600">Beneficiary</label>
                                    <select name="beneficiary_id" class="form-control form-control-sm">
                                        <option value="">— Pilih Beneficiary —</option>
                                        @foreach($beneficiaries as $b)
                                            <option value="{{ $b->id }}">{{ $b->nickname }} — {{ $b->complete_name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Berlaku untuk semua PO dalam batch ini. Wajib sebelum diajukan ke Treasury.</small>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="font-weight-600">Rekening Sumber</label>
                                    <select name="source_fund_id" class="form-control form-control-sm">
                                        <option value="">— Pilih Rekening —</option>
                                        @foreach($sourceFunds as $sf)
                                            <option value="{{ $sf->id }}">{{ $sf->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div id="manualAmountWrap" class="form-group" style="display:none;">
                                <label class="font-weight-600">Nominal per PO</label>
                                <div id="manualAmountList" class="border rounded p-2" style="font-size:12px;"></div>
                                <small class="text-muted">Total nominal manual harus sama persis dengan Total Overhead di atas.</small>
                            </div>

                            <div class="form-group">
                                <label class="font-weight-600">Catatan / Dasar Alokasi <span class="text-danger">*</span></label>
                                <textarea name="notes" class="form-control form-control-sm" rows="3" placeholder="Jelaskan dasar perhitungan & alokasi overhead ini..." required></textarea>
                            </div>

                            <div class="form-group mb-0">
                                <label class="font-weight-600">Lampiran</label>
                                <input type="file" name="attachments[]" class="form-control-file" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                                <small class="text-muted">Bukti perhitungan pool overhead — bisa lebih dari satu file.</small>
                            </div>

                            <div class="text-right mt-3">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Alokasi</button>
                            </div>
                        </form>
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

    function fmt(v) {
        v = (v || '').toString().replace(/\D/g, '');
        return v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    function unfmt(v) { return parseInt((v || '0').toString().replace(/\D/g, ''), 10) || 0; }

    $('#inpBatchTotal').on('input', function () { this.value = fmt(this.value); });

    /* ── Rincian per item (Entri Tunggal) ── */
    var detailIdx = 0;
    function recalcDetails() {
        var grand = 0;
        $('#detailBody tr').each(function (i) {
            var qty = parseInt($(this).find('.d-qty').val(), 10) || 0;
            var price = unfmt($(this).find('.d-price').val());
            var tot = qty * price;
            grand += tot;
            $(this).find('.d-total').text(fmt(String(tot)) || '0');
            $(this).find('.d-no').text(i + 1);
        });
        $('#detailGrand').text(fmt(String(grand)) || '0');
    }
    function addDetailRow() {
        var i = detailIdx++;
        var opts = document.getElementById('tplAccountOptions').innerHTML;
        $('#detailBody').append(
            '<tr>' +
            '<td class="d-no text-center"></td>' +
            '<td><select name="details[' + i + '][account_id]" class="form-control form-control-sm">' + opts + '</select></td>' +
            '<td><input type="text" name="details[' + i + '][description]" class="form-control form-control-sm" maxlength="500" required></td>' +
            '<td><input type="number" name="details[' + i + '][qty]" class="form-control form-control-sm text-right d-qty" min="1" value="1" required></td>' +
            '<td><input type="text" name="details[' + i + '][unit_price]" class="form-control form-control-sm text-right d-price" placeholder="0" required></td>' +
            '<td class="text-right d-total">0</td>' +
            '<td class="text-center"><button type="button" class="btn btn-xs btn-danger btn-del-detail" aria-label="Hapus baris"><i class="fas fa-trash"></i></button></td>' +
            '</tr>'
        );
        recalcDetails();
    }
    $('#btnAddDetail').on('click', addDetailRow);
    $(document).on('click', '.btn-del-detail', function () {
        if ($('#detailBody tr').length > 1) { $(this).closest('tr').remove(); recalcDetails(); }
    });
    $(document).on('input', '.d-qty', recalcDetails);
    $(document).on('input', '.d-price', function () { this.value = fmt(this.value); recalcDetails(); });
    addDetailRow();

    TesysSearchableSelect.init('#singleProjectSelect', { placeholder: '-- Pilih PO --', searchPlaceholder: 'Cari PO...' });
    TesysSearchableSelect.init('#batchProjectSelect', { placeholder: 'Pilih No. PO...', searchPlaceholder: 'Cari PO...' });

    function renderManualAmountRows() {
        var selected = $('#batchProjectSelect').val() || [];
        var $wrap = $('#manualAmountList');
        var existing = {};
        $wrap.find('input.manual-amount-input').each(function () {
            existing[$(this).data('pid')] = $(this).val();
        });
        $wrap.empty();

        if (!selected.length) {
            $wrap.append('<div class="text-muted">Pilih No. PO terlebih dahulu.</div>');
            return;
        }

        selected.forEach(function (pid) {
            var label = $('#batchProjectSelect option[value="' + pid + '"]').text();
            var val = existing[pid] || '';
            $wrap.append(
                '<div class="d-flex align-items-center mb-1" style="gap:8px;">' +
                '<div style="flex:1;">' + label + '</div>' +
                '<input type="text" class="form-control form-control-sm text-right manual-amount-input" ' +
                'style="width:160px;" name="manual_amount[' + pid + ']" data-pid="' + pid + '" value="' + val + '" placeholder="0">' +
                '</div>'
            );
        });
    }

    function toggleDistributionMode() {
        var isManual = $('#selDistribution').val() === 'manual';
        $('#manualAmountWrap').toggle(isManual);
        if (isManual) renderManualAmountRows();
    }

    $('#selDistribution').on('change', toggleDistributionMode);
    $(document).on('change', '#batchProjectSelect', function () {
        if ($('#selDistribution').val() === 'manual') renderManualAmountRows();
    });
    $(document).on('input', '.manual-amount-input', function () { this.value = fmt(this.value); });

    toggleDistributionMode();

    $('#frmBatch').on('submit', function (e) {
        if ($('#selDistribution').val() !== 'manual') return;
        var total = unfmt($('#inpBatchTotal').val());
        var sum = 0;
        $('.manual-amount-input').each(function () { sum += unfmt($(this).val()); });
        if (sum !== total) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning', title: 'Nominal tidak sama',
                text: 'Total nominal manual (' + fmt(sum) + ') harus sama dengan Total Overhead (' + fmt(total) + ').',
                confirmButtonColor: '#92232b'
            });
        }
    });
});
</script>
@endpush
