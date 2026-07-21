@extends('layouts.app')

@section('title', 'Tambah Budget Plan')

@section('content')

<!-- CONTENT HEADER -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6 d-flex align-items-center">
                <a href="{{ url('/budget-plan') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="m-0 font-weight-bold">Tambah Budget Plan</h1>
                    <small class="text-muted">Form penambahan Budget Plan baru</small>
                </div>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right mb-0">
                    <li class="breadcrumb-item"><a href="#">Finance</a></li>
                    <li class="breadcrumb-item"><a href="{{ url('/budget-plan') }}">Budget Plan</a></li>
                    <li class="breadcrumb-item active">Tambah</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">

        <form action="{{ url('/budget-plan') }}" method="POST"
              enctype="multipart/form-data" id="frmBudgetPlan">
            @csrf

            <!-- ============================================================ -->
            <!-- SECTION 1: INFORMASI UTAMA                                    -->
            <!-- ============================================================ -->
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-money-check-alt mr-1"></i> Informasi Budget Plan
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

                        <!-- PO -->
                        <div class="col-md-5">
                            <div class="form-group">
                                <label class="font-weight-600">
                                    PO (Project Order) <span class="text-danger">*</span>
                                </label>
                                <select name="project_id" id="selectProject"
                                        class="form-control @error('project_id') is-invalid @enderror"
                                        required>
                                    <option value="">— Pilih PO —</option>
                                    @foreach($projects as $project)
                                        <option value="{{ $project->id }}"
                                            {{ old('project_id') == $project->id ? 'selected' : '' }}>
                                            [{{ $project->code ?? '-' }}] {{ $project->name }}
                                            @if($project->customer)
                                                — {{ $project->customer->name }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('project_id')
                                    <small class="text-danger d-block">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>

                        <!-- DESKRIPSI -->
                        <div class="col-md-5">
                            <div class="form-group">
                                <label class="font-weight-600">
                                    Deskripsi <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="description"
                                       class="form-control @error('description') is-invalid @enderror"
                                       value="{{ old('description') }}"
                                       placeholder="Deskripsi budget plan..."
                                       autocomplete="off" required>
                                @error('description')
                                    <small class="text-danger d-block">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>

                        <!-- TYPE -->
                        <div class="col-md-2">
                            <div class="form-group">
                                <label class="font-weight-600">
                                    Tipe <span class="text-danger">*</span>
                                </label>
                                <select name="type"
                                        class="form-control @error('type') is-invalid @enderror"
                                        required>
                                    @foreach($types as $t)
                                        <option value="{{ $t }}"
                                            {{ old('type') == $t ? 'selected' : '' }}>
                                            {{ ucfirst(str_replace('_', ' ', $t)) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('type')
                                    <small class="text-danger d-block">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>

                        <!-- NOTES -->
                        <div class="col-md-12">
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
            <!-- SECTION 2: DETAIL BUDGET                                      -->
            <!-- ============================================================ -->
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-list-ul mr-1"></i> Detail Budget
                    </h3>
                    <div class="ml-auto d-flex align-items-center" style="gap:8px;">
                        <span id="totalAmountLabel"
                              class="badge badge-primary px-3 py-2"
                              style="font-size:13px;">
                            Total: Rp 0
                        </span>
                        <button type="button" class="btn btn-sm btn-primary" id="btnAddDetail">
                            <i class="fas fa-plus mr-1"></i> Tambah Baris
                        </button>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" id="tblDetail">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width:5%;" class="text-center">No</th>
                                    <th style="width:10%;">Tipe</th>
                                    <th style="width:18%;">Kategori</th>
                                    <th style="width:22%;">Account</th>
                                    <th style="width:18%;" class="text-right">Amount (Rp)</th>
                                    <th style="width:22%;">Notes</th>
                                    <th style="width:5%;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="detailBody">
                                @php $oldDetails = old('details', [[]]); @endphp
                                @foreach($oldDetails as $i => $d)
                                <tr class="detail-row">
                                    <td class="text-center align-middle row-no">{{ $i + 1 }}</td>
                                    <td>
                                        <select name="details[{{ $i }}][type]"
                                                class="form-control form-control-sm">
                                            @foreach($detailTypes as $dt)
                                                <option value="{{ $dt }}"
                                                    {{ ($d['type'] ?? 'main') == $dt ? 'selected' : '' }}>
                                                    {{ ucfirst($dt) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="details[{{ $i }}][category]"
                                                class="form-control form-control-sm select-category">
                                            <option value="">— Pilih —</option>
                                            @foreach($categories as $cat)
                                                <option value="{{ $cat }}"
                                                    {{ ($d['category'] ?? '') == $cat ? 'selected' : '' }}>
                                                    {{ $cat }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="details[{{ $i }}][account_id]"
                                                class="form-control form-control-sm select-account">
                                            <option value="">— Pilih Category dulu —</option>
                                            @foreach($accounts->where('category', $d['category'] ?? '') as $acc)
                                                <option value="{{ $acc->id }}"
                                                    {{ ($d['account_id'] ?? '') == $acc->id ? 'selected' : '' }}>
                                                    {{ $acc->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text"
                                               name="details[{{ $i }}][amount]"
                                               class="form-control form-control-sm text-right money-detail"
                                               value="{{ old('details.'.$i.'.amount') }}"
                                               placeholder="0">
                                    </td>
                                    <td>
                                        <input type="text"
                                               name="details[{{ $i }}][notes]"
                                               class="form-control form-control-sm"
                                               value="{{ $d['notes'] ?? '' }}"
                                               placeholder="Keterangan...">
                                    </td>
                                    <td class="text-center align-middle">
                                        <button type="button"
                                                class="btn btn-danger btn-sm btn-remove-detail">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer">
                    <small class="text-muted">
                        <i class="fas fa-info-circle mr-1"></i>
                        Minimal 1 baris detail wajib diisi. Account akan muncul setelah Kategori dipilih.
                    </small>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- SECTION 3: LAMPIRAN                                           -->
            <!-- ============================================================ -->
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-paperclip mr-1"></i> Lampiran
                    </h3>
                    <button type="button" class="btn btn-sm btn-primary ml-auto"
                            id="btnAddAttachment">
                        <i class="fas fa-plus mr-1"></i> Tambah Lampiran
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" id="tblAttachment">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width:5%;" class="text-center">No</th>
                                    <th style="width:35%;">Judul</th>
                                    <th style="width:50%;">File</th>
                                    <th style="width:10%;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="attachmentBody">
                                {{-- baris ditambah via JS --}}
                            </tbody>
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
                    <a href="{{ url('/budget-plan') }}" class="btn btn-secondary mr-2">
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

@endsection

@push('styles')
<style>
.font-weight-600 { font-weight:600; }
#tblDetail td, #tblAttachment td { vertical-align:middle; }
</style>
@endpush

{{-- Data untuk JS: diletakkan di luar @push agar tidak dibaca sebagai JS --}}
<div id="accountsDataEl"
     data-accounts="{{ htmlspecialchars(json_encode($accounts->groupBy('category')->map(fn($g) => $g->map(fn($a) => ['id' => $a->id, 'name' => $a->name]))), ENT_QUOTES) }}"
     style="display:none;"></div>

@push('scripts')
<script>
const accountsData = JSON.parse(
    document.getElementById('accountsDataEl').dataset.accounts
);
</script>
<script>
$(function () {

    /*
    |--------------------------------------------------------------------------
    | RUPIAH FORMAT
    |--------------------------------------------------------------------------
    */
    function formatRupiah(v) {
        v = (v || '').toString().replace(/\D/g, '');
        return v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function parseRupiah(v) {
        return parseInt((v || '').toString().replace(/\D/g, '')) || 0;
    }

    $(document).on('input', '.money-detail', function () {
        var pos = this.selectionStart;
        this.value = formatRupiah(this.value);
        recalcTotal();
    });

    // Init existing rows
    $('.money-detail').each(function () {
        this.value = formatRupiah(this.value);
    });

    /*
    |--------------------------------------------------------------------------
    | RECALC TOTAL
    |--------------------------------------------------------------------------
    */
    function recalcTotal() {
        var total = 0;
        $('.money-detail').each(function () {
            total += parseRupiah(this.value);
        });
        $('#totalAmountLabel').text('Total: Rp ' + total.toLocaleString('id-ID'));
    }

    recalcTotal();

    /*
    |--------------------------------------------------------------------------
    | RENUMBER
    |--------------------------------------------------------------------------
    */
    function renumber(tbodyId) {
        $('#' + tbodyId + ' .row-no').each(function (i) { $(this).text(i + 1); });
    }

    /*
    |--------------------------------------------------------------------------
    | CATEGORY → ACCOUNT FILTER
    |--------------------------------------------------------------------------
    */
    function buildAccountOptions(category, selectedId) {
        var options = '<option value="">— Pilih Account —</option>';
        var list = accountsData[category] || [];
        list.forEach(function (a) {
            var sel = (selectedId && selectedId == a.id) ? 'selected' : '';
            options += '<option value="' + a.id + '" ' + sel + '>' + a.name + '</option>';
        });
        if (!list.length) {
            options = '<option value="">— Tidak ada account —</option>';
        }
        return options;
    }

    $(document).on('change', '.select-category', function () {
        var cat = $(this).val();
        var accountSel = $(this).closest('tr').find('.select-account');
        accountSel.html(buildAccountOptions(cat, null));
    });

    /*
    |--------------------------------------------------------------------------
    | DETAIL ROWS
    |--------------------------------------------------------------------------
    */
    var detailIndex = $('#detailBody tr').length;

    function newDetailRow(i) {
        return `
        <tr class="detail-row">
            <td class="text-center align-middle row-no"></td>
            <td>
                <select name="details[${i}][type]" class="form-control form-control-sm">
                    <option value="main">Main</option>
                    <option value="additional">Additional</option>
                </select>
            </td>
            <td>
                <select name="details[${i}][category]" class="form-control form-control-sm select-category">
                    <option value="">— Pilih —</option>
                    <option value="Variable Cost">Variable Cost</option>
                    <option value="Fixed Cost">Fixed Cost</option>
                    <option value="Overhead">Overhead</option>
                </select>
            </td>
            <td>
                <select name="details[${i}][account_id]" class="form-control form-control-sm select-account">
                    <option value="">— Pilih Category dulu —</option>
                </select>
            </td>
            <td>
                <input type="text" name="details[${i}][amount]"
                       class="form-control form-control-sm text-right money-detail"
                       placeholder="0">
            </td>
            <td>
                <input type="text" name="details[${i}][notes]"
                       class="form-control form-control-sm"
                       placeholder="Keterangan...">
            </td>
            <td class="text-center align-middle">
                <button type="button" class="btn btn-danger btn-sm btn-remove-detail">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>`;
    }

    $('#btnAddDetail').on('click', function () {
        $('#detailBody').append(newDetailRow(detailIndex++));
        renumber('detailBody');
    });

    $(document).on('click', '.btn-remove-detail', function () {
        $(this).closest('tr').remove();
        renumber('detailBody');
        recalcTotal();
    });

    /*
    |--------------------------------------------------------------------------
    | ATTACHMENT ROWS
    |--------------------------------------------------------------------------
    */
    var attachIndex = 0;

    $('#btnAddAttachment').on('click', function () {
        var i = attachIndex++;
        var row = `
        <tr>
            <td class="text-center align-middle row-no"></td>
            <td><input type="text" name="attachments[${i}][title]"
                       class="form-control form-control-sm"
                       placeholder="Judul lampiran"></td>
            <td><input type="file" name="attachments[${i}][file]"
                       class="form-control-file"></td>
            <td class="text-center align-middle">
                <button type="button" class="btn btn-danger btn-sm btn-remove-att">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>`;
        $('#attachmentBody').append(row);
        renumber('attachmentBody');
    });

    $(document).on('click', '.btn-remove-att', function () {
        $(this).closest('tr').remove();
        renumber('attachmentBody');
    });

});
</script>
@endpush