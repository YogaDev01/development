{{-- Form dokumen Cash Flow manual — dipakai bersama oleh create & edit.
     Satu berkas supaya aturan baris rincian tidak pernah berbeda antara
     menambah dan mengubah. --}}

@php
    /** @var \App\Models\CashFlow|null $cashFlow */
    $isEdit = isset($cashFlow) && $cashFlow->exists;
    $rows   = old('details', $isEdit
        ? $cashFlow->details->map(fn ($d) => [
            'direction'        => $d->direction,
            'category'         => $d->category,
            'description'      => $d->description,
            'amount'           => number_format($d->amount, 0, ',', '.'),
            'reference_number' => $d->reference_number,
        ])->all()
        : []);
@endphp

@if($errors->any())
<div class="alert alert-danger rounded-0 shadow-sm">
    <strong>Periksa kembali isian berikut:</strong>
    <ul class="mb-0 mt-1" style="font-size:12px;">
        @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="card card-primary card-outline shadow-sm">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i> Informasi Dokumen</h3>
    </div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group col-lg-3 col-md-6">
                <label class="font-weight-600">Tanggal <span class="text-danger">*</span></label>
                <input type="date" name="date" class="form-control form-control-sm"
                       value="{{ old('date', $isEdit ? $cashFlow->date->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
            </div>

            <div class="form-group col-lg-3 col-md-6">
                <label class="font-weight-600">Rekening / Sumber Dana</label>
                <select name="source_fund_id" class="form-control form-control-sm">
                    <option value="">— Pilih Rekening —</option>
                    @foreach($sourceFunds as $sf)
                        <option value="{{ $sf->id }}" @selected((string) old('source_fund_id', $isEdit ? $cashFlow->source_fund_id : '') === (string) $sf->id)>{{ $sf->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group col-lg-3 col-md-6">
                <label class="font-weight-600">No. Referensi</label>
                <input type="text" name="reference_number" class="form-control form-control-sm"
                       value="{{ old('reference_number', $isEdit ? $cashFlow->reference_number : '') }}"
                       placeholder="Mis. no. rekening koran / bukti transfer">
            </div>

            <div class="form-group col-lg-3 col-md-6">
                <label class="font-weight-600">Deskripsi <span class="text-danger">*</span></label>
                <input type="text" name="description" class="form-control form-control-sm"
                       value="{{ old('description', $isEdit ? $cashFlow->description : '') }}"
                       placeholder="Ringkasan dokumen" required>
            </div>

            <div class="form-group col-12 mb-0">
                <label class="font-weight-600">Catatan</label>
                <textarea name="notes" rows="2" class="form-control form-control-sm"
                          placeholder="Penjelasan tambahan...">{{ old('notes', $isEdit ? $cashFlow->notes : '') }}</textarea>
            </div>
        </div>
    </div>
</div>

<div class="card card-primary card-outline shadow-sm">
    <div class="card-header d-flex align-items-center flex-wrap" style="gap:6px;">
        <h3 class="card-title"><i class="fas fa-list-ul mr-1"></i> Rincian Pergerakan Kas</h3>
        <div class="ml-auto">
            <button type="button" class="btn btn-sm btn-primary" id="btnAddRow">
                <i class="fas fa-plus mr-1"></i> Tambah Baris
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead class="bg-light">
                    <tr>
                        <th scope="col" style="width:4%;" class="text-center">No</th>
                        <th scope="col" style="width:12%;">Arah</th>
                        <th scope="col" style="width:20%;">Kategori</th>
                        <th scope="col" style="width:30%;">Deskripsi</th>
                        <th scope="col" style="width:17%;" class="text-right">Nominal</th>
                        <th scope="col" style="width:12%;">Referensi</th>
                        <th scope="col" style="width:5%;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="detailBody"></tbody>
            </table>
        </div>
    </div>
    <div class="card-footer d-flex flex-wrap justify-content-end" style="gap:24px;">
        <div><span class="text-muted" style="font-size:11px;">Cash In</span>
            <strong id="sumIn" class="ml-2 text-success">0</strong></div>
        <div><span class="text-muted" style="font-size:11px;">Cash Out</span>
            <strong id="sumOut" class="ml-2 text-danger">0</strong></div>
        <div><span class="text-muted" style="font-size:11px;">Net Cash Flow</span>
            <strong id="sumNet" class="ml-2">0</strong></div>
    </div>
</div>

@push('scripts')
<script>
$(function () {
    var categories = @json($categories);
    var initialRows = @json(array_values((array) $rows));

    function rp(n) { return (n || 0).toLocaleString('id-ID'); }
    function fmt(v) {
        var digits = (v || '').toString().replace(/\D/g, '');
        return digits ? parseInt(digits, 10).toLocaleString('id-ID') : '';
    }

    function optionsHtml(selected) {
        var html = '';
        Object.keys(categories).forEach(function (key) {
            html += '<option value="' + key + '"' + (key === selected ? ' selected' : '') + '>'
                  + categories[key] + '</option>';
        });
        return html;
    }

    function rowHtml(i, row) {
        row = row || {};
        return '' +
        '<tr class="detail-row">' +
            '<td class="text-center row-no">' + (i + 1) + '</td>' +
            '<td>' +
                '<select name="details[' + i + '][direction]" class="form-control form-control-sm inp-direction" required>' +
                    '<option value="IN"' + (row.direction === 'IN' ? ' selected' : '') + '>Cash In</option>' +
                    '<option value="OUT"' + (row.direction === 'OUT' ? ' selected' : '') + '>Cash Out</option>' +
                '</select>' +
            '</td>' +
            '<td>' +
                '<select name="details[' + i + '][category]" class="form-control form-control-sm" required>' +
                    optionsHtml(row.category) +
                '</select>' +
            '</td>' +
            '<td><input type="text" name="details[' + i + '][description]" class="form-control form-control-sm" ' +
                'value="' + (row.description || '').replace(/"/g, '&quot;') + '" required></td>' +
            '<td><input type="text" name="details[' + i + '][amount]" class="form-control form-control-sm text-right inp-amount" ' +
                'value="' + (row.amount || '') + '" placeholder="0" required></td>' +
            '<td><input type="text" name="details[' + i + '][reference_number]" class="form-control form-control-sm" ' +
                'value="' + (row.reference_number || '').replace(/"/g, '&quot;') + '"></td>' +
            '<td class="text-center">' +
                '<button type="button" class="btn btn-sm btn-outline-danger btn-remove" title="Hapus baris">' +
                    '<i class="fas fa-trash"></i></button>' +
            '</td>' +
        '</tr>';
    }

    function renumber() {
        $('#detailBody .detail-row').each(function (i) {
            $(this).find('.row-no').text(i + 1);
            $(this).find('select, input').each(function () {
                var name = $(this).attr('name');
                if (name) $(this).attr('name', name.replace(/details\[\d+\]/, 'details[' + i + ']'));
            });
        });
    }

    function recalc() {
        var totalIn = 0, totalOut = 0;
        $('#detailBody .detail-row').each(function () {
            var amount = parseInt(($('.inp-amount', this).val() || '0').replace(/\./g, ''), 10) || 0;
            if ($('.inp-direction', this).val() === 'IN') totalIn += amount; else totalOut += amount;
        });

        // Net Cash Flow = Total Cash Inflow − Total Cash Outflow (M-01).
        var net = totalIn - totalOut;
        $('#sumIn').text(rp(totalIn));
        $('#sumOut').text(rp(totalOut));
        $('#sumNet').text(rp(net)).toggleClass('text-success', net >= 0).toggleClass('text-danger', net < 0);
    }

    function addRow(row) {
        $('#detailBody').append(rowHtml($('#detailBody .detail-row').length, row));
        recalc();
    }

    $('#btnAddRow').on('click', function () { addRow(); });

    $('#detailBody').on('click', '.btn-remove', function () {
        // Selalu sisakan satu baris — dokumen tanpa rincian ditolak backend,
        // dan tabel kosong tanpa petunjuk membingungkan.
        if ($('#detailBody .detail-row').length <= 1) {
            $(this).closest('tr').find('input').val('');
            recalc();
            return;
        }
        $(this).closest('tr').remove();
        renumber();
        recalc();
    });

    $('#detailBody').on('input', '.inp-amount', function () {
        this.value = fmt(this.value);
        recalc();
    });
    $('#detailBody').on('change', '.inp-direction', recalc);

    if (initialRows.length) {
        initialRows.forEach(function (row) { addRow(row); });
    } else {
        addRow();
    }
    recalc();
});
</script>
@endpush
