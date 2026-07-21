@extends('layouts.app')

@section('content')

<!-- CONTENT HEADER -->
<div class="content-header">

    <div class="container-fluid">

        <div class="row mb-2">

            <!-- TITLE -->
            <div class="col-sm-6">

                <h1 class="m-0">

                    <a 
                        href="{{ url('/budget-request/' . $budgetRequest->id) }}"
                        class="text-dark mr-2"
                        style="text-decoration: none;"
                    >

                        <i class="fas fa-arrow-left"></i>

                    </a>

                    Submit Realization

                </h1>

            </div>

            <!-- BREADCRUMB -->
            <div class="col-sm-6">

                <ol class="breadcrumb float-sm-right">

                    <li class="breadcrumb-item">

                        Operational Cost

                    </li>

                    <li class="breadcrumb-item">

                        <a href="{{ url('/budget-request') }}">
                            Budget Request
                        </a>

                    </li>

                    <li class="breadcrumb-item active">

                        Realization

                    </li>

                </ol>

            </div>

        </div>

    </div>

</div>

<!-- MAIN CONTENT -->
<div class="content">

    <div class="container-fluid">

        <div class="row">

            <!-- LEFT - SUMMARY -->
            <div class="col-lg-4">

                <!-- REQUEST SUMMARY -->
                <div class="card card-primary card-outline shadow-sm">

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

                                <span class="badge badge-info px-3 py-2">

                                    PAID

                                </span>

                            </div>

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

                                    <th width="5%">No</th>

                                    <th>Description</th>

                                    <th width="10%">Qty</th>

                                    <th width="18%">Unit Price</th>

                                    <th width="18%">Total</th>

                                </tr>

                            </thead>

                            <tbody>

                                @forelse($budgetRequest->details as $detail)

                                    <tr>

                                        <td>{{ $loop->iteration }}</td>

                                        <td>{{ $detail->description }}</td>

                                        <td>{{ number_format($detail->qty, 0, ',', '.') }}</td>

                                        <td>Rp {{ number_format($detail->amount, 0, ',', '.') }}</td>

                                        <td class="font-weight-bold text-primary">

                                            Rp {{ number_format($detail->total, 0, ',', '.') }}

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

                <!-- REALIZATION FORM -->
                <div class="card card-info card-outline shadow-sm">

                    <div class="card-header">

                        <h3 class="card-title">

                            <i class="fas fa-check-double mr-1"></i>

                            Submit Realization Evidence

                        </h3>

                    </div>

                    <form 
                        id="realizeForm"
                        action="{{ url('/budget-request/' . $budgetRequest->id . '/realize') }}"
                        method="POST"
                        enctype="multipart/form-data"
                    >

                        @csrf

                        <div class="card-body">

                            <!-- COMPLETION DATE -->
                            <div class="form-group">

                                <label class="font-weight-bold">

                                    <i class="fas fa-calendar-check mr-1"></i>

                                    Completion Date

                                </label>

                                <input 
                                    type="date"
                                    name="completion_date"
                                    id="completionDate"
                                    class="form-control"
                                    value="{{ now()->format('Y-m-d') }}"
                                    required
                                >

                                <small class="text-muted d-block mt-2">

                                    Tanggal pekerjaan/kegiatan selesai

                                </small>

                            </div>

                            <!-- REALIZATION DOCUMENTS -->
                            <div class="form-group">

                                <label class="font-weight-bold">

                                    <i class="fas fa-file-upload mr-1"></i>

                                    Upload Realization Documents

                                </label>

                                <div class="custom-file">

                                    <input 
                                        type="file"
                                        name="realization"
                                        id="realization"
                                        class="custom-file-input"
                                        accept="application/pdf,image/*"
                                    >

                                    <label 
                                        class="custom-file-label"
                                        for="realization"
                                    >

                                        Pilih file bukti realisasi

                                    </label>

                                </div>

                                <small class="text-muted d-block mt-2">

                                    Format: PDF, JPG, PNG (Max 10MB). Foto pekerjaan, laporan, atau dokumen lainnya

                                </small>

                            </div>

                            <!-- NOTES -->
                            <div class="form-group">

                                <label class="font-weight-bold">

                                    <i class="fas fa-sticky-note mr-1"></i>

                                    Additional Notes

                                </label>

                                <textarea 
                                    name="notes"
                                    id="notes"
                                    class="form-control"
                                    rows="3"
                                    placeholder="Catatan tambahan tentang realisasi..."
                                ></textarea>

                                <small class="text-muted d-block mt-2">

                                    Catatan tambahan jika diperlukan

                                </small>

                            </div>

                        </div>

                        <!-- FOOTER -->
                        <div class="card-footer">

                            <button 
                                type="submit"
                                id="submitBtn"
                                class="btn btn-info"
                            >

                                <i class="fas fa-paper-plane mr-1"></i>

                                Submit Realization

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

    let form = $('#realizeForm');




    let realizationInput = $('#realization');

    /*
    |--------------------------------------------------------------------------
    | FILE INPUT LABEL
    |--------------------------------------------------------------------------
    */

    realizationInput.on('change', function () {

        let fileName = this.files.length > 0 ? this.files[0].name : 'Pilih file bukti realisasi';

        $(this).next('.custom-file-label').text(fileName);

    });

    /*
    |--------------------------------------------------------------------------
    | VALIDATE FILE SIZE
    |--------------------------------------------------------------------------
    */

    realizationInput.on('change', function () {

        let file = this.files[0];

        if (file) {

            let maxSize = 10 * 1024 * 1024; // 10MB

            if (file.size > maxSize) {

                Swal.fire({

                    title: 'File Terlalu Besar',

                    text: 'Ukuran file tidak boleh lebih dari 10MB',

                    icon: 'error',

                    confirmButtonText: 'OK'

                });

                realizationInput.val('');

                $(this).next('.custom-file-label').text('Pilih file bukti realisasi');

                return;

            }

        }

    });

    /*
    |--------------------------------------------------------------------------
    | FORM SUBMIT
    |--------------------------------------------------------------------------
    */

    form.on('submit', function (e) {

        e.preventDefault();

        Swal.fire({

            title: 'Submit Realisasi?',

            text: 'Konfirmasi pengajuan realisasi untuk request ini',

            icon: 'question',

            showCancelButton: true,

            confirmButtonColor: '#17a2b8',

            cancelButtonColor: '#6c757d',

            confirmButtonText: 'Ya, Submit',

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

.form-group label {

    margin-bottom: 0.8rem;

}

.card-header .card-title {

    margin-bottom: 0;

    font-size: 1.1rem;

}

</style>

@endpush