@extends('layouts.app')

@section('title', 'Finance Forecast')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Finance Forecast</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item active">Forecast</li>
            </ol>
        </div>

        <x-stat-cards :cards="[
            ['Total', $summary['total'], 'primary', 'fas fa-binoculars'],
            ['Draft', $summary['draft'], 'secondary', 'fas fa-pen'],
            ['Active', $summary['active'], 'success', 'fas fa-play-circle'],
            ['Closed', $summary['closed'], 'dark', 'fas fa-lock'],
        ]" />

        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header d-flex align-items-center flex-wrap">
                <h3 class="card-title"><i class="fas fa-binoculars mr-1"></i> Daftar Forecast</h3>
                <div class="ml-auto d-flex flex-wrap" style="gap:8px;">
                    <select name="status" class="form-control form-control-sm" onchange="location.href='?status='+this.value" style="width:auto;">
                        <option value="">Semua Status</option>
                        <option value="DRAFT" {{ request('status') === 'DRAFT' ? 'selected' : '' }}>DRAFT</option>
                        <option value="ACTIVE" {{ request('status') === 'ACTIVE' ? 'selected' : '' }}>ACTIVE</option>
                        <option value="CLOSED" {{ request('status') === 'CLOSED' ? 'selected' : '' }}>CLOSED</option>
                    </select>
                    <a href="{{ url('/forecast/create') }}" class="btn btn-success btn-sm">
                        <i class="fas fa-plus mr-1"></i> Tambah Forecast
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0" style="font-size:12.5px;">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" style="width:1%;">No</th>
                                <th scope="col">Description</th>
                                <th scope="col" class="text-nowrap">Period</th>
                                <th scope="col" class="text-nowrap">Source Fund</th>
                                <th scope="col" class="text-right text-nowrap">Forecast</th>
                                <th scope="col" class="text-right text-nowrap">Actual</th>
                                <th scope="col" class="text-right text-nowrap">Variance</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Status</th>
                                <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($forecasts as $f)
                            @php $varClass = $f->variance < 0 ? 'text-danger' : 'text-success'; @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    {{-- Judulnya catatan forecast; PO ditampilkan sebagai
                                         ringkasan karena satu forecast bisa memuat beberapa PO
                                         (lihat Forecast::getProjectSummaryAttribute()). --}}
                                    <strong>{{ $f->label }}</strong>
                                    <div class="text-muted" style="font-size:10px;">PO: {{ $f->project_summary }}</div>
                                </td>
                                <td class="text-nowrap">{{ $f->date_start->format('d/m/Y') }} - {{ $f->date_end->format('d/m/Y') }}</td>
                                <td>
                                    {{-- Rekening kini melekat di baris detail, jadi satu
                                         forecast bisa menyentuh beberapa rekening. --}}
                                    @php
                                        $rek = $f->details->pluck('sourceFund.name')->filter()->unique()->values();
                                    @endphp
                                    @forelse($rek->take(2) as $nama)
                                        <span class="badge badge-light border px-2 py-1" style="font-size:9.5px;">{{ $nama }}</span>
                                    @empty
                                        <span class="text-muted">-</span>
                                    @endforelse
                                    @if($rek->count() > 2)
                                        <span class="text-muted" style="font-size:10px;">+{{ $rek->count() - 2 }}</span>
                                    @endif
                                </td>
                                <td class="text-right">{{ $f->forecast_amount_formatted }}</td>
                                <td class="text-right">{{ $f->actual_amount_formatted }}</td>
                                <td class="text-right {{ $varClass }}">{{ $f->variance_formatted }} ({{ $f->achievement_pct }}%)</td>
                                <td><span class="badge badge-{{ $f->status_color }}">{{ $f->status }}</span></td>
                                <td class="text-center action-column">
                                    <div class="action-buttons ts-actions">

                                        @if($f->status === 'DRAFT')
                                        <a href="{{ url('/forecast/' . $f->id . '/edit') }}"
                                           class="ts-action-primary is-neutral"
                                           title="Edit" aria-label="Edit forecast">
                                            <i class="fas fa-pen" aria-hidden="true"></i><span class="ts-al">Edit</span>
                                        </a>
                                        @else
                                        <a href="{{ url('/forecast/' . $f->id) }}"
                                           class="ts-action-primary is-neutral"
                                           title="Detail" aria-label="Detail forecast">
                                            <i class="fas fa-eye" aria-hidden="true"></i><span class="ts-al">Detail</span>
                                        </a>
                                        @endif

                                        @if($f->status === 'DRAFT')
                                        <button type="button" class="ts-action-more"
                                                aria-haspopup="true" aria-expanded="false"
                                                title="Aksi lain" aria-label="Aksi lain">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>

                                        <div class="ts-action-menu dropdown-menu" hidden>

                                            <a href="{{ url('/forecast/' . $f->id) }}" class="dropdown-item">
                                                <i class="fas fa-eye"></i>Detail
                                            </a>

                                            <div class="dropdown-divider"></div>

                                            <form action="{{ url('/forecast/' . $f->id) }}" method="POST" class="delete-form">
                                                @csrf @method('DELETE')
                                                <button aria-label="Hapus" type="submit" class="dropdown-item is-danger">
                                                    <i class="fas fa-trash"></i>Hapus
                                                </button>
                                            </form>

                                        </div>
                                        @endif

                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">Belum ada forecast.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

@endsection

@push('scripts')
<script>
$(function () {
    $('.delete-form').submit(function (e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: 'Hapus Forecast?', text: 'Data yang dihapus tidak dapat dikembalikan.',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#9a3030', cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
        }).then(function (r) { if (r.isConfirmed) form.submit(); });
    });
});
</script>
@endpush
