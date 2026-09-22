@extends('layouts.app')

@section('title', 'Edit Cost Request')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ url('/budget-request') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Edit Cost Request</h1>
                    <small class="text-muted">
                        Kode: <strong>{{ $cr->code }}</strong>
                        &nbsp;·&nbsp;
                        <span class="badge badge-{{ $cr->status_color }}">{{ $cr->status }}</span>
                    </small>
                </div>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item"><a href="{{ url('/budget-request') }}">Cost Request</a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </div>

        {{-- Catatan revisi dari manager/finance --}}
        @if($cr->statusLogs->first() && in_array($cr->statusLogs->first()->action_type, ['manager_revised','finance_revised']))
        <div class="alert alert-warning mb-3">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <strong>Catatan Revisi ({{ $cr->statusLogs->first()->actor_role }}):</strong>
            {{ $cr->statusLogs->first()->notes }}
        </div>
        @endif

        <form action="{{ url('/budget-request/' . $cr->id) }}" method="POST"
              enctype="multipart/form-data" id="frmCR">
            @csrf
            @method('PUT')

            <!-- SECTION 1: INFORMASI UTAMA -->
            <div class="card card-warning card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-file-invoice-dollar mr-1"></i> Informasi Cost Request
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
                    <div class="row po-two-col ratio-70-30 form-clean">

                        <!-- KOLOM KIRI -->
                        <div class="col-md-6 po-col-left">

                            <!-- BUDGET PLAN -->
                            <div class="form-group">
                                <label class="font-weight-600">Budget Plan <span class="text-danger">*</span></label>
                                <select name="budget_plan_id" id="selectBudgetPlan"
                                        class="form-control @error('budget_plan_id') is-invalid @enderror" required>
                                    <option value="">— Pilih Budget Plan —</option>
                                    @foreach($budgetPlans as $bp)
                                        @php
                                            $bpLabel = $bp->isProjectBudget()
                                                ? ($bp->project?->customer?->initial ?? '-') . ' - [' . ($bp->project?->code ?? '-') . '] ' . $bp->description
                                                : ($bp->costCenter?->name ?? '-') . ' - ' . $bp->description;
                                        @endphp
                                        <option value="{{ $bp->id }}"
                                            data-remaining="{{ $bp->remainingAmount() }}"
                                            data-source="{{ $bp->source_type }}"
                                            data-label="{{ $bpLabel }}"
                                            {{ old('budget_plan_id', $cr->budget_plan_id) == $bp->id ? 'selected' : '' }}>
                                            {{ $bpLabel }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">
                                    Sisa budget: <strong id="remainingBudget" class="text-success">—</strong>
                                </small>
                                @error('budget_plan_id')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>

                            <!-- TIPE + BENEFICIARY -->
                            <div class="d-flex" style="gap:12px;">
                                <div class="form-group" style="flex:0 0 34%;">
                                    <label class="font-weight-600">Tipe Request <span class="text-danger">*</span></label>
                                    <select name="type_id" id="selectType" class="form-control @error('type_id') is-invalid @enderror" required>
                                        <option value="">— Pilih Tipe —</option>
                                        @foreach($types as $type)
                                            <option value="{{ $type->id }}" data-name="{{ strtolower($type->name) }}"
                                                {{ old('type_id', $cr->type_id) == $type->id ? 'selected' : '' }}>
                                                {{ $type->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('type_id')<small class="text-danger d-block">{{ $message }}</small>@enderror
                                </div>
                                <div class="form-group" style="flex:1 1 auto;min-width:0;">
                                    <label class="font-weight-600">Penerima Dana <span class="text-danger">*</span></label>
                                    <select name="beneficiary_id" id="selectBeneficiary"
                                            class="form-control @error('beneficiary_id') is-invalid @enderror" required>
                                        <option value="">— Pilih Beneficiary —</option>
                                        @foreach($beneficiaries as $ben)
                                            @php $benLabel = $ben->complete_name . ' — ' . ($ben->bank?->name ?? '-') . ' ' . ($ben->account_number ?? '-'); @endphp
                                            <option value="{{ $ben->id }}"
                                                data-bank="{{ $ben->bank?->name ?? '-' }}"
                                                data-account="{{ $ben->account_number ?? '-' }}"
                                                data-email="{{ $ben->contact ?? '' }}"
                                                data-label="{{ $benLabel }}"
                                                {{ old('beneficiary_id', $cr->beneficiary_id) == $ben->id ? 'selected' : '' }}>
                                                {{ $benLabel }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted" id="beneficiaryInfo">
                                        @if($cr->beneficiary)
                                            <i class="fas fa-university mr-1"></i>
                                            {{ $cr->beneficiary->bank?->name ?? '-' }} — {{ $cr->beneficiary->account_number ?? '-' }}
                                        @endif
                                    </small>
                                    @error('beneficiary_id')<small class="text-danger d-block">{{ $message }}</small>@enderror
                                </div>
                            </div>

                            <!-- DESKRIPSI -->
                            <div class="form-group mb-0">
                                <label class="font-weight-600">Deskripsi / Keperluan <span class="text-danger">*</span></label>
                                <input type="text" name="description"
                                       class="form-control @error('description') is-invalid @enderror"
                                       value="{{ old('description', $cr->description) }}"
                                       placeholder="Jelaskan keperluan pengajuan dana..."
                                       autocomplete="off" required>
                                @error('description')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>

                        </div>

                        <!-- KOLOM KANAN -->
                        <div class="col-md-6 po-col-right">

                            <!-- PROJECT — hanya untuk budget yang bersumber dari PO -->
                            <div class="form-group" id="clusterBox">
                                <label class="font-weight-600">Project <span class="text-danger">*</span></label>
                                <select name="cluster_id" id="selectCluster"
                                        class="form-control @error('cluster_id') is-invalid @enderror" required>
                                    <option value="">— Pilih Project —</option>
                                    @foreach($clusters as $cluster)
                                        <option value="{{ $cluster->id }}"
                                            {{ old('cluster_id', $cr->cluster_id) == $cluster->id ? 'selected' : '' }}>
                                            [{{ $cluster->code }}] {{ $cluster->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('cluster_id')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>

                            <!-- EMAIL NOTIFIKASI -->
                            <div class="form-group">
                                <label class="font-weight-600">Email Notifikasi</label>
                                <div id="emailTagsBox" class="form-control @error('email') is-invalid @enderror"
                                     style="height:auto;min-height:38px;display:flex;flex-wrap:wrap;align-items:center;gap:6px;padding:6px 8px;cursor:text;">
                                    <input type="text" id="emailTagInput"
                                           style="border:none;outline:none;flex:1 1 auto;min-width:140px;padding:2px 0;"
                                           placeholder="Tambah email...">
                                </div>
                                <input type="hidden" name="email" id="emailHidden" value="{{ old('email', $cr->email) }}">
                                <small class="text-muted">Tekan Enter atau koma (,) untuk menambahkan email.</small>
                                @error('email')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>

                            <!-- NOTES -->
                            <div class="form-group mb-0">
                                <label class="font-weight-600">Notes</label>
                                <textarea name="notes" class="form-control" rows="2"
                                          placeholder="Catatan tambahan...">{{ old('notes', $cr->notes) }}</textarea>
                            </div>

                        </div>

                        <!-- NOB WARNING -->
                        <div class="col-12 mt-2">
                            <div id="nobWarning" class="alert alert-warning mb-0" style="display:none;font-size:13px;">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                <strong>Perhatian:</strong> Total pengajuan melebihi sisa budget.
                                Deskripsi akan otomatis ditambahkan prefix <strong>[NOB]</strong>.
                            </div>
                        </div>

                        <!-- WORKFLOW INFO berdasarkan Tipe Request -->
                        <div class="col-12 mt-2">
                            <div id="workflowAlert" class="alert mb-0" style="display:none;font-size:13px;"></div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- SECTION 2: RINCIAN PENGAJUAN -->
            <div class="card card-warning card-outline shadow-sm">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title"><i class="fas fa-list-ul mr-1"></i> Rincian Pengajuan</h3>
                    <div class="ml-auto d-flex align-items-center" style="gap:8px;">
                        <span id="totalLabel" class="badge badge-primary px-3 py-2" style="font-size:13px;">Total: 0</span>
                        <a href="#" id="btnDownloadDetailTemplate" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-file-excel mr-1"></i> Download Template
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnImportDetailExcel">
                            <i class="fas fa-file-import mr-1"></i> Import Excel
                        </button>
                        <input type="file" id="importDetailFileInput" accept=".xlsx,.xls" style="display:none;">
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
                                    <th scope="col" style="width:25%;">Sub Item Budget</th>
                                    <th scope="col" style="width:20%;">Deskripsi</th>
                                    <th scope="col" class="text-right col-remaining" style="display:none;">Sisa Tersedia</th>
                                    <th scope="col" style="width:12%;" class="text-right">Harga Satuan</th>
                                    <th scope="col" style="width:6%;" class="text-center">Qty</th>
                                    <th scope="col" style="width:12%;" class="text-right">Total</th>
                                    <th scope="col" style="width:10%;" class="text-center">Lampiran Item</th>
                                    <th scope="col" style="width:5%;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="detailBody">
                                <!-- Diisi oleh JS setelah sub item Budget Plan berhasil di-fetch -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <small class="text-muted">
                        <i class="fas fa-info-circle mr-1"></i>
                        Data lama akan diganti seluruhnya saat disimpan.
                    </small>
                </div>
            </div>

            <!-- SECTION 3: LAMPIRAN EXISTING (global/pendukung) -->
            @if($cr->requestAttachments()->count() > 0)
            <div class="card card-warning card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-paperclip mr-1"></i> Lampiran Tambahan / Pendukung (Existing)</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:5%;" class="text-center">No</th>
                                    <th scope="col" style="width:65%;">Nama File</th>
                                    <th scope="col" style="width:15%;" class="text-center">Preview</th>
                                    <th scope="col" style="width:15%;" class="text-center">Hapus</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($cr->requestAttachments()->get() as $i => $att)
                                <tr>
                                    <td class="text-center align-middle">{{ $i + 1 }}</td>
                                    <td class="align-middle">
                                        <small class="text-muted">{{ $att->original_name }}</small>
                                        <small class="text-muted ml-2">({{ $att->size_formatted }})</small>
                                    </td>
                                    <td class="text-center align-middle">
                                        <a href="{{ asset('storage/' . $att->path) }}" target="_blank"
                                           class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                    <td class="text-center align-middle">
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
                    <small class="text-danger">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Centang file yang ingin dihapus, lalu klik Simpan.
                    </small>
                </div>
            </div>
            @endif

            <!-- SECTION 4: LAMPIRAN BARU -->
            <div class="card card-warning card-outline shadow-sm">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-upload mr-1"></i> Tambah Lampiran Tambahan / Pendukung
                        <span id="attRequiredBadge" class="badge badge-danger ml-1" style="display:none;">Wajib</span>
                    </h3>
                    <button type="button" class="btn btn-sm btn-primary ml-auto" id="btnAddAttachment">
                        <i class="fas fa-plus mr-1"></i> Tambah Lampiran
                    </button>
                </div>
                <div class="card-body">
                    <div class="alert alert-light border mb-3" style="font-size:12px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        Bukti <strong>global / pendukung</strong> (bukan khusus satu item), contoh: foto undangan meeting.
                        Nota/bukti <strong>per item</strong> di-upload di kolom <strong>“Lampiran Item”</strong> pada tabel Rincian Pengajuan.
                    </div>
                    <div id="dropZoneCR" class="att-dropzone mb-3" tabindex="0">
                        <i class="fas fa-cloud-upload-alt fa-2x mb-2 d-block"></i>
                        <p class="mb-0">
                            Drag &amp; drop file ke sini, paste gambar (Ctrl+V), atau
                            <span class="text-primary" style="text-decoration:underline;cursor:pointer;" id="dropZoneBrowseCR">klik untuk pilih</span>
                        </p>
                        <small class="text-muted">Bisa pilih/drop/paste beberapa file sekaligus</small>
                        <input type="file" id="dropZoneInputCR" multiple style="display:none;">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:5%;" class="text-center">No</th>
                                    <th scope="col" style="width:85%;">File</th>
                                    <th scope="col" style="width:10%;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="attachmentBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <small class="text-muted">
                        <i class="fas fa-info-circle mr-1"></i>
                        Format: pdf, jpg, png, doc(x), xls(x). Maks 5 MB per file.
                    </small>
                </div>
            </div>

            <!-- ACTION -->
            <div class="card shadow-sm">
                <div class="card-body d-flex flex-wrap" style="gap:8px;">
                    <a href="{{ url('/budget-request') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left mr-1"></i> Kembali
                    </a>
                    <button type="submit" class="btn btn-warning" id="btnSaveCR">
                        <i class="fas fa-save mr-1"></i> Simpan Perubahan
                    </button>
                    <small id="saveHint" class="text-danger align-self-center" style="display:none;"></small>
                </div>
            </div>

        </form>

        {{-- Submit form (terpisah) --}}
        @if($cr->isDraft() && $cr->details->count() > 0)
        <form action="{{ url('/budget-request/' . $cr->id . '/submit') }}"
              method="POST" id="frmSubmit">
            @csrf
        </form>
        <div class="card shadow-sm border-primary">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <strong class="text-primary">Siap diajukan?</strong>
                    <p class="mb-0 text-muted" style="font-size:13px;">
                        Setelah submit, CR tidak dapat diedit sampai dikembalikan oleh Manager.
                    </p>
                </div>
                <button type="button" class="btn btn-primary ml-3" id="btnSubmit">
                    <i class="fas fa-paper-plane mr-1"></i> Submit ke Manager
                </button>
            </div>
        </div>
        @endif

    </div>
</div>

<textarea id="accountsHolder" style="display:none;">{{ json_encode($accounts->map(fn($a) => ['id' => $a->id, 'name' => $a->name])) }}</textarea>

<textarea id="crDetailsHolder" style="display:none;">{{ json_encode($cr->details->map(fn($d) => [
    'id'               => $d->id,
    'budget_detail_id' => $d->budget_detail_id,
    'amount'           => $d->amount,
    'qty'              => $d->qty,
    'notes'            => $d->notes,
    'attachments'      => $d->attachments->map(fn($a) => [
        'id'   => $a->id,
        'name' => $a->original_name,
        'url'  => asset('storage/' . $a->path),
    ]),
])) }}</textarea>

{{-- Account per jenis sumber dana (di-swap saat Budget Plan berubah) --}}
<textarea id="accountsBySourceHolder" style="display:none;">{{ json_encode([
    'PROJECT'  => $accountsBySource['PROJECT']->map(fn($a) => ['id' => $a->id, 'name' => $a->name]),
    'OVERHEAD' => $accountsBySource['OVERHEAD']->map(fn($a) => ['id' => $a->id, 'name' => $a->name]),
    'PAYROLL'  => $accountsBySource['PAYROLL']->map(fn($a) => ['id' => $a->id, 'name' => $a->name]),
]) }}</textarea>

<textarea id="existingAttachmentCountHolder" style="display:none;">{{ $cr->requestAttachments()->count() }}</textarea>

<script>
document.addEventListener('DOMContentLoaded', function () {

    var accounts      = JSON.parse(document.getElementById('accountsHolder').value);
    var crDetails      = JSON.parse(document.getElementById('crDetailsHolder').value) || [];
    var budgetDetails = []; // sub-item Budget Plan yang sedang aktif (di-fetch via AJAX)
    var firstLoad      = true;

    var ATTACHMENT_REQUIRED_TYPES = ['payment', 'pettycash', 'petty cash', 'purchase', 'reimbursement'];
    var existingAttachmentCount = parseInt(document.getElementById('existingAttachmentCountHolder').value, 10) || 0;

    // ── SEARCHABLE DROPDOWN: Budget Plan & Beneficiary ──────────────
    TesysSearchableSelect.init('#selectBudgetPlan', { placeholder: '— Pilih Budget Plan —', searchPlaceholder: 'Cari Budget Plan...' });
    TesysSearchableSelect.init('#selectBeneficiary', { placeholder: '— Pilih Beneficiary —', searchPlaceholder: 'Cari Beneficiary...' });

    function fmt(v) {
        v = (v || '').toString().replace(/\D/g, '');
        return v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    function parse(v) {
        return parseInt((v || '').toString().replace(/\D/g, '')) || 0;
    }

    function calcRow(row) {
        var amount = parse(row.querySelector('.inp-amount').value);
        var qty    = parseInt(row.querySelector('.inp-qty').value) || 1;
        var total  = amount * qty;
        row.querySelector('.inp-total').value = fmt(total.toString());

        // Tandai badge [NOB][selisih] bila total baris melebihi sisa sub-item budget
        // (badge terpisah & read-only — user TIDAK bisa menghapusnya dari Notes)
        var sel      = row.querySelector('.select-budget-detail');
        var bd       = sel ? budgetDetails.find(function (b) { return String(b.id) === String(sel.value); }) : null;
        var nobBadge = row.querySelector('.nob-badge');
        if (nobBadge) {
            var isNob = !!(bd && total > bd.remaining);
            if (isNob) {
                var overage = total - bd.remaining;
                nobBadge.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i>[NOB][' + overage.toLocaleString('id-ID') + ']';
                nobBadge.classList.remove('d-none');
            } else {
                nobBadge.classList.add('d-none');
            }
        }

        return total;
    }

    function recalc() {
        var grand = 0;
        document.querySelectorAll('#detailBody .detail-row').forEach(function (row) {
            grand += calcRow(row);
        });
        document.getElementById('totalLabel').textContent =
            'Total: ' + grand.toLocaleString('id-ID');
        if (window.__updateSubmitState) window.__updateSubmitState();
    }

    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('inp-amount')) {
            e.target.value = fmt(e.target.value);
            recalc();
        }
        if (e.target.classList.contains('inp-qty')) recalc();
    });

    document.querySelectorAll('.inp-amount').forEach(function (el) {
        el.value = fmt(el.value);
    });
    recalc();

    function renumber(tbodyId) {
        document.querySelectorAll('#' + tbodyId + ' .row-no').forEach(function (el, i) {
            el.textContent = i + 1;
        });
    }

    function buildSubItemOptions(selectedId) {
        var html = '<option value="">— Pilih Sub Item —</option>';
        budgetDetails.forEach(function (bd) {
            var sel = (selectedId && String(selectedId) === String(bd.id)) ? ' selected' : '';
            var label = bd.account + ' - ' + (bd.deskripsi || '-') + ' (' + (bd.unit || '-') + ') - ' + bd.remaining_formatted;
            html += '<option value="' + bd.id + '"' + sel + '>' + label + '</option>';
        });
        return html;
    }

    // ── AKTIFKAN DROPDOWN SEARCHABLE UNTUK SELECT SUB ITEM DI TIAP BARIS ──
    function initSubItemPicker(scope) {
        (scope || document).querySelectorAll('select.select-budget-detail').forEach(function (sel) {
            TesysSearchableSelect.initOne($(sel), {
                placeholder: '— Pilih Sub Item —',
                searchPlaceholder: 'Cari Sub Item...',
                onChange: function (selectEl) {
                    updateRemainingCell(selectEl.closest('tr'), true);
                    recalc();
                }
            });
        });
    }

    // force = true kalau dipanggil dari event "user eksplisit ganti Sub Item"
    // (harga SELALU disegarkan ke default Sub Item yang baru). Tanpa force,
    // hanya isi kalau field masih kosong/0 — dipakai saat render awal baris
    // supaya nilai amount tersimpan dari database (CR yang diedit) tidak tertimpa.
    function updateRemainingCell(row, force) {
        var sel  = row.querySelector('.select-budget-detail');
        var cell = row.querySelector('.cell-remaining');
        var bd   = budgetDetails.find(function (b) { return String(b.id) === String(sel.value); });

        if (!bd) {
            cell.textContent = '—';
            cell.style.color = '';
            return;
        }

        cell.textContent = bd.remaining_formatted;
        cell.style.color = bd.remaining > 0 ? 'var(--success)' : 'var(--danger)';

        var amountInput = row.querySelector('.inp-amount');
        if (force || !amountInput.value || amountInput.value === '0') {
            amountInput.value = fmt(String(bd.amount));
            calcRow(row);
        }
    }

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('select-budget-detail')) {
            updateRemainingCell(e.target.closest('tr'), true);
            recalc();
        }
    });

    // ── EMAIL NOTIFIKASI — tag/badge input ──────────────────────────
    (function () {
        var box    = document.getElementById('emailTagsBox');
        var input  = document.getElementById('emailTagInput');
        var hidden = document.getElementById('emailHidden');
        var emails = (hidden.value || '').split(',').map(function (e) { return e.trim(); }).filter(Boolean);

        function sync() {
            hidden.value = emails.join(',');
        }

        function render() {
            box.querySelectorAll('.email-tag').forEach(function (el) { el.remove(); });
            emails.forEach(function (email) {
                var tag = document.createElement('span');
                tag.className = 'badge badge-light border email-tag';
                tag.style.cssText = 'font-size:12px;font-weight:500;padding:5px 8px;display:inline-flex;align-items:center;gap:6px;';
                tag.textContent = email;
                var x = document.createElement('i');
                x.className = 'fas fa-times';
                x.style.cssText = 'cursor:pointer;opacity:.6;font-size:10px;';
                x.addEventListener('click', function () {
                    emails = emails.filter(function (e) { return e !== email; });
                    sync();
                    render();
                });
                tag.appendChild(x);
                box.insertBefore(tag, input);
            });
            sync();
        }

        function addEmail(raw) {
            raw.split(',').forEach(function (part) {
                var email = part.trim();
                if (email && emails.indexOf(email) === -1) {
                    emails.push(email);
                }
            });
            input.value = '';
            render();
        }

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                if (input.value.trim()) addEmail(input.value);
            } else if (e.key === 'Backspace' && !input.value && emails.length) {
                emails.pop();
                sync();
                render();
            }
        });
        input.addEventListener('blur', function () {
            if (input.value.trim()) addEmail(input.value);
        });
        box.addEventListener('click', function (e) {
            if (e.target === box) input.focus();
        });

        // Render nilai existing (email CR) dulu, baru tambahkan email user yang login.
        render();
        addEmail('{{ Auth::user()->email }}');

        window.__addNotifyEmail = addEmail;
    })();

    // Beneficiary info + auto-fill email
    document.getElementById('selectBeneficiary').addEventListener('change', function () {
        var opt  = this.options[this.selectedIndex];
        var info = document.getElementById('beneficiaryInfo');
        if (this.value) {
            info.innerHTML = '<i class="fas fa-university mr-1"></i>' +
                (opt.dataset.bank || '-') + ' — ' + (opt.dataset.account || '-');
        } else {
            info.textContent = '';
        }

        var email = opt.dataset.email || '';
        if (this.value && email) {
            window.__addNotifyEmail(email);
        }
    });

    // Sisa budget + sumber dana
    var bpSelect = document.getElementById('selectBudgetPlan');

    /* Budget cost center (overhead/payroll) tidak terhubung ke project,
       jadi dropdown Project disembunyikan & dikosongkan. */
    function applySource(sourceType) {
        var isProject  = (sourceType === 'PROJECT' || !sourceType);
        var clusterSel = document.getElementById('selectCluster');
        var box        = document.getElementById('clusterBox');

        if (box) box.style.display = isProject ? '' : 'none';
        if (clusterSel) {
            clusterSel.required = isProject;
            if (!isProject) clusterSel.value = '';
        }
    }

    // ── SUB ITEM BUDGET (fetch via AJAX tiap Budget Plan berganti) ──
    function fetchBudgetDetails(bpId, prefillFromExisting) {
        var tbody = document.getElementById('detailBody');

        if (!bpId) {
            budgetDetails = [];
            tbody.innerHTML = '';
            recalc();
            return;
        }

        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3">' +
            '<i class="fas fa-spinner fa-spin mr-1"></i> Memuat sub item budget...</td></tr>';

        fetch('{{ url("/api/budget-plan") }}/' + bpId + '/details')
            .then(function (res) { return res.json(); })
            .then(function (data) {
                budgetDetails = data || [];
                tbody.innerHTML = '';

                var rows = (prefillFromExisting && crDetails.length) ? crDetails : [null];
                rows.forEach(function (d) {
                    tbody.insertAdjacentHTML('beforeend', makeDetailRow(detailIdx++, d));
                });

                renumber('detailBody');
                initSubItemPicker(tbody);
                document.querySelectorAll('#detailBody select.select-budget-detail').forEach(function (sel) {
                    updateRemainingCell(sel.closest('tr'));
                });
                recalc();
            })
            .catch(function () {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-3">' +
                    'Gagal memuat sub item budget. Silakan coba lagi.</td></tr>';
            });
    }

    function updateRemaining() {
        var opt       = bpSelect.options[bpSelect.selectedIndex];
        var remaining = parseInt(opt.dataset.remaining) || 0;
        var el        = document.getElementById('remainingBudget');

        if (bpSelect.value) {
            el.textContent = remaining.toLocaleString('id-ID');
            el.style.color = remaining > 0 ? 'var(--success)' : 'var(--danger)';
        } else {
            el.textContent = '—';
            el.style.color = '';
        }

        applySource(bpSelect.value ? (opt.dataset.source || 'PROJECT') : 'PROJECT');
        fetchBudgetDetails(bpSelect.value, firstLoad);
        firstLoad = false;
    }

    bpSelect.addEventListener('change', updateRemaining);
    updateRemaining(); // init on load

    // Detail rows
    // ── NOB CHECK ──────────────────────────────────────────────────
    function checkNOB() {
        var bpSel = document.getElementById('selectBudgetPlan');
        if (!bpSel || !bpSel.value) { var w = document.getElementById('nobWarning'); if(w) w.style.display='none'; return; }
        var opt       = bpSel.options[bpSel.selectedIndex];
        var remaining = parseInt(opt.dataset.remaining) || 0;
        var grand     = 0;
        document.querySelectorAll('#detailBody .detail-row').forEach(function (row) {
            var amount = parseInt((row.querySelector('.inp-amount').value || '0').replace(/\./g,'')) || 0;
            var qty    = parseInt(row.querySelector('.inp-qty').value) || 1;
            grand += amount * qty;
        });
        var isNob = grand > remaining;
        var w = document.getElementById('nobWarning');
        if (w) w.style.display = isNob ? 'block' : 'none';
    }

    var detailIdx = 0;

    function makeDetailRow(i, prefill) {
        prefill = prefill || {};
        var subOpts   = buildSubItemOptions(prefill.budget_detail_id || null);
        var amountVal = prefill.amount ? fmt(String(prefill.amount)) : '';
        var qtyVal    = prefill.qty || 1;
        var notesVal  = (prefill.notes || '').replace(/"/g, '&quot;');
        var idField   = prefill.id ? '<input type="hidden" name="details[' + i + '][id]" value="' + prefill.id + '">' : '';

        var existAtt = (prefill.attachments || []).map(function (a) {
            return '<label class="d-block text-muted mb-0" style="font-weight:400;cursor:pointer;">' +
                '<input type="checkbox" name="delete_detail_attachments[]" value="' + a.id + '" class="del-detail-att"> ' +
                '<a href="' + a.url + '" target="_blank" rel="noopener"><i class="fas fa-file mr-1"></i>' +
                (a.name || 'file').replace(/</g, '&lt;') + '</a></label>';
        }).join('');

        return '<tr class="detail-row">' + idField +
            '<td class="text-center align-middle row-no"></td>' +
            '<td><select name="details[' + i + '][budget_detail_id]" class="form-control form-control-sm select-budget-detail" required>' + subOpts + '</select></td>' +
            '<td>' +
                '<span class="badge badge-danger nob-badge d-none mb-1"><i class="fas fa-exclamation-triangle mr-1"></i>NOB</span>' +
                '<input type="text" name="details[' + i + '][notes]" class="form-control form-control-sm" value="' + notesVal + '" placeholder="Deskripsi..." required>' +
            '</td>' +
            '<td class="text-right cell-remaining col-remaining" style="display:none;">—</td>' +
            '<td><input type="text" name="details[' + i + '][amount]" class="form-control form-control-sm text-right inp-amount" value="' + amountVal + '" placeholder="0" required></td>' +
            '<td><input type="number" name="details[' + i + '][qty]" class="form-control form-control-sm text-center inp-qty" value="' + qtyVal + '" min="1" required></td>' +
            '<td><input type="text" name="details[' + i + '][total_display]" class="form-control form-control-sm text-right inp-total" value="0" readonly style="background:var(--surface-2);"></td>' +
            '<td class="align-middle detail-att-cell" data-idx="' + i + '" tabindex="0">' +
                '<input type="file" name="details[' + i + '][attachments][]" class="d-none detail-att-input" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">' +
                '<button type="button" class="btn btn-outline-secondary btn-sm btn-detail-att" style="white-space:nowrap;padding:2px 8px;font-size:11px;">' +
                    '<i class="fas fa-paperclip"></i> <span class="detail-att-label">Lampiran</span></button>' +
                '<div class="detail-att-hint text-muted" style="font-size:9px;">drop / paste / klik</div>' +
                (existAtt ? '<div class="mt-1" style="font-size:10px;line-height:1.5;">' + existAtt + '</div>' : '') +
                '<div class="detail-att-list mt-1" style="font-size:10px;line-height:1.5;"></div>' +
            '</td>' +
            '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-detail"><i class="fas fa-trash"></i></button></td>' +
        '</tr>';
    }

    // ── LAMPIRAN PER ITEM (drag & drop + paste + klik) ─────────────────────
    function appendFilesToInput(input, fileList) {
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
        label.textContent = files.length ? (files.length + ' file baru') : 'Lampiran';
        list.innerHTML = files.map(function (f, idx) {
            return '<div class="text-muted"><i class="fas fa-file mr-1"></i>' +
                f.name.replace(/</g, '&lt;') +
                ' <i class="fas fa-times text-danger detail-att-del" data-i="' + idx + '" style="cursor:pointer;"></i></div>';
        }).join('');
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
        if (window.__updateSubmitState) window.__updateSubmitState();
    });

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('del-detail-att')) {
            var lbl = e.target.closest('label');
            if (lbl) lbl.style.textDecoration = e.target.checked ? 'line-through' : '';
            if (window.__updateSubmitState) window.__updateSubmitState();
            return;
        }
        if (!e.target.classList.contains('detail-att-input')) return;
        renderDetailAttList(e.target.closest('.detail-att-cell'));
        if (window.__updateSubmitState) window.__updateSubmitState();
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

    document.getElementById('btnAddDetail').addEventListener('click', function () {
        if (!document.getElementById('selectBudgetPlan').value) {
            Swal.fire({ icon: 'warning', title: 'Pilih Budget Plan dulu', text: 'Sub Item Budget baru muncul setelah Budget Plan dipilih.', confirmButtonColor: '#92232b' });
            return;
        }
        document.getElementById('detailBody').insertAdjacentHTML('beforeend', makeDetailRow(detailIdx++, null));
        renumber('detailBody');
        initSubItemPicker(document.getElementById('detailBody'));
    });

    // ── DOWNLOAD TEMPLATE & IMPORT EXCEL — Rincian Pengajuan ────────────────
    // Catatan: import MENGGANTI seluruh baris yang sedang ada di form (sama
    // seperti perilaku "Data lama akan diganti seluruhnya saat disimpan") —
    // termasuk lampiran per item pada baris yang tergantikan.
    document.getElementById('btnDownloadDetailTemplate').addEventListener('click', function (e) {
        e.preventDefault();
        var bpId = document.getElementById('selectBudgetPlan').value;
        if (!bpId) {
            Swal.fire({ icon: 'warning', title: 'Pilih Budget Plan dulu', text: 'Template dibuat sesuai Sub Item Budget dari Budget Plan yang dipilih.', confirmButtonColor: '#92232b' });
            return;
        }
        window.location.href = '{{ url("/budget-request/import-template") }}?budget_plan_id=' + bpId;
    });

    document.getElementById('btnImportDetailExcel').addEventListener('click', function () {
        if (!document.getElementById('selectBudgetPlan').value) {
            Swal.fire({ icon: 'warning', title: 'Pilih Budget Plan dulu', confirmButtonColor: '#92232b' });
            return;
        }
        document.getElementById('importDetailFileInput').value = '';
        document.getElementById('importDetailFileInput').click();
    });

    document.getElementById('importDetailFileInput').addEventListener('change', function () {
        var file = this.files[0];
        if (!file) return;
        var bpId = document.getElementById('selectBudgetPlan').value;

        var formData = new FormData();
        formData.append('file', file);
        formData.append('budget_plan_id', bpId);
        formData.append('_token', $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}');

        Swal.fire({
            title: 'Memproses file...', text: 'Mohon tunggu sebentar.',
            allowOutsideClick: false, didOpen: function () { Swal.showLoading(); }
        });

        $.ajax({
            url: '{{ url("/budget-request/import-preview") }}',
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
                document.getElementById('detailBody').innerHTML = '';
                detailIdx = 0;

                res.rows.forEach(function (r) {
                    var i = detailIdx++;
                    document.getElementById('detailBody').insertAdjacentHTML('beforeend', makeDetailRow(i, {
                        budget_detail_id: r.budget_detail_id,
                        amount: r.amount,
                        qty: r.qty,
                        notes: r.notes
                    }));
                });

                renumber('detailBody');
                initSubItemPicker(document.getElementById('detailBody'));
                document.querySelectorAll('#detailBody select.select-budget-detail').forEach(function (sel) {
                    updateRemainingCell(sel.closest('tr'));
                });
                recalc();

                Swal.fire({
                    icon: 'success', title: 'Import berhasil',
                    text: res.rows.length + ' baris dimuat ke Rincian Pengajuan.',
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

    document.addEventListener('click', function (e) {
        if (e.target.closest('.btn-remove-detail')) {
            e.target.closest('tr').remove();
            renumber('detailBody');
            recalc();
        }
        if (e.target.closest('.btn-remove-att')) {
            e.target.closest('tr').remove();
            renumber('attachmentBody');
            if (window.__updateSubmitState) window.__updateSubmitState();
        }
    });

    // Attachment rows (+ drag & drop multi-file)
    var attIdx = 0;

    function addAttachmentRow(file) {
        var i     = attIdx++;
        var tbody = document.getElementById('attachmentBody');
        var tr    = document.createElement('tr');
        tr.innerHTML =
            '<td class="text-center align-middle row-no"></td>' +
            '<td><input type="file" name="attachments[' + i + '][file]" class="form-control-file"></td>' +
            '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-att"><i class="fas fa-trash"></i></button></td>';
        tbody.appendChild(tr);

        if (file) {
            var input = tr.querySelector('input[type="file"]');
            try {
                var dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
            } catch (err) {
                // Browser lama tanpa dukungan DataTransfer — baris tetap dibuat kosong.
            }
        }
        renumber('attachmentBody');
        if (window.__updateSubmitState) window.__updateSubmitState();
    }

    document.getElementById('btnAddAttachment').addEventListener('click', function () {
        addAttachmentRow(null);
    });

    (function () {
        var zone   = document.getElementById('dropZoneCR');
        var input  = document.getElementById('dropZoneInputCR');
        var browse = document.getElementById('dropZoneBrowseCR');

        zone.addEventListener('click', function () { input.click(); });
        browse.addEventListener('click', function (e) {
            e.stopPropagation();
            input.click();
        });

        input.addEventListener('change', function () {
            Array.from(this.files).forEach(function (f) { addAttachmentRow(f); });
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
                Array.from(files).forEach(function (f) { addAttachmentRow(f); });
            }
        });

        // ── PASTE IMAGE FROM CLIPBOARD (Ctrl+V) — tidak perlu file fisik ──
        function handlePaste(e) {
            var items = (e.clipboardData || window.clipboardData) ? (e.clipboardData || window.clipboardData).items : null;
            if (!items) return;
            var found = false;
            for (var i = 0; i < items.length; i++) {
                if (items[i].kind === 'file') {
                    var f = items[i].getAsFile();
                    if (f) {
                        var ext = (f.type.split('/')[1] || 'png');
                        var renamed = new File([f], 'pasted-image-' + Date.now() + '.' + ext, { type: f.type });
                        addAttachmentRow(renamed);
                        found = true;
                    }
                }
            }
            if (found) e.preventDefault();
        }
        zone.addEventListener('paste', handlePaste);
        document.addEventListener('paste', function (e) {
            var tag = (document.activeElement && document.activeElement.tagName) || '';
            if (tag === 'INPUT' || tag === 'TEXTAREA') return;
            if (document.activeElement && document.activeElement.closest &&
                document.activeElement.closest('.detail-att-cell')) return;
            handlePaste(e);
        });
    })();

    // Submit
    var btnSubmit = document.getElementById('btnSubmit');
    if (btnSubmit) {
        btnSubmit.addEventListener('click', function () {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Submit Cost Request?',
                    text: 'CR akan dikirim ke Manager untuk direview. Setelah submit tidak dapat diedit.',
                    icon: 'question', showCancelButton: true,
                    confirmButtonColor: '#42627d', cancelButtonColor: '#55595e',
                    confirmButtonText: 'Ya, Submit', cancelButtonText: 'Batal'
                }).then(function (r) { if (r.isConfirmed) document.getElementById('frmSubmit').submit(); });
            } else {
                document.getElementById('frmSubmit').submit();
            }
        });
    }

    // ── LAMPIRAN WAJIB untuk tipe tertentu (Payment/Pettycash/Purchase/Reimbursement) ──
    function typeRequiresAttachment() {
        var sel = document.getElementById('selectType');
        var opt = sel.options[sel.selectedIndex];
        var name = opt ? (opt.dataset.name || '') : '';
        return ATTACHMENT_REQUIRED_TYPES.indexOf(name) !== -1;
    }

    function refreshAttachmentRequiredUI() {
        document.getElementById('attRequiredBadge').style.display = typeRequiresAttachment() ? 'inline-block' : 'none';
    }

    document.getElementById('selectType').addEventListener('change', refreshAttachmentRequiredUI);
    refreshAttachmentRequiredUI();

    // ── INFO WORKFLOW berdasarkan Tipe Request ─────────────────────────────
    function refreshWorkflowAlert() {
        var sel = document.getElementById('selectType');
        var opt = sel.options[sel.selectedIndex];
        var name = opt ? (opt.dataset.name || '') : '';
        var box = document.getElementById('workflowAlert');
        if (!sel.value) { box.style.display = 'none'; return; }

        if (name === 'cash advance') {
            box.className = 'alert alert-info mb-0';
            box.innerHTML = '<i class="fas fa-route mr-1"></i> <strong>Cash Advance</strong> — workflow: ' +
                '<strong>Process &rarr; Paid &rarr; Settle &rarr; Closed</strong>. ' +
                'Ada tahap realisasi (settlement) setelah dana cair, jadi bukti final dilengkapi saat settle.';
        } else {
            box.className = 'alert alert-warning mb-0';
            box.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Workflow: ' +
                '<strong>Process &rarr; Paid &rarr; Closed</strong> (tanpa tahap realisasi). ' +
                'Pastikan <strong>semua bukti/evidence lengkap &amp; jelas sebelum submit</strong> — ' +
                'nota per item di kolom "Lampiran Item", bukti pendukung di Lampiran Tambahan.';
        }
        box.style.display = 'block';
    }
    document.getElementById('selectType').addEventListener('change', refreshWorkflowAlert);
    refreshWorkflowAlert();

    // ── HITUNG JUMLAH LAMPIRAN (item + tambahan, existing yang tidak dihapus + baru) ──
    function totalAttachmentCount() {
        var n = 0;
        document.querySelectorAll('#attachmentBody input[type="file"]').forEach(function (i) {
            if (i.files && i.files.length) n += i.files.length;
        });
        document.querySelectorAll('#detailBody .detail-att-input').forEach(function (i) {
            if (i.files && i.files.length) n += i.files.length;
        });
        var gDel = document.querySelectorAll('input[name="delete_attachments[]"]:checked').length;
        n += Math.max(0, existingAttachmentCount - gDel);

        var dExist = crDetails.reduce(function (s, d) { return s + ((d.attachments || []).length); }, 0);
        var dDel   = document.querySelectorAll('.del-detail-att:checked').length;
        n += Math.max(0, dExist - dDel);
        return n;
    }

    // ── ENABLE/DISABLE TOMBOL SIMPAN ─────────────────────────────────────
    function updateSubmitState() {
        var reasons = [];

        if (!document.getElementById('selectBudgetPlan').value) reasons.push('Budget Plan');
        if (!document.getElementById('selectType').value)       reasons.push('Tipe Request');
        if (!document.getElementById('selectBeneficiary').value) reasons.push('Penerima Dana');
        if (!document.querySelector('[name="description"]').value.trim()) reasons.push('Deskripsi');

        var clusterSel = document.getElementById('selectCluster');
        var clusterBox = document.getElementById('clusterBox');
        if (clusterBox && clusterBox.style.display !== 'none' && clusterSel && clusterSel.required && !clusterSel.value) {
            reasons.push('Project');
        }

        var rows = document.querySelectorAll('#detailBody .detail-row');
        if (!rows.length) {
            reasons.push('minimal 1 baris rincian');
        } else {
            rows.forEach(function (row) {
                var sub  = row.querySelector('.select-budget-detail');
                var note = row.querySelector('[name$="[notes]"]');
                var amt  = row.querySelector('.inp-amount');
                if ((sub && !sub.value) || (note && !note.value.trim()) || (amt && parse(amt.value) <= 0)) {
                    if (reasons.indexOf('rincian belum lengkap') === -1) reasons.push('rincian belum lengkap');
                }
            });
        }

        if (typeRequiresAttachment() && totalAttachmentCount() < 1) {
            reasons.push('minimal 1 lampiran (item atau tambahan)');
        }

        var btn  = document.getElementById('btnSaveCR');
        var hint = document.getElementById('saveHint');
        btn.disabled = reasons.length > 0;
        if (reasons.length) {
            hint.textContent = 'Lengkapi: ' + reasons.join(', ');
            hint.style.display = 'inline';
        } else {
            hint.style.display = 'none';
        }
    }

    document.getElementById('frmCR').addEventListener('input', updateSubmitState);
    document.getElementById('frmCR').addEventListener('change', updateSubmitState);
    window.__updateSubmitState = updateSubmitState;
    setTimeout(updateSubmitState, 300);
    updateSubmitState();

    document.getElementById('frmCR').addEventListener('submit', function (e) {
        updateSubmitState();
        if (document.getElementById('btnSaveCR').disabled) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Form belum lengkap',
                text: document.getElementById('saveHint').textContent || 'Lengkapi data yang wajib diisi.',
                confirmButtonColor: '#92232b'
            });
        }
    });

});
</script>

@endsection

@push('styles')
<style>
.font-weight-600 { font-weight:600; }
table td { vertical-align:middle !important; }

/* Dropdown searchable kustom (TesysSearchableSelect) di dalam sel tabel — sedikit lebih ramping. */
td .tss-btn { padding:.25rem .5rem; font-size:13.5px; }

.detail-att-cell { outline: none; transition: background .15s ease; }
.detail-att-cell:focus-within { background: var(--warning-bg); }
.detail-att-hint { opacity: .7; }
.btn:disabled { cursor: not-allowed; }

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