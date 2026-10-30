# Bukti pengukuran browser — komponen Tailwind `x-tw.*` (F2, tahap E2b-1; ditambah prop `href` pada `x-tw.button`)

Bukti nyata (browser sungguhan, bukan simulasi) bahwa tujuh komponen
`resources/views/components/tw/{button,input,select,badge,card,modal,table}.blade.php`
dan artefak produksi `public/css/tesys-tw.css` hidup berdampingan dengan CSS legacy
tanpa merusaknya, mengikuti token proyek, dan memenuhi aksesibilitas dasar.
Tailwind **belum dimuat** di layout/halaman mana pun; semua pengukuran memakai galeri
statis hasil render Blade asli (`scripts/tailwind-harness/render.php`).

Berkas di folder ini:

| Berkas | Isi |
|---|---|
| `hasil-chrome.json`, `hasil-edge.json` | Hasil per butir (status + nilai terukur) dan rincian ringkas. Tanpa dump ratusan properti: yang disimpan hanya selisih, ringkasan, dan hash digest snapshot. |
| `chrome-*.png` (14 berkas) | Screenshot kunci (Chrome saja): tombol/form terang & gelap di 390x844 dan 1366x768, **tautan-sebagai-tombol (`x-tw.button href`) terang & gelap di 1366x768**, badge+kartu terang & gelap, tabel terang, modal terbuka terang & gelap, fokus papan ketik, modal berisi panjang di 1280x600, tabel mode kartu 390x844. |

## Cara menjalankan ulang

Dari akar proyek (Windows; tanpa npm, tanpa dev server, tanpa database, tanpa `.env`):

```powershell
# Jalur php/node di bawah hanyalah CONTOH (Laragon di mesin pengembang ini). Jangan mengasumsikan jalur:
# cari dulu dengan `Get-Command php` / `Get-Command node` (CLAUDE.md 4.3) dan ganti sesuai hasilnya.

# 1. bangun artefak produksi (dan, bila perlu, artefak uji untuk butir G6-fixed-inset-0)
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/build-tailwind.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/build-tailwind.ps1 -Target uji

# 2. render galeri -> tools/tailwind/uji/galeri-{tw,baseline,tw-awal}.html (ter-ignore git)
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe scripts/tailwind-harness/render.php

# 3. jalankan matriks CDP (Chrome lalu Edge, headless, port 9333/9334, user-data-dir sementara)
C:\laragon\bin\nodejs\node-v22\node.exe scripts/tailwind-harness/cdp-run.mjs
#   opsi: --browser=chrome|edge|chrome,edge  --bagian=iso,token,kaskade,fokus,keyboard,aria,modal,sentuh,zoom,responsif,kontras,kartu,lain,landmark,tautan
#         --foto=chrome  --out=<folder>  --chrome=<path>  --edge=<path>
```

Runner hanya memulai dan mematikan proses browser miliknya sendiri (PID dicatat di JSON,
`penghentian.sisaProsesPenanda` = 0 berarti tidak ada proses dengan `user-data-dir` runner
yang tertinggal; folder sementara dihapus). Ia menolak berjalan bila port debug sudah dipakai.
Sisa proses > 0 (atau tak terverifikasi) membuat butir `G0-proses-bersih` FAIL dan kode keluar runner 1.
Koneksi WebSocket CDP dibatasi `--remote-allow-origins=http://127.0.0.1:<port>` (bukan `*`).
Lama jalan: sekitar 2 menit per browser.

## Keselamatan proses

- **Jangan menjalankan `chrome.exe` / `msedge.exe` langsung**, termasuk `--version`: perintah itu dapat
  membuka tab/jendela di sesi browser pengguna. Baca versi lewat PowerShell:
  `(Get-Item -LiteralPath '<jalur exe>').VersionInfo.ProductVersion` (runner mencatatnya sebagai
  `detail.lingkungan.versiBerkasExe`; jalur yang memuat tanda kutip tunggal ditolak).
- **Jangan memakai `python` / `python3`**: alias stub Windows Store menggantung tanpa keluaran. Pakai Node atau PowerShell.
- **Jangan `taskkill /IM`** (mematikan semua proses bernama itu, termasuk browser pengguna). Hanya matikan PID milik
  runner sendiri (`taskkill /PID <pid> /T /F`); runner melakukannya otomatis hanya bila browser tidak keluar sendiri.
- Sebelum menjalankan, pastikan port **9333** (Chrome) dan **9334** (Edge) bebas; setelah selesai, pastikan
  `penghentian.sisaProsesPenanda = 0` di kedua `hasil-*.json`.
- Jalur `php-8.3.30-Win32-vs16-x64` dan `node-v22` pada dokumen ini adalah **CONTOH**, bukan jaminan; CLAUDE.md 4.3
  melarang mengasumsikan jalur — verifikasi dengan `Get-Command php` / `Get-Command node`.
- `scripts/tailwind-harness/render.php` hanya berjalan di CLI (keluar dengan pesan bila `PHP_SAPI` bukan `cli`).

## Yang diukur

Browser: **Chrome 155.0.8059.86** dan **Edge 156.0.4314.42** (keduanya Chromium, headless `--headless=new`,
`--allow-file-access-from-files`, galeri dibuka lewat `file:///`). Matriks: 4 keadaan
(terang/gelap x aksen bawaan `#92232b` / aksen kustom Teal `#0f6e66`), viewport
360x740, 390x844, 768x1024, 1024x768, 1366x768, 1920x1080 (+ 900x700, 901x700, 1280x600 untuk batas zoom/tinggi pendek).
Galeri memuat CSS legacy dalam urutan layout asli, `tesys-tw.css` produksi di akhir (varian `tw`),
sebelum `tesys-ui.css` (varian `awal`), atau tidak sama sekali (varian `baseline`), plus jQuery,
Bootstrap bundle, DataTables, `tesys-table.js`, `tesys-ui.js` agar modal, label dan mode kartu berperilaku nyata.

| Butir | Chrome | Edge | Ringkas |
|---|---|---|---|
| G1-isolasi | PASS | PASS | 46 elemen/pseudo legacy x 4 keadaan x 2 viewport x 2 posisi `<link>`: 0 selisih computed-style (+ ukuran/posisi relatif); `--tw-*` (19 `@property` terdaftar, inert) dicatat terpisah; digest per snapshot di JSON |
| G1-kontrol-positif | PASS | PASS | CSS bocor sengaja (`button{border-radius:0}`, `h1`, `label`, `a`) terdeteksi (10 selisih) -> uji isolasi tidak buta |
| G2-token | PASS | PASS | 1.596 pengecekan (warna, tepi, radius, font, bobot, min-height, padding, bayangan, hover, disabled-tak-berubah, modal) = nilai token (di-resolve lewat probe `var()`), 0 deviasi di 4 keadaan |
| G2-transisi | PASS | PASS | `transition` memakai `--dur` (0,22s) dan `--ease` |
| G3-bang | PASS | PASS | **46 pasangan** (kelas `!` x elemen, kini termasuk `<a>`; 152 konteks) diukur dengan membandingkan bentuk dengan `!` vs bentuk tanpa `!` (aturan `@layer utilities` disuntik; selektor gabungan `_td`/`_th` dipecah per koma): semuanya perlu di minimal satu konteks (polos / DataTables / mobile, terang / gelap kustom, istirahat / fokus). Kelengkapan: **32 kelas `!` di artefak produksi, 32 terukur, 0 tak terukur** (20 pada F2; 32 setelah pilot F3 dan prop `href`) (himpunan di artefak harus subset dari yang terukur, jika tidak butir FAIL dan daftar yang hilang disebutkan) |
| G3-cincin-tunggal | PASS | PASS | Fokus papan ketik: tepat satu indikator (satu outline; tanpa ring ganda) pada tombol x5, input x2, select x2, tutup modal, di 4 keadaan |
| G3-radius-fokus | PASS | PASS | Radius saat fokus = istirahat (legacy `:focus-visible{border-radius:4px}` dikalahkan `rounded-sm!`) |
| G3-cursor-disabled | PASS | PASS | aktif `pointer`, disabled `not-allowed`, loading `progress` (`button:disabled` dengan `!` terbukti perlu: `button{cursor:pointer}` legacy menang tanpa `!`) |
| G3-label | PASS | PASS | Label sama dengan label legacy form-group (12px/500/text-dim/margin 5px) |
| G3-kartu-aktif / -vs-legacy / -off | PASS | PASS | <=640px: `ts-card-table` + `data-label`; tampilan kartu setara tabel DataTables legacy; `card-mode=false` -> `data-ts-card="off"` |
| G4-fokus-kontras | PASS | PASS | Indikator fokus vs latar (`--bg`/`--surface`/`--surface-2`) minimum **5,10:1** pada 8 praset aksen x terang/gelap x 10 komponen (syarat 3:1) |
| G4-fokus-forced-colors | PASS | PASS | Emulasi `forced-colors: active`: seluruh 10 kontrol tetap punya outline terlukis |
| G4-tab-maju / -shift-tab / -enter-space | PASS | PASS | Urutan Tab = urutan DOM (41 tabbable; disabled/loading dilewati); Shift+Tab terbalik; Enter dan Space memicu submit |
| G4-modal-* (papan-ketik, fokus-masuk, escape-kembali) | PASS | PASS | Fokus masuk modal, jebakan fokus Bootstrap, Escape menutup, fokus kembali ke pemicu (`data-toggle`), tombol x menutup |
| G4-modal-backdrop-zoom | PASS | PASS | Zoom .8: backdrop rasio 1,0 terhadap viewport; 6 titik tepi/sudut tertutup |
| G4-modal-ukuran | PASS | PASS | Dialog 640x723 = dialog legacy pada viewport sama |
| G4-modal-viewport-pendek / -gulir-internal | PASS | PASS | Tinggi <=700 / lebar <=575: isi menggulir di dalam `<section>`, header+footer tetap terlihat; viewport lain sama dengan modal legacy |
| G4-aria | PASS | PASS | 14 pemeriksaan: nama aksesibel, `aria-invalid`/`aria-describedby`/`role=alert`, `aria-busy`, dialog bernama dari judul, tombol tutup "Tutup", label tanpa `for` dihubungkan `tesys-ui.js` |
| G5-target-sentuh-44 | **FAIL** | **FAIL** | Deviasi desain tercatat: tombol 34/30px, input/select 34px, tutup modal 40px (390x844). Sama dengan kontrol legacy (36px form-control, 34px `.btn`). Bukan regresi; keputusan desain terbuka |
| G5-target-sentuh-24 | PASS | PASS | Semua target >= 24x24 (WCAG 2.2 AA 2.5.8) |
| G5-ketuk | PASS | PASS | `Input.dispatchTouchEvent`: tombol, submit, fokus input/select, buka & tutup modal; tombol disabled tak merespons |
| G6-zoom-computed | PASS | PASS | `zoom` 0.8 pada >=901px, 1 pada <=900px (diperiksa di 900/901) |
| G6-artefak-tanpa-vh | PASS | PASS | Artefak produksi tanpa `*-screen`, satuan `vh/vw/dvh`, `fixed` |
| G6-fixed-inset-0 | PASS | PASS | (Artefak uji, informasi) `fixed inset-0` menutup viewport penuh di 1366/1920/390 |
| RESP-viewport | PASS | PASS | 6 viewport x terang/gelap: tanpa overflow horizontal |
| G7-kontras-teks | PASS | PASS | Terendah **4,81:1** (badge positive, terang); on-accent `#fff` 6,10-11,48:1 pada 8 praset x 2 tema (normal & hover) |
| G4-landmark | PASS | PASS | 11 anak langsung kartu/modal diperiksa lewat pohon aksesibilitas Chromium (`getPartialAXTree` + `getFullAXTree`): peran `generic`/`none`, **0** landmark `banner`/`contentinfo` di seluruh halaman, 0 tag `<header>`/`<footer>` di komponen. Sebelum perbaikan: 6 elemen terukur `banner`/`contentinfo` (`<header>`/`<footer>` di card dan modal) |
| G2-token-tautan | PASS | PASS | `<a>` (`x-tw.button href`): 2.752 pengecekan (4 varian x 2 ukuran x normal/ikon/_blank/disabled/loading; warna, tepi, radius, font, min-height, padding, bayangan, display, text-decoration, cursor, opacity; hover per varian; disabled/loading tak berubah saat hover; lebar x tinggi = kembaran `<button>`) = token, 0 deviasi di 4 keadaan |
| G3-fokus-tautan | PASS | PASS | 28 pengukuran (7 tautan x 4 keadaan): satu outline `accent-text`, radius fokus = istirahat, warna teks & dekorasi tak berubah saat fokus |
| G4-tautan-papan-ketik | PASS | PASS | Enter mengaktifkan (klik + navigasi ke `#tw-lnk-tujuan`), **Space tidak** (klik 0, hash tetap); 24 tautan aktif ikut urutan Tab, 0 disabled/loading tabbable; disabled/loading tanpa `href`, `tabindex=-1`, `aria-disabled=true`, `role=link`; ctrl+klik membuka 1 tab baru (event `ctrlKey=true`, `defaultPrevented=false`, halaman asal tak berpindah; tab ditutup runner) |
| G4-aria-tautan | PASS | PASS | role `link` + nama dari isi (ikon `aria-hidden`); disabled/loading: role link + `disabled` pada pohon aksesibilitas, loading `busy`; `target=_blank` otomatis `rel="noopener noreferrer"`; tanpa target tanpa `rel` |
| G7-kontras-tautan | PASS | PASS | 4 varian x (istirahat, hover) x 4 keadaan >= 4,5:1; terendah **6,10:1** (sebelum `!`: 1,85:1 pada hover gelap) |
| G5-tautan-sentuh | PASS | PASS | 390x844: `<a>` md 105,4x34 dan sm 93,7x30 = kembaran `<button>` (deviasi 44px sama dengan `G5-target-sentuh-44`); ketukan mengaktifkan tautan; ketukan pada tautan disabled tidak menavigasi (event klik JS tetap terkirim: batas `<a>` tanpa `href`) |
| G0-proses-bersih | PASS | PASS | `sisaProsesPenanda` = 0 dan folder sementara terhapus setelah penghentian browser |

Total per browser: **40 PASS + 1 FAIL** (G5-target-sentuh-44, deviasi desain tercatat; tautan tidak mengubahnya). Sebelumnya 34 PASS + 1 FAIL; 6 butir baru `*-tautan`. Hasil Chrome dan Edge identik pada seluruh butir (nilai terukur di JSON sama, kecuali digest/ID proses).

## Temuan yang menggerakkan perubahan komponen

Setiap perubahan di bawah memiliki ukuran "sebelum" (disimpan di laporan serah, bukan di folder ini) dan "sesudah" (JSON ini).

1. **Teks tombol primer.** `--chrome-text` (`#d2ccce`) gagal 4,5:1 pada aksen praset Teal/Forest/Rust (3,85 / 3,91 / 3,93). `--color-on-accent` kini `#fff` (sama dengan literal legacy `.btn-primary`). Satu-satunya hex di `resources/tailwind/tesys-tw.css`; **perlu konfirmasi desain** (token `--on-accent` khusus lebih tepat untuk F3). Aksen kuning bebas `#ffd400` tetap gagal (1,43:1) — keterbatasan pemilih warna bebas, sama dengan tombol legacy.
2. **Indikator fokus** dialihkan dari ring `box-shadow` ke **outline** (`focus-visible:outline-2 outline-offset-2 outline-accent-text!`): ring **tidak dilukis di `forced-colors`** (semua 10 kontrol kehilangan fokus), dan ring primer terang-bawaan berwarna sama dengan isi tombol. `outline-none!` dan `ring-*` dihapus. Hanya warna outline yang diberi `!` (terbukti perlu: di tema gelap/kustom `--accent` legacy kalah kontras). Catatan ukur: pada zoom .8 outline `2px` dibulatkan Chromium menjadi **1 piksel perangkat** (computed 1,25px) — perilaku yang sama dengan outline legacy; ring lama berspread 2px (perkiraan 1,6px tersaji, tidak diukur ulang) sedikit lebih tebal. Menebalkan indikator butuh `!` pada lebar outline: tidak dilakukan.
3. **Tabel — `!` yang tak perlu dihapus:** `border-b` pada `th` dan `py-cell-y` pada `td` (nilai legacy sudah sama).
4. **Tabel — `!` yang merusak legacy diperbaiki:** `px-cell-x!` pada `th` memotong padding kanan 26px ikon urut DataTables (kini dikecualikan untuk `.dataTable`); `!` pada `td` merusak mode kartu <=640px (padding kartu `6px 0` berubah ke 9px/12px). Karena `tesys-table.js` memasang `.ts-card-table` pada **semua** tabel daftar di `.main .content` di semua lebar, pengecualian memakai varian lebar `min-sm` (>=641px), bukan kelas. Konsekuensi: di <=640px gaya th/td mengikuti legacy.
5. **Label** `x-tw.input`/`x-tw.select`: warna disamakan dengan legacy (`text-text-dim`); ukuran token 12,5px kalah dari `luxe.css:535` (12px) di dalam `.form-group` — deviasi diterima agar tampil sama dengan label legacy sebelahnya.
6. **Harness**: galeri kini memasang kelas `sidebar-off` di <=900px seperti `app.blade.php:55-65` (tanpa itu selubung gelap `.shell::after` menutupi halaman dan ketukan jatuh ke `.shell`), tabel pembanding legacy ikut DataTables dan punya kolom yang sama.

Pengamatan (bukan kegagalan): modal yang dibuka programatik (`.modal('show')` tanpa pemicu `data-toggle`) tidak mengembalikan fokus ke elemen pemicu setelah ditutup; `document.activeElement` berakhir di `body` (Chrome) atau di elemen dalam modal yang sudah tersembunyi (Edge) — hasilnya berbeda antar jalan. Itu perilaku Bootstrap 4, bukan komponen; pemanggil yang membuka modal secara programatik perlu mengembalikan fokus sendiri (nilai di `detail.modalProgramatik`).

7. **Landmark.** `<header>`/`<footer>` pada `x-tw.card` dan `x-tw.modal` terpetakan menjadi landmark `banner`/`contentinfo` (terukur 6 elemen di galeri, bukan turunan `section/article/main`). Card kini memakai `<div>`; modal memakai `<section>` (bukan `div` anak langsung `.modal-content` karena `compat.css:297`); peran terukur `generic`. Kelas `tw:` tidak berubah, jadi artefak produksi identik.
8. **Pemindai `!`.** Pemindai lama hanya mencocokkan selektor pertama dalam daftar koma dan mencatat kunci sebelum memeriksa `disabled`, sehingga `min-sm:[&:not(.dataTable)_th]:px-cell-x!` dan `disabled:cursor-not-allowed!` tidak pernah terukur. Keduanya kini terukur dan terbukti perlu.

9. **Tautan (`x-tw.button href`).** Diukur sebelum dan sesudah `!` (Chrome 155, 4 keadaan; "sebelum" = `<a>` memakai kelas tombol tanpa `!` khusus tautan):
   - **Sebelum:** latar `<a>` terukur `rgba(0,0,0,0)` pada primary/secondary/danger (token: `rgb(146,35,43)`, `rgb(255,255,255)`, `rgb(244,230,230)`) dan warna teks `rgb(146,35,43)` (accent-text) di SEMUA varian termasuk primary (token `#fff`); saat hover warna `rgb(126,28,36)` (accent-dark) dan latar tetap transparan, juga pada tautan disabled/loading; kontras hover gelap terendah 1,85:1 (G7 FAIL). Sebab: reboot BS `a{background-color:transparent}`, `a{color:inherit}`, `body.tesys-ui a`/`a:hover` (`compat.css:365-366`), BS `a:hover`.
   - **Tidak bermasalah tanpa `!`:** `text-decoration` (tetap `none` saat istirahat/hover/fokus), `display`, `min-height`, padding, tepi, radius, bayangan, kursor, opacity, outline fokus.
   - **Sesudah:** `!` pada latar (`bg-accent!`, `bg-surface!`, `bg-danger-bg!`; ghost transparan tanpa `!`), warna teks (4 varian), `hover:bg-*!` (3 varian), `hover:text-surface!` (danger), `hover:text-text!` (ghost); 0 deviasi dari 2.752 pengecekan, G7 terendah 6,10:1.
   - Pemindai `G3-bang` pada percobaan pertama menandai `text-control!`, `text-data!`, `leading-tight!` **tidak perlu pada `<a>`** (reboot BS hanya mengatur `button`); tautan memakai bentuk tanpa `!` dan G2 tetap PASS. Kini 46 pasangan, semuanya perlu.
   - Pengukuran ctrl+klik: percobaan pertama gagal karena gulir halus legacy (`scroll-behavior`) belum selesai saat koordinat dibaca (klik jatuh di elemen lain); runner kini menunggu gulir selesai.
   - Batas: `<a>` tanpa `href` tetap meneruskan event klik JS (`klikJsDisabled=1`), beda dengan `<button disabled>`.

Lingkungan halaman (`detail.lingkungan`): `tesys-tw.css` termuat (113 aturan menurut runner = 112 blok `.tw\:` + 1 blok universal `*,:before,:after,::backdrop`, diverifikasi Grep; 19 `@property`) dan font Inter siap (`document.fonts.check`), sehingga metrik teks mendekati produksi. Juga dicatat: SHA-256 dan ukuran `public/css/tesys-tw.css`, SHA-256 galeri HTML yang diuji (`galeri-baseline|tw|tw-awal`), `galeri.blade.php`, dan tiap komponen Blade.

## NOT RUN (tidak diuji, tidak boleh diklaim)

- **Firefox**, **WebKit/Safari** (tidak ada di mesin). Perilaku `@property`, `:has()`, outline + `border-radius`, `zoom` CSS bisa berbeda.
- **Perangkat sentuh fisik / emulator iOS-Android**: yang diuji hanya emulasi sentuh Chromium (`Input.dispatchTouchEvent`).
- **Halaman Blade nyata di aplikasi** (layout asli, sidebar, sesi, data): galeri statis dengan CSS/JS yang sama, tetapi bukan halaman aplikasi; tabel nyata dengan toolbar/tfoot/kolom dinamis tidak diuji.
- **Plugin CSS lain** (TomSelect/`TesysSearchableSelect` pada `x-tw.select`, flatpickr, SweetAlert) tidak dimuat dan tidak diuji interaksinya.
- Pembaca layar nyata (NVDA/JAWS/VoiceOver); yang diuji adalah pohon aksesibilitas Chromium (nama, peran, properti).
- Uji beban/kinerja render dan `prefers-reduced-motion`.
- Test PHPUnit (`TailwindAssetTest`) tidak dijalankan oleh runner CDP. Dijalankan terpisah oleh sesi utama (TestCase non-Laravel tanpa artisan/DB). Terbaru yang tercatat (F3, gerbang §4.3 langkah 1–5 terpenuhi): exit 0, 11 tes, 477 asersi; dijalankan ulang sesi utama setelah prop `href` (artefak dan komponen berubah): **exit 0, 11 tes, 495 asersi**. Riwayat: F2 dengan pengecualian §4.3 (config cache ada) 468 asersi; sebelum Refinement R1 10 tes/447 asersi.
- Insiden proses (INFO): `chrome.exe --version` pernah membuka tab di sesi Chrome pengguna dan alias `python` Windows Store menggantung; aturan pencegahan ada di "Keselamatan proses" di atas. Pada pekerjaan prop `href`, `python` terpanggil sekali tak sengaja dan menggantung; hanya pohon proses milik sesi itu (PID bash -> python) yang dimatikan per PID, tanpa `taskkill /IM`.
