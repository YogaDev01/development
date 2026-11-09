{{-- Isian form PIC — dipakai modal Tambah & Edit di halaman PIC & Beneficiary.
     $p = prefix id elemen (tambahPic / editPic); $withStatus = tampilkan Status. --}}
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label class="font-weight-600" for="{{ $p }}Type">Type <span class="text-danger">*</span></label>
            <select name="type" id="{{ $p }}Type" class="form-control" required>
                @foreach(\App\Models\MasterPic::TYPES as $val => $label)
                    <option value="{{ $val }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-md-8">
        <div class="form-group">
            <label class="font-weight-600" for="{{ $p }}Pic">Nama PIC <span class="text-danger">*</span></label>
            <input type="text" name="pic" id="{{ $p }}Pic" class="form-control" placeholder="Masukkan nama lengkap"
                   style="text-transform:uppercase;" autocomplete="off" required>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="font-weight-600" for="{{ $p }}Position">Jabatan</label>
            <input type="text" name="position" id="{{ $p }}Position" class="form-control" placeholder="Masukkan jabatan" autocomplete="off">
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="font-weight-600" for="{{ $p }}Phone">Phone</label>
            <input type="text" name="phone" id="{{ $p }}Phone" class="form-control" placeholder="Masukkan nomor telepon" autocomplete="off">
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="font-weight-600" for="{{ $p }}Email">Email</label>
            <input type="email" name="email" id="{{ $p }}Email" class="form-control" placeholder="Masukkan email" autocomplete="off">
        </div>
    </div>
    {{-- Token & Nominal hanya untuk tipe internal (disembunyikan & dikosongkan JS untuk tipe lain). --}}
    <div class="col-md-6 pic-internal-only">
        <div class="form-group">
            <label class="font-weight-600" for="{{ $p }}Token">Token</label>
            <input type="text" name="token" id="{{ $p }}Token" class="form-control" maxlength="100" placeholder="Masukkan token" autocomplete="off">
        </div>
    </div>
    <div class="col-md-6 pic-internal-only">
        <div class="form-group">
            <label class="font-weight-600" for="{{ $p }}Nominal">Nominal</label>
            <input type="text" name="nominal" id="{{ $p }}Nominal" class="form-control text-right money-input"
                   inputmode="decimal" placeholder="0" autocomplete="off">
        </div>
    </div>
    {{-- M-02 — hubungkan PIC internal ke orang sebenarnya (opsional). --}}
    <div class="col-md-6">
        <div class="form-group">
            <label class="font-weight-600" for="{{ $p }}User">Akun User</label>
            <select name="user_id" id="{{ $p }}User" class="form-control">
                <option value="">— Tidak terhubung —</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}">{{ strtoupper($tanpaDummy($u->username)) }} — {{ $tanpaDummy($u->name) }}</option>
                @endforeach
            </select>
            <small class="form-text text-muted">Isi untuk PIC internal supaya datanya menyatu dengan Team.</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="font-weight-600" for="{{ $p }}Employee">Data Karyawan</label>
            <select name="employee_id" id="{{ $p }}Employee" class="form-control">
                <option value="">— Tidak terhubung —</option>
                @foreach($employees as $e)
                    <option value="{{ $e->id }}">{{ $tanpaDummy($e->name) }} ({{ $e->employee_number }})</option>
                @endforeach
            </select>
        </div>
    </div>
    @if($withStatus)
    <div class="col-md-6">
        <div class="form-group mb-0">
            <label class="font-weight-600" for="{{ $p }}Status">Status <span class="text-danger">*</span></label>
            <select name="status" id="{{ $p }}Status" class="form-control" required>
                <option value="ACTIVE">ACTIVE</option>
                <option value="INACTIVE">INACTIVE</option>
            </select>
        </div>
    </div>
    @endif
</div>
