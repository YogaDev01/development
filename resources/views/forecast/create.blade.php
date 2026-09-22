@extends('layouts.app')

@section('title', 'Tambah Forecast')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Tambah Forecast</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="{{ url('/forecast') }}">Forecast</a></li>
                <li class="breadcrumb-item active">Tambah</li>
            </ol>
        </div>

        <form action="{{ url('/forecast') }}" method="POST" id="frmForecast">
            @csrf

            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header"><h3 class="card-title">Informasi Forecast</h3></div>
                <div class="card-body">
                    <div class="row">
                        {{-- PO/Project TIDAK di sini. Satu forecast adalah satu
                             RENTANG WAKTU (mis. "Overhead Sep 2026 — Week 4") yang
                             isinya bisa menyentuh beberapa PO sekaligus, atau tidak
                             menyentuh PO sama sekali. FK-nya melekat di tiap baris
                             detail, sama seperti Source Fund. --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Date Start <span class="text-danger">*</span></label>
                                <input type="date" name="date_start" class="form-control @error('date_start') is-invalid @enderror" value="{{ old('date_start') }}" required>
                                @error('date_start')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="font-weight-600">Date End <span class="text-danger">*</span></label>
                                <input type="date" name="date_end" class="form-control @error('date_end') is-invalid @enderror" value="{{ old('date_end') }}" required>
                                @error('date_end')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>
                        {{-- Source Fund PINDAH ke baris detail: satu forecast bisa
                             menyentuh beberapa rekening, dan memaksanya satu rekening
                             di header membuat orang harus memecah forecast hanya karena
                             kolomnya ada di tempat yang salah. --}}
                        <div class="col-md-12">
                            <div class="form-group mb-0">
                                <label class="font-weight-600">Catatan</label>
                                <input type="text" name="notes" class="form-control" value="{{ old('notes') }}"
                                       maxlength="255" placeholder="Keterangan tambahan (opsional)" autocomplete="off">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title">Detail Forecast</h3>
                    <button type="button" class="btn btn-sm btn-primary ml-auto" id="btnAddDetail">
                        <i class="fas fa-plus mr-1"></i> Tambah Baris
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:1%;">No</th>
                                    <th scope="col">Description</th>
                                    <th scope="col" style="width:22%;">PO / Project</th>
                                    <th scope="col" style="width:10%;">Tipe</th>
                                    <th scope="col" style="width:18%;">Source Fund</th>
                                    <th scope="col" style="width:16%;">Amount</th>
                                    <th scope="col" style="width:5%;"></th>
                                </tr>
                            </thead>
                            <tbody id="detailBody"></tbody>
                            <tfoot>
                                <tr style="background:var(--surface-2);">
                                    <th colspan="5" class="text-right">Rencana Bersih (Masuk &minus; Keluar)</th>
                                    <th id="detailTotal">0</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <a href="{{ url('/forecast') }}" class="btn btn-secondary"><i class="fas fa-times mr-1"></i> Batal</a>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i> Simpan</button>
                </div>
            </div>
        </form>

    </div>
</div>

@endsection

@push('scripts')
<script>
$(function () {
    var detailIdx = 0;

    // Opsi rekening dirender sekali dari server, lalu dipakai ulang untuk
    // setiap baris yang ditambahkan.
    var opsiRekening = @json($sourceFunds->map(fn ($sf) => ['id' => $sf->id, 'name' => $sf->name]))
        .map(function (sf) { return '<option value="' + sf.id + '">' + sf.name + '</option>'; })
        .join('');

    // Opsi PO juga dirender sekali lalu dipakai ulang per baris. Sengaja boleh
    // kosong: rencana overhead memang tidak menunjuk PO mana pun.
    var opsiPo = @json($projects->map(fn ($p) => ['id' => $p->id, 'label' => ($p->code ?? '-') . ' — ' . ($p->name ?? '-')]))
        .map(function (po) { return '<option value="' + po.id + '">' + po.label + '</option>'; })
        .join('');

    function fmtRp(v) {
        v = (v || '').toString().replace(/\D/g, '');
        return v ? parseInt(v).toLocaleString('id-ID') : '';
    }

    function makeDetailRow(i) {
        return '<tr class="detail-row">' +
            '<td class="text-center align-middle row-no"></td>' +
            '<td><input type="text" name="details[' + i + '][description]" class="form-control form-control-sm" required></td>' +
            '<td>' +
                '<select name="details[' + i + '][project_id]" class="form-control form-control-sm">' +
                    '<option value="">— Tanpa PO —</option>' + opsiPo +
                '</select>' +
            '</td>' +
            '<td>' +
                '<select name="details[' + i + '][type]" class="form-control form-control-sm detail-type">' +
                    '<option value="IN">Masuk</option>' +
                    '<option value="OUT">Keluar</option>' +
                '</select>' +
            '</td>' +
            '<td>' +
                '<select name="details[' + i + '][source_fund_id]" class="form-control form-control-sm">' +
                    '<option value="">— Pilih Rekening —</option>' + opsiRekening +
                '</select>' +
            '</td>' +
            '<td><input type="text" name="details[' + i + '][amount]" class="form-control form-control-sm text-right detail-amount" placeholder="0" required></td>' +
            '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-detail"><i class="fas fa-trash"></i></button></td>' +
        '</tr>';
    }

    function renumber() {
        $('.detail-row').each(function (i) { $(this).find('.row-no').text(i + 1); });
    }

    /* Rencana bersih = Masuk − Keluar. Menjumlah semuanya begitu saja akan
       membuat rencana PENGELUARAN menaikkan nilai forecast — angka yang tidak
       berarti apa pun. Rumus ini cerminan Forecast::syncAmounts(). */
    function recalcTotal() {
        var total = 0;
        $('.detail-row').each(function () {
            var nilai = parseInt(($('.detail-amount', this).val() || '0').replace(/\./g, '')) || 0;
            total += ($('.detail-type', this).val() === 'OUT') ? -nilai : nilai;
        });
        $('#detailTotal')
            .text(total.toLocaleString('id-ID'))
            .toggleClass('text-danger', total < 0);
    }

    $(document).on('change', '.detail-type', recalcTotal);

    $('#btnAddDetail').on('click', function () {
        $('#detailBody').append(makeDetailRow(detailIdx++));
        renumber();
    });

    $(document).on('click', '.btn-remove-detail', function () {
        if ($('.detail-row').length <= 1) {
            Swal.fire({ icon: 'warning', title: 'Minimal 1 baris detail', confirmButtonColor: '#92232b' });
            return;
        }
        $(this).closest('tr').remove();
        renumber();
        recalcTotal();
    });

    $(document).on('input', '.detail-amount', function () {
        this.value = fmtRp(this.value);
        recalcTotal();
    });

    $('#btnAddDetail').trigger('click');

    $('#frmForecast').on('submit', function () {
        $('.detail-amount').each(function () { this.value = this.value.replace(/\./g, ''); });
    });
});
</script>
@endpush
