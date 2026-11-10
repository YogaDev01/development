{{-- Isi tabel gabungan semua tipe CR (fragment HTML, dimuat AJAX). Kerangka ada di index.blade.php. --}}
@php
    $tid    = 'tbl' . strtoupper($tab);      // tblALL
    $canAdd = Auth::user()->hasRole(['superadmin','manager','supervisor']);
    /* Kolom checkbox bulk Approve/Reject (tahap pengajuan & settlement) hanya untuk peran efektif
       (termasuk View As) yang berwenang di salah satu tahap — daftar peran dari
       BudgetRequest::STAGE_ROLES, sama dengan gerbang tombol per baris di _rows. */
    $canBulk = Auth::user()->hasRole(
        array_merge(['superadmin'], ...array_values(\App\Models\BudgetRequest::bulkStageRoles()))
    );

    /* Gaya tabel (>= 641px; M5a): satu string di <table> memakai varian turunan, bukan kelas per sel
       (tabel bisa 1.000 baris). <= 640px tidak disentuh: mode kartu tesys-table.js/datatable.css/mobile.css.
       Sufiks ! hanya pada properti yang terbukti dikalahkan CSS legacy tak berlayer (tesys-ui.css
       thead th/tbody td, DataTables, datatable.css); daftar + alasan terukur di docs/ui/tailwind-components.md. */
    $gayaTabel = implode(' ', [
        // semua sel satu baris (kolom centang tak punya text-nowrap); Description boleh membungkus dan dibatasi lebarnya;
        // lebar minimum 17rem hanya di jendela >= 1440px (di bawahnya Status CA tertutup kolom Action beku pada gulir 0)
        'tw:min-sm:[&_:is(th,td)]:whitespace-nowrap',
        'tw:min-sm:[&_td.cr-col-desc]:whitespace-normal tw:min-sm:[&_td.cr-col-desc]:min-w-[9rem] tw:min-sm:min-[1440px]:[&_td.cr-col-desc]:min-w-[17rem] tw:min-sm:[&_td.cr-col-desc]:max-w-[20rem] tw:min-sm:[&_td.cr-col-desc]:[overflow-wrap:anywhere]',
        // th/td: tipografi, jarak vertikal, garis th = tokens refine.css §8 (berlaku untuk semua table.dataTable), tanpa kelas di sini.
        // Jarak horizontal 8px (token sp-2, bukan --cell-x 12px): 11 kolom + 2 beku harus muat di 1440px; ruang panah urut th tetap 20px (refine 14d).
        'tw:min-sm:[&_td:not(.cr-col-check)]:px-2! tw:min-sm:[&_thead_th:not(.cr-col-check)]:pl-2! tw:min-sm:[&_tbody_td]:bg-surface',
        // tfoot (total): angka = ukuran data + warna teks (legacy §8 memberi 12px/redam); latar, garis atas, label = legacy
        'tw:min-sm:[&_tfoot_th]:text-data! tw:min-sm:[&_tfoot_th:not(.cr-foot-label)]:text-text!',
        // kolom beku: centang (kiri) dan Action (kanan); Code tidak dibekukan; garis tipis token (bayangan 1px), bukan bayangan gelap
        'tw:min-sm:[&_th:is(.cr-col-check,.action-column)]:sticky!',
        'tw:min-sm:[&_:is(th,td).cr-col-check]:left-0 tw:min-sm:[&_:is(th,td).cr-col-check]:w-[36px] tw:min-sm:[&_:is(th,td).cr-col-check]:min-w-[36px] tw:min-sm:[&_:is(th,td).cr-col-check]:max-w-[36px] tw:min-sm:[&_td.cr-col-check]:sticky tw:min-sm:[&_:is(th,td).cr-col-check]:z-3 tw:min-sm:[&_:is(th,td).cr-col-check]:px-0!',
        'tw:min-sm:[&_:is(th,td).action-column]:right-0 tw:min-sm:[&_td.action-column]:sticky tw:min-sm:[&_:is(th,td).action-column]:z-3 tw:min-sm:[&_:is(th,td).action-column]:shadow-[-1px_0_0_var(--border)]',
        'tw:min-sm:[&_thead_th:is(.cr-col-check,.action-column)]:z-4',
    ]);

    /* Mode kartu mobile (<= 640px; M5b). Kartu lama (tesys-table.js + datatable.css) dimatikan lewat
       data-ts-card="off" pada <table>; susunannya ditiru di sini dengan varian turunan (bukan kelas per sel):
       judul = Code, status kanan atas, centang kiri atas, aksi di dasar kartu, field berlabel (data-lbl dari
       server; bukan data-label karena tesys-table.js menghapus data-label sel tbody pada tabel "off"),
       tfoot = blok ringkasan di atas daftar. Padding/garis sel memakai variabel --crk-p/--crk-b supaya
       cukup satu ! per properti. Daftar ! + alasan terukur: docs/ui/tailwind-components.md. */
    $gayaKartu = implode(' ', [
        // tabel jadi tumpukan kartu; thead tetap ada untuk pembaca layar/pengurut tetapi tak terlihat
        'tw:max-sm:flex tw:max-sm:flex-col tw:max-sm:[&>thead]:sr-only',
        // kartu = baris
        'tw:max-sm:[&>tbody>tr]:relative tw:max-sm:[&>tbody>tr]:block tw:max-sm:[&>tbody>tr]:mb-[10px] tw:max-sm:[&>tbody>tr]:px-3 tw:max-sm:[&>tbody>tr]:py-1 tw:max-sm:[&>tbody>tr]:rounded-md tw:max-sm:[&>tbody>tr]:border tw:max-sm:[&>tbody>tr]:border-border tw:max-sm:[&>tbody>tr]:shadow-xs',
        'tw:max-sm:[&>tbody>tr:has(>td.cr-col-check)]:pl-[42px]',
        // pesan kosong / tak ada yang cocok (satu-satunya td ber-colspan di tbody): bukan kartu; isinya boleh membungkus
        // (compat/luxe.css memberi td:last-child nowrap; ditimpa pada anak sel, tanpa !)
        'tw:max-sm:[&>tbody>tr:has(>td[colspan])]:border-0 tw:max-sm:[&>tbody>tr:has(>td[colspan])]:shadow-none tw:max-sm:[&>tbody>tr:has(>td[colspan])]:p-0 tw:max-sm:[&_td[colspan]]:block tw:max-sm:[&_td[colspan]>*]:whitespace-normal',
        // sel = field: label (::before, 38%) kiri, isi kanan; garis bawah tipis kecuali sel terakhir
        'tw:max-sm:[&>tbody>tr>td]:flex tw:max-sm:[&>tbody>tr>td]:items-baseline tw:max-sm:[&>tbody>tr>td]:justify-between tw:max-sm:[&>tbody>tr>td]:gap-3',
        'tw:max-sm:[&>:is(tbody,tfoot)>tr>*]:[padding:var(--crk-p,6px_0)]! tw:max-sm:[&>:is(tbody,tfoot)>tr>*]:[border-width:var(--crk-b,0_0_1px)]! tw:max-sm:[&>tbody>tr>td:last-child]:[--crk-b:0]',
        'tw:max-sm:[&_td[data-lbl]]:before:content-[attr(data-lbl)] tw:max-sm:[&_td[data-lbl]]:before:shrink-0 tw:max-sm:[&_td[data-lbl]]:before:basis-[38%] tw:max-sm:[&_td[data-lbl]:not(.cr-col-desc)]:before:max-w-[38%] tw:max-sm:[&_td[data-lbl]]:before:text-left tw:max-sm:[&_td[data-lbl]]:before:text-[11px] tw:max-sm:[&_td[data-lbl]]:before:font-section tw:max-sm:[&_td[data-lbl]]:before:text-text-faint',
        // nilai panjang tanpa spasi (nama) boleh patah, bukan meluap dari kartu; Requestor/PIC tak lagi ber-text-nowrap (BS !important)
        'tw:max-sm:[&_td[data-lbl]]:[overflow-wrap:anywhere]',
        // Description (dua blok): kisi label | isi bertumpuk, rata kanan
        'tw:max-sm:[&_td.cr-col-desc]:grid tw:max-sm:[&_td.cr-col-desc]:grid-cols-[minmax(0,38%)_minmax(0,1fr)] tw:max-sm:[&_td.cr-col-desc]:gap-y-[2px] tw:max-sm:[&_td.cr-col-desc]:text-right tw:max-sm:[&_td.cr-col-desc]:before:row-[1/span_9] tw:max-sm:[&_td.cr-col-desc>*]:col-start-2',
        // judul = Code (ruang kanan untuk chip status)
        'tw:max-sm:[&_td.cr-col-code]:justify-start tw:max-sm:[&_td.cr-col-code]:flex-wrap tw:max-sm:[&_td.cr-col-code]:[--crk-p:6px_96px_6px_0]',
        // status: kanan atas kartu
        'tw:max-sm:[&_td[data-card=status]]:absolute tw:max-sm:[&_td[data-card=status]]:top-2 tw:max-sm:[&_td[data-card=status]]:right-3 tw:max-sm:[&_td[data-card=status]]:max-w-[45%] tw:max-sm:[&_td[data-card=status]]:[--crk-p:0] tw:max-sm:[&_td[data-card=status]]:[--crk-b:0]',
        // centang: kiri atas kartu, kotak 20px; tanpa garis aksen baris tercentang datatable.css (kartu lama juga tanpa)
        'tw:max-sm:[&_td.cr-col-check]:absolute tw:max-sm:[&_td.cr-col-check]:top-[6px] tw:max-sm:[&_td.cr-col-check]:left-3 tw:max-sm:[&_td.cr-col-check]:[--crk-b:0] tw:max-sm:[&_td.cr-col-check]:shadow-none! tw:max-sm:[&_.cr-check-row]:size-5',
        // aksi: rata kanan di dasar kartu; kisi tombol 36px (luxe.css) diperbesar ke target sentuh 44px lewat zoom (tanpa !)
        'tw:max-sm:[&_td.action-column]:justify-end tw:max-sm:[&_td.action-column]:[--crk-p:8px_0_6px] tw:max-sm:[&_.ts-actions]:[zoom:calc(44/36)]',
        // tfoot = blok ringkasan berlabel di atas daftar (teks tetap diisi recalcFooter)
        'tw:max-sm:[&>tfoot]:order-first tw:max-sm:[&>tfoot]:mb-[10px] tw:max-sm:[&>tfoot>tr]:block tw:max-sm:[&>tfoot>tr]:rounded-md tw:max-sm:[&>tfoot>tr]:border tw:max-sm:[&>tfoot>tr]:border-border-strong tw:max-sm:[&>tfoot>tr]:bg-surface-2 tw:max-sm:[&>tfoot>tr]:px-3 tw:max-sm:[&>tfoot>tr]:py-2',
        'tw:max-sm:[&>tfoot>tr>th]:flex tw:max-sm:[&>tfoot>tr>th]:justify-between tw:max-sm:[&>tfoot>tr>th]:gap-3 tw:max-sm:[&>tfoot>tr>th]:[--crk-p:3px_0] tw:max-sm:[&>tfoot>tr>th]:[--crk-b:0] tw:max-sm:[&>tfoot>tr>th:empty]:hidden',
        'tw:max-sm:[&_th[data-lbl]]:before:content-[attr(data-lbl)] tw:max-sm:[&_th[data-lbl]]:before:font-regular tw:max-sm:[&_th[data-lbl]]:before:text-text-faint tw:max-sm:[&_th[data-lbl]]:before:normal-case tw:max-sm:[&_th[data-lbl]]:text-text!',
        /* >= 641px: data-ts-card="off" membuat aturan "kolom pertama beku" datatable.css (bagian 1b,
           table.dataTable:not(.ts-card-table), tanpa layer) ikut berlaku dan menimpa z-index serta bayangan
           sel pertama dari $gayaTabel. Dua kelas ! ini mengembalikan nilai M5a persis (baris tercentang
           dikecualikan agar penanda aksen datatable.css tetap menang). */
        'tw:min-sm:[&>thead>tr>th:first-child,&>tbody>tr>td:first-child:not([colspan])]:[z-index:var(--crk-z,3)]! tw:min-sm:[&>thead>tr>th]:[--crk-z:4]',
        'tw:min-sm:[&>tbody>tr:not(:has(:checked))>td:first-child:not([colspan])]:[box-shadow:var(--crk-sh,none)]! tw:min-sm:[&_td.cr-col-code,&>tbody>tr:is(.row-nob,.row-nob-item)>td:first-child]:[--crk-sh:var(--tw-inset-shadow),var(--tw-inset-ring-shadow),var(--tw-ring-offset-shadow),var(--tw-ring-shadow),var(--tw-shadow)]',
    ]);
@endphp

@if($truncated)
<div role="status" class="tw:mb-3 tw:flex tw:items-start tw:gap-2 tw:rounded-md tw:border tw:border-border tw:bg-warning-bg tw:px-3 tw:py-2 tw:text-data tw:leading-data tw:text-warning">
    <i class="fas fa-exclamation-triangle tw:mt-1" aria-hidden="true"></i>
    <span>
        Menampilkan <strong>{{ number_format($maxRows, 0, ',', '.') }}</strong> Cost Request terbaru dari total
        <strong>{{ number_format($total, 0, ',', '.') }}</strong> pada rentang tanggal ini.
        Persempit rentang tanggal, atau gunakan <strong>Export Excel</strong> untuk data lengkap.
    </span>
</div>
@endif

<div class="cr-table-wrap">
    <table id="{{ $tid }}" class="table table-bordered mb-0 cr-tbl{{ $canBulk ? ' cr-has-check' : '' }} {{ $gayaTabel }} {{ $gayaKartu }}" data-tab="{{ $tab }}" data-ts-card="off">
        <thead class="bg-light">
            <tr>
                @if($canBulk)
                <th scope="col" class="all text-center cr-col-check">
                    <input type="checkbox" class="cr-check-all" aria-label="Pilih semua baris yang bisa diproses">
                </th>
                @endif
                <th scope="col" width="4%" class="all">No</th>
                <th scope="col" data-col="code" class="all text-nowrap cr-col-code" style="width:1%;">Code</th>
                <th scope="col" data-col="desc" class="all cr-col-desc">Description</th>
                <th scope="col" data-col="type" class="text-nowrap" style="width:1%;">Tipe</th>
                <th scope="col" data-col="tanggal" class="text-nowrap" style="width:1%;">Tanggal</th>
                <th scope="col" data-col="paid_at" class="text-nowrap" style="width:1%;">Tanggal Paid</th>
                <th scope="col" data-col="pengaju" class="text-nowrap" style="width:1%;">Requestor</th>
                {{-- PIC & Beneficiary dilebur jadi satu nilai: nama PIC bila ada,
                     bila kosong/"-" memakai beneficiary (diputuskan di _rows). --}}
                <th scope="col" data-col="pic" class="text-nowrap" style="width:1%;">PIC</th>
                <th scope="col" class="text-right text-nowrap" style="width:1%;" data-col="request">Request</th>
                <th scope="col" class="text-right text-nowrap" style="width:1%;" data-col="realisasi">Settlement</th>
                <th scope="col" class="text-right text-nowrap" style="width:1%;" data-col="refund">Refund</th>
                {{-- Kebalikan Refund: settlement melebihi pengajuan, jadi masih ada
                     kekurangan bayar yang ditagihkan balik. --}}
                <th scope="col" class="text-right text-nowrap" style="width:1%;" data-col="claim">Expense Claim</th>
                {{-- Cost = biaya yang diakui (BudgetRequest::realizedCostValue()). --}}
                <th scope="col" class="text-right text-nowrap" style="width:1%;" data-col="cost">Cost</th>
                <th scope="col" data-col="status" class="text-nowrap" style="width:1%;">Status</th>
                <th scope="col" data-col="close_date" class="text-nowrap" style="width:1%;">Close Date</th>
                {{-- Header kosong (hanya untuk pembaca layar), tidak bisa diurutkan, selebar tombolnya. --}}
                <th scope="col" class="text-center all action-column" style="width:1%;"><span class="sr-only">Aksi</span></th>
            </tr>
        </thead>
        <tbody>
            @include('budget-request._rows', ['rows' => $rows, 'canBulk' => $canBulk])
        </tbody>
        <tfoot>
            <tr class="cr-foot-row">
                @if($canBulk)
                <th class="cr-col-check"></th>
                @endif
                <th colspan="8" class="text-right cr-foot-label">Total (data tampil)</th>
                <th class="text-right text-nowrap cr-foot-amount" data-lbl="Request"></th>
                <th class="text-right text-nowrap cr-foot-realisasi" data-lbl="Settlement"></th>
                <th class="text-right text-nowrap cr-foot-var" data-lbl="Refund"></th>
                <th class="text-right text-nowrap cr-foot-claim" data-lbl="Expense Claim"></th>
                <th class="text-right text-nowrap cr-foot-cost" data-lbl="Cost"></th>
                <th colspan="3"></th>
            </tr>
        </tfoot>
    </table>

    {{-- Keadaan kosong: template (bukan baris tbody) karena DataTables yang merender baris. --}}
    <template class="cr-empty-template">
        <div class="tw:flex tw:flex-col tw:items-center tw:gap-4 tw:px-card tw:py-10 tw:text-center">
            <div class="tw:flex tw:h-12 tw:w-12 tw:items-center tw:justify-center tw:rounded-md tw:border tw:border-border tw:bg-surface-2 tw:text-text-dim">
                <i class="fas fa-folder-open" aria-hidden="true"></i>
            </div>
            <div>
                <div class="tw:text-sub tw:font-section tw:text-text">Belum ada Cost Request</div>
                <div class="tw:mt-1 tw:text-body tw:text-text-dim">Belum ada pengajuan pada rentang tanggal dan filter yang sedang dipakai. Coba perlebar rentang tanggalnya, atau buat pengajuan baru.</div>
            </div>
            @if($canAdd)
            <x-tw.button href="{{ url('/budget-request/create') }}" size="sm"><i class="fas fa-plus" aria-hidden="true"></i> Tambah Request</x-tw.button>
            @endif
        </div>
    </template>
</div>
