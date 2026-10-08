{{-- Field form tugas, dipakai modal Buat & Ubah. Tanpa atribut id (dua modal
     memakai partial yang sama); modal Ubah mengisi nilainya lewat JS by name.
     Variabel: $assignees (User aktif), $edit (bool, opsional). --}}
@php $edit = $edit ?? false; @endphp

<div class="form-group">
    <label class="font-weight-bold" style="font-size:13px;">Judul <span class="text-danger">*</span></label>
    <input type="text" name="title" class="form-control form-control-sm" maxlength="255"
           value="{{ $edit ? '' : old('title') }}" required>
</div>

<div class="form-group">
    <label class="font-weight-bold" style="font-size:13px;">Deskripsi</label>
    <textarea name="description" class="form-control form-control-sm" rows="3" maxlength="5000">{{ $edit ? '' : old('description') }}</textarea>
</div>

<div class="form-row">
    <div class="form-group col-md-6">
        <label class="font-weight-bold" style="font-size:13px;">Tugaskan ke <span class="text-danger">*</span></label>
        <select name="assignee_id" class="form-control form-control-sm js-assignee-select" required>
            <option value="">— Pilih user —</option>
            @foreach ($assignees as $u)
                <option value="{{ $u->id }}" @selected(!$edit && old('assignee_id') == $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group col-md-3">
        <label class="font-weight-bold" style="font-size:13px;">Prioritas <span class="text-danger">*</span></label>
        <select name="priority" class="form-control form-control-sm" required>
            <option value="LOW"    @selected(!$edit && old('priority') === 'LOW')>Rendah</option>
            <option value="MEDIUM" @selected($edit || old('priority', 'MEDIUM') === 'MEDIUM')>Sedang</option>
            <option value="HIGH"   @selected(!$edit && old('priority') === 'HIGH')>Tinggi</option>
        </select>
    </div>
    <div class="form-group col-md-3">
        <label class="font-weight-bold" style="font-size:13px;">Tenggat</label>
        <input type="date" name="due_date" class="form-control form-control-sm" value="{{ $edit ? '' : old('due_date') }}">
    </div>
</div>

<div class="form-group mb-0">
    <label class="font-weight-bold" style="font-size:13px;">Lampiran</label>
    <div class="assignment-dropzone" data-dropzone>
        <input type="file" name="attachments[]" class="d-none" multiple
               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
        <div class="dz-prompt">
            <i class="fas fa-cloud-upload-alt mr-1"></i>
            Seret &amp; lepas berkas ke sini, tempel gambar (Ctrl+V), atau
            <a href="#" class="dz-browse">pilih berkas</a>
        </div>
        <ul class="dz-list list-unstyled text-left mb-0"></ul>
    </div>
    <small class="text-muted">PDF / gambar / dokumen Office, maks 5 MB per berkas. @if($edit) Menambah lampiran tidak menghapus yang lama. @endif</small>
</div>
