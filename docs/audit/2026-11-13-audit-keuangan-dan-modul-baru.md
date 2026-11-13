# Audit Statis TESYS — Modul Keuangan Inti & Modul Baru (2026-11-13)

**Metode:** analisis statis kode (read-only) oleh 5 subagent reviewer paralel. Tanpa DB, tanpa test,
tanpa browser, `.env` tidak dibuka. Semua temuan runtime = **Not Verified** sampai diuji.
**Cakupan:** Cost Request + Settlement, Treasury + Closing, Invoice + Cash Receipt, Accounting + Cash & Bank
(untracked), Letter / Manpower / Recruitment / Overview (untracked). Modul lain **belum diaudit**.
**Tidak ada P0 terbukti.** Status semua temuan: **Open**.

## 1. Ringkasan per modul

| Modul | Verdict reviewer | P1 | P2 | P3 |
|---|---|---|---|---|
| Cost Request + Settlement | Approve with changes | 4 | 11 | 8 |
| Treasury + Closing | Approve with changes | 3 | 6 | 5 |
| Invoice + Cash Receipt | Approve with changes | 2 | 7 | 5 |
| Accounting + Cash & Bank (baru) | Approve with changes | 4 | 6 | 9 |
| Letter / Manpower / Recruitment (baru) | **Reject (belum siap merge)** | 3 | 6 | 12 |

## 2. Temuan P1 (High)

| ID | Modul | Isu | Bukti | Butuh keputusan bisnis? |
|---|---|---|---|---|
| CR-01 | CR Settlement | Close settlement tanpa row lock & transaksi → Close+Reject bersamaan bisa sama-sama lolos | `Concerns/HandlesCrSettlement.php:379-400` | Tidak |
| CR-02 | CR Settlement | Reject tahap Close menghapus Second Payment berdasar cek di luar lock (SP bisa sudah masuk batch) | `HandlesCrSettlement.php:426-450` | Tidak (CRITICAL — uang) |
| CR-03 | CR | `update()` tidak mengecek hak Budget Plan tujuan (PAYROLL/Overhead) seperti `store()` | `BudgetRequestController.php:1130-1146` vs `2092-2097` | Tidak |
| CR-04 | CR | Superadmin edit penuh di PAID/CLOSED bisa ubah nominal yang sudah dibayar, tanpa lock | `BudgetRequest.php:225-230`, `BudgetRequestController.php:2114-2157` | **Ya** |
| TR-01/02 | Treasury | Batch `approve()` / `rejectBatch()` tanpa row lock → status batch & CR bisa tidak konsisten | `DailyTransactionController.php:829-866`, `986-1034` | Tidak |
| CL-01 | Closing | Periode CLOSED tidak mengunci penulisan di modul mana pun (hanya deteksi drift) | `ClosingService.php:16-19,77-79` | **Ya** (blokir vs deteksi) |
| IV-01 | Invoice | Hapus invoice tanpa `deleted_by`/`delete_reason` (melanggar §5) | `InvoiceCashReceiptController.php:783-813` | Tidak |
| IV-03 | Invoice | Route memberi `collection` akses edit/hapus/settle Cash Receipt yang di Blade disembunyikan | `routes/web.php:956-980`, `invoice-cash-receipt/show.blade.php:13-24` | **Ya** |
| AC-01 | Accounting | Modul Accounting **tanpa feature test** sama sekali | grep `tests/` = 0 | Tidak |
| AC-02 | Accounting | Kolom uang jurnal disimpan dalam **sen** (×100), menyimpang dari §5 "integer rupiah" | `2026_11_14_000100_...php:12-17,102-133`, `JournalGenerator.php:32` | **Ya** (putuskan sebelum migrasi jalan) |
| CB-01/02 | Cash & Bank | Ekspor CSV Transaction Viewer & Cash Reports tanpa `CsvSafe` (CSV injection) | `CashTransactionController.php:72-75`, `CashReportController.php:78-83` | Tidak |
| RK-01 | Recruitment | Kandidat tetap bisa di-hire walau Manpower Request dibatalkan | `ManpowerRequestController.php:243-245`, `RecruitmentController.php:106-167` | Tidak |
| LT-01 | Letter | Aksi alur (submit/cancel/sent/complete/delete) bisa dijalankan pemegang menu mana pun atas surat orang lain | `LetterController.php:285-330` | **Ya** (siapa pelaku per aksi) |
| LT-02 | Letter | Edit isi/info surat orang lain tanpa cek pemilik; tidak ada scope visibilitas | `LetterController.php:62-88,156-238` | Sebagian |

## 3. Temuan P2 (Medium) — ringkas

**Validasi uang (pola lintas modul):** `normalizeMoney()`/`money()` membuang semua non-digit → `"1.500,50"` jadi ×100,
minus hilang, tanpa batas atas. Ditemukan di CR-06, TR-05, IV-10, CB-05; impor rekening koran memotong sen diam-diam (CB-06).
→ Satu parser bersama + validasi regex/max.

**Konkurensi tanpa lock:** CR-08 (`submitRealization`), CR-09 (`submit`), CR-10 (`update` Draft), CL-02/03 (Closing build/close/reopen,
unique `period,deleted_at` tak efektif), IV-07 (`update` invoice), RC-02 (hapus header penerimaan), MP-01 (edit MP vs approve),
AC-04 (post jurnal vs tutup periode).

**Soft delete / file:** CR-11 & IV-02 menghapus file fisik saat record di-soft-delete; LT-04 hard delete surat beserta log; IV-06 alasan
cancel tidak masuk log (salah field `notes` vs `cancel_reason`); AC-03 log mapping `old_values` = nilai baru.

**Otorisasi & akses:** CR-12 template import menerima Budget Plan apa pun (IDOR info); RC-01 Cash Receipt tanpa scope Access Group;
CB-03 ekspor CSV tanpa gerbang role; CB-04 lampiran transfer/adjustment/saldo awal tak bisa dibuka approver (tidak terdaftar di `FileAccess`);
RK-05 menu recruitment tampil untuk non-HR lalu 403; LT-03 lampiran/TTD surat tak terkunci status; MP-03 HR bisa approve pengajuan sendiri.

**Validasi lain:** CR-05 lampiran global CR tanpa `mimes`/`max` (§15); CR-07 submit settlement tanpa validasi tanggal/vendor;
LT-05 kategori surat bisa diganti tapi nomor tetap.

**Butuh keputusan bisnis:** CR-13 (pengaju ≠ approver), CR-15 (lock periode di CR), CL-04/05/06/07 (definisi rekap closing,
potensi hitung ganda, drift nominal, pemisahan peran close), IV-05 (cancel invoice yang sudah dibayar sebagian), RC-03 (idempotensi
pembayaran sebagian).

## 4. Temuan P3 (Low) — ringkas

Validasi `notes`/`qty`/`ids` bulk tanpa batas (CR-16..18); audit edit Draft tak tercatat (CR-19); gap nomor CR (CR-21);
hex warna inline (CR-22) dan komponen non-standar di view HRIS/Letter (OV-05: tidak pakai `x-status`/`x-empty`);
daftar role tersebar di luar `AccessRoles` (CR-23, CB-10, OV-01); N+1 & daftar tanpa cap di HRIS (OV-04);
double-submit form create di modul baru (AC-07, CB-07, OV-03); menu baru tak ada di `MenuItemSeeder` (OV-02);
retensi data kandidat (RK-03); impor kandidat 500 bila field kepanjangan (RK-04); TR-04, TR-06..08, IV-08/09/11/12/13,
AC-05/06/08, CB-08/09, MP-02/04, LT-06, RK-02, OV-06, CL-08.

**Dokumentasi:** CLAUDE.md §5 (daftar jalur tanpa lock) sudah tidak sesuai kode — beberapa sudah memakai `transitionStatus` (CR-24).

## 5. Yang sudah baik (terverifikasi dari kode)

- `markPaid` Treasury, alokasi Cash Receipt, posting jurnal: row lock + cek ulang status di dalam lock → pembayaran/posting ganda tertolak.
- Approve/reject CR, settlement check/verify/approve, takeout, bulk review memakai `transitionStatus()`.
- Nomor dokumen dari `DocumentSequence` di semua modul yang diaudit.
- Upload ke disk `secure` dengan `mimes`/`max` (kecuali CR-05); `findVisibleCr`/`findVisibleInvoice` menutup IDOR aksi workflow.
- Jurnal: debit = kredit dihitung ulang di backend; Posted tidak bisa diedit; koreksi via jurnal pembalik; SoD pembuat ≠ poster.
- Migrasi baru aditif, ber-guard, timestamp unik, FK RESTRICT, tanpa kolom uang decimal/float.
- Guard double-click global `initBusyButtons` (`public/js/tesys-shell.js:336-364`).

## 6. Testing results

| Item | Status |
|---|---|
| Feature test (seluruh modul) | **NOT RUN** — sesuai pilihan "statis saja" |
| UI/responsif di browser | **NOT RUN** |
| Test case TC-01..TC-10 per form | **Not Verified** |

## 7. Rencana remediasi (usulan, belum dikerjakan)

**Gelombang 1 — sebelum merge modul baru (tanpa keputusan bisnis):**
CB-01/02 (CsvSafe), AC-01 (test Accounting), RK-01 (blok hire bila MP batal), AC-03, IV-06, CB-04 (FileAccess lampiran Cash),
RK-05, CR-05 (validasi lampiran).

**Gelombang 2 — locking & integritas (HIGH/CRITICAL, perlu persetujuan + test):**
CR-01, CR-02, TR-01/02, CR-08/09/10, IV-07, RC-02, MP-01, AC-04, CL-02.

**Gelombang 3 — butuh keputusan bisnis dulu:**
AC-02 (sen vs rupiah — **sebelum migrasi 2026_11_14 dijalankan**), CL-01/CR-15 (lock periode), CR-04, IV-03, LT-01/02,
CR-13, MP-03, IV-05, CL-04..07.

**Gelombang 4 — konsistensi & UX:**
Parser uang bersama (CR-06, TR-05, IV-10, CB-05/06), soft delete + file (IV-01, CR-11, IV-02, LT-04), konsolidasi role ke
`AccessRoles`, komponen `x-status`/`x-empty` di modul baru, double-submit modul baru, update CLAUDE.md §5.

## 7a. Keputusan bisnis (pemilik proses, 2026-11-13)

| Topik | Keputusan | Dampak ke temuan |
|---|---|---|
| AC-02 | Satuan tampilan rupiah, jurnal boleh pecahan (sen) | Penyimpanan BIGINT sen (rupiah×100) = sesuai keputusan. Dicatat sebagai **pengecualian §5** khusus tabel akuntansi. Input jurnal manual sudah ada (`POST accounting/journal/manual`, `JournalController::storeManual`). |
| CL-01 / CR-15 | Periode CLOSED **memblokir penulisan** | Perlu guard periode di semua jalur tulis (HIGH). |
| CR-04 | Superadmin boleh mengedit apa pun; **setiap perubahan wajib tercatat di log** | CR-04 bukan bug; yang wajib: log lengkap (nilai lama→baru) di semua jalur edit Superadmin. |
| IV-03 | User hanya boleh mengakses apa yang tampil di layarnya | Route harus disamakan dengan Blade (cabut akses `collection` yang tidak tampil). |
| LT-01 / LT-02 | Siapa pun yang melihat menu boleh menjalankan aksi / membuat surat | Perilaku saat ini **sesuai**; LT-01/02 ditutup (bukan bug). |
| CR-13 / MP-03 | Pengaju tidak boleh menjadi approver, **kecuali Superadmin** | Perlu guard self-approval di CR & MP (Letter sudah, pengecualian Superadmin perlu dicek). |

## 8. Belum diaudit

Budget Plan, Purchase Order/Project, Cash Management, Cost Register/Expense, Forecast, Payroll, Master data, Todo List,
Asset Management, Trouble Ticket, Assignment; audit UI/design system lintas halaman; responsive di perangkat nyata.
