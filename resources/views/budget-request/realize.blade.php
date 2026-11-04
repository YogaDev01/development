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
                            <x-tw.button id="btnResetSettle" variant="secondary" size="sm" class="tw:max-sm:min-h-12" title="Kembalikan baris settlement ke rincian pengajuan awal"><i class="fas fa-undo" aria-hidden="true"></i> Reset</x-tw.button>
                            <x-tw.button id="btnViewRequest" variant="secondary" size="sm" class="tw:max-sm:min-h-12"><i class="fas fa-eye" aria-hidden="true"></i> View Request</x-tw.button>
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
                            {{-- Terisi otomatis 1:1 dari item pengajuan; baris pertama tiap item tidak bisa dihapus. --}}
                            @php $sudahPrimer = []; @endphp
                            @forelse($settleRows as $i => $row)
                                @php
                                    $detailRow = $row['detail'];
                                    $isPrimary = $detailRow && ! isset($sudahPrimer[$detailRow->id]);
                                    if ($detailRow) $sudahPrimer[$detailRow->id] = true;
                                @endphp
                                @include('budget-request._realize_row', [
                                    'k'           => $i,
                                    'rowId'       => $row['id'],
                                    'detail'      => $detailRow,
                                    'isPrimary'   => $isPrimary,
                                    'orphanId'    => $row['orphan_detail_id'] ?? null,
                                    'bdId'        => $row['budget_detail_id'],
                                    // Baris belum tersimpan: deskripsi awal = deskripsi item pengajuan.
                                    'notes'       => $row['id'] ? $row['notes'] : ($row['notes'] ?? \Illuminate\Support\Str::limit((string) $detailRow?->notes, 255, '')),
                                    'changeNote'  => $row['change_note'],
                                    'qty'         => $row['qty'] ?? null,
                                    'amount'      => $row['price'] ?? $row['amount'],
                                    'attachments' => $row['attachments'],
                                ])
                            @empty
                            <tr class="realize-empty">
                                <td colspan="8" class="text-center tw:py-6 tw:text-data tw:text-text-dim">CR ini tidak punya item pengajuan. Klik <strong>Tambah Baris</strong> untuk menambah baris settlement.</td>
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
                    'k' => '__K__', 'rowId' => null, 'detail' => null, 'isPrimary' => false, 'orphanId' => null, 'bdId' => null,
                    'notes' => null, 'changeNote' => null, 'qty' => 1, 'amount' => 0, 'attachments' => collect(),
                ])
            </template>
            {{-- Baris default (tombol Reset): satu baris per item pengajuan (bukan TAKEOUT),
                 sama dengan isi awal settlementFormRows() sebelum ada simpanan. --}}
            <template id="realizeDefaultTpl">
                @foreach($budgetRequest->details->reject(fn ($d) => $d->isTakenOut())->values() as $pos => $d)
                    @include('budget-request._realize_row', [
                        'k' => 'd' . $pos, 'rowId' => null, 'detail' => $d, 'isPrimary' => true, 'orphanId' => null,
                        'bdId' => $d->budget_detail_id, 'notes' => \Illuminate\Support\Str::limit((string) $d->notes, 255, ''),
                        'changeNote' => null, 'qty' => (int) $d->qty, 'amount' => (int) $d->amount, 'attachments' => collect(),
                    ])
                @endforeach
            </template>
            <script type="application/json" id="realizeSubItems">@json($subItems->keyBy('id'))</script>
            {{-- Rincian pengajuan (item yang ikut, bukan takeout) untuk "View Request". --}}
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

            {{-- BUKTI TRANSFER PENGEMBALIAN — hanya tampil bila total Settle di
                 bawah nilai pengajuan (ada sisa dana dikembalikan). Opsional;
                 tersimpan sebagai lampiran global berawalan "bukti-transfer-". --}}
            <x-tw.card id="refundProofCard" style="display:none;">
                <x-slot:header>
                    <div class="tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-3">
                        <div role="heading" aria-level="2" class="tw:flex tw:items-center tw:gap-2">
                            <i class="fas fa-money-check-alt" aria-hidden="true"></i> Bukti Transfer Pengembalian
                        </div>
                        <div class="tw:text-data tw:text-text-dim">Sisa dana dikembalikan: <strong id="refundAmount" class="tw:tabular-nums tw:text-text">0</strong></div>
                    </div>
                </x-slot:header>

                <div id="dropZoneRefund" class="att-dropzone" tabindex="0">
                    <i class="fas fa-cloud-upload-alt fa-2x mb-2 d-block" aria-hidden="true"></i>
                    <div class="tw:text-control tw:text-text">
                        Drag &amp; drop bukti transfer ke sini, paste gambar (Ctrl+V), atau
                        <span class="tw:cursor-pointer tw:font-label tw:text-accent-text tw:underline" id="dropZoneBrowseRefund" role="button" tabindex="0">klik untuk pilih</span>
                    </div>
                    <div class="tw:mt-1 tw:text-caption tw:text-text-dim">Bisa beberapa file sekaligus</div>
                </div>
                <input type="file" name="refund_proofs[]" id="refundProofInput" multiple accept=".pdf,.jpg,.jpeg,.png" style="display:none;">
                {{-- Bukti transfer dari simpanan sebelumnya (draft / ditolak). Klik × untuk
                     menandai hapus — baru berlaku saat disimpan, bisa dibatalkan. --}}
                @php $refundLama = $budgetRequest->realizationAttachments->filter(fn ($a) => str_starts_with((string) $a->original_name, 'bukti-transfer-')); @endphp
                @if($refundLama->isNotEmpty())
                <div class="refund-proof-list">
                    @foreach($refundLama as $att)
                    <div class="refund-proof-item att-lama">
                        <input type="checkbox" name="delete_realization_global_attachments[]" value="{{ $att->id }}" class="del-realize-att-global d-none">
                        <i class="fas fa-file mr-1" aria-hidden="true"></i>
                        <a href="#" class="att-lama-open" data-url="{{ asset('storage/' . $att->path) }}" data-nama="{{ $att->original_name }}">{{ $att->original_name }}</a>
                        <span class="tw:text-caption tw:text-text-dim">({{ $att->size_formatted }}, tersimpan)</span>
                        <button type="button" class="refund-proof-del att-lama-del" title="Hapus lampiran ini" aria-label="Hapus {{ $att->original_name }}"><i class="fas fa-times" aria-hidden="true"></i></button>
                    </div>
                    @endforeach
                </div>
                @endif
                <div id="refundProofList" class="refund-proof-list"></div>

                <x-slot:footer>
                    <i class="fas fa-info-circle tw:mr-1" aria-hidden="true"></i>
                    Format: PDF, JPG, PNG. Maks 10 MB per file. Bukti belanja dilampirkan per baris di kolom Lampiran Item.
                </x-slot:footer>
            </x-tw.card>

            {{-- LOG HISTORY NOTE — opsional, tersimpan di settlement_notes dan ikut
                 tercatat di Log CR bersama rincian settlement yang disubmit. Kolomnya
                 baru muncul setelah tombolnya diklik. --}}
            <x-tw.card>
                <x-slot:header>
                    <div class="tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-3">
                        <div role="heading" aria-level="2" class="tw:flex tw:items-center tw:gap-2">
                            <i class="fas fa-comment-dots" aria-hidden="true"></i> Log History Note
                            <span class="settle-optional tw:text-caption tw:text-text-dim">(opsional)</span>
                        </div>
                        <x-tw.button id="btnAddSettleNote" size="sm" class="tw:max-sm:min-h-12"><i class="fas fa-plus" aria-hidden="true"></i> Tambah Note</x-tw.button>
                    </div>
                </x-slot:header>

                @php $ketAwal = old('settlement_notes', $budgetRequest->settlement_notes ?? ''); @endphp
                <div id="settleNoteArea" style="{{ filled($ketAwal) ? '' : 'display:none;' }}">
                    <x-tw.textarea name="settlement_notes" id="settlementNotes" rows="3" maxlength="1000" :value="$ketAwal"
                                   placeholder="Mis. nota hotel atas nama rekan kerja, selisih karena kenaikan tarif, dsb."
                                   hint="Note ini tersimpan di data Cost Request dan ikut tercatat di Log History. Maks 1000 karakter." />
                </div>
                <p id="settleNoteEmpty" style="{{ filled($ketAwal) ? 'display:none;' : '' }}" class="tw:m-0 tw:text-data tw:leading-body tw:text-text-dim">
                    Belum ada note. Klik <strong>Tambah Note</strong> kalau settlement ini perlu penjelasan di Log History.
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

    /* Lampiran Item wajib untuk baris bernilai Settle > 0 (4 Nov 2026; ditegakkan
       juga di server saat Submit). Lampiran tersimpan yang dicentang hapus tidak dihitung. */
    function barisTanpaLampiran($row) {
        if (rowTotal($row) <= 0) return false;
        var baru = ($row.find('.detail-att-input')[0] || {}).files;
        if (baru && baru.length) return false;
        return !$row.find('.del-realize-att').filter(function () { return !this.checked; }).length;
    }

    function nomorTanpaLampiran() {
        var no = [];
        realizeRows().each(function (i) {
            var kurang = barisTanpaLampiran($(this));
            $(this).find('.detail-att-cell').toggleClass('att-wajib-kurang', kurang);
            $(this).find('.att-wajib-badge').toggle(kurang);
            // Tooltip penjelasan di tombol Lampiran selama masih wajib & kosong.
            $(this).find('.detail-att-drop').attr('title', kurang
                ? 'Lampiran wajib karena nilai Settle baris ini lebih dari 0. Klik, drag & drop, atau paste bukti belanja sebelum Submit.'
                : 'Tambah lampiran (klik, drag & drop, atau paste)');
            if (kurang) no.push(i + 1);
        });
        return no;
    }

    /* Settle satu baris = Harga Satuan x Qty. */
    function rowTotal($row) {
        return parseRp($row.find('.realize-amount-input').val())
            * Math.max(1, parseInt($row.find('.realize-qty-input').val(), 10) || 1);
    }

    /* Badge "Sub Utama" / "Sub Pecahan" — hanya untuk item asal yang sedang
       dipecah (>1 baris terkunci dengan item asal yang sama). */
    function refreshSplitBadges() {
        var jumlah = {};
        function asal($r) { return $r.find('.realize-sub-locked').length ? String($r.data('origin') || '') : ''; }
        realizeRows().each(function () { var o = asal($(this)); if (o) jumlah[o] = (jumlah[o] || 0) + 1; });
        realizeRows().each(function () {
            var o = asal($(this));
            $(this).find('.realize-split-badge').toggle(!!o && jumlah[o] > 1);
        });
    }

    /* Harga Satuan 0 -> Qty tampil 0 & terkunci; Harga > 0 -> Qty bisa diubah lagi
       (kembali ke nilai sebelumnya, minimal 1). Input dimatikan (disabled) supaya
       tidak terkirim: server membaca qty kosong sebagai 1, Settle tetap 0. */
    function syncQtyLock($row) {
        var $qty = $row.find('.realize-qty-input');
        if (parseRp($row.find('.realize-amount-input').val()) <= 0) {
            if (!$qty.prop('disabled')) $qty.data('qty-prev', $qty.val());
            $qty.val('0').prop('disabled', true).attr('title', 'Qty terkunci 0 karena Harga Satuan 0. Isi Harga Satuan untuk mengubah Qty.');
        } else if ($qty.prop('disabled')) {
            $qty.prop('disabled', false).removeAttr('title')
                .val(Math.max(1, parseInt($qty.data('qty-prev'), 10) || 1));
        }
    }

    function recalcTotalRealized() {
        refreshSplitBadges();
        realizeRows().each(function () { syncQtyLock($(this)); });
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

        // Form bukti transfer hanya bila total Settle di bawah nilai pengajuan.
        // Input dimatikan saat tersembunyi supaya berkasnya tidak ikut terkirim.
        var kurangDariRequest = totalSettle < REQ_TOTAL;
        $('#refundProofCard').toggle(kurangDariRequest);
        $('#refundProofInput').prop('disabled', !kurangDariRequest);
        $('#refundAmount').text(moneyFmt(Math.max(0, totalVariance)));

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
        // Baris item pengajuan: Sub COA terkunci (hidden input), bukan <select>.
        if (!$row.find('select.realize-sub-select').length) return;
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
            // Pecahan item asal: ikut item asalnya, Settle 0. Sub COA DIKUNCI
            // ke Sub COA item asal (4 Nov 2026) — <select> diganti hidden input
            // + tampilan terkunci yang sama dengan baris sumbernya.
            var $f = opts.$from;
            $row.attr('data-origin', $f.data('origin')).attr('data-origin-sub', $f.data('origin-sub'))
                .attr('data-req', $f.data('req')).attr('data-qty', $f.data('qty'));
            $row.data('origin', $f.data('origin')).data('origin-sub', $f.data('origin-sub'))
                .data('req', $f.data('req')).data('qty', $f.data('qty'));
            $row.find('.realize-origin-input').val($f.data('origin'));
            $row.find('.realize-origin-label').remove();
            $row.find('.btn-split-row').show();
            $row.find('.realize-qty-input').val($f.data('qty') || 1);
            var $sel = $row.find('select.realize-sub-select');
            $('<input type="hidden" class="realize-sub-select">')
                .attr('name', $sel.attr('name'))
                .val(String($f.data('origin-sub') || ''))
                .insertBefore($sel);
            $f.find('.realize-sub-locked').first().clone().insertBefore($sel);
            $sel.remove();
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

    /* ── VIEW REQUEST: rincian pengajuan lengkap (read-only) ──
       Modal lebar (hampir selebar layar); COA & Sub COA digabung satu kolom,
       angka tidak terpotong baris, header tabel menempel saat discroll. */
    $('#btnViewRequest').on('click', function () {
        var ids = Object.keys(REQ_ITEMS), total = 0, totalQty = 0;
        var body = ids.map(function (id, i) {
            var it = REQ_ITEMS[id]; total += it.total; totalQty += parseFloat(it.qty) || 0;
            return '<tr>'
                + '<td class="vr-no">' + (i + 1) + '</td>'
                + '<td class="vr-coa"><div class="vr-coa-name">' + esc(it.coa) + '</div><div class="vr-sub">' + esc(it.sub_coa) + '</div></td>'
                + '<td class="vr-desc">' + esc(it.desc || '-') + '</td>'
                + '<td class="vr-num vr-center">' + esc(String(it.qty).replace('.', ',')) + '</td>'
                + '<td class="vr-num">' + moneyFmt(it.price) + '</td>'
                + '<td class="vr-num vr-strong">' + moneyFmt(it.total) + '</td>'
                + '</tr>';
        }).join('');
        Swal.fire({
            title: 'Rincian Pengajuan — {{ $budgetRequest->code }}',
            html: '<div class="vr-meta"><span>' + ids.length + ' item</span><span>Total Request <strong>' + moneyFmt(REQ_TOTAL) + '</strong></span></div>'
                + '<div class="vr-wrap"><table class="vr-table">'
                + '<thead><tr><th class="vr-no">No</th><th>COA / Sub COA</th><th>Deskripsi</th><th class="vr-center">Qty</th><th class="vr-num">Harga Satuan</th><th class="vr-num">Total</th></tr></thead>'
                + '<tbody>' + (body || '<tr><td colspan="6" class="vr-center vr-empty">Tidak ada item</td></tr>') + '</tbody>'
                + '<tfoot><tr><th colspan="3" class="vr-num">Total</th><th class="vr-num vr-center">' + esc(String(totalQty).replace('.', ',')) + '</th><th></th><th class="vr-num">' + moneyFmt(total) + '</th></tr></tfoot>'
                + '</table></div>',
            width: 'min(1280px, 94vw)',
            showConfirmButton: false,
            showCloseButton: true,
            customClass: { popup: 'vr-popup', closeButton: 'att-close-btn' }
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

    /* ── RESET: kembalikan form ke rincian pengajuan awal ─────────────────
       Pecahan, baris tambahan, isian, Note & lampiran item dibuang dari form;
       baris default diambil dari <template id="realizeDefaultTpl">. Baru
       berlaku ke database saat Simpan/Submit — baris tersimpan yang tidak
       ada lagi di form (beserta lampirannya) dihapus server saat itu. */
    $('#btnResetSettle').on('click', function () {
        var adaTersimpan = realizeRows().filter(function () { return !!$(this).find('.realize-row-id').val(); }).length > 0;

        Swal.fire({
            title: 'Reset Settlement?',
            html: 'Semua baris dikembalikan ke rincian pengajuan awal: pecahan, baris tambahan, isian deskripsi/harga/qty, Note, dan Lampiran Item dihapus dari form.'
                + (adaTersimpan ? '<br><br><strong>Draft yang sudah tersimpan (termasuk lampiran item-nya) ikut terhapus saat Anda menekan Simpan/Submit.</strong> Batalkan dengan Cancel / muat ulang halaman sebelum menyimpan.' : ''),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#92232b',
            cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Reset',
            cancelButtonText: 'Batal'
        }).then(function (res) {
            if (!res.isConfirmed) return;

            var html  = document.getElementById('realizeDefaultTpl').innerHTML.trim();
            var $rows = $($.parseHTML(html)).filter('tr.realize-row');

            $('#realizeBody').empty().append($rows.length ? $rows
                : $('<tr class="realize-empty"><td colspan="8" class="text-center tw:py-6 tw:text-data tw:text-text-dim">CR ini tidak punya item pengajuan. Klik <strong>Tambah Baris</strong> untuk menambah baris settlement.</td></tr>'));

            $rows.each(function () {
                var $row = $(this);
                $row.find('.realize-amount-input').each(function () { this.value = fmtRp(this.value); });
                refreshNoteCount($row.find('.realize-notes-input')[0]);
                refreshNoteItem($row.find('.realize-note-cell'));
            });

            recalcTotalRealized();
            updateSettleSubmitState();
            if (window.tsToast) window.tsToast('success', 'Settlement dikembalikan ke rincian pengajuan awal');
        });
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

        var tanpaLampiran = nomorTanpaLampiran();

        if (!$rows.length) {
            reasons.push('belum ada baris settlement (klik Tambah Baris)');
        } else {

            // Baris tambahan wajib memilih Sub COA (ditegakkan juga di server).
            var tanpaSub = [];
            $rows.each(function (i) {
                if (barisKosong($(this))) return;
                if (!String($(this).data('origin') || '') && !$(this).find('.realize-sub-select').val()) tanpaSub.push(i + 1);
            });
            if (tanpaSub.length) reasons.push('Sub COA baris no. ' + tanpaSub.join(', '));
            if (tanpaLampiran.length) reasons.push('Lampiran Item baris no. ' + tanpaLampiran.join(', ') + ' (Settle > 0)');
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
            var nm = f.name.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
            return '<div class="text-muted att-row"><i class="fas fa-file mr-1"></i>' +
                '<a href="#" class="detail-att-open" data-i="' + idx + '" title="' + nm + '">' + nm + '</a>' +
                ' <i class="fas fa-times text-danger detail-att-del ml-1" data-i="' + idx + '" style="cursor:pointer;" title="Hapus lampiran ini"></i></div>';
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

    /* ── BUKTI TRANSFER PENGEMBALIAN — drag & drop + paste + klik ──────────
       Satu <input name="refund_proofs[]"> menampung semua berkas (DataTransfer),
       daftar di bawah dropzone bisa diklik untuk dicek / dihapus. Paste hanya
       ditangkap saat fokus di kartu ini, supaya tidak bentrok dengan lampiran lain. */
    (function () {
        var zone   = document.getElementById('dropZoneRefund');
        var input  = document.getElementById('refundProofInput');
        var browse = document.getElementById('dropZoneBrowseRefund');
        var list   = document.getElementById('refundProofList');
        if (!zone || !input) return;

        var REFUND_EXT = ['pdf', 'jpg', 'jpeg', 'png'];

        function render() {
            list.innerHTML = Array.from(input.files || []).map(function (f, i) {
                return '<div class="refund-proof-item"><i class="fas fa-file mr-1" aria-hidden="true"></i>'
                    + '<a href="#" class="refund-proof-open" data-i="' + i + '" title="Lihat bukti transfer">' + esc(f.name) + '</a>'
                    + ' <button type="button" class="refund-proof-del" data-i="' + i + '" title="Hapus" aria-label="Hapus ' + escAttr(f.name) + '"><i class="fas fa-times" aria-hidden="true"></i></button></div>';
            }).join('');
        }

        function tambah(fileList) {
            var ok = [], tolak = [];
            tolakVideo(fileList).forEach(function (f) {
                (REFUND_EXT.indexOf((f.name || '').split('.').pop().toLowerCase()) !== -1 ? ok : tolak).push(f);
            });
            if (tolak.length) {
                Swal.fire({ icon: 'error', title: 'Format tidak didukung', html: 'Bukti transfer hanya PDF, JPG, atau PNG:<br><strong>' + tolak.map(function (f) { return esc(f.name); }).join('<br>') + '</strong>', confirmButtonColor: '#92232b' });
            }
            if (!ok.length) return;
            try {
                var dt = new DataTransfer();
                Array.from(input.files || []).forEach(function (f) { dt.items.add(f); });
                ok.forEach(function (f) { dt.items.add(f); });
                input.files = dt.files;
            } catch (err) {}
            render();
        }

        var picker = document.createElement('input');
        picker.type = 'file'; picker.multiple = true; picker.accept = input.accept;
        picker.addEventListener('change', function () { tambah(picker.files); picker.value = ''; });

        zone.addEventListener('click', function () { picker.click(); });
        browse.addEventListener('click', function (e) { e.stopPropagation(); picker.click(); });
        browse.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); e.stopPropagation(); picker.click(); }
        });

        ['dragenter', 'dragover'].forEach(function (evt) {
            zone.addEventListener(evt, function (e) { e.preventDefault(); e.stopPropagation(); zone.classList.add('dragover'); });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            zone.addEventListener(evt, function (e) { e.preventDefault(); e.stopPropagation(); zone.classList.remove('dragover'); });
        });
        zone.addEventListener('drop', function (e) {
            if (e.dataTransfer && e.dataTransfer.files.length) tambah(e.dataTransfer.files);
        });

        zone.addEventListener('paste', function (e) {
            var cd = e.clipboardData || window.clipboardData;
            if (!cd || !cd.items) return;
            var picked = [];
            for (var i = 0; i < cd.items.length; i++) {
                if (cd.items[i].kind !== 'file') continue;
                var f = cd.items[i].getAsFile();
                if (f) picked.push(new File([f], 'pasted-' + Date.now() + '.' + (f.type.split('/')[1] || 'png'), { type: f.type }));
            }
            if (picked.length) { e.preventDefault(); tambah(picked); }
        });

        list.addEventListener('click', function (e) {
            var open = e.target.closest('.refund-proof-open');
            var del  = e.target.closest('.refund-proof-del');
            if (open) {
                e.preventDefault();
                bukaFileLokal((input.files || [])[parseInt(open.dataset.i, 10)]);
            } else if (del) {
                var idx = parseInt(del.dataset.i, 10);
                try {
                    var dt = new DataTransfer();
                    Array.from(input.files).forEach(function (f, i) { if (i !== idx) dt.items.add(f); });
                    input.files = dt.files;
                } catch (err) {}
                render();
            }
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
                        // Sub COA baris item pengajuan terkunci — hanya baris tambahan yang ikut file.
                        if (sub) $row.find('select.realize-sub-select').val(sub).trigger('tss:sync');
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
        // Lampiran Item wajib untuk baris bernilai Settle > 0.
        var missAtt = nomorTanpaLampiran();
        // Baris tambahan wajib memilih Sub COA.
        var missSub = [];
        realizeRows().each(function (idx) {
            if (barisKosong($(this))) return;
            if (!String($(this).data('origin') || '') && !$(this).find('.realize-sub-select').val()) missSub.push(idx + 1);
        });
        return { missAtt: missAtt, missSub: missSub };
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
        if (cek.missAtt.length) {
            Swal.fire({
                icon: 'warning',
                title: 'Lampiran Item belum lengkap',
                text: 'Baris bernilai Settle > 0 wajib punya Lampiran Item. Baris: no. ' + cek.missAtt.join(', ') + '.',
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
            Swal.fire({ icon: 'info', title: 'Belum ada baris', text: 'Tambahkan minimal satu baris settlement (Tambah Baris) sebelum menyimpan.', confirmButtonColor: '#92232b' });
            return;
        }
        var cek   = cekKelengkapanBaris();
        // Sub COA baris tambahan wajib juga saat simpan draft (server menolak).
        if (cek.missSub.length) {
            peringatkanKelengkapan({ missSub: cek.missSub, missAtt: [] });
            return;
        }
        var kurang = [];
        if (cek.missAtt.length) kurang.push('Lampiran Item baris no. ' + cek.missAtt.join(', '));

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

/* Sub COA terkunci (baris item pengajuan & pecahannya). */
.realize-sub-locked {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    min-width: 200px; padding: 4px 8px; font-size: 12px; line-height: 1.4;
    color: var(--text); background: var(--surface-2);
    border: 1px solid var(--border); border-radius: var(--radius-sm); cursor: not-allowed;
}
.realize-sub-locked .fa-lock { color: var(--text-faint); font-size: 10px; flex-shrink: 0; }

/* ── Lampiran Item per baris (dropzone mini) ── */
.detail-att-cell { outline: none; transition: background .15s ease; padding: 6px 8px; }
.detail-att-cell:focus-within { background: var(--warning-bg); }
.att-wajib-badge > span { cursor: help; }
/* Settle > 0 tapi belum ada lampiran: wajib. */
.detail-att-cell.att-wajib-kurang .detail-att-drop { background: var(--danger-bg); box-shadow: 0 0 0 2px var(--danger); }

.settle-optional { font-weight: 400; }

/* ── Bukti transfer pengembalian ── */
.refund-proof-list { margin-top: 10px; font-size: 12px; }
.refund-proof-item { display: flex; align-items: center; gap: 4px; padding: 3px 0; color: var(--text-dim); }
.refund-proof-del { background: none; border: 0; padding: 0 4px; color: var(--danger); cursor: pointer; }

/* ── Animasi modal SweetAlert di halaman ini: fade + naik tipis, tanpa "bounce"
   bawaan swal2-show. Prefix body supaya menang atas CSS yang disuntikkan
   sweetalert2.all.min.js saat runtime. ── */
@keyframes realize-modal-in  { from { opacity: 0; transform: translate3d(0, 8px, 0) scale(.985); } to { opacity: 1; transform: none; } }
@keyframes realize-modal-out { from { opacity: 1; transform: none; } to { opacity: 0; transform: translate3d(0, 4px, 0) scale(.99); } }
body .swal2-popup.swal2-show { animation: realize-modal-in .22s var(--ease-out) both; }
body .swal2-popup.swal2-hide { animation: realize-modal-out .14s ease-in both; }
/* Ikon (warning/info/question/error) bawaannya ikut memantul — diganti fade. */
body .swal2-popup .swal2-icon.swal2-icon-show { animation: realize-modal-in .22s var(--ease-out) both; }
body .swal2-popup .swal2-icon.swal2-icon-show .swal2-icon-content,
body .swal2-popup .swal2-icon.swal2-icon-show .swal2-x-mark { animation: none; }
@media (prefers-reduced-motion: reduce) {
    body .swal2-popup.swal2-show, body .swal2-popup.swal2-hide, body .swal2-popup .swal2-icon.swal2-icon-show { animation: none; }
}

/* ── Modal View Request ── */
.swal2-popup.vr-popup { padding: 20px 24px 24px; }
.vr-popup .swal2-html-container { margin: 12px 0 0; text-align: left; }
.vr-meta { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 8px; font-size: var(--fs-caption); color: var(--text-dim); }
.vr-meta strong { color: var(--text); font-variant-numeric: tabular-nums; }
.vr-wrap { max-height: 65vh; overflow: auto; border: 1px solid var(--border); border-radius: var(--radius-md); }
.vr-table { width: 100%; border-collapse: collapse; font-size: 12.5px; color: var(--text); }
.vr-table th, .vr-table td { padding: 8px 12px; border-bottom: 1px solid var(--border-soft); vertical-align: top; }
.vr-table thead th {
    position: sticky; top: 0; z-index: 1; background: var(--surface-2);
    font-size: var(--fs-caption); font-weight: 600; text-transform: uppercase; letter-spacing: .03em;
    color: var(--text-dim); white-space: nowrap; border-bottom: 1px solid var(--border);
}
.vr-table tbody tr:hover { background: var(--surface-2); }
.vr-table tfoot th { position: sticky; bottom: 0; background: var(--surface-2); border-top: 1px solid var(--border); border-bottom: 0; white-space: nowrap; }
.vr-table .vr-no { width: 1%; text-align: center; color: var(--text-dim); white-space: nowrap; }
.vr-table .vr-coa { width: 22%; min-width: 180px; }
.vr-table .vr-coa-name { font-weight: 600; }
.vr-table .vr-sub { margin-top: 2px; color: var(--text-dim); }
.vr-table .vr-desc { min-width: 280px; line-height: 1.45; word-break: break-word; }
.vr-table .vr-num { width: 1%; text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
.vr-table .vr-center { text-align: center; }
.vr-table .vr-strong { font-weight: 600; }
.vr-table .vr-empty { color: var(--text-dim); }

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

/* Baris lampiran item: nama panjang dipotong (…) supaya tombol X selalu terlihat & bisa diklik. */
.att-row { display: flex; align-items: center; min-width: 0; }
.att-row > i { flex: none; }
.att-row > a { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.att-row > .att-lama-del, .att-row > .detail-att-del { padding: 2px 4px; }

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
