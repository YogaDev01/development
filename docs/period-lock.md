# Kunci periode (Closing CLOSED memblokir penulisan)

Keputusan pemilik 2026-11-13 (audit CL-01 / CR-15). Rancangan: Planner 2026-11-13.
Implementasi: `App\Support\PeriodLock`, `App\Exceptions\PeriodLockedException`, `config/closing.php`.

## Aturan

- `Closing` berstatus `CLOSED` memblokir penulisan dokumen finance yang **tanggal acuannya** jatuh di periodenya.
  `DRAFT` dan `REOPENED` = terbuka.
- Tanggal acuan = tanggal yang direkap `ClosingService` (tanggal diblokir = tanggal direkap):
  Invoice `invoice_date`, Cash Receipt `received_date`, CR `paid_at`, Treasury `date`.
- Ini guard **keadaan data** (seperti cek status), bukan otorisasi: tidak dipasang sebagai middleware.
  Controller penentu; Blade mencerminkannya lewat `PeriodLock::forModel()` (tombol dinonaktifkan + tooltip).
- Superadmin asli (bukan "View As") boleh menembus dengan field `alasan_periode`; penembusan dicatat di
  `UserActivityLog` (module `closing`, action `period_override`). Jalur bulk/otomatis tidak punya bypass.
  Bila tanggal itu juga di `AccountingPeriod` CLOSED/LOCKED, penembusan ditolak (buka Accounting dulu).
- `CLOSING_ENFORCE_FROM_PERIOD` (Y-m, env → `config/closing.php`): periode di bawah nilai ini tidak memblokir.
  Kosong = semua periode CLOSED memblokir.

## Status per tahap

| Tahap | Modul | Status |
|---|---|---|
| 0 | Fondasi (`PeriodLock`, `Closing::close` transaksional) | Selesai |
| 1 | Invoice (store, update, submit, revise→DRAFT, cancel), Cash Receipt (kedua controller) | Selesai (test belum dijalankan) |
| 2 | Treasury (store, addItem, removeDraftItem, destroy draft, takeout/reject item, superUpdate, markPaid, superTakeoutPaid) + edit Superadmin CR yang sudah dibayar | Selesai (test belum dijalankan) |
| 3 | Cash & Bank (Transfer, Adjustment), Cash Management | Selesai (test belum dijalankan); saldo awal & rekonsiliasi sengaja tidak diguard (keputusan pemilik) |
| 4 | Expense, Payroll | Selesai tanpa perubahan kode: pembayarannya sudah diguard lewat Treasury (tahap 2), lihat catatan |
| 5 | Silang Closing ↔ AccountingPeriod, CLAUDE.md §8, dokumen | Selesai (test belum dijalankan) |

## Catatan tahap 1

- Tidak diguard (tidak mengubah keanggotaan/nominal rekap): hapus Invoice DRAFT, verify/approve/mark-received.
- Submit invoice dan "Batalkan alokasi" tidak punya jalur bypass di UI: Superadmin mengedit tanggal dulu
  (form edit memuat field alasan) atau periode dibuka kembali.
- Alasan penghapusan Cash Receipt oleh Superadmin juga dipakai sebagai `alasan_periode`.

## Catatan tahap 2 (CRITICAL)

- Tanggal acuan Treasury = `DailyTransaction.date` (sama dengan rekap). `markPaid` memeriksa `batch->date` DAN hari ini
  (`paid_at` CR/Expense direkap sebagai pembayaran); pemeriksaan dilakukan sebelum lampiran bukti disimpan.
- `store`/`addItem`/`removeDraftItem`/`destroy` draft tidak punya jalur bypass di UI (Superadmin: buka periode atau
  `superUpdate` tanggal dengan alasan). `markPaid`, `superUpdate`, takeout, dan edit CR punya field `alasan_periode`.
- Tidak diguard (tidak mengubah keanggotaan/nominal rekap): `submit`, `approve`, `rejectBatch`, bulk (memanggil aksi satuan).
- Risiko operasional: batch yang masih berjalan melewati akhir bulan lalu periodenya ditutup tidak bisa dibayar
  (opsi A keputusan #3). Jalan keluar: buka kembali periode, atau Superadmin menembus dengan alasan.
- Tombol di drawer Treasury dibangun JS, sehingga hanya halaman detail batch yang menonaktifkan "Tandai PAID";
  aksi lain menampilkan pesan galat dari controller.
- Pemeriksaan kunci di `BudgetRequestController::update` dilakukan sebelum transaksi (tanpa shared lock); race dengan
  `Closing::close` sempit dan tercakup temuan CR-10.

## Catatan tahap 3

- Yang mengubah saldo hanya dokumen berstatus APPROVED (Transfer, Adjustment) atau POSTED (Cash Flow), jadi yang diguard:
  `store` (tanggal dokumen), `approve` (Transfer/Adjustment), `post` dan `cancel` Cash Flow POSTED, `store`/`update` Cash Flow.
  `reject`/`cancel` dokumen PENDING dan hapus/batal DRAFT tidak diguard (tidak mengubah saldo; mencegah dokumen tertahan).
- Saldo awal rekening (`BankAccountController::storeOpening/verifyOpening`) dan Rekonsiliasi TIDAK diguard: saldo awal adalah
  titik mulai buku kas (bertanggal sebelum periode berjalan, termasuk periode yang sudah CLOSED saat go-live), dan rekonsiliasi
  adalah kontrol yang wajar dilakukan setelah tutup bulan. Keputusan pemilik 2026-11-13: tetap tidak diblokir.
- Modul ini belum masuk rekap `ClosingService`, jadi `drift()` tidak melihat perubahannya; guard ini murni berbasis tanggal dokumen.
- Pembuatan Cash Flow otomatis dari modul lain (bila ada) tidak melalui controller ini dan belum diguard.

## Catatan tahap 4 (Expense & Payroll)

- Pembayaran Expense (termasuk Second Payment Expense) terjadi lewat Treasury `markPaid`, yang sudah memeriksa tanggal batch dan
  hari ini (tahap 2). Jalur Expense lain (`store`/`update`/`submit`/`approve`/`reopen`/`cancel`) berlaku sebelum dibayar dan tidak punya
  tanggal rekap; settlement Expense mengikuti keputusan #2 (tidak diblokir, tidak mengubah baris rekap pembayaran).
- Payroll Run: `period` (Y-m) adalah bulan gaji, bukan tanggal peristiwa (gaji bulan Juli lazim diproses di Agustus), sehingga
  memblokir menurut `period` akan menghalangi proses yang wajar. `markPaid`/`close` Payroll hanya mengubah status dokumen; uang
  bergerak lewat batch Treasury yang sudah diguard. Karena itu Payroll tidak diguard.
- Alokasi periode Expense (`cost_register_periods`, diskala ulang saat settlement disetujui) memengaruhi laporan per periode;
  Expense belum masuk rekap `ClosingService`. **PERLU KEPUTUSAN** bila pemilik ingin alokasi itu ikut dikunci.

## Catatan tahap 5 (Closing ↔ AccountingPeriod)

- Dua tabel tetap terpisah, saling mengacu satu arah: Closing (operasional) ditutup lebih dulu, baru AccountingPeriod.
- `AccountingPeriodController::transition` (OPEN → CLOSED) menolak bila ada bulan dalam rentang periode yang belum punya Closing
  CLOSED (`PeriodLock::unclosedMonths`; bulan < `closing.enforce_from_period` dibebaskan).
- `ClosingController::reopen` menolak bila ada AccountingPeriod CLOSED/LOCKED yang beririsan (`PeriodLock::accountingBlockerForRange`).
- Penembusan Superadmin atas dokumen operasional ditolak bila tanggalnya di AccountingPeriod CLOSED/LOCKED (sejak tahap 0).
- Panduan pengguna dan changelog belum diubah (hanya saat rilis diminta, CLAUDE.md §17).

## Keputusan pemilik setelah review (2026-11-13)

- **Perubahan nominal pada dokumen yang sudah direkap diterima, tidak diblokir** (mis. Cash Receipt di periode terbuka melunasi atau
  menyinkron PPh invoice bertanggal di periode CLOSED lewat `Invoice::sinkronPphDariCashReceipt`; pelunasan invoice lama adalah
  alur normal). Agar terlihat, `ClosingService::drift()` kini ikut membandingkan nominal: daftar `changed` (nominal direkap vs
  sekarang) dihitung di `has_difference`, tampil di banner dan kartu Selisih halaman Closing. Rekap modul COLLECTION memang
  bergantung waktu (snapshot saldo sekarang), sehingga pelunasan susulan akan tampil sebagai selisih; itu perilaku yang diharapkan.
- **Purchase Order (`release_date`, `nominal`) tidak diblokir.** Modul PURCHASE_ORDER tetap masuk rekap Closing, tetapi penulisan
  `ProjectController` tidak diguard `PeriodLock`; perubahannya hanya terlihat lewat `drift()`.

## Perbaikan setelah review (2026-11-13)

- Test: `AccountingJournalTest` disesuaikan (tutup periode akuntansi butuh Closing CLOSED); menu `cash-receipt` untuk test multi-invoice;
  `tests/TestCase.php` mengosongkan cache `PeriodLock` di setUp/tearDown; kasus array pada `alasan_periode` diuji.
- `PeriodLock`: kunci bersama kini mencari lewat `period` (indeks unik, satu baris), `alasan_periode` non-string dianggap kosong,
  dan penembusan GAGAL bila log override tidak dapat ditulis (fail-closed). `createFromFormat('!Y-m')` mencegah luapan pada hari 29–31.
- Urutan kunci: guard `store` invoice dipindah ke setelah kunci No. PO/BAST. Guard `BudgetRequestController::update` dan
  `CashManagementController::post/cancel` kini di dalam transaksi + `lock: true`; log override tidak tertulis untuk aksi yang gagal
  (Transfer/Adjustment: guard terakhir setelah semua pemeriksaan lain).
- Bulk Revise Invoice menangkap `PeriodLockedException` per invoice. `destroyCashReceipt` membaca tanggal dari baris terkunci;
  `CashReceiptController::cancel` memeriksa tanggal tiap alokasi. `removeAllocation` tanpa bypass (sesuai UI); halaman detail
  penerimaan menonaktifkan Alokasi/Batalkan dan menyediakan field alasan di modal Alokasi.
- Belum/ sengaja tidak: tombol tambah item/hapus draft di halaman detail Treasury belum dinonaktifkan (controller yang menolak);
  `ClosingController::reopen` ↔ `AccountingPeriodController` tanpa kunci bersama (race sempit antar dua aksi Superadmin/akuntansi);
  command konsol (`CreateBastFromInvoice`, `SplitSyntheticBast`, `RepairInvoiceCapturedTotal`) tidak melewati guard (operasi manual).
