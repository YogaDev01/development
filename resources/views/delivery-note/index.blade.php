@extends('layouts.app')

@section('title', 'Surat Jalan Invoice')

@section('content')

<div class="content">
    <div class="container-fluid">
      <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        {{-- Judul & breadcrumb topbar: shell memindahkan <h1> dan .breadcrumb pertama ke topbar
             (tesys-shell.js hoistPageTitle); disembunyikan secara visual. --}}
        <div class="tw:sr-only">
            <h1>Surat Jalan Invoice</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Surat Jalan Invoice</li>
            </ol>
        </div>

        @php $bolehBuatDN = Auth::user()->hasRole(['superadmin','admin','finance','finance_manager','collection']); @endphp
        <x-tw.page-header title="Daftar Surat Jalan"
            description="Surat Jalan yang terbit dari invoice APPROVED, beserta status kirim dan terimanya.">
            @if($bolehBuatDN)
            <x-slot:actions>
                <x-tw.button href="{{ route('delivery-note.create') }}" class="ts-add-btn tw:max-sm:min-h-12"><i class="fas fa-plus" aria-hidden="true"></i> Buat Surat Jalan</x-tw.button>
            </x-slot:actions>
            @endif
        </x-tw.page-header>

        @if($bolehBuatDN)
        {{-- Mobile: tombol di header disembunyikan dan diganti tombol melayang (tesys-ui-refine.css). --}}
        <a href="{{ route('delivery-note.create') }}" class="ts-fab" aria-label="Buat Surat Jalan" title="Buat Surat Jalan">
            <i class="fas fa-plus" aria-hidden="true"></i>
        </a>
        @endif

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

        {{-- Kartu tunggal: toolbar + tabel + paginasi (pola Cost Request). --}}
        <x-tw.card padding="sm">
                {{--
                     Penyaringan dikerjakan di browser: kotak cari disambungkan ke pencarian
                     DataTables, Status/Customer memakai dropdown centang TesysColumnFilter.
                     Tidak ada tombol "Terapkan" dan tidak ada muat ulang halaman — mengetik/
                     mencentang langsung menyaring, dan kartu di atas ikut dihitung ulang.
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

                    {{-- Filter "Last Update Time" (TesysDateRange, server-side; kosong = semua data). --}}
                    <div class="d-flex align-items-center flex-wrap dn-date-range" style="gap:6px;">
                            <input type="text" id="dnDateRange" class="form-control form-control-sm tdr-input" readonly aria-label="Rentang tanggal">
                            <input type="hidden" id="dnDateFrom" value="{{ $dateFrom ?? '' }}">
                            <input type="hidden" id="dnDateTo" value="{{ $dateTo ?? '' }}">
                        </div>

                    <button type="button" id="btnClearFilterDN" class="btn btn-outline-secondary btn-sm"
                            title="Clear Filter" aria-label="Clear Filter">
                        <i class="fas fa-eraser"></i>
                    </button>

                    <x-tw.button href="{{ route('delivery-note.export') }}" id="btnExportDN" variant="secondary" size="sm">
                        <i class="fas fa-file-excel" aria-hidden="true"></i> Export Excel
                    </x-tw.button>
                </div>

                <div class="table-responsive">
                    <table id="tblDeliveryNote" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" class="text-nowrap">Kode Surat Jalan</th>
                                <th scope="col">Customer</th>
                                <th scope="col" class="text-center text-nowrap">Jml Invoice</th>
                                <th scope="col">Invoice / No. PO</th>
                                <th scope="col" class="text-nowrap">Tanggal Kirim</th>
                                <th scope="col" class="text-nowrap">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($deliveryNotes as $dn)
                            {{-- data-* dibaca dua pihak: TesysColumnFilter untuk
                                 menyaring, dan penghitung kartu untuk menjumlah
                                 baris yang tersisa. --}}
                            <tr data-id="{{ $dn->id }}"
                                data-status="{{ $dn->status_label }}"
                                data-customer="{{ $dn->customer->name ?? '-' }}"
                                data-href="{{ route('delivery-note.show', $dn->id) }}">
                                <td class="text-nowrap">
                                    {{-- Klik kode = salin; detail dibuka lewat klik baris. --}}
                                    <button type="button" class="ts-copy-code" data-code="{{ $dn->code }}" title="Klik untuk menyalin kode">{{ $dn->code }}</button>
                                </td>
                                <td class="ts-col-desc">{{ $dn->customer->name ?? '-' }}</td>
                                <td class="text-center">{{ $dn->items_count }}</td>
                                <td>
                                    {{-- Kode invoice ditampilkan langsung supaya hasil
                                         pencarian bisa dipastikan tanpa membuka detailnya
                                         satu per satu. Dibatasi 3; sisanya diringkas. --}}
                                    {{ $dn->items->take(3)->map(fn ($it) => $it->invoice->code ?? '-')->implode(', ') ?: '-' }}@if($dn->items_count > 3)
                                        <span class="text-muted" style="font-size:11px;">+{{ $dn->items_count - 3 }} lagi</span>
                                    @endif
                                    {{-- Kode invoice sisanya dan No. PO ikut bisa dicari
                                         walau tidak ditampilkan — kolomnya akan penuh
                                         kalau semuanya dicetak. --}}
                                    <span class="d-none">{{ $dn->items->pluck('invoice.code')->filter()->implode(' ') }} {{ $dn->items->pluck('invoice.poNumber.po_number')->filter()->implode(' ') }}</span>
                                </td>
                                <td class="text-nowrap" data-order="{{ $dn->sent_at?->format('Y-m-d') }}">{{ $dn->sent_at ? $dn->sent_at->format('d/m/Y') : '-' }}</td>
                                <td class="text-nowrap"><span class="badge badge-{{ $dn->status_color }} px-2 py-1">{{ $dn->status_label }}</span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
        </x-tw.card>
      </div>
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
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
#tblDeliveryNote tbody tr[data-href] { cursor:pointer; }

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
            { responsivePriority: 1, targets: [0, 1] },
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

    /* ── FILTER "LAST UPDATE TIME" ─────────────────────────────────────────
    | Komponen bersama TesysDateRange (sama seperti Cost Request / Treasury) dengan
    | dropdown rentang cepat. Filternya server-side (?date_from=&date_to=&range=)
    | pada kolom updated_at; TANPA pilihan = semua data (tidak ada batas bawaan).
    | Pilihan cepat langsung memuat ulang; "Custom..." memakai tombol Terapkan. */
    (function () {
        var siap = false;
        function muatUlang(from, to, range) {
            var url = new URL(window.location.href);
            if (from && to) {
                url.searchParams.set('date_from', from);
                url.searchParams.set('date_to', to);
                url.searchParams.set('range', range || 'custom');
            } else {
                url.searchParams.delete('date_from');
                url.searchParams.delete('date_to');
                url.searchParams.delete('range');
            }
            window.location.href = url.toString();
        }

        var periode = (typeof TesysDateRange === 'object')
            ? TesysDateRange.init({
                input: '#dnDateRange', from: '#dnDateFrom', to: '#dnDateTo',
                quick: true, quickLabel: 'Last Update Time',
                onApply: function (from, to, range) {
                    // Nilai awal ditulis saat inisialisasi; "custom" menunggu tombol Terapkan.
                    if (!siap || range === 'custom') return;
                    muatUlang(from, to, range);
                },
                onClear: function () { muatUlang('', '', ''); },
                applyButton: { label: 'Terapkan', onClick: function (from, to) { muatUlang(from, to, 'custom'); } }
            })
            : null;
        siap = true;

        if (!periode) {
            console.warn('TesysDateRange tidak tersedia - filter tanggal beralih ke mode input manual.');
            $('#dnDateRange').remove();
            $('#dnDateFrom, #dnDateTo').attr('type', 'text')
                .addClass('form-control form-control-sm').attr('placeholder', 'YYYY-MM-DD')
                .on('change', function () { muatUlang($('#dnDateFrom').val(), $('#dnDateTo').val(), 'custom'); });
        }
    })();

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

    /* Export Excel mengikuti filter yang SEDANG aktif — lihat public/js/tesys-export-filtered.js. */
    if (window.TesysExportFiltered) {
        TesysExportFiltered.wire('#btnExportDN', table, '{{ route('delivery-note.export') }}');
    }

    /* Klik di mana saja pada baris membuka detail surat jalan (pola Cost Request). Kontrol di
       dalam baris (kode yang disalin, tautan, kontrol responsif DataTables) bekerja sendiri. */
    $(document).on('click', '#tblDeliveryNote tbody tr[data-href]', function (e) {
        if ($(e.target).closest('a, button, input, select, textarea, label, td.dtr-control').length) return;
        if (window.getSelection && String(window.getSelection()).length) return;
        window.location.href = $(this).attr('data-href');
    });
});
</script>
@endpush
