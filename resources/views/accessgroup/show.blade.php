@extends('layouts.app')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Detail Access Group</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Master</a></li>
                <li class="breadcrumb-item"><a href="{{ url('/accessgroup') }}">Access Group</a></li>
                <li class="breadcrumb-item active">Detail</li>
            </ol>
        </div>

        <!-- ACCESS GROUP INFO -->
        <div class="card card-primary card-outline shadow-sm">

            <div class="card-header">

                <h3 class="card-title">

                    <i class="fas fa-users-cog mr-1"></i>

                    Informasi Access Group

                </h3>

            </div>

            <div class="card-body">

                <div class="row">

                    <!-- ACCESS GROUP INFO -->
                    <div class="col-md-6">

                        <table class="table table-bordered">

                            <tr>

                                <th width="35%">

                                    Access Group Name

                                </th>

                                <td>

                                    {{ $accessGroup->name }}

                                </td>

                            </tr>

                            <tr>

                                <th>

                                    Total Project

                                </th>

                                <td>

                                    {{ $accessGroup->details->count() }}

                                </td>

                            </tr>

                            <tr>

                                <th>

                                    Total User

                                </th>

                                <td>

                                    {{ $accessGroup->users->count() }}

                                </td>

                            </tr>

                            <tr>

                                <th>

                                    Created At

                                </th>

                                <td>

                                    {{ $accessGroup->created_at->format('d M Y H:i') }}

                                </td>

                            </tr>

                        </table>

                    </div>

                    <!-- PROJECT ACCESS -->
                    <div class="col-md-6">

                        <div class="border rounded p-3 bg-light">

                            <strong class="d-block mb-3">

                                Project Access

                            </strong>

                            @forelse($accessGroup->details as $detail)

                                <span class="badge badge-primary mb-2 mr-1 p-2">

                                    {{ $detail->project->name ?? '-' }}

                                </span>

                            @empty

                                <span class="text-muted">

                                    Tidak ada project

                                </span>

                            @endforelse

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <!-- USER LIST -->
        <div class="card card-secondary card-outline shadow-sm">

            <div class="card-header">

                <h3 class="card-title">

                    <i class="fas fa-users mr-1"></i>

                    User Dalam Access Group

                </h3>

            </div>

            <div class="card-body table-responsive p-0">

                <table class="table table-hover table-bordered mb-0">

                    <thead class="thead-light">

                        <tr>

                            <th scope="col" width="5%">

                                No

                            </th>

                            <th scope="col">

                                Name

                            </th>

                            <th scope="col">

                                Email

                            </th>

                            <th scope="col">

                                Role

                            </th>

                            <th scope="col">

                                Created At

                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse($accessGroup->users as $user)

                            <tr>

                                <td>

                                    {{ $loop->iteration }}

                                </td>

                                <td>

                                    {{ $user->name }}

                                </td>

                                <td>

                                    {{ $user->email }}

                                </td>

                                <td>

                                    {{ $user->role->name ?? '-' }}

                                </td>

                                <td>

                                    {{ $user->created_at->format('d M Y') }}

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td 
                                    colspan="5"
                                    class="text-center text-muted py-4"
                                >

                                    <i class="fas fa-folder-open d-block mb-2"></i>

                                    Tidak ada user dalam access group ini

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

            <!-- FOOTER -->
            <div class="card-footer">

                <a 
                    href="{{ url('/accessgroup') }}"
                    class="btn btn-secondary"
                >

                    <i class="fas fa-arrow-left mr-1"></i>

                    Kembali

                </a>

                <a 
                    href="{{ url('/accessgroup/' . $accessGroup->id . '/edit') }}"
                    class="btn btn-primary"
                >

                    <i class="fas fa-edit mr-1"></i>

                    Edit

                </a>

            </div>

        </div>

    </div>

</div>

@endsection