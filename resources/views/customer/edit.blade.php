@extends('layouts.app')

@section('title', 'Customer')

@section('content')

@php
    // Baris upload lampiran BARU (existing ditampilkan terpisah di bawah).
    $newAttachments = old('attachments', [[]]);
@endphp

<!-- CONTENT HEADER -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <div class="d-flex align-items-center">
                    <a href="{{ url('/customer') }}" class="btn btn-secondary btn-sm mr-3">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="m-0 font-weight-bold">Edit Customer</h1>
                        <small class="text-muted">Form perubahan data customer</small>
                    </div>
                </div>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right mb-0">
                    <li class="breadcrumb-item"><a href="#">Master</a></li>
                    <li class="breadcrumb-item"><a href="{{ url('/customer') }}">Customer</a></li>
                    <li class="breadcrumb-item active">Edit</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="content">
    <div class="container-fluid">

        <form action="{{ url('/customer/' . $customer->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            {{-- ============================ DATA UTAMA ============================ --}}
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-user-edit mr-1"></i> Form Edit Customer</h3>
                </div>
                <div class="card-body">
                    <div class="row">

                        <!-- LEFT -->
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Nama Customer</label>
                                <input type="text" name="name"
                                    class="form-control @error('name') is-invalid @enderror"
                                    autocomplete="off" value="{{ old('name', $customer->name) }}"
                                    placeholder="Masukkan nama customer..."
                                    style="text-transform: uppercase;" required>
                                @error('name')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>
                            <div class="form-group mb-0">
                                <label>Alamat</label>
                                <textarea name="address" rows="7"
                                    class="form-control @error('address') is-invalid @enderror"
                                    autocomplete="off"
                                    placeholder="Masukkan alamat customer...">{{ old('address', $customer->address) }}</textarea>
                                @error('address')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>
                        </div>

                        <!-- RIGHT -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Type</label>
                                <select name="customer_type_id"
                                    class="form-control @error('customer_type_id') is-invalid @enderror" required>
                                    <option value="">Pilih Type</option>
                                    @foreach($types as $type)
                                        <option value="{{ $type->id }}"
                                            {{ old('customer_type_id', $customer->customer_type_id) == $type->id ? 'selected' : '' }}>
                                            {{ $type->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('customer_type_id')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>
                            <div class="form-group">
                                <label>Initial <small class="text-muted">(3 huruf, untuk nomor invoice)</small></label>
                                <input type="text" name="initial" id="inpInitial"
                                    class="form-control @error('initial') is-invalid @enderror"
                                    autocomplete="off" value="{{ old('initial', $customer->initial) }}" maxlength="3"
                                    style="text-transform:uppercase;" placeholder="mis. KPA">
                                @error('initial')<small class="text-danger">{{ $message }}</small>@enderror
                                <small class="text-muted">Terisi otomatis dari nama, bisa diedit manual.</small>
                            </div>
                            <div class="form-group">
                                <label>NPWP</label>
                                <input type="text" name="npwp"
                                    class="form-control @error('npwp') is-invalid @enderror"
                                    autocomplete="off" value="{{ old('npwp', $customer->npwp) }}"
                                    placeholder="Masukkan NPWP...">
                                @error('npwp')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>
                            <div class="form-group mb-0">
                                <label>Status</label>
                                <select name="status"
                                    class="form-control @error('status') is-invalid @enderror" required>
                                    <option value="ACTIVE" {{ old('status', $customer->status) == 'ACTIVE' ? 'selected' : '' }}>ACTIVE</option>
                                    <option value="INACTIVE" {{ old('status', $customer->status) == 'INACTIVE' ? 'selected' : '' }}>INACTIVE</option>
                                </select>
                                @error('status')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            {{-- ============================ KONTAK (PIC) ============================ --}}
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-address-book mr-1"></i> Kontak (PIC)</h3>
                </div>
                <div class="card-body">
                    @php
                        $selectedPicIds = old('pics', $customer->pics->pluck('id')->all());
                    @endphp
                    <div class="form-group mb-0">
                        <label class="font-weight-600">PIC Customer</label>
                        <select name="pics[]" id="selPics" multiple placeholder="Pilih PIC...">
                            @foreach($picsCustomer as $p)
                                <option value="{{ $p->id }}" {{ collect($selectedPicIds)->contains($p->id) ? 'selected' : '' }}>
                                    {{ $p->pic }}@if($p->position) — {{ $p->position }}@endif
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted d-block mt-1">
                            PIC diambil dari Master PIC. Belum ada di daftar?
                            <a href="{{ url('/master-pic') }}" target="_blank">Tambah di Master PIC</a>.
                        </small>
                    </div>
                </div>
            </div>

            {{-- ============================ LAMPIRAN ============================ --}}
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title"><i class="fas fa-paperclip mr-1"></i> Lampiran</h3>
                    <button type="button" class="btn btn-sm btn-primary ml-auto" id="addAttachment">
                        <i class="fas fa-plus mr-1"></i> Tambah Lampiran
                    </button>
                </div>
                <div class="card-body">

                    {{-- EXISTING --}}
                    @if($customer->attachments->isNotEmpty())
                    <h6 class="font-weight-bold text-muted mb-2">Lampiran tersimpan</h6>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width:5%">No</th>
                                    <th style="width:30%">Judul</th>
                                    <th>File</th>
                                    <th style="width:12%" class="text-center">Hapus</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($customer->attachments as $i => $att)
                                <tr>
                                    <td class="text-center align-middle">{{ $i + 1 }}</td>
                                    <td class="align-middle">{{ $att->title ?? '-' }}</td>
                                    <td class="align-middle">
                                        <a href="{{ asset('storage/' . $att->file_path) }}" target="_blank">
                                            <i class="fas fa-file mr-1"></i>{{ $att->file_name ?? 'Lihat file' }}
                                        </a>
                                    </td>
                                    <td class="text-center align-middle">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input"
                                                id="del_att_{{ $att->id }}"
                                                name="delete_attachments[]" value="{{ $att->id }}">
                                            <label class="custom-control-label text-danger" for="del_att_{{ $att->id }}">Hapus</label>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif

                    {{-- NEW UPLOAD --}}
                    <h6 class="font-weight-bold text-muted mb-2">Tambah lampiran baru</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" id="attachmentTable">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width:5%">No</th>
                                    <th style="width:35%">Judul</th>
                                    <th>File</th>
                                    <th style="width:5%" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="attachmentBody" data-next-index="{{ count($newAttachments) }}">
                                @foreach($newAttachments as $i => $a)
                                <tr>
                                    <td class="row-no text-center align-middle">{{ $i + 1 }}</td>
                                    <td><input type="text" name="attachments[{{ $i }}][title]" class="form-control form-control-sm" value="{{ $a['title'] ?? '' }}" placeholder="mis. NPWP, Akta, SIUP"></td>
                                    <td>
                                        <input type="file" name="attachments[{{ $i }}][file]" class="form-control-file @error('attachments.'.$i.'.file') is-invalid @enderror">
                                        @error('attachments.'.$i.'.file')<small class="text-danger d-block">{{ $message }}</small>@enderror
                                    </td>
                                    <td class="text-center align-middle">
                                        <button type="button" class="btn btn-sm btn-danger js-remove-attachment"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <small class="text-muted">Format: pdf, jpg, png, doc(x), xls(x). Maks 5 MB per file.</small>
                </div>
            </div>

            {{-- ============================ AKSI ============================ --}}
            <div class="card shadow-sm">
                <div class="card-body d-flex">
                    <a href="{{ url('/customer') }}" class="btn btn-secondary mr-2">
                        <i class="fas fa-arrow-left mr-1"></i> Kembali
                    </a>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save mr-1"></i> Update Customer
                    </button>
                </div>
            </div>

        </form>

    </div>
</div>

@endsection

@push('scripts')
<script>
$(function () {

    let attachmentIndex = parseInt(document.getElementById('attachmentBody').dataset.nextIndex, 10) || 0;

    // ── Auto-suggest Initial dari Nama Customer (hanya kalau field masih kosong) ──
    var initialEditedManually = $('#inpInitial').val().trim() !== '';
    var suggestTimer = null;

    $('#inpInitial').on('input', function () { initialEditedManually = true; });

    $('input[name="name"]').on('input', function () {
        if (initialEditedManually) return;
        var name = this.value;
        clearTimeout(suggestTimer);
        if (!name.trim()) { return; }

        suggestTimer = setTimeout(function () {
            $.get('{{ url("/customer/suggest-initial") }}', { name: name }, function (res) {
                if (!initialEditedManually && res.initial) {
                    $('#inpInitial').val(res.initial);
                }
            });
        }, 400);
    });

    function renumber(tbodyId) {
        $('#' + tbodyId + ' .row-no').each(function (i) { $(this).text(i + 1); });
    }

    // ---- PIC — multi-select dari Master PIC ----
    new TomSelect('#selPics', { plugins: ['remove_button'], persist: false, create: false, placeholder: 'Pilih PIC...' });

    $('#addAttachment').on('click', function () {
        const i = attachmentIndex++;
        $('#attachmentBody').append(`
            <tr>
                <td class="row-no text-center align-middle"></td>
                <td><input type="text" name="attachments[${i}][title]" class="form-control form-control-sm" placeholder="mis. NPWP, Akta, SIUP"></td>
                <td><input type="file" name="attachments[${i}][file]" class="form-control-file"></td>
                <td class="text-center align-middle">
                    <button type="button" class="btn btn-sm btn-danger js-remove-attachment"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`);
        renumber('attachmentBody');
    });

    $('#attachmentBody').on('click', '.js-remove-attachment', function () {
        $(this).closest('tr').remove();
        renumber('attachmentBody');
    });

});
</script>
@endpush