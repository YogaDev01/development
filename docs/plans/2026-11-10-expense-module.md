# Rencana: Cost Register → Expense

Tanggal: 2026-11-10 · Status: **DISETUJUI — Fase 1, 2 & 3 diimplementasikan (belum dirilis)** · Risiko: **HIGH / CRITICAL**
(workflow persetujuan, otorisasi, alur pembayaran Treasury, migrasi skema tabel keuangan,
perhitungan biaya PO).

## 1. Permintaan

Modul "Cost Register" diganti menjadi **Expense** dengan konsep:

1. Hanya Finance Manager & CEO.
2. Alur: diajukan Finance Manager → disetujui CEO → dibayar (& settle untuk CA) → Closed.
3. Tanpa budgeting (tidak memotong Budget Plan / `BudgetConsumption`).
4. COA fleksibel: akun kategori Variable Cost, Fixed Cost, atau Overhead.
5. Bisa terikat ke satu PO, banyak PO, atau Cost Center (Non-PO).
6. Mirip Cost Request: ada Cash Advance (CA) dan Non-CA, alur lebih singkat.

Keputusan pengguna (2026-11-10):

| # | Pertanyaan | Keputusan |
|---|---|---|
| K1 | Data Cost Register lama | Tetap di tabel `cost_registers`, diperluas (kolom baru nullable). Data lama tampil sebagai Expense Non-CA. |
| K2 | Alokasi multi-PO / cost center | Per baris rincian: tiap rincian memilih Akun + tujuan (PO atau Cost Center). |
| K3 | Settlement CA | Finance Manager input realisasi → CEO approve → Closed. Kelebihan = refund dicatat; kekurangan = Second Payment lewat Treasury sebelum Close. |
| K4 | Pembayaran | Tetap lewat batch Treasury (Daily Transaction). |

## 2. Kondisi saat ini (terverifikasi dari kode)

- Tabel `cost_registers`: header per **satu** PO (`project_id` NOT NULL, FK `cascadeOnDelete`),
  `type` ENUM(`FIXED_COST`,`OVERHEAD`) NOT NULL, `amount`, status ENUM
  `DRAFT, VERIFIED, APPROVED, QUEUED, CLOSED, CANCELLED`.
- `cost_register_details`: akun (dibatasi kategori Fixed Cost/Overhead di `create()`),
  deskripsi, qty, harga, total. Tidak punya tujuan PO/cost center.
- Peran: `CostRegister::SUBMIT_ROLES = superadmin, finance, finance_manager`;
  `APPROVE_ROLES = superadmin, approver`. Route `menu:cost-register` + `role:`.
- Treasury: `daily_transaction_items.cost_register_id`; batch dibayar → register
  `QUEUED → CLOSED` (`DailyTransactionController::markPaid`). Tidak ada tahap PAID/settle.
- Biaya PO: `Project::fixedCostTotal()` / `overheadCostTotal()` = SUM `amount` header per
  `type`, **semua status** (termasuk DRAFT/CANCELLED) — perilaku lama, lihat §6.
  `ReportController` (laporan PO) juga menjumlah per `type`.
- Todo List: antrean `costRegisterApprove` (VERIFIED) & `costRegisterBatch` (APPROVED).
- Tab "Alokasi Overhead (Batch)" membuat banyak register sekaligus (`batch_code`).

## 3. Desain usulan

### 3.1 Alur status

```
Non-CA : DRAFT → VERIFIED("Submitted") → APPROVED → QUEUED("Process") → CLOSED
CA     : DRAFT → VERIFIED → APPROVED → QUEUED → PAID
           → SETTLE_SUBMITTED (FM input realisasi) → [CEO approve]
              ├─ selisih ≤ 0 (pas / refund dicatat)        → CLOSED
              └─ kurang bayar → Second Payment PENDING → Treasury bayar → CLOSED
Batal  : DRAFT/VERIFIED/APPROVED → CANCELLED ; CEO "Revisi" VERIFIED → DRAFT
CEO tolak settlement → kembali PAID (FM perbaiki realisasi)
```

Status baru di ENUM: `PAID`, `SETTLE_SUBMITTED` (+ nilai lama tetap). Penambahan status = HIGH.

### 3.2 Skema (migrasi baru, additive + 2 MODIFY kolom)

`cost_registers`:
- `+ expense_no` varchar nullable unique (dari `DocumentSequence`, mis. `EXP/2026/11/0001`; data lama kosong).
- `+ is_cash_advance` boolean default false.
- `+ settled_amount`, `refund_amount`, `shortfall_amount` BIGINT UNSIGNED nullable.
- `+ settle_submitted_by/at`, `settle_approved_by/at` (FK users, nullOnDelete).
- `MODIFY project_id` → **nullable**; FK diganti `cascadeOnDelete` → **RESTRICT** (§5 CLAUDE.md).
  Diisi hanya untuk data lama; Expense baru memakai tujuan per rincian.
- `MODIFY type` → **nullable** (data lama tetap FIXED_COST/OVERHEAD; Expense baru NULL,
  kategori dari rincian).
- `MODIFY status` ENUM + `PAID`, `SETTLE_SUBMITTED`.

`cost_register_details`:
- `+ project_id` nullable FK RESTRICT, `+ cost_center_id` nullable FK RESTRICT
  (tepat salah satu wajib untuk Expense baru — validasi controller).
- `+ cost_category` varchar(20) nullable — snapshot kategori akun saat disimpan
  (Variable Cost / Fixed Cost / Overhead), supaya laporan tidak berubah jika kategori akun
  di master diubah kemudian.

Tabel baru:
- `cost_register_settlement_details` (rincian realisasi CA: akun, tujuan PO/CC, kategori, deskripsi, qty, harga, total).
- `cost_register_settlement_attachments` (disk `secure`, lewat `FileAccess`).
- `cost_register_second_payments` (amount, status PENDING/PAID, paid_by/at, notes).
- `daily_transaction_items + cost_register_second_payment_id` nullable FK RESTRICT.

Data lama: **tidak ada UPDATE data**. Semua baris lama → `is_cash_advance = 0`, kolom baru NULL.

### 3.3 Otorisasi

- `SUBMIT_ROLES = ['superadmin', 'finance_manager']` (role `finance` **dicabut** dari buat/ajukan/batal).
- `APPROVE_ROLES = ['superadmin', 'approver']` (CEO) — juga untuk approve settlement.
- `SETTLE_ROLES = ['superadmin', 'finance_manager']`.
- Lihat daftar/detail: tetap `menu:cost-register` (kunci menu dipertahankan agar
  `role_menu_items` tidak perlu dimigrasi); label menu jadi "Expense".
- Treasury (role treasury/finance) tetap memproses batch, tidak bisa membuat/menyetujui Expense.
- Route `role:`, konstanta model, dan kondisi tombol Blade diselaraskan (§8).

### 3.4 Route & UI

- URL baru `/expense`; `/cost-register*` (GET) redirect 301 ke `/expense*`. Nama route internal
  `cost-register.*` dipertahankan (dipakai Treasury, Todo, Navigation, tests).
- Index: judul "Expense", kartu status tambah Fase CA (Paid / Settle), kolom tambah
  No. Expense, Jenis (CA/Non-CA), Tujuan (PO / multi-PO / Cost Center).
- Form create: toggle Cash Advance / Non-CA; rincian: Akun (semua kategori aktif,
  dikelompokkan) · Tujuan (PO atau Cost Center, searchable) · Deskripsi · Qty · Harga · Total.
  Tab "Alokasi Overhead (Batch)" **dihapus** (digantikan multi-PO per rincian).
  `Tipe` header dihapus (kategori dari akun).
- Detail drawer: rincian per tujuan, panel settlement (CA), Second Payment, log.
- Halaman/ modal Settle (FM) & tombol Approve/Tolak Settle (CEO).
- Treasury create batch: label "Expense"; sumber tambahan Second Payment Expense.
- Todo List: "Expense — Approval" (CEO), "Expense — Masukkan ke Batch", baru:
  "Expense — Input Settlement" (FM, status PAID), "Expense — Approval Settlement" (CEO).
- Sidebar via `config/navigation.php`; Panduan & docs diperbarui.

### 3.5 Biaya PO (perhitungan — butuh persetujuan eksplisit)

Usulan:
- Data lama (header `type` terisi, tanpa tujuan per rincian): perilaku **tidak diubah**.
- Expense baru: biaya PO = SUM `total` rincian yang `project_id` = PO tersebut, dikelompokkan
  `cost_category`; untuk CA memakai rincian **settlement** setelah CLOSED, sebelum itu rincian pengajuan.
- Hanya dihitung jika status ∉ {DRAFT, CANCELLED} (berbeda dari data lama yang menghitung semua status — lihat §6).
- Kategori **Variable Cost** dari Expense ditambahkan ke `Project::variableCostTotal()` → ini
  mengubah angka Variable Cost / Gross Profit PO. **Perlu konfirmasi.**
- Rincian ke Cost Center tidak masuk biaya PO mana pun.

## 4. Tahapan implementasi (usul 2 fase, masing-masing bisa diuji & di-deploy terpisah)

**Fase 1 — Expense Non-CA** (rename, peran, COA fleksibel, multi-PO/CC)
1. Migrasi skema §3.2 bagian `cost_registers` (expense_no, is_cash_advance, nullable project_id/type, FK restrict) & `cost_register_details`.
2. Model `CostRegister`: konstanta peran/status, label, helper tujuan; `CostRegisterDetail` relasi project/costCenter.
3. Controller: create/store (validasi tujuan per rincian, DocumentSequence), hapus storeBatch, export ikut kolom baru.
4. Route `/expense` + redirect; navigation; Blade index/create/detail.
5. `Project::fixedCostTotal/overheadCostTotal` + `ReportController` membaca rincian Expense baru (§3.5).
6. Todo label; tests `CostRegisterWorkflowTest`, `MenuAccessFinanceModulesTest`, `TodoListFinanceQueueTest` disesuaikan + test baru (finance ditolak, multi-PO, cost center).

**Fase 2 — Expense Cash Advance + Settlement** (CRITICAL: alur uang Treasury)
1. Migrasi status ENUM, kolom settlement, tabel settlement/second payment, FK di `daily_transaction_items`.
2. `DailyTransactionController::markPaid`: Expense CA `QUEUED → PAID`; Second Payment Expense `PENDING → PAID`; unbatch/reject untuk sumber baru.
3. Controller settle (FM), approve/tolak settle (CEO) memakai `TransitionsStatus`-style row lock.
4. UI settlement, Todo antrean baru, test alur CA lengkap (pas, refund, kurang bayar).

## 5. File terdampak (perkiraan)

Migrasi baru (2–3), `app/Models/CostRegister*.php`, model baru settlement/second payment,
`CostRegisterController`, `DailyTransactionController`, `DailyTransactionItem`, `DailyTransaction`,
`Project`, `ReportController`, `TodoService`, `TodoListController`, `FileAccess`,
`routes/web.php`, `config/navigation.php`, `config/panduan.php`,
`resources/views/cost-register/*`, `daily-transaction/*`, `todo-list/index`, `reports/*`,
`panduan/topics/cost-register`, `tests/Feature/*`, `docs/*`.

## 6. Risiko & temuan

- **Perhitungan PO berubah** untuk Expense baru (§3.5) — HIGH.
- **Alur bayar Treasury** berubah (PAID untuk CA, Second Payment baru) — CRITICAL.
- **Role `finance` kehilangan akses buat/ajukan** — pengguna finance yang sekarang menginput Cost Register tidak bisa lagi.
- Draft/Verified lama milik role finance tetap ada; FM yang melanjutkan.
- Tab Alokasi Overhead (Batch) hilang; `batch_code` data lama tetap.
- Temuan lama (tidak diubah tanpa persetujuan): `fixedCostTotal/overheadCostTotal` menghitung
  register DRAFT & CANCELLED; FK `cost_registers.project_id` dan
  `daily_transaction_items.cost_register_id` masih `cascadeOnDelete` (usul ganti RESTRICT pada migrasi Fase 1).
- Migrasi wajib dijalankan manual di host sebelum kode di-upload (kolom nullable dulu).

## 7. Keputusan tambahan (2026-11-10)

1. Kategori biaya per rincian = otomatis dari kategori akun (snapshot `cost_category`).
2. Expense kategori Variable Cost menambah Variable Cost PO.
3. Biaya PO dari Expense baru hanya dihitung bila status ≠ DRAFT/CANCELLED.
4. Role `finance` dicabut; yang terlibat hanya `finance_manager` dan CEO (`approver`).
5. Dikerjakan 2 fase; Fase 1 dulu.

## 8. Catatan implementasi Fase 1

- URL `/expense` (redirect 301 dari `/cost-register` & `/cost-register/create`); nama route,
  menu key, tabel, dan model tetap `cost-register` / `CostRegister`.
- Header Expense baru: `project_id` dan `type` NULL; nomor `EXP-YYMM-0001`.
- `config/permissions.php` seed_roles diselaraskan dengan route. Tabel `role_permissions`
  (isi lama, mode shadow) masih memuat `finance` — sinkronisasi lewat `security:sync-permissions --apply`
  adalah langkah manusia bila mode shadow diaktifkan.
- Baris `menu_items` key `cost-register` di DB masih berlabel "Cost Register" / route
  `/cost-register` (sidebar & modal Menu Access membaca `config/navigation.php`, jadi tidak
  memengaruhi tampilan). Tidak diubah karena butuh migrasi data.
- Menu Access `cost-register` untuk role `finance` tidak dicabut otomatis: finance masih bisa
  MELIHAT daftar bila dicentang, tetapi tidak bisa membuat/mengajukan/menyetujui. Cabut
  centangnya di Menu Access bila finance tidak boleh melihat sama sekali.

## 9. Catatan implementasi Fase 2

- Migrasi `2026_11_10_000200_expense_fase2_cash_advance_settlement.php` (jalankan SETELAH
  `000100`). Status ENUM + `PAID`, `SETTLE_SUBMITTED`, **`SETTLE_APPROVED`** — status ketiga ini
  tambahan dari rencana §3.1: menandai settlement sudah disetujui tetapi kekurangan bayar belum
  dibayar Treasury (CR memakai pola serupa lewat SECOND_APPROVED).
- Expense induk TIDAK berubah status saat Second Payment-nya dibatch/di-submit (beda dengan CR
  yang induknya pindah ke QUEUED); langsung CLOSED saat batch dibayar.
- Refund (kelebihan) hanya dicatat di `refund_amount`; tidak ada alur pengembalian dana ke kas.
  **Perlu konfirmasi** bila refund harus dicatat sebagai penerimaan kas.
- Todo List: antrean baru `expenseSettleInput` (FM) dan `expenseSettleApprove` (CEO). Second
  Payment Expense tidak punya antrean Todo sendiri untuk Treasury; muncul di halaman Buat Batch.
- File bukti realisasi: folder `cost_register_settlement_attachments`, dibuka pemegang menu
  Treasury ATAU menu Expense (`FileAccess`). Lampiran pengajuan Expense (`cost_register_attachments`)
  tetap hanya menu Treasury — perilaku lama, potensi perbaikan terpisah.
  **Sudah diperbaiki di §11 R3.**
## 10. Fase 3 — Rejected, multi-periode, alokasi Fixed Cost / Overhead (2026-11-10)

Keputusan pengguna:

| # | Hal | Keputusan |
|---|---|---|
| F1 | Rejected | CEO Reject (catatan wajib) → REJECTED; FM **Revisi** (→ DRAFT, diedit & diajukan ulang) atau **Batalkan** (→ CANCELLED). Menggantikan "Minta Revisi". |
| F2 | Settle Rejected | CEO tolak settlement → SETTLE_REJECTED; FM memperbaiki & mengajukan ulang. |
| F3 | Multi-periode | Periode awal–akhir (bulan); bagi rata (sisa rupiah ke bulan awal) atau manual (tiap bulan > 0, jumlah = nilai). Tabel `cost_register_periods`. Settlement CA disetujui → periode diskala ulang proporsional ke nilai realisasi. |
| F4 | Variable Cost | Wajib ke satu PO. |
| F5 | Fixed Cost / Overhead | Satu PO, **seluruh PO tahun X** (PO dibuat / `created_at` tahun X), **PO terpilih** (min. 2), atau Cost Center. Dibagi `nilai × (Nilai PO / Total Nilai PO)` dengan Nilai PO = `projects.value`. |
| F6 | PO penerima | Hanya PO disetujui (EARLY_START s.d. CLOSED, `Project::EXPENSE_ALLOCATION_STATUSES`) dan bernilai > 0. |
| F7 | Penguncian | **Selalu dihitung ulang** saat dibaca (`App\Support\ExpenseAllocation`); PO baru / perubahan nilai / status menggeser porsi. |

Implementasi:
- Migrasi `2026_11_10_000300_expense_periods_allocation_rejected.php` (setelah `000200`).
- Rincian (`cost_register_details`, `cost_register_settlement_details`): `allocation` (po/annual/selected/cc),
  `allocation_year`; pivot `cost_register_detail_projects` / `cost_register_settlement_detail_projects`.
- Pembulatan: metode sisa terbesar, jumlah porsi = nilai rincian (tanpa rupiah hilang).
- Status yang dihitung sebagai biaya PO: semua kecuali DRAFT, **REJECTED**, CANCELLED.
  **Dicabut oleh §11 (audit alur).**
- UI: kolom rincian Harga Satuan → Qty → Total; Beneficiary searchable; Rekening Sumber dihapus dari form
  (dipilih Treasury); Edit untuk Draft; index kolom bawaan No. Expense, Deskripsi, Beneficiary, Periode, Nilai,
  Tanggal + tombol **Kolom** (pilihan per pengguna, localStorage); stepper + Rejected & Settle Rejected.
- Todo List: CEO "Reject" menggantikan "Kembalikan ke Draft"; antrean baru `expenseRejected` (FM);
  `expenseSettleInput` juga memuat SETTLE_REJECTED.

Batasan / perlu diketahui:
- Karena dihitung ulang, angka Fixed Cost / Overhead PO lama bisa berubah saat PO baru disetujui di tahun yang sama
  (sesuai keputusan F7).
- Bila suatu saat tidak ada PO penerima (mis. semua PO tahun itu dibatalkan), rincian itu tidak masuk PO mana pun.
  Saat disimpan, form menolak pilihan tanpa PO penerima.
- Kolom manual `projects.overhead` / `projects.fixed_cost` (Net Profit di daftar PO) TIDAK diubah oleh Expense —
  laporan PO (`ReportController`) memakai Expense. **Perlu konfirmasi** apakah daftar PO harus ikut memakai Expense.
  **Terjawab di §11 (dijumlahkan).**
- Pembebanan per bulan saat ini untuk tampilan & ekspor; belum ada laporan bulanan yang memakainya.

## 11. Revisi hasil audit alur Expense (2026-11-10)

Audit alur Expense atas permintaan pengguna menemukan dua hal yang mengubah angka keuangan
(R1, R2); keduanya disetujui pengguna dan dikerjakan. Menyusul: R3 (akses lampiran pengajuan)
dan perbaikan semaian penomoran dokumen.

Temuan audit yang **belum dikerjakan** dan masih terbuka: P3 (rincian target `po` tidak
memeriksa status PO), P5 (pembebanan multi-periode belum dipakai perhitungan apa pun),
P6 (cancel saat Expense masih di batch Draft, dan `addItem` tidak mendukung Expense),
P7 (`submit`/`approve`/`cancel` tanpa row lock), P8 (file lampiran yatim saat hapus Draft),
P9 (rentang tanggal default index menyembunyikan Expense bulan lalu), P10 (tidak ada plafon
nilai), P11 (refund kelebihan Cash Advance tanpa alur pengembalian kas).

Di luar modul Expense, audit yang sama menemukan: semaian `COUNT(*)` pada 8 pemanggil
`DocumentSequence::next()` lain (`CashFlow`, `CashReceiptHeader`, `DailyTransaction`,
`DeliveryNote`, `Invoice` ×2, `TroubleTicket`, `SettlementReceiptService`,
`TroubleTicketWorkflow`) dan ketidakcocokan `request_status_logs.acted_by` NOT NULL dengan
`BudgetRequest::addLog()` yang menulis `$user?->id`. Keduanya **belum dikerjakan**.

### R1 — Biaya PO hanya setelah dananya keluar (menggantikan §10 baris "Status yang dihitung")

Keputusan pengguna: **hanya setelah dibayar**.

`CostRegister::PO_COST_EXCLUDED_STATUSES` diganti `PO_COST_COUNTED_STATUSES` =
`PAID, SETTLE_SUBMITTED, SETTLE_REJECTED, SETTLE_APPROVED, CLOSED`.

- Non-CA: praktis hanya CLOSED (QUEUED → CLOSED saat batch Treasury dibayar).
- CA: mulai PAID, termasuk SETTLE_REJECTED (uang sudah keluar, realisasinya yang diperbaiki).
- DRAFT, VERIFIED ("Submitted"), APPROVED, QUEUED, REJECTED, CANCELLED tidak dihitung.
- Alasan: aturan lama membuat pengajuan yang belum disetujui CEO sudah mengubah biaya PO, dan
  angkanya bergoyang mengikuti antrean approval. Aturan baru sejalan dengan Variable Cost PO
  yang hanya menghitung Cost Request yang sudah cair (`BudgetRequest::scopeDisbursed()`).
- Data Cost Register lama (header `type`) tetap dihitung seperti sebelumnya: semua status.

**Dampak data:** angka Variable/Fixed/Overhead dan Net Profit PO akan **turun** untuk PO yang
punya Expense berstatus VERIFIED/APPROVED/QUEUED. Tidak ada migrasi — pembagian selalu
dihitung ulang saat dibaca (F7).

### R2 — Fixed Cost & Overhead PO satu sumber, komponennya dijumlahkan

Keputusan pengguna: **dijumlahkan** (kolom manual tetap dipakai, tidak dihapus).

`Project::fixedCostTotal()` / `overheadCostTotal()` = kolom manual `projects.fixed_cost` /
`projects.overhead` + Cost Register lama (per `type`) + Expense (`ExpenseAllocation`).
Semua halaman kini memakainya: daftar PO, Export PO, modal tab Cost (`project.show-json`),
Finance Summary Executive, Laporan PO.

- Sebelumnya daftar PO, Export PO, modal Cost, dan Finance Summary hanya memakai kolom manual
  (Expense tidak terlihat), sedangkan Laporan PO hanya memakai Cost Register lama + Expense
  (kolom manual tidak terlihat) — empat halaman, dua angka berbeda.
- Modal tab Cost juga melewatkan Expense kategori Variable Cost, sehingga angkanya lebih kecil
  daripada kolom "Variable Cost" di daftar PO untuk PO yang sama. Diperbaiki, dan ditambah
  baris **"Expense (Variable Cost)"** supaya Total Request − Refund + Expense = Variable Cost.
- Performa: `Project::preloadCostRegisterCosts()` mengisi cache biaya Expense + Cost Register
  lama untuk seluruh daftar dalam 4 query (dipanggil `preloadFinancials()` dan Finance Summary),
  jadi daftar PO tidak menambah query per baris.

**Konsekuensi operasional (wajib diketahui pengguna):** karena dijumlahkan, biaya yang sudah
dicatat lewat Expense **tidak boleh** diisi ulang di kolom manual Overhead / Fixed Cost pada
form PO — akan terhitung dua kali. Net Profit di Laporan PO akan **turun** sebesar kolom
manual yang sebelumnya tidak ikut dihitung di sana.
### R3 — Lampiran pengajuan Expense bisa dibuka pemegang menu Expense

Temuan P4. `App\Services\FileAccess` hanya mengizinkan folder
`cost_register_attachments` untuk pemegang menu `finance-daily-transaction` (Treasury),
padahal drawer Expense menampilkan daftar lampirannya kepada setiap pemegang menu
`cost-register`. Akibatnya CEO/approver yang tidak dicentang menu Treasury melihat
tautan lampiran yang menghasilkan 403 — "tombol terlihat tapi 403" yang dilarang
CLAUDE.md §8 — dan menyetujui Expense tanpa bisa membuka buktinya.

Aturannya kini sama dengan bukti realisasi di baris berikutnya:

```php
'cost_register_attachments' => $this->allowsMenu('finance-daily-transaction')
    || $this->allowsMenu('cost-register'),
```

- Ini **bukan** aturan baru, melainkan penerapan prinsip yang sudah ditulis di blok
  komentar `FileAccess`: keterlihatan berkas = keterlihatan halaman modulnya.
- Yang melebar hanya pemegang menu `cost-register`; pengguna tanpa salah satu menu
  tetap 403, dan bawaan `default => false` tidak disentuh.
- Siapa yang punya menu `cost-register` tetap diatur lewat Role > Menu Access. Bila
  role `finance` masih dicentang menu itu (lihat §8), ia kini juga bisa membuka
  lampiran pengajuan — cabut centangnya bila tidak dikehendaki.

Test: `tests/Feature/SecureFileAccessTest.php` — pemegang menu Expense bisa membuka
lampiran pengajuan & bukti realisasi, pemegang menu Treasury tetap bisa, dan pengguna
tanpa kedua menu ditolak.
