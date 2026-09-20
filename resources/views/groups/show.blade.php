@extends('layouts.app')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ url('/groups') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Detail Business Group</h1>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Master</a></li>
                <li class="breadcrumb-item"><a href="{{ url('/groups') }}">Business Group</a></li>
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
                                    class="fas fa-layer-group text-white"
                                    style="font-size:40px;"
                                ></i>

                            </div>

                        </div>

                        <!-- NAME -->
                        <h3 class="profile-username text-center">

                            {{ $group->name }}

                        </h3>

                        <!-- CODE -->
                        <p class="text-muted text-center">

                            {{ $group->code }}

                        </p>

                        <!-- STATUS -->
                        <div class="text-center mb-3">

                            @if($group->status == 'ACTIVE')

                                <span class="badge badge-success px-3 py-2">

                                    ACTIVE

                                </span>

                            @else

                                <span class="badge badge-danger px-3 py-2">

                                    INACTIVE

                                </span>

                            @endif

                        </div>

                        <!-- INFO -->
                        <ul class="list-group list-group-unbordered mb-3">

                            <li class="list-group-item">

                                <b>Total Cluster</b>

                                <span class="float-right">

                                    {{ $clusters->count() }}

                                </span>

                            </li>

                            <li class="list-group-item">

                                <b>Input By</b>

                                <span class="float-right">

                                    {{ $group->user->name ?? '-' }}

                                </span>

                            </li>

                            <li class="list-group-item">

                                <b>Created At</b>

                                <span class="float-right">

                                    {{ $group->created_at->format('d M Y') }}

                                </span>

                            </li>

                        </ul>

                        <!-- ACTION -->
                        <a 
                            href="{{ url('/groups/' . $group->id . '/edit') }}"
                            class="btn btn-primary btn-block"
                        >

                            <i class="fas fa-edit mr-1"></i>

                            Edit Group

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

                            Informasi Business Group

                        </h3>

                    </div>

                    <div class="card-body p-0">

                        <table class="table table-bordered mb-0">

                            <tr>

                                <th width="30%">

                                    Group Code

                                </th>

                                <td>

                                    {{ $group->code }}

                                </td>

                            </tr>

                            <tr>

                                <th>

                                    Group Name

                                </th>

                                <td>

                                    {{ $group->name }}

                                </td>

                            </tr>

                            <tr>

                                <th>

                                    Status

                                </th>

                                <td>

                                    @if($group->status == 'ACTIVE')

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

                            <tr>

                                <th>

                                    Input By

                                </th>

                                <td>

                                    {{ $group->user->name ?? '-' }}

                                </td>

                            </tr>

                            <tr>

                                <th>

                                    Created At

                                </th>

                                <td>

                                    {{ $group->created_at->format('d M Y H:i') }}

                                </td>

                            </tr>

                        </table>

                    </div>

                </div>

                <!-- CLUSTER LIST -->
                <div class="card">

                    <div class="card-header">

                        <h3 class="card-title">

                            <i class="fas fa-sitemap mr-1"></i>

                            Cluster Yang Menggunakan Business Group Ini

                        </h3>

                    </div>

                    <div class="card-body table-responsive p-0">

                        <table class="table table-hover table-bordered mb-0">

                            <thead class="bg-light">

                                <tr>

                                    <th scope="col" width="5%">

                                        No

                                    </th>

                                    <th scope="col" width="20%">

                                        Cluster Code

                                    </th>

                                    <th scope="col">

                                        Cluster Name

                                    </th>

                                    <th scope="col" width="20%">

                                        Status

                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                @forelse($clusters as $cluster)

                                    <tr>

                                        <td>

                                            {{ $loop->iteration }}

                                        </td>

                                        <td>

                                            {{ $cluster->code }}

                                        </td>

                                        <td>

                                            {{ $cluster->name }}

                                        </td>

                                        <td>

                                            @if($cluster->status == 'ACTIVE')

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
                                            colspan="4"
                                            class="text-center text-muted py-4"
                                        >

                                            <i class="fas fa-folder-open d-block mb-2"></i>

                                            Belum ada cluster yang menggunakan business group ini

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