# Tailwind CSS (Fase F0-F2) — koeksistensi dengan Bootstrap 4 + `tesys-ui-*`

Status: **F0-F1 = PoC isolasi; F2 = tujuh komponen Blade `x-tw.*` + harness + bukti browser (Chrome/Edge). Belum dimuat
di layout/Blade mana pun; belum diverifikasi di halaman aplikasi nyata, Firefox, Safari, maupun perangkat sentuh fisik.**
Keputusan pengguna: Opsi B (Tailwind berdampingan, isolasi ketat). Tujuan: kerapian CSS dan redesain
bertahap. Pilot yang direncanakan = modul Feedback (Fase F3, **di luar** dokumen ini).
Risiko PoC/F2: LOW (berkas baru, tidak dimuat). Risiko saat dimuat di halaman nyata: MEDIUM (UI global lintas
lapis CSS) — lihat §7 dan §10.

Dokumen terkait: [`tailwind-components.md`](tailwind-components.md) (peta token + celah, API 7 komponen, daftar `!` terukur,
batas F2 vs F3, deviasi) dan [`tailwind-evidence/README.md`](tailwind-evidence/README.md) (cara menjalankan ulang pengukuran CDP,
hasil per butir). Bagian §9 menyimpan hasil Chrome-headless F0-F1 sebagai catatan historis; bukti terkini ada di folder evidence.

## 1. Versi, binary, checksum

| Item | Nilai |
|---|---|
| Tailwind | **v4.3.3**, binary mandiri (tanpa Node, tanpa npm; `package.json` tidak berubah) |
| Berkas | `tools/tailwind/tailwindcss.exe` (Windows x64), 112.503.296 byte |
| SHA-256 binary | `e0e260ce048014e9268f6237ff18f8ccf02cef521cbd0ae04e82c2cdf7aa3955` |
| Sumber unduhan | `https://github.com/tailwindlabs/tailwindcss/releases/download/v4.3.3/tailwindcss-windows-x64.exe` |
| Git | `tools/tailwind/` ada di `.gitignore` (binary dan artefak uji `tools/tailwind/uji/` **tidak** di-commit); hasil build produksi `public/css/tesys-tw.css` **di-commit** |

`scripts/build-tailwind.ps1` menolak berjalan bila SHA-256 binary berbeda dari tabel di atas.
Server produksi tidak membutuhkan binary: ia hanya melayani `public/css/tesys-tw.css` seperti CSS lain.

## 2. Berkas

| Berkas | Peran |
|---|---|
| `resources/tailwind/tesys-tw.css` | masukan build **produksi**: impor tema+utilitas, peta token, breakpoint, daftar putih `@source`; **tanpa safelist** |
| `resources/tailwind/tesys-tw.uji.css` | masukan build **uji**: `@import "./tesys-tw.css"` + safelist uji (kelas representatif untuk harness, termasuk kelas yang dilarang di halaman) |
| `public/css/tesys-tw.css` | hasil build produksi (minified, deterministik); satu-satunya artefak yang boleh dilayani |
| `tools/tailwind/uji/tesys-tw.uji.css` | hasil build uji (ter-ignore git; **tidak pernah** ke `public/`) |
| `resources/views/components/tw/{button,input,select,badge,card,modal,table}.blade.php` | tujuh komponen Blade `x-tw.*` (F2); API di [`tailwind-components.md`](tailwind-components.md) |
| `scripts/build-tailwind.ps1` | pembungkus build (`-Target` produksi atau uji; `-Cek` untuk uji determinisme) |
| `scripts/tailwind-harness/` | `render.php` (render galeri Blade standalone, tanpa boot Laravel/DB), `galeri.blade.php`, `cdp-run.mjs` (runner CDP Chrome/Edge); lihat [evidence/README](tailwind-evidence/README.md) |
| `docs/ui/tailwind-evidence/` | `hasil-chrome.json`, `hasil-edge.json`, screenshot kunci, README cara menjalankan ulang |
| `tests/Unit/TailwindAssetTest.php` | penjaga statis tanpa DB (ukuran, prefix, tanpa reset/palet, artefak produksi bebas safelist/kelas terlarang, token ada, kelas `tw:` di komponen/JS ada di artefak, belum dimuat). Versi terbaru (pasca-`href`) lulus 11 tes/495 asersi (lihat §9) |
| `docs/ui/tailwind.md`, `docs/ui/tailwind-components.md` | dokumen ini dan dokumen komponen |

## 3. Perintah build (persis)

Dari akar proyek (`C:\laragon\www\tesys`), dengan pemeriksaan checksum binary dan ringkasan ukuran/hash:

```
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/build-tailwind.ps1                    # produksi (bawaan) -> public/css/tesys-tw.css
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/build-tailwind.ps1 -Cek               # dua build ke folder sementara, bandingkan hash + berkas terpasang
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/build-tailwind.ps1 -Target uji        # uji -> tools/tailwind/uji/tesys-tw.uji.css
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/build-tailwind.ps1 -Target uji -Cek
```

Tidak ada target gabungan; jalankan dua kali bila perlu keduanya. Target uji menolak keluaran di bawah `public\`.
`-Cek` keluar 1 bila berkas terpasang berbeda dari hasil build (atau build tidak deterministik); bila berkas terpasang belum ada,
perbandingan dilewati dan tidak dianggap gagal. Tidak memanggil artisan/composer/npm/database.
Satu-satunya berkas yang ditulis per target: `public/css/tesys-tw.css` (produksi) atau `tools/tailwind/uji/tesys-tw.uji.css` (uji).
Hasil tidak bergantung pada direktori kerja (F0-F1: cwd akar proyek vs `/` → hash sama).

**Terkini (M5a+M5b, tabel dan kartu mobile Index Cost Request):** produksi `public/css/tesys-tw.css` **31.758 B** (termasuk M5b, perbaikan lebar Description M5a, dan perbaikan nowrap kartu M-3; M5a murni: 21.129 B, `da7d6568…`), SHA-256 `1b3b552cfe673e30016f40244ddc93517c31732e82aac82cc44168c46eaad30d`, `-Cek` exit 0 — lihat [`tailwind-budget-request.md`](tailwind-budget-request.md) (M4: 17.311 B, `4e79d24d…`; M3: 16.951 B, `2c024e1c…`; M2: 16.733 B, `1638d61d…`; M1: 15.634 B, `6e89716b…`). Tabel di bawah adalah riwayat F2.

Hasil F2 (keadaan setelah tujuh komponen ada; diverifikasi sesi utama, ukuran/hash diperiksa ulang read-only):

| Target | Ukuran | SHA-256 | Status |
|---|---|---|---|
| produksi `public/css/tesys-tw.css` (setelah prop `href` pada `x-tw.button`) | **10.400 byte** | `f2215381e0054416d5448c3923abffada988e9d4ed181583811dca28edabe258` | `-Cek` exit 0, deterministik (pilot F3: 9.329 B, `e10c7b7a…158f20`; F2: 9.109 B, `97017fff…10de86`). **Berbeda dari artefak pilot F3: unggah ulang bila pilot dideploy.** |
| uji `tools/tailwind/uji/tesys-tw.uji.css` (setelah prop `href`) | **21.976 byte** | `9bfdbacb256ae9f445a9baf5e2ac89ec8a095c3a4723224ad7aaf0005e1ac956` | berkas lokal ter-ignore git (F3: 21.136 B, `66a55e47…bbb905`; F2: 20.977 B) |

Hash berubah bila masukan, isi `resources/views` (termasuk komponen `x-tw.*`), atau `public/js/tesys-*.js` yang memuat kelas `tw:` berubah.
Ukuran produksi kini lebih kecil dari PoC lama (17.422 B) karena safelist uji dipisah dan hanya kelas yang tertulis utuh di komponen/JS yang dihasilkan.
Hash PoC lama `8d3164b2…64187f` (17.422 B, dengan safelist) hanya historis.

## 4. Aturan isolasi (keputusan sintaks v4.3.3 beserta buktinya)

| Aturan | Cara di v4.3.3 | Bukti |
|---|---|---|
| Prefix wajib | `@import "tailwindcss/theme.css" layer(theme) prefix(tw)`; kelas ditulis `tw:flex` | keluaran: setiap kelas `.tw\:…` |
| Tanpa preflight | hanya impor `theme.css` + `utilities.css` (bukan `@import "tailwindcss"`) | grep keluaran: nol aturan `html/body/h1-h6/button/img/ul/*` kecuali satu pengecualian di bawah |
| Tanpa palet bawaan | `@theme inline { --*: initial; … }` | tidak ada `oklch(`, tidak ada `--tw-color-`, tidak ada `:root`/`:host` |
| Token dipetakan, bukan disalin | `@theme inline`: nilai tema `var(--surface)` disisipkan langsung ke utilitas | `.tw\:bg-surface{background-color:var(--surface)}` |
| Pemindaian daftar putih | `source(none)` pada kedua impor + `@source "../views"` + `@source "../../public/js/tesys-*.js"` | uji tiruan: berkas di `plugins/`, `vendor/`, `storage/`, `js/lib-lain.js` tidak menghasilkan kelas |
| Mode gelap = atribut proyek | `@custom-variant dark (&:where(html[data-theme="dark"], html[data-theme="dark"] *))` | `.tw\:dark\:bg-surface-2:where(html[data-theme=dark],…)` |
| Breakpoint proyek | `--breakpoint-xs/sm/md/lg: 481/641/769/901px` (angka proyek +1px) | lihat §6 |

**Satu-satunya selektor non-`.tw\:`** di keluaran: `*,:before,:after,::backdrop` di dalam
`@layer properties { @supports (…peramban lama…) { … } }`. Isinya hanya deklarasi `--tw-*`
(25 buah pada PoC lama; jumlahnya mengikuti utilitas yang dihasilkan) dan hanya aktif di Safari < 16.4 / Firefox < 128. Ia dibuat Tailwind dan tidak bisa dimatikan tanpa
pasca-proses. Tidak ada deklarasi visual. Selain itu ada `@property --tw-*` (registrasi properti kustom
`--tw-*`; 19 registrasi pada artefak produksi F2; nama tidak bentrok dengan token proyek — pada PoC lama
`grep -- '--tw-' public/css public/js resources/views` kosong, kini hanya `tesys-tw.css` sendiri yang memuatnya).
Efek sampingnya: Chrome menampilkan properti kustom `--tw-*` terdaftar itu di `getComputedStyle` setiap elemen
(bukan properti yang dilukis); harness F2 mencatatnya terpisah dari perbandingan isolasi.

Hex di keluaran: `#0000` dan `#fff`. `#0000`/sebagian `#fff` adalah nilai internal Tailwind untuk bayangan/cincin kosong (bukan warna tampilan);
`#fff` juga memuat `text-on-accent` (`--color-on-accent`), satu-satunya hex di masukan produksi — lihat
[`tailwind-components.md`](tailwind-components.md) (f) dan Daftar tugas F3. `TailwindAssetTest` mengizinkan `#fff`, jadi tidak membedakan keduanya.

### Cara memuat (kelak, Fase F3 — bukan sekarang)

F0-F2 **tidak memuat** `tesys-tw.css` di layout atau Blade mana pun (komponen `x-tw.*` ada tetapi belum dipakai halaman). Kelak berkas hanya dimuat lewat direktif
`@aset('css/tesys-tw.css')` (bukan `asset()` mentah) di `@push('styles')` halaman yang dimigrasi, supaya `?v=` mengikuti
`App\Support\AssetVersion` (produksi = `config('changelog.current')` atau `ASSET_VERSION`). CSS di-cache 1 tahun
(`public/.htaccess:15`), jadi perubahan isi CSS butuh bump versi. Perhatikan `@stack('styles')` di
`resources/views/layouts/app.blade.php:155` berada sebelum `tesys-ui-tokens.css` s.d. `tesys-ui-refine.css` (baris 168-191);
karena tw ber-`@layer`, posisi ini tidak mengubah hasil (§7).

### Kelas dinamis dilarang

Tailwind hanya menghasilkan CSS untuk kelas yang tertulis **utuh** di sumber yang dipindai.

```
SALAH  class="tw:p-{{ $n }}"   'tw:bg-' + warna   "tw:" . $tone
BENAR  ['ok' => 'tw:bg-success-bg', 'gagal' => 'tw:bg-danger-bg'][$status]
```

Kelas yang dirakit di PHP/JS harus ditulis penuh dalam peta literal, di Blade atau `public/js/tesys-*.js`.
Kelas yang hanya muncul di database/konten tidak akan ada CSS-nya.

## 5. Peta token

Nama kunci tema bebas; nilainya **wajib** `var(--token)` yang ada di `public/css/tesys-ui*.css`
(dijaga `TailwindAssetTest`: 81 rujukan token, semuanya terdefinisi). Hanya kunci berikut yang ada;
`tw:p-7`, `tw:text-sm`, `tw:bg-blue-500` **tidak** dihasilkan (sengaja).

| Utilitas | Token |
|---|---|
| `bg-/text-/border-/ring-` + `canvas` `surface` `surface-2/3` `border` `border-strong` `text` `text-dim` `text-faint` | `--bg` `--surface` `--surface-2/3` `--border` `--border-strong` `--text` `--text-dim` `--text-faint` |
| … + `accent` `accent-dark/soft/tint/tint-2/text/ring` | `--accent*` |
| … + `success|warning|orange|danger|info|purple|primary|darkbadge` dan `*-bg` | token semantik + `-bg` |
| … + `charcoal` `charcoal-2` | `--charcoal*` |
| jarak `p/m/gap/w/h/inset…` `0 1 2 3 4 5 6 8 10 12` | `--sp-1…--sp-12` (`0` = `0px`) |
| `ctl` `ctl-sm` `card` `card-sm` `cell-x` `cell-y` (mis. `tw:h-ctl`, `tw:p-card`) | `--ctl-h` `--ctl-h-sm` `--card-pad` `--card-pad-sm` `--cell-x` `--cell-y` |
| `rounded-sm|md|lg|pill` | `--radius-sm|md|lg|pill` |
| `shadow-xs|sm|md|lg|accent` | `--shadow-*` |
| `text-page|section|sub|body|control|data|th|label|caption|kpi` | `--fs-*` (skala refine) |
| `font-heading|section|label|regular` (bobot) | `--fw-heading|section|label|body` |
| `font-display|body` (keluarga) | `--font-display|body` |
| `leading-tight|body|data`, `tracking-micro` | `--lh-*`, `--ls-micro` |
| `transition` (durasi/kurva bawaan), `ease-standar`, `ease-out` | `--dur`, `--ease`, `--ease-out`; durasi lain: `tw:duration-(--dur-fast)` |

Catatan penamaan: `font-body` = keluarga huruf; bobot isi bernama `font-regular` (kunci `font-body` ganda
membuat bobot tak terjangkau — ditemukan saat build). `canvas` (bukan `page`) agar tidak ambigu dengan `text-page`.
Rujukan `var(--token)` ikut kaskade di elemennya, jadi bila suatu subpohon menimpa token, utilitas ikut.

## 6. Breakpoint

Fakta terverifikasi (Grep): di `public/css/tesys-ui-*.css` `max-width` 900/640/480 dipakai banyak aturan (mis. `tesys-ui-mobile.css`,
`tesys-ui-shell.css`), shell memakai `min-width: 901px`, tetapi **tidak ada** `max-width: 768px`. Angka 768 hanya muncul sebagai
`min-width: 768px` (`tesys-ui-base.css:930`, `tesys-ui-refine.css:296`); `max-width: 768px` hanya ada di `<style>` beberapa Blade
(12 berkas, mis. `user/index`, `role/index`, `budget-request/show`). Tailwind v4 `max-*` bersifat
eksklusif (`width < N`), maka nilainya dibuat N+1:

| Varian | Aktif bila | Padanan CSS proyek |
|---|---|---|
| `tw:max-lg:` | lebar ≤ 900 | `@media (max-width:900px)` |
| `tw:max-md:` | ≤ 768 | `@media (max-width:768px)` |
| `tw:max-sm:` | ≤ 640 | `@media (max-width:640px)` |
| `tw:max-xs:` | ≤ 480 | `@media (max-width:480px)` |
| `tw:min-lg:` / `tw:lg:` | ≥ 901 | shell `min-width:901px` |
| `tw:md:` / `tw:sm:` / `tw:xs:` | ≥ 769 / ≥ 641 / ≥ 481 | — |

**Peringatan:** `tw:sm/md/lg` **BUKAN** breakpoint Bootstrap (576/768/992). `tw:md` = ≥ 769, sedangkan `min-width:768px` legacy aktif
mulai 768 (celah 1px di lebar 768). `tw:lg` = ≥ 901, sedangkan Bootstrap `lg` = ≥ 992. Jangan mencampur varian breakpoint `tw:*`
dengan `col-md-*` / `d-lg-*` (atau kelas responsif Bootstrap lain) pada elemen yang sama.

Catatan kaki: `tw:max-*` dikeluarkan sebagai `not all and (min-width:N+1)`. Pada lebar pecahan (mis. 900,5px akibat zoom
peramban) `tw:max-lg` bisa cocok sementara `max-width:900px` legacy tidak; kesetaraan tabel di atas hanya berlaku untuk lebar bulat.

## 7. Kaskade: `@layer` vs CSS legacy tanpa layer (temuan penting)

Utilitas Tailwind berada di `@layer utilities`; CSS proyek (Bootstrap, `tesys-ui-*`) **tanpa layer**. Konsekuensi (diukur,
Chrome 155, aksen kustom, lihat §9):

- `tw:*` biasa **kalah** dari aturan legacy mana pun pada properti yang sama (selektor tipe `td/th/h1/label/small/button`,
  `.btn`, `.form-control`, `.card`, `.table`, `.text-muted`, `.p-0`, `.d-flex`), berapa pun spesifisitasnya. Ia menang hanya
  atas stylesheet bawaan peramban atau pada properti yang tidak disentuh aturan legacy pada elemen itu. Ini per PROPERTI, bukan
  per elemen (hasil ukur harness untuk properti yang diuji; mis. `ul padding-left` menang, tetapi Bootstrap mengatur
  `ul{margin-bottom}` dan `img{vertical-align,border-style}` sehingga `tw:mb-*` pada `ul` kalah).
- `tw:*!` (sufiks `!`) menjadi `!important` berlayer dan **menang atas semuanya**, termasuk `!important` legacy
  tanpa layer (`.text-muted`, `.p-0`, `.d-flex` Bootstrap). Aturan CSS: untuk `!important`, urutan layer dibalik dan
  yang tanpa layer paling lemah. Pakai `!` hanya bila disengaja dan beri komentar; jangan `important` global.
- Karena tw layered, **posisi `<link>` tidak mengubah hasil** (sebelum `tesys-ui.css` seperti `@stack('styles')`, atau
  sesudah `tesys-ui-refine.css`). Kontrol positif: CSS tanpa layer yang sama *berubah perilaku* menurut posisi.
- Untuk halaman yang dikerjakan ulang dengan tw, jangan mencampur kelas Bootstrap dan `tw:` untuk properti yang sama
  pada elemen yang sama; kelas legacy akan diam-diam menang.
- Daftar `!` yang kini dipakai komponen (diukur per kelas di browser) dan alasannya: [`tailwind-components.md`](tailwind-components.md) (c).

## 8. Larangan

1. **Kelas dinamis** (lihat §4): selalu tulis nama kelas utuh.
2. **Hex/warna baru, `rgb()`, `oklch()`, nilai arbitrer berwarna** (`tw:bg-[#fff]`): dilarang; semua warna dari token.
   Nilai arbitrer `tw:x-(--token)` hanya untuk token yang ada. Satu pengecualian terukur yang masih terbuka:
   `--color-on-accent: #fff` di masukan produksi (belum disetujui pemilik; lihat §12 dan Daftar tugas F3).
3. **Satuan viewport**: `tw:h-screen`, `tw:w-screen`, `tw:min-h-screen`, `tw:h-dvh`, `tw:*-[100vh]` dilarang. Di desktop
   (≥901px) `html{zoom:.8}` membuat `vh/vw/dvh` hanya menutup 80% viewport (terukur 0,8; `tesys-ui-shell.css` memperbaiki
   `.modal`, `.modal-backdrop`, `.ts-drawer-root`, `.ts-cmd-root` dengan `calc(100vw / var(--ts-zoom))`). Lapisan fixed
   memakai `tw:fixed tw:inset-0` (terukur menutup viewport, tidak termasuk gutter scrollbar).
4. **Preflight / `@import "tailwindcss"` penuh / `@tailwind base`**: dilarang (reset global).
5. **Tanpa prefix / mengubah prefix `tw`**, atau menambah plugin/`@apply` ke CSS legacy: dilarang tanpa persetujuan.
6. **`important` global** pada impor utilitas: dilarang (lihat §7). `!` per kelas boleh, terbatas.
7. **Menyunting `public/css/tesys-tw.css` manual**: dilarang; selalu build ulang. Perubahan masukan harus disertai
   build ulang dan pembaruan hash di dokumen ini.
8. Mengubah tema/token proyek dari sisi Tailwind: dilarang. Token hanya didefinisikan di `tesys-ui-tokens.css` /
   `tesys-ui-refine.css`; berkas ini hanya merujuk.
9. Memuat `tesys-tw.css` di layout global sebelum pilot disetujui: dilarang (`TailwindAssetTest` menjaga ini selama F0-F2).

## 9. Hasil verifikasi

### 9.1 Bukti F2 (historis) — CDP, Chrome 155.0.8059.86 dan Edge 156.0.4314.42
> Setelah prop `href` pada `x-tw.button` angkanya menjadi 40 PASS + 1 FAIL per browser dan `G3-bang` 46 pasangan / 152 konteks / 32 kelas `!` (lihat `tailwind-evidence/README.md` dan `tailwind-components.md`). Tabel di bawah mempertahankan keadaan F2 sebagai riwayat.

Sumber: [`tailwind-evidence/`](tailwind-evidence/README.md) (`hasil-chrome.json`, `hasil-edge.json`, screenshot, cara menjalankan ulang) dan
ringkasan di [`tailwind-components.md`](tailwind-components.md) (f). Harness ada di repo: `scripts/tailwind-harness/`.

| Browser | PASS | FAIL | Catatan |
|---|---|---|---|
| Chrome 155.0.8059.86 | 34 | 1 | FAIL = `G5-target-sentuh-44` (target sentuh 44px; deviasi desain tercatat, lolos 24px WCAG 2.2) |
| Edge 156.0.4314.42 | 34 | 1 | identik dengan Chrome |

Butir baru pada putaran terakhir: `G4-landmark` (11 elemen diperiksa, 0 landmark `banner`/`contentinfo`; sebelumnya 6 elemen terukur dan diperbaiki dengan
mengganti `<header>`/`<footer>` di card/modal) dan `G0-proses-bersih`. `G3-bang`: **32 pasangan kelas-elemen, 124 konteks; 20 kelas `!` di artefak = 20 terukur,
0 tak terukur, tidak ada yang tak perlu** (putaran sebelumnya 31/116 dengan 2 kelas tak terukur karena bug pemindai; sudah diperbaiki). `G2-token`: 1.596 pengecekan, 0 deviasi.
Hash galeri/komponen yang diuji ada di `detail.lingkungan` pada JSON evidence.

**NOT RUN (pada F2; Firefox kemudian diuji di F3-d):** Firefox; WebKit/Safari; perangkat sentuh fisik (hanya emulasi sentuh Chromium); halaman Blade nyata di aplikasi; plugin CSS/JS lain
(TomSelect/`TesysSearchableSelect`, flatpickr, SweetAlert); pembaca layar nyata; kinerja dan `prefers-reduced-motion`.
**`TailwindAssetTest`:** pernah dijalankan sesi utama pada versi tes SEBELUM Refinement R1 — `php vendor/phpunit/phpunit/phpunit tests/Unit/TailwindAssetTest.php`,
exit 0, **10 tes, 447 asersi**, 249 ms (TestCase non-Laravel; tanpa artisan, tanpa DB). Itu **pengecualian CLAUDE.md §4.3** yang disetujui pengguna:
`bootstrap/cache/config.php` ada dan TIDAK dihapus; §4.3 tidak dipenuhi penuh. R1 kemudian mengubah tes (regex `tokenTw()` diperluas, `kelasDiArtefak()` berbasis himpunan, tes meta baru),
jadi hasil itu tidak berlaku untuk versi sekarang. Versi terbaru dijalankan ulang atas persetujuan pengguna (pengecualian §4.3 yang sama): **exit 0, 11 tes, 468 asersi, 140 ms**. Pada F3 (config cache dihapus pengguna, §4.3 langkah 1–5 terpenuhi, output `config:show` dikonfirmasi pengguna) dijalankan ulang setelah guard Feedback ditambahkan: **exit 0, 11 tes, 477 asersi, 269 ms**. Setelah prop `href` pada `x-tw.button` (komponen dan artefak berubah), dijalankan ulang sesi utama dengan gerbang yang sama: **exit 0, 11 tes, 495 asersi, 184 ms**.
Hasil PASS "7 tes" di 9.2 adalah untuk keadaan F0-F1. Pemeriksaan statis build F2: `-Cek` produksi exit 0 (deterministik), ukuran/hash di §3.

### Verifikasi lanjutan F2 (2026-10-26)

| Butir | Hasil |
|---|---|
| `build-tailwind.ps1 -Cek` (produksi) | exit 0; 9.109 B, SHA-256 `97017fff…10de86` (lengkap di §3) |
| Artefak uji (ter-ignore git) | 20.977 B, SHA-256 `5dfc4e0a…96627f` |
| Hitungan artefak produksi (Grep, read-only) | 112 blok aturan `.tw\:` (satu blok memuat dua selektor `td`/`th`), 19 `@property --tw-*`, 1 blok universal `*,:before,:after,::backdrop` |
| CDP Chrome 155 dan Edge 156 | F2: 34 PASS + 1 FAIL (historis); setelah prop `href`: **40 PASS + 1 FAIL** per browser (lihat evidence README) |
| `TailwindAssetTest` | **Pengecualian CLAUDE.md §4.3 (dicatat eksplisit):** dijalankan sesi utama atas persetujuan pengguna pada versi SEBELUM R1 — exit 0, 10 tes, 447 asersi, 249 ms; `bootstrap/cache/config.php` ada dan TIDAK dihapus; TestCase non-Laravel tanpa artisan/DB; prosedur §4.3 tidak dipenuhi penuh. Versi terbaru (pasca-R1) dengan pengecualian yang sama: exit 0, 11 tes, 468 asersi, 140 ms. **F3:** gerbang §4.3 langkah 1–5 terpenuhi (config cache dihapus pengguna) → exit 0, 11 tes, **477 asersi**, 269 ms |
| Firefox, WebKit/Safari, sentuh fisik, halaman Blade nyata, plugin CSS lain | **NOT RUN** |

### 9.2 Historis — F0-F1 (Chrome 155 headless, harness lokal; BUKAN browser pane)

Bagian ini mencatat keadaan PoC sebelum komponen (artefak 17.422 B dengan safelist; angka seperti 310 selektor, 17,4 KB, dan hash `8d3164b2…` tidak berlaku lagi untuk
artefak produksi sekarang). Harness F0-F1 itu berada di folder kerja sesi (di luar repo, sudah tidak dipakai); harness yang berlaku kini ada di repo: `scripts/tailwind-harness/`
(cara menjalankan: [`tailwind-evidence/README.md`](tailwind-evidence/README.md#cara-menjalankan-ulang)). Bukti mentah F0-F1 ada di laporan serah-terima. Ringkas:

| AC | Hasil |
|---|---|
| AC-1 build deterministik | LULUS: dua build hash identik; PowerShell dan Git Bash identik; cwd berbeda identik |
| AC-2 isolasi | LULUS di Chrome 155: 48 elemen legacy × ±620 properti terhitung (termasuk properti kustom token) × 4 keadaan (terang/gelap × aksen default/kustom) × 2 posisi `<link>`, nol selisih nilai terhitung/rect antara `tesys-tw.css` mati vs aktif; digest identik dengan halaman tanpa `<link>` dan dengan berkas hilang. Kontrol positif (CSS bocor `h1{margin-left}`) terdeteksi |
| AC-3 selektor ber-prefix | LULUS: 310 selektor `.tw\:…` (termasuk 2 varian `dark:` ber-`:where`); satu pengecualian `*,…` (§4) |
| AC-4 token mengikuti tema/aksen | LULUS: 196 pemeriksaan (49 utilitas × 4 keadaan), nol selisih terhadap `var(--token)` |
| AC-5 kaskade | TERUKUR (§7) |
| AC-6 overlay/viewport/breakpoint | `inset-0` menutup viewport; `vh/vw/dvh` 0,8 di bawah zoom .8; breakpoint cocok `matchMedia` pada 320, 480, 481, 640, 641, 768, 769, 900, 901, 1262 |
| AC-7 `?v=` dan berkas hilang | LULUS (statis + harness): `AssetVersion::url()` tidak memeriksa berkas; berkas hilang = satu 404, halaman identik |
| AC-8 ukuran | 17,4 KB (3,6 KB gzip) untuk ±300 kelas; batas atas uji (±1.270 kelas, kartesian penuh) 63 KB (9,6 KB gzip) |

**BELUM DIVERIFIKASI**: browser pane/peramban lain (Firefox, Safari, Edge, peramban lama), perangkat sentuh nyata,
render visual (hanya `getComputedStyle`), halaman Blade nyata (tidak ada yang memuat tw), plugin CSS
(fontawesome, DataTables, tom-select, flatpickr) dan `<style>` per halaman/inline layout tidak ikut harness.

### Verifikasi lanjutan 2026-10-25 (sebelum F2; historis)

| Butir | Hasil |
|---|---|
| Build normal `scripts/build-tailwind.ps1` | exit 0; 17.422 byte; SHA-256 `8d3164b2…64187f`; identik dengan berkas sebelum build |
| `-Cek` pada repo | exit 0; build 1 = build 2 = artefak terpasang |
| `-Cek`, artefak terpasang berbeda (fixture di folder sementara, bukan repo) | exit 1 + peringatan; folder sementara `tesys-tw-cek-*` terhapus |
| `-Cek`, tanpa artefak terpasang (fixture) | exit 0 |
| SHA-256 binary tidak cocok (fixture) / binary hilang (fixture) | exit 1, build dibatalkan sebelum binary dijalankan |
| Hasil build dari lokasi lain (fixture, `resources/views` kosong) | hash sama → keluaran hanya ditentukan blok safelist PoC, belum oleh Blade |
| `php -l tests/Unit/TailwindAssetTest.php` | tidak ada galat sintaks |
| Menjalankan `TailwindAssetTest` (versi F0-F1, historis) | **PASS** — `php vendor/phpunit/phpunit/phpunit tests/Unit/TailwindAssetTest.php`, exit 0: 7 tes, 329 asersi (PHPUnit melaporkan 304 ms; tanpa artisan, tanpa boot Laravel, tanpa DB). Dijalankan sebagai pengecualian yang disetujui pengguna terhadap CLAUDE.md §4.3 langkah 1 (`bootstrap/cache/config.php` ada dan tidak dihapus); §4.3 tidak dipenuhi penuh |
| Isolasi CSS terkompilasi (analisis statis) | 310 selektor `.tw\:`; satu-satunya selektor lain `*,:before,:after,::backdrop` (cadangan `--tw-*`); tanpa `:root`/`:host`/`oklch(`/preflight; hex hanya `#0000`/`#fff`; `!important` hanya pada kelas ber-suffix `!` |

## 10. Rollback

F0-F2 tidak dimuat di mana pun, jadi tidak ada dampak runtime. Mundur total:

1. Hapus `public/css/tesys-tw.css`, `resources/tailwind/`, `resources/views/components/tw/`, `scripts/build-tailwind.ps1`, `scripts/tailwind-harness/`,
   `tests/Unit/TailwindAssetTest.php`, `docs/ui/tailwind.md`, `docs/ui/tailwind-components.md`, `docs/ui/tailwind-evidence/`,
   dan baris `/tools/tailwind/` di `.gitignore`. Hapus folder `tools/tailwind/` lokal (termasuk `uji/`).
   Hapus juga butir "Tailwind (PoC F0-F1 …)" di `docs/architecture.md` (cari teks itu; nomor baris rapuh) agar tidak yatim.
2. Tidak ada migrasi, config, route, atau data yang tersentuh.

Setelah (kelak, F3) dimuat di layout/halaman:

1. Rollback halaman pilot = revert Blade dari git. Melepas `<link>` `tesys-tw.css` saja membuat halaman yang tata letaknya
   seluruhnya `tw:` tampil tanpa tata letak/gaya tw (data tidak rusak, tetapi tidak rapi). CSS legacy tidak bergantung pada tw.
2. Karena CSS produksi di-cache 1 tahun (`public/.htaccess`) dan versi aset = `config('changelog.current')`
   (`AssetVersion`), pembaruan/rollback berkas CSS hanya terlihat setelah versi rilis naik (atau `ASSET_VERSION` diubah di `.env` server —
   langkah manusia). Deploy tetap manual (`docs/DEPLOYMENT.md`).
3. Nama kelas `tw:` tidak dikenal Bootstrap, sehingga tanpa berkas tw ia tidak merusak apa pun; ia hanya tidak bergaya (lihat butir 1).

## 11. Kebijakan build dan aturan proyek

- **Kebijakan build disetujui pemilik pada 2026-10-26 dan diterapkan** (belum di-commit): `CLAUDE.md` §2 (baris Frontend), `docs/architecture.md`
  (bagian Stack dan butir Tailwind), dan komentar `package.json` kini menyatakan: tidak ada Vite/bundler/Node build; **pengecualian** Tailwind dibangun hanya
  di mesin developer lewat `scripts/build-tailwind.ps1` (binary mandiri ber-SHA-256, gitignored); produksi hanya menyajikan `public/css/tesys-tw.css` yang
  di-commit; tanpa kompiler atau dependensi runtime baru di produksi. `package.json` hanya mendapat tambahan komentar (tanpa skrip/dependensi).
- Komentar `package.json` (alasan Vite/Tailwind dicabut 26 Sep 2026: "dua sistem berdampingan dengan satu yang mati" itu mahal) tetap berlaku:
  kebijakan di atas hanya sah selama `tesys-tw.css` benar-benar dimuat oleh halaman. Bila F3 dibatalkan, tarik kembali pengecualian ini
  (revert ketiga berkas kebijakan dan hapus artefak) supaya tidak tersisa sistem yang mati.
- CLAUDE.md §9 (tokens/komponen) tetap berlaku: tw hanya merujuk token, tidak mendefinisikan warna. Satu pengecualian yang **belum disetujui**: `--color-on-accent: #fff` (lihat §12).

## Daftar tugas F3 (saat pilot)

1. ~~Hapus blok "SAFELIST UJI PoC" dari masukan produksi~~ — **sudah terpenuhi di F2** oleh pemisahan: safelist kini hanya di
   `resources/tailwind/tesys-tw.uji.css` (keluaran `tools/tailwind/uji/`, tidak pernah ke `public/`); artefak produksi bebas safelist dan dijaga `TailwindAssetTest`.
2. Bangun ulang artefak produksi (`scripts/build-tailwind.ps1`) setiap kali komponen/JS ber-`tw:` berubah; perbarui hash/ukuran di §3.
3. ~~Hapus atau sesuaikan `test_poc_belum_dimuat_di_blade_dan_binary_tidak_ikut_git`~~ (F3-b: diganti `test_tesys_tw_hanya_dimuat_di_view_feedback_satu_kali_per_halaman_utama`; M1: diganti nama menjadi `test_tesys_tw_hanya_dimuat_di_view_feedback_dan_cost_request_satu_kali_per_halaman_utama`, mengizinkan juga `budget-request/index`) di `tests/Unit/TailwindAssetTest.php` saat halaman pertama memuat `tesys-tw.css`.
4. `TailwindAssetTest` **sengaja gagal** bila versi Tailwind dinaikkan (versi dipatok oleh banner `tailwindcss v4.3.3` + SHA-256
   binary di skrip); perbarui tes, skrip, dan §1 bersama upgrade.
5. Jalankan `TailwindAssetTest` lagi setelah komponen/test berubah (pasca-`href` lulus 11 tes/495 asersi dengan gerbang §4.3 terpenuhi; ulangi setiap komponen/test/view Feedback berubah, dan pastikan config cache tidak ada).
6. Putuskan token `--on-accent` khusus di `tesys-ui-tokens.css` untuk menggantikan `--color-on-accent: #fff` (satu-satunya hex di masukan produksi).
7. Putuskan target sentuh 44px (tombol 34/30px, input/select 34px, tutup modal 40px saat ini; lolos 24px WCAG 2.2) atau terima nilai legacy.
8. Firefox 158 headless sudah diuji di F3-d (awalnya 4 butir FAIL; setelah diagnostik ulang QA penuh 33 PASS/0 FAIL, penyebab FAIL lama tidak diketahui; #1 jaringan: `halaman` dijalankan ulang dengan saringan abort dipertajam, 26 PASS/0 FAIL, 0 fetchError di halaman Feedback; penyebab FAIL lama tidak terbukti; lihat bagian F3); Safari/WebKit masih NOT RUN, perangkat sentuh nyata, dan interaksi dengan plugin (TomSelect/`TesysSearchableSelect`, flatpickr).
9. Uji pada halaman Blade nyata (layout, sidebar, sesi, data) sebelum pilot Feedback; termasuk tabel dengan toolbar DataTables/`tfoot`/kolom dinamis.
10. Setelah mengunggah komponen Blade baru ke server: `php artisan optimize:clear` (cache view). Saat `tesys-tw.css` mulai dimuat: muat lewat `@aset('css/tesys-tw.css')`
    dan naikkan versi aset (`config('changelog.current')` / `ASSET_VERSION`), karena CSS di-cache 1 tahun. Langkah deploy tetap manual.
11. Ulangi pengukuran `G3-bang` (butir CDP) bila CSS legacy tombol/form/modal/tabel diubah: setiap `!` bergantung pada aturan legacy tertentu.

## F3 Pilot Feedback

Status: **diimplementasikan di working copy, belum di-commit, belum dideploy.** Bukti: [`tailwind-evidence/f3/`](tailwind-evidence/f3/README.md).

**Berkas.** Diubah: `resources/views/feedback/{index,create,show,inbox}.blade.php` (`_disclaimer` tidak diubah), `tests/Unit/TailwindAssetTest.php`
(guard baru + view Feedback ikut diperiksa terhadap artefak), `scripts/tailwind-harness/cdp-pages.mjs` (jeda sisa proses, selektor, `--user-superadmin`, ekspor modul).
Baru: `scripts/tailwind-harness/cdp-qa-f3.mjs`, bukti di `docs/ui/tailwind-evidence/f3/`. Artefak `public/css/tesys-tw.css` dibangun ulang (§3). Komponen `x-tw.*`, layout, route, controller, model, JS/CSS legacy: **tidak berubah**.

**Keputusan.**
- Tailwind dimuat hanya lewat `@push('styles')` + `@aset('css/tesys-tw.css')`, satu kali per halaman utama (dijaga `TailwindAssetTest`). Urutan terukur: setelah `tesys-ui-base.css`, sebelum `tesys-ui.css`/tokens/dst.
- Dipakai: `x-tw.card` (index, create, show), `x-tw.table` (index, tabel statis), `x-tw.badge` (semua halaman; `:tone` = alias model `category_color`/`status_color`; Ya=positive, Tidak=neutral), `x-tw.select`/`x-tw.input` (kategori, judul, WhatsApp), `x-tw.button`.
- Tombol navigasi (Buat Submission di `index`, kembali di `create`/`show`) = **`<x-tw.button href="{{ route(...) }}">`** (merender `<a href>`; tanpa pembungkus `<form method="GET">`, tanpa kelas `no-busy`; jarak kanan tombol kembali lewat kelas BS `mr-3` pada `<a>`). Risiko **M2 ditutup**: ctrl/middle-klik membuka tab baru, URL tanpa akhiran `?`, peran `link` bagi pembaca layar. Target kembali di `show`: `feedback.inbox` bila `$authorized && !$isOwner`, selain itu `feedback.index` (logika tidak berubah). Tombol kembali punya `aria-label`. Tombol Buat/Tandai Selesai berubah dari hijau (`btn-success`) ke varian primer (aksen). Inbox tidak punya tombol navigasi form.
- Dipertahankan legacy: textarea (komponen input tidak mendukung), checkbox `custom-control`, tabel key-value Informasi (th = judul baris), tabel inbox + DataTables dan kartunya (`datatable.css` menarget `.card:has(.dataTables_wrapper)`), tombol Detail (`ts-action-primary`, `btn-info`), alert, `x-stat-cards`, `x-empty`, disclaimer.
- Target sentuh mobile: `tw:max-sm:min-h-12`/`max-sm:h-12`/`max-sm:min-w-12` (48px, token `--sp-12`; spacing 11 tidak ada di tema) lewat atribut kelas; komponen tidak diubah; desktop tidak membesar (input/select `x-tw` 34px vs legacy form-control-sm ~30px: selisih dari token `--ctl-h`).
- Judul kartu = `<span role="heading" aria-level="2">` (header kartu `x-tw.card` bukan h3).

**Hasil (Chrome 155.0.8059.86, Edge 156.0.4314.42; rinci di README f3).** Fungsi create/toggle/validasi native & server/submit/respond/close SweetAlert/403 inbox staff identik; tw dimuat 1x di 40/40 pengukuran halaman Feedback per browser;
halaman non-pilot tidak memuat tw (0) dan daftar CSS/JS identik; digest gaya identik di Chrome (Edge: `/` dan `/profile` berbeda karena akun uji berbeda, lihat README). Tanpa galat konsol baru, tanpa overflow horizontal.
QA tambahan (`cdp-qa-f3.mjs`): 27 butir, 0 FAIL di kedua browser (sentuh 48px, kontras min 4,81:1, fokus >= 7,38:1, aksen 8 praset x 2 tema, urutan Tab, nama aksesibel, escape XSS). Galeri `cdp-run.mjs` ulang: 34 PASS / 1 FAIL (`G5-target-sentuh-44`, sama dengan F2; komponen tidak diubah), `G3-bang` PASS. Setelah prop `href` (komponen button berubah, view Feedback tidak): 40 PASS / 1 FAIL (`G5-target-sentuh-44` tetap), 6 butir `*-tautan` baru, `G3-bang` 46 pasangan PASS; rinci di [`tailwind-evidence/README.md`](tailwind-evidence/README.md) (temuan 9).
QA **href** (tombol navigasi sudah `<a>`; `cdp-pages.mjs --label=href` + `cdp-qa-f3.mjs --label=href`, Chrome dan Edge, akun `dummy.superadmin`/`dummy.staff`): 60 pengukuran + 13 skenario per browser tanpa galat runner; 31 butir QA, **0 FAIL** di kedua browser (termasuk 4 butir baru `tautan-href-*`: elemen `<a>` dengan href `/feedback`, `/feedback/create`, `/feedback-inbox`, bukan di dalam form, tanpa `?`; klik biasa dan Enter menavigasi; ctrl+klik membuka tepat 1 tab baru dan halaman asal tetap; peran `link` + nama aksesibel; tanpa teks "Memproses"). Sentuh 390x844: kembali 48x48, Buat 144,6x48. Kontras `<a>` terang/gelap lolos; 8 praset x 2 tema: tautan primer terendah 6,10:1, fokus terendah 5,55:1. tw 1x di 40/40 halaman Feedback; non-pilot 0x; fungsi identik dengan "sesudah" (selisih hanya id/jam/jumlah baris DB uji dan akun Chrome). Rinci: README f3.

**Rollback (diuji).** Pengalihan ke `href`: memulihkan 3 view (`index`, `create`, `show`) dari salinan pra-href mengembalikan hash pra-href (`sha256sum -c` OK), lalu versi href dipasang kembali (`cmp` identik). Rollback penuh pilot: menyalin 5 view dari backup asli memulihkan hash asli (`sha256sum -c` OK, 0 kemunculan `tesys-tw`), lalu versi pilot dipasang kembali (hash = sebelum uji). Uji hanya pada level berkas, bukan render server. Rollback produksi = revert 4 view (+ guard test); CSS yang tidak dimuat inert.

**Deploy.** Unggah view + `public/css/tesys-tw.css`, `php artisan optimize:clear`, dan **naikkan versi aset** (`config('changelog.current')`/`ASSET_VERSION`): CSS di-cache 1 tahun; di server pilot (APP_ENV=local) versi = `time()` sehingga tidak perlu.

**Firefox (F3-d).** Gecko 158.0.1 headless via WebDriver BiDi (runner `scripts/tailwind-harness/bidi-firefox.mjs`, profil sementara `-no-remote`, tanpa geckodriver): run awal: halaman nyata 25 PASS, 1 FAIL, 3 INFO; QA 28 PASS, 3 FAIL, 1 INFO (salinan awal: `hasil-qa-firefox.awal.json`); galat runner 0; cleanup bersih. **4 butir FAIL pada run awal; diagnostik ulang 2026-10-26: #2, #3, #4 PASS dengan pengukuran diperbaiki (fokus Tab murni, XSS dipisah), QA penuh 33 PASS/0 FAIL/4 INFO; #1 tidak direproduksi: `halaman` dijalankan ulang dengan saringan abort dipertajam (hanya abort permintaan yang dimulai sebelum navigasi baru dan tidak diminta ulang dokumen baru yang disaring) menghasilkan 26 PASS, 0 FAIL, 0 fetchError pada 40 muatan Feedback; jalur saringan tidak teruji dengan data nyata dan penyebab FAIL lama tidak terbukti. Rincian awal:** (1) abort jaringan akibat navigasi (artefak pengukuran Firefox), (2) dan (4) indikator fokus tema gelap/aksen — `:focus-visible` tidak cocok pada pengukuran, belum terpastikan (butir terang PASS), (3) escape XSS di halaman show — belum terukur (tak ada injeksi; payload tak terbukti tampil). Bukti Tab murni mendukung fokus komponen Tailwind pada halaman create (Firefox 158 headless, input sintetis BiDi); itu BUKAN klaim "fokus Firefox lulus" secara menyeluruh. Temuan sisi legacy: outline checkbox `#chkWilling` tema gelap 2,06:1 (aksen bawaan), belum dibandingkan dengan Chrome. Detail, perintah, dan klasifikasi: `tailwind-evidence/f3/README.md` bagian Firefox. Cakupan mesin yang diklaim: Chromium (Chrome 155, Edge 156) dan Gecko (Firefox 158 headless); WebKit/Safari dan sentuh fisik NOT RUN; uji "sentuh" hanya ukuran target pada layout 390 px.

**Risiko / UNKNOWN.** (M2 ditutup oleh `href`; `<a>` tanpa `href` tetap menerima klik JS, tidak relevan karena tombol pilot selalu berhref.) `--color-on-accent:#fff` masih menunggu keputusan; Safari/WebKit, sentuh fisik, pembaca layar: NOT RUN (Firefox: lihat paragraf di atas); `TailwindAssetTest` dijalankan sesi utama setelah implementasi (exit 0, 11 tes, 477 asersi; 495 setelah prop `href`); akun `superadmin` di DB pilot berhenti menerima kata sandi uji di tengah sesi (sebab tidak diketahui; QA memakai `dummy.superadmin`).

## 12. UNKNOWN / PERLU KONFIRMASI

- Matriks peramban yang wajib didukung (Q2). `@layer`, `:where`, `color-mix`, `@property`: PoC memakai fitur ini; peramban tanpa
  `@layer` (< Chrome 99 / Safari 15.4 / Firefox 97) akan membuang seluruh blok utilitas (halaman legacy tetap utuh, kelas `tw:` tak
  bergaya). `color-mix` sudah dipakai layout (aksen). Belum ada keputusan batas minimum.
- Baseline git untuk fase berikutnya (Q7), Q8, rencana deploy dan versi aset (Q9): belum dijawab pengguna.
- ~~Apakah `public/css/tesys-tw.css` yang berisi safelist uji boleh dideploy~~ — tidak lagi relevan: artefak produksi F2 bebas safelist
  (9.109 B); apakah berkas produksi itu boleh ikut dideploy sebelum ada halaman yang memuatnya: belum diputuskan.
- Nada `dark` pada `x-tw.badge` (alias Bootstrap `dark` dipetakan ke `neutral`): UNKNOWN / NEEDS CONFIRMATION (dasar di [`tailwind-components.md`](tailwind-components.md) (b) badge).
- `--color-on-accent: #fff` (satu-satunya literal hex baru di masukan produksi) adalah pengecualian atas CLAUDE.md §9 ("tanpa hex baru") yang **BELUM disetujui pemilik**.
  Dua opsi: (1) terima di F2; (2) token `--on-accent` di `tesys-ui-tokens.css` pada F3. **UNKNOWN / PERLU KONFIRMASI** (keputusan pemilik).
- Target sentuh 44px: keputusan desain terbuka (Daftar tugas F3, butir 7).
- Apakah `public/css/tesys-tw.css` produksi boleh ikut dideploy sebelum ada halaman yang memuatnya: rekomendasi boleh diunggah (inert, tidak dimuat siapa pun) tetapi tidak wajib; keputusan pemilik.
- `php artisan optimize:clear` di server setelah komponen Blade baru diunggah (cache view): langkah manusia saat deploy.
- Perilaku di Firefox, Safari/WebKit, perangkat sentuh nyata, halaman aplikasi nyata, dan plugin lain: **NOT RUN**, belum diketahui.
- `TailwindAssetTest` versi terbaru (pasca-R1): lulus 11 tes/495 asersi (pasca-`href`, gerbang §4.3 langkah 1–5 terpenuhi; lihat §9). Bila `bootstrap/cache/config.php` muncul lagi (mis. `optimize`), gerbang gagal lagi.
- Insiden proses (INFO): `chrome.exe --version` pernah membuka tab di sesi Chrome pengguna dan alias `python` Windows Store menggantung; aturan pencegahan di
  [`tailwind-evidence/README.md`](tailwind-evidence/README.md#keselamatan-proses).
- Ringkasan lengkap keputusan terbuka dan risiko komponen: [`tailwind-components.md`](tailwind-components.md) (g).
