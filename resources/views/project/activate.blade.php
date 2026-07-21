@extends('layouts.app')

@section('title', 'Aktifkan PO')

@section('content')

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6 d-flex align-items-center">
                <a href="{{ url('/project') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="m-0 font-weight-bold">Aktifkan PO</h1>
                    <small class="text-muted">
                        <span class="badge badge-dark">{{ $project->code }}</span>
                        &nbsp;·&nbsp;
                        <span class="badge badge-{{ $project->status_color }}">{{ $project->status_label }}</span>
                    </small>
                </div>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right mb-0">
                    <li class="breadcrumb-item"><a href="#">Finance</a></li>
                    <li class="breadcrumb-item"><a href="{{ url('/project') }}">List PO</a></li>
                    <li class="breadcrumb-item active">Aktifkan</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">

        {{-- Alert revisi --}}
        @php
            $lastLog = $project->logs()->where('action', 'revised')->latest()->first();
        @endphp
        @if($lastLog)
        <div class="alert alert-warning mb-3">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <strong>Catatan Revisi (Finance):</strong> {{ $lastLog->notes }}
        </div>
        @endif

        <form action="{{ url('/project/' . $project->id . '/activate') }}" method="POST"
              enctype="multipart/form-data" id="frmProject">
            @csrf
            @method('PUT')

            <div class="card card-warning card-outline shadow-sm">
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

                        <!-- KODE (readonly) -->
                        <div class="col-md-2">
                            <div class="form-group">
                                <label class="font-weight-600">Kode <span class="badge badge-secondary ml-1" style="font-size:10px;">Fixed</span></label>
                                <input type="text" class="form-control"
                                       value="{{ $project->code }}" readonly
                                       style="background:#f4f6f9;cursor:not-allowed;font-weight:700;">
                            </div>
                        </div>

                        <!-- SCOPE OF WORK -->
                        <div class="col-md-7">
                            <div class="form-group">
                                <label class="font-weight-600">Scope of Work <span class="text-danger">*</span></label>
                                <input type="text" name="name"
                                       class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name', $project->name) }}"
                                       placeholder="Deskripsi lingkup pekerjaan..."
                                       style="text-transform:uppercase;"
                                       autocomplete="off" required>
                                @error('name')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>

                        <!-- IMPLEMENT BY -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="font-weight-600">Implement By</label>
                                <select name="done_by" class="form-control @error('done_by') is-invalid @enderror">
                                    <option value="">— Pilih —</option>
                                    @foreach(['TEREX','HANASTA','SUBCON'] as $opt)
                                        <option value="{{ $opt }}" {{ old('done_by', $project->done_by) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- CUSTOMER -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Customer</label>
                                <select name="customer_id" class="form-control @error('customer_id') is-invalid @enderror">
                                    <option value="">— Pilih Customer —</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}"
                                            {{ old('customer_id', $project->customer_id) == $customer->id ? 'selected' : '' }}>
                                            {{ $customer->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- TERM OF PAYMENT -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Term of Payment</label>
                                <input type="text" name="term_of_payment"
                                       class="form-control"
                                       value="{{ old('term_of_payment', $project->term_of_payment) }}"
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
                            </div>
                        </div>

                        <!-- NO PO (wajib untuk aktivasi) -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">
                                    No PO <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="po_number"
                                       class="form-control @error('po_number') is-invalid @enderror"
                                       value="{{ old('po_number', $project->po_number) }}"
                                       placeholder="Nomor PO dari customer..."
                                       required>
                                <small class="text-muted">Wajib diisi untuk mengaktifkan PO.</small>
                                @error('po_number')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <!-- NILAI KONTRAK -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Nilai Kontrak</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">Rp</span>
                                    </div>
                                    <input type="text" name="value"
                                           class="form-control text-right inp-money"
                                           value="{{ old('value', number_format($project->value, 0, ',', '.')) }}"
                                           placeholder="0" autocomplete="off">
                                </div>
                            </div>
                        </div>

                        <!-- NOTES -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Notes</label>
                                <textarea name="notes" class="form-control" rows="2"
                                          placeholder="Catatan tambahan...">{{ old('notes', $project->notes) }}</textarea>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- SECTION PROJECT DETAIL -->
            <div class="card card-warning card-outline shadow-sm">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title"><i class="fas fa-project-diagram mr-1"></i> Project & Milestone</h3>
                    <button type="button" class="btn btn-sm btn-primary ml-auto" id="btnAddDetail">
                        <i class="fas fa-plus mr-1"></i> Tambah Detail
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width:4%;" class="text-center">No</th>
                                    <th style="width:20%;">Project</th>
                                    <th style="width:15%;">Milestone</th>
                                    <th style="width:13%;">Plan Start</th>
                                    <th style="width:13%;">Plan Finish</th>
                                    <th style="width:13%;">Actual Start</th>
                                    <th style="width:13%;">Actual Finish</th>
                                    <th style="width:5%;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="detailBody">
                                @forelse($project->details as $i => $det)
                                <tr>
                                    <td class="text-center align-middle row-no-detail">{{ $i + 1 }}</td>
                                    <td>
                                        <select name="details[{{ $i }}][cluster_id]" class="form-control form-control-sm">
                                            <option value="">— Pilih Cluster —</option>
                                            @foreach($clusters as $cluster)
                                                <option value="{{ $cluster->id }}" {{ $det->cluster_id == $cluster->id ? 'selected' : '' }}>
                                                    {{ $cluster->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="details[{{ $i }}][stage_id]" class="form-control form-control-sm">
                                            <option value="">— Pilih Stage —</option>
                                            @foreach($stages as $stage)
                                                <option value="{{ $stage->id }}" {{ $det->stage_id == $stage->id ? 'selected' : '' }}>
                                                    {{ $stage->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="date" name="details[{{ $i }}][plan_start]" class="form-control form-control-sm" value="{{ $det->plan_start?->format('Y-m-d') }}"></td>
                                    <td><input type="date" name="details[{{ $i }}][plan_end]" class="form-control form-control-sm" value="{{ $det->plan_end?->format('Y-m-d') }}"></td>
                                    <td><input type="date" name="details[{{ $i }}][actual_start]" class="form-control form-control-sm" value="{{ $det->actual_start?->format('Y-m-d') }}"></td>
                                    <td><input type="date" name="details[{{ $i }}][actual_end]" class="form-control form-control-sm" value="{{ $det->actual_end?->format('Y-m-d') }}"></td>
                                    <td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-detail"><i class="fas fa-trash"></i></button></td>
                                </tr>
                                @empty
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <small class="text-muted">Data detail lama akan diganti seluruhnya saat disimpan.</small>
                </div>
            </div>

            <!-- SECTION PIC -->
            <div class="card card-warning card-outline shadow-sm">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title"><i class="fas fa-users mr-1"></i> PIC Internal</h3>
                    <button type="button" class="btn btn-sm btn-primary ml-auto" id="btnAddPic">
                        <i class="fas fa-plus mr-1"></i> Tambah PIC
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width:4%;" class="text-center">No</th>
                                    <th style="width:25%;">Nama PIC</th>
                                    <th style="width:20%;">Jabatan / Posisi</th>
                                    <th style="width:22%;">Whatsapp</th>
                                    <th style="width:24%;">Email</th>
                                    <th style="width:5%;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="picBody">
                                @forelse($project->pics as $i => $pic)
                                <tr>
                                    <td class="text-center align-middle row-no">{{ $i + 1 }}</td>
                                    <td><input type="text" name="pics[{{ $i }}][pic]" class="form-control form-control-sm" value="{{ $pic->pic }}" placeholder="Nama PIC..."></td>
                                    <td><input type="text" name="pics[{{ $i }}][position]" class="form-control form-control-sm" value="{{ $pic->position }}" placeholder="Jabatan..."></td>
                                    <td><input type="text" name="pics[{{ $i }}][contact]" class="form-control form-control-sm" value="{{ $pic->contact }}" placeholder="Whatsapp..."></td>
                                    <td><input type="text" name="pics[{{ $i }}][remark]" class="form-control form-control-sm" value="{{ $pic->remark }}" placeholder="Email..."></td>
                                    <td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-pic"><i class="fas fa-trash"></i></button></td>
                                </tr>
                                @empty
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <small class="text-muted">
                        <i class="fas fa-info-circle mr-1"></i> Data PIC lama akan diganti seluruhnya saat disimpan.
                    </small>
                </div>
            </div>

            <!-- LAMPIRAN EXISTING -->
            @php $generalAtts = $project->attachments->where('type', 'general'); @endphp
            @if($generalAtts->count() > 0)
            <div class="card card-warning card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-paperclip mr-1"></i> Lampiran Existing</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width:5%;" class="text-center">No</th>
                                    <th style="width:65%;">Nama File</th>
                                    <th style="width:15%;" class="text-center">Preview</th>
                                    <th style="width:15%;" class="text-center">Hapus</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($generalAtts as $i => $att)
                                <tr>
                                    <td class="text-center align-middle">{{ $i + 1 }}</td>
                                    <td class="align-middle"><small class="text-muted">{{ $att->title ?? $att->file_name }}</small></td>
                                    <td class="text-center align-middle">
                                        <a href="{{ asset('storage/' . $att->file_path) }}" target="_blank"
                                           class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                    <td class="text-center align-middle">
                                        <input type="checkbox" name="delete_attachments[]"
                                               value="{{ $att->id }}"
                                               style="width:18px;height:18px;accent-color:#dc3545;">
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <small class="text-danger"><i class="fas fa-exclamation-triangle mr-1"></i> Centang file yang ingin dihapus.</small>
                </div>
            </div>
            @endif

            <!-- LAMPIRAN BARU -->
            <div class="card card-warning card-outline shadow-sm">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title"><i class="fas fa-upload mr-1"></i> Tambah Lampiran Baru</h3>
                    <button type="button" class="btn btn-sm btn-primary ml-auto" id="btnAddAtt">
                        <i class="fas fa-plus mr-1"></i> Tambah
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width:5%;" class="text-center">No</th>
                                    <th style="width:85%;">File</th>
                                    <th style="width:10%;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="attBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <small class="text-muted">Format: pdf, jpg, png, doc(x), xls(x). Maks 10 MB per file.</small>
                </div>
            </div>

            <!-- CATATAN AKTIVASI + ACTION -->
            <div class="card shadow-sm border-success">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold text-dark">
                        <i class="fas fa-play-circle mr-1 text-success"></i> Aktivasi PO
                    </h3>
                </div>
                <div class="card-body">

                    <div class="form-group">
                        <label class="font-weight-600">Catatan Aktivasi (opsional)</label>
                        <textarea name="activation_notes" class="form-control" rows="2"
                                  placeholder="Catatan aktivasi...">{{ old('activation_notes') }}</textarea>
                        <small class="text-muted">Dicatat pada log perubahan status.</small>
                    </div>

                    <div class="alert alert-info py-2 px-3 mb-3" style="font-size:13px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        Menyimpan form ini akan memperbarui data PO <strong>dan</strong> mengubah statusnya
                        dari <strong>WAITING PO</strong> menjadi <strong>ACTIVE</strong>.
                    </div>

                    <div class="d-flex flex-wrap" style="gap:8px;">
                        <a href="{{ url('/project') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left mr-1"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-success" id="btnActivate">
                            <i class="fas fa-play mr-1"></i> Simpan &amp; Aktifkan
                        </button>
                    </div>

                </div>
            </div>

        </form>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

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

    var attIdx = 0;
    var detailIdx = document.querySelectorAll('#detailBody tr').length;
    var clusterOptions = '';
    var stageOptions = '';

    $.get('/cluster/options-json', function(clusters) {
        clusters.forEach(function(c) { clusterOptions += '<option value="' + c.id + '">' + c.name + '</option>'; });
    });
    $.get('/stage/options-json', function(stages) {
        stages.forEach(function(s) { stageOptions += '<option value="' + s.id + '">' + s.name + '</option>'; });
    });

    var btnAddDetail = document.getElementById('btnAddDetail');
    if (btnAddDetail) {
        btnAddDetail.addEventListener('click', function () {
            var i = detailIdx++;
            document.getElementById('detailBody').insertAdjacentHTML('beforeend',
                '<tr>' +
                    '<td class="text-center align-middle row-no-detail"></td>' +
                    '<td><select name="details[' + i + '][cluster_id]" class="form-control form-control-sm"><option value="">— Pilih Cluster —</option>' + clusterOptions + '</select></td>' +
                    '<td><select name="details[' + i + '][stage_id]" class="form-control form-control-sm"><option value="">— Pilih Stage —</option>' + stageOptions + '</select></td>' +
                    '<td><input type="date" name="details[' + i + '][plan_start]" class="form-control form-control-sm"></td>' +
                    '<td><input type="date" name="details[' + i + '][plan_end]" class="form-control form-control-sm"></td>' +
                    '<td><input type="date" name="details[' + i + '][actual_start]" class="form-control form-control-sm"></td>' +
                    '<td><input type="date" name="details[' + i + '][actual_end]" class="form-control form-control-sm"></td>' +
                    '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-detail"><i class="fas fa-trash"></i></button></td>' +
                '</tr>'
            );
            renumberDetail();
        });
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('.btn-remove-detail')) {
            e.target.closest('tr').remove();
            renumberDetail();
        }
    });

    function renumberDetail() {
        document.querySelectorAll('#detailBody .row-no-detail').forEach(function(el, i) { el.textContent = i+1; });
    }

    var picIdx = document.querySelectorAll('#picBody tr').length;
    var btnAddPic = document.getElementById('btnAddPic');
    if (btnAddPic) {
        btnAddPic.addEventListener('click', function () {
            var i = picIdx++;
            document.getElementById('picBody').insertAdjacentHTML('beforeend',
                '<tr>' +
                    '<td class="text-center align-middle row-no"></td>' +
                    '<td><input type="text" name="pics[' + i + '][pic]" class="form-control form-control-sm" placeholder="Nama PIC..."></td>' +
                    '<td><input type="text" name="pics[' + i + '][position]" class="form-control form-control-sm" placeholder="Jabatan..."></td>' +
                    '<td><input type="text" name="pics[' + i + '][contact]" class="form-control form-control-sm" placeholder="Kontak..."></td>' +
                    '<td><input type="text" name="pics[' + i + '][remark]" class="form-control form-control-sm" placeholder="Keterangan..."></td>' +
                    '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-pic"><i class="fas fa-trash"></i></button></td>' +
                '</tr>'
            );
            renumber('picBody');
        });
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('.btn-remove-pic')) {
            e.target.closest('tr').remove();
            renumber('picBody');
        }
    });

    var btnAddAtt = document.getElementById('btnAddAtt');
    if (btnAddAtt) {
        btnAddAtt.addEventListener('click', function () {
            var i = attIdx++;
            document.getElementById('attBody').insertAdjacentHTML('beforeend',
                '<tr>' +
                    '<td class="text-center align-middle row-no"></td>' +
                    '<td><input type="file" name="attachments[' + i + ']" class="form-control-file"></td>' +
                    '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-att"><i class="fas fa-trash"></i></button></td>' +
                '</tr>'
            );
            renumber('attBody');
        });
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('.btn-remove-att')) {
            e.target.closest('tr').remove();
            renumber('attBody');
        }
    });

    function renumber(tbodyId) {
        document.querySelectorAll('#' + tbodyId + ' .row-no').forEach(function (el, i) {
            el.textContent = i + 1;
        });
    }

    // Konfirmasi aktivasi
    var btnActivate = document.getElementById('btnActivate');
    if (btnActivate && typeof Swal !== 'undefined') {
        btnActivate.addEventListener('click', function (e) {
            var form = btnActivate.closest('form');
            var poInput = form.querySelector('input[name="po_number"]');

            if (!poInput || !poInput.value.trim()) {
                return; // biarkan validasi HTML5 "required" yang menangani
            }

            e.preventDefault();

            Swal.fire({
                title: 'Simpan & Aktifkan PO?',
                text: 'Perubahan akan disimpan dan status PO berubah menjadi ACTIVE.',
                icon: 'question', showCancelButton: true,
                confirmButtonColor: '#28a745', cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Aktifkan', cancelButtonText: 'Batal'
            }).then(function (r) {
                if (r.isConfirmed) form.submit();
            });
        });
    }

});
</script>

@endsection

@push('styles')
<style>
.font-weight-600 { font-weight:600; }
table td { vertical-align:middle !important; }
</style>
@endpush