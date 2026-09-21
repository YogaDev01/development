# Changelog

Semua perubahan penting pada Tesys (Terex System) dicatat di file ini.

Format mengikuti [Keep a Changelog](https://keepachangelog.com/id/1.1.0/),
dan proyek ini memakai [Semantic Versioning](https://semver.org/lang/id/).

> Sumber data yang ditampilkan di Dashboard ada di `config/changelog.php`.
> Setiap kali menambah rilis, perbarui **kedua** file agar sinkron.
>
> Tanggal rilis 1.1.0 - 1.5.0 bersifat perkiraan (belum ada histori git
> terpisah); sesuaikan bila ada tanggal pasti.

---

## [2.2.0] - 2026-09-21

### Berubah
- Nomor Cost Request 4 digit lebih pendek: slot cluster dihapus, jadi CR-{bulan}{tahun}{kode PO}{urut} — contoh CR-0926008500180039 kini terbit sebagai CR-092600850039. Kode lama tidak diubah.
- Second Payment kini dipilih saat membuat batch Treasury, sejajar Cost Request dan Cost Register.
- Tombol "Mark Paid" Second Payment di To-Do List dihapus — bagian itu sekarang informatif saja dan menampilkan kode batch yang membayarnya.
- Second Payment ikut file transfer bank dan Export batch, dengan nominal selisihnya saja dan Reference berakhiran "-2".
- Riwayat Cost Request mencatat Second Payment masuk batch, disetujui CEO, dibayar, atau di-takeout.
- Modal detail Cost Request menampilkan status Second Payment beserta batch Treasury-nya, bukan sekadar "Pending".

### Perbaikan
- Kelebihan Settlement yang dibayar lewat Second Payment kini ikut mengurangi sisa Budget Plan dan ikut dihitung saat pengecekan NOB — sebelumnya uangnya keluar tapi sisa anggaran tidak berkurang.
- Second Payment tidak bisa lagi masuk ke dua batch sekaligus.
- Batch yang memuat Second Payment tidak lagi menghitung ganda rincian Chart of Account maupun nilai Summary Export.

## [2.1.0] - 2026-09-18 — Pengamanan Data, Ketahanan Transaksi & Jaring Uji

Rilis ini hasil audit menyeluruh. Tidak ada fitur baru bagi pengguna; yang
berubah adalah hal-hal yang selama ini menahan risiko tanpa terlihat.

### Security
- Lampiran (invoice, BAST, bukti transfer, bukti settlement) tidak lagi dilayani
  langsung dari `public/storage`. Semua berkas pindah ke disk privat dan hanya
  keluar lewat route ber-otentikasi yang memeriksa hak akses per modul.
- API ekspor publik: token pindah ke header, dibandingkan dengan `hash_equals`,
  dibatasi 30 permintaan/menit, dan setiap penolakan dicatat.
- Login dibatasi 5 percobaan gagal per (username + IP) dengan jeda 5 menit.
- Setiap aksi ber-`{id}` pada Cost Request, Invoice dan Budget Plan kini
  memeriksa hak akses per baris — dulu hanya daftarnya yang tersaring, URL-nya
  terbuka.
- Header keamanan (X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy)
  dipasang untuk seluruh respons.
- 34 peringatan kerentanan dependensi ditutup lewat pembaruan paket.
- Pustaka pihak ketiga (SweetAlert2, Tom Select, Bootstrap Select, Select2)
  dilayani dari server sendiri, tidak lagi dari CDN tanpa SRI.

### Fixed
- Pengecekan NOB tidak menghitung Cost Request berstatus `CHECKED`, sehingga
  sisa anggaran yang dilaporkan lebih besar dari kenyataan.
- Approval ganda: pemeriksaan status dan penulisan status kini satu transaksi
  dengan baris terkunci, jadi dua klik bersamaan tidak lagi menghasilkan dua log
  dan dua notifikasi.
- Nomor dokumen (Invoice, Cost Request, Daily Transaction, Surat Jalan) tidak
  lagi dibuat dari `COUNT(*) + 1`, yang bisa bentrok saat dua orang menyimpan
  bersamaan dan memakai ulang nomor setelah dokumen dihapus.
- Pembayaran melebihi sisa tagihan bisa lolos bila dua Cash Receipt dicatat
  bersamaan; pemeriksaannya kini di dalam transaksi dengan invoice terkunci.
- Cash Receipt bernominal nol bisa tersimpan bila kolom nominal diisi huruf.
- Membuka daftar Purchase Order menjalankan UPDATE per baris — halaman baca
  yang diam-diam mengubah data.
- Migrasi lama yang menyetel status tidak dikenal menjadi `APPROVED` secara
  massal dinonaktifkan.

### Changed
- Penanda NOB pindah dari awalan teks pada deskripsi ke kolom `is_nob` +
  `nob_amount` (data lama ikut dipindahkan).
- Penghapusan Cash Receipt kini wajib beralasan, ditandai (soft delete) dan
  dicatat — bukan dihapus permanen bersama bukti transfernya.
- Kolom uang yang masih `decimal(18,2)` disamakan menjadi bilangan bulat rupiah.
- Notifikasi dikirim lewat antrean (worker wajib jalan), dengan batas waktu 5
  detik ke API WhatsApp.
- Daftar besar dibatasi 1.000 baris dengan spanduk pemberitahuan; angka ringkasan
  tetap dihitung dari seluruh data.
- Indeks `(status, tanggal)` ditambahkan dan penyaringan tanggal tidak lagi
  memakai `whereDate()` yang mematikan indeks.
- Form invoice (tambah/ubah/lihat) memakai tata letak menurun.

### Removed
- Alur approval usang (`WorkflowService`, `BudgetRequestApprovalController`) yang
  memakai status tidak lagi ada dan tidak pernah terpasang di route.
- Dua layout tak terpakai (`app-legacy`, `app-adminlte`), partial `views/ui`, dan
  `routes/ui.php` yang tidak pernah dimuat.

### Added
- Jaring uji otomatis pertama: 61 uji yang menutup akses berkas, pembatasan
  login, hak akses lintas Access Group, alur approval, perhitungan anggaran,
  penomoran dokumen, pembayaran, dan penggambaran seluruh halaman utama.
- Dokumentasi: README proyek, `docs/DEPLOYMENT.md` (rilis, cron, worker,
  cadangan, pemulihan) dan `docs/GLOSSARY.md` (pemetaan istilah UI ↔ kode).

## [2.0.0] - 2026-09-15 — Rilis Besar: Settlement, Modul Team & Finance Forecast

### Added
- Modul Team: struktur organisasi (Department, Team, Team Member) dengan Organization
  Chart dinamis berdasarkan level.
- Akses PO kini bisa diberikan lewat Team → Access Group, selain assignment langsung
  per user.
- Modul Finance Forecast: rencana vs realisasi per periode, status Draft → Active →
  Closed, Variance & Achievement %, serta auto-close terjadwal (`forecast:auto-close`).
- Second Payment otomatis pada Cost Request Cash Advance saat Settlement melebihi
  nilai pengajuan awal.
- Lampiran per item (selain global) pada Settlement Cost Request.
- Kolom "On Review" pada modal View Budget Todo List.
- Section "Treasury — Buat Batch Pembayaran" di Todo List (CR Approved yang siap
  dibatch, sebelumnya tidak muncul di worklist manapun).
- PO Quantity + alert "PO Adjustment Required".
- Invoice: tombol "Tandai Diterima" dan Jatuh Tempo Aktual.
- Invoice: Cancel & Revise (khusus Finance Manager).
- 6 KPI card baru + Active Cash Advance + PO Summary (formula VAR bisa dipilih) pada
  Summary Finance.
- Popup "What's New" otomatis saat membuka Dashboard, dengan opsi "jangan tampilkan
  lagi" per rilis.

### Changed
- Settlement Cash Advance kini 3 tahap approval: Head Check → Finance Verify → CEO
  Close (sebelumnya 1 langkah Finance langsung Close). Label "Realization" diganti
  "Settlement" di seluruh tampilan.
- Batas maksimal nilai Settlement dihapus — kelebihan ditagihkan lewat Second Payment.
- Nilai PO tidak lagi diinput manual, otomatis dihitung dari total nominal seluruh
  No. PO.
- Budget Plan otomatis masuk tahap Closing begitu PO terkait sudah Paid.
- Urutan tab Summary Finance: Executive → Collection → Budget. Tabel Customer
  Outstanding & Manager Collection Performance digabung jadi satu tabel dengan toggle
  grouping.
- Tampilan/header puluhan halaman Create/Edit/Detail dirapikan agar konsisten dengan
  pola modern di semua halaman index — tanpa mengubah warna tema.
- Proteksi delete User yang masih menjadi Project Manager atau punya Cost Request.
- Source Fund wajib di batch Treasury & Invoice; dropdown Source Fund Invoice hanya
  menampilkan rekening yang mengandung "PT".

---

## [1.5.0] - 2026-09-08 — Penyempurnaan Summary Collection

### Added
- Filter bulan (`col_month`) pada halaman Summary Collection.

### Changed
- Outstanding = invoice berstatus `RECEIVED` / `PARTIALLY_PAID` ditambah invoice
  Overdue; invoice `SENT` yang belum jatuh tempo tidak lagi dihitung.
- Logika Outstanding & Overdue dipusatkan pada satu closure bersama agar konsisten
  antar tampilan.

---

## [1.4.0] - 2026-09-01 — Project Manager & kolom biaya proyek

### Added
- Kolom Contract, Overhead, dan Fixed Cost pada data proyek.
- Pemilihan Project Manager Internal dari daftar user.

### Changed
- PM Internal mengacu ke `projects.manager_id` (relasi ke tabel `users`),
  menggantikan input teks bebas.

---

## [1.3.0] - 2026-08-25 — Lampiran per item pada Cost Request

### Added
- Lampiran per item (request detail attachment) pada form Cost Request.
- Lampiran global pada Cost Request.
- Hint alur kerja yang muncul saat memilih tipe Cost Request.

### Changed
- Proses simpan detail (`syncDetails`) melakukan upsert berdasarkan id item agar
  lampiran tidak hilang saat edit.

---

## [1.2.1] - 2026-08-18 — Takeout item saat approval Cost Request

### Added
- Head / Finance / CEO dapat takeout / restore item (request detail) selama proses
  approval.
- Nilai yang diproses otomatis berkurang sesuai item yang dikeluarkan.

### Changed
- Bila seluruh item dikeluarkan, Cost Request otomatis berstatus `REJECTED`.

---

## [1.2.0] - 2026-08-13 — Pemisahan alur kerja Cost Request

### Changed
- Cash Advance memakai siklus penuh: `QUEUED -> PAID -> REALIZED -> CLOSED`.
- Tipe Cost Request lain langsung `QUEUED -> CLOSED` saat mark-paid.
- Label status diselaraskan: `QUEUED` = "Process", `REALIZED` = "Settle".

---

## [1.1.0] - 2026-08-04 — Perombakan tampilan Budget Plan

### Added
- Modal form Budget Plan dengan ukuran & tata letak baru.
- Sidebar cost center di dalam form.
- Tab-tab pengelompokan isian.
- Pewarnaan baris per NOB (Not of Budget).
- Sorting kolom dan filter pada tabel.
- Lampiran dan log perubahan Budget Plan.

---

## [1.0.0] - 2026-07-24 — Rilis perdana Tesys (Terex System)

Aplikasi operasional & keuangan Terex mulai digunakan.

### Added
- Dashboard terpadu: panel Need Action, Reminder, dan Last Activity per pengguna.
- Modul Project & Project Monitoring: aktivasi proyek, detail, stage, dan lampiran.
- Modul Budget Plan dan Budget Request beserta realisasinya.
- Modul Cost Request dengan alur approval berjenjang.
- Modul Invoice & Cash Receipt dan Summary Collection.
- Modul Payroll Run, Daily Transaction, dan Finance Summary.
- Master data: Customer, Beneficiary, Bank, Cluster, Cluster Group, Cost Center,
  Source Fund, Stage, Request Type, Master PIC.
- Manajemen akses: User, Role, Access Group, middleware `CheckRole`, dan mode "View As".
- TesysAI (Beta): asisten tanya-jawab operasional dengan riwayat percakapan.

[1.5.0]: https://example.com/tesys/releases/1.5.0
[1.4.0]: https://example.com/tesys/releases/1.4.0
[1.3.0]: https://example.com/tesys/releases/1.3.0
[1.2.1]: https://example.com/tesys/releases/1.2.1
[1.2.0]: https://example.com/tesys/releases/1.2.0
[1.1.0]: https://example.com/tesys/releases/1.1.0
[1.0.0]: https://example.com/tesys/releases/1.0.0
