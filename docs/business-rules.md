# Business rules (verified only)

Every rule here is traced to code. Anything else is marked
**Needs confirmation.** Code is the source of current behavior; where comments
disagree with code, it is listed under *Discrepancies*.

Glossary of Indonesian/internal terms: `docs/GLOSSARY.md`.

## Domain map

| Requested domain | In TESYS | Main code |
|------------------|----------|-----------|
| Cash Advance | Cost Request with RequestType named "Cash Advance" | `BudgetRequest`, `BudgetRequestController`, `HandlesCrSettlement` |
| Payment (outgoing) | Cost Request (non-CA = "Cost Payment") + Treasury batch | same + `DailyTransactionController` |
| Treasury | Daily Transaction batches (CASH_OUT / TOP_UP), Cost Register | `DailyTransaction*`, `CostRegister*` |
| Settlement | CA realization + 3-step approval + Second Payment (expense claim) | `HandlesCrSettlement`, `BudgetRequestRealization`, `BudgetRequestSecondPayment` |
| Purchase | **Customer Purchase Orders** (`projects`, `project_po_numbers`) — revenue side, not procurement | `Project`, `ProjectController` |
| Petty Cash | No dedicated module found. **Needs confirmation** |
| Reimbursement | No dedicated module found; Cost Payment / Expense Claim may cover it. **Needs confirmation** |
| Cash Transaction | Cash Management (`cash_flows`), Treasury batches | `CashManagementController`, `CashFlowService` |
| Cash Receipt | Invoice payments (`cash_receipts`) + receipt headers with allocation (`cash_receipt_headers`) | `InvoiceCashReceiptController`, `CashReceiptController` |
| Invoice | `invoices` + Delivery Note | `InvoiceCashReceiptController`, `DeliveryNoteController`, `InvoiceCalculationService` |
| Approval | Per-module status chains + Todo List worklist | `TodoListController`, `BuildsCrWorklist` |
| Beneficiary | Payee master (bank account), linked to Master PIC | `BeneficiaryController`, `MasterPic` |
| Employee/User | `users` (login, role, access group), `employees` (HR), `master_pics` | |
| Reporting | Finance Summary, Reports, exports | `FinanceSummaryController`, `ReportController` |
| Closing | Period closing (monthly recap) | `ClosingController`, `ClosingService` |
| Budget | Budget Plan per project / cost center | `BudgetPlan*`, `BudgetConsumption` |
| Cost Control | Role `cost_control` may verify CRs; Cost Center master; Forecast | |

## Relationships

```
Project (PO) ─┬─ BudgetPlan ── BudgetPlanDetail ── RequestDetail
              │       │
Cost Center ──┘       └── BudgetRequest (CR) ── RequestDetail / Realization / SecondPayment
                                 │
                       DailyTransactionItem ── DailyTransaction (Treasury batch)
Project ── ProjectPoNumber ── Invoice ── CashReceipt / DeliveryNote
CashReceiptHeader ── allocations → Invoice
Closing ← ClosingService reads Treasury, Invoice, Collection, Cash Receipt…
```

## Confirmed rules

### Cost Request
- Statuses: `BudgetRequest::STATUSES` (15 values).
- Transitions: `BudgetRequest::getAllowedNextStatuses()`; executed with row
  lock via `TransitionsStatus`; each writes `request_status_logs`.
- Cash Advance detection by RequestType name (`cash advance`, `cash-advance`,
  `cashadvance`).
- Submit requires: CA → ≥1 global attachment; non-CA → every non-TAKEOUT
  detail has an item attachment.
- CA limit per PIC: from `master_pics.token` (count) and `.nominal` (rupiah);
  re-checked on submit. Count includes statuses `PAID…SETTLE_APPROVED`;
  nominal counts until CLOSED/CANCELLED.
- Item takeout/restore during approval reduces processed amount; all items out
  → REJECTED (per project notes; see `takeoutDetail`).
- Rejection → REJECTED; applicant (manager/supervisor) chooses Revise
  (back to DRAFT) or Cancel (CANCELLED).
- Refund = `amount − realization`, only for CA and only once settled
  (`SETTLED_STATUSES`); never negative.
- Expense Claim = `realization − amount` (> 0) → extra approvals + Second Payment.
- Cost value on `main`: `realizedCostValue()` = realization total only when the
  CR is settled (`isSettled()`) and has realizations; otherwise `amount`.
  (Branch `feature/effective-amount` — unreleased — changes this to "CA with
  realization > 0 uses realization" and applies it to NOB and budget usage.
  Needs Finance approval before it becomes a rule.)
- NOB flag (`is_nob`, `nob_amount`) set by budget check (`checkNob`).

### Budget Plan
- Statuses: DRAFT, SUBMITTED, CHECKED, VERIFIED, ACTIVE, CLOSING, CLOSED, REJECTED.
- Usage/remaining computed by `App\Support\BudgetConsumption` (+ `scopeDisbursed`).
- Plan Finish dan auto-closing berbasis tanggal DIHAPUS (keputusan 22 Okt 2026):
  command `budget-plan:auto-closing` dan transisi otomatis "PO PAID → BP ACTIVE
  menjadi CLOSING" tidak ada lagi; form BP maupun form PO tidak meminta Plan Finish.
  Kolom `budget_plans.plan_finish` dibiarkan (nullable, data lama tidak dimigrasi).
  Satu-satunya pemicu otomatis status BP: saat PO ditutup
  (`ProjectController::closeBudgetPlans()` — dipanggil dari `close`, `financeClose`,
  `verifyClosing`) BP milik PO berstatus ACTIVE/CLOSING menjadi CLOSED dengan log
  `closed_with_po`. BP non-PO (Cost Center/Overhead/Payroll) hanya ditutup manual
  (`submit-closing` manager → `verify-closing` Finance); closing manual BP bundled
  tetap diizinkan.
- **Budget Plan bundled** (`budget_plans.via_po_approval = 1`, keputusan 22 Okt 2026):
  Head/superadmin (`AccessRoles::PO_BUDGET_BUNDLE`) boleh mengisi SATU Budget Plan
  (opsional) langsung di form PO. Statusnya ikut approval PO (submit → CHECKED,
  verify → VERIFIED, approve → ACTIVE termasuk Early Start, finance-revise → DRAFT).
  Detail BP hanya bisa diubah selagi DRAFT lewat Edit PO; PO EARLY_START yang
  diedit kembali ke DRAFT TIDAK membuka BP yang sudah ACTIVE. BP tambahan tetap
  dibuat lewat `/budget-plan/create` (workflow lama). Cost Request hanya memakai
  BP ACTIVE (`BudgetRequestController`), sehingga BP bundled baru bisa dipakai
  setelah PO di-approve.

### Treasury batch
- Statuses: PENDING_APPROVER → DONE (approver) → PAID (treasury mark-paid); REJECTED.
- Mark paid requires ≥1 attachment; per item: CA CR QUEUED→PAID; non-CA
  QUEUED→CLOSED; Cost Register QUEUED→PAID; Second Payment PENDING→PAID and
  parent CR QUEUED→CLOSED.

### Invoice
- Statuses: DRAFT, SUBMITTED, VERIFIED, APPROVED, SENT, RECEIVED,
  PARTIALLY_PAID, PAID, CANCELLED (+ display OVERDUE).
- APPROVED→SENT happens when the invoice is put on a Delivery Note.
- Collection "Outstanding" = RECEIVED/PARTIALLY_PAID + overdue (SENT not yet
  due excluded) — per Finance Summary implementation.

### Purchase Order (Project)
- Statuses: DRAFT, SUBMITTED, WAITING_PO, ACTIVE, PROGRESS_DONE, PAID,
  CLOSING, CLOSED; several auto-transitions via `Project::syncStatusFromPoNumbers()`.
- PO money visibility restricted (`AccessRoles::PO_VALUE`, `PO_MONEY_COLUMNS`).

### Numbering
- `DocumentSequence` counters never go backwards; gaps are expected.

## Discrepancies (reported, not fixed)

1. `BudgetRequest::STATUSES` comment says SETTLE_APPROVED / SECOND_APPROVED
   "transitions not yet installed", but `getAllowedNextStatuses()` and
   `settleApprove()`/`secondApprove()` implement them.
2. `DailyTransactionController::markPaid()` comment says the parent CR of a
   Second Payment "stays SETTLE_VERIFIED", but the code closes it when it is
   QUEUED; model flow is SECOND_APPROVED → QUEUED → CLOSED. Both comments in
   `approve()` and `markPaid()` reflect an older design.
3. Refund comment block says settlement stages are "SETTLE_CHECKED →
   SETTLE_VERIFIED → CLOSED"; `SETTLED_STATUSES` also includes SETTLE_APPROVED
   and SECOND_APPROVED.
4. ~~Invoice VERIFIED shown as "awaiting CEO"~~ — text fixed on `develop`
   (now "Menunggu persetujuan Finance Manager"). See `docs/security.md`.
5. Route `ceo-close-settlement` is gated to finance_manager.
6. Project memory notes say non-CA goes QUEUED→CLOSED "on mark-paid" — matches code.

## Needs confirmation

- Petty cash and reimbursement processes (no modules found).
- Whether self-approval should be blocked.
- Whether a CLOSED period must block edits in other modules.
- Exact meaning of PO "Purchase" vs procurement (no procurement module found).
- Production role assignments.
- Cash Advance count limit (`master_pics.token`) only counts CRs in
  `CA_COUNTED_STATUSES` (PAID → SETTLE_APPROVED), so a PIC can have more CAs
  submitted/in approval than the limit; once all are paid the count exceeds
  it. The code comment says this is intentional ("dihitung mulai CR mencapai
  tahap PAID"). Confirm with Finance.

## Cash Receipt — sisa tagihan bruto & sinkron PPh (feature/cash-receipt-tagihan-pph)

- **Sisa bruto** (`Invoice::sisaBruto()`, `hitungSisaBruto()`) = `billed_amount + tax_amount − Σ cash_receipts.amount − Σ cash_receipts.withholding_tax` (receipt aktif). Batas input di `CashReceiptController::store/allocate` dan batas settle PPh (`sisaBruto(kecuali receipt itu) − amount`). Penerimaan di halaman invoice (`storeCashReceipt`, hanya nominal) tetap memakai `remainingBalance()`.
- **PPh receipt → PPh invoice** (`Invoice::sinkronPphDariCashReceipt()`): target = Σ PPh receipt aktif. Target > 0 → `invoices.withholding_tax` diganti target; target 0 → PPh invoice jadi 0 hanya bila receipt ber-PPh dibalik (hapus/batal/settle ke 0) atau receipt melunasi sisa bruto dengan PPh 0; selain itu PPh di muka dipertahankan. `captured_total` dihitung ulang lewat `InvoiceCalculationService::computeFor` (hanya `withholding_tax` dan `captured_total` ditulis). Ditolak (DomainException, transaksi batal) bila `billed_amount`/`tax_amount` tersimpan berbeda dari hasil service. **Pembalikan hanya bila PPh invoice hasil sinkron** (PPh invoice = ΣP receipt aktif sebelum pembalikan); bila tidak (mis. PPh di muka 2jt, receipt lama ber-PPh 1), PPh invoice dibiarkan. Pada pembalikan, invoice inkonsisten tidak memblokir hapus/batal: sinkron dilewati dan dicatat sebagai komentar "PPh invoice tidak disinkron ... periksa manual" (keputusan konservatif); pada jalur tambah/settle tetap ditolak. **Anti kelebihan bayar**: penerimaan yang membuat total diterima melebihi `captured_total` setelah sinkron ditolak (mis. PPh di muka 2jt, bruto 111jt: 110jt tanpa PPh ditolak; 109jt boleh; 111jt dengan PPh 0 melunasi bruto dan menolkan PPh). Jejak: komentar invoice + `UserActivityLog` (invoice/update).
- **Rezim sinkron**: `Invoice::hitungDiterima(ΣA, ΣP, PPh invoice)` = ΣA + (PPh invoice > 0 dan = ΣP ? 0 : ΣP). Satu-satunya rumus "diterima" (`totalReceived`, Closing, Summary Collection, Customer, export invoice). Data lama (PPh invoice 0 atau ≠ ΣP) tidak berubah.
- Data lama yang kebetulan PPh invoice = ΣP receipt > 0 berubah hasilnya; cek read-only: `SELECT i.id,i.code FROM invoices i WHERE i.withholding_tax>0 AND i.deleted_at IS NULL AND i.withholding_tax=(SELECT COALESCE(SUM(c.withholding_tax),0) FROM cash_receipts c WHERE c.invoice_id=i.id AND c.deleted_at IS NULL)`.
- **PPh otomatis di form Create (client-side saja)**: PPh = sisa − diterima hanya terisi bila ≤ 15% × DPP invoice (`billed_amount`; konstanta `BATAS_PPH_PERSEN`, perbandingan integer `PPh×100 ≤ 15×DPP`). Di atas itu kolom dibiarkan kosong dengan catatan merah kecil; PPh yang sudah diketik manual tidak ditimpa. DPP 0/kosong: tidak diisi otomatis (hanya hasil 0 yang wajar). Dialog konfirmasi simpan memberi peringatan merah untuk tiap invoice ber-PPh > 15% DPP (juga untuk input manual); tidak memblokir. **Server tidak membatasi persentase PPh** (keputusan pemilik; hanya batas sisa bruto).
- Pembacaan uang masuk (Σ amount: Cash Management, `Project::paymentTotal`, `paidDppByProject`) tidak diubah; penyebut `captured_total` bergeser mengikuti PPh yang disinkronkan.
- Closing: rekap Collection dibangun ulang dari data (bukan snapshot nilai); `drift()` hanya membandingkan kunci modul#id.
