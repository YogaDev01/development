@extends('layouts.app')

@section('title', 'Detail Forecast')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">{{ $forecast->label }}</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="{{ url('/forecast') }}">Forecast</a></li>
                <li class="breadcrumb-item active">Detail</li>
            </ol>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card shadow-sm" style="background:var(--charcoal);color:#fff;">
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <span class="badge badge-{{ $forecast->status_color }}">{{ $forecast->status }}</span>
                        </div>
                        <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Period</span><div class="text-white mt-1">{{ $forecast->date_start->format('d M Y') }} - {{ $forecast->date_end->format('d M Y') }}</div></div>
                        {{-- PO melekat di baris detail, jadi satu forecast bisa
                             menyentuh beberapa PO — yang ditampilkan di sini
                             daftarnya, bukan satu nilai tunggal. --}}
                        @php
                            $poForecast = $forecast->details
                                ->map(fn ($d) => $d->project)
                                ->filter()
                                ->unique('id')
                                ->values();
                        @endphp
                        <div class="mb-2">
                            <span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">PO</span>
                            <div class="text-white mt-1">
                                @forelse($poForecast as $po)
                                    <div>{{ $po->code }} &middot; {{ $po->customer->name ?? '-' }}</div>
                                @empty
                                    <div class="text-muted">Tanpa PO</div>
                                @endforelse
                            </div>
                        </div>
                        @if($forecast->notes)
                        <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Catatan</span><div class="text-white mt-1">{{ $forecast->notes }}</div></div>
                        @endif
                        <hr style="border-color:rgba(255,255,255,0.08);">
                        <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Forecast Amount</span><div style="color:var(--accent);font-size:16px;font-weight:700;" class="mt-1">{{ $forecast->forecast_amount_formatted }}</div></div>
                        <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Actual Amount</span><div class="text-white mt-1" style="font-size:15px;font-weight:700;">{{ $forecast->actual_amount_formatted }}</div></div>
                        <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Variance</span><div class="mt-1" style="font-size:15px;font-weight:700;color:{{ $forecast->variance < 0 ? '#F87171' : '#34D399' }};">{{ $forecast->variance_formatted }}</div></div>
                        <div class="mb-2"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Achievement</span><div class="text-white mt-1" style="font-size:15px;font-weight:700;">{{ $forecast->achievement_pct }}%</div></div>

                        <div class="mt-3">
                            @if($forecast->status === 'DRAFT')
                            <a href="{{ url('/forecast/' . $forecast->id . '/edit') }}" class="btn btn-outline-warning btn-sm btn-block mb-2">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </a>
                            <button type="button" class="btn btn-success btn-sm btn-block" id="btnActivate">
                                <i class="fas fa-play mr-1"></i> Activate
                            </button>
                            @elseif($forecast->status === 'ACTIVE')
                            <small class="d-block text-center mb-2" style="color:var(--text-faint);">
                                Update Actual per item pada tabel Detail Forecast.
                            </small>
                            <button type="button" class="btn btn-dark btn-sm btn-block" id="btnClose">
                                <i class="fas fa-lock mr-1"></i> Close
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0">Detail Forecast</h3>
                        @if($forecast->status === 'ACTIVE' && $forecast->details->isNotEmpty())
                        <button type="submit" form="frmUpdateActual" class="btn btn-success btn-sm ml-auto">
                            <i class="fas fa-save mr-1"></i> Simpan Actual
                        </button>
                        @endif
                    </div>
                    <div class="card-body p-0">
                        <form id="frmUpdateActual" action="{{ url('/forecast/' . $forecast->id . '/update-actual') }}" method="POST">
                            @csrf
                            <div class="table-responsive">
                                <table class="table table-bordered mb-0" style="font-size:12.5px;">
                                    <thead class="bg-light">
                                        <tr>
                                            <th scope="col">Description</th>
                                            <th scope="col" class="text-nowrap">PO / Project</th>
                                            <th scope="col" class="text-nowrap">Tipe</th>
                                            <th scope="col" class="text-nowrap">Source Fund</th>
                                            <th scope="col" class="text-right">Amount (Plan)</th>
                                            <th scope="col" class="text-right" style="width:160px;">Actual</th>
                                            <th scope="col" class="text-right">Variance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($forecast->details as $d)
                                        <tr>
                                            <td>{{ $d->description }}</td>
                                            <td class="text-nowrap">
                                                @if($d->project)
                                                    <span title="{{ $d->project->name }}">{{ $d->project->code }}</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-nowrap">
                                                <span class="badge badge-{{ $d->type_color }} px-2 py-1" style="font-size:9.5px;">{{ $d->type_label }}</span>
                                            </td>
                                            <td class="text-nowrap">{{ $d->sourceFund->name ?? '-' }}</td>
                                            <td class="text-right">{{ $d->amount_formatted }}</td>
                                            <td class="text-right">
                                                @if($forecast->status === 'ACTIVE')
                                                <input type="text" name="details[{{ $d->id }}]" class="form-control form-control-sm text-right inp-actual-item" value="{{ $d->actual_amount }}">
                                                @else
                                                {{ $d->actual_amount_formatted }}
                                                @endif
                                            </td>
                                            <td class="text-right {{ ($d->amount - $d->actual_amount) < 0 ? 'text-danger' : 'text-success' }}">
                                                {{ number_format($d->amount - $d->actual_amount, 0, ',', '.') }}
                                            </td>
                                        </tr>
                                        @empty
                                        <tr><td colspan="7" class="text-center text-muted py-3">Belum ada detail.</td></tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot>
                                        <tr style="background:var(--surface-2);">
                                            <th colspan="3" class="text-right">Total</th>
                                            <th class="text-right">{{ $forecast->forecast_amount_formatted }}</th>
                                            <th class="text-right">{{ $forecast->actual_amount_formatted }}</th>
                                            <th class="text-right">{{ $forecast->variance_formatted }}</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<form id="frmActivate" action="{{ url('/forecast/' . $forecast->id . '/activate') }}" method="POST" style="display:none;">@csrf</form>
<form id="frmClose" action="{{ url('/forecast/' . $forecast->id . '/close') }}" method="POST" style="display:none;">@csrf</form>

@endsection

@push('scripts')
<script>
$(function () {
    $('.inp-actual-item').on('input', function () {
        var v = this.value.replace(/\D/g, '');
        this.value = v ? parseInt(v).toLocaleString('id-ID') : '';
    });

    $('#frmUpdateActual').on('submit', function () {
        $(this).find('.inp-actual-item').each(function () {
            $(this).val($(this).val().replace(/\./g, ''));
        });
    });

    $('#btnActivate').on('click', function () {
        Swal.fire({
            title: 'Activate Forecast?', text: 'Header & detail akan terkunci setelah diaktifkan.',
            icon: 'question', showCancelButton: true,
            confirmButtonColor: 'var(--success)', cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Activate', cancelButtonText: 'Batal'
        }).then(function (r) { if (r.isConfirmed) $('#frmActivate').submit(); });
    });

    $('#btnClose').on('click', function () {
        Swal.fire({
            title: 'Close Forecast?', text: 'Forecast yang sudah CLOSED tidak dapat dibuka lagi.',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#343a40', cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Close', cancelButtonText: 'Batal'
        }).then(function (r) { if (r.isConfirmed) $('#frmClose').submit(); });
    });
});
</script>
@endpush
