# M1 — Redesain visual halaman Cost Request (index)

Status: **diimplementasikan di working copy, belum di-commit, belum dideploy.** Hanya presentasi (Blade + satu artefak CSS):
tidak ada perubahan route, controller, model, permission, query, URL, atau JS inline. Bukti gambar: `docs/ui/tailwind-evidence/m1/`.
Mengikuti pola F4 ([`tailwind-f4-redesain.md`](tailwind-f4-redesain.md)); aturan Tailwind di [`tailwind.md`](tailwind.md).

## Yang dibuat Tailwind (`tw:`)
| Bagian | Cara |
|---|---|
| Kepala halaman | `h1` + breadcrumb dipindah ke wadah `tw:sr-only` (shell memindahkannya ke topbar; isi h1 tetap "Cost Request"), lalu `x-tw.page-header` (eyebrow, judul, deskripsi). "Tambah Request" (`x-tw.button href`, peran sama: superadmin/manager/supervisor) pindah dari toolbar ke kepala; disembunyikan saat tab Database aktif lewat varian `has` di pembungkus halaman + hook `cr-add-request` (perilaku lama: tombol tidak ada di tab Database). |
| Tata letak | Pembungkus halaman, baris tab+rentang tanggal (`.cr-head`), wadah kontrol tanggal: utilitas `tw:flex/gap`. Tombol Refresh = `x-tw.button` (id `btnRefreshCR` tetap). |
| Kartu | `x-tw.card padding=sm` menggantikan `.card.cr-card > .card-body` (div murni; popover filter/kolom tidak terpotong). |
| Toolbar | Wadah `.cr-toolbar` tw (tanpa `.custom-toolbar*`/`.toolbar-row`); Clear/Filter/Kolom/Export/Reset filter/bulk Approve-Reject-Batal = `x-tw.button` dengan kelas hook JS dipertahankan. Bar bulk tetap ditoggle `d-none`/`d-flex` oleh JS. Target sentuh mobile 48px (`tw:max-sm:min-h-12`). |
| Sel tabel | Deskripsi (tebal; merah NOB / kuning NOB sub-item) + metadata redam; badge NOB dan Jenis = `x-tw.badge dot`; tanggal/PIC/requestor hierarki tipografi; `tw:tabular-nums` pada nominal; nomor urut redam. PIC dibungkus satu `div` (rata kanan di kartu mobile). |
| Alert dipotong 1.000 baris, keadaan kosong, kerangka pemuatan | Markup tw (tanpa `.alert`, `x-empty`, `x-skeleton`); kerangka pemuatan statis tanpa animasi. |
| Hover baris | Aturan inline index diubah: baris disorot `--surface-2` per sel (sebelumnya hover dipaku `--surface`, hanya sel beku yang menyorot). |

## Tetap legacy (alasan terukur/teknis)
- **Tab** (`ul.nav.nav-tabs.cr-main-tabs` + `.nav-link`): plugin tab Bootstrap membutuhkan `.nav`/`.active`; `<a>` memerlukan `!` untuk menang atas `a{color}`/`.nav-link` (aturan proyek: tanpa `!` baru). Gaya §14a refine sudah memakai token.
- **Strip status** (`x-workflow-stepper`): komponen bersama halaman lain; gaya §14b refine.
- **Th/td/tfoot, kolom beku, mode kartu mobile** (`.cr-tbl`, `.cr-hscroll`, `ts-card-table`, DataTables `dom/language`): kelas-kelas itu kontrak JS/DataTables; tw biasa kalah dari §14d refine (`!important`) dan mode kartu `tesys-table.js`. Mengalahkannya = `!` baru atau menimpa CSS legacy, keduanya dilarang di M1.
- Input cari (dibuat JS), panel filter `TesysColumnFilter`, kolom centang bulk, kolom aksi `ts-action-*`, `x-status`, drawer, semua modal.

## CSS legacy yang dirapikan (terbukti tak dipakai lagi, grep)
- `public/css/tesys-ui-refine.css` §14c: aturan `.cr-card`, `.cr-toolbar .custom-toolbar-left/right`, `.cr-toolbar .btn-success`, dan blok reset `.cr-toolbar` (latar/bingkai/jarak `.custom-toolbar`); komentar §14 disesuaikan.
- `budget-request/index.blade.php` `<style>`: `.custom-toolbar*`, `.toolbar-row/-label/-input` beserta pasangannya di `@media (max-width:576px)`.
Aturan §14 lain TIDAK diubah (masih dipakai elemen yang kelasnya adalah kontrak).

## Perbaikan pasca-review (M-1..M-3, L-1..L-3)
- **Popover mobile (M-1)**: `refine.css` §14c blok `@media (max-width:640px)` memulihkan aturan lama mobile.css (`.custom-toolbar-left .cr-pop*`) untuk `.cr-toolbar`: `.cr-toolbar{position:relative}`, `.cr-pop{position:static}`, `.cr-pop-menu{left/right:0; min-width:0; width:auto}`. `!` pada left/right/min-width **izin khusus**: hanya memulihkan aturan lama yang sama, karena JS index menulis left/right sebagai inline style dan `_toolbar` menulis `min-width:420px` inline.
- **Tombol hapus filter (M-2)**: `.cr-btn-clear{width:100%}` di `@media (max-width:576px)` index diubah `width:auto` (aturan penimpa `.custom-toolbar .btn` mobile.css sudah tak berlaku).
- **Busy bulk (M-3)**: `TesysUI.busy()` hanya digayakan components.css untuk `.btn`/`.ts-action-primary`; `x-tw.button` tak punya `.btn`. `refine.css` §14c menambah `.cr-bulk-bar .is-busy` (label disembunyikan, spinner memakai keyframes `ts-spin` dan `--ts-busy-color`; tanpa hex/animasi baru). `color:transparent !important` dipakai agar menang atas aturan kompatibilitas AdminLTE, sejalan dengan components.css:504.
- **CSS yatim dihapus (L-1)**: `.cr-desc-main`, `.cr-meta`, `.cr-nob-badge`, `.cr-jenis-badge`, `.row-nob(-item) .cr-desc-main`, `.cr-toolbar .toolbar-input` (refine.css) dan `.card-header*` di `@media` index (grep: tak ada pemakai di `resources/` maupun `public/js`).
- **Dukungan `:has()` (L-3)**: sembunyi-nya "Tambah Request" di tab Database memakai `:has()` (Chrome 105+, Firefox 121+, Safari 15.4+). Tanpa dukungan, tombol tetap tampil di tab Database (hanya tautan ke halaman create, tidak merusak).

## Build dan uji
- `scripts/build-tailwind.ps1`: `public/css/tesys-tw.css` **15.634 B** (anggaran 50 KB; gzip 3.471 B), SHA-256 `6e89716b8cadb510a36da2c65f6e507a118cabfad8b1827ee572f21e972184eb`, `-Cek` exit 0 (deterministik, berkas terpasang = hasil build). Sebelum M1: 14.129 B `ab275d4c…`. **Unggah ulang CSS dan naikkan versi aset** (CSS di-cache 1 tahun).
- `tests/Unit/TailwindAssetTest.php`: view `budget-request` ikut diperiksa terhadap artefak; penjaga pemuatan sekarang mengizinkan `feedback/*` dan `budget-request/index` (persis 1x), partial `_toolbar/_table/_rows/_rows_db` 0x. Tes **belum dijalankan** (gerbang CLAUDE.md §4.3 belum dipenuhi).
- Tidak ada kelas `!` baru, hex baru, gradien, atau animasi.

## Verifikasi browser (Chrome headless 1440x900 dan 390x844 via CDP, server uji `http://localhost:8099`, DB `tesys_f3_pilot`)
Tab CA/CP/DB (45/27/72 baris), ganti rentang tanggal (7 hari: tabel dimuat ulang, badge 10), cari, filter, kolom, stepper, centang → bar bulk + dialog konfirmasi Approve/Reject (tidak dikonfirmasi), drawer lewat status dan kode, Export (permintaan 200), Refresh, keadaan kosong, kerangka pemuatan, gagal muat + Coba lagi, mode gelap, peran lewat View As (head/manager/guest/finance; login langsung akun dummy.<role> selain superadmin mendapat 403 di DB uji), konsol dan jaringan bersih. Tidak diuji: Firefox/Safari, perangkat sentuh nyata, pembaca layar, aksi POST bulk yang dikonfirmasi, menu per-peran di DB uji.

## Temuan di luar cakupan
- Kolom Status terpotong di balik kolom Action yang beku pada 1440 px (sudah demikian sebelum M1).
- Teks keadaan kosong menyebut "atau buat pengajuan baru" walau peran tidak boleh membuat (teks lama).
- Aturan lama `tr:hover` yang dipaku `--surface` membuat hover tak terlihat (diubah di M1, lihat atas).

---

# M2 — Redesain visual halaman create Cost Request

Status: **diimplementasikan di working copy, belum di-commit, belum dideploy.** Hanya presentasi: tidak ada perubahan route, controller, model, permission, validasi, nama field, atau logika JS (hanya string kelas pada elemen yang dibuat JS). Bukti: `docs/ui/tailwind-evidence/m2/` (sebelum/sesudah desktop, mobile, gelap, galat server, Cash Advance, SweetAlert; snapshot kontrak DOM `kontrak-dom-*.json`).

## Yang dibuat Tailwind (`tw:`)
| Bagian | Cara |
|---|---|
| Kepala | `h1`+breadcrumb di wadah `tw:sr-only`; `x-tw.page-header` (eyebrow, judul, deskripsi) + `x-tw.button href` "Kembali ke daftar". |
| Kartu | Tiga `x-tw.card` bertajuk (Informasi, Rincian, Lampiran; footer = petunjuk) + kartu aksi (`padding=sm`). Ringkasan galat server = blok tw (`role=alert`). |
| Informasi | Grid 2/3 + 1/3 (>= 901px). Label/hint/galat per field = tw; Deskripsi = `x-tw.input` (uppercase lewat `tw:uppercase`), Notes = `x-tw.textarea` (galat per field kini ikut tampil untuk `notes`). Kotak email = div tw (`#emailTagsBox`, fokus-dalam). |
| Alert | `#nobWarning` (statis) dan `#workflowAlert`/`#caQuotaAlert` (kelas dirakit JS) = panel tw nada warning/info/danger. |
| Rincian/Lampiran | Header kartu memuat Total (`x-tw.badge`), Download Template (`x-tw.button href`), Import, Tambah Baris; tabel polos (tanpa `.table`/`.table-bordered`), lebar minimum 860px di mobile; badge "Wajib" = `x-tw.badge`. Info lampiran = panel tw. Footer aksi: hint (`#saveHint`) + Kembali + Simpan (`x-tw.button type=submit`, id `#btnSaveCR`), kolom terbalik di mobile. Target sentuh mobile 48px. |

## Tetap legacy
- `<select class="form-control">` untuk Budget Plan, Tipe, Penerima, Project (+ `TesysSearchableSelect.init/initOne`), semua kontrol baris rincian (dibangun JS: `form-control-sm`, tombol `btn`), baris lampiran global, SweetAlert, drop zone (`.att-dropzone`, CSS halaman).
- Hook kelas `cr-type-ben/-col`, `cr-ben-col` (mobile.css:278), `cr-detail-head/-actions` dipertahankan.

## Perubahan JS (hanya string kelas; logika tidak berubah)
1. Tag email (`render()`): `className` memakai kelas tw (bukan `badge badge-light border`), `style.cssText` dihapus; ikon hapus memakai kelas tw. `.email-tag` tetap.
2. `renderCaQuota()`: peta `cls` dan `box.className` memakai kelas tw (bukan `alert alert-*`).
3. `refreshWorkflowAlert()`: dua `className` memakai kelas tw.

## CSS legacy
- `<style>` halaman: dihapus `.font-weight-600` (tak dipakai lagi di view ini; definisi di view lain milik masing-masing). Dropzone: `transition: all` dihapus; latar hover/dragover `--warning-bg` diganti `--accent-tint`; warna teks `--text-dim`. Ditambah `select#selectType/#selectCluster.form-control{height:var(--ctl-h)}` (select native sebelumnya ~19px, jauh lebih pendek dari kotak lain).
- `tesys-ui-base.css` `.form-clean`/`.po-two-col`, `tesys-ui-mobile.css` §4/§8/§9: **tidak diubah** (masih dipakai view lain / hook yang dipertahankan; grep).

## Perubahan perilaku/visual yang kasatmata
- `#remainingBudget` kini bisa merah (inline `style.color` danger menang karena `text-success` BS `!important` diganti `tw:text-success`); sebelumnya selalu hijau walau sisa <= 0 (bug lama).
- Tombol hapus baris/lampiran tidak lagi bergaya `ts-icon-only`. Koreksi (M3): penyebab bukan JS yang menargetkan `.card`, melainkan pemilih `PEMILIH = ".table td .btn"` di `public/js/tesys-icon-buttons.js:27` (+ aturan `luxe.css:922`); kelas hilang karena tabel kini polos (bukan `.table`). Tetap tombol merah ikon; ditambah `title`/`aria-label` statis ("Hapus baris"/"Hapus lampiran") pada string JS.
- Id otomatis `ts-fld-*` pada Deskripsi/Notes diganti `description`/`notes` (+ `description-error` saat galat).

## Build dan uji
- `public/css/tesys-tw.css` **16.733 B** (anggaran 50 KB; gzip 3.672 B), SHA-256 `1638d61deb6238ce598e90dd2a1531ebc9698949dd884d0960f50b362b677035`, `-Cek` exit 0. Sebelum M2: 15.634 B `6e89716b…`. **Unggah ulang CSS dan naikkan versi aset.**
- `TailwindAssetTest`: view create diizinkan dan diperiksa persis 1x memuat tw (`@aset`). **Tes belum dijalankan** (gerbang CLAUDE.md §4.3 belum dipenuhi).
- Tanpa kelas `!` baru, hex baru, gradien, atau animasi.

## Verifikasi browser (Chrome headless CDP, `http://localhost:8099`, DB `tesys_f3_pilot`, akun `dummy.superadmin`)
Desktop 1440x900, mobile 390x844, gelap; pilih BP/Tipe/Beneficiary/Cluster, tambah/hapus baris, total, NOB (alert + badge), Cash Advance (alert workflow, SweetAlert non-Internal), tag email, lampiran item + drop zone (input file disintesis), Simpan disabled/enabled + SweetAlert "Form belum lengkap", tombol Template/Import/Tambah Baris tanpa BP (SweetAlert), submit nyata Cost Payment (redirect ke `/budget-request`; satu CR uji tertulis di DB uji), galat server per-field (old() BP/rincian/email tetap), konsol dan jaringan bersih, overflow mobile 0. Tidak diuji: Firefox/Safari, sentuh nyata, pembaca layar, unggah file lewat dialog OS, import Excel sungguhan.

---

# M3 — Redesain visual halaman edit Cost Request

Status: **diimplementasikan di working copy, belum di-commit, belum dideploy.** Hanya presentasi: tidak ada perubahan route, controller, model, permission, validasi, nama field, atau logika JS (hanya string kelas/atribut aksesibilitas pada elemen yang dibuat JS). Bukti: `docs/ui/tailwind-evidence/m3/` (`sebelum-*`/`sesudah-*` desktop 1440, mobile 390, gelap, SweetAlert, lampiran existing; `kontrak-dom-sebelum/sesudah.json`; `kontrak-diff.txt`).

## Yang dibuat Tailwind (`tw:`)
Sistem visual sama dengan create (M2): `h1`+breadcrumb di `tw:sr-only`; `x-tw.page-header` (judul "Edit Cost Request", deskripsi "Kode: …", aksi = chip `x-status` (context `budget-request`) + "Kembali ke daftar"); catatan revisi (manager/finance) = panel tw tonal warning; ringkasan galat server = blok tw `role=alert`; kartu Informasi, Rincian, Lampiran existing (global), Lampiran baru, aksi; kartu "Siap diajukan?" (`x-tw.card padding=sm`, `#btnSubmit` = `x-tw.button`; `form#frmSubmit` kini `hidden` di dalam kartu). Deskripsi = `x-tw.input` (+`tw:uppercase`, sama dengan create; nilai tersimpan memang di-`strtoupper` controller), Notes = `x-tw.textarea` (galat per field ikut tampil). Tabel lampiran existing: tabel polos, tombol pratinjau `x-tw.button href target=_blank` (aria-label per file), kotak centang hapus `tw:size-[18px] tw:accent-danger` + aria-label.

## Tetap legacy
`select.form-control` (Budget Plan, Tipe, Penerima, Project), kontrol baris rincian dan lampiran per item yang dibangun JS, SweetAlert, drop zone `.att-dropzone`, label `del-detail-att` (lampiran item existing dari JS).

## Perubahan JS (hanya string)
Tag email, `renderCaQuota()`, `refreshWorkflowAlert()` memakai kelas tw (sama dengan create); tombol hapus baris/lampiran diberi `title`+`aria-label`; toggle badge Wajib memakai `''` (bukan `inline-block`); tautan `#dropZoneBrowseCR` mendapat `tabindex=0` + handler keydown Enter/Spasi.

## Perbaikan LOW dari review M2 (create + edit)
- LOW-1: `#dropZoneBrowseCR` `role=button` kini `tabindex=0` + Enter/Spasi membuka pemilih file.
- LOW-2: koreksi catatan `ts-icon-only` (lihat M2 di atas) + `title`/`aria-label` statis pada tombol hapus buatan JS.
- LOW-4: badge Wajib tidak lagi dipaksa `inline-block` (kini mengikuti `tw:inline-flex`; pada kepala flex terhitung `flex`).
- LOW-5: `#saveHint` `role=status` -> `aria-live="off"` (teks "Lengkapi: …" berubah tiap ketikan; tetap dibaca lewat SweetAlert saat kirim).
- LOW-3 (UAT mode gelap): dicek ringan untuk create/edit lewat tangkapan layar gelap edit (kontras terbaca, tombol hapus baris tampak pucat = gaya legacy); belum UAT manusia.

## Perubahan perilaku/visual yang kasatmata
- Status di kepala kini chip `x-status` (label dari `WorkflowStatus`, mis. "Draft") menggantikan `badge badge-{status_color}` dengan kode mentah.
- Deskripsi tampil kapital saat mengetik (`tw:uppercase`), sesuai create; data tersimpan tidak berubah.
- Id otomatis `ts-fld-*` pada Deskripsi/Notes menjadi `description`/`notes`.
- Tombol hapus baris tak lagi `ts-icon-only` (sama dengan create).

## Temuan di luar cakupan (tidak diubah)
- `recalc()` di edit tidak memanggil `checkNOB()` (create memanggil), sehingga `#nobWarning` tidak pernah tampil di edit; badge NOB per baris tetap jalan. Perilaku lama.
- Tautan pratinjau lampiran global memakai `asset('storage/'.$att->path)` (kebijakan: lampiran privat via `SecureFileController`); perilaku lama, perlu konfirmasi.
- Catatan revisi (`manager_revised`/`finance_revised`) tidak teruji di DB uji (tidak ada CR dengan log tersebut); hanya markup yang ditinjau.

## Build dan uji
- `public/css/tesys-tw.css` **16.951 B** (anggaran 50 KB; gzip 3.715 B), SHA-256 `2c024e1ceda8252800e2fb88fb6c94f4458a6fd144e25d073a3f777034e0c0ae`, `-Cek` exit 0. Sebelum M3: 16.733 B `1638d61d…`. **Unggah ulang CSS dan naikkan versi aset.**
- `TailwindAssetTest`: edit diizinkan memuat tw persis 1x (`@aset`); asersi lain tidak diubah. **Tes belum dijalankan** (gerbang CLAUDE.md §4.3 belum dipenuhi; `bootstrap/cache/config.php` ada).
- Tanpa kelas `!` baru, hex baru, gradien, atau animasi.

## Verifikasi browser (Chrome headless CDP, `http://localhost:8099`, DB `tesys_f3_pilot`, `dummy.superadmin`)
Edit CR 261 (CP, 2 baris + lampiran item) dan 244 (4 baris): 1440x900, 390x844 (overflow 0), gelap; ganti ke Cash Advance (alert workflow, badge Wajib), tambah baris, NOB per baris, centang hapus lampiran item, tag email, SweetAlert form belum lengkap dan konfirmasi Submit (tidak dikonfirmasi), simpan draft nyata dengan lampiran global + Notes (redirect `/budget-request`, data tersimpan, kartu lampiran existing tampil), konsol dan jaringan bersih. Kontrak DOM (nama field, nilai, id, form) identik kecuali daftar di `kontrak-diff.txt`. Tidak diuji: Firefox/Safari, sentuh nyata, pembaca layar, hapus lampiran global lewat simpan, Submit ke Manager yang dikonfirmasi, import Excel, CR bercatatan revisi, peran selain superadmin.

---

# M4 — Redesain visual halaman Submit Settlement (realize)

Status: **diimplementasikan di working copy, belum di-commit, belum dideploy.** Hanya presentasi: tidak ada perubahan route, controller, model, permission, validasi, nama field, perhitungan, `<template id="realizeRowTpl">`, `<script id="realizeSubItems">`, maupun logika JS (hanya string kelas/atribut aksesibilitas). Bukti: `docs/ui/tailwind-evidence/m4/` (`sebelum-*`/`sesudah-*` desktop 1440, mobile 390, gelap, pop-up SweetAlert; `hasil-sebelum.json`/`hasil-sesudah.json`; `kontrak-diff.txt`). Rute halaman: `/budget-request/{id}/realize-form`.

## Yang dibuat Tailwind (`tw:`)
Sistem visual sama dengan create/edit: `h1`+breadcrumb di `tw:sr-only`; `x-tw.page-header` (judul, "Kode: …", "Kembali ke daftar"); alasan reject = panel tw `role=alert`; kartu ringkasan Request (`x-tw.badge` kode + `x-status` + deskripsi + tiga ubin Total Request / Total Settle (`#statTotalSettle`) / Variance (`#statVariance`)); kartu Settlement per Item (header: Download Template, Import Excel, Tambah Baris), Bukti Settlement (Global), Keterangan (`x-tw.textarea`), footer aksi (hint `#submitHint`, Cancel, Simpan `#saveDraftBtn[data-action]`, Submit Settlement `#submitBtn` di kanan). Tabel `#tblSettlement` polos (tanpa `.table*`), lebar minimum 1180px (gulir horizontal di mobile). Baris (`_realize_row`): tombol Lampiran/Note/Pecah/Hapus = `x-tw.button`, badge asal = `x-tw.badge`, label/peringatan = utilitas tw.

## Tetap legacy
`select.form-control` Sub COA (`TesysSearchableSelect`), input deskripsi/nominal `form-control-sm`, tombol `desc-expand`, ikon `att-lama-del`, drop zone `.att-dropzone`, SweetAlert, kelas hook JS (`realize-*`, `btn-note-item`, `btn-split-row`, `btn-remove-row`, `btn-detail-att`, `detail-att-*`, `att-lama*`, `text-muted`/`text-danger` pada penghitung karakter yang ditoggle JS).

## Perubahan JS (hanya string/atribut, 4 perubahan)
1. `updateSettleSubmitState()`: dua `toggleClass('btn-primary'/'btn-secondary')` pada `#submitBtn` dihapus (tombol tw tak boleh bercampur kelas `.btn`); status tetap lewat `disabled` (tombol redup saat belum lengkap, aktif saat siap).
2. Tombol hapus baris lampiran global (string JS) diberi `title`/`aria-label`.
3. `#dropZoneBrowseReal` (`role=button`, `tabindex=0`) mendapat handler Enter/Spasi (sama dengan create/edit).
4. `addAttachmentRow`: tombol `.btn-remove-real-att` ditambah `title`, `aria-label="Hapus lampiran"`, dan `aria-hidden` pada ikon (hanya atribut).

## Sisa legacy yang sengaja dibiarkan
- Tombol `.btn` hasil render JS di pop-up SweetAlert dan tombol `.btn-remove-real-att` (string JS) tetap kelas Bootstrap.
- `<small>` di pop-up SweetAlert tidak diubah.

## Catatan urutan footer (perlu konfirmasi pengguna)
Urutan DOM footer: Cancel, Simpan, Submit. Di mobile (`flex-col-reverse`) urutan visual berbeda dari urutan fokus Tab (terverifikasi di 390px: fokus Cancel lebih dulu, lalu Simpan yang tampil di atasnya; Submit tidak terfokus saat `disabled`). Dianggap sengaja/diterima; **perlu konfirmasi pengguna**.

## Perubahan visual/kontrak yang kasatmata
- `#submitBtn`: abu-abu ↔ merah diganti redup ↔ aksen; urutan DOM footer: Cancel, Simpan, Submit (sebelumnya Submit, Simpan, Cancel).
- Nominal Request di tabel tidak lagi biru (`text-primary`), tiga ringkasan tidak lagi berwarna (Variance tetap hijau/merah lewat JS).
- Penghitung karakter `small` -> `div`; `#submitHint` `small` -> `span`; id baru `settlementNotes-hint`; tombol aksi tak lagi `ts-icon-only`.
- Kartu Bukti Global tetap kosong sebelum "Tambah Lampiran" diklik (perilaku lama).
- `.summary-stat*`, `.card-header .card-title`, aturan `#tblSettlement th`, `.realize-origin-label*`, `.note-item-preview`, `.detail-att-hint` di `<style>` halaman dihapus (tak dipakai lagi).

## Build dan uji
- `public/css/tesys-tw.css` **17.311 B** (anggaran 50 KB), SHA-256 `4e79d24d7f783e33dbe3efd4e9e1092a841bbadbf01b4511c5aa65299d998ebd`, `-Cek` OK. Sebelum M4: 16.951 B `2c024e1c…`. **Unggah ulang CSS dan naikkan versi aset.**
- `TailwindAssetTest`: `budget-request/realize` diizinkan memuat tw persis 1x (`@aset`), `_realize_row` 0x; asersi lain tidak diubah. **Tes belum dijalankan** (gerbang CLAUDE.md §4.3 belum dipenuhi; `bootstrap/cache/config.php` ada). Tanpa kelas `!` baru, hex baru, gradien, animasi.

## Verifikasi browser (Chrome headless CDP, `http://localhost:8099`, DB uji `tesys_f3_pilot`, `dummy.superadmin`)
CR 217 (Cash Advance PAID, 4 item): skenario identik sebelum vs sesudah (ketik nominal, nominal melebihi/nol/1, pindah Sub COA, pecah, tambah, hapus lewat konfirmasi, pop-up Note + validasi wajib, pop-up deskripsi, lampiran per baris, bukti global, keterangan, validasi submit): **15 keadaan kalkulasi (`#statTotalSettle`, `#statVariance`, subtotal, kolom Request/Variance per baris, peringatan budget, status Submit) identik persis**; data FormData identik. Simpan Draft nyata (redirect `/budget-request`, flash sukses): baris, Sub COA, nominal, change_note, lampiran, bukti global tersimpan identik sebelum/sesudah (hanya id auto-increment beda). Muat ulang: lampiran lama tampil, tandai-hapus/batal, pratinjau. Submit nyata di CR 205 (status -> REALIZED, 2 baris tersimpan, log `realization_submitted`). Gelap, mobile 390 (overflow 0), konsol/jaringan bersih. Judul `h1` "Submit Settlement" dan breadcrumb "Finance / Cost Request / Settlement" tampil di topbar (`#tsTitle`/`#tsCrumb`, hoist `tesys-shell.js`) pada 1440 dan 390; tidak tersisa `h1`/`.breadcrumb` di konten. Data uji CR 217 direset; CR 205 tetap REALIZED di DB uji.
Tidak diuji: Firefox/Safari, sentuh nyata, pembaca layar, Import Excel sungguhan, CR SETTLE_REJECTED (panel alasan reject hanya ditinjau markup-nya), peran selain superadmin.

## Temuan di luar cakupan (tidak diubah)
- Server (`validateSettlementEvidence`) mewajibkan tanda tangan (`signatures[i]`) bagi baris yang Settle-nya berselisih dari Request atau baris tambahan bernominal, tetapi halaman ini tidak punya input tanda tangan: Simpan/Submit dengan selisih ditolak ("E-sign belum diisi"). Hanya Settle = Request per item yang bisa disimpan lewat UI ini. Perlu konfirmasi (perilaku lama). **UNKNOWN / NEEDS CONFIRMATION**; di luar M4, HIGH (workflow/validasi settlement, `BudgetRequestController.php:3212`), tidak diperbaiki.
- Tautan lampiran memakai `asset('storage/'.$att->path)` (realize `:181` lama, `_realize_row:92` lama): bertentangan dengan kebijakan lampiran privat; perilaku lama.

---

# M5a — Redesain tabel Index Cost Request (desktop >= 641px)

Status: **diimplementasikan di working copy, belum di-commit, belum dideploy.** Hanya presentasi: tidak ada perubahan route, controller, model, permission, query, perhitungan, DOM kontrak, maupun logika JS. **Mode kartu mobile (<= 640px) tidak disentuh oleh M5a; dikerjakan terpisah pada M5b (bagian di bawah).** Bukti: `docs/ui/tailwind-evidence/m5/` (`sebelum-*`/`sesudah-*`; `hasil-luapan.json`, `hasil-fungsional.json`, `kontrak-dom-dan-ukur.txt`).

## Pendekatan
Satu string `$gayaTabel` (31 kelas, semuanya varian lebar `tw:min-sm:`; tercatat 30 saat M5a awal, bertambah dengan penjaga lebar Description) pada `<table>` di `budget-request/_table.blade.php`: varian turunan (`[&_td…]`, `[&_thead_th…]`), tanpa kelas per sel (tabel sampai 1.000 baris). Kelas hook (`cr-tbl`, `cr-col-code/-desc/-check`, `action-column`, `cr-check-all/-row`, `cr-foot-*`, `row-nob*`, `data-*`, `col-raw-*`) tidak diubah. Tanpa `important` global; tanpa hex/rgba/gradien/animasi baru.

| Bagian | Cara |
|---|---|
| th / td | **Tidak diberi kelas tw**: `refine.css` §8 sudah memberi semua `table.dataTable` tipografi dan jarak berbasis token (th 12px/600/tanpa kapital/redam/latar `surface-2` `!important`; td jarak vertikal `--cell-y`). Menyalin nilai itu menjadi kelas `!` terukur mubazir (7 kelas dicoba dan dibuang). Satu-satunya penyimpangan: jarak horizontal sel **8px** (`px-2!`/`pl-2!`, token `--sp-2`, bukan `--cell-x` 12px) supaya 11 kolom + 2 beku muat di 1440px; ruang panah urut th (padding kanan 20px, `refine.css` §14d) dipertahankan. |
| Description | `whitespace-normal`, `min-w-[9rem]` (lantai), `min-w-[17rem]` hanya di jendela >= 1440 px (`min-[1440px]:`), `max-w-[20rem]` (sel lain satu baris lewat `whitespace-nowrap`), plus `[overflow-wrap:anywhere]` (tanpa `!`) supaya teks tanpa spasi patah di batas lebar, bukan menimpa kolom tetangga. Dapat membungkus. Terukur 1440 (CA): `X` x120 tanpa spasi -> lebar sel tetap 292 px, `scrollWidth` = `clientWidth`, tumpang tindih dengan kolom sebelah 0, tinggi baris 54 -> 95 px; saat M5a, mobile 390 tak berubah oleh kelas ini (kartu mobile baru dikerjakan pada M5b). |
| tfoot | angka `text-data!` + `text-text!` (legacy §8: 12px redam); latar, garis atas dan label tetap legacy. Karena aturan `.cr-foot-row th` di `refine.css` dicabut, tipografi tfoot kini **bobot legacy `shell.css` (700, angka dan label)** dan padding horizontal footer **12px (`--cell-x`)**, bukan 8px seperti td. |
| Kolom beku | centang (kiri, 36px), Code (kiri, bergeser 36px bila ada centang), Action (kanan): `sticky` + `left/right` + `z-3` (th `z-4`); th `sticky!` karena `.sorting` DataTables memasang `position:relative`. **Bayangan gelap diganti garis token 1px** (`shadow-[1px_0_0_var(--border)]`). |
| Baris NOB | penanda tepi kiri `shadow-[inset_3px_0_0_var(--danger \| --warning)]` (tanpa `!`, terukur); teks sel kini netral (aturan lama merah/kuning dihapus di >= 641px; di mobile: penanda tepi kiri kartu dari M5b, bukan teks merah). |
| Hover, baris tercentang | tidak dipindah: `datatable.css` (`!important`) sudah memenangkan keduanya atas aturan lama di `refine.css`/`index`; nilai terhitung hover dan baris tercentang identik sebelum/sesudah. Aturan lama yang mati itu dihapus (>= 641px). |
| Nominal nol surut | tetap `refine.css` §14d (selektor `td[data-raw="0"]` perlu tanda kutip di dalam nama kelas tw, tidak aman bagi pemindai Tailwind/`TailwindAssetTest`). Akibat kecil: pada baris NOB nol kini ikut surut (teks NOB-merah lama menimpanya). |

## Legacy yang dihapus (hanya untuk >= 641px)
- `refine.css` §14d: aturan th/td (padding, font, warna, garis), garis beku, NOB, baris tercentang (mati), footer total. **Dipertahankan**: reset bayangan tabel, `.table-bordered` (garis), panah urut th (§14d), hierarki `.cr-number-btn/.cr-cell-link`, nominal nol; §14e (aksi baris) dan §14f (paginasi) tidak disentuh.
- `index.blade.php` `<style>`: `.row-nob td`, latar/hover baris, `.cr-table-wrap .cr-tbl`, seluruh blok `.cr-hscroll`/beku/centang, `.cr-tbl{width:100% !important}`, `.action-column`. Pada M5a semuanya sempat disalin ke `@media (max-width:640px)`; salinan itu sudah dihapus pada M5b (kini tidak ada `@media (max-width:640px)` terkait tabel ini di `index.blade.php`; kartu = `$gayaKartu`).
- Kelas `table table-bordered` pada `<table>` **masih dipertahankan** (M5a dan M5b): pada M5a pencabutannya mengubah mode kartu lama (tinggi tabel +10px, border sel kartu, teks NOB tidak merah, z-index sticky; bukti `kontrak-dom-dan-ukur.txt`). Setelah M5b mode kartu lama sudah diganti, tetapi pencabutan kelas ini **belum diuji ulang**; jangan dicabut tanpa pengukuran 390 px dan >= 641 px (aturan `refine.css` §14d untuk `.cr-tbl.table-bordered` juga bergantung padanya).

## Perbaikan Status terpotong di balik Action (diukur, px CSS; "tertutup" = px visual)
Penyebab: tabel lebih lebar dari wadah; Status di sebelah kiri Action beku sehingga pada gulir 0 Status tertutup. Pada gulir maksimum Status tidak pernah tertutup (sebelum dan sesudah: 0,0-0,8 px), jadi **ruang cadangan kanan (padding-right wadah + scroll-padding-right) tidak diperlukan dan tidak dibuat**; cukup menyempitkan tabel (Description membungkus + jarak sel 8px).

| Tab / lebar | luapan tabel sebelum -> sesudah | Status tertutup @gulir 0 sebelum -> sesudah |
|---|---|---|
| CA 1440 | 102 -> **0** | 81,3 -> **0** |
| CP 1440 | 0 -> 0 | 0 -> 0 |
| DB 1440 (tanpa Action) | 0 -> 0 | n/a |
| CA 1280 | 302 -> 196 | 241,3 -> 157,0 |
| CP 1280 | 103 -> **0** | 82,7 -> **0** |
| DB 1280 | 175 -> 92 | n/a |
| CA / CP 1024 | 622 / 423 -> 516 / 302 | 497,3 / 338,7 -> 413,0 / 241,8 |
| CA / CP 768 | 890 / 692 -> 785 / 571 | 889,8 / 691,6 -> 785,0 / 571,1 |

**Catatan zoom:** `html` memakai `zoom: .8` pada lebar jendela >= 901px, jadi lebar efektif tata letak = lebar jendela / 0,8 (jendela 1366 -> 1.707 px CSS; 1440 -> 1.800). Angka "lebar" di tabel ini adalah lebar jendela.

Ukuran tambahan jendela 1366 dan 1536 (px visual, `scrollLeft` 0; sebelum = cadangan `br-backup-m5` dipasang sementara, sesudah = working copy dengan `overflow-wrap`; 10 baris per tab):

| Tab / lebar | scrollWidth vs clientWidth sebelum -> sesudah | Status tertutup @gulir 0 sebelum -> sesudah (baris tertutup) | Status tertutup @gulir maks |
|---|---|---|---|
| CA 1366 | 1385 vs 1350 -> 1438 vs 1350 | 0 -> **70,98** (0 -> 10 baris) | 0 -> 0 |
| CP 1366 | 1350 vs 1350 -> 1350 vs 1350 | 0 -> 0 | 0 -> 0 |
| DB 1366 (tanpa Action) | 1350 vs 1350 -> sama | n/a | n/a |
| CA / CP / DB 1536 | 1562 vs 1562 (tanpa luapan) -> sama | 0 -> 0 | 0 -> 0 |

**Perbaikan M5a (lanjutan):** `min-w-[17rem]` Description hanya berlaku di jendela >= 1440 px (varian `tw:min-sm:min-[1440px]:`; media query membaca lebar jendela, bukan lebar efektif setelah zoom .8), di bawahnya lantai `min-w-[9rem]`. Tanpa `!`; himpunan `!` tetap 6. Terukur (px visual, 10 baris, Status tertutup Action pada gulir 0 / gulir maksimum):

| Tab-lebar | Sebelum perbaikan (tertutup 0 / maks; scrollW; Desc; tinggi median) | Sesudah (sama) |
|---|---|---|
| CA 1280 | 156,98 / 0; 1438; 230; 56,55 | 54,58 / 0,58; 1310; 128; 81,53 |
| CA 1366 | 70,98 / 0; 1438; 230; 56,55 | **0 / 0**; 1350; 159; 68,53 (sebelum M5a: 0; 1385; 148; 68,53) |
| CA 1440 | 0 / 0; 1442; 233; 56,55 | sama persis |
| CA 1536 | 0 / 0; 1562; 329; 43,55 | sama persis |
| CP 1280 / 1366 / 1440 / 1536 | 0 / 0 | sama persis (Desc 245 / 331 / 405 / 501) |
| DB 1280 / 1366 / 1440 / 1536 | scrollW 1334 / 1350 / 1442 / 1562 | 1242 / 1350 / 1442 / 1562 |

CA 1280 tetap meluap sedikit (Status tertutup 54,58 px pada gulir 0; 0,58 pada gulir maksimum) karena isi minimum 11 kolom + 2 beku melebihi 1242 px; tanpa lantai 9rem hasilnya lebih buruk (Description 78 px, tinggi baris median 119 px, maksimum 405 px). Teks 120 karakter tanpa spasi tidak menimpa kolom sebelah di 1280/1366/1440 (tumpang tindih 0). (Pengukuran mobile saat M5a: identik; sejak M5b mobile berubah sengaja.)

Harga yang dibayar: tinggi baris median (px visual) CA 1440 **41,95 -> 56,55** (+35%; deskripsi 2 baris + metadata), CP 1440 46,9 -> 48,5; pada 641-768 px 52-58 -> 70,5. Wadah dan perilaku DataTables tidak berubah.

## Tampilan yang kasatmata berubah
- th/td lebih lega vertikal (td 9px, th 10px; sebelumnya 8px/9px), th 12px (sebelumnya 11,5px), jarak horizontal sel 8px (sebelumnya 10px); ukuran font td tetap 13px.
- Description membungkus; garis beku tipis menggantikan bayangan gelap; baris NOB: hanya penanda tepi + teks deskripsi merah dari markup (sel lain netral); footer: angka 12,5px `--text`.

## Build dan uji
- `public/css/tesys-tw.css` **31.758 B** (anggaran 50 KB; sudah termasuk M5a, M5b, perbaikan lebar Description, dan perbaikan nama panjang M-3; saat M5a murni 21.129 B `da7d6568…`; sebelum perbaikan M-3 31.658 B `be65b294…`), SHA-256 `1b3b552cfe673e30016f40244ddc93517c31732e82aac82cc44168c46eaad30d`, `-Cek` exit 0 (deterministik). Sebelum M5a: 17.311 B `4e79d24d…`. **Unggah ulang CSS dan naikkan versi aset.** Kelas `!` baru: **12 total = 6 M5a + 6 M5b** (anggaran 14), daftar + alasan terukur di [`tailwind-components.md`](tailwind-components.md).
- `TailwindAssetTest`: tes baru `test_tabel_cost_request_m5a_*` (semua kelas `$gayaTabel` varian `min-sm:`, himpunan `!` persis 6, hook ada); penjaga lama (tw dimuat persis 1x di index, 0x di partial) tidak berubah. **Tes belum dijalankan** (gerbang CLAUDE.md §4.3 belum dipenuhi; `bootstrap/cache/config.php` ada). Logika pencocokan token diperiksa dengan salinan skrip PHP di luar PHPUnit: token `$gayaTabel` (kini 31) semuanya ada di artefak; tes M5a ditambah 3 penjaga lebar minimum Description (17rem hanya `min-[1440px]:`, lantai 9rem).

## Isu terbuka pra-ada (bukan bagian M5a, tidak diperbaiki)
`TailwindAssetTest::test_kelas_tw_di_komponen_dan_js_ada_di_artefak_produksi` diperkirakan **merah** oleh 3 token di view Feedback, yang tidak ada di artefak produksi: `tw:mt-0.5` (`feedback/create.blade.php:86,108`, `feedback/inbox.blade.php:25`), `tw:` yang tertangkap pemindai dari komentar `_disclaimer.blade.php:5` ("prefix tw:"), dan `tw:after:content-['']` (`feedback/create.blade.php:110`). Belum diverifikasi dengan menjalankan tes (tes tidak dijalankan). Opsi perbaikan: (a) ganti `tw:mt-0.5` dengan kelas yang ada di artefak (mis. `tw:mt-1`) atau tambahkan ke sumber CSS lalu build ulang; (b) ubah teks komentar `_disclaimer` supaya tidak berisi `tw:` mentah; (c) ganti `after:content-['']` dengan elemen/utility yang aman bagi pemindai (tanda kutip di dalam nama kelas tidak aman, lihat catatan nominal nol); atau (d) perketat pemindai tes. Perubahan ini menyentuh pilot Feedback, jadi diputuskan terpisah.

## Verifikasi browser (Chrome headless CDP, `http://localhost:8099`, DB `tesys_f3_pilot`, `dummy.superadmin`; peran lain lewat View As)
- Skenario fungsional identik sebelum vs sesudah (berkas cadangan dipasang sementara untuk "sebelum"): 3 tab, cari (+ keadaan kosong), filter kolom Status, toggle kolom, sortir tiap kolom (asc dan desc), pilih-semua + dialog bulk Approve (ditutup Escape, tidak dikonfirmasi, tanpa permintaan POST), centang satu baris, menu aksi `.ts-action-more`, hover, total tfoot setelah cari/filter/ganti tab, Export (permintaan 200), View As head/manager/finance/guest: **233 nilai, selisih 0**. Konsol dan jaringan bersih (0 galat).
- Kontrak DOM: 1.842 nilai, selisih 0. Mobile 390/640 (hanya berlaku untuk M5a, sebelum M5b): gaya terhitung identik, tangkapan 390px byte identik. Mode gelap 1440 dan lebar 1280/1024/768 diperiksa (tangkapan + angka luapan).
- Tidak diuji: Firefox/Safari, perangkat sentuh nyata, pembaca layar, aksi POST bulk yang dikonfirmasi, baris NOB sub-item dengan data nyata (diuji sintetis), tabel > 1.000 baris.

---

# M5b — Kartu mobile tabel Index Cost Request (<= 640px)

Status: **diimplementasikan di working copy, belum di-commit, belum dideploy; rilis hanya setelah UAT.** M5b diterima pengguna dan dikerjakan **di luar alur review awal** (setelah M5a); review M5a+M5b menghasilkan APPROVE WITH CHANGES, tindak lanjutnya tercatat di bawah. Hanya presentasi: tanpa perubahan route/controller/model/permission/query/perhitungan/logika JS; `tesys-table.js`, `datatable.css`, `mobile.css`, §14e/§14f tidak diubah.

## Pendekatan
- Kartu lama (`tesys-table.js` + `datatable.css`) **dimatikan** dengan `data-ts-card="off"` pada `<table>` (didukung `tesys-table.js`: tabel di dalam `[data-ts-card="off"]` tidak diberi `.ts-card-table`). Susunannya ditiru satu string `$gayaKartu` (84 kelas: 80 varian `tw:max-sm:` + 4 `tw:min-sm:` pemulih) di `_table.blade.php`; varian turunan pada `<table>`, bukan kelas per sel.
- **Label field ditulis server** (`data-lbl="..."` pada `<td>` di `_rows`/`_rows_db`, dan pada 6 `<th>` tfoot), bukan `data-label`: `tesys-table.js` menghapus `data-label` sel tbody pada tabel `off`. Label dibaca `::before` (`content-[attr(data-lbl)]`). Status memakai `data-card="status"` (kanan atas kartu).
- Variabel `--crk-p` (padding), `--crk-b` (border-width), `--crk-z`, `--crk-sh`: sel-sel tertentu menimpa nilainya tanpa `!`, sehingga cukup **satu `!` per properti** pada aturan induk.
- Susunan kartu: judul = Code; status kanan atas; centang kiri atas (kotak 20px); aksi di dasar kartu (zoom 44/36 untuk target sentuh 44px); Description = kisi label | isi; tfoot = blok ringkasan berlabel di atas daftar (teks tetap diisi `recalcFooter`); baris NOB = penanda tepi kiri kartu; pesan kosong (`td[colspan]`) bukan kartu.
- `thead` dijadikan `sr-only` (tetap ada untuk pembaca layar/pengurut DataTables).

## Kelas `!` M5b (6; total 12 = 6 M5a + 6 M5b, anggaran 14)
| Kelas | Alasan |
|---|---|
| `max-sm:[&>:is(tbody,tfoot)>tr>*]:[padding:var(--crk-p,6px_0)]!` | `refine.css` §8 `table.dataTable tbody td{padding...!important}` dan padding th: terukur di 390px: tanpa `!` 126 elemen (CA) dan 104 (CP) berbeda (mis. `6px 0 -> 9px 12px`) |
| `max-sm:[&>:is(tbody,tfoot)>tr>*]:[border-width:var(--crk-b,0_0_1px)]!` | aturan legacy tak berlayer (`.table-bordered`/DataTables) mengalahkan utilitas; terukur 33 sel berbeda di CA tanpa `!` (mis. `0 -> 0 0 1px 0`, `0 -> 1px 0 1px 0`) |
| `max-sm:[&_td.cr-col-check]:shadow-none!` | `datatable.css` memberi baris tercentang bayangan aksen inset; kartu lama juga tanpa; terukur: tanpa `!` bayangan aksen + garis 1px muncul pada sel centang |
| `max-sm:[&_th[data-lbl]]:text-text!` | `refine.css` §8 `body.tesys-ui .table tfoot th{color:var(--text-dim)}`: angka ringkasan harus `--text` (terukur `rgb(90,79,81)` vs `rgb(37,24,27)` pada 3 elemen) |
| `min-sm:[...first-child...]:[z-index:var(--crk-z,3)]!` | pemulih M5a: dengan `data-ts-card="off"` aturan "kolom pertama beku" `datatable.css` §1b (`table.dataTable:not(.ts-card-table)`, tanpa layer) ikut berlaku di >= 641px dan menimpa z-index sel pertama dari `$gayaTabel` |
| `min-sm:[...:not(:has(:checked))...]:[box-shadow:var(--crk-sh,none)]!` | pemulih M5a: aturan yang sama menimpa bayangan sel pertama (garis beku/penanda NOB); baris tercentang dikecualikan agar penanda aksen `datatable.css` tetap menang |

Dua `!` terakhir (min-sm) tidak punya berkas ukur per-kelas sendiri; pembenarannya adalah selisih desktop **0** terhadap M5a (`desktop-1440-1280-selisih.txt`, CA/CP/DB di 1440 dan 1280). Empat kelas `min-sm` pemulih: dua `!` di atas + `thead>th:[--crk-z:4]` + dua penetap `--crk-sh`. `TailwindAssetTest::test_kartu_mobile_cost_request_m5b_*` menjaga himpunan `!` persis dan varian sempit.

## Bukti (`docs/ui/tailwind-evidence/m5b/`)
- `hasil-uji-bang-max-sm.json` (4 `!` max-sm), `hasil-uji-kelas-s2.json` (efek tiap kelas `$gayaKartu`), `hasil-fungsional-390.json` + `sesudah-fn-*.png` (cari, keadaan kosong, filter Status, toggle kolom PIC, centang + dialog bulk Approve ditutup Escape tanpa POST, menu aksi, kartu NOB), `sebelum-*`/`sesudah-*` 390/640 termasuk mode gelap, `desktop-1440-1280-selisih.txt` (selisih desktop 0).

## Batas yang tidak diuji
Firefox/Safari, perangkat sentuh nyata, pembaca layar, aksi POST bulk yang dikonfirmasi, tabel > 1.000 baris, baris NOB sub-item dengan data nyata, lebar 641-900px (mode kartu berhenti di 640px), pencabutan `table table-bordered` (lihat M5a).

## Tindak lanjut review M5a+M5b
- **H-1 (selesai)** `CostRequestBulkReviewTest` memakai `assertStringNotContainsString('cr-check-row', ...)` sedangkan kelas itu kini muncul di atribut `class` `<table>` (`[&_.cr-check-row]:size-5`): asersi diperketat menjadi `class="cr-check-row"` (bentuk persis di `_rows.blade.php`). Asersi lain di `tests/` diperiksa: hook `cr-check-all`, `cr-col-check` (sudah penanda `<th>` spesifik), `ts-action-more` tidak terkena; `cr-col-code`/`cr-foot-*`/`action-column` hanya dipakai `assertStringContainsString` (tetap benar). **Tes belum dijalankan** (gerbang CLAUDE.md §4.3).
- **M-2 (tidak terbukti)** kolom "No" sticky bersama Code: di semua tab kolom No disembunyikan DataTables (`columnDefs visible:false`, `index.blade.php:947`) sehingga tidak ada di DOM; sel sticky pertama = Code (atau centang). Tanpa tumpang tindih (View As manager/supervisor di DB uji tidak memiliki baris; diukur lewat simulasi tab CA tanpa kolom centang dan tab DB, 1280/1440). Bukti `m5b/m2/`.
- **M-3 (cacat terbukti, diperbaiki)** nama 28 karakter di sel PIC/Requestor kartu 390px meluap (`scrollWidth` 322/367 vs `clientWidth` 261) dan memicu gulir horizontal di `.cr-hscroll` (410 vs 317), karena `text-nowrap` BS (`!important`) menang. Perbaikan tanpa `!`/tanpa JS: `text-nowrap` dilepas dari `<td>` Requestor dan PIC (`_rows`, `_rows_db`; >= 641px tetap satu baris lewat `whitespace-nowrap` `$gayaTabel`) + satu kelas `max-sm:[&_td[data-lbl]]:[overflow-wrap:anywhere]`. Sesudah: tanpa luapan, wrapper 317 vs 317, tinggi sel 33 -> 52px; desktop 1280/1440 tak berubah (scrollWidth sama). Sel Tanggal/Tanggal Paid tetap `text-nowrap` (format tanggal tetap pendek). Bukti `m5b/m3/`.
- **L-1 (dicatat, belum dikerjakan)** padding/border `td[colspan]` pesan kosong (6px 0, tanpa pembanding visual lama). Perlu keputusan desain.
- **L-2, L-3, L-5 (isu terbuka / UAT / potential future improvement)**: tidak dikerjakan di putaran ini; `mobile.css` §11 ("tabel Cost Request, hanya Action yang beku di HP") kini hampir yatim karena kartu baru tidak memakai `.ts-card-table`; rapikan setelah UAT. Rincian L-2/L-3/L-5 ada di laporan review (bukan di repositori; isi tidak diverifikasi di sini). M-1 = dokumentasi ini.
