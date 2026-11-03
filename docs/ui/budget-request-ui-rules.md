# Aturan UI/UX halaman Cost Request (`/budget-request`)

Dicatat 29 Okt 2026 dari penyempurnaan halaman Cost Request. Halaman ini dipakai
sebagai **acuan** untuk halaman lain (daftar, form, drawer detail). Aturan di sini
turunan dari `docs/ui/` yang sudah ada dan tidak menggantikannya. Bila ada konflik,
`CLAUDE.md` (§9 UI/UX, §20 risiko) yang menang.

Berkas utama: `resources/views/budget-request/*.blade.php`,
`public/css/tesys-ui-refine.css` (lapisan terakhir, tanpa @layer), komponen
`x-tw.*`, `x-status`, `x-workflow-stepper`, `x-drawer`.

---

## 1. Prinsip umum

1. Padat tapi terbaca; warna **hanya dari token** (`tesys-ui-tokens.css`), tanpa hex
   baru, tanpa gradien/animasi dekoratif.
2. Satu informasi, satu tempat. Jangan menampilkan data yang sama di dua tabel atau
   dua baris (mis. kode CR di header dan di kartu, tabel perbandingan + rincian).
3. Semua perubahan tampilan ditulis di `tesys-ui-refine.css` dengan cakupan
   `body.tesys-ui …`. Tailwind (`tw:`) tidak bisa di-build ulang di server; kelas
   arbitrer baru (`tw:w-[..]`) **tidak** akan ada hasil build-nya, jadi pakai CSS.
4. Teks UI & komentar berbahasa Indonesia, mengikuti kode di sekitarnya.
5. Angka uang rata kanan, `tabular-nums`, format `1.000.000`; nilai nol di tabel
   ringkasan ditampilkan `-`.
6. Perubahan tampilan tidak boleh mengubah hook JS (kelas `btn-*`, `realize-*`,
   `data-*`) atau aturan bisnis; bila perlu, tambah kelas baru, jangan ganti nama.

## 2. Kepala halaman

- Judul besar + **satu kalimat** deskripsi abu-abu. **Tanpa eyebrow** (breadcrumb
  sudah ada di topbar; `<h1>` + `.breadcrumb` ditulis `tw:sr-only` karena shell
  memindahkannya ke topbar).
- Aksi utama ("Tambah …") di kanan header. Di layar ≤ 640px tombol itu
  disembunyikan dan diganti **FAB** bulat (kanan bawah, di atas bottom-nav);
  FAB tidak tampil di tab "Database"/tab tanpa aksi tambah.
- Kode dokumen di header form (mis. halaman settlement) = tombol **klik untuk
  salin** (lihat §6), bukan teks "Kode: …".

## 3. Tab kategori

- Gaya **pil**: radius 10px, tanpa garis bawah/garis pemisah; aktif = latar
  `--accent-tint` + teks `--accent-text`; hover = `--surface-2`.
- Ukuran 14px, padding 10×18px. Jumlah per tab = pil putih berbingkai tipis.
- ≤ 640px: satu baris yang bisa digeser (tanpa membungkus), padding lebih rapat.

## 4. Stepper status (filter cepat)

- Dikelompokkan per **fase** dalam satu kartu; tiap fase = judul kecil + deretan
  tile (angka besar + label). Tiap tile mengisi filter status (klik lagi = lepas).
- Tile 0 = garis putus-putus, redup. Tile "ditolak > 0" = latar `--danger-bg`,
  teks `--danger`.
- Tile aktif = latar `--accent` terisi **dan teks putih** (termasuk tile danger;
  spesifisitas aturan danger tidak boleh mengalahkan aturan aktif).
- Mobile: satu baris, bisa digeser, tanpa scrollbar.

## 5. Tabel daftar

- Toolbar dan tabel dalam **satu kartu**. Toolbar: cari, hapus filter, **Filter**,
  **Kolom**, lalu **Export** di kanan. Filter baris tersedia untuk semua kolom
  penting (mis. Requestor, PIC).
- Kolom **Code tidak dibekukan**; yang beku hanya checkbox. Lebar kolom tetap
  (`table-layout: fixed`), teks panjang dipotong `…` (deskripsi maks 60 karakter),
  kolom uang cukup untuk `1.000.000.000`.
- Lebar kolom **bisa diseret** user, disimpan di `localStorage` per tab; klik dua
  kali handle = kembali ke bawaan. Hanya ≥ 641px.
- Kode, deskripsi: tidak tebal. Tanpa tanda merah di tepi baris; penanda NOB lewat
  **warna font**, bukan badge. Tanpa baris kecil di bawah deskripsi.
- Satu nilai per sel (mis. PIC; bila kosong pakai Beneficiary). Header kolom aksi
  kosong (`sr-only "Aksi"`), tidak bisa disortir/dicari.
- **Klik baris = buka drawer detail.** Kontrol di dalam baris (tautan, tombol,
  input, label, menu aksi, kolom centang) dikecualikan.
- **Kolom aksi**: 0 aksi = kosong; 1 aksi = tombol utama; > 1 aksi = tombol ⋮ berisi
  menu, aksi berbahaya dipisah garis. Semua baris **setinggi sama** (kontainer aksi
  32px tetap walau kosong).
- **Klik kode = salin** ke clipboard, dengan toast standar (§8).

## 6. Bulk action

- Checkbox per baris; hak centang dihitung server (`data-elig`), JS hanya
  menguncinya. **Satu status per proses**, maks 50, melewati batas = seluruh
  pilihan dibatalkan + peringatan.
- Bar bulk muncul bila ada pilihan: jumlah + total nominal, tombol Approve (label
  mengikuti tahap), Reject (disembunyikan bila tahap tidak punya reject), Batal.
- Konfirmasi Swal merangkum jumlah, tahap asal→tujuan, total, jumlah NOB/takeout.
  Server menerima `expected[id]` dan **melewati** CR yang statusnya sudah berubah,
  lalu melaporkan yang berhasil dan yang dilewati.

## 7. Drawer detail

- Tab di drawer: Info, Request, Settlement, Log. Jangan menampilkan dua tabel
  dengan isi yang sama; ringkasan total ditaruh di **footer** tabel rincian.
- **Viewer lampiran** (carousel + zoom) mengisi ruang kosong di **kiri drawer**
  (lebar = sisa setelah panel), bukan layar penuh, supaya data di drawer tetap
  terbaca. ≤ 640px tetap layar penuh. Esc menutup viewer dulu, baru drawer.

## 8. Notifikasi & dialog

- Info singkat (disalin, tersimpan, gagal): **toast standar** `window.tsToast(tipe,
  judul, pesan)` (kanan-bawah, kartu berwarna, tombol ×, hilang otomatis). **Jangan**
  memakai `Swal` bermode toast.
- `Swal` dipakai untuk **konfirmasi** aksi yang mengubah status/uang/data penting
  dan untuk peringatan yang harus dibaca. **Tanpa konfirmasi** untuk membuang baris
  yang masih di form (belum disimpan).

## 9. Form (create / edit / settlement)

- **Lebar penuh** (tanpa `max-w`), tanpa eyebrow, kartu per bagian.
- **Tabel rincian** (`table-layout: fixed`, ≥ 641px): No 44px; kolom pilihan 280–300px;
  harga & total 128–136px (muat 10 digit); qty 68–76px (muat 3 digit);
  **deskripsi = sisa lebar**; lampiran 150px; aksi 56–96px. Di HP tabel digeser
  mendatar. Hitung total di JS **dan** hitung ulang di server.
- Lampiran per baris = **kotak dropzone** putus-putus warna aksen (klik / drop /
  paste), tanpa tombol klip dan tanpa teks petunjuk "drop / paste / klik".
  Lampiran global memakai dropzone yang sama.
- Jangan menampilkan penghitung karakter permanen ("Sisa 255 karakter"); batas
  cukup lewat `maxlength`.
- **Dropdown bertingkat** (`TesysSearchableSelect`): judul grup tebal dan tak bisa
  diklik; item hanya teks spesifik (tanpa mengulang nama grup); kotak terpilih
  memakai label lengkap; kata kunci grup ikut dicari; lencana (mis. Internal/
  External) di akhir item; sisa budget tidak ditampilkan di daftar.
- **Badge "Wajib\*"**: tidak di header kolom. Muncul **per baris/bagian** hanya saat
  aturan wajib benar-benar berlaku (mis. Keterangan & Lampiran Settlement ketika
  total settle melebihi pengajuan). Aturan wajib selalu ditegakkan **di server**
  juga; UI hanya mencerminkannya.
- Tombol **Submit nonaktif** sampai syarat lengkap + petunjuk merah berisi apa yang
  kurang; **Simpan draft** tetap boleh setengah jadi.
- View Request: tombol aksi ringan di header kartu rincian; membuka modal lebar
  (`min(1280px, 94vw)`) read-only berisi rincian lengkap (COA/Sub COA satu kolom,
  angka tidak terpotong, header menempel). Auto Fill dihapus 4 Nov 2026 — baris
  item terisi & terkunci otomatis.

## 10. Responsif

- ≥ 641px: layout tabel penuh. ≤ 640px: kartu/tab satu baris/FAB; area sentuh
  tombol ≥ 48px (`tw:max-sm:min-h-12`); tidak ada scroll horizontal pada halaman
  (hanya pada tabel di dalam `.table-responsive`).
- `html` memakai `zoom` ≈ 80%: ukuran hasil `getBoundingClientRect` adalah piksel
  layar, nilai `style` adalah piksel ter-zoom — bagi dengan zoom bila dicampur.

## 11. Aksesibilitas

- Tombol ikon wajib `aria-label` + `title`; header kosong pakai `sr-only`.
- Fokus terlihat (`box-shadow: 0 0 0 3px var(--accent-ring)`); Esc menutup lapisan
  teratas; fokus ditahan di dalam drawer.

## 12. Prosedur kerja

- Setelah perubahan Blade: `php artisan optimize:clear` di host. CSS/JS statis
  cukup diunggah + Ctrl+F5.
- Sebelum rilis, cek: kompilasi Blade, sintaks JS, kurung CSS seimbang
  (lihat pemeriksaan di riwayat audit 29 Okt 2026).
- Perubahan yang menyentuh perhitungan uang, validasi server, atau alur status
  adalah **HIGH** (CLAUDE.md §20): rencana + persetujuan + feature test.

## 13. Penerapan ke halaman lain (29 Okt 2026)

Yang sudah berlaku lintas halaman (tanpa menyentuh logika):

- **Stepper berfase** hanya untuk halaman ber-workflow: Budget Plan, Treasury (batch),
  Invoice/Collection, Payroll Run, Purchase Order, Trouble Ticket — lewat prop
  `:groups` pada `<x-workflow-stepper>`. Aturan CSS fase memakai `:is(.cr-pane, .wf-host)`;
  komponen membungkus mode berfase dengan `.wf-host`, jadi halaman baru cukup memberi
  `:groups`. Halaman tanpa workflow **tidak** memakai stepper.
- **Tab kategori pil**: tambahkan kelas `ts-pill-tabs` pada `<ul class="nav nav-tabs …">`
  tingkat halaman (sudah: Beneficiary, PIC, Cash Management, Finance Summary,
  Assignment, Trouble Ticket Settings). Tab di dalam modal/drawer tetap bergaya garis bawah.
- **Toast**: info singkat memakai `window.tsToast`; `Swal` bermode toast sudah
  diganti (Forecast, Role). Halaman baru jangan memakai `Swal.fire({toast:true})`.
- **Input file** di area konten (`.main > .content`) tampil sebagai kotak putus-putus
  warna aksen (CSS global; `.d-none`/`[hidden]`/`.custom-file-input` dikecualikan).
- Kartu, toolbar, tabel data, tombol "Tambah" (btn-success -> aksen) sudah diseragamkan
  oleh lapisan compat/datatable sebelumnya.

Belum diterapkan (perlu verifikasi visual per halaman): header dalam-halaman
(`x-tw.page-header`), kartu `x-tw.card`, klik-baris-buka-drawer, kolom bisa diseret.
Terapkan bertahap per modul, mulai dari yang paling sering dipakai.

## 14. Pola index generik (contoh pertama: Treasury, 29 Okt 2026)

Untuk menyamakan halaman daftar lain dengan Cost Request tanpa menyalin CSS:

- Bungkus isi halaman dengan `.ts-index` (flex kolom, `tw:gap-4 tw:py-4`); header pakai
  `x-tw.page-header` (judul berbeda dari judul topbar + satu kalimat deskripsi), tombol
  tambah diberi kelas `ts-add-btn` dan FAB `<a class="ts-fab">` untuk HP.
- Toolbar + tabel + paginasi dalam **satu** `x-tw.card padding="sm"`; `.custom-toolbar`
  lama di dalamnya otomatis tanpa panel sendiri. Halaman wajib memuat `tesys-tw.css`.
- Tabel diberi kelas `ts-idx-tbl`: aturan aksi baris 32px, kolom aksi (`th/td.action-column`,
  header `sr-only`), kursor baris, dan paginasi Cost Request ikut berlaku.
- Sel: kode = `<button class="ts-copy-code" data-code>` (handler global di `tesys-ui.js`,
  toast standar); deskripsi `ts-col-desc` dipotong 60 karakter + `title`; nominal
  `ts-col-money`; tanpa badge dekoratif untuk teks biasa; tanpa baris kecil di bawah sel —
  informasi tambahan jadi kolom sendiri (Tanggal, Dibuat Oleh, Item).
- Kolom aksi: 0 aksi = kosong, 1 aksi = tombol, >1 = menu ⋮; "Detail" tidak di menu karena
  **klik baris membuka `x-drawer`** (Info / rincian / lampiran) yang memuat endpoint JSON
  yang sudah ada; kaki drawer = "Buka Halaman Detail" + salinan aksi baris.
