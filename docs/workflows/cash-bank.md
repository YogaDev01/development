# Cash & Bank (Fase 1 — dibuat 2026-11-13)

Lapisan kontrol di atas modul kas yang sudah ada. **Tidak ada ledger kedua.**

## Sumber data
| Hal | Sumber |
|---|---|
| Rekening | `source_funds` (kolom baru: `code`, `account_type`, `account_holder`, `branch`, `currency`, `description`) |
| Saldo awal | `cash_opening_balances` (satu per rekening, maker-checker) |
| Pergerakan kas | `CashFlowService`: Cash Receipt (IN), Treasury `PAID` bertipe `CASH_OUT` (OUT), Cash Flow `POSTED` (manual) |
| Transfer internal | Treasury `TOP_UP` `PAID`: hanya memengaruhi saldo per rekening (`CashFlowService::accountLedger`), tidak memengaruhi total Cash In/Out |

## Rumus (`CashBalanceService`)
`Saldo = Saldo Awal (APPROVED) + Cash In − Cash Out`.
- Transaksi sebelum tanggal efektif saldo awal tidak dihitung.
- Rekening tanpa saldo awal approved dihitung dari 0 dan muncul di Attention Required.
- Draft/pending/rejected/cancelled/belum dibayar tidak masuk ledger.
- Baris tanpa `source_fund_id` dilaporkan terpisah (tidak ikut saldo rekening mana pun).

## Menu & akses
- Grup sidebar "Cash & Bank": Cash Overview, Treasury, Cash Receipt, Cashflow, Forecast, Bank Accounts (URL lama tidak berubah). Reconciliation menyusul (Fase 3).
- Kunci menu baru `cash-overview`, `bank-accounts` (migrasi `2026_11_13_000900`) — **tidak diberikan ke role mana pun**; atur lewat Menu Access.
- Tulis (rekening, saldo awal) = `superadmin`, `finance_manager` (`BankAccountController::WRITE_ROLES`, route, dan Blade).
- Verifikasi saldo awal: pengguna lain, bukan pembuat. Saldo awal approved terkunci; koreksi lewat Cash Adjustment (Fase 2).

## Fase 2 — Internal Transfer, Cash Adjustment, Transaction Viewer
- Tabel `cash_transfers` dan `cash_adjustments` (status PENDING → APPROVED/REJECTED/CANCELLED; nomor dari `DocumentSequence`: `TRF/yyyy/nnnn`, `ADJ/yyyy/nnnn`). Dokumen tidak dihapus.
- Hanya APPROVED yang dibaca `CashFlowService`: transfer menjadi dua sisi (OUT asal, IN tujuan; hanya untuk saldo per rekening), **biaya transfer** menjadi pengeluaran nyata terpisah dari rekening asal, adjustment menjadi IN/OUT di ledger konsolidasi.
- Kontrol: buat/batal = `superadmin, finance_manager, finance`; setujui/tolak = `superadmin, finance_manager`; pembuat tidak boleh menyetujui; persetujuan dalam satu transaksi DB dengan kunci baris (tidak bisa disetujui dua kali); saldo asal (nominal + biaya, atau adjustment OUT) tidak boleh menjadi negatif; tanggal tidak boleh sebelum saldo awal rekening.
- Top Up Treasury tetap berjalan terpisah (tidak disentuh); keduanya tampil di Cash Transactions dengan penanda "Transfer".
- Menu baru: `cash-transactions`, `cash-transfers`, `cash-adjustments` (tanpa pemberian otomatis ke role).
- Belum ada: reversal dokumen APPROVED (pembatalan hanya untuk PENDING), jadi koreksi dokumen approved = adjustment baru.

## Fase 3 — Reconciliation
- Tabel: `cash_reconciliations` (REC/yyyy/nnnn), `bank_statement_imports`, `bank_statement_lines`, `cash_reconciliation_matches`, `cash_reconciliation_logs`. Tidak mengubah ledger.
- Alur: DRAFT (impor CSV, cocokkan) → SUBMITTED → APPROVED (periode terkunci). Semua perubahan data hanya pada DRAFT, dalam transaksi DB dengan kunci baris. Buka ulang hanya `superadmin` + alasan + log.
- CSV: kolom `date, description, reference, debit (keluar), credit (masuk), balance`; pratinjau → validasi → deteksi duplikat (hash per rekening, mempertimbangkan baris kembar) → impor baris OK; maks. 1.000 baris/impor; template tersedia.
- Pencocokan: otomatis 1-1 (arah & nominal sama persis, selisih ≤ 3 hari, referensi diprioritaskan) dan manual (satu bank ↔ banyak baris TESYS → Partially Matched bila nominal beda). Kunci unik (rekening, ledger_key) menjamin satu transaksi hanya sekali dicocokkan; baris bank unik per rekening.
- Ringkasan: Saldo bank disesuaikan = saldo rekening koran + outstanding deposits − outstanding payments; Saldo TESYS disesuaikan = saldo ledger + kredit bank belum tercatat − debit bank belum tercatat; Selisih = keduanya. Submit tanpa selisih nol / semua baris cocok wajib alasan pengecualian; approve = pengecualian berizin. Penyetuju ≠ pembuat ≠ pengirim.
- Kunci periode: Internal Transfer & Cash Adjustment baru bertanggal di periode APPROVED ditolak. **Treasury dan Cash Receipt belum dikunci** (perlu persetujuan, mengubah modul lama).
- Syarat angka benar: saldo awal rekening ditetapkan pada awal rekening koran pertama; outstanding mencakup semua baris ledger belum cocok sampai akhir periode.

## Fase 4 — Cashflow, Forecast, Laporan
- **Cashflow** (`/cash-management`): menampilkan saldo awal & akhir periode dari `CashBalanceService` (sumber sama dengan Cash Overview), mengikuti filter rekening. Selisih terhadap saldo awal + net dijelaskan di halaman (transaksi tanpa rekening, sebelum saldo awal, sisi transfer).
- **Forecast** — `CashReportService::outlook()` memisahkan tiga lapis yang tidak pernah dicampur: A. kas aktual (saldo ledger); B. terkonfirmasi masa depan = piutang invoice (`Invoice::UNPAID_STATUSES`, "diterima" via `Invoice::hitungDiterima`) jatuh tempo ≤ horizon + batch Treasury `CASH_OUT` berstatus `DONE` (scheduled payments) + CR `APPROVED` yang belum masuk batch (kolom `amount`, item INCLUDED); C. sisa forecast ACTIVE (amount − actual). Piutang overdue/di luar horizon dan batch `PENDING_APPROVER` ditampilkan tetapi TIDAK masuk proyeksi. Ringkasan di `/forecast` hanya untuk pemegang menu `cash-overview`.
- **Laporan** `/cash-reports/{position|bank-balance|transactions|in-out|cashflow|reconciliation|transfers|adjustments|forecast}`: filter tanggal & rekening (horizon hari untuk forecast), CSV (UTF-8 BOM, terbuka di Excel), cetak (`?print=1`). Lihat = menu `cash-reports`; CSV/cetak = `superadmin, finance_manager, finance` (`CashReportController::EXPORT_ROLES`).

## Perlu konfirmasi
- Apakah biaya (`charge`) pada Top Up merupakan pengeluaran kas terpisah (saat ini diabaikan, sama dengan Cashflow existing).
- `cash_flows.total_in/total_out/amount` bertipe `integer` (bukan BIGINT) — tidak diubah.
- Cash Receipt tidak punya status konfirmasi; semua baris dihitung POSTED (perilaku existing).
