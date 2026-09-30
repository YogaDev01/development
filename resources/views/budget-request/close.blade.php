@extends('layouts.app')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ url('/budget-request/' . $budgetRequest->id) }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Close Budget Request</h1>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item">Operational Cost</li>
                <li class="breadcrumb-item"><a href="{{ url('/budget-request') }}">Budget Request</a></li>
                <li class="breadcrumb-item active">Close</li>
            </ol>
        </div>

        <div class="row">

            <!-- LEFT - SUMMARY & STATUS LOGS -->
            <div class="col-lg-4">

                <!-- REQUEST SUMMARY -->
                <div class="card card-primary card-outline shadow-sm mb-3">

                    <div class="card-header">

                        <h3 class="card-title">

                            <i class="fas fa-file-invoice-dollar mr-1"></i>

                            Request Summary

                        </h3>

                    </div>

                    <div class="card-body">

                        <!-- CODE -->
                        <div class="form-group mb-3">

                            <label class="font-weight-bold">

                                Request Code

                            </label>

                            <input 
                                type="text"
                                class="form-control"
                                value="{{ $budgetRequest->code ?? '-' }}"
                                readonly
                            >

                        </div>

                        <!-- DESCRIPTION -->
                        <div class="form-group mb-3">

                            <label class="font-weight-bold">

                                Description

                            </label>

                            <textarea 
                                class="form-control"
                                rows="3"
                                readonly
                            >{{ $budgetRequest->description }}</textarea>

                        </div>

                        <!-- AMOUNT -->
                        <div class="form-group mb-3">

                            <label class="font-weight-bold">

                                Total Amount

                            </label>

                            <div class="input-group">

                                <div class="input-group-prepend">

                                    <span class="input-group-text">Rp</span>

                                </div>

                                <input 
                                    type="text"
                                    class="form-control font-weight-bold text-primary"
                                    value="{{ number_format($budgetRequest->amount, 0, ',', '.') }}"
                                    readonly
                                >

                            </div>

                        </div>

                        <!-- STATUS -->
                        <div class="form-group">

                            <label class="font-weight-bold">

                                Current Status

                            </label>

                            <div>

                                <x-status :status="$budgetRequest->status" context="budget-request" size="md" />

                            </div>

                        </div>

                    </div>

                </div>

                <!-- STATUS TIMELINE -->
                <div class="card shadow-sm">

                    <div class="card-header">

                        <h3 class="card-title">

                            <i class="fas fa-history mr-1"></i>

                            Workflow History

                        </h3>

                    </div>

                    <div class="card-body p-0">

                        <div class="timeline">

                            @forelse($budgetRequest->statusLogs as $log)

                                <div class="timeline-item">

                                    <span class="timeline-icon" style="background-color: var(--info);">

                                        <i class="fas fa-{{ 
                                            match($log->action_type) {
                                                'submit'  => 'paper-plane',
                                                'approve' => 'check-circle',
                                                'reject'  => 'times-circle',
                                                'pay'     => 'money-bill-wave',
                                                'realize' => 'check-double',
                                                default   => 'circle',
                                            }
                                        }}"></i>

                                    </span>

                                    <div class="timeline-body">

                                        <small class="text-muted">

                                            {{ $log->created_at ? $log->created_at->format('d/m/Y H:i') : '-' }}

                                        </small>

                                        <strong class="d-block">

                                            {{ ucfirst($log->action_type) }}

                                        </strong>

                                        <small class="text-muted">

                                            By: {{ $log->actor_name }}

                                        </small>

                                        @if($log->notes)

                                            <p class="small mt-1 mb-0">

                                                {{ $log->notes }}

                                            </p>

                                        @endif

                                    </div>

                                </div>

                            @empty

                                <div class="p-3 text-center text-muted">

                                    Belum ada history

                                </div>

                            @endforelse

                        </div>

                    </div>

                </div>

            </div>

            <!-- RIGHT - FORM -->
            <div class="col-lg-8">

                <!-- DETAIL TABLE -->
                <div class="card shadow-sm mb-3">

                    <div class="card-header">

                        <h3 class="card-title">

                            <i class="fas fa-list mr-1"></i>

                            Request Detail

                        </h3>

                    </div>

                    <div class="card-body table-responsive p-0">

                        <table class="table table-hover table-bordered mb-0">

                            <thead class="bg-light">

                                <tr>

                                    <th scope="col" width="5%">No</th>

                                    <th scope="col">Description</th>

                                    <th scope="col" width="10%">Qty</th>

                                    <th scope="col" width="18%">Unit Price</th>

                                    <th scope="col" width="18%">Total</th>

                                </tr>

                            </thead>

                            <tbody>

                                @forelse($budgetRequest->details as $detail)

                                    <tr>

                                        <td>{{ $loop->iteration }}</td>

                                        <td>{{ $detail->description }}</td>

                                        <td>{{ number_format($detail->qty, 0, ',', '.') }}</td>

                                        <td>{{ number_format($detail->amount, 0, ',', '.') }}</td>

                                        <td class="font-weight-bold text-primary">

                                            {{ number_format($detail->total, 0, ',', '.') }}

                                        </td>

                                    </tr>

                                @empty

                                    <tr>

                                        <td colspan="5" class="text-center text-muted py-4">

                                            Tidak ada detail

                                        </td>

                                    </tr>

                                @endforelse

                            </tbody>

                        </table>

                    </div>

                </div>

                <!-- CLOSE FORM -->
                <div class="card card-danger card-outline shadow-sm">

                    <div class="card-header">

                        <h3 class="card-title">

                            <i class="fas fa-lock mr-1"></i>

                            Close Request

                        </h3>

                    </div>

                    <form 
                        id="closeForm"
                        action="{{ url('/budget-request/' . $budgetRequest->id . '/close') }}"
                        method="POST"
                    >

                        @csrf

                        <div class="card-body">

                            <!-- WARNING ALERT -->
                            <div class="alert alert-warning border-0 mb-4">

                                <h5 class="alert-heading mb-2">

                                    <i class="fas fa-exclamation-triangle mr-2"></i>

                                    Important Notice

                                </h5>

                                <p class="mb-0">

                                    Menutup request ini akan menyelesaikan seluruh alur workflow. 

                                    <strong>Pastikan semua proses sebelumnya (pembayaran & realisasi) sudah selesai.</strong>

                                </p>

                            </div>

                            <!-- CONFIRMATION CHECKBOX -->
                            <div class="form-group">

                                <div class="custom-control custom-checkbox">

                                    <input 
                                        type="checkbox"
                                        id="confirmClose"
                                        class="custom-control-input"
                                        required
                                    >

                                    <label class="custom-control-label" for="confirmClose">

                                        <span class="font-weight-bold">

                                            Saya setuju untuk menutup request ini

                                        </span>

                                        <div class="small text-muted">

                                            Dengan memilih ini, request akan ditandai sebagai CLOSED dan tidak bisa diubah lagi.

                                        </div>

                                    </label>

                                </div>

                            </div>

                            <!-- CLOSURE NOTES -->
                            <div class="form-group">

                                <label class="font-weight-bold">

                                    <i class="fas fa-sticky-note mr-1"></i>

                                    Closure Notes (Optional)

                                </label>

                                <textarea 
                                    name="notes"
                                    id="notes"
                                    class="form-control"
                                    rows="3"
                                    placeholder="Catatan penutupan / ringkasan final..."
                                ></textarea>

                                <small class="text-muted d-block mt-2">

                                    Catatan tambahan untuk penutupan request

                                </small>

                            </div>

                        </div>

                        <!-- FOOTER -->
                        <div class="card-footer">

                            <button 
                                type="submit"
                                id="submitBtn"
                                class="btn btn-danger"
                                disabled
                            >

                                <i class="fas fa-lock mr-1"></i>

                                Close Request

                            </button>

                            <a 
                                href="{{ url('/budget-request/' . $budgetRequest->id) }}"
                                class="btn btn-secondary"
                            >

                                <i class="fas fa-times mr-1"></i>

                                Cancel

                            </a>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection

@push('scripts')

<script>

$(function () {

    let form = $('#closeForm');

    let confirmCheckbox = $('#confirmClose');

    let submitBtn = $('#submitBtn');

    /*
    |--------------------------------------------------------------------------
    | ENABLE SUBMIT BUTTON WHEN CHECKBOX CHECKED
    |--------------------------------------------------------------------------
    */

    confirmCheckbox.on('change', function () {

        submitBtn.prop('disabled', !$(this).is(':checked'));

    });

    /*
    |--------------------------------------------------------------------------
    | FORM SUBMIT
    |--------------------------------------------------------------------------
    */

    form.on('submit', function (e) {

        e.preventDefault();

        if (!confirmCheckbox.is(':checked')) {

            Swal.fire({

                title: 'Konfirmasi Diperlukan',

                text: 'Silakan centang checkbox konfirmasi',

                icon: 'warning',

                confirmButtonText: 'OK'

            });

            return false;

        }

        Swal.fire({

            title: 'Close Request?',

            html: `

                <p>Anda akan menutup request ini secara final.</p>

                <p><strong>Aksi ini tidak dapat dibatalkan!</strong></p>

            `,

            icon: 'warning',

            showCancelButton: true,

            confirmButtonColor: 'var(--danger)',

            cancelButtonColor: '#55595e',

            confirmButtonText: 'Ya, Close Request',

            cancelButtonText: 'Batal'

        }).then((result) => {

            if (result.isConfirmed) {

                form.unbind('submit').submit();

            }

        });

    });

});

</script>

@endpush

@push('styles')

<style>

.timeline {

    position: relative;

    padding: 20px 0;

}

.timeline-item {

    padding-bottom: 20px;

    position: relative;

    border-left: 2px solid var(--border);

    margin-left: 20px;

    padding-left: 20px;

}

.timeline-item:last-child {

    border-left: none;

}

.timeline-icon {

    position: absolute;

    left: -32px;

    top: 0;

    width: 30px;

    height: 30px;

    border-radius: 50%;

    background: var(--info);

    color: white;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 14px;

    border: 3px solid white;

    box-shadow: 0 0 0 2px #dee2e6;

}

.timeline-body {

    padding: 10px;

}

.custom-control-label {

    cursor: pointer;

    user-select: none;

}

</style>

@endpush