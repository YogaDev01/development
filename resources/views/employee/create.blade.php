@extends('layouts.app')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ url('/employee') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Tambah Karyawan</h1>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="{{ url('/employee') }}">Karyawan</a></li>
                <li class="breadcrumb-item active">Tambah</li>
            </ol>
        </div>

        <form action="{{ url('/employee') }}" method="POST" id="formEmployee">
            @csrf

            {{-- IDENTITAS --}}
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold text-dark">
                        <i class="fas fa-id-card mr-2" style="color:var(--accent);"></i> Identitas Karyawan
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-600">No Induk</label>
                                <input type="text" name="employee_number" class="form-control @error('employee_number') is-invalid @enderror"
                                       value="{{ old('employee_number') }}" placeholder="mis. EMP-001">
                                @error('employee_number')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label class="font-weight-600">Nama Karyawan <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name') }}" style="text-transform:uppercase;" required>
                                @error('name')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="font-weight-600">Jenis Kelamin</label>
                                <select name="gender" class="form-control">
                                    <option value="">— Pilih —</option>
                                    <option value="L" {{ old('gender') === 'L' ? 'selected' : '' }}>Laki-laki</option>
                                    <option value="P" {{ old('gender') === 'P' ? 'selected' : '' }}>Perempuan</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="font-weight-600">Tempat Lahir</label>
                                <input type="text" name="birth_place" class="form-control" value="{{ old('birth_place') }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="font-weight-600">Tanggal Lahir</label>
                                <input type="date" name="birth_date" class="form-control" value="{{ old('birth_date') }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="font-weight-600">NIK</label>
                                <input type="text" name="nik" class="form-control" value="{{ old('nik') }}" placeholder="No. KTP">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">No Telepon</label>
                                <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Email</label>
                                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                       value="{{ old('email') }}">
                                @error('email')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group mb-md-0">
                                <label class="font-weight-600">Alamat</label>
                                <textarea name="address" class="form-control" rows="2">{{ old('address') }}</textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label class="font-weight-600">Domisili</label>
                                <textarea name="domicile" class="form-control" rows="2">{{ old('domicile') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- PAJAK & REKENING --}}
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold text-dark">
                        <i class="fas fa-money-check-alt mr-2" style="color:var(--accent);"></i> Pajak &amp; Rekening
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">NPWP</label>
                                <input type="text" name="npwp" class="form-control" value="{{ old('npwp') }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-600">Status PTKP</label>
                                <select name="ptkp_status" class="form-control">
                                    <option value="">— Pilih PTKP —</option>
                                    @foreach($ptkpStatuses as $ptkp)
                                        <option value="{{ $ptkp }}" {{ old('ptkp_status') === $ptkp ? 'selected' : '' }}>{{ $ptkp }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-600">Bank</label>
                                <select name="bank_id" class="form-control">
                                    <option value="">— Pilih Bank —</option>
                                    @foreach($banks as $bank)
                                        <option value="{{ $bank->id }}" {{ old('bank_id') == $bank->id ? 'selected' : '' }}>
                                            {{ $bank->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-600">Nama Rekening</label>
                                <input type="text" name="account_name" class="form-control"
                                       value="{{ old('account_name') }}" style="text-transform:uppercase;">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-600">No. Rekening</label>
                                <input type="text" name="account_number" class="form-control" value="{{ old('account_number') }}">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label class="font-weight-600">Gaji Default</label>
                                <input type="text" name="default_salary" id="defaultSalary" class="form-control"
                                       value="{{ old('default_salary') }}" placeholder="0" inputmode="numeric">
                                <small class="text-muted">Nilai awal saat generate payroll; bisa diubah per periode.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label class="font-weight-600">Catatan</label>
                                <textarea name="notes" class="form-control" rows="1">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex">
                    <a href="{{ url('/employee') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left mr-1"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-success ml-auto">
                        <i class="fas fa-save mr-1"></i> Simpan
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
</style>
@endpush

@push('scripts')
<script>
$(function () {
    // Format ribuan untuk gaji default
    var salary = document.getElementById('defaultSalary');
    if (salary) {
        function fmt(v) {
            v = (v || '').toString().replace(/\D/g, '');
            return v ? parseInt(v, 10).toLocaleString('id-ID') : '';
        }
        salary.value = fmt(salary.value);
        salary.addEventListener('input', function () {
            var pos = this.value.length - this.selectionStart;
            this.value = fmt(this.value);
            this.selectionStart = this.selectionEnd = Math.max(0, this.value.length - pos);
        });
    }
});
</script>
@endpush