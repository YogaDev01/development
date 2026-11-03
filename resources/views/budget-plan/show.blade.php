@extends('layouts.app')

@section('content')

@php

    $totalRequestAmount = $budgetPlan->budgetRequests->sum('amount');

    $remainingBudget = $budgetPlan->amount - $totalRequestAmount;

    $usedPercentage = $budgetPlan->amount > 0 ? round(($totalRequestAmount / $budgetPlan->amount) * 100, 2) : 0;

    $remainingPercentage = 100 - $usedPercentage;

@endphp

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ url('/budget-plan') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Detail Budget Plan</h1>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item">Operational Cost</li>
                <li class="breadcrumb-item"><a href="{{ url('/budget-plan') }}">Budget Plan</a></li>
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
                                    class="fas fa-wallet text-white"
                                    style="font-size:40px;"
                                ></i>

                            </div>

                        </div>

                        <!-- TITLE -->
                        <h3 class="profile-username text-center">

                            {{ $budgetPlan->description }}

                        </h3>

                        {{-- STATUS
                             Dulu hanya tiga status yang punya badge (ACTIVE/
                             INACTIVE/CLOSED), sehingga Budget Plan yang sedang
                             Draft/Submitted/Checked/Verified/Closing tidak
                             menampilkan apa pun. Sekarang memakai komponen
                             bersama, jadi label, keterangan, dan warnanya sama
                             dengan modul lain — dan tidak ada status yang
                             terlewat. --}}
                        <div class="text-center mb-3">
                            <x-status :status="$budgetPlan->status" context="budget-plan" size="md" :description="true" />
                        </div>

                        {{-- Posisi dokumen di dalam alurnya. --}}
                        <x-workflow-flow :status="$budgetPlan->status" context="budget-plan" class="mb-3" />

                        <!-- INFO -->
                        <ul class="list-group list-group-unbordered mb-3">

                            <li class="list-group-item">

                                <b>Amount</b>

                                <span class="float-right text-primary font-weight-bold">

                                    {{ number_format($budgetPlan->amount, 0, ',', '.') }}

                                </span>

                            </li>

                            <li class="list-group-item">

                                <b>Total Request Amount</b>

                                <span class="float-right text-info font-weight-bold">

                                    {{ number_format($totalRequestAmount, 0, ',', '.') }}

                                </span>

                            </li>

                            <li class="list-group-item">

                                <b>Used Budget</b>

                                <span class="float-right text-warning font-weight-bold">

                                    {{ $usedPercentage }}%

                                </span>

                            </li>

                            <li class="list-group-item">

                                <b>Remaining Budget</b>

                                <span class="float-right {{ $remainingBudget < 0 ? 'text-danger' : 'text-success' }} font-weight-bold">

                                    {{ number_format($remainingBudget, 0, ',', '.') }}

                                </span>

                            </li>

                            <li class="list-group-item">

                                <b>Remaining %</b>

                                <span class="float-right text-success font-weight-bold">

                                    {{ $remainingPercentage }}%

                                </span>

                            </li>

                            <li class="list-group-item">

                                <b>Total Attachment</b>

                                <span class="float-right">

                                    {{ $budgetPlan->attachments->count() }} File

                                </span>

                            </li>

                            <li class="list-group-item">

                                <b>Total Request</b>

                                <span class="float-right">

                                    {{ $budgetPlan->budgetRequests->count() }} Request

                                </span>

                            </li>

                            <li class="list-group-item">

                                <b>Input By</b>

                                <span class="float-right">

                                    {{ $budgetPlan->inputBy->name ?? '-' }}

                                </span>

                            </li>

                            <li class="list-group-item">

                                <b>Created At</b>

                                <span class="float-right">

                                    {{ $budgetPlan->created_at->format('d M Y H:i') }}

                                </span>

                            </li>

                        </ul>

                        <!-- ACTION -->
                        @unless($budgetPlan->isBundled())
                        <a 
                            href="{{ url('/budget-plan/' . $budgetPlan->id . '/edit') }}"
                            class="btn btn-primary btn-block"
                        >

                            <i class="fas fa-edit mr-1"></i>

                            Edit Budget Plan

                        </a>
                        @endunless

                    </div>

                </div>

            </div>

            <!-- RIGHT -->
            <div class="col-md-8">

                <!-- INFORMATION -->
                <div class="card">

                    <div class="card-header">

                        <h3 class="card-title">

                            <i class="fas fa-info-circle mr-1"></i>

                            Informasi Budget Plan

                        </h3>

                    </div>

                    <div class="card-body p-0">

                        <table class="table table-bordered mb-0">

                            <tr>

                                <th width="30%">

                                    Customer

                                </th>

                                <td>

                                    @if($budgetPlan->project)

                                        <span class="badge badge-info px-2 py-1">

                                            {{ $budgetPlan->project->customer->name }}

                                        </span>

                                    @else

                                        -

                                    @endif

                                </td>

                            </tr>

                            <tr>

                                <th>

                                    Project

                                </th>

                                <td>

                                    @if($budgetPlan->project)

                                        {{ $budgetPlan->project->name }}

                                    @else

                                        -

                                    @endif

                                </td>

                            </tr>

                            <tr>

                                <th>

                                    Description

                                </th>

                                <td>

                                    {{ $budgetPlan->description }}

                                </td>

                            </tr>

                        </table>

                    </div>

                </div>

                <!-- ATTACHMENT -->
                <div class="card">

                    <div class="card-header">

                        <h3 class="card-title">

                            <i class="fas fa-paperclip mr-1"></i>

                            Attachments

                        </h3>

                    </div>

                    <div class="card-body table-responsive p-0">

                        <table class="table table-hover table-bordered mb-0">

                            <thead class="bg-light">

                                <tr>

                                    <th scope="col" width="5%">

                                        No

                                    </th>

                                    <th scope="col">

                                        File Name

                                    </th>

                                    <th scope="col" width="15%">

                                        Action

                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                @forelse($budgetPlan->attachments as $attachment)

                                    <tr>

                                        <td>

                                            {{ $loop->iteration }}

                                        </td>

                                        <td>

                                            {{ $attachment->original_name }}

                                        </td>

                                        <td>

                                            <a 
                                                href="{{ asset('storage/' . $attachment->path) }}" data-evidence="bp-att" data-evidence-name="{{ $attachment->original_name }}"
                                                target="_blank"
                                                class="btn btn-info btn-sm"
                                            >

                                                <i class="fas fa-eye mr-1"></i>

                                            </a>

                                        </td>

                                    </tr>

                                @empty

                                    <tr>

                                        <td 
                                            colspan="3"
                                            class="text-center text-muted py-4"
                                        >

                                            <i class="fas fa-paperclip d-block mb-2"></i>

                                            Tidak ada attachment

                                        </td>

                                    </tr>

                                @endforelse

                            </tbody>

                        </table>

                    </div>

                </div>

                <!-- BUDGET REQUEST -->
                <div class="card">

                    <div class="card-header">

                        <h3 class="card-title">

                            <i class="fas fa-file-invoice-dollar mr-1"></i>

                            Budget Request Yang Menggunakan Budget Plan Ini

                        </h3>

                    </div>

                    <div class="card-body table-responsive p-0">

                        <table class="table table-hover table-bordered mb-0">

                            <thead class="bg-light">

                                <tr>

                                    <th scope="col" width="5%" class="all">

                                        No

                                    </th>

                                    <th scope="col" width="15%" class="all">

                                        Code

                                    </th>

                                    <th scope="col">

                                        Description

                                    </th>

                                    <th scope="col" width="15%">

                                        Amount

                                    </th>

                                    <th scope="col" width="12%">

                                        Status

                                    </th>

                                    <th scope="col" width="10%" class="text-center all action-column">

                                        Action

                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                @forelse($budgetPlan->budgetRequests as $request)

                                    <tr>

                                        <td>

                                            {{ $loop->iteration }}

                                        </td>

                                        <td>

                                            {{ $request->code }}

                                        </td>

                                        <td>

                                            {{ $request->description }}

                                        </td>

                                        <td class="font-weight-bold text-primary">

                                            {{ number_format($request->amount, 0, ',', '.') }}

                                        </td>

                                        <td>

                                            {{-- Peta warna/ikon status yang ditulis khusus di halaman ini
                                                 sudah ketinggalan: 'PENDING_APPROVER'/'PENDING_FINANCE'
                                                 bukan lagi status Cost Request, sementara CHECKED,
                                                 VERIFIED, QUEUED, dan seluruh tahap Settlement tidak
                                                 punya warna sama sekali. Diganti komponen bersama yang
                                                 membaca App\Support\WorkflowStatus. --}}
                                            <x-status :status="$request->status" context="budget-request" />

                                        </td>

                                        <!-- ACTION COLUMN -->
                                        <td class="text-center action-column">

                                            <div class="action-buttons">

                                                <a 
                                                    href="{{ url('/budget-request/' . $request->id) }}"
                                                    class="btn btn-info btn-sm"
                                                    title="View Detail"
                                                >

                                                    <i class="fas fa-eye"></i>

                                                </a>

                                            </div>

                                        </td>

                                    </tr>

                                @empty

                                    <tr>

                                        <td 
                                            colspan="6"
                                            class="text-center text-muted py-4"
                                        >

                                            <i class="fas fa-file-invoice-dollar d-block mb-2"></i>

                                            Belum ada budget request

                                        </td>

                                    </tr>

                                @endforelse

                            </tbody>

                            @if($budgetPlan->budgetRequests->count() > 0)

                                <tfoot>

                                    <tr class="bg-light">

                                        <th colspan="3" class="text-right">

                                            TOTAL BUDGET REQUEST

                                        </th>

                                        <th class="text-primary font-weight-bold">

                                            {{ number_format($totalRequestAmount, 0, ',', '.') }}

                                        </th>

                                        <th colspan="2"></th>

                                    </tr>

                                    <tr class="bg-light">

                                        <th colspan="3" class="text-right">

                                            REMAINING BUDGET

                                        </th>

                                        <th class="{{ $remainingBudget < 0 ? 'text-danger' : 'text-success' }} font-weight-bold">

                                            {{ number_format($remainingBudget, 0, ',', '.') }}

                                        </th>

                                        <th colspan="2"></th>

                                    </tr>

                                </tfoot>

                            @endif

                        </table>

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
| CARD
|--------------------------------------------------------------------------
*/

.card {

    border-radius: var(--radius-lg);

    overflow: hidden;

    border: 0;

}

.card-header {

    padding: 14px 18px;

}

/*
|--------------------------------------------------------------------------
| PROFILE CARD
|--------------------------------------------------------------------------
*/

.card-body.box-profile {

    padding: 15px;

}

/*
|--------------------------------------------------------------------------
| ACTION COLUMN - LOCK RESPONSIVE
|--------------------------------------------------------------------------
*/

.action-column {

    white-space: nowrap !important;
    min-width: 100px !important;

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
| MOBILE RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 768px) {

    .content-header .row {

        flex-direction: column;

    }

    .breadcrumb {

        float: left !important;

        margin-top: 10px;

    }

}

</style>

@endpush