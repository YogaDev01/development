@extends('layouts.app')

@section('title', 'Edit Budget')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ url('/budget-plan') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Edit Budget</h1>
                    <small class="text-muted">
                        <span class="badge badge-dark">{{ $budgetPlan->code }}</span>
                        &nbsp;·&nbsp;
                        <span class="badge badge-{{ $budgetPlan->status_color }}">{{ $budgetPlan->status }}</span>
                    </small>
                </div>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item"><a href="{{ url('/budget-plan') }}">Budgeting</a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </div>

        {{-- Alert revisi --}}
        @if($budgetPlan->approver_notes)
        <div class="alert alert-warning mb-3">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <strong>Catatan Penolakan:</strong> {{ $budgetPlan->approver_notes }}
        </div>
        @endif

        <form action="{{ url('/budget-plan/' . $budgetPlan->id) }}" method="POST"
              enctype="multipart/form-data" id="frmBudgetPlan">
            @csrf
            @method('PUT')

            <!-- INFORMASI UTAMA -->
            <div class="card card-warning card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-money-check-alt mr-1"></i> Informasi Budget
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
                    <div class="row po-two-col form-clean">

                        <!-- KOLOM KIRI -->
                        <div class="col-md-6 po-col-left">

                            @if($budgetPlan->isProjectBudget())
                            <!-- PO (budget proyek — masih boleh ganti PO) -->
                            <div class="form-group mb-0">
                                <label class="font-weight-600">PO (Project Order) <span class="text-danger">*</span></label>
                                <select name="project_id" id="selProject" class="form-control @error('project_id') is-invalid @enderror" required>
                                    <option value="">— Pilih PO —</option>
                                    @foreach($projects as $project)
                                        <option value="{{ $project->id }}"
                                            {{ old('project_id', $budgetPlan->project_id) == $project->id ? 'selected' : '' }}>
                                            @if($project->customer){{ $project->customer->name }} - @endif[{{ $project->code ?? '-' }}] {{ $project->name }}
                                        </option>
                                    @endforeach
                                    {{-- Pastikan PO existing selalu ada di dropdown meski statusnya berubah --}}
                                    @if($budgetPlan->project && !$projects->contains('id', $budgetPlan->project_id))
                                        <option value="{{ $budgetPlan->project_id }}" selected>
                                            @if($budgetPlan->project->customer){{ $budgetPlan->project->customer->name }} - @endif[{{ $budgetPlan->project->code ?? '-' }}] {{ $budgetPlan->project->name }}
                                        </option>
                                    @endif
                                </select>
                                @error('project_id')
                                    <small class="text-danger d-block">{{ $message }}</small>
                                @enderror
                            </div>
                            @else
                            <!-- COST CENTER (budget internal — sumber dana dikunci) -->
                            <div class="form-group mb-0">
                                <label class="font-weight-600">Cost Center</label>
                                <input type="text" class="form-control" readonly
                                       value="[{{ $budgetPlan->costCenter->code ?? '-' }}] {{ $budgetPlan->costCenter->name ?? '-' }}">
                                <small class="text-muted">
                                    Sumber dana <strong>{{ $budgetPlan->source_type }}</strong> tidak dapat diubah setelah budget dibuat.
                                </small>
                            </div>
                            @endif

                        </div>

                        <!-- KOLOM KANAN -->
                        <div class="col-md-6 po-col-right">

                            <!-- DESKRIPSI -->
                            <div class="form-group">
                                <label class="font-weight-600">Deskripsi <span class="text-danger">*</span></label>
                                <input type="text" name="description"
                                       class="form-control @error('description') is-invalid @enderror"
                                       value="{{ old('description', $budgetPlan->description) }}"
                                       placeholder="Deskripsi budget..."
                                       style="text-transform:uppercase;"
                                       autocomplete="off" required>
                                @error('description')
                                    <small class="text-danger d-block">{{ $message }}</small>
                                @enderror
                            </div>

                            <!-- PLAN FINISH -->
                            <div class="form-group">
                                <label class="font-weight-600">
                                    Plan Finish <span class="text-danger">*</span>
                                </label>
                                <input type="date" name="plan_finish"
                                       class="form-control @error('plan_finish') is-invalid @enderror"
                                       value="{{ old('plan_finish', $budgetPlan->plan_finish?->format('Y-m-d')) }}" required>
                                @error('plan_finish')
                                    <small class="text-danger d-block">{{ $message }}</small>
                                @enderror
                                <small class="text-muted">Tanggal rencana periode budget selesai. Setelah tanggal ini terlewati, budget otomatis masuk tahap Closing.</small>
                            </div>

                            <!-- NOTES -->
                            <div class="form-group mb-0">
                                <label class="font-weight-600">Notes</label>
                                <textarea name="notes" class="form-control" rows="2"
                                          placeholder="Catatan tambahan...">{{ old('notes', $budgetPlan->notes) }}</textarea>
                            </div>

                        </div>

                    </div>
                </div>
            </div>

            <!-- DETAIL BUDGET -->
            <div class="card card-warning card-outline shadow-sm">
                <div class="card-header d-flex align-items-center flex-wrap" style="gap:8px;">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-list-ul mr-1"></i> Detail Budget
                    </h3>
                    <div class="ml-auto d-flex align-items-center flex-wrap" style="gap:8px;">
                        <span id="totalAmountLabel" class="badge badge-primary px-3 py-2" style="font-size:13px;">
                            Total: {{ $budgetPlan->amount_formatted }}
                        </span>
                        <a href="#" id="btnDownloadTemplate" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-file-excel mr-1"></i> Download Template
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnImportExcel">
                            <i class="fas fa-file-import mr-1"></i> Import Excel
                        </button>
                        <input type="file" id="importFileInput" accept=".xlsx,.xls" style="display:none;">
                        <button type="button" class="btn btn-sm btn-primary" id="btnAddDetail">
                            <i class="fas fa-plus mr-1"></i> Tambah Baris
                        </button>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:4%;" class="text-center">No</th>
                                    <th scope="col" style="width:18%;">Chart of Account</th>
                                    <th scope="col" style="width:17%;">Deskripsi</th>
                                    <th scope="col" style="width:12%;" class="text-right">Harga Satuan (Rp)</th>
                                    <th scope="col" style="width:7%;" class="text-center">Qty</th>
                                    <th scope="col" style="width:10%;">Unit</th>
                                    <th scope="col" style="width:12%;" class="text-right">Total (Rp)</th>
                                    <th scope="col" class="col-notes">Notes</th>
                                    <th scope="col" style="width:5%;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="detailBody">
                                @forelse($budgetPlan->details as $i => $det)
                                <tr class="detail-row">
                                    <td class="text-center align-middle row-no">{{ $i + 1 }}</td>
                                    <td>
                                        <select name="details[{{ $i }}][account_id]"
                                                class="form-control form-control-sm select-account" required>
                                            <option value="">— Pilih Account —</option>
                                            @foreach($accounts as $acc)
                                                <option value="{{ $acc->id }}"
                                                    {{ $det->account_id == $acc->id ? 'selected' : '' }}>
                                                    {{ $acc->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text"
                                               name="details[{{ $i }}][deskripsi]"
                                               class="form-control form-control-sm"
                                               value="{{ $det->deskripsi }}"
                                               placeholder="Deskripsi item...">
                                    </td>
                                    <td>
                                        <input type="text"
                                               name="details[{{ $i }}][amount]"
                                               class="form-control form-control-sm text-right money-detail"
                                               value="{{ number_format($det->amount, 0, ',', '.') }}"
                                               placeholder="0">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0.01"
                                               name="details[{{ $i }}][qty]"
                                               class="form-control form-control-sm text-center qty-detail"
                                               value="{{ rtrim(rtrim(number_format((float) $det->qty, 2, '.', ''), '0'), '.') ?: 1 }}">
                                    </td>
                                    <td>
                                        <select name="details[{{ $i }}][unit]" class="form-control form-control-sm select-unit" required>
                                            <option value="">—</option>
                                            @foreach(\App\Models\BudgetPlanDetail::UNITS as $u)
                                                <option value="{{ $u }}" {{ $det->unit == $u ? 'selected' : '' }}>
                                                    {{ ucfirst($u) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text"
                                               class="form-control form-control-sm text-right total-detail"
                                               value="{{ number_format($det->total, 0, ',', '.') }}"
                                               readonly tabindex="-1">
                                    </td>
                                    <td class="col-notes">
                                        <input type="text"
                                               name="details[{{ $i }}][notes]"
                                               class="form-control form-control-sm"
                                               value="{{ $det->notes }}"
                                               placeholder="Keterangan...">
                                    </td>
                                    <td class="text-center align-middle">
                                        <button type="button" class="btn btn-danger btn-sm btn-remove-detail">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                {{-- jika kosong, tetap sediakan 1 baris --}}
                                <tr class="detail-row">
                                    <td class="text-center align-middle row-no">1</td>
                                    <td>
                                        <select name="details[0][account_id]" class="form-control form-control-sm select-account" required>
                                            <option value="">— Pilih Account —</option>
                                            @foreach($accounts as $acc)
                                                <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="text" name="details[0][deskripsi]" class="form-control form-control-sm" placeholder="Deskripsi item..."></td>
                                    <td><input type="text" name="details[0][amount]" class="form-control form-control-sm text-right money-detail" placeholder="0"></td>
                                    <td><input type="number" step="0.01" min="0.01" name="details[0][qty]" class="form-control form-control-sm text-center qty-detail" value="1"></td>
                                    <td>
                                        <select name="details[0][unit]" class="form-control form-control-sm select-unit" required>
                                            <option value="">—</option>
                                            @foreach(\App\Models\BudgetPlanDetail::UNITS as $u)
                                                <option value="{{ $u }}">{{ ucfirst($u) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="text" class="form-control form-control-sm text-right total-detail" value="0" readonly tabindex="-1"></td>
                                    <td class="col-notes"><input type="text" name="details[0][notes]" class="form-control form-control-sm" placeholder="Keterangan..."></td>
                                    <td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-detail"><i class="fas fa-trash"></i></button></td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <small class="text-muted">
                        <i class="fas fa-info-circle mr-1"></i>
                        Semua baris lama akan diganti. Total = Harga Satuan × Qty, dihitung otomatis.
                    </small>
                </div>
            </div>

            <!-- LAMPIRAN EXISTING -->
            @if($budgetPlan->attachments->count() > 0)
            <div class="card card-warning card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-paperclip mr-1"></i> Lampiran Existing</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:5%;" class="text-center">No</th>
                                    <th scope="col">Nama File</th>
                                    <th scope="col" style="width:12%;" class="text-center">Preview</th>
                                    <th scope="col" style="width:10%;" class="text-center">Hapus</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($budgetPlan->attachments as $i => $att)
                                <tr>
                                    <td class="text-center">{{ $i + 1 }}</td>
                                    <td><small class="text-muted">{{ $att->original_name }}</small></td>
                                    <td class="text-center">
                                        <a href="{{ asset('storage/' . $att->path) }}" target="_blank"
                                           class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox" name="delete_attachments[]"
                                               value="{{ $att->id }}"
                                               style="width:18px;height:18px;accent-color:var(--danger);">
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
                    <button type="button" class="btn btn-sm btn-primary ml-auto" id="btnAddAttachment">
                        <i class="fas fa-plus mr-1"></i> Tambah
                    </button>
                </div>
                <div class="card-body">
                    <div id="dropZoneBP" class="att-dropzone mb-3">
                        <i class="fas fa-cloud-upload-alt fa-2x mb-2 d-block"></i>
                        <p class="mb-0">
                            Drag &amp; drop file ke sini, paste gambar (Ctrl+V), atau
                            <span class="text-primary" style="text-decoration:underline;cursor:pointer;" id="dropZoneBrowseBP">klik untuk pilih</span>
                        </p>
                        <small class="text-muted">Bisa pilih/drop/paste beberapa file sekaligus</small>
                        <input type="file" id="dropZoneInputBP" multiple style="display:none;"
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:5%;" class="text-center">No</th>
                                    <th scope="col" style="width:50%;">File</th>
                                    <th scope="col" style="width:10%;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="attachmentBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <small class="text-muted">Format: pdf, jpg, png, doc(x), xls(x). Maks 5 MB per file.</small>
                </div>
            </div>

            <!-- ACTION -->
            <div class="card shadow-sm">
                <div class="card-body d-flex flex-wrap" style="gap:8px;">
                    <a href="{{ url('/budget-plan') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left mr-1"></i> Kembali
                    </a>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save mr-1"></i> Simpan Perubahan
                    </button>
                </div>
            </div>

        </form>

    </div>
</div>

<textarea id="accountsHolder" style="display:none;">{{ json_encode($accounts->map(fn($a) => ['id' => $a->id, 'name' => $a->name])) }}</textarea>
<textarea id="unitsHolder" style="display:none;">{{ json_encode(\App\Models\BudgetPlanDetail::UNITS) }}</textarea>
<input type="hidden" id="detailIdxHolder" value="{{ $budgetPlan->details->count() ?: 1 }}">

@endsection

@push('styles')
<style>
.font-weight-600 { font-weight:600; }
.table td, .table th { vertical-align:middle !important; }

.col-notes { display:none; }

td .tss-btn { padding:.25rem .5rem; font-size:13.5px; }

.att-dropzone {
    border: 2px dashed var(--border);
    border-radius: var(--radius-md);
    padding: 28px 20px;
    text-align: center;
    color: var(--text-faint);
    cursor: pointer;
    transition: all .2s ease;
    background: var(--surface-2);
}
.att-dropzone:hover {
    border-color: var(--accent);
    background: var(--warning-bg);
}
.att-dropzone.dragover {
    border-color: var(--accent);
    background: var(--warning-bg);
    color: var(--accent-dark);
}
</style>
@endpush

@push('scripts')
<script>
$(function () {

    var accounts  = JSON.parse(document.getElementById('accountsHolder').value);
    var units     = JSON.parse(document.getElementById('unitsHolder').value);
    var detailIdx = parseInt(document.getElementById('detailIdxHolder').value) || 1;
    var attIdx    = 0;

    // ── SEARCHABLE DROPDOWN: PO ──────────────────────────────────────
    if (document.getElementById('selProject')) {
        TesysSearchableSelect.init('#selProject', { placeholder: '— Pilih PO —', searchPlaceholder: 'Cari PO...' });
    }

    // ── SEARCHABLE DROPDOWN: Chart of Account & Unit per baris ────────
    function initDetailSelects(scope) {
        (scope || document).querySelectorAll('select.select-account').forEach(function (sel) {
            TesysSearchableSelect.initOne($(sel), { placeholder: '— Pilih Account —', searchPlaceholder: 'Cari Account...' });
        });
        (scope || document).querySelectorAll('select.select-unit').forEach(function (sel) {
            TesysSearchableSelect.initOne($(sel), { placeholder: '—', searchPlaceholder: 'Cari Unit...' });
        });
    }
    initDetailSelects(document.getElementById('detailBody'));

    function fmtRp(v) {
        v = (v || '').toString().replace(/[^\d]/g, '');
        return v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function recalcRow($row) {
        var amount = parseInt(($row.find('.money-detail').val() || '0').replace(/\./g, '')) || 0;
        var qty    = parseFloat($row.find('.qty-detail').val()) || 0;
        var total  = Math.round(amount * qty);
        $row.find('.total-detail').val(total.toLocaleString('id-ID'));
        return total;
    }

    function recalcTotal() {
        var total = 0;
        $('.detail-row').each(function () {
            total += recalcRow($(this));
        });
        $('#totalAmountLabel').text('Total: ' + total.toLocaleString('id-ID'));
    }

    $(document).on('input', '.money-detail', function () {
        this.value = fmtRp(this.value);
        recalcTotal();
    });

    $(document).on('input change', '.qty-detail', function () {
        recalcTotal();
    });

    // Init format on load
    $('.money-detail').each(function () { this.value = fmtRp(this.value); });
    recalcTotal();

    function buildAccountOptions(selectedId) {
        var html = '<option value="">— Pilih Account —</option>';
        accounts.forEach(function (a) {
            html += '<option value="' + a.id + '"' + (a.id == selectedId ? ' selected' : '') + '>' + a.name + '</option>';
        });
        return html;
    }

    function buildUnitOptions(selectedUnit) {
        var html = '<option value="">—</option>';
        units.forEach(function (u) {
            var label = u.charAt(0).toUpperCase() + u.slice(1);
            html += '<option value="' + u + '"' + (u === selectedUnit ? ' selected' : '') + '>' + label + '</option>';
        });
        return html;
    }

    // source_type asli budget plan ini bisa jadi tipe cost center apapun (OVERHEAD,
    // PAYROLL, TAX, ASSET, dst) — tapi endpoint Import/Download Template Account cuma
    // punya 3 kategori (Variable Cost/Fixed Cost/Overhead), jadi semua tipe non-project
    // & non-payroll di-collapse ke bucket 'OVERHEAD' yang sama (pola sama seperti
    // currentSourceKey() di create.blade.php).
    var rawSourceType = '{{ $budgetPlan->source_type }}';
    var bpSourceType  = (rawSourceType === 'PROJECT' || rawSourceType === 'PAYROLL') ? rawSourceType : 'OVERHEAD';

    // ── Download Template & Import Excel ────────────────────────────────
    $('#btnDownloadTemplate').on('click', function (e) {
        e.preventDefault();
        var url = '{{ url("/budget-plan/import-template") }}?source=' + bpSourceType;
        window.location.href = url;
    });

    $('#btnImportExcel').on('click', function () {
        $('#importFileInput').val('').trigger('click');
    });

    $('#importFileInput').on('change', function () {
        var file = this.files[0];
        if (!file) return;

        var formData = new FormData();
        formData.append('file', file);
        formData.append('source_type', bpSourceType);
        formData.append('_token', '{{ csrf_token() }}');

        Swal.fire({
            title: 'Memproses file...', text: 'Mohon tunggu sebentar.',
            allowOutsideClick: false, didOpen: function () { Swal.showLoading(); }
        });

        $.ajax({
            url: '{{ url("/budget-plan/import-preview") }}',
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
                $('#detailBody').empty();
                detailIdx = 0;

                res.rows.forEach(function (r) {
                    var i = detailIdx++;
                    var accOptions = buildAccountOptions(r.account_id || '');
                    $('#detailBody').append(
                        '<tr class="detail-row">' +
                            '<td class="text-center align-middle row-no"></td>' +
                            '<td><select name="details[' + i + '][account_id]" class="form-control form-control-sm select-account" required>' + accOptions + '</select></td>' +
                            '<td><input type="text" name="details[' + i + '][deskripsi]" class="form-control form-control-sm" value="' + (r.deskripsi || '') + '" placeholder="Deskripsi item..."></td>' +
                            '<td><input type="text" name="details[' + i + '][amount]" class="form-control form-control-sm text-right money-detail" value="' + fmtRp(String(r.amount || 0)) + '"></td>' +
                            '<td><input type="number" step="0.01" min="0.01" name="details[' + i + '][qty]" class="form-control form-control-sm text-center qty-detail" value="' + (r.qty || 1) + '"></td>' +
                            '<td><select name="details[' + i + '][unit]" class="form-control form-control-sm select-unit" required>' + buildUnitOptions(r.unit || '') + '</select></td>' +
                            '<td><input type="text" class="form-control form-control-sm text-right total-detail" value="0" readonly tabindex="-1"></td>' +
                            '<td class="col-notes"><input type="text" name="details[' + i + '][notes]" class="form-control form-control-sm" value="' + (r.notes || '') + '" placeholder="Keterangan..."></td>' +
                            '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-detail"><i class="fas fa-trash"></i></button></td>' +
                        '</tr>'
                    );
                });

                initDetailSelects(document.getElementById('detailBody'));
                renumber();
                recalcTotal();

                Swal.fire({
                    icon: 'success', title: 'Import berhasil',
                    text: res.rows.length + ' baris data dimuat ke Detail Budget.',
                    confirmButtonColor: 'var(--success)', timer: 2000, showConfirmButton: false
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

    $('#btnAddDetail').on('click', function () {
        var i = detailIdx++;
        $('#detailBody').append(
            '<tr class="detail-row">' +
                '<td class="text-center align-middle row-no"></td>' +
                '<td><select name="details[' + i + '][account_id]" class="form-control form-control-sm select-account" required>' + buildAccountOptions('') + '</select></td>' +
                '<td><input type="text" name="details[' + i + '][deskripsi]" class="form-control form-control-sm" placeholder="Deskripsi item..."></td>' +
                '<td><input type="text" name="details[' + i + '][amount]" class="form-control form-control-sm text-right money-detail" placeholder="0"></td>' +
                '<td><input type="number" step="0.01" min="0.01" name="details[' + i + '][qty]" class="form-control form-control-sm text-center qty-detail" value="1"></td>' +
                '<td><select name="details[' + i + '][unit]" class="form-control form-control-sm select-unit" required>' + buildUnitOptions('') + '</select></td>' +
                '<td><input type="text" class="form-control form-control-sm text-right total-detail" value="0" readonly tabindex="-1"></td>' +
                '<td class="col-notes"><input type="text" name="details[' + i + '][notes]" class="form-control form-control-sm" placeholder="Keterangan..."></td>' +
                '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-detail"><i class="fas fa-trash"></i></button></td>' +
            '</tr>'
        );
        initDetailSelects(document.getElementById('detailBody'));
        renumber();
    });

    $(document).on('click', '.btn-remove-detail', function () {
        if ($('.detail-row').length > 1) {
            $(this).closest('tr').remove();
            renumber();
            recalcTotal();
        } else {
            Swal.fire({ icon:'warning', title:'Minimal 1 baris', confirmButtonColor:'#92232b' });
        }
    });

    function renumber() {
        $('.detail-row .row-no').each(function (i) { $(this).text(i + 1); });
    }

    // ── Tambah Lampiran (+ drag & drop multi-file) ─────────────────────
    function addAttachmentRowBP(file) {
        var i = attIdx++;
        var $tr = $(
            '<tr>' +
                '<td class="text-center align-middle att-no"></td>' +
                '<td><input type="file" name="attachments[' + i + '][file]" class="form-control-file"></td>' +
                '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-att"><i class="fas fa-trash"></i></button></td>' +
            '</tr>'
        );
        $('#attachmentBody').append($tr);

        if (file) {
            var input = $tr.find('input[type="file"]')[0];
            try {
                var dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
            } catch (err) {
                // Browser lama tanpa dukungan DataTransfer — baris tetap dibuat kosong.
            }
        }
        renumberAtt();
    }

    $('#btnAddAttachment').on('click', function () {
        addAttachmentRowBP(null);
    });

    $(document).on('click', '.btn-remove-att', function () {
        $(this).closest('tr').remove();
        renumberAtt();
    });

    function renumberAtt() {
        $('#attachmentBody tr .att-no').each(function (i) { $(this).text(i + 1); });
    }

    // Drop zone
    (function () {
        var zone   = document.getElementById('dropZoneBP');
        var input  = document.getElementById('dropZoneInputBP');
        var browse = document.getElementById('dropZoneBrowseBP');

        zone.addEventListener('click', function () { input.click(); });
        browse.addEventListener('click', function (e) {
            e.stopPropagation();
            input.click();
        });

        input.addEventListener('change', function () {
            Array.from(this.files).forEach(function (f) { addAttachmentRowBP(f); });
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
                Array.from(files).forEach(function (f) { addAttachmentRowBP(f); });
            }
        });

        // ── PASTE IMAGE FROM CLIPBOARD (Ctrl+V) — tidak perlu file fisik ──
        function handlePasteBP(e) {
            var items = (e.clipboardData || window.clipboardData) ? (e.clipboardData || window.clipboardData).items : null;
            if (!items) return;
            var found = false;
            for (var i = 0; i < items.length; i++) {
                if (items[i].kind === 'file') {
                    var f = items[i].getAsFile();
                    if (f) {
                        var ext = (f.type.split('/')[1] || 'png');
                        var renamed = new File([f], 'pasted-image-' + Date.now() + '.' + ext, { type: f.type });
                        addAttachmentRowBP(renamed);
                        found = true;
                    }
                }
            }
            if (found) e.preventDefault();
        }
        zone.addEventListener('paste', handlePasteBP);
        document.addEventListener('paste', function (e) {
            // Hanya tangkap paste global kalau fokus bukan di field teks lain
            var tag = (document.activeElement && document.activeElement.tagName) || '';
            if (tag === 'INPUT' || tag === 'TEXTAREA') return;
            handlePasteBP(e);
        });
    })();

});
</script>
@endpush