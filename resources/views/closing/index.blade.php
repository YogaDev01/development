@extends('layouts.app')

@section('title', 'Tutup Periode')

@section('content')

<div class="content">
    <div class="container-fluid">
      <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        @if(session('success'))
        <div class="alert alert-success rounded-0 shadow-sm mb-0">{{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ session('error') }}</div>
        @endif

        {{-- Judul & breadcrumb topbar: shell memindahkan <h1> dan .breadcrumb pertama ke topbar
             (tesys-shell.js hoistPageTitle); disembunyikan secara visual. --}}
        <div class="tw:sr-only">
            <h1>Tutup Periode</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Tutup Periode</li>
            </ol>
        </div>

        <x-tw.page-header title="Rekap Periode"
            description="Rekap transaksi finance per periode — Treasury, Invoice, Collection, Cash Receipt, Cost Request, dan Purchase Order.">
            <x-slot:actions>
                <form method="GET" action="{{ route('closing.index') }}" class="d-flex align-items-center" style="gap:8px;">
                    <label class="mb-0 text-muted" style="font-size:11px;">Periode</label>
                    <select name="periode" class="form-control form-control-sm" style="width:auto;" onchange="this.form.submit()">
                        @foreach($periods as $value => $label)
                            <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            </x-slot:actions>
        </x-tw.page-header>

        @php
            $status = $closing->status ?? 'BELUM DIREKAP';
        @endphp

        <x-tw.card padding="sm">
            <div class="d-flex align-items-center flex-wrap" style="gap:12px;">
                <div>
                    <div class="text-muted" style="font-size:11px;">Periode</div>
                    <strong style="font-size:15px;">
                        {{ $startDate->translatedFormat('d M Y') }} &ndash; {{ $endDate->translatedFormat('d M Y') }}
                    </strong>
                </div>
                <div>
                    <div class="text-muted" style="font-size:11px;">Status</div>
                    <x-status :status="$status" context="period-closing" />
                </div>
                @if($closing)
                <div>
                    <div class="text-muted" style="font-size:11px;">Kode</div>
                    <strong style="font-size:13px;">{{ $closing->code }}</strong>
                </div>
                @endif
                @if($closing?->closed_at)
                <div>
                    <div class="text-muted" style="font-size:11px;">Ditutup Oleh</div>
                    <strong style="font-size:13px;">{{ strtoupper($closing->closedBy->username ?? '-') }}</strong>
                    <span class="text-muted" style="font-size:11px;">&middot; {{ $closing->closed_at->format('d M Y H:i') }}</span>
                </div>
                @endif

                <div class="ml-auto d-flex flex-wrap" style="gap:6px;">
                    @if(!$closing || $closing->isDraft())
                    <form action="{{ route('closing.build') }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="period" value="{{ $period }}">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fas fa-sync-alt mr-1"></i> {{ $closing ? 'Susun Ulang Rekap' : 'Susun Rekap' }}
                        </button>
                    </form>
                    @endif

                    @if($closing && $closing->isDraft() && $closing->details->isNotEmpty())
                    <form action="{{ route('closing.close', $closing->id) }}" method="POST" class="d-inline js-confirm"
                          data-title="Tutup periode {{ $period }}?"
                          data-text="Setelah ditutup, rekap tidak bisa disusun ulang sebelum periode dibuka kembali.">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success">
                            <i class="fas fa-lock mr-1"></i> Tutup Periode
                        </button>
                    </form>
                    @endif

                    @if($closing?->isClosed())
                    <button type="button" class="btn btn-sm btn-outline-warning" data-toggle="modal" data-target="#modalReopen">
                        <i class="fas fa-lock-open mr-1"></i> Buka Kembali
                    </button>
                    @endif
                </div>
            </div>
        </x-tw.card>

        {{-- PERIOD CONTROL — perbandingan rekap tersimpan vs keadaan data
             sekarang. Inilah yang menjawab "ada transaksi yang terlewat?". --}}
        @if($closing && $drift['has_difference'])
        <div class="alert alert-warning rounded-0 shadow-sm mb-0" style="font-size:12px;">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            <strong>Rekap tidak lagi cocok dengan data.</strong>
            @if($drift['missing']->isNotEmpty())
                {{ $drift['missing']->count() }} transaksi belum masuk rekap.
            @endif
            @if($drift['stale']->isNotEmpty())
                {{ $drift['stale']->count() }} baris rekap sudah tidak ada di data.
            @endif
            @if($closing->isDraft())
                Susun ulang rekap sebelum menutup periode.
            @else
                Periode ini sudah ditutup — buka kembali kalau rekapnya perlu diperbarui.
            @endif
        </div>
        @endif

        <x-stat-cards :cards="[
            ['Total Transaksi', number_format($liveSummary['total_transaction']), 'primary', 'fas fa-list-ol'],
            ['Total Nilai', 'Rp ' . number_format($liveSummary['total_value'], 0, ',', '.'), 'info', 'fas fa-coins'],
            ['Sudah Direkap', $closing ? number_format($closing->total_transaction) : '0', 'success', 'fas fa-clipboard-check'],
            ['Selisih', $closing ? number_format($drift['missing']->count() + $drift['stale']->count()) : '—', $drift['has_difference'] ? 'danger' : 'secondary', 'fas fa-not-equal'],
        ]" />

        {{-- RINGKASAN PER MODUL --}}
        <x-tw.card padding="sm">
            <h6 class="ts-dw-section-title"><i class="fas fa-layer-group mr-1"></i> Rekap per Modul</h6>
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col">Modul</th>
                                <th scope="col" class="text-center">Transaksi (data saat ini)</th>
                                <th scope="col" class="text-right">Nilai (data saat ini)</th>
                                <th scope="col" class="text-center">Transaksi (rekap)</th>
                                <th scope="col" class="text-right">Nilai (rekap)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($liveSummary['by_module'] as $key => $row)
                            @php
                                $recorded = $closing
                                    ? $closing->details->where('source_module', $key)
                                    : collect();
                                $mismatch = $closing && $recorded->count() !== $row['count'];
                            @endphp
                            <tr @class(['table-warning' => $mismatch])>
                                <td>{{ $row['label'] }}</td>
                                <td class="text-center">{{ number_format($row['count']) }}</td>
                                <td class="text-right">{{ (int) $row['amount'] === 0 ? '-' : number_format($row['amount'], 0, ',', '.') }}</td>
                                <td class="text-center">{{ $closing ? number_format($recorded->count()) : '—' }}</td>
                                <td class="text-right">{{ $closing ? ((int) $recorded->sum('amount') === 0 ? '-' : number_format($recorded->sum('amount'), 0, ',', '.')) : '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr style="background:var(--surface-2);font-weight:700;">
                                <th>Total</th>
                                <th class="text-center">{{ number_format($liveSummary['total_transaction']) }}</th>
                                <th class="text-right">{{ number_format($liveSummary['total_value'], 0, ',', '.') }}</th>
                                <th class="text-center">{{ $closing ? number_format($closing->total_transaction) : '—' }}</th>
                                <th class="text-right">{{ $closing ? number_format($closing->total_value, 0, ',', '.') : '—' }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
        </x-tw.card>

        {{-- DETAIL REKAP --}}
        @if($closing && $closing->details->isNotEmpty())
        <x-tw.card padding="sm">
            <div class="custom-toolbar mb-3 d-flex align-items-center flex-wrap" style="gap:8px;">
                <h6 class="ts-dw-section-title mb-0 mr-auto"><i class="fas fa-stream mr-1"></i> Detail Rekap</h6>
                <div class="d-flex align-items-center" style="gap:6px;">
                    <select id="closingModuleFilter" class="form-control form-control-sm" style="width:auto;font-size:11px;">
                        <option value="">Semua Modul</option>
                        @foreach($modules as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="search" id="closingSearch" class="form-control form-control-sm"
                           placeholder="Cari referensi / deskripsi" style="width:220px;font-size:11px;">
                </div>
            </div>
                <div class="table-responsive">
                    <table id="tblClosingDetail" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" class="text-nowrap">Modul</th>
                                <th scope="col" class="text-nowrap">Referensi</th>
                                <th scope="col" class="text-nowrap">Tanggal</th>
                                <th scope="col">Deskripsi</th>
                                <th scope="col" class="text-center text-nowrap">Status</th>
                                <th scope="col" class="text-right text-nowrap">Nilai</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($closing->details as $d)
                            <tr data-module="{{ $d->source_module }}">
                                <td class="text-nowrap">{{ $modules[$d->source_module] ?? $d->source_module }}</td>
                                <td class="text-nowrap">
                                    @if($d->source_url)
                                        <a href="{{ $d->source_url }}" target="_blank">{{ $d->reference_number }}</a>
                                    @else
                                        {{ $d->reference_number }}
                                    @endif
                                </td>
                                <td class="text-nowrap">{{ $d->transaction_date?->format('d M Y') ?? '-' }}</td>
                                <td>{{ $d->description }}</td>
                                <td class="text-center">
                                    <span class="badge badge-secondary px-2 py-1">{{ $d->status_at_closing ?: '-' }}</span>
                                </td>
                                <td class="text-right text-nowrap ts-col-money">{{ (int) $d->amount === 0 ? '-' : number_format($d->amount, 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
        </x-tw.card>
        @elseif(!$closing)
        <x-tw.card padding="sm">
            <x-empty title="Periode ini belum direkap"
                     subtitle="Klik Susun Rekap untuk mengumpulkan transaksi finance pada periode ini."
                     icon="fas fa-clipboard-list" />
        </x-tw.card>
        @endif

        {{-- RIWAYAT --}}
        @if($history->isNotEmpty())
        <x-tw.card padding="sm">
            <h6 class="ts-dw-section-title"><i class="fas fa-history mr-1"></i> Riwayat Penutupan</h6>
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col">Periode</th>
                                <th scope="col" class="text-center">Status</th>
                                <th scope="col" class="text-center">Transaksi</th>
                                <th scope="col" class="text-right">Total Nilai</th>
                                <th scope="col">Ditutup Oleh</th>
                                <th scope="col" class="text-nowrap">Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($history as $h)
                            <tr>
                                <td>
                                    <a href="{{ route('closing.index', ['periode' => $h->period]) }}">
                                        {{ \Carbon\Carbon::createFromFormat('Y-m', $h->period)->translatedFormat('F Y') }}
                                    </a>
                                </td>
                                <td class="text-center"><x-status :status="$h->status" context="period-closing" /></td>
                                <td class="text-center">{{ number_format($h->total_transaction) }}</td>
                                <td class="text-right">{{ number_format($h->total_value, 0, ',', '.') }}</td>
                                <td>{{ strtoupper($h->closedBy->username ?? '-') }}</td>
                                <td class="text-nowrap">{{ $h->closed_at?->format('d M Y H:i') ?? '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
        </x-tw.card>
        @endif

      </div>
    </div>
</div>

@if($closing?->isClosed())
<div class="modal fade" id="modalReopen" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:var(--radius-lg);overflow:hidden;">
            <div class="modal-header" style="background:var(--charcoal);border-bottom:3px solid var(--accent);">
                <h5 class="modal-title text-white"><i class="fas fa-lock-open mr-2" style="color:var(--accent);"></i>Buka Kembali Periode</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="{{ route('closing.reopen', $closing->id) }}" method="POST">
                @csrf
                <div class="modal-body" style="padding:24px 28px;">
                    <p class="text-muted" style="font-size:12px;">
                        Periode yang dibuka kembali bisa disusun ulang rekapnya. Alasannya dicatat dan tetap terlihat di riwayat.
                    </p>
                    <div class="form-group mb-0">
                        <label class="font-weight-600">Alasan <span class="text-danger">*</span></label>
                        <input type="text" name="reopen_reason" class="form-control" maxlength="255" required>
                    </div>
                </div>
                <div class="modal-footer" style="background:var(--surface-2);border-top:1px solid var(--border);">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning">Buka Kembali</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
<style>
.font-weight-600 { font-weight:600; }
.table td, .table th { vertical-align:middle !important; }
#tblClosingDetail { width:100% !important; }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    /* Konfirmasi aksi tutup/susun-ulang didaftarkan lebih dulu dan di luar
       guard tabel — periode yang belum direkap tidak punya tabel detail, tapi
       tombolnya tetap ada. */
    $('.js-confirm').on('submit', function (e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: $(form).data('title') || 'Lanjutkan?',
            text: $(form).data('text') || '',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: 'var(--danger)',
            cancelButtonColor: 'var(--text-muted)',
            confirmButtonText: 'Ya, Lanjutkan',
            cancelButtonText: 'Batal'
        }).then(function (r) { if (r.isConfirmed) form.submit(); });
    });

    var $table = $('#tblClosingDetail');
    if (!$table.length) return;

    var table = $table.DataTable({
        responsive: true, autoWidth: false, pageLength: 25, lengthChange: true,
        searching: true, ordering: true, order: [[2, 'desc']],
        dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12"i><"col-md-6 col-12 d-flex justify-content-md-end"lp>>',
        language: {
            zeroRecords: 'Data tidak ditemukan',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            lengthMenu: 'Page Size _MENU_',
            paginate: { previous: 'Previous', next: 'Next' }
        }
    });

    $('#closingSearch').on('keyup', function () { table.search(this.value).draw(); });

    /* Filter modul lewat mesin filter DataTables — bukan menyembunyikan baris
       dengan CSS — supaya paging dan penghitungan "menampilkan X dari Y" tetap
       benar setelah difilter. Didaftarkan SEKALI; handler change hanya memicu
       gambar ulang. */
    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable !== $table[0]) return true;

        var value = $('#closingModuleFilter').val();
        if (!value) return true;

        return $(table.row(dataIndex).node()).data('module') === value;
    });

    $('#closingModuleFilter').on('change', function () { table.draw(); });
});
</script>
@endpush
