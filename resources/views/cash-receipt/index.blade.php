@extends('layouts.app')

@section('title', 'Cash Receipt')

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
            <h1>Cash Receipt</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Cash Receipt</li>
            </ol>
        </div>

        <x-tw.page-header title="Daftar Penerimaan"
            description="Batch penerimaan dana atas kumpulan invoice — cerminan Treasury, yang membatch pembayaran Cost Request.">
            <x-slot:actions>
                <x-tw.button href="{{ route('cash-receipt.create') }}" class="ts-add-btn tw:max-sm:min-h-12"><i class="fas fa-plus" aria-hidden="true"></i> Catat Penerimaan</x-tw.button>
            </x-slot:actions>
        </x-tw.page-header>

        {{-- Mobile: tombol di header disembunyikan dan diganti tombol melayang (tesys-ui-refine.css). --}}
        <a href="{{ route('cash-receipt.create') }}" class="ts-fab" aria-label="Catat Penerimaan" title="Catat Penerimaan">
            <i class="fas fa-plus" aria-hidden="true"></i>
        </a>

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

        {{-- Kartu tunggal: toolbar + tabel + paginasi (pola Cost Request). --}}
        <x-tw.card padding="sm">
                {{--
                     Rentang tanggal terima dikerjakan di SERVER (muat ulang dengan
                     ?date_from=&date_to=&range=); kartu, daftar, dan footer mengikuti
                     rentang itu. Kotak cari, Status, dan Customer tetap di browser:
                     kotak cari disambungkan ke pencarian DataTables, Status/Customer
                     memakai dropdown centang TesysColumnFilter.
                --}}
                @php
                    $rangeAktif = $range;
                @endphp
                <div class="custom-toolbar mb-3 d-flex align-items-center justify-content-start flex-wrap" style="gap:8px;">

                    <div class="toolbar-row">
                        <span class="toolbar-label">Cari :</span>
                        <div class="toolbar-input">
                            <input type="search" id="crSearch" class="form-control form-control-sm"
                                   placeholder="Kode / customer">
                        </div>
                    </div>

                    <div id="crRowFilters" class="d-flex flex-wrap" style="gap:8px;"></div>

                    <button type="button" id="btnClearFilterCR" class="btn btn-outline-secondary btn-sm"
                            title="Clear Filter" aria-label="Clear Filter">
                        <i class="fas fa-eraser"></i>
                    </button>

                    <div class="cr-pop cr-col-dropdown">
                        <button type="button" class="btn btn-outline-secondary btn-sm cr-pop-toggle">
                            <i class="fas fa-columns mr-1"></i> Kolom
                        </button>
                        <div class="cr-pop-menu p-2" style="min-width:200px;max-height:340px;overflow-y:auto;">
                            @foreach(['tanggal' => 'Tanggal', 'tagihan' => 'Tagihan', 'pph' => 'Total PPh', 'invoice' => 'Invoice'] as $key => $label)
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input cr-col-vis-toggle" id="crColVis_{{ $key }}" data-column="{{ $key }}">
                                <label class="custom-control-label" for="crColVis_{{ $key }}" style="font-size:12px;">{{ $label }}</label>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="ml-auto d-flex align-items-center flex-wrap" style="gap:8px;">
                        <div id="crCustomRange" class="{{ $rangeAktif === 'custom' ? 'd-flex' : 'd-none' }} align-items-center flex-wrap" style="gap:6px;">
                            <span class="text-muted" style="font-size:12px;">Periode</span>
                            <div class="d-flex align-items-center cr-date-range" style="gap:4px;">
                                <input type="text" id="crDateRange" class="form-control form-control-sm tdr-input" readonly>
                                <input type="hidden" id="crDateFrom" value="{{ $dateFrom }}">
                                <input type="hidden" id="crDateTo" value="{{ $dateTo }}">
                            </div>
                        </div>
                        <select id="crQuickRange" class="form-control form-control-sm" style="width:auto;"
                                title="Tanggal terima. Tanpa pilihan = bulan berjalan ({{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }} - {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }})">
                            <option value="">Tanggal Terima</option>
                            <option value="1d" @selected($rangeAktif === '1d')>1 hari terakhir</option>
                            <option value="3d" @selected($rangeAktif === '3d')>3 hari terakhir</option>
                            <option value="7d" @selected($rangeAktif === '7d')>7 hari terakhir</option>
                            <option value="30d" @selected($rangeAktif === '30d')>30 hari terakhir</option>
                            <option value="1y" @selected($rangeAktif === '1y')>1 tahun terakhir</option>
                            <option value="custom" @selected($rangeAktif === 'custom')>Custom…</option>
                        </select>
                    </div>
                </div>

                {{-- Tanpa ekstensi responsive DataTables (bentrok dengan toggle kolom manual): tabel digulir horizontal. --}}
                <div class="cr-hscroll table-responsive">
                    <table id="tblCashReceipt" class="table table-bordered table-hover mb-0 ts-idx-tbl">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" class="text-nowrap">Kode</th>
                                <th scope="col" class="text-nowrap">Tanggal</th>
                                <th scope="col">Customer</th>
                                <th scope="col" class="text-right text-nowrap">Tagihan</th>
                                <th scope="col" class="text-right text-nowrap">Total PPh</th>
                                <th scope="col" class="text-right text-nowrap">Total Diterima</th>
                                <th scope="col">Invoice</th>
                                <th scope="col" class="text-center text-nowrap">Status</th>
                                <th scope="col" class="text-center action-column" style="width:1%;"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($headers as $h)
                            @php
                                $allocated   = $h->allocatedAmount();
                                $unallocated = $h->unallocatedAmount();
                            @endphp
                            {{-- data-* dibaca tiga pihak: TesysColumnFilter untuk
                                 menyaring, penghitung kartu, dan footer untuk menjumlah
                                 baris yang tersisa. --}}
                            <tr data-status="{{ $h->status }}"
                                data-href="{{ route('cash-receipt.show', $h->id) }}"
                                data-customer="{{ $h->customer->name ?? '-' }}"
                                data-tagihan="{{ (int) $h->kolom_tagihan }}"
                                data-pph="{{ (int) $h->kolom_pph }}"
                                data-diterima="{{ (int) $h->kolom_diterima }}"
                                data-invoices="{{ $h->details->count() }}"
                                data-unallocated="{{ max(0, $unallocated) }}">
                                <td class="text-nowrap">
                                    {{-- Klik kode = salin; detail dibuka lewat klik baris. --}}
                                    <button type="button" class="ts-copy-code" data-code="{{ $h->code }}" title="Klik untuk menyalin kode">{{ $h->code }}</button>
                                </td>
                                <td class="text-nowrap" data-order="{{ $h->received_date?->format('Y-m-d') }}">{{ $h->received_date?->format('d M Y') }}</td>
                                <td class="ts-col-desc">{{ $h->customer->name ?? '-' }}</td>
                                {{-- Nilai nol di kolom uang ditulis "-". --}}
                                <td class="text-right text-nowrap ts-col-money">{{ (int) $h->kolom_tagihan === 0 ? '-' : number_format($h->kolom_tagihan, 0, ',', '.') }}</td>
                                <td class="text-right text-nowrap ts-col-money">{{ (int) $h->kolom_pph === 0 ? '-' : number_format($h->kolom_pph, 0, ',', '.') }}</td>
                                <td class="text-right text-nowrap ts-col-money">{{ (int) $h->kolom_diterima === 0 ? '-' : number_format($h->kolom_diterima, 0, ',', '.') }}</td>
                                <td>
                                    {{-- Kode invoice ditampilkan langsung supaya penerimaan
                                         bisa ditelusuri tanpa membuka detailnya satu per
                                         satu — pola yang sama dengan Surat Jalan. --}}
                                    {{ $h->details->take(3)->map(fn ($d) => $d->invoice->code ?? '-')->implode(', ') ?: '-' }}@if($h->details->count() > 3)
                                        <span class="text-muted" style="font-size:11px;">+{{ $h->details->count() - 3 }} lagi</span>
                                    @endif
                                    @if($unallocated > 0)
                                        <span class="text-warning" style="font-size:11px;" title="Nominal belum teralokasi ke invoice">
                                            · Rp {{ number_format($unallocated, 0, ',', '.') }} belum teralokasi
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center"><x-status :status="$h->status" context="cash-receipt" /></td>
                                <td class="text-center action-column">
                                    {{-- Detail dibuka lewat klik baris. Satu-satunya aksi: Hapus, sama dengan tombol di
                                         halaman detail — hanya DRAFT yang belum punya alokasi (CashReceiptController::destroy
                                         menolak yang sudah teralokasi). Tanpa aksi = sel kosong. --}}
                                    <div class="action-buttons ts-actions">
                                        @if($h->status === 'DRAFT' && $h->details->isEmpty())
                                        <form action="{{ route('cash-receipt.destroy', $h->id) }}" method="POST" class="js-confirm-delete d-inline"
                                              data-title="Hapus penerimaan {{ $h->code }}?" data-text="Belum ada alokasi, jadi aman dihapus.">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="ts-action-primary is-neutral" title="Hapus" aria-label="Hapus penerimaan {{ $h->code }}">
                                                <i class="fas fa-trash" aria-hidden="true"></i><span class="ts-al">Hapus</span>
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        {{-- Satu sel per kolom (tanpa colspan) supaya footer tetap sinkron saat kolom disembunyikan. --}}
                        <tfoot>
                            <tr style="background:var(--surface-2);font-size:12px;">
                                <th class="text-nowrap">Total</th>
                                <th></th>
                                <th></th>
                                <th class="text-right text-nowrap cr-foot-tagihan"></th>
                                <th class="text-right text-nowrap cr-foot-pph"></th>
                                <th class="text-right text-nowrap cr-foot-diterima"></th>
                                <th></th>
                                <th></th>
                                <th class="action-column"></th>
                            </tr>
                        </tfoot>
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
.font-weight-600 { font-weight:600; }
.table td, .table th { vertical-align:middle !important; }

/* Bilah filter: .toolbar-row/.toolbar-input belum punya definisi global, jadi
   tanpa ini label "Cari :" jatuh ke baris sendiri di atas input. Nilainya
   disamakan dengan index lain (Account, Bank, Access Group). */
.toolbar-row   { display:flex; align-items:center; gap:8px; }
.toolbar-label { min-width:50px; font-weight:600; color:var(--text-dim); white-space:nowrap; margin-bottom:0; }
.toolbar-input { min-width:230px; }
.custom-toolbar .toolbar-input .form-control { width:100%; }

/* Tabel digulir horizontal (pola .cr-hscroll Cost Request). */
.cr-hscroll { overflow-x:auto; }
/* Gulir mendatar hanya pada tabel (dibungkus .ts-dt-scroll lewat JS), bukan footer info + paginasi. */
.cr-hscroll:has(> .dataTables_wrapper) { overflow-x:visible; }
.ts-dt-scroll { overflow-x:auto; width:100%; }
#tblCashReceipt tbody tr[data-href] { cursor:pointer; }
.cr-hscroll #tblCashReceipt { width:auto !important; min-width:100%; margin-bottom:0; }
.cr-hscroll #tblCashReceipt th,
.cr-hscroll #tblCashReceipt td { white-space:nowrap; }

/* Popover tombol Kolom — ditempel pada tombolnya sendiri (salinan kecil blok .cr-pop Cost Request). */
.cr-pop { position: relative; }
.cr-pop-menu {
    display: none; position: absolute; top: calc(100% + 4px); left: 0; z-index: 1080;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); box-shadow: 0 8px 24px rgba(0,0,0,.14);
}
.cr-pop.show .cr-pop-menu { display: block; }

@media (max-width: 575.98px) {
    .toolbar-row   { width:100%; }
    .toolbar-input { flex:1; min-width:0; }
}
</style>
@endpush

@push('scripts')
<script>
$(function () {

    /* Kolom yang boleh disembunyikan -> indeks kolom. Kode, Customer, Total
       Diterima, Status, dan Aksi sengaja tidak ada di sini (wajib tampil). */
    var MAP_VIS = { tanggal: 1, tagihan: 3, pph: 4, invoice: 6 };
    function visKey(key) { return 'crCashColVis_' + key; }
    function getVis(key) { var v = localStorage.getItem(visKey(key)); return v === null ? true : v === '1'; }
    function setVis(key, tampil) { localStorage.setItem(visKey(key), tampil ? '1' : '0'); }

    var defs = [{ targets: [8], orderable: false, searchable: false }];
    Object.keys(MAP_VIS).forEach(function (k) {
        defs.push({ targets: [MAP_VIS[k]], visible: getVis(k) });
    });

    // TIDAK memakai ekstensi "responsive" (bentrok dengan toggle visibility manual): tabel digulir lewat .cr-hscroll.
    var table = $('#tblCashReceipt').DataTable({
        autoWidth: false, pageLength: 25, lengthChange: true, searching: true,
        order: [], // urutan dari server (terbaru di atas) dipertahankan
        columnDefs: defs,
        dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex justify-content-end"lp>>',
        language: {
            lengthMenu: 'Page Size _MENU_',
            emptyTable: '<div class="ts-empty ts-empty-compact">'
                + '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-receipt"></i></span>'
                + '<p class="ts-empty-title">Belum ada penerimaan</p>'
                + '<p class="ts-empty-msg">Belum ada bukti terima pada rentang tanggal ini.</p></div>',
            zeroRecords: '<div class="ts-empty ts-empty-compact">'
                + '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-search"></i></span>'
                + '<p class="ts-empty-title">Tidak ada yang cocok</p>'
                + '<p class="ts-empty-msg">Tidak ada penerimaan yang cocok dengan filter yang sedang aktif.</p></div>',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            paginate: { previous: 'Previous', next: 'Next' }
        }
    });

    // Scrollbar mendatar hanya untuk tabel (footer info + paginasi tetap diam).
    $('#tblCashReceipt').wrap('<div class="ts-dt-scroll"></div>');

    /* Klik di mana saja pada baris membuka detail penerimaan (pola Cost Request). Kontrol di
       dalam baris (kode yang disalin, tombol Hapus) bekerja sendiri. */
    $(document).on('click', '#tblCashReceipt tbody tr[data-href]', function (e) {
        if ($(e.target).closest('a, button, input, select, textarea, label, form').length) return;
        if (window.getSelection && String(window.getSelection()).length) return;
        window.location.href = $(this).attr('data-href');
    });

    /* ── TOMBOL KOLOM ─────────────────────────────────────────────────── */
    $('.cr-col-vis-toggle').each(function () {
        var key = $(this).data('column');
        if (MAP_VIS[key] == null) return;
        $(this).prop('checked', table.column(MAP_VIS[key]).visible());
    }).on('change', function () {
        var key = $(this).data('column');
        if (MAP_VIS[key] == null) return;
        var tampil = $(this).is(':checked');
        table.column(MAP_VIS[key]).visible(tampil);
        setVis(key, tampil);
        try { table.columns.adjust(); } catch (e) {}
    });

    $(document).on('click', '.cr-pop-toggle', function (e) {
        e.stopPropagation();
        var $pop = $(this).closest('.cr-pop');
        var buka = !$pop.hasClass('show');

        $('.cr-pop').removeClass('show');
        $pop.toggleClass('show', buka);
        if (!buka) return;

        // Geser kalau panel keluar dari tepi kanan layar.
        var $menu = $pop.find('.cr-pop-menu');
        $menu.css({ left: 0, right: 'auto' });
        var rect = $menu[0].getBoundingClientRect();
        if (rect.right > window.innerWidth - 8) $menu.css({ left: 'auto', right: 0 });
    });
    $(document).on('click', function (e) {
        if ($(e.target).closest('.cr-pop').length) return;
        $('.cr-pop').removeClass('show');
    });
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') $('.cr-pop').removeClass('show');
    });

    /* Hapus draft — delegasi, karena baris bisa dirender ulang oleh DataTables. */
    $('#tblCashReceipt').on('submit', '.js-confirm-delete', function (e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: $(form).data('title') || 'Hapus penerimaan?',
            text: $(form).data('text') || '',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: 'var(--danger)',
            cancelButtonColor: 'var(--text-muted)',
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
     | Kartu dan footer dihitung ulang dari baris yang LOLOS filter (bukan hanya
     | yang tampil di halaman aktif), jadi angka selalu menggambarkan isi tabel
     | di bawahnya. Rentang tanggal sudah diterapkan server; yang dihitung ulang
     | di sini hanya pencarian dan dropdown Status/Customer.
     */
    var rupiah = function (n) { return 'Rp ' + (n || 0).toLocaleString('id-ID'); };
    var angka  = function (n) { return (n || 0) === 0 ? '-' : n.toLocaleString('id-ID'); };   // nol di kolom uang = "-"

    function refreshCards() {
        var jumlah = 0, total = 0, invoice = 0, sisa = 0;

        table.rows({ search: 'applied' }).nodes().each(function (tr) {
            jumlah  += 1;
            total   += parseInt(tr.getAttribute('data-diterima') || 0, 10);
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

    // Sel footer dicari lewat kelas (bukan indeks): DataTables ikut mencabut sel footer kolom yang disembunyikan.
    function recalcFooter() {
        var tagihan = 0, pph = 0, diterima = 0;

        table.rows({ search: 'applied' }).nodes().each(function (tr) {
            tagihan  += parseInt(tr.getAttribute('data-tagihan') || 0, 10);
            pph      += parseInt(tr.getAttribute('data-pph') || 0, 10);
            diterima += parseInt(tr.getAttribute('data-diterima') || 0, 10);
        });

        var $foot = $('#tblCashReceipt tfoot');
        $foot.find('.cr-foot-tagihan').text(angka(tagihan));
        $foot.find('.cr-foot-pph').text(angka(pph));
        $foot.find('.cr-foot-diterima').text(angka(diterima));
    }

    table.on('draw', function () { refreshCards(); recalcFooter(); });
    table.on('column-visibility.dt', function () { recalcFooter(); });
    refreshCards();
    recalcFooter();

    /* ── FILTER TANGGAL (server-side) ──────────────────────────────────────
     | Komponen bersama TesysDateRange (public/js/tesys-date-range.js). Menerapkan
     | rentang = muat ulang halaman dengan ?date_from=&date_to=&range=. Kalau
     | flatpickr gagal termuat, komponennya mengembalikan null dan filter turun
     | ke dua input teks manual (YYYY-MM-DD). */
    function terapkanRentang(dari, sampai, range) {
        if (!dari || !sampai) { return; }
        var url = new URL(window.location.href);
        url.searchParams.set('date_from', dari);
        url.searchParams.set('date_to', sampai);
        if (range) { url.searchParams.set('range', range); }
        else       { url.searchParams.delete('range'); }
        window.location.href = url.toString();
    }

    var crPeriode = (typeof TesysDateRange === 'object')
        ? TesysDateRange.init({
            input: '#crDateRange', from: '#crDateFrom', to: '#crDateTo',
            applyButton: { label: 'Terapkan', onClick: function (dari, sampai) { terapkanRentang(dari, sampai); } }
        })
        : null;

    if (!crPeriode) {
        console.warn('TesysDateRange tidak tersedia - filter tanggal beralih ke mode input manual.');
        $('#crDateFrom, #crDateTo').attr('type', 'text')
            .addClass('form-control form-control-sm')
            .attr('placeholder', 'YYYY-MM-DD');
        $('#crDateRange').remove();
        $('<button type="button" class="btn btn-primary btn-sm"><i class="fas fa-check mr-1"></i> Terapkan</button>')
            .on('click', function () { terapkanRentang($('#crDateFrom').val(), $('#crDateTo').val()); })
            .appendTo('.cr-date-range');
    }

    function fmtTanggal(d) {
        var p = function (n) { return (n < 10 ? '0' : '') + n; };
        return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
    }

    $('#crQuickRange').on('change', function () {
        var v = $(this).val();

        // Pilihan kosong = kembali ke bawaan (bulan berjalan).
        if (!v) {
            var url = new URL(window.location.href);
            ['date_from', 'date_to', 'range'].forEach(function (k) { url.searchParams.delete(k); });
            window.location.href = url.toString();
            return;
        }

        if (v === 'custom') {
            $('#crCustomRange').removeClass('d-none').addClass('d-flex');
            if (crPeriode) { crPeriode.open(); }
            return;
        }
        $('#crCustomRange').addClass('d-none').removeClass('d-flex');

        var sekarang = new Date();
        var mulai = new Date(sekarang.getTime());
        if (v === '1d')       { mulai.setDate(mulai.getDate() - 1); }
        else if (v === '3d')  { mulai.setDate(mulai.getDate() - 3); }
        else if (v === '7d')  { mulai.setDate(mulai.getDate() - 7); }
        else if (v === '30d') { mulai.setDate(mulai.getDate() - 30); }
        else if (v === '1y')  { mulai.setFullYear(mulai.getFullYear() - 1); }

        terapkanRentang(fmtTanggal(mulai), fmtTanggal(sekarang), v);
    });
});
</script>
@endpush
