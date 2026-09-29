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

        {{-- Kartu dibungkus wadah ber-id: isinya dihitung ulang dari baris yang
             lolos filter setiap kali tabel digambar ulang, supaya kartu dan
             tabel tidak pernah bercerita hal berbeda. --}}
        <div id="dnCards">
        <x-stat-cards :cards="[
            ['Total Surat Jalan', $summary['total'], 'primary', 'fas fa-truck'],
            ['Sent', $summary['sent'], 'warning', 'fas fa-paper-plane'],
            ['Received', $summary['received'], 'success', 'fas fa-check-double'],
        ]" />
        </div>

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
                {{--
                     Penyaringan dikerjakan di browser, bentuknya disamakan
                     dengan Cash Receipt: kotak cari disambungkan ke pencarian
                     DataTables, Status/Customer memakai dropdown centang
                     TesysColumnFilter. Tidak ada tombol "Terapkan" dan tidak
                     ada muat ulang halaman — mengetik/mencentang langsung
                     menyaring, dan kartu di atas ikut dihitung ulang.
                --}}
                <div class="custom-toolbar mb-3 d-flex align-items-center justify-content-start flex-wrap" style="gap:8px;">

                    <div class="toolbar-row">
                        <span class="toolbar-label">Cari :</span>
                        <div class="toolbar-input">
                            <input type="search" id="dnSearch" class="form-control form-control-sm"
                                   placeholder="Kode surat jalan / kode invoice / no. PO / customer">
                        </div>
                    </div>

                    <div id="dnRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>

                    <button type="button" id="btnClearFilterDN" class="btn btn-outline-secondary btn-sm"
                            title="Clear Filter" aria-label="Clear Filter">
                        <i class="fas fa-eraser"></i>
                    </button>
                </div>

                <div class="table-responsive">
                    <table id="tblDeliveryNote" class="table table-bordered table-hover table-striped mb-0">
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
                            @foreach($deliveryNotes as $dn)
                            {{-- data-* dibaca dua pihak: TesysColumnFilter untuk
                                 menyaring, dan penghitung kartu untuk menjumlah
                                 baris yang tersisa. --}}
                            <tr data-status="{{ $dn->status_label }}"
                                data-customer="{{ $dn->customer->name ?? '-' }}">
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
                                    {{-- Kode invoice sisanya dan No. PO ikut bisa dicari
                                         walau tidak ditampilkan — kolomnya akan penuh
                                         kalau semuanya dicetak. --}}
                                    <span class="d-none">{{ $dn->items->pluck('invoice.code')->filter()->implode(' ') }} {{ $dn->items->pluck('invoice.poNumber.po_number')->filter()->implode(' ') }}</span>
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
/* Blok "Cari :" di bilah filter tidak punya gaya sendiri di halaman ini,
   sehingga label jatuh di atas input dan barisnya terlihat tidak sejajar
   dengan dropdown & tombol di sebelahnya. Aturannya disamakan dengan
   halaman index lain (Account, Budget Plan): label dan input satu baris,
   rata tengah secara vertikal. */
.toolbar-row   { display:flex; align-items:center; gap:8px; }
.toolbar-label { min-width:50px; font-weight:600; color:var(--text-dim); white-space:nowrap; margin-bottom:0; }
.toolbar-input { min-width:260px; }
.custom-toolbar .toolbar-input .form-control { width:100%; }
.table td, .table th { vertical-align:middle !important; }

@media (max-width: 575.98px) {
    .toolbar-row   { width:100%; }
    .toolbar-input { flex:1; min-width:0; }
}
</style>
@endpush

@push('scripts')
<script>
$(function () {

    var table = $('#tblDeliveryNote').DataTable({
        responsive: { details: { type: 'inline' } },
        autoWidth: false, pageLength: 25, lengthChange: true, searching: true,
        order: [], // urutan dari server (terbaru di atas) dipertahankan
        columnDefs: [
            { targets: [6], orderable: false },
            { responsivePriority: 1, targets: [0, 1, -1] },
        ],
        dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex justify-content-end"lp>>',
        language: {
            lengthMenu: 'Page Size _MENU_',
            emptyTable: '<div class="ts-empty ts-empty-compact">'
                + '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-truck"></i></span>'
                + '<p class="ts-empty-title">Belum ada Surat Jalan</p>'
                + '<p class="ts-empty-msg">Surat Jalan terbit dari invoice yang sudah APPROVED.</p></div>',
            zeroRecords: '<div class="ts-empty ts-empty-compact">'
                + '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-search"></i></span>'
                + '<p class="ts-empty-title">Tidak ada yang cocok</p>'
                + '<p class="ts-empty-msg">Tidak ada Surat Jalan yang cocok dengan filter yang sedang aktif.</p></div>',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            paginate: { previous: 'Previous', next: 'Next' }
        }
    });

    /* Kotak cari sendiri (kotak bawaan DataTables disembunyikan lewat dom). */
    $('#dnSearch').on('keyup search', function () { table.search(this.value).draw(); });

    /* Dropdown centang. Status memakai daftar TETAP supaya nilai yang belum
       dipakai satu baris pun tetap terlihat — berguna justru saat seseorang
       ingin memastikan memang belum ada. Customer disimpulkan dari baris. */
    TesysColumnFilter.initRowFilters('#tblDeliveryNote', '#dnRowFilters', [
        { key: 'status',   label: 'Status',   options: ['Sent', 'Received'], search: false },
        { key: 'customer', label: 'Customer' },
    ]);

    $('#btnClearFilterDN').on('click', function () {
        TesysColumnFilter.clearAll('#tblDeliveryNote');
        $('#dnSearch').val('');
        table.search('').draw();
    });

    /*
     | Kartu ringkasan dihitung ulang dari baris yang LOLOS filter (bukan hanya
     | yang tampil di halaman aktif), jadi angka di atas selalu menggambarkan
     | isi tabel di bawahnya. Inilah yang membuat penyaringan di browser aman
     | dilakukan — dulu penyaringan dipaksa lewat reload demi hal ini.
     */
    function refreshCards() {
        var total = 0, sent = 0, received = 0;

        table.rows({ search: 'applied' }).nodes().each(function (tr) {
            total += 1;
            if (tr.getAttribute('data-status') === 'Received') { received += 1; } else { sent += 1; }
        });

        var $nilai = $('#dnCards .ts-kpi-value');
        $nilai.eq(0).text(total);
        $nilai.eq(1).text(sent);
        $nilai.eq(2).text(received);
    }

    table.on('draw', refreshCards);
});
</script>
@endpush
