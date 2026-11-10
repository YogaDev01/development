{{--
    Tabel rincian Expense (form Expense & form settlement). Kolom: Akun, Pembebanan,
    Deskripsi, Harga Satuan, Qty, Total. Butuh $accounts, $projects, $eligibleProjects,
    $annualYears, $costCenters (CostRegisterController::detailOptions()).

    Pembebanan (sama dengan aturan server CostRegisterController::normalizeDetails()):
      - akun Variable Cost  : hanya satu PO.
      - Fixed Cost/Overhead : satu PO, seluruh PO per tahun, PO terpilih (min. 2), atau Cost Center.
    Seluruh PO per tahun & PO terpilih dibagi proporsional nilai PO, dihitung ulang
    di laporan (App\Support\ExpenseAllocation).

    JS: ExpenseRows.init({ prefill: [...], onChange: function (total) {} })
--}}
@php $totalLabel = $totalLabel ?? 'Total Nominal'; @endphp
<div class="table-responsive">
    <table class="table table-bordered table-sm mb-1" style="table-layout:fixed;min-width:1040px;">
        <thead style="background:var(--surface-2);">
            <tr>
                <th style="width:44px;">No</th>
                <th style="width:210px;">Akun</th>
                <th style="width:280px;">Pembebanan</th>
                <th>Deskripsi</th>
                <th class="text-right" style="width:130px;">Harga Satuan</th>
                <th class="text-right" style="width:72px;">Qty</th>
                <th class="text-right" style="width:130px;">Total</th>
                <th style="width:48px;"><span class="sr-only">Aksi</span></th>
            </tr>
        </thead>
        <tbody id="detailBody"></tbody>
        <tfoot>
            <tr style="background:var(--surface-2);">
                <th colspan="6" class="text-right">{{ $totalLabel }}</th>
                <th class="text-right" id="detailGrand">0</th>
                <th></th>
            </tr>
        </tfoot>
    </table>
</div>
<button type="button" class="btn btn-sm btn-secondary" id="btnAddDetail"><i class="fas fa-plus mr-1"></i> Tambah Rincian</button>
<small class="text-muted d-block mt-1">
    Variable Cost wajib ke satu PO. Fixed Cost / Overhead bisa ke satu PO, seluruh PO dalam satu tahun, beberapa PO terpilih
    (dibagi proporsional nilai PO), atau Cost Center.
</small>

<template id="tplAccountOptions">
    <option value="">— Akun —</option>
    @foreach($accounts->groupBy('category') as $kategori => $akun)
    <optgroup label="{{ $kategori }}">
        @foreach($akun as $a)
        <option value="{{ $a->id }}" data-category="{{ $a->category }}">{{ $a->name }}</option>
        @endforeach
    </optgroup>
    @endforeach
</template>

<template id="tplTargetOptions">
    <option value="">— Pilih Pembebanan —</option>
    <optgroup label="Satu PO">
        @foreach($projects as $p)
        <option value="po:{{ $p->id }}" data-label="PO {{ $p->code }}">{{ $p->code }} — {{ $p->customer->name ?? '-' }} — {{ Str::limit($p->name, 40) }}</option>
        @endforeach
    </optgroup>
    <optgroup label="Seluruh PO per tahun (Fixed Cost / Overhead)">
        @forelse($annualYears as $th)
        <option value="annual:{{ $th['year'] }}" class="opt-fo"
                data-label="Semua PO {{ $th['year'] }}">Semua PO tahun {{ $th['year'] }} — {{ $th['count'] }} PO · @rupiah($th['value'])</option>
        @empty
        @endforelse
    </optgroup>
    <optgroup label="PO terpilih (Fixed Cost / Overhead)">
        <option value="selected" class="opt-fo" data-label="Beberapa PO terpilih">Beberapa PO terpilih (proporsional nilai PO)…</option>
    </optgroup>
    @if($costCenters->isNotEmpty())
    <optgroup label="Cost Center (Fixed Cost / Overhead)">
        @foreach($costCenters as $cc)
        <option value="cc:{{ $cc->id }}" class="opt-fo" data-label="CC {{ $cc->code }}">{{ $cc->code }} — {{ $cc->name }}</option>
        @endforeach
    </optgroup>
    @endif
</template>

<template id="tplSelectedOptions">
    @foreach($eligibleProjects as $p)
    <option value="{{ $p->id }}" data-label="{{ $p->code }}">{{ $p->code }} — {{ $p->customer->name ?? '-' }} · @rupiah($p->value)</option>
    @endforeach
</template>

@push('scripts')
<script>
window.ExpenseRows = (function () {
    'use strict';

    function fmt(v) {
        v = (v || '').toString().replace(/\D/g, '');
        return v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    function unfmt(v) { return parseInt((v || '0').toString().replace(/\D/g, ''), 10) || 0; }

    var idx = 0, onChange = function () {};

    function total() {
        var grand = 0;
        $('#detailBody tr').each(function () {
            grand += (parseInt($(this).find('.d-qty').val(), 10) || 0) * unfmt($(this).find('.d-price').val());
        });
        return grand;
    }

    function recalc() {
        $('#detailBody tr').each(function (i) {
            var tot = (parseInt($(this).find('.d-qty').val(), 10) || 0) * unfmt($(this).find('.d-price').val());
            $(this).find('.d-total').text(fmt(String(tot)) || '0');
            $(this).find('.d-no').text(i + 1);
        });
        var g = total();
        $('#detailGrand').text(fmt(String(g)) || '0');
        onChange(g);
    }

    /* Variable Cost: hanya "Satu PO". Pilihan lain dinonaktifkan (TesysSearchableSelect
       melewati opsi disabled) dan direset bila sedang terpilih. */
    function applyCategory($tr) {
        var cat = $tr.find('.d-acc option:selected').data('category') || '';
        var hanyaPo = cat === 'Variable Cost';
        var $t = $tr.find('.d-target');
        $t.find('option.opt-fo').prop('disabled', hanyaPo);
        if (hanyaPo && $t.find('option:selected').hasClass('opt-fo')) $t.val('');
        $t.trigger('tss:sync');
        toggleSelected($tr);
    }

    /* Multi-select PO hanya aktif saat pembebanan = "Beberapa PO terpilih";
       saat tersembunyi ia dinonaktifkan supaya tidak ikut terkirim. */
    function toggleSelected($tr) {
        var aktif = $tr.find('.d-target').val() === 'selected';
        $tr.find('.d-sel-wrap').toggle(aktif);
        $tr.find('.d-sel').prop('disabled', !aktif);
    }

    function addRow(d) {
        d = d || {};
        var i = idx++;
        var $tr = $('<tr>' +
            '<td class="d-no text-center"></td>' +
            '<td><select name="details[' + i + '][account_id]" class="form-control form-control-sm d-acc" aria-label="Akun"></select></td>' +
            '<td><select name="details[' + i + '][target]" class="form-control form-control-sm d-target" aria-label="Pembebanan"></select>' +
                '<div class="d-sel-wrap mt-1" style="display:none;">' +
                '<select name="details[' + i + '][project_ids][]" class="form-control form-control-sm d-sel" multiple aria-label="PO terpilih" disabled></select>' +
                '<small class="text-muted">Minimal 2 PO yang sudah disetujui.</small></div></td>' +
            '<td><input type="text" name="details[' + i + '][description]" class="form-control form-control-sm d-desc" maxlength="500" aria-label="Deskripsi"></td>' +
            '<td><input type="text" name="details[' + i + '][unit_price]" class="form-control form-control-sm text-right d-price" placeholder="0" inputmode="numeric" aria-label="Harga satuan"></td>' +
            '<td><input type="number" name="details[' + i + '][qty]" class="form-control form-control-sm text-right d-qty" min="1" value="1" aria-label="Qty"></td>' +
            '<td class="text-right d-total">0</td>' +
            '<td class="text-center"><button type="button" class="btn btn-xs btn-danger btn-del-detail" aria-label="Hapus baris"><i class="fas fa-trash"></i></button></td>' +
            '</tr>');
        $tr.find('.d-acc').html(document.getElementById('tplAccountOptions').innerHTML);
        $tr.find('.d-target').html(document.getElementById('tplTargetOptions').innerHTML);
        $tr.find('.d-sel').html(document.getElementById('tplSelectedOptions').innerHTML);
        $('#detailBody').append($tr);

        $tr.find('.d-acc').val(d.account_id ? String(d.account_id) : '');
        $tr.find('.d-target').val(d.target || '');
        if ($tr.find('.d-target').val() === null) $tr.find('.d-target').val('');
        (d.project_ids || []).forEach(function (pid) {
            $tr.find('.d-sel option[value="' + pid + '"]').prop('selected', true);
        });
        $tr.find('.d-desc').val(d.description || '');
        $tr.find('.d-qty').val(d.qty || 1);
        $tr.find('.d-price').val(fmt(String(d.unit_price || '')));

        if (window.TesysSearchableSelect) {
            TesysSearchableSelect.initOne($tr.find('.d-target'), { placeholder: '— Pilih Pembebanan —', searchPlaceholder: 'Cari PO / tahun / Cost Center...' });
            TesysSearchableSelect.initOne($tr.find('.d-sel'), { placeholder: 'Pilih PO...', searchPlaceholder: 'Cari PO...' });
        }
        applyCategory($tr);
        recalc();
    }

    /* Validasi sisi klien (server tetap memvalidasi ulang). Mengembalikan pesan atau ''. */
    function validate() {
        var pesan = '';
        if (!$('#detailBody tr').length) return 'Tambahkan minimal satu rincian.';
        $('#detailBody tr').each(function (i) {
            var no = i + 1, $tr = $(this);
            if (!$tr.find('.d-acc').val()) { pesan = 'Rincian #' + no + ': pilih akun.'; return false; }
            var t = $tr.find('.d-target').val();
            if (!t) { pesan = 'Rincian #' + no + ': pilih pembebanan.'; return false; }
            if (t === 'selected' && ($tr.find('.d-sel').val() || []).length < 2) { pesan = 'Rincian #' + no + ': pilih minimal 2 PO.'; return false; }
            if (!$.trim($tr.find('.d-desc').val())) { pesan = 'Rincian #' + no + ': deskripsi wajib diisi.'; return false; }
            if (unfmt($tr.find('.d-price').val()) <= 0) { pesan = 'Rincian #' + no + ': harga satuan harus lebih dari 0.'; return false; }
            if ((parseInt($tr.find('.d-qty').val(), 10) || 0) < 1) { pesan = 'Rincian #' + no + ': qty minimal 1.'; return false; }
        });
        return pesan;
    }

    function init(opts) {
        opts = opts || {};
        if (typeof opts.onChange === 'function') onChange = opts.onChange;

        $('#btnAddDetail').on('click', function () { addRow(); });
        $(document).on('click', '.btn-del-detail', function () {
            if ($('#detailBody tr').length > 1) { $(this).closest('tr').remove(); recalc(); }
        });
        $(document).on('change', '#detailBody .d-acc', function () { applyCategory($(this).closest('tr')); });
        $(document).on('change', '#detailBody .d-target', function () { toggleSelected($(this).closest('tr')); });
        $(document).on('input', '#detailBody .d-qty', recalc);
        $(document).on('input', '#detailBody .d-price', function () { this.value = fmt(this.value); recalc(); });

        var prefill = opts.prefill || [];
        if (prefill.length) { prefill.forEach(addRow); } else { addRow(); }
    }

    return { init: init, total: total, validate: validate, fmt: fmt, unfmt: unfmt };
})();
</script>
@endpush
