@extends('layouts.app')

@section('title', 'Buat Batch Baru')

@section('content')

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <div class="d-flex align-items-center">
                    <a href="{{ url('/daily-transaction') }}" class="btn btn-secondary btn-sm mr-3">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="m-0 font-weight-bold">Buat Batch Baru</h1>
                        <small class="text-muted">Form pembuatan batch pembayaran Treasury</small>
                    </div>
                </div>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right mb-0">
                    <li class="breadcrumb-item"><a href="#">Finance</a></li>
                    <li class="breadcrumb-item"><a href="{{ url('/daily-transaction') }}">Treasury</a></li>
                    <li class="breadcrumb-item active">Buat Batch</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">

        <form action="{{ url('/daily-transaction') }}" method="POST" id="frmCreateBatch">
            @csrf

            <div class="row">

                <!-- INFORMASI BATCH -->
                <div class="col-md-4">
                    <div class="card card-success card-outline shadow-sm">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i> Informasi Batch</h3>
                        </div>
                        <div class="card-body">

                            @if($errors->any())
                            <div class="alert alert-danger rounded-0">
                                <ul class="mb-0 mt-0 pl-3">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            @endif

                            <div class="form-group">
                                <label class="font-weight-600">Deskripsi</label>
                                <input type="text" name="description" class="form-control form-control-sm"
                                       style="text-transform:uppercase;"
                                       value="{{ old('description') }}"
                                       placeholder="mis. Pembayaran mingguan CR PLTS...">
                            </div>

                            <div class="form-group">
                                <label class="font-weight-600">Source Fund</label>
                                <select name="source_fund_id" id="selSourceFund" class="form-control form-control-sm">
                                    <option value="">— Pilih Source Fund —</option>
                                    @foreach($sourceFunds as $sf)
                                        <option value="{{ $sf->id }}" {{ old('source_fund_id') == $sf->id ? 'selected' : '' }}>
                                            {{ $sf->name }} — {{ $sf->bank->name ?? '-' }} ({{ $sf->no_account ?? '-' }})
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted d-block mt-1">
                                    Belum ada di daftar? <a href="{{ url('/source-fund') }}" target="_blank">Tambah Source Fund</a>.
                                </small>
                            </div>

                            <div class="form-group">
                                <label class="font-weight-600">Charge (Biaya Admin Transfer)</label>
                                <input type="text" name="charge" id="inpCharge" class="form-control form-control-sm text-right"
                                       value="{{ old('charge', 0) }}" placeholder="0">
                            </div>

                            <div class="form-group mb-0">
                                <label class="font-weight-600">Notes (opsional)</label>
                                <textarea name="notes" class="form-control form-control-sm" rows="2"
                                          placeholder="Catatan batch...">{{ old('notes') }}</textarea>
                            </div>

                        </div>
                    </div>

                    <!-- KALKULASI (VISUAL SAJA) -->
                    <div class="card shadow-sm">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between align-items-baseline mb-1">
                                <span class="text-muted" style="font-size:12px;">Nilai Batch (CR terpilih)</span>
                                <strong id="calcBatchValue" style="font-size:13px;">Rp 0</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-baseline mb-1">
                                <span class="text-muted" style="font-size:12px;">Charge</span>
                                <strong id="calcCharge" style="font-size:13px;">Rp 0</strong>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between align-items-baseline">
                                <span class="font-weight-600" style="font-size:13px;">Total Ditransfer</span>
                                <strong id="calcGrandTotal" class="text-success" style="font-size:16px;">Rp 0</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PILIH CR -->
                <div class="col-md-8">
                    <div class="card card-primary card-outline shadow-sm">
                        <div class="card-header d-flex align-items-center">
                            <h3 class="card-title"><i class="fas fa-list-check mr-1"></i> Pilih CR</h3>
                            <div class="ml-auto d-flex" style="gap:6px;">
                                <button type="button" class="btn btn-xs btn-secondary" id="btnSelectAll">Semua</button>
                                <button type="button" class="btn btn-xs btn-secondary" id="btnClearAll">Reset</button>
                            </div>
                        </div>
                        <div class="card-body">
                            @if($availableCRs->isEmpty())
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                Tidak ada CR berstatus VERIFIED yang tersedia.
                            </div>
                            @else
                            <div id="crCheckList" style="max-height:600px;overflow-y:auto;border:1px solid #dee2e6;border-radius:6px;">
                                @foreach($availableCRs as $cr)
                                <label class="cr-check-item d-flex align-items-start p-2 mb-0"
                                       style="border-bottom:1px solid #f0f0f0;cursor:pointer;gap:10px;">
                                    <input type="checkbox" name="cr_ids[]" value="{{ $cr->id }}"
                                           class="cr-checkbox mt-1"
                                           style="width:16px;height:16px;flex-shrink:0;accent-color:#28a745;">
                                    <div style="flex:1;min-width:0;">
                                        <div style="font-size:12px;font-weight:700;">{{ $cr->code }}</div>
                                        <div style="font-size:12px;color:#374151;">{{ Str::limit($cr->description, 60) }}</div>
                                        <div style="font-size:11px;color:#6B7280;">
                                            {{ $cr->type->name ?? '-' }} · {{ $cr->cluster->name ?? '-' }}
                                        </div>
                                        <div style="font-size:12px;font-weight:700;color:#2563EB;">
                                            {{ $cr->amount_formatted }}
                                        </div>
                                    </div>
                                </label>
                                @endforeach
                            </div>
                            <small class="text-muted mt-2 d-block">
                                <span id="selectedCount">0</span> CR dipilih ·
                                Total: <strong id="selectedTotal">Rp 0</strong>
                            </small>
                            @endif
                        </div>
                        @if($availableCRs->isNotEmpty())
                        <div class="card-footer">
                            <button type="submit" class="btn btn-success btn-block">
                                <i class="fas fa-plus-circle mr-1"></i> Buat Batch
                            </button>
                        </div>
                        @endif
                    </div>
                </div>

            </div>
        </form>

    </div>
</div>

<textarea id="crAmountsHolder" style="display:none;">{{ json_encode($availableCRs->pluck('amount', 'id')) }}</textarea>

@endsection

@push('styles')
<style>
.font-weight-600 { font-weight:600; }
.cr-check-item:hover { background:#f8f9fa; }
.cr-check-item.selected { background:#EFF6FF; }
.btn-xs { padding:2px 8px; font-size:11px; border-radius:4px; }
</style>
@endpush

@push('scripts')
<script>
$(function () {

    var crAmounts = JSON.parse(document.getElementById('crAmountsHolder').value);

    function fmtRp(v) {
        v = (v || '').toString().replace(/[^\d]/g, '');
        return v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function updateCalc() {
        var count = 0, total = 0;
        $('.cr-checkbox:checked').each(function () {
            count++;
            total += crAmounts[$(this).val()] || 0;
        });
        var charge = parseInt(($('#inpCharge').val() || '0').replace(/\./g, '')) || 0;

        $('#selectedCount').text(count);
        $('#selectedTotal').text('Rp ' + total.toLocaleString('id-ID'));
        $('#calcBatchValue').text('Rp ' + total.toLocaleString('id-ID'));
        $('#calcCharge').text('Rp ' + charge.toLocaleString('id-ID'));
        $('#calcGrandTotal').text('Rp ' + (total + charge).toLocaleString('id-ID'));
    }

    $('#inpCharge').on('input', function () {
        this.value = fmtRp(this.value);
        updateCalc();
    });

    $(document).on('change', '.cr-checkbox', function () {
        $(this).closest('.cr-check-item').toggleClass('selected', $(this).is(':checked'));
        updateCalc();
    });

    $('#btnSelectAll').on('click', function () {
        $('.cr-checkbox').prop('checked', true).closest('.cr-check-item').addClass('selected');
        updateCalc();
    });

    $('#btnClearAll').on('click', function () {
        $('.cr-checkbox').prop('checked', false).closest('.cr-check-item').removeClass('selected');
        updateCalc();
    });

    $('#frmCreateBatch').on('submit', function (e) {
        e.preventDefault();
        var form = this;
        var count = $('.cr-checkbox:checked').length;
        if (count === 0) {
            Swal.fire({ icon: 'warning', title: 'Pilih minimal 1 CR', confirmButtonColor: '#F5A623' });
            return;
        }
        Swal.fire({
            title: 'Buat Batch?',
            text: count + ' CR akan dimasukkan ke dalam batch baru.',
            icon: 'question', showCancelButton: true,
            confirmButtonColor: '#28a745', cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Buat', cancelButtonText: 'Batal'
        }).then(function (r) { if (r.isConfirmed) form.submit(); });
    });

    updateCalc();

});
</script>
@endpush