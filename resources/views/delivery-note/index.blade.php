@extends('layouts.app')

@section('title', 'Surat Jalan Invoice')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Surat Jalan Invoice</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item active">Surat Jalan Invoice</li>
            </ol>
        </div>

        <x-stat-cards :cards="[
            ['Total Surat Jalan', $summary['total'], 'primary', 'fas fa-truck'],
            ['Sent', $summary['sent'], 'warning', 'fas fa-paper-plane'],
            ['Received', $summary['received'], 'success', 'fas fa-check-double'],
        ]" />

        <div class="card card-primary card-outline shadow-sm mt-3">
            <div class="card-header d-flex align-items-center flex-wrap" style="gap:8px;">
                <h3 class="card-title"><i class="fas fa-truck mr-1"></i> Daftar Surat Jalan</h3>
                <div class="ml-auto d-flex" style="gap:8px;">
                    @if(Auth::user()->hasRole(['superadmin','admin','finance','finance_manager','collection']))
                    <a href="{{ route('delivery-note.create') }}" class="btn btn-success btn-sm">
                        <i class="fas fa-plus mr-1"></i> Buat Surat Jalan
                    </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                {{-- MN-08 —
                     Bentuk bilah filter disamakan dengan halaman index lain
                     (List PO, Budget Plan): satu baris .custom-toolbar, kontrol
                     setinggi 30px, tombol bersih berikon penghapus di ujung.
                     Yang TIDAK diubah adalah cara kerjanya — penyaringan tetap
                     diproses di server lewat GET, karena kartu ringkasan di atas
                     dibangun dari query yang sama; memindahkannya ke DataTables
                     akan membuat kartu dan tabel bercerita hal berbeda. --}}
                <form method="GET" action="{{ route('delivery-note.index') }}"
                      class="custom-toolbar mb-3 d-flex align-items-center justify-content-start flex-wrap" style="gap:8px;">

                    <div class="toolbar-row">
                        <span class="toolbar-label">Cari :</span>
                        <div class="toolbar-input">
                            <input type="search" name="q" class="form-control form-control-sm"
                                   value="{{ $filters['q'] }}"
                                   placeholder="Kode surat jalan / kode invoice / no. PO / customer">
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
                        @foreach(\App\Models\DeliveryNote::STATUSES as $st)
                            <option value="{{ $st }}" @selected($filters['status'] === $st)>{{ $st }}</option>
                        @endforeach
                    </select>

                    <button type="submit" class="btn btn-sm btn-outline-secondary col-mf-btn" title="Terapkan filter">
                        <i class="fas fa-filter"></i> Terapkan
                    </button>

                    @if($filters['q'] !== '' || $filters['status'] || $filters['customer_id'])
                    <a href="{{ route('delivery-note.index') }}" class="btn btn-outline-secondary btn-sm" title="Clear Filter" aria-label="Clear Filter">
                        <i class="fas fa-eraser"></i>
                    </a>
                    @endif
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" style="width:14%;">Kode Surat Jalan</th>
                                <th scope="col">Customer</th>
                                <th scope="col" style="width:10%;" class="text-center">Jml Invoice</th>
                                <th scope="col" style="width:20%;">Invoice / No. PO</th>
                                <th scope="col" style="width:14%;">Tanggal Kirim</th>
                                <th scope="col" style="width:12%;">Status</th>
                                <th scope="col" style="width:10%;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($deliveryNotes as $dn)
                            <tr>
                                <td><span class="badge badge-dark px-2 py-1" style="font-size:11px;">{{ $dn->code }}</span></td>
                                <td>{{ $dn->customer->name ?? '-' }}</td>
                                <td class="text-center">{{ $dn->items_count }}</td>
                                <td>
                                    {{-- Kode invoice ditampilkan langsung supaya hasil
                                         pencarian bisa dipastikan tanpa membuka detailnya
                                         satu per satu. Dibatasi 3; sisanya diringkas. --}}
                                    @forelse($dn->items->take(3) as $it)
                                        <span class="badge badge-light border px-2 py-1" style="font-size:9.5px;">
                                            {{ $it->invoice->code ?? '-' }}
                                        </span>
                                    @empty
                                        <span class="text-muted">-</span>
                                    @endforelse
                                    @if($dn->items_count > 3)
                                        <span class="text-muted" style="font-size:10px;">+{{ $dn->items_count - 3 }} lagi</span>
                                    @endif
                                </td>
                                <td>{{ $dn->sent_at ? $dn->sent_at->format('d/m/Y') : '-' }}</td>
                                <td><span class="badge badge-{{ $dn->status_color }} px-2 py-1">{{ $dn->status_label }}</span></td>
                                <td class="text-center action-column">
                                    <div class="action-buttons ts-actions">
                                        <a href="{{ route('delivery-note.show', $dn->id) }}"
                                           class="ts-action-primary is-neutral"
                                           title="Detail" aria-label="Detail surat jalan {{ $dn->code ?? '' }}">
                                            <i class="fas fa-eye" aria-hidden="true"></i><span class="ts-al">Detail</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7">
                                    <x-empty title="Tidak ada Surat Jalan yang cocok dengan filter ini."
                                             icon="fas fa-folder-open" compact />
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $deliveryNotes->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
