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

        {{-- Kartu dibungkus wadah ber-id: isinya dihitung ulang dari baris yang
             terlihat setiap kali filter tabel berubah, supaya kartu dan tabel
             tidak pernah bercerita hal berbeda. --}}
        <div id="crCards">
        <x-stat-cards :cards="[
            ['Bukti Terima', $summary['count'], 'secondary', 'fas fa-receipt'],
            ['Total Diterima', 'Rp ' . number_format($summary['total'], 0, ',', '.'), 'success', 'fas fa-hand-holding-usd'],
            ['Invoice Tertutup', $summary['invoice_count'], 'primary', 'fas fa-file-invoice'],
            ['Belum Teralokasi', 'Rp ' . number_format($summary['unallocated'], 0, ',', '.'), $summary['unallocated'] > 0 ? 'warning' : 'secondary', 'fas fa-unlink'],
        ]" />
        </div>

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
                     Penyaringan dikerjakan di browser: kotak cari disambungkan
                     ke pencarian DataTables, dan Status/Customer memakai
                     dropdown centang TesysColumnFilter — bentuk yang sama
                     dengan halaman index lain. Tidak ada tombol "Terapkan" dan
                     tidak ada muat ulang halaman: mencentang langsung menyaring.
                --}}
                <div class="custom-toolbar mb-3 d-flex align-items-center justify-content-start flex-wrap" style="gap:8px;">

                    <div class="toolbar-row">
                        <span class="toolbar-label">Cari :</span>
                        <div class="toolbar-input">
                            <input type="search" id="crSearch" class="form-control form-control-sm"
                                   placeholder="Kode / referensi / customer">
                        </div>
                    </div>

                    <div id="crRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>

                    <button type="button" id="btnClearFilterCR" class="btn btn-outline-secondary btn-sm"
                            title="Clear Filter" aria-label="Clear Filter">
                        <i class="fas fa-eraser"></i>
                    </button>
                </div>

                <div class="table-responsive">
                    <table id="tblCashReceipt" class="table table-bordered table-hover table-striped mb-0">
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
                            @foreach($headers as $h)
                            @php
                                $allocated   = $h->allocatedAmount();
                                $unallocated = $h->unallocatedAmount();
                                $stBadge = ['DRAFT' => 'badge-warning', 'ALLOCATED' => 'badge-success', 'CANCELLED' => 'badge-danger'][$h->status] ?? 'badge-secondary';
                            @endphp
                            {{-- data-* dibaca dua pihak: TesysColumnFilter untuk
                                 menyaring, dan penghitung kartu untuk menjumlah
                                 baris yang tersisa. --}}
                            <tr data-status="{{ $h->status }}"
                                data-customer="{{ $h->customer->name ?? '-' }}"
                                data-total="{{ (int) $h->total_amount }}"
                                data-invoices="{{ $h->details->count() }}"
                                data-unallocated="{{ max(0, $unallocated) }}">
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
                                    {{-- Sama dengan tombol Hapus di halaman detail: hanya DRAFT yang belum punya alokasi
                                         (CashReceiptController::destroy menolak yang sudah teralokasi). --}}
                                    @if($h->status === 'DRAFT' && $h->details->isEmpty())
                                    <form action="{{ route('cash-receipt.destroy', $h->id) }}" method="POST" class="js-confirm-delete d-inline"
                                          data-title="Hapus penerimaan {{ $h->code }}?" data-text="Belum ada alokasi, jadi aman dihapus.">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
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

/* Bilah filter: .toolbar-row/.toolbar-input belum punya definisi global, jadi
   tanpa ini label "Cari :" jatuh ke baris sendiri di atas input. Nilainya
   disamakan dengan index lain (Account, Bank, Access Group). */
.toolbar-row   { display:flex; align-items:center; gap:8px; }
.toolbar-label { min-width:50px; font-weight:600; color:var(--text-dim); white-space:nowrap; margin-bottom:0; }
.toolbar-input { min-width:230px; }
.custom-toolbar .toolbar-input .form-control { width:100%; }

@media (max-width: 575.98px) {
    .toolbar-row   { width:100%; }
    .toolbar-input { flex:1; min-width:0; }
}
</style>
@endpush

@push('scripts')
<script>
$(function () {

    var table = $('#tblCashReceipt').DataTable({
        responsive: { details: { type: 'inline' } },
        autoWidth: false, pageLength: 25, lengthChange: true, searching: true,
        order: [], // urutan dari server (terbaru di atas) dipertahankan
        columnDefs: [
            { targets: [7], orderable: false },
            { responsivePriority: 1, targets: [0, 2, -1] },
        ],
        dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex justify-content-end"lp>>',
        language: {
            lengthMenu: 'Page Size _MENU_',
            emptyTable: '<div class="ts-empty ts-empty-compact">'
                + '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-receipt"></i></span>'
                + '<p class="ts-empty-title">Belum ada penerimaan</p>'
                + '<p class="ts-empty-msg">Belum ada bukti terima yang tercatat.</p></div>',
            zeroRecords: '<div class="ts-empty ts-empty-compact">'
                + '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-search"></i></span>'
                + '<p class="ts-empty-title">Tidak ada yang cocok</p>'
                + '<p class="ts-empty-msg">Tidak ada penerimaan yang cocok dengan filter yang sedang aktif.</p></div>',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            paginate: { previous: 'Previous', next: 'Next' }
        }
    });

    /* Hapus draft — delegasi, karena baris bisa dipindah/dirender ulang oleh DataTables (mode responsive). */
    $('#tblCashReceipt').on('submit', '.js-confirm-delete', function (e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: $(form).data('title') || 'Hapus penerimaan?',
            text: $(form).data('text') || '',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#9a3030',
            cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(function (r) { if (r.isConfirmed) form.submit(); });
    });

    /* Kotak cari sendiri (kotak bawaan DataTables disembunyikan lewat dom). */
    $('#crSearch').on('keyup search', function () { table.search(this.value).draw(); });

    /* Dropdown centang. Status memakai daftar TETAP supaya nilai yang belum
       dipakai satu baris pun tetap terlihat — berguna justru saat seseorang
       ingin memastikan memang belum ada. Customer disimpulkan dari baris. */
    TesysColumnFilter.initRowFilters('#tblCashReceipt', '#crRowFilters', [
        { key: 'status',   label: 'Status',   options: @json(\App\Models\CashReceiptHeader::STATUSES), search: false },
        { key: 'customer', label: 'Customer' },
    ]);

    $('#btnClearFilterCR').on('click', function () {
        TesysColumnFilter.clearAll('#tblCashReceipt');
        $('#crSearch').val('');
        table.search('').draw();
    });

    /*
     | Kartu ringkasan dihitung ulang dari baris yang LOLOS filter (bukan hanya
     | yang tampil di halaman aktif), jadi angka di atas selalu menggambarkan
     | isi tabel di bawahnya. Inilah yang membuat penyaringan di browser aman
     | dilakukan — dulu penyaringan dipaksa lewat reload demi hal ini.
     */
    var rupiah = function (n) { return 'Rp ' + (n || 0).toLocaleString('id-ID'); };

    function refreshCards() {
        var jumlah = 0, total = 0, invoice = 0, sisa = 0;

        table.rows({ search: 'applied' }).nodes().each(function (tr) {
            jumlah  += 1;
            total   += parseInt(tr.getAttribute('data-total') || 0, 10);
            invoice += parseInt(tr.getAttribute('data-invoices') || 0, 10);
            sisa    += parseInt(tr.getAttribute('data-unallocated') || 0, 10);
        });

        var $nilai = $('#crCards .ts-kpi-value');
        $nilai.eq(0).text(jumlah);
        $nilai.eq(1).text(rupiah(total));
        $nilai.eq(2).text(invoice);
        $nilai.eq(3).text(rupiah(sisa));

        // Nada ikon "Belum Teralokasi" ikut keadaan, seperti versi server-nya.
        var $ic = $('#crCards .ts-kpi').eq(3).find('.ts-kpi-ic');
        $ic.toggleClass('ts-tone-attention', sisa > 0).toggleClass('ts-tone-neutral', sisa <= 0);
    }

    table.on('draw', refreshCards);
});
</script>
@endpush
