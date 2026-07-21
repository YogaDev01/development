@extends('layouts.app')

@section('title', 'Edit Cost Request')

@section('content')

<!-- CONTENT HEADER -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6 d-flex align-items-center">
                <a href="{{ url('/budget-request') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="m-0 font-weight-bold">Edit Cost Request</h1>
                    <small class="text-muted">
                        Kode: <strong>{{ $cr->code }}</strong>
                        &nbsp;·&nbsp;
                        <span class="badge badge-{{ $cr->status_color }}">{{ $cr->status }}</span>
                    </small>
                </div>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right mb-0">
                    <li class="breadcrumb-item"><a href="#">Finance</a></li>
                    <li class="breadcrumb-item"><a href="{{ url('/budget-request') }}">Cost Request</a></li>
                    <li class="breadcrumb-item active">Edit</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">

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
                    <div class="row">

                        <!-- TIPE -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="font-weight-600">Tipe Request <span class="text-danger">*</span></label>
                                <select name="type_id" class="form-control @error('type_id') is-invalid @enderror" required>
                                    <option value="">— Pilih Tipe —</option>
                                    @foreach($types as $type)
                                        <option value="{{ $type->id }}"
                                            {{ old('type_id', $cr->type_id) == $type->id ? 'selected' : '' }}>
                                            {{ $type->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('type_id')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>

                        <!-- BUDGET PLAN -->
                        <div class="col-md-5">
                            <div class="form-group">
                                <label class="font-weight-600">Budget Plan <span class="text-danger">*</span></label>
                                <select name="budget_plan_id" id="selectBudgetPlan"
                                        class="form-control @error('budget_plan_id') is-invalid @enderror" required>
                                    <option value="">— Pilih Budget Plan —</option>
                                    @foreach($budgetPlans as $bp)
                                        <option value="{{ $bp->id }}"
                                            data-remaining="{{ $bp->remainingAmount() }}"
                                            data-source="{{ $bp->source_type }}"
                                            {{ old('budget_plan_id', $cr->budget_plan_id) == $bp->id ? 'selected' : '' }}>
                                            [{{ $bp->code }}] {{ $bp->description }}
                                            @if($bp->isProjectBudget())
                                                — {{ $bp->project->name ?? '-' }}
                                            @else
                                                — {{ $bp->source_type }}: {{ $bp->costCenter->name ?? '-' }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">
                                    Sisa budget: <strong id="remainingBudget" class="text-success">—</strong>
                                </small>
                                @error('budget_plan_id')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>

                        <!-- CLUSTER — hanya untuk budget yang bersumber dari PO -->
                        <div class="col-md-4" id="clusterBox">
                            <div class="form-group">
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
                        </div>

                        <!-- BENEFICIARY -->
                        <div class="col-md-5">
                            <div class="form-group">
                                <label class="font-weight-600">Beneficiary (Tujuan Transfer) <span class="text-danger">*</span></label>
                                <select name="beneficiary_id" id="selectBeneficiary"
                                        class="form-control @error('beneficiary_id') is-invalid @enderror" required>
                                    <option value="">— Pilih Beneficiary —</option>
                                    @foreach($beneficiaries as $ben)
                                        <option value="{{ $ben->id }}"
                                            data-bank="{{ $ben->bank->name ?? '-' }}"
                                            data-account="{{ $ben->account_number ?? '-' }}"
                                            {{ old('beneficiary_id', $cr->beneficiary_id) == $ben->id ? 'selected' : '' }}>
                                            {{ $ben->complete_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted" id="beneficiaryInfo">
                                    @if($cr->beneficiary)
                                        <i class="fas fa-university mr-1"></i>
                                        {{ $cr->beneficiary->bank->name ?? '-' }} — {{ $cr->beneficiary->account_number ?? '-' }}
                                    @endif
                                </small>
                                @error('beneficiary_id')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>

                        <!-- DESKRIPSI -->
                        <div class="col-md-7">
                            <div class="form-group">
                                <label class="font-weight-600">Deskripsi / Keperluan <span class="text-danger">*</span></label>
                                <input type="text" name="description"
                                       class="form-control @error('description') is-invalid @enderror"
                                       value="{{ old('description', $cr->description) }}"
                                       placeholder="Jelaskan keperluan pengajuan dana..."
                                       autocomplete="off" required>
                                @error('description')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>

                        <!-- NOB WARNING -->
                        <div class="col-md-12">
                            <div id="nobWarning" class="alert alert-warning mb-2" style="display:none;font-size:13px;">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                <strong>Perhatian:</strong> Total pengajuan melebihi sisa budget.
                                Deskripsi akan otomatis ditambahkan prefix <strong>[NOB]</strong>.
                            </div>
                        </div>

                        <!-- EMAIL NOTIFIKASI -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Email Notifikasi</label>
                                <input type="text" name="email"
                                       class="form-control @error('email') is-invalid @enderror"
                                       value="{{ old('email', $cr->email) }}"
                                       placeholder="email1@domain.com, email2@domain.com"
                                       autocomplete="off">
                                <small class="text-muted">Pisahkan beberapa email dengan koma (,).</small>
                            </div>
                        </div>

                        <!-- NOTES -->
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label class="font-weight-600">Notes</label>
                                <textarea name="notes" class="form-control" rows="2"
                                          placeholder="Catatan tambahan...">{{ old('notes', $cr->notes) }}</textarea>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- SECTION 2: RINCIAN PENGAJUAN -->
            <div class="card card-warning card-outline shadow-sm">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title"><i class="fas fa-list-ul mr-1"></i> Rincian Pengajuan</h3>
                    <div class="ml-auto d-flex align-items-center" style="gap:8px;">
                        <span id="totalLabel" class="badge badge-primary px-3 py-2" style="font-size:13px;">Total: Rp 0</span>
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
                                    <th style="width:4%;" class="text-center">No</th>
                                    <th style="width:32%;">Sub Item Budget</th>
                                    <th style="width:12%;" class="text-right">Sisa Tersedia</th>
                                    <th style="width:12%;" class="text-right">Harga Satuan</th>
                                    <th style="width:6%;" class="text-center">Qty</th>
                                    <th style="width:12%;" class="text-right">Total</th>
                                    <th style="width:17%;">Notes</th>
                                    <th style="width:5%;" class="text-center">Aksi</th>
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

            <!-- SECTION 3: LAMPIRAN EXISTING -->
            @if($cr->requestAttachments()->count() > 0)
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
                                               style="width:18px;height:18px;accent-color:#dc3545;">
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
                    <h3 class="card-title"><i class="fas fa-upload mr-1"></i> Tambah Lampiran Baru</h3>
                    <button type="button" class="btn btn-sm btn-primary ml-auto" id="btnAddAttachment">
                        <i class="fas fa-plus mr-1"></i> Tambah Lampiran
                    </button>
                </div>
                <div class="card-body">
                    <div id="dropZoneCR" class="att-dropzone mb-3">
                        <i class="fas fa-cloud-upload-alt fa-2x mb-2 d-block"></i>
                        <p class="mb-0">
                            Drag &amp; drop file ke sini, atau
                            <span class="text-primary" style="text-decoration:underline;cursor:pointer;" id="dropZoneBrowseCR">klik untuk pilih</span>
                        </p>
                        <small class="text-muted">Bisa pilih/drop beberapa file sekaligus</small>
                        <input type="file" id="dropZoneInputCR" multiple style="display:none;"
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width:5%;" class="text-center">No</th>
                                    <th style="width:85%;">File</th>
                                    <th style="width:10%;" class="text-center">Aksi</th>
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
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save mr-1"></i> Simpan Perubahan
                    </button>
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
    'budget_detail_id' => $d->budget_detail_id,
    'amount'           => $d->amount,
    'qty'              => $d->qty,
    'notes'            => $d->notes,
])) }}</textarea>

{{-- Account per jenis sumber dana (di-swap saat Budget Plan berubah) --}}
<textarea id="accountsBySourceHolder" style="display:none;">{{ json_encode([
    'PROJECT'  => $accountsBySource['PROJECT']->map(fn($a) => ['id' => $a->id, 'name' => $a->name]),
    'OVERHEAD' => $accountsBySource['OVERHEAD']->map(fn($a) => ['id' => $a->id, 'name' => $a->name]),
    'PAYROLL'  => $accountsBySource['PAYROLL']->map(fn($a) => ['id' => $a->id, 'name' => $a->name]),
]) }}</textarea>

<script>
document.addEventListener('DOMContentLoaded', function () {

    var accounts      = JSON.parse(document.getElementById('accountsHolder').value);
    var crDetails      = JSON.parse(document.getElementById('crDetailsHolder').value) || [];
    var budgetDetails = []; // sub-item Budget Plan yang sedang aktif (di-fetch via AJAX)
    var firstLoad      = true;

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
            'Total: Rp ' + grand.toLocaleString('id-ID');
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
            html += '<option value="' + bd.id + '"' + sel + '>' + bd.label + ' (' + (bd.unit || '-') + ')</option>';
        });
        return html;
    }

    function updateRemainingCell(row) {
        var sel  = row.querySelector('.select-budget-detail');
        var cell = row.querySelector('.cell-remaining');
        var bd   = budgetDetails.find(function (b) { return String(b.id) === String(sel.value); });

        if (!bd) {
            cell.textContent = '—';
            cell.style.color = '';
            return;
        }

        cell.textContent = bd.remaining_formatted;
        cell.style.color = bd.remaining > 0 ? '#28a745' : '#dc3545';

        var amountInput = row.querySelector('.inp-amount');
        if (!amountInput.value || amountInput.value === '0') {
            amountInput.value = fmt(String(bd.amount));
            calcRow(row);
        }
    }

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('select-budget-detail')) {
            updateRemainingCell(e.target.closest('tr'));
            recalc();
        }
    });

    // Beneficiary info
    document.getElementById('selectBeneficiary').addEventListener('change', function () {
        var opt  = this.options[this.selectedIndex];
        var info = document.getElementById('beneficiaryInfo');
        if (this.value) {
            info.innerHTML = '<i class="fas fa-university mr-1"></i>' +
                (opt.dataset.bank || '-') + ' — ' + (opt.dataset.account || '-');
        } else {
            info.textContent = '';
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

        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">' +
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
                document.querySelectorAll('#detailBody .select-budget-detail').forEach(function (sel) {
                    updateRemainingCell(sel.closest('tr'));
                });
                recalc();
            })
            .catch(function () {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-3">' +
                    'Gagal memuat sub item budget. Silakan coba lagi.</td></tr>';
            });
    }

    function updateRemaining() {
        var opt       = bpSelect.options[bpSelect.selectedIndex];
        var remaining = parseInt(opt.dataset.remaining) || 0;
        var el        = document.getElementById('remainingBudget');

        if (bpSelect.value) {
            el.textContent = 'Rp ' + remaining.toLocaleString('id-ID');
            el.style.color = remaining > 0 ? '#28a745' : '#dc3545';
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
        var notesVal  = prefill.notes || '';

        return '<tr class="detail-row">' +
            '<td class="text-center align-middle row-no"></td>' +
            '<td><select name="details[' + i + '][budget_detail_id]" class="form-control form-control-sm select-budget-detail" required>' + subOpts + '</select></td>' +
            '<td class="text-right cell-remaining">—</td>' +
            '<td><input type="text" name="details[' + i + '][amount]" class="form-control form-control-sm text-right inp-amount" value="' + amountVal + '" placeholder="0" required></td>' +
            '<td><input type="number" name="details[' + i + '][qty]" class="form-control form-control-sm text-center inp-qty" value="' + qtyVal + '" min="1" required></td>' +
            '<td><input type="text" name="details[' + i + '][total_display]" class="form-control form-control-sm text-right inp-total" value="0" readonly style="background:#f8f9fa;"></td>' +
            '<td>' +
                '<span class="badge badge-danger nob-badge d-none mb-1"><i class="fas fa-exclamation-triangle mr-1"></i>NOB</span>' +
                '<input type="text" name="details[' + i + '][notes]" class="form-control form-control-sm" value="' + notesVal + '" placeholder="Keterangan...">' +
            '</td>' +
            '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-detail"><i class="fas fa-trash"></i></button></td>' +
        '</tr>';
    }

    document.getElementById('btnAddDetail').addEventListener('click', function () {
        if (!document.getElementById('selectBudgetPlan').value) {
            Swal.fire({ icon: 'warning', title: 'Pilih Budget Plan dulu', text: 'Sub Item Budget baru muncul setelah Budget Plan dipilih.', confirmButtonColor: '#F5A623' });
            return;
        }
        document.getElementById('detailBody').insertAdjacentHTML('beforeend', makeDetailRow(detailIdx++, null));
        renumber('detailBody');
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
                    confirmButtonColor: '#007bff', cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Submit', cancelButtonText: 'Batal'
                }).then(function (r) { if (r.isConfirmed) document.getElementById('frmSubmit').submit(); });
            } else {
                document.getElementById('frmSubmit').submit();
            }
        });
    }

});
</script>

@endsection

@push('styles')
<style>
.font-weight-600 { font-weight:600; }
table td { vertical-align:middle !important; }

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
</style>
@endpush