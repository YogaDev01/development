@extends('layouts.app')

@section('title', 'Tambah PO')

@section('content')

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6 d-flex align-items-center">
                <a href="{{ url('/project') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="m-0 font-weight-bold">Tambah PO</h1>
                    <small class="text-muted">Form penambahan PO baru</small>
                </div>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right mb-0">
                    <li class="breadcrumb-item"><a href="#">Finance</a></li>
                    <li class="breadcrumb-item"><a href="{{ url('/project') }}">Purchase Order</a></li>
                    <li class="breadcrumb-item active">Tambah</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">

        <form action="{{ url('/project') }}" method="POST" enctype="multipart/form-data" id="frmProject">
            @csrf

            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-file-contract mr-1"></i> Informasi PO
                    </h3>
                </div>

                @if($errors->any())
                <div class="alert alert-danger rounded-0 mb-0">
                    <strong>Terjadi kesalahan:</strong>
                    <ul class="mb-0 mt-2">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <div class="card-body">
                    <div class="row">

                        <!-- SCOPE OF WORK / NAMA PO -->
                        <div class="col-md-8">
                            <div class="form-group">
                                <label class="font-weight-600">Scope of Work <span class="text-danger">*</span></label>
                                <input type="text" name="name"
                                       class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name') }}"
                                       placeholder="Deskripsi lingkup pekerjaan..."
                                       style="text-transform:uppercase;"
                                       autocomplete="off" required>
                                @error('name')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>

                        <!-- IMPLEMENT BY (DONE BY) -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-600">Implement By</label>
                                <select name="done_by" class="form-control @error('done_by') is-invalid @enderror">
                                    <option value="">— Pilih —</option>
                                    <option value="TEREX" {{ old('done_by') === 'TEREX' ? 'selected' : '' }}>TEREX</option>
                                    <option value="HANASTA" {{ old('done_by') === 'HANASTA' ? 'selected' : '' }}>HANASTA</option>
                                    <option value="SUBCON" {{ old('done_by') === 'SUBCON' ? 'selected' : '' }}>SUBCON</option>
                                </select>
                                @error('done_by')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>

                        <!-- CUSTOMER -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Customer</label>
                                <select name="customer_id" class="form-control @error('customer_id') is-invalid @enderror">
                                    <option value="">— Pilih Customer —</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                                            {{ $customer->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('customer_id')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>

                        <!-- TERM OF PAYMENT -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Term of Payment</label>
                                <input type="text" name="term_of_payment"
                                       class="form-control @error('term_of_payment') is-invalid @enderror"
                                       value="{{ old('term_of_payment') }}"
                                       list="topSuggestions"
                                       placeholder="Contoh: Net 30, Monthly, Termin..."
                                       autocomplete="off">
                                <datalist id="topSuggestions">
                                    <option value="Net 30">
                                    <option value="Net 45">
                                    <option value="Net 60">
                                    <option value="Monthly">
                                    <option value="50% DP - 50% Selesai">
                                    <option value="Termin per Tahap">
                                    <option value="Lunas di Muka">
                                </datalist>
                                @error('term_of_payment')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>

                        <!-- NILAI KONTRAK -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Nilai PO</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">Rp</span>
                                    </div>
                                    <input type="text" name="value"
                                           class="form-control text-right inp-money @error('value') is-invalid @enderror"
                                           value="{{ old('value') }}"
                                           placeholder="0" autocomplete="off">
                                </div>
                                @error('value')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>

                        <!-- NOTES -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Notes</label>
                                <textarea name="notes" class="form-control" rows="2"
                                          placeholder="Catatan tambahan...">{{ old('notes') }}</textarea>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- SECTION PROJECT DETAIL -->
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header d-flex align-items-center flex-wrap" style="gap:8px;">
                    <h3 class="card-title mb-0"><i class="fas fa-project-diagram mr-1"></i> Project & Milestone</h3>
                    <div class="ml-auto d-flex align-items-center flex-wrap" style="gap:8px;">
                        <a href="#" id="btnDownloadDetailTemplate" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-file-excel mr-1"></i> Download Template
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnImportDetailExcel">
                            <i class="fas fa-file-import mr-1"></i> Import Excel
                        </button>
                        <input type="file" id="importDetailFileInput" accept=".xlsx,.xls" style="display:none;">
                        <button type="button" class="btn btn-sm btn-primary" id="btnAddDetail">
                            <i class="fas fa-plus mr-1"></i> Tambah Detail
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width:5%;" class="text-center">No</th>
                                    <th style="width:25%;">Project</th>
                                    <th style="width:20%;">Milestone</th>
                                    <th style="width:20%;">Plan Start</th>
                                    <th style="width:20%;">Plan Finish</th>
                                    <th style="width:10%;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="detailBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- SECTION PIC -->
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-users mr-1"></i> PIC</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">PIC Internal</label>
                                <select name="pics_internal[]" id="selPicsInternal" multiple placeholder="Pilih PIC Internal...">
                                    @foreach($picsInternal as $p)
                                        <option value="{{ $p->id }}">
                                            {{ $p->pic }}@if($p->position) — {{ $p->position }}@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label class="font-weight-600">PIC Customer</label>
                                <select name="pics_customer[]" id="selPicsCustomer" multiple placeholder="Pilih PIC Customer...">
                                    @foreach($picsCustomer as $p)
                                        <option value="{{ $p->id }}">
                                            {{ $p->pic }}@if($p->position) — {{ $p->position }}@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <small class="text-muted">
                        <i class="fas fa-info-circle mr-1"></i>
                        PIC diambil dari Master PIC. Belum ada di daftar?
                        <a href="{{ url('/master-pic') }}" target="_blank">Tambah di Master PIC</a>.
                    </small>
                </div>
            </div>

            <!-- LAMPIRAN -->
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title"><i class="fas fa-paperclip mr-1"></i> Lampiran</h3>
                    <button type="button" class="btn btn-sm btn-primary ml-auto" id="btnAddAtt">
                        <i class="fas fa-plus mr-1"></i> Tambah Lampiran
                    </button>
                </div>
                <div class="card-body">
                    <div id="dropZonePO" class="att-dropzone mb-3">
                        <i class="fas fa-cloud-upload-alt fa-2x mb-2 d-block"></i>
                        <p class="mb-0">
                            Drag &amp; drop file ke sini, atau
                            <span class="text-primary" style="text-decoration:underline;cursor:pointer;" id="dropZoneBrowsePO">klik untuk pilih</span>
                        </p>
                        <small class="text-muted">Bisa pilih/drop beberapa file sekaligus</small>
                        <input type="file" id="dropZoneInputPO" multiple style="display:none;"
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width:5%;" class="text-center">No</th>
                                    <th style="width:30%;">Judul</th>
                                    <th style="width:55%;">File</th>
                                    <th style="width:10%;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="attBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <small class="text-muted">
                        <i class="fas fa-info-circle mr-1"></i>
                        Format: pdf, jpg, png, doc(x), xls(x). Maks 10 MB per file.
                    </small>
                </div>
            </div>

            <!-- ACTION -->
            <div class="card shadow-sm">
                <div class="card-body d-flex">
                    <a href="{{ url('/project') }}" class="btn btn-secondary mr-2">
                        <i class="fas fa-arrow-left mr-1"></i> Kembali
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Simpan sebagai Draft
                    </button>
                </div>
            </div>

        </form>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // Format Rupiah
    function fmt(v) {
        v = (v || '').toString().replace(/\D/g, '');
        return v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('inp-money')) {
            e.target.value = fmt(e.target.value);
        }
    });

    document.querySelectorAll('.inp-money').forEach(function (el) {
        el.value = fmt(el.value);
    });

    // Project Detail rows
    var detailIdx = 0;
    var clusterOptions = '';
    var stageOptions = '';

    // Load clusters and stages via AJAX
    $.get('/cluster/options-json', function(clusters) {
        clusters.forEach(function(c) {
            clusterOptions += '<option value="' + c.id + '">' + c.name + '</option>';
        });
    });
    $.get('/stage/options-json', function(stages) {
        stages.forEach(function(s) {
            stageOptions += '<option value="' + s.id + '">' + s.name + '</option>';
        });
    });

    function makeDetailRowHTML(i, prefill) {
        prefill = prefill || {};
        var clusterSel = clusterOptions.replace(
            'value="' + (prefill.cluster_id || '') + '"',
            'value="' + (prefill.cluster_id || '') + '" selected'
        );
        var stageSel = stageOptions.replace(
            'value="' + (prefill.stage_id || '') + '"',
            'value="' + (prefill.stage_id || '') + '" selected'
        );

        return '<tr>' +
                '<td class="text-center align-middle row-no-detail"></td>' +
                '<td><select name="details[' + i + '][cluster_id]" class="form-control form-control-sm"><option value="">— Pilih Project —</option>' + clusterSel + '</select></td>' +
                '<td><select name="details[' + i + '][stage_id]" class="form-control form-control-sm"><option value="">— Pilih Milestone —</option>' + stageSel + '</select></td>' +
                '<td><input type="date" name="details[' + i + '][plan_start]" class="form-control form-control-sm" value="' + (prefill.plan_start || '') + '"></td>' +
                '<td><input type="date" name="details[' + i + '][plan_end]" class="form-control form-control-sm" value="' + (prefill.plan_end || '') + '"></td>' +
                '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-detail"><i class="fas fa-trash"></i></button></td>' +
            '</tr>';
    }

    document.getElementById('btnAddDetail').addEventListener('click', function () {
        var i = detailIdx++;
        document.getElementById('detailBody').insertAdjacentHTML('beforeend', makeDetailRowHTML(i, null));
        renumberAll();
    });

    // ── Download Template & Import Excel — Project & Milestone ──────────
    document.getElementById('btnDownloadDetailTemplate').addEventListener('click', function (e) {
        e.preventDefault();
        window.location.href = '{{ url("/project/import-template") }}';
    });

    document.getElementById('btnImportDetailExcel').addEventListener('click', function () {
        document.getElementById('importDetailFileInput').value = '';
        document.getElementById('importDetailFileInput').click();
    });

    document.getElementById('importDetailFileInput').addEventListener('change', function () {
        var file = this.files[0];
        if (!file) return;

        var formData = new FormData();
        formData.append('file', file);
        formData.append('_token', '{{ csrf_token() }}');

        Swal.fire({
            title: 'Memproses file...', text: 'Mohon tunggu sebentar.',
            allowOutsideClick: false, didOpen: function () { Swal.showLoading(); }
        });

        $.ajax({
            url: '{{ url("/project/import-preview") }}',
            type: 'POST', data: formData, processData: false, contentType: false
        }).done(function (res) {
            Swal.close();

            if (res.errors && res.errors.length) {
                Swal.fire({
                    icon: 'warning', title: 'Sebagian data bermasalah',
                    html: '<div style="text-align:left;max-height:250px;overflow:auto;">' +
                          res.errors.map(function (e) { return '<div>• ' + e + '</div>'; }).join('') +
                          '</div>',
                    confirmButtonColor: '#F5A623'
                });
            }

            if (res.rows && res.rows.length) {
                document.getElementById('detailBody').innerHTML = '';
                detailIdx = 0;

                res.rows.forEach(function (r) {
                    var i = detailIdx++;
                    document.getElementById('detailBody').insertAdjacentHTML('beforeend', makeDetailRowHTML(i, {
                        cluster_id: r.cluster_id,
                        stage_id:   r.stage_id,
                        plan_start: r.plan_start,
                        plan_end:   r.plan_end
                    }));
                });

                renumberAll();

                Swal.fire({
                    icon: 'success', title: 'Import berhasil',
                    text: res.rows.length + ' baris data dimuat ke Project & Milestone.',
                    confirmButtonColor: '#28a745', timer: 2000, showConfirmButton: false
                });
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

    document.addEventListener('click', function (e) {
        if (e.target.closest('.btn-remove-detail')) {
            e.target.closest('tr').remove();
            renumberAll();
        }
    });

    function renumberAll() {
        document.querySelectorAll('#detailBody .row-no-detail').forEach(function(el, i) { el.textContent = i+1; });
        document.querySelectorAll('#attBody .row-no').forEach(function(el, i) { el.textContent = i+1; });
    }

    // PIC — multi-select dari Master PIC
    new TomSelect('#selPicsInternal', { plugins: ['remove_button'], persist: false, create: false, placeholder: 'Pilih PIC Internal...' });
    new TomSelect('#selPicsCustomer', { plugins: ['remove_button'], persist: false, create: false, placeholder: 'Pilih PIC Customer...' });

    // Attachment rows (+ drag & drop multi-file)
    var attIdx = 0;

    function addAttRowPO(file) {
        var i = attIdx++;
        var html =
            '<tr>' +
                '<td class="text-center align-middle row-no"></td>' +
                '<td><input type="text" name="attachments[' + i + '][title]" class="form-control form-control-sm att-title" style="text-transform:uppercase;" placeholder="Judul lampiran..." required></td>' +
                '<td><input type="file" name="attachments[' + i + '][file]" class="form-control-file"></td>' +
                '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-att"><i class="fas fa-trash"></i></button></td>' +
            '</tr>';
        document.getElementById('attBody').insertAdjacentHTML('beforeend', html);

        if (file) {
            var rows       = document.querySelectorAll('#attBody tr');
            var lastRow    = rows[rows.length - 1];
            var input      = lastRow.querySelector('input[type="file"]');
            var titleInput = lastRow.querySelector('.att-title');
            try {
                var dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
            } catch (err) {
                // Browser lama tanpa dukungan DataTransfer — baris tetap dibuat kosong.
            }
            if (titleInput && !titleInput.value) {
                titleInput.value = file.name.replace(/\.[^/.]+$/, '');
            }
        }
        renumberAll();
    }

    document.getElementById('btnAddAtt').addEventListener('click', function () {
        addAttRowPO(null);
    });

    // Drop zone
    (function () {
        var zone   = document.getElementById('dropZonePO');
        var input  = document.getElementById('dropZoneInputPO');
        var browse = document.getElementById('dropZoneBrowsePO');

        zone.addEventListener('click', function () { input.click(); });
        browse.addEventListener('click', function (e) {
            e.stopPropagation();
            input.click();
        });

        input.addEventListener('change', function () {
            Array.from(this.files).forEach(function (f) { addAttRowPO(f); });
            this.value = '';
        });

        ['dragenter', 'dragover'].forEach(function (evt) {
            zone.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            zone.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.remove('dragover');
            });
        });
        zone.addEventListener('drop', function (e) {
            var files = e.dataTransfer ? e.dataTransfer.files : null;
            if (files && files.length) {
                Array.from(files).forEach(function (f) { addAttRowPO(f); });
            }
        });
    })();

    document.addEventListener('click', function (e) {
        if (e.target.closest('.btn-remove-att')) {
            e.target.closest('tr').remove();
            renumberAll();
        }
    });



});
</script>

@endsection

@push('styles')
<style>
.font-weight-600 { font-weight:600; }
table td { vertical-align:middle !important; }

.ts-wrapper { min-width:0; }
.ts-control { border-radius:6px !important; border:1px solid #ced4da !important; min-height:38px !important; padding:6px 10px !important; box-shadow:none !important; }

.att-dropzone {
    border: 2px dashed #ced4da;
    border-radius: 8px;
    padding: 28px 20px;
    text-align: center;
    color: #6c757d;
    cursor: pointer;
    transition: all .2s ease;
    background: #fafbfc;
}
.att-dropzone:hover {
    border-color: #F5A623;
    background: #FFFBF3;
}
.att-dropzone.dragover {
    border-color: #F5A623;
    background: #FFF3DC;
    color: #C97E0A;
}

@media (max-width: 576px) {
    .ts-wrapper, .ts-control { width:100% !important; min-width:0 !important; max-width:100% !important; }
    .ts-control > input { min-width:0 !important; width:100% !important; }
    .table-responsive { overflow-x:auto; -webkit-overflow-scrolling:touch; }
}
</style>
@endpush