@extends('layouts.app')

@section('title', 'Buat Batch Baru')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ url('/daily-transaction') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Buat Batch Baru</h1>
                    <small class="text-muted">Form pembuatan batch pembayaran Treasury</small>
                </div>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item"><a href="{{ url('/daily-transaction') }}">Treasury</a></li>
                <li class="breadcrumb-item active">Buat Batch</li>
            </ol>
        </div>

        @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 mt-0 pl-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-header py-2" style="border-bottom:none;padding-bottom:0;">
                <div class="tt-tabs" id="typeToggle">
                    <label class="type-toggle-btn active" data-type="CASH_OUT">
                        <input type="radio" name="type_radio" value="CASH_OUT" checked> Cash Out
                    </label>
                    <label class="type-toggle-btn" data-type="TOP_UP">
                        <input type="radio" name="type_radio" value="TOP_UP"> Top Up
                    </label>
                </div>
            </div>

            <form action="{{ url('/daily-transaction') }}" method="POST" id="frmCreateBatch">
                @csrf
                <input type="hidden" name="type" id="inpType" value="CASH_OUT">

                <div class="card-body">
                    <small class="text-muted d-block mb-3">
                        <strong>Cash Out</strong> = pembayaran Cost Request ke pihak ketiga.
                        <strong>Top Up</strong> = transfer dana dari Main Account ke rekening operasional (tanpa CR).
                    </small>

                    <!-- ================= CASH OUT ================= -->
                    <div id="sectionCashOut" class="row batch-two-col form-clean">

                        <!-- INFORMASI BATCH + KALKULASI -->
                        <div class="col-md-4 batch-col-left">

                            <div class="form-group">
                                <label class="font-weight-600">Deskripsi <span class="text-danger">*</span></label>
                                <input type="text" name="description" class="form-control form-control-sm" required
                                       style="text-transform:uppercase;"
                                       value="{{ old('description') }}"
                                       placeholder="mis. Pembayaran mingguan CR PLTS...">
                            </div>

                            <div class="form-group">
                                <label class="font-weight-600">Source Fund <span class="text-danger">*</span></label>
                                <select name="source_fund_id" id="selSourceFund" class="form-control form-control-sm" required>
                                    <option value="">— Pilih Source Fund —</option>
                                    @foreach($operationalSourceFunds as $sf)
                                        <option value="{{ $sf->id }}" {{ old('source_fund_id') == $sf->id ? 'selected' : '' }}>
                                            {{ $sf->name }} — {{ $sf->bank->name ?? '-' }} ({{ $sf->no_account ?? '-' }})
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted d-block mt-1">
                                    Belum ada di daftar? <a href="{{ url('/source-fund') }}" target="_blank">Tambah Source Fund</a>.
                                </small>
                            </div>

                            <small class="text-muted d-block mb-2">
                                <i class="fas fa-info-circle mr-1"></i> Charge (biaya admin transfer) diisi nanti saat batch ditandai PAID.
                            </small>

                            <div class="form-group">
                                <label class="font-weight-600">Notes (opsional)</label>
                                <textarea name="notes" class="form-control form-control-sm" rows="2"
                                          placeholder="Catatan batch...">{{ old('notes') }}</textarea>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between align-items-baseline mb-2">
                                <span class="calc-caption">Nilai Batch (CR terpilih)</span>
                                <strong id="calcBatchValue" style="font-size:13px;">0</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-baseline">
                                <span class="calc-caption">Total Nilai Batch</span>
                                <strong id="calcGrandTotal" class="text-success" style="font-size:16px;">0</strong>
                            </div>

                        </div>

                        <!-- PILIH CR -->
                        <div class="col-md-8 batch-col-right">
                            <div class="d-flex align-items-center mb-2">
                                <h3 class="cr-section-title mb-0"><i class="fas fa-tasks mr-1"></i> Pilih CR</h3>
                                <div class="ml-auto d-flex" style="gap:6px;">
                                    <button type="button" class="btn btn-xs btn-secondary" id="btnSelectAll">Semua</button>
                                    <button type="button" class="btn btn-xs btn-secondary" id="btnClearAll">Reset</button>
                                </div>
                            </div>

                            @if($availableCRs->isEmpty() && $availableRegisters->isEmpty() && $availableSecondPayments->isEmpty())
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                Tidak ada CR berstatus APPROVED, Cost Register berstatus SUBMITTED,
                                maupun Second Payment yang menunggu pembayaran.
                            </div>
                            @else
                            @if($availableCRs->isEmpty())
                            <div class="alert alert-info py-2 mb-2" style="font-size:12px;">
                                Tidak ada CR APPROVED — batch ini akan berisi Cost Register / Second Payment saja.
                            </div>
                            @endif
                            @php
                                $crGroups = $availableCRs->groupBy('pm_internal_name')->sortKeys();
                            @endphp
                            <div id="crCheckList" class="cr-check-list">
                                @foreach($crGroups as $pmName => $group)
                                    <div class="cr-group-header">
                                        {{ $pmName }}
                                        <span class="badge badge-secondary ml-1">{{ $group->count() }}</span>
                                    </div>
                                    @foreach($group as $cr)
                                    <label class="cr-check-item d-flex align-items-start">
                                        <input type="checkbox" name="cr_ids[]" value="{{ $cr->id }}"
                                               class="cr-checkbox mt-1"
                                               style="width:16px;height:16px;flex-shrink:0;accent-color:var(--success);">
                                        <div class="cr-item-body">
                                            <div class="cr-item-row">
                                                <span class="cr-item-desc">{{ Str::limit($cr->description, 70) }}</span>
                                                <span class="cr-item-amount">{{ $cr->amount_formatted }}</span>
                                            </div>
                                            <div class="cr-item-beneficiary">
                                                <i class="fas fa-user mr-1"></i>{{ $cr->beneficiary->complete_name ?? '-' }}
                                            </div>
                                        </div>
                                    </label>
                                    @endforeach
                                @endforeach
                            </div>
                            {{-- MN-09 — biaya Non-CR. Bentuknya sengaja sama persis
                                 dengan daftar CR di atas, dan checkbox-nya memakai
                                 kelas .cr-checkbox yang sama supaya penghitung
                                 "dipilih / total" di bawah tidak perlu logika
                                 terpisah yang bisa jadi berbeda. --}}
                            @if($availableRegisters->isNotEmpty())
                            <div class="cr-group-header mt-3">
                                <i class="fas fa-book mr-1"></i> Cost Register (Non-CR)
                                <span class="badge badge-secondary ml-1">{{ $availableRegisters->count() }}</span>
                            </div>
                            <div class="cr-check-list">
                                @foreach($availableRegisters as $reg)
                                <label class="cr-check-item d-flex align-items-start">
                                    <input type="checkbox" name="cost_register_ids[]" value="{{ $reg->id }}"
                                           class="cr-checkbox mt-1"
                                           data-amount="{{ $reg->amount }}"
                                           style="width:16px;height:16px;flex-shrink:0;accent-color:var(--success);">
                                    <div class="cr-item-body">
                                        <div class="cr-item-row">
                                            <span class="cr-item-desc">
                                                <span class="badge badge-{{ $reg->type_color }} mr-1" style="font-size:9px;">{{ $reg->type_label }}</span>
                                                {{ Str::limit($reg->notes, 60) }}
                                            </span>
                                            <span class="cr-item-amount">{{ number_format($reg->amount, 0, ',', '.') }}</span>
                                        </div>
                                        <div class="cr-item-beneficiary">
                                            <i class="fas fa-user mr-1"></i>{{ $reg->beneficiary->complete_name ?? '-' }}
                                            <span class="ml-2"><i class="fas fa-file-contract mr-1"></i>{{ $reg->project->code ?? '-' }}</span>
                                        </div>
                                    </div>
                                </label>
                                @endforeach
                            </div>
                            @endif

                            {{-- SECOND PAYMENT — kekurangan bayar hasil Settlement Cash
                                 Advance. Sama seperti Cost Register, ia memakai kelas
                                 .cr-checkbox dan data-amount yang sama supaya masuk
                                 hitungan total batch tanpa logika terpisah.
                                 Nominalnya adalah SELISIHNYA saja, bukan nilai CR. --}}
                            @if($availableSecondPayments->isNotEmpty())
                            <div class="cr-group-header mt-3">
                                <i class="fas fa-hand-holding-usd mr-1"></i> Second Payment (Kekurangan Settlement)
                                <span class="badge badge-secondary ml-1">{{ $availableSecondPayments->count() }}</span>
                            </div>
                            <div class="cr-check-list">
                                @foreach($availableSecondPayments as $sp)
                                <label class="cr-check-item d-flex align-items-start">
                                    <input type="checkbox" name="second_payment_ids[]" value="{{ $sp->id }}"
                                           class="cr-checkbox mt-1"
                                           data-amount="{{ $sp->amount }}"
                                           style="width:16px;height:16px;flex-shrink:0;accent-color:var(--success);">
                                    <div class="cr-item-body">
                                        <div class="cr-item-row">
                                            <span class="cr-item-desc">
                                                <span class="badge badge-warning mr-1" style="font-size:9px;">2nd</span>
                                                <strong>{{ $sp->budgetRequest->code ?? '-' }}</strong>
                                                — {{ Str::limit($sp->budgetRequest->description ?? '-', 55) }}
                                            </span>
                                            <span class="cr-item-amount">{{ $sp->amount_formatted }}</span>
                                        </div>
                                        <div class="cr-item-beneficiary">
                                            <i class="fas fa-user mr-1"></i>{{ $sp->budgetRequest->beneficiary->complete_name ?? '-' }}
                                            <span class="ml-2" title="Nilai pengajuan awal yang sudah dibayar">
                                                <i class="fas fa-receipt mr-1"></i>Pengajuan awal: {{ $sp->budgetRequest->amount_formatted ?? '-' }}
                                            </span>
                                        </div>
                                    </div>
                                </label>
                                @endforeach
                            </div>
                            @endif

                            <small class="text-muted mt-2 d-block">
                                <span id="selectedCount">0</span> item dipilih ·
                                Total: <strong id="selectedTotal">0</strong>
                            </small>
                            @endif
                        </div>

                    </div>

                    <!-- ================= TOP UP ================= -->
                    <div id="sectionTopUp" class="row" style="display:none;">
                        <div class="col-md-6 offset-md-3 form-clean">

                            <div class="form-group">
                                <label class="font-weight-600">Dari (Main Account)</label>
                                <select name="from_source_fund_id_display" id="selFromFund" class="form-control form-control-sm" disabled>
                                    @forelse($mainSourceFunds as $sf)
                                        <option value="{{ $sf->id }}">{{ $sf->name }} — {{ $sf->bank->name ?? '-' }} ({{ $sf->no_account ?? '-' }})</option>
                                    @empty
                                        <option value="">— Belum ada Main Account —</option>
                                    @endforelse
                                </select>
                                <input type="hidden" name="from_source_fund_id" id="inpFromFund" value="{{ $mainSourceFunds->first()->id ?? '' }}">
                                @if($mainSourceFunds->isEmpty())
                                <small class="text-danger d-block mt-1">
                                    Belum ada Source Fund yang ditandai Main Account. <a href="{{ url('/source-fund') }}" target="_blank">Atur di Source Fund</a>.
                                </small>
                                @endif
                            </div>

                            <div class="form-group">
                                <label class="font-weight-600">Ke (Rekening Operasional) <span class="text-danger">*</span></label>
                                <select name="to_source_fund_id" id="selToFund" class="form-control form-control-sm">
                                    <option value="">— Pilih Rekening Tujuan —</option>
                                    @foreach($operationalSourceFunds as $sf)
                                        <option value="{{ $sf->id }}" {{ old('to_source_fund_id') == $sf->id ? 'selected' : '' }}>
                                            {{ $sf->name }} — {{ $sf->bank->name ?? '-' }} ({{ $sf->no_account ?? '-' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="font-weight-600">Jumlah <span class="text-danger">*</span></label>
                                <input type="text" name="amount" id="inpTopUpAmount" class="form-control text-right"
                                       value="{{ old('amount') }}" placeholder="0">
                            </div>

                            <div class="form-group">
                                <label class="font-weight-600">Deskripsi <span class="text-danger">*</span></label>
                                <input type="text" name="description" class="form-control form-control-sm" required
                                       style="text-transform:uppercase;"
                                       value="{{ old('description') }}"
                                       placeholder="mis. Top Up Operasional Agustus...">
                            </div>

                            <div class="form-group mb-0">
                                <label class="font-weight-600">Notes (opsional)</label>
                                <textarea name="notes" class="form-control form-control-sm" rows="2"
                                          placeholder="Catatan...">{{ old('notes') }}</textarea>
                            </div>

                        </div>
                    </div>

                </div>

                <div class="card-footer">
                    <div id="footerCashOut" @if($availableCRs->isEmpty() && $availableRegisters->isEmpty() && $availableSecondPayments->isEmpty()) style="display:none;" @endif>
                        <button type="submit" class="btn btn-success" id="btnSubmitCashOut">
                            <i class="fas fa-plus-circle mr-1"></i> Buat Batch
                        </button>
                    </div>
                    <div id="footerTopUp" style="display:none;">
                        <button type="submit" class="btn btn-info" id="btnSubmitTopUp">
                            <i class="fas fa-exchange-alt mr-1"></i> Buat Top Up
                        </button>
                    </div>
                </div>
            </form>

        </div>

    </div>
</div>

<textarea id="crAmountsHolder" style="display:none;">{{ json_encode($availableCRs->pluck('amount', 'id')) }}</textarea>
{{-- MN-09 — nominal Cost Register ditaruh di data-amount tiap checkbox (lihat
     daftar di atas), jadi tidak perlu peta terpisah seperti CR. --}}

@endsection

@push('styles')
<style>
.font-weight-600 { font-weight:600; }

.calc-caption {
    font-size:11px; font-weight:700; letter-spacing:.4px;
    text-transform:uppercase; color:var(--text-faint);
}

.cr-check-list {
    max-height:600px; overflow-y:auto;
    border:1px solid var(--border); border-radius:var(--radius-md);
}
.cr-check-item {
    padding:10px 12px; margin-bottom:0; gap:10px; cursor:pointer;
    border-bottom:1px solid var(--border); transition:background .12s ease;
}
.cr-check-list .cr-check-item:last-child { border-bottom:none; }
.cr-check-item:hover { background:var(--surface-2); }
.cr-check-item.selected { background:var(--info-bg); }

.cr-item-body { flex:1; min-width:0; }
.cr-item-row { display:flex; align-items:baseline; justify-content:space-between; gap:14px; }
.cr-item-desc { font-size:13px; font-weight:600; color:var(--text); }
.cr-item-beneficiary { font-size:11.5px; color:var(--text-faint); margin-top:4px; }
.cr-item-amount { flex-shrink:0; white-space:nowrap; font-size:16px; font-weight:700; color:var(--info); }

.cr-group-header {
    padding:8px 12px; font-size:11px; font-weight:700; letter-spacing:.3px;
    text-transform:uppercase; color:var(--text-faint); background:var(--surface-2);
    border-bottom:1px solid var(--border); border-top:1px solid var(--border);
}
.cr-check-list .cr-group-header:first-child { border-top:none; }

.cr-section-title { font-size:1rem; font-weight:600; margin:0; }

.batch-two-col { margin-left:-8px; margin-right:-8px; }
.batch-two-col > .batch-col-left,
.batch-two-col > .batch-col-right { padding-left:8px; padding-right:8px; }
.batch-two-col .batch-col-right { border-left:1px solid var(--border); padding-left:20px; }
@media (max-width: 767.98px) {
    .batch-two-col { margin-left:0; margin-right:0; }
    .batch-two-col > .batch-col-left,
    .batch-two-col > .batch-col-right { padding-left:15px; padding-right:15px; }
    .batch-two-col .batch-col-right {
        border-left:none; border-top:1px solid var(--border);
        padding-top:16px; margin-top:12px;
    }
}

.btn-xs { padding:2px 8px; font-size:11px; border-radius:var(--radius-sm); }

.tt-tabs { display:flex; gap:28px; }
.type-toggle-btn {
    cursor:pointer; padding:8px 2px 10px; margin-bottom:-1px;
    font-weight:600; font-size:14px; color:var(--text-faint);
    border-bottom:2px solid transparent; transition:color .15s ease, border-color .15s ease;
}
.type-toggle-btn:hover { color:var(--text); }
.type-toggle-btn input { position:absolute; opacity:0; pointer-events:none; }
.type-toggle-btn.active { color:var(--text); border-bottom-color:var(--accent); }
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
            /* Nominal CR datang dari peta crAmounts (dikirim sebagai JSON);
               Cost Register membawa nominalnya sendiri di data-amount (MN-09).
               Keduanya dijumlah di satu tempat ini supaya total batch tidak
               pernah melewatkan salah satu jenis. */
            var own = parseInt($(this).data('amount'), 10);
            total += isNaN(own) ? (crAmounts[$(this).val()] || 0) : own;
        });
        $('#selectedCount').text(count);
        $('#selectedTotal').text(total.toLocaleString('id-ID'));
        $('#calcBatchValue').text(total.toLocaleString('id-ID'));
        $('#calcGrandTotal').text(total.toLocaleString('id-ID'));
    }

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

    // ── TYPE TOGGLE (Cash Out / Top Up) ──
    // Field bernama sama (mis. "description", "notes") ada di kedua section.
    // Section yang tidak aktif WAJIB di-disable, bukan cuma disembunyikan —
    // kalau tidak, browser tetap ikut submit input yang hidden itu dan bisa
    // menimpa nilai dari section yang aktif (nama field-nya sama).
    function setSectionActive($section, active) {
        $section.toggle(active);
        $section.find('input, select, textarea').prop('disabled', !active);
    }

    $('.type-toggle-btn').on('click', function () {
        var type = $(this).data('type');
        $('.type-toggle-btn').removeClass('active');
        $(this).addClass('active');
        $('#inpType').val(type);

        setSectionActive($('#sectionCashOut'), type !== 'TOP_UP');
        setSectionActive($('#sectionTopUp'), type === 'TOP_UP');

        $('#footerCashOut').toggle(type !== 'TOP_UP' && $('#crCheckList').length > 0);
        $('#footerTopUp').toggle(type === 'TOP_UP');
    });

    // State awal: Cash Out aktif, disable field-field di section Top Up.
    setSectionActive($('#sectionTopUp'), false);

    // ── FORMAT ANGKA RUPIAH (Top Up amount) ──
    $('#inpTopUpAmount').on('input', function () {
        var v = (this.value || '').replace(/[^\d]/g, '');
        this.value = v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    });

    $('#frmCreateBatch').on('submit', function (e) {
        e.preventDefault();
        var form = this;
        var type = $('#inpType').val();

        if (type === 'TOP_UP') {
            var fromId = $('#inpFromFund').val();
            var toId = $('#selToFund').val();
            var amount = ($('#inpTopUpAmount').val() || '').replace(/[^\d]/g, '');
            var topUpDesc = $('#sectionTopUp input[name="description"]').val();

            if (!topUpDesc || !topUpDesc.trim()) {
                Swal.fire({ icon: 'warning', title: 'Deskripsi wajib diisi', confirmButtonColor: '#92232b' });
                return;
            }
            if (!fromId) {
                Swal.fire({ icon: 'warning', title: 'Belum ada Main Account', text: 'Atur Main Account di halaman Source Fund terlebih dahulu.', confirmButtonColor: '#92232b' });
                return;
            }
            if (!toId) {
                Swal.fire({ icon: 'warning', title: 'Pilih rekening tujuan (Ke)', confirmButtonColor: '#92232b' });
                return;
            }
            if (!amount || parseInt(amount, 10) <= 0) {
                Swal.fire({ icon: 'warning', title: 'Isi jumlah Top Up', confirmButtonColor: '#92232b' });
                return;
            }

            Swal.fire({
                title: 'Buat Top Up?',
                text: parseInt(amount, 10).toLocaleString('id-ID') + ' akan ditransfer dari Main Account.',
                icon: 'question', showCancelButton: true,
                confirmButtonColor: '#42627d', cancelButtonColor: '#55595e',
                confirmButtonText: 'Ya, Buat', cancelButtonText: 'Batal'
            }).then(function (r) { if (r.isConfirmed) form.submit(); });
            return;
        }

        var cashOutDesc = $('#sectionCashOut input[name="description"]').val();
        if (!cashOutDesc || !cashOutDesc.trim()) {
            Swal.fire({ icon: 'warning', title: 'Deskripsi wajib diisi', confirmButtonColor: '#92232b' });
            return;
        }

        var count = $('.cr-checkbox:checked').length;
        if (count === 0) {
            Swal.fire({ icon: 'warning', title: 'Pilih minimal 1 CR atau Cost Register', confirmButtonColor: '#92232b' });
            return;
        }
        Swal.fire({
            title: 'Buat Batch?',
            text: count + ' item akan dimasukkan ke dalam batch baru.',
            icon: 'question', showCancelButton: true,
            confirmButtonColor: 'var(--success)', cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Buat', cancelButtonText: 'Batal'
        }).then(function (r) { if (r.isConfirmed) form.submit(); });
    });

    updateCalc();

});
</script>
@endpush