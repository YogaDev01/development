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
- Auto-closing command `budget-plan:auto-closing` runs daily (rule details:
  see command source).

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
4. Invoice VERIFIED shown as "awaiting CEO" but only finance_manager (and
   superadmin) can approve. See `docs/security.md`.
5. Route `ceo-close-settlement` is gated to finance_manager.
6. Project memory notes say non-CA goes QUEUED→CLOSED "on mark-paid" — matches code.

## Needs confirmation

- Petty cash and reimbursement processes (no modules found).
- Whether self-approval should be blocked.
- Whether a CLOSED period must block edits in other modules.
- Exact meaning of PO "Purchase" vs procurement (no procurement module found).
- Production role assignments.
