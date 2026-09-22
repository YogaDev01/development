@extends('layouts.app')

@section('content')

@php

    $totalAmount = $budgetPlans->sum('amount');

@endphp

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ url('/project') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">PO Workspace</h1>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Master</a></li>
                <li class="breadcrumb-item"><a href="{{ url('/project') }}">PO</a></li>
                <li class="breadcrumb-item active">Detail</li>
            </ol>
        </div>

        <div class="row">

            <!-- LEFT -->
            <div class="col-md-4">

                <!-- PROFILE CARD -->
                <div class="card card-primary card-outline">

                    <div class="card-body box-profile">

                        <!-- PROJECT NAME -->
                        <h3 class="font-weight-bold text-center mb-3">

                            {{ $project->name }}

                        </h3>

                        <!-- STATUS -->
                        <div class="text-center mb-3">

                            @if($project->status == 'ACTIVE')

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

                                <b>Total Budget Plan</b>

                                <span class="float-right">

                                    {{ $budgetPlans->count() }}

                                </span>

                            </li>

                            <li class="list-group-item">

                                <b>Total Cluster</b>

                                <span class="float-right">

                                    {{ $clusters->count() }}

                                </span>

                            </li>

                            <li class="list-group-item">

                                <b>Total Amount</b>

                                <span class="float-right text-primary font-weight-bold">

                                    {{ number_format($totalAmount, 0, ',', '.') }}

                                </span>

                            </li>

                            <li class="list-group-item">

                                <b>Customer</b>

                                <span class="float-right">

                                    {{ $project->customer->name ?? '-' }}

                                </span>

                            </li>

                            <li class="list-group-item">

                                <b>Input By</b>

                                <span class="float-right">

                                    {{ $project->user->name ?? '-' }}

                                </span>

                            </li>

                            <li class="list-group-item">

                                <b>Created At</b>

                                <span class="float-right">

                                    {{ $project->created_at->format('d M Y') }}

                                </span>

                            </li>

                        </ul>

                        <!-- ACTION -->
                        <a
                            href="{{ url('/project/' . $project->id . '/edit') }}"
                            class="btn btn-primary btn-block"
                        >

                            <i class="fas fa-edit mr-1"></i>

                            Edit PO

                        </a>

                    </div>

                </div>

            </div>

            <!-- RIGHT -->
            <div class="col-md-8">

                <!-- PROJECT INFORMATION -->
                <div class="card">

                    <div class="card-header">

                        <h3 class="card-title">

                            <i class="fas fa-info-circle mr-1"></i>

                            Informasi PO

                        </h3>

                    </div>

                    <div class="card-body p-0">

                        <table class="table table-bordered mb-0">

                            <tr>

                                <th width="30%">
                                    Deskripsi
                                </th>

                                <td>
                                    {{ $project->name }}
                                </td>

                            </tr>

                            <tr>

                                <th>
                                    Customer
                                </th>

                                <td>

                                    @if($project->customer)

                                        <span class="badge badge-info px-2 py-1">

                                            {{ $project->customer->name }}

                                        </span>

                                    @else

                                        -

                                    @endif

                                </td>

                            </tr>

                            <tr>

                                <th>
                                    Notes
                                </th>

                                <td>
                                    {{ $project->notes ?? '-' }}
                                </td>

                            </tr>

                            <tr>

                                <th>
                                    Status
                                </th>

                                <td>

                                    @if($project->status == 'ACTIVE')

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

                        </table>

                    </div>

                </div>

                
                <div class="card">

                    <div class="card-header">

                        <h3 class="card-title">

                            <i class="fas fa-chart-line mr-1"></i>

                            PO Operational Summary

                        </h3>

                    </div>

                    <div class="card-body">

                        <div class="row text-center">

                            <div class="col-md-3 border-right">
                                <h5 class="text-warning mb-1">Documentation</h5>
                                <small class="text-muted">Current Stage</small>
                            </div>

                            <div class="col-md-3 border-right">
                                <h5 class="text-success mb-1">75%</h5>
                                <small class="text-muted">Progress</small>
                            </div>

                            <div class="col-md-3 border-right">
                                <h5 class="mb-1">{{ $budgetPlans->count() }}</h5>
                                <small class="text-muted">Budget Plans</small>
                            </div>

                            <div class="col-md-3">
                                <h5 class="mb-1">{{ $clusters->count() }}</h5>
                                <small class="text-muted">Projects</small>
                            </div>

                        </div>

                        <hr>

                        <div class="d-flex justify-content-between align-items-center flex-wrap">

                            <span class="badge badge-success px-3 py-2">Survey</span>
                            <i class="fas fa-arrow-right text-muted"></i>
                            <span class="badge badge-success px-3 py-2">Implementation</span>
                            <i class="fas fa-arrow-right text-muted"></i>
                            <span class="badge badge-warning px-3 py-2">Documentation</span>
                            <i class="fas fa-arrow-right text-muted"></i>
                            <span class="badge badge-secondary px-3 py-2">BAST</span>

                        </div>

                    </div>

                </div>

                <!-- BUDGET PLAN LIST -->
                <div class="card">

                    <div class="card-header">

                        <h3 class="card-title">

                            <i class="fas fa-wallet mr-1"></i>

                            Finance Flow

                        </h3>

                    </div>

                    <div class="card-body">

                        <div class="table-responsive">

                            <table
                                id="budgetPlanTable"
                                class="table table-hover table-bordered mb-0"
                            >

                                <thead class="bg-light">

                                    <tr>

                                        <th scope="col" width="5%" class="all">
                                            No
                                        </th>

                                        <th scope="col" class="all">
                                            Description
                                        </th>

                                        <th scope="col" width="20%">
                                            Amount
                                        </th>

                                        <th scope="col" width="15%">
                                            Attachment
                                        </th>

                                        <th scope="col" width="15%" class="all text-center action-column">
                                            Action
                                        </th>

                                    </tr>

                                </thead>

                                <tbody>

                                    @forelse($budgetPlans as $budget)

                                        <tr>

                                            <!-- NO -->
                                            <td>

                                                {{ $loop->iteration }}

                                            </td>

                                            <!-- DESCRIPTION -->
                                            <td>

                                                {{ $budget->description }}

                                            </td>

                                            <!-- AMOUNT -->
                                            <td>

                                                {{ number_format($budget->amount, 0, ',', '.') }}

                                            </td>

                                            <!-- ATTACHMENT -->
                                            <td>

                                                @if($budget->attachments->count() > 0)

                                                    <span class="badge badge-success">

                                                        {{ $budget->attachments->count() }} File

                                                    </span>

                                                @else

                                                    <span class="text-muted">

                                                        Tidak ada

                                                    </span>

                                                @endif

                                            </td>

                                            <!-- ACTION -->
                                            <td class="text-center action-column">

                                                <div class="action-buttons">

                                                    <a
                                                        href="{{ url('/budget-plan/' . $budget->id) }}"
                                                        class="btn btn-info btn-sm"
                                                    >

                                                        <i class="fas fa-eye"></i>

                                                    </a>

                                                </div>

                                            </td>

                                        </tr>

                                    @empty

                                        <tr>

                                            <td
                                                colspan="5"
                                                class="text-center text-muted py-4"
                                            >

                                                <i class="fas fa-folder-open d-block mb-2"></i>

                                                Belum ada budget plan yang menggunakan PO ini

                                            </td>

                                        </tr>

                                    @endforelse

                                </tbody>

                            </table>

                        </div>

                    </div>

                </div>

                <!-- CLUSTER LIST -->
                <div class="card">

                    <div class="card-header">

                        <h3 class="card-title">

                            <i class="fas fa-layer-group mr-1"></i>

                            Project Progress

                        </h3>

                    </div>

                    <div class="card-body">

                        <div class="table-responsive">

                            <table
                                id="clusterTable"
                                class="table table-hover table-bordered mb-0"
                            >

                                <thead class="bg-light">

                                    <tr>

                                        <th scope="col" width="5%" class="all">
                                            No
                                        </th>

                                        <th scope="col" width="15%">
                                            Code
                                        </th>

                                        <th scope="col" class="all">
                                            Nama Project
                                        </th>

                                        <th scope="col" width="20%">
                                            Current Stage
                                        </th>

                                        <th scope="col" width="15%">
                                            Progress
                                        </th>

                                        <th scope="col" width="10%">
                                            Action
                                        </th>

                                    </tr>

                                </thead>

                                <tbody>

                                    @forelse($clusters as $cluster)

                                        <tr>

                                            <!-- NO -->
                                            <td>

                                                {{ $loop->iteration }}

                                            </td>

                                            <!-- CODE -->
                                            <td>

                                                <span class="badge badge-dark px-2 py-1">

                                                    {{ $cluster->code }}

                                                </span>

                                            </td>

                                            <!-- NAME -->
                                            <td>

                                                {{ $cluster->name }}

                                            </td>

                                            <!-- CURRENT STAGE -->
                                            <td>
                                                @php
                                                    $stages = ["Survey","Implementation","Documentation","BAST"];
                                                    $stage = $stages[$loop->index % 4];
                                                    $progress = [25,50,75,100][$loop->index % 4];
                                                @endphp
                                                <span class="badge badge-warning">{{ $stage }}</span>
                                            </td>

                                           <td>
                                                <div class="progress progress-sm">
                                                    <div
                                                        class="progress-bar bg-success"
                                                        style="width: 75%;"
                                                    >
                                                        75%
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="text-center">
                                                <a href="#" class="btn btn-info btn-sm">
                                                    <i class="fas fa-stream"></i>
                                                </a>
                                            </td>

                                        </tr>

                                    @empty

                                        <tr>

                                            <td
                                                colspan="5"
                                                class="text-center text-muted py-4"
                                            >

                                                <i class="fas fa-layer-group d-block mb-2"></i>

                                                Belum ada Project yang tergabung dengan PO ini

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

</div>

@endsection

@push('styles')

<style>

/*
|--------------------------------------------------------------------------
| TABLE GLOBAL
|--------------------------------------------------------------------------
*/

.table td,
.table th {

    vertical-align: middle !important;

}

/*
|--------------------------------------------------------------------------
| ACTION COLUMN
|--------------------------------------------------------------------------
*/

.action-column {

    white-space: nowrap !important;
    min-width: 30px !important;

}

.action-buttons {

    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: nowrap;
    gap: 4px;

}

.action-buttons .btn {

    flex-shrink: 0;

}

/*
|--------------------------------------------------------------------------
| RESPONSIVE ICON
|--------------------------------------------------------------------------
*/

table.dataTable.dtr-inline.collapsed > tbody > tr > td:first-child:before,
table.dataTable.dtr-inline.collapsed > tbody > tr > th:first-child:before {

    top: 50%;
    transform: translateY(-50%);

}

</style>

@endpush

@push('scripts')

<script>

$(function () {

    /*
    |--------------------------------------------------------------------------
    | BUDGET PLAN TABLE
    |--------------------------------------------------------------------------
    */

    $('#budgetPlanTable').DataTable({

        responsive: {
            details: {
                type: 'inline'
            }
        },

        autoWidth: false,

        paging: false,

        searching: false,

        ordering: false,

        info: false,

        columnDefs: [

            {
                responsivePriority: 1,
                targets: [0, 1, -1]
            },

            {
                responsivePriority: 100,
                targets: [2, 3]
            }

        ]

    });

    /*
    |--------------------------------------------------------------------------
    | CLUSTER TABLE
    |--------------------------------------------------------------------------
    */

    $('#clusterTable').DataTable({

        responsive: {
            details: {
                type: 'inline'
            }
        },

        autoWidth: false,

        paging: false,

        searching: false,

        ordering: false,

        info: false,

        columnDefs: [

            {
                responsivePriority: 1,
                targets: [0, 2]
            },

            {
                responsivePriority: 100,
                targets: [1, 3, 4]
            }

        ]

    });

});

</script>

@endpush