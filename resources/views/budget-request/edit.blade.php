@extends('layouts.app')

@section('title', 'Edit Cost Request')

@section('content')

<div class="content">
    <div class="container-fluid">
        <div class="tw:flex tw:flex-col tw:gap-5 tw:py-4">

            {{-- Judul & breadcrumb topbar: shell memindahkan <h1> dan .breadcrumb pertama ke topbar lalu
                 menghapusnya dari konten (tesys-shell.js hoistPageTitle); disembunyikan secara visual. --}}
            <div class="tw:sr-only">
                <h1>Edit Cost Request</h1>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Finance</a></li>
                    <li class="breadcrumb-item"><a href="{{ url('/budget-request') }}">Cost Request</a></li>
                    <li class="breadcrumb-item active">Edit</li>
                </ol>
            </div>

            <x-tw.page-header title="Edit Cost Request"
                description="Kode: {{ $cr->code }}">
                <x-slot:actions>
                    <x-status :status="$cr->status" context="budget-request" size="md" />
                    <x-tw.button href="{{ url('/budget-request') }}" variant="secondary" class="tw:max-sm:min-h-12"><i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali ke daftar</x-tw.button>
                </x-slot:actions>
            </x-tw.page-header>

            {{-- Catatan revisi dari manager/finance --}}
            @if($cr->statusLogs->first() && in_array($cr->statusLogs->first()->action_type, ['manager_revised','finance_revised']))
            <div class="tw:rounded-md tw:border tw:border-warning tw:bg-warning-bg tw:p-3 tw:text-data tw:leading-body tw:text-warning">
                <i class="fas fa-exclamation-triangle tw:mr-1" aria-hidden="true"></i>
                <strong>Catatan Revisi:</strong>
                {{ $cr->statusLogs->first()->notes }}
            </div>
            @endif

            @if($errors->any())
            <div role="alert" class="tw:rounded-md tw:border tw:border-danger tw:bg-danger-bg tw:p-3 tw:text-data tw:leading-body tw:text-danger">
                <div class="tw:font-label">Terjadi kesalahan:</div>
                <div class="tw:mt-1 tw:flex tw:flex-col tw:gap-1">
                    @foreach($errors->all() as $error)
                    <div class="tw:flex tw:gap-2"><span aria-hidden="true">&bull;</span><span>{{ $error }}</span></div>
                    @endforeach
                </div>
            </div>
            @endif

        @if(! $cr->isEditable())
            {{-- Edit Superadmin di luar Draft (BudgetRequest::isEditableBy). --}}
            <div class="alert alert-warning py-2 mb-0" style="font-size:12.5px;">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                <strong>Edit Superadmin — Cost Request berstatus {{ $cr->status }}.</strong>
                Perubahan dicatat di log CR ("Diedit (Superadmin)") beserta nilai lama &rarr; baru.
                Batch Treasury, settlement, dan pemakaian budget membaca nominal CR secara langsung:
                mengubah rincian setelah diproses/dibayar ikut mengubah angka-angka tersebut tanpa transaksi koreksi.
            </div>
        @endif

        <form action="{{ url('/budget-request/' . $cr->id) }}" method="POST"
              enctype="multipart/form-data" id="frmCR" class="tw:flex tw:min-w-0 tw:flex-col tw:gap-5">
            @csrf
            @method('PUT')
            @if($cr->paid_at)
                {{-- Kunci periode: CR yang sudah dibayar direkap Closing menurut paid_at. --}}
                @include('partials.period-lock-field')
            @endif

            <!-- ============================================================ -->
            <!-- SECTION 1: INFORMASI UTAMA                                    -->
            <!-- ============================================================ -->
            <x-tw.card>
                <x-slot:header>
                    <div role="heading" aria-level="2" class="tw:flex tw:items-center tw:gap-2">
                        <i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> Informasi Cost Request
                    </div>
                </x-slot:header>

                <div class="tw:grid tw:grid-cols-1 tw:gap-x-8 tw:lg:grid-cols-3">

                    <!-- KOLOM KIRI -->
                    <div class="tw:min-w-0 tw:lg:col-span-2">

                        <!-- BUDGET PLAN -->
                        <div class="form-group">
                            <label class="tw:text-label tw:font-label tw:text-text-dim">Budget Plan<span class="tw:text-danger" aria-hidden="true"> *</span></label>
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
                            <div class="tw:mt-1 tw:text-caption tw:text-text-dim">
                                Sisa budget:
                                <strong id="remainingBudget" class="tw:tabular-nums tw:text-success">—</strong>
                            </div>
                            @error('budget_plan_id')<div role="alert" class="tw:mt-1 tw:text-caption tw:text-danger">{{ $message }}</div>@enderror
                        </div>

                        <!-- TIPE + BENEFICIARY -->
                        <div class="cr-type-ben tw:flex tw:gap-3">
                            <div class="form-group cr-type-col tw:shrink-0 tw:grow-0 tw:basis-[34%]">
                                <label for="selectType" class="tw:text-label tw:font-label tw:text-text-dim">Tipe Request<span class="tw:text-danger" aria-hidden="true"> *</span></label>
                                <select name="type_id" id="selectType" class="form-control @error('type_id') is-invalid @enderror" required>
                                    <option value="">— Pilih Tipe —</option>
                                    @foreach($types as $type)
                                        <option value="{{ $type->id }}" data-name="{{ strtolower($type->name) }}"
                                            {{ old('type_id', $cr->type_id) == $type->id ? 'selected' : '' }}>
                                            {{ $type->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('type_id')<div role="alert" class="tw:mt-1 tw:text-caption tw:text-danger">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group cr-ben-col tw:min-w-0 tw:flex-auto">
                                <label class="tw:text-label tw:font-label tw:text-text-dim">Penerima Dana<span class="tw:text-danger" aria-hidden="true"> *</span></label>
                                <select name="beneficiary_id" id="selectBeneficiary"
                                        class="form-control @error('beneficiary_id') is-invalid @enderror" required>
                                    <option value="">— Pilih Beneficiary —</option>
                                    @foreach($beneficiaries as $ben)
                                        {{-- Format: nama rekening (Bank - nomor rekening) + lencana Internal/External.
                                             Nickname tidak tampil tapi tetap bisa dicari (data-search). --}}
                                        @php
                                            $benLabel = $ben->complete_name . ' (' . ($ben->bank?->name ?? '-') . ' - ' . ($ben->account_number ?? '-') . ')';
                                            $benInternal = $ben->category === 'INTERNAL';
                                        @endphp
                                        <option value="{{ $ben->id }}"
                                            data-bank="{{ $ben->bank?->name ?? '-' }}"
                                            data-account="{{ $ben->account_number ?? '-' }}"
                                            data-email="{{ $ben->contact ?? '' }}"
                                            data-category="{{ $ben->category }}"
                                            data-label="{{ $benLabel }}"
                                            data-search="{{ $ben->nickname }}"
                                            data-badge="{{ $benInternal ? 'Internal' : 'External' }}"
                                            data-badge-tone="{{ $benInternal ? 'internal' : 'external' }}"
                                            {{ old('beneficiary_id', $cr->beneficiary_id) == $ben->id ? 'selected' : '' }}>
                                            {{ $benLabel }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="tw:mt-1 tw:text-caption tw:text-text-dim" id="beneficiaryInfo">
                                    @if($cr->beneficiary)
                                        <i class="fas fa-university tw:mr-1" aria-hidden="true"></i>
                                        {{ $cr->beneficiary->bank?->name ?? '-' }} — {{ $cr->beneficiary->account_number ?? '-' }}
                                    @endif
                                </div>
                                @error('beneficiary_id')<div role="alert" class="tw:mt-1 tw:text-caption tw:text-danger">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <!-- DESKRIPSI -->
                        <x-tw.input name="description" label="Deskripsi / Keperluan" required
                                    :value="old('description', $cr->description)" :error="$errors->first('description')"
                                    placeholder="Jelaskan keperluan pengajuan dana..."
                                    autocomplete="off" class="tw:uppercase" />

                    </div>

                    <!-- KOLOM KANAN -->
                    <div class="tw:min-w-0">

                        <!-- PROJECT — hanya untuk budget yang bersumber dari PO -->
                        <div class="form-group" id="clusterBox">
                            <label for="selectCluster" class="tw:text-label tw:font-label tw:text-text-dim">Project<span class="tw:text-danger" aria-hidden="true"> *</span></label>
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
                            @error('cluster_id')<div role="alert" class="tw:mt-1 tw:text-caption tw:text-danger">{{ $message }}</div>@enderror
                        </div>

                        <!-- EMAIL NOTIFIKASI -->
                        <div class="form-group">
                            <label class="tw:text-label tw:font-label tw:text-text-dim">Email Notifikasi</label>
                            <div id="emailTagsBox"
                                 class="tw:text-control tw:flex tw:min-h-ctl tw:cursor-text tw:flex-wrap tw:items-center tw:gap-2 tw:rounded-sm tw:border tw:px-2 tw:py-1 tw:focus-within:border-accent-text tw:focus-within:outline-2 tw:focus-within:outline-offset-2 tw:focus-within:outline-accent-text @error('email') tw:border-danger tw:bg-danger-bg @else tw:border-border tw:bg-surface @enderror">
                                <input type="text" id="emailTagInput"
                                       class="tw:min-w-[140px] tw:flex-auto tw:bg-transparent tw:py-1 tw:text-text tw:placeholder:text-text-faint"
                                       style="border:none;outline:none;"
                                       placeholder="Tambah email...">
                            </div>
                            <input type="hidden" name="email" id="emailHidden" value="{{ old('email', $cr->email) }}">
                            <div class="tw:mt-1 tw:text-caption tw:text-text-dim">Tekan Enter atau koma (,) untuk menambahkan email.</div>
                            @error('email')<div role="alert" class="tw:mt-1 tw:text-caption tw:text-danger">{{ $message }}</div>@enderror
                        </div>

                        <!-- NOTES -->
                        <x-tw.textarea name="notes" label="Notes" rows="2" :value="old('notes', $cr->notes)"
                                       :error="$errors->first('notes')" placeholder="Catatan tambahan..." />

                    </div>
                </div>

                <!-- NOB indicator -->
                <div id="nobWarning" role="alert" class="tw:mt-3 tw:rounded-md tw:border tw:border-warning tw:bg-warning-bg tw:p-3 tw:text-data tw:leading-body tw:text-warning" style="display:none;">
                    <i class="fas fa-exclamation-triangle tw:mr-1" aria-hidden="true"></i>
                    <strong>Perhatian:</strong> Total pengajuan melebihi sisa budget.
                    Deskripsi akan otomatis ditambahkan prefix <strong>[NOB]</strong>.
                </div>

                <!-- WORKFLOW INFO berdasarkan Tipe Request -->
                <div id="workflowAlert" class="tw:mt-3" style="display:none;"></div>
                <div id="caQuotaAlert" class="tw:mt-3" style="display:none;"></div>
            </x-tw.card>

            <!-- ============================================================ -->
            <!-- SECTION 2: RINCIAN PENGAJUAN                                  -->
            <!-- ============================================================ -->
            <x-tw.card padding="none">
                <x-slot:header>
                    <div class="cr-detail-head tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-3">
                        <div role="heading" aria-level="2" class="tw:flex tw:items-center tw:gap-2">
                            <i class="fas fa-list-ul" aria-hidden="true"></i> Rincian Pengajuan
                        </div>
                        <div class="cr-detail-actions tw:flex tw:flex-wrap tw:items-center tw:gap-2">
                            <x-tw.badge id="totalLabel" tone="primary" size="md" class="tw:tabular-nums">Total: 0</x-tw.badge>
                            <x-tw.button href="#" id="btnDownloadDetailTemplate" variant="secondary" size="sm" class="tw:max-sm:min-h-12"><i class="fas fa-file-excel" aria-hidden="true"></i> Download Template</x-tw.button>
                            <x-tw.button id="btnImportDetailExcel" variant="secondary" size="sm" class="tw:max-sm:min-h-12"><i class="fas fa-file-import" aria-hidden="true"></i> Import Excel</x-tw.button>
                            <input type="file" id="importDetailFileInput" accept=".xlsx,.xls" style="display:none;">
                            <x-tw.button id="btnAddDetail" size="sm" class="tw:max-sm:min-h-12"><i class="fas fa-plus" aria-hidden="true"></i> Tambah Baris</x-tw.button>
                        </div>
                    </div>
                </x-slot:header>

                <div class="table-responsive tw:pb-1">
                    <table class="cr-detail-table tw:w-full tw:min-w-[860px] tw:border-collapse">
                        <thead>
                            <tr>
                                <th scope="col" class="tw:w-[4%] text-center">No</th>
                                <th scope="col" class="tw:w-[25%]">Sub Item Budget</th>
                                <th scope="col" class="tw:w-[20%]">Deskripsi</th>
                                <th scope="col" class="text-right col-remaining" style="display:none;">Sisa Tersedia</th>
                                <th scope="col" class="cr-d-harga tw:w-[12%] text-right">Harga Satuan</th>
                                <th scope="col" class="cr-d-qty tw:w-[6%] text-center">Qty</th>
                                <th scope="col" class="cr-d-total tw:w-[12%] text-right">Total</th>
                                <th scope="col" class="cr-d-att tw:w-[10%] text-center">Lampiran Item <x-tw.badge id="itemAttRequiredBadge" tone="danger" style="display:none;">Wajib</x-tw.badge></th>
                                <th scope="col" class="cr-d-aksi tw:w-[5%] text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="detailBody">
                            <!-- Diisi oleh JS setelah sub item Budget Plan berhasil di-fetch -->
                        </tbody>
                    </table>
                </div>

                <x-slot:footer>
                    <i class="fas fa-info-circle tw:mr-1" aria-hidden="true"></i>
                    Data lama akan diganti seluruhnya saat disimpan.
                </x-slot:footer>
            </x-tw.card>

            <!-- ============================================================ -->
            <!-- SECTION 3: LAMPIRAN EXISTING (global/pendukung)               -->
            <!-- ============================================================ -->
            @if($cr->requestAttachments()->count() > 0)
            <x-tw.card padding="none">
                <x-slot:header>
                    <div role="heading" aria-level="2" class="tw:flex tw:items-center tw:gap-2">
                        <i class="fas fa-paperclip" aria-hidden="true"></i> Lampiran Tambahan / Pendukung (Existing)
                    </div>
                </x-slot:header>

                <div class="table-responsive tw:pb-1">
                    <table class="tw:w-full tw:border-collapse">
                        <thead>
                            <tr>
                                <th scope="col" class="tw:w-[5%] text-center">No</th>
                                <th scope="col" class="tw:w-[65%]">Nama File</th>
                                <th scope="col" class="tw:w-[15%] text-center">Preview</th>
                                <th scope="col" class="tw:w-[15%] text-center">Hapus</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cr->requestAttachments()->get() as $i => $att)
                            <tr>
                                <td class="text-center align-middle">{{ $i + 1 }}</td>
                                <td class="align-middle">
                                    <span class="tw:text-caption tw:text-text-dim">{{ $att->original_name }}</span>
                                    <span class="tw:ml-2 tw:text-caption tw:text-text-dim">({{ $att->size_formatted }})</span>
                                </td>
                                <td class="text-center align-middle">
                                    <x-tw.button href="{{ asset('storage/' . $att->path) }}" target="_blank" variant="secondary" size="sm"
                                                 class="tw:max-sm:min-h-12" title="Lihat {{ $att->original_name }}" aria-label="Lihat {{ $att->original_name }}"><i class="fas fa-eye" aria-hidden="true"></i></x-tw.button>
                                </td>
                                <td class="text-center align-middle">
                                    <input type="checkbox" name="delete_attachments[]"
                                           value="{{ $att->id }}"
                                           aria-label="Hapus {{ $att->original_name }}"
                                           class="tw:size-[18px] tw:accent-danger">
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-slot:footer>
                    <span class="tw:text-danger">
                        <i class="fas fa-exclamation-triangle tw:mr-1" aria-hidden="true"></i>
                        Centang file yang ingin dihapus, lalu klik Simpan.
                    </span>
                </x-slot:footer>
            </x-tw.card>
            @endif

            <!-- ============================================================ -->
            <!-- SECTION 4: LAMPIRAN BARU                                      -->
            <!-- ============================================================ -->
            <x-tw.card>
                <x-slot:header>
                    <div class="tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-3">
                        <div role="heading" aria-level="2" class="tw:flex tw:items-center tw:gap-2">
                            <i class="fas fa-upload" aria-hidden="true"></i> Tambah Lampiran Tambahan / Pendukung
                            <x-tw.badge id="attRequiredBadge" tone="danger" style="display:none;">Wajib</x-tw.badge>
                        </div>
                        <x-tw.button id="btnAddAttachment" size="sm" class="tw:max-sm:min-h-12"><i class="fas fa-plus" aria-hidden="true"></i> Tambah Lampiran</x-tw.button>
                    </div>
                </x-slot:header>

                <div class="tw:mb-4 tw:rounded-md tw:border tw:border-border tw:bg-surface-2 tw:p-3 tw:text-caption tw:leading-body tw:text-text-dim">
                    <i class="fas fa-info-circle tw:mr-1" aria-hidden="true"></i>
                    Bukti <strong>global / pendukung</strong> (bukan khusus satu item), contoh: foto undangan meeting.
                    Nota/bukti <strong>per item</strong> di-upload di kolom <strong>“Lampiran Item”</strong> pada tabel Rincian Pengajuan.
                    <div class="tw:mt-2">
                        <strong>Aturan:</strong> tipe <strong>Cash Advance</strong> wajib mengisi lampiran di sini
                        (Lampiran Item opsional, bukti final dilengkapi saat settlement). Tipe <strong>selain</strong>
                        Cash Advance sebaliknya — <strong>Lampiran Item wajib di setiap baris rincian</strong>,
                        lampiran di sini opsional.
                    </div>
                </div>

                <div id="attUploadArea" style="display:none;">
                    <div id="dropZoneCR" class="att-dropzone tw:mb-4" tabindex="0">
                        <i class="fas fa-cloud-upload-alt fa-2x mb-2 d-block" aria-hidden="true"></i>
                        <div class="tw:text-control tw:text-text">
                            Drag &amp; drop file ke sini, paste gambar (Ctrl+V), atau
                            <span class="tw:cursor-pointer tw:font-label tw:text-accent-text tw:underline" id="dropZoneBrowseCR" role="button" tabindex="0">klik untuk pilih</span>
                        </div>
                        <div class="tw:mt-1 tw:text-caption tw:text-text-dim">Bisa pilih/drop/paste beberapa file sekaligus</div>
                        <input type="file" id="dropZoneInputCR" multiple style="display:none;">
                    </div>
                    <div class="table-responsive" id="attTableWrap" style="display:none;">
                        <table class="tw:w-full tw:border-collapse">
                            <thead>
                                <tr>
                                    <th scope="col" class="tw:w-[5%] text-center">No</th>
                                    <th scope="col" class="tw:w-[85%]">File</th>
                                    <th scope="col" class="tw:w-[10%] text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="attachmentBody"></tbody>
                        </table>
                    </div>
                </div>

                <x-slot:footer>
                    <i class="fas fa-info-circle tw:mr-1" aria-hidden="true"></i>
                    Format: pdf, jpg, png, doc(x), xls(x). Maks 5 MB per file.
                </x-slot:footer>
            </x-tw.card>

            <!-- ACTION -->
            <x-tw.card padding="sm">
                <div class="tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-3 tw:max-sm:flex-col-reverse tw:max-sm:items-stretch">
                    <span id="saveHint" aria-live="off" class="tw:min-w-0 tw:flex-1 tw:text-caption tw:leading-body tw:text-danger" style="display:none;"></span>
                    <div class="tw:flex tw:flex-wrap tw:items-center tw:gap-2 tw:max-sm:flex-col-reverse tw:max-sm:[&>*]:w-full">
                        <x-tw.button href="{{ url('/budget-request') }}" variant="secondary" class="tw:max-sm:min-h-12"><i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali</x-tw.button>
                        <x-tw.button type="submit" id="btnSaveCR" class="tw:max-sm:min-h-12"><i class="fas fa-save" aria-hidden="true"></i> Simpan Perubahan</x-tw.button>
                    </div>
                </div>
            </x-tw.card>

        </form>

        {{-- Submit form (terpisah) --}}
        @if($cr->isDraft() && $cr->details->count() > 0)
        <x-tw.card padding="sm">
            <form action="{{ url('/budget-request/' . $cr->id . '/submit') }}"
                  method="POST" id="frmSubmit" hidden>
                @csrf
            </form>
            <div class="tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-3 tw:max-sm:flex-col tw:max-sm:items-stretch">
                <div class="tw:min-w-0">
                    <div class="tw:font-label tw:text-accent-text">Siap diajukan?</div>
                    <div class="tw:mt-1 tw:text-data tw:leading-body tw:text-text-dim">
                        Setelah submit, CR tidak dapat diedit sampai dikembalikan oleh Manager.
                    </div>
                </div>
                <x-tw.button id="btnSubmit" class="tw:max-sm:min-h-12"><i class="fas fa-paper-plane" aria-hidden="true"></i> Submit ke Manager</x-tw.button>
            </div>
        </x-tw.card>
        @endif

        </div>
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

    var CASH_ADVANCE_TYPES = ['cash advance', 'cash-advance', 'cashadvance'];
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
        /* Dikelompokkan per akun: judul akun (tebal, tidak bisa diklik) lalu
           "Deskripsi (satuan)". Sisa budget sengaja tidak ditampilkan di daftar;
           nilainya tetap ada di budgetDetails untuk sel Sisa dan aturan NOB. */
        function h(t) { return String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
        var grup = {}, urutan = [];
        budgetDetails.forEach(function (bd) {
            var akun = bd.account || '-';
            if (!grup[akun]) { grup[akun] = []; urutan.push(akun); }
            grup[akun].push(bd);
        });
        var html = '<option value="">— Pilih Sub Item —</option>';
        urutan.forEach(function (akun) {
            html += '<optgroup label="' + h(akun) + '">';
            grup[akun].forEach(function (bd) {
                var sel = (selectedId && String(selectedId) === String(bd.id)) ? ' selected' : '';
                var teks = (bd.deskripsi || '-') + ' (' + (bd.unit || '-') + ')';
                html += '<option value="' + bd.id + '"' + sel + ' data-label="' + h(akun + ' - ' + teks) + '"'
                      + ' data-item-label="' + h(teks) + '"'
                      + ' data-search="' + h(akun) + '">' + h(teks) + '</option>';
            });
            html += '</optgroup>';
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
                tag.className = 'email-tag tw:inline-flex tw:items-center tw:gap-2 tw:rounded-pill tw:border tw:border-border tw:bg-surface-2 tw:px-2 tw:py-1 tw:text-caption tw:text-text';
                tag.textContent = email;
                var x = document.createElement('i');
                x.className = 'fas fa-times tw:cursor-pointer tw:text-text-faint tw:hover:text-danger';
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

        if (beneficiaryNotInternalForCashAdvance()) {
            Swal.fire({
                icon: 'warning',
                title: 'Cash Advance hanya untuk Internal',
                text: 'Beneficiary yang dipilih bukan kategori Internal. Pilih beneficiary Internal, atau ganti Tipe Request.',
                confirmButtonColor: '#42627d'
            });
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
                '<div class="desc-wrap">' +
                    '<input type="text" name="details[' + i + '][notes]" class="form-control form-control-sm inp-notes" maxlength="255" value="' + notesVal + '" placeholder="Deskripsi..." required>' +
                    '<button type="button" class="desc-expand" title="Lihat deskripsi lengkap" tabindex="-1"><i class="fas fa-expand-alt"></i></button>' +
                '</div>' +
            '</td>' +
            '<td class="text-right cell-remaining col-remaining" style="display:none;">—</td>' +
            '<td><input type="text" name="details[' + i + '][amount]" class="form-control form-control-sm text-right inp-amount" value="' + amountVal + '" placeholder="0" required></td>' +
            '<td><input type="number" name="details[' + i + '][qty]" class="form-control form-control-sm text-center inp-qty" value="' + qtyVal + '" min="1" required></td>' +
            '<td><input type="text" name="details[' + i + '][total_display]" class="form-control form-control-sm text-right inp-total" value="0" readonly style="background:var(--surface-2);"></td>' +
            '<td class="align-middle detail-att-cell" data-idx="' + i + '" tabindex="0">' +
                '<input type="file" name="details[' + i + '][attachments][]" class="d-none detail-att-input" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">' +
                '<button type="button" class="btn-detail-att detail-att-drop">' +
                    '<i class="fas fa-cloud-upload-alt" aria-hidden="true"></i><span class="detail-att-label">Lampiran</span></button>' +
                (existAtt ? '<div class="mt-1" style="font-size:10px;line-height:1.5;">' + existAtt + '</div>' : '') +
                '<div class="detail-att-list mt-1" style="font-size:10px;line-height:1.5;"></div>' +
            '</td>' +
            '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-detail" title="Hapus baris" aria-label="Hapus baris"><i class="fas fa-trash" aria-hidden="true"></i></button></td>' +
        '</tr>';
    }

    // ── LAMPIRAN PER ITEM (drag & drop + paste + klik) ─────────────────────

    /* ── SISA KARAKTER DESKRIPSI ─────────────────────────────────────────── */
    var DESC_MAX = 255;

    function refreshDescCount(el) {
        var $info = $(el).closest('.desc-wrap').find('.desc-count');
        if (!$info.length) return;
        var sisa = DESC_MAX - (el.value || '').length;
        $info.text(sisa <= 0
                ? 'Batas ' + DESC_MAX + ' karakter sudah habis — tidak bisa menambah teks lagi.'
                : 'Sisa ' + sisa + ' karakter.')
             .toggleClass('text-danger', sisa <= 0)
             .toggleClass('text-muted', sisa > 0);
    }

    $(document).on('input', '.inp-notes', function () { refreshDescCount(this); });
    $(document).on('click', '#btnAddDetail', function () {
        setTimeout(function () { $('.inp-notes').each(function () { refreshDescCount(this); }); }, 0);
    });
    // Baris juga dirender ulang saat Budget Plan diganti / data awal dimuat.
    $(document).on('change', '#selectBudgetPlan', function () {
        setTimeout(function () { $('.inp-notes').each(function () { refreshDescCount(this); }); }, 400);
    });
    setTimeout(function () { $('.inp-notes').each(function () { refreshDescCount(this); }); }, 300);

    /* ── LAMPIRAN: video ditolak, file bisa dibuka untuk dicek ───────────── */
    var VIDEO_EXT = ['mp4','mov','avi','mkv','wmv','flv','webm','m4v','3gp','mpg','mpeg','ts','m2ts','ogv'];

    function isVideoFile(f) {
        if (!f) return false;
        if ((f.type || '').toLowerCase().indexOf('video/') === 0) return true;
        return VIDEO_EXT.indexOf((f.name || '').split('.').pop().toLowerCase()) !== -1;
    }

    /** Buang file video; kalau ada, tampilkan pop-up. */
    function tolakVideo(fileList) {
        var ok = [], tolak = [];
        Array.from(fileList || []).forEach(function (f) { (isVideoFile(f) ? tolak : ok).push(f); });

        if (tolak.length) {
            Swal.fire({
                icon: 'error',
                title: 'Lampiran tidak boleh video',
                html: 'File berikut tidak bisa dilampirkan:<br><strong>'
                    + tolak.map(function (f) { return $('<div>').text(f.name).html(); }).join('<br>')
                    + '</strong><br><br>Lampirkan foto (JPG/PNG) atau dokumen (PDF, DOC, XLS) saja.',
                confirmButtonColor: '#92232b'
            });
        }
        return ok;
    }

    /** Pratinjau file yang belum terunggah — pop-up, bukan tab baru. */
    function bukaFileLokal(file) {
        if (!file) return;

        var url   = URL.createObjectURL(file);
        var nama  = $('<div>').text(file.name).html();
        var ext   = (file.name || '').split('.').pop().toLowerCase();
        var isImg = (file.type || '').indexOf('image/') === 0 || ['jpg','jpeg','png','gif','webp','bmp'].indexOf(ext) !== -1;
        var isPdf = (file.type || '') === 'application/pdf' || ext === 'pdf';

        var isi;
        if (isImg) {
            isi = '<img src="' + url + '" alt="' + nama + '" style="max-width:100%;max-height:70vh;object-fit:contain;border-radius:6px;">';
        } else if (isPdf) {
            isi = '<iframe src="' + url + '" style="width:100%;height:70vh;border:1px solid var(--border);border-radius:6px;"></iframe>';
        } else {
            isi = '<p class="mb-2">Pratinjau tidak tersedia untuk jenis file ini.</p>'
                + '<a href="' + url + '" download="' + nama + '" class="btn btn-sm btn-primary">'
                + '<i class="fas fa-download mr-1"></i> Unduh untuk memeriksa</a>';
        }

        Swal.fire({
            title: nama, html: isi,
            width: isImg || isPdf ? '860px' : '520px',
            showConfirmButton: false, showCloseButton: true,
            customClass: { closeButton: 'att-close-btn' },
            didClose: function () { URL.revokeObjectURL(url); }
        });
    }

    // Klik nama file pada Lampiran Item -> pratinjau.
    document.addEventListener('click', function (e) {
        var a = e.target.closest('.detail-att-open');
        if (!a) return;
        e.preventDefault();
        var input = a.closest('.detail-att-cell').querySelector('.detail-att-input');
        bukaFileLokal((input.files || [])[parseInt(a.dataset.i, 10)]);
    });

    // Klik nama file pada Lampiran Tambahan (global) -> pratinjau.
    document.addEventListener('click', function (e) {
        var a = e.target.closest('.att-global-open');
        if (!a) return;
        e.preventDefault();
        var input = a.closest('td').querySelector('input[type="file"]');
        bukaFileLokal((input.files || [])[0]);
    });

    // Baris lampiran global: tolak video & tampilkan tautan pratinjau.
    document.addEventListener('change', function (e) {
        var input = e.target.closest('#attachmentBody input[type="file"]');
        if (!input) return;

        if ((input.files || []).length && !tolakVideo(input.files).length) {
            input.value = '';
        }

        renderGlobalAttPreview(input);
    });

    function appendFilesToInput(input, fileList) {
        fileList = tolakVideo(fileList);
        if (!fileList.length) return;
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
                '<a href="#" class="detail-att-open" data-i="' + idx + '" title="Lihat lampiran">' +
                f.name.replace(/</g, '&lt;') + '</a>' +
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
            refreshAttTable();
            if (window.__updateSubmitState) window.__updateSubmitState();
        }
    });

    // Attachment rows (+ drag & drop multi-file)
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


    /** Nama file pada baris Lampiran Tambahan — bisa diklik untuk diperiksa. */
    function renderGlobalAttPreview(input) {
        if (!input) return;
        var box = input.parentElement.querySelector('.att-global-preview');
        if (!box) return;

        var f = (input.files || [])[0];
        box.innerHTML = f
            ? '<i class="fas fa-file mr-1"></i><a href="#" class="att-global-open" title="Lihat lampiran">'
                + $('<div>').text(f.name).html() + '</a>'
            : '';
    }

    function addAttachmentRow(file) {
        showAttUploadArea();
        var i     = attIdx++;
        var tbody = document.getElementById('attachmentBody');
        var tr    = document.createElement('tr');
        tr.innerHTML =
            '<td class="text-center align-middle row-no"></td>' +
            '<td><input type="file" name="attachments[' + i + '][file]" class="form-control-file">' +
                '<div class="att-global-preview mt-1" style="font-size:11px;"></div></td>' +
            '<td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-remove-att" title="Hapus lampiran" aria-label="Hapus lampiran"><i class="fas fa-trash" aria-hidden="true"></i></button></td>';
        tbody.appendChild(tr);
        refreshAttTable();

        if (file) {
            var input = tr.querySelector('input[type="file"]');
            try {
                var dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
            } catch (err) {
                // Browser lama tanpa dukungan DataTransfer — baris tetap dibuat kosong.
            }

            // File dari drop/paste tidak memicu 'change', jadi tautan
            // pratinjaunya dirender langsung di sini.
            renderGlobalAttPreview(input);
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

    (function () {
        var zone   = document.getElementById('dropZoneCR');
        var input  = document.getElementById('dropZoneInputCR');
        var browse = document.getElementById('dropZoneBrowseCR');

        zone.addEventListener('click', function () { input.click(); });
        browse.addEventListener('click', function (e) {
            e.stopPropagation();
            input.click();
        });
        // role="button" harus bisa diaktifkan lewat papan ketik (Enter / Spasi).
        browse.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                e.stopPropagation();
                input.click();
            }
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
    // ── ATURAN LAMPIRAN ────────────────────────────────────────────────────
    //  Cash Advance : Lampiran Tambahan (global) WAJIB, Lampiran Item opsional.
    //  Tipe lain    : Lampiran Item WAJIB di tiap baris rincian, global opsional.
    function typeIsCashAdvance() {
        var sel = document.getElementById('selectType');
        var opt = sel.options[sel.selectedIndex];
        var name = opt ? (opt.dataset.name || '') : '';
        return CASH_ADVANCE_TYPES.indexOf(name) !== -1;
    }

    /** Cash Advance hanya boleh ke beneficiary kategori INTERNAL. */
    function beneficiaryNotInternalForCashAdvance() {
        if (!typeIsCashAdvance()) return false;
        var sel = document.getElementById('selectBeneficiary');
        var opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value) return false;
        return (opt.dataset.category || '') !== 'INTERNAL';
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
        if (gBadge) gBadge.style.display = ca ? '' : 'none';
        if (iBadge) iBadge.style.display = ca ? 'none' : '';
    }

    document.getElementById('selectType').addEventListener('change', refreshAttachmentRequiredUI);
    refreshAttachmentRequiredUI();

    document.getElementById('selectType').addEventListener('change', function () {
        if (beneficiaryNotInternalForCashAdvance()) {
            Swal.fire({
                icon: 'warning',
                title: 'Cash Advance hanya untuk Internal',
                text: 'Beneficiary yang sudah dipilih bukan kategori Internal. Pilih beneficiary Internal, atau ganti Tipe Request.',
                confirmButtonColor: '#42627d'
            });
        }
    });

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
        var cls = q.level === 'blocked' ? 'tw:border-danger tw:bg-danger-bg tw:text-danger'
                : (q.level === 'warning' ? 'tw:border-warning tw:bg-warning-bg tw:text-warning' : 'tw:border-info tw:bg-info-bg tw:text-info');
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

        box.className = 'tw:mt-3 tw:rounded-md tw:border tw:p-3 tw:text-data tw:leading-body ' + cls;
        box.innerHTML = '<i class="fas ' + icon + ' mr-1"></i> ' + head + bagian +
            ' <br><small>Jumlah pengajuan dihitung sejak CR berstatus Paid; limit (jumlah maupun nominal) pulih setelah pengajuan lewat Second Approve.</small>';
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
                  '&cr_id=' + encodeURIComponent({{ $cr->id }} || '');

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
            box.className = 'tw:mt-3 tw:rounded-md tw:border tw:border-info tw:bg-info-bg tw:p-3 tw:text-data tw:leading-body tw:text-info';
            box.innerHTML = '<i class="fas fa-route mr-1"></i> <strong>Cash Advance</strong> — workflow: ' +
                '<strong>Process &rarr; Paid &rarr; Settle &rarr; Closed</strong>. ' +
                'Ada tahap realisasi (settlement) setelah dana cair, jadi bukti final dilengkapi saat settle.';
        } else {
            box.className = 'tw:mt-3 tw:rounded-md tw:border tw:border-warning tw:bg-warning-bg tw:p-3 tw:text-data tw:leading-body tw:text-warning';
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
        if (beneficiaryNotInternalForCashAdvance()) reasons.push('Cash Advance hanya untuk Internal');
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


    /* ── LIHAT DESKRIPSI PENUH ─────────────────────────────────────────────
       Kolom deskripsi sempit dan teksnya sering terpotong. Ikon di ujung
       kanan kotak membuka pop-up berisi teks utuh; klik di mana saja untuk
       menutup. Isinya hanya dibaca — penyuntingan tetap di kotaknya. */
    $(document).on('click', '.desc-expand', function () {
        var $inp = $(this).closest('.desc-wrap').find('.inp-notes');
        var isi  = $inp.val() || '';

        Swal.fire({
            title: 'Deskripsi',
            html: '<textarea id="descPopupInput" class="form-control" rows="6" maxlength="255" '
                + 'placeholder="Deskripsi..." '
                + 'style="font-size:13px;width:100%;resize:vertical;">'
                + $('<div>').text(isi).html() + '</textarea>'
                + '<small id="descPopupCount" class="d-block mt-1" style="font-size:11px;text-align:left;"></small>',
            width: '560px',
            showConfirmButton: false,
            showCloseButton: true,
            customClass: { closeButton: 'att-close-btn' },
            didOpen: function (popup) {
                var ta  = popup.querySelector('#descPopupInput');
                var inf = popup.querySelector('#descPopupCount');
                if (!ta) return;

                // Penghitung sisa karakter, sama seperti kolom Description Settle.
                function hitung() {
                    var sisa = 255 - ta.value.length;
                    inf.textContent = sisa <= 0
                        ? 'Batas 255 karakter sudah habis — tidak bisa menambah teks lagi.'
                        : 'Sisa ' + sisa + ' karakter.';
                    inf.className = 'd-block mt-1 ' + (sisa <= 0 ? 'text-danger' : 'text-muted');
                }

                ta.addEventListener('input', hitung);
                hitung();

                ta.focus();
                ta.setSelectionRange(ta.value.length, ta.value.length);
            },
            /* Ditulis balik ke kotak aslinya saat pop-up ditutup — dengan cara
               apa pun (X, Esc, klik di luar) — supaya tidak ada hasil ketikan
               yang hilang. Event input/change dipicu agar validasi & keadaan
               tombol Simpan ikut menyesuaikan. */
            willClose: function (popup) {
                var ta = popup.querySelector('#descPopupInput');
                if (ta) $inp.val(ta.value).trigger('input').trigger('change');
            }
        });
    });

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
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
<style>

/* Kotak deskripsi + ikon "lihat penuh" di ujung kanannya. */
.desc-wrap { position: relative; }
/* Ruang untuk ikonnya, DAN latar penuh di belakangnya — tanpa itu teks yang
   panjang lewat di bawah ikon sehingga ikonnya sulit dikenali/diklik. */
.desc-wrap .inp-notes { padding-right: 30px; }
.desc-wrap .desc-expand {
    position: absolute; top: 2px; right: 2px; bottom: 2px; width: 26px;
    display: flex; align-items: center; justify-content: center;
    background: var(--surface); border: none; border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
    line-height: 1; color: var(--text-faint); cursor: pointer; font-size: 11px; z-index: 2;
}
.desc-wrap .desc-expand:hover { color: var(--accent); }
/* Sisa karakter digantung DI LUAR alur (absolute) supaya tinggi selnya tidak
   berubah — kalau ikut alur, kotak Deskripsi terdorong naik dan tidak lagi
   sejajar dengan Harga Satuan/Qty di baris yang sama. */
.desc-wrap .desc-count {
    position: absolute; top: 100%; left: 0; margin-top: 2px;
    font-size: 10px; line-height: 1.2; white-space: nowrap;
}

/* Select native (Tipe, Project) setinggi kontrol lain di form ini (kotak searchable & input tw = --ctl-h). */
select#selectType.form-control, select#selectCluster.form-control { height: var(--ctl-h); }

/* Tombol X pop-up: tanpa kotak, merah. */
.swal2-close.att-close-btn { color: var(--danger); background: transparent; box-shadow: none; border: none; outline: none; }
.swal2-close.att-close-btn:hover, .swal2-close.att-close-btn:focus { color: var(--danger); background: transparent; box-shadow: none; outline: none; }
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
    color: var(--text-dim);
    cursor: pointer;
    background: var(--surface-2);
}
.att-dropzone:hover {
    border-color: var(--accent);
    background: var(--accent-tint);
}
.att-dropzone.dragover {
    border-color: var(--accent);
    background: var(--accent-tint);
    color: var(--accent-dark);
}
</style>
@endpush