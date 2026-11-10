@extends('layouts.app')

@section('title', 'Edit PO')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ url('/project') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Edit PO</h1>
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
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </div>

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

        <form action="{{ url('/project/' . $project->id) }}" method="POST"
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
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="font-weight-600">Kode <span class="badge badge-secondary ml-1" style="font-size:10px;">Fixed</span></label>
                                <input type="text" class="form-control"
                                       value="{{ $project->code }}" readonly
                                       style="background:var(--surface-2);cursor:not-allowed;font-weight:700;">
                            </div>
                        </div>
                    </div>

                    <div class="row po-two-col form-clean">

                        <!-- KOLOM KIRI -->
                        <div class="col-md-6 po-col-left">

                            <!-- SCOPE OF WORK -->
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

                            <!-- CUSTOMER -->
                            <div class="form-group">
                                <label class="font-weight-600">Customer</label>
                                <select name="customer_id" id="selectCustomer" class="form-control @error('customer_id') is-invalid @enderror">
                                    <option value="">— Pilih Customer —</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}"
                                            {{ old('customer_id', $project->customer_id) == $customer->id ? 'selected' : '' }}>
                                            {{ $customer->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- MANAGER (PM Internal) -->
                            <div class="form-group">
                                <label class="font-weight-600">Manager</label>
                                <select name="manager_id" id="selectManager" class="form-control @error('manager_id') is-invalid @enderror">
                                    <option value="">— Pilih Manager —</option>
                                    @foreach($managers as $m)
                                        <option value="{{ $m->id }}" {{ old('manager_id', $project->manager_id) == $m->id ? 'selected' : '' }}>{{ $m->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- NILAI PO — dihitung otomatis dari total nominal No. PO, tidak diinput manual -->
                            <div class="form-group">
                                <label class="font-weight-600">Nilai PO</label>
                                <input type="text" class="form-control text-right"
                                       value="{{ number_format($project->value, 0, ',', '.') }}" readonly disabled>
                                <small class="text-muted d-block mt-1">Otomatis = total nominal semua No. PO yang dientri di bawah.</small>
                            </div>

                            <!-- TIPE PEKERJAAN -->
                            <div class="form-group">
                                <label class="font-weight-600">Tipe</label>
                                <select name="type" class="form-control @error('type') is-invalid @enderror">
                                    <option value="">— Pilih Tipe —</option>
                                    @foreach(\App\Models\Project::TYPES as $tKey => $tLabel)
                                        <option value="{{ $tKey }}" {{ old('type', $project->type) === $tKey ? 'selected' : '' }}>{{ $tLabel }}</option>
                                    @endforeach
                                </select>
                                @error('type')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                            <!-- PO QUANTITY -->
                            <div class="form-group">
                                <label class="font-weight-600">PO Quantity</label>
                                <input type="number" min="0" step="1" name="po_quantity"
                                       class="form-control text-right @error('po_quantity') is-invalid @enderror"
                                       value="{{ old('po_quantity', $project->po_quantity) }}"
                                       placeholder="Jumlah No. PO yang diharapkan" autocomplete="off">
                                @error('po_quantity')<small class="text-danger d-block">{{ $message }}</small>@enderror
                                @if($project->poAdjustmentRequired())
                                <small class="text-warning d-block mt-1">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> PO Adjustment Required — jumlah No. PO ({{ $project->poNumbers->count() }}) tidak sama dengan PO Quantity ({{ $project->po_quantity }}).
                                </small>
                                @endif
                            </div>

                            <!-- NILAI KONTRAK -->
                            <div class="form-group">
                                <label class="font-weight-600">Nilai Kontrak</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">Rp</span>
                                    </div>
                                    <input type="text" name="contract"
                                           class="form-control text-right inp-money"
                                           value="{{ old('contract', number_format($project->contract ?? 0, 0, ',', '.')) }}"
                                           placeholder="0" autocomplete="off">
                                </div>
                                <small class="text-muted">Nilai kontrak yang ditandatangani (bisa berbeda dari Nilai PO).</small>
                            </div>

                            <!-- RESTITUSI -->
                            <div class="form-group{{ (in_array($project->status, ['WAITING_PO','SUBMITTED']) && Auth::user()->hasRole(['superadmin','finance','finance_manager'])) ? '' : ' mb-0' }}">
                                <label class="font-weight-600">Restitusi</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">Rp</span>
                                    </div>
                                    <input type="text" name="restitusi"
                                           class="form-control text-right inp-money"
                                           value="{{ old('restitusi', number_format($project->restitusi ?? 0, 0, ',', '.')) }}"
                                           placeholder="0" autocomplete="off">
                                </div>
                            </div>

                            <!-- NO PO (hanya finance saat WAITING_PO) -->
                            @if(in_array($project->status, ['WAITING_PO','SUBMITTED']) && Auth::user()->hasRole(['superadmin','finance','finance_manager']))
                            <div class="form-group mb-0">
                                <label class="font-weight-600">No PO</label>
                                <input type="text" name="po_number"
                                       class="form-control"
                                       value="{{ old('po_number', $project->po_number) }}"
                                       placeholder="Nomor PO dari customer...">
                                <small class="text-muted">Isi jika sudah diterima dari customer.</small>
                            </div>
                            @endif

                        </div>

                        <!-- KOLOM KANAN -->
                        <div class="col-md-6 po-col-right">

                            <!-- TERM OF PAYMENT -->
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

                            <!-- ACCESS GROUP -->
                            <div class="form-group">
                                <label class="font-weight-600">Access Group</label>
                                <select name="access_group_ids[]" id="selAccessGroups" multiple>
                                    @foreach($accessGroups as $ag)
                                        @php
                                            $agManagers = $ag->users->pluck('name')->implode(', ');
                                            $agLabel = $ag->name . ($agManagers ? ' (' . $agManagers . ')' : '');
                                        @endphp
                                        <option value="{{ $ag->id }}" data-label="{{ $agLabel }}"
                                            {{ collect(old('access_group_ids', $selectedAccessGroupIds))->contains($ag->id) ? 'selected' : '' }}>
                                            {{ $agLabel }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Menentukan user/tim mana yang bisa lihat PO ini (filter operasional).</small>
                            </div>

                            <!-- FINANCING (hanya finance & superadmin, default Non Loan) -->
                            @if(!Auth::user()->hasRole(['head']))
                            <div class="form-group">
                                <label class="font-weight-600">Financing</label>
                                <select name="financing" class="form-control @error('financing') is-invalid @enderror">
                                    @foreach(['PL' => 'PL - PO Loan', 'IL' => 'IL - Invoice Loan', 'NL' => 'NL - Non Loan'] as $opt => $optLabel)
                                        <option value="{{ $opt }}" {{ old('financing', $project->financing ?? 'NL') === $opt ? 'selected' : '' }}>{{ $optLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif

                            <!-- BIAYA TAMBAHAN: Overhead & Fixed Cost (manual, terpisah dari Cost Register) -->
                            <div class="form-group">
                                <label class="font-weight-600">Overhead</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                    <input type="text" name="overhead"
                                           class="form-control text-right inp-money"
                                           value="{{ old('overhead', number_format($project->overhead ?? 0, 0, ',', '.')) }}"
                                           placeholder="0" autocomplete="off">
                                </div>
                            </div>

                            <div class="form-group mb-0">
                                <label class="font-weight-600">Fixed Cost</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                    <input type="text" name="fixed_cost"
                                           class="form-control text-right inp-money"
                                           value="{{ old('fixed_cost', number_format($project->fixed_cost ?? 0, 0, ',', '.')) }}"
                                           placeholder="0" autocomplete="off">
                                </div>
                                <small class="text-muted">Nominal manual. Kolom Overhead / Fixed Cost pada daftar PO
                                    dan laporan = nominal ini <strong>ditambah</strong> biaya dari modul Expense yang
                                    dibebankan ke PO ini — jangan isi ulang di sini biaya yang sudah dicatat lewat
                                    Expense (akan terhitung dua kali).</small>
                            </div>

                        </div>

                    </div>
                </div>
            </div>

            <!-- SECTION PROJECT DETAIL -->
            <div class="card card-warning card-outline shadow-sm">
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
                                    <th scope="col" style="width:5%;" class="text-center">No</th>
                                    <th scope="col" style="width:25%;">Project</th>
                                    <th scope="col" style="width:20%;">Milestone</th>
                                    <th scope="col" style="width:20%;">Plan Start</th>
                                    <th scope="col" style="width:20%;">Plan Finish</th>
                                    <th scope="col" style="width:10%;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="detailBody">
                                @forelse($project->details as $i => $det)
                                <tr>
                                    <td class="text-center align-middle row-no-detail">{{ $i + 1 }}</td>
                                    <td>
                                        {{-- Identitas baris: tanpa ini controller tidak bisa membedakan
                                             milestone lama dari baru dan terpaksa hapus-buat-ulang — yang
                                             memutus log monitoring & me-reset status/bobot milestone.
                                             Sengaja TIDAK diletakkan di sel No: renumberDetail() menimpa
                                             isi sel itu dengan textContent, input ini akan ikut terhapus. --}}
                                        <input type="hidden" name="details[{{ $i }}][id]" value="{{ $det->id }}">
                                        <select name="details[{{ $i }}][cluster_id]" class="form-control form-control-sm select-cluster">
                                            <option value="">— Pilih Cluster —</option>
                                            @foreach($clusters as $cluster)
                                                <option value="{{ $cluster->id }}" {{ $det->cluster_id == $cluster->id ? 'selected' : '' }}>
                                                    {{ $cluster->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="details[{{ $i }}][stage_id]" class="form-control form-control-sm select-stage">
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
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-users mr-1"></i> PIC</h3>
                </div>
                <div class="card-body">
                    @php
                        $selectedInternalIds = $project->pics->where('type', 'internal')->pluck('id')->all();
                        $selectedCustomerIds = $project->pics->where('type', 'customer')->pluck('id')->all();
                    @endphp
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">PIC Internal</label>
                                <select name="pics_internal[]" id="selPicsInternal" multiple>
                                    @foreach($picsInternal as $p)
                                        @php $picLabel = $p->pic . ($p->position ? ' — ' . $p->position : ''); @endphp
                                        <option value="{{ $p->id }}" data-label="{{ $picLabel }}" {{ in_array($p->id, $selectedInternalIds) ? 'selected' : '' }}>{{ $picLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label class="font-weight-600">PIC Customer</label>
                                <select name="pics_customer[]" id="selPicsCustomer" multiple>
                                    @foreach($picsCustomer as $p)
                                        @php $picLabel = $p->pic . ($p->position ? ' — ' . $p->position : ''); @endphp
                                        <option value="{{ $p->id }}" data-label="{{ $picLabel }}" {{ in_array($p->id, $selectedCustomerIds) ? 'selected' : '' }}>{{ $picLabel }}</option>
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
                        <a href="{{ url('/pic-beneficiary') }}" target="_blank">Tambah di PIC &amp; Beneficiary</a>.
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
                                    <th scope="col" style="width:5%;" class="text-center">No</th>
                                    <th scope="col" style="width:65%;">Nama File</th>
                                    <th scope="col" style="width:15%;" class="text-center">Preview</th>
                                    <th scope="col" style="width:15%;" class="text-center">Hapus</th>
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
                    <button type="button" class="btn btn-sm btn-primary ml-auto" id="btnAddAtt">
                        <i class="fas fa-plus mr-1"></i> Tambah
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
                                    <th scope="col" style="width:5%;" class="text-center">No</th>
                                    <th scope="col" style="width:30%;">Judul</th>
                                    <th scope="col" style="width:55%;">File</th>
                                    <th scope="col" style="width:10%;" class="text-center">Aksi</th>
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

            <!-- DETAIL NO. PO -->
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
                                            <a href="{{ asset('storage/' . $att->file_path) }}" target="_blank" class="d-block">
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
                        No. PO yang sudah ada tidak bisa diedit/dihapus dari sini — ubah statusnya lewat halaman Detail PO.
                    </small>
                    @endif

                    <div class="d-flex align-items-center mb-2">
                        <strong style="font-size:13px;">Tambah No. PO Baru</strong>
                        <button type="button" class="btn btn-sm btn-primary ml-auto" id="btnAddPoNumber">
                            <i class="fas fa-plus mr-1"></i> Tambah No. PO
                        </button>
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
            </div>

            {{-- BUDGET (opsional, Head/superadmin) — Budget Plan yang ikut approval PO --}}
            @include('project._budget-section', ['canFillBudget' => $canFillBudget, 'budgetAccounts' => $budgetAccounts, 'bundledBudget' => $bundledBudget, 'project' => $project])

            <!-- NOTES -->
            <div class="card card-warning card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-sticky-note mr-1"></i> Notes</h3>
                </div>
                <div class="card-body">
                    <div class="form-group mb-0">
                        <textarea name="notes" class="form-control" rows="2"
                                  placeholder="Catatan tambahan...">{{ old('notes', $project->notes) }}</textarea>
                    </div>
                </div>
            </div>

            <!-- ACTION -->
            <div class="card shadow-sm">
                <div class="card-body d-flex flex-wrap" style="gap:8px;">
                    <a href="{{ url('/project') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left mr-1"></i> Kembali
                    </a>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save mr-1"></i> Simpan Perubahan
                    </button>
                </div>
            </div>

        </form>

        {{-- Submit (terpisah, hanya DRAFT + head) --}}
        @if($project->status === 'DRAFT' && Auth::user()->hasRole(['superadmin','head']))
        <form action="{{ url('/project/' . $project->id . '/submit') }}"
              method="POST" id="frmSubmit">
            @csrf
        </form>
        <div class="card shadow-sm border-primary">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <strong class="text-primary">Siap diajukan ke Finance?</strong>
                    <p class="mb-0 text-muted" style="font-size:13px;">
                        Simpan dulu perubahan sebelum submit.
                    </p>
                </div>
                <button type="button" class="btn btn-primary ml-3" id="btnSubmit">
                    <i class="fas fa-paper-plane mr-1"></i> Submit ke Finance
                </button>
            </div>
        </div>
        @endif

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
                '<td><select name="details[' + i + '][cluster_id]" class="form-control form-control-sm select-cluster"><option value="">— Pilih Project —</option>' + clusterSel + '</select></td>' +
                '<td><select name="details[' + i + '][stage_id]" class="form-control form-control-sm select-stage"><option value="">— Pilih Milestone —</option>' + stageSel + '</select></td>' +
                '<td><input type="date" name="details[' + i + '][plan_start]" class="form-control form-control-sm" value="' + (prefill.plan_start || '') + '"></td>' +
                '<td><input type="date" name="details[' + i + '][plan_end]" class="form-control form-control-sm" value="' + (prefill.plan_end || '') + '"></td>' +
                '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-detail"><i class="fas fa-trash"></i></button></td>' +
            '</tr>';
    }

    // ── SEARCHABLE DROPDOWN: Customer & tiap baris Project/Milestone ────
    TesysSearchableSelect.init('#selectCustomer', { placeholder: '— Pilih Customer —', searchPlaceholder: 'Cari Customer...' });
    TesysSearchableSelect.init('#selectManager', { placeholder: '— Pilih Manager —', searchPlaceholder: 'Cari Manager...' });

    function initDetailPickers(scope) {
        (scope || document).querySelectorAll('select.select-cluster').forEach(function (sel) {
            TesysSearchableSelect.initOne($(sel), { placeholder: '— Pilih Project —', searchPlaceholder: 'Cari Project...' });
        });
        (scope || document).querySelectorAll('select.select-stage').forEach(function (sel) {
            TesysSearchableSelect.initOne($(sel), { placeholder: '— Pilih Milestone —', searchPlaceholder: 'Cari Milestone...' });
        });
    }
    initDetailPickers(document.getElementById('detailBody'));

    var btnAddDetail = document.getElementById('btnAddDetail');
    if (btnAddDetail) {
        btnAddDetail.addEventListener('click', function () {
            var i = detailIdx++;
            document.getElementById('detailBody').insertAdjacentHTML('beforeend', makeDetailRowHTML(i, null));
            initDetailPickers(document.getElementById('detailBody'));
            renumberDetail();
        });
    }

    // ── Download Template & Import Excel — Project & Milestone ──────────
    var btnDLTemplate = document.getElementById('btnDownloadDetailTemplate');
    if (btnDLTemplate) {
        btnDLTemplate.addEventListener('click', function (e) {
            e.preventDefault();
            window.location.href = '{{ url("/project/import-template") }}';
        });
    }

    var btnImportExcel = document.getElementById('btnImportDetailExcel');
    if (btnImportExcel) {
        btnImportExcel.addEventListener('click', function () {
            document.getElementById('importDetailFileInput').value = '';
            document.getElementById('importDetailFileInput').click();
        });
    }

    var importDetailInput = document.getElementById('importDetailFileInput');
    if (importDetailInput) {
        importDetailInput.addEventListener('change', function () {
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
                        confirmButtonColor: '#92232b'
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

                    initDetailPickers(document.getElementById('detailBody'));
                    renumberDetail();

                    Swal.fire({
                        icon: 'success', title: 'Import berhasil',
                        text: res.rows.length + ' baris data dimuat ke Project & Milestone.',
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

    // PIC — multi-select dari Master PIC
    TesysSearchableSelect.init('#selPicsInternal', { placeholder: 'Pilih PIC Internal...', searchPlaceholder: 'Cari PIC Internal...' });
    TesysSearchableSelect.init('#selPicsCustomer', { placeholder: 'Pilih PIC Customer...', searchPlaceholder: 'Cari PIC Customer...' });
    TesysSearchableSelect.init('#selAccessGroups', { placeholder: 'Pilih Access Group...', searchPlaceholder: 'Cari Access Group...' });

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
        renumber('attBody');
    }

    var btnAddAtt = document.getElementById('btnAddAtt');
    if (btnAddAtt) {
        btnAddAtt.addEventListener('click', function () {
            addAttRowPO(null);
        });
    }

    // Detail No. PO — tambah baris baru (append-only, No. PO lama tidak diedit dari sini)
    var poNumIdx = 0;

    function addPoNumberRow() {
        var i = poNumIdx++;
        var html =
            '<tr>' +
                '<td class="text-center align-middle row-no"></td>' +
                '<td><input type="text" name="po_numbers[' + i + '][po_number]" class="form-control form-control-sm" style="text-transform:uppercase;" placeholder="No. PO..." required></td>' +
                '<td><div class="input-group input-group-sm"><div class="input-group-prepend"><span class="input-group-text">Rp</span></div>' +
                    '<input type="text" name="po_numbers[' + i + '][nominal]" class="form-control inp-money" placeholder="0"></div></td>' +
                '<td><input type="date" name="po_numbers[' + i + '][release_date]" class="form-control form-control-sm"></td>' +
                '<td><input type="file" name="po_numbers[' + i + '][file]" class="form-control-file"></td>' +
                '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-ponum"><i class="fas fa-trash"></i></button></td>' +
            '</tr>';
        document.getElementById('poNumberBody').insertAdjacentHTML('beforeend', html);
        renumber('poNumberBody');
    }

    var btnAddPoNumber = document.getElementById('btnAddPoNumber');
    if (btnAddPoNumber) {
        btnAddPoNumber.addEventListener('click', addPoNumberRow);
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('.btn-remove-ponum')) {
            e.target.closest('tr').remove();
            renumber('poNumberBody');
        }
    });

    // Drop zone
    (function () {
        var zone   = document.getElementById('dropZonePO');
        var input  = document.getElementById('dropZoneInputPO');
        var browse = document.getElementById('dropZoneBrowsePO');
        if (!zone) return;

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
            renumber('attBody');
        }
    });

    function renumber(tbodyId) {
        document.querySelectorAll('#' + tbodyId + ' .row-no').forEach(function (el, i) {
            el.textContent = i + 1;
        });
    }

    // Submit confirmation
    var btnSubmit = document.getElementById('btnSubmit');
    if (btnSubmit && typeof Swal !== 'undefined') {
        btnSubmit.addEventListener('click', function () {
            Swal.fire({
                title: 'Submit PO ke Finance?',
                text: 'PO akan dikirim ke Finance untuk direview. Pastikan data sudah benar.',
                icon: 'question', showCancelButton: true,
                confirmButtonColor: '#42627d', cancelButtonColor: '#55595e',
                confirmButtonText: 'Ya, Submit', cancelButtonText: 'Batal'
            }).then(function (r) {
                if (r.isConfirmed) document.getElementById('frmSubmit').submit();
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

.ts-wrapper { min-width:0; }
.ts-control { border-radius:var(--radius-sm) !important; border:1px solid var(--border) !important; min-height:38px !important; padding:6px 10px !important; box-shadow:none !important; }

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

@media (max-width: 576px) {
    .ts-wrapper, .ts-control { width:100% !important; min-width:0 !important; max-width:100% !important; }
    .ts-control > input { min-width:0 !important; width:100% !important; }
    .table-responsive { overflow-x:auto; -webkit-overflow-scrolling:touch; }
}
</style>
@endpush