{{--
    Bagian Budget pada form PO (opsional) — membuat/mengubah SATU Budget Plan
    "bundled" (budget_plans.via_po_approval = 1) bersama PO. Status BP ikut
    approval PO: submit -> CHECKED, verify -> VERIFIED, approve -> ACTIVE,
    revise -> DRAFT, dan otomatis CLOSED saat PO ditutup. BP tambahan tetap
    dibuat lewat /budget-plan/create.

    Variabel:
      $canFillBudget   bool        Head/superadmin (AccessRoles::PO_BUDGET_BUNDLE)
      $budgetAccounts  Collection  account kategori Variable Cost
      $bundledBudget   ?BudgetPlan BP bundled milik PO (edit saja; boleh tidak ada)
      $project         ?Project    PO yang diedit (tidak ada pada form Tambah)

    Bisa diedit hanya bila: user berhak, dan (belum ada BP & PO baru/DRAFT) atau
    (BP ada & berstatus DRAFT). Selain itu tampil read-only dengan tautan ke BP.
--}}
@php
    $bundledBudget  = $bundledBudget ?? null;
    $budgetIsEdit   = isset($project);
    $budgetEditable = $canFillBudget && ($bundledBudget
        ? $bundledBudget->isDraft()
        : (! $budgetIsEdit || $project->status === 'DRAFT'));

    // Tombol "Hapus Budget": hanya di edit, BP bundled DRAFT (sudah termasuk di $budgetEditable),
    // dan PO sendiri DRAFT. Controller memeriksa ulang syarat yang sama.
    $budgetBisaDihapus = $budgetEditable && $budgetIsEdit && $bundledBudget && $project->status === 'DRAFT';

    $pobDesc   = old('budget.description', $bundledBudget?->description);
    $pobNotes  = old('budget.notes', $bundledBudget?->notes);

    // Account tersimpan yang sudah tidak ada di daftar aktif tetap jadi option
    // (ditandai nonaktif) supaya nilai lama tidak terkosongkan diam-diam saat disimpan.
    $pobAccountNonaktif = $bundledBudget
        ? $bundledBudget->details
            ->filter(fn ($d) => $d->account_id && ! $budgetAccounts->contains('id', $d->account_id))
            ->unique('account_id')
        : collect();

    // Baris awal: input lama (validasi gagal) > detail BP tersimpan > satu baris kosong.
    $pobRows = old('budget.details');
    if ($pobRows === null) {
        $pobRows = $bundledBudget
            ? $bundledBudget->details->map(fn ($d) => [
                'account_id' => $d->account_id,
                'deskripsi'  => $d->deskripsi,
                'amount'     => number_format((int) $d->amount, 0, ',', '.'),
                'qty'        => (float) $d->qty,
                'unit'       => $d->unit,
                'notes'      => $d->notes,
            ])->all()
            : [[]];
    }
@endphp

@if($budgetEditable)
<div id="pobWrap">
<!-- BUDGET (opsional) -->
<div class="card card-primary card-outline shadow-sm" id="pobCard">
    <div class="card-header d-flex align-items-center flex-wrap" style="gap:8px;">
        <h3 class="card-title mb-0">
            <i class="fas fa-money-check-alt mr-1"></i> Budget
            <small class="text-muted ml-1">(opsional)</small>
        </h3>
        @if($budgetBisaDihapus)
        <div class="ml-auto d-flex align-items-center flex-wrap" style="gap:8px;">
            <button type="button" class="btn btn-sm btn-outline-danger" id="pobDelete">
                <i class="fas fa-trash-alt mr-1"></i> Hapus Budget dari PO ini
            </button>
        </div>
        @endif
    </div>

    @if($budgetBisaDihapus)
    <input type="hidden" name="budget[hapus]" id="pobHapus" value="0">
    <div class="alert alert-danger rounded-0 mb-0 d-none" id="pobDeleteNotice">
        <i class="fas fa-exclamation-triangle mr-1"></i>
        Budget <strong>{{ $bundledBudget->code }}</strong> akan dihapus permanen saat PO disimpan.
        <button type="button" class="btn btn-sm btn-light ml-2" id="pobDeleteUndo">Batalkan</button>
    </div>
    @endif

    @if($errors->has('budget.*'))
    <div class="alert alert-danger rounded-0 mb-0">
        <ul class="mb-0">
            @foreach($errors->get('budget.*') as $msgs)
                @foreach($msgs as $m)<li>{{ $m }}</li>@endforeach
            @endforeach
        </ul>
    </div>
    @endif

    <div class="card-body">
        <div class="row form-clean">
            <div class="col-md-6">
                <div class="form-group mb-0">
                    <label class="font-weight-600">Deskripsi Budget</label>
                    <input type="text" name="budget[description]" class="form-control"
                           value="{{ $pobDesc }}" placeholder="Deskripsi budget..."
                           style="text-transform:uppercase;" autocomplete="off">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group mb-0">
                    <label class="font-weight-600">Notes Budget</label>
                    <textarea name="budget[notes]" class="form-control" rows="2"
                              placeholder="Catatan tambahan...">{{ $pobNotes }}</textarea>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DETAIL BUDGET -->
<div class="card card-primary card-outline shadow-sm">
    <div class="card-header d-flex align-items-center flex-wrap" style="gap:8px;">
        <h3 class="card-title mb-0">
            <i class="fas fa-list-ul mr-1"></i> Detail Budget
        </h3>
        <div class="ml-auto d-flex align-items-center flex-wrap" style="gap:8px;">
            <span id="pobTotal" class="badge badge-primary px-3 py-2" style="font-size:13px;">Total: 0</span>
            <button type="button" id="pobDownloadTpl" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-file-excel mr-1"></i> Download Template
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary" id="pobImport">
                <i class="fas fa-file-import mr-1"></i> Import Excel
            </button>
            <input type="file" id="pobImportFile" accept=".xlsx,.xls" style="display:none;">
            <button type="button" class="btn btn-sm btn-primary" id="pobAddRow">
                <i class="fas fa-plus mr-1"></i> Tambah Baris
            </button>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered mb-0" id="pobTable">
                <thead class="bg-light">
                    <tr>
                        <th scope="col" style="width:4%;" class="text-center">No</th>
                        <th scope="col" style="width:22%;">Chart of Account</th>
                        <th scope="col" style="width:20%;">Deskripsi</th>
                        <th scope="col" style="width:14%;" class="text-right">Harga Satuan (Rp)</th>
                        <th scope="col" style="width:7%;" class="text-center">Qty</th>
                        <th scope="col" style="width:11%;">Unit</th>
                        <th scope="col" style="width:14%;" class="text-right">Total (Rp)</th>
                        <th scope="col" style="width:5%;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="pobBody"></tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        <small class="text-muted">
            <i class="fas fa-info-circle mr-1"></i>
            Kosongkan seluruh bagian ini bila PO tidak membawa budget. Bila diisi: deskripsi
            dan minimal 1 baris wajib ada. Total = Harga Satuan × Qty, dihitung otomatis.
            Budget ini tidak punya approval sendiri — ia ikut Submit / Verify / Approve PO,
            baru bisa dipakai Cost Request setelah PO di-approve, dan otomatis CLOSED saat PO ditutup.
        </small>
    </div>
</div>
</div>

<template id="pobRowTpl">
    <tr class="pob-row">
        <td class="text-center align-middle pob-no"></td>
        <td>
            <select name="budget[details][__I__][account_id]" class="form-control form-control-sm pob-account">
                <option value="">— Pilih Account —</option>
                @foreach($budgetAccounts as $acc)
                    <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                @endforeach
                @foreach($pobAccountNonaktif as $d)
                    <option value="{{ $d->account_id }}">{{ $d->account?->name ?? ('Account #' . $d->account_id) }} (nonaktif)</option>
                @endforeach
            </select>
        </td>
        <td><input type="text" name="budget[details][__I__][deskripsi]" class="form-control form-control-sm pob-desc" placeholder="Deskripsi item..."></td>
        <td><input type="text" name="budget[details][__I__][amount]" class="form-control form-control-sm text-right pob-money" placeholder="0"></td>
        <td><input type="number" step="0.01" min="0.01" name="budget[details][__I__][qty]" class="form-control form-control-sm text-center pob-qty" value="1"></td>
        <td>
            <select name="budget[details][__I__][unit]" class="form-control form-control-sm pob-unit">
                <option value="">—</option>
                @foreach(\App\Models\BudgetPlanDetail::UNITS as $u)
                    <option value="{{ $u }}">{{ ucfirst($u) }}</option>
                @endforeach
            </select>
        </td>
        <td><input type="text" class="form-control form-control-sm text-right pob-total" value="0" readonly tabindex="-1"></td>
        <td class="text-center align-middle">
            <button type="button" class="btn btn-danger btn-sm pob-remove" title="Hapus baris"><i class="fas fa-trash"></i></button>
            <input type="hidden" name="budget[details][__I__][notes]" class="pob-notes">
        </td>
    </tr>
</template>

@push('styles')
<style>
#pobTable td .tss-btn { padding:.25rem .5rem; font-size:13.5px; }
</style>
@endpush

<script>
document.addEventListener('DOMContentLoaded', function () {
    var body = document.getElementById('pobBody');
    var tpl  = document.getElementById('pobRowTpl');
    if (!body || !tpl) return;

    var initialRows = @json(array_values((array) $pobRows));
    var idx = 0;

    function fmtRp(v) {
        v = (v || '').toString().replace(/\D/g, '');
        return v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function recalc() {
        var grand = 0;
        body.querySelectorAll('.pob-row').forEach(function (tr, n) {
            tr.querySelector('.pob-no').textContent = n + 1;
            var amount = parseInt((tr.querySelector('.pob-money').value || '0').replace(/\./g, ''), 10) || 0;
            var qty    = parseFloat(tr.querySelector('.pob-qty').value) || 0;
            var total  = Math.round(amount * qty);
            tr.querySelector('.pob-total').value = total.toLocaleString('id-ID');
            grand += total;
        });
        document.getElementById('pobTotal').textContent = 'Total: ' + grand.toLocaleString('id-ID');
    }

    // Nilai diisi lewat properti .value (bukan string HTML) supaya aman dari injeksi markup.
    // .value select diisi SEBELUM TesysSearchableSelect.initOne (label tombol dibaca saat init),
    // dan baris harus sudah ada di DOM karena initOne menyisipkan tombol sebelum <select>.
    function addRow(data) {
        data = data || {};
        var i = idx++;
        var html = tpl.innerHTML.replace(/__I__/g, i);
        var holder = document.createElement('tbody');
        holder.innerHTML = html;
        var tr = holder.querySelector('tr');

        if (data.account_id) tr.querySelector('.pob-account').value = data.account_id;
        if (data.deskripsi)  tr.querySelector('.pob-desc').value    = data.deskripsi;
        if (data.amount)     tr.querySelector('.pob-money').value   = fmtRp(data.amount);
        if (data.qty)        tr.querySelector('.pob-qty').value     = data.qty;
        if (data.unit)       tr.querySelector('.pob-unit').value    = data.unit;
        if (data.notes)      tr.querySelector('.pob-notes').value   = data.notes;

        body.appendChild(tr);

        TesysSearchableSelect.initOne($(tr.querySelector('.pob-account')), { placeholder: '— Pilih Account —', searchPlaceholder: 'Cari Account...' });
        TesysSearchableSelect.initOne($(tr.querySelector('.pob-unit')), { placeholder: '—', searchPlaceholder: 'Cari Unit...' });

        recalc();
    }

    document.getElementById('pobAddRow').addEventListener('click', function () { addRow(); });

    body.addEventListener('input', function (e) {
        if (e.target.classList.contains('pob-money')) e.target.value = fmtRp(e.target.value);
        if (e.target.classList.contains('pob-money') || e.target.classList.contains('pob-qty')) recalc();
    });

    body.addEventListener('click', function (e) {
        var btn = e.target.closest('.pob-remove');
        if (!btn) return;
        btn.closest('tr').remove();
        recalc();
    });

    initialRows.forEach(addRow);
    if (!initialRows.length) addRow();

    // ── Download Template & Import Excel ─────────────────────────────────
    // Import hanya mengisi ulang tabel (tidak menyimpan); simpan tetap lewat tombol Simpan PO.
    document.getElementById('pobDownloadTpl').addEventListener('click', function () {
        window.location.href = '{{ route('project.budget-import-template') }}';
    });

    var fileInput = document.getElementById('pobImportFile');

    document.getElementById('pobImport').addEventListener('click', function () {
        fileInput.value = '';
        fileInput.click();
    });

    fileInput.addEventListener('change', function () {
        var file = this.files[0];
        if (!file) return;

        var formData = new FormData();
        formData.append('file', file);
        formData.append('_token', $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}');

        Swal.fire({
            title: 'Memproses file...', text: 'Mohon tunggu sebentar.',
            allowOutsideClick: false, didOpen: function () { Swal.showLoading(); }
        });

        $.ajax({
            url: '{{ route('project.budget-import-preview') }}',
            type: 'POST', data: formData, processData: false, contentType: false
        }).done(function (res) {
            Swal.close();

            if (res.errors && res.errors.length) {
                // Pesan memuat teks dari sel Excel, jadi disusun lewat textContent (bukan string HTML).
                var box = document.createElement('div');
                box.style.cssText = 'text-align:left;max-height:250px;overflow:auto;';
                res.errors.forEach(function (msg) {
                    var line = document.createElement('div');
                    line.textContent = '• ' + msg;
                    box.appendChild(line);
                });
                Swal.fire({ icon: 'warning', title: 'Sebagian data bermasalah', html: box, confirmButtonColor: '#92232b' });
            }

            if (res.rows && res.rows.length) {
                // Ganti seluruh baris yang ada dengan hasil import
                body.innerHTML = '';
                idx = 0;
                res.rows.forEach(addRow);

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
            var j = xhr.responseJSON || {};
            var msg = j.message
                || (j.errors && j.errors[0])
                || 'Gagal memproses file. Pastikan format Excel sesuai template.';
            Swal.fire({ icon: 'error', title: 'Import Gagal', text: msg });
        });
    });

    // Hapus Budget (hanya ada di edit PO DRAFT): konfirmasi, lalu input budget dinonaktifkan
    // supaya hanya budget[hapus]=1 yang terkirim. "Batalkan" mengembalikan form seperti semula.
    // Yang dinonaktifkan mencakup tombol Import/Tambah Baris/Download Template dan tombol widget (tss).
    var btnDel  = document.getElementById('pobDelete');
    var hapus   = document.getElementById('pobHapus');
    var notice  = document.getElementById('pobDeleteNotice');
    var btnUndo = document.getElementById('pobDeleteUndo');
    var wrap    = document.getElementById('pobWrap');

    function setHapus(on) {
        hapus.value = on ? '1' : '0';
        notice.classList.toggle('d-none', !on);
        btnDel.classList.toggle('d-none', on);
        wrap.querySelectorAll('input, select, textarea, button').forEach(function (el) {
            if (el === hapus || el === btnUndo || el === btnDel) return;
            el.disabled = on;
        });
    }

    if (btnDel && hapus && notice && btnUndo && wrap) {
        btnDel.addEventListener('click', function () {
            var pesan = 'Budget akan dihapus permanen beserta detail, log, dan lampirannya. Tindakan ini tidak bisa dibatalkan.';
            if (typeof Swal === 'undefined') {
                if (confirm(pesan)) setHapus(true);
                return;
            }
            Swal.fire({
                icon: 'warning', title: 'Hapus Budget dari PO ini?', text: pesan,
                showCancelButton: true, confirmButtonText: 'Ya, hapus saat disimpan', cancelButtonText: 'Batal',
                confirmButtonColor: '#dc3545'
            }).then(function (r) { if (r.isConfirmed) setHapus(true); });
        });
        btnUndo.addEventListener('click', function () { setHapus(false); });
    }
});
</script>

@elseif($bundledBudget)
<!-- BUDGET (read-only) -->
<div class="card card-primary card-outline shadow-sm">
    <div class="card-header d-flex align-items-center flex-wrap" style="gap:8px;">
        <h3 class="card-title mb-0">
            <i class="fas fa-money-check-alt mr-1"></i> Budget
            <small class="text-muted ml-1">(ikut approval PO)</small>
        </h3>
        <div class="ml-auto d-flex align-items-center flex-wrap" style="gap:8px;">
            <span class="badge badge-{{ $bundledBudget->status_color }} px-3 py-2">{{ $bundledBudget->status }}</span>
            <span class="badge badge-primary px-3 py-2" style="font-size:13px;">Total: {{ $bundledBudget->amount_formatted }}</span>
            <a href="{{ url('/budget-plan') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-external-link-alt mr-1"></i> Buka Budget Plan
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="mb-2">
            <strong>{{ $bundledBudget->code }}</strong> — {{ $bundledBudget->description }}
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-2">
                <thead class="bg-light">
                    <tr>
                        <th scope="col">Chart of Account</th>
                        <th scope="col">Deskripsi</th>
                        <th scope="col" class="text-right">Harga Satuan (Rp)</th>
                        <th scope="col" class="text-center">Qty</th>
                        <th scope="col" class="text-right">Total (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bundledBudget->details as $d)
                    <tr>
                        <td>{{ $d->account?->name ?? '-' }}</td>
                        <td>{{ $d->deskripsi ?? '-' }}</td>
                        <td class="text-right">{{ number_format((int) $d->amount, 0, ',', '.') }}</td>
                        <td class="text-center">{{ rtrim(rtrim(number_format((float) $d->qty, 2, ',', '.'), '0'), ',') }}</td>
                        <td class="text-right">{{ number_format((int) $d->total, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <small class="text-muted">
            <i class="fas fa-lock mr-1"></i>
            Budget hanya bisa diubah selagi berstatus DRAFT oleh Head. Statusnya bergerak bersama PO
            dan otomatis CLOSED saat PO ditutup.
        </small>
    </div>
</div>
@endif
