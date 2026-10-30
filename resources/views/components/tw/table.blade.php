{{--
    <x-tw.table> — tabel Tailwind (prefix tw): div.table-responsive > table.

    Pembungkus tetap div.table-responsive (kelas Bootstrap) karena
    tesys-column-filter.js memakai closest('.table-responsive'). <table> TIDAK
    diberi kelas .table / .dataTable, jadi aturan "body.tesys-ui .table*"
    milik refine.css tidak berlaku; tampilan th/td diatur komponen ini lewat
    varian turunan pada <table> (pemanggil menulis th/td polos, tanpa kelas).

    Props:
      id       : id <table>
      dense    : true -> jarak vertikal sel lebih rapat
      caps     : true -> header kolom KAPITAL (nilai legacy th dibiarkan, tanpa ! baru);
                 false (default) = huruf biasa (perilaku lama)
      cardMode : true (default) = perilaku legacy tesys-table.js (tampil kartu
                 di layar <= 640px untuk DataTable / data-ts-card-scope);
                 false -> data-ts-card="off" pada <table>

    Slot:
      head    : <x-slot:head><tr><th>…</th></tr></x-slot:head> — HARUS satu <tr>
                tanpa colspan/rowspan (kontrak tesys-table.js). Label kolom
                "No", "Aksi", "Status" dipertahankan pemanggil; data-label
                JANGAN ditulis manual (diisi tesys-table.js).
      default : isi <tbody> (baris <tr>)
      foot    : <x-slot:foot> opsional, isi <tfoot>

    Atribut lain (class, data-*, aria-*) diteruskan ke <table>. DataTables 1.x
    tetap; komponen tidak menyentuh opsi dom/kolom dinamis. Tabel dengan
    toolbar DataTables / tfoot ringkasan (F3 dan seterusnya) tetap legacy.

    Sufiks ! (aturan legacy tak berlayer tesys-ui.css:299-308 menang atas
    utilitas berlayer biasa). Semua yang tersisa terbukti perlu pada tabel polos
    (ukur CDP, butir G3-bang), dan semuanya hanya berlaku >= 641px (varian min-sm)
    supaya mode kartu <= 640px tetap milik legacy. Nilai token vs legacy tabel polos:
      table : text-data!                       (legacy 12px, token 12,5px)
      th    : text-th! normal-case! tracking-micro! text-text-dim! bg-surface-2!
              py-cell-y! border-border-strong!, px-cell-x! (kecuali .dataTable)
              (legacy 10px KAPITAL, letter-spacing .03em, text-faint, surface,
              padding 7px 9px, border-bottom --border)
      td    : px-cell-x! (kecuali .dataTable)  (legacy 9px)
              dense: py-1!
    TANPA ! (terbukti tak perlu): th border-b (legacy sudah 1px solid) dan td py-cell-y
    (legacy 9px = token; mode kartu legacy 6px tetap menang).
    Tidak diberi ! karena nilai legacy sudah sama: text-align kiri, bobot 600
    (font-heading), garis bawah td --border, vertical-align middle.

    Batas: di <= 640px gaya th/td mengikuti legacy (mode kartu atau tabel biasa);
    komponen dirancang hidup berdampingan dengan CSS legacy (belum mandiri).
--}}
@props([
    'id'       => null,
    'dense'    => false,
    'cardMode' => true,
    'caps'     => false,
])

@php
    // ! hanya pada properti yang terbukti dikalahkan legacy (ukur CDP, butir G3-bang), dan
    // hanya >= 641px (varian min-sm): di <= 640px tesys-table.js/datatable.css memakai mode
    // kartu (td padding 6px 0, th dsb.) dan ! di sana akan merusaknya. Kelas .ts-card-table
    // terpasang di SEMUA tabel daftar pada .main .content di segala lebar, jadi tidak bisa
    // dipakai sebagai pengecualian. Tabel .dataTable dikecualikan untuk px th/td: DataTables
    // memberi th padding kanan 26px (ruang ikon urut) yang akan terpotong oleh !.
    $dasar = 'tw:w-full tw:border-collapse tw:text-text tw:min-sm:text-data! tw:min-sm:[&_th]:text-th! tw:min-sm:[&_th]:text-text-dim! tw:min-sm:[&_th]:bg-surface-2! tw:min-sm:[&:not(.dataTable)_th]:px-cell-x! tw:min-sm:[&_th]:py-cell-y! tw:min-sm:[&_th]:border-border-strong! tw:[&_th]:border-b tw:[&_th]:font-heading tw:[&_th]:text-left tw:min-sm:[&:not(.dataTable)_td]:px-cell-x! tw:[&_td]:py-cell-y tw:[&_td]:align-middle';

    $jarak = $dense
        ? 'tw:min-sm:[&_td]:py-1!'
        : '';

    // Huruf header: bawaan = huruf biasa + tracking token (kedua nilai dikalahkan legacy, jadi !).
    // caps = KAPITAL: kelas ! di atas dilepas dan nilai legacy th (kapital, tracking .03em) dibiarkan
    // berlaku; uppercase eksplisit (tanpa !) hanya menyamakan nilai itu. Tidak ada kelas ! baru.
    $huruf = $caps
        ? 'tw:min-sm:[&_th]:uppercase'
        : 'tw:min-sm:[&_th]:normal-case! tw:min-sm:[&_th]:tracking-micro!';

    $tambahan = [
        'class' => trim($dasar . ' ' . $huruf . ' ' . $jarak),
    ];
    if ($id) { $tambahan['id'] = $id; }
    if (! $cardMode) { $tambahan['data-ts-card'] = 'off'; }
@endphp

<div class="table-responsive">
    <table {{ $attributes->merge($tambahan) }}>
        @if (isset($head) && $head->hasActualContent())
            <thead>{{ $head }}</thead>
        @endif
        <tbody>{{ $slot }}</tbody>
        @if (isset($foot) && $foot->hasActualContent())
            <tfoot>{{ $foot }}</tfoot>
        @endif
    </table>
</div>
