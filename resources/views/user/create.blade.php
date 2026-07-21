@extends('layouts.app')

@section('content')

<div class="content-header">

    <div class="container-fluid">

        <div class="row mb-2">

            <!-- TITLE -->
            <div class="col-sm-6 d-flex align-items-center">

                <a href="{{ url('/user') }}"
                class="btn btn-secondary btn-sm mr-3">

                    <i class="fas fa-arrow-left"></i>

                </a>

                <h1 class="m-0 font-weight-bold">

                    Tambah User

                </h1>

            </div>

            <!-- BREADCRUMB -->
            <div class="col-sm-6">

                <ol class="breadcrumb float-sm-right">

                    <li class="breadcrumb-item">

                        <a href="#">
                            Master
                        </a>

                    </li>

                    <li class="breadcrumb-item">

                        <a href="{{ url('/user') }}">
                            User
                        </a>

                    </li>

                    <li class="breadcrumb-item active">

                        Tambah User

                    </li>

                </ol>

            </div>

        </div>

    </div>

</div>

<!-- MAIN CONTENT -->
<div class="content">

    <div class="container-fluid">

        <div class="card card-primary card-outline shadow-sm">

            <!-- HEADER -->
            <div class="card-header">

                <h3 class="card-title">

                    <i class="fas fa-user-plus mr-1"></i>

                    Form Tambah User

                </h3>

            </div>

            <!-- ERROR MESSAGE -->
            @if ($errors->any())

                <div class="alert alert-danger rounded-0 mb-0">

                    <strong>
                        Terjadi kesalahan:
                    </strong>

                    <ul class="mb-0 mt-2">

                        @foreach ($errors->all() as $error)

                            <li>{{ $error }}</li>

                        @endforeach

                    </ul>

                </div>

            @endif

            <!-- FORM -->
            <form action="{{ url('/user') }}" method="POST">

                @csrf

                <div class="card-body">

                    <div class="row">

                        <!-- FULL NAME -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label>

                                    Nama Lengkap

                                </label>

                                <input 
                                    type="text"
                                    name="name"
                                    class="form-control"
                                    autocomplete="off"
                                    value="{{ old('name') }}"
                                    placeholder="Masukkan nama lengkap"
                                    style="text-transform: uppercase;"
                                    required
                                >

                                <small class="text-muted">

                                    Nama lengkap user untuk kebutuhan approval & audit.

                                </small>

                                @error('name')

                                    <small class="text-danger d-block">

                                        {{ $message }}

                                    </small>

                                @enderror

                            </div>

                        </div>

                        <!-- USERNAME -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label>

                                    Username Login

                                </label>

                                <input 
                                    type="text"
                                    name="username"
                                    class="form-control"
                                    autocomplete="off"
                                    value="{{ old('username') }}"
                                    placeholder="Masukkan username login"
                                    required
                                >

                                <small class="text-muted">

                                    Username digunakan untuk login system.

                                </small>

                                @error('username')

                                    <small class="text-danger d-block">

                                        {{ $message }}

                                    </small>

                                @enderror

                            </div>

                        </div>

                        <!-- EMAIL -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label>

                                    Email

                                </label>

                                <input 
                                    type="email"
                                    name="email"
                                    class="form-control"
                                    autocomplete="off"
                                    value="{{ old('email') }}"
                                    placeholder="Masukkan email"
                                    required
                                >

                                @error('email')

                                    <small class="text-danger d-block">

                                        {{ $message }}

                                    </small>

                                @enderror

                            </div>

                        </div>

                        <!-- ROLE -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label>

                                    Role

                                </label>

                                <select 
                                    name="role_id"
                                    class="form-control"
                                    required
                                >

                                    <option value="">
                                        Pilih Role
                                    </option>

                                    @foreach($roles as $role)

                                        <option 
                                            value="{{ $role->id }}"
                                            {{ old('role_id') == $role->id ? 'selected' : '' }}
                                        >

                                            {{ $role->name }}

                                        </option>

                                    @endforeach

                                </select>

                                @error('role_id')

                                    <small class="text-danger d-block">

                                        {{ $message }}

                                    </small>

                                @enderror

                            </div>

                        </div>

                        <!-- ACCESS GROUP -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label>

                                    Access Group

                                </label>

                                <select 
                                    name="access_group_id"
                                    class="form-control"
                                    required
                                >

                                    <option value="">
                                        Pilih Access Group
                                    </option>

                                    @foreach($accessGroups as $group)

                                        <option 
                                            value="{{ $group->id }}"
                                            {{ old('access_group_id') == $group->id ? 'selected' : '' }}
                                        >

                                            {{ $group->name }}

                                        </option>

                                    @endforeach

                                </select>

                                <small class="text-muted">

                                    Tentukan akses data user berdasarkan group.

                                </small>

                                @error('access_group_id')

                                    <small class="text-danger d-block">

                                        {{ $message }}

                                    </small>

                                @enderror

                            </div>

                        </div>

                        <!-- PASSWORD -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label>

                                    Password

                                </label>

                                <input 
                                    type="password"
                                    name="password"
                                    class="form-control"
                                    placeholder="Masukkan password"
                                    required
                                >

                                <small class="text-muted">

                                    Minimal 6 karakter.

                                </small>

                                @error('password')

                                    <small class="text-danger d-block">

                                        {{ $message }}

                                    </small>

                                @enderror

                            </div>

                        </div>

                        <!-- CONFIRM PASSWORD -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label>

                                    Konfirmasi Password

                                </label>

                                <input 
                                    type="password"
                                    name="password_confirmation"
                                    class="form-control"
                                    placeholder="Ulangi password"
                                    required
                                >

                            </div>

                        </div>

                        <!-- STATUS -->
                        <div class="col-md-6">

                            <div class="form-group">

                                <label>

                                    Status

                                </label>

                                <select 
                                    name="status"
                                    class="form-control"
                                    required
                                >

                                    <option 
                                        value="ACTIVE"
                                        {{ old('status') == 'ACTIVE' ? 'selected' : '' }}
                                    >

                                        ACTIVE

                                    </option>

                                    <option 
                                        value="INACTIVE"
                                        {{ old('status') == 'INACTIVE' ? 'selected' : '' }}
                                    >

                                        INACTIVE

                                    </option>

                                </select>

                                @error('status')

                                    <small class="text-danger d-block">

                                        {{ $message }}

                                    </small>

                                @enderror

                            </div>

                        </div>

                    </div>

                </div>

                <!-- FOOTER -->
                <div class="card-footer">

                    <button 
                        type="submit"
                        class="btn btn-primary"
                    >

                        <i class="fas fa-save mr-1"></i>

                        Simpan

                    </button>

                    <a 
                        href="{{ url('/user') }}"
                        class="btn btn-secondary"
                    >

                        <i class="fas fa-arrow-left mr-1"></i>

                        Kembali

                    </a>

                </div>

            </form>

        </div>

    </div>

</div>

@endsection

@push('styles')

<link 
    rel="stylesheet" 
    href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css"
/>

<style>

.select2-container .select2-selection--multiple {
    min-height: 38px;
    border: 1px solid #ced4da;
}

</style>

@endpush

@push('scripts')

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>

$(function () {

    /*
    |--------------------------------------------------------------------------
    | INIT SELECT2
    |--------------------------------------------------------------------------
    */

    $('.select2').select2({

        placeholder: 'Pilih Access Group',
        width: '100%'

    });

    /*
    |--------------------------------------------------------------------------
    | AUTO GENERATE USERNAME
    |--------------------------------------------------------------------------
    */

    $('input[name="name"]').on('keyup', function () {

        let username = $(this)
            .val()
            .toLowerCase()
            .replace(/\s+/g, '');

        $('input[name="username"]').val(username);

    });

});

</script>

@endpush