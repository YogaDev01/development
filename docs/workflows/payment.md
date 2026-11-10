# Workflow — Payment (Cost Payment & Treasury batch)

## Cost Payment (every CR type that is not Cash Advance)

Same request leg as Cash Advance (DRAFT → SUBMITTED → CHECKED → VERIFIED →
APPROVED → QUEUED), but submit requires an item attachment on every
non-TAKEOUT detail, and on batch mark-paid the CR goes **QUEUED → CLOSED**
(no settlement).

## Treasury batch (`daily_transactions`)

Types: `CASH_OUT` (CR / Cost Register / Second Payment items), `TOP_UP`
(transfer between company accounts, no items).

| From → To | Action | Roles |
|-----------|--------|-------|
| — → DRAFT | `store` (since 29 Oct 2026). Items are reserved (`DailyTransaction::ACTIVE_STATUSES` includes DRAFT) but CR / Cost Register / Second Payment parent **do not change status** | superadmin, finance, finance_manager, treasury |
| DRAFT (edit) | `add-item` (no status change), `draft-remove-item` (item row deleted), `DELETE daily-transaction/{id}` (draft deleted; sources untouched) | superadmin, finance, finance_manager, treasury |
| DRAFT → PENDING_APPROVER | `submit`: locks rows, re-checks CR APPROVED / register SUBMITTED / SP PENDING + parent SECOND_APPROVED, then CR → QUEUED (`batched`), register → QUEUED, SP parent → QUEUED; notifies approvers. CEO cannot approve a DRAFT. Payroll Run batches still start at PENDING_APPROVER | superadmin, finance, finance_manager, treasury |
| PENDING_APPROVER (edit) | `add-item` (CRs move APPROVED → QUEUED) | superadmin, finance, finance_manager, treasury |
| PENDING_APPROVER → DONE | `approve` | superadmin, approver |
| PENDING_APPROVER → DONE / REJECTED (massal) | `bulk-approve` / `bulk-reject` (notes wajib) dari daftar Treasury: per batch menjalankan `approve` / `rejectBatch`; maks 50; `expected[id]` melewati batch yang statusnya berubah; Draft dilewati | superadmin, approver |
| PENDING_APPROVER → REJECTED | `reject-batch` (CRs back to APPROVED) | superadmin, approver |
| Per item, batch PENDING_APPROVER | `reject-item` = **Takeout**: CR QUEUED → APPROVED (log `batch_takeout`; Second Payment parent → SECOND_APPROVED; Cost Register → SUBMITTED) | superadmin, approver |
| Per item, batch PENDING_APPROVER | `reject-cr-item` = **Reject**: CR QUEUED → REJECTED (`batch_item_rejected`); Second Payment: pending SP deleted, parent CR → SETTLE_REJECTED (`second_payment_rejected`); Cost Register not allowed | superadmin, approver |
| DONE → PAID | `mark-paid` (≥1 attachment, optional charge) | superadmin, finance, finance_manager, treasury |

Effects of mark-paid: see `docs/business-rules.md#treasury-batch`.

Risk: `markPaid` checks `isDone()` before the transaction without a row lock.

## Expense (dulu Cost Register)

Sejak 2026-11-10 (rencana `docs/plans/2026-11-10-expense-module.md`, Fase 1). Tabel tetap
`cost_registers`; URL `/expense`, nama route tetap `cost-register.*`, menu key `cost-register`.

- Status: DRAFT → VERIFIED ("Submitted") → APPROVED → QUEUED ("Process") → CLOSED,
  + CANCELLED (DRAFT/VERIFIED/REJECTED/APPROVED). CEO Reject: VERIFIED → REJECTED (catatan wajib);
  FM Revisi: REJECTED → DRAFT (bisa diedit) atau Batalkan. Draft bisa diedit (Fase 3).
  QUEUED/CLOSED diubah Treasury (`DailyTransactionController`): batch dibayar → CLOSED.
- Peran (`CostRegister::SUBMIT_ROLES` / `APPROVE_ROLES` = route `role:`):
  buat/ajukan/batal/hapus Draft = superadmin, finance_manager; approve/revisi =
  superadmin, approver. Role `finance` dicabut. Melihat halaman = Menu Access `cost-register`.
- Tanpa budgeting. Rincian (`cost_register_details`): akun (semua kategori aktif), tujuan
  tepat satu dari `project_id` (PO) atau `cost_center_id`, `cost_category` = snapshot
  kategori akun. Nomor `EXP-YYMM-0001` dari `DocumentSequence` (`CostRegister::nextExpenseNo()`).
- Biaya PO (Fase 3, `App\Support\ExpenseAllocation`, dihitung ulang setiap dibaca):
  **hanya setelah dananya keluar lewat Treasury** — `CostRegister::PO_COST_COUNTED_STATUSES`
  = PAID, SETTLE_SUBMITTED, SETTLE_REJECTED, SETTLE_APPROVED, CLOSED (revisi audit 2026-11-10;
  sebelumnya semua status kecuali DRAFT/REJECTED/CANCELLED, sehingga pengajuan yang baru
  "Submitted" sudah mengubah angka PO). Non-CA praktis = CLOSED; CA mulai dari PAID.
  Sejalan dengan Variable Cost PO yang hanya menghitung Cost Request yang sudah cair.
  Variable Cost wajib ke satu PO. Fixed Cost / Overhead: satu PO, seluruh PO
  yang dibuat pada tahun X, PO terpilih, atau Cost Center; tahunan & terpilih dibagi
  nilai × (Nilai PO / Total Nilai PO) atas PO disetujui (EARLY_START–CLOSED) bernilai > 0.
  Hasil masuk `Project::variableCostTotal()` / `fixedCostTotal()` / `overheadCostTotal()` dan laporan PO.
- Fixed Cost & Overhead PO = **satu sumber** `Project::fixedCostTotal()` / `overheadCostTotal()`
  (revisi audit 2026-11-10): kolom manual `projects.fixed_cost` / `projects.overhead` +
  Cost Register lama (per `type`) + Expense. Dipakai daftar PO, Export PO, modal tab Cost
  (`project.show-json`), Finance Summary Executive, dan Laporan PO — semuanya kini menampilkan
  angka yang sama. Karena **dijumlahkan**, biaya yang sudah dicatat lewat Expense tidak boleh
  diisi ulang di kolom manual PO (akan terhitung dua kali). Cache massal:
  `Project::preloadCostRegisterCosts()` (4 query untuk seluruh daftar).
  Modal tab Cost juga menambahkan baris "Expense (Variable Cost)" supaya
  Total Request − Refund + Expense = Variable Cost.
- Periode: nilai bisa dibebankan ke beberapa bulan (`cost_register_periods`, bagi rata / manual).
- Data Cost Register lama (header `project_id` + `type` FIXED_COST/OVERHEAD, kode CREG-xxxxx)
  tetap dihitung seperti sebelumnya (semua status, per `type`).
- **Expense Cash Advance** (Fase 2, migrasi `2026_11_10_000200`, `is_cash_advance = 1`):
  QUEUED → PAID (mark-paid batch) → SETTLE_SUBMITTED (Finance Manager input realisasi +
  bukti, `cost_register_settlement_details` / `_attachments`) → CEO:
  - setuju, realisasi ≤ dibayar → CLOSED (`refund_amount` dicatat saja, tidak ada alur pengembalian dana);
  - setuju, realisasi > dibayar → SETTLE_APPROVED + `cost_register_second_payments` PENDING →
    batch Treasury (`expense_second_payment_ids`; baris item mengisi `cost_register_id` +
    `cost_register_second_payment_id`) → mark-paid: Second Payment PAID, Expense CLOSED;
  - tolak (catatan wajib) → SETTLE_REJECTED (FM memperbaiki & mengajukan ulang).
  Submit batch tidak mengubah status induk; takeout / batch ditolak hanya mengeluarkan barisnya
  (Second Payment tetap PENDING). Reject item tidak berlaku (sama seperti Expense biasa).
  Biaya PO: setelah settlement disetujui, biaya dihitung dari rincian realisasi, bukan pengajuan.
  Transisi settlement memakai kunci baris + cek status di dalam transaksi.

## Payroll Run

Statuses DRAFT, SUBMITTED, VERIFIED, APPROVED, PAID, CLOSED, REJECTED
(`PayrollRunController`, `menu:payroll-run`). Role gates per step: Needs confirmation.
