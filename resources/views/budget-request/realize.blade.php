@extends('layouts.app')

@section('title', 'Submit Settlement')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ url('/budget-request?open=' . $budgetRequest->id) }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Submit Settlement</h1>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item"><a href="{{ url('/budget-request') }}">Cost Request</a></li>
                <li class="breadcrumb-item active">Settlement</li>
            </ol>
        </div>

        @php
            $existingByDetail = $budgetRequest->realizations->keyBy('request_detail_id');
        @endphp

        {{-- Alasan reject terakhir — hanya tampil kalau CR ini sedang
             SETTLE_REJECTED, supaya pengaju langsung tahu apa yang harus
             direvisi tanpa harus membuka tab Log. --}}
        @if($budgetRequest->status === 'SETTLE_REJECTED' && $rejectReason)
        <div class="alert alert-danger shadow-sm mb-3">
            <strong><i class="fas fa-exclamation-triangle mr-1"></i> Settlement ini ditolak.</strong>
            <div class="mt-1">{{ $rejectReason->notes }}</div>
            <div class="mt-1" style="font-size:11px;color:var(--text-faint);">
                {{ $rejectReason->actor_name ?? 'System' }} · {{ $rejectReason->created_at->format('d M Y H:i') }}
            </div>
        </div>
        @endif

        <!-- REQUEST SUMMARY — strip horizontal, lebar sama dengan card di bawahnya -->
        <div class="card card-primary card-outline shadow-sm mb-3">
            <div class="card-body py-3">
                <div class="row align-items-center">
                    <div class="col-lg-5 mb-2 mb-lg-0">
                        <div class="d-flex align-items-center flex-wrap" style="gap:8px;">
                            <span class="badge badge-dark px-2 py-1" style="font-size:12px;">{{ $budgetRequest->code }}</span>
                            <x-status :status="$budgetRequest->status" context="budget-request" />
                        </div>
                        <div class="mt-2" style="font-size:13px;color:var(--text);">{{ $budgetRequest->description }}</div>
                    </div>
                    <div class="col-lg-7">
                        <div class="d-flex flex-wrap summary-stat-row">
                            <div class="summary-stat">
                                <div class="summary-stat-label">Total Request</div>
                                <div class="summary-stat-value text-primary">{{ number_format($budgetRequest->amount, 0, ',', '.') }}</div>
                            </div>
                            <div class="summary-stat">
                                <div class="summary-stat-label">Total Settle</div>
                                <div class="summary-stat-value text-info" id="statTotalSettle">0</div>
                            </div>
                            <div class="summary-stat">
                                <div class="summary-stat-label">Variance</div>
                                <div class="summary-stat-value" id="statVariance">0</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form id="realizeForm"
              action="{{ route('budget-request.submit-realization', $budgetRequest->id) }}"
              method="POST" enctype="multipart/form-data">
            @csrf

            <!-- DETAIL / SETTLEMENT PER ITEM -->
            <div class="card shadow-sm mb-3">
                <div class="card-header d-flex align-items-center flex-wrap" style="gap:8px;">
                    <h3 class="card-title mb-0"><i class="fas fa-list mr-1"></i> Settlement per Item</h3>
                    <div class="ml-auto d-flex align-items-center" style="gap:8px;">
                        <a href="{{ url('/budget-request/' . $budgetRequest->id . '/settlement-template') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-file-excel mr-1"></i> Download Template
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnImportSettlementExcel">
                            <i class="fas fa-file-import mr-1"></i> Import Excel
                        </button>
                        <input type="file" id="importSettlementFileInput" accept=".xlsx,.xls" style="display:none;">
                    </div>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover table-bordered mb-0" id="tblSettlement" style="font-size:12px;">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" style="width:22%;">Description Request</th>
                                <th scope="col" style="width:26%;">Description Settle</th>
                                <th scope="col" class="text-center text-nowrap" style="width:1%;">Qty</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;">Request</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;">Settle</th>
                                <th scope="col" class="text-right text-nowrap" style="width:1%;">Variance</th>
                                <th scope="col" class="text-center text-nowrap" style="width:1%;">Lampiran Item <span class="badge badge-danger" title="Wajib jika nilai Settle lebih dari 0">Wajib*</span></th>
                                <th scope="col" class="text-center text-nowrap" style="width:1%;">Note <span class="badge badge-danger" title="Wajib jika nilai Settle melebihi Request">Wajib*</span></th>
                            </tr>
                        </thead>
                        <tbody id="realizeBody">
                            @forelse($budgetRequest->details->where('status', '!=', 'TAKEOUT')->values() as $i => $detail)
                            @php $existing = $existingByDetail->get($detail->id); @endphp
                            <tr data-detail-id="{{ $detail->id }}">
                                <td class="align-middle realize-desc-request-cell">
                                    {{ $detail->account?->name ?? '-' }} - {{ $detail->notes ?: '-' }}
                                    <input type="hidden" name="realizations[{{ $i }}][request_detail_id]" value="{{ $detail->id }}">
                                </td>
                                <td>
                                    <div class="desc-wrap">
                                        <input type="text" name="realizations[{{ $i }}][notes]"
                                               class="form-control form-control-sm realize-notes-input"
                                               maxlength="255"
                                               value="{{ $existing->notes ?? '' }}" placeholder="Catatan settle...">
                                        <button type="button" class="desc-expand" title="Lihat / ubah deskripsi lengkap" tabindex="-1">
                                            <i class="fas fa-expand-alt"></i>
                                        </button>
                                    </div>
                                    {{-- Penghitung sisa karakter: ikut berubah saat mengetik dan
                                         berubah merah begitu batasnya habis, supaya user tahu
                                         kenapa ketikannya berhenti. --}}
                                    <small class="realize-notes-count text-muted d-block mt-1" style="font-size:10px;"></small>
                                </td>
                                <td class="text-center text-nowrap align-middle">{{ rtrim(rtrim(number_format($detail->qty, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="text-right text-nowrap align-middle font-weight-bold text-primary realize-request-cell" data-raw="{{ (int) $detail->total }}">
                                    {{ number_format($detail->total, 0, ',', '.') }}
                                </td>
                                <td class="text-nowrap">
                                    <input type="text" inputmode="numeric"
                                           name="realizations[{{ $i }}][amount]"
                                           class="form-control form-control-sm text-right realize-amount-input"
                                           value="{{ (int) ($existing->amount ?? $detail->total) }}" placeholder="0">
                                </td>
                                <td class="text-right text-nowrap align-middle font-weight-bold realize-variance-cell">0</td>
                                <td class="text-nowrap detail-att-cell" data-idx="{{ $i }}" tabindex="0">
                                    <input type="file" name="realizations[{{ $i }}][attachments][]" class="d-none detail-att-input" multiple
                                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                                    <button type="button" class="btn btn-outline-secondary btn-sm btn-detail-att" style="white-space:nowrap;padding:2px 8px;font-size:11px;">
                                        <i class="fas fa-paperclip"></i> <span class="detail-att-label">Lampiran</span>
                                    </button>
                                    <div class="detail-att-hint text-muted" style="font-size:9px;">drop / paste / klik</div>
                                    {{-- Lampiran dari submit sebelumnya (mis. settlement yang sempat
                                         di-reject dan kembali ke Paid) tetap ditampilkan supaya tidak
                                         perlu diunggah ulang. Centang untuk membuangnya. --}}
                                    @if($existing && $existing->attachments->isNotEmpty())
                                    <div class="mt-1 realize-att-existing" style="font-size:10px;line-height:1.5;">
                                        @foreach($existing->attachments as $att)
                                        {{-- Tampilannya disamakan dengan file yang baru dipilih:
                                             nama diklik untuk memeriksa isinya, × untuk membuang
                                             supaya bisa diganti lampiran lain. --}}
                                        <div class="att-lama text-muted">
                                            <input type="checkbox" name="delete_realization_attachments[]"
                                                   value="{{ $att->id }}" class="del-realize-att d-none">
                                            <i class="fas fa-file mr-1"></i>
                                            <a href="#" class="att-lama-open"
                                               data-url="{{ asset('storage/' . $att->path) }}"
                                               data-nama="{{ $att->original_name }}">{{ $att->original_name }}</a>
                                            <i class="fas fa-times text-danger att-lama-del ml-1" style="cursor:pointer;" title="Hapus lampiran ini"></i>
                                        </div>
                                        @endforeach
                                    </div>
                                    @endif
                                    <div class="detail-att-list mt-1" style="font-size:10px;line-height:1.5;"></div>
                                </td>
                                {{-- Catatan perubahan Description Request → Description Settle.
                                     Ikon saja supaya kolomnya tidak melebar; isinya dibuka di pop-up.
                                     Wajib diisi kalau Settle melebihi Request (lihat validasi submit). --}}
                                <td class="text-center align-middle realize-note-cell">
                                    <input type="hidden" name="realizations[{{ $i }}][change_note]"
                                           class="realize-change-note" value="{{ $existing->change_note ?? '' }}">
                                    <button type="button" class="btn btn-sm btn-primary btn-note-item" title="Isi catatan perubahan">
                                        <i class="fas fa-sticky-note mr-1"></i> Note
                                    </button>
                                    <div class="note-item-preview text-muted mt-1" style="font-size:9px;line-height:1.3;"></div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Tidak ada detail</td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr style="background:var(--surface-2);">
                                <th colspan="3" class="text-right">Subtotal</th>
                                <th class="text-right" id="footRequestTotal">0</th>
                                <th class="text-right" id="footSettleTotal">0</th>
                                <th class="text-right" id="footVarianceTotal">0</th>
                                <th></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- BUKTI SETTLEMENT (GLOBAL) -->
            <div class="card card-info card-outline shadow-sm mb-3">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0"><i class="fas fa-check-double mr-1"></i> Bukti Settlement (Global)</h3>
                    <button type="button" class="btn btn-sm btn-primary ml-auto" id="btnAddRealAttachment">
                        <i class="fas fa-plus mr-1"></i> Tambah Lampiran
                    </button>
                </div>
                <div class="card-body">
                    @if($budgetRequest->realizationAttachments->isNotEmpty())
                    {{-- Bukti global dari submit sebelumnya — ikut dipertahankan. --}}
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered table-sm mb-0" style="font-size:12px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:5%;" class="text-center">No</th>
                                    <th scope="col">File (sudah diunggah)</th>
                                    <th scope="col" style="width:10%;" class="text-center">Hapus</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($budgetRequest->realizationAttachments as $i => $att)
                                <tr>
                                    <td class="text-center align-middle">{{ $i + 1 }}</td>
                                    <td class="align-middle att-lama">
                                        <input type="checkbox" name="delete_realization_global_attachments[]"
                                               value="{{ $att->id }}" class="del-realize-att-global d-none">
                                        <i class="fas fa-file mr-1"></i>
                                        <a href="#" class="att-lama-open"
                                           data-url="{{ asset('storage/' . $att->path) }}"
                                           data-nama="{{ $att->original_name }}">{{ $att->original_name }}</a>
                                        <small class="text-muted ml-2">({{ $att->size_formatted }})</small>
                                    </td>
                                    <td class="text-center align-middle">
                                        <button type="button" class="btn btn-sm btn-danger att-lama-del" title="Hapus lampiran ini">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif

                    <div id="realAttUploadArea" style="display:none;">
                    <div id="dropZoneReal" class="att-dropzone mb-3" tabindex="0">
                        <i class="fas fa-cloud-upload-alt fa-2x mb-2 d-block"></i>
                        <p class="mb-0">
                            Drag &amp; drop file ke sini, paste gambar (Ctrl+V), atau
                            <span class="text-primary" style="text-decoration:underline;cursor:pointer;" id="dropZoneBrowseReal">klik untuk pilih</span>
                        </p>
                        <small class="text-muted">Bisa pilih/drop/paste beberapa file sekaligus</small>
                        <input type="file" id="dropZoneInputReal" multiple style="display:none;">
                    </div>
                    <div class="table-responsive mt-3" id="realAttTableWrap" style="display:none;">
                        <table class="table table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:5%;" class="text-center">No</th>
                                    <th scope="col">File</th>
                                    <th scope="col" style="width:10%;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="realAttachmentBody"></tbody>
                        </table>
                    </div>
                    </div>
                </div>
                <div class="card-footer">
                    <small class="text-muted">
                        <i class="fas fa-info-circle mr-1"></i>
                        Format: PDF, JPG, PNG, DOC, XLS. Maks 10 MB per file. Foto pekerjaan, laporan, atau dokumen pendukung lainnya.
                    </small>
                </div>
            </div>

            {{-- KETERANGAN SETTLEMENT — opsional, ikut tercatat di Log CR bersama
                 rincian settlement yang disubmit. Polanya sama dengan lampiran:
                 kolomnya baru muncul setelah tombolnya diklik. --}}
            <div class="card card-secondary card-outline shadow-sm mb-3">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0"><i class="fas fa-comment-dots mr-1"></i> Keterangan</h3>
                    <button type="button" class="btn btn-sm btn-primary ml-auto" id="btnAddSettleNote">
                        <i class="fas fa-plus mr-1"></i> Tambah Keterangan
                    </button>
                </div>
                <div class="card-body">
                    <div id="settleNoteArea" style="display:none;">
                        <textarea name="settlement_notes" id="settlementNotes" class="form-control" rows="3"
                                  maxlength="1000" placeholder="Mis. nota hotel atas nama rekan kerja, selisih karena kenaikan tarif, dsb."></textarea>
                        <small class="text-muted d-block mt-1">
                            Keterangan ini ikut tersimpan di Log Cost Request bersama settlement yang disubmit. Maks 1000 karakter.
                        </small>
                    </div>
                    <p id="settleNoteEmpty" class="text-muted mb-0" style="font-size:12px;">
                        Belum ada keterangan. Klik <strong>Tambah Keterangan</strong> kalau settlement ini perlu penjelasan.
                    </p>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body d-flex align-items-center" style="gap:8px;">
                    <button type="submit" id="submitBtn" class="btn btn-secondary" disabled>
                        <i class="fas fa-paper-plane mr-1"></i> Submit Settlement
                    </button>
                    {{-- Simpan draft — data tersimpan TANPA mengajukan, status CR
                         tetap PAID/SETTLE_REJECTED. Boleh disimpan walau belum
                         lengkap; kelengkapan baru wajib saat Submit. --}}
                    <button type="button" id="saveDraftBtn" class="btn btn-outline-secondary"
                            data-action="{{ route('budget-request.save-realization-draft', $budgetRequest->id) }}">
                        <i class="fas fa-save mr-1"></i> Simpan
                    </button>
                    <a href="{{ url('/budget-request?open=' . $budgetRequest->id) }}" class="btn btn-secondary">
                        <i class="fas fa-times mr-1"></i> Cancel
                    </a>
                    <small id="submitHint" class="text-muted ml-1" style="display:none;"></small>
                </div>
            </div>
        </form>

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

    /* ── TOTAL / VARIANCE OTOMATIS (per baris + subtotal + ringkasan) ── */
    function recalcTotalRealized() {
        var totalRequest = 0, totalSettle = 0;

        $('#tblSettlement tbody tr[data-detail-id]').each(function () {
            var $row = $(this);
            var req  = parseFloat($row.find('.realize-request-cell').data('raw')) || 0;
            var set  = parseRp($row.find('.realize-amount-input').val());
            var vr   = req - set;

            totalRequest += req;
            totalSettle  += set;

            $row.find('.realize-variance-cell')
                .text(moneyFmt(vr))
                .toggleClass('text-var-pos', vr >= 0)
                .toggleClass('text-var-neg', vr < 0);
        });

        var totalVariance = totalRequest - totalSettle;

        $('#statTotalSettle').text(moneyFmt(totalSettle));
        $('#statVariance').text(moneyFmt(totalVariance)).toggleClass('text-success', totalVariance >= 0).toggleClass('text-danger', totalVariance < 0);
        $('#footRequestTotal').text(moneyFmt(totalRequest));
        $('#footSettleTotal').text(moneyFmt(totalSettle));
        $('#footVarianceTotal').text(moneyFmt(totalVariance)).toggleClass('text-var-pos', totalVariance >= 0).toggleClass('text-var-neg', totalVariance < 0);
    }
    $(document).on('input', '.realize-amount-input', recalcTotalRealized);
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
        var $rows   = $('#tblSettlement tbody tr[data-detail-id]');
        var reasons = [];

        if (!$rows.length) {
            reasons.push('tidak ada item yang bisa disettle');
        } else {
            var tanpaAtt = [];
            $rows.each(function (i) {
                var $row = $(this);
                // Settle boleh 0; baris bernilai 0 tidak wajib berlampiran.
                if (parseRp($row.find('.realize-amount-input').val()) <= 0) return;

                var inp  = $row.find('.detail-att-input')[0];
                var baru  = inp && inp.files && inp.files.length;
                // Lampiran lama yang tidak dicentang hapus tetap dihitung.
                var lama  = $row.find('.del-realize-att').filter(function () { return !this.checked; }).length;
                if (!baru && !lama) tanpaAtt.push(i + 1);
            });

            if (tanpaAtt.length) reasons.push('Lampiran Item baris no. ' + tanpaAtt.join(', '));
        }

        var siap = reasons.length === 0;

        // Abu-abu selama belum lengkap, merah (warna aksi utama TESYS) begitu
        // settlement benar-benar siap dikirim. "Simpan" TIDAK ikut gerbang ini:
        // draft boleh disimpan setengah jalan.
        $('#submitBtn')
            .prop('disabled', !siap)
            .toggleClass('btn-primary', siap)
            .toggleClass('btn-secondary', !siap);

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
            '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-real-att"><i class="fas fa-trash"></i></button></td>';
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
       Baris tabel ini tetap (tidak bisa tambah/kurang) — import hanya
       mengisi ulang kolom "Settle" per item, dicocokkan lewat
       Request Detail ID (jangan diubah di file template). ── */
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
                var applied = 0;
                res.rows.forEach(function (r) {
                    var $row = $('tr[data-detail-id="' + r.request_detail_id + '"]');
                    if (!$row.length) return;
                    $row.find('.realize-amount-input').val(fmtRp(String(r.amount)));
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
        var req   = parseInt($row.find('.realize-request-cell').data('raw'), 10) || 0;
        var settle = parseRp($row.find('.realize-amount-input').val());
        var wajib = settle > req;

        Swal.fire({
            title: 'Catatan Perubahan',
            html: (wajib
                    ? '<div class="text-danger mb-2" style="font-size:12px;text-align:left;">'
                      + 'Nilai Settle melebihi Request — catatan ini wajib diisi.</div>'
                    : '')
                + '<textarea id="noteItemInput" class="form-control" rows="5" maxlength="' + CHANGE_NOTE_MAX + '" '
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
                if (wajib && !v) {
                    Swal.showValidationMessage('Catatan wajib diisi karena Settle melebihi Request.');
                    return false;
                }
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
        // CR yang sudah PAID/SETTLE_REJECTED: tiap baris settlement wajib
        // punya Lampiran Item.
        var missAtt = [];
        $('#realizeBody tr[data-detail-id]').each(function (idx) {
            if (parseRp($(this).find('.realize-amount-input').val()) <= 0) return;
            var inp  = this.querySelector('.detail-att-input');
            var baru = inp && inp.files && inp.files.length;
            var lama = $(this).find('.del-realize-att').filter(function () { return !this.checked; }).length;
            if (!baru && !lama) missAtt.push(idx + 1);
        });
        // Settle melebihi Request wajib disertai catatan perubahan.
        var missNote = [];
        $('#realizeBody tr[data-detail-id]').each(function (idx) {
            var req    = parseInt($(this).find('.realize-request-cell').data('raw'), 10) || 0;
            var settle = parseRp($(this).find('.realize-amount-input').val());
            if (settle > req && !($(this).find('.realize-change-note').val() || '').trim()) {
                missNote.push(idx + 1);
            }
        });
        return { missAtt: missAtt, missNote: missNote };
    }

    /** true kalau ada yang kurang (dan sudah ditampilkan pop-up-nya). */
    function peringatkanKelengkapan(cek) {
        if (cek.missAtt.length) {
            Swal.fire({
                icon: 'warning',
                title: 'Lampiran Item belum lengkap',
                text: 'Setiap baris Rincian Settlement wajib punya Lampiran Item. Baris belum berlampiran: no. ' + cek.missAtt.join(', ') + '.',
                confirmButtonColor: '#92232b'
            });
            return true;
        }
        if (cek.missNote.length) {
            Swal.fire({
                icon: 'warning',
                title: 'Note belum diisi',
                text: 'Baris dengan nilai Settle melebihi Request wajib diisi Note. Baris: no. ' + cek.missNote.join(', ') + '.',
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
        var cek   = cekKelengkapanBaris();
        var kurang = [];
        if (cek.missAtt.length)  kurang.push('Lampiran Item baris no. ' + cek.missAtt.join(', '));
        if (cek.missNote.length) kurang.push('Note baris no. ' + cek.missNote.join(', '));

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
<style>
.card-header .card-title { margin-bottom: 0; font-size: 1.1rem; }

/* ── Request Summary — strip horizontal ── */
.summary-stat-row { gap: 0; }
.summary-stat {
    flex: 1 1 0;
    min-width: 140px;
    padding: 0 16px;
    border-left: 1px solid var(--border);
}
.summary-stat:first-child { border-left: none; padding-left: 0; }
.summary-stat-label { font-size: 11px; color: var(--text-faint); text-transform: uppercase; font-weight: 600; letter-spacing: .02em; }
.summary-stat-value { font-size: 17px; font-weight: 700; margin-top: 2px; }

.text-var-pos { color: var(--success); }
.text-var-neg { color: var(--danger); }

/* ── Tabel Settlement per Item — tampilan lebih clean & ringkas ── */
#tblSettlement th {
    color: var(--text-faint); font-size: 10.5px; text-transform: uppercase; letter-spacing: .02em;
    font-weight: 700; border-bottom-width: 1px; vertical-align: middle;
}
#tblSettlement td { vertical-align: middle; padding: 6px 10px; }
#tblSettlement tbody tr:hover { background: var(--surface-2); }
#tblSettlement .form-control-sm { font-size: 12px; }
#tblSettlement .realize-amount-input { min-width: 92px; font-weight: 600; }
#tblSettlement .realize-notes-input { min-width: 180px; width: 100%; }
#tblSettlement .realize-desc-request-cell {
    max-width: 260px; white-space: normal; word-break: break-word;
    color: var(--text-dim); font-size: 11.5px; line-height: 1.4;
}
#tblSettlement tfoot th { font-size: 11px; color: var(--text); border-top: 2px solid var(--border); }

/* ── Lampiran Item per baris (dropzone mini) ── */
.detail-att-cell { outline: none; transition: background .15s ease; padding: 6px 8px; }
.detail-att-cell:focus-within { background: var(--warning-bg); }
.detail-att-hint { opacity: .6; }
.detail-att-cell .btn-detail-att { border-radius: var(--radius-sm); }

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

/* ── Ikon catatan perubahan per baris ── */
/* Tombol Note mengikuti tombol aksi lain (mis. "Tambah Lampiran"): ikon +
   tulisan, bukan ikon telanjang. Baris yang catatannya sudah terisi diberi
   cincin tipis; isinya terlihat lewat cuplikan teks di bawah tombol. */
.btn-note-item { white-space: nowrap; }
.btn-note-item.has-note { box-shadow: 0 0 0 3px var(--accent-ring); }
.note-item-preview { max-width: 120px; margin-left: auto; margin-right: auto; }

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
    color: var(--text-faint); cursor: pointer; transition: all .2s ease; background: var(--surface-2);
}
.att-dropzone:hover { border-color: var(--accent); background: var(--warning-bg); }
.att-dropzone.dragover { border-color: var(--accent); background: var(--warning-bg); color: var(--accent-dark); }

@media (max-width: 991.98px) {
    .summary-stat { border-left: none; border-top: 1px solid var(--border); padding: 8px 0 0; margin-top: 8px; }
    .summary-stat:first-child { border-top: none; margin-top: 0; padding-top: 0; }
}
</style>
@endpush
