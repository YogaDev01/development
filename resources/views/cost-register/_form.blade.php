{{--
    Form Expense — dipakai halaman Tambah ($cr = null) dan Edit Draft ($cr terisi).
    Butuh: $cr, $prefill, $periodPrefill, $beneficiaries + opsi rincian (detailOptions()).
--}}
@php
    $baris = old('details') ? array_values(old('details')) : $prefill;
    $isCa = (bool) old('is_cash_advance', $cr?->is_cash_advance ?? false);
    $mulai = old('period_start', $cr?->period_start?->format('Y-m') ?? $cr?->period?->format('Y-m') ?? now()->format('Y-m'));
    $akhir = old('period_end', $cr?->period_end?->format('Y-m') ?? $mulai);
    $metode = old('period_method', $cr?->period_method ?? 'equal');
    $nominalPeriode = old('period_amounts', $periodPrefill);
    $benTerpilih = old('beneficiary_id', $cr?->beneficiary_id);
@endphp

<div class="alert alert-light border" style="font-size:12px;">
    Biaya di luar Cost Request, tanpa budgeting. Kategori biaya mengikuti <strong>Akun</strong>: Variable Cost dibebankan ke satu PO;
    Fixed Cost / Overhead bisa ke satu PO, seluruh PO dalam satu tahun, beberapa PO terpilih (dibagi proporsional nilai PO), atau
    Cost Center. Nilai bisa dibebankan ke beberapa bulan. Setelah diajukan Finance Manager, Expense menunggu approval CEO sebelum
    dibayar lewat Treasury.
</div>

<div class="row">
    <div class="col-lg-4 form-group">
        <label class="font-weight-600 d-block">Jenis <span class="text-danger">*</span></label>
        <div class="btn-group btn-group-toggle" data-toggle="buttons" role="radiogroup" aria-label="Jenis Expense">
            <label class="btn btn-sm btn-outline-secondary {{ $isCa ? '' : 'active' }}">
                <input type="radio" name="is_cash_advance" value="0" autocomplete="off" @checked(! $isCa)> Non-CA (bayar langsung)
            </label>
            <label class="btn btn-sm btn-outline-secondary {{ $isCa ? 'active' : '' }}">
                <input type="radio" name="is_cash_advance" value="1" autocomplete="off" @checked($isCa)> Cash Advance
            </label>
        </div>
        <small class="text-muted d-block mt-1">Cash Advance: setelah dibayar, Finance Manager wajib input realisasi (settlement) untuk disetujui CEO.</small>
    </div>
    <div class="col-lg-8 form-group">
        <label class="font-weight-600 d-block">Periode Pembebanan <span class="text-danger">*</span></label>
        <div class="d-flex flex-wrap align-items-center" style="gap:8px;">
            <input type="month" name="period_start" id="inpPeriodStart" class="form-control form-control-sm" style="max-width:170px;" value="{{ $mulai }}" aria-label="Periode awal" required>
            <span class="text-muted">s.d.</span>
            <input type="month" name="period_end" id="inpPeriodEnd" class="form-control form-control-sm" style="max-width:170px;" value="{{ $akhir }}" aria-label="Periode akhir">
            <div class="btn-group btn-group-toggle ml-1" data-toggle="buttons" id="grpPeriodMethod" role="radiogroup" aria-label="Metode pembagian">
                <label class="btn btn-sm btn-outline-secondary {{ $metode === 'manual' ? '' : 'active' }}">
                    <input type="radio" name="period_method" value="equal" autocomplete="off" @checked($metode !== 'manual')> Bagi rata
                </label>
                <label class="btn btn-sm btn-outline-secondary {{ $metode === 'manual' ? 'active' : '' }}">
                    <input type="radio" name="period_method" value="manual" autocomplete="off" @checked($metode === 'manual')> Bagi manual
                </label>
            </div>
        </div>
        <small class="text-muted d-block mt-1" id="periodHint">Satu bulan: seluruh nilai dibebankan ke bulan itu.</small>
    </div>
</div>

<div class="form-group">
    <label class="font-weight-600">Rincian <span class="text-danger">*</span></label>
    @include('cost-register._detail_rows')
</div>

{{-- Pembebanan per bulan (tampil bila lebih dari satu bulan). --}}
<div class="form-group" id="periodPanel" style="display:none;">
    <label class="font-weight-600">Pembebanan per Bulan</label>
    <div class="table-responsive">
        <table class="table table-bordered table-sm mb-1" style="max-width:520px;">
            <thead style="background:var(--surface-2);">
                <tr><th>Periode</th><th class="text-right" style="width:200px;">Nominal</th></tr>
            </thead>
            <tbody id="periodBody"></tbody>
            <tfoot>
                <tr style="background:var(--surface-2);">
                    <th class="text-right">Jumlah</th>
                    <th class="text-right" id="periodSum">0</th>
                </tr>
            </tfoot>
        </table>
    </div>
    <small class="text-muted" id="periodNote"></small>
</div>

{{-- MN-09 — tujuan pembayaran. Tanpa Beneficiary, Expense tidak bisa diajukan:
     Treasury perlu tahu ke mana uangnya ditransfer. Rekening sumber diatur Treasury. --}}
<div class="form-group">
    <label class="font-weight-600" for="selBeneficiary">Beneficiary</label>
    <select name="beneficiary_id" id="selBeneficiary" class="form-control form-control-sm">
        <option value="">— Pilih Beneficiary —</option>
        @foreach($beneficiaries as $b)
            <option value="{{ $b->id }}" data-label="{{ $b->nickname }} — {{ $b->complete_name }}"
                    data-search="{{ $b->bank->name ?? '' }} {{ $b->account_number }}"
                    @selected((string) $benTerpilih === (string) $b->id)>{{ $b->nickname }} — {{ $b->complete_name }}{{ $b->bank ? ' · ' . $b->bank->name . ' ' . $b->account_number : '' }}</option>
        @endforeach
    </select>
    <small class="text-muted">Wajib diisi sebelum diajukan. Rekening sumber dipilih Treasury saat menyusun batch.</small>
</div>

<div class="form-group">
    <label class="font-weight-600" for="txtNotes">Catatan / Dasar Perhitungan <span class="text-danger">*</span></label>
    <textarea name="notes" id="txtNotes" class="form-control form-control-sm" rows="3" maxlength="2000" placeholder="Jelaskan keperluan dan dasar perhitungan nominal ini..." required>{{ old('notes', $cr?->notes) }}</textarea>
</div>

<div class="form-group mb-0">
    <label class="font-weight-600" for="inpAttachments">Lampiran</label>
    @if($cr && $cr->attachments->isNotEmpty())
    <div class="mb-2" style="font-size:12px;">
        @foreach($cr->attachments as $a)
        <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="rmAtt{{ $a->id }}" name="remove_attachments[]" value="{{ $a->id }}">
            <label class="custom-control-label" for="rmAtt{{ $a->id }}">
                <a href="/storage/{{ $a->file_path }}" target="_blank" rel="noopener"><i class="fas fa-paperclip"></i> {{ $a->title }}</a>
                <span class="text-muted">— centang untuk menghapus</span>
            </label>
        </div>
        @endforeach
    </div>
    @endif
    <input type="file" name="attachments[]" id="inpAttachments" class="form-control-file" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
    <small class="text-muted">Bukti perhitungan/invoice — bisa lebih dari satu file, maks. 10 MB per file.</small>
</div>

@push('scripts')
<script>
$(function () {
    var R = window.ExpenseRows;
    var NOMINAL_AWAL = @json((object) $nominalPeriode);
    var manualVal = {}; // nilai manual per 'YYYY-MM' yang sudah diketik
    Object.keys(NOMINAL_AWAL).forEach(function (k) { manualVal[k] = String(NOMINAL_AWAL[k]); });
    var BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    function daftarBulan() {
        var a = $('#inpPeriodStart').val(), b = $('#inpPeriodEnd').val() || a;
        if (!/^\d{4}-\d{2}$/.test(a || '')) return [];
        if (!/^\d{4}-\d{2}$/.test(b || '')) b = a;
        var y = +a.slice(0, 4), m = +a.slice(5, 7), y2 = +b.slice(0, 4), m2 = +b.slice(5, 7), out = [];
        while ((y < y2 || (y === y2 && m <= m2)) && out.length <= 60) {
            out.push(y + '-' + (m < 10 ? '0' : '') + m);
            m++; if (m > 12) { m = 1; y++; }
        }
        return out;
    }
    function labelBulan(k) { return BULAN[+k.slice(5, 7) - 1] + ' ' + k.slice(0, 4); }
    function metode() { return $('input[name="period_method"]:checked').val() || 'equal'; }

    function renderPeriode() {
        var bulan = daftarBulan(), total = R.total(), n = bulan.length;
        var akhirSebelumAwal = $('#inpPeriodEnd').val() && $('#inpPeriodStart').val() && $('#inpPeriodEnd').val() < $('#inpPeriodStart').val();
        $('#grpPeriodMethod').toggle(n > 1);
        $('#periodPanel').toggle(n > 1);
        if (akhirSebelumAwal) { $('#periodHint').text('Periode akhir tidak boleh sebelum periode awal.').addClass('text-danger'); return; }
        $('#periodHint').removeClass('text-danger').text(n > 1
            ? n + ' bulan — ' + (metode() === 'manual' ? 'isi nominal tiap bulan (tidak boleh 0; jumlah harus sama dengan total).' : 'nilai dibagi rata, sisa rupiah ke bulan awal.')
            : 'Satu bulan: seluruh nilai dibebankan ke bulan itu.');
        if (n <= 1) return;

        var $body = $('#periodBody').empty(), jumlah = 0;
        if (metode() === 'equal') {
            var dasar = Math.floor(total / n), sisa = total - dasar * n;
            bulan.forEach(function (k, i) {
                var v = dasar + (i < sisa ? 1 : 0); jumlah += v;
                $body.append('<tr><td>' + labelBulan(k) + '</td><td class="text-right" style="font-variant-numeric:tabular-nums;">' + R.fmt(String(v)) + '</td></tr>');
            });
            $('#periodNote').text(total > 0 && total < n ? 'Nilai terlalu kecil untuk dibagi ke ' + n + ' bulan.' : '');
        } else {
            bulan.forEach(function (k) {
                var v = manualVal[k] || '';
                jumlah += R.unfmt(v);
                $body.append('<tr><td>' + labelBulan(k) + '</td><td><input type="text" class="form-control form-control-sm text-right p-amt" inputmode="numeric" data-k="' + k
                    + '" name="period_amounts[' + k + ']" value="' + R.fmt(v) + '" aria-label="Nominal ' + labelBulan(k) + '"></td></tr>');
            });
            var selisih = total - jumlah;
            $('#periodNote').text(selisih === 0 ? '' : (selisih > 0 ? 'Kurang ' : 'Lebih ') + R.fmt(String(Math.abs(selisih))) + ' dari total rincian.');
        }
        $('#periodSum').text(R.fmt(String(jumlah)) || '0');
    }

    $(document).on('input', '#periodBody .p-amt', function () {
        this.value = R.fmt(this.value);
        manualVal[$(this).data('k')] = this.value;
        var jumlah = 0;
        $('#periodBody .p-amt').each(function () { jumlah += R.unfmt(this.value); });
        $('#periodSum').text(R.fmt(String(jumlah)) || '0');
        var selisih = R.total() - jumlah;
        $('#periodNote').text(selisih === 0 ? '' : (selisih > 0 ? 'Kurang ' : 'Lebih ') + R.fmt(String(Math.abs(selisih))) + ' dari total rincian.');
    });
    $('#inpPeriodStart, #inpPeriodEnd').on('change', function () {
        if (!$('#inpPeriodEnd').val()) $('#inpPeriodEnd').val($('#inpPeriodStart').val());
        renderPeriode();
    });
    $('input[name="period_method"]').on('change', renderPeriode);

    R.init({ prefill: @json($baris), onChange: renderPeriode });

    if (window.TesysSearchableSelect) {
        TesysSearchableSelect.init('#selBeneficiary', { placeholder: '— Pilih Beneficiary —', searchPlaceholder: 'Cari Beneficiary...' });
    }

    /* Validasi sebelum kirim (server memvalidasi ulang). */
    $('#frmExpense').on('submit', function (e) {
        var pesan = R.validate();
        var bulan = daftarBulan(), total = R.total();
        if (!pesan && !bulan.length) pesan = 'Periode awal wajib diisi.';
        if (!pesan && $('#inpPeriodEnd').val() < $('#inpPeriodStart').val()) pesan = 'Periode akhir tidak boleh sebelum periode awal.';
        if (!pesan && bulan.length > 1 && metode() === 'equal' && total < bulan.length) pesan = 'Nilai terlalu kecil untuk dibagi ke ' + bulan.length + ' bulan.';
        if (!pesan && bulan.length > 1 && metode() === 'manual') {
            var jumlah = 0, adaNol = false;
            $('#periodBody .p-amt').each(function () { var v = R.unfmt(this.value); jumlah += v; if (v <= 0) adaNol = true; });
            if (adaNol) pesan = 'Nominal setiap bulan wajib diisi dan tidak boleh 0.';
            else if (jumlah !== total) pesan = 'Jumlah pembagian manual harus sama dengan total rincian (' + R.fmt(String(total)) + ').';
        }
        if (pesan) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'Periksa kembali', text: pesan });
        }
    });
});
</script>
@endpush
