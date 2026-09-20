@extends('layouts.app')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ url('/bank') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Detail Bank</h1>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Master</a></li>
                <li class="breadcrumb-item"><a href="{{ url('/bank') }}">Bank</a></li>
                <li class="breadcrumb-item active">Detail</li>
            </ol>
        </div>

        <div class="row">

            <!-- LEFT -->
            <div class="col-md-4">

                <!-- PROFILE CARD -->
                <div class="card card-primary card-outline">

                    <div class="card-body box-profile">

                        <!-- ICON -->
                        <div class="text-center mb-3">

                            <div 
                                class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center"
                                style="width:100px;height:100px;"
                            >

                                <i 
                                    class="fas fa-university text-white"
                                    style="font-size:40px;"
                                ></i>

                            </div>

                        </div>

                        <!-- BANK NAME -->
                        <h3 class="profile-username text-center">

                            {{ $bank->name }}

                        </h3>

                        <!-- INFO -->
                        <ul class="list-group list-group-unbordered mb-3">

                            <li class="list-group-item">

                                <b>Total Beneficiary</b>

                                <span class="float-right">

                                    {{ $beneficiaries->count() }}

                                </span>

                            </li>

                            <li class="list-group-item">

                                <b>Created At</b>

                                <span class="float-right">

                                    {{ $bank->created_at->format('d M Y') }}

                                </span>

                            </li>

                            <li class="list-group-item">

                                <b>Updated At</b>

                                <span class="float-right">

                                    {{ $bank->updated_at->format('d M Y') }}

                                </span>

                            </li>

                        </ul>

                        <!-- ACTION -->
                        <a 
                            href="{{ url('/bank/' . $bank->id . '/edit') }}"
                            class="btn btn-primary btn-block"
                        >

                            <i class="fas fa-edit mr-1"></i>

                            Edit Bank

                        </a>

                    </div>

                </div>

            </div>

            <!-- RIGHT -->
            <div class="col-md-8">

                <!-- DETAIL CARD -->
                <div class="card">

                    <div class="card-header">

                        <h3 class="card-title">

                            <i class="fas fa-info-circle mr-1"></i>

                            Informasi Bank

                        </h3>

                    </div>

                    <div class="card-body p-0">

                        <table class="table table-bordered mb-0">

                            <tr>

                                <th width="30%">

                                    Nama Bank

                                </th>

                                <td>

                                    {{ $bank->name }}

                                </td>

                            </tr>

                            <tr>

                                <th>

                                    Kode Bank

                                </th>

                                <td>

                                    {{ $bank->code ?? '-' }}

                                </td>

                            </tr>

                            <tr>

                                <th>

                                    Default Method

                                </th>

                                <td>

                                    @if($bank->default_method)

                                        <span class="badge badge-info">{{ $bank->default_method }}</span>

                                    @else

                                        -

                                    @endif

                                </td>

                            </tr>

                            <tr>

                                <th>

                                    Total Beneficiary

                                </th>

                                <td>

                                    {{ $beneficiaries->count() }}

                                </td>

                            </tr>

                            <tr>

                                <th>

                                    Created At

                                </th>

                                <td>

                                    {{ $bank->created_at->format('d M Y H:i') }}

                                </td>

                            </tr>

                            <tr>

                                <th>

                                    Updated At

                                </th>

                                <td>

                                    {{ $bank->updated_at->format('d M Y H:i') }}

                                </td>

                            </tr>

                        </table>

                    </div>

                </div>

                <!-- BENEFICIARY LIST -->
                <div class="card">

                    <div class="card-header">

                        <h3 class="card-title">

                            <i class="fas fa-users mr-1"></i>

                            Beneficiary Yang Menggunakan Bank Ini

                        </h3>

                    </div>

                    <div class="card-body table-responsive p-0">

                        <table class="table table-hover table-bordered mb-0">

                            <thead class="bg-light">

                                <tr>

                                    <th scope="col" width="5%">

                                        No

                                    </th>

                                    <th scope="col" width="25%">

                                        Nickname

                                    </th>

                                    <th scope="col">

                                        Complete Name

                                    </th>

                                    <th scope="col" width="25%">

                                        Account Number

                                    </th>

                                    <th scope="col" width="15%">

                                        Status

                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                @forelse($beneficiaries as $beneficiary)

                                    <tr>

                                        <td>

                                            {{ $loop->iteration }}

                                        </td>

                                        <!-- NICKNAME -->
                                        <td>

                                            {{ $beneficiary->nickname ?? '-' }}

                                        </td>

                                        <!-- COMPLETE NAME -->
                                        <td>

                                            {{ $beneficiary->complete_name ?? '-' }}

                                        </td>

                                        <!-- ACCOUNT NUMBER -->
                                        <td>

                                            {{ $beneficiary->account_number ?? '-' }}

                                        </td>

                                        <!-- STATUS -->
                                        <td>

                                            @if($beneficiary->status == 'ACTIVE')

                                                <span class="badge badge-success">

                                                    ACTIVE

                                                </span>

                                            @else

                                                <span class="badge badge-danger">

                                                    INACTIVE

                                                </span>

                                            @endif

                                        </td>

                                    </tr>

                                @empty

                                    <tr>

                                        <td 
                                            colspan="5"
                                            class="text-center text-muted py-4"
                                        >

                                            <i class="fas fa-folder-open d-block mb-2"></i>

                                            Belum ada beneficiary yang menggunakan bank ini

                                        </td>

                                    </tr>

                                @endforelse

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection