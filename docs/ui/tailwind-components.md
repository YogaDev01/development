# Komponen Tailwind `x-tw.*` (Fase F2)

Status: **komponen + bukti browser ada; dimuat hanya oleh halaman pilot Feedback (`index/create/show/inbox`) dan halaman Cost Request (`budget-request/index` M1/M5a, `create` M2, `edit` M3, `realize` M4: [`tailwind-budget-request.md`](tailwind-budget-request.md)), tidak oleh layout atau halaman lain.** Dokumen ini melengkapi
[`tailwind.md`](tailwind.md) (versi, build, isolasi, kaskade dasar, larangan). Isi komponen tidak diulang di
sana; sumber kebenaran API adalah berkas Blade-nya (komentar kepala tiap berkas memuat alasan `!`).

| Komponen | Berkas |
|---|---|
| `x-tw.button` | [`resources/views/components/tw/button.blade.php`](../../resources/views/components/tw/button.blade.php) |
| `x-tw.input` | [`.../input.blade.php`](../../resources/views/components/tw/input.blade.php) |
| `x-tw.select` | [`.../select.blade.php`](../../resources/views/components/tw/select.blade.php) |
| `x-tw.textarea` (F4-b) | [`.../textarea.blade.php`](../../resources/views/components/tw/textarea.blade.php) |
| `x-tw.badge` | [`.../badge.blade.php`](../../resources/views/components/tw/badge.blade.php) |
| `x-tw.page-header`, `x-tw.stat` (F4-a) | [`.../page-header.blade.php`](../../resources/views/components/tw/page-header.blade.php), [`.../stat.blade.php`](../../resources/views/components/tw/stat.blade.php); API di [`tailwind-f4-redesain.md`](tailwind-f4-redesain.md) |
| `x-tw.card` | [`.../card.blade.php`](../../resources/views/components/tw/card.blade.php) |
| `x-tw.modal` | [`.../modal.blade.php`](../../resources/views/components/tw/modal.blade.php) |
| `x-tw.table` | [`.../table.blade.php`](../../resources/views/components/tw/table.blade.php) |

Aturan bersama semua komponen: komponen anonim (tanpa kelas PHP), tanpa `route()`/`asset()`/`old()`/`@error`/`@lang`
di dalamnya (nilai dikirim pemanggil lewat prop); kelas `tw:` ditulis **literal penuh** (peta `@php`, bukan rangkaian);
hanya token proyek, tanpa hex baru (satu pengecualian: `--color-on-accent`, lihat (f)); atribut lain diteruskan lewat
`$attributes->merge()`; tanpa `vh/vw/dvh/*-screen`, tanpa `z-index`/`zoom` sendiri.

## (a) Peta token dan celah

Urutan muat CSS di layout: base, ui, tokens, components, compat, pages, shell, motion, luxe, mobile, datatable, refine,
lalu `<style id="tesys-accent-style">`. Definisi yang **menang** (nilai yang dibaca utilitas `tw:` lewat `var()`):

| Kelompok token | Definisi yang menang | Catatan |
|---|---|---|
| Warna, spasi, radius, bayangan, status | `tesys-ui-tokens.css` | menimpa `tesys-ui.css` (blok `:root` awal) dan `tesys-ui-base.css` |
| Skala tipografi `--fs-*`, `--fw-heading`, `--ls-micro` | `tesys-ui-refine.css` | menang atas blok tipografi di `tokens.css`; komentar di `tokens.css` tentang skala itu basi (tracking efektif .02em) |
| `--accent*` kustom | `<style id="tesys-accent-style">` di `layouts/app.blade.php` (turunan `color-mix`) | per perangkat |
| Mode gelap | `html[data-theme="dark"]` di `tokens.css` | spesifisitas (0,1,1) menang atas `:root` mana pun |

**Belum dipetakan di `@theme inline`** (sengaja; dipetakan hanya bila ada komponen yang butuh): chrome/topbar
(`--chrome*`, `--topbar*`), `--text-muted` (legacy, gagal AA), `--border-soft`, `--amber*`, `--gold*`,
`--dur-fast`/`--dur-slow` (hanya lewat `tw:duration-(--dur-fast)`), z-index (tidak ada token; literal 1040-2000 di CSS legacy),
token fokus/disabled (tidak ada; pola literal `0 0 0 3px var(--accent-ring)` dan `opacity .45` di CSS legacy).

**Celah dark/aksen** (temuan, tidak diperbaiki di F2):

| Celah | Dampak |
|---|---|
| `--accent-ring` hanya ditimpa oleh blok `:root` aksen; di gelap `tokens.css` (spesifisitas lebih tinggi) tetap menang | ring fokus gelap selalu burgundy bawaan walau aksen kustom. Tidak memengaruhi komponen: fokus komponen = outline `accent-text`, bukan `accent-ring` |
| Tidak ditimpa aksen: `--shadow-accent`, `--shadow-chrome`, `--sidebar-fade`, `--charcoal`, `--topbar-dim`, `--chrome-text`, `--chrome-dim`, `--sidebar-text-dim` | `tw:shadow-accent` (tombol primer) tetap warna bawaan pada aksen kustom |
| Tidak ada token "teks di atas aksen" | memaksa `--color-on-accent: #fff` (lihat (f)) |

**Kontras terhitung** dari nilai token terang/gelap (WCAG 2.x; skrip `kontras.mjs` + `kontras.txt` hanya ada di folder
kerja sesi, **di luar repo**, sebagai asal angka; bukan bukti repo). Pasangan lain yang dihitung (32 per tema) LULUS dan tidak
dicantumkan (mis. teks utama 11,66-17,14; `accent-text`/surface 8,41 terang dan 7,38 gelap; `text`/`text-dim` pada semua permukaan >= 6,79).

| Pasangan | Terang | Gelap | Batas | Hasil |
|---|---|---|---|---|
| `text-muted` / surface (tidak dipetakan) | 3,30 | 4,81 | 4,5 | **GAGAL terang** (legacy; dibiarkan) |
| `text-faint` / surface-2 | 5,28 | 4,44 | 4,5 | **GAGAL gelap (tipis)** |
| `border` / surface | 1,30 | 1,29 | 3 | **GAGAL** (batas input legacy lemah; temuan desain) |
| `border-strong` / surface | 1,53 | 1,61 | 3 | **GAGAL** |
| `accent` / surface | 8,41 | 2,06 | 3 | **GAGAL gelap**: `accent` tidak boleh jadi indikator fokus di gelap |
| teks status / `*-bg` (6 nada) | 4,81-6,50 | 5,64-6,98 | 4,5 | lulus (terendah: `success`/`success-bg` terang 4,81) |
| `white` / `accent` bawaan | 8,41 | 8,41 | 4,5 | lulus; 8 praset aksen x 2 tema: 6,10-11,48 (normal & hover) |

## (b) API komponen

Pass-through: semua atribut di luar prop (id, name, data-*, aria-*, title, class tambahan, dst.) masuk ke elemen yang disebut.
Nilai prop di luar daftar jatuh ke bawaan (varian/ukuran/nada) kecuali dicatat lain.

### `x-tw.button` (`<button>`, atau `<a>` bila `href` diberikan)

| Prop | Nilai | Bawaan |
|---|---|---|
| `variant` | `primary` `secondary` `danger` `ghost` | `primary` |
| `size` | `sm` (`min-h-ctl-sm px-3 text-data!`) `md` (`min-h-ctl px-4 text-control!`) | `md` |
| `type` | `button` `submit` `reset`; nilai di luar daftar divalidasi dan jatuh ke `button` | `button` |
| `loading` | bool: `disabled` + `aria-busy="true"`, kursor `progress`, tanpa animasi | `false` |
| `disabled` | bool | `false` |
| `href` | string tak kosong -> dirender sebagai `<a href="...">` (di-escape); selain itu `<button>` | `null` |

**Mode tautan (`href`).** Jawaban atas risiko M2 pilot F3 (form GET + tombol kehilangan ctrl/middle-klik, "salin tautan", peran `link` bagi pembaca layar). Semua varian, ukuran, token, dan
target sentuh sama dengan `<button>` (terukur: lebar x tinggi identik dengan kembaran `<button>` di semua keadaan). Perilaku:
- Prop `type` diabaikan; atribut lain (`target`, `rel`, `download`, `data-*`, `aria-*`, `title`, `id`) diteruskan. `target="_blank"` tanpa `rel` otomatis diberi `rel="noopener noreferrer"` (`rel` dari pemanggil menang).
- `disabled`/`loading` pada tautan: dirender **tanpa `href`** (tidak bisa diaktifkan, tidak ikut urutan Tab), dengan `aria-disabled="true" role="link" tabindex="-1"` (`loading` juga `aria-busy="true"`), opacity 45% dan kursor `not-allowed`/`progress` (tanpa `!`: terukur tidak dikalahkan legacy).
  **Batas:** `<a>` tanpa `href` tetap menerima event klik JS (beda dengan `<button disabled>`; terukur `klikJsDisabled=1`): tidak menavigasi, tetapi pemanggil tidak boleh memasang `onclick`/`data-toggle` pada tautan disabled.
- Papan ketik native `<a>`: **Enter** mengaktifkan, **Space tidak** (beda dengan `<button>`); ctrl+klik membuka tab baru (default tidak dicegah).
- Nilai `href` hanya di-escape (HTML); komponen tidak memvalidasi skema. Jangan meneruskan input pengguna mentah (mis. `javascript:`); pemanggil memakai `route()`/`url()`.
- `href` kosong/null/bukan string = komponen turun diam-diam menjadi `<button type="button">` yang tidak melakukan apa-apa; pastikan nilainya terisi. Jangan menimpa `tabindex`/`role`/`aria-disabled` pada tautan disabled (atribut pemanggil menang pada `merge()`).
- Konflik legacy khusus `<a>` dan `!` yang dibutuhkan: lihat (c). Kelas `text-control`/`text-data`/`leading-tight` pada tautan **tanpa** `!` (terukur tidak perlu; reboot BS hanya mengatur `button`).
- **Pilot Feedback memakainya sejak F3-c.** Tombol navigasi di `feedback/{index,create,show}` kini `<x-tw.button href="{{ route(...) }}">` (bukan lagi form GET + tombol); M2 ditutup. Terbukti di Chrome/Edge: `<a href>` tanpa `?`, ctrl+klik membuka tab baru, sentuh 48×48 di mobile.

Slot: isi tombol (ikon + teks; uppercase/truncate taruh di `<span>` dalam slot karena reboot BS `button{text-transform:none;overflow:visible}`).
Warna per varian: primary `bg-accent`/`text-on-accent`/`shadow-accent` (hover `accent-dark`); secondary `surface`+`border`; danger `danger-bg`+`danger`
(hover isi `danger`); ghost transparan `text-dim`. Hover hanya lewat `enabled:hover:`. `disabled:opacity-45` (utilitas bawaan, setara `.btn:disabled`).
Fokus = satu outline (`outline-2 outline-offset-2 outline-accent-text!`).
Kontrak JS: tidak ada handler; `data-toggle`, `onclick`, submit tetap milik pemanggil. Tanpa kelas `.btn`. Hanya untuk tombol BARU:
tombol toolbar/sel tabel tetap legacy (`btn btn-*`, `ts-action-*`).

### `x-tw.input` (`div.form-group` > `label` + `input` + hint + error)

| Prop | Arti |
|---|---|
| `label` | teks label (kosong = tanpa `<label>`); `required` menambah `*` (aria-hidden) |
| `name` | dipakai persis (boleh `details[0][x]`) |
| `id` | id kontrol; kosong = diturunkan dari `name` (karakter selain huruf/angka/`_`/`-` jadi `-`); tanpa keduanya = tanpa `id`/`for`/id hint-error |
| `type` | bawaan `text` |
| `value` | nilai awal (pemanggil memberi `old()`) |
| `error` | teks galat: `aria-invalid="true"`, `aria-describedby`, pesan `role="alert"` (id `{id}-error`), tepi `danger` + latar `danger-bg` |
| `hint` | petunjuk (id `{id}-hint`, masuk `aria-describedby`) |
| `required`, `disabled` | bool, atribut native |

Wadah memakai `tw:text-control tw:leading-body` (ukuran font diwarisi karena reboot BS `input{font-size:inherit}`; **jangan** `tw:text-*` di kontrol).
Tinggi `h-ctl`, `rounded-sm!`, fokus outline + `border-accent-text`, `placeholder:text-text-faint`.
Kontrak JS: wadah tetap `.form-group` (`tesys-ui.js` menghubungkan `<label>` ke kontrol hanya bila `.form-group` berisi satu kontrol).

### `x-tw.select` (`div.form-group` > `label` + `select` + hint + error)

Prop sama dengan `x-tw.input` tanpa `type`/`value`, ditambah `placeholder` (opsi pertama `<option value="">`).
**`multiple` TIDAK didukung** (tinggi tetap `h-ctl` satu baris akan memotong daftar); untuk multi-pilih pakai select legacy.
Slot = `<option>`/`<optgroup>` dari pemanggil (opsi terpilih ditandai pemanggil). `<select>` native; TomSelect/`TesysSearchableSelect` boleh di-init pada id-nya,
komponen tidak menyentuh `.tss-*`, `.ts-wrapper`, `.flatpickr-*` (interaksi dengan plugin itu **tidak diuji**).

### `x-tw.textarea` (`div.form-group` > `label` + `textarea` + hint + error) — F4-b, aditif

Prop sama dengan `x-tw.input` tanpa `type`, ditambah `rows` (bawaan 4). `value` = isi awal (ter-escape `{{ }}`; pemanggil memberi `old()`), bukan atribut.
`id` diturunkan dari `name` bila kosong; `error` -> `aria-invalid`, `aria-describedby`, pesan `role="alert"`; `hint` -> `aria-describedby`.
Tinggi mengikuti `rows` (tanpa `h-ctl`), `px-3 py-2`, `resize-y`; wadah `.form-group` (kontrak label-for `tesys-ui.js`) membawa ukuran font (`tw:text-control`).
Kelas `!`: hanya `rounded-sm!` dan `outline-accent-text!` (sama dengan `x-tw.input`/`select`); tidak ada `!` baru. Dipakai: `feedback/create`, `feedback/show` (respond). Galeri: bagian `x-tw.input / x-tw.select`.

### `x-tw.badge` (`<span>`)

| Prop | Nilai | Bawaan |
|---|---|---|
| `tone` | `neutral` `attention` `progress` `positive` `done` `danger` (6 nada WorkflowStatus) atau alias | `neutral`; nilai tak dikenal = `neutral` |
| `size` | `sm` (`px-2 py-1 text-caption`) `md` (`px-3 py-1 text-data`) | `sm` |

Pewarnaan nada: neutral = `surface-2`/`text-dim`/`border`; attention = `warning-bg`/`warning`; progress = `info-bg`/`info`; positive = `success-bg`/`success`;
done = `darkbadge-bg`/`darkbadge`; danger = `danger-bg`/`danger` (selaras `.ts-status-*` di `tesys-ui-components.css`). Alias: `primary`,`info` -> progress;
`success` -> positive; `warning` -> attention; `secondary`,`light` -> neutral; `dark` -> neutral **[UNKNOWN / NEEDS CONFIRMATION: desain "dark"]**.
Dasar: `.badge-dark` legacy (tema terang; `compat.css`/`luxe.css`) = latar solid `--darkbadge` + teks putih, bukan `--charcoal`; `neutral` (abu muda + teks `text-dim`) berbeda dari legacy gelap solid;
nada `done` memakai keluarga `darkbadge` dengan warna terbalik (latar `darkbadge-bg`, teks `darkbadge`). Apakah `dark` harus gelap solid, `done`, atau `neutral` belum diputuskan.
Tidak menggantikan `x-status` (status workflow) maupun `badge-{status_color}` massal. Ukuran `sm` = 12px, lebih besar dari `.ts-status-sm` (10,5px) karena komponennya berbeda.

### `x-tw.card` (`<div>` murni)

| Prop | Nilai | Bawaan |
|---|---|---|
| `padding` | `md` (`p-card`) `sm` (`p-card-sm`) `none` (isi tanpa padding; header/footer tetap `px-card`) | `md` |

Slot: `header` (`<x-slot:header>`, berpembatas bawah), default, `footer` (`<x-slot:footer>`, berpembatas atas, `bg-surface-2`). Header/footer hanya muncul bila berisi.
Header/footer dirender sebagai `<div>` (bukan `<header>`/`<footer>`): di luar `section/article/main`, kedua tag itu terpetakan menjadi landmark `banner`/`contentinfo`
(terukur 6 elemen di galeri sebelum perbaikan; setelahnya 0, butir `G4-landmark`).
Tanpa kelas `.card`/`.card-header`/`.card-body` supaya JS toolbar/ikon legacy (`tesys-toolbar-actions`, `tesys-icon-buttons`, `tesys-row-actions`) tidak aktif tak sengaja.
Tanpa `!`. Kartu ber-toolbar/DataTables tetap `.card` legacy.

### `x-tw.modal` (kerangka Bootstrap 4, isi Tailwind)

| Prop | Arti |
|---|---|
| `id` | **wajib**; id `.modal`, dasar `{id}-title` (`aria-labelledby`) |
| `size` | `sm` `lg` `xl` (kelas `modal-sm|lg|xl` pada `.modal-dialog`); kosong = normal |

Slot: `title` (span `role="heading" aria-level="2"`), default (di `<section>` yang menggulir), `footer` (`<x-slot:footer>`, rata kanan).
Kerangka dipertahankan persis: `div.modal.fade[tabindex=-1][role=dialog][aria-hidden=true][aria-labelledby]` > `div.modal-dialog[role=document]` > `div.modal-content`.
Isi `.modal-content` = tiga `<section>` ber-`tw:` (kepala, isi, kaki; bukan `<header>`/`<footer>` karena menjadi landmark `banner`/`contentinfo`, terukur di `G4-landmark`; bukan `div` karena aturan `compat.css`); **tanpa** `.modal-header/.modal-body/.modal-footer/.close` dan tanpa `div` anak langsung `.modal-content`
(aturan legacy `.modal*` dan aturan layar pendek di `compat.css` akan bentrok). Tombol tutup `button[data-dismiss=modal][aria-label="Tutup"]` 40x40.
Kontrak JS: komponen **tidak** membuka/menutup modal; tetap `data-toggle="modal"` / `$('#id').modal('show')`, `shown.bs.modal` (`tesys-ui.js`), `hide.bs.modal` (`tesys-searchable-select.js`).
Radius/bayangan `.modal-content` tetap legacy (12px `!important` di `shell.css`); tidak menambah `z-index`/`zoom`.

### `x-tw.table` (`div.table-responsive` > `table`)

| Prop | Arti | Bawaan |
|---|---|---|
| `id` | id `<table>` | kosong |
| `dense` | `td` lebih rapat (`py-1!`, >= 641px) | `false` |
| `cardMode` | `false` -> `data-ts-card="off"` pada `<table>` | `true` (perilaku legacy `tesys-table.js` di <= 640px) |

Slot: `head` (`<thead>`; **satu `<tr>` tanpa colspan/rowspan**, kontrak `tesys-table.js`; label "No"/"Aksi"/"Status" dipertahankan pemanggil; `data-label` jangan ditulis manual),
default (isi `<tbody>`), `foot` (`<tfoot>`, opsional). Pembungkus `div.table-responsive` dipertahankan (`tesys-column-filter.js` memakai `closest('.table-responsive')`).
`<table>` tanpa kelas `.table`/`.dataTable` (aturan `body.tesys-ui .table*` milik refine tidak berlaku); pemanggil menulis `th`/`td` polos. DataTables 1.x tetap; komponen
tidak menyentuh opsi `dom`, kolom dinamis, atau `tfoot` ringkasan (F3+).

## (c) Aturan kaskade dan daftar `!` terukur

Dasar (rinci di [`tailwind.md` §7](tailwind.md)): utilitas `tw:` ada di `@layer utilities`, CSS legacy tanpa layer **menang** pada properti yang sama; `tw:x!` menang atas
semuanya, termasuk `!important` legacy. Aturan pakai: **`!` hanya** pada properti yang terbukti dikalahkan legacy, diukur di browser (butir `G3-bang`: bentuk dengan `!` dibandingkan bentuk
tanpa `!` di 152 konteks; 46 pasangan kelas-elemen (kini termasuk `<a>`), semuanya perlu di minimal satu konteks; 32 kelas `!` komponen di artefak = 32 terukur, 0 tak terukur, tidak ada yang tak perlu; butir `G3-bang` itu belum dijalankan ulang sesudah M5a, 6 kelas `!` tabel Cost Request diukur terpisah, lihat bawah); tidak ada `important` global. Jangan mencampur kelas Bootstrap dan `tw:` untuk properti
yang sama pada satu elemen. Daftar yang ada sekarang (sumber: komentar kepala tiap komponen):

| Komponen / elemen | `!` | Alasan (aturan legacy yang menang tanpa `!`) |
|---|---|---|
| button, input, select, tutup modal | `rounded-sm!` | reboot BS `button{border-radius:0}`; `:focus-visible{border-radius:4px}` (`tesys-ui.css`). Pada input/select hanya terukur perlu saat fokus |
| button, input, select, tutup modal | `outline-accent-text!` (hanya warna, saat `focus-visible`) | `:focus-visible{outline:2px solid var(--accent)}`; `--accent` di gelap hanya 2,06:1. Lebar/offset outline tanpa `!` (nilai sama dengan legacy) |
| button | `text-control!`/`text-data!`, `leading-tight!` | reboot BS `button{font-size:inherit;line-height:inherit}` |
| button (hanya `<button>`) | `text-control!`/`text-data!`, `leading-tight!` | reboot BS `button{font-size:inherit;line-height:inherit}`; pada `<a>` terukur TIDAK perlu, tautan memakai bentuk tanpa `!` |
| button | `disabled:cursor-not-allowed!` / `disabled:cursor-progress!` | `button{cursor:pointer}` di `tesys-ui.css` (hanya `<button>`; tautan disabled: kursor tanpa `!`) |
| button mode `href` (`<a>`) | `bg-accent!` `bg-surface!` `bg-danger-bg!` (latar varian primary/secondary/danger; ghost transparan tanpa `!`), `hover:bg-accent-dark!` `hover:bg-surface-2!` `hover:bg-danger!` | reboot BS `a{background-color:transparent}`: sebelum `!`, `<a>` terukur transparan di semua varian berlatar (primary `rgba(0,0,0,0)` vs token `rgb(146,35,43)`) |
| button mode `href` (`<a>`) | `text-on-accent!` `text-text!` `text-danger!` `text-text-dim!`; `hover:text-surface!` (danger), `hover:text-text!` (ghost) | `a{color:inherit}` (`tesys-ui.css:54`), `body.tesys-ui a` / `a:hover` (`compat.css:365-366`), BS `a:hover`: sebelum `!`, warna teks terukur `rgb(146,35,43)` (accent-text) dan saat hover `rgb(126,28,36)` (accent-dark) di semua varian, termasuk tautan disabled; kontras hover gelap jatuh ke 1,85:1 |
| tutup modal | `text-sub!`, `leading-tight!` | reboot BS `button` (sama di atas) |
| modal kepala/kaki (`<section>`) | `flex!` | reboot BS `header,footer,section{display:block}` |
| table | `text-data!` | legacy 12px vs token 12,5px |
| table `th` | `text-th!` `normal-case!` `tracking-micro!` `text-text-dim!` `bg-surface-2!` `py-cell-y!` `border-border-strong!`, `px-cell-x!` (kecuali `.dataTable`) | `tesys-ui.css` memberi th 10px KAPITAL, letter-spacing .03em, `text-faint`, `surface`, padding 7px 9px |
| table `td` | `px-cell-x!` (kecuali `.dataTable`); `dense`: `py-1!` | padding legacy 9px |

**Tabel Index Cost Request (M5a, bukan komponen; satu string `$gayaTabel` di `<table>` pada `budget-request/_table.blade.php`): 6 kelas `!` baru M5a (tabel di bawah) + 6 kelas `!` M5b (`$gayaKartu`, tabel sesudahnya) = 12 total**; 6 kelas M5a semuanya varian lebar `tw:min-sm:` (>= 641px), tiap kelas diukur di Chrome 155 (CDP, DB uji, 1440px, tab CA/CP/DB): kelas dilepas dan diganti aturan setara tanpa `!` di `@layer utilities`, lalu nilai terhitung dibandingkan. Anggaran rencana 14; terpakai 12. Penjaga: `TailwindAssetTest::test_tabel_cost_request_m5a_*` (daftar ini = himpunan persis masing-masing string; menambah `!` harus mengubah tes dan dokumen ini).

| Kelas (`tw:min-sm:` dihilangkan) | Terukur tanpa `!` -> dengan `!` | Aturan legacy yang menang tanpa `!` |
|---|---|---|
| `[&_td:not(.cr-col-check)]:px-2!` | td `padding-left` 12px -> 8px | `refine.css` §8 `table.dataTable tbody td{padding-left/right:var(--cell-x) !important}` |
| `[&_thead_th:not(.cr-col-check)]:pl-2!` | th `padding-left` 12px -> 8px | `refine.css` §8 `table.dataTable thead th{padding:10px var(--cell-x)}` (aturan tak berlayer selalu menang atas utilitas berlayer tanpa `!`) |
| `[&_:is(th,td).cr-col-check]:px-0!` | padding th/td/tfoot-th kolom centang 12px -> 0px | dua aturan di atas |
| `[&_th:is(.cr-col-check,.cr-col-code,.action-column)]:sticky!` | th Code dan Action (`.sorting`) `position` relative -> sticky | DataTables `thead .sorting{position:relative}` (alasan yang sama dengan komentar lama di `index.blade.php`) |
| `[&_tfoot_th]:text-data!` | tfoot th `font-size` 12px -> 12,5px | `refine.css` §8 `body.tesys-ui .table tfoot th{font-size:var(--fs-th) !important}` |
| `[&_tfoot_th:not(.cr-foot-label)]:text-text!` | tfoot th `color` redam (`--text-dim`) -> `--text` | `refine.css` §8 `body.tesys-ui .table tfoot th{color:var(--text-dim)}` |

Terukur TIDAK perlu `!` (dibuktikan tanpa `!` menang, atau legacy tidak mengatur propertinya): penanda NOB `shadow-[inset_3px_0_0_var(--danger|--warning)]` (aturan bayangan kolom pertama `datatable.css` berkecuali `.ts-card-table`; sejak M5b tabel ini ber-`data-ts-card="off"` sehingga TIDAK ber-`.ts-card-table`, aturan itu berlaku dan ditimpa balik oleh dua `!` min-sm M5b di bawah; pada M5a awal kelas itu terpasang oleh `tesys-table.js`), latar sel `bg-surface`, kolom beku (`sticky` pada td, `left/right`, `z-3`, garis `shadow-[1px_0_0_var(--border)]`), lebar/jarak Description (`whitespace-normal`, `min-w-[9rem]`, `min-w-[17rem]` hanya `min-[1440px]:`, `max-w-[20rem]`), `whitespace-nowrap`. Tidak dipindah ke tw: tipografi th/td dan latar th (sudah token lewat `refine.css` §8, nilai sama), sorot baris dan baris tercentang (`datatable.css`, `!important`, sudah menang atas aturan lama), nominal nol surut (`refine.css` §14d: selektor atribut `[data-raw="0"]` bernilai angka memerlukan tanda kutip di dalam nama kelas, tidak aman bagi pemindai Tailwind dan `TailwindAssetTest::tokenTw`).

**Tabel Index Cost Request (M5b, kartu mobile `$gayaKartu`): 6 kelas `!` tambahan** (varian `tw:max-sm:` kecuali dua yang `tw:min-sm:`). Penjaga: `TailwindAssetTest::test_kartu_mobile_cost_request_m5b_*`. Bukti `tailwind-evidence/m5b/`.

| Kelas | Terukur / alasan | Aturan legacy yang menang tanpa `!` |
|---|---|---|
| `max-sm:[&>:is(tbody,tfoot)>tr>*]:[padding:var(--crk-p,6px_0)]!` | tanpa `!`: 126 elemen (CA) / 104 (CP) berbeda, mis. `6px 0 -> 9px 12px` | `refine.css` §8 padding th/td (`!important` pada td) |
| `max-sm:[&>:is(tbody,tfoot)>tr>*]:[border-width:var(--crk-b,0_0_1px)]!` | 33 sel berbeda di CA (mis. `0 -> 0 0 1px 0`, `0 -> 1px 0 1px 0`) | `.table-bordered` + garis `refine.css` §14d/DataTables (tak berlayer) |
| `max-sm:[&_td.cr-col-check]:shadow-none!` | tanpa `!`: bayangan aksen inset 3px + garis 1px muncul pada sel centang | `datatable.css` penanda baris tercentang (`!important`) |
| `max-sm:[&_th[data-lbl]]:text-text!` | warna angka tfoot `rgb(90,79,81)` -> `rgb(37,24,27)` (3 elemen) | `refine.css` §8 `tfoot th{color:var(--text-dim)}` |
| `min-sm:[&>thead>tr>th:first-child,&>tbody>tr>td:first-child:not([colspan])]:[z-index:var(--crk-z,3)]!` | pemulih M5a (z-index sel pertama); bukti = selisih desktop 0 vs M5a (`desktop-1440-1280-selisih.txt`); tidak ada ukuran per-kelas | `datatable.css` §1b (kolom pertama beku, `table.dataTable:not(.ts-card-table)`) kini berlaku karena `data-ts-card="off"` |
| `min-sm:[&>tbody>tr:not(:has(:checked))>td:first-child:not([colspan])]:[box-shadow:var(--crk-sh,none)]!` | pemulih M5a (bayangan sel pertama; baris tercentang dikecualikan); bukti sama | `datatable.css` §1b |

Total `!` tabel Cost Request: 12 (6 M5a + 6 M5b), di bawah anggaran 14. Kelas non-`!` M5b yang sengaja memakai variabel `--crk-*` agar cukup satu `!` per properti: lihat `_table.blade.php`.

Semua `!` tabel komponen `x-tw.table` berlaku **hanya >= 641px** (varian lebar `min-sm`), dan `px-*!` dikecualikan untuk `.dataTable`:
- mode kartu <= 640px (`tesys-table.js` memasang `.ts-card-table` di SEMUA tabel daftar di `.main .content`, jadi tidak bisa dibedakan lewat kelas) memakai padding `6px 0`, dan `!` merusaknya;
- DataTables memberi `th` padding kanan 26px (ruang ikon urut) yang akan terpotong `px-cell-x!`.
Konsekuensi: di <= 640px gaya th/td mengikuti legacy.

`!` dihapus karena terbukti tidak perlu: `border-b` pada `th` dan `py-cell-y` pada `td` (nilai legacy sudah sama; kelasnya tetap ada tanpa `!`). `outline-none!` dan seluruh `ring-*` pada kontrol dihapus (diganti outline).
Tanpa `!` karena nilai legacy sudah sama: `text-align` kiri, bobot 600 th, garis bawah td, `vertical-align: middle`.
Label `x-tw.input`/`x-tw.select` tanpa `!`: warna `text-dim` disamakan dengan legacy; ukuran token 12,5px kalah dari `luxe.css` (12px) di dalam `.form-group` (deviasi diterima).
Komponen selain `x-tw.button href` tidak memakai `<a>`, `h1-h6`, `p`, `small`, `legend` (CSS legacy `a` mengalahkan warna `tw:`; `x-tw.button href` mengatasinya dengan `!` terukur di atas). Terukur TIDAK perlu `!` pada `<a>`: `text-decoration` (tetap `none` saat hover/fokus), `display`, ukuran/padding, tepi, bayangan, kursor, outline fokus selain yang sudah ber-`!` (`rounded-sm!`, `outline-accent-text!` terbukti perlu juga pada `<a>`). Posisi `<link tesys-tw.css>` tidak mengubah hasil (layered).

## (d) Batas F2 vs F3+

| F2 (ada dan diukur) | F3+ (tidak dikerjakan) |
|---|---|
| 7 komponen di atas; tabel **statis** (`x-tw.table` + `th`/`td` polos) | DataTables (opsi `dom`, toolbar, pagination), kolom dinamis, `tfoot` ringkasan terintegrasi |
| Galeri statis hasil render Blade asli, diukur di Chrome/Edge | Halaman Blade nyata, pemuatan `tesys-tw.css` di layout/halaman (lewat `@aset`) |
| `x-status` tetap untuk status workflow (tidak diganti `x-tw.badge`) | Pilot Feedback; migrasi halaman |
| Tombol toolbar/sel tabel (`btn btn-*`, `ts-action-*`) dan kartu ber-toolbar tetap legacy | Redesain/penyatuan kontrol legacy |
| Modal: kerangka Bootstrap dipertahankan | Modal tanpa Bootstrap JS |

Tidak ada controller/model/route/test aplikasi yang berubah di F2; tidak ada halaman yang memuat Tailwind.

## (e) Render galeri dan menjalankan CDP

Langkah lengkap (build produksi + uji, `render.php`, `cdp-run.mjs`, opsi, perilaku proses browser) ada di
[`tailwind-evidence/README.md`](tailwind-evidence/README.md#cara-menjalankan-ulang); tidak diulang di sini. Ringkas komponen harness (semuanya di `scripts/tailwind-harness/`):

| Berkas | Peran |
|---|---|
| `render.php` | merender `galeri.blade.php` dengan container Illuminate\View minimal (tanpa boot Laravel, tanpa DB, tanpa `.env`) ke `tools/tailwind/uji/galeri-{tw,baseline,tw-awal}.html` (ter-ignore git) |
| `galeri.blade.php` | galeri komponen + elemen legacy kanonik, CSS legacy dalam urutan layout, jQuery/Bootstrap/DataTables/`tesys-table.js`/`tesys-ui.js` |
| `cdp-run.mjs` | runner CDP Node 22 (tanpa dependensi), Chrome lalu Edge headless, port 9333/9334, user-data-dir sementara; hanya mematikan proses miliknya |

Prasyarat: `public/css/tesys-tw.css` produksi sudah dibangun; butir `G6-fixed-inset-0` memakai artefak uji (`-Target uji`).
Runner TIDAK mengembalikan kode keluar non-nol untuk butir ukur yang FAIL (mis. target sentuh 44px); status ada di `hasil-*.json`. Pengecualian: butir `G0-proses-bersih` (proses/folder sisa) dan kegagalan runner menghasilkan kode keluar 1.

## (f) Ringkasan bukti, NOT RUN, deviasi

Bukti: [`tailwind-evidence/`](tailwind-evidence/README.md) (`hasil-chrome.json`, `hasil-edge.json`, 12 PNG Chrome). Dijalankan 2026-10-25, **Chrome 155.0.8059.86** dan
**Edge 156.0.4314.42** (Chromium headless, galeri `file:///`), 4 keadaan (terang/gelap x aksen bawaan/kustom Teal) x 6 viewport utama (+ 3 viewport batas).

| Hasil | Chrome | Edge |
|---|---|---|
| PASS | 40 | 40 |
| FAIL | 1 (`G5-target-sentuh-44`) | 1 (identik) |

Butir PASS mencakup (butir tautan `x-tw.button href` yang baru: `G2-token-tautan`, `G3-fokus-tautan`, `G4-tautan-papan-ketik`, `G4-aria-tautan`, `G7-kontras-tautan`, `G5-tautan-sentuh`; butir baru F2: `G4-landmark` dan `G0-proses-bersih`; hash galeri/komponen yang diuji ada di `detail.lingkungan` pada JSON): isolasi legacy (46 elemen, 0 selisih; kontrol positif terdeteksi), kesesuaian token (1.596 pengecekan), tiap `!` perlu, satu indikator fokus, radius saat fokus, kursor disabled,
label, landmark (0 `banner`/`contentinfo`), proses browser bersih, mode kartu mobile, kontras fokus >= 5,10:1 pada 8 praset x terang/gelap, fokus di `forced-colors`, urutan Tab/Shift+Tab/Enter/Space, perilaku modal (fokus masuk, Escape, backdrop zoom .8,
viewport pendek), ARIA (14 pemeriksaan), target sentuh >= 24px, ketuk (emulasi sentuh Chromium), zoom, tanpa overflow horizontal, kontras teks >= 4,81:1.

**NOT RUN (tidak boleh diklaim; Firefox diuji kemudian di F3-d, lihat `tailwind.md`):** Firefox; WebKit/Safari; perangkat sentuh fisik / emulator iOS-Android; halaman Blade nyata di aplikasi; plugin CSS/JS lain (TomSelect/`TesysSearchableSelect` pada
`x-tw.select`, flatpickr, SweetAlert); pembaca layar nyata; uji kinerja dan `prefers-reduced-motion`.

**`TailwindAssetTest`:** versi terbaru (pasca-F3) lulus — exit 0, 11 tes, 495 asersi (477 sebelum prop `href`). Sampai F2 ia dijalankan sebagai pengecualian CLAUDE.md §4.3 (config cache ada); pada F3 config cache dihapus pengguna dan langkah 1–5 §4.3 terpenuhi (rinci: `tailwind.md` §9 dan §11).

**Deviasi dan temuan tercatat:**

| # | Deviasi | Status |
|---|---|---|
| 1 | Target sentuh < 44px: tombol 34/30px, input/select 34px, tutup modal 40px (390x844). Kontrol legacy sama (`.btn` 34px, `.form-control` 36px). Lolos 24px (WCAG 2.2 AA 2.5.8) | keputusan desain terbuka |
| 2 | Label 12,5px (token) kalah dari 12px legacy (`luxe.css`) di `.form-group`; tampil sama dengan label legacy | diterima |
| 3 | Outline fokus pada zoom .8 (>= 901px) dibulatkan Chromium menjadi 1 piksel perangkat (computed 1,25px), sama dengan outline legacy; ring lama lebih tebal (perkiraan, tidak diukur ulang). Menebalkan butuh `!` pada lebar outline: tidak dilakukan | diterima |
| 4 | Aksen kustom bebas terang (mis. `#ffd400`) gagal kontras teks tombol primer (1,43:1) karena `on-accent` putih; sama dengan tombol legacy. Praset 8 warna lulus | keterbatasan pemilih warna bebas |
| 5 | `--color-on-accent: #fff` = satu-satunya hex di `resources/tailwind/tesys-tw.css` (legacy `.btn-primary` juga literal `#fff`; `--chrome-text` gagal 4,5:1 pada Teal/Forest/Rust: 3,85-3,93). `TailwindAssetTest` mengizinkan `#fff` karena juga nilai internal Tailwind, jadi tes tidak menjaga ini | BELUM disetujui pemilik; keputusan: terima di F2 atau token `--on-accent` di `tesys-ui-tokens.css` (F3); UNKNOWN / PERLU KONFIRMASI |
| 6 | Modal yang dibuka programatik (`.modal('show')` tanpa `data-toggle`) tidak mengembalikan fokus ke pemicu (perilaku Bootstrap 4; Chrome dan Edge berbeda hasil akhir). Pemanggil mengembalikan fokus sendiri | catatan pemakaian |
| 7 | Batas input `--border` vs latar 1,3:1 dan `border-strong` 1,53:1 (kontras < 3:1) | temuan desain legacy, bukan komponen |
| 8 | `badge` ukuran `sm` 12px vs `.ts-status-sm` 10,5px | disengaja (komponen berbeda) |

Artefak build terukur terbaru (prop `href`, dibangun dan `-Cek` exit 0 oleh executor; diverifikasi ulang sesi utama: `-Cek` exit 0, hash dan ukuran sama): produksi `public/css/tesys-tw.css` **10.400 B**, SHA-256 `f2215381e0054416d5448c3923abffada988e9d4ed181583811dca28edabe258`; uji `tools/tailwind/uji/tesys-tw.uji.css` 21.976 B, `9bfdbacb256ae9f445a9baf5e2ac89ec8a095c3a4723224ad7aaf0005e1ac956`. Riwayat F2 (diverifikasi sesi utama): produksi 9.109 B, SHA-256 `97017fff5e3f72da4c94b665146b06331535ce88e56b623bca74e82a2410de86`
(`build-tailwind.ps1 -Cek` exit 0, deterministik); uji `tools/tailwind/uji/tesys-tw.uji.css` 20.977 B, `5dfc4e0a6dd0cdbb202958a4a825d7c7c279e332c5efc3d14acd57bb0d96627f`.
Artefak produksi: 112 blok aturan `.tw\:` (diverifikasi Grep; satu blok memuat dua selektor `td`/`th`) + 1 blok universal `*,:before,:after,::backdrop` (cadangan peramban lama; total 113 blok bergaya),
19 `@property --tw-*`, tanpa safelist, tanpa `*-screen`/`dvh`.

## (g) Keputusan terbuka dan risiko

| # | Hal | Catatan |
|---|---|---|
| 1 | `--color-on-accent: #fff` = satu-satunya literal hex baru, pengecualian atas CLAUDE.md §9 ("tanpa hex baru"); **BELUM disetujui pemilik**. Opsi: terima di F2, atau token `--on-accent` di `tesys-ui-tokens.css` pada F3 | UNKNOWN / PERLU KONFIRMASI; perubahan token = persetujuan pemilik |
| 2 | Target sentuh: naikkan ke 44px atau terima 34/30px seperti legacy | memengaruhi `--ctl-h`, kepadatan UI; keputusan desain |
| 3 | Nada `dark` pada `x-tw.badge` (dasar di (b) badge) | UNKNOWN / NEEDS CONFIRMATION |
| 4 | Matriks peramban wajib (Firefox/Safari belum diuji; `@property`, `:has()`, `zoom`, outline + `border-radius` bisa berbeda) | lihat `tailwind.md` §12 |
| 5 | Komponen Blade baru: di server perlu `php artisan optimize:clear` setelah unggah (cache view), dan versi aset CSS harus naik saat `tesys-tw.css` dimuat (CSS di-cache 1 tahun) | langkah manusia/deploy |
| 6 | Setiap `!` adalah utang kaskade: bila CSS legacy berubah/dibuang, `!` bisa tak perlu lagi atau mengunci nilai usang | uji ulang `G3-bang` bila CSS legacy tabel/tombol/modal diubah |
| 7 | Mode kartu tabel <= 640px dan `.dataTable` mengandalkan legacy; komponen belum mandiri | F3+ |
| 8 | Bukti hanya Chromium dan galeri statis | jangan diperlakukan sebagai bukti halaman nyata |
| 9 | Apakah `public/css/tesys-tw.css` produksi boleh ikut dideploy sebelum ada halaman yang memuatnya | rekomendasi: boleh diunggah (inert) tetapi tidak wajib; keputusan pemilik |
| 10 | Insiden proses (INFO): `chrome.exe --version` pernah membuka tab di sesi Chrome pengguna; alias `python` Windows Store menggantung | pencegahan: [`tailwind-evidence/README.md`](tailwind-evidence/README.md#keselamatan-proses) |
