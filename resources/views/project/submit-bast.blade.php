@extends('layouts.app')

@section('title', 'Submit BAST')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <button type="button" class="btn btn-secondary btn-sm mr-3" onclick="window.close(); history.back();">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div>
                    <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Submit BAST</h1>
                    <small class="text-muted">{{ $project->code }} — {{ $project->name }} ({{ $project->customer->name ?? '-' }})</small>
                </div>
            </div>
        </div>

        @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @php
            $eligible = $project->poNumbers->whereIn('status', ['RELEASED', 'PARTIAL']);
            $others   = $project->poNumbers->whereNotIn('status', ['RELEASED', 'PARTIAL']);
        @endphp

        <div class="alert alert-info" style="font-size:12.5px;">
            <i class="fas fa-info-circle mr-1"></i>
            Isi No. BAST + Tanggal BAST + Nominal BAST + lampiran untuk No. PO yang mau disubmit (boleh beberapa file sekaligus per No. PO, dan boleh beberapa No. PO sekaligus). No. PO yang tidak diisi lampirannya akan dilewati otomatis.
            Centang <strong>"Tandai lengkap"</strong> kalau ini sudah dokumen BAST terakhir untuk No. PO tersebut — kalau belum yakin, biarkan tidak dicentang dan konfirmasi belakangan lewat tombol <strong>Tandai BAST Lengkap</strong>.
        </div>

        @if($eligible->isEmpty())
        <div class="card shadow-sm">
            <div class="card-body text-center text-muted py-5">
                <i class="fas fa-check-circle d-block mb-2" style="font-size:24px;"></i>
                Tidak ada No. PO yang perlu submit BAST (semua sudah BAST/Invoiced/Paid, atau belum ada No. PO Released).
            </div>
        </div>
        @else
        <form action="{{ route('project.submit-bast.store', $project->id) }}" method="POST" enctype="multipart/form-data" id="frmBastBatch">
            @csrf

            @foreach($eligible as $po)
            <div class="card shadow-sm mb-3">
                <div class="card-header d-flex align-items-center flex-wrap" style="gap:8px;">
                    <strong>{{ $po->po_number }}</strong>
                    <span class="text-muted" style="font-size:12px;">{{ $po->nominal_formatted }}</span>
                    <span class="badge badge-{{ $po->status_color }} px-2 py-1 ml-1">{{ $po->status_label }}</span>

                    @if($po->status === 'PARTIAL')
                    <div class="ml-auto d-flex align-items-center" style="gap:10px;">
                        <span class="text-muted" style="font-size:11px;">
                            BAST terakhir: {{ $po->no_bast ?? '-' }} &middot; {{ $po->tanggal_bast?->format('d M Y') ?? '-' }}
                        </span>
                        <button type="button" class="btn btn-outline-success btn-sm btn-complete-bast"
                                data-po="{{ $po->po_number }}"
                                data-action="{{ route('project.po-number.complete-bast', $po->id) }}">
                            <i class="fas fa-check-double mr-1"></i> Tandai BAST Lengkap
                        </button>
                    </div>
                    @endif
                </div>
                <div class="card-body">

                    @if($po->bastAttachments->isNotEmpty())
                    <div class="mb-3">
                        <small class="text-muted d-block mb-1">Lampiran BAST sebelumnya:</small>
                        @foreach($po->bastAttachments as $att)
                        <a href="{{ asset('storage/' . $att->file_path) }}" target="_blank" class="d-block" style="font-size:12px;">
                            <i class="fas fa-paperclip mr-1"></i>{{ $att->title ?? $att->file_name }}
                        </a>
                        @endforeach
                    </div>
                    @endif

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-600">No BAST</label>
                                <input type="text" name="po_numbers[{{ $po->id }}][no_bast]" class="form-control form-control-sm" placeholder="Nomor BAST...">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-600">Tanggal BAST</label>
                                <input type="date" name="po_numbers[{{ $po->id }}][tanggal_bast]" class="form-control form-control-sm">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-600">Nominal BAST</label>
                                <input type="text" name="po_numbers[{{ $po->id }}][nominal_bast]" class="form-control form-control-sm bast-nominal-input text-right" placeholder="0">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group mb-0">
                                <label class="font-weight-600">Lampiran BAST</label>
                                <div class="bast-dropzone" data-po-id="{{ $po->id }}" tabindex="0">
                                    <i class="fas fa-cloud-upload-alt mb-1 d-block"></i>
                                    <span style="font-size:12px;">
                                        Drag &amp; drop / paste gambar, atau
                                        <span class="text-primary bast-browse" style="text-decoration:underline;cursor:pointer;">klik untuk pilih</span>
                                    </span>
                                </div>
                                <input type="file" name="po_numbers[{{ $po->id }}][attachments][]"
                                       class="bast-file-input" data-po-id="{{ $po->id }}" multiple
                                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" style="display:none;">
                                <div class="bast-file-preview" data-po-id="{{ $po->id }}"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-check">
                        <input type="checkbox" name="po_numbers[{{ $po->id }}][mark_complete]" value="1" class="form-check-input" id="markComplete{{ $po->id }}">
                        <label class="form-check-label" for="markComplete{{ $po->id }}">
                            Tandai lengkap setelah submit ini (semua dokumen BAST untuk No. PO ini sudah diupload)
                        </label>
                    </div>

                </div>
            </div>
            @endforeach

            <div class="text-right mb-4">
                <button type="submit" class="btn btn-primary" id="btnSubmitBastBatch">
                    <i class="fas fa-file-signature mr-1"></i> Submit Semua BAST
                </button>
            </div>
        </form>

        {{-- Form terpisah (di luar #frmBastBatch) untuk aksi "Tandai BAST Lengkap" —
             sengaja TIDAK di-nested di dalam form besar di atas, karena <form> di dalam
             <form> tidak valid dan membuat browser menutup form induk lebih awal. --}}
        <form id="frmCompleteBastShared" method="POST" style="display:none;">
            @csrf
        </form>
        @endif

        @if($others->isNotEmpty())
        <div class="section-heading" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-faint);margin:20px 0 8px;">
            No. PO Lain (BAST Lengkap / Invoiced / Paid)
        </div>
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0" style="font-size:12.5px;">
                    <thead style="background:var(--surface-2);">
                        <tr>
                            <th scope="col">No. PO</th>
                            <th scope="col" class="text-right">Nominal</th>
                            <th scope="col">Status</th>
                            <th scope="col">No. BAST</th>
                            <th scope="col">Tanggal BAST</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($others as $po)
                        <tr>
                            <td>{{ $po->po_number }}</td>
                            <td class="text-right">{{ $po->nominal_formatted }}</td>
                            <td><span class="badge badge-{{ $po->status_color }} px-2 py-1">{{ $po->status_label }}</span></td>
                            <td>{{ $po->no_bast ?? '-' }}</td>
                            <td>{{ $po->tanggal_bast?->format('d M Y') ?? '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

    </div>
</div>

@endsection

@push('styles')
<style>
    .bast-dropzone {
        border: 2px dashed var(--border);
        border-radius: var(--radius-md);
        padding: 14px 12px;
        text-align: center;
        color: var(--text-faint);
        cursor: pointer;
        transition: all .2s ease;
        background: var(--surface-2);
    }
    .bast-dropzone:hover { border-color: var(--accent); background: var(--warning-bg); }
    .bast-dropzone.dragover { border-color: var(--accent); background: var(--warning-bg); color: var(--accent-dark); }
    .bast-dropzone i { font-size: 18px; }
    .bast-file-preview { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
    .bast-file-chip {
        display: flex; align-items: center; gap: 6px;
        background: var(--info-bg); border: 1px solid var(--border); border-radius: var(--radius-sm);
        padding: 4px 8px; font-size: 11.5px; max-width: 100%;
    }
    .bast-file-chip .fname { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 160px; }
    .bast-file-chip .bast-file-remove { cursor: pointer; color: var(--danger); font-weight: 700; line-height: 1; }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    $('#frmBastBatch').on('submit', function (e) {
        var hasAnyFile = false;
        var incomplete = [];

        $('.card.shadow-sm.mb-3', this).each(function () {
            var $card = $(this);
            var fileInput = $card.find('input[type="file"]').get(0);
            var hasFile = !!(fileInput && fileInput.files && fileInput.files.length > 0);
            if (!hasFile) return;

            hasAnyFile = true;

            var noBast  = $card.find('input[name$="[no_bast]"]').val();
            var tanggal = $card.find('input[name$="[tanggal_bast]"]').val();
            var nominal = $card.find('input[name$="[nominal_bast]"]').val();
            if (!noBast || !tanggal || !nominal) {
                incomplete.push($card.find('.card-header strong').first().text());
            }
        });

        if (!hasAnyFile) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'Belum ada lampiran', text: 'Isi lampiran minimal 1 No. PO sebelum submit.', confirmButtonColor: '#92232b' });
            return;
        }

        if (incomplete.length) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning', title: 'Data BAST belum lengkap',
                text: 'No. PO ' + incomplete.join(', ') + ' sudah ada lampiran, tapi No. BAST, Tanggal BAST, dan/atau Nominal BAST belum diisi. Lengkapi dulu supaya tidak dilewati saat submit.',
                confirmButtonColor: '#92232b'
            });
            return;
        }

        Swal.fire({
            title: 'Submit BAST?',
            text: 'Pastikan No. BAST, Tanggal BAST, Nominal BAST, dan lampiran sudah benar untuk setiap No. PO yang diisi.',
            icon: 'question', showCancelButton: true,
            confirmButtonColor: 'var(--info)', cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Submit', cancelButtonText: 'Batal'
        }).then(function (r) {
            if (!r.isConfirmed) return;
            $('#btnSubmitBastBatch').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');
            e.target.submit();
        });
        e.preventDefault();
    });

    $('.bast-nominal-input').on('input', function () {
        var v = (this.value || '').toString().replace(/\D/g, '');
        this.value = v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    });

    // ==== Drag & drop + paste-image untuk tiap Lampiran BAST per No. PO ====
    function addFilesToBastInput(input, newFiles) {
        var dt = new DataTransfer();
        Array.prototype.forEach.call(input.files, function (f) { dt.items.add(f); });
        Array.prototype.forEach.call(newFiles, function (f) { dt.items.add(f); });
        input.files = dt.files;
    }

    function removeFileFromBastInput(input, index) {
        var dt = new DataTransfer();
        Array.prototype.forEach.call(input.files, function (f, i) {
            if (i !== index) dt.items.add(f);
        });
        input.files = dt.files;
    }

    function renderBastPreview(poId) {
        var input = document.querySelector('.bast-file-input[data-po-id="' + poId + '"]');
        var $preview = $('.bast-file-preview[data-po-id="' + poId + '"]');
        $preview.empty();
        Array.prototype.forEach.call(input.files, function (f, i) {
            var $chip = $('<div class="bast-file-chip"></div>');
            $chip.append('<i class="fas fa-paperclip text-muted"></i>');
            $chip.append('<span class="fname" title="' + f.name + '">' + f.name + '</span>');
            var $rm = $('<span class="bast-file-remove">&times;</span>');
            $rm.on('click', function (e) {
                e.stopPropagation();
                removeFileFromBastInput(input, i);
                renderBastPreview(poId);
            });
            $chip.append($rm);
            $preview.append($chip);
        });
    }

    $('.bast-dropzone').each(function () {
        var $zone = $(this);
        var poId = $zone.data('po-id');
        var input = document.querySelector('.bast-file-input[data-po-id="' + poId + '"]');

        $zone.on('click', function (e) {
            if (!$(e.target).is('input')) input.click();
        });

        $(input).on('change', function () {
            renderBastPreview(poId);
        });

        $zone.on('dragenter dragover', function (e) {
            e.preventDefault(); e.stopPropagation();
            $zone.addClass('dragover');
        });
        $zone.on('dragleave', function (e) {
            e.preventDefault(); e.stopPropagation();
            $zone.removeClass('dragover');
        });
        $zone.on('drop', function (e) {
            e.preventDefault(); e.stopPropagation();
            $zone.removeClass('dragover');
            var files = e.originalEvent.dataTransfer.files;
            if (files && files.length) {
                addFilesToBastInput(input, files);
                renderBastPreview(poId);
            }
        });

        $zone.on('paste', function (e) {
            handleBastPaste(e, poId, input);
        });
    });

    function handleBastPaste(e, poId, input) {
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
            addFilesToBastInput(input, files);
            renderBastPreview(poId);
        }
    }

    // Fallback: paste global saat fokus bukan di input/textarea, arahkan ke dropzone terakhir yang diklik/fokus.
    var lastFocusedBastPoId = null;
    $('.bast-dropzone').on('focus click', function () {
        lastFocusedBastPoId = $(this).data('po-id');
    });
    $(document).on('paste', function (e) {
        var tag = document.activeElement ? document.activeElement.tagName : '';
        if (tag === 'INPUT' || tag === 'TEXTAREA') return;
        if (!lastFocusedBastPoId) return;
        if (document.activeElement && $(document.activeElement).hasClass('bast-dropzone')) return;
        var input = document.querySelector('.bast-file-input[data-po-id="' + lastFocusedBastPoId + '"]');
        if (input) handleBastPaste(e, lastFocusedBastPoId, input);
    });

    $('.btn-complete-bast').on('click', function () {
        var po = $(this).data('po');
        var action = $(this).data('action');
        Swal.fire({
            title: 'BAST sudah diupload semua?',
            text: 'No. PO ' + po + ' akan ditandai BAST lengkap.',
            icon: 'question', showCancelButton: true,
            confirmButtonColor: 'var(--success)', cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Lengkap', cancelButtonText: 'Batal'
        }).then(function (r) {
            if (r.isConfirmed) {
                document.getElementById('frmCompleteBastShared').action = action;
                document.getElementById('frmCompleteBastShared').submit();
            }
        });
    });
});
</script>
@endpush
