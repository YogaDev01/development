# PR: develop-v2 → main

**Judul:** Perbaikan konkurensi transaksi keuangan, otorisasi bulk review & baseline dokumentasi

**Base:** `main` (`70dfa9e`) · **Compare:** `develop-v2` · **Dibuat:** 2026-10-02
Buat PR: https://github.com/YogaDev01/tesys/compare/main...develop-v2

---

## Ringkasan

Perbaikan **preventif** hasil audit teknis read-only (2 Okt 2026) atas TESYS.
Fokusnya race condition: dua request yang berjalan bersamaan (dua tab, dua
staf, atau bulk Todo List + klik satuan) bisa sama-sama lolos pemeriksaan
status dan menghasilkan efek ganda.

Pemeriksaan data produksi (query SELECT) menunjukkan **belum ada kasus yang
terjadi**: tidak ada CR dibayar dua kali, tidak ada Second Payment ganda,
tidak ada over-alokasi Cash Receipt, dan tidak ada CR yang terjebak.
**Tidak ada koreksi data.**

Tidak ada migration. Tidak ada perubahan rumus keuangan, urutan status,
notifikasi, atau route. Satu perubahan otorisasi (lihat "Otorisasi").

## Perubahan kode

### Treasury (`DailyTransactionController`)
- **H1 – buat batch & tambah item:** baris CR / Second Payment / Cost Register
  dikunci (`FOR UPDATE`) dan statusnya diperiksa ulang. Satu CR tidak lagi bisa
  masuk dua batch aktif (risiko dibayar dua kali).
- **M1 – Mark Paid:** batch dikunci sebelum lampiran disimpan. Klik ganda tidak
  lagi menggandakan lampiran & log atau menimpa biaya bank (`charge`).
- **Second Payment re-batch:** saat batch ditolak (`reject-batch`) atau item SP
  dikeluarkan (`reject-item`), CR induk kembali dari `QUEUED` ke
  `SECOND_APPROVED`. Sebelumnya SP tidak pernah bisa dibatch ulang dan CR
  tertahan tanpa jalan keluar.

### Settlement (`Concerns/HandlesCrSettlement`)
- **H2 – verifikasi Finance:** status + pembuatan Second Payment berada di dalam
  `transitionStatus()` (kunci baris), sama seperti aksi settlement lain.
  Mencegah Second Payment ganda.

### Cost Request (`BudgetRequestController`)
- **M3:** `managerRevise`, bulk review (approve/reject), dan
  `takeoutDetail`/`restoreDetail` memakai transisi terkunci. Revise + Cancel
  bersamaan tidak lagi menghidupkan CR yang sudah CANCELLED; bulk tidak lagi
  menimpa keputusan orang lain di tengah loop.

### Cash Receipt (`CashReceiptController`)
- **H3:** `allocate` mengunci header dulu, lalu invoice; `cancel` memakai urutan
  kunci yang sama. Mencegah alokasi melebihi uang yang diterima dan alokasi
  pada bukti terima yang sedang dibatalkan.

### Invoice & Surat Jalan (`InvoiceCashReceiptController`, `DeliveryNoteController`)
- submit / verify / approve / revise memakai `transitionStatus()`.
- `cancel`, settle PPh: invoice dikunci; `storeCashReceipt` memeriksa ulang
  status di dalam kunci.
- Surat Jalan: invoice dikunci dan diperiksa ulang — satu invoice tidak lagi
  bisa masuk dua Surat Jalan.

### Teks UI Invoice (`WorkflowStatus`, panduan, konfirmasi submit)
- Status VERIFIED sebelumnya tertulis "Menunggu persetujuan **CEO**", padahal
  route hanya mengizinkan **Finance Manager**. Teks disesuaikan; otorisasi
  tidak berubah.

## ⚠️ Otorisasi (mohon direview eksplisit)
- `budget-request/review-bulk-approve` dan `review-bulk-reject` sebelumnya
  memuat CR berdasarkan id **tanpa** pemeriksaan Access Group. Head bisa
  memproses CR proyek lain lewat POST yang dibuat sendiri. Sekarang memakai
  `visibleCrs()`, sama dengan aksi satuan dan bulk settlement. Lewat UI normal
  tidak ada perubahan yang terasa.

## Dokumentasi
- `CLAUDE.md` — aturan pengembangan (keamanan produksi, uang, otorisasi,
  migrasi, Definition of Done, klasifikasi risiko, wajib cek config cache
  sebelum test).
- `docs/` — arsitektur, database, keamanan, aturan bisnis terverifikasi,
  alur kerja (cash advance, settlement, payment, purchase, approval), modul,
  git workflow.

## Test
- 8 file test baru/diperbarui, **44 test baru**. Setiap skenario race
  disimulasikan dan **terbukti gagal pada kode lama**, lolos pada kode baru.
  - `TreasuryBatchLockTest` (8), `SettlementVerifyLockTest` (5),
    `CashReceiptAllocateLockTest` (5), `MarkPaidLockTest` (5),
    `CostRequestReviewLockTest` (9), `SecondPaymentRebatchTest` (4),
    `InvoiceLockTest` (8)
  - `BeneficiaryPicViewTest`: test usang (rekening Master PIC, dihapus sejak
    v2.5.0) diganti dengan test perilaku sekarang.
- **Suite penuh: 205/205.**
- Batasan: race yang butuh dua koneksi DB (dua alokasi ke dua invoice berbeda,
  dua takeout atas dua item terakhir, dua settle PPh pada receipt berbeda)
  tidak bisa direproduksi di PHPUnit satu koneksi → wajib UAT.

## UAT sebelum merge (dua sesi/tab bersamaan, di lingkungan uji)
- [ ] Dua Treasury membuat batch dari CR APPROVED yang sama → satu ditolak
- [ ] Dua tab menekan Mark Paid batch yang sama → lampiran tersimpan sekali
- [ ] Tolak batch berisi Second Payment → SP muncul lagi di Buat Batch
- [ ] Dua Finance memverifikasi settlement ber-Expense Claim → 1 Second Payment
- [ ] Bukti terima Rp10jt, alokasi Rp8jt ke 2 invoice bersamaan → satu ditolak
- [ ] Head A bulk approve sementara Head B reject CR yang sama → reject tidak tertimpa
- [ ] Revise + Cancel CR REJECTED bersamaan → tetap CANCELLED
- [ ] Dua takeout bersamaan atas dua item terakhir → CR jadi REJECTED
- [ ] Dua verifikasi invoice bersamaan → satu ditolak
- [ ] Pembayaran dicatat bersamaan dengan pembatalan invoice → pembayaran ditolak
- [ ] Satu invoice ke dua Surat Jalan bersamaan → satu ditolak
- [ ] Regresi alur normal: batch, Mark Paid, verifikasi settlement, alokasi,
      approval invoice, Surat Jalan, bulk review dari Todo List

## Deploy
Tidak ada migration. Upload file berikut (sisanya docs/test, tidak perlu di server):
- app/Http/Controllers/BudgetRequestController.php
- app/Http/Controllers/CashReceiptController.php
- app/Http/Controllers/Concerns/HandlesCrSettlement.php
- app/Http/Controllers/DailyTransactionController.php
- app/Http/Controllers/DeliveryNoteController.php
- app/Http/Controllers/InvoiceCashReceiptController.php
- app/Support/WorkflowStatus.php
- resources/views/invoice-cash-receipt/index.blade.php
- resources/views/invoice-cash-receipt/show.blade.php
- resources/views/panduan/topics/invoice-cash-receipt.blade.php

Setelah upload: `php artisan view:clear` (dan `config:cache`/`route:cache`
sesuai `docs/DEPLOYMENT.md` bila dipakai di server). **Jangan** menjalankan
test di server.

## Tidak termasuk PR ini
- `feature/effective-amount` (perubahan rumus "Nilai Efektif") — menunggu
  review Finance.
- Keputusan bisnis terbuka: self-approval, kunci periode Closing, limit jumlah
  Cash Advance, akses lampiran, pelaksana verifikasi Invoice di Todo List
  (lihat `docs/business-rules.md` & `docs/security.md`).

🤖 Generated with [Claude Code](https://claude.com/claude-code)
