@extends('layouts.app')

@section('content')

<!-- CONTENT HEADER -->
<div class="content-header">

    <div class="container-fluid">

        <div class="row mb-2">

            <!-- TITLE -->
            <div class="col-sm-6 d-flex align-items-center">

                <a href="{{ url('/accessgroup') }}"
                class="btn btn-secondary btn-sm mr-3">

                    <i class="fas fa-arrow-left"></i>

                </a>

                <h1 class="m-0 font-weight-bold">

                    Tambah Access Group

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

                        <a href="{{ url('/accessgroup') }}">
                            Access Group
                        </a>

                    </li>

                    <li class="breadcrumb-item active">

                        Tambah

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

                    <i class="fas fa-users-cog mr-1"></i>

                    Form Tambah Access Group

                </h3>

            </div>

            <!-- ERROR -->
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
            <form 
                action="{{ url('/accessgroup') }}"
                method="POST"
            >

                @csrf

                <div class="card-body">

                    <div class="row">

                        <!-- ACCESS GROUP NAME -->
                        <div class="col-md-12">

                            <div class="form-group">

                                <label>

                                    Access Group Name

                                </label>

                                <input 
                                    type="text"
                                    name="name"
                                    class="form-control"
                                    value="{{ old('name') }}"
                                    placeholder="Contoh : FINANCE AREA 1"
                                    style="text-transform: uppercase;"
                                    autocomplete="off"
                                    required
                                >

                                <small class="text-muted">

                                    Nama group akses untuk pengelompokan hak akses project.

                                </small>

                                @error('name')

                                    <small class="text-danger d-block">

                                        {{ $message }}

                                    </small>

                                @enderror

                            </div>

                        </div>

                        <!-- PROJECT ACCESS -->
                        <div class="col-md-12">

                            <div class="form-group">

                                <label>

                                    Project Access

                                </label>

                                <select 
                                    name="project_ids[]"
                                    id="project_ids"
                                    class="selectpicker form-control"
                                    multiple
                                    data-live-search="true"
                                    data-actions-box="true"
                                    data-selected-text-format="count > 2"
                                    title="Pilih project access..."
                                >

                                    @foreach($projects as $project)

                                        <option 
                                            value="{{ $project->id }}"
                                            {{
                                                collect(old('project_ids'))
                                                    ->contains($project->id)
                                                    ? 'selected'
                                                    : ''
                                            }}
                                        >

                                            [{{ $project->code ?? '-' }}] [{{ $project->po_number ?? '-' }}] [{{ $project->done_by ?? '-' }}] {{ $project->name }}

                                        </option>

                                    @endforeach

                                </select>

                                <small class="text-muted">

                                    Pilih project yang dapat diakses oleh access group ini.

                                </small>

                                @error('project_ids')

                                    <small class="text-danger d-block">

                                        {{ $message }}

                                    </small>

                                @enderror

                            </div>

                        </div>

                        <!-- COST CENTER ACCESS (OVERHEAD) -->
                        <div class="col-md-12">

                            <div class="form-group">

                                <label>

                                    Overhead (Cost Center) Access

                                </label>

                                <select
                                    name="cost_center_ids[]"
                                    id="cost_center_ids"
                                    class="selectpicker form-control"
                                    multiple
                                    data-live-search="true"
                                    data-actions-box="true"
                                    data-selected-text-format="count > 2"
                                    title="Pilih overhead access..."
                                >

                                    @foreach($costCenters as $cc)

                                        <option
                                            value="{{ $cc->id }}"
                                            {{
                                                collect(old('cost_center_ids'))
                                                    ->contains($cc->id)
                                                    ? 'selected'
                                                    : ''
                                            }}
                                        >

                                            [{{ $cc->code }}] {{ $cc->name }}

                                        </option>

                                    @endforeach

                                </select>

                                <small class="text-muted">

                                    Pilih cost center overhead yang dapat diakses. Kosongkan bila group ini hanya untuk project.

                                </small>

                                @error('cost_center_ids')

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
                        href="{{ url('/accessgroup') }}"
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

@push('scripts')

<!-- BOOTSTRAP SELECT -->
<link 
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/css/bootstrap-select.min.css"
>

<script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/js/bootstrap-select.min.js"></script>

<script>

$(function () {

    /*
    |--------------------------------------------------------------------------
    | BOOTSTRAP SELECT
    |--------------------------------------------------------------------------
    */

    $('#project_ids').selectpicker({

        liveSearch: true,

        actionsBox: true,

        deselectAllText: 'Clear',

        selectAllText: 'Select All',

        noneSelectedText: 'Pilih project access...',

    });

    $('#cost_center_ids').selectpicker({

        liveSearch: true,

        actionsBox: true,

        deselectAllText: 'Clear',

        selectAllText: 'Select All',

        noneSelectedText: 'Pilih overhead access...',

    });

    /*
    |--------------------------------------------------------------------------
    | AUTO UPPERCASE
    |--------------------------------------------------------------------------
    */

    $('input[name="name"]').on('input', function () {

        $(this).val(

            $(this).val().toUpperCase()

        );

    });

});

</script>

@endpush