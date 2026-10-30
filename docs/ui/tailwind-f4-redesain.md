# F4 — Redesain visual Feedback (F4-a: komponen + index; F4-b: create, show, inbox)

Status: **F4-a dan F4-b diimplementasikan di working copy, belum di-commit, belum dideploy.**
Bukti: `docs/ui/tailwind-evidence/f4/` (`sebelum/`, `sesudah/`, `perbandingan/`, `hasil-f4-*.json`).

## Arah desain
Terang, border halus + bayangan sangat ringan, hierarki jelas (eyebrow > judul > deskripsi), ubin ringkas, kartu tabel dengan pill berdot.
Aturan CLAUDE.md §9 tetap: tanpa gradien/animasi dekoratif, hanya token proyek, chrome tidak diubah.

## Token
- Dipakai: token yang sudah dipetakan (`text-kpi`, `text-caption`, `rounded-md`, `rounded-pill`, `border-border`, dst.).
- **Baru (1):** `--text-title: calc(var(--fs-page) * 1.15)` di `resources/tailwind/tesys-tw.css` (judul kepala halaman ~20,7px; tak ada token antara `--fs-page` 18px dan `--fs-kpi` 24px). Tanpa hex baru.

## Komponen baru / diperluas (aditif)
| Komponen | API |
|---|---|
| `x-tw.page-header` | props `eyebrow`, `title`, `description`; slot `actions`. Judul = `div role="heading" aria-level="2"` (BUKAN `h1`: shell memindahkan `h1` ke topbar). Halaman menulis `h1`+breadcrumb sendiri di wadah `tw:sr-only`. |
| `x-tw.stat` | props `label`, `value`, `tone` (`accent` + 6 nada badge), `hint`. Tidak menghitung apa pun. |
| `x-tw.badge` | prop baru `dot` (default `false`; keluaran tanpa `dot` tidak berubah). Titik `bg-current`, `aria-hidden`. |
| `x-tw.textarea` (F4-b) | API seperti `x-tw.input` tanpa `type`, plus `rows` (4); `value` = isi (ter-escape); `error`/`hint`/`required`/`disabled`; wadah `.form-group`; tanpa `!` baru. Detail: `tailwind-components.md`. |
| `x-tw.table` | prop baru `caps` (default `false`): header KAPITAL; melepas `normal-case!`/`tracking-micro!` dan membiarkan nilai legacy th. Tidak ada kelas `!` baru. Urutan kelas default berubah, himpunannya sama. |

## Aturan pakai
Judul halaman: tulis `h1` di wadah `tw:sr-only` (untuk topbar) + `x-tw.page-header` untuk kepala visual. Angka ubin dihitung di Blade dari data yang sudah ada.
Mobile ≤640px tetap memakai mode kartu legacy `tesys-table.js` (th/td milik legacy; lihat konflik).

## Hasil index (Chrome 155, 1440x900 / 390x844; Edge 156 metrik saja)
- Elemen berkelas `tw:` di konten 72 -> 282; kelas legacy unik 26 -> 10 (sisa: ikon `fas/fa-*`, `ts-card-*` dari JS kartu, `content/container-fluid/table-responsive`); atribut `style` inline 5 -> 0.
- Tanpa galat konsol/jaringan, overflow horizontal 0, `tesys-tw.css` dimuat 1x. Target sentuh mobile <44px: 22/23 -> 0/23 (desktop tetap 23/23 < 44px, deviasi 44px lama).
- Hover baris = `surface-2`; fokus Tab murni pada "Lihat": outline 1,25px `accent-text`, `:focus-visible` benar.
- Konflik legacy terukur (`cdp-f4.mjs`, penyelidik): konflik nyata hanya (1) `td` padding vertikal di mode kartu mobile (disengaja, legacy menang) dan (2) transisi `tr` (legacy 0,12s; kelas `tw:transition` pada `tr` dihapus). Selisih lain = artefak penyelidik (style border, lebar piksel, `auto`). **Tidak ada `!` baru; tidak ada override global.**

## Penilaian jujur
Desktop: jelas lebih rapi (hierarki, ubin, pill, kolom aksi). Mobile: header dan ubin rapi, tetapi kartu baris masih kartu legacy (tanggal tebal, pemisah ganda, area aksi longgar); redesain kartu mobile sendiri butuh keputusan (mematikan mode kartu = di luar F4-a). Judul 20,7px dibatasi token; kapital th 12px terasa agak tebal.

## Risiko / saran
Artefak CSS berubah (12.004 B, sha256 `40555b01…ccb5dd`): unggah + naikkan versi aset. Harness F3 (`cdp-pages.mjs` main, `cdp-qa-f3.mjs`) mencari `.ts-action-primary` di index: tidak ada lagi; perbarui sebelum dipakai ulang. Saran: pola header+ubin+kartu dipakai modul lain setelah F4-b.

## F4-b — create, show, inbox
Semua view: `h1`+breadcrumb di wadah `tw:sr-only` (topbar), `x-tw.page-header`, lebar `max-w-[1200px]`, `tesys-tw.css` 1x. `_disclaimer` kini kartu panduan Tailwind (penanda `.feedback-disclaimer`, tanpa `.alert`).
- **Create**: grid 2/3 + 1/3 (>=901px; panduan sticky). Tiga bagian bertajuk, footer (Batal `<a>` + Kirim). Centang = `<input type=checkbox id=chkWilling name=willing_to_discuss value=1>` + `label[for]` dengan area klik penuh (`::after`), baris >=48px; teks di `<span>` agar tidak dikalahkan warna/ukuran label legacy. Galat server per-kolom (`$errors->first`) + blok ringkasan (div, bukan ul/`.alert-danger`). Kontrak DOM/JS (id, toggle `required`, wrapper) tidak berubah.
- **Show**: header + pill status/kategori + meta; kiri isi + tanggapan CEO, kanan Informasi (baris div, bukan tabel), Beri Tanggapan, Tandai Selesai (`.frmCloseFeedback` + SweetAlert utuh), panduan ringkas. Perilaku baru yang kasatmata: galat validasi respond kosong kini tampil di bawah textarea (sebelumnya tidak ada umpan balik).
- **Inbox**: header, catatan rahasia (tw), `x-stat-cards`, kartu + DataTables legacy tidak diubah; hanya badge berdot. DataTables/paginasi/ukuran halaman diuji berfungsi.
- Artefak CSS: 14.129 B, sha256 `ab275d4c…8d` (build deterministik, `-Cek` exit 0). Tidak ada kelas `!` baru.
- Metrik (Chrome 155): elemen `tw:` create 17->63, show 14->62/66, inbox 30->58; kelas legacy unik create 32->9, show 31->9, inbox 83->76; atribut style inline create 2->0, show 15/16->0, inbox 7->6 (DataTables). Overflow horizontal 0, tanpa galat konsol/jaringan, Chrome dan Edge.
- QA fungsional `cdp-qa-f3.mjs` (label f4b): Chrome 31/31 dan Edge 31/31 PASS pada run akhir; `cdp-pages.mjs`: 13 skenario, galatRunner 0 (Chrome dan Edge). Run pertama gagal 6 butir kontras/badge karena titik dekoratif `aria-hidden` (kosong) ikut terhitung; selektor harness dipersempit (`:not([aria-hidden=true])`), asersi tidak dilonggarkan.
- Konflik legacy terukur: hanya `label` (ukuran 12px/warna dim menang atas `text-label`/`text-text`; diatasi dengan span di centang, sudah dikenal di input) dan `strong` (bobot 700 menang atas 600; diterima). Selebihnya artefak penyelidik.
- Bukti: `f4/sesudah/`, `f4/perbandingan/` (create/show/inbox, desktop+mobile), `f4/hasil-f4-sesudah-*.json`, `f4/qa/hasil-f4b-*.json`, `f3/hasil-qa-f4b-*.json`.
- Keterbatasan: perbandingan show memakai submission berbeda (data uji bertambah); isolasi non-pilot dengan akun SAMA hanya terbukti untuk halaman akun staf/DB-agnostik (12 digest identik; beranda/profil superadmin tidak sebanding karena akun F3 `superadmin` vs `dummy.superadmin`); tw.css tidak dimuat di 20/20 halaman non-pilot. Firefox tidak dijalankan.
