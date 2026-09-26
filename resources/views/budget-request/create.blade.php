@extends('layouts.app')

@section('title', 'Tambah Cost Request')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ url('/budget-request') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Tambah Cost Request</h1>
                    <small class="text-muted">Form pengajuan dana baru</small>
                </div>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item"><a href="{{ url('/budget-request') }}">Cost Request</a></li>
                <li class="breadcrumb-item active">Tambah</li>
            </ol>
        </div>

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
                                            {{ old('budget_plan_id') == $bp->id ? 'selected' : '' }}>
                                            {{ $bpLabel }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">
                                    Sisa budget:
                                    <strong id="remainingBudget" class="text-success">—</strong>
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
                                            <option value="{{ $type->id }}" data-name="{{ strtolower($type->name) }}" {{ old('type_id') == $type->id ? 'selected' : '' }}>
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
                                                {{ old('beneficiary_id') == $ben->id ? 'selected' : '' }}>
                                                {{ $benLabel }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted" id="beneficiaryInfo"></small>
                                    @error('beneficiary_id')<small class="text-danger d-block">{{ $message }}</small>@enderror
                                </div>
                            </div>

                            <!-- DESKRIPSI -->
                            <div class="form-group mb-0">
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

                        <!-- KOLOM KANAN -->
                        <div class="col-md-6 po-col-right">

                            <!-- PROJECT — hanya untuk budget yang bersumber dari PO -->
                            <div class="form-group" id="clusterBox">
                                <label class="font-weight-600">Project <span class="text-danger">*</span></label>
                                <select name="cluster_id" id="selectCluster"
                                        class="form-control @error('cluster_id') is-invalid @enderror" required>
                                    <option value="">— Pilih Budget Plan dulu —</option>
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
                                <input type="hidden" name="email" id="emailHidden" value="{{ old('email') }}">
                                <small class="text-muted">Tekan Enter atau koma (,) untuk menambahkan email.</small>
                                @error('email')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>

                            <!-- NOTES -->
                            <div class="form-group mb-0">
                                <label class="font-weight-600">Notes</label>
                                <textarea name="notes" class="form-control" rows="2"
                                          placeholder="Catatan tambahan...">{{ old('notes') }}</textarea>
                            </div>

                        </div>

                        <!-- NOB indicator -->
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
                            <div id="caQuotaAlert" class="alert mb-0 mt-2" style="display:none;font-size:13px;"></div>
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
                            Total: 0
                        </span>
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
                                    <th scope="col" style="width:10%;" class="text-center">Lampiran Item <span id="itemAttRequiredBadge" class="badge badge-danger" style="display:none;">Wajib</span></th>
                                    <th scope="col" style="width:5%;" class="text-center">Aksi</th>
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
                        <i class="fas fa-paperclip mr-1"></i> Lampiran Tambahan / Pendukung
                        <span id="attRequiredBadge" class="badge badge-danger ml-1" style="display:none;">Wajib</span>
                    </h3>
                    <button type="button" class="btn btn-sm btn-primary ml-auto" id="btnAddAttachment">
                        <i class="fas fa-plus mr-1"></i> Tambah Lampiran
                    </button>
                </div>
                <div class="card-body">
                    <div class="alert alert-light border mb-3" style="font-size:12px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        Lampiran di sini untuk bukti yang <strong>bersifat global / pendukung</strong> —
                        bukan khusus satu item (contoh: foto undangan meeting, surat tugas).
                        Nota/bukti <strong>per item</strong> (nota bensin, nota makan, nota hotel)
                        di-upload di kolom <strong>“Lampiran Item”</strong> pada tabel Rincian Pengajuan di atas.
                        <br>
                        <strong>Aturan:</strong> tipe <strong>Cash Advance</strong> wajib mengisi lampiran di sini
                        (Lampiran Item opsional, bukti final dilengkapi saat settlement). Tipe <strong>selain</strong>
                        Cash Advance sebaliknya — <strong>Lampiran Item wajib di setiap baris rincian</strong>,
                        lampiran di sini opsional.
                    </div>
                    <div id="attUploadArea" style="display:none;">
                        <div id="dropZoneCR" class="att-dropzone mb-3" tabindex="0">
                            <i class="fas fa-cloud-upload-alt fa-2x mb-2 d-block"></i>
                            <p class="mb-0">
                                Drag &amp; drop file ke sini, paste gambar (Ctrl+V), atau
                                <span class="text-primary" style="text-decoration:underline;cursor:pointer;" id="dropZoneBrowseCR">klik untuk pilih</span>
                            </p>
                            <small class="text-muted">Bisa pilih/drop/paste beberapa file sekaligus</small>
                            <input type="file" id="dropZoneInputCR" multiple style="display:none;">
                        </div>
                        <div class="table-responsive mt-3" id="attTableWrap" style="display:none;">
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
                <div class="card-body d-flex align-items-center">
                    <a href="{{ url('/budget-request') }}" class="btn btn-secondary mr-2">
                        <i class="fas fa-arrow-left mr-1"></i> Kembali
                    </a>
                    <button type="submit" class="btn btn-primary" id="btnSaveCR">
                        <i class="fas fa-save mr-1"></i> Simpan sebagai Draft
                    </button>
                    <small id="saveHint" class="text-danger ml-3" style="display:none;"></small>
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
                'id'   => $d->cluster?->id   ?? null,
                'code' => $d->cluster?->code ?? '-',
                'name' => $d->cluster?->name ?? '-',
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

    var CASH_ADVANCE_TYPES = ['cash advance', 'cash-advance', 'cashadvance'];

    // ── SEARCHABLE DROPDOWN: Budget Plan & Beneficiary ──────────────
    TesysSearchableSelect.init('#selectBudgetPlan', { placeholder: '— Pilih Budget Plan —', searchPlaceholder: 'Cari Budget Plan...' });
    TesysSearchableSelect.init('#selectBeneficiary', { placeholder: '— Pilih Beneficiary —', searchPlaceholder: 'Cari Beneficiary...' });

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
            'Total: ' + grand.toLocaleString('id-ID');
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

    // ── UPDATE SEL SISA TERSEDIA + AUTO-ISI HARGA SATUAN ────────────
    // force = true kalau dipanggil dari event "user eksplisit ganti Sub Item"
    // (harga SELALU disegarkan ke default Sub Item yang baru). Tanpa force,
    // hanya isi kalau field masih kosong/0 — dipakai saat render awal baris
    // (create: baris baru; edit: jangan timpa nilai tersimpan dari database).
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

        // Prefill: email user yang sedang login, ditambahkan otomatis saat halaman dibuka.
        addEmail('{{ Auth::user()->email }}');

        // Expose supaya bisa dipanggil dari handler beneficiary di bawah.
        window.__addNotifyEmail = addEmail;
    })();

    // ── BENEFICIARY INFO + AUTO-FILL EMAIL ──────────────────────────
    document.getElementById('selectBeneficiary').addEventListener('change', function () {
        var opt   = this.options[this.selectedIndex];
        var bank  = opt.dataset.bank || '';
        var acc   = opt.dataset.account || '';
        var email = opt.dataset.email || '';
        var info  = document.getElementById('beneficiaryInfo');
        if (this.value && bank) {
            info.innerHTML = '<i class="fas fa-university mr-1"></i>' + bank + ' — ' + acc;
        } else {
            info.textContent = '';
        }

        if (this.value && email) {
            window.__addNotifyEmail(email);
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

        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3">' +
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

    function applyBudgetPlanSelection(prefillFromOld) {
        var bpSel     = document.getElementById('selectBudgetPlan');
        var opt       = bpSel.options[bpSel.selectedIndex];
        var remaining = parseInt(opt.dataset.remaining) || 0;
        var el        = document.getElementById('remainingBudget');
        var bpId      = bpSel.value;
        var source    = opt.dataset.source || 'PROJECT';

        // Update sisa budget (header)
        if (bpId) {
            el.textContent = remaining.toLocaleString('id-ID');
            el.style.color = remaining > 0 ? 'var(--success)' : 'var(--danger)';
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
            'Total: ' + grand.toLocaleString('id-ID');
        checkNOB();
        if (window.__updateSubmitState) window.__updateSubmitState();
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
                '<div class="detail-att-list mt-1" style="font-size:10px;line-height:1.5;"></div>' +
            '</td>' +
            '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-detail"><i class="fas fa-trash"></i></button></td>' +
        '</tr>';
    }

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
    });

    // ── ATTACHMENT ROWS (+ drag & drop multi-file) ──────────────────
    var attIdx = 0;

    /** Area upload lampiran global disembunyikan sampai user menambah lampiran. */
    function showAttUploadArea() {
        var area = document.getElementById('attUploadArea');
        if (area) area.style.display = '';
    }

    /** Daftar file hanya tampil kalau memang sudah ada barisnya. */
    function refreshAttTable() {
        var wrap  = document.getElementById('attTableWrap');
        var tbody = document.getElementById('attachmentBody');
        if (wrap && tbody) wrap.style.display = tbody.children.length ? '' : 'none';
    }

    function addAttachmentRow(file) {
        showAttUploadArea();
        var i     = attIdx++;
        var tbody = document.getElementById('attachmentBody');
        var tr    = document.createElement('tr');
        tr.innerHTML =
            '<td class="text-center align-middle row-no"></td>' +
            '<td><input type="file" name="attachments[' + i + '][file]" class="form-control-file"></td>' +
            '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-att"><i class="fas fa-trash"></i></button></td>';
        tbody.appendChild(tr);
        refreshAttTable();

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
        if (window.__updateSubmitState) window.__updateSubmitState();
    }

    document.getElementById('btnAddAttachment').addEventListener('click', function () {
        // Cukup buka area upload-nya; baris file baru muncul setelah ada file
        // yang benar-benar dipilih/di-drop/di-paste.
        showAttUploadArea();
        var zone = document.getElementById('dropZoneCR');
        if (zone) zone.focus();
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('.btn-remove-att')) {
            e.target.closest('tr').remove();
            renumber('attachmentBody');
            refreshAttTable();
            if (window.__updateSubmitState) window.__updateSubmitState();
        }
    });

    // ── LAMPIRAN PER ITEM (drag & drop + paste + klik, kolom "Lampiran Item") ──
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
        label.textContent = files.length ? (files.length + ' file') : 'Lampiran';
        list.innerHTML = files.map(function (f, idx) {
            return '<div class="text-muted"><i class="fas fa-file mr-1"></i>' +
                f.name.replace(/</g, '&lt;') +
                ' <i class="fas fa-times text-danger detail-att-del" data-i="' + idx + '" style="cursor:pointer;"></i></div>';
        }).join('');
    }

    // Klik tombol → file picker (menambah, tidak menimpa)
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-detail-att');
        if (!btn) return;
        var real = btn.closest('.detail-att-cell').querySelector('.detail-att-input');
        var tmp  = document.createElement('input');
        tmp.type = 'file'; tmp.multiple = true; tmp.accept = real.accept;
        tmp.addEventListener('change', function () { appendFilesToInput(real, tmp.files); });
        tmp.click();
    });

    // Hapus satu file yang sudah dipilih
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
        updateSubmitState();
    });

    document.addEventListener('change', function (e) {
        if (!e.target.classList.contains('detail-att-input')) return;
        renderDetailAttList(e.target.closest('.detail-att-cell'));
        updateSubmitState();
    });

    // Drag & drop ke sel
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

    // Paste ke sel yang sedang fokus (klik tombol/sel dulu, lalu Ctrl+V)
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
    }, true); // capture: jalan sebelum handler paste global

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
            // Hanya tangkap paste global kalau fokus bukan di field teks lain
            var tag = (document.activeElement && document.activeElement.tagName) || '';
            if (tag === 'INPUT' || tag === 'TEXTAREA') return;
            // Paste yang ditujukan ke sel "Lampiran Item" sudah ditangani handler khusus.
            if (document.activeElement && document.activeElement.closest &&
                document.activeElement.closest('.detail-att-cell')) return;
            handlePaste(e);
        });
    })();

    // Inisialisasi sumber dana (mis. saat kembali dari validasi gagal)
    (function initSource() {
        var bpSel = document.getElementById('selectBudgetPlan');
        if (bpSel && bpSel.value) {
            applyBudgetPlanSelection(true);
        }
    })();

    // ── LAMPIRAN WAJIB untuk tipe tertentu (Payment/Pettycash/Purchase/Reimbursement) ──
    // ── ATURAN LAMPIRAN ────────────────────────────────────────────────────
    //  Cash Advance : Lampiran Tambahan (global) WAJIB, Lampiran Item opsional.
    //  Tipe lain    : Lampiran Item WAJIB di tiap baris rincian, global opsional.
    function typeIsCashAdvance() {
        var sel = document.getElementById('selectType');
        var opt = sel.options[sel.selectedIndex];
        var name = opt ? (opt.dataset.name || '') : '';
        return CASH_ADVANCE_TYPES.indexOf(name) !== -1;
    }

    /** Lampiran global: file baru + existing yang tidak dicentang hapus. */
    function globalAttachmentCount() {
        var n = 0;
        document.querySelectorAll('#attachmentBody input[type="file"]').forEach(function (i) {
            if (i.files && i.files.length) n += i.files.length;
        });
        var exist = (typeof existingAttachmentCount !== 'undefined') ? existingAttachmentCount : 0;
        var gDel  = document.querySelectorAll('input[name="delete_attachments[]"]:checked').length;
        return n + Math.max(0, exist - gDel);
    }

    /** Nomor baris rincian yang belum punya Lampiran Item. */
    function rowsMissingItemAttachment() {
        var miss = [];
        document.querySelectorAll('#detailBody .detail-row').forEach(function (row, idx) {
            var inp = row.querySelector('.detail-att-input');
            if (inp && inp.files && inp.files.length) return;

            var chks = row.querySelectorAll('.del-detail-att');
            var kept = 0;
            chks.forEach(function (c) { if (!c.checked) kept++; });
            if (kept > 0) return;

            miss.push(idx + 1);
        });
        return miss;
    }

    function refreshAttachmentRequiredUI() {
        var ca = typeIsCashAdvance();
        var gBadge = document.getElementById('attRequiredBadge');
        var iBadge = document.getElementById('itemAttRequiredBadge');
        if (gBadge) gBadge.style.display = ca ? 'inline-block' : 'none';
        if (iBadge) iBadge.style.display = ca ? 'none' : 'inline-block';
    }

    document.getElementById('selectType').addEventListener('change', refreshAttachmentRequiredUI);
    refreshAttachmentRequiredUI();

    // ── HITUNG JUMLAH LAMPIRAN (item + tambahan) ───────────────────────────
    function totalAttachmentCount() {
        var n = 0;
        document.querySelectorAll('#attachmentBody input[type="file"]').forEach(function (i) {
            if (i.files && i.files.length) n += i.files.length;
        });
        document.querySelectorAll('#detailBody .detail-att-input').forEach(function (i) {
            if (i.files && i.files.length) n += i.files.length;
        });
        return n;
    }

    // ── ENABLE/DISABLE TOMBOL SIMPAN ──────────────────────────────────────
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

        refreshAttachmentRequiredUI();
        if (typeIsCashAdvance() && caQuotaState && caQuotaState.has_quota) {
            if (caQuotaState.remaining_count !== null && caQuotaState.remaining_count < 1) {
                reasons.push('jatah pengajuan Cash Advance PIC ' + caQuotaState.pic_name + ' sudah habis');
            } else if (caQuotaAmountExceeded()) {
                reasons.push('total pengajuan melebihi sisa limit Cash Advance (' + caQuotaState.remaining_amount_formatted + ')');
            }
        }
        if (typeIsCashAdvance()) {
            if (globalAttachmentCount() < 1) {
                reasons.push('Lampiran Tambahan / Pendukung (wajib untuk Cash Advance)');
            }
        } else {
            var missAtt = rowsMissingItemAttachment();
            if (missAtt.length) {
                reasons.push('Lampiran Item pada baris rincian no. ' + missAtt.join(', '));
            }
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

    // Re-cek setiap ada perubahan pada form
    document.getElementById('frmCR').addEventListener('input', updateSubmitState);
    document.getElementById('frmCR').addEventListener('change', updateSubmitState);
    window.__updateSubmitState = updateSubmitState;
    setTimeout(updateSubmitState, 300);
    updateSubmitState();


    /* ── LIMIT CASH ADVANCE PER PIC ─────────────────────────────────────────
       Setiap beneficiary terhubung ke satu PIC. Semua Cash Advance milik PIC
       itu berbagi satu limit yang diambil dari Master PIC (token = jumlah
       pengajuan, nominal = total rupiah). Panel ini hanya tampil untuk tipe
       Cash Advance. */
    var caQuotaState = null;   // null = tidak ada limit / belum diambil

    function renderCaQuota() {
        var box = document.getElementById('caQuotaAlert');
        if (!box) return;

        if (!typeIsCashAdvance() || !caQuotaState || !caQuotaState.has_quota) {
            box.style.display = 'none';
            return;
        }

        var q = caQuotaState;
        var cls = q.level === 'blocked' ? 'alert-danger'
                : (q.level === 'warning' ? 'alert-warning' : 'alert-info');
        var icon = q.level === 'ok' ? 'fa-info-circle' : 'fa-exclamation-triangle';
        var head = q.level === 'blocked'
                ? 'Limit Cash Advance PIC <strong>' + q.pic_name + '</strong> sudah habis — pengajuan baru tidak bisa disimpan.'
                : (q.level === 'warning'
                    ? 'Limit Cash Advance PIC <strong>' + q.pic_name + '</strong> hampir habis.'
                    : 'Limit Cash Advance PIC <strong>' + q.pic_name + '</strong>.');

        /* Aspek yang tidak diisi di Master PIC tidak ditampilkan — menulis
           "0" atau "tanpa batas" di situ cuma bikin salah baca. */
        var bagian = '';
        if (q.limit_count !== null) {
            bagian += ' Pengajuan berjalan: <strong>' + q.used_count + ' / ' + q.limit_count + '</strong>' +
                      ' (sisa ' + q.remaining_count + ').';
        }
        if (q.limit_amount !== null) {
            bagian += ' Nominal: <strong>' + q.used_amount_formatted + ' / ' + q.limit_amount_formatted + '</strong>' +
                      ' (sisa ' + q.remaining_amount_formatted + ').';
        }

        box.className = 'alert mb-0 mt-2 ' + cls;
        box.innerHTML = '<i class="fas ' + icon + ' mr-1"></i> ' + head + bagian +
            ' <br><small>Jumlah pengajuan dihitung sejak CR berstatus Paid; limit pulih setelah pengajuan di-Close.</small>';
        box.style.display = 'block';
    }

    function fetchCaQuota() {
        var ben = document.getElementById('selectBeneficiary').value;
        if (!typeIsCashAdvance() || !ben) {
            caQuotaState = null;
            renderCaQuota();
            if (window.__updateSubmitState) window.__updateSubmitState();
            return;
        }

        var url = '{{ route('budget-request.ca-quota') }}?beneficiary_id=' + encodeURIComponent(ben) +
                  '&cr_id=' + encodeURIComponent(null || '');

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                caQuotaState = j;
                renderCaQuota();
                if (window.__updateSubmitState) window.__updateSubmitState();
            })
            .catch(function () { caQuotaState = null; renderCaQuota(); });
    }

    /** Sisa limit nominal terlampaui oleh total rincian saat ini? */
    function caQuotaAmountExceeded() {
        if (!typeIsCashAdvance() || !caQuotaState || !caQuotaState.has_quota) return false;
        if (caQuotaState.remaining_amount === null) return false;   // nominal tidak dibatasi
        var total = 0;
        document.querySelectorAll('#detailBody .detail-row').forEach(function (row) {
            var amt = row.querySelector('.inp-amount');
            var qty = row.querySelector('.inp-qty');
            total += parse(amt ? amt.value : 0) * Math.max(1, parseInt(qty ? qty.value : 1, 10) || 1);
        });
        return total > caQuotaState.remaining_amount;
    }

    document.getElementById('selectBeneficiary').addEventListener('change', fetchCaQuota);
    document.getElementById('selectType').addEventListener('change', fetchCaQuota);
    fetchCaQuota();

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