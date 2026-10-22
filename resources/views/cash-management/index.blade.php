@extends('layouts.app')

@section('title', 'Cash Management')

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
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Cash Management</h1>
                <small class="text-muted">
                    Pergerakan kas perusahaan — disusun dari Cash Receipt, Treasury, dan entri manual. Tidak ada transaksi yang dicatat dua kali.
                </small>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Finance</a></li>
                <li class="breadcrumb-item active">Cash Management</li>
            </ol>
        </div>

        {{-- Ringkasan berasal dari koleksi yang SAMA dengan tabel di bawah
             (CashFlowService::summarize menerima ledger yang sudah difilter),
             jadi filter apa pun yang dipakai, kartu dan tabel tidak akan pernah
             bercerita lain. --}}
        <div id="cmSummary">
        <x-stat-cards :cards="[
            ['Cash Inflow', 'Rp ' . number_format($summary['total_in'], 0, ',', '.'), 'success', 'fas fa-arrow-down'],
            ['Cash Outflow', 'Rp ' . number_format($summary['total_out'], 0, ',', '.'), 'danger', 'fas fa-arrow-up'],
            ['Net Cash Flow', 'Rp ' . number_format($summary['net'], 0, ',', '.'), $summary['net'] >= 0 ? 'primary' : 'warning', 'fas fa-balance-scale'],
            ['Transaksi', $summary['count'] . ' (' . $summary['count_in'] . ' in / ' . $summary['count_out'] . ' out)', 'secondary', 'fas fa-exchange-alt'],
        ]" />
        </div>

        {{-- FILTER

             Tata letaknya tetap seperti semula: satu kotak per field. Yang
             berubah hanya mesinnya — Arah / Sumber / Rekening memakai daftar
             centang TesysColumnFilter (mekanisme yang sama dengan Cost
             Request), yang membaca atribut data- pada tiap baris dan menyaring
             di halaman. Tidak ada muat ulang, jadi daftar centangnya tidak
             pernah menutup sendiri. Rentang tanggal tetap disaring di server
             karena rentang menentukan dataset-nya, dan kartu ringkasan
             dihitung ulang dari baris yang lolos filter. --}}
        <div class="card shadow-sm mt-3">
            <div class="card-body py-3">
                <div class="form-row align-items-end">
                    <form method="GET" action="{{ url('/cash-management') }}" id="formFilterKas" class="form-row col-lg-4 col-md-8 col-12 p-0 m-0">
                        {{-- Satu kotak rentang (TesysDateRange): klik membuka dua
                             kalender From/To; nilainya tetap dikirim sebagai
                             from & to seperti sebelumnya. --}}
                        <div class="form-group col-12 mb-2">
                            <label class="font-weight-600" style="font-size:11px;">Periode</label>
                            <div>
                                <input type="text" id="cmDateRange" class="form-control form-control-sm tdr-input" readonly>
                                <input type="hidden" name="from" id="cmDateFrom" value="{{ $filters['from'] }}">
                                <input type="hidden" name="to" id="cmDateTo" value="{{ $filters['to'] }}">
                            </div>
                        </div>
                    </form>

                    {{-- Tombol + daftar centang ketiga filter ini dibuat
                         TesysColumnFilter ke dalam wadah kosong di bawah. --}}
                    <div class="form-group col-lg-2 col-md-4 col-6 mb-2">
                        <label class="font-weight-600" style="font-size:11px;">Arah</label>
                        <div id="cmFilterDirection" class="cm-row-filters"></div>
                    </div>
                    <div class="form-group col-lg-2 col-md-4 col-6 mb-2">
                        <label class="font-weight-600" style="font-size:11px;">Sumber</label>
                        <div id="cmFilterModule" class="cm-row-filters"></div>
                    </div>
                    <div class="form-group col-lg-2 col-md-4 col-6 mb-2">
                        <label class="font-weight-600" style="font-size:11px;">Rekening</label>
                        <div id="cmFilterFund" class="cm-row-filters"></div>
                    </div>
                    <div class="form-group col-lg-2 col-md-4 col-12 mb-2">
                        <label class="font-weight-600" style="font-size:11px;">Cari</label>
                        <div class="d-flex" style="gap:6px;">
                            <input type="search" id="cmSearch" class="form-control form-control-sm"
                                   placeholder="Deskripsi / referensi">
                            <button type="button" id="cmClearFilter" class="btn btn-outline-secondary btn-sm text-nowrap"
                                    title="Hapus filter (periode tetap)">
                                <i class="fas fa-eraser"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs ts-tabs-sticky mt-3" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#tabLedger" role="tab">
                    <i class="fas fa-stream mr-1"></i> Buku Kas
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tabPeriod" role="tab">
                    <i class="fas fa-calendar-alt mr-1"></i> Rekap Periode
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tabManual" role="tab">
                    <i class="fas fa-pen-square mr-1"></i> Entri Manual
                </a>
            </li>
        </ul>

        <div class="tab-content">

            {{-- ── BUKU KAS ───────────────────────────────────────────────── --}}
            <div class="tab-pane fade show active" id="tabLedger" role="tabpanel">
                <div class="card card-primary card-outline shadow-sm" style="border-top-left-radius:0;">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="tblLedger" class="table table-bordered table-hover table-striped mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th scope="col" class="text-nowrap">Tanggal</th>
                                        <th scope="col" class="text-nowrap">Sumber</th>
                                        <th scope="col" class="text-nowrap">Referensi</th>
                                        <th scope="col">Deskripsi</th>
                                        <th scope="col" class="text-nowrap">Kategori</th>
                                        <th scope="col" class="text-nowrap">Rekening</th>
                                        <th scope="col" class="text-right text-nowrap">Cash In</th>
                                        <th scope="col" class="text-right text-nowrap">Cash Out</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($ledger as $row)
                                    <tr data-direction="{{ $row['direction'] }}"
                                        data-module="{{ $row['source_module'] }}"
                                        data-fund="{{ $row['source_fund'] }}"
                                        data-amount="{{ $row['amount'] }}">
                                        <td class="text-nowrap">{{ $row['date']?->format('d M Y') ?? '-' }}</td>
                                        <td>
                                            @php
                                                $modBadge = [
                                                    'CASH_RECEIPT' => 'badge-success',
                                                    'TREASURY'     => 'badge-danger',
                                                    'CASH_FLOW'    => 'badge-secondary',
                                                ][$row['source_module']] ?? 'badge-secondary';
                                            @endphp
                                            <span class="badge {{ $modBadge }} px-2 py-1">{{ $modules[$row['source_module']] ?? $row['source_module'] }}</span>
                                        </td>
                                        <td class="text-nowrap">
                                            @if($row['url'])
                                                <a href="{{ $row['url'] }}" target="_blank">{{ $row['reference_number'] }}</a>
                                            @else
                                                {{ $row['reference_number'] }}
                                            @endif
                                        </td>
                                        <td>{{ $row['description'] }}</td>
                                        <td class="text-nowrap">{{ $row['category'] }}</td>
                                        <td class="text-nowrap">{{ $row['source_fund'] }}</td>
                                        <td class="text-right text-nowrap text-success">
                                            {{ $row['direction'] === 'IN' ? number_format($row['amount'], 0, ',', '.') : '-' }}
                                        </td>
                                        <td class="text-right text-nowrap text-danger">
                                            {{ $row['direction'] === 'OUT' ? number_format($row['amount'], 0, ',', '.') : '-' }}
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="8">
                                            <x-empty title="Tidak ada pergerakan kas pada rentang ini" icon="fas fa-wallet" compact />
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                                @if($ledger->isNotEmpty())
                                <tfoot>
                                    <tr style="background:var(--surface-2);font-weight:700;">
                                        <th colspan="6" class="text-right">Total</th>
                                        <th class="text-right text-success" id="cmFootIn">{{ number_format($summary['total_in'], 0, ',', '.') }}</th>
                                        <th class="text-right text-danger" id="cmFootOut">{{ number_format($summary['total_out'], 0, ',', '.') }}</th>
                                    </tr>
                                    <tr style="background:var(--surface-2);font-weight:700;">
                                        <th colspan="6" class="text-right">Net Cash Flow</th>
                                        <th colspan="2" id="cmFootNet" class="text-right {{ $summary['net'] >= 0 ? 'text-success' : 'text-danger' }}">
                                            {{ number_format($summary['net'], 0, ',', '.') }}
                                        </th>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── REKAP PERIODE ──────────────────────────────────────────── --}}
            <div class="tab-pane fade" id="tabPeriod" role="tabpanel">
                <div class="card card-primary card-outline shadow-sm" style="border-top-left-radius:0;">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th scope="col">Periode</th>
                                        <th scope="col" class="text-center">Transaksi</th>
                                        <th scope="col" class="text-right">Cash Inflow</th>
                                        <th scope="col" class="text-right">Cash Outflow</th>
                                        <th scope="col" class="text-right">Net Cash Flow</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($monthly as $m)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::createFromFormat('Y-m', $m['period'])->translatedFormat('F Y') }}</td>
                                        <td class="text-center">{{ $m['count'] }}</td>
                                        <td class="text-right text-success">{{ number_format($m['total_in'], 0, ',', '.') }}</td>
                                        <td class="text-right text-danger">{{ number_format($m['total_out'], 0, ',', '.') }}</td>
                                        <td class="text-right {{ $m['net'] >= 0 ? 'text-success' : 'text-danger' }}">
                                            <strong>{{ number_format($m['net'], 0, ',', '.') }}</strong>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5">
                                            <x-empty title="Belum ada rekap periode" icon="fas fa-calendar-alt" compact />
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if(!empty($summary['by_module']))
                        <h6 class="mt-4 mb-2 font-weight-bold" style="font-size:12px;">Rincian per Sumber</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th scope="col">Sumber</th>
                                        <th scope="col" class="text-center">Transaksi</th>
                                        <th scope="col" class="text-right">Cash In</th>
                                        <th scope="col" class="text-right">Cash Out</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($summary['by_module'] as $row)
                                    <tr>
                                        <td>{{ $modules[$row['label']] ?? $row['label'] }}</td>
                                        <td class="text-center">{{ $row['count'] }}</td>
                                        <td class="text-right text-success">{{ number_format($row['in'], 0, ',', '.') }}</td>
                                        <td class="text-right text-danger">{{ number_format($row['out'], 0, ',', '.') }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ── ENTRI MANUAL ───────────────────────────────────────────── --}}
            <div class="tab-pane fade" id="tabManual" role="tabpanel">
                <div class="card card-primary card-outline shadow-sm" style="border-top-left-radius:0;">
                    <div class="card-header d-flex align-items-center flex-wrap" style="gap:8px;">
                        <h3 class="card-title"><i class="fas fa-pen-square mr-1"></i> Dokumen Cash Flow Manual</h3>
                        <div class="ml-auto">
                            <a href="{{ route('cash-management.create') }}" class="btn btn-success btn-sm">
                                <i class="fas fa-plus mr-1"></i> Tambah Cash Flow
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted" style="font-size:11px;">
                            Dipakai untuk pergerakan kas yang tidak punya dokumen di modul lain — setoran modal,
                            biaya bank, koreksi kas. Pembayaran Cost Request dan pelunasan invoice
                            <strong>tidak perlu diinput di sini</strong>; keduanya sudah otomatis masuk Buku Kas dari Treasury dan Cash Receipt.
                        </p>
                        <div class="table-responsive">
                            <table id="tblManual" class="table table-bordered table-hover table-striped mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th scope="col" class="text-nowrap">Kode</th>
                                        <th scope="col" class="text-nowrap">Tanggal</th>
                                        <th scope="col">Deskripsi</th>
                                        <th scope="col" class="text-nowrap">Rekening</th>
                                        <th scope="col" class="text-right text-nowrap">Cash In</th>
                                        <th scope="col" class="text-right text-nowrap">Cash Out</th>
                                        <th scope="col" class="text-center text-nowrap">Status</th>
                                        <th scope="col" class="text-nowrap">Dibuat Oleh</th>
                                        <th scope="col" class="text-center text-nowrap">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($documents as $doc)
                                    <tr>
                                        <td class="text-nowrap"><strong>{{ $doc->code }}</strong></td>
                                        <td class="text-nowrap">{{ $doc->date?->format('d M Y') }}</td>
                                        <td>{{ $doc->description }}</td>
                                        <td class="text-nowrap">{{ $doc->sourceFund->name ?? '-' }}</td>
                                        <td class="text-right text-nowrap text-success">{{ number_format($doc->total_in, 0, ',', '.') }}</td>
                                        <td class="text-right text-nowrap text-danger">{{ number_format($doc->total_out, 0, ',', '.') }}</td>
                                        <td class="text-center">
                                            @php
                                                $stBadge = ['DRAFT' => 'badge-secondary', 'POSTED' => 'badge-success', 'CANCELLED' => 'badge-danger'][$doc->status] ?? 'badge-secondary';
                                            @endphp
                                            <span class="badge {{ $stBadge }} px-2 py-1">{{ $doc->status }}</span>
                                        </td>
                                        <td class="text-nowrap">{{ strtoupper($doc->createdBy->username ?? '-') }}</td>
                                        <td class="text-center text-nowrap">
                                            <a href="{{ route('cash-management.show', $doc->id) }}" class="btn btn-sm btn-outline-primary" title="Detail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="9">
                                            <x-empty title="Belum ada entri manual" icon="fas fa-pen-square" compact />
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
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
#tblLedger, #tblManual { width:100% !important; }
/* Tombol filter dibuat TesysColumnFilter; disamakan tingginya dengan kotak
   isian lain di bilah ini supaya satu baris terlihat rata. */
.cm-row-filters .tcf-row-btn {
    width:100%; display:flex; align-items:center; justify-content:space-between;
    height:calc(1.5em + .5rem + 2px); font-size:13px; text-align:left; overflow:hidden;
}
</style>
@endpush

@push('scripts')
<script>
$(function () {
    /* DataTable = mesin sort, paging, pencarian, dan penyaringan baris.
       Server hanya menentukan rentang tanggal (dataset-nya); sisanya disaring
       di halaman supaya daftar centang tidak tertutup tiap kali dipakai.
       Kartu ringkasan ikut dihitung ulang dari baris yang lolos filter. */
    var common = {
        responsive: true, autoWidth: false, pageLength: 25, lengthChange: true,
        // searching: true wajib — kotak Cari dan filter baris TesysColumnFilter
        // keduanya lewat mesin pencarian DataTables. Kotak bawaannya tidak
        // ditampilkan karena 'f' tidak ada di dom.
        searching: true, ordering: true,
        dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex justify-content-end"lp>>',
        language: {
            zeroRecords: 'Data tidak ditemukan',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            lengthMenu: 'Page Size _MENU_',
            paginate: { previous: 'Previous', next: 'Next' }
        }
    };

    /* Penjaga keadaan kosong memakai kelas .ts-empty — itulah kelas yang
       benar-benar dipakai komponen x-empty. Sebelumnya dicari .empty-state,
       yang tidak pernah ada, sehingga tabel kosong (satu sel colspan) tetap
       dipasangi DataTables; DataTables melempar "Incorrect column count" dan
       seluruh skrip halaman ini mati di situ — itu sebab filter dan dropdown
       tidak pernah bereaksi. */
    function pasangTabel() {
        if ($('#tblLedger tbody tr').length && !$('#tblLedger tbody .ts-empty').length) {
            $('#tblLedger').DataTable($.extend({}, common, { order: [[0, 'desc']] }));
        }
        if ($('#tblManual tbody tr').length && !$('#tblManual tbody .ts-empty').length) {
            $('#tblManual').DataTable($.extend({}, common, { order: [[1, 'desc']] }));
        }
    }
    // Kalau satu tabel gagal dipasang, sisa halaman (filter, pencarian,
    // ringkasan) tetap harus hidup.
    try { pasangTabel(); } catch (e) { if (window.console) console.error(e); }

    var $formFilter = $('#formFilterKas');

    /* ── FILTER ────────────────────────────────────────────────────────────
    | Mekanismenya sama dengan Cost Request: TesysColumnFilter membuat tombol
    | "Filter" berisi daftar centang per field (lengkap dengan kotak cari, chip
    | filter aktif, dan Reset), membaca nilainya dari atribut data- pada tiap
    | <tr>, lalu menyaring di halaman. Tidak ada muat ulang, jadi daftar
    | centangnya tidak pernah tertutup sendiri.
    |
    | Rentang tanggal tetap di server: ia menentukan dataset-nya, bukan sekadar
    | menyembunyikan baris. */
    /* Rentang tanggal memakai komponen bersama TesysDateRange; form dikirim
       begitu rentangnya berubah (perilaku lama: submit saat tanggal diganti). */
    var cmNilaiAwal = '{{ $filters['from'] }}|{{ $filters['to'] }}';
    if (typeof TesysDateRange === 'object') {
        TesysDateRange.init({
            input: '#cmDateRange', from: '#cmDateFrom', to: '#cmDateTo',
            quick: true,
            onApply: function (from, to) {
                if (from + '|' + to === cmNilaiAwal) return;
                $('#formFilterKas').submit();
            }
        });
    } else {
        $('#cmDateRange').remove();
        $('#cmDateFrom, #cmDateTo').attr('type', 'date').addClass('form-control form-control-sm');
    }

    $('#formFilterKas').on('change', 'input[type="date"]', function () {
        $('#formFilterKas').trigger('submit');
    });

    var tabelLedger = $.fn.dataTable.isDataTable('#tblLedger') ? $('#tblLedger').DataTable() : null;

    if (tabelLedger && window.TesysColumnFilter) {
        /* Satu panggilan per field, masing-masing ke wadahnya sendiri: dengan
           begitu tiap filter tetap jadi kotak terpisah di bilah ini. Kalau
           ketiganya dikirim sekaligus, TesysColumnFilter melipatnya jadi satu
           tombol "Filter" seperti di Cost Request — di sini tata letak per
           field yang dipertahankan. */
        TesysColumnFilter.initRowFilters('#tblLedger', $('#cmFilterDirection'), [
            { key: 'direction', label: 'Semua Arah', valueLabels: { IN: 'Cash In', OUT: 'Cash Out' }, search: false },
        ]);
        TesysColumnFilter.initRowFilters('#tblLedger', $('#cmFilterModule'), [
            { key: 'module', label: 'Semua Modul', valueLabels: @json($modules), search: false },
        ]);
        TesysColumnFilter.initRowFilters('#tblLedger', $('#cmFilterFund'), [
            { key: 'fund', label: 'Semua Rekening' },
        ]);

        $('#cmSearch').on('input', function () {
            tabelLedger.search(this.value).draw();
        });

        $('#cmClearFilter').on('click', function () {
            $('#cmSearch').val('');
            tabelLedger.search('');
            TesysColumnFilter.clearAll('#tblLedger');
        });

        /* Kartu ringkasan & baris Total dihitung ulang dari baris yang lolos
           filter. Tanpa ini, angka di atas akan bercerita lain dari tabel di
           bawahnya begitu filter dipakai — persis bug yang dihindari di
           Collection Summary. */
        function rupiah(n) { return Math.round(n).toLocaleString('id-ID'); }

        function hitungUlangRingkasan() {
            var masuk = 0, keluar = 0, nIn = 0, nOut = 0;

            tabelLedger.rows({ search: 'applied' }).nodes().each(function (tr) {
                var nilai = parseInt(tr.getAttribute('data-amount'), 10) || 0;
                if (tr.getAttribute('data-direction') === 'IN') { masuk += nilai; nIn++; }
                else { keluar += nilai; nOut++; }
            });

            var bersih  = masuk - keluar;
            var $nilai  = $('#cmSummary .ts-kpi-value');

            $nilai.eq(0).text('Rp ' + rupiah(masuk));
            $nilai.eq(1).text('Rp ' + rupiah(keluar));
            $nilai.eq(2).text('Rp ' + rupiah(bersih));
            $nilai.eq(3).text((nIn + nOut) + ' (' + nIn + ' in / ' + nOut + ' out)');

            $('#cmFootIn').text(rupiah(masuk));
            $('#cmFootOut').text(rupiah(keluar));
            $('#cmFootNet').text(rupiah(bersih))
                .toggleClass('text-success', bersih >= 0)
                .toggleClass('text-danger', bersih < 0);
        }

        tabelLedger.on('draw', hitungUlangRingkasan);
    }

    // DataTables salah menghitung lebar kolom kalau tabelnya masih tersembunyi
    // saat diinisialisasi — hitung ulang begitu tab-nya benar-benar tampil.
    $('a[data-toggle="tab"]').on('shown.bs.tab', function () {
        $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
    });
});
</script>
@endpush
