@extends('layouts.app')

@section('title', 'Detail Surat Jalan')

@php
    $canReceive = $deliveryNote->status === 'SENT' && Auth::user()->hasRole(['superadmin','admin','finance','finance_manager','collection']);
@endphp

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ route('delivery-note.index') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">
                        {{ $deliveryNote->code }}
                        <span class="badge badge-{{ $deliveryNote->status_color }} px-2 py-1 align-middle ml-1" style="font-size:12px;">
                            {{ $deliveryNote->status_label }}
                        </span>
                    </h1>
                    <small class="text-muted">{{ $deliveryNote->customer->name ?? '-' }}</small>
                </div>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item"><a href="{{ route('delivery-note.index') }}">Surat Jalan Invoice</a></li>
                <li class="breadcrumb-item active">Detail</li>
            </ol>
        </div>

        @if(session('success'))
        <div class="alert alert-success rounded-0 shadow-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div class="alert alert-danger rounded-0 shadow-sm">{{ session('error') }}</div>
        @endif

        {{-- TOOLBAR AKSI --}}
        <div class="card shadow-sm">
            <div class="card-body d-flex flex-wrap" style="gap:8px;">
                <a href="{{ route('delivery-note.print', $deliveryNote->id) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-print mr-1"></i> Cetak
                </a>
                @if($canReceive)
                <button type="button" class="btn btn-success btn-sm" id="btnMarkReceived">
                    <i class="fas fa-check-double mr-1"></i> Tandai Diterima
                </button>
                @endif
            </div>
        </div>

        <div class="row">

            <!-- KIRI: INFORMASI -->
            <div class="col-md-4">
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i> Informasi Surat Jalan</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0" style="font-size:13px;">
                            <tr>
                                <th style="width:42%;color:var(--text-faint);font-weight:600;">Customer</th>
                                <td>{{ $deliveryNote->customer->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th style="color:var(--text-faint);font-weight:600;vertical-align:top;">Alamat</th>
                                <td style="white-space:pre-line;">{{ $deliveryNote->customer->address ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th style="color:var(--text-faint);font-weight:600;">Dibuat Oleh</th>
                                <td>{{ $deliveryNote->createdBy->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th style="color:var(--text-faint);font-weight:600;">Tanggal Kirim</th>
                                <td>{{ $deliveryNote->sent_at ? $deliveryNote->sent_at->format('d/m/Y H:i') : '-' }}</td>
                            </tr>
                            @if($deliveryNote->status === 'RECEIVED')
                            <tr>
                                <th style="color:var(--text-faint);font-weight:600;">Diterima Oleh</th>
                                <td>{{ $deliveryNote->receivedBy->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th style="color:var(--text-faint);font-weight:600;">Tanggal Diterima</th>
                                <td>{{ $deliveryNote->received_at ? $deliveryNote->received_at->format('d/m/Y H:i') : '-' }}</td>
                            </tr>
                            @endif
                            <tr>
                                <th style="color:var(--text-faint);font-weight:600;vertical-align:top;">Catatan</th>
                                <td style="white-space:pre-line;">{{ $deliveryNote->notes ?: '-' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- KANAN: DAFTAR INVOICE + LOG -->
            <div class="col-md-8">

                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header d-flex align-items-center flex-wrap" style="gap:8px;">
                        <h3 class="card-title"><i class="fas fa-file-invoice mr-1"></i> Daftar Invoice ({{ $deliveryNote->items->count() }})</h3>
                        {{-- MN-08 — pencarian menjangkau Kode Invoice dan No. PO.
                             Penyaringan dilakukan di klien karena seluruh isi Surat
                             Jalan sudah ada di halaman ini (tidak ada paging), jadi
                             memanggil server hanya akan memperlambat tanpa
                             menambah apa pun. --}}
                        <div class="ml-auto">
                            <input type="search" id="sjSearch" class="form-control form-control-sm"
                                   placeholder="Cari kode invoice / no. PO" style="width:260px;font-size:12px;">
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th scope="col" style="width:5%;" class="text-center">No</th>
                                        <th scope="col">Kode Invoice</th>
                                        <th scope="col">No. PO</th>
                                        <th scope="col">Purchase Order</th>
                                        <th scope="col" style="width:16%;" class="text-right">Nilai Tagihan</th>
                                        <th scope="col" style="width:12%;">Status</th>
                                        <th scope="col" style="width:8%;" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($deliveryNote->items as $item)
                                    @php $inv = $item->invoice; @endphp
                                    @php
                                        $poNumber = $inv?->poNumber?->po_number;
                                        $sjHaystack = strtolower(trim(
                                            ($inv->code ?? '') . ' ' . ($poNumber ?? '') . ' '
                                            . ($inv->po->code ?? '') . ' ' . ($inv->po->name ?? '')
                                        ));
                                    @endphp
                                    <tr class="sj-invoice-row" data-search="{{ $sjHaystack }}">
                                        <td class="text-center sj-row-no">{{ $loop->iteration }}</td>
                                        <td><span class="badge badge-dark px-2 py-1" style="font-size:10px;">{{ $inv->code ?? '-' }}</span></td>
                                        <td>
                                            @if($poNumber)
                                                <span style="font-family:monospace;font-size:11px;">{{ $poNumber }}</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $inv->po->name ?? '-' }}
                                            @if($inv?->po?->code)
                                                <div class="text-muted" style="font-size:10px;">{{ $inv->po->code }}</div>
                                            @endif
                                        </td>
                                        <td class="text-right"><strong>{{ number_format($inv->captured_total ?? 0, 0, ',', '.') }}</strong></td>
                                        <td><span class="badge badge-secondary px-2 py-1">{{ $inv->status ?? '-' }}</span></td>
                                        <td class="text-center">
                                            @if($inv)
                                            <a href="{{ route('invoice-cash-receipt.show', $inv->id) }}" class="btn btn-info btn-sm" title="Detail Invoice">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="7" class="text-center text-muted py-3">Belum ada invoice.</td></tr>
                                    @endforelse
                                    <tr id="sjNoMatch" style="display:none;">
                                        <td colspan="7" class="text-center text-muted py-3">
                                            Tidak ada invoice yang cocok dengan pencarian.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-history mr-1"></i> Log &amp; Lampiran</h3>
                    </div>
                    <div class="card-body">
                        <div class="dn-timeline">
                            @forelse($deliveryNote->logs as $log)
                            <div class="dn-timeline-item">
                                <span class="dn-timeline-dot badge-{{ $log->action_color }}"></span>
                                <div class="dn-timeline-body">
                                    <span class="badge badge-{{ $log->action_color }} px-2 py-1 mr-2" style="font-size:9px;">{{ strtoupper($log->action_label) }}</span>
                                    <strong>{{ $log->actor_name ?? '-' }}</strong>
                                    <div class="dn-timeline-date">{{ $log->created_at->format('d/m/Y H:i') }}</div>
                                    @if($log->notes)
                                    <div class="dn-timeline-notes">{{ $log->notes }}</div>
                                    @endif
                                    @if($log->attachments->isNotEmpty())
                                    <div class="dn-timeline-attachments">
                                        @foreach($log->attachments as $att)
                                        <a href="/storage/{{ $att->file_path }}" data-evidence="dn-log-{{ $log->id }}" data-evidence-name="{{ $att->title ?? 'File' }}" target="_blank" class="dn-evidence-chip">
                                            <i class="fas fa-paperclip mr-1"></i>{{ $att->title ?? 'File' }}
                                        </a>
                                        @endforeach
                                    </div>
                                    @endif
                                </div>
                            </div>
                            @empty
                            <div class="text-center text-muted py-3">Belum ada log.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

{{-- MODAL: TANDAI DITERIMA --}}
@if($canReceive)
<div class="modal fade" id="mdlMarkReceived" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white"><i class="fas fa-check-double mr-2" style="color:var(--accent);"></i> Tandai Diterima</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="{{ route('delivery-note.receive', $deliveryNote->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <div class="alert alert-light border mb-3" style="font-size:12px;">
                        Semua invoice ({{ $deliveryNote->items->count() }}) dalam Surat Jalan ini akan ikut ditandai <strong>RECEIVED</strong>.
                    </div>
                    <div class="form-group">
                        <label class="font-weight-600">Bukti Terima (Evidence) <span class="text-danger">*</span></label>
                        <div class="att-dropzone" id="dropZoneEvidence" tabindex="0">
                            <i class="fas fa-cloud-upload-alt mb-1 d-block"></i>
                            <span style="font-size:12px;">
                                Drag &amp; drop file / paste gambar (Ctrl+V), atau
                                <span class="text-primary" style="text-decoration:underline;cursor:pointer;" id="dropZoneEvidenceBrowse">klik untuk pilih</span>
                            </span>
                        </div>
                        <input type="file" name="evidence[]" id="dropZoneEvidenceInput" multiple
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" style="display:none;">
                        <div class="att-file-preview" id="evidenceFilePreview"></div>
                        <small class="text-muted">Bisa pilih beberapa file — foto, dokumen tanda terima, dsb.</small>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Catatan (opsional)</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Catatan penerimaan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check-double mr-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection

@push('styles')
<style>
.font-weight-600 { font-weight:600; }
.table td, .table th { vertical-align:middle !important; }

.dn-timeline { position:relative; padding-left:26px; }
.dn-timeline::before { content:""; position:absolute; left:9px; top:6px; bottom:6px; width:2px; background:var(--surface-3); }
.dn-timeline-item { position:relative; padding-bottom:20px; }
.dn-timeline-item:last-child { padding-bottom:0; }
.dn-timeline-dot {
    position:absolute; left:-26px; top:2px; width:16px; height:16px; border-radius:50%;
    border:3px solid var(--border); box-shadow:0 0 0 1px var(--border);
}
.dn-timeline-body { font-size:12px; }
.dn-timeline-date { font-size:11px; color:var(--text-faint); margin-top:3px; }
.dn-timeline-notes { font-size:12px; margin-top:6px; padding:8px 12px; background:var(--surface-2); border-radius:var(--radius-sm); border:1px solid var(--border); }
.dn-timeline-attachments { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
.dn-evidence-chip {
    display:inline-flex; align-items:center; font-size:11px; padding:4px 8px;
    background:var(--info-bg); border:1px solid var(--border); border-radius:var(--radius-sm); color:var(--text); text-decoration:none;
}
.dn-evidence-chip:hover { background:var(--info-bg); color:var(--text); }

.att-dropzone {
    border: 2px dashed var(--border);
    border-radius: var(--radius-md);
    padding: 24px 20px;
    text-align: center;
    color: var(--text-faint);
    cursor: pointer;
    transition: all .2s ease;
    background: var(--surface-2);
}
.att-dropzone:hover { border-color: var(--accent); background: var(--warning-bg); }
.att-dropzone.dragover { border-color: var(--accent); background: var(--warning-bg); color: var(--accent-dark); }
.att-dropzone i { font-size: 20px; }
.att-file-preview { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.att-file-chip {
    display: flex; align-items: center; gap: 6px;
    background: var(--info-bg); border: 1px solid var(--border); border-radius: var(--radius-sm);
    padding: 4px 8px; font-size: 11.5px; max-width: 100%;
}
.att-file-chip .fname { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 200px; }
.att-file-chip .att-file-remove { cursor: pointer; color: var(--danger); font-weight: 700; line-height: 1; }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    /* MN-08 — pencarian Kode Invoice / No. PO.
       Nomor urut ikut dihitung ulang supaya hasil pencarian tidak menampilkan
       nomor yang melompat-lompat. */
    var timer = null;

    $('#sjSearch').on('input', function () {
        var q = $(this).val().toLowerCase().trim();

        clearTimeout(timer);
        timer = setTimeout(function () {
            var shown = 0;

            $('.sj-invoice-row').each(function () {
                var match = !q || ($(this).data('search') || '').toString().indexOf(q) !== -1;
                $(this).toggle(match);
                if (match) $(this).find('.sj-row-no').text(++shown);
            });

            $('#sjNoMatch').toggle(shown === 0);
        }, 120);
    });
});
</script>
@endpush

@push('scripts')
<script>
$(function () {
    $('#btnMarkReceived').on('click', function () { $('#mdlMarkReceived').modal('show'); });

    // ==== Drag & drop + paste-image untuk Bukti Terima (Evidence) ====
    var evidenceInput = document.getElementById('dropZoneEvidenceInput');
    var $evidenceZone  = $('#dropZoneEvidence');

    function addFilesToEvidenceInput(newFiles) {
        var dt = new DataTransfer();
        Array.prototype.forEach.call(evidenceInput.files, function (f) { dt.items.add(f); });
        Array.prototype.forEach.call(newFiles, function (f) { dt.items.add(f); });
        evidenceInput.files = dt.files;
    }

    function removeFileFromEvidenceInput(index) {
        var dt = new DataTransfer();
        Array.prototype.forEach.call(evidenceInput.files, function (f, i) {
            if (i !== index) dt.items.add(f);
        });
        evidenceInput.files = dt.files;
    }

    function renderEvidencePreview() {
        var $preview = $('#evidenceFilePreview');
        $preview.empty();
        Array.prototype.forEach.call(evidenceInput.files, function (f, i) {
            var $chip = $('<div class="att-file-chip"></div>');
            $chip.append('<i class="fas fa-paperclip text-muted"></i>');
            $chip.append('<span class="fname" title="' + f.name + '">' + f.name + '</span>');
            var $rm = $('<span class="att-file-remove">&times;</span>');
            $rm.on('click', function (e) {
                e.stopPropagation();
                removeFileFromEvidenceInput(i);
                renderEvidencePreview();
            });
            $chip.append($rm);
            $preview.append($chip);
        });
    }

    $evidenceZone.on('click', function (e) {
        if (!$(e.target).is('input')) evidenceInput.click();
    });

    $(evidenceInput).on('change', renderEvidencePreview);

    $evidenceZone.on('dragenter dragover', function (e) {
        e.preventDefault(); e.stopPropagation();
        $evidenceZone.addClass('dragover');
    });
    $evidenceZone.on('dragleave', function (e) {
        e.preventDefault(); e.stopPropagation();
        $evidenceZone.removeClass('dragover');
    });
    $evidenceZone.on('drop', function (e) {
        e.preventDefault(); e.stopPropagation();
        $evidenceZone.removeClass('dragover');
        var files = e.originalEvent.dataTransfer.files;
        if (files && files.length) {
            addFilesToEvidenceInput(files);
            renderEvidencePreview();
        }
    });

    function handleEvidencePaste(e) {
        var cd = e.originalEvent ? e.originalEvent.clipboardData : e.clipboardData;
        if (!cd || !cd.items) return;
        var files = [];
        for (var i = 0; i < cd.items.length; i++) {
            var item = cd.items[i];
            if (item.kind === 'file') {
                var f = item.getAsFile();
                if (f) {
                    var ext = (f.type.split('/')[1] || 'png');
                    var renamed = new File([f], 'pasted-image-' + Date.now() + '.' + ext, { type: f.type });
                    files.push(renamed);
                }
            }
        }
        if (files.length) {
            e.preventDefault();
            addFilesToEvidenceInput(files);
            renderEvidencePreview();
        }
    }

    $evidenceZone.on('paste', handleEvidencePaste);

    $(document).on('paste', function (e) {
        if (!$('#mdlMarkReceived').hasClass('show')) return;
        var tag = document.activeElement ? document.activeElement.tagName : '';
        if (tag === 'INPUT' || tag === 'TEXTAREA') return;
        if (document.activeElement && document.activeElement.id === 'dropZoneEvidence') return;
        handleEvidencePaste(e);
    });

    $('#mdlMarkReceived form').on('submit', function (e) {
        if (!evidenceInput.files || evidenceInput.files.length === 0) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'Bukti terima kosong', text: 'Lampirkan minimal 1 file bukti terima.', confirmButtonColor: '#92232b' });
        }
    });
});
</script>
@endpush
