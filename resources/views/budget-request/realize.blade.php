@extends('layouts.app')

@section('title', 'Submit Settlement')

@section('content')

<div class="content">
    <div class="container-fluid">
        <div class="tw:flex tw:flex-col tw:gap-5 tw:py-4">

            {{-- Judul & breadcrumb topbar: shell memindahkan <h1> dan .breadcrumb pertama ke topbar lalu
                 menghapusnya dari konten (tesys-shell.js hoistPageTitle); disembunyikan secara visual. --}}
            <div class="tw:sr-only">
                <h1>Submit Settlement</h1>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">Finance</li>
                    <li class="breadcrumb-item"><a href="{{ url('/budget-request') }}">Cost Request</a></li>
                    <li class="breadcrumb-item active">Settlement</li>
                </ol>
            </div>

            @php
                $kodeCr = '<button type="button" class="cr-copy-code" data-code="' . e($budgetRequest->code) . '" title="Klik untuk menyalin kode">' . e($budgetRequest->code) . '</button>';
            @endphp
            <x-tw.page-header title="Submit Settlement" :description="new \Illuminate\Support\HtmlString($kodeCr)">
                <x-slot:actions>
                    <x-tw.button href="{{ url('/budget-request?open=' . $budgetRequest->id) }}" variant="secondary" class="tw:max-sm:min-h-12"><i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali ke daftar</x-tw.button>
                </x-slot:actions>
            </x-tw.page-header>

            {{-- Alasan reject terakhir — hanya tampil kalau CR ini sedang
                 SETTLE_REJECTED, supaya pengaju langsung tahu apa yang harus
                 direvisi tanpa harus membuka tab Log. --}}
            @if($budgetRequest->status === 'SETTLE_REJECTED' && $rejectReason)
            <div role="alert" class="tw:rounded-md tw:border tw:border-danger tw:bg-danger-bg tw:p-3 tw:text-data tw:leading-body tw:text-danger">
                <div class="tw:font-label"><i class="fas fa-exclamation-triangle tw:mr-1" aria-hidden="true"></i> Settlement ini ditolak.</div>
                <div class="tw:mt-1">{{ $rejectReason->notes }}</div>
                <div class="tw:mt-1 tw:text-caption tw:text-text-dim">
                    {{ $rejectReason->actor_name ?? 'System' }} · {{ $rejectReason->created_at->format('d M Y H:i') }}
                </div>
            </div>
            @endif

            <!-- REQUEST SUMMARY — strip horizontal, lebar sama dengan kartu di bawahnya -->
            <x-tw.card padding="sm">
                <div class="tw:grid tw:grid-cols-1 tw:items-center tw:gap-4 tw:lg:grid-cols-5">
                    <div class="tw:min-w-0 tw:lg:col-span-2">
                        @php
                            // Requestor - PIC; tanpa PIC, pakai Beneficiary langsung (sama dengan kolom PIC di daftar CR).
                            $requestor = $budgetRequest->applicant?->username ? strtoupper($budgetRequest->applicant->username) : trim((string) ($budgetRequest->applicant?->name ?? ''));
                            $picNama   = trim((string) ($budgetRequest->beneficiary?->pic?->pic ?? ''));
                            $picNama   = ($picNama === '' || $picNama === '-') ? trim((string) ($budgetRequest->beneficiary?->nickname ?? '')) : $picNama;
                            $picNama   = ($picNama === '-') ? '' : $picNama;
                        @endphp
                        <div class="cr-settle-title tw:leading-body tw:text-text">{{ $budgetRequest->description }}</div>
                        <div class="tw:mt-1 tw:text-data tw:text-text-dim">{{ $requestor !== '' ? $requestor : '-' }}@if($picNama !== '') - {{ $picNama }}@endif</div>
                    </div>
                    <div class="tw:grid tw:grid-cols-1 tw:gap-3 tw:sm:grid-cols-3 tw:lg:col-span-3">
                        <div class="tw:min-w-0 tw:rounded-md tw:border tw:border-border tw:bg-surface-2 tw:px-4 tw:py-3">
                            <div class="tw:text-caption tw:font-label tw:uppercase tw:tracking-micro tw:text-text-dim">Total Request</div>
                            <div class="tw:mt-1 tw:text-kpi tw:font-heading tw:leading-tight tw:tabular-nums tw:text-text">{{ number_format($budgetRequest->amount, 0, ',', '.') }}</div>
                        </div>
                        <div class="tw:min-w-0 tw:rounded-md tw:border tw:border-border tw:bg-surface-2 tw:px-4 tw:py-3">
                            <div class="tw:text-caption tw:font-label tw:uppercase tw:tracking-micro tw:text-text-dim">Total Settle</div>
                            <div id="statTotalSettle" class="tw:mt-1 tw:text-kpi tw:font-heading tw:leading-tight tw:tabular-nums tw:text-text">0</div>
                        </div>
                        <div class="tw:min-w-0 tw:rounded-md tw:border tw:border-border tw:bg-surface-2 tw:px-4 tw:py-3">
                            <div class="tw:text-caption tw:font-label tw:uppercase tw:tracking-micro tw:text-text-dim">Variance</div>
                            <div id="statVariance" class="tw:mt-1 tw:text-kpi tw:font-heading tw:leading-tight tw:tabular-nums">0</div>
                        </div>
                    </div>
                </div>
            </x-tw.card>

        <form id="realizeForm"
              action="{{ route('budget-request.submit-realization', $budgetRequest->id) }}"
              method="POST" enctype="multipart/form-data" class="tw:flex tw:min-w-0 tw:flex-col tw:gap-5">
            @csrf

            <!-- DETAIL / SETTLEMENT PER ITEM -->
            <x-tw.card padding="none">
                <x-slot:header>
                    <div class="tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-3">
                        <div role="heading" aria-level="2" class="tw:flex tw:items-center tw:gap-2">
                            <i class="fas fa-list-ul" aria-hidden="true"></i> Settlement per Item
                        </div>
                        <div class="tw:flex tw:flex-wrap tw:items-center tw:gap-2">
                            <x-tw.button id="btnViewRequest" variant="secondary" size="sm" class="tw:max-sm:min-h-12"><i class="fas fa-eye" aria-hidden="true"></i> View Request</x-tw.button>
                            <x-tw.button id="btnAutoFill" variant="secondary" size="sm" class="tw:max-sm:min-h-12" title="Salin seluruh rincian pengajuan menjadi baris settlement"><i class="fas fa-magic" aria-hidden="true"></i> Auto Fill</x-tw.button>
                            <x-tw.button href="{{ url('/budget-request/' . $budgetRequest->id . '/settlement-template') }}" variant="secondary" size="sm" class="tw:max-sm:min-h-12"><i class="fas fa-file-excel" aria-hidden="true"></i> Download Template</x-tw.button>
                            <x-tw.button id="btnImportSettlementExcel" variant="secondary" size="sm" class="tw:max-sm:min-h-12"><i class="fas fa-file-import" aria-hidden="true"></i> Import Excel</x-tw.button>
                            <input type="file" id="importSettlementFileInput" accept=".xlsx,.xls" style="display:none;">
                            {{-- Baris tambahan tanpa item asal (wajib pilih Sub COA & isi Note). --}}
                            <x-tw.button id="btnAddSettleRow" size="sm" class="tw:max-sm:min-h-12"><i class="fas fa-plus" aria-hidden="true"></i> Tambah Baris</x-tw.button>
                        </div>
                    </div>
                </x-slot:header>

                <div class="table-responsive tw:pb-1">
                    <table id="tblSettlement" class="cr-realize-table tw:w-full tw:min-w-[1180px] tw:border-collapse">
                        <thead>
                            <tr>
                                <th scope="col" class="tw:w-[24%]">Sub COA / Item Request</th>
                                <th scope="col" class="tw:w-[24%]">Description Settle</th>
                                <th scope="col" class="text-right text-nowrap tw:w-[1%]">Harga Satuan</th>
                                <th scope="col" class="text-center text-nowrap tw:w-[1%]">Qty</th>
                                <th scope="col" class="text-right text-nowrap tw:w-[1%]">Settle</th>
                                <th scope="col" class="text-center text-nowrap tw:w-[1%]">Lampiran Item</th>
                                <th scope="col" class="text-center text-nowrap tw:w-[1%]">Note</th>
                                <th scope="col" class="text-center text-nowrap tw:w-[1%]">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="realizeBody">
                            {{-- Awalnya kosong; hanya baris yang sudah tersimpan (draft / ditolak) yang tampil. --}}
                            @forelse($settleRows as $i => $row)
                                @include('budget-request._realize_row', [
                                    'k'           => $i,
                                    'rowId'       => $row['id'],
                                    'detail'      => $row['detail'],
                                    'orphanId'    => $row['orphan_detail_id'] ?? null,
                                    'bdId'        => $row['budget_detail_id'],
                                    'notes'       => $row['notes'],
                                    'changeNote'  => $row['change_note'],
                                    'qty'         => $row['qty'] ?? null,
                                    'amount'      => $row['price'] ?? $row['amount'],
                                    'attachments' => $row['attachments'],
                                ])
                            @empty
                            <tr class="realize-empty">
                                <td colspan="8" class="text-center tw:py-6 tw:text-data tw:text-text-dim">Belum ada baris. Klik <strong>Auto Fill</strong> untuk menyalin rincian pengajuan, atau <strong>Tambah Baris</strong>.</td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="tw:bg-surface-2">
                                <th colspan="4" class="text-right">Subtotal</th>
                                <th class="text-right tw:tabular-nums" id="footSettleTotal">0</th>
                                <th></th>
                                <th></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-tw.card>

            {{-- Template baris baru (Pecah / Tambah Baris / import). --}}
            <template id="realizeRowTpl">
                @include('budget-request._realize_row', [
                    'k' => '__K__', 'rowId' => null, 'detail' => null, 'orphanId' => null, 'bdId' => null,
                    'notes' => null, 'changeNote' => null, 'qty' => 1, 'amount' => 0, 'attachments' => collect(),
                ])
            </template>
            <script type="application/json" id="realizeSubItems">@json($subItems->keyBy('id'))</script>
            {{-- Rincian pengajuan (item yang ikut, bukan takeout) untuk "View Request" & "Auto Fill". --}}
            @php
                $reqItems = $budgetRequest->details->reject(fn ($d) => $d->isTakenOut())->mapWithKeys(fn ($d) => [$d->id => [
                    'coa'     => $d->account?->name ?? '-',
                    'sub_coa' => $d->budgetDetail?->deskripsi ?: '-',
                    'budget_detail_id' => $d->budget_detail_id ? (int) $d->budget_detail_id : null,
                    'desc'    => (string) $d->notes,
                    'qty'     => (float) $d->qty,
                    'price'   => (int) $d->amount,
                    'total'   => (int) $d->total,
                ]]);
            @endphp
            <script type="application/json" id="realizeReqItems">@json($reqItems)</script>

            <!-- BUKTI SETTLEMENT (GLOBAL) -->
            <x-tw.card>
                <x-slot:header>
                    <div class="tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-3">
                        <div role="heading" aria-level="2" class="tw:flex tw:items-center tw:gap-2">
                            <i class="fas fa-check-double" aria-hidden="true"></i> Bukti Settlement (Global)
                            <x-tw.badge tone="danger" id="attWajib" style="display:none;" title="Wajib karena total Settle melebihi nilai pengajuan">Wajib*</x-tw.badge>
                        </div>
                        <x-tw.button id="btnAddRealAttachment" size="sm" class="tw:max-sm:min-h-12"><i class="fas fa-plus" aria-hidden="true"></i> Tambah Lampiran</x-tw.button>
                    </div>
                </x-slot:header>

                <div>
                    @if($budgetRequest->realizationAttachments->isNotEmpty())
                    {{-- Bukti global dari submit sebelumnya — ikut dipertahankan. --}}
                    <div class="table-responsive tw:mb-4">
                        <table class="tw:w-full tw:border-collapse">
                            <thead>
                                <tr>
                                    <th scope="col" class="tw:w-[5%] text-center">No</th>
                                    <th scope="col">File (sudah diunggah)</th>
                                    <th scope="col" class="tw:w-[10%] text-center">Hapus</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($budgetRequest->realizationAttachments as $i => $att)
                                <tr>
                                    <td class="text-center align-middle">{{ $i + 1 }}</td>
                                    <td class="align-middle att-lama">
                                        <input type="checkbox" name="delete_realization_global_attachments[]"
                                               value="{{ $att->id }}" class="del-realize-att-global d-none">
                                        <i class="fas fa-file tw:mr-1" aria-hidden="true"></i>
                                        <a href="#" class="att-lama-open"
                                           data-url="{{ asset('storage/' . $att->path) }}"
                                           data-nama="{{ $att->original_name }}">{{ $att->original_name }}</a>
                                        <span class="tw:ml-2 tw:text-caption tw:text-text-dim">({{ $att->size_formatted }})</span>
                                    </td>
                                    <td class="text-center align-middle">
                                        <x-tw.button variant="danger" size="sm" class="att-lama-del" title="Hapus lampiran ini" aria-label="Hapus lampiran {{ $att->original_name }}"><i class="fas fa-times" aria-hidden="true"></i></x-tw.button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif

                    <div id="realAttUploadArea" style="display:none;">
                    <div id="dropZoneReal" class="att-dropzone tw:mb-4" tabindex="0">
                        <i class="fas fa-cloud-upload-alt fa-2x mb-2 d-block" aria-hidden="true"></i>
                        <div class="tw:text-control tw:text-text">
                            Drag &amp; drop file ke sini, paste gambar (Ctrl+V), atau
                            <span class="tw:cursor-pointer tw:font-label tw:text-accent-text tw:underline" id="dropZoneBrowseReal" role="button" tabindex="0">klik untuk pilih</span>
                        </div>
                        <div class="tw:mt-1 tw:text-caption tw:text-text-dim">Bisa pilih/drop/paste beberapa file sekaligus</div>
                        <input type="file" id="dropZoneInputReal" multiple style="display:none;">
                    </div>
                    <div class="table-responsive" id="realAttTableWrap" style="display:none;">
                        <table class="tw:w-full tw:border-collapse">
                            <thead>
                                <tr>
                                    <th scope="col" class="tw:w-[5%] text-center">No</th>
                                    <th scope="col">File</th>
                                    <th scope="col" class="tw:w-[10%] text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="realAttachmentBody"></tbody>
                        </table>
                    </div>
                    </div>
                </div>

                <x-slot:footer>
                    <i class="fas fa-info-circle tw:mr-1" aria-hidden="true"></i>
                    Format: PDF, JPG, PNG, DOC, XLS. Maks 10 MB per file. Foto pekerjaan, laporan, atau dokumen pendukung lainnya.
                </x-slot:footer>
            </x-tw.card>

            {{-- KETERANGAN SETTLEMENT — opsional, ikut tercatat di Log CR bersama
                 rincian settlement yang disubmit. Polanya sama dengan lampiran:
                 kolomnya baru muncul setelah tombolnya diklik. --}}
            <x-tw.card>
                <x-slot:header>
                    <div class="tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-3">
                        <div role="heading" aria-level="2" class="tw:flex tw:items-center tw:gap-2">
                            <i class="fas fa-comment-dots" aria-hidden="true"></i> Keterangan
                            <x-tw.badge tone="danger" id="ketWajib" style="display:none;" title="Wajib karena total Settle melebihi nilai pengajuan">Wajib*</x-tw.badge>
                        </div>
                        <x-tw.button id="btnAddSettleNote" size="sm" class="tw:max-sm:min-h-12"><i class="fas fa-plus" aria-hidden="true"></i> Tambah Keterangan</x-tw.button>
                    </div>
                </x-slot:header>

                @php $ketAwal = old('settlement_notes', $budgetRequest->settlement_notes ?? ''); @endphp
                <div id="settleNoteArea" style="{{ filled($ketAwal) ? '' : 'display:none;' }}">
                    <x-tw.textarea name="settlement_notes" id="settlementNotes" rows="3" maxlength="1000" :value="$ketAwal"
                                   placeholder="Mis. nota hotel atas nama rekan kerja, selisih karena kenaikan tarif, dsb."
                                   hint="Keterangan ini tersimpan di data Cost Request dan ikut tercatat di Log. Wajib diisi bila total Settle melebihi nilai pengajuan. Maks 1000 karakter." />
                </div>
                <p id="settleNoteEmpty" style="{{ filled($ketAwal) ? 'display:none;' : '' }}" class="tw:m-0 tw:text-data tw:leading-body tw:text-text-dim">
                    Belum ada keterangan. Klik <strong>Tambah Keterangan</strong> kalau settlement ini perlu penjelasan.
                </p>
            </x-tw.card>

            <x-tw.card padding="sm">
                <div class="tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-3 tw:max-sm:flex-col-reverse tw:max-sm:items-stretch">
                    <span id="submitHint" aria-live="off" class="tw:min-w-0 tw:flex-1 tw:text-caption tw:leading-body tw:text-danger" style="display:none;"></span>
                    <div class="tw:flex tw:flex-wrap tw:items-center tw:gap-2 tw:max-sm:flex-col-reverse tw:max-sm:[&>*]:w-full">
                        <x-tw.button href="{{ url('/budget-request?open=' . $budgetRequest->id) }}" variant="secondary" class="tw:max-sm:min-h-12"><i class="fas fa-times" aria-hidden="true"></i> Cancel</x-tw.button>
                        {{-- Simpan draft — data tersimpan TANPA mengajukan, status CR
                             tetap PAID/SETTLE_REJECTED. Boleh disimpan walau belum
                             lengkap; kelengkapan baru wajib saat Submit. --}}
                        <x-tw.button id="saveDraftBtn" variant="secondary" class="tw:max-sm:min-h-12"
                                     data-action="{{ route('budget-request.save-realization-draft', $budgetRequest->id) }}"><i class="fas fa-save" aria-hidden="true"></i> Simpan</x-tw.button>
                        <x-tw.button type="submit" id="submitBtn" disabled class="tw:max-sm:min-h-12"><i class="fas fa-paper-plane" aria-hidden="true"></i> Submit Settlement</x-tw.button>
                    </div>
                </div>
            </x-tw.card>
        </form>

        </div>
    </div>
</div>

@endsection


@push('scripts')
<script>
$(function () {
    let form = $('#realizeForm');

    function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
    function escAttr(s) { return esc(s).replace(/"/g, '&quot;'); }
    function moneyFmt(v) {
        var sign = v < 0 ? '-' : '';
        return sign + Math.abs(Math.round(v)).toLocaleString('id-ID');
    }
    // Format input Settle sambil diketik ("4000000" -> "4.000.000"), tanpa prefix Rp di dalam kotaknya.
    function fmtRp(v) {
        v = (v || '').toString().replace(/\D/g, '');
        return v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    function parseRp(v) {
        return parseInt((v || '').toString().replace(/\D/g, '')) || 0;
    }

    // Inisialisasi nilai awal (server-rendered, masih polos) jadi berformat titik.
    $('.realize-amount-input').each(function () { this.value = fmtRp(this.value); });

    $(document).on('input', '.realize-amount-input', function () {
        this.value = fmtRp(this.value);
    });
    $(document).on('input', '.realize-qty-input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 3);
    });
    $(document).on('blur', '.realize-qty-input', function () {
        if (!parseInt(this.value, 10)) this.value = 1;
    });

    /* ── TOTAL / VARIANCE OTOMATIS (per item asal + subtotal + ringkasan) ──
       Settlement Custom COA (25 Okt 2026): satu item pengajuan boleh dipecah
       jadi beberapa baris (Sub COA berbeda) dan boleh ada baris tambahan.
       Request & Variance karena itu dihitung PER ITEM ASAL dan hanya ditulis
       di baris pertama item tersebut — baris pecahan berikutnya "-" — supaya
       nilai Request tidak terhitung ganda. Baris tambahan ber-Request 0. */
    var SUB_ITEMS = {};
    try { SUB_ITEMS = JSON.parse(document.getElementById('realizeSubItems').textContent || '{}') || {}; } catch (e) { SUB_ITEMS = {}; }

    var REQ_TOTAL = {{ (int) $budgetRequest->amount }};   // nilai pengajuan CR (dasar aturan "Settle melebihi")
    var REQ_ITEMS = {};
    try { REQ_ITEMS = JSON.parse(document.getElementById('realizeReqItems').textContent || '{}') || {}; } catch (e) { REQ_ITEMS = {}; }

    function realizeRows() { return $('#realizeBody tr.realize-row'); }

    /* Baris tambahan bernilai 0/kosong dibuang server saat disimpan
       (isEmptyRealizationRow) — jadi tidak perlu Sub COA maupun Note. */
    function barisKosong($row) {
        return !String($row.data('origin') || '') && rowTotal($row) <= 0;
    }

    /* Keterangan & Lampiran Settlement (header) wajib bila total Settle melebihi nilai pengajuan CR. */
    function totalSettleNow() {
        var t = 0;
        realizeRows().each(function () { t += rowTotal($(this)); });
        return t;
    }
    function adaMelebihi() { return totalSettleNow() > REQ_TOTAL; }

    function lampiranGlobalKosong() {
        var baru = $('#realAttachmentBody .real-att-input').filter(function () { return this.files && this.files.length; }).length;
        var lama = $('.del-realize-att-global').filter(function () { return !this.checked; }).length;
        return !baru && !lama;
    }

    function ketKosong() { return !($('#settlementNotes').val() || '').trim(); }

    /* Settle satu baris = Harga Satuan x Qty. */
    function rowTotal($row) {
        return parseRp($row.find('.realize-amount-input').val())
            * Math.max(1, parseInt($row.find('.realize-qty-input').val(), 10) || 1);
    }

    function recalcTotalRealized() {
        var totalSettle = 0, perSub = {};

        realizeRows().each(function () {
            var $row   = $(this);
            var origin = String($row.data('origin') || '');
            var tot    = rowTotal($row);
            var sub    = String($row.find('.realize-sub-select').val() || '');
            totalSettle += tot;
            if (sub) perSub[sub] = (perSub[sub] || 0) + tot;

            $row.find('.realize-total-cell').text(moneyFmt(tot));

            var asal = String($row.data('origin-sub') || '');
            $row.find('.realize-moved-label').toggle(!!origin && !!sub && sub !== asal);
        });

        // Peringatan (informatif, tidak memblokir): total Settle per Sub COA
        // melebihi sisa budget sub-item itu di luar CR ini.
        realizeRows().each(function () {
            var $row = $(this);
            var sub  = String($row.find('.realize-sub-select').val() || '');
            var info = SUB_ITEMS[sub];
            var lebih = info && (perSub[sub] || 0) > (parseInt(info.available, 10) || 0);
            $row.find('.realize-budget-warn')
                .toggle(!!lebih)
                .html(lebih ? '<i class="fas fa-exclamation-triangle mr-1"></i>Melebihi sisa budget Sub COA ini (sisa ' + moneyFmt(info.available) + ')' : '');
        });

        var totalVariance = REQ_TOTAL - totalSettle;

        // Keterangan & Lampiran Settlement wajib bila total Settle melebihi nilai pengajuan.
        var melebihi = totalSettle > REQ_TOTAL;
        $('#ketWajib, #attWajib').toggle(melebihi);
        if (melebihi) { $('#settleNoteArea').show(); $('#settleNoteEmpty').hide(); }

        $('#statTotalSettle').text(moneyFmt(totalSettle));
        $('#statVariance').text(moneyFmt(totalVariance)).toggleClass('text-success', totalVariance >= 0).toggleClass('text-danger', totalVariance < 0);
        $('#footSettleTotal').text(moneyFmt(totalSettle));
    }
    $(document).on('input', '.realize-amount-input, .realize-qty-input', recalcTotalRealized);
    $(document).on('change', '.realize-sub-select', recalcTotalRealized);

    /* ── PECAH / TAMBAH / HAPUS BARIS ─────────────────────────────────────
       Baris baru dibuat dari <template> yang sama dengan baris server
       (_realize_row.blade.php). Indeks form selalu baru (bukan diurutkan
       ulang) — server membaca urutan kiriman apa adanya. */
    var rowSeq = realizeRows().length;

    function initSubSelect($row) {
        if (window.TesysSearchableSelect) {
            TesysSearchableSelect.initOne($row.find('.realize-sub-select'), {
                placeholder: '— Pilih Sub COA —',
                searchPlaceholder: 'Cari Sub COA...'
            });
        }
    }

    function buatBaris(opts) {
        var k   = 'n' + (rowSeq++);
        var tpl = document.getElementById('realizeRowTpl').innerHTML.replace(/__K__/g, k);
        // trim(): jQuery hanya mengurai string HTML yang diawali "<".
        var $row = $($.parseHTML(tpl.trim())).filter('tr.realize-row').first();
        opts = opts || {};

        if (opts.$from) {
            // Pecahan item asal: ikut item & Sub COA asalnya, Settle 0.
            var $f = opts.$from;
            $row.attr('data-origin', $f.data('origin')).attr('data-origin-sub', $f.data('origin-sub'))
                .attr('data-req', $f.data('req')).attr('data-qty', $f.data('qty'));
            $row.data('origin', $f.data('origin')).data('origin-sub', $f.data('origin-sub'))
                .data('req', $f.data('req')).data('qty', $f.data('qty'));
            $row.find('.realize-origin-input').val($f.data('origin'));
            $row.find('.realize-origin-label').remove();
            $row.find('.btn-split-row').show();
            $row.find('.realize-qty-input').val($f.data('qty') || 1);
            $row.find('.realize-sub-select').val(opts.sub != null ? String(opts.sub) : ($f.find('.realize-sub-select').val() || ''));
        } else if (opts.sub != null) {
            $row.find('.realize-sub-select').val(String(opts.sub));
        }

        $row.find('.realize-amount-input').val(fmtRp(String(opts.amount || 0)));

        if (opts.$after && opts.$after.length) {
            opts.$after.after($row);
        } else {
            $('#realizeBody').append($row);
        }
        $('#realizeBody tr.realize-empty').remove();

        initSubSelect($row);
        refreshNoteCount($row.find('.realize-notes-input')[0]);
        refreshNoteItem($row.find('.realize-note-cell'));
        recalcTotalRealized();
        updateSettleSubmitState();
        return $row;
    }

    /* ── AUTO FILL: salin SELURUH rincian pengajuan menjadi baris settlement ──
       Satu baris per item (Sub COA, deskripsi, qty, nilai). Item yang sudah
       punya baris di form dilewati, jadi tidak ada baris yang tertimpa. */
    $('#btnAutoFill').on('click', function () {
        var sudah = {};
        realizeRows().each(function () { var o = String($(this).data('origin') || ''); if (o) sudah[o] = true; });

        var ditambah = 0;
        Object.keys(REQ_ITEMS).forEach(function (id) {
            if (sudah[id]) return;
            var it  = REQ_ITEMS[id];
            var sub = it.budget_detail_id ? String(it.budget_detail_id) : '';
            var $row = buatBaris({ sub: sub || null, amount: it.price });

            $row.attr({ 'data-origin': id, 'data-origin-sub': sub, 'data-req': it.total, 'data-qty': it.qty })
                .data('origin', id).data('origin-sub', sub).data('req', it.total).data('qty', it.qty);
            $row.find('.realize-origin-input').val(id);
            $row.find('.realize-origin-label').remove();
            $row.find('.btn-split-row').show();
            $row.find('.realize-notes-input').val(String(it.desc || '').slice(0, 255));
            $row.find('.realize-qty-input').val(Math.max(1, parseInt(it.qty, 10) || 1));
            ditambah++;
        });

        recalcTotalRealized();
        updateSettleSubmitState();

        if (!ditambah) {
            Swal.fire({ icon: 'info', title: 'Tidak ada yang ditambahkan', text: Object.keys(REQ_ITEMS).length ? 'Semua item pengajuan sudah punya baris di form.' : 'CR ini tidak punya item pengajuan.', confirmButtonColor: '#92232b' });
        }
    });

    /* ── VIEW REQUEST: rincian pengajuan lengkap (read-only) ── */
    $('#btnViewRequest').on('click', function () {
        var ids = Object.keys(REQ_ITEMS), total = 0;
        var body = ids.map(function (id, i) {
            var it = REQ_ITEMS[id]; total += it.total;
            return '<tr><td class="text-center">' + (i + 1) + '</td><td>' + esc(it.coa) + '</td><td>' + esc(it.sub_coa) + '</td><td>' + esc(it.desc || '-')
                + '</td><td class="text-center">' + esc(String(it.qty).replace('.', ',')) + '</td><td class="text-right">' + moneyFmt(it.price)
                + '</td><td class="text-right">' + moneyFmt(it.total) + '</td></tr>';
        }).join('');
        Swal.fire({
            title: 'Rincian Pengajuan — {{ $budgetRequest->code }}',
            html: '<div class="table-responsive" style="max-height:60vh;text-align:left;"><table class="table table-sm mb-0" style="font-size:12.5px;">'
                + '<thead><tr><th class="text-center">No</th><th>COA</th><th>Sub COA</th><th>Deskripsi</th><th class="text-center">Qty</th><th class="text-right">Harga</th><th class="text-right">Total</th></tr></thead>'
                + '<tbody>' + (body || '<tr><td colspan="7" class="text-center text-muted">Tidak ada item</td></tr>') + '</tbody>'
                + '<tfoot><tr><th colspan="6" class="text-right">Total Request</th><th class="text-right">' + moneyFmt(total) + '</th></tr></tfoot></table></div>',
            width: '960px',
            showConfirmButton: false,
            showCloseButton: true,
            customClass: { closeButton: 'att-close-btn' }
        });
    });

    $(document).on('click', '.btn-split-row', function () {
        var $row   = $(this).closest('tr.realize-row');
        var origin = String($row.data('origin') || '');
        // Sisipkan setelah baris TERAKHIR item yang sama, supaya pecahannya berkumpul.
        var $last  = realizeRows().filter(function () { return String($(this).data('origin') || '') === origin; }).last();
        buatBaris({ $from: $row, $after: $last });
    });

    $('#btnAddSettleRow').on('click', function () {
        buatBaris({});
    });

    /* Klik kode CR = salin ke clipboard. */
    $(document).on('click', '.cr-copy-code', function () {
        var kode = String($(this).data('code') || $.trim($(this).text()));
        function toast(pesan, ikon) {
            if (window.tsToast) window.tsToast(ikon === 'error' ? 'error' : 'success', pesan);
        }
        function berhasil() { toast('Kode ' + kode + ' disalin'); }
        function gagal() { toast('Gagal menyalin kode', 'error'); }
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(kode).then(berhasil, gagal);
            return;
        }
        var ta = document.createElement('textarea');
        ta.value = kode; ta.setAttribute('readonly', ''); ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy') ? berhasil() : gagal(); } catch (err) { gagal(); }
        document.body.removeChild(ta);
    });

    $(document).on('click', '.btn-remove-row', function () {
        // Tanpa konfirmasi: baris hanya hilang dari form dan baru berlaku saat disimpan.
        $(this).closest('tr.realize-row').remove();
        recalcTotalRealized();
        updateSettleSubmitState();
    });

    realizeRows().each(function () { initSubSelect($(this)); });
    recalcTotalRealized();

    /* ── SISA KARAKTER "DESCRIPTION SETTLE" ───────────────────────────────── */
    var NOTE_MAX = 255;

    function refreshNoteCount(el) {
        var sisa  = NOTE_MAX - (el.value || '').length;
        var $info = $(el).closest('td').find('.realize-notes-count');
        if (!$info.length) return;

        if (sisa <= 0) {
            $info.text('Batas ' + NOTE_MAX + ' karakter sudah habis — tidak bisa menambah teks lagi.')
                 .removeClass('text-muted').addClass('text-danger');
        } else {
            $info.text('Sisa ' + sisa + ' karakter.')
                 .removeClass('text-danger').addClass('text-muted');
        }
    }

    $(document).on('input', '.realize-notes-input', function () { refreshNoteCount(this); });

    /* ── LIHAT / UBAH DESKRIPSI PENUH ─────────────────────────────────────
       Kolomnya sempit dan teks panjang terpotong. Ikon di ujung kanan kotak
       membuka pop-up berisi teks utuh yang juga bisa disunting; ditutup
       dengan klik di luar, Esc, atau tanda X. */
    $(document).on('click', '.desc-expand', function () {
        var $inp = $(this).closest('.desc-wrap').find('.realize-notes-input');

        Swal.fire({
            title: 'Description Settle',
            html: '<textarea id="descPopupInput" class="form-control" rows="6" maxlength="255" '
                + 'placeholder="Catatan settle..." style="font-size:13px;width:100%;resize:vertical;">'
                + $('<div>').text($inp.val() || '').html() + '</textarea>'
                + '<small id="descPopupCount" class="d-block mt-1" style="font-size:11px;text-align:left;"></small>',
            width: '560px',
            showConfirmButton: false,
            showCloseButton: true,
            customClass: { closeButton: 'att-close-btn' },
            didOpen: function (popup) {
                var ta  = popup.querySelector('#descPopupInput');
                var inf = popup.querySelector('#descPopupCount');
                if (!ta) return;

                function hitung() {
                    var sisa = NOTE_MAX - ta.value.length;
                    inf.textContent = sisa <= 0
                        ? 'Batas ' + NOTE_MAX + ' karakter sudah habis — tidak bisa menambah teks lagi.'
                        : 'Sisa ' + sisa + ' karakter.';
                    inf.className = 'd-block mt-1 ' + (sisa <= 0 ? 'text-danger' : 'text-muted');
                }

                ta.addEventListener('input', hitung);
                hitung();
                ta.focus();
                ta.setSelectionRange(ta.value.length, ta.value.length);
            },
            willClose: function (popup) {
                var ta = popup.querySelector('#descPopupInput');
                if (ta) $inp.val(ta.value).trigger('input').trigger('change');
            }
        });
    });
    $('.realize-notes-input').each(function () { refreshNoteCount(this); });

    /* ── KETERANGAN SETTLEMENT ─────────────────────────────────────────────
       Kolomnya disembunyikan sampai user menekan "Tambah Keterangan", sama
       seperti area upload lampiran. */
    document.getElementById('btnAddSettleNote').addEventListener('click', function () {
        var area = document.getElementById('settleNoteArea');
        var kosong = document.getElementById('settleNoteEmpty');
        if (area) area.style.display = '';
        if (kosong) kosong.style.display = 'none';
        var ta = document.getElementById('settlementNotes');
        if (ta) ta.focus();
    });

    /* ── KEADAAN TOMBOL SUBMIT ──────────────────────────────────────────────
       Tombolnya abu-abu & mati selama syaratnya belum lengkap, lalu berubah
       jadi tombol aktif begitu semua baris siap — supaya jelas kapan
       settlement benar-benar boleh dikirim. */
    function updateSettleSubmitState() {
        var $rows   = realizeRows();
        var reasons = [];

        if (!$rows.length) {
            reasons.push('belum ada baris settlement (klik Auto Fill atau Tambah Baris)');
        } else {

            // Baris tambahan wajib memilih Sub COA (ditegakkan juga di server).
            var tanpaSub = [];
            $rows.each(function (i) {
                if (barisKosong($(this))) return;
                if (!String($(this).data('origin') || '') && !$(this).find('.realize-sub-select').val()) tanpaSub.push(i + 1);
            });
            if (tanpaSub.length) reasons.push('Sub COA baris no. ' + tanpaSub.join(', '));

            if (adaMelebihi() && ketKosong()) reasons.push('Keterangan (Settle melebihi pengajuan)');
            if (adaMelebihi() && lampiranGlobalKosong()) reasons.push('Lampiran Settlement (Settle melebihi pengajuan)');
        }

        var siap = reasons.length === 0;

        // Redup (disabled) selama belum lengkap, aktif (warna aksi utama TESYS)
        // begitu settlement benar-benar siap dikirim. "Simpan" TIDAK ikut
        // gerbang ini: draft boleh disimpan setengah jalan.
        $('#submitBtn').prop('disabled', !siap);

        $('#submitHint')
            .text(siap ? '' : 'Lengkapi: ' + reasons.join('; '))
            .toggle(!siap);
    }

    $(document).on('input change', '#realizeForm', updateSettleSubmitState);
    window.__updateSettleSubmitState = updateSettleSubmitState;
    updateSettleSubmitState();

    /* ── LAMPIRAN ITEM PER BARIS (drag & drop + paste + klik) ───────────────
       Pola sama seperti kolom "Lampiran Item" di form Cost Request. ── */
    /* ── LAMPIRAN: video ditolak, file bisa dibuka untuk dicek ─────────────
       Daftar accept hanya menyaring dialog "pilih file"; drag-drop dan paste
       bisa menyelipkan apa saja, jadi penyaringnya dipasang di sini. */
    var VIDEO_EXT = ['mp4','mov','avi','mkv','wmv','flv','webm','m4v','3gp','mpg','mpeg','ts','m2ts','ogv'];

    function isVideoFile(f) {
        if (!f) return false;
        if ((f.type || '').toLowerCase().indexOf('video/') === 0) return true;
        var ext = (f.name || '').split('.').pop().toLowerCase();
        return VIDEO_EXT.indexOf(ext) !== -1;
    }

    /** Buang file video dari daftar; kalau ada, tampilkan pop-up. */
    function tolakVideo(fileList) {
        var ok = [], tolak = [];
        Array.from(fileList || []).forEach(function (f) {
            (isVideoFile(f) ? tolak : ok).push(f);
        });

        if (tolak.length) {
            Swal.fire({
                icon: 'error',
                title: 'Lampiran tidak boleh video',
                html: 'File berikut tidak bisa dilampirkan:<br><strong>'
                    + tolak.map(function (f) { return $('<div>').text(f.name).html(); }).join('<br>')
                    + '</strong><br><br>Lampirkan foto (JPG/PNG) atau dokumen (PDF, DOC, XLS) saja.',
                confirmButtonColor: '#92232b'
            });
        }

        return ok;
    }

    /**
     * Tampilkan file yang BELUM terunggah di dalam pop-up — bukan tab baru,
     * yang sering diblokir browser dan membuat user keluar dari form.
     * Gambar dirender langsung, PDF di-embed, sisanya cukup tombol unduh.
     */
    function bukaFileLokal(file) {
        if (!file) return;

        var url   = URL.createObjectURL(file);
        var nama  = $('<div>').text(file.name).html();
        var tipe  = (file.type || '').toLowerCase();
        var ext   = (file.name || '').split('.').pop().toLowerCase();
        var isImg = tipe.indexOf('image/') === 0 || ['jpg','jpeg','png','gif','webp','bmp'].indexOf(ext) !== -1;
        var isPdf = tipe === 'application/pdf' || ext === 'pdf';

        var isi;
        if (isImg) {
            isi = '<img src="' + url + '" alt="' + nama + '" ' +
                  'style="max-width:100%;max-height:70vh;object-fit:contain;border-radius:6px;">';
        } else if (isPdf) {
            isi = '<iframe src="' + url + '" style="width:100%;height:70vh;border:1px solid var(--border);border-radius:6px;"></iframe>';
        } else {
            isi = '<p class="mb-2">Pratinjau tidak tersedia untuk jenis file ini.</p>' +
                  '<a href="' + url + '" download="' + nama + '" class="btn btn-sm btn-primary">' +
                  '<i class="fas fa-download mr-1"></i> Unduh untuk memeriksa</a>';
        }

        Swal.fire({
            title: nama,
            html: isi,
            width: isImg || isPdf ? '860px' : '520px',
            // Penutupnya cukup tanda X di kanan atas — tombol "Tutup" di bawah
            // hanya menambah tinggi pop-up tanpa fungsi baru.
            showConfirmButton: false,
            showCloseButton: true,
            customClass: { closeButton: 'att-close-btn' },
            // Blob baru dilepas setelah pop-up ditutup, kalau tidak gambarnya kosong.
            didClose: function () { URL.revokeObjectURL(url); }
        });
    }

    /** Pratinjau lampiran yang SUDAH tersimpan — pop-up, sama seperti file baru. */
    function bukaFileTersimpan(url, nama) {
        var ext   = (nama || '').split('.').pop().toLowerCase();
        var isImg = ['jpg','jpeg','png','gif','webp','bmp'].indexOf(ext) !== -1;
        var isPdf = ext === 'pdf';
        var judul = $('<div>').text(nama || 'Lampiran').html();

        var isi;
        if (isImg) {
            isi = '<img src="' + url + '" alt="' + judul + '" style="max-width:100%;max-height:70vh;object-fit:contain;border-radius:6px;">';
        } else if (isPdf) {
            isi = '<iframe src="' + url + '" style="width:100%;height:70vh;border:1px solid var(--border);border-radius:6px;"></iframe>';
        } else {
            isi = '<p class="mb-2">Pratinjau tidak tersedia untuk jenis file ini.</p>' +
                  '<a href="' + url + '" target="_blank" rel="noopener" class="btn btn-sm btn-primary">' +
                  '<i class="fas fa-external-link-alt mr-1"></i> Buka file</a>';
        }

        Swal.fire({
            title: judul,
            html: isi,
            width: isImg || isPdf ? '860px' : '520px',
            showConfirmButton: false,
            showCloseButton: true,
            customClass: { closeButton: 'att-close-btn' }
        });
    }

    $(document).on('click', '.att-lama-open', function (e) {
        e.preventDefault();
        bukaFileTersimpan($(this).data('url'), $(this).data('nama'));
    });

    /* Tombol × pada lampiran lama: tidak langsung menghapus file, hanya
       menandainya untuk dibuang saat settlement disubmit — bisa dibatalkan
       lagi dengan klik kedua. */
    $(document).on('click', '.att-lama-del', function () {
        // Lampiran item: tombolnya berada DI DALAM .att-lama. Lampiran global:
        // tombolnya di kolom sebelah, jadi dicari lewat barisnya.
        var $wrap = $(this).closest('.att-lama');
        if (!$wrap.length) $wrap = $(this).closest('tr').find('.att-lama').first();
        var $chk  = $wrap.find('input[type="checkbox"]');

        var hapus = !$chk.prop('checked');
        $chk.prop('checked', hapus);
        $wrap.toggleClass('att-lama-dibuang', hapus);
        $(this).attr('title', hapus ? 'Batalkan penghapusan' : 'Hapus lampiran ini');

        if (window.__updateSettleSubmitState) window.__updateSettleSubmitState();
    });

    function appendFilesToInput(input, fileList) {
        fileList = tolakVideo(fileList);
        if (!fileList.length) return;

        if (!fileList || !fileList.length) return;
        try {
            var dt = new DataTransfer();
            Array.from(input.files || []).forEach(function (f) { dt.items.add(f); });
            Array.from(fileList).forEach(function (f) { dt.items.add(f); });
            input.files = dt.files;
        } catch (e) {
            try { input.files = fileList; } catch (e2) {}
        }
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function renderDetailAttList(cell) {
        var input = cell.querySelector('.detail-att-input');
        var list  = cell.querySelector('.detail-att-list');
        var label = cell.querySelector('.detail-att-label');
        var files = Array.from(input.files || []);
        label.textContent = files.length ? (files.length + ' file') : 'Lampiran';
        list.innerHTML = files.map(function (f, idx) {
            return '<div class="text-muted"><i class="fas fa-file mr-1"></i>' +
                '<a href="#" class="detail-att-open" data-i="' + idx + '" title="Lihat lampiran">' +
                f.name.replace(/</g, '&lt;') + '</a>' +
                ' <i class="fas fa-times text-danger detail-att-del" data-i="' + idx + '" style="cursor:pointer;"></i></div>';
        }).join('');
        if (window.__updateSettleSubmitState) window.__updateSettleSubmitState();
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-detail-att');
        if (!btn) return;
        var real = btn.closest('.detail-att-cell').querySelector('.detail-att-input');
        var tmp  = document.createElement('input');
        tmp.type = 'file'; tmp.multiple = true; tmp.accept = real.accept;
        tmp.addEventListener('change', function () { appendFilesToInput(real, tmp.files); });
        tmp.click();
    });

    // Klik nama file -> buka isinya untuk dicek.
    document.addEventListener('click', function (e) {
        var a = e.target.closest('.detail-att-open');
        if (!a) return;
        e.preventDefault();
        var cell  = a.closest('.detail-att-cell');
        var input = cell.querySelector('.detail-att-input');
        bukaFileLokal((input.files || [])[parseInt(a.dataset.i, 10)]);
    });

    document.addEventListener('click', function (e) {
        var x = e.target.closest('.detail-att-del');
        if (!x) return;
        var cell  = x.closest('.detail-att-cell');
        var input = cell.querySelector('.detail-att-input');
        var idx   = parseInt(x.dataset.i, 10);
        try {
            var dt = new DataTransfer();
            Array.from(input.files).forEach(function (f, i) { if (i !== idx) dt.items.add(f); });
            input.files = dt.files;
        } catch (err) {}
        renderDetailAttList(cell);
    });

    document.addEventListener('change', function (e) {
        if (!e.target.classList.contains('detail-att-input')) return;
        renderDetailAttList(e.target.closest('.detail-att-cell'));
    });

    document.addEventListener('dragover', function (e) {
        var cell = e.target.closest ? e.target.closest('.detail-att-cell') : null;
        if (!cell) return;
        e.preventDefault();
        cell.style.background = 'var(--warning-bg)';
    });
    document.addEventListener('dragleave', function (e) {
        var cell = e.target.closest ? e.target.closest('.detail-att-cell') : null;
        if (cell) cell.style.background = '';
    });
    document.addEventListener('drop', function (e) {
        var cell = e.target.closest ? e.target.closest('.detail-att-cell') : null;
        if (!cell) return;
        e.preventDefault();
        cell.style.background = '';
        var f = e.dataTransfer ? e.dataTransfer.files : null;
        if (f && f.length) appendFilesToInput(cell.querySelector('.detail-att-input'), f);
    });

    document.addEventListener('paste', function (e) {
        var cell = (document.activeElement && document.activeElement.closest)
            ? document.activeElement.closest('.detail-att-cell') : null;
        if (!cell) return;
        var cd = e.clipboardData || window.clipboardData;
        if (!cd || !cd.items) return;
        var picked = [];
        for (var i = 0; i < cd.items.length; i++) {
            if (cd.items[i].kind === 'file') {
                var f = cd.items[i].getAsFile();
                if (f) {
                    var ext = (f.type.split('/')[1] || 'png');
                    picked.push(new File([f], 'pasted-' + Date.now() + '.' + ext, { type: f.type }));
                }
            }
        }
        if (picked.length) {
            e.preventDefault();
            e.stopImmediatePropagation();
            appendFilesToInput(cell.querySelector('.detail-att-input'), picked);
        }
    }, true);

    /* ── BUKTI SETTLEMENT (GLOBAL) — drag & drop + paste, multi-file ────────
       Pola sama seperti "Lampiran Tambahan / Pendukung" di form Cost Request. ── */
    var attIdx = 0;

    /** Area upload disembunyikan sampai user menekan "Tambah Lampiran". */
    function showRealAttUploadArea() {
        var area = document.getElementById('realAttUploadArea');
        if (area) area.style.display = '';
    }

    /** Daftar file hanya tampil kalau memang sudah ada barisnya. */
    function refreshRealAttTable() {
        var wrap  = document.getElementById('realAttTableWrap');
        var tbody = document.getElementById('realAttachmentBody');
        if (wrap && tbody) wrap.style.display = tbody.children.length ? '' : 'none';
    }

    document.getElementById('btnAddRealAttachment').addEventListener('click', function () {
        // Cukup buka area upload-nya; baris file baru muncul setelah ada file
        // yang benar-benar dipilih/di-drop/di-paste.
        showRealAttUploadArea();
        var zone = document.getElementById('dropZoneReal');
        if (zone) zone.focus();
    });

    function addAttachmentRow(file) {
        if (file && !tolakVideo([file]).length) return;   // video: ditolak, baris tidak dibuat

        showRealAttUploadArea();
        var i     = attIdx++;
        var tbody = document.getElementById('realAttachmentBody');
        var tr    = document.createElement('tr');
        tr.innerHTML =
            '<td class="text-center align-middle row-no"></td>' +
            '<td><input type="file" name="realization_attachments[' + i + ']" class="form-control-file real-att-input">' +
                '<div class="real-att-preview mt-1" style="font-size:11px;"></div></td>' +
            '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-real-att" title="Hapus lampiran" aria-label="Hapus lampiran"><i class="fas fa-trash" aria-hidden="true"></i></button></td>';
        tbody.appendChild(tr);

        if (file) {
            var input = tr.querySelector('input[type="file"]');
            try {
                var dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
            } catch (err) {}
        }

        renderRealAttPreview(tr.querySelector('.real-att-input'));
        renumberAttachments();
    }

    /** Nama file pada baris lampiran global — bisa diklik untuk dicek. */
    function renderRealAttPreview(input) {
        if (!input) return;
        var box = input.parentElement.querySelector('.real-att-preview');
        var f   = (input.files || [])[0];
        if (!box) return;

        box.innerHTML = f
            ? '<i class="fas fa-file mr-1"></i><a href="#" class="real-att-open" title="Lihat lampiran">'
                + $('<div>').text(f.name).html() + '</a>'
            : '';
    }

    // File dipilih lewat input barisnya sendiri: tolak video & perbarui tautan.
    document.addEventListener('change', function (e) {
        var input = e.target.closest('.real-att-input');
        if (!input) return;

        if ((input.files || []).length && !tolakVideo(input.files).length) {
            input.value = '';
        }
        renderRealAttPreview(input);
    });

    document.addEventListener('click', function (e) {
        var a = e.target.closest('.real-att-open');
        if (!a) return;
        e.preventDefault();
        var input = a.closest('td').querySelector('.real-att-input');
        bukaFileLokal((input.files || [])[0]);
    });

    function renumberAttachments() {
        document.querySelectorAll('#realAttachmentBody .row-no').forEach(function (el, i) { el.textContent = i + 1; });
        refreshRealAttTable();
        if (window.__updateSettleSubmitState) window.__updateSettleSubmitState();
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('.btn-remove-real-att')) {
            e.target.closest('tr').remove();
            renumberAttachments();
        }
    });

    (function () {
        var zone   = document.getElementById('dropZoneReal');
        var input  = document.getElementById('dropZoneInputReal');
        var browse = document.getElementById('dropZoneBrowseReal');

        zone.addEventListener('click', function () { input.click(); });
        browse.addEventListener('click', function (e) { e.stopPropagation(); input.click(); });
        // role="button" harus bisa diaktifkan lewat papan ketik (Enter / Spasi).
        browse.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                e.stopPropagation();
                input.click();
            }
        });

        input.addEventListener('change', function () {
            Array.from(this.files).forEach(function (f) { addAttachmentRow(f); });
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
            if (files && files.length) Array.from(files).forEach(function (f) { addAttachmentRow(f); });
        });

        function handlePasteReal(e) {
            var items = (e.clipboardData || window.clipboardData) ? (e.clipboardData || window.clipboardData).items : null;
            if (!items) return;
            var found = false;
            for (var i = 0; i < items.length; i++) {
                if (items[i].kind === 'file') {
                    var f = items[i].getAsFile();
                    if (f) {
                        var ext = (f.type.split('/')[1] || 'png');
                        addAttachmentRow(new File([f], 'pasted-image-' + Date.now() + '.' + ext, { type: f.type }));
                        found = true;
                    }
                }
            }
            if (found) e.preventDefault();
        }
        zone.addEventListener('paste', handlePasteReal);
        document.addEventListener('paste', function (e) {
            var tag = (document.activeElement && document.activeElement.tagName) || '';
            if (tag === 'INPUT' || tag === 'TEXTAREA') return;
            if (document.activeElement && document.activeElement.closest && document.activeElement.closest('.detail-att-cell')) return;
            handlePasteReal(e);
        });
    })();

    /* ── DOWNLOAD TEMPLATE & IMPORT EXCEL — Settlement per Item ─────────────
       Settlement Custom COA (25 Okt 2026): baris file dicocokkan ke baris
       form lewat Request Detail ID + Budget Detail ID (Sub COA). Baris file
       yang tidak punya pasangan di form ditambahkan sebagai baris baru —
       pecahan item (Request Detail ID diisi) atau baris tambahan (kosong).
       Baris form yang tidak ada di file dibiarkan apa adanya. ── */
    $('#btnImportSettlementExcel').on('click', function () {
        $('#importSettlementFileInput').val('').trigger('click');
    });

    $('#importSettlementFileInput').on('change', function () {
        var file = this.files[0];
        if (!file) return;

        var formData = new FormData();
        formData.append('file', file);
        formData.append('_token', $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}');

        Swal.fire({
            title: 'Memproses file...', text: 'Mohon tunggu sebentar.',
            allowOutsideClick: false, didOpen: function () { Swal.showLoading(); }
        });

        $.ajax({
            url: '{{ url("/budget-request/" . $budgetRequest->id . "/settlement-import-preview") }}',
            type: 'POST', data: formData, processData: false, contentType: false
        }).done(function (res) {
            Swal.close();

            if (res.errors && res.errors.length) {
                Swal.fire({
                    icon: 'warning', title: 'Sebagian data bermasalah',
                    html: '<div style="text-align:left;max-height:250px;overflow:auto;">' +
                          res.errors.map(function (er) { return '<div>• ' + er + '</div>'; }).join('') +
                          '</div>',
                    confirmButtonColor: '#92232b'
                });
            }

            if (res.rows && res.rows.length) {
                var applied = 0, terpakai = [];
                res.rows.forEach(function (r) {
                    var origin = r.request_detail_id ? String(r.request_detail_id) : '';
                    var sub    = r.budget_detail_id ? String(r.budget_detail_id) : '';
                    var bebas  = realizeRows().filter(function () {
                        return terpakai.indexOf(this) === -1 && String($(this).data('origin') || '') === origin;
                    });
                    // Pasangan terbaik: item & Sub COA sama; lalu item yang sama saja
                    // (hanya untuk item asal — baris tambahan wajib Sub COA sama).
                    var $row = bebas.filter(function () { return String($(this).find('.realize-sub-select').val() || '') === sub; }).first();
                    if (!$row.length && origin) $row = bebas.first();

                    if (!$row.length) {
                        var $asal = origin
                            ? realizeRows().filter(function () { return String($(this).data('origin') || '') === origin; })
                            : $();
                        $row = $asal.length
                            ? buatBaris({ $from: $asal.first(), $after: $asal.last(), sub: sub, amount: r.amount })
                            : buatBaris({ sub: sub, amount: r.amount });
                    } else {
                        $row.find('.realize-amount-input').val(fmtRp(String(r.amount)));
                        $row.find('.realize-qty-input').val(1);
                        if (sub) $row.find('.realize-sub-select').val(sub).trigger('tss:sync');
                    }

                    terpakai.push($row[0]);
                    applied++;
                });
                recalcTotalRealized();
                if (window.__updateSettleSubmitState) window.__updateSettleSubmitState();

                if (applied > 0) {
                    Swal.fire({
                        icon: 'success', title: 'Import berhasil',
                        text: applied + ' baris Settle diperbarui dari file.',
                        confirmButtonColor: 'var(--success)', timer: 2000, showConfirmButton: false
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Tidak ada baris yang cocok', text: 'Pastikan file berasal dari Download Template CR ini.' });
                }
            } else if (!res.errors || !res.errors.length) {
                Swal.fire({ icon: 'error', title: 'Tidak ada data', text: 'File tidak berisi baris data yang valid.' });
            }
        }).fail(function (xhr) {
            Swal.close();
            var msg = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'Gagal memproses file. Pastikan format Excel sesuai template.';
            Swal.fire({ icon: 'error', title: 'Import Gagal', text: msg });
        });
    });

    /* ── CATATAN PERUBAHAN PER BARIS (ikon Note) ─────────────────────────
       Nilainya disimpan di hidden input, ditampilkan sebagai cuplikan kecil di
       bawah ikon supaya terlihat baris mana yang sudah diisi tanpa membuka
       pop-up satu per satu. */
    var CHANGE_NOTE_MAX = 500;

    function refreshNoteItem($cell) {
        var val  = ($cell.find('.realize-change-note').val() || '').trim();
        var $btn = $cell.find('.btn-note-item');
        $btn.toggleClass('has-note', !!val);
        $cell.find('.note-item-preview').text(val ? (val.length > 28 ? val.slice(0, 28) + '…' : val) : '');
    }

    $('#realizeBody .realize-note-cell').each(function () { refreshNoteItem($(this)); });

    $(document).on('click', '.btn-note-item', function () {
        var $cell = $(this).closest('.realize-note-cell');
        var $inp  = $cell.find('.realize-change-note');
        var $row  = $cell.closest('tr');

        Swal.fire({
            title: 'Catatan Perubahan',
            html: '<textarea id="noteItemInput" class="form-control" rows="5" maxlength="' + CHANGE_NOTE_MAX + '" '
                + 'placeholder="Alasan perubahan dari Description Request ke Description Settle..." '
                + 'style="font-size:13px;width:100%;resize:vertical;">'
                + $('<div>').text($inp.val() || '').html() + '</textarea>'
                + '<small id="noteItemCount" class="d-block mt-1 text-muted" style="font-size:11px;text-align:left;"></small>',
            width: '560px',
            showCancelButton: true,
            confirmButtonText: 'Simpan',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#42627d',
            cancelButtonColor: '#55595e',
            didOpen: function (popup) {
                var ta  = popup.querySelector('#noteItemInput');
                var inf = popup.querySelector('#noteItemCount');
                if (!ta) return;
                function hitung() { inf.textContent = 'Sisa ' + (CHANGE_NOTE_MAX - ta.value.length) + ' karakter.'; }
                ta.addEventListener('input', hitung);
                hitung();
                ta.focus();
            },
            preConfirm: function () {
                var ta = Swal.getPopup().querySelector('#noteItemInput');
                var v  = (ta.value || '').trim();
                return v;
            }
        }).then(function (res) {
            if (!res.isConfirmed) return;
            $inp.val(res.value || '');
            refreshNoteItem($cell);
        });
    });

    /* Kelengkapan baris — dipakai SAMA PERSIS oleh Submit maupun Simpan
       (draft), supaya data yang tersimpan tidak pernah setengah lengkap. */
    function cekKelengkapanBaris() {
        // Keterangan & Lampiran Settlement (header) wajib bila total Settle melebihi nilai pengajuan.
        var melebihi = adaMelebihi();
        var missKet  = melebihi && ketKosong();
        var missLamp = melebihi && lampiranGlobalKosong();
        // Baris tambahan wajib memilih Sub COA.
        var missSub = [];
        realizeRows().each(function (idx) {
            if (barisKosong($(this))) return;
            if (!String($(this).data('origin') || '') && !$(this).find('.realize-sub-select').val()) missSub.push(idx + 1);
        });
        return { missKet: missKet, missLamp: missLamp, missSub: missSub };
    }

    /** true kalau ada yang kurang (dan sudah ditampilkan pop-up-nya). */
    function peringatkanKelengkapan(cek) {
        if (cek.missSub.length) {
            Swal.fire({
                icon: 'warning',
                title: 'Sub COA belum dipilih',
                text: 'Baris tambahan wajib memilih Sub COA. Baris: no. ' + cek.missSub.join(', ') + '.',
                confirmButtonColor: '#92232b'
            });
            return true;
        }
        if (cek.missKet || cek.missLamp) {
            var kurang = [];
            if (cek.missKet)  kurang.push('Keterangan');
            if (cek.missLamp) kurang.push('Lampiran Settlement');
            Swal.fire({
                icon: 'warning',
                title: 'Data header belum lengkap',
                text: 'Total Settle melebihi nilai pengajuan, sehingga ' + kurang.join(' dan ') + ' wajib diisi.',
                confirmButtonColor: '#92232b'
            });
            return true;
        }
        return false;
    }

    /* SUBMIT — mengajukan settlement, CR pindah ke REALIZED. */
    form.on('submit', function (e) {
        e.preventDefault();

        if (peringatkanKelengkapan(cekKelengkapanBaris())) return;

        Swal.fire({
            title: 'Submit Settlement?',
            text: 'Konfirmasi pengajuan settlement untuk Cost Request ini.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#42627d',
            cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Submit',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) form.unbind('submit').submit();
        });
    });

    /* SIMPAN — menyimpan draft, status CR TIDAK berubah. Data boleh belum
       lengkap (lampiran item / Note) — kelengkapannya baru diwajibkan saat
       Submit. Form hanya diarahkan ke route simpan-draft sebelum dikirim. */
    $('#saveDraftBtn').on('click', function () {
        // Draft tanpa satu baris pun ditolak server (realizations wajib minimal 1).
        if (!realizeRows().length) {
            Swal.fire({ icon: 'info', title: 'Belum ada baris', text: 'Tambahkan minimal satu baris settlement (Auto Fill atau Tambah Baris) sebelum menyimpan.', confirmButtonColor: '#92232b' });
            return;
        }
        var cek   = cekKelengkapanBaris();
        // Sub COA baris tambahan wajib juga saat simpan draft (server menolak).
        if (cek.missSub.length) {
            peringatkanKelengkapan({ missSub: cek.missSub, missKet: false, missLamp: false });
            return;
        }
        var kurang = [];
        if (cek.missKet) kurang.push('Keterangan');
        if (cek.missLamp) kurang.push('Lampiran Settlement');

        Swal.fire({
            title: 'Simpan Draft Settlement?',
            text: kurang.length
                ? 'Belum lengkap (' + kurang.join('; ') + '), tapi tetap bisa disimpan. Status CR belum berubah.'
                : 'Data tersimpan tanpa mengajukan — status CR belum berubah.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#42627d',
            cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Simpan',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (!result.isConfirmed) return;
            form.attr('action', $('#saveDraftBtn').data('action'));
            form.unbind('submit').get(0).submit();
        });
    });
});
</script>
@endpush

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
<style>
.cr-copy-code { background:none; border:0; padding:0; margin:0; font:inherit; color:inherit; cursor:pointer; }
.cr-copy-code:hover { color:var(--accent); text-decoration:underline; }
.cr-copy-code:focus-visible { outline:none; box-shadow:0 0 0 3px var(--accent-ring); border-radius:var(--radius-sm); }
.text-var-pos { color: var(--success); }
.text-var-neg { color: var(--danger); }

/* ── Tabel Settlement per Item ── */
#tblSettlement td { vertical-align: middle; padding: 6px 10px; }
#tblSettlement tbody tr:hover { background: var(--surface-2); }
#tblSettlement .form-control-sm { font-size: 12px; }
#tblSettlement .realize-amount-input { min-width: 92px; font-weight: 600; }
#tblSettlement .realize-notes-input { min-width: 180px; width: 100%; }
#tblSettlement .realize-desc-request-cell { max-width: 260px; white-space: normal; word-break: break-word; }
#tblSettlement tfoot th { font-size: 11px; color: var(--text); border-top: 2px solid var(--border); padding: 8px 10px; }

/* ── Sub COA per baris (Settlement Custom COA) ── */
#tblSettlement .tss-btn { font-size: 12px; padding: 2px 8px; min-width: 200px; text-align: left; }

/* ── Lampiran Item per baris (dropzone mini) ── */
.detail-att-cell { outline: none; transition: background .15s ease; padding: 6px 8px; }
.detail-att-cell:focus-within { background: var(--warning-bg); }

/* Kotak deskripsi + ikon "lihat penuh" di ujung kanannya. Latarnya penuh
   supaya teks panjang tidak lewat di bawah ikon. */
.desc-wrap { position: relative; }
.desc-wrap .realize-notes-input { padding-right: 30px; }
.desc-wrap .desc-expand {
    position: absolute; top: 2px; right: 2px; bottom: 2px; width: 26px;
    display: flex; align-items: center; justify-content: center;
    background: var(--surface); border: none; border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
    line-height: 1; color: var(--text-faint); cursor: pointer; font-size: 11px; z-index: 2;
}
.desc-wrap .desc-expand:hover { color: var(--accent); }

/* Baris yang catatan perubahannya sudah terisi diberi cincin tipis; isinya
   terlihat lewat cuplikan teks di bawah tombol. */
.btn-note-item.has-note { box-shadow: 0 0 0 3px var(--accent-ring); }

/* Lampiran lama yang ditandai untuk dibuang saat submit. */
.att-lama-dibuang { text-decoration: line-through; opacity: .55; }

/* Tombol X pratinjau lampiran: tanpa kotak/latar, merah saja. */
.swal2-close.att-close-btn {
    color: var(--danger);
    background: transparent;
    box-shadow: none;
    border: none;
    outline: none;
}
.swal2-close.att-close-btn:hover,
.swal2-close.att-close-btn:focus {
    color: var(--danger);
    background: transparent;
    box-shadow: none;
    outline: none;
}

/* ── Bukti Settlement (Global) dropzone ── */
.att-dropzone {
    border: 2px dashed var(--border); border-radius: var(--radius-md); padding: 28px 20px; text-align: center;
    color: var(--text-dim); cursor: pointer; background: var(--surface-2);
}
.att-dropzone:hover { border-color: var(--accent); background: var(--accent-tint); }
.att-dropzone.dragover { border-color: var(--accent); background: var(--accent-tint); color: var(--accent-dark); }
</style>
@endpush
