# Accounting (General Ledger) — status & aturan

Branch `v3.4.0`. Fase 1 (fondasi) selesai ditulis; Fase 2–5 belum.

## Keputusan desain (disepakati 2026-11-13/14)
- **Satuan uang = BIGINT SEN** (rupiah × 100) di SEMUA tabel akuntansi. Alasan: bank
  memotong/menambah dengan sen; laporan harus sama dengan rekening koran. Tetap
  integer (tidak ada decimal/float). Hanya modul Accounting yang memakai sen —
  tabel lain tetap rupiah. Konversi hanya lewat `App\Support\AccountingMoney`.
- Alur jurnal: **Draft → Submitted → Posted**. Finance manager me-review &
  memposting; revisi = kembali ke Draft. Draft boleh diedit/dihapus (soft delete);
  Posted tidak pernah diubah — koreksi lewat jurnal pembalik.
- **Tanggal jurnal ≠ tanggal input.** `journal_date` boleh backdate selama
  periodenya OPEN; laporan memakai `journal_date`, bukan `created_at`.
- Klasifikasi beban/aset/prepaid dilakukan **saat input jurnal**, bukan di data
  transaksi lama. Account Mapping hanya memberi akun usulan.
- Cash Advance **tidak** dijurnal di Fase 2 (menyusul, mis. saat CR CLOSED /
  jurnal Uang Muka). Saldo piutang awal lewat **jurnal saldo awal manual**.

## Fase 1 (ada di kode)
Tabel: `coa_accounts`, `accounting_periods`, `account_mappings`, `journals`,
`journal_lines`, `journal_sources` (kunci anti-dobel `active_key` UNIQUE),
`accounting_audit_logs`. Menu: `accounting-coa`, `accounting-mapping`,
`accounting-period` (hak diberikan lewat Menu Access; migrasi tidak memberi akses).
Perubahan data (CRUD COA/mapping/periode) hanya untuk role superadmin,
finance_manager, accounting; buka kembali periode: superadmin & finance_manager.

## Fase 2 (ada di kode, belum dijalankan/diuji)
- Menu `accounting-journal` (Journal Entries): tab Jurnal dan tab Transaksi Sumber.
- `App\Services\JournalGenerator`: jurnal Draft dari Treasury (1 jurnal per batch PAID,
  item Cash Advance / Second Payment dilewati; Top Up = Dr rekening tujuan, Cr rekening
  asal) dan Cash Receipt (per header bukan batal; detail lama tanpa header per detail).
- Cash Receipt: `total_amount` header = diterima + PPh. Dr Bank (total − PPh), Dr PPh
  dibayar di muka, Cr Piutang (alokasi + PPh), Cr Uang Muka Customer (sisa belum dialokasi).
- Mapping dipakai: Treasury `EXPENSE` (pembeda `request_type:ID` / `expense_type:X`, lalu umum),
  `BANK_CHARGE`; Cash Receipt `CUSTOMER_COLLECTION`, `CUSTOMER_ADVANCE`. (PPh tidak dijurnal.)
  Akun bank = COA yang tertaut ke rekening (Chart of Accounts). Mapping/tautan hilang = jurnal ditolak.
- Cut-over = tanggal mulai periode akuntansi paling awal; transaksi sebelum itu tidak dijurnal.
- Posting: pembuat ≠ pemosting (kecuali superadmin), periode Open, debit = kredit, akun aktif,
  dan nominal sumber harus masih sama dengan jurnal (`sourceDrift`).
- Anti-dobel: `journal_sources.active_key` UNIQUE; dilepas saat Draft dihapus.

## Belum / perlu konfirmasi
- Tanggal cut-over pembukuan (akuntan) — menahan Fase 2.
- Jurnal Treasury/Cash Receipt, GL, Trial Balance: Fase 2–3.
- Integrasi `UserActivityLog` (modul 'accounting') belum dipasang; jejak audit
  sementara di `accounting_audit_logs`.

## Fase 3 (ada di kode, belum dijalankan/diuji)
- Menu `accounting-ledger` (General Ledger) dan `accounting-trial-balance` (Trial Balance).
- `App\Services\LedgerService` membaca HANYA jurnal POSTED dan REVERSED (jurnal asal yang
  dibalik tetap dihitung; pembaliknya POSTED sehingga keduanya saling menetralkan). Draft
  dan Submitted tidak pernah memengaruhi saldo. Urutan/batas = `journal_date`, bukan `created_at`.
- GL: saldo awal, mutasi, saldo berjalan mengikuti saldo normal akun; filter periode/tanggal,
  project, cost center; nomor jurnal membuka jurnal asal; CSV dan cetak.
- Trial Balance: saldo awal/mutasi/saldo akhir, hierarki dengan roll-up induk dan tombol
  buka/tutup, filter tipe/kategori, CSV, cetak, tautan ke GL. Total dihitung dari nilai akun
  langsung (tanpa roll-up) dan ada peringatan bila debit ≠ kredit.
- CSV/cetak: superadmin, finance_manager, accounting. Pembatasan data per departemen/cabang
  belum ada (batasan Fase 3).

## Fase 4 (ada di kode, belum dijalankan/diuji)
- Menu: `accounting-income-statement`, `-balance-sheet`, `-cash-flow`, `-equity`
  (route `/accounting/statements/{income|balance|cash|equity}`), CSV dan cetak.
- `App\Services\FinancialStatementService`: seluruhnya dari buku besar (jurnal POSTED/REVERSED).
- Klasifikasi per akun COA (kolom baru `statement_section`, `cash_flow_activity`; kosong =
  bawaan menurut tipe akun). Laba Rugi: Pendapatan, Beban Pokok Layanan, Beban Operasional,
  Pendapatan/Beban Lain, Beban Pajak. Neraca: Aset/Liabilitas lancar vs tidak lancar, ekuitas
  (Modal, Laba Ditahan, Dividen, Lainnya). Akun beban penyusutan/keuangan ditempatkan
  akuntan lewat penempatan akun.
- Neraca: belum ada jurnal penutup tahun; laba tahun lalu dan laba berjalan dihitung dari akun
  pendapatan/beban (tahun fiskal = 1 Januari — ASUMSI, perlu konfirmasi). Ada cek Aset = Liabilitas + Ekuitas.
- Arus Kas: metode langsung dari buku besar. Kas = akun COA yang tertaut ke rekening Cash & Bank;
  baris lawan tiap jurnal kas menentukan aktivitas (Operasi/Investasi/Pendanaan); perpindahan
  antar-kas tidak muncul. Ada cek saldo kas akhir = buku besar.
- Perubahan Ekuitas: ekuitas awal, setoran modal, laba bersih, dividen, lainnya, ekuitas akhir;
  dicek terhadap Neraca.

## Fase 5 (ada di kode, belum dijalankan/diuji)
- Jurnal manual (`/accounting/journal/create`): General, Accrual, Adjustment, Reclassification,
  Depreciation, dan Jurnal Saldo Awal (OPENING — dipakai untuk saldo awal piutang dkk. pada cut-over).
  Alur Draft → Submitted → Posted, validasi, SoD, dan audit SAMA dengan jurnal dari sumber.
- Akrual: `auto_reverse_date` opsional; saat akrual diposting dibuat Draft pembalik bertanggal itu
  (tetap di-submit & diposting; pembuatnya = pemosting akrual sehingga orang lain yang memposting).
- Jurnal pembalik: dari jurnal Posted → Draft REVERSAL (debit/kredit ditukar, tidak bisa diedit).
  Saat pembalik diposting: jurnal asal → REVERSED, dan sumber transaksi asal dilepas
  (active_key = NULL) sehingga bisa dijurnal ulang sebagai jurnal pengganti. Jurnal pembalik
  tidak bisa dibalik lagi; satu jurnal hanya punya satu pembalik aktif.
- Journal Audit Trail (`/accounting/audit`, menu `accounting-audit`): filter objek, aksi, user,
  tanggal, nomor jurnal; nilai sebelum/sesudah; hanya baca.
- Migrasi `2026_11_14_000700`: kolom `journals.auto_reverse_date` + menu audit.

## Perbaikan hasil review (2026-11-14, belum dijalankan/diuji)
- **Cash Receipt**: jurnal HANYA mencatat uang yang diterima. Dr Bank = Σ diterima per invoice + sisa belum
  dialokasikan; Cr Piutang = Σ diterima; Cr Uang Muka Customer = sisa. PPh tidak dijurnal (tipe mapping
  WITHHOLDING_TAX dihapus dari katalog). Keterbatasan: header tidak menyimpan "kas diterima", jadi bila data
  alokasi di modul Cash Receipt diubah menjadi tidak sesuai kas, jurnal mengikuti data tersebut.
- **Sidik sumber** (`journal_sources.source_snapshot`): dibandingkan ulang saat Submit/Post; perubahan nominal,
  alokasi, status, pembatalan, atau penghapusan sumber memblokir dan menandai `needs_review`.
- **Daftar Transaksi Sumber**: hanya sejak tanggal mulai pembukuan, ada filter tanggal, dan peringatan
  "Data belum lengkap" bila terpotong 400 baris per jenis.
- **Input nominal**: koma = desimal, titik hanya ribuan; "1000.50" / input tidak valid ditolak (bukan dianggap 0).
- **Pemisahan tugas**: pemosting ≠ pembuat dan ≠ editor terakhir (`journals.last_edited_by`); superadmin dikecualikan.
  Pembalik otomatis akrual dibuat sistem (tanpa pembuat).
- **Periode**: tidak bisa ditutup selagi ada jurnal Draft/Submitted; periode lebih awal dari periode pertama
  butuh konfirmasi (menggeser cut-over).
- **Report lock**: kategori, penempatan laporan, aktivitas kas, rekening bank, dan induk akun terkunci bila akun
  punya mutasi di periode CLOSED/LOCKED. Buka kembali periodenya dulu untuk mengubah.
- **Cash Advance & biaya bank batch yang semuanya CA**: dijurnal manual (Jurnal Manual).
- CSV: sel teks dinetralkan dari formula injection (`App\Support\CsvSafe`); Buku Besar tampil maks. 1.000 baris.
- Daftar role Accounting dipusatkan di `AccessRoles::ACCOUNTING_*` (route + controller).
- Migrasi baru: `2026_11_14_000800` (journal_sources.source_snapshot, journals.last_edited_by).
