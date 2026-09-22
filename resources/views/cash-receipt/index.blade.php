@extends('layouts.app')

@section('title', 'Cash Receipt')

@section('content')

<div class="content">
    <div class="container-fluid">

        @if(session('success'))
        <div class="alert alert-success rounded-0 shadow-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div class="alert alert-danger rounded-0 shadow-sm">{{ session('error') }}</div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Cash Receipt</h1>
                <small class="text-muted">
                    Batch penerimaan dana atas kumpulan invoice — cerminan Treasury, yang membatch pembayaran Cost Request.
                </small>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item active">Cash Receipt</li>
            </ol>
        </div>

        <x-stat-cards :cards="[
            ['Bukti Terima', $summary['count'], 'secondary', 'fas fa-receipt'],
            ['Total Diterima', 'Rp ' . number_format($summary['total'], 0, ',', '.'), 'success', 'fas fa-hand-holding-usd'],
            ['Invoice Tertutup', $summary['invoice_count'], 'primary', 'fas fa-file-invoice'],
            ['Belum Teralokasi', 'Rp ' . number_format($summary['unallocated'], 0, ',', '.'), $summary['unallocated'] > 0 ? 'warning' : 'secondary', 'fas fa-unlink'],
        ]" />

        <div class="card card-primary card-outline shadow-sm mt-3">
            <div class="card-header d-flex align-items-center flex-wrap" style="gap:8px;">
                <h3 class="card-title"><i class="fas fa-receipt mr-1"></i> Daftar Penerimaan</h3>
                <div class="ml-auto">
                    <a href="{{ route('cash-receipt.create') }}" class="btn btn-success btn-sm">
                        <i class="fas fa-plus mr-1"></i> Catat Penerimaan
                    </a>
                </div>
            </div>

            <div class="card-body">
                {{--
                     Bentuk bilah filter disamakan dengan halaman index lain
                     (List PO, Budget Plan): satu baris .custom-toolbar, kontrol
                     setinggi 30px, tombol bersih berikon penghapus di ujung.
                     Yang TIDAK diubah adalah cara kerjanya — penyaringan tetap
                     diproses di server lewat GET, karena kartu ringkasan di atas
                     dibangun dari query yang sama; memindahkannya ke DataTables
                     akan membuat kartu dan tabel bercerita hal berbeda. --}}
                <form method="GET" action="{{ route('cash-receipt.index') }}"
                      class="custom-toolbar mb-3 d-flex align-items-center justify-content-start flex-wrap" style="gap:8px;">

                    <div class="toolbar-row">
                        <span class="toolbar-label">Cari :</span>
                        <div class="toolbar-input">
                            <input type="search" name="q" class="form-control form-control-sm"
                                   value="{{ $filters['q'] }}"
                                   placeholder="Kode / no. referensi / kode invoice / customer">
                        </div>
                    </div>

                    <select name="customer_id" class="form-control form-control-sm {{ $filters['customer_id'] ? ' col-mf-active' : '' }}"
                            style="width:auto;min-width:150px;" aria-label="Filter Customer">
                        <option value="">Semua Customer</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" @selected((string) $filters['customer_id'] === (string) $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>

                    <select name="status" class="form-control form-control-sm {{ $filters['status'] ? ' col-mf-active' : '' }}"
                            style="width:auto;min-width:130px;" aria-label="Filter Status">
                        <option value="">Semua Status</option>
                        @foreach(\App\Models\CashReceiptHeader::STATUSES as $st)
                            <option value="{{ $st }}" @selected($filters['status'] === $st)>{{ $st }}</option>
                        @endforeach
                    </select>

                    <button type="submit" class="btn btn-sm btn-outline-secondary col-mf-btn" title="Terapkan filter">
                        <i class="fas fa-filter"></i> Terapkan
                    </button>

                    @if($filters['q'] !== '' || $filters['status'] || $filters['customer_id'])
                    <a href="{{ route('cash-receipt.index') }}" class="btn btn-outline-secondary btn-sm" title="Clear Filter" aria-label="Clear Filter">
                        <i class="fas fa-eraser"></i>
                    </a>
                    @endif
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" class="text-nowrap">Kode</th>
                                <th scope="col" class="text-nowrap">Tanggal</th>
                                <th scope="col">Customer</th>
                                <th scope="col" class="text-nowrap">Referensi</th>
                                <th scope="col" class="text-right text-nowrap">Total Diterima</th>
                                <th scope="col" style="width:22%;">Invoice</th>
                                <th scope="col" class="text-center text-nowrap">Status</th>
                                <th scope="col" class="text-center text-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($headers as $h)
                            @php
                                $allocated   = $h->allocatedAmount();
                                $unallocated = $h->unallocatedAmount();
                                $stBadge = ['DRAFT' => 'badge-warning', 'ALLOCATED' => 'badge-success', 'CANCELLED' => 'badge-danger'][$h->status] ?? 'badge-secondary';
                            @endphp
                            <tr>
                                <td class="text-nowrap"><strong>{{ $h->code }}</strong></td>
                                <td class="text-nowrap">{{ $h->received_date?->format('d M Y') }}</td>
                                <td>{{ $h->customer->name ?? '-' }}</td>
                                <td class="text-nowrap">{{ $h->reference_number ?: '-' }}</td>
                                <td class="text-right text-nowrap"><strong>{{ number_format($h->total_amount, 0, ',', '.') }}</strong></td>
                                <td>
                                    {{-- Kode invoice ditampilkan langsung supaya penerimaan
                                         bisa ditelusuri tanpa membuka detailnya satu per
                                         satu — pola yang sama dengan Surat Jalan. --}}
                                    @forelse($h->details->take(3) as $d)
                                        <span class="badge badge-light border px-2 py-1" style="font-size:9.5px;">
                                            {{ $d->invoice->code ?? '-' }}
                                        </span>
                                    @empty
                                        <span class="text-muted">-</span>
                                    @endforelse
                                    @if($h->details->count() > 3)
                                        <span class="text-muted" style="font-size:10px;">+{{ $h->details->count() - 3 }} lagi</span>
                                    @endif
                                    @if($unallocated > 0)
                                        <div class="text-warning" style="font-size:10px;">
                                            Rp {{ number_format($unallocated, 0, ',', '.') }} belum teralokasi
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center"><span class="badge {{ $stBadge }} px-2 py-1">{{ $h->status }}</span></td>
                                <td class="text-center text-nowrap">
                                    <a href="{{ route('cash-receipt.show', $h->id) }}" class="btn btn-sm btn-outline-primary" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8">
                                    <x-empty title="Tidak ada penerimaan yang cocok dengan filter ini"
                                             icon="fas fa-receipt" compact />
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $headers->links() }}
                </div>
            </div>
        </div>

    </div>
</div>

@endsection

@push('styles')
<style>
.font-weight-600 { font-weight:600; }
.table td, .table th { vertical-align:middle !important; }
</style>
@endpush
