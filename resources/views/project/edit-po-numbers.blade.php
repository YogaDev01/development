@extends('layouts.app')

@section('title', 'Tambah No. PO')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ url('/project') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Tambah No. PO</h1>
                    <small class="text-muted">
                        <span class="badge badge-dark">{{ $project->code }}</span>
                        &nbsp;·&nbsp;
                        <span class="badge badge-{{ $project->status_color }}">{{ $project->status_label }}</span>
                    </small>
                </div>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item"><a href="{{ url('/project') }}">Purchase Order</a></li>
                <li class="breadcrumb-item active">Tambah No. PO</li>
            </ol>
        </div>

        <div class="alert alert-info mb-3">
            <i class="fas fa-lock mr-2"></i>
            PO ini berstatus <strong>Waiting PO</strong>. Sebagai Head, halaman ini cuma untuk menambah <strong>No. PO</strong> —
            data lain (Scope of Work, Milestone, PIC, Lampiran, dll) tidak bisa diubah dari sini.
        </div>

        <div class="card card-secondary card-outline shadow-sm">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i> Informasi PO (read-only)</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 form-group mb-2">
                        <label class="font-weight-600">Scope of Work</label>
                        <div>{{ $project->name }}</div>
                    </div>
                    <div class="col-md-3 form-group mb-2">
                        <label class="font-weight-600">Customer</label>
                        <div>{{ $project->customer->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-3 form-group mb-0">
                        <label class="font-weight-600">Nilai PO</label>
                        <div>{{ $project->value_formatted }}</div>
                    </div>
                </div>
            </div>
        </div>

        <form action="{{ url('/project/' . $project->id . '/edit-po-numbers') }}" method="POST"
              enctype="multipart/form-data" id="frmPoNumbers">
            @csrf
            @method('PUT')

            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-file-invoice mr-1"></i> Detail No. PO</h3>
                </div>
                <div class="card-body">
                    @if($project->poNumbers->isNotEmpty())
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:5%;" class="text-center">No</th>
                                    <th scope="col" style="width:20%;">No. PO</th>
                                    <th scope="col" style="width:20%;">Nominal PO</th>
                                    <th scope="col" style="width:15%;">Tanggal Rilis</th>
                                    <th scope="col" style="width:15%;">Status</th>
                                    <th scope="col" style="width:25%;">Lampiran</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($project->poNumbers as $i => $p)
                                <tr>
                                    <td class="text-center">{{ $i + 1 }}</td>
                                    <td>{{ $p->po_number }}</td>
                                    <td>{{ $p->nominal_formatted }}</td>
                                    <td>{{ $p->release_date?->format('d M Y') ?? '-' }}</td>
                                    <td><span class="badge badge-{{ $p->status_color }}">{{ $p->status }}</span></td>
                                    <td>
                                        @forelse($p->attachments as $att)
                                            <a href="{{ asset('storage/' . $att->file_path) }}" data-evidence="po-{{ $p->id }}" data-evidence-name="{{ $att->title ?? $att->file_name }}" target="_blank" class="d-block">
                                                <i class="fas fa-paperclip mr-1"></i>{{ $att->title ?? $att->file_name }}
                                            </a>
                                        @empty
                                            <span class="text-muted">-</span>
                                        @endforelse
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <small class="text-muted d-block mb-3">
                        <i class="fas fa-info-circle mr-1"></i>
                        No. PO yang sudah ada tidak bisa diedit/dihapus dari sini.
                    </small>
                    @endif

                    <div class="d-flex align-items-center flex-wrap mb-2" style="gap:8px;">
                        <strong style="font-size:13px;">Tambah No. PO Baru</strong>
                        <div class="ml-auto d-flex align-items-center flex-wrap" style="gap:8px;">
                            <a href="#" id="btnDownloadPoNumberTemplate" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-file-excel mr-1"></i> Download Template
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnImportPoNumberExcel">
                                <i class="fas fa-file-import mr-1"></i> Import Excel
                            </button>
                            <input type="file" id="importPoNumberFileInput" accept=".xlsx,.xls" style="display:none;">
                            <button type="button" class="btn btn-sm btn-primary" id="btnAddPoNumber">
                                <i class="fas fa-plus mr-1"></i> Tambah No. PO
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:5%;" class="text-center">No</th>
                                    <th scope="col" style="width:20%;">No. PO</th>
                                    <th scope="col" style="width:20%;">Nominal PO</th>
                                    <th scope="col" style="width:18%;">Tanggal Rilis</th>
                                    <th scope="col" style="width:27%;">Lampiran</th>
                                    <th scope="col" style="width:10%;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="poNumberBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <small class="text-muted">Format: pdf, jpg, png, doc(x), xls(x). Maks 10 MB per file.</small>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body d-flex flex-wrap" style="gap:8px;">
                    <a href="{{ url('/project') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left mr-1"></i> Kembali
                    </a>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save mr-1"></i> Simpan No. PO
                    </button>
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

    var poNumIdx = 0;

    function addPoNumberRow(data) {
        data = data || {};
        var i = poNumIdx++;
        var poNumber    = data.po_number ? String(data.po_number).replace(/"/g, '&quot;') : '';
        var nominal     = data.nominal ? fmt(data.nominal) : '';
        var releaseDate = data.release_date || '';
        var html =
            '<tr>' +
                '<td class="text-center align-middle row-no"></td>' +
                '<td><input type="text" name="po_numbers[' + i + '][po_number]" class="form-control form-control-sm" style="text-transform:uppercase;" placeholder="No. PO..." value="' + poNumber + '" required></td>' +
                '<td><div class="input-group input-group-sm"><div class="input-group-prepend"><span class="input-group-text">Rp</span></div>' +
                    '<input type="text" name="po_numbers[' + i + '][nominal]" class="form-control inp-money" placeholder="0" value="' + nominal + '"></div></td>' +
                '<td><input type="date" name="po_numbers[' + i + '][release_date]" class="form-control form-control-sm" value="' + releaseDate + '"></td>' +
                '<td><input type="file" name="po_numbers[' + i + '][file]" class="form-control-file"></td>' +
                '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-ponum"><i class="fas fa-trash"></i></button></td>' +
            '</tr>';
        document.getElementById('poNumberBody').insertAdjacentHTML('beforeend', html);
        renumber('poNumberBody');
    }

    function renumber(tbodyId) {
        document.querySelectorAll('#' + tbodyId + ' .row-no').forEach(function (el, i) {
            el.textContent = i + 1;
        });
    }

    document.getElementById('btnAddPoNumber').addEventListener('click', function () { addPoNumberRow(); });

    document.addEventListener('click', function (e) {
        if (e.target.closest('.btn-remove-ponum')) {
            e.target.closest('tr').remove();
            renumber('poNumberBody');
        }
    });

    addPoNumberRow();

    // ── Download Template & Import Excel — No. PO ──────────
    document.getElementById('btnDownloadPoNumberTemplate').addEventListener('click', function (e) {
        e.preventDefault();
        window.location.href = '{{ url("/project/po-number/import-template") }}';
    });

    document.getElementById('btnImportPoNumberExcel').addEventListener('click', function () {
        document.getElementById('importPoNumberFileInput').value = '';
        document.getElementById('importPoNumberFileInput').click();
    });

    document.getElementById('importPoNumberFileInput').addEventListener('change', function () {
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
            url: '{{ url("/project/po-number/import-preview") }}',
            type: 'POST', data: formData, processData: false, contentType: false
        }).done(function (res) {
            Swal.close();

            if (res.errors && res.errors.length) {
                Swal.fire({
                    icon: 'warning', title: 'Sebagian data bermasalah',
                    html: '<div style="text-align:left;max-height:250px;overflow:auto;">' +
                          res.errors.map(function (e) { return '<div>• ' + e + '</div>'; }).join('') +
                          '</div>',
                    confirmButtonColor: '#92232b'
                });
            }

            if (res.rows && res.rows.length) {
                document.getElementById('poNumberBody').innerHTML = '';
                poNumIdx = 0;

                res.rows.forEach(function (r) {
                    addPoNumberRow({
                        po_number:    r.po_number,
                        nominal:      r.nominal,
                        release_date: r.release_date
                    });
                });

                Swal.fire({
                    icon: 'success', title: 'Import berhasil',
                    text: res.rows.length + ' baris No. PO dimuat. Lampiran tetap perlu diupload manual per baris.',
                    confirmButtonColor: 'var(--success)', timer: 2500, showConfirmButton: false
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
});
</script>

@endsection
