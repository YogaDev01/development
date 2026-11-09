{{-- Isian form Beneficiary — dipakai modal Tambah & Edit di halaman PIC &
     Beneficiary. $p = prefix id elemen (tambahBen / editBen); $withStatus =
     tampilkan Status. --}}
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label class="font-weight-600" for="{{ $p }}Nickname">Nickname <span class="text-danger">*</span></label>
            <input type="text" name="nickname" id="{{ $p }}Nickname" class="form-control" placeholder="Masukkan nickname"
                   style="text-transform:uppercase;" autocomplete="off" required>
        </div>
    </div>
    <div class="col-md-8">
        <div class="form-group">
            <label class="font-weight-600" for="{{ $p }}CompleteName">Complete Name <span class="text-danger">*</span></label>
            <input type="text" name="complete_name" id="{{ $p }}CompleteName" class="form-control" placeholder="Masukkan nama lengkap"
                   style="text-transform:uppercase;" autocomplete="off" required>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="font-weight-600" for="{{ $p }}Category">Kategori <span class="text-danger">*</span></label>
            <select name="category" id="{{ $p }}Category" class="form-control" required>
                @foreach(\App\Models\Beneficiary::CATEGORIES as $cat)
                    <option value="{{ $cat }}" @selected($cat === 'EXTERNAL')>{{ $cat }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="font-weight-600" for="{{ $p }}Pic">PIC
                @unless($withStatus)<span class="text-danger" id="{{ $p }}PicReq" style="display:none;">*</span>@endunless
            </label>
            <select name="pic_id" id="{{ $p }}Pic" class="form-control">
                <option value="">— Pilih PIC —</option>
                {{-- Form Edit memuat SEMUA PIC (termasuk INACTIVE): kalau PIC saat ini
                     tidak ada di pilihan, menyimpan akan diam-diam mengosongkan pic_id. --}}
                @foreach(($withStatus ? $pics : $picOptions) as $po)
                    <option value="{{ $po->id }}">{{ $po->pic }}@if($po->position) — {{ $po->position }}@endif @if($po->status !== 'ACTIVE')(INACTIVE)@endif</option>
                @endforeach
            </select>
            @unless($withStatus)
                <small class="form-text text-muted">Wajib untuk kategori INTERNAL.</small>
            @endunless
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="font-weight-600" for="{{ $p }}Bank">Bank</label>
            <select name="bank_id" id="{{ $p }}Bank" class="form-control">
                <option value="">— Pilih Bank —</option>
                @foreach($banks as $bank)
                    <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="font-weight-600" for="{{ $p }}AccountNumber">Account Number</label>
            <input type="text" name="account_number" id="{{ $p }}AccountNumber" class="form-control"
                   placeholder="Masukkan nomor rekening" autocomplete="off">
        </div>
    </div>
    {{-- Kolom `contact` = nomor telepon / kontak (tabel ini tidak punya email). --}}
    <div class="col-md-6">
        <div class="form-group mb-0">
            <label class="font-weight-600" for="{{ $p }}Contact">Kontak</label>
            <input type="text" name="contact" id="{{ $p }}Contact" class="form-control" placeholder="Nomor telepon / kontak" autocomplete="off">
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
