@extends('layouts.app')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ url('/budget-request') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Detail Budget Request</h1>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item">Operational Cost</li>
                <li class="breadcrumb-item"><a href="{{ url('/budget-request') }}">Budget Request</a></li>
                <li class="breadcrumb-item active">Detail</li>
            </ol>
        </div>

        <div class="row">

            <!-- LEFT -->
            <div class="col-lg-4">

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
                                    class="fas fa-file-invoice-dollar text-white"
                                    style="font-size:40px;"
                                ></i>

                            </div>

                        </div>

                        <!-- TITLE -->
                        <h3 class="profile-username text-center">

                            {{ $budgetRequest->description }}

                        </h3>

                        {{-- Status memakai komponen bersama. Rantai if/elseif
                             sebelumnya hanya meliput 8 status dan masih menyebut
                             PENDING_APPROVER/PENDING_FINANCE yang sudah tidak ada di
                             BudgetRequest::STATUSES — akibatnya CR berstatus CHECKED,
                             VERIFIED, QUEUED, SETTLE_*, dan CANCELLED tidak menampilkan
                             badge apa pun. --}}
                        <div class="text-center mb-3">
                            <x-status :status="$budgetRequest->status"
                                      context="budget-request"
                                      size="md"
                                      :description="true" />
                        </div>

                        <!-- INFO -->
                        <ul class="list-group list-group-unbordered mb-3">

                            <li class="list-group-item">

                                <b>Code</b>

                                <span class="float-right">

                                    {{ $budgetRequest->code ?? '-' }}

                                </span>

                            </li>

                            <li class="list-group-item">

                                <b>Cluster</b>

                                <span class="float-right">

                                    {{ $budgetRequest->cluster?->name ?? '-' }}

                                </span>

                            </li>

                            <li class="list-group-item">

                                <b>Requestor</b>

                                <span class="float-right">

                                    {{ $budgetRequest->applicant?->name ?? '-' }}

                                </span>

                            </li>

                            <li class="list-group-item">

                                <b>Beneficiary</b>

                                <div class="float-right text-right">

                                    <strong class="d-block">

                                        {{ $budgetRequest->beneficiary?->complete_name ?? '-' }}

                                    </strong>

                                    <small class="text-muted d-block">

                                        {{ $budgetRequest->beneficiary->bank?->name ?? '-' }}
                                        - 
                                        {{ $budgetRequest->beneficiary?->account_number ?? '-' }}

                                    </small>

                                </div>

                            </li>

                            <li class="list-group-item">

                                <b>Amount</b>

                                <span class="float-right text-primary font-weight-bold">

                                    {{ number_format($budgetRequest->amount, 0, ',', '.') }}

                                </span>

                            </li>

                            <li class="list-group-item">

                                <b>Created At</b>

                                <span class="float-right">

                                    {{ $budgetRequest->created_at ?? '-' }}

                                </span>

                            </li>

                        </ul>

                        <!-- ACTION BUTTONS - WORKFLOW BASED -->
                        <div class="btn-group-vertical w-100" role="group">

                            <!-- EDIT (Draft Only) -->
                            @if($budgetRequest->status === 'DRAFT' && Auth::user()->id === $budgetRequest->applicant_id)

                                <a 
                                    href="{{ url('/budget-request/' . $budgetRequest->id . '/edit') }}"
                                    class="btn btn-warning"
                                >

                                    <i class="far fa-edit mr-1"></i>

                                    Edit Request

                                </a>

                            @endif

                            <!-- SUBMIT (Draft Only) -->
                            @if($budgetRequest->status === 'DRAFT' && Auth::user()->id === $budgetRequest->applicant_id)

                                <form 
                                    action="{{ url('/budget-request/' . $budgetRequest->id . '/submit') }}"
                                    method="POST"
                                    style="width: 100%;"
                                    class="submit-form"
                                >

                                    @csrf

                                    <button 
                                        type="submit"
                                        class="btn btn-success w-100"
                                    >

                                        <i class="fas fa-paper-plane mr-1"></i>

                                        Submit Request

                                    </button>

                                </form>

                            @endif

                            <!-- REVIEW (Pending Approver) -->
                            @if($budgetRequest->status === 'PENDING_APPROVER' && in_array(Auth::user()->role->name, ['approver', 'superadmin', 'admin']))

                                <a 
                                    href="{{ route('budget-request.review', $budgetRequest->id) }}"
                                    class="btn btn-primary"
                                >

                                    <i class="fas fa-check-circle mr-1"></i>

                                    Review Request

                                </a>

                            @endif

                            <!-- REVIEW (Pending Finance) -->
                            @if($budgetRequest->status === 'PENDING_FINANCE' && in_array(Auth::user()->role->name, ['finance', 'finance_manager', 'superadmin', 'admin']))

                                <a 
                                    href="{{ route('budget-request.review', $budgetRequest->id) }}"
                                    class="btn btn-primary"
                                >

                                    <i class="fas fa-check-circle mr-1"></i>

                                    Review Request

                                </a>

                            @endif

                            <!-- MARK AS PAID (Approved) -->
                            @if($budgetRequest->status === 'APPROVED' && in_array(Auth::user()->role->name, ['finance', 'finance_manager', 'superadmin', 'admin']))

                                <a 
                                    href="{{ route('budget-request.pay-form', $budgetRequest->id) }}"
                                    class="btn btn-success"
                                >

                                    <i class="fas fa-money-bill-wave mr-1"></i>

                                    Mark as Paid

                                </a>

                            @endif

                            <!-- SUBMIT REALIZATION (Paid) -->
                            @if($budgetRequest->status === 'PAID' && Auth::user()->hasRole(['superadmin', 'manager', 'supervisor']))

                                <a 
                                    href="{{ route('budget-request.realize-form', $budgetRequest->id) }}"
                                    class="btn btn-info"
                                >

                                    <i class="fas fa-check-double mr-1"></i>

                                    Submit Realization

                                </a>

                            @endif

                            <!-- CLOSE REQUEST (Realized) -->
                            @if($budgetRequest->status === 'REALIZED' && in_array(Auth::user()->role->name, ['superadmin', 'admin']))

                                <a 
                                    href="{{ route('budget-request.close-form', $budgetRequest->id) }}"
                                    class="btn btn-danger"
                                >

                                    <i class="fas fa-lock mr-1"></i>

                                    Close Request

                                </a>

                            @endif

                        </div>

                    </div>

                </div>

            </div>

            <!-- RIGHT -->
            <div class="col-lg-8">

                <!-- INFORMATION -->
                <div class="card shadow-sm border-0">

                    <div class="card-header bg-white border-0">

                        <h3 class="card-title font-weight-bold">

                            <i class="fas fa-info-circle mr-2"></i>

                            Informasi Budget Request

                        </h3>

                    </div>

                    <div class="card-body p-0">

                        <table class="table table-bordered table-hover mb-0">

                            <tr>

                                <th width="30%">

                                    Request Code

                                </th>

                                <td>

                                    <span class="badge badge-dark px-3 py-2">

                                        {{ $budgetRequest->code }}

                                    </span>

                                </td>

                            </tr>

                            <tr>

                                <th>

                                    Request Type

                                </th>

                                <td>

                                    <span class="badge badge-primary px-3 py-2">

                                        {{ $budgetRequest->type?->name ?? '-' }}

                                    </span>

                                </td>

                            </tr>

                            <tr>

                                <th>

                                    Budget Plan

                                </th>

                                <td>

                                    {{ $budgetRequest->budgetPlan?->description ?? '-' }}

                                </td>

                            </tr>

                            <tr>

                                <th>

                                    Description

                                </th>

                                <td>

                                    {{ $budgetRequest->description }}

                                </td>

                            </tr>

                            <tr>

                                <th>

                                    Notes

                                </th>

                                <td>

                                    {{ $budgetRequest->notes ?? '-' }}

                                </td>

                            </tr>

                        </table>

                    </div>

                </div>

                <!-- REQUEST DETAIL -->
                <div class="card shadow-sm border-0">

                    <div class="card-header bg-white border-0">

                        <h3 class="card-title font-weight-bold">

                            <i class="fas fa-list mr-2"></i>

                            Request Detail

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

                                        Description

                                    </th>

                                    <th scope="col" width="10%">

                                        Qty

                                    </th>

                                    <th scope="col" width="18%">

                                        Unit Price

                                    </th>

                                    <th scope="col" width="18%">

                                        Total

                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                @forelse($budgetRequest->details as $detail)

                                    <tr>

                                        <td>

                                            {{ $loop->iteration }}

                                        </td>

                                        <td>

                                            {{ $detail->description }}

                                        </td>

                                        <td>

                                            {{ number_format($detail->qty, 0, ',', '.') }}

                                        </td>

                                        <td>

                                            {{ number_format($detail->amount, 0, ',', '.') }}

                                        </td>

                                        <td class="font-weight-bold text-primary">

                                            {{ number_format($detail->total, 0, ',', '.') }}

                                        </td>

                                    </tr>

                                @empty

                                    <tr>

                                        <td 
                                            colspan="5"
                                            class="text-center text-muted py-4"
                                        >

                                            <i class="fas fa-list d-block mb-2"></i>

                                            Belum ada request detail

                                        </td>

                                    </tr>

                                @endforelse

                            </tbody>

                            @if($budgetRequest->details->count() > 0)

                                <tfoot>

                                    <tr>

                                        <th 
                                            colspan="4"
                                            class="text-right"
                                        >

                                            Grand Total

                                        </th>

                                        <th class="text-primary">

                                            {{ number_format($budgetRequest->details->sum('total'), 0, ',', '.') }}

                                        </th>

                                    </tr>

                                </tfoot>

                            @endif

                        </table>

                    </div>

                </div>

                <!-- ATTACHMENTS -->
                <div class="card shadow-sm border-0">

                    <div class="card-header bg-white border-0">

                        <h3 class="card-title font-weight-bold">

                            <i class="fas fa-paperclip mr-2"></i>

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
                                    <th scope="col" width="12%">

                                        Type

                                    </th>


                                    <th scope="col" width="15%">

                                        Size

                                    </th>

                                    <th scope="col" width="15%">

                                        Action

                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                @forelse($budgetRequest->attachments as $attachment)

                                    <tr>

                                        <td>

                                            {{ $loop->iteration }}

                                        </td>

                                        <td>

                                            {{ $attachment->original_name }}

                                        </td>
                                        <td>

                                            <span class="badge badge-{{ $attachment->type === 'Receipt' ? 'success' : ($attachment->type === 'Realization' ? 'info' : 'secondary') }}">

                                                {{ $attachment->type ?? 'Request' }}

                                            </span>

                                        </td>


                                        <td>

                                            {{ number_format($attachment->size / 1024, 2) }} KB

                                        </td>

                                        <td>

                                            <a 
                                                href="{{ asset('storage/' . $attachment->path) }}" data-evidence="cr-att" data-evidence-name="{{ $attachment->original_name }}"
                                                target="_blank"
                                                class="btn btn-info btn-sm"
                                            >

                                                <i class="fas fa-eye mr-1"></i>

                                                View

                                            </a>

                                        </td>

                                    </tr>

                                @empty

                                    <tr>

                                        <td 
                                            colspan="5"
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

                <!-- WORKFLOW HISTORY -->
                <div class="card shadow-sm border-0">

                    <div class="card-header bg-white border-0">

                        <h3 class="card-title font-weight-bold">

                            <i class="fas fa-history mr-2"></i>

                            Workflow History

                        </h3>

                    </div>

                    <div class="card-body">

                        <div class="timeline">

                            @forelse($budgetRequest->statusLogs->sortBy('id') as $log)

                                @php
                                    $wfIcon = match($log->action_type) {
                                        'submit'  => 'paper-plane',
                                        'approve' => 'check-circle',
                                        'reject'  => 'times-circle',
                                        'pay'     => 'money-bill-wave',
                                        'realize' => 'check-double',
                                        'close'   => 'lock',
                                        default   => 'circle',
                                    };
                                @endphp

                                <div class="timeline-item">

                                    <span class="timeline-icon wf-{{ $log->action_type }}">

                                        <i class="fas fa-{{ $wfIcon }}"></i>

                                    </span>

                                    <div class="timeline-body">

                                        <div class="d-flex justify-content-between align-items-start flex-wrap">

                                            <strong class="text-capitalize">{{ str_replace('_', ' ', $log->action_type) }}</strong>

                                            <small class="text-muted">{{ $log->created_at ? $log->created_at->format('d/m/Y H:i') : '-' }}</small>

                                        </div>

                                        <small class="text-muted d-block mt-1">

                                            <span class="badge badge-light border">{{ \App\Models\BudgetRequest::statusLabelFor($log->status_from) }}</span>

                                            <i class="fas fa-arrow-right mx-1"></i>

                                            <span class="badge badge-light border">{{ \App\Models\BudgetRequest::statusLabelFor($log->status_to) }}</span>

                                        </small>

                                        <small class="text-muted d-block mt-1">

                                            <i class="fas fa-user mr-1"></i>{{ $log->actor_name ?? '-' }}

                                        </small>

                                        @if(!empty($log->notes))

                                            <div class="mt-2 p-2 bg-light rounded">

                                                <i class="fas fa-comment-dots text-muted mr-1"></i>

                                                {{ $log->notes }}

                                            </div>

                                        @endif

                                    </div>

                                </div>

                            @empty

                                <p class="text-center text-muted py-3 mb-0">

                                    <i class="fas fa-history d-block mb-2"></i>

                                    Belum ada riwayat workflow

                                </p>

                            @endforelse

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

.profile-username {

    font-size: 20px;

    font-weight: 500;

    margin-bottom: 10px;

    line-height: 1.3;

}

/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.table {

    margin-bottom: 0;

}

.table th {

    font-weight: 700;

    background: var(--surface-2);

    vertical-align: middle !important;

}

.table td {

    vertical-align: middle !important;

}

/*
|--------------------------------------------------------------------------
| BADGE
|--------------------------------------------------------------------------
*/

.badge {

    font-size: 12px;

    border-radius: var(--radius-sm);

}

/*
|--------------------------------------------------------------------------
| BUTTON GROUP
|--------------------------------------------------------------------------
*/

.btn {

    border-radius: var(--radius-sm);

    font-weight: 400;

}

.btn-group-vertical .btn {

    border-radius: 0;

}

.btn-group-vertical .btn:first-child {

    border-top-left-radius: 6px;

    border-top-right-radius: 6px;

}

.btn-group-vertical .btn:last-child {

    border-bottom-left-radius: 6px;

    border-bottom-right-radius: 6px;

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

    .project-title {

        font-size: 22px;

    }

}

/*
| WORKFLOW TIMELINE
*/
.timeline { position: relative; padding: 10px 0; }
.timeline-item { padding-bottom: 20px; position: relative; border-left: 2px solid var(--border); margin-left: 20px; padding-left: 20px; }
.timeline-item:last-child { border-left: none; padding-bottom: 0; }
.timeline-icon { position: absolute; left: -16px; top: 0; width: 30px; height: 30px; border-radius: 50%; background: var(--info); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 13px; border: 3px solid var(--border); box-shadow: 0 0 0 2px var(--border); }
.timeline-icon.wf-submit  { background: var(--text-faint); }
.timeline-icon.wf-approve { background: var(--success); }
.timeline-icon.wf-reject  { background: var(--danger); }
.timeline-icon.wf-pay     { background: var(--info); }
.timeline-icon.wf-realize { background: var(--info); }
.timeline-icon.wf-close   { background: var(--charcoal); }
.timeline-body { padding: 2px 0 0 6px; }

</style>

@endpush

@push('scripts')

<script>

$(function () {

    /*
    |--------------------------------------------------------------------------
    | SUBMIT FORM CONFIRMATION
    |--------------------------------------------------------------------------
    */

    $('.submit-form').on('submit', function (e) {

        e.preventDefault();

        Swal.fire({

            title: 'Submit Request?',

            text: 'Request akan diubah ke status PENDING_APPROVER dan dikirim untuk review',

            icon: 'question',

            showCancelButton: true,

            confirmButtonColor: 'var(--success)',

            cancelButtonColor: '#55595e',

            confirmButtonText: 'Ya, Submit',

            cancelButtonText: 'Batal'

        }).then((result) => {

            if (result.isConfirmed) {

                $(this).unbind('submit').submit();

            }

        });

    });

});

</script>

@endpush