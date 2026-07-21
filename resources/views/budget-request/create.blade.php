@extends('layouts.app')

@section('title', 'Tambah Cost Request')

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
                    <h1 class="m-0 font-weight-bold">Tambah Cost Request</h1>
                    <small class="text-muted">Form pengajuan dana baru</small>
                </div>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right mb-0">
                    <li class="breadcrumb-item"><a href="#">Finance</a></li>
                    <li class="breadcrumb-item"><a href="{{ url('/budget-request') }}">Cost Request</a></li>
                    <li class="breadcrumb-item active">Tambah</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">

        <form action="{{ url('/budget-request') }}" method="POST"
              enctype="multipart/form-data" id="frmCR">
            @csrf

            <!-- ============================================================ -->
            <!-- SECTION 1: INFORMASI UTAMA                                    -->
            <!-- ============================================================ -->
            <div class="card card-primary card-outline shadow-sm">
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
                                        <option value="{{ $type->id }}" {{ old('type_id') == $type->id ? 'selected' : '' }}>
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
                                            {{ old('budget_plan_id') == $bp->id ? 'selected' : '' }}>
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
                                    Sisa budget:
                                    <strong id="remainingBudget" class="text-success">—</strong>
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
                                    <option value="">— Pilih Budget Plan dulu —</option>
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
                                            {{ old('beneficiary_id') == $ben->id ? 'selected' : '' }}>
                                            {{ $ben->complete_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted" id="beneficiaryInfo"></small>
                                @error('beneficiary_id')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>

                        <!-- DESKRIPSI -->
                        <div class="col-md-7">
                            <div class="form-group">
                                <label class="font-weight-600">Deskripsi / Keperluan <span class="text-danger">*</span></label>
                                <input type="text" name="description"
                                       class="form-control @error('description') is-invalid @enderror"
                                       value="{{ old('description') }}"
                                       placeholder="Jelaskan keperluan pengajuan dana..."
                                       style="text-transform:uppercase;"
                                       autocomplete="off" required>
                                @error('description')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>

                        <!-- DESKRIPSI — NOB indicator -->
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
                                       value="{{ old('email') }}"
                                       placeholder="email1@domain.com, email2@domain.com"
                                       autocomplete="off">
                                <small class="text-muted">Pisahkan beberapa email dengan koma (,).</small>
                                @error('email')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>

                        <!-- NOTES -->
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label class="font-weight-600">Notes</label>
                                <textarea name="notes" class="form-control" rows="2"
                                          placeholder="Catatan tambahan...">{{ old('notes') }}</textarea>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- SECTION 2: RINCIAN PENGAJUAN                                  -->
            <!-- ============================================================ -->
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-list-ul mr-1"></i> Rincian Pengajuan
                    </h3>
                    <div class="ml-auto d-flex align-items-center" style="gap:8px;">
                        <span id="totalLabel" class="badge badge-primary px-3 py-2" style="font-size:13px;">
                            Total: Rp 0
                        </span>
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
                                <!-- Baris diisi oleh JS setelah Budget Plan dipilih (lihat #btnAddDetail / initSource) -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer">
                    <small class="text-muted">
                        <i class="fas fa-info-circle mr-1"></i>
                        Pilih Budget Plan terlebih dahulu untuk memunculkan pilihan Sub Item Budget.
                        Total = Harga Satuan × Qty, dihitung otomatis.
                    </small>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- SECTION 3: LAMPIRAN PENGAJUAN                                 -->
            <!-- ============================================================ -->
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-paperclip mr-1"></i> Lampiran Pengajuan
                    </h3>
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
                <div class="card-body d-flex">
                    <a href="{{ url('/budget-request') }}" class="btn btn-secondary mr-2">
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

{{-- Account options untuk JS --}}
<textarea id="accountsHolder" style="display:none;">{{ json_encode($accounts->map(fn($a) => ['id' => $a->id, 'name' => $a->name])) }}</textarea>

{{-- Account per jenis sumber dana (di-swap saat Budget Plan berubah) --}}
<textarea id="accountsBySourceHolder" style="display:none;">{{ json_encode([
    'PROJECT'  => $accountsBySource['PROJECT']->map(fn($a) => ['id' => $a->id, 'name' => $a->name]),
    'OVERHEAD' => $accountsBySource['OVERHEAD']->map(fn($a) => ['id' => $a->id, 'name' => $a->name]),
    'PAYROLL'  => $accountsBySource['PAYROLL']->map(fn($a) => ['id' => $a->id, 'name' => $a->name]),
]) }}</textarea>

{{-- Clusters per budget plan untuk JS --}}
<textarea id="bpClustersHolder" style="display:none;">{{ json_encode(
    $budgetPlans->mapWithKeys(fn($bp) => [
        $bp->id => $bp->project
            ? $bp->project->details->map(fn($d) => [
                'id'   => $d->cluster->id   ?? null,
                'code' => $d->cluster->code ?? '-',
                'name' => $d->cluster->name ?? '-',
            ])->filter(fn($c) => $c['id'])->unique('id')->values()
            : collect()
    ])
) }}</textarea>

<textarea id="oldDetailsHolder" style="display:none;">{{ json_encode(old('details', [])) }}</textarea>
<textarea id="oldBudgetPlanHolder" style="display:none;">{{ json_encode(old('budget_plan_id', '')) }}</textarea>
<textarea id="oldClusterHolder" style="display:none;">{{ json_encode(old('cluster_id', null)) }}</textarea>

<script>
document.addEventListener('DOMContentLoaded', function () {

    var accounts   = JSON.parse(document.getElementById('accountsHolder').value);
    var bpClusters = JSON.parse(document.getElementById('bpClustersHolder').value);
    var oldDetails = JSON.parse(document.getElementById('oldDetailsHolder').value) || [];
    var budgetDetails = []; // sub-item Budget Plan yang sedang aktif (di-fetch via AJAX)

    // ── FORMAT RUPIAH ──────────────────────────────────────────────
    function fmt(v) {
        v = (v || '').toString().replace(/\D/g, '');
        return v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    function parse(v) {
        return parseInt((v || '').toString().replace(/\D/g, '')) || 0;
    }

    // ── HITUNG TOTAL BARIS ─────────────────────────────────────────
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

    // ── HITUNG GRAND TOTAL ─────────────────────────────────────────
    function recalc() {
        var grand = 0;
        document.querySelectorAll('#detailBody .detail-row').forEach(function (row) {
            grand += calcRow(row);
        });
        document.getElementById('totalLabel').textContent =
            'Total: Rp ' + grand.toLocaleString('id-ID');
    }

    // ── EVENT: AMOUNT / QTY ────────────────────────────────────────
    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('inp-amount')) {
            e.target.value = fmt(e.target.value);
            recalc();
        }
        if (e.target.classList.contains('inp-qty')) {
            recalc();
        }
    });

    // Init baris existing
    document.querySelectorAll('.inp-amount').forEach(function (el) {
        el.value = fmt(el.value);
    });
    recalc();

    // ── RENUMBER ───────────────────────────────────────────────────
    function renumber(tbodyId) {
        document.querySelectorAll('#' + tbodyId + ' .row-no').forEach(function (el, i) {
            el.textContent = i + 1;
        });
    }

    // ── BUILD SUB ITEM BUDGET OPTIONS ───────────────────────────────
    function buildSubItemOptions(selectedId) {
        var html = '<option value="">— Pilih Sub Item —</option>';
        budgetDetails.forEach(function (bd) {
            var sel = (selectedId && String(selectedId) === String(bd.id)) ? ' selected' : '';
            html += '<option value="' + bd.id + '"' + sel + '>' + bd.label + ' (' + (bd.unit || '-') + ')</option>';
        });
        return html;
    }

    // ── UPDATE SEL SISA TERSEDIA + AUTO-ISI HARGA SATUAN ────────────
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

        // Auto-isi Harga Satuan dari harga satuan budget bila field masih kosong
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

    // ── BENEFICIARY INFO ───────────────────────────────────────────
    document.getElementById('selectBeneficiary').addEventListener('change', function () {
        var opt  = this.options[this.selectedIndex];
        var bank = opt.dataset.bank || '';
        var acc  = opt.dataset.account || '';
        var info = document.getElementById('beneficiaryInfo');
        if (this.value && bank) {
            info.innerHTML = '<i class="fas fa-university mr-1"></i>' + bank + ' — ' + acc;
        } else {
            info.textContent = '';
        }
    });

    // ── SISA BUDGET + SUMBER DANA + FILTER CLUSTER ────────────────

    /* Kosongkan & sembunyikan cluster untuk budget cost center (overhead/payroll),
       karena cost center tidak terhubung ke project manapun. */
    function applySource(sourceType) {
        var isProject  = (sourceType === 'PROJECT' || !sourceType);
        var clusterSel = document.getElementById('selectCluster');

        document.getElementById('clusterBox').style.display = isProject ? '' : 'none';
        clusterSel.required = isProject;

        if (!isProject) {
            clusterSel.innerHTML = '<option value="">—</option>';
            clusterSel.value = '';
        }
    }

    // ── SUB ITEM BUDGET (fetch via AJAX tiap Budget Plan berganti) ──
    function fetchBudgetDetails(bpId, prefillFromOld) {
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

                var rowsToPrefill = (prefillFromOld && oldDetails && oldDetails.length) ? oldDetails : null;

                if (rowsToPrefill) {
                    rowsToPrefill.forEach(function (d) {
                        tbody.insertAdjacentHTML('beforeend', makeDetailRow(detailIdx++, d));
                    });
                } else {
                    tbody.insertAdjacentHTML('beforeend', makeDetailRow(detailIdx++, null));
                }

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

    function applyBudgetPlanSelection(prefillFromOld) {
        var bpSel     = document.getElementById('selectBudgetPlan');
        var opt       = bpSel.options[bpSel.selectedIndex];
        var remaining = parseInt(opt.dataset.remaining) || 0;
        var el        = document.getElementById('remainingBudget');
        var bpId      = bpSel.value;
        var source    = opt.dataset.source || 'PROJECT';

        // Update sisa budget (header)
        if (bpId) {
            el.textContent = 'Rp ' + remaining.toLocaleString('id-ID');
            el.style.color = remaining > 0 ? '#28a745' : '#dc3545';
        } else {
            el.textContent = '—';
            el.style.color = '';
        }

        applySource(bpId ? source : 'PROJECT');
        fetchBudgetDetails(bpId, prefillFromOld);

        // Cluster hanya relevan untuk budget proyek
        if (bpId && source !== 'PROJECT') return;

        var clusterSel = document.getElementById('selectCluster');
        clusterSel.innerHTML = '';

        if (!bpId || !bpClusters[bpId] || !bpClusters[bpId].length) {
            clusterSel.innerHTML = '<option value="">— Tidak ada project terhubung —</option>';
            return;
        }

        var clusters = bpClusters[bpId];
        var html = '<option value="">— Pilih Project —</option>';
        clusters.forEach(function (c) {
            html += '<option value="' + c.id + '">[' + c.code + '] ' + c.name + '</option>';
        });
        clusterSel.innerHTML = html;

        // Auto-select jika hanya 1 cluster
        if (clusters.length === 1) {
            clusterSel.value = clusters[0].id;
        }

        // Cluster lama (validasi gagal) tetap dipertahankan
        var oldCluster = JSON.parse(document.getElementById('oldClusterHolder').value || 'null');
        if (prefillFromOld && oldCluster) {
            clusterSel.value = oldCluster;
        }
    }

    document.getElementById('selectBudgetPlan').addEventListener('change', function () {
        applyBudgetPlanSelection(false);
    });

    // ── NOB CHECK ──────────────────────────────────────────────────
    function checkNOB() {
        var bpSel = document.getElementById('selectBudgetPlan');
        if (!bpSel.value) { document.getElementById('nobWarning').style.display = 'none'; return; }
        var opt       = bpSel.options[bpSel.selectedIndex];
        var remaining = parseInt(opt.dataset.remaining) || 0;
        var grand     = 0;
        document.querySelectorAll('#detailBody .detail-row').forEach(function (row) {
            var amount = parseInt((row.querySelector('.inp-amount').value || '0').replace(/\./g,'')) || 0;
            var qty    = parseInt(row.querySelector('.inp-qty').value) || 1;
            grand += amount * qty;
        });
        var isNob = grand > remaining;
        document.getElementById('nobWarning').style.display = isNob ? 'block' : 'none';
    }

    // Override recalc to also check NOB
    var _origRecalc = recalc;
    function recalc() {
        var grand = 0;
        document.querySelectorAll('#detailBody .detail-row').forEach(function (row) {
            grand += calcRow(row);
        });
        document.getElementById('totalLabel').textContent =
            'Total: Rp ' + grand.toLocaleString('id-ID');
        checkNOB();
    }

    // ── DETAIL ROWS ────────────────────────────────────────────────
    var detailIdx = 0;

    function makeDetailRow(i, prefill) {
        prefill = prefill || {};
        var subOpts = buildSubItemOptions(prefill.budget_detail_id || null);
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
    });

    // ── ATTACHMENT ROWS (+ drag & drop multi-file) ──────────────────
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
                // Browser lama tanpa dukungan DataTransfer — baris tetap dibuat kosong,
                // user tinggal pilih file itu secara manual.
            }
        }
        renumber('attachmentBody');
    }

    document.getElementById('btnAddAttachment').addEventListener('click', function () {
        addAttachmentRow(null);
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('.btn-remove-att')) {
            e.target.closest('tr').remove();
            renumber('attachmentBody');
        }
    });

    // Drop zone
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

    // Inisialisasi sumber dana (mis. saat kembali dari validasi gagal)
    (function initSource() {
        var bpSel = document.getElementById('selectBudgetPlan');
        if (bpSel && bpSel.value) {
            applyBudgetPlanSelection(true);
        }
    })();

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