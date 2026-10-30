# F3 — baseline "SEBELUM" pilot Feedback (halaman nyata)

Pengukuran browser sungguhan terhadap halaman Feedback ASLI (belum dimigrasi ke Tailwind) dan
halaman non-pilot sebagai baseline isolasi. Runner: `scripts/tailwind-harness/cdp-pages.mjs`.

| Berkas | Isi |
|---|---|
| `hasil-sebelum-chrome.json`, `hasil-sebelum-edge.json` | Per halaman x tema (terang/gelap) x viewport (390x844, 1366x768): HTTP, judul, digest gaya, CSS/JS dimuat, galat konsol, overflow, ARIA/fokus/target sentuh; plus 13 skenario (login, toggle form, submit invalid/valid, respond, close). |
| `sebelum/*.png` | 17 screenshot (Chrome saja). |

## Cara menjalankan ulang
Prasyarat: server pilot sudah berjalan di `http://127.0.0.1:8099` ke DB uji (bukan `laravel`/`tesys_test`),
dimigrasi + di-seed (`DatabaseSeeder` -> `superadmin`; `DummyUserPerRoleSeeder` -> `dummy.staff`).
Kata sandi akun uji diambil dari seeder tersebut dan diberikan lewat variabel lingkungan
`TESYS_PW_SUPERADMIN` / `TESYS_PW_STAFF` (atau `--pw-superadmin=`/`--pw-staff=`); tidak ditulis di repo.
Port 9333 (Chrome) dan 9334 (Edge) harus bebas.

```
node scripts/tailwind-harness/cdp-pages.mjs --browser=chrome,edge --label=sebelum   # atau --label=sesudah
```
(jalur node contoh: `C:\laragon\bin\nodejs\node-v22\node.exe`; verifikasi dengan `Get-Command node`.)
Opsi: `--base`, `--out`, `--foto=chrome`, `--chrome=`, `--edge=`. PERINGATAN: tiap run MENULIS ke DB uji
(1 submission valid, 1 tanggapan, 1 close, 2 kiriman invalid ditolak) sehingga baris `feedback_submissions` bertambah 1 per run.

## Lingkungan
Chrome 155.0.8059.86, Edge 156.0.4314.42 (headless=new). Login lewat formulir nyata `/login`
(`#username`, `#password`, Enter). Tema gelap lewat `localStorage['tesys-theme']='dark'` sebelum navigasi (kunci yang dipakai layout).
Pemilihan `<select>` kategori dan `noValidate` pada form untuk menguji galat server dilakukan lewat JS (headless tidak punya popup native).

## Catatan penghentian Edge
Run Edge: `Browser.close` tidak membuat proses induk Edge keluar; JSON mencatat `sisaProsesPenanda: 1`, `taskkill: "gagal"`.
Proses itu (PID 33072, `user-data-dir` runner) ternyata sudah mati saat diperiksa ulang; folder sementara dihapus manual.
Verifikasi akhir: 0 proses dengan penanda `tesys-pages-*`. (Perilaku runner: pemeriksaan sisa dilakukan 0,6 dtk setelah kill; perlu jeda lebih panjang untuk Edge.)

---

# F3-b — hasil "SESUDAH" (pilot Feedback sudah memakai `x-tw.*`)

| Berkas | Isi |
|---|---|
| `hasil-sesudah-chrome.json`, `hasil-sesudah-edge.json` | Runner `cdp-pages.mjs --label=sesudah` (60 pengukuran + 13 skenario). Chrome memakai akun `superadmin` (kata sandi lama masih berlaku saat itu); Edge memakai `dummy.superadmin` (`--user-superadmin`), karena `superadmin` mendadak ditolak (sebab tidak diketahui; DB tidak diubah). |
| `sesudah/*.png` | 17 screenshot Chrome, nama sama dengan `sebelum/` (berpasangan). |
| `hasil-qa-sesudah-{chrome,edge}.json` | `cdp-qa-f3.mjs`: 27 butir, 0 FAIL kedua browser (sentuh 390x844, kontras, fokus, aksen, Tab, nama aksesibel, escape, kontrak DOM). |
| `galeri-sesudah/` | `cdp-run.mjs` ulang (artefak baru): Chrome dan Edge masing-masing 34 PASS, 1 FAIL (`G5-target-sentuh-44`, identik dengan F2); `G3-bang` PASS; `G0-proses-bersih` PASS. Evidence F2 di folder induk tidak ditimpa. |

Perintah: `node scripts/tailwind-harness/cdp-pages.mjs --browser=chrome|edge --label=sesudah [--user-superadmin=dummy.superadmin]` dan `node scripts/tailwind-harness/cdp-qa-f3.mjs --browser=chrome|edge`.
Runner diperbaiki: pemeriksaan sisa proses menunggu hingga proses ber-penanda hilang (>= 3 dtk, maks 20 dtk; Edge terukur 3,8-6,4 dtk, `sisaProsesPenanda` = 0 di semua run).

## Perbandingan sebelum/sesudah (Chrome; Edge sama kecuali dicatat)
- Isolasi: `tesys-tw.css` dimuat tepat 1x pada 40/40 pengukuran halaman Feedback, selalu setelah `tesys-ui-base.css` dan sebelum `tesys-ui.css`/tokens/…/refine (di layout `@stack('styles')` berada setelah base). Halaman non-pilot (`/`, `/profile`, `/department`, `/bank`, `/budget-request`): tw 0x, daftar CSS/JS identik, `digestGaya` identik di Chrome. Edge: `/` dan `/profile` berbeda hanya karena akun berbeda (data pribadi pada konten); belum dibuktikan dengan akun sama (dugaan, bukan bukti). `/bank`: galat DataTables `_DT_CellIndex` sudah ada sebelumnya (2 entri konsol, sama).
- Fungsi: skenario toggle, validasi native & server (judul/WA), submit valid -> flash, respond, close SweetAlert (batal/ya), 403 inbox staff: identik; tanpa galat konsol baru; tanpa overflow horizontal. Selisih hanya bertambahnya baris DB, id, jam; `infoTabel` null pada run pertama karena selektor runner `.card-body table` (kartu `x-tw.card` tanpa `.card-body`) — selektor diperbaiki.
- Anomali runner: halaman pertama setelah login (`feedback-index-kosong` 390x844 terang, Chrome) mencatat daftar CSS berbeda (urutan/duplikat jaringan); bukan perubahan aplikasi.
- Target sentuh 390x844 (kontrol utama): sebelum tombol 34-36px, input/select 35-36px; sesudah 48x48 (back), 309x48 (select/judul/WA), 162,8x48 (Kirim), 144,6x48 (Buat), 147,8x48 (Tanggapan), 186,8x48 (Tandai Selesai). Tersisa < 44: label checkbox 285x38 + kotak centang (legacy), tombol Detail 36px (legacy), kontrol DataTables inbox.
- Nama aksesibel: tombol kembali "Kembali ke daftar General Feedback"/"Kembali ke Feedback Inbox" (sebelumnya `tombolTanpaNama` 1 -> 0). Tersisa (bawaan, tidak diperbaiki): `textarea[name=response_text]` tanpa nama.
- Kontras (terang/gelap, aksen bawaan): minimum 4,81:1 (badge terang / placeholder gelap); tombol primer 8,41; fokus >= 7,38:1; 8 praset x 2 tema: tombol >= 6,10, fokus >= 5,55. `#ffd400` (bebas): 1,43:1 (sama dengan F2 deviasi #4). Outline fokus computed 1,25px pada zoom .8 (deviasi F2 #3). Transisi `outline-color` membuat sampel < 0,3 dtk tampak lebih terang; QA menunggu 550 ms.
- Urutan Tab create: kembali, kategori, judul, isi masukan, centang diskusi, WhatsApp, Kirim (sesuai urutan DOM). Enter pada tombol kembali menavigasi.
- Zoom: html computed 0,8 pada 1366, 1 pada 390; tinggi kontrol 34 CSS px (tersaji 27).
- Desktop: input/select `x-tw` 34px vs `form-control-sm` legacy ~30px (token `--ctl-h`); tombol sm/md setara legacy.

NOT RUN (pada F3-b; Firefox diuji di F3-d, bagian di bawah): Firefox, WebKit/Safari, sentuh fisik, pembaca layar; `TailwindAssetTest` tidak dijalankan oleh executor; sesi utama menjalankannya setelah implementasi: exit 0, 11 tes, 477 asersi.

---

# F3-c — hasil "HREF" (tombol navigasi pilot dialihkan dari form GET ke `<a href>`)

Perubahan: `feedback/index.blade.php` (Buat Submission), `create.blade.php` dan `show.blade.php` (kembali) memakai
`<x-tw.button href="{{ route(...) }}">` tanpa `<form method="GET" class="… no-busy">`. Inbox tidak punya tombol navigasi form. Artefak `public/css/tesys-tw.css` tidak berubah:
**10.400 B, `f2215381e0054416d5448c3923abffada988e9d4ed181583811dca28edabe258`** (`build-tailwind.ps1` exit 0, `-Cek` exit 0; jarak `mr-3` memakai kelas BS legacy, bukan kelas `tw:` baru).

| Berkas | Isi |
|---|---|
| `hasil-href-{chrome,edge}.json` | `cdp-pages.mjs --label=href --user-superadmin=dummy.superadmin`: 60 pengukuran + 13 skenario, galatRunner=0, sisaProsesPenanda=0. |
| `href/*.png` | 17 screenshot Chrome, nama sama dengan `sesudah/`. |
| `hasil-qa-href-{chrome,edge}.json` | `cdp-qa-f3.mjs --label=href`: 31 butir (27 lama dengan selektor tautan + 4 baru `tautan-href-*`), 0 FAIL. |

Perintah: `node scripts/tailwind-harness/cdp-pages.mjs --browser=chrome|edge --label=href --user-superadmin=dummy.superadmin` dan `node scripts/tailwind-harness/cdp-qa-f3.mjs --browser=chrome|edge --label=href --user-superadmin=dummy.superadmin`
(kata sandi lewat `TESYS_PW_*`). `cdp-qa-f3.mjs` diperbarui: selektor tombol navigasi menjadi `.content a.tw\:inline-flex[href]`, peran harapan `link`, opsi `--label`, fungsi `periksaTautan` (href/tanpa form/tanpa `?`/klik/Enter/ctrl+klik via CDP `modifiers:2`/nama aksesibel), kontras tautan pada loop aksen.

Hasil dibanding "sesudah" (pra-href): fungsi identik (toggle, validasi native+server, submit valid -> flash, respond, close SweetAlert batal/ya, 403 inbox staff); konsol per halaman identik; overflow 0; tw dimuat 1x pada 40/40 pengukuran Feedback, 0x pada halaman non-pilot; `tombolTanpaNama` tidak berubah. `digestGaya`/`digestKunci` halaman pilot berubah (button -> a, wajar); non-pilot: Edge identik (akun sama), Chrome berbeda hanya karena "sesudah" Chrome memakai akun `superadmin` (kini `dummy.superadmin`; data pribadi pada konten). Selisih skenario hanya id, jam, jumlah baris DB uji. Screenshot index/create terlihat sama dengan `<button>` sebelumnya (posisi/ukuran/warna).

Tautan (kedua browser): `<a href>` ada, href `/feedback` (kembali di create/show staff), `/feedback/create` (Buat), `/feedback-inbox` (kembali di show superadmin); bukan di dalam form; tanpa `?`; klik biasa dan Enter menavigasi tepat ke target; ctrl+klik membuka 1 tab baru (ditutup runner), halaman asal tetap; peran `link`, nama aksesibel dari `aria-label`/isi; tanpa teks "Memproses"; sentuh 390x844 kembali 48x48, Buat 144,6x48; kontras terang/gelap lolos; praset aksen 8 x 2 tema: tautan primer min 6,10:1, fokus min 5,55:1; Tab dari create: tautan kembali pertama, urutan sama; Enter pada kembali menavigasi.

NOT RUN (pada F3-c; Firefox diuji di F3-d, bagian di bawah): Firefox, WebKit/Safari, sentuh fisik, pembaca layar; `TailwindAssetTest` (dijalankan sesi utama setelah pengalihan href: exit 0, 11 tes, **495 asersi**; angka 477 di atas historis). Catatan: `superadmin` tidak dipakai (sandi lama tidak berlaku); tidak ada kunci throttle login pada run ini.

## Firefox (F3-d) — Gecko 158.0.1, headless, WebDriver BiDi

**Cakupan yang boleh diklaim:** Gecko (Firefox 158.0.1, paket Store `Mozilla.Firefox` 158.0.1.0, build 20261019185856), headless, tanpa emulasi sentuh/seluler (hanya `setViewport` 390/1366). WebKit/Safari, Firefox non-headless, sentuh fisik, dan pembaca layar: **NOT RUN**.

| Berkas | Isi | Ringkasan |
|---|---|---|
| `hasil-firefox.json` | 29 butir halaman nyata (`--bagian=halaman`), dijalankan ulang 2026-10-26 dengan saringan abort yang dipertajam | 26 PASS, 0 FAIL, 3 INFO, galat runner 0 (run awal: 25 PASS, 1 FAIL, 3 INFO; salinan: `hasil-firefox.awal.json`) |
| `hasil-qa-firefox.json` | 37 butir QA (padanan `cdp-qa-f3`, dijalankan ulang penuh setelah diagnostik; lihat "Diagnostik ulang") | 33 PASS, 0 FAIL, 4 INFO, galat runner 0 (sebelumnya 28 PASS, 3 FAIL, 1 INFO) |
| `firefox/` | 14 screenshot (1366×768 dan 390×844; terang dan gelap) | Tata letak sebanding dengan `href/` pada 2 pasang yang dibandingkan |

**Cara menjalankan ulang** (server pilot terisolasi harus hidup; lihat bagian awal README ini):
`node scripts/tailwind-harness/bidi-firefox.mjs --bagian=halaman,qa --base=http://127.0.0.1:8099 --port=9444 --user-superadmin=dummy.superadmin` (kata sandi via env `TESYS_PW_STAFF` / `TESYS_PW_SUPERADMIN`; nilai seed ada di seeder repo, tidak ditulis di sini).
Firefox dijalankan runner dengan `firefox.exe -no-remote -profile <profil-sementara> -headless -remote-debugging-port=9444 about:blank` (alias MSIX `%LOCALAPPDATA%\Microsoft\WindowsApps\firefox.exe`; jangan membuka folder `WindowsApps`). Tanpa geckodriver/dependensi (BiDi bawaan Remote Agent).
Jebakan: `fs.existsSync` gagal pada alias MSIX (pakai `lstatSync`); jalur env cache Laravel di Windows harus diawali `/` dengan `MSYS_NO_PATHCONV=1`; `php -S` satu utas sehingga navigasi cepat bisa menghasilkan halaman "tidak ada koneksi" sesaat.

**PASS (terbukti di Firefox):** login lewat formulir; `tesys-tw.css` dimuat 1× sebelum `tesys-ui-*.css`; halaman non-pilot tanpa Tailwind dan CSS sama dengan Chrome; toggle kategori/WhatsApp; validasi native dan server; submit valid → flash; 403 inbox untuk staff; DataTables inbox; respond kosong/valid; close SweetAlert; `<a href>` (klik, Enter, href benar); kontras teks terang/gelap (min 4,81:1); kontras tombol/tautan 8 aksen praset (min 6,1:1); zoom computed 0,8 di ≥901px; tema gelap/terang; fitur CSS efektif (`color-mix`, `@layer`, `:has`, `zoom`); nama aksesibel (`browsingContext.locateNodes`); ukuran target pada layout 390 px.

**Status RUN AWAL (historis) — 4 butir FAIL; keadaan terkini ada di bagian "Diagnostik ulang" di bawah: #2, #3, #4 PASS pada pengukuran baru; #1 PASS pada run ulang halaman (tidak terulang; PENYEBAB FAIL LAMA TIDAK TERBUKTI, dan klasifikasi "bukan aplikasi" pada tabel ini adalah dugaan awal, bukan bukti):**

| # | Butir | Aktual | Klasifikasi (Reviewer) |
|---|---|---|---|
| 1 | `F-jaringan-tanpa-gagal-halaman-feedback` | font Inter `NS_BINDING_ABORTED` pada halaman `feedback-show-superadmin-closed` + 19 abort polling `/notifications`, `/todo-list/has-pending` | (Dugaan awal, historis) Pengukuran/perilaku peristiwa Firefox: abort permintaan yang dibatalkan navigasi dicatat ke halaman baru (Chrome 0). Font tampil benar di screenshot. Bukan aplikasi. |
| 2 | `indikator-fokus-komponen-gelap` | 4 elemen `fokusVisible:false`, `outline none 3.75px` (tema terang PASS dengan kode yang sama; satu elemen gelap `fokusVisible:true`, outline solid 1,25px, rasio 7,38) | **Belum terpastikan**; condong artefak pengukuran/heuristik `:focus-visible`. Perlu diagnostik ulang dengan Tab murni. |
| 3 | `escape-xss-index-show` | index `{el:false, skrip:false, teks:true}`; show `{el:false, skrip:false, teks:false}` | **Belum terukur**: tak ada elemen/skrip injeksi (aman), tetapi payload tak terbukti tampil di show; tidak boleh dipakai sebagai bukti escape di show Firefox (escape didukung kode `{{ }}` dan bukti index). |
| 4 | `aksen-praset-8x2-kontras-dan-fokus` | 16/16 gagal hanya pada syarat fokus (`fokusVisible:false`); kontras tombol/tautan lulus (min 6,1:1) | Sama dengan #2: belum terpastikan, condong artefak. Butir kontras dan fokus sebaiknya dipisah. |

(Historis — run awal; saringan itu sudah diganti aturan baru di bagian Diagnostik ulang, field `abortNavigasiNotifikasi` tidak ada lagi.) Tidak ada asersi yang dilemahkan; satu-satunya penyaringan: abort polling navigasi dihitung terpisah (`abortNavigasiNotifikasi: 19`).

**Setup/cleanup:** profil sementara unik `tesys-ff-*`; proses dikenali lewat command line yang memuat profil + keturunannya; penutupan sopan `browser.close`; `sisaProsesPenanda: 0`, `profilDihapus: true`. Saat uji tidak ada proses Firefox milik pengguna (`pidBukanRunnerSebelum/Sesudah` kosong) — jadi "PID pengguna tak berubah" benar secara hampa, bukan bukti bahwa sesi pengguna terlindungi.
Data uji ditulis ke DB `tesys_f3_pilot` (submission, tanggapan, close). Server pilot dijalankan dengan isolasi env (cache konfigurasi dilewati lewat `APP_*_CACHE` ke jalur tak ada).

### Diagnostik ulang (Firefox, 2026-10-26) — hanya pengukuran runner; aplikasi tidak diubah

Berkas: `hasil-diag-firefox.json` (bagian `diag-fokus`, `qa-xss`, `diag-jaringan`), `firefox/diag-fokus-{gelap,terang}-bawaan-{kembali,kirim}.png` (4), `firefox/diag-xss-show-terang-1366x768.png` (1), dan `hasil-qa-firefox.json` dijalankan ulang penuh (**33 PASS, 0 FAIL, 4 INFO, galat runner 0**; `navigasiDiulang` kosong; `sisaProsesPenanda` 0, profil dihapus). Runner: `--bagian=diag-fokus|qa-xss|diag-jaringan` (+ `qa`). Bagian `halaman` kemudian dijalankan ulang (lihat paragraf "Saringan abort dipertajam" di bawah).

**#2 dan #4 (fokus) — FAIL lama tidak terulang dengan Tab murni; PENYEBAB FAIL LAMA TIDAK DIKETAHUI (tidak terbukti bahwa metode lama cacat: `.focus()` skrip kini juga memberi `:focus-visible`); bukan temuan kompatibilitas aplikasi pada pengukuran baru.** Fokus hanya lewat Tab/Shift+Tab (`input.performActions`, tanpa `.focus()` skrip dan tanpa klik; Space pada checkbox lewat papan ketik), dari awal dokumen, di tab baru per pengukuran (9 run diag, urutan eksekusi tercatat di JSON: gelap, terang, 2 run metode lama, 4 run aksen #92232b/#0f6e66, ulang gelap di akhir). Pada seluruh run (gelap/terang, aksen bawaan, #92232b, #0f6e66): `document.hasFocus()` true; 11 elemen di `.content` menerima fokus, `:focus` dan `:focus-visible` cocok pada 11/11; 5 elemen komponen Tailwind (tautan kembali, select, judul, WhatsApp, tombol kirim; 7 pengukuran maju+mundur) punya outline `solid` 1,25px (computed; 2px dikali zoom .8, sama seperti catatan Chromium di README utama), offset 2px, radius 6px, rasio terhadap latar induk min **7,07:1 (gelap)** dan **6,48:1 (terang, #0f6e66)**; Shift+Tab mengikuti urutan terbalik. Aturan yang cocok (direkam dari `document.styleSheets`): `.tw\:focus-visible\:outline-2/outline-offset-2/outline-accent-text!` (layer utilities) dan `:focus-visible{outline:2px solid var(--accent)}` legacy. Metode lama (Tab sekali lalu `.focus()` skrip) di tab bersih juga memberi `:focus-visible` true; di QA penuh 0/16 praset memberi false. **Penyebab kegagalan run lama tidak berhasil direproduksi; tidak diketahui** (dugaan belum terbukti: keadaan fokus/jendela tab lama setelah interaksi mouse). Butir diperbaiki: `urutan-tab-create-*` dan `indikator-fokus-komponen-*` kini Tab murni (+ butir tombol kirim); `aksen-praset-8x2-kontras-dan-fokus` dipecah menjadi `aksen-kontras-8x2` (PASS, min 6,10:1) dan `aksen-fokus-8x2` (PASS, 16/16, min rasio fokus 5,55:1), plus INFO `aksen-fokus-metode-lama-informasi`. Ini bukti untuk halaman create di Firefox 158 headless dengan input sintetis BiDi; **bukan** klaim menyeluruh "fokus Firefox lulus" (non-headless, input fisik, halaman lain tidak diuji).

Temuan sisi (legacy, TIDAK dimigrasi ke Tailwind, belum dibandingkan dengan Chrome): checkbox `#chkWilling` (custom-control) tema gelap memiliki outline accent dengan rasio **2,06:1** (aksen #92232b) dan **2,84:1** (#0f6e66), di bawah 3:1; terang lulus (8,41 / 6,10). Textarea `.form-control` tidak memakai outline (aturan legacy `.form-control:focus{outline:0}`) tetapi ring `box-shadow` 3px alfa 0,38 (gelap) / 0,2 (terang); warna ring gelap tetap merah `rgba(146,35,43,.38)` pada aksen #0f6e66. Dilaporkan sebagai "Potential future improvement" (butir INFO `fokus-komponen-legacy-informasi-*`).

**#3 (XSS show) — PASS setelah pengukuran diperbaiki.** Pemisahan: `escape-xss-index` dan `escape-xss-show`. Id submission baru diambil dari baris yang memuat judul payload (bukan baris pertama), halaman show diverifikasi benar (path `/feedback/<id>`, title "Detail Submission", bukan fallback "Tidak ada koneksi", ulang maks 2x). Terukur (run `qa-xss` id 20 dan QA penuh id 21): `elInjeksi` false, `skripInjeksi` false, `teksJudul` true, `teksPesan` true; judul tampil sebagai teks di `div#tsTitle.page-title` (topbar) karena `public/js/tesys-shell.js` memindahkan teks `h1` ke topbar dan menghapus `h1` (jadi `h1` null di DOM akhir adalah normal). Penyebab FAIL lama tidak dapat dipastikan dari data lama (tidak ada judul/title/path tersimpan); kandidat: halaman/id yang dimuat salah. **Tidak ada elemen/skrip injeksi pada run mana pun.**

**#1 (jaringan) — tidak direproduksi pada dua pengukuran independen (`diag-jaringan` 24 muatan dan `halaman` ulang 40 muatan Feedback), penyebab FAIL lama tetap tidak terbukti.** Runner kini mencatat stempel waktu, URL dokumen aktif, dan `dalamJendelaNavigasi` (NS_BINDING_ABORTED yang jatuh <=400 ms setelah dokumen baru selesai; hanya itu yang disaring, jumlahnya dicatat; abort lain tetap FAIL). `diag-jaringan` (baca-saja; staff dan superadmin; 6 URL x tema x 2 viewport = 24 muatan, termasuk show submission Closed oleh superadmin) menghasilkan **0 fetchError** (PASS). Run `halaman` ulang (lihat di bawah) juga 0 fetchError pada 40 muatan Feedback.

Tambahan runner: `Hal.goto` mengulang maks 2x saat Firefox menolak navigasi "interrupted an unfinished navigation" (dicatat di `navigasiDiulang`); sebuah run QA sebelumnya sempat berhenti karena galat ini dan hasilnya ditimpa run penuh. Screenshot diag = 5 berkas (4 fokus + 1 XSS show). Data uji ditulis ke DB `tesys_f3_pilot` (submission baru dari run qa-xss dan QA).

**Saringan abort dipertajam dan `halaman` dijalankan ulang (2026-10-26; hanya runner yang diubah).** Saringan lama menyaring `NS_BINDING_ABORTED` bila stempel waktunya <= (dokumen baru selesai + 400 ms) tanpa batas bawah dan tanpa memeriksa permintaan, sehingga abort font nyata di jendela itu bisa tertelan. Aturan baru (komentar `Hal.gagalDetail()`): abort disaring (dianggap milik dokumen LAMA) hanya bila (1) permintaan itu tercatat dimulai (`network.beforeRequestSent`, catatan waktu yang tidak dihapus `reset()`) SEBELUM `browsingContext.navigationStarted` navigasi baru, (2) abortnya jatuh antara awal navigasi baru dan 400 ms setelah dokumen baru selesai, dan (3) guard: URL (tanpa kueri) yang sama tidak diminta oleh dokumen baru; abort font/stylesheet/skrip/gambar/dokumen yang dimulai pada/setelah navigasi baru, yang waktu mulainya tak diketahui, atau yang URL-nya diminta dokumen baru, TIDAK pernah disaring (FAIL). Setiap fetchError membawa `kategori` (destination, atau initiatorType), `msMulaiSebelumNav`, `dalamJendelaNavigasi`, dan `alasan`; butir #1 memuat `rincianSaring` (tersaring dan gagal per kategori, daftar URL tersaring). **Hasil run ulang:** `hasil-firefox.json` 26 PASS, 0 FAIL, 3 INFO, galat runner 0, `navigasiDiulang` kosong; butir `F-jaringan-tanpa-gagal-halaman-feedback` PASS dengan **0 fetchError pada 40 muatan halaman Feedback (tersaring 0, gagal 0)**; satu-satunya perubahan status dibanding run awal adalah butir ini (FAIL ke PASS), butir lain sama. Karena tidak ada fetchError Feedback, **jalur penyaringan baru tidak teruji dengan data nyata di Feedback** (0 abort untuk disaring); yang terbukti hanya bahwa pada run ini tidak ada abort font/polling di halaman Feedback. Di halaman non-pilot `reg-budget-request` (terang 1366x768, tidak dihitung di butir) terukur 1 abort `xmlhttprequest /budget-request/tab/ca` yang TIDAK disaring karena URL yang sama diminta ulang dokumen baru (guard bekerja konservatif; informasi saja). Kesimpulan #1: FAIL lama **tidak direproduksi** dan tidak ada bukti cacat aplikasi; penyebabnya (kemungkinan balapan abort polling/font saat navigasi pada run awal) **tidak terbukti**, jadi butir ditutup sebagai "tidak terulang", bukan "terjelaskan". Pesan: data uji ditulis ke DB `tesys_f3_pilot` (submission baru dari runner `halaman`); screenshot `firefox/` ditimpa (14 berkas halaman + 5 diag).

**Catatan rekam jejak:** `hasil-qa-firefox.awal.json` = hasil QA run awal (28 PASS, 3 FAIL) sebelum pengukuran diperbaiki; `hasil-qa-firefox.json` = run QA penuh setelah perbaikan (33 PASS, 0 FAIL, 4 INFO). PASS berlaku untuk pengukuran baru, bukan penutupan penyebab FAIL lama. `hasil-firefox.awal.json` = hasil `halaman` run awal (25 PASS, 1 FAIL: #1). Saringan abort kini dibatasi ke permintaan dokumen lama (lihat paragraf di atas). Uji XSS hanya mencakup konteks teks (bukan atribut/inline JS).

**Catatan penyimpanan screenshot:** hanya PNG `href/`, `firefox/` (termasuk `diag-*`), dan PNG F2 di folder induk yang di-commit. PNG `sebelum/`, `sesudah/`, dan `galeri-sesudah/` hanya disimpan lokal (di-gitignore, hanya pola `*.png`); JSON hasilnya (`hasil-*.json`) tetap di-commit. Perbandingan sebelum/sesudah pada repo bersih karenanya hanya bisa dibaca dari JSON; jalankan ulang `cdp-pages.mjs` untuk menghasilkan ulang PNG.
